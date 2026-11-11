<?php

class OAuthServer {
    public static function sendCorsHeaders(): void {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Accept, Authorization, Mcp-Protocol-Version');
        header('Access-Control-Max-Age: 86400');
    }

    public static function getBaseUrl(): string {
        return rtrim(url(), '/');
    }

    /**
     * RFC 9728 Protected Resource Metadata
     * GET /.well-known/oauth-protected-resource (and /mcp subpath)
     */
    public static function handleProtectedResourceMetadata(): void {
        self::sendCorsHeaders();
        $base = self::getBaseUrl();
        json_response([
            'resource' => $base . '/mcp',
            'authorization_servers' => [$base],
            'bearer_methods_supported' => ['header'],
            'scopes_supported' => ['mcp', 'sites:read', 'sites:write'],
            'resource_name' => APP_NAME . ' MCP Server',
            'resource_documentation' => $base . '/docs#mcp-agents',
        ]);
    }

    /**
     * RFC 8414 Authorization Server Metadata
     * GET /.well-known/oauth-authorization-server (and openid-configuration fallback)
     */
    public static function handleAuthServerMetadata(): void {
        self::sendCorsHeaders();
        $base = self::getBaseUrl();
        json_response([
            'issuer' => $base,
            'authorization_endpoint' => $base . '/oauth/authorize',
            'token_endpoint' => $base . '/oauth/token',
            'registration_endpoint' => $base . '/oauth/register',
            'response_types_supported' => ['code'],
            'response_modes_supported' => ['query'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'code_challenge_methods_supported' => ['S256', 'plain'],
            'token_endpoint_auth_methods_supported' => ['none', 'client_secret_post', 'client_secret_basic'],
            'scopes_supported' => ['mcp', 'sites:read', 'sites:write'],
            'service_documentation' => $base . '/docs#mcp-agents',
        ]);
    }

    /**
     * RFC 7591 Dynamic Client Registration
     * POST /oauth/register
     */
    public static function handleRegister(): void {
        self::sendCorsHeaders();
        $raw = file_get_contents('php://input');
        $body = json_decode($raw ?: '', true);
        if (!is_array($body)) {
            self::oauthError('invalid_client_metadata', 'Request body must be valid JSON.', 400);
            return;
        }

        $redirectUris = $body['redirect_uris'] ?? null;
        if (!is_array($redirectUris) || empty($redirectUris)) {
            self::oauthError('invalid_redirect_uri', 'redirect_uris must be a non-empty array.', 400);
            return;
        }

        $cleanRedirects = [];
        foreach ($redirectUris as $uri) {
            if (!is_string($uri) || !self::isValidRedirectUri($uri)) {
                self::oauthError('invalid_redirect_uri', 'Invalid redirect_uri: ' . (string)$uri, 400);
                return;
            }
            $cleanRedirects[] = $uri;
        }

        $clientName = trim((string)($body['client_name'] ?? 'MCP Client'));
        if ($clientName === '') {
            $clientName = 'MCP Client';
        }
        $authMethod = (string)($body['token_endpoint_auth_method'] ?? 'none');
        if (!in_array($authMethod, ['none', 'client_secret_post', 'client_secret_basic'], true)) {
            $authMethod = 'none';
        }

        $grantTypes = $body['grant_types'] ?? ['authorization_code', 'refresh_token'];
        if (!is_array($grantTypes) || empty($grantTypes)) {
            $grantTypes = ['authorization_code', 'refresh_token'];
        }
        $grantStr = implode(' ', array_map('strval', $grantTypes));

        $clientId = 'pd_client_' . bin2hex(random_bytes(12));
        $clientSecret = null;
        $clientSecretHash = null;
        if ($authMethod !== 'none') {
            $clientSecret = 'pd_sec_' . bin2hex(random_bytes(24));
            $clientSecretHash = hash('sha256', $clientSecret);
        }

        $db = Database::getConnection();
        $stmt = $db->prepare("
            INSERT INTO oauth_clients (client_id, client_secret_hash, client_name, redirect_uris_json, grant_types, token_endpoint_auth_method)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $clientId,
            $clientSecretHash,
            substr($clientName, 0, 120),
            json_encode(array_values($cleanRedirects)),
            substr($grantStr, 0, 200),
            $authMethod,
        ]);

        $response = [
            'client_id' => $clientId,
            'client_name' => $clientName,
            'redirect_uris' => array_values($cleanRedirects),
            'grant_types' => array_values($grantTypes),
            'response_types' => ['code'],
            'token_endpoint_auth_method' => $authMethod,
            'client_id_issued_at' => time(),
        ];
        if ($clientSecret !== null) {
            $response['client_secret'] = $clientSecret;
            $response['client_secret_expires_at'] = 0;
        }

        json_response($response, 201);
    }

    /**
     * OAuth 2.1 Authorization Endpoint (GET / POST /oauth/authorize)
     */
    public static function handleAuthorize(string $method): void {
        $src = $method === 'POST' ? $_POST : $_GET;
        $clientId = trim((string)($src['client_id'] ?? ''));
        $redirectUri = trim((string)($src['redirect_uri'] ?? ''));
        $responseType = trim((string)($src['response_type'] ?? 'code'));
        $scope = trim((string)($src['scope'] ?? 'mcp sites:read sites:write')) ?: 'mcp sites:read sites:write';
        $state = (string)($src['state'] ?? '');
        $codeChallenge = trim((string)($src['code_challenge'] ?? ''));
        $codeChallengeMethod = trim((string)($src['code_challenge_method'] ?? 'S256')) ?: 'S256';
        $resource = trim((string)($src['resource'] ?? ''));

        $client = self::findClient($clientId);
        if (!$client) {
            self::renderAuthorizeError('Unknown or unregistered OAuth client_id.');
            return;
        }

        $registeredUris = json_decode($client['redirect_uris_json'] ?? '[]', true) ?: [];
        if ($redirectUri === '' || !in_array($redirectUri, $registeredUris, true)) {
            self::renderAuthorizeError('The requested redirect_uri does not match this registered client.');
            return;
        }

        if ($responseType !== 'code') {
            self::redirectWithError($redirectUri, 'unsupported_response_type', 'Only response_type=code is supported.', $state);
            return;
        }

        if ($codeChallenge === '' || !in_array($codeChallengeMethod, ['S256', 'plain'], true)) {
            self::redirectWithError($redirectUri, 'invalid_request', 'PKCE code_challenge (S256) is required.', $state);
            return;
        }

        // Gate on authenticated browser session
        if (!Auth::hasSession()) {
            $qs = http_build_query([
                'client_id' => $clientId,
                'redirect_uri' => $redirectUri,
                'response_type' => $responseType,
                'scope' => $scope,
                'state' => $state,
                'code_challenge' => $codeChallenge,
                'code_challenge_method' => $codeChallengeMethod,
                'resource' => $resource,
            ]);
            redirect('login?redirect=' . urlencode('oauth/authorize?' . $qs));
            return;
        }

        $user = Auth::user();
        if (!$user) {
            redirect('login');
            return;
        }

        if ($method === 'GET') {
            if (empty($_SESSION['oauth_csrf'])) {
                $_SESSION['oauth_csrf'] = bin2hex(random_bytes(24));
            }
            render_view('oauth_authorize', [
                'title' => 'Authorize ' . ($client['client_name'] ?? 'App') . ' — PageDock',
                'client' => $client,
                'user' => $user,
                'csrfToken' => $_SESSION['oauth_csrf'],
                'params' => [
                    'client_id' => $clientId,
                    'redirect_uri' => $redirectUri,
                    'response_type' => $responseType,
                    'scope' => $scope,
                    'state' => $state,
                    'code_challenge' => $codeChallenge,
                    'code_challenge_method' => $codeChallengeMethod,
                    'resource' => $resource,
                ],
            ]);
            return;
        }

        // POST decision
        $csrf = (string)($_POST['csrf_token'] ?? '');
        if (empty($_SESSION['oauth_csrf']) || !hash_equals($_SESSION['oauth_csrf'], $csrf)) {
            self::renderAuthorizeError('Invalid session confirmation token. Please reload and try again.');
            return;
        }
        unset($_SESSION['oauth_csrf']);

        if (($_POST['decision'] ?? '') !== 'approve') {
            self::redirectWithError($redirectUri, 'access_denied', 'User denied access.', $state);
            return;
        }

        $rawCode = 'pd_code_' . bin2hex(random_bytes(24));
        $codeHash = hash('sha256', $rawCode);
        $expiresAt = gmdate('Y-m-d H:i:s', time() + 600);

        $db = Database::getConnection();
        $stmt = $db->prepare("
            INSERT INTO oauth_codes (code_hash, client_id, user_id, redirect_uri, scope, code_challenge, code_challenge_method, resource, expires_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $codeHash,
            $clientId,
            (int)$user['id'],
            $redirectUri,
            substr($scope, 0, 200),
            $codeChallenge,
            $codeChallengeMethod,
            $resource !== '' ? substr($resource, 0, 450) : null,
            $expiresAt,
        ]);

        $query = ['code' => $rawCode];
        if ($state !== '') {
            $query['state'] = $state;
        }
        $sep = str_contains($redirectUri, '?') ? '&' : '?';
        header('Location: ' . $redirectUri . $sep . http_build_query($query));
        exit;
    }

    /**
     * OAuth 2.1 Token Endpoint (POST /oauth/token)
     */
    public static function handleToken(): void {
        self::sendCorsHeaders();
        $raw = file_get_contents('php://input');
        $body = $_POST;
        if (empty($body) && !empty($raw)) {
            $json = json_decode($raw, true);
            if (is_array($json)) {
                $body = $json;
            } else {
                parse_str($raw, $parsed);
                if (is_array($parsed)) {
                    $body = $parsed;
                }
            }
        }

        $grantType = trim((string)($body['grant_type'] ?? ''));

        // Optional HTTP Basic client auth
        [$basicId, $basicSecret] = self::parseBasicClientAuth();
        $clientId = trim((string)($body['client_id'] ?? $basicId ?? ''));
        $clientSecret = (string)($body['client_secret'] ?? $basicSecret ?? '');

        if ($grantType === 'authorization_code') {
            $code = trim((string)($body['code'] ?? ''));
            $redirectUri = trim((string)($body['redirect_uri'] ?? ''));
            $codeVerifier = trim((string)($body['code_verifier'] ?? ''));

            if ($code === '' || $redirectUri === '' || $codeVerifier === '') {
                self::oauthError('invalid_request', 'code, redirect_uri, and code_verifier are required.', 400);
                return;
            }

            $db = Database::getConnection();
            $stmt = $db->prepare("SELECT * FROM oauth_codes WHERE code_hash = ? LIMIT 1");
            $stmt->execute([hash('sha256', $code)]);
            $codeRow = $stmt->fetch();

            if (!$codeRow || !empty($codeRow['used'])) {
                self::oauthError('invalid_grant', 'Authorization code is invalid or already used.', 400);
                return;
            }

            $expTs = strtotime($codeRow['expires_at'] . ' UTC');
            if ($expTs === false || $expTs < time()) {
                self::oauthError('invalid_grant', 'Authorization code has expired.', 400);
                return;
            }

            if ($clientId !== '' && !hash_equals($codeRow['client_id'], $clientId)) {
                self::oauthError('invalid_grant', 'client_id mismatch.', 400);
                return;
            }
            $effectiveClientId = $codeRow['client_id'];

            if (!hash_equals($codeRow['redirect_uri'], $redirectUri)) {
                self::oauthError('invalid_grant', 'redirect_uri mismatch.', 400);
                return;
            }

            $client = self::findClient($effectiveClientId);
            if ($client && !empty($client['client_secret_hash'])) {
                if ($clientSecret === '' || !hash_equals($client['client_secret_hash'], hash('sha256', $clientSecret))) {
                    self::oauthError('invalid_client', 'Invalid client_secret.', 401);
                    return;
                }
            }

            if (!self::verifyPkce($codeVerifier, $codeRow['code_challenge'], $codeRow['code_challenge_method'])) {
                self::oauthError('invalid_grant', 'PKCE code_verifier check failed.', 400);
                return;
            }

            // Mark code single-use
            $upd = $db->prepare("UPDATE oauth_codes SET used = 1 WHERE id = ?");
            $upd->execute([$codeRow['id']]);

            $ttl = 2592000; // 30 days
            $scope = $codeRow['scope'] ?: 'mcp sites:read sites:write';
            $tokenLabel = 'OAuth: ' . ($client['client_name'] ?? 'MCP Client');
            $issued = Auth::createApiToken((int)$codeRow['user_id'], $tokenLabel, $scope, $ttl, true);

            json_response([
                'access_token' => $issued['token'],
                'token_type' => 'Bearer',
                'expires_in' => $ttl,
                'refresh_token' => $issued['refresh_token'],
                'scope' => $scope,
            ]);
            return;
        }

        if ($grantType === 'refresh_token') {
            $refreshToken = trim((string)($body['refresh_token'] ?? ''));
            if ($refreshToken === '') {
                self::oauthError('invalid_request', 'refresh_token is required.', 400);
                return;
            }

            $db = Database::getConnection();
            $stmt = $db->prepare("SELECT * FROM api_tokens WHERE refresh_token_hash = ? LIMIT 1");
            $stmt->execute([hash('sha256', $refreshToken)]);
            $oldRow = $stmt->fetch();
            if (!$oldRow) {
                self::oauthError('invalid_grant', 'Refresh token is invalid or revoked.', 400);
                return;
            }

            $del = $db->prepare("DELETE FROM api_tokens WHERE id = ?");
            $del->execute([$oldRow['id']]);

            $ttl = 2592000;
            $scope = $oldRow['scopes'] ?: 'mcp sites:read sites:write';
            $issued = Auth::createApiToken((int)$oldRow['user_id'], $oldRow['name'], $scope, $ttl, true);

            json_response([
                'access_token' => $issued['token'],
                'token_type' => 'Bearer',
                'expires_in' => $ttl,
                'refresh_token' => $issued['refresh_token'],
                'scope' => $scope,
            ]);
            return;
        }

        self::oauthError('unsupported_grant_type', 'Supported grant_type values: authorization_code, refresh_token.', 400);
    }

    private static function verifyPkce(string $verifier, string $challenge, string $method): bool {
        if ($method === 'S256') {
            $computed = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
            return hash_equals($challenge, $computed);
        }
        if ($method === 'plain') {
            return hash_equals($challenge, $verifier);
        }
        return false;
    }

    private static function findClient(string $clientId): ?array {
        if ($clientId === '') {
            return null;
        }
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM oauth_clients WHERE client_id = ? LIMIT 1");
        $stmt->execute([$clientId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private static function isValidRedirectUri(string $uri): bool {
        $parts = parse_url($uri);
        if (!$parts || empty($parts['scheme']) || empty($parts['host'])) {
            return false;
        }
        $scheme = strtolower($parts['scheme']);
        $host = strtolower($parts['host']);
        if ($scheme === 'https') {
            return true;
        }
        if ($scheme === 'http' && in_array($host, ['localhost', '127.0.0.1', '[::1]'], true)) {
            return true;
        }
        return false;
    }

    private static function parseBasicClientAuth(): array {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/^\s*Basic\s+(.+)\s*$/i', $header, $m)) {
            $decoded = base64_decode(trim($m[1]), true);
            if ($decoded !== false && str_contains($decoded, ':')) {
                [$id, $sec] = explode(':', $decoded, 2);
                return [urldecode($id), urldecode($sec)];
            }
        }
        return [null, null];
    }

    private static function redirectWithError(string $redirectUri, string $error, string $desc, string $state = ''): void {
        $params = ['error' => $error, 'error_description' => $desc];
        if ($state !== '') {
            $params['state'] = $state;
        }
        $sep = str_contains($redirectUri, '?') ? '&' : '?';
        header('Location: ' . $redirectUri . $sep . http_build_query($params));
        exit;
    }

    private static function renderAuthorizeError(string $message): void {
        http_response_code(400);
        render_view('oauth_authorize', [
            'title' => 'Authorization Error — PageDock',
            'error' => $message,
            'client' => null,
            'user' => Auth::user(),
            'csrfToken' => '',
            'params' => [],
        ]);
    }

    private static function oauthError(string $error, string $description, int $status = 400): void {
        json_response([
            'error' => $error,
            'error_description' => $description,
        ], $status);
    }
}

