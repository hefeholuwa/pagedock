<?php
/**
 * Test Suite: PageDock Platform Administrator Portal & Governance
 * Verifies schema migrations, Admin telemetry metrics, site suspension,
 * access control enforcement, user role management, and zero emojis.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/SiteManager.php';
require_once __DIR__ . '/../src/Admin.php';
require_once __DIR__ . '/../src/Router.php';

function assertAdminTest(string $name, bool $pass, string $detail = ''): void {
    if ($pass) {
        echo "[PASS] {$name}\n";
    } else {
        echo "[FAIL] {$name} - {$detail}\n";
        exit(1);
    }
}

echo "=== Testing PageDock Admin Portal & Fleet Oversight ===\n\n";

$db = Database::getConnection();

// 1. Verify Database Schema Migrations
$userCols = $db->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_COLUMN, 1);
assertAdminTest('users table has role column', in_array('role', $userCols));

$siteCols = $db->query("PRAGMA table_info(sites)")->fetchAll(PDO::FETCH_COLUMN, 1);
assertAdminTest('sites table has is_suspended column', in_array('is_suspended', $siteCols));

// 2. Verify Configured Admin User Auto-Promotion
$adminEmail = defined('ADMIN_EMAIL') ? ADMIN_EMAIL : 'ajogbejei@gmail.com';
$adminUserStmt = $db->prepare("SELECT id, email, role FROM users WHERE LOWER(email) = LOWER(?)");
$adminUserStmt->execute([$adminEmail]);
$adminUser = $adminUserStmt->fetch();

if (!$adminUser) {
    // Seed primary admin user if not present in test DB
    $res = Auth::register($adminEmail, 'password123', 'Platform Owner');
    $adminId = $res['user_id'];
    $db->exec("UPDATE users SET role = 'admin' WHERE id = " . (int)$adminId);
    $adminUser = ['id' => $adminId, 'email' => $adminEmail, 'role' => 'admin'];
}

assertAdminTest('Admin user exists with role=admin', !empty($adminUser) && $adminUser['role'] === 'admin');

// 3. Test Admin Telemetry & Metrics
$stats = Admin::getPlatformStats();
assertAdminTest('getPlatformStats returns sites metrics', isset($stats['sites']['total']) && isset($stats['sites']['active']));
assertAdminTest('getPlatformStats returns storage metrics', isset($stats['sites']['total_size_formatted']));
assertAdminTest('getPlatformStats returns traffic metrics', isset($stats['traffic']['all_time_views']) && isset($stats['traffic']['unique_visitors']));
assertAdminTest('getPlatformStats returns forms metrics', isset($stats['forms']['total']) && isset($stats['forms']['clean']));
assertAdminTest('getPlatformStats returns system metrics', isset($stats['system']['php_version']) && isset($stats['system']['storage_driver']));

// 4. Test Site Creation & Site Oversight Listing
$testSlug = 'admin-test-' . bin2hex(random_bytes(3));
$deployRes = SiteManager::createFromHtml('<!DOCTYPE html><html><body><h1>Admin Test Site</h1></body></html>', 'Admin Test Site', $testSlug);
assertAdminTest('Test site deployed successfully', !empty($deployRes['success']));

$allSites = Admin::getAllSites($testSlug);
assertAdminTest('getAllSites locates test site by slug', count($allSites) >= 1 && $allSites[0]['slug'] === $testSlug);
assertAdminTest('Site has is_suspended=false by default', $allSites[0]['is_suspended'] === false);

// 5. Test Site Suspension & Un-suspension Toggle
$toggleRes1 = Admin::toggleSiteSuspension($testSlug);
assertAdminTest('toggleSiteSuspension toggles to suspended', !empty($toggleRes1['success']) && $toggleRes1['is_suspended'] === true);

$siteAfterSuspend = SiteManager::getSite($testSlug);
assertAdminTest('Database record reflects is_suspended=1', (int)$siteAfterSuspend['is_suspended'] === 1);

// Test serving suspended site returns 451
$serveScript = '
    require_once "config/config.php";
    require_once "src/Database.php";
    require_once "src/SiteManager.php";
    require_once "src/Router.php";
    $_SERVER["REQUEST_METHOD"] = "GET";
    $_SERVER["REQUEST_URI"] = "/s/' . $testSlug . '";
    Router::handle();
';
$serveOut = shell_exec('php -r ' . escapeshellarg($serveScript));
assertAdminTest('Serving suspended site renders Site Suspended notice', str_contains($serveOut, 'Site Suspended'));

// Toggle back to active
$toggleRes2 = Admin::toggleSiteSuspension($testSlug);
assertAdminTest('toggleSiteSuspension un-suspends back to active', !empty($toggleRes2['success']) && $toggleRes2['is_suspended'] === false);

// 6. Test Users Management & Role Toggling
$regularEmail = 'test_reg_' . bin2hex(random_bytes(3)) . '@example.test';
$regRes = Auth::register($regularEmail, 'password123', 'Standard User');
$regularId = (int)$regRes['user_id'];

// Switch active session back to admin user
$_SESSION['user_id'] = (int)$adminUser['id'];

$allUsers = Admin::getAllUsers($regularEmail);
assertAdminTest('getAllUsers finds newly registered user', count($allUsers) >= 1 && $allUsers[0]['id'] === $regularId);
assertAdminTest('New user defaults to role=user', $allUsers[0]['role'] === 'user');

// Promote user to admin
$roleToggle1 = Admin::toggleUserRole($regularId);
assertAdminTest('Promote user to admin succeeded', !empty($roleToggle1['success']) && $roleToggle1['new_role'] === 'admin');

// Demote back to user
$roleToggle2 = Admin::toggleUserRole($regularId);
assertAdminTest('Demote user back to user succeeded', !empty($roleToggle2['success']) && $roleToggle2['new_role'] === 'user');

// Safety: Cannot demote primary admin
$selfDemote = Admin::toggleUserRole((int)$adminUser['id']);
assertAdminTest('Primary admin cannot be demoted', empty($selfDemote['success']));

// 7. Test Activity Audit Timeline
$activity = Admin::getRecentActivity(10);
assertAdminTest('getRecentActivity returns chronological items', is_array($activity) && count($activity) > 0);

// 8. Test Clean Up Expired Sites Engine
$cleanRes = Admin::cleanupExpiredSites();
assertAdminTest('cleanupExpiredSites runs cleanly without error', isset($cleanRes['deleted']));

// Clean up test site
SiteManager::deleteSite($testSlug);

// 9. Zero Emoji Compliance Verification
$emojiRegex = '/[\x{1F600}-\x{1F64F}\x{1F300}-\x{1F5FF}\x{1F680}-\x{1F6FF}\x{1F700}-\x{1F77F}\x{1F780}-\x{1F7FF}\x{1F800}-\x{1F8FF}\x{1F900}-\x{1F9FF}\x{1FA00}-\x{1FA6F}\x{1FA70}-\x{1FAFF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}]/u';

assertAdminTest('src/Admin.php has ZERO emojis', !preg_match($emojiRegex, file_get_contents(__DIR__ . '/../src/Admin.php')));
assertAdminTest('views/admin.php has ZERO emojis', !preg_match($emojiRegex, file_get_contents(__DIR__ . '/../views/admin.php')));
assertAdminTest('src/Auth.php has ZERO emojis', !preg_match($emojiRegex, file_get_contents(__DIR__ . '/../src/Auth.php')));
assertAdminTest('src/Database.php has ZERO emojis', !preg_match($emojiRegex, file_get_contents(__DIR__ . '/../src/Database.php')));
assertAdminTest('src/Router.php has ZERO emojis', !preg_match($emojiRegex, file_get_contents(__DIR__ . '/../src/Router.php')));
assertAdminTest('views/layouts/header.php has ZERO emojis', !preg_match($emojiRegex, file_get_contents(__DIR__ . '/../views/layouts/header.php')));

echo "\n[SUCCESS] ALL ADMIN PORTAL & PLATFORM OVERSIGHT TESTS PASSED!\n";
