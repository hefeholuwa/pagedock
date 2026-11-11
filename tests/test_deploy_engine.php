<?php
/**
 * Test Suite for Pillar 1: Ingestion Engine & Surge/Redoost Deployment
 */

require_once dirname(__DIR__) . '/config/config.php';

function assertTest($name, $condition, $details = '') {
    if ($condition) {
        echo "✅ PASS: $name\n";
    } else {
        echo "❌ FAIL: $name ($details)\n";
        exit(1);
    }
}

echo "=== Testing Ingestion Engine (Pillar 1) ===\n\n";

// 1. Test Single HTML Deployment
$html = '<!DOCTYPE html><html><head><title>Surge Test</title></head><body><h1>Hello World</h1></body></html>';
$slug = 'test-deploy-' . bin2hex(random_bytes(3));

$res = SiteManager::deployFromHtml($html, 'Surge Test Site', $slug);
assertTest('deployFromHtml succeeded', $res['success'] === true);
assertTest('Allocated expected slug', $res['slug'] === $slug);
assertTest('Generated claim token', !empty($res['claim_token']));
assertTest('Live URL returned', str_contains($res['url'], '/s/' . $slug));

// 2. Test Updating Existing Site with Authority (via claim_token in $_SERVER)
$_SERVER['HTTP_X_CLAIM_TOKEN'] = $res['claim_token'];
$updatedHtml = '<!DOCTYPE html><html><head><title>Surge Test v2</title></head><body><h1>Hello World V2</h1></body></html>';
$resUpdate = SiteManager::deployFromHtml($updatedHtml, 'Surge Test Site v2', $slug);
assertTest('Authorized update succeeded', $resUpdate['success'] === true);
assertTest('Updated flag set', $resUpdate['updated'] === true);
assertTest('Version bumped to v2', $resUpdate['version'] === 2);

// Verify file on disk updated
$onDisk = file_get_contents(SITES_DIR . '/' . $slug . '/index.html');
assertTest('On-disk content updated', str_contains($onDisk, 'Hello World V2'));

// 3. Test Unauthorized Update Attempt (Different Token, fresh session)
$_SESSION = [];
$_SERVER['HTTP_X_CLAIM_TOKEN'] = 'invalid-token-xyz';
$resBad = SiteManager::deployFromHtml($updatedHtml, 'Hacked', $slug);
assertTest('Unauthorized update rejected with status 403', ($resBad['status_code'] ?? 0) === 403);
assertTest('Error message mentions already claimed', str_contains($resBad['error'], 'already claimed'));

// 4. Test ZIP Deployment
$tmpZip = tempnam(sys_get_temp_dir(), 'pd_test_') . '.zip';
$zip = new ZipArchive();
$zip->open($tmpZip, ZipArchive::CREATE);
$zip->addFromString('index.html', '<h1>Zip Home</h1>');
$zip->addFromString('about.html', '<h1>About Page</h1>');
$zip->addFromString('style.css', 'body { color: red; }');
$zip->close();

$zipSlug = 'test-zip-deploy-' . bin2hex(random_bytes(3));
unset($_SERVER['HTTP_X_CLAIM_TOKEN']);
$resZip = SiteManager::deployFromZip($tmpZip, 'Zip Site', $zipSlug);
@unlink($tmpZip);

assertTest('deployFromZip succeeded', $resZip['success'] === true);
assertTest('3 files extracted', $resZip['file_count'] === 3);

// Cleanup test sites
$db = Database::getConnection();
$db->prepare("DELETE FROM sites WHERE slug IN (?, ?)")->execute([$slug, $zipSlug]);
exec("rm -rf " . escapeshellarg(SITES_DIR . '/' . $slug));
exec("rm -rf " . escapeshellarg(SITES_DIR . '/' . $zipSlug));

echo "\n✨ ALL PILLAR 1 (INGESTION ENGINE) TESTS PASSED!\n";
