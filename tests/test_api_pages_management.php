<?php
/**
 * Test Suite: Pages Management REST API Endpoints
 * Tests GET, POST, and DELETE /api/sites/{slug}/pages
 */

require_once dirname(__DIR__) . '/config/config.php';

function assert_true($cond, $msg) {
    if (!$cond) {
        echo "[FAIL] $msg\n";
        exit(1);
    }
    echo "[PASS] $msg\n";
}

echo "=== Running Pages Management API Test Suite ===\n\n";

$testSlug = 'test-api-pages-' . time();
$siteRes = SiteManager::createFromHtml(
    '<!DOCTYPE html><html><head><title>Test API Pages</title></head><body><h1>Hello</h1></body></html>',
    'Test API Pages',
    $testSlug
);
assert_true($siteRes['success'], 'Created test site');

$site = SiteManager::getSite($testSlug);
$token = $site['claim_token'];
$baseUrl = 'http://localhost:8080';

// Test 1: GET /api/sites/{slug}/pages
$ch = curl_init("{$baseUrl}/api/sites/{$testSlug}/pages?token={$token}");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$res = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

assert_true($code === 200, "GET /api/sites/{slug}/pages returned HTTP 200 (got $code)");
$data = json_decode($res, true);
assert_true($data['success'] === true, 'Response marked success');
assert_true(is_array($data['pages']) && count($data['pages']) >= 1, 'Returns list of pages containing at least index.html');
assert_true($data['pages'][0]['file'] === 'index.html', 'First page is index.html');
assert_true($data['pages'][0]['isEntry'] === true, 'index.html marked as entry page');

// Test 2: POST /api/sites/{slug}/pages (create about.html)
$payload = json_encode([
    'title' => 'About Us',
    'filename' => 'about.html',
    'layout' => 'inherit'
]);
$ch = curl_init("{$baseUrl}/api/sites/{$testSlug}/pages?token={$token}");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$res = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

assert_true($code === 200, "POST /api/sites/{slug}/pages returned HTTP 200 (got $code)");
$createData = json_decode($res, true);
assert_true($createData['success'] === true, 'Page creation reported success');
assert_true($createData['file'] === 'about.html', 'Created file is about.html');

$siteDir = realpath(SITES_DIR . '/' . $testSlug);
assert_true(file_exists($siteDir . '/about.html'), 'about.html physically exists on disk');

// Test 3: POST /api/sites/{slug}/pages/delete (attempt deleting index.html - must fail)
$ch = curl_init("{$baseUrl}/api/sites/{$testSlug}/pages/delete?token={$token}");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['file' => 'index.html']));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$res = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

assert_true($code === 400, "Deleting index.html returns HTTP 400 (got $code)");
$delEntry = json_decode($res, true);
assert_true($delEntry['success'] === false, 'Deleting index.html was forbidden');

// Test 4: POST /api/sites/{slug}/pages/delete (delete about.html)
$ch = curl_init("{$baseUrl}/api/sites/{$testSlug}/pages/delete?token={$token}");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['file' => 'about.html']));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$res = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

assert_true($code === 200, "Deleting about.html returns HTTP 200 (got $code)");
$delRes = json_decode($res, true);
assert_true($delRes['success'] === true, 'about.html deleted successfully');
assert_true(!file_exists($siteDir . '/about.html'), 'about.html no longer on disk');

// Clean up
SiteManager::deleteSite($testSlug);

echo "\nAll Pages API tests passed successfully!\n";
