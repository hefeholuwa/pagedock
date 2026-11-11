<?php

class Auth {
    private static ?int $bearerUserId = null;
    private static bool $bearerChecked = false;
    private static bool $bearerAttempted = false;
    private static bool $bearerInvalid = false;

    public static function hasSession(): bool {
        return isset($_SESSION['user_id']);
    }

    public static function check(): bool {
        if (isset($_SESSION['user_id'])) {
            return true;
        }
        self::resolveBearerRequest();
        return self::$bearerUserId !== null;
    }

    public static function id(): ?int {
        if (isset($_SESSION['user_id'])) {
            return (int)$_SESSION['user_id'];
        }
        self::resolveBearerRequest();
        return self::$bearerUserId;
    }

    public static function setBearerUser(?int $userId): void {
        self::$bearerUserId = $userId;
        self::$bearerChecked = true;
        self::$bearerAttempted = $userId !== null;
        self::$bearerInvalid = false;
    }

    public static function hasInvalidBearer(): bool {
        self::resolveBearerRequest();
        return self::$bearerAttempted && self::$bearerInvalid;
    }

    public static function resolveBearerRequest(): ?int {
        if (self::$bearerChecked) {
            return self::$bearerUserId;
        }
        self::$bearerChecked = true;

        $rawToken = function_exists('get_bearer_token') ? get_bearer_token() : null;
        if (!$rawToken) {
            return null;
        }

        self::$bearerAttempted = true;
        $hash = hash('sha256', $rawToken);
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT id, user_id, expires_at FROM api_tokens WHERE token_hash = ? LIMIT 1");
        $stmt->execute([$hash]);
        $row = $stmt->fetch();

        if (!$row) {
            self::$bearerInvalid = true;
            return null;
        }

        if (!empty($row['expires_at'])) {
            $expTs = strtotime($row['expires_at'] . ' UTC');
            if ($expTs !== false && $expTs < time()) {
                self::$bearerInvalid = true;
                return null;
            }
        }

        $upd = $db->prepare("UPDATE api_tokens SET last_used_at = ? WHERE id = ?");
        $upd->execute([gmdate('Y-m-d H:i:s'), $row['id']]);

        self::$bearerUserId = (int)$row['user_id'];
        self::$bearerInvalid = false;
        return self::$bearerUserId;
    }

    public static function createApiToken(
        int $userId,
        string $name = 'Personal Access Token',
        string $scopes = 'mcp sites:read sites:write',
        ?int $ttlSeconds = null,
        bool $withRefreshToken = false
    ): array {
        $isOAuth = $withRefreshToken || str_starts_with(strtolower($name), 'oauth:');
        $prefixTag = $isOAuth ? 'pd_oauth_' : 'pd_pat_';
        $plainToken = $prefixTag . bin2hex(random_bytes(24));
        $tokenHash = hash('sha256', $plainToken);
        $tokenPrefix = substr($plainToken, 0, 14) . '…';
        $expiresAt = $ttlSeconds !== null ? gmdate('Y-m-d H:i:s', time() + $ttlSeconds) : null;

        $plainRefresh = null;
        $refreshHash = null;
        if ($withRefreshToken) {
            $plainRefresh = 'pd_ref_' . bin2hex(random_bytes(24));
            $refreshHash = hash('sha256', $plainRefresh);
        }

        $db = Database::getConnection();
        $stmt = $db->prepare("
            INSERT INTO api_tokens (user_id, name, token_hash, token_prefix, refresh_token_hash, scopes, expires_at)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$userId, trim($name) ?: 'Personal Access Token', $tokenHash, $tokenPrefix, $refreshHash, $scopes, $expiresAt]);

        $res = [
            'id' => (int)$db->lastInsertId(),
            'token' => $plainToken,
            'token_prefix' => $tokenPrefix,
            'name' => trim($name) ?: 'Personal Access Token',
            'scopes' => $scopes,
            'expires_at' => $expiresAt,
        ];
        if ($plainRefresh !== null) {
            $res['refresh_token'] = $plainRefresh;
        }
        return $res;
    }

    public static function listApiTokens(int $userId): array {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT id, name, token_prefix, scopes, expires_at, last_used_at, created_at
            FROM api_tokens
            WHERE user_id = ?
            ORDER BY created_at DESC, id DESC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll() ?: [];
    }

    public static function revokeApiToken(int $userId, int $tokenId): bool {
        $db = Database::getConnection();
        $stmt = $db->prepare("DELETE FROM api_tokens WHERE id = ? AND user_id = ?");
        $stmt->execute([$tokenId, $userId]);
        return $stmt->rowCount() > 0;
    }

    public static function user(): ?array {
        if (!self::check()) {
            return null;
        }

        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT id, email, name, plan, created_at FROM users WHERE id = ?");
        $stmt->execute([self::id()]);
        $user = $stmt->fetch();

        if (!$user) {
            self::logout();
            return null;
        }

        return $user;
    }

    public static function register(string $email, string $password, string $name = ''): array {
        $email = trim(strtolower($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => 'Please provide a valid email address.'];
        }

        if (strlen($password) < 6) {
            return ['success' => false, 'error' => 'Password must be at least 6 characters.'];
        }

        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            return ['success' => false, 'error' => 'An account with this email already exists.'];
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $insert = $db->prepare("INSERT INTO users (email, password_hash, name) VALUES (?, ?, ?)");
        $insert->execute([$email, $hash, trim($name) ?: explode('@', $email)[0]]);
        
        $userId = (int)$db->lastInsertId();
        $_SESSION['user_id'] = $userId;

        // Auto-claim any guest sites stored in this session
        self::claimSessionSites($userId);

        return ['success' => true, 'user_id' => $userId];
    }

    public static function login(string $email, string $password): array {
        $email = trim(strtolower($email));
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT id, password_hash FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return ['success' => false, 'error' => 'Invalid email or password.'];
        }

        $_SESSION['user_id'] = (int)$user['id'];
        self::claimSessionSites((int)$user['id']);

        return ['success' => true];
    }

    public static function logout(): void {
        unset($_SESSION['user_id']);
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    /**
     * Get validated active guest claim tokens that actually exist in the database.
     * Prunes dead or already-claimed tokens from the session.
     * 
     * @return array List of valid claim tokens
     */
    public static function getActiveGuestTokens(): array {
        if (empty($_SESSION['guest_claim_tokens']) || !is_array($_SESSION['guest_claim_tokens'])) {
            return [];
        }

        $tokens = array_values(array_unique(array_filter($_SESSION['guest_claim_tokens'])));
        if (empty($tokens)) {
            $_SESSION['guest_claim_tokens'] = [];
            return [];
        }

        $db = Database::getConnection();
        $placeholders = implode(',', array_fill(0, count($tokens), '?'));
        $stmt = $db->prepare("SELECT claim_token FROM sites WHERE user_id IS NULL AND claim_token IN ($placeholders)");
        $stmt->execute($tokens);
        $validTokens = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

        // Prune session tokens so stale/deleted sites don't linger
        $_SESSION['guest_claim_tokens'] = $validTokens;

        return $validTokens;
    }

    /**
     * Link any anonymous sites created with this browser session's claim tokens to the user
     */
    public static function claimSessionSites(int $userId): void {
        $tokens = self::getActiveGuestTokens();
        if (!empty($tokens)) {
            $db = Database::getConnection();
            $in = str_repeat('?,', count($tokens) - 1) . '?';
            $params = array_merge([$userId], $tokens);

            $stmt = $db->prepare("UPDATE sites SET user_id = ?, expires_at = NULL WHERE user_id IS NULL AND claim_token IN ($in)");
            $stmt->execute($params);
            
            $_SESSION['guest_claim_tokens'] = [];
        }
    }
}
