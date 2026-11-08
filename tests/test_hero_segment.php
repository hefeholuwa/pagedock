<?php
/**
 * End-to-end verification script for the Hero Deployment Segment:
 * - Upload tab (.html and .zip)
 * - Custom title & slug handling
 * - Paste tab
 * - Demo tab
 * - Deployed site serving (/s/{slug})
 * - Live editor access (/editor/{slug})
 * - Form submission endpoint (/api/forms/{slug})
 */

$baseUrl = 'http://localhost:8080';
$results = [];

function assertTest($name, $condition, $details = '') {
    global $results;
    if ($condition) {
        $results[] = ['name' => $name, 'status' => 'PASS', 'details' => $details];
        echo "✅ PASS: $name\n";
    } else {
        $results[] = ['name' => $name, 'status' => 'FAIL', 'details' => $details];
        echo "❌ FAIL: $name ($details)\n";
    }
}

// 1. Verify Home Page HTML contains all segment elements
echo "--- 1. Testing Home Page Segment Markup ---\n";
$homeHtml = file_get_contents($baseUrl . '/');
assertTest("Home page responds with HTTP 200", $homeHtml !== false);
assertTest("Contains badge 'Instant Static Web Hosting'", strpos($homeHtml, 'Instant Static Web Hosting') !== false);
assertTest("Contains title 'Host your HTML.'", strpos($homeHtml, 'Host your HTML.') !== false);
assertTest("Contains tab-btn for upload-tab", strpos($homeHtml, 'data-tab="upload-tab"') !== false);
assertTest("Contains tab-btn for paste-tab", strpos($homeHtml, 'data-tab="paste-tab"') !== false);
assertTest("Contains tab-btn for demo-tab", strpos($homeHtml, 'data-tab="demo-tab"') !== false);
assertTest("Contains file input (.html, .htm, .zip)", strpos($homeHtml, 'accept=".html,.htm,.zip"') !== false);
assertTest("Contains site title input", strpos($homeHtml, 'id="upload-title"') !== false);
assertTest("Contains custom slug input with /s/ prefix", strpos($homeHtml, 'id="upload-slug"') !== false && strpos($homeHtml, 'class="slug-prefix"') !== false);
assertTest("Contains 'Deploy Website' submit button", strpos($homeHtml, 'id="upload-submit-btn"') !== false);
assertTest("Contains deploy-status progress indicator", strpos($homeHtml, 'id="deploy-status"') !== false);
assertTest("Contains deploy-success container with live link & copy button", strpos($homeHtml, 'id="deploy-success"') !== false && strpos($homeHtml, 'id="copy-url-btn"') !== false);

// 2. Test Single HTML File Upload
echo "\n--- 2. Testing HTML File Upload via /api/upload ---\n";
$tempHtmlFile = tempnam(sys_get_temp_dir(), 'pd_html_') . '.html';
file_put_contents($tempHtmlFile, "<!DOCTYPE html><html><head><title>Test HTML Upload</title></head><body><h1>Uploaded HTML Success</h1><p>Running on PageDock</p></body></html>");

$ch = curl_init($baseUrl . '/api/upload');
$cfile = new CURLFile($tempHtmlFile, 'text/html', 'test-upload.html');
$postData = [
    'file' => $cfile,
    'title' => 'My HTML Test Site',
    'slug' => 'test-html-slug-' . time()
];
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$responseRaw = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
unlink($tempHtmlFile);

$resp = json_decode($responseRaw, true);
assertTest("Upload single HTML returns 200 OK", $httpCode === 200, "HTTP $httpCode: $responseRaw");
assertTest("Upload single HTML response contains success=true", ($resp['success'] ?? false) === true);
assertTest("Upload single HTML custom slug respected", strpos($resp['slug'] ?? '', 'test-html-slug-') === 0);

if (!empty($resp['slug'])) {
    $deployedSlug = $resp['slug'];
    $siteContent = file_get_contents($baseUrl . '/s/' . $deployedSlug);
    assertTest("Deployed HTML site is live and reachable at /s/{slug}", strpos($siteContent, 'Uploaded HTML Success') !== false);
    
    // Check editor
    $editorHtml = file_get_contents($baseUrl . '/editor/' . $deployedSlug);
    assertTest("Editor loads correctly for deployed HTML site", strpos($editorHtml, 'Uploaded HTML Success') !== false);
}

// 3. Test ZIP Archive Upload
echo "\n--- 3. Testing ZIP Archive Upload via /api/upload ---\n";
$tempZipFile = tempnam(sys_get_temp_dir(), 'pd_zip_') . '.zip';
$zip = new ZipArchive();
if ($zip->open($tempZipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
    $zip->addFromString('index.html', '<!DOCTYPE html><html><head><title>ZIP Site</title><link rel="stylesheet" href="style.css"></head><body><h1>ZIP Archive Success</h1></body></html>');
    $zip->addFromString('style.css', 'body { background: #111; color: #fff; }');
    $zip->addFromString('about.html', '<h1>About this ZIP site</h1>');
    $zip->close();
}

$ch = curl_init($baseUrl . '/api/upload');
$cfile = new CURLFile($tempZipFile, 'application/zip', 'test-bundle.zip');
$zipSlug = 'test-zip-slug-' . time();
$postData = [
    'file' => $cfile,
    'title' => 'My ZIP Multi-Page Site',
    'slug' => $zipSlug
];
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$responseRaw = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
unlink($tempZipFile);

$resp = json_decode($responseRaw, true);
assertTest("Upload ZIP returns 200 OK", $httpCode === 200, "HTTP $httpCode: $responseRaw");
assertTest("Upload ZIP response contains success=true", ($resp['success'] ?? false) === true);

if (!empty($resp['slug'])) {
    $deployedZipSlug = $resp['slug'];
    $siteContent = file_get_contents($baseUrl . '/s/' . $deployedZipSlug);
    assertTest("Deployed ZIP index.html is live and reachable", strpos($siteContent, 'ZIP Archive Success') !== false);
    
    $cssContent = file_get_contents($baseUrl . '/s/' . $deployedZipSlug . '/style.css');
    assertTest("Deployed ZIP asset style.css is served correctly", strpos($cssContent, 'background: #111') !== false);

    $context = stream_context_create(['http' => ['follow_location' => 1]]);
    $aboutContent = @file_get_contents($baseUrl . '/s/' . $deployedZipSlug . '/about', false, $context) ?: @file_get_contents($baseUrl . '/s/' . $deployedZipSlug . '/about.html', false, $context);
    assertTest("Deployed ZIP subpage about.html is served correctly", strpos($aboutContent, 'About this ZIP site') !== false);
}

// 4. Test Paste HTML Tab (/api/paste)
echo "\n--- 4. Testing Paste HTML Tab via /api/paste ---\n";
$pasteSlug = 'test-paste-slug-' . time();
$ch = curl_init($baseUrl . '/api/paste');
$postData = [
    'title' => 'My Pasted Portfolio',
    'slug' => $pasteSlug,
    'html' => '<!DOCTYPE html><html><body><h1>Pasted HTML Content Verified</h1></body></html>'
];
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$responseRaw = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$resp = json_decode($responseRaw, true);
assertTest("Paste HTML returns 200 OK", $httpCode === 200, "HTTP $httpCode: $responseRaw");
assertTest("Paste HTML response contains success=true", ($resp['success'] ?? false) === true);

if (!empty($resp['slug'])) {
    $pastedContent = file_get_contents($baseUrl . '/s/' . $resp['slug']);
    assertTest("Pasted site is live at /s/{slug}", strpos($pastedContent, 'Pasted HTML Content Verified') !== false);
}

// 5. Test Demo Button Behavior (Sending Developer Portfolio Demo)
echo "\n--- 5. Testing Demo Button Payload via /api/paste ---\n";
$demoHtml = '<!DOCTYPE html><html><body><h1>Alex Rivera Portfolio</h1><form action="/api/forms/demo" method="POST"><input name="message" value="hello"></form></body></html>';
$ch = curl_init($baseUrl . '/api/paste');
$postData = [
    'title' => 'Developer Portfolio Demo',
    'html' => $demoHtml
];
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$responseRaw = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$resp = json_decode($responseRaw, true);
assertTest("Demo template deployment returns 200 OK", $httpCode === 200, "HTTP $httpCode: $responseRaw");
assertTest("Demo template creates site with auto slug", !empty($resp['slug']));

if (!empty($resp['slug'])) {
    $demoSiteSlug = $resp['slug'];
    $demoContent = file_get_contents($baseUrl . '/s/' . $demoSiteSlug);
    assertTest("Demo site serves Alex Rivera Portfolio", strpos($demoContent, 'Alex Rivera Portfolio') !== false);
    
    // 6. Test Form Submission on this deployed site!
    echo "\n--- 6. Testing Zero-Backend Form Submission (/api/forms/{slug}) ---\n";
    $ch = curl_init($baseUrl . '/api/forms/' . $demoSiteSlug);
    $formData = [
        'name' => 'Alice Tester',
        'email' => 'alice@example.com',
        'message' => 'Testing form submission for PageDock!'
    ];
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $formData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $formRaw = curl_exec($ch);
    $formCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $formResp = json_decode($formRaw, true);
    assertTest("Form submission endpoint returns 200 OK", $formCode === 200, "HTTP $formCode: $formRaw");
    assertTest("Form submission response indicates success", ($formResp['success'] ?? false) === true);
}

// 7. Test Edge Cases & Validation
echo "\n--- 7. Testing Validation & Error Handling ---\n";
// Empty file upload
$ch = curl_init($baseUrl . '/api/upload');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$emptyRaw = curl_exec($ch);
$emptyCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
assertTest("Empty upload returns 400 Bad Request", $emptyCode === 400);

// Invalid file extension
$tempTxt = tempnam(sys_get_temp_dir(), 'pd_txt_') . '.exe';
file_put_contents($tempTxt, 'dummy binary');
$ch = curl_init($baseUrl . '/api/upload');
$cfile = new CURLFile($tempTxt, 'application/octet-stream', 'malware.exe');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, ['file' => $cfile]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$invalidRaw = curl_exec($ch);
$invalidCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
unlink($tempTxt);
$invalidResp = json_decode($invalidRaw, true);
assertTest("Invalid file extension (.exe) rejected with 400", $invalidCode === 400);
assertTest("Rejection specifies unsupported file type", strpos($invalidResp['error'] ?? '', 'Unsupported file type') !== false);

// Empty paste HTML
$ch = curl_init($baseUrl . '/api/paste');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, ['html' => '']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$emptyPasteRaw = curl_exec($ch);
$emptyPasteCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
assertTest("Empty HTML paste returns 400 Bad Request", $emptyPasteCode === 400);

echo "\n============================================\n";
$total = count($results);
$passed = count(array_filter($results, fn($r) => $r['status'] === 'PASS'));
echo "RESULTS: $passed / $total TESTS PASSED\n";
echo "============================================\n";
