<?php
/**
 * Test Suite for Pillar 5: Lifecycle & Version Engine (TTL, Claim Tokens, Expiry Pruning)
 */

require_once dirname(__DIR__) . '/config/config.php';

function assertTest($name, $condition, $details = '') {
    if ($condition) {
        echo "✅ PASS: $name\n";
    } else {
        echo "❌ FAIL: $name ($details)\n";
        exit(1);
    }
}

echo "=== Testing Lifecycle & Version Engine (Pillar 5) ===\n\n";

$db = Database::getConnection();

// 1. Ensure user exists
$userStmt = $db->query("SELECT id FROM users LIMIT 1");
$user = $userStmt ? $userStmt->fetch() : null;
if (!$user) {
    $resUser = Auth::register('testuser_' . time() . '@example.com', 'secret123', 'Test User');
    $userId = $resUser['user_id'];
} else {
    $userId = (int)$user['id'];
}

// 2. Test Guest Deploy generates 48h TTL
$_SESSION = []; // Ensure anonymous session
$guestSlug = 'test-guest-lifecycle-' . bin2hex(random_bytes(3));
$guestRes = SiteManager::createFromHtml('<h1>Guest Site</h1>', 'Guest Project', $guestSlug);

assertTest('Guest site created', $guestRes['success'] === true);
assertTest('Claim token provided', !empty($guestRes['claim_token']));

$stmt = $db->prepare("SELECT * FROM sites WHERE slug = ?");
$stmt->execute([$guestSlug]);
$siteRow = $stmt->fetch();

assertTest('Site record has user_id NULL', $siteRow['user_id'] === null);
assertTest('Site record has expires_at set', !empty($siteRow['expires_at']));
$expiresEpoch = strtotime($siteRow['expires_at']);
$diffHours = ($expiresEpoch - time()) / 3600;
assertTest('Expires in ~48 hours', $diffHours > 47 && $diffHours <= 48.1);

// 3. Test Claiming Site to Registered User
$claimRes = SiteManager::claimSite($guestRes['claim_token'], $userId);
assertTest('claimSite succeeded', $claimRes['success'] === true);

$stmt->execute([$guestSlug]);
$claimedRow = $stmt->fetch();
assertTest('Claimed site has user_id set', (int)$claimedRow['user_id'] === $userId);
assertTest('Claimed site expires_at is now NULL (permanent)', $claimedRow['expires_at'] === null);

// 4. Test Expired Site Pruning
$expiredSlug = 'test-expired-lifecycle-' . bin2hex(random_bytes(3));
$expiredRes = SiteManager::createFromHtml('<h1>Expired Site</h1>', 'Expired Project', $expiredSlug);

// Artificially set expires_at to 2 hours ago
$db->prepare("UPDATE sites SET expires_at = datetime('now', '-2 hours') WHERE slug = ?")->execute([$expiredSlug]);

$prunedCount = SiteManager::cleanupExpiredSites();
assertTest('cleanupExpiredSites pruned at least 1 site', $prunedCount >= 1);

// Verify expired site is gone from DB and disk
$checkStmt = $db->prepare("SELECT id FROM sites WHERE slug = ?");
$checkStmt->execute([$expiredSlug]);
assertTest('Expired site deleted from DB', $checkStmt->fetch() === false);
assertTest('Expired site folder deleted from disk', !is_dir(SITES_DIR . '/' . $expiredSlug));

// Clean up test sites
$db->prepare("DELETE FROM sites WHERE slug = ?")->execute([$guestSlug]);
exec("rm -rf " . escapeshellarg(SITES_DIR . '/' . $guestSlug));

echo "\n✨ ALL PILLAR 5 (LIFECYCLE & CLAIM ENGINE) TESTS PASSED!\n";

