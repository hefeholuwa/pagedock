<?php
/**
 * Test Suite for Phase 6: Multi-Page Layout Sync & Drift Engine
 * Validates:
 * 1. Shared block extraction (<!-- pd:shared --> and <!-- hh:shared -->)
 * 2. Normalization algorithm (stripping active classes, aria-current, depth URLs)
 * 3. Drift detection across multi-page site
 * 4. GET /api/sites/{slug}/shared-blocks endpoint
 * 5. POST /api/sites/{slug}/sync-shared propagation endpoint
 * 6. Dynamic route-aware active navigation link injection across distinct pages
 * 7. Snapshot versioning integration upon layout sync
 */

require_once dirname(__DIR__) . '/config/config.php';

$baseUrl = 'http://127.0.0.1:8080';
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

echo "=== Running Phase 6: Multi-Page Layout Sync Test Suite ===\n\n";

// 1. Shared Block Extraction & Syntax Parsing
echo "--- 1. Shared Block Extraction & Attribute Parsing ---\n";

$sampleHtml = <<<HTML
<!DOCTYPE html>
<html>
<head><title>Test</title></head>
<body>
<!-- pd:shared header active-class="nav-active" aria-current="page" -->
<header class="main-header">
  <nav>
    <a href="/" class="nav-active" aria-current="page">Home</a>
    <a href="/about">About</a>
  </nav>
</header>
<!-- /pd:shared header -->

<main><h1>Body Content</h1></main>

<!-- hh:shared footer -->
<footer>
  <p>&copy; 2026 Acme Corp. All rights reserved.</p>
</footer>
<!-- /hh:shared footer -->
</body>
</html>
HTML;

$blocks = SharedLayoutManager::parseBlocks($sampleHtml);
assertTest('Extracts exactly 2 shared blocks', count($blocks) === 2);
assertTest('Identifies pd:shared header', isset($blocks['header']));
assertTest('Identifies hh:shared footer (backward-compatibility alias)', isset($blocks['footer']));
assertTest('Parses custom active-class="nav-active"', ($blocks['header']['attributes']['active-class'] ?? '') === 'nav-active');
assertTest('Parses aria-current attribute', ($blocks['header']['attributes']['aria-current'] ?? '') === 'page');

// 2. Normalization Algorithm (comparable)
echo "\n--- 2. Normalization Algorithm (comparable) ---\n";

$homeNav = <<<HTML
<header class="main-header">
  <nav>
    <a href="/" class="active" aria-current="page">Home</a>
    <a href="/about.html">About</a>
    <a href="/contact">Contact</a>
  </nav>
</header>
HTML;

$aboutNav = <<<HTML
<header class="main-header">
  <nav>
    <a href="/">Home</a>
    <a href="/about" class="active" aria-current="page">About</a>
    <a href="/contact.html">Contact</a>
  </nav>
</header>
HTML;

$blogNav = <<<HTML
<header class="main-header">
  <nav>
    <a href="../">Home</a>
    <a href="../about.html">About</a>
    <a href="../contact.html">Contact</a>
  </nav>
</header>
HTML;

$normHome = SharedLayoutManager::normalize($homeNav, 'index.html');
$normAbout = SharedLayoutManager::normalize($aboutNav, 'about.html');
$normBlog = SharedLayoutManager::normalize($blogNav, 'blog/post.html');

assertTest('Normalized home has active class stripped', !str_contains($normHome, 'active'));
assertTest('Normalized home has aria-current stripped', !str_contains($normHome, 'aria-current'));
assertTest('Home and About blocks normalize to identical comparable string', $normHome === $normAbout);
assertTest('Subfolder relative links (../about.html) normalize to root-relative /about', str_contains($normBlog, 'href="/about"'));
assertTest('Nested blog nav normalizes identical to root nav', $normHome === $normBlog);

// 3. Multi-Page Site Setup & Drift Detection
echo "\n--- 3. Multi-Page Site Setup & Drift Detection ---\n";

$testSlug = 'layout-sync-test-' . time();
$siteDir = SITES_DIR . '/' . $testSlug;
mkdir($siteDir . '/blog', 0755, true);

$footerHtml = <<<HTML
<!-- pd:shared footer -->
<footer>
  <p>&copy; 2026 Sovereign Host. All rights reserved.</p>
</footer>
<!-- /pd:shared footer -->
HTML;

// index.html
file_put_contents($siteDir . '/index.html', <<<HTML
<!DOCTYPE html>
<html>
<head><title>Home</title></head>
<body>
<!-- pd:shared header -->
<header>
  <nav>
    <a href="/" class="active" aria-current="page">Home</a>
    <a href="/about">About</a>
    <a href="/contact">Contact</a>
  </nav>
</header>
<!-- /pd:shared header -->
<h1>Home Page</h1>
$footerHtml
</body>
</html>
HTML
);

// about.html (matching header)
file_put_contents($siteDir . '/about.html', <<<HTML
<!DOCTYPE html>
<html>
<head><title>About</title></head>
<body>
<!-- pd:shared header -->
<header>
  <nav>
    <a href="/">Home</a>
    <a href="/about" class="active" aria-current="page">About</a>
    <a href="/contact">Contact</a>
  </nav>
</header>
<!-- /pd:shared header -->
<h1>About Us</h1>
$footerHtml
</body>
</html>
HTML
);

// contact.html (DRIFTED header: outdated navigation with missing link or changed branding)
file_put_contents($siteDir . '/contact.html', <<<HTML
<!DOCTYPE html>
<html>
<head><title>Contact</title></head>
<body>
<!-- pd:shared header -->
<header>
  <nav>
    <a href="/">Old Home</a>
    <a href="/support">Old Support</a>
  </nav>
</header>
<!-- /pd:shared header -->
<h1>Contact Us</h1>
$footerHtml
</body>
</html>
HTML
);

// Register site in DB
$db = Database::getConnection();
$claimToken = 'claim-' . bin2hex(random_bytes(10));
$_SESSION['guest_claim_tokens'] = [$claimToken];
$stmt = $db->prepare("INSERT INTO sites (claim_token, slug, title, entry_file, current_version, file_count, total_size_bytes) VALUES (?, ?, ?, 'index.html', 1, 3, 2048)");
$stmt->execute([$claimToken, $testSlug, 'Layout Sync Test Site']);
$siteId = (int)$db->lastInsertId();
VersionManager::createSnapshot($siteId, $testSlug, 'Initial multi-page deployment');

// Scan and test drift detection
$inspection = SharedLayoutManager::inspectSite($testSlug);
assertTest('Inspection reports success', $inspection['success'] === true);
assertTest('Inspection detects 3 HTML files', $inspection['total_html_files'] === 3);
assertTest('Inspection detects drift = true', $inspection['has_drift'] === true);
assertTest('Inspection detects header has drift', ($inspection['blocks']['header']['has_drift'] ?? false) === true);
assertTest('Inspection reports contact.html as drifted page', str_contains($inspection['blocks']['header']['drift_summary'], 'contact.html'));
assertTest('Inspection detects footer is in sync across all pages', ($inspection['blocks']['footer']['has_drift'] ?? true) === false);

// 4. Shared Blocks Inspection API: GET /api/sites/{slug}/shared-blocks
echo "\n--- 4. Testing GET /api/sites/{slug}/shared-blocks API ---\n";

// Unauthorized access
$ch = curl_init("$baseUrl/api/sites/$testSlug/shared-blocks");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_exec($ch);
$unauthHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
assertTest('GET shared-blocks without auth returns 403', $unauthHttp === 403, "Got HTTP $unauthHttp");

// Authorized access with token
$ch = curl_init("$baseUrl/api/sites/$testSlug/shared-blocks?token=$claimToken");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$resApi = curl_exec($ch);
$apiHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$apiData = json_decode($resApi, true);

assertTest('GET shared-blocks with token returns 200', $apiHttp === 200, "Got HTTP $apiHttp");
assertTest('API reports has_drift = true', ($apiData['has_drift'] ?? false) === true);
assertTest('API reports header has_drift = true', ($apiData['blocks']['header']['has_drift'] ?? false) === true);
assertTest('API reports footer has_drift = false', ($apiData['blocks']['footer']['has_drift'] ?? true) === false);

// 5. Cross-Page Synchronization: POST /api/sites/{slug}/sync-shared
echo "\n--- 5. Testing POST /api/sites/{slug}/sync-shared API ---\n";

// Unauthorized sync
$ch = curl_init("$baseUrl/api/sites/$testSlug/sync-shared");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_exec($ch);
$unauthSyncHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
assertTest('POST sync-shared without auth returns 403', $unauthSyncHttp === 403, "Got HTTP $unauthSyncHttp");

// Authorized sync of header across all pages
$ch = curl_init("$baseUrl/api/sites/$testSlug/sync-shared?token=$claimToken");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'block_name' => 'header'
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$syncRes = curl_exec($ch);
$syncHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$syncData = json_decode($syncRes, true);

assertTest('POST sync-shared returns 200 OK', $syncHttp === 200, "Got HTTP $syncHttp");
assertTest('Sync response indicates success = true', ($syncData['success'] ?? false) === true);
assertTest('Sync affected all 3 pages', ($syncData['synced_count'] ?? 0) === 3);
assertTest('Sync generated new snapshot version v2', ($syncData['new_version'] ?? 0) === 2);

// Re-inspect site to verify drift has been eliminated
$reInspection = SharedLayoutManager::inspectSite($testSlug);
assertTest('After sync, site has_drift is false', $reInspection['has_drift'] === false);
assertTest('After sync, header has_drift is false', $reInspection['blocks']['header']['has_drift'] === false);
assertTest('Header summary states all 3 pages in sync', str_contains($reInspection['blocks']['header']['drift_summary'], 'All 3 pages in sync'));

// 6. Dynamic Route-Aware Active Link State Verification
echo "\n--- 6. Dynamic Route-Aware Active Link State Verification ---\n";

$indexContent = file_get_contents($siteDir . '/index.html');
$aboutContent = file_get_contents($siteDir . '/about.html');
$contactContent = file_get_contents($siteDir . '/contact.html');

// index.html: <a href="/"> should be active
assertTest('index.html has active class on / link', preg_match('/<a[^>]*href=["\']\/["\'][^>]*class=["\'][^"\']*active[^"\']*["\']/i', $indexContent) === 1);
assertTest('index.html has aria-current="page" on / link', preg_match('/<a[^>]*href=["\']\/["\'][^>]*aria-current=["\']page["\']/i', $indexContent) === 1);
assertTest('index.html does NOT have active class on /about link', preg_match('/<a[^>]*href=["\']\/about["\'][^>]*class=["\'][^"\']*active[^"\']*["\']/i', $indexContent) === 0);

// about.html: <a href="/about"> should be active
assertTest('about.html has active class on /about link', preg_match('/<a[^>]*href=["\']\/about["\'][^>]*class=["\'][^"\']*active[^"\']*["\']/i', $aboutContent) === 1);
assertTest('about.html has aria-current="page" on /about link', preg_match('/<a[^>]*href=["\']\/about["\'][^>]*aria-current=["\']page["\']/i', $aboutContent) === 1);
assertTest('about.html does NOT have active class on / link', preg_match('/<a[^>]*href=["\']\/["\'][^>]*class=["\'][^"\']*active[^"\']*["\']/i', $aboutContent) === 0);

// contact.html: <a href="/contact"> should be active
assertTest('contact.html was updated from Old Home to canonical navbar', str_contains($contactContent, 'Home') && !str_contains($contactContent, 'Old Home'));
assertTest('contact.html has active class on /contact link', preg_match('/<a[^>]*href=["\']\/contact["\'][^>]*class=["\'][^"\']*active[^"\']*["\']/i', $contactContent) === 1);
assertTest('contact.html has aria-current="page" on /contact link', preg_match('/<a[^>]*href=["\']\/contact["\'][^>]*aria-current=["\']page["\']/i', $contactContent) === 1);
assertTest('contact.html does NOT have active class on /about link', preg_match('/<a[^>]*href=["\']\/about["\'][^>]*class=["\'][^"\']*active[^"\']*["\']/i', $contactContent) === 0);

// 7. Cleanup Test Data
echo "\n--- 7. Cleaning Up Test Data ---\n";
SiteManager::deleteSite($testSlug);
$deletedSite = SiteManager::getSite($testSlug);
assertTest('Test site record removed from database', $deletedSite === null);
assertTest('Test site directory removed from disk', !is_dir($siteDir));

// Summary
echo "\n=== Test Results Summary ===\n";
$passed = count(array_filter($results, fn($r) => $r['status'] === 'PASS'));
$total = count($results);
echo "Total Tests: $total | Passed: $passed | Failed: " . ($total - $passed) . "\n";

if ($passed === $total) {
    echo "ALL PHASE 6 TESTS PASSED!\n";
    exit(0);
} else {
    echo "SOME TESTS FAILED!\n";
    exit(1);
}
