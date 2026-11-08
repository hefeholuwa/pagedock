<?php
/**
 * Test Suite for Phase 2: Zero-Backend Forms Engine & Forms Inbox
 */

require_once dirname(__DIR__) . '/config/config.php';

$baseUrl = 'http://localhost:8080';
$results = [];

function assertTest($name, $condition, $details = '') {
    global $results;
    if ($condition) {
        $results[] = ['name' => $name, 'status' => 'PASS'];
        echo "PASS: $name\n";
    } else {
        $results[] = ['name' => $name, 'status' => 'FAIL', 'details' => $details];
        echo "FAIL: $name ($details)\n";
    }
}

echo "=== Running Phase 2: Forms Inbox & Management Test Suite ===\n\n";

// 1. Setup Test Site
$testSlug = 'inbox-test-' . time();
$siteDir = SITES_DIR . '/' . $testSlug;
mkdir($siteDir, 0755, true);

$htmlContent = <<<HTML
<!DOCTYPE html>
<html>
<head><title>Forms Inbox Test Site</title></head>
<body>
  <h1>Contact Page</h1>
  <form action="/__pd/form" method="POST">
    <input type="text" name="name">
    <input type="email" name="email">
    <button type="submit">Submit</button>
  </form>
</body>
</html>
HTML;

file_put_contents($siteDir . '/index.html', $htmlContent);

$db = Database::getConnection();
$claimToken = 'claim-' . bin2hex(random_bytes(10));
$stmt = $db->prepare("INSERT INTO sites (claim_token, slug, title, entry_file, file_count, total_size_bytes) VALUES (?, ?, ?, ?, 1, 1024)");
$stmt->execute([$claimToken, $testSlug, 'Inbox Test Site', 'index.html']);
$siteId = (int)$db->lastInsertId();

// 2. Submit Valid Lead via /__pd/form
echo "--- 1. Submitting Valid Lead ---\n";
$validPayload = [
    '_site' => $testSlug,
    '_form' => 'Lead Gen Form',
    'name' => 'Sarah Connor',
    'email' => 'sarah@skynet-resistance.io',
    'interest' => 'Enterprise Tier'
];

$ch = curl_init("$baseUrl/__pd/form");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($validPayload));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json', "Referer: $baseUrl/s/$testSlug/"]);
$resJson = curl_exec($ch);
$validData = json_decode($resJson, true);

assertTest("Valid form submission returns ok=true", ($validData['ok'] ?? false) === true);
$validSubId = (int)($validData['submission_id'] ?? 0);
assertTest("Valid submission allocated valid ID", $validSubId > 0);

// 3. Submit Bot Lead with Honeypot (_pd_hp)
echo "\n--- 2. Submitting Spam Lead via Honeypot ---\n";
$spamPayload = [
    '_site' => $testSlug,
    '_form' => 'Lead Gen Form',
    '_pd_hp' => 'Automated bot crawler input',
    'name' => 'Crypto Bot 9000',
    'email' => 'bot@pumpanddump.xyz'
];

$ch = curl_init("$baseUrl/__pd/form");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($spamPayload));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json', "Referer: $baseUrl/s/$testSlug/"]);
$resJson = curl_exec($ch);
$spamData = json_decode($resJson, true);

assertTest("Spam form returns deceptive ok=true", ($spamData['ok'] ?? false) === true);
$spamSubId = (int)($spamData['submission_id'] ?? 0);
assertTest("Spam submission recorded in DB", $spamSubId > 0);

// Verify DB statuses
$stmt = $db->prepare("SELECT is_spam FROM form_submissions WHERE id = ?");
$stmt->execute([$validSubId]);
$validRec = $stmt->fetch();
$stmt->closeCursor();
assertTest("Valid submission has is_spam = 0", (int)($validRec['is_spam'] ?? 1) === 0);

$stmt = $db->prepare("SELECT is_spam FROM form_submissions WHERE id = ?");
$stmt->execute([$spamSubId]);
$spamRec = $stmt->fetch();
$stmt->closeCursor();
assertTest("Honeypot submission has is_spam = 1", (int)($spamRec['is_spam'] ?? 0) === 1);

// 4. Test Forms Stats
echo "\n--- 3. Testing Submission Statistics ---\n";
$stats = Forms::getSubmissionStats($siteId);
assertTest("Stats total count equals 2", $stats['total'] === 2, "Got: " . $stats['total']);
assertTest("Stats valid count equals 1", $stats['valid'] === 1, "Got: " . $stats['valid']);
assertTest("Stats spam count equals 1", $stats['spam'] === 1, "Got: " . $stats['spam']);

// 5. Test Forms Submissions Isolation (Valid vs Spam Quarantine)
echo "\n--- 4. Testing Submissions Filtering ---\n";
$validSubs = Forms::getSubmissions($siteId, false);
assertTest("Valid filter returns exactly 1 item", count($validSubs) === 1);
assertTest("Valid filter item is Sarah Connor", ($validSubs[0]['data']['name'] ?? '') === 'Sarah Connor');

$quarantineSubs = Forms::getSubmissions($siteId, true);
assertTest("Spam filter returns exactly 1 item", count($quarantineSubs) === 1);
assertTest("Spam filter item is Crypto Bot 9000", ($quarantineSubs[0]['data']['name'] ?? '') === 'Crypto Bot 9000');

// 6. Test Dashboard Forms Inbox Route (GET /dashboard/forms/{slug}?token={token})
echo "\n--- 5. Testing Dashboard Inbox View Route ---\n";
$ch = curl_init("$baseUrl/dashboard/forms/$testSlug?token=$claimToken");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$html = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

assertTest("GET /dashboard/forms/{slug} returns HTTP 200", $code === 200, "Code: $code");
assertTest("Inbox view renders site title", strpos($html, 'Inbox Test Site') !== false);
assertTest("Inbox view renders valid lead Sarah Connor", strpos($html, 'Sarah Connor') !== false);
assertTest("Inbox view excludes spam in default view", strpos($html, 'Crypto Bot 9000') === false);
assertTest("Inbox view renders Export CSV button", strpos($html, 'Export CSV') !== false);
assertTest("Inbox view renders Google Sheets button", strpos($html, 'Google Sheets Formula') !== false);

// Test Spam Quarantine View
$ch = curl_init("$baseUrl/dashboard/forms/$testSlug?token=$claimToken&spam=1");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$spamHtml = curl_exec($ch);
assertTest("Spam view renders Crypto Bot 9000", strpos($spamHtml, 'Crypto Bot 9000') !== false);
assertTest("Spam view excludes Sarah Connor", strpos($spamHtml, 'Sarah Connor') === false);

// 7. Test Toggle Spam Endpoint (POST /api/forms/{id}/toggle-spam?token={token})
echo "\n--- 6. Testing Toggle Spam API ---\n";
$ch = curl_init("$baseUrl/api/forms/$validSubId/toggle-spam?token=$claimToken");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
$resJson = curl_exec($ch);
$toggleData = json_decode($resJson, true);
assertTest("Toggle spam returns success=true", ($toggleData['success'] ?? false) === true);

$stmt = $db->prepare("SELECT is_spam FROM form_submissions WHERE id = ?");
$stmt->execute([$validSubId]);
$toggledRec = $stmt->fetch();
$stmt->closeCursor();
assertTest("Valid submission flipped to spam (is_spam = 1)", (int)($toggledRec['is_spam'] ?? 0) === 1);

// Toggle back
$ch = curl_init("$baseUrl/api/forms/$validSubId/toggle-spam?token=$claimToken");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_exec($ch);

$stmt = $db->prepare("SELECT is_spam FROM form_submissions WHERE id = ?");
$stmt->execute([$validSubId]);
$restoredRec = $stmt->fetch();
$stmt->closeCursor();
assertTest("Submission restored to valid (is_spam = 0)", (int)($restoredRec['is_spam'] ?? 1) === 0);

// 8. Test Delete Submission Endpoint (POST /api/forms/{id}/delete?token={token})
echo "\n--- 7. Testing Delete Submission API ---\n";
$ch = curl_init("$baseUrl/api/forms/$spamSubId/delete?token=$claimToken");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
$resJson = curl_exec($ch);
$delData = json_decode($resJson, true);
assertTest("Delete submission returns success=true", ($delData['success'] ?? false) === true);

$stmt = $db->prepare("SELECT COUNT(*) as count FROM form_submissions WHERE id = ?");
$stmt->execute([$spamSubId]);
$countRec = $stmt->fetch();
$stmt->closeCursor();
assertTest("Spam submission permanently removed from database", (int)($countRec['count'] ?? 1) === 0);

// 9. Unauthorized Rejection Check
echo "\n--- 8. Testing Security & Unauthorized Access Checks ---\n";
$ch = curl_init("$baseUrl/api/forms/$validSubId/delete"); // No claim token or login session
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
$unauthJson = curl_exec($ch);
$unauthCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
assertTest("Unauthenticated delete rejected with HTTP 403", $unauthCode === 403, "Code: $unauthCode");

// 10. CSV Export Header Check
echo "\n--- 9. Testing CSV Export Endpoint Headers ---\n";
$ch = curl_init("$baseUrl/api/sites/$testSlug/forms/export");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
$csvResponse = curl_exec($ch);
assertTest("CSV export has Access-Control-Allow-Origin header", strpos($csvResponse, 'Access-Control-Allow-Origin: *') !== false);
assertTest("CSV export has Content-Type text/csv", strpos($csvResponse, 'Content-Type: text/csv') !== false);
assertTest("CSV export contains Sarah Connor", strpos($csvResponse, 'Sarah Connor') !== false);

// Cleanup
$files = glob($siteDir . '/*');
foreach ($files as $f) unlink($f);
rmdir($siteDir);
usleep(100000);
$db->exec("DELETE FROM form_submissions WHERE site_id = $siteId");
$db->exec("DELETE FROM sites WHERE id = $siteId");

echo "\n============================================\n";
$total = count($results);
$passed = count(array_filter($results, fn($r) => $r['status'] === 'PASS'));
echo "RESULTS: $passed / $total TESTS PASSED\n";
echo "============================================\n";

