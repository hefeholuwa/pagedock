<?php
// PageDock User Password Provisioning & Reset Tool
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Database.php';

$email = $argv[1] ?? 'ajogbejei@gmail.com';
$password = $argv[2] ?? 'password123';
$name = $argv[3] ?? 'hefeholuwa';

$db = Database::getConnection();
$stmt = $db->prepare("SELECT id, email, name FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();

$hash = password_hash($password, PASSWORD_DEFAULT);

if ($user) {
    $update = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
    $update->execute([$hash, $user['id']]);
    echo "[SUCCESS] Password updated for existing account {$email} to: {$password}\n";
} else {
    $insert = $db->prepare("INSERT INTO users (email, password_hash, name, plan) VALUES (?, ?, ?, 'free')");
    $insert->execute([$email, $hash, $name]);
    $newId = $db->lastInsertId();
    echo "[SUCCESS] Created user account #{$newId} for {$email} ({$name}) with password: {$password}\n";
}

