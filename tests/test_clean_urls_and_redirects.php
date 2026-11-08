<?php
/**
 * Test Suite for Step 1: Clean URLs, Netlify _redirects Engine & Custom 404
 */

require_once dirname(__DIR__) . '/config/config.php';

$baseUrl = 'http://localhost:8080';
$results = [];

function assertTest($name, $condition, $details = '') {
    global $results;
    if ($condition) {
        $results[] = ['name' => $name, 'status' => 'PASS'];
        echo "✅ PASS: $name\n";
    } else {
        $results[] = ['name' => $name, 'status' => 'FAIL', 'details' => $details];
        echo "❌ FAIL: $name ($details)\n";
    }
}

echo "=== Running Step 1: Clean URLs & Redirects Tests ===\n\n";

// 1. Direct Unit Test of RedirectManager
echo "--- 1. RedirectManager Unit Tests ---\n";
$tempSiteDir = sys_get_temp_dir() . '/pd_test_site_' . time();
mkdir($tempSiteDir, 0755, true);

$redirectsContent = <<<EOT
# Sample Netlify style redirects
/old-about           /about              301
/temp-promo          /special            302
/blog/*              /articles/*         301
/?p=42               /hello-world        301
/external            https://example.com 302
EOT;
file_put_contents($tempSiteDir . '/_redirects', $redirectsContent);

$rules = RedirectManager::parse($tempSiteDir);
assertTest("Parsed 5 redirect rules", count($rules) === 5);

// Test exact match
$m1 = RedirectManager::match($rules, '/old-about');
assertTest("Exact match /old-about -> /about with 301", ($m1['target'] ?? '') === '/about' && ($m1['status'] ?? 0) === 301);

// Test 302 temporary redirect
$m2 = RedirectManager::match($rules, '/temp-promo');
assertTest("Temporary match /temp-promo -> /special with 302", ($m2['target'] ?? '') === '/special' && ($m2['status'] ?? 0) === 302);

// Test wildcard match
$m3 = RedirectManager::match($rules, '/blog/tech/deep-dive');
assertTest("Wildcard match /blog/* -> /articles/tech/deep-dive", ($m3['target'] ?? '') === '/articles/tech/deep-dive');

// Test query string match
$m4 = RedirectManager::match($rules, '/', ['p' => '42']);
assertTest("Query string match /?p=42 -> /hello-world", ($m4['target'] ?? '') === '/hello-world');

// Test external URL redirect
$m5 = RedirectManager::match($rules, '/external');
assertTest("External redirect target https://example.com", ($m5['target'] ?? '') === 'https://example.com');

unlink($tempSiteDir . '/_redirects');
rmdir($tempSiteDir);

// 2. Integration Tests via Live HTTP Server
echo "\n--- 2. End-to-End HTTP Integration Tests ---\n";

// Create a test site with multiple pages, _redirects, and 404.html
$testSlug = 'clean-url-test-' . time();
$siteDir = SITES_DIR . '/' . $testSlug;
mkdir($siteDir, 0755, true);

file_put_contents($siteDir . '/index.html', '<!DOCTYPE html><html><head><title>Home</title></head><body><h1>Welcome Home</h1><a href="https://google.com">External Link</a></body></html>');
file_put_contents($siteDir . '/about.html', '<!DOCTYPE html><html><head><title>About</title></head><body><h1>About Us Page</h1></body></html>');
file_put_contents($siteDir . '/contact.html', '<!DOCTYPE html><html><head><title>Contact</title></head><body><h1>Contact Us Page</h1></body></html>');
file_put_contents($siteDir . '/404.html', '<!DOCTYPE html><html><head><title>Custom 404</title></head><body><h1>My Brand Custom 404 Page</h1></body></html>');
file_put_contents($siteDir . '/_redirects', "/legacy-contact  /contact  301\n/promo/*  /about/*  302\n");

// Register in SQLite DB
$db = Database::getConnection();
$claimToken = 'claim-' . bin2hex(random_bytes(8));
$stmt = $db->prepare("INSERT INTO sites (claim_token, slug, title, entry_file, file_count, total_size_bytes) VALUES (?, ?, ?, ?, 5, 1024)");
$stmt->execute([$claimToken, $testSlug, 'Clean URL Test Site', 'index.html']);

// Test A: Requesting /s/{slug}/about (no .html) should resolve /about.html
$ch = curl_init("$baseUrl/s/$testSlug/about");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$body = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
assertTest("Clean URL /about resolves with 200 OK", $code === 200, "Code: $code");
assertTest("Clean URL /about serves About Us content", strpos($body, 'About Us Page') !== false);

// Test B: Requesting /s/{slug}/about.html should 301-redirect to /s/{slug}/about
$ch = curl_init("$baseUrl/s/$testSlug/about.html");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$redirectUrl = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
assertTest("Visiting /about.html returns 301 Redirect", $code === 301, "Code: $code");
assertTest("Redirect target is clean URL /about", str_ends_with($redirectUrl, "/s/$testSlug/about"), "Redirect URL: $redirectUrl");

// Test B2: Requesting /s/{slug}/about.html?utm_source=twitter&ref=blog should preserve query string
$ch = curl_init("$baseUrl/s/$testSlug/about.html?utm_source=twitter&ref=blog");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$redirectUrl = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
assertTest("Query string preserved on /about.html 301 redirect", $code === 301 && str_ends_with($redirectUrl, "/s/$testSlug/about?utm_source=twitter&ref=blog"), "Redirect URL: $redirectUrl");

// Test B3: Requesting /s/{slug}/index.html?tracking_id=xyz should 301-redirect to /s/{slug}?tracking_id=xyz
$ch = curl_init("$baseUrl/s/$testSlug/index.html?tracking_id=xyz");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$redirectUrl = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
assertTest("Visiting /index.html with query string redirects 301 to root preserving query", $code === 301 && str_ends_with($redirectUrl, "/s/$testSlug?tracking_id=xyz"), "Redirect URL: $redirectUrl");

// Test C: Netlify _redirects rule /legacy-contact -> /contact (301)
$ch = curl_init("$baseUrl/s/$testSlug/legacy-contact");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$redirectUrl = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
assertTest("_redirects rule returns 301 Redirect", $code === 301, "Code: $code");
assertTest("_redirects target is /contact", str_ends_with($redirectUrl, "/s/$testSlug/contact"), "Redirect URL: $redirectUrl");

// Test D: Custom 404 page rendering
$ch = curl_init("$baseUrl/s/$testSlug/page-that-does-not-exist");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$body = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
assertTest("Non-existent page returns HTTP 404", $code === 404, "Code: $code");
assertTest("Serves site's custom 404.html content", strpos($body, 'My Brand Custom 404 Page') !== false);

// Test E: Security - _redirects file must NOT be served directly
$ch = curl_init("$baseUrl/s/$testSlug/_redirects");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$body = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
assertTest("Accessing /_redirects returns 404", $code === 404, "Code: $code");
assertTest("_redirects contents are not leaked", strpos($body, 'legacy-contact') === false);

// Test F: Outbound link policy (rel="nofollow ugc")
$ch = curl_init("$baseUrl/s/$testSlug/");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$body = curl_exec($ch);
assertTest("Outbound link receives rel='nofollow ugc'", strpos($body, 'rel="nofollow ugc"') !== false, "Body: $body");

// Test G: Production .htaccess verification
$htaccessPath = dirname(__DIR__) . '/public/.htaccess';
assertTest("Production .htaccess exists in public/", file_exists($htaccessPath));
if (file_exists($htaccessPath)) {
    $htaccessContent = file_get_contents($htaccessPath);
    assertTest(".htaccess contains RewriteEngine On", strpos($htaccessContent, 'RewriteEngine On') !== false);
    assertTest(".htaccess contains index.php [QSA,L] routing rule", strpos($htaccessContent, 'RewriteRule ^ index.php [QSA,L]') !== false);
    assertTest(".htaccess blocks direct access to _redirects", strpos($htaccessContent, 'RewriteRule _redirects$ - [F,L]') !== false);
}

// Cleanup test site
$files = glob($siteDir . '/*');
foreach ($files as $f) unlink($f);
rmdir($siteDir);
$db->exec("DELETE FROM sites WHERE slug = '$testSlug'");

echo "\n============================================\n";
$total = count($results);
$passed = count(array_filter($results, fn($r) => $r['status'] === 'PASS'));
echo "RESULTS: $passed / $total TESTS PASSED\n";
echo "============================================\n";
