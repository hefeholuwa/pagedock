<?php
/**
 * PageDock - Physical Asset Decoupling Test Suite (Approach A)
 * Verifies:
 * 1. Extraction of <style> blocks into physical style.css on disk
 * 2. Replacement of <style> with <link rel="stylesheet" href="style.css">
 * 3. Extraction of executable inline <script> blocks into physical script.js
 * 4. Preservation of <script src="..."> and <script type="application/ld+json"> in HTML
 * 5. Multi-block consolidation (multiple styles/scripts combined)
 * 6. Idempotency (already decoupled sites remain untouched)
 * 7. Atomic multi-file saving via POST /api/editor/{slug}
 * 8. HTTP static file serving of style.css and script.js with correct MIME types
 * 9. Snapshot versioning and 1-click rollback of multi-file bundles
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/SiteManager.php';
require_once __DIR__ . '/../src/VersionManager.php';
require_once __DIR__ . '/../src/Auth.php';

echo "=== Running Web Editor Physical Asset Decoupling Test Suite ===\n\n";

$passCount = 0;
$failCount = 0;

function assertTest(bool $condition, string $message): void {
    global $passCount, $failCount;
    if ($condition) {
        echo "PASS: {$message}\n";
        $passCount++;
    } else {
        echo "FAIL: {$message}\n";
        $failCount++;
    }
}

// Ensure session exists
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$testSlug = 'test-decoupling-' . time();
$testSiteDir = SITES_DIR . '/' . $testSlug;

// 1. Create Monolithic Test HTML
$monolithicHtml = <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sovereign Test Landing Page</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #C7F547; --dark: #0d0f12; }
        body { background: var(--dark); color: #fff; font-family: 'Inter', sans-serif; }
    </style>
    <style>
        .hero { padding: 80px 20px; text-align: center; }
        .btn { background: var(--primary); color: #000; padding: 12px 24px; }
    </style>
    <script src="https://cdn.example.com/analytics.js"></script>
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "WebSite",
        "name": "Sovereign Test"
    }
    </script>
</head>
<body>
    <div class="hero">
        <h1>Decoupled Sovereignty</h1>
        <button id="cta-btn" class="btn">Take Action</button>
    </div>
    <script>
        console.log("App init starting...");
        const btn = document.getElementById('cta-btn');
    </script>
    <script>
        btn.addEventListener('click', () => alert('Clicked!'));
    </script>
</body>
</html>
HTML;

echo "--- 1. Testing Decoupling on Site Creation ---\n";
$created = SiteManager::createFromHtml($monolithicHtml, 'Sovereign Test', $testSlug);
assertTest($created['success'] === true, "Site created successfully with slug {$testSlug}");

// Verify disk structure
$htmlPath = $testSiteDir . '/index.html';
$cssPath = $testSiteDir . '/style.css';
$jsPath = $testSiteDir . '/script.js';

assertTest(file_exists($htmlPath), "index.html exists on disk");
assertTest(file_exists($cssPath), "style.css physically exists on disk");
assertTest(file_exists($jsPath), "script.js physically exists on disk");

$savedHtml = file_get_contents($htmlPath);
$savedCss = file_get_contents($cssPath);
$savedJs = file_get_contents($jsPath);

echo "\n--- 2. Verifying Pure HTML & Extracted Assets ---\n";
// index.html should NOT contain inline <style> tags
assertTest(!str_contains($savedHtml, '<style>'), "index.html contains zero <style> opening tags");
assertTest(!str_contains($savedHtml, '</style>'), "index.html contains zero </style> closing tags");

// index.html must have <link rel="stylesheet" href="style.css">
assertTest(str_contains($savedHtml, '<link rel="stylesheet" href="style.css">'), "index.html links to style.css");

// index.html must have <script src="script.js"></script>
assertTest(str_contains($savedHtml, '<script src="script.js"></script>'), "index.html links to script.js");

// External scripts and JSON-LD must remain untouched in index.html
assertTest(str_contains($savedHtml, 'https://cdn.example.com/analytics.js'), "External CDN script preserved in index.html");
assertTest(str_contains($savedHtml, 'application/ld+json'), "JSON-LD schema preserved in index.html");
assertTest(str_contains($savedHtml, '"@type": "WebSite"'), "JSON-LD schema content intact inside index.html");

// style.css must contain extracted CSS from both blocks
assertTest(str_contains($savedCss, '--primary: #C7F547;'), "style.css contains :root variables");
assertTest(str_contains($savedCss, '.hero { padding: 80px 20px; text-align: center; }'), "style.css contains second style block");

// script.js must contain extracted JS from both blocks in order
assertTest(str_contains($savedJs, 'console.log("App init starting...");'), "script.js contains first script block");
assertTest(str_contains($savedJs, "btn.addEventListener('click'"), "script.js contains second script block");

// script.js must NOT contain JSON-LD
assertTest(!str_contains($savedJs, 'schema.org'), "script.js does not contain JSON-LD");

echo "\n--- 3. Testing Decoupling Idempotency ---\n";
$decoupleAgain = SiteManager::decoupleAssets($testSlug, 'index.html', false);
assertTest($decoupleAgain['success'] === true, "Second decouple execution succeeded");
assertTest($decoupleAgain['decoupled'] === false, "No further decoupling needed (idempotent)");

echo "\n--- 4. Testing Atomic Multi-File Save (POST /api/editor/{slug}) ---\n";
$claimToken = $created['claim_token'];
$_SESSION['guest_claim_tokens'] = [$claimToken];

$baseUrl = 'http://localhost:8080';
$ch = curl_init("{$baseUrl}/api/editor/{$testSlug}?token={$claimToken}");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'files' => [
        'index.html' => "<!DOCTYPE html><html><head><link rel=\"stylesheet\" href=\"style.css\"></head><body><h1>Edited Headline</h1><script src=\"script.js\"></script></body></html>",
        'style.css' => "body { background: #000; color: #C7F547; }",
        'script.js' => "console.log('Updated JS v2');",
        '_redirects' => "/old-path / 301"
    ]
]));
$saveRespRaw = curl_exec($ch);
$saveHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

assertTest($saveHttpCode === 200, "POST /api/editor/{$testSlug} returns HTTP 200");
$saveResp = json_decode($saveRespRaw, true);
assertTest($saveResp['success'] === true, "Batch save response reports success=true");
assertTest(count($saveResp['files']) === 4, "Batch save updated all 4 files");
assertTest(($saveResp['version'] ?? 0) === 2, "Atomic batch save produced single version revision v2");

// Verify disk contents after batch save
assertTest(str_contains(file_get_contents($htmlPath), '<h1>Edited Headline</h1>'), "index.html updated on disk");
assertTest(str_contains(file_get_contents($cssPath), 'background: #000;'), "style.css updated on disk");
assertTest(str_contains(file_get_contents($jsPath), 'Updated JS v2'), "script.js updated on disk");
assertTest(file_exists($testSiteDir . '/_redirects'), "_redirects created on disk");

echo "\n--- 5. Testing HTTP Static File Serving & MIME Types ---\n";
// Test index.html
$ch = curl_init("{$baseUrl}/s/{$testSlug}");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$htmlResp = curl_exec($ch);
$htmlHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$htmlMime = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

assertTest($htmlHttpCode === 200, "GET /s/{$testSlug} returns HTTP 200");
assertTest(str_contains($htmlMime, 'text/html'), "index.html served with text/html MIME type ({$htmlMime})");
assertTest(str_contains($htmlResp, 'Edited Headline'), "Served HTML contains updated content");

// Test style.css
$ch = curl_init("{$baseUrl}/s/{$testSlug}/style.css");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$cssResp = curl_exec($ch);
$cssHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$cssMime = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

assertTest($cssHttpCode === 200, "GET /s/{$testSlug}/style.css returns HTTP 200");
assertTest(str_contains($cssMime, 'text/css'), "style.css served with text/css MIME type ({$cssMime})");
assertTest(str_contains($cssResp, 'background: #000;'), "Served CSS contains style rules");

// Test script.js
$ch = curl_init("{$baseUrl}/s/{$testSlug}/script.js");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$jsResp = curl_exec($ch);
$jsHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$jsMime = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

assertTest($jsHttpCode === 200, "GET /s/{$testSlug}/script.js returns HTTP 200");
assertTest(str_contains($jsMime, 'javascript'), "script.js served with javascript MIME type ({$jsMime})");
assertTest(str_contains($jsResp, 'Updated JS v2'), "Served JS contains script code");

echo "\n--- 6. Testing 1-Click Rollback of Multi-File Bundle ---\n";
$ch = curl_init("{$baseUrl}/api/sites/{$testSlug}/rollback?token={$claimToken}");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['version' => 1]));
$rollbackRespRaw = curl_exec($ch);
$rollbackHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

assertTest($rollbackHttpCode === 200, "POST /api/sites/{$testSlug}/rollback returns HTTP 200");
$rollbackResp = json_decode($rollbackRespRaw, true);
assertTest($rollbackResp['success'] === true, "Rollback reports success=true");
assertTest($rollbackResp['restored_version'] === 1, "Restored target is version v1");

// Verify that disk reverted to v1 contents
$restoredHtml = file_get_contents($htmlPath);
$restoredCss = file_get_contents($cssPath);
assertTest(str_contains($restoredHtml, 'Decoupled Sovereignty'), "index.html restored to v1 headline");
assertTest(str_contains($restoredCss, '--primary: #C7F547;'), "style.css restored to v1 colors");

echo "\n--- 7. Cleaning Up Test Data ---\n";
$deleted = SiteManager::deleteSite($testSlug);
assertTest($deleted['success'] === true, "Test site record removed from database");
assertTest(!is_dir($testSiteDir), "Test site directory removed from disk");

echo "\n=== Test Results Summary ===\n";
echo "Total Tests: " . ($passCount + $failCount) . " | Passed: {$passCount} | Failed: {$failCount}\n";
if ($failCount === 0) {
    echo "ALL PHYSICAL ASSET DECOUPLING TESTS PASSED!\n";
    exit(0);
} else {
    echo "SOME TESTS FAILED!\n";
    exit(1);
}
