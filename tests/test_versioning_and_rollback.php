<?php
/**
 * Test Suite for Phase 5: Snapshot Versioning & Rollback Engine
 * Tests immutable revisions, snapshot creation, version listing API,
 * 1-click rollback, historical restoration, audit trails, and security.
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

echo "=== Running Phase 5: Snapshot Versioning & Rollback Test Suite ===\n\n";

// 1. Initial Deployment & v1 Snapshot Creation
echo "--- 1. Testing Initial Site Deployment & v1 Snapshot ---\n";
$testSlug = 'version-test-' . time();
$initialHtml = "<!DOCTYPE html><html><head><title>Version 1 Title</title></head><body><h1>Welcome to Version 1</h1><p>Original deployment</p></body></html>";

$deployRes = SiteManager::createFromHtml($initialHtml, 'Version Test Site', $testSlug);
assertTest('Site deployment succeeds', $deployRes['success'] === true);
assertTest('Deploy response contains slug', $deployRes['slug'] === $testSlug);
assertTest('Deploy response reports version 1', isset($deployRes['version']) && $deployRes['version'] === 1);

$claimToken = $deployRes['claim_token'];
$site = SiteManager::getSite($testSlug);
assertTest('Site record exists in database', $site !== null);
assertTest('Site current_version is initialized to 1', (int)($site['current_version'] ?? 0) === 1);

// Verify v1 snapshot on disk
$siteDir = SITES_DIR . '/' . $testSlug;
$v1Dir = $siteDir . '/.snapshots/v1';
assertTest('Directory .snapshots/v1 exists on disk', is_dir($v1Dir));
assertTest('Snapshot .snapshots/v1/index.html exists', file_exists($v1Dir . '/index.html'));
assertTest('Snapshot v1 contains initial HTML', file_exists($v1Dir . '/index.html') && file_get_contents($v1Dir . '/index.html') === $initialHtml);

// Verify site_versions table record for v1
$db = Database::getConnection();
$stmt = $db->prepare("SELECT * FROM site_versions WHERE site_id = ? ORDER BY version_number ASC");
$stmt->execute([$site['id']]);
$versions = $stmt->fetchAll();
assertTest('site_versions has exactly 1 row initially', count($versions) === 1);
assertTest('Version 1 row has version_number = 1', (int)($versions[0]['version_number'] ?? 0) === 1);
assertTest('Version 1 row has summary', !empty($versions[0]['summary']));

// 2. Editor Update & v2 Snapshot Creation
echo "\n--- 2. Testing Editor Update & v2 Snapshot ---\n";
$v2Html = "<!DOCTYPE html><html><head><title>Version 2 Title</title></head><body><h1>Welcome to Version 2</h1><p>First update</p></body></html>";
$updateRes = SiteManager::updateSiteHtml($testSlug, $v2Html);
assertTest('Site update succeeds', $updateRes['success'] === true);
assertTest('Site update reports version 2', isset($updateRes['version']) && $updateRes['version'] === 2);

$site = SiteManager::getSite($testSlug);
assertTest('Site current_version is updated to 2', (int)($site['current_version'] ?? 0) === 2);

$v2Dir = $siteDir . '/.snapshots/v2';
assertTest('Directory .snapshots/v2 exists on disk', is_dir($v2Dir));
assertTest('Snapshot .snapshots/v2/index.html exists', file_exists($v2Dir . '/index.html'));
assertTest('Snapshot v2 contains v2 HTML', file_exists($v2Dir . '/index.html') && file_get_contents($v2Dir . '/index.html') === $v2Html);

// Verify Immutability: v1 snapshot must NOT be modified
assertTest('Snapshot v1 remains immutable with original v1 HTML', file_get_contents($v1Dir . '/index.html') === $initialHtml);

// Verify live index.html matches v2
assertTest('Live site index.html matches v2 HTML', file_get_contents($siteDir . '/index.html') === $v2Html);

// 3. Editor Update & v3 Snapshot Creation
echo "\n--- 3. Testing Third Update & v3 Snapshot ---\n";
$v3Html = "<!DOCTYPE html><html><head><title>Version 3 Title</title></head><body><h1>Welcome to Version 3</h1><p>Second update</p></body></html>";
$updateRes3 = SiteManager::updateSiteHtml($testSlug, $v3Html);
assertTest('Third update reports version 3', isset($updateRes3['version']) && $updateRes3['version'] === 3);

$site = SiteManager::getSite($testSlug);
assertTest('Site current_version is 3', (int)($site['current_version'] ?? 0) === 3);

// 4. Version Listing API: GET /api/sites/{slug}/versions
echo "\n--- 4. Testing Version Listing API ---\n";

// 4a. Unauthorized attempt (no token, no session)
$ch = curl_init("$baseUrl/api/sites/$testSlug/versions");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$unauthRes = curl_exec($ch);
$unauthHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
assertTest('GET /api/sites/{slug}/versions without auth returns 403', $unauthHttp === 403, "Got HTTP $unauthHttp");

// 4b. Authorized attempt with token query parameter
$ch = curl_init("$baseUrl/api/sites/$testSlug/versions?token=$claimToken");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$authRes = curl_exec($ch);
$authHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);

$listData = json_decode($authRes, true);
assertTest('GET /api/sites/{slug}/versions with token returns 200', $authHttp === 200, "Got HTTP $authHttp");
assertTest('API response reports success: true', ($listData['success'] ?? false) === true);
assertTest('API response reports current_version = 3', ($listData['current_version'] ?? 0) === 3);
assertTest('API response contains 3 versions', isset($listData['versions']) && count($listData['versions']) === 3);
assertTest('Versions are sorted descending (v3, v2, v1)', 
    isset($listData['versions'][0]['version_number']) &&
    $listData['versions'][0]['version_number'] === 3 &&
    $listData['versions'][1]['version_number'] === 2 &&
    $listData['versions'][2]['version_number'] === 1
);

// 5. Restore Specific Version: POST /api/sites/{slug}/versions/1/restore
echo "\n--- 5. Testing Specific Version Restore (Rollback to v1) ---\n";

// 5a. Unauthorized restore
$ch = curl_init("$baseUrl/api/sites/$testSlug/versions/1/restore");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$unauthRbRes = curl_exec($ch);
$unauthRbHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
assertTest('POST restore without auth returns 403', $unauthRbHttp === 403, "Got HTTP $unauthRbHttp");

// 5b. Restore non-existent version v999
$ch = curl_init("$baseUrl/api/sites/$testSlug/versions/999/restore?token=$claimToken");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$badRbRes = curl_exec($ch);
$badRbHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$badRbData = json_decode($badRbRes, true);
assertTest('POST restore non-existent version returns 400', $badRbHttp === 400, "Got HTTP $badRbHttp");
assertTest('Error message indicates revision does not exist', str_contains($badRbData['error'] ?? '', 'does not exist'));

// 5c. Valid restore to v1
$ch = curl_init("$baseUrl/api/sites/$testSlug/versions/1/restore?token=$claimToken");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$restoreRes = curl_exec($ch);
$restoreHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$restoreData = json_decode($restoreRes, true);

assertTest('Restore to v1 returns 200', $restoreHttp === 200, "Got HTTP $restoreHttp");
assertTest('Restore response reports success: true', ($restoreData['success'] ?? false) === true);
assertTest('Restore reports restored_version = 1', ($restoreData['restored_version'] ?? 0) === 1);
assertTest('Restore creates new audit version v4', ($restoreData['new_version'] ?? 0) === 4);

// Verify live file has been reverted to v1 content
$liveHtmlAfterRestore = file_get_contents($siteDir . '/index.html');
assertTest('Live site index.html now contains restored v1 HTML', $liveHtmlAfterRestore === $initialHtml);

// Verify live HTTP serving at /s/{slug} serves v1
$ch = curl_init("$baseUrl/s/$testSlug");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$servedContent = curl_exec($ch);
$servedHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
assertTest('Live endpoint /s/{slug} returns HTTP 200', $servedHttp === 200);
assertTest('Live endpoint serves restored v1 HTML', str_contains($servedContent, 'Welcome to Version 1'));

// 6. 1-Click Rollback Endpoint: POST /api/sites/{slug}/rollback
echo "\n--- 6. Testing 1-Click Rollback Endpoint ---\n";

// 6a. Rollback to version 2 explicitly
$ch = curl_init("$baseUrl/api/sites/$testSlug/rollback?token=$claimToken");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['version' => 2]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$rb2Res = curl_exec($ch);
$rb2Http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$rb2Data = json_decode($rb2Res, true);

assertTest('1-click rollback with target version returns 200', $rb2Http === 200, "Got HTTP $rb2Http");
assertTest('Rollback reports restored_version = 2', ($rb2Data['restored_version'] ?? 0) === 2);
assertTest('Rollback creates new audit version v5', ($rb2Data['new_version'] ?? 0) === 5);
assertTest('Live index.html reverted to v2 HTML', file_get_contents($siteDir . '/index.html') === $v2Html);

// 6b. Default 1-click rollback (no payload -> rolls back to previous version: current - 1)
// Current is 5, so default target should be version 4 (which was the v1 restore)
$ch = curl_init("$baseUrl/api/sites/$testSlug/rollback?token=$claimToken");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, '{}');
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$defRbRes = curl_exec($ch);
$defRbHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$defRbData = json_decode($defRbRes, true);

assertTest('Default 1-click rollback without version param returns 200', $defRbHttp === 200, "Got HTTP $defRbHttp");
assertTest('Default rollback restored target version 4', ($defRbData['restored_version'] ?? 0) === 4);
assertTest('Default rollback creates new version v6', ($defRbData['new_version'] ?? 0) === 6);

// 7. Security: Snapshot Directory Public Access Prohibition
echo "\n--- 7. Testing Security & Snapshot Isolation ---\n";

$ch = curl_init("$baseUrl/s/$testSlug/.snapshots/v1/index.html");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$secContent = curl_exec($ch);
$secHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
assertTest('Public access to /.snapshots/v1/index.html is blocked (HTTP 404)', $secHttp === 404, "Got HTTP $secHttp");

$ch = curl_init("$baseUrl/s/$testSlug/.snapshots/");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$secDirRes = curl_exec($ch);
$secDirHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
assertTest('Public access to /.snapshots/ folder is blocked (HTTP 404)', $secDirHttp === 404, "Got HTTP $secDirHttp");

// Verify all 6 snapshot directories exist and maintain file integrity
for ($v = 1; $v <= 6; $v++) {
    $snapDir = $siteDir . "/.snapshots/v$v";
    assertTest("Snapshot folder .snapshots/v$v exists on disk", is_dir($snapDir));
    assertTest("Snapshot file .snapshots/v$v/index.html exists", file_exists($snapDir . '/index.html'));
}

// 8. Cleanup Test Site
echo "\n--- 8. Cleaning Up Test Data ---\n";
SiteManager::deleteSite($testSlug);
$deletedSite = SiteManager::getSite($testSlug);
assertTest('Test site record removed from database', $deletedSite === null);
assertTest('Site directory removed from disk', !is_dir($siteDir));

// Summary
echo "\n=== Test Results Summary ===\n";
$passed = count(array_filter($results, fn($r) => $r['status'] === 'PASS'));
$total = count($results);
echo "Total Tests: $total | Passed: $passed | Failed: " . ($total - $passed) . "\n";

if ($passed === $total) {
    echo "ALL PHASE 5 TESTS PASSED!\n";
    exit(0);
} else {
    echo "SOME TESTS FAILED!\n";
    exit(1);
}
