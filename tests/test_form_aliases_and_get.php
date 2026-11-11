<?php
/**
 * Test Suite: Form Endpoint Aliases (__hh/form & __pd/form) and GET status guide
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/SiteManager.php';
require_once __DIR__ . '/../src/Forms.php';

function runScenario(string $method, string $uri, array $headers = [], array $post = []): array {
    $script = '
        require_once "config/config.php";
        require_once "src/Database.php";
        require_once "src/SiteManager.php";
        require_once "src/Forms.php";
        require_once "src/Router.php";

        $_SERVER["REQUEST_METHOD"] = ' . var_export($method, true) . ';
        $_SERVER["REQUEST_URI"] = ' . var_export($uri, true) . ';
        $_POST = ' . var_export($post, true) . ';
    ';
    foreach ($headers as $k => $v) {
        $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $k));
        $script .= '$_SERVER[' . var_export($serverKey, true) . '] = ' . var_export($v, true) . '; ';
    }
    $script .= '
        ob_start();
        Router::handle();
        $out = ob_get_clean();
        $code = http_response_code() ?: 200;
        echo json_encode(["code" => $code, "out" => $out]);
    ';

    $cmd = 'php -r ' . escapeshellarg($script);
    $res = shell_exec($cmd);
    $decoded = json_decode($res, true);
    if (isset($decoded['code'])) {
        return $decoded;
    }
    // If Router called json_response which exits immediately, $res is the JSON itself
    return ['code' => 200, 'out' => $res, 'data' => $decoded];
}

function assertTest(string $name, bool $pass, string $detail = ''): void {
    if ($pass) {
        echo "✅ PASS: $name\n";
    } else {
        echo "❌ FAIL: $name - $detail\n";
        exit(1);
    }
}

echo "=== Testing Form Aliases & GET Status Guide ===\n\n";

// 1. GET /__hh/form (HTML format)
$r1 = runScenario('GET', '/__hh/form', ['Accept' => 'text/html']);
assertTest("GET /__hh/form returns HTTP 200 (not 404)", $r1['code'] === 200, "Got code {$r1['code']}");
assertTest("GET /__hh/form contains Form Ingestion Endpoint Guide", str_contains($r1['out'], 'Form Ingestion Endpoint') || str_contains($r1['out'], 'Zero-Backend Form'), "Output: " . substr($r1['out'], 0, 200));
assertTest("GET /__hh/form mentions POST /__hh/form", str_contains($r1['out'], '/__hh/form'));

// 2. GET /__hh/form (JSON format)
$r2 = runScenario('GET', '/__hh/form', ['Accept' => 'application/json']);
assertTest("GET /__hh/form JSON returns HTTP 200", $r2['code'] === 200);
$d2 = $r2['data'] ?? json_decode($r2['out'], true);
assertTest("GET /__hh/form JSON has status operational", ($d2['status'] ?? '') === 'operational');

// 3. GET /__pd/form (HTML format)
$r3 = runScenario('GET', '/__pd/form', ['Accept' => 'text/html']);
assertTest("GET /__pd/form returns HTTP 200", $r3['code'] === 200);
assertTest("GET /__pd/form contains guide", str_contains($r3['out'], 'Form Ingestion Endpoint'));

// 4. GET /__pd/form (JSON format)
$r4 = runScenario('GET', '/__pd/form', ['Accept' => 'application/json']);
assertTest("GET /__pd/form JSON returns HTTP 200", $r4['code'] === 200);
$d4 = $r4['data'] ?? json_decode($r4['out'], true);
assertTest("GET /__pd/form JSON has status operational", ($d4['status'] ?? '') === 'operational');

// 5. GET /f/demo-site (HTML format)
$r5 = runScenario('GET', '/f/demo-site', ['Accept' => 'text/html']);
assertTest("GET /f/demo-site returns HTTP 200", $r5['code'] === 200);
assertTest("GET /f/demo-site contains target slug", str_contains($r5['out'], 'demo-site'));

// 6. POST /__hh/form (Universal Submit via legacy alias)
$slug = 'test-form-alias-' . time();
SiteManager::createFromHtml('<h1>Form Site</h1>', 'Form Site', $slug);
$site = SiteManager::getSite($slug);

$postData = [
    '_site' => $slug,
    'name' => 'Chioma Okonkwo',
    'email' => 'chioma@example.com',
    'message' => 'Hello from legacy /__hh/form endpoint',
    '_pd_ts' => (string)(time() - 5),
];

$r6 = runScenario('POST', '/__hh/form', ['Accept' => 'application/json'], $postData);
assertTest("POST /__hh/form returns HTTP 200", $r6['code'] === 200, "Output: " . $r6['out']);
$d6 = $r6['data'] ?? json_decode($r6['out'], true);
assertTest("POST /__hh/form returns ok=true", ($d6['ok'] ?? false) === true);

// 7. Verify submission was stored in DB
$submissions = Forms::getSubmissions((int)$site['id']);
assertTest("Submission exists in DB for site", count($submissions) >= 1);
assertTest("Submission contains correct name", ($submissions[0]['data']['name'] ?? '') === 'Chioma Okonkwo');

echo "\n✨ ALL 11 FORM ALIAS AND GET ROUTING TESTS PASSED!\n";
