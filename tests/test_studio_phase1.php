<?php
/**
 * Test Suite: PageDock Visual Studio Phase 1 (Route, Shell & Integrations)
 */

require_once __DIR__ . '/../config/config.php';

echo "=== Running PageDock Visual Studio Phase 1 Test Suite ===\n\n";

$passCount = 0;
$failCount = 0;

function assertTest(string $desc, bool $condition): void {
    global $passCount, $failCount;
    if ($condition) {
        echo "PASS: {$desc}\n";
        $passCount++;
    } else {
        echo "FAIL: {$desc}\n";
        $failCount++;
    }
}

// 1. Create a test site
$slug = 'test-studio-' . substr(bin2hex(random_bytes(4)), 0, 8);
$initialHtml = '<!DOCTYPE html><html><head><title>Studio Site</title></head><body><header class="hero"><h1 class="hero-title">Studio Launch</h1><p class="hero-desc">Testing visual studio integration.</p></header></body></html>';
$created = SiteManager::createFromHtml($initialHtml, 'Studio Launch Test', $slug);

assertTest('Test site created successfully for Studio tests', $created['success'] === true);
$site = SiteManager::getSite($slug);
assertTest('Site retrieved from database', !empty($site));

// 2. Test Studio Route: GET /studio/{slug}
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/studio/' . $slug;
$_SERVER['HTTP_HOST'] = 'localhost:8080';

// Capture output of Router::handle()
ob_start();
Router::handle();
$studioOutput = ob_get_clean();

assertTest('GET /studio/{slug} renders HTML output', !empty($studioOutput));
assertTest('Studio output contains Visual Studio title', str_contains($studioOutput, 'Visual Studio — Studio Launch Test'));
assertTest('Studio output contains left dock panel (#studio-panel)', str_contains($studioOutput, 'id="studio-panel"'));
assertTest('Studio output contains center canvas (#studio-canvas)', str_contains($studioOutput, 'id="studio-canvas"'));
assertTest('Studio output contains preview iframe (#studio-preview-iframe)', str_contains($studioOutput, 'id="studio-preview-iframe"'));
assertTest('Studio output contains responsive switcher buttons', str_contains($studioOutput, 'data-width="768px"') && str_contains($studioOutput, 'data-width="375px"'));
assertTest('Studio output contains widget palette', str_contains($studioOutput, 'panel-widgets-view') && str_contains($studioOutput, 'Section Blueprints'));
assertTest('Studio output contains 3-tab inspector controls', str_contains($studioOutput, 'data-subtab="content"') && str_contains($studioOutput, 'data-subtab="style"') && str_contains($studioOutput, 'data-subtab="advanced"'));
assertTest('Studio output contains script assets', str_contains($studioOutput, 'assets/js/studio.js') && str_contains($studioOutput, 'assets/css/studio.css'));

// 3. Test 404 on Invalid Slug
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/studio/non-existent-random-slug-999';

ob_start();
Router::handle();
$notFoundOutput = ob_get_clean();
assertTest('GET /studio/non-existent returns 404 view', str_contains($notFoundOutput, '404') || str_contains($notFoundOutput, 'Not Found'));

// 4. Test Code Editor Topbar Links to Studio
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/editor/' . $slug;

ob_start();
Router::handle();
$editorOutput = ob_get_clean();
assertTest('Code Editor topbar contains link to /studio/' . $slug, str_contains($editorOutput, 'studio/' . $slug));

// 5. Cleanup
SiteManager::deleteSite($slug);
assertTest('Test site purged cleanly', SiteManager::getSite($slug) === null);

echo "\n=== Visual Studio Phase 1 Test Summary ===\n";
echo "Total: " . ($passCount + $failCount) . " | Passed: {$passCount} | Failed: {$failCount}\n";

if ($failCount > 0) {
    echo "SOME TESTS FAILED!\n";
    exit(1);
} else {
    echo "ALL VISUAL STUDIO PHASE 1 TESTS PASSED!\n";
    exit(0);
}

