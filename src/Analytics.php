<?php
/**
 * PageDock Cookieless Edge Analytics Engine
 * Privacy-First, Zero-Cookie Analytics System matching HTMLHost.co Master Specification Section 8:
 * - Beacon endpoint (/__pd/v and /__hh/v)
 * - Daily visitor fingerprinting: SHA-256(Salt + IP + UA + Date) - zero PII stored
 * - Unique visitors vs total views
 * - Device categorization (Desktop, Mobile, Tablet)
 * - Referrer host extraction & UTM tracking
 * - Country detection via Edge headers (Cloudflare CF-IPCountry / Vercel)
 * - Real-time stats & time-series aggregation
 */

class Analytics {
    private const SALT = 'pd_privacy_analytics_salt_v1';

    /**
     * Categorize User Agent into device type
     */
    public static function parseDevice(string $userAgent): string {
        $ua = strtolower($userAgent);

        if (str_contains($ua, 'ipad') || str_contains($ua, 'tablet') || (str_contains($ua, 'android') && !str_contains($ua, 'mobile'))) {
            return 'tablet';
        }

        if (str_contains($ua, 'mobile') || str_contains($ua, 'iphone') || str_contains($ua, 'ipod') || str_contains($ua, 'android')) {
            return 'mobile';
        }

        return 'desktop';
    }

    /**
     * Clean and normalize referrer URL to a concise domain/channel label
     */
    public static function parseReferrerHost(?string $referrer): string {
        if (!$referrer || trim($referrer) === '') {
            return 'Direct / None';
        }

        $parsed = parse_url($referrer);
        $host = strtolower($parsed['host'] ?? '');

        if (!$host) {
            return 'Direct / None';
        }

        // Strip leading www.
        if (str_starts_with($host, 'www.')) {
            $host = substr($host, 4);
        }

        // Map known social and search referrers to clean names
        if (str_contains($host, 'google.')) return 'google.com';
        if (str_contains($host, 'github.com')) return 'github.com';
        if (str_contains($host, 't.co') || str_contains($host, 'twitter.com') || str_contains($host, 'x.com')) return 'x.com';
        if (str_contains($host, 'linkedin.com')) return 'linkedin.com';
        if (str_contains($host, 'reddit.com')) return 'reddit.com';
        if (str_contains($host, 'facebook.com') || str_contains($host, 'fb.com')) return 'facebook.com';
        if (str_contains($host, 'news.ycombinator.com')) return 'news.ycombinator.com';
        if (str_contains($host, 'youtube.com')) return 'youtube.com';

        return $host;
    }

    /**
     * Extract client IP address respecting reverse proxies & CDNs
     */
    public static function getClientIp(array $server): string {
        if (!empty($server['HTTP_CF_CONNECTING_IP'])) {
            return trim($server['HTTP_CF_CONNECTING_IP']);
        }
        if (!empty($server['HTTP_X_FORWARDED_FOR'])) {
            $parts = explode(',', $server['HTTP_X_FORWARDED_FOR']);
            return trim($parts[0]);
        }
        return $server['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    /**
     * Generate daily privacy-preserving visitor hash (zero PII stored)
     */
    public static function generateVisitorHash(string $ip, string $userAgent, string $dateString): string {
        $salt = defined('APP_KEY') ? APP_KEY : self::SALT;
        return hash('sha256', $salt . '|' . $ip . '|' . $userAgent . '|' . $dateString);
    }

    /**
     * Record a page view event from the edge beacon
     */
    public static function recordView(array $payload, array $server): array {
        // 1. Resolve target site
        $site = null;
        if (!empty($payload['_site'])) {
            $site = SiteManager::getSite($payload['_site']);
        } elseif (!empty($payload['slug'])) {
            $site = SiteManager::getSite($payload['slug']);
        }

        if (!$site) {
            $referer = $server['HTTP_REFERER'] ?? ($payload['referrer'] ?? '');
            if ($referer) {
                $refPath = parse_url($referer, PHP_URL_PATH) ?? '';
                if (preg_match('#/s/([a-zA-Z0-9_\-]+)#', $refPath, $m)) {
                    $site = SiteManager::getSite($m[1]);
                }
            }
        }

        if (!$site) {
            return ['ok' => false, 'error' => 'Target site not resolved'];
        }

        $siteId = (int)$site['id'];
        $path = !empty($payload['path']) ? '/' . ltrim(trim($payload['path']), '/') : '/';
        // Strip query parameters from path for clean aggregation
        $path = strtok($path, '?');
        if (strlen($path) > 255) {
            $path = substr($path, 0, 255);
        }

        $ip = self::getClientIp($server);
        $ua = $server['HTTP_USER_AGENT'] ?? ($payload['ua'] ?? '');
        $today = date('Y-m-d');
        $visitorHash = self::generateVisitorHash($ip, $ua, $today);

        $referrer = $payload['referrer'] ?? ($server['HTTP_REFERER'] ?? null);
        $referrerHost = self::parseReferrerHost($referrer);
        $device = self::parseDevice($ua);

        // Edge Geo headers (Cloudflare, Vercel, or custom proxy)
        $country = strtoupper(trim($server['HTTP_CF_IPCOUNTRY'] ?? ($server['HTTP_X_COUNTRY_CODE'] ?? 'XX')));
        if (strlen($country) > 4) {
            $country = 'XX';
        }

        $db = Database::getConnection();

        // Check if visitor has already been counted today for this site
        $todayStart = $today . ' 00:00:00';
        $checkStmt = $db->prepare("
            SELECT id FROM analytics_events 
            WHERE site_id = ? AND visitor_hash = ? AND created_at >= ? 
            LIMIT 1
        ");
        $checkStmt->execute([$siteId, $visitorHash, $todayStart]);
        $alreadyVisited = (bool)$checkStmt->fetch();
        $isUnique = $alreadyVisited ? 0 : 1;

        // Insert event
        $stmt = $db->prepare("
            INSERT INTO analytics_events (site_id, path, visitor_hash, referrer, referrer_host, device_type, country_code, is_unique)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $siteId,
            $path,
            $visitorHash,
            $referrer ? substr($referrer, 0, 500) : null,
            substr($referrerHost, 0, 255),
            $device,
            $country,
            $isUnique
        ]);

        // Increment site total view counter
        $upStmt = $db->prepare("UPDATE sites SET views_count = views_count + 1 WHERE id = ?");
        $upStmt->execute([$siteId]);

        return [
            'ok' => true,
            'is_unique' => (bool)$isUnique,
            'site' => $site['slug']
        ];
    }

    /**
     * Get aggregated metrics and time-series for a site
     */
    public static function getSiteAnalytics(int $siteId, int $days = 30): array {
        $db = Database::getConnection();
        $days = max(1, min(90, $days));
        $sinceDate = date('Y-m-d 00:00:00', strtotime("-{$days} days"));

        // 1. Total Views & Unique Visitors in period
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as total_views,
                COUNT(DISTINCT visitor_hash) as unique_visitors
            FROM analytics_events 
            WHERE site_id = ? AND created_at >= ?
        ");
        $stmt->execute([$siteId, $sinceDate]);
        $totals = $stmt->fetch() ?: ['total_views' => 0, 'unique_visitors' => 0];

        $totalViews = (int)$totals['total_views'];
        $uniqueVisitors = (int)$totals['unique_visitors'];

        // 2. Today's stats
        $todayStart = date('Y-m-d 00:00:00');
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as today_views,
                COUNT(DISTINCT visitor_hash) as today_uniques
            FROM analytics_events 
            WHERE site_id = ? AND created_at >= ?
        ");
        $stmt->execute([$siteId, $todayStart]);
        $todayStats = $stmt->fetch() ?: ['today_views' => 0, 'today_uniques' => 0];

        // 3. Daily time-series breakdown
        // Group by DATE(created_at)
        $stmt = $db->prepare("
            SELECT 
                DATE(created_at) as date_val,
                COUNT(*) as views,
                COUNT(DISTINCT visitor_hash) as uniques
            FROM analytics_events 
            WHERE site_id = ? AND created_at >= ?
            GROUP BY DATE(created_at)
            ORDER BY date_val ASC
        ");
        $stmt->execute([$siteId, $sinceDate]);
        $dailyRows = $stmt->fetchAll();

        // Index daily rows by date
        $dailyMap = [];
        foreach ($dailyRows as $row) {
            $dailyMap[$row['date_val']] = [
                'views' => (int)$row['views'],
                'uniques' => (int)$row['uniques']
            ];
        }

        // Fill all dates in range with zeros if no views occurred
        $timeSeries = [];
        for ($i = $days; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-{$i} days"));
            $timeSeries[] = [
                'date' => $d,
                'label' => date('M j', strtotime($d)),
                'views' => $dailyMap[$d]['views'] ?? 0,
                'uniques' => $dailyMap[$d]['uniques'] ?? 0
            ];
        }

        // 4. Top Pages
        $stmt = $db->prepare("
            SELECT path, COUNT(*) as views
            FROM analytics_events 
            WHERE site_id = ? AND created_at >= ?
            GROUP BY path
            ORDER BY views DESC
            LIMIT 10
        ");
        $stmt->execute([$siteId, $sinceDate]);
        $topPages = $stmt->fetchAll();
        foreach ($topPages as &$p) {
            $p['views'] = (int)$p['views'];
            $p['percentage'] = $totalViews > 0 ? round(($p['views'] / $totalViews) * 100, 1) : 0;
        }

        // 5. Top Referrers
        $stmt = $db->prepare("
            SELECT referrer_host as host, COUNT(*) as views
            FROM analytics_events 
            WHERE site_id = ? AND created_at >= ?
            GROUP BY referrer_host
            ORDER BY views DESC
            LIMIT 10
        ");
        $stmt->execute([$siteId, $sinceDate]);
        $topReferrers = $stmt->fetchAll();
        foreach ($topReferrers as &$r) {
            $r['views'] = (int)$r['views'];
            $r['percentage'] = $totalViews > 0 ? round(($r['views'] / $totalViews) * 100, 1) : 0;
        }

        // 6. Device Distribution
        $stmt = $db->prepare("
            SELECT device_type as device, COUNT(*) as count
            FROM analytics_events 
            WHERE site_id = ? AND created_at >= ?
            GROUP BY device_type
            ORDER BY count DESC
        ");
        $stmt->execute([$siteId, $sinceDate]);
        $deviceRows = $stmt->fetchAll();
        $devices = [];
        foreach ($deviceRows as $dev) {
            $cnt = (int)$dev['count'];
            $devices[] = [
                'device' => ucfirst($dev['device']),
                'count' => $cnt,
                'percentage' => $totalViews > 0 ? round(($cnt / $totalViews) * 100, 1) : 0
            ];
        }

        // 7. Top Countries
        $stmt = $db->prepare("
            SELECT country_code as country, COUNT(*) as views
            FROM analytics_events 
            WHERE site_id = ? AND created_at >= ?
            GROUP BY country_code
            ORDER BY views DESC
            LIMIT 8
        ");
        $stmt->execute([$siteId, $sinceDate]);
        $topCountries = $stmt->fetchAll();
        foreach ($topCountries as &$c) {
            $c['views'] = (int)$c['views'];
            $c['percentage'] = $totalViews > 0 ? round(($c['views'] / $totalViews) * 100, 1) : 0;
        }

        return [
            'days' => $days,
            'total_views' => $totalViews,
            'unique_visitors' => $uniqueVisitors,
            'today_views' => (int)$todayStats['today_views'],
            'today_uniques' => (int)$todayStats['today_uniques'],
            'time_series' => $timeSeries,
            'top_pages' => $topPages,
            'top_referrers' => $topReferrers,
            'devices' => $devices,
            'top_countries' => $topCountries
        ];
    }
}

