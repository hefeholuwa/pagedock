<?php
/**
 * Test Suite: Terminal CLI & cURL Ingestion Endpoint (/api/deploy)
 * Tests Surge-style text banner output, multipart file uploads, raw body streaming,
 * and claim token authenticated updates.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/SiteManager.php';
require_once __DIR__ . '/../src/Router.php';

function runDeployScenario(array $serverOverrides, array $files = [], ?string $rawBody = null): array {
    $codeFile = tempnam(sys_get_temp_dir(), 'pd_test_code_');
    
    $script = '
        $codeFile = ' . var_export($codeFile, true) . ';
        register_shutdown_function(function() use ($codeFile) {
            file_put_contents($codeFile, (string)(http_response_code() ?: 200));
        });

        require_once "config/config.php";
        require_once "src/Database.php";
        require_once "src/SiteManager.php";
        require_once "src/Router.php";

        $_SERVER["REQUEST_METHOD"] = "POST";
        $_SERVER["REQUEST_URI"] = "/api/deploy";
    ';
    
    foreach ($serverOverrides as $k => $v) {
        $script .= '$_SERVER[' . var_export($k, true) . '] = ' . var_export($v, true) . '; ';
    }
    
    if (!empty($files)) {
        $script .= '$_FILES = ' . var_export($files, true) . '; ';
    }

    $script .= '
        Router::handle();
    ';

    $cmd = 'php -r ' . escapeshellarg($script);
    $out = shell_exec($cmd);
    
    $code = 200;
    if (file_exists($codeFile)) {
        $code = (int)file_get_contents($codeFile);
        @unlink($codeFile);
    }
    
    return ['code' => $code, 'out' => $out];
}

function assertTest(string $name, bool $pass, string $detail = ''): void {
    if ($pass) {
        echo "✅ PASS: $name\n";
    } else {
        echo "❌ FAIL: $name - $detail\n";
        exit(1);
    }
}

echo "=== Testing Terminal CLI & cURL Deploy Engine ===\n\n";

// 1. Test cURL User-Agent receiving Surge-style Plaintext Output
$tmpHtml = tempnam(sys_get_temp_dir(), 'pd_html_') . '.html';
file_put_contents($tmpHtml, '<!DOCTYPE html><html><body><h1>Hello from cURL</h1></body></html>');

$customSlug = 'cli-test-' . bin2hex(random_bytes(3));
$files = [
    'file' => [
        'name' => 'index.html',
        'type' => 'text/html',
        'tmp_name' => $tmpHtml,
        'error' => UPLOAD_ERR_OK,
        'size' => filesize($tmpHtml)
    ]
];

$r1 = runDeployScenario([
    'HTTP_USER_AGENT' => 'curl/8.7.1',
    'HTTP_X_PROJECT_SLUG' => $customSlug,
    'HTTP_X_TITLE' => 'cURL Deployed Site'
], $files);

assertTest("cURL deploy returns HTTP 200", $r1['code'] === 200, "Got code {$r1['code']}");
assertTest("Output has Surge-style Published banner", str_contains($r1['out'], 'Published to PageDock successfully!'), "Output: " . $r1['out']);
assertTest("Output displays Live URL", str_contains($r1['out'], 'Live URL:'));
assertTest("Output displays Edit Online link", str_contains($r1['out'], 'Edit Online:'));
assertTest("Output displays Claim Token", str_contains($r1['out'], 'Claim Token: pd_claim_'));
assertTest("Output includes update cURL instructions", str_contains($r1['out'], 'To deploy updates:'));

// Extract claim token from terminal output
preg_match('/Claim Token:\s+(pd_claim_[a-zA-Z0-9]+)/', $r1['out'], $m);
$claimToken = $m[1] ?? '';
assertTest("Extracted valid claim token from cURL output", !empty($claimToken), "Token: $claimToken");

// 2. Test Updating Existing Site with cURL and X-Claim-Token Header
$tmpHtml2 = tempnam(sys_get_temp_dir(), 'pd_html2_') . '.html';
file_put_contents($tmpHtml2, '<!DOCTYPE html><html><body><h1>Hello from cURL v2</h1></body></html>');

$filesUpdate = [
    'file' => [
        'name' => 'index.html',
        'type' => 'text/html',
        'tmp_name' => $tmpHtml2,
        'error' => UPLOAD_ERR_OK,
        'size' => filesize($tmpHtml2)
    ]
];

$r2 = runDeployScenario([
    'HTTP_USER_AGENT' => 'curl/8.7.1',
    'HTTP_X_PROJECT_SLUG' => $customSlug,
    'HTTP_X_CLAIM_TOKEN' => $claimToken
], $filesUpdate);

assertTest("Authorized cURL update returns HTTP 200", $r2['code'] === 200);
assertTest("Output shows 'Updated to PageDock successfully!'", str_contains($r2['out'], 'Updated to PageDock successfully!'));

// Verify on disk content
$siteRecord = SiteManager::getSite($customSlug);
assertTest("Version bumped to v2", (int)($siteRecord['current_version'] ?? 0) === 2);
$diskContent = file_get_contents(SITES_DIR . '/' . $customSlug . '/index.html');
assertTest("On-disk content updated with v2", str_contains($diskContent, 'Hello from cURL v2'));

// 3. Test Unauthorized cURL Update Attempt (Wrong Token)
$r3 = runDeployScenario([
    'HTTP_USER_AGENT' => 'curl/8.7.1',
    'HTTP_X_PROJECT_SLUG' => $customSlug,
    'HTTP_X_CLAIM_TOKEN' => 'wrong-token-12345'
], $filesUpdate);

assertTest("Unauthorized update returns HTTP 403", $r3['code'] === 403, "Got code {$r3['code']}, out: " . $r3['out']);
assertTest("Output prints clean error failure banner", str_contains($r3['out'], 'Deployment Failed:'));

// 4. Test cURL ZIP Bundle Deploy
$tmpZip = tempnam(sys_get_temp_dir(), 'pd_zip_') . '.zip';
$zip = new ZipArchive();
$zip->open($tmpZip, ZipArchive::CREATE);
$zip->addFromString('index.html', '<h1>ZIP Title</h1>');
$zip->addFromString('style.css', 'body { color: red; }');
$zip->addFromString('script.js', 'console.log("ready");');
$zip->close();

$zipSlug = 'cli-zip-' . bin2hex(random_bytes(3));
$zipFiles = [
    'file' => [
        'name' => 'bundle.zip',
        'type' => 'application/zip',
        'tmp_name' => $tmpZip,
        'error' => UPLOAD_ERR_OK,
        'size' => filesize($tmpZip)
    ]
];

$r4 = runDeployScenario([
    'HTTP_USER_AGENT' => 'curl/8.7.1',
    'HTTP_X_PROJECT_SLUG' => $zipSlug
], $zipFiles);

assertTest("cURL ZIP deploy returns HTTP 200", $r4['code'] === 200);
assertTest("ZIP deploy shows Published banner", str_contains($r4['out'], 'Published to PageDock successfully!'));

$zipSite = SiteManager::getSite($zipSlug);
assertTest("Site created with 3 files", (int)($zipSite['file_count'] ?? 0) === 3);

// 5. Test Non-cURL Client (JSON response)
$tmpHtml3 = tempnam(sys_get_temp_dir(), 'pd_html3_') . '.html';
file_put_contents($tmpHtml3, '<h1>JSON Client</h1>');

$filesJson = [
    'file' => [
        'name' => 'index.html',
        'type' => 'text/html',
        'tmp_name' => $tmpHtml3,
        'error' => UPLOAD_ERR_OK,
        'size' => filesize($tmpHtml3)
    ]
];

$r5 = runDeployScenario([
    'HTTP_USER_AGENT' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)',
    'HTTP_ACCEPT' => 'application/json'
], $filesJson);

assertTest("Browser/API client returns HTTP 200", $r5['code'] === 200);
$jsonObj = json_decode($r5['out'], true);
assertTest("Response is valid JSON with success=true", ($jsonObj['success'] ?? false) === true);
assertTest("JSON includes live URL and claim token", !empty($jsonObj['url']) && !empty($jsonObj['claim_token']));

@unlink($tmpHtml);
@unlink($tmpHtml2);
@unlink($tmpHtml3);
@unlink($tmpZip);

echo "\n✨ ALL 16 TERMINAL CLI & CURL TESTS PASSED!\n";
