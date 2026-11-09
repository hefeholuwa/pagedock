<?php
/**
 * PageDock - Authentication & Dashboard Test Suite
 * Verifies:
 * 1. Gating of /dashboard to authenticated users only (redirects unauthenticated)
 * 2. User registration validation (email format, password min length, duplicate prevention)
 * 3. User login validation (wrong password rejection, valid credential acceptance)
 * 4. Anonymous site auto-claiming on registration & login
 * 5. Dashboard UI rendering (user profile, KPI summary, site cards, search/filter)
 * 6. User logout & session termination
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/SiteManager.php';
require_once __DIR__ . '/../src/VersionManager.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

echo "=== Running Authentication & Dashboard Test Suite ===\n\n";

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

$baseUrl = 'http://localhost:8080';
$cookieFile = sys_get_temp_dir() . '/pagedock_test_cookie_' . time() . '.txt';

// Clean test user data
$testEmail = 'testuser_' . time() . '@example.com';
$testPass = 'sovereign123';
$testName = 'Test Operator';

echo "--- 1. Testing Unauthenticated Dashboard Access Gate ---\n";
// Unauthenticated request to /dashboard
$ch = curl_init("{$baseUrl}/dashboard");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
$resp = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$redirectUrl = curl_getinfo($ch, CURLINFO_REDIRECT_URL);

assertTest($httpCode === 302, "GET /dashboard without auth returns HTTP 302", "Got {$httpCode}");
assertTest(str_contains($resp, 'login?redirect=dashboard') || str_contains($redirectUrl, 'login?redirect=dashboard'), "Redirect location points to /login?redirect=dashboard");

echo "\n--- 2. Testing Registration Validations ---\n";
// Invalid email format
$badEmailRes = Auth::register('not-an-email', 'validpass123');
assertTest($badEmailRes['success'] === false, "Rejects invalid email format");
assertTest(str_contains($badEmailRes['error'], 'valid email'), "Error message mentions valid email");

// Short password (< 6 chars)
$shortPassRes = Auth::register('valid@example.com', '123');
assertTest($shortPassRes['success'] === false, "Rejects short password (< 6 chars)");
assertTest(str_contains($shortPassRes['error'], '6 characters'), "Error message mentions 6 characters");

// Valid Registration
$regRes = Auth::register($testEmail, $testPass, $testName);
assertTest($regRes['success'] === true, "Valid registration succeeds");
$userId = $regRes['user_id'];
assertTest($userId > 0, "Registration returned valid user ID ({$userId})");

// Duplicate Registration Prevention
$dupRes = Auth::register($testEmail, 'anotherpass123');
assertTest($dupRes['success'] === false, "Rejects duplicate email registration");
assertTest(str_contains($dupRes['error'], 'already exists'), "Error message informs email already exists");

// Clear session to test login
Auth::logout();
assertTest(!Auth::check(), "Session cleared after logout");

echo "\n--- 3. Testing Authentication & Login ---\n";
// Wrong password
$wrongPassRes = Auth::login($testEmail, 'wrongpassword');
assertTest($wrongPassRes['success'] === false, "Login rejects incorrect password");
assertTest(!Auth::check(), "User remains unauthenticated on failed login");

// Correct login
$loginRes = Auth::login($testEmail, $testPass);
assertTest($loginRes['success'] === true, "Login succeeds with valid credentials");
assertTest(Auth::check() === true, "Auth::check() returns true");
assertTest(Auth::id() === $userId, "Auth::id() matches registered user ID");

$user = Auth::user();
assertTest($user['email'] === $testEmail, "Auth::user() retrieves correct user record");
assertTest($user['name'] === $testName, "Auth::user() contains user display name");

Auth::logout();

echo "\n--- 4. Testing Anonymous Site Claiming on Registration & Login ---\n";
// 1. Create site as anonymous guest
$_SESSION['guest_claim_tokens'] = [];

$anonSlug = 'anon-site-' . time();
$anonSite = SiteManager::createFromHtml("<!DOCTYPE html><html><body><h1>Anon Page</h1></body></html>", "Anon Site", $anonSlug);
assertTest($anonSite['success'] === true, "Created anonymous site");

$db = Database::getConnection();
$stmt = $db->prepare("SELECT user_id, claim_token FROM sites WHERE slug = ?");
$stmt->execute([$anonSlug]);
$dbRow = $stmt->fetch();
assertTest($dbRow['user_id'] === null, "Anonymous site has user_id = null initially");
$claimToken = $dbRow['claim_token'];

// Emulate guest session with this claim token
$_SESSION['guest_claim_tokens'] = [$claimToken];

// Register a new user with this session active
$claimerEmail = 'claimer_' . time() . '@example.com';
$claimerReg = Auth::register($claimerEmail, 'password123', 'Claimer User');
assertTest($claimerReg['success'] === true, "New claimer registered");
$claimerId = $claimerReg['user_id'];

// Verify site has been claimed
$stmt->execute([$anonSlug]);
$claimedRow = $stmt->fetch();
assertTest((int)$claimedRow['user_id'] === $claimerId, "Anonymous site automatically claimed by new user ID ({$claimerId})");
assertTest(empty($_SESSION['guest_claim_tokens']), "Session claim tokens cleared after claiming");

echo "\n--- 5. Testing HTTP Login & Dashboard Rendering via Cookies ---\n";
// HTTP POST /login with curl and cookie jar
$ch = curl_init("{$baseUrl}/login");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'email' => $claimerEmail,
    'password' => 'password123',
    'redirect' => 'dashboard'
]));
$loginHtml = curl_exec($ch);
$loginCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

assertTest($loginCode === 302, "POST /login returns HTTP 302 redirect", "Got {$loginCode}");

// Access /dashboard with authenticated session cookie
$ch = curl_init("{$baseUrl}/dashboard");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
$dashHtml = curl_exec($ch);
$dashCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

assertTest($dashCode === 200, "GET /dashboard with auth cookie returns HTTP 200 OK");
assertTest(str_contains($dashHtml, 'Claimer User'), "Dashboard renders user profile name 'Claimer User'");
assertTest(str_contains($dashHtml, $claimerEmail), "Dashboard renders user email");
assertTest(str_contains($dashHtml, 'user-avatar-circle'), "Dashboard renders user avatar circle");
assertTest(str_contains($dashHtml, 'site-search-input'), "Dashboard renders instant search & filter input");
assertTest(str_contains($dashHtml, $anonSlug), "Dashboard renders the claimed site ({$anonSlug})");
assertTest(str_contains($dashHtml, "dashboard/forms/{$anonSlug}"), "Dashboard site card links to Forms Inbox");
assertTest(str_contains($dashHtml, "dashboard/analytics/{$anonSlug}"), "Dashboard site card links to Edge Analytics");
assertTest(str_contains($dashHtml, "s/{$anonSlug}"), "Dashboard site card links to Live Site");

echo "\n--- 6. Testing User Logout ---\n";
$ch = curl_init("{$baseUrl}/logout");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
$logoutHtml = curl_exec($ch);
$logoutCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
assertTest($logoutCode === 302, "GET /logout returns HTTP 302");

// Subsequent request to /dashboard should now redirect
$ch = curl_init("{$baseUrl}/dashboard");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
$dashAfterLogout = curl_exec($ch);
$codeAfterLogout = curl_getinfo($ch, CURLINFO_HTTP_CODE);
assertTest($codeAfterLogout === 302, "Subsequent GET /dashboard returns HTTP 302 (logged out)");

echo "\n--- 7. Cleaning Up Test Data ---\n";
// Delete claimed test site
SiteManager::deleteSite($anonSlug);
// Delete test users from DB
$db->exec("DELETE FROM users WHERE id IN ({$userId}, {$claimerId})");
if (file_exists($cookieFile)) unlink($cookieFile);

echo "\n=== Test Results Summary ===\n";
echo "Total Tests: " . ($passCount + $failCount) . " | Passed: {$passCount} | Failed: {$failCount}\n";
if ($failCount === 0) {
    echo "ALL AUTHENTICATION & DASHBOARD TESTS PASSED!\n";
    exit(0);
} else {
    echo "SOME TESTS FAILED!\n";
    exit(1);
}
