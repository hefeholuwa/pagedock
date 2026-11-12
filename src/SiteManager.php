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
        $claimToken = 'pd_claim_' . bin2hex(random_bytes(16));
        $userId = Auth::id();
        $expiresAt = $userId ? null : date('Y-m-d H:i:s', time() + (48 * 3600));

        // Extract <title> if not provided
        if (!$title && preg_match('/<title>(.*?)<\/title>/is', $html, $matches)) {
            $title = htmlspecialchars_decode(trim($matches[1]));
        }
        $title = $title ?: 'Untitled Site';

        $db = Database::getConnection();
        $stmt = $db->prepare("
            INSERT INTO sites (user_id, claim_token, slug, title, entry_file, file_count, total_size_bytes, expires_at)
            VALUES (?, ?, ?, ?, ?, 1, ?, ?)
        ");
        $stmt->execute([$userId, $claimToken, $slug, $title, $entryFile, $size, $expiresAt]);
        $siteId = (int)$db->lastInsertId();

        // Automatically decouple inlined styles/scripts into physical files
        self::decoupleAssets($slug, $entryFile, false);

        // Create immutable v1 snapshot
        $version = VersionManager::createSnapshot($siteId, $slug, 'Initial HTML deployment');

        // Track claim token in guest session
        if (!$userId) {
            $_SESSION['guest_claim_tokens'][] = $claimToken;
        }

        // Synchronize site files and SQLite database to Cloudflare R2
        if (class_exists('R2Storage') && R2Storage::isConfigured()) {
            R2Storage::syncDirectoryToR2($siteDir, 'sites/' . $slug);
            Database::syncDatabaseToR2();
        }

        return [
            'success' => true,
            'slug' => $slug,
            'title' => $title,
            'claim_token' => $claimToken,
            'version' => $version,
            'file_count' => 1,
            'url' => url('s/' . $slug)
        ];
    }

    /**
     * Scan directory file count and byte size recursively
     */
    public static function scanDirStats(string $dir): array {
        $count = 0;
        $bytes = 0;
        if (!is_dir($dir)) return ['count' => 0, 'bytes' => 0];
        $iter = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($iter as $file) {
            if ($file->isFile()) {
                $count++;
                $bytes += $file->getSize();
            }
        }
        return ['count' => $count, 'bytes' => $bytes];
    }

    /**
     * Deploy or update a site from a ZIP archive
     * Surge / Redoost pattern: updates if authorized, rejects if slug claimed, creates if new
     */
    public static function deployFromZip(string $zipFilePath, ?string $title = null, ?string $preferredSlug = null): array {
        if ($preferredSlug) {
            $existing = self::getSite($preferredSlug);
            if ($existing) {
                if (!self::canManage($existing)) {
                    return [
                        'success' => false,
                        'error' => "Slug '{$preferredSlug}' is already claimed. Provide valid X-Claim-Token or choose another slug.",
                        'status_code' => 403
                    ];
                }
                $siteDir = SITES_DIR . '/' . $existing['slug'];
                $zip = new ZipArchive();
                if ($zip->open($zipFilePath) !== true) {
                    return ['success' => false, 'error' => 'Failed to open uploaded ZIP file.', 'status_code' => 400];
                }
                $zip->extractTo($siteDir);
                $zip->close();

                self::normalizeExtractedSite($siteDir);
                $stats = self::scanDirStats($siteDir);
                $version = VersionManager::createSnapshot((int)$existing['id'], $existing['slug'], 'CLI/cURL ZIP deployment update');

                $db = Database::getConnection();
                $stmt = $db->prepare("UPDATE sites SET file_count = ?, total_size_bytes = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$stats['count'], $stats['bytes'], $existing['id']]);

                return [
                    'success' => true,
                    'slug' => $existing['slug'],
                    'title' => $existing['title'],
                    'claim_token' => $existing['claim_token'],
                    'version' => $version,
                    'file_count' => $stats['count'],
                    'url' => url('s/' . $existing['slug']),
                    'updated' => true
                ];
            }
        }
        return self::createFromZip($zipFilePath, $title, $preferredSlug);
    }

    /**
     * Deploy or update a site from raw HTML
     */
    public static function deployFromHtml(string $html, ?string $title = null, ?string $preferredSlug = null): array {
        if ($preferredSlug) {
            $existing = self::getSite($preferredSlug);
            if ($existing) {
                if (!self::canManage($existing)) {
                    return [
                        'success' => false,
                        'error' => "Slug '{$preferredSlug}' is already claimed. Provide valid X-Claim-Token or choose another slug.",
                        'status_code' => 403
                    ];
                }
                $siteDir = SITES_DIR . '/' . $existing['slug'];
                $entryFile = $existing['entry_file'] ?: 'index.html';
                file_put_contents($siteDir . '/' . $entryFile, $html);

                $stats = self::scanDirStats($siteDir);
                $version = VersionManager::createSnapshot((int)$existing['id'], $existing['slug'], 'CLI/cURL HTML deployment update');

                $db = Database::getConnection();
                $stmt = $db->prepare("UPDATE sites SET file_count = ?, total_size_bytes = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$stats['count'], $stats['bytes'], $existing['id']]);

                return [
                    'success' => true,
                    'slug' => $existing['slug'],
                    'title' => $existing['title'],
                    'claim_token' => $existing['claim_token'],
                    'version' => $version,
                    'file_count' => $stats['count'],
                    'url' => url('s/' . $existing['slug']),
                    'updated' => true
                ];
            }
        }
        return self::createFromHtml($html, $title, $preferredSlug);
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

        // Normalize extracted site: purge OS junk, unwrap enclosing folder or build artifacts
        self::normalizeExtractedSite($siteDir);
        $stats = self::scanDirStats($siteDir);
        $fileCount = $stats['count'];
        $totalBytes = $stats['bytes'];

        // Determine entry file
        $entryFile = 'index.html';
        if (file_exists($siteDir . '/index.html')) {
            $entryFile = 'index.html';
        } elseif (file_exists($siteDir . '/index.htm')) {
            $entryFile = 'index.htm';
        } else {
            $foundHtml = self::findFirstHtmlFile($siteDir);
            if ($foundHtml) {
                $entryFile = $foundHtml;
            } elseif ($firstHtmlFile && file_exists($siteDir . '/' . $firstHtmlFile)) {
                $entryFile = $firstHtmlFile;
            }
        }

        $claimToken = 'pd_claim_' . bin2hex(random_bytes(16));
        $userId = Auth::id();
        $expiresAt = $userId ? null : date('Y-m-d H:i:s', time() + (48 * 3600));
        $title = $title ?: 'Project ' . strtoupper($slug);

        $db = Database::getConnection();
        $stmt = $db->prepare("
            INSERT INTO sites (user_id, claim_token, slug, title, entry_file, file_count, total_size_bytes, expires_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$userId, $claimToken, $slug, $title, $entryFile, $fileCount, $totalBytes, $expiresAt]);
        $siteId = (int)$db->lastInsertId();

        // Create immutable v1 snapshot
        $version = VersionManager::createSnapshot($siteId, $slug, 'Initial ZIP archive deployment');

        if (!$userId) {
            $_SESSION['guest_claim_tokens'][] = $claimToken;
        }

        // Synchronize site files and SQLite database to Cloudflare R2
        if (class_exists('R2Storage') && R2Storage::isConfigured()) {
            R2Storage::syncDirectoryToR2($siteDir, 'sites/' . $slug);
            Database::syncDatabaseToR2();
        }

        return [
            'success' => true,
            'slug' => $slug,
            'title' => $title,
            'claim_token' => $claimToken,
            'version' => $version,
            'file_count' => $fileCount,
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
        if (!$siteDir || !is_dir($siteDir)) {
            if (class_exists('R2Storage') && R2Storage::isConfigured()) {
                $targetDir = SITES_DIR . '/' . $slug;
                R2Storage::syncR2ToDirectory('sites/' . $slug, $targetDir);
                $siteDir = realpath($targetDir);
            }
        }
        if (!$siteDir) return null;

        $targetFile = $relPath ?: ($site['entry_file'] ?: 'index.html');
        $targetPath = realpath($siteDir . '/' . ltrim($targetFile, '/'));
        if ($targetPath && str_starts_with($targetPath, $siteDir) && file_exists($targetPath)) {
            return file_get_contents($targetPath);
        }
        return null;
    }

    /**
     * Get list of all relative file paths in a site
     */
    public static function getSiteFileList(string $slug): array {
        $site = self::getSite($slug);
        if (!$site) return [];
        $siteDir = realpath(SITES_DIR . '/' . $slug);
        if (!$siteDir || !is_dir($siteDir)) return [];

        $files = [];
        $iter = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($siteDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($iter as $file) {
            if ($file->isFile()) {
                $rel = str_replace('\\', '/', substr($file->getPathname(), strlen($siteDir) + 1));
                if (!str_starts_with($rel, '.') && !str_starts_with($rel, '__MACOSX')) {
                    $files[] = $rel;
                }
            }
        }
        sort($files);
        // Keep index.html at top if present
        if (($idx = array_search('index.html', $files)) !== false && $idx > 0) {
            array_splice($files, $idx, 1);
            array_unshift($files, 'index.html');
        }
        return $files;
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

        // Synchronize updated site files and SQLite database to Cloudflare R2
        if (class_exists('R2Storage') && R2Storage::isConfigured()) {
            R2Storage::syncDirectoryToR2($siteDir, 'sites/' . $slug);
            Database::syncDatabaseToR2();
        }

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
     * Create a new page with smart scaffolding, layout inheritance, or cloning
     */
    public static function createPage(string $slug, string $filename, string $title, string $layout = 'inherit', string $sourceFile = 'index.html'): array {
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

        // Clean & sanitize filename
        $cleanName = basename(trim($filename));
        $cleanName = preg_replace('/\.html?$/i', '', $cleanName);
        $cleanName = preg_replace('/[^a-zA-Z0-9_-]/', '-', strtolower($cleanName));
        $cleanName = trim($cleanName, '-');
        if (empty($cleanName)) {
            $cleanName = 'page-' . time();
        }
        $targetFile = $cleanName . '.html';
        $targetPath = $siteDir . '/' . $targetFile;

        if (file_exists($targetPath)) {
            return ['success' => false, 'error' => 'Page already exists: ' . $targetFile];
        }

        $title = trim($title) ?: ucfirst(str_replace(['-', '_'], ' ', $cleanName));
        $siteTitle = $site['title'] ?: 'PageDock Site';
        $pageTitle = $title . ' — ' . $siteTitle;

        $pageContent = '';

        if ($layout === 'clone') {
            $sourceContent = self::getSiteHtml($slug, $sourceFile) ?: self::getSiteHtml($slug, 'index.html');
            if (!$sourceContent) {
                return ['success' => false, 'error' => 'Source page to clone not found.'];
            }
            if (preg_match('/<title\b[^>]*>(.*?)<\/title>/is', $sourceContent)) {
                $pageContent = preg_replace('/<title\b[^>]*>(.*?)<\/title>/is', '<title>' . htmlspecialchars($pageTitle) . '</title>', $sourceContent);
            } else {
                $pageContent = $sourceContent;
            }
        } elseif ($layout === 'blank') {
            $hasCss = file_exists($siteDir . '/style.css');
            $hasJs = file_exists($siteDir . '/script.js');
            $cssLink = $hasCss ? "  <link rel=\"stylesheet\" href=\"style.css\">\n" : '';
            $jsScript = $hasJs ? "  <script defer src=\"script.js\"></script>\n" : '';
            $pageContent = "<!DOCTYPE html>\n<html lang=\"en\">\n<head>\n  <meta charset=\"UTF-8\">\n  <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n  <title>" . htmlspecialchars($pageTitle) . "</title>\n{$cssLink}{$jsScript}</head>\n<body>\n  <main class=\"container\" id=\"main\">\n    <section class=\"py-16\">\n      <h1>" . htmlspecialchars($title) . "</h1>\n      <p>Welcome to the " . htmlspecialchars($title) . " page.</p>\n    </section>\n  </main>\n</body>\n</html>";
        } else {
            // Default: 'inherit' layout from sourceFile or index.html
            $sourceContent = self::getSiteHtml($slug, $sourceFile) ?: self::getSiteHtml($slug, 'index.html');
            if (!$sourceContent) {
                return self::createPage($slug, $filename, $title, 'blank', $sourceFile);
            }

            // Extract <head>
            $headContent = '';
            if (preg_match('/<head\b[^>]*>([\s\S]*?)<\/head>/i', $sourceContent, $headMatch)) {
                $headInner = $headMatch[1];
                if (preg_match('/<title\b[^>]*>(.*?)<\/title>/is', $headInner)) {
                    $headInner = preg_replace('/<title\b[^>]*>(.*?)<\/title>/is', '<title>' . htmlspecialchars($pageTitle) . '</title>', $headInner);
                } else {
                    $headInner = "  <title>" . htmlspecialchars($pageTitle) . "</title>\n" . $headInner;
                }
                $headContent = $headInner;
            } else {
                $headContent = "  <meta charset=\"UTF-8\">\n  <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n  <title>" . htmlspecialchars($pageTitle) . "</title>";
            }

            // Extract body attributes
            $bodyAttrs = '';
            if (preg_match('/<body\b([^>]*)>/i', $sourceContent, $bodyMatch)) {
                $bodyAttrs = trim($bodyMatch[1]);
            }

            // Extract shared header block
            $headerBlock = '';
            if (preg_match('/(<!--\s*(?:pd|hh):shared\s+header(?:\s+[^>]*)?\s*-->[\s\S]*?<!--\s*\/(?:pd|hh):shared\s+header\s*-->)/i', $sourceContent, $hMatch)) {
                $rawHeader = $hMatch[1];
                $newRoute = '/' . $cleanName;
                $headerBlock = SharedLayoutManager::tailorBlockForPage($rawHeader, $newRoute, $targetFile, 'active', 'true');
            } elseif (preg_match('/(<header\b[^>]*>[\s\S]*?<\/header>)/i', $sourceContent, $hMatch)) {
                $headerBlock = "<!-- hh:shared header -->\n" . $hMatch[1] . "\n<!-- /hh:shared header -->";
            }

            // Extract shared footer block
            $footerBlock = '';
            if (preg_match('/(<!--\s*(?:pd|hh):shared\s+footer(?:\s+[^>]*)?\s*-->[\s\S]*?<!--\s*\/(?:pd|hh):shared\s+footer\s*-->)/i', $sourceContent, $fMatch)) {
                $footerBlock = $fMatch[1];
            } elseif (preg_match('/(<footer\b[^>]*>[\s\S]*?<\/footer>)/i', $sourceContent, $fMatch)) {
                $footerBlock = "<!-- hh:shared footer -->\n" . $fMatch[1] . "\n<!-- /hh:shared footer -->";
            }

            $bodyOpen = $bodyAttrs ? "<body {$bodyAttrs}>" : "<body>";

            $mainContent = "  <main id=\"main\" class=\"container\">\n    <section class=\"py-16\">\n      <h1>" . htmlspecialchars($title) . "</h1>\n      <p>Welcome to the " . htmlspecialchars($title) . " page.</p>\n    </section>\n  </main>";

            $pageContent = "<!DOCTYPE html>\n<html lang=\"en\">\n<head>\n{$headContent}\n</head>\n{$bodyOpen}\n";
            if ($headerBlock) {
                $pageContent .= $headerBlock . "\n";
            }
            $pageContent .= $mainContent . "\n";
            if ($footerBlock) {
                $pageContent .= $footerBlock . "\n";
            }
            $pageContent .= "</body>\n</html>";
        }

        $res = self::updateSiteFiles($slug, [$targetFile => $pageContent], "Created page {$targetFile} ({$title})");
        if (!$res['success']) {
            return $res;
        }

        return [
            'success' => true,
            'file' => $targetFile,
            'relPath' => $targetFile,
            'title' => $title,
            'url' => url('s/' . $slug . '/' . $cleanName)
        ];
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

        $token = $_SERVER['HTTP_X_CLAIM_TOKEN'] ?? $_POST['claim_token'] ?? $_GET['token'] ?? null;
        if ($token && hash_equals($site['claim_token'], (string)$token)) {
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

        // Delete from Cloudflare R2
        if (class_exists('R2Storage') && R2Storage::isConfigured()) {
            R2Storage::deletePrefix('sites/' . $slug);
            Database::syncDatabaseToR2();
        }

        return ['success' => true];
    }

    /**
     * Claim an anonymous/guest site to a registered user account
     * Clears expires_at and assigns user_id
     */
    public static function claimSite(string $claimToken, int $userId): array {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM sites WHERE claim_token = ?");
        $stmt->execute([$claimToken]);
        $site = $stmt->fetch();
        if (!$site) {
            return ['success' => false, 'error' => 'Invalid or expired claim token.'];
        }
        if ($site['user_id'] && (int)$site['user_id'] !== $userId) {
            return ['success' => false, 'error' => 'This site has already been claimed by another account.'];
        }

        $stmt = $db->prepare("UPDATE sites SET user_id = ?, expires_at = NULL, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$userId, $site['id']]);

        return [
            'success' => true,
            'slug' => $site['slug'],
            'title' => $site['title'],
            'claimed' => true
        ];
    }

    /**
     * Claim all guest sites in session when a user signs in or registers
     */
    public static function claimSessionSites(int $userId): int {
        $tokens = Auth::getActiveGuestTokens();
        if (empty($tokens)) return 0;

        $db = Database::getConnection();
        $claimed = 0;
        foreach (array_unique($tokens) as $token) {
            $stmt = $db->prepare("UPDATE sites SET user_id = ?, expires_at = NULL, updated_at = CURRENT_TIMESTAMP WHERE claim_token = ? AND user_id IS NULL");
            $stmt->execute([$userId, $token]);
            if ($stmt->rowCount() > 0) {
                $claimed++;
            }
        }
        $_SESSION['guest_claim_tokens'] = [];
        return $claimed;
    }

    /**
     * Auto-prune expired guest sites
     */
    public static function cleanupExpiredSites(): int {
        $db = Database::getConnection();
        $stmt = $db->query("SELECT id, slug FROM sites WHERE expires_at IS NOT NULL AND expires_at < CURRENT_TIMESTAMP");
        $expired = $stmt ? $stmt->fetchAll() : [];
        $deleted = 0;
        foreach ($expired as $site) {
            $siteDir = SITES_DIR . '/' . $site['slug'];
            $delStmt = $db->prepare("DELETE FROM sites WHERE id = ?");
            $delStmt->execute([$site['id']]);
            if (is_dir($siteDir)) {
                self::recursiveRmdir($siteDir);
            }
            $deleted++;
        }
        return $deleted;
    }

    /**
     * Clean OS metadata artifacts and unwrap nested root directories
     */
    public static function normalizeExtractedSite(string $siteDir): void {
        if (!is_dir($siteDir)) return;

        // 1. Remove __MACOSX, .DS_Store, Thumbs.db recursively
        self::purgeOsJunk($siteDir);

        // 2. Loop to unwrap any single enclosing root directory (e.g. site/ or dist/ or build/)
        for ($depth = 0; $depth < 5; $depth++) {
            $items = scandir($siteDir);
            $cleanItems = array_values(array_diff($items, ['.', '..']));

            // If there's only 1 item in the site directory and it is a directory, move its contents up
            if (count($cleanItems) === 1 && is_dir($siteDir . '/' . $cleanItems[0])) {
                $subDir = $siteDir . '/' . $cleanItems[0];
                $subFiles = scandir($subDir);
                foreach ($subFiles as $sf) {
                    if ($sf !== '.' && $sf !== '..') {
                        rename($subDir . '/' . $sf, $siteDir . '/' . $sf);
                    }
                }
                @rmdir($subDir);
                self::purgeOsJunk($siteDir);
                continue;
            }

            // Also check if index.html is missing at root, but exists inside a common build folder
            if (!file_exists($siteDir . '/index.html') && !file_exists($siteDir . '/index.htm')) {
                foreach (['dist', 'build', 'public', 'out', 'html'] as $knownSub) {
                    $candidateDir = $siteDir . '/' . $knownSub;
                    if (is_dir($candidateDir) && (file_exists($candidateDir . '/index.html') || file_exists($candidateDir . '/index.htm'))) {
                        $subFiles = scandir($candidateDir);
                        foreach ($subFiles as $sf) {
                            if ($sf !== '.' && $sf !== '..') {
                                if (!file_exists($siteDir . '/' . $sf)) {
                                    rename($candidateDir . '/' . $sf, $siteDir . '/' . $sf);
                                }
                            }
                        }
                        self::recursiveRmdir($candidateDir);
                        break;
                    }
                }
            }
            break;
        }
    }

    private static function purgeOsJunk(string $dir): void {
        if (!is_dir($dir)) return;
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $full = $dir . '/' . $item;
            if ($item === '__MACOSX' || $item === '.DS_Store' || $item === 'Thumbs.db' || str_starts_with($item, '._')) {
                if (is_dir($full)) {
                    self::recursiveRmdir($full);
                } else {
                    @unlink($full);
                }
            } elseif (is_dir($full)) {
                self::purgeOsJunk($full);
            }
        }
    }

    public static function findFirstHtmlFile(string $dir): ?string {
        if (!is_dir($dir)) return null;
        $iter = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($iter as $file) {
            if ($file->isFile()) {
                $ext = strtolower($file->getExtension());
                if ($ext === 'html' || $ext === 'htm') {
                    $rel = substr($file->getPathname(), strlen($dir) + 1);
                    return str_replace('\\', '/', $rel);
                }
            }
        }
        return null;
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
