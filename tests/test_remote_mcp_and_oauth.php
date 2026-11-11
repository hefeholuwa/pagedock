<?php
/**
 * PageDock - Remote Streamable HTTP MCP, OAuth 2.1 (PKCE + DCR), & Bearer API Test Suite
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Database.php';

echo "=== Running Remote MCP, OAuth 2.1 & Bearer API Test Suite ===\n\n";

$passCount = 0;
$failCount = 0;

function assertTest(bool $condition, string $message, string $extra = ''): void {
    global $passCount, $failCount;
    if ($condition) {
        echo "PASS: {$message}\n";
        $passCount++;
    } else {
        echo "FAIL: {$message}" . ($extra ? " ({$extra})" : "") . "\n";
        $failCount++;
    }
}

$port = 8099;
$baseUrl = "http://127.0.0.1:{$port}";
$docRoot = realpath(__DIR__ . '/../public');
$cookieFile = sys_get_temp_dir() . '/pd_mcp_oauth_cookie_' . getmypid() . '.txt';

$proc = proc_open(
    ['php', '-S', "127.0.0.1:{$port}", '-t', $docRoot],
    [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
    $pipes,
    realpath(__DIR__ . '/..')
);

register_shutdown_function(function () use (&$proc, $cookieFile) {
    if (is_resource($proc)) {
        proc_terminate($proc);
        proc_close($proc);
    }
    if (file_exists($cookieFile)) {
        @unlink($cookieFile);
    }
});

// Wait for local server ready
$ready = false;
for ($i = 0; $i < 30; $i++) {
    $fp = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.2);
    if ($fp) {
        fclose($fp);
        $ready = true;
        break;
    }
    usleep(100000);
}
assertTest($ready, "Local PHP test server started on {$baseUrl}");
if (!$ready) {
    exit(1);
}

function httpReq(
    string $method,
    string $url,
    $body = null,
    array $headers = [],
    ?string $cookieJar = null,
    bool $followRedirects = false
): array {
    $ch = curl_init($url);
    $respHeaders = [];
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, $followRedirects);
    curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($curl, $line) use (&$respHeaders) {
        $len = strlen($line);
        if (str_contains($line, ':')) {
            [$k, $v] = explode(':', $line, 2);
            $respHeaders[strtolower(trim($k))] = trim($v);
        }
        return $len;
    });
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($body) ? http_build_query($body) : $body);
    }
    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    if ($cookieJar) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieJar);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieJar);
    }
    $raw = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    return ['status' => $status, 'headers' => $respHeaders, 'body' => (string)$raw];
}

// 1. OAuth Discovery Metadata
echo "\n--- 1. OAuth 2.1 Discovery Endpoints ---\n";
$prm = httpReq('GET', "{$baseUrl}/.well-known/oauth-protected-resource");
$prmJson = json_decode($prm['body'], true);
assertTest($prm['status'] === 200, "GET /.well-known/oauth-protected-resource returns 200", "Got {$prm['status']}");
assertTest(($prmJson['resource'] ?? '') === "{$baseUrl}/mcp", "Protected resource matches /mcp");
assertTest(in_array($baseUrl, $prmJson['authorization_servers'] ?? [], true), "Authorization server includes base URL");

$asm = httpReq('GET', "{$baseUrl}/.well-known/oauth-authorization-server");
$asmJson = json_decode($asm['body'], true);
assertTest($asm['status'] === 200, "GET /.well-known/oauth-authorization-server returns 200");
assertTest(($asmJson['registration_endpoint'] ?? '') === "{$baseUrl}/oauth/register", "Advertises DCR endpoint");
assertTest(in_array('S256', $asmJson['code_challenge_methods_supported'] ?? [], true), "Supports PKCE S256");

// 2. Unauthenticated & Invalid Bearer rejection on /mcp
echo "\n--- 2. MCP Unauthorized Challenge (RFC 9728) ---\n";
$unauthMcp = httpReq('POST', "{$baseUrl}/mcp", json_encode(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize']), ['Content-Type: application/json']);
assertTest($unauthMcp['status'] === 401, "POST /mcp without token returns 401");
assertTest(str_contains($unauthMcp['headers']['www-authenticate'] ?? '', 'resource_metadata='), "401 includes WWW-Authenticate resource_metadata");

$badMcp = httpReq('POST', "{$baseUrl}/mcp", json_encode(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize']), [
    'Content-Type: application/json',
    'Authorization: Bearer pd_oauth_totally_invalid_token',
]);
assertTest($badMcp['status'] === 401, "POST /mcp with invalid Bearer returns 401");

// 3. Dynamic Client Registration (ChatGPT style)
echo "\n--- 3. Dynamic Client Registration (RFC 7591) ---\n";
$redirectUri = 'https://chatgpt.com/connector_platform_oauth_redirect';
$dcr = httpReq('POST', "{$baseUrl}/oauth/register", json_encode([
    'client_name' => 'ChatGPT Test Connector',
    'redirect_uris' => [$redirectUri],
    'token_endpoint_auth_method' => 'none',
]), ['Content-Type: application/json']);
$dcrJson = json_decode($dcr['body'], true);
assertTest($dcr['status'] === 201, "POST /oauth/register returns 201", "Got {$dcr['status']}: {$dcr['body']}");
$clientId = $dcrJson['client_id'] ?? '';
assertTest(str_starts_with($clientId, 'pd_client_'), "Issued valid client_id ({$clientId})");

// 4. Register User + OAuth Consent + PKCE Authorization Code Exchange
echo "\n--- 4. User Registration, OAuth Consent & PKCE Exchange ---\n";
$testEmail = 'mcp_oauth_' . time() . '@example.com';
$testPass = 'testpass123!';
$reg = httpReq('POST', "{$baseUrl}/register", [
    'email' => $testEmail,
    'password' => $testPass,
    'name' => 'MCP Tester',
], [], $cookieFile, false);
assertTest($reg['status'] === 302, "User registration sets session cookie");

$verifier = bin2hex(random_bytes(32));
$challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
$state = 'st_' . bin2hex(random_bytes(8));

$authUrl = "{$baseUrl}/oauth/authorize?" . http_build_query([
    'client_id' => $clientId,
    'redirect_uri' => $redirectUri,
    'response_type' => 'code',
    'scope' => 'mcp sites:read sites:write',
    'state' => $state,
    'code_challenge' => $challenge,
    'code_challenge_method' => 'S256',
]);
$consentGet = httpReq('GET', $authUrl, null, [], $cookieFile);
assertTest($consentGet['status'] === 200, "GET /oauth/authorize renders consent page");
assertTest(str_contains($consentGet['body'], 'ChatGPT Test Connector'), "Consent screen shows client name");

preg_match('/name="csrf_token"\s+value="([^"]+)"/', $consentGet['body'], $csrfMatch);
$csrf = $csrfMatch[1] ?? '';
assertTest($csrf !== '', "Extracted CSRF token from consent page");

$consentPost = httpReq('POST', "{$baseUrl}/oauth/authorize", [
    'csrf_token' => $csrf,
    'client_id' => $clientId,
    'redirect_uri' => $redirectUri,
    'response_type' => 'code',
    'scope' => 'mcp sites:read sites:write',
    'state' => $state,
    'code_challenge' => $challenge,
    'code_challenge_method' => 'S256',
    'decision' => 'approve',
], [], $cookieFile, false);

assertTest($consentPost['status'] === 302, "POST /oauth/authorize approve redirects back to ChatGPT callback");
$loc = $consentPost['headers']['location'] ?? '';
assertTest(str_starts_with($loc, $redirectUri), "Redirect target matches registered redirect_uri");
parse_str((string)parse_url($loc, PHP_URL_QUERY), $cbQuery);
$authCode = $cbQuery['code'] ?? '';
assertTest($authCode !== '' && ($cbQuery['state'] ?? '') === $state, "Callback carries code and preserved state");

// PKCE wrong verifier must fail
$badToken = httpReq('POST', "{$baseUrl}/oauth/token", [
    'grant_type' => 'authorization_code',
    'code' => $authCode,
    'redirect_uri' => $redirectUri,
    'client_id' => $clientId,
    'code_verifier' => 'wrong_verifier_value',
]);
assertTest($badToken['status'] === 400, "Token endpoint rejects wrong PKCE code_verifier");

// PKCE valid verifier must succeed
$goodToken = httpReq('POST', "{$baseUrl}/oauth/token", [
    'grant_type' => 'authorization_code',
    'code' => $authCode,
    'redirect_uri' => $redirectUri,
    'client_id' => $clientId,
    'code_verifier' => $verifier,
]);
$tokenJson = json_decode($goodToken['body'], true);
assertTest($goodToken['status'] === 200 && !empty($tokenJson['access_token']), "Token endpoint issues OAuth Bearer access_token", $goodToken['body']);
$accessToken = $tokenJson['access_token'] ?? '';
$refreshToken = $tokenJson['refresh_token'] ?? '';

// Code replay must fail
$replayToken = httpReq('POST', "{$baseUrl}/oauth/token", [
    'grant_type' => 'authorization_code',
    'code' => $authCode,
    'redirect_uri' => $redirectUri,
    'client_id' => $clientId,
    'code_verifier' => $verifier,
]);
assertTest($replayToken['status'] === 400, "Authorization code cannot be replayed");

// 5. Full Streamable HTTP MCP Session
echo "\n--- 5. Streamable HTTP MCP Lifecycle & Tools ---\n";
$mcpHeaders = [
    'Content-Type: application/json',
    "Authorization: Bearer {$accessToken}",
];

$initResp = httpReq('POST', "{$baseUrl}/mcp", json_encode([
    'jsonrpc' => '2.0',
    'id' => 1,
    'method' => 'initialize',
    'params' => [
        'protocolVersion' => '2025-03-26',
        'capabilities' => new stdClass(),
        'clientInfo' => ['name' => 'chatgpt-test', 'version' => '1.0'],
    ],
]), $mcpHeaders);
$initJson = json_decode($initResp['body'], true);
assertTest($initResp['status'] === 200, "MCP initialize returns 200");
assertTest(($initJson['result']['serverInfo']['name'] ?? '') === 'pagedock', "Server info identifies pagedock");
assertTest(!empty($initResp['headers']['mcp-session-id']), "Response includes Mcp-Session-Id header");

$notifResp = httpReq('POST', "{$baseUrl}/mcp", json_encode([
    'jsonrpc' => '2.0',
    'method' => 'notifications/initialized',
]), $mcpHeaders);
assertTest($notifResp['status'] === 202, "notifications/initialized returns 202 Accepted");

$toolsListResp = httpReq('POST', "{$baseUrl}/mcp", json_encode([
    'jsonrpc' => '2.0',
    'id' => 2,
    'method' => 'tools/list',
]), $mcpHeaders);
$toolsJson = json_decode($toolsListResp['body'], true);
$tools = $toolsJson['result']['tools'] ?? [];
assertTest(count($tools) === 11, "tools/list exposes all 11 PageDock MCP tools", "Count=" . count($tools));
// Verify list_sites inputSchema.properties encodes as JSON object `{}` not `[]`
assertTest(str_contains($toolsListResp['body'], '"properties":{}'), "Empty properties schema encodes as {} object (not [])");

// deploy_html tool call
$deployResp = httpReq('POST', "{$baseUrl}/mcp", json_encode([
    'jsonrpc' => '2.0',
    'id' => 3,
    'method' => 'tools/call',
    'params' => [
        'name' => 'deploy_html',
        'arguments' => [
            'html' => '<!DOCTYPE html><html><head><title>ChatGPT Live Test</title></head><body><h1>Hello from MCP</h1></body></html>',
            'title' => 'ChatGPT Live Test',
        ],
    ],
]), $mcpHeaders);
$deployRpc = json_decode($deployResp['body'], true);
$deployData = json_decode($deployRpc['result']['content'][0]['text'] ?? '{}', true);
assertTest(!empty($deployData['success']) && !empty($deployData['slug']), "MCP deploy_html created hosted site", $deployResp['body']);
$createdSlug = $deployData['slug'] ?? '';

// list_sites tool call + REST GET /api/sites
$listSitesResp = httpReq('POST', "{$baseUrl}/mcp", json_encode([
    'jsonrpc' => '2.0',
    'id' => 4,
    'method' => 'tools/call',
    'params' => ['name' => 'list_sites', 'arguments' => new stdClass()],
]), $mcpHeaders);
$listSitesRpc = json_decode($listSitesResp['body'], true);
$listSitesData = json_decode($listSitesRpc['result']['content'][0]['text'] ?? '{}', true);
$foundSlugs = array_column($listSitesData['sites'] ?? [], 'slug');
assertTest(in_array($createdSlug, $foundSlugs, true), "MCP list_sites returns newly deployed owned site");

$restSites = httpReq('GET', "{$baseUrl}/api/sites", null, ["Authorization: Bearer {$accessToken}"]);
$restSitesJson = json_decode($restSites['body'], true);
assertTest($restSites['status'] === 200 && ($restSitesJson['count'] ?? 0) >= 1, "GET /api/sites works with Bearer token");

$openApi = httpReq('GET', "{$baseUrl}/openapi.json");
$openApiJson = json_decode($openApi['body'], true);
assertTest($openApi['status'] === 200 && isset($openApiJson['paths']['/api/deploy']), "GET /openapi.json returns OpenAPI 3.1 schema");

// update_site_file & pull_site
$updResp = httpReq('POST', "{$baseUrl}/mcp", json_encode([
    'jsonrpc' => '2.0',
    'id' => 5,
    'method' => 'tools/call',
    'params' => [
        'name' => 'update_site_file',
        'arguments' => [
            'slug' => $createdSlug,
            'path' => 'index.html',
            'content' => '<!DOCTYPE html><html><body><h1>Updated by ChatGPT</h1></body></html>',
            'summary' => 'ChatGPT v2 patch',
        ],
    ],
]), $mcpHeaders);
$updData = json_decode(json_decode($updResp['body'], true)['result']['content'][0]['text'] ?? '{}', true);
assertTest(!empty($updData['success']) && (int)($updData['version'] ?? 0) >= 2, "MCP update_site_file creates version v2");

$pullResp = httpReq('POST', "{$baseUrl}/mcp", json_encode([
    'jsonrpc' => '2.0',
    'id' => 6,
    'method' => 'tools/call',
    'params' => [
        'name' => 'pull_site',
        'arguments' => ['slug' => $createdSlug],
    ],
]), $mcpHeaders);
$pulledHtml = json_decode($pullResp['body'], true)['result']['content'][0]['text'] ?? '';
assertTest(str_contains($pulledHtml, 'Updated by ChatGPT'), "MCP pull_site returns updated HTML");

// Refresh token exchange
$refreshResp = httpReq('POST', "{$baseUrl}/oauth/token", [
    'grant_type' => 'refresh_token',
    'refresh_token' => $refreshToken,
]);
$refreshJson = json_decode($refreshResp['body'], true);
assertTest($refreshResp['status'] === 200 && !empty($refreshJson['access_token']) && $refreshJson['access_token'] !== $accessToken, "OAuth refresh_token rotates access_token");
$rotatedToken = $refreshJson['access_token'] ?? '';

// Old token should now be rejected, new token should delete site cleanly
$delResp = httpReq('POST', "{$baseUrl}/mcp", json_encode([
    'jsonrpc' => '2.0',
    'id' => 7,
    'method' => 'tools/call',
    'params' => [
        'name' => 'delete_site',
        'arguments' => ['slug' => $createdSlug],
    ],
]), ['Content-Type: application/json', "Authorization: Bearer {$rotatedToken}"]);
$delData = json_decode(json_decode($delResp['body'], true)['result']['content'][0]['text'] ?? '{}', true);
assertTest(!empty($delData['success']), "Rotated OAuth token deletes test site cleanly");

// Personal Access Token via /api/tokens + revoke
echo "\n--- 6. Dashboard Personal Access Tokens ---\n";
$patCreate = httpReq('POST', "{$baseUrl}/api/tokens", json_encode(['name' => 'Custom GPT Test']), ['Content-Type: application/json'], $cookieFile);
$patJson = json_decode($patCreate['body'], true);
assertTest($patCreate['status'] === 201 && str_starts_with($patJson['token']['token'] ?? '', 'pd_pat_'), "POST /api/tokens issues Personal Access Token");
$patId = $patJson['token']['id'] ?? 0;

$patRevoke = httpReq('POST', "{$baseUrl}/api/tokens/{$patId}/revoke", '', [], $cookieFile);
assertTest($patRevoke['status'] === 200, "POST /api/tokens/{id}/revoke revokes token");

// Cleanup test user & OAuth client
$db = Database::getConnection();
$db->prepare("DELETE FROM users WHERE email = ?")->execute([$testEmail]);
$db->prepare("DELETE FROM oauth_clients WHERE client_id = ?")->execute([$clientId]);

echo "\n=== Summary: {$passCount} PASSED, {$failCount} FAILED ===\n";
exit($failCount > 0 ? 1 : 0);

