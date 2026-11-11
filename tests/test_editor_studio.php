<?php
/**
 * Test Suite for Pillar 2: Studio & Responsive Preview Engine
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

echo "=== Testing Studio & Responsive Canvas (Pillar 2) ===\n\n";

$slug = 'test-studio-' . bin2hex(random_bytes(3));
$html = '<!DOCTYPE html><html><head><title>Studio Site</title><link rel="stylesheet" href="style.css"></head><body><h1>Studio Content</h1><script src="script.js"></script></body></html>';

// 1. Create site
$res = SiteManager::createFromHtml($html, 'Studio Test Site', $slug);
assertTest('Site created successfully', $res['success'] === true);

// Add auxiliary files to test multi-file tabs
$siteDir = SITES_DIR . '/' . $slug;
file_put_contents($siteDir . '/style.css', 'body { background: #000; color: #fff; }');
file_put_contents($siteDir . '/script.js', 'console.log("Studio alive");');

// 2. Test getSiteFileList
$files = SiteManager::getSiteFileList($slug);
assertTest('Site file list contains index.html', in_array('index.html', $files));
assertTest('Site file list contains style.css', in_array('style.css', $files));
assertTest('Site file list contains script.js', in_array('script.js', $files));
assertTest('index.html is first file', $files[0] === 'index.html');

// 3. Test getSiteHtml for auxiliary files
$css = SiteManager::getSiteHtml($slug, 'style.css');
assertTest('getSiteHtml retrieves style.css', str_contains($css, 'background: #000'));

$js = SiteManager::getSiteHtml($slug, 'script.js');
assertTest('getSiteHtml retrieves script.js', str_contains($js, 'Studio alive'));

// 4. Test updateSiteFiles (Publish from Studio)
$_SERVER['HTTP_X_CLAIM_TOKEN'] = $res['claim_token'];
$updatePayload = [
    'style.css' => 'body { background: #111; color: #C7F547; }',
    'index.html' => '<!DOCTYPE html><html><head><title>Studio Updated</title></head><body><h1>Updated Title</h1></body></html>'
];

$resUpdate = SiteManager::updateSiteFiles($slug, $updatePayload, 'Studio manual publish');
assertTest('updateSiteFiles succeeded', $resUpdate['success'] === true);
assertTest('Version bumped to v2', $resUpdate['version'] === 2);

// Verify files on disk
$newCss = file_get_contents($siteDir . '/style.css');
assertTest('Disk style.css updated', str_contains($newCss, '#C7F547'));

$newHtml = file_get_contents($siteDir . '/index.html');
assertTest('Disk index.html updated', str_contains($newHtml, 'Updated Title'));

// Cleanup
$db = Database::getConnection();
$db->prepare("DELETE FROM sites WHERE slug = ?")->execute([$slug]);
exec("rm -rf " . escapeshellarg($siteDir));

echo "\n✨ ALL PILLAR 2 (STUDIO ENGINE) TESTS PASSED!\n";
