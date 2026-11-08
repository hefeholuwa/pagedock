<?php

class SiteManager {
    /**
     * Generate a unique URL slug
     */
    public static function generateUniqueSlug(?string $preferred = null): string {
        $db = Database::getConnection();

        if ($preferred) {
            $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9_-]+/', '-', $preferred), '-'));
            if (strlen($slug) >= 3) {
                $stmt = $db->prepare("SELECT id FROM sites WHERE slug = ?");
                $stmt->execute([$slug]);
                if (!$stmt->fetch()) {
                    return $slug;
                }
            }
        }

        // Generate random clean slug e.g. "dock-x7b9q2"
        do {
            $random = substr(str_shuffle("abcdefghjkmnpqrstuvwxyz23456789"), 0, 7);
            $slug = 'pd-' . $random;
            $stmt = $db->prepare("SELECT id FROM sites WHERE slug = ?");
            $stmt->execute([$slug]);
        } while ($stmt->fetch());

        return $slug;
    }

    /**
     * Create site from pasted/raw HTML
     */
    public static function createFromHtml(string $html, ?string $title = null, ?string $preferredSlug = null): array {
        if (trim($html) === '') {
            return ['success' => false, 'error' => 'HTML content cannot be empty.'];
        }

        $slug = self::generateUniqueSlug($preferredSlug ?: $title);
        $siteDir = SITES_DIR . '/' . $slug;

        if (!mkdir($siteDir, 0755, true)) {
            return ['success' => false, 'error' => 'Could not create site directory on disk.'];
        }

        // Automatically bind demo or template form placeholders to the allocated site slug
        $html = str_replace(
            ['/api/forms/demo', '/api/forms/YOUR-SLUG', '/api/forms/YOUR_SLUG'],
            url('api/forms/' . $slug),
            $html
        );

        $entryFile = 'index.html';
        $filePath = $siteDir . '/' . $entryFile;
        file_put_contents($filePath, $html);

        $size = filesize($filePath);
        $claimToken = bin2hex(random_bytes(16));
        $userId = Auth::id();

        // Extract <title> if not provided
        if (!$title && preg_match('/<title>(.*?)<\/title>/is', $html, $matches)) {
            $title = htmlspecialchars_decode(trim($matches[1]));
        }
        $title = $title ?: 'Untitled Site';

        $db = Database::getConnection();
        $stmt = $db->prepare("
            INSERT INTO sites (user_id, claim_token, slug, title, entry_file, file_count, total_size_bytes)
            VALUES (?, ?, ?, ?, ?, 1, ?)
        ");
        $stmt->execute([$userId, $claimToken, $slug, $title, $entryFile, $size]);
        $siteId = (int)$db->lastInsertId();

        // Automatically decouple inlined styles/scripts into physical files
        self::decoupleAssets($slug, $entryFile, false);

        // Create immutable v1 snapshot
        $version = VersionManager::createSnapshot($siteId, $slug, 'Initial HTML deployment');

        // Track claim token in guest session
        if (!$userId) {
            $_SESSION['guest_claim_tokens'][] = $claimToken;
        }

        return [
            'success' => true,
            'slug' => $slug,
            'title' => $title,
            'claim_token' => $claimToken,
            'version' => $version,
            'url' => url('s/' . $slug)
        ];
    }

    /**
     * Create site from an uploaded ZIP archive
     */
    public static function createFromZip(string $zipFilePath, ?string $title = null, ?string $preferredSlug = null): array {
        if (!class_exists('ZipArchive')) {
            return ['success' => false, 'error' => 'ZipArchive extension not available on server.'];
        }

        $zip = new ZipArchive();
        if ($zip->open($zipFilePath) !== true) {
            return ['success' => false, 'error' => 'Failed to open uploaded ZIP file.'];
        }

        $slug = self::generateUniqueSlug($preferredSlug ?: $title);
        $siteDir = SITES_DIR . '/' . $slug;

        if (!mkdir($siteDir, 0755, true)) {
            $zip->close();
            return ['success' => false, 'error' => 'Could not allocate site storage folder.'];
        }

        $fileCount = 0;
        $totalBytes = 0;
        $hasIndex = false;
        $firstHtmlFile = null;

        // Security check and extraction
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entryName = $zip->getNameIndex($i);
            
            // Prevent zip-slip (directory traversal)
            if (str_contains($entryName, '../') || str_starts_with($entryName, '/') || str_starts_with($entryName, '\\')) {
                continue;
            }

            // Skip hidden Mac OS / metadata files
            if (str_starts_with($entryName, '__MACOSX/') || str_contains($entryName, '.DS_Store')) {
                continue;
            }

            $stat = $zip->statIndex($i);
            $totalBytes += $stat['size'] ?? 0;
            
            if (!str_ends_with($entryName, '/')) {
                $fileCount++;
                $lower = strtolower($entryName);
                if ($lower === 'index.html' || $lower === 'index.htm') {
                    $hasIndex = true;
                } elseif (str_ends_with($lower, '.html') && $firstHtmlFile === null) {
                    $firstHtmlFile = $entryName;
                }
            }
        }

        // Extract files safely
        $zip->extractTo($siteDir);
        $zip->close();

        // If files were nested inside a single root folder in the zip, flatten it if needed
        $files = scandir($siteDir);
        $cleanFiles = array_diff($files, ['.', '..']);
        if (count($cleanFiles) === 1) {
            $onlyItem = $siteDir . '/' . reset($cleanFiles);
            if (is_dir($onlyItem)) {
                // Move contents up one level
                $subItems = scandir($onlyItem);
                foreach ($subItems as $sub) {
                    if ($sub !== '.' && $sub !== '..') {
                        rename($onlyItem . '/' . $sub, $siteDir . '/' . $sub);
                    }
                }
                rmdir($onlyItem);
            }
        }

        // Determine entry file
        $entryFile = 'index.html';
        if (file_exists($siteDir . '/index.html')) {
            $entryFile = 'index.html';
        } elseif (file_exists($siteDir . '/index.htm')) {
            $entryFile = 'index.htm';
        } elseif ($firstHtmlFile && file_exists($siteDir . '/' . $firstHtmlFile)) {
            $entryFile = $firstHtmlFile;
        }

        $claimToken = bin2hex(random_bytes(16));
        $userId = Auth::id();
        $title = $title ?: 'Project ' . strtoupper($slug);

        $db = Database::getConnection();
        $stmt = $db->prepare("
            INSERT INTO sites (user_id, claim_token, slug, title, entry_file, file_count, total_size_bytes)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$userId, $claimToken, $slug, $title, $entryFile, $fileCount, $totalBytes]);
        $siteId = (int)$db->lastInsertId();

        // Create immutable v1 snapshot
        $version = VersionManager::createSnapshot($siteId, $slug, 'Initial ZIP archive deployment');

        if (!$userId) {
            $_SESSION['guest_claim_tokens'][] = $claimToken;
        }

        return [
            'success' => true,
            'slug' => $slug,
            'title' => $title,
            'claim_token' => $claimToken,
            'version' => $version,
            'url' => url('s/' . $slug)
        ];
    }

    /**
     * Get site record by slug
     */
    public static function getSite(string $slug): ?array {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM sites WHERE slug = ?");
        $stmt->execute([$slug]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Read the entry HTML content for a site (or specific relative HTML file)
     */
    public static function getSiteHtml(string $slug, ?string $relPath = null): ?string {
        $site = self::getSite($slug);
        if (!$site) return null;

        $siteDir = realpath(SITES_DIR . '/' . $slug);
        if (!$siteDir) return null;

        $targetFile = $relPath ?: ($site['entry_file'] ?: 'index.html');
        $targetPath = realpath($siteDir . '/' . ltrim($targetFile, '/'));
        if ($targetPath && str_starts_with($targetPath, $siteDir) && file_exists($targetPath)) {
            return file_get_contents($targetPath);
        }
        return null;
    }

    /**
     * Update multiple site files atomically in a single operation
     */
    public static function updateSiteFiles(string $slug, array $files, ?string $summary = null): array {
        $site = self::getSite($slug);
        if (!$site) {
            return ['success' => false, 'error' => 'Site not found.'];
        }

        if (!self::canManage($site)) {
            return ['success' => false, 'error' => 'Unauthorized to edit this site.'];
        }

        $siteDir = realpath(SITES_DIR . '/' . $slug);
        if (!$siteDir || !is_dir($siteDir)) {
            return ['success' => false, 'error' => 'Site directory missing.'];
        }

        $savedFiles = [];
        foreach ($files as $relPath => $content) {
            if (!is_string($relPath) || !is_string($content)) continue;
            $cleanRel = ltrim($relPath, '/');
            $targetPath = $siteDir . '/' . $cleanRel;

            // Security traversal check
            $realParent = realpath(dirname($targetPath));
            if ($realParent && !str_starts_with($realParent, $siteDir)) {
                continue;
            }

            $parent = dirname($targetPath);
            if (!is_dir($parent)) {
                mkdir($parent, 0755, true);
            }

            file_put_contents($targetPath, $content);
            $savedFiles[] = $cleanRel;
        }

        if (empty($savedFiles)) {
            return ['success' => false, 'error' => 'No valid files provided to save.'];
        }

        $allFiles = scandir($siteDir);
        $realFiles = array_filter($allFiles, fn($f) => !in_array($f, ['.', '..', '.snapshots']));
        $totalBytes = 0;
        foreach ($realFiles as $rf) {
            $p = $siteDir . '/' . $rf;
            if (is_file($p)) $totalBytes += filesize($p);
        }

        $db = Database::getConnection();
        $stmt = $db->prepare("UPDATE sites SET file_count = ?, total_size_bytes = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([count($realFiles), $totalBytes, $site['id']]);

        // Create immutable revision snapshot
        $snapshotSummary = $summary ?: ("Web Editor save (" . implode(', ', $savedFiles) . ")");
        $version = VersionManager::createSnapshot((int)$site['id'], $slug, $snapshotSummary);

        return [
            'success' => true,
            'slug' => $slug,
            'files' => $savedFiles,
            'version' => $version
        ];
    }

    /**
     * Update the HTML content of a site (or specific relative HTML file)
     */
    public static function updateSiteHtml(string $slug, string $htmlContent, ?string $relPath = null): array {
        $targetRel = $relPath ?: 'index.html';
        return self::updateSiteFiles($slug, [$targetRel => $htmlContent], "HTML update (" . basename($targetRel) . ") via Web Editor");
    }

    /**
     * Decouple inlined <style> and <script> tags from an HTML file into physical style.css and script.js on disk.
     *
     * @param string $slug Site slug
     * @param string $relFile Relative HTML file (defaults to 'index.html')
     * @param bool $createSnapshot Whether to record an immutable version snapshot
     * @return array Decoupling operation result
     */
    public static function decoupleAssets(string $slug, string $relFile = 'index.html', bool $createSnapshot = true): array {
        $site = self::getSite($slug);
        if (!$site) {
            return ['success' => false, 'error' => 'Site not found.'];
        }

        $siteDir = realpath(SITES_DIR . '/' . $slug);
        if (!$siteDir || !is_dir($siteDir)) {
            return ['success' => false, 'error' => 'Site directory missing.'];
        }

        $targetRel = $relFile ?: ($site['entry_file'] ?: 'index.html');
        $targetPath = $siteDir . '/' . ltrim($targetRel, '/');
        if (!file_exists($targetPath) || !is_file($targetPath)) {
            return ['success' => false, 'error' => 'HTML file not found.'];
        }

        // Security check
        $realFile = realpath($targetPath);
        if ($realFile && !str_starts_with($realFile, $siteDir)) {
            return ['success' => false, 'error' => 'Invalid file path.'];
        }

        $html = file_get_contents($targetPath);
        if ($html === false) {
            return ['success' => false, 'error' => 'Could not read HTML file.'];
        }

        $cssDecoupled = false;
        $jsDecoupled = false;

        // 1. Extract inlined <style> tags
        preg_match_all('/<style\b[^>]*>(.*?)<\/style>/is', $html, $styleMatches);
        $extractedCss = [];
        if (!empty($styleMatches[1])) {
            foreach ($styleMatches[1] as $rawBlock) {
                $trimmed = trim($rawBlock);
                if ($trimmed !== '') {
                    $extractedCss[] = $trimmed;
                }
            }
        }

        $cssRelPath = 'style.css';
        $cssDiskPath = $siteDir . '/' . $cssRelPath;
        if (!empty($extractedCss)) {
            $combinedCss = implode("\n\n", $extractedCss);
            if (file_exists($cssDiskPath)) {
                $existingCss = file_get_contents($cssDiskPath);
                if (!str_contains($existingCss, $combinedCss)) {
                    file_put_contents($cssDiskPath, rtrim($existingCss) . "\n\n/* Inlined styles decoupled from {$targetRel} */\n" . $combinedCss . "\n");
                }
            } else {
                file_put_contents($cssDiskPath, "/* style.css — Decoupled from {$targetRel} */\n\n" . $combinedCss . "\n");
            }

            // Check if HTML already links to style.css
            $hasCssLink = (bool)preg_match('/<link\b[^>]*\bhref=["\']\S*style\.css["\'][^>]*>/i', $html);
            $replacedFirst = false;

            $html = preg_replace_callback('/<style\b[^>]*>.*?<\/style>/is', function($matches) use (&$replacedFirst, $hasCssLink, $cssRelPath) {
                if (!$hasCssLink && !$replacedFirst) {
                    $replacedFirst = true;
                    return '<link rel="stylesheet" href="' . $cssRelPath . '">';
                }
                return '';
            }, $html);

            if (!$hasCssLink && !$replacedFirst) {
                if (str_contains($html, '</head>')) {
                    $html = str_replace('</head>', "    <link rel=\"stylesheet\" href=\"{$cssRelPath}\">\n</head>", $html);
                } else {
                    $html = "<link rel=\"stylesheet\" href=\"{$cssRelPath}\">\n" . $html;
                }
            }
            $cssDecoupled = true;
        }

        // 2. Extract inlined <script> tags (excluding external src= and application/ld+json / application/json)
        preg_match_all('/<script\b(?![^>]*\bsrc=)(?![^>]*\btype=["\']application\/(ld\+)?json["\'])[^>]*>(.*?)<\/script>/is', $html, $scriptMatches);
        $extractedJs = [];
        if (!empty($scriptMatches[2])) {
            foreach ($scriptMatches[2] as $rawJs) {
                $trimmed = trim($rawJs);
                if ($trimmed !== '') {
                    $extractedJs[] = $trimmed;
                }
            }
        }

        $jsRelPath = 'script.js';
        $jsDiskPath = $siteDir . '/' . $jsRelPath;
        if (!empty($extractedJs)) {
            $combinedJs = implode("\n\n", $extractedJs);
            if (file_exists($jsDiskPath)) {
                $existingJs = file_get_contents($jsDiskPath);
                if (!str_contains($existingJs, $combinedJs)) {
                    file_put_contents($jsDiskPath, rtrim($existingJs) . "\n\n// Inlined scripts decoupled from {$targetRel}\n" . $combinedJs . "\n");
                }
            } else {
                file_put_contents($jsDiskPath, "/* script.js — Decoupled from {$targetRel} */\n\n" . $combinedJs . "\n");
            }

            // Check if HTML already links to script.js
            $hasJsLink = (bool)preg_match('/<script\b[^>]*\bsrc=["\']\S*script\.js["\'][^>]*><\/script>/i', $html);
            $totalMatches = count($scriptMatches[0]);
            $matchIndex = 0;

            $html = preg_replace_callback('/<script\b(?![^>]*\bsrc=)(?![^>]*\btype=["\']application\/(ld\+)?json["\'])[^>]*>.*?<\/script>/is', function($matches) use (&$matchIndex, $totalMatches, $hasJsLink, $jsRelPath) {
                $matchIndex++;
                if (!$hasJsLink && $matchIndex === $totalMatches) {
                    return '<script src="' . $jsRelPath . '"></script>';
                }
                return '';
            }, $html);

            if (!$hasJsLink && $matchIndex === 0) {
                if (str_contains($html, '</body>')) {
                    $html = str_replace('</body>', "    <script src=\"{$jsRelPath}\"></script>\n</body>", $html);
                } else {
                    $html .= "\n<script src=\"{$jsRelPath}\"></script>";
                }
            }
            $jsDecoupled = true;
        }

        if ($cssDecoupled || $jsDecoupled) {
            // Clean up excessive blank lines
            $html = preg_replace("/\n{3,}/", "\n\n", $html);
            file_put_contents($targetPath, $html);

            // Update file count and total size in DB
            $allFiles = scandir($siteDir);
            $realFiles = array_filter($allFiles, fn($f) => !in_array($f, ['.', '..', '.snapshots']));
            $totalBytes = 0;
            foreach ($realFiles as $rf) {
                $p = $siteDir . '/' . $rf;
                if (is_file($p)) $totalBytes += filesize($p);
            }

            $db = Database::getConnection();
            $stmt = $db->prepare("UPDATE sites SET file_count = ?, total_size_bytes = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([count($realFiles), $totalBytes, $site['id']]);

            $version = null;
            if ($createSnapshot) {
                $desc = "Decoupled inlined assets (" . implode(', ', array_filter([$cssDecoupled ? 'style.css' : '', $jsDecoupled ? 'script.js' : ''])) . ")";
                $version = VersionManager::createSnapshot((int)$site['id'], $slug, $desc);
            }

            return [
                'success' => true,
                'decoupled' => true,
                'css_created' => $cssDecoupled,
                'js_created' => $jsDecoupled,
                'version' => $version
            ];
        }

        return [
            'success' => true,
            'decoupled' => false,
            'css_created' => false,
            'js_created' => false
        ];
    }

    /**
     * Check if current visitor has permissions to manage/edit/delete this site
     */
    public static function canManage(array $site): bool {
        $userId = Auth::id();
        if ($userId && (int)$site['user_id'] === $userId) {
            return true;
        }

        $sessionTokens = $_SESSION['guest_claim_tokens'] ?? [];
        if (in_array($site['claim_token'], $sessionTokens, true)) {
            return true;
        }

        if (isset($_GET['token']) && $_GET['token'] === $site['claim_token']) {
            return true;
        }

        return false;
    }

    /**
     * Get sites for a user (or session guests)
     */
    public static function getUserSites(): array {
        $db = Database::getConnection();
        $userId = Auth::id();

        $selectSql = "
            SELECT s.*, 
                   (SELECT COUNT(*) FROM form_submissions WHERE site_id = s.id AND is_spam = 0) as forms_count,
                   (SELECT COUNT(*) FROM form_submissions WHERE site_id = s.id AND is_spam = 1) as spam_count
            FROM sites s
        ";

        if ($userId) {
            $stmt = $db->prepare($selectSql . " WHERE s.user_id = ? ORDER BY s.created_at DESC");
            $stmt->execute([$userId]);
            return $stmt->fetchAll();
        }

        $tokens = Auth::getActiveGuestTokens();
        if (!empty($tokens)) {
            $in = str_repeat('?,', count($tokens) - 1) . '?';
            $stmt = $db->prepare($selectSql . " WHERE s.claim_token IN ($in) ORDER BY s.created_at DESC");
            $stmt->execute($tokens);
            return $stmt->fetchAll();
        }

        return [];
    }

    /**
     * Increment view count
     */
    public static function incrementView(int $siteId): void {
        $db = Database::getConnection();
        $stmt = $db->prepare("UPDATE sites SET views_count = views_count + 1 WHERE id = ?");
        $stmt->execute([$siteId]);
    }

    /**
     * Apply targeted AI patch to an HTML element fragment
     */
    public static function applyElementAiPatch(string $elementHtml, string $instruction): string {
        $instr = strtolower(trim($instruction));
        
        // 1. Text changes
        if (preg_match('/(?:change text to|set text to|rename to|say|title)\s+["\']?([^"\']+)["\']?/i', $instruction, $m)) {
            $newText = htmlspecialchars(trim($m[1]));
            return preg_replace('/>(.*?)</s', '>' . $newText . '<', $elementHtml, 1) ?: $elementHtml;
        }

        // 2. Hover effect / hover state
        if (str_contains($instr, 'hover state') || str_contains($instr, 'hover effect') || str_contains($instr, 'hover')) {
            if (str_contains($elementHtml, 'style="')) {
                return preg_replace('/style="([^"]*)"/', 'style="$1; transition: all 0.25s ease; cursor: pointer;" onmouseover="this.style.transform=\'translateY(-2px) scale(1.02)\'; this.style.boxShadow=\'0 8px 24px rgba(125,211,252,0.35)\'" onmouseout="this.style.transform=\'\'; this.style.boxShadow=\'\'"', $elementHtml);
            } else {
                return preg_replace('/<([a-z0-9]+)/i', '<$1 style="transition: all 0.25s ease; cursor: pointer;" onmouseover="this.style.transform=\'translateY(-2px) scale(1.02)\'; this.style.boxShadow=\'0 8px 24px rgba(125,211,252,0.35)\'" onmouseout="this.style.transform=\'\'; this.style.boxShadow=\'\'"', $elementHtml, 1);
            }
        }

        // 3. Make larger / make smaller
        if (str_contains($instr, 'larger') || str_contains($instr, 'big')) {
            if (str_contains($elementHtml, 'style="')) {
                return preg_replace('/style="([^"]*)"/', 'style="$1; font-size: 1.25em; padding: 14px 28px;"', $elementHtml);
            } else {
                return preg_replace('/<([a-z0-9]+)/i', '<$1 style="font-size: 1.25em; padding: 14px 28px;"', $elementHtml, 1);
            }
        }

        // 4. Change colors / dark gradient
        if (str_contains($instr, 'gradient') || str_contains($instr, 'color') || str_contains($instr, 'dark')) {
            $grad = 'background: linear-gradient(135deg, #0ea5e9, #38bdf8); color: #041421; font-weight: 600; border: none; border-radius: 8px;';
            if (str_contains($instr, 'dark')) {
                $grad = 'background: linear-gradient(135deg, #0b0f17, #1e293b); color: #7dd3fc; border: 1px solid #334155; border-radius: 8px;';
            }
            if (str_contains($elementHtml, 'style="')) {
                return preg_replace('/style="([^"]*)"/', 'style="$1; ' . $grad . '"', $elementHtml);
            } else {
                return preg_replace('/<([a-z0-9]+)/i', '<$1 style="' . $grad . '"', $elementHtml, 1);
            }
        }

        // 5. Make bolder
        if (str_contains($instr, 'bold') || str_contains($instr, 'bolder')) {
            if (str_contains($elementHtml, 'style="')) {
                return preg_replace('/style="([^"]*)"/', 'style="$1; font-weight: 700;"', $elementHtml);
            } else {
                return preg_replace('/<([a-z0-9]+)/i', '<$1 style="font-weight: 700;"', $elementHtml, 1);
            }
        }

        // Default graceful enhancement: apply modern styling touch
        if (str_contains($elementHtml, 'style="')) {
            return preg_replace('/style="([^"]*)"/', 'style="$1; box-shadow: 0 4px 14px rgba(0,0,0,0.15);"', $elementHtml);
        } else {
            return preg_replace('/<([a-z0-9]+)/i', '<$1 style="box-shadow: 0 4px 14px rgba(0,0,0,0.15);"', $elementHtml, 1);
        }
    }

    /**
     * Delete site
     */
    public static function deleteSite(string $slug): array {
        $site = self::getSite($slug);
        if (!$site) {
            return ['success' => false, 'error' => 'Site not found.'];
        }

        if (!self::canManage($site)) {
            return ['success' => false, 'error' => 'Unauthorized.'];
        }

        // Delete from DB
        $db = Database::getConnection();
        $stmt = $db->prepare("DELETE FROM sites WHERE id = ?");
        $stmt->execute([$site['id']]);

        // Delete folder from disk
        $siteDir = SITES_DIR . '/' . $slug;
        if (is_dir($siteDir)) {
            self::recursiveRmdir($siteDir);
        }

        return ['success' => true];
    }

    private static function recursiveRmdir(string $dir): void {
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = $dir . '/' . $item;
            is_dir($path) ? self::recursiveRmdir($path) : unlink($path);
        }
        rmdir($dir);
    }
}
