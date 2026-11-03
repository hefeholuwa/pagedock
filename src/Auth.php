<?php

class Auth {
    public static function check(): bool {
        return isset($_SESSION['user_id']);
    }

    public static function id(): ?int {
        return $_SESSION['user_id'] ?? null;
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
        session_destroy();
    }

    /**
     * Link any anonymous sites created with this browser session's claim tokens to the user
     */
    public static function claimSessionSites(int $userId): void {
        if (!empty($_SESSION['guest_claim_tokens']) && is_array($_SESSION['guest_claim_tokens'])) {
            $db = Database::getConnection();
            $tokens = $_SESSION['guest_claim_tokens'];
            $in = str_repeat('?,', count($tokens) - 1) . '?';
            $params = array_merge([$userId], $tokens);

            $stmt = $db->prepare("UPDATE sites SET user_id = ? WHERE user_id IS NULL AND claim_token IN ($in)");
            $stmt->execute($params);
            
            $_SESSION['guest_claim_tokens'] = [];
        }
    }
}
