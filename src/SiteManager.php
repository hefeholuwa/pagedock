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

        // Track claim token in guest session
        if (!$userId) {
            $_SESSION['guest_claim_tokens'][] = $claimToken;
        }

        return [
            'success' => true,
            'slug' => $slug,
            'title' => $title,
            'claim_token' => $claimToken,
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

        if (!$userId) {
            $_SESSION['guest_claim_tokens'][] = $claimToken;
        }

        return [
            'success' => true,
            'slug' => $slug,
            'title' => $title,
            'claim_token' => $claimToken,
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
     * Read the entry HTML content for a site
     */
    public static function getSiteHtml(string $slug): ?string {
        $site = self::getSite($slug);
        if (!$site) return null;

        $path = SITES_DIR . '/' . $slug . '/' . ($site['entry_file'] ?: 'index.html');
        if (file_exists($path)) {
            return file_get_contents($path);
        }
        return null;
    }

    /**
     * Update the HTML content of a site
     */
    public static function updateSiteHtml(string $slug, string $htmlContent): array {
        $site = self::getSite($slug);
        if (!$site) {
            return ['success' => false, 'error' => 'Site not found.'];
        }

        if (!self::canManage($site)) {
            return ['success' => false, 'error' => 'Unauthorized to edit this site.'];
        }

        $siteDir = SITES_DIR . '/' . $slug;
        $entryFile = $site['entry_file'] ?: 'index.html';
        $filePath = $siteDir . '/' . $entryFile;

        file_put_contents($filePath, $htmlContent);

        $db = Database::getConnection();
        $stmt = $db->prepare("UPDATE sites SET updated_at = CURRENT_TIMESTAMP, total_size_bytes = ? WHERE id = ?");
        $stmt->execute([filesize($filePath), $site['id']]);

        return ['success' => true, 'slug' => $slug];
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

        if ($userId) {
            $stmt = $db->prepare("SELECT * FROM sites WHERE user_id = ? ORDER BY created_at DESC");
            $stmt->execute([$userId]);
            return $stmt->fetchAll();
        }

        $tokens = $_SESSION['guest_claim_tokens'] ?? [];
        if (!empty($tokens)) {
            $in = str_repeat('?,', count($tokens) - 1) . '?';
            $stmt = $db->prepare("SELECT * FROM sites WHERE claim_token IN ($in) ORDER BY created_at DESC");
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
