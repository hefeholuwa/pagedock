<?php

class Admin {
    /**
     * Compile global telemetry and health metrics across the entire platform
     */
    public static function getPlatformStats(): array {
        $db = Database::getConnection();

        // 1. Sites Metrics
        $totalSites = (int)$db->query("SELECT COUNT(*) FROM sites")->fetchColumn();
        $activeSites = (int)$db->query("SELECT COUNT(*) FROM sites WHERE is_suspended = 0")->fetchColumn();
        $suspendedSites = (int)$db->query("SELECT COUNT(*) FROM sites WHERE is_suspended = 1")->fetchColumn();
        $claimedSites = (int)$db->query("SELECT COUNT(*) FROM sites WHERE user_id IS NOT NULL")->fetchColumn();
        $guestSites = (int)$db->query("SELECT COUNT(*) FROM sites WHERE user_id IS NULL")->fetchColumn();

        $totalSizeBytes = (int)$db->query("SELECT COALESCE(SUM(total_size_bytes), 0) FROM sites")->fetchColumn();
        $totalViews = (int)$db->query("SELECT COALESCE(SUM(views_count), 0) FROM sites")->fetchColumn();

        // 2. Users Metrics
        $totalUsers = (int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $adminUsers = (int)$db->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();

        // 3. Analytics & Traffic Telemetry
        $uniqueVisitors = (int)$db->query("SELECT COUNT(DISTINCT visitor_hash) FROM analytics_events")->fetchColumn();
        
        // 24h & 7d Views
        $views24h = 0;
        $views7d = 0;
        try {
            $stmt24 = $db->query("SELECT COUNT(*) FROM analytics_events WHERE created_at >= datetime('now', '-1 day')");
            $views24h = (int)($stmt24 ? $stmt24->fetchColumn() : 0);

            $stmt7d = $db->query("SELECT COUNT(*) FROM analytics_events WHERE created_at >= datetime('now', '-7 days')");
            $views7d = (int)($stmt7d ? $stmt7d->fetchColumn() : 0);
        } catch (\Throwable $e) {
            // Fallback for non-sqlite engines if needed
            $views24h = 0;
            $views7d = 0;
        }

        // 4. Form Submissions
        $totalForms = (int)$db->query("SELECT COUNT(*) FROM form_submissions")->fetchColumn();
        $spamForms = (int)$db->query("SELECT COUNT(*) FROM form_submissions WHERE is_spam = 1")->fetchColumn();
        $cleanForms = $totalForms - $spamForms;

        // 5. Version Snapshots
        $totalVersions = (int)$db->query("SELECT COUNT(*) FROM site_versions")->fetchColumn();

        // 6. Storage & Infrastructure Status
        $storageDriver = defined('STORAGE_DRIVER') ? STORAGE_DRIVER : 'local';
        $r2Configured = class_exists('R2Storage') && R2Storage::isConfigured();
        
        $dbSizeBytes = 0;
        if (defined('DB_PATH') && file_exists(DB_PATH)) {
            $dbSizeBytes = (int)@filesize(DB_PATH);
        }

        return [
            'sites' => [
                'total' => $totalSites,
                'active' => $activeSites,
                'suspended' => $suspendedSites,
                'claimed' => $claimedSites,
                'guest' => $guestSites,
                'total_size_bytes' => $totalSizeBytes,
                'total_size_formatted' => self::formatBytes($totalSizeBytes),
                'total_views' => $totalViews,
            ],
            'users' => [
                'total' => $totalUsers,
                'admins' => $adminUsers,
                'standard' => max(0, $totalUsers - $adminUsers),
            ],
            'traffic' => [
                'all_time_views' => $totalViews,
                'views_24h' => $views24h,
                'views_7d' => $views7d,
                'unique_visitors' => $uniqueVisitors,
            ],
            'forms' => [
                'total' => $totalForms,
                'clean' => $cleanForms,
                'spam' => $spamForms,
            ],
            'versions' => [
                'total' => $totalVersions,
            ],
            'system' => [
                'storage_driver' => $storageDriver,
                'r2_configured' => $r2Configured,
                'r2_bucket' => defined('R2_BUCKET') ? R2_BUCKET : '',
                'db_driver' => defined('DB_DRIVER') ? DB_DRIVER : 'sqlite',
                'db_size_bytes' => $dbSizeBytes,
                'db_size_formatted' => self::formatBytes($dbSizeBytes),
                'php_version' => PHP_VERSION,
                'server_os' => PHP_OS,
                'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'PHP CLI / Built-in',
                'memory_usage_formatted' => self::formatBytes(memory_get_usage(true)),
                'peak_memory_formatted' => self::formatBytes(memory_get_peak_usage(true)),
                'server_time' => gmdate('Y-m-d H:i:s') . ' UTC',
            ],
        ];
    }

    /**
     * Get searchable and filterable list of all hosted sites on the platform
     */
    public static function getAllSites(string $search = '', string $filter = '', int $limit = 100, int $offset = 0): array {
        $db = Database::getConnection();

        $sql = "
            SELECT s.*, 
                   u.email as owner_email,
                   u.name as owner_name,
                   (SELECT COUNT(*) FROM form_submissions WHERE site_id = s.id AND is_spam = 0) as forms_count,
                   (SELECT COUNT(*) FROM site_versions WHERE site_id = s.id) as version_snapshots
            FROM sites s
            LEFT JOIN users u ON s.user_id = u.id
            WHERE 1=1
        ";

        $params = [];

        // Filter by state
        if ($filter === 'active') {
            $sql .= " AND s.is_suspended = 0";
        } elseif ($filter === 'suspended') {
            $sql .= " AND s.is_suspended = 1";
        } elseif ($filter === 'claimed') {
            $sql .= " AND s.user_id IS NOT NULL";
        } elseif ($filter === 'guest') {
            $sql .= " AND s.user_id IS NULL";
        }

        // Search across slug, title, claim_token, and owner email
        $trimmedSearch = trim($search);
        if ($trimmedSearch !== '') {
            $sql .= " AND (s.slug LIKE ? OR s.title LIKE ? OR s.claim_token LIKE ? OR u.email LIKE ?)";
            $term = '%' . $trimmedSearch . '%';
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        $sql .= " ORDER BY s.created_at DESC LIMIT " . (int)$limit . " OFFSET " . (int)$offset;

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll() ?: [];

        return array_map(function ($site) {
            $slug = $site['slug'];
            return [
                'id' => (int)$site['id'],
                'slug' => $slug,
                'title' => $site['title'] ?: $slug,
                'entry_file' => $site['entry_file'] ?: 'index.html',
                'file_count' => (int)($site['file_count'] ?? 1),
                'total_size_bytes' => (int)($site['total_size_bytes'] ?? 0),
                'total_size_formatted' => self::formatBytes((int)($site['total_size_bytes'] ?? 0)),
                'views_count' => (int)($site['views_count'] ?? 0),
                'forms_count' => (int)($site['forms_count'] ?? 0),
                'current_version' => (int)($site['current_version'] ?? 1),
                'is_suspended' => (int)($site['is_suspended'] ?? 0) === 1,
                'user_id' => $site['user_id'] ? (int)$site['user_id'] : null,
                'owner_email' => $site['owner_email'] ?? null,
                'owner_name' => $site['owner_name'] ?? null,
                'claim_token' => $site['claim_token'] ?? '',
                'created_at' => $site['created_at'] ?? '',
                'updated_at' => $site['updated_at'] ?? '',
                'liveUrl' => url('s/' . $slug . '/'),
                'editorUrl' => url('editor/' . $slug),
            ];
        }, $rows);
    }

    /**
     * Get registered users list with owned sites count and total views
     */
    public static function getAllUsers(string $search = '', int $limit = 100, int $offset = 0): array {
        $db = Database::getConnection();

        $sql = "
            SELECT u.id, u.email, u.name, u.plan, u.role, u.created_at,
                   COUNT(s.id) as sites_count,
                   COALESCE(SUM(s.views_count), 0) as total_views,
                   COALESCE(SUM(s.total_size_bytes), 0) as total_bytes
            FROM users u
            LEFT JOIN sites s ON u.id = s.user_id
            WHERE 1=1
        ";

        $params = [];
        $trimmedSearch = trim($search);
        if ($trimmedSearch !== '') {
            $sql .= " AND (u.email LIKE ? OR u.name LIKE ?)";
            $term = '%' . $trimmedSearch . '%';
            $params[] = $term;
            $params[] = $term;
        }

        $sql .= " GROUP BY u.id ORDER BY u.created_at DESC LIMIT " . (int)$limit . " OFFSET " . (int)$offset;

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll() ?: [];

        return array_map(function ($u) {
            return [
                'id' => (int)$u['id'],
                'email' => $u['email'],
                'name' => $u['name'] ?: explode('@', $u['email'])[0],
                'plan' => $u['plan'] ?: 'free',
                'role' => $u['role'] ?: 'user',
                'sites_count' => (int)($u['sites_count'] ?? 0),
                'total_views' => (int)($u['total_views'] ?? 0),
                'total_bytes' => (int)($u['total_bytes'] ?? 0),
                'total_size_formatted' => self::formatBytes((int)($u['total_bytes'] ?? 0)),
                'created_at' => $u['created_at'] ?? '',
            ];
        }, $rows);
    }

    /**
     * Build unified real-time chronological activity feed
     */
    public static function getRecentActivity(int $limit = 25): array {
        $db = Database::getConnection();
        $activities = [];

        // 1. Recent Version Deployments
        try {
            $stmtV = $db->query("
                SELECT v.id, v.version_number, v.summary, v.created_at,
                       s.slug, s.title, u.email as user_email
                FROM site_versions v
                JOIN sites s ON v.site_id = s.id
                LEFT JOIN users u ON s.user_id = u.id
                ORDER BY v.created_at DESC LIMIT " . (int)$limit
            );
            while ($row = $stmtV->fetch()) {
                $activities[] = [
                    'type' => 'deployment',
                    'title' => 'Deployment snapshot v' . $row['version_number'] . ' for ' . $row['slug'],
                    'detail' => $row['summary'] ?: 'Published deployment',
                    'meta' => $row['user_email'] ? 'By ' . $row['user_email'] : 'Guest / CLI deploy',
                    'timestamp' => $row['created_at'],
                    'link' => url('s/' . $row['slug'] . '/'),
                ];
            }
        } catch (\Throwable $e) {}

        // 2. Recent Form Submissions
        try {
            $stmtF = $db->query("
                SELECT f.id, f.form_name, f.is_spam, f.created_at, s.slug, s.title
                FROM form_submissions f
                JOIN sites s ON f.site_id = s.id
                ORDER BY f.created_at DESC LIMIT " . (int)$limit
            );
            while ($row = $stmtF->fetch()) {
                $activities[] = [
                    'type' => 'form_lead',
                    'title' => (!empty($row['is_spam']) ? '[SPAM] ' : '') . 'Form submission on ' . $row['slug'],
                    'detail' => 'Form: ' . ($row['form_name'] ?: 'Contact'),
                    'meta' => !empty($row['is_spam']) ? 'Flagged as spam' : 'Valid lead captured',
                    'timestamp' => $row['created_at'],
                    'link' => url('dashboard/forms/' . $row['slug']),
                ];
            }
        } catch (\Throwable $e) {}

        // 3. Recent User Signups
        try {
            $stmtU = $db->query("
                SELECT id, email, name, role, created_at
                FROM users
                ORDER BY created_at DESC LIMIT " . (int)$limit
            );
            while ($row = $stmtU->fetch()) {
                $activities[] = [
                    'type' => 'user_signup',
                    'title' => 'New user registered: ' . $row['email'],
                    'detail' => 'Role: ' . ($row['role'] ?: 'user'),
                    'meta' => 'User ID #' . $row['id'],
                    'timestamp' => $row['created_at'],
                    'link' => '#',
                ];
            }
        } catch (\Throwable $e) {}

        // Sort combined feed descending by timestamp
        usort($activities, function ($a, $b) {
            return strcmp($b['timestamp'], $a['timestamp']);
        });

        return array_slice($activities, 0, $limit);
    }

    /**
     * Toggle site suspension status (active vs suspended)
     */
    public static function toggleSiteSuspension(string $slug): array {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT id, slug, is_suspended FROM sites WHERE slug = ?");
        $stmt->execute([$slug]);
        $site = $stmt->fetch();

        if (!$site) {
            return ['success' => false, 'error' => 'Site not found'];
        }

        $newStatus = ((int)($site['is_suspended'] ?? 0) === 1) ? 0 : 1;
        $upd = $db->prepare("UPDATE sites SET is_suspended = ? WHERE id = ?");
        $upd->execute([$newStatus, $site['id']]);

        // Backup SQLite to R2 if configured
        Database::syncDatabaseToR2();

        return [
            'success' => true,
            'slug' => $slug,
            'is_suspended' => ($newStatus === 1),
            'status_label' => ($newStatus === 1) ? 'Suspended' : 'Active',
        ];
    }

    /**
     * Admin delete of any hosted site
     */
    public static function deleteSite(string $slug): array {
        $res = SiteManager::deleteSite($slug);
        if (!empty($res['success'])) {
            Database::syncDatabaseToR2();
        }
        return $res;
    }

    /**
     * Toggle user role between user and admin
     */
    public static function toggleUserRole(int $userId): array {
        $currentId = Auth::id();
        if ($currentId === $userId) {
            return ['success' => false, 'error' => 'You cannot modify your own administrative role.'];
        }

        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT id, email, role FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $targetUser = $stmt->fetch();

        if (!$targetUser) {
            return ['success' => false, 'error' => 'User not found.'];
        }

        // Prevent demoting primary configured ADMIN_EMAIL
        if (defined('ADMIN_EMAIL') && ADMIN_EMAIL !== '' && strtolower($targetUser['email']) === strtolower(ADMIN_EMAIL)) {
            return ['success' => false, 'error' => 'Primary platform administrator cannot be demoted.'];
        }

        $currentRole = $targetUser['role'] ?: 'user';
        $newRole = ($currentRole === 'admin') ? 'user' : 'admin';

        $upd = $db->prepare("UPDATE users SET role = ? WHERE id = ?");
        $upd->execute([$newRole, $userId]);

        return [
            'success' => true,
            'user_id' => $userId,
            'email' => $targetUser['email'],
            'new_role' => $newRole,
        ];
    }

    /**
     * Purge expired guest sites past their expiration date
     */
    public static function cleanupExpiredSites(): array {
        $db = Database::getConnection();
        $stmt = $db->query("
            SELECT slug FROM sites 
            WHERE user_id IS NULL 
              AND expires_at IS NOT NULL 
              AND expires_at < CURRENT_TIMESTAMP
        ");
        $expired = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        $deleted = 0;

        foreach ($expired as $slug) {
            $res = SiteManager::deleteSite($slug);
            if (!empty($res['success'])) {
                $deleted++;
            }
        }

        if ($deleted > 0) {
            Database::syncDatabaseToR2();
        }

        return [
            'success' => true,
            'scanned' => count($expired),
            'deleted' => $deleted,
        ];
    }

    /**
     * Helper to format bytes into KB, MB, GB
     */
    public static function formatBytes(int $bytes): string {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        if ($bytes < 1024 * 1024) {
            return number_format($bytes / 1024, 1) . ' KB';
        }
        if ($bytes < 1024 * 1024 * 1024) {
            return number_format($bytes / (1024 * 1024), 2) . ' MB';
        }
        return number_format($bytes / (1024 * 1024 * 1024), 2) . ' GB';
    }
}
