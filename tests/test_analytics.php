<?php
/**
 * Test Suite for Phase 3: Cookieless Edge Analytics Engine
 */

require_once dirname(__DIR__) . '/config/config.php';

$baseUrl = 'http://localhost:8080';
$results = [];

function assertTest($name, $condition, $details = '') {
    global $results;
    if ($condition) {
        $results[] = ['name' => $name, 'status' => 'PASS'];
        echo "PASS: $name\n";
    } else {
        $results[] = ['name' => $name, 'status' => 'FAIL', 'details' => $details];
        echo "FAIL: $name ($details)\n";
    }
}

echo "=== Running Phase 3: Cookieless Edge Analytics Test Suite ===\n\n";

// 1. Setup Test Site
$testSlug = 'analytics-test-' . time();
$siteDir = SITES_DIR . '/' . $testSlug;
mkdir($siteDir, 0755, true);

$htmlContent = <<<HTML
<!DOCTYPE html>
<html>
<head><title>Analytics Benchmark Site</title></head>
<body>
  <h1>Welcome to PageDock Edge Analytics</h1>
  <p>Static site payload</p>
</body>
</html>
HTML;

file_put_contents($siteDir . '/index.html', $htmlContent);

$db = Database::getConnection();
$claimToken = 'claim-' . bin2hex(random_bytes(10));
$stmt = $db->prepare("INSERT INTO sites (claim_token, slug, title, entry_file, file_count, total_size_bytes, views_count) VALUES (?, ?, ?, ?, 1, 1024, 0)");
$stmt->execute([$claimToken, $testSlug, 'Analytics Benchmark Site', 'index.html']);
$siteId = (int)$db->lastInsertId();

// 2. Edge Injected Beacon Script Test
echo "--- 1. Edge Beacon Script Injection ---\n";
$ch = curl_init("$baseUrl/s/$testSlug/");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$servedHtml = curl_exec($ch);

assertTest("Served HTML contains Cookieless Analytics Edge Beacon", strpos($servedHtml, 'PageDock Cookieless Analytics Edge Beacon') !== false);
assertTest("Beacon points to /__pd/v", strpos($servedHtml, '/__pd/v') !== false);
assertTest("Beacon includes target site slug", strpos($servedHtml, $testSlug) !== false);

// 3. First Visit via /__pd/v (Unique Desktop Visit from US)
echo "\n--- 2. First Visitor Beacon Recording (/__pd/v) ---\n";
$desktopPayload = [
    '_site' => $testSlug,
    'path' => '/',
    'referrer' => 'https://github.com/trending/php'
];

$ch = curl_init("$baseUrl/__pd/v");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($desktopPayload));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    'X-Forwarded-For: 198.51.100.42',
    'CF-IPCountry: US'
]);
$resJson = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$resData = json_decode($resJson, true);

assertTest("POST /__pd/v returns HTTP 200", $code === 200, "Code: $code");
assertTest("Response reports ok=true", ($resData['ok'] ?? false) === true);
assertTest("First visit is identified as unique (is_unique=true)", ($resData['is_unique'] ?? false) === true);

// Verify DB Record
$stmt = $db->prepare("SELECT * FROM analytics_events WHERE site_id = ? ORDER BY id DESC LIMIT 1");
$stmt->execute([$siteId]);
$firstRecord = $stmt->fetch();
$stmt->closeCursor();

assertTest("Database recorded device as desktop", ($firstRecord['device_type'] ?? '') === 'desktop');
assertTest("Database recorded country code as US", ($firstRecord['country_code'] ?? '') === 'US');
assertTest("Database cleaned referrer to github.com", ($firstRecord['referrer_host'] ?? '') === 'github.com');
assertTest("Visitor hash is SHA-256 (64 hex characters)", strlen($firstRecord['visitor_hash'] ?? '') === 64);

// 4. Repeat Visit Same Day (Deduplication Check)
echo "\n--- 3. Repeat Visit Deduplication Check ---\n";
$repeatPayload = [
    '_site' => $testSlug,
    'path' => '/features',
    'referrer' => ''
];

$ch = curl_init("$baseUrl/__pd/v");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($repeatPayload));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    'X-Forwarded-For: 198.51.100.42',
    'CF-IPCountry: US'
]);
$resJson = curl_exec($ch);
$repeatData = json_decode($resJson, true);

assertTest("Repeat visit from same hash reports ok=true", ($repeatData['ok'] ?? false) === true);
assertTest("Repeat visit flagged as NOT unique (is_unique=false)", ($repeatData['is_unique'] ?? true) === false);

// 5. Mobile Visit via Aliased Endpoint (/__hh/v)
echo "\n--- 4. Mobile Visit via Aliased Endpoint (/__hh/v) ---\n";
$mobilePayload = [
    '_site' => $testSlug,
    'path' => '/pricing',
    'referrer' => 'https://t.co/xyz123'
];

$ch = curl_init("$baseUrl/__hh/v");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($mobilePayload));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'User-Agent: Mozilla/5.0 (iPhone; CPU iPhone OS 17_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Mobile/15E148 Safari/604.1',
    'X-Forwarded-For: 203.0.113.88',
    'CF-IPCountry: GB'
]);
$resJson = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$mobileData = json_decode($resJson, true);

assertTest("Aliased /__hh/v returns HTTP 200", $code === 200, "Code: $code");
assertTest("Mobile visit is unique", ($mobileData['is_unique'] ?? false) === true);

$stmt = $db->prepare("SELECT * FROM analytics_events WHERE site_id = ? AND path = '/pricing'");
$stmt->execute([$siteId]);
$mobileRecord = $stmt->fetch();
$stmt->closeCursor();

assertTest("iPhone UA classified as 'mobile'", ($mobileRecord['device_type'] ?? '') === 'mobile');
assertTest("Twitter/X referrer mapped to 'x.com'", ($mobileRecord['referrer_host'] ?? '') === 'x.com');
assertTest("Country recorded as GB", ($mobileRecord['country_code'] ?? '') === 'GB');

// 6. Tablet Visit via /__pd/v
echo "\n--- 5. Tablet Visit Classification ---\n";
$tabletPayload = [
    '_site' => $testSlug,
    'path' => '/docs',
    'referrer' => 'https://www.google.com/search?q=pagedock'
];

$ch = curl_init("$baseUrl/__pd/v");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($tabletPayload));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'User-Agent: Mozilla/5.0 (iPad; CPU OS 16_5 like Mac OS X) AppleWebKit/605.1.15',
    'X-Forwarded-For: 198.51.100.99',
    'CF-IPCountry: DE'
]);
$resJson = curl_exec($ch);
$tabletData = json_decode($resJson, true);

assertTest("Tablet visit recorded ok=true", ($tabletData['ok'] ?? false) === true);

$stmt = $db->prepare("SELECT * FROM analytics_events WHERE site_id = ? AND path = '/docs'");
$stmt->execute([$siteId]);
$tabletRecord = $stmt->fetch();
$stmt->closeCursor();

assertTest("iPad UA classified as 'tablet'", ($tabletRecord['device_type'] ?? '') === 'tablet');
assertTest("Google referrer mapped to 'google.com'", ($tabletRecord['referrer_host'] ?? '') === 'google.com');

// 7. Verify Aggregated Analytics in SiteManager
echo "\n--- 6. Verifying Site views_count Synchronization ---\n";
$stmt = $db->prepare("SELECT views_count FROM sites WHERE id = ?");
$stmt->execute([$siteId]);
$siteRow = $stmt->fetch();
$stmt->closeCursor();

// 4 total events sent (desktop / + desktop /features + mobile /pricing + tablet /docs)
assertTest("Site views_count incremented to 4", (int)$siteRow['views_count'] === 4, "Views: " . $siteRow['views_count']);

// 8. Analytics Aggregation API (GET /api/sites/{slug}/analytics)
echo "\n--- 7. Analytics JSON API (GET /api/sites/{slug}/analytics) ---\n";
$ch = curl_init("$baseUrl/api/sites/$testSlug/analytics?token=$claimToken");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$resJson = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$apiData = json_decode($resJson, true);

assertTest("GET /api/sites/{slug}/analytics returns HTTP 200", $code === 200);
assertTest("API success flag is true", ($apiData['success'] ?? false) === true);
$stats = $apiData['analytics'] ?? [];
assertTest("Total views equals 4", ($stats['total_views'] ?? 0) === 4, "Total: " . ($stats['total_views'] ?? 0));
assertTest("Unique visitors equals 3 (Desktop, Mobile, Tablet)", ($stats['unique_visitors'] ?? 0) === 3, "Uniques: " . ($stats['unique_visitors'] ?? 0));
assertTest("Today views equals 4", ($stats['today_views'] ?? 0) === 4);
assertTest("Top pages array populated", count($stats['top_pages'] ?? []) >= 4);
assertTest("Top referrers contains github.com", in_array('github.com', array_column($stats['top_referrers'] ?? [], 'host')));
assertTest("Device breakdown contains Desktop, Mobile, Tablet", count($stats['devices'] ?? []) === 3);

// Unauthorized API Request
$ch = curl_init("$baseUrl/api/sites/$testSlug/analytics"); // No token or cookie
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_exec($ch);
$unauthCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
assertTest("Unauthorized API access rejected with HTTP 403", $unauthCode === 403);

// 9. Dashboard Analytics View (GET /dashboard/analytics/{slug})
echo "\n--- 8. Dashboard Analytics View (GET /dashboard/analytics/{slug}) ---\n";
$ch = curl_init("$baseUrl/dashboard/analytics/$testSlug?token=$claimToken");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$viewHtml = curl_exec($ch);
$viewCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

assertTest("GET /dashboard/analytics/{slug} returns HTTP 200", $viewCode === 200);
assertTest("View renders site title", strpos($viewHtml, 'Analytics Benchmark Site') !== false);
assertTest("View renders Cookieless Analytics header", strpos($viewHtml, 'Cookieless Analytics') !== false);
assertTest("View renders KPI metric cards", strpos($viewHtml, 'Total Views') !== false && strpos($viewHtml, 'Unique Visitors') !== false);
assertTest("View renders Traffic Over Time chart section", strpos($viewHtml, 'Traffic Over Time') !== false);
assertTest("View renders Top Pages breakdown", strpos($viewHtml, 'Top Pages') !== false);
assertTest("View renders Traffic Referrers breakdown", strpos($viewHtml, 'Traffic Referrers') !== false);
assertTest("View renders Devices breakdown", strpos($viewHtml, 'Devices') !== false);
assertTest("View renders Geographic Origins breakdown", strpos($viewHtml, 'Geographic Origins') !== false);

// Cleanup
$files = glob($siteDir . '/*');
foreach ($files as $f) unlink($f);
rmdir($siteDir);
usleep(100000);
$db->exec("DELETE FROM analytics_events WHERE site_id = $siteId");
$db->exec("DELETE FROM sites WHERE id = $siteId");

echo "\n============================================\n";
$total = count($results);
$passed = count(array_filter($results, fn($r) => $r['status'] === 'PASS'));
echo "RESULTS: $passed / $total TESTS PASSED\n";
echo "============================================\n";

