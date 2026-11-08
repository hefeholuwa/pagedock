<?php
/**
 * PageDock Snapshot Versioning & Rollback Engine
 * Implements immutable revision snapshots on publication and 1-click rollbacks
 * matching HTMLHost.co Master Specification Section 9 & 12:
 * - GET /api/sites/{slug}/versions
 * - POST /api/sites/{slug}/versions/{n}/restore
 * - POST /api/sites/{slug}/rollback
 */

class VersionManager {
    /**
     * Create an immutable snapshot of current site files
     */
    public static function createSnapshot(int $siteId, string $slug, string $summary = 'Published deployment'): int {
        $siteDir = realpath(SITES_DIR . '/' . $slug);
        if (!$siteDir || !is_dir($siteDir)) {
            return 1;
        }

        $db = Database::getConnection();

        // 1. Determine next version number
        $stmt = $db->prepare("SELECT MAX(version_number) as max_v FROM site_versions WHERE site_id = ?");
        $stmt->execute([$siteId]);
        $row = $stmt->fetch();
        $nextVersion = ((int)($row['max_v'] ?? 0)) + 1;

        // 2. Prepare snapshots folder
        $snapshotsBase = $siteDir . '/.snapshots';
        $targetDir = $snapshotsBase . '/v' . $nextVersion;

        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        // 3. Copy files to snapshot (excluding .snapshots and OS metadata)
        $metrics = self::copyDirectory($siteDir, $targetDir, ['.snapshots', '__MACOSX', '.DS_Store', 'Thumbs.db']);

        // 4. Record in database
        $stmt = $db->prepare("
            INSERT INTO site_versions (site_id, version_number, file_count, total_size_bytes, summary)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $siteId,
            $nextVersion,
            $metrics['file_count'],
            $metrics['total_bytes'],
            $summary
        ]);

        // 5. Update current_version on site record
        $upStmt = $db->prepare("UPDATE sites SET current_version = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $upStmt->execute([$nextVersion, $siteId]);

        return $nextVersion;
    }

    /**
     * Get all version revisions for a site
     */
    public static function getVersions(int $siteId): array {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM site_versions WHERE site_id = ? ORDER BY version_number DESC");
        $stmt->execute([$siteId]);
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Restore site to a historical snapshot
     */
    public static function restoreVersion(array $site, int $targetVersion): array {
        $siteDir = realpath(SITES_DIR . '/' . $site['slug']);
        if (!$siteDir || !is_dir($siteDir)) {
            return ['success' => false, 'error' => 'Site directory not found.'];
        }

        $snapshotDir = $siteDir . '/.snapshots/v' . $targetVersion;
        if (!is_dir($snapshotDir)) {
            return ['success' => false, 'error' => "Revision snapshot v{$targetVersion} does not exist on disk."];
        }

        // 1. Wipe current site files (preserving .snapshots)
        self::cleanSiteRoot($siteDir, ['.snapshots']);

        // 2. Restore files from snapshot
        self::copyDirectory($snapshotDir, $siteDir);

        // 3. Create a new audit revision reflecting the rollback
        $newVersion = self::createSnapshot((int)$site['id'], $site['slug'], "Rollback to revision v{$targetVersion}");

        // Update site file metrics
        $metrics = self::measureDirectory($siteDir, ['.snapshots']);
        $db = Database::getConnection();
        $upStmt = $db->prepare("UPDATE sites SET file_count = ?, total_size_bytes = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $upStmt->execute([$metrics['file_count'], $metrics['total_bytes'], $site['id']]);

        return [
            'success' => true,
            'restored_version' => $targetVersion,
            'new_version' => $newVersion,
            'slug' => $site['slug'],
            'message' => "Successfully restored revision v{$targetVersion}."
        ];
    }

    /**
     * Recursively copy directory skipping excluded folder names
     */
    private static function copyDirectory(string $src, string $dst, array $excludes = []): array {
        $fileCount = 0;
        $totalBytes = 0;

        $dir = opendir($src);
        if (!$dir) return ['file_count' => 0, 'total_bytes' => 0];

        if (!is_dir($dst)) {
            mkdir($dst, 0755, true);
        }

        while (($item = readdir($dir)) !== false) {
            if ($item === '.' || $item === '..') continue;
            if (in_array($item, $excludes, true)) continue;

            $srcPath = $src . '/' . $item;
            $dstPath = $dst . '/' . $item;

            if (is_dir($srcPath)) {
                $sub = self::copyDirectory($srcPath, $dstPath, $excludes);
                $fileCount += $sub['file_count'];
                $totalBytes += $sub['total_bytes'];
            } else {
                copy($srcPath, $dstPath);
                $fileCount++;
                $totalBytes += filesize($srcPath);
            }
        }
        closedir($dir);

        return ['file_count' => $fileCount, 'total_bytes' => $totalBytes];
    }

    /**
     * Clean all files in directory except excluded paths
     */
    private static function cleanSiteRoot(string $dir, array $excludes = []): void {
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            if (in_array($item, $excludes, true)) continue;

            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                self::recursiveDelete($path);
            } else {
                unlink($path);
            }
        }
    }

    private static function recursiveDelete(string $path): void {
        if (is_dir($path)) {
            $files = array_diff(scandir($path), ['.', '..']);
            foreach ($files as $f) {
                self::recursiveDelete($path . '/' . $f);
            }
            rmdir($path);
        } elseif (file_exists($path)) {
            unlink($path);
        }
    }

    private static function measureDirectory(string $dir, array $excludes = []): array {
        $fileCount = 0;
        $totalBytes = 0;

        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            if (in_array($item, $excludes, true)) continue;

            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $sub = self::measureDirectory($path, $excludes);
                $fileCount += $sub['file_count'];
                $totalBytes += $sub['total_bytes'];
            } else {
                $fileCount++;
                $totalBytes += filesize($path);
            }
        }

        return ['file_count' => $fileCount, 'total_bytes' => $totalBytes];
    }
}

