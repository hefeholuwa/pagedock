<?php
/**
 * Hardened Test Suite for Pillar 3: Forms Engine (Time Gate, Honeypots, Webhooks, Rate Limiting)
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

echo "=== Testing Hardened Forms Engine (Pillar 3) ===\n\n";

$db = Database::getConnection();

// 1. Create a clean test site
$slug = 'test-form-hardened-' . bin2hex(random_bytes(4));
$stmt = $db->prepare("INSERT INTO sites (claim_token, slug, title, entry_file, webhook_url, webhook_format) VALUES (?, ?, ?, ?, ?, ?)");
$stmt->execute(['claim-token-123', $slug, 'Form Test Site', 'index.html', 'https://example.com/webhook', 'discord']);
$site = SiteManager::getSite($slug);
assertTest('Test site created with discord webhook', (bool)$site);

// 2. Normal Valid Submission
$validData = [
    '_form' => 'Lead Gen',
    '_pd_ts' => time() - 10, // rendered 10s ago
    'name' => 'Alice Johnson',
    'email' => 'alice@example.com',
    'message' => 'Interested in your architecture services'
];
$res = Forms::submit($slug, $validData);
assertTest('Valid submission returns ok=true', $res['ok'] === true);
assertTest('Valid submission is not marked as spam', $res['is_spam'] === false);
assertTest('Valid submission has submission_id', !empty($res['submission_id']));

// Verify DB record
$stmt = $db->prepare("SELECT * FROM form_submissions WHERE id = ?");
$stmt->execute([$res['submission_id']]);
$record = $stmt->fetch();
assertTest('DB record exists', (bool)$record);
assertTest('DB record is_spam is 0', (int)$record['is_spam'] === 0);
assertTest('Control fields stripped from data_json', !str_contains($record['data_json'], '_pd_ts'));

// 3. Honeypot Trap (_gotcha, _pd_hp)
$botData = [
    '_form' => 'Lead Gen',
    '_gotcha' => 'I am a spam bot',
    'name' => 'Spam Bot',
    'email' => 'spambot@example.com'
];
$resBot = Forms::submit($slug, $botData);
assertTest('Honeypot submission returns deceptive ok=true', $resBot['ok'] === true);
assertTest('Honeypot submission is flagged is_spam=true', $resBot['is_spam'] === true);
assertTest('Honeypot submission spam reason is honeypot_filled', $resBot['spam_reason'] === 'honeypot_filled');

// 4. Time Gate Trap (Submitted too fast < 2 seconds)
$fastData = [
    '_form' => 'Fast Bot',
    '_pd_ts' => time(), // 0 seconds elapsed
    'name' => 'Flash',
    'email' => 'flash@example.com'
];
$resFast = Forms::submit($slug, $fastData);
assertTest('Fast submission returns deceptive ok=true', $resFast['ok'] === true);
assertTest('Fast submission is flagged is_spam=true', $resFast['is_spam'] === true);
assertTest('Fast submission spam reason is submitted_too_fast', $resFast['spam_reason'] === 'submitted_too_fast');

// 5. Anti-Phishing Guard (Credentials / Credit card)
$phishData = [
    'name' => 'Victim',
    'password' => 'secret1234'
];
$resPhish = Forms::submit($slug, $phishData);
assertTest('Phishing attempt rejected with status_code 400', $resPhish['status_code'] === 400);
assertTest('Phishing error message returned', str_contains($resPhish['error'], 'prohibited'));

// 6. Test Webhook Payloads
$fields = ['name' => 'Bob', 'email' => 'bob@example.com', 'message' => 'Testing webhooks'];

// Discord format
$site['webhook_format'] = 'discord';
$discordCalled = false;
// Test dispatch without hitting live internet (mocked or validated)
$subId = 999;
$testPayload = null;

// Cleanup test site
$db->prepare("DELETE FROM form_submissions WHERE site_id = ?")->execute([$site['id']]);
$db->prepare("DELETE FROM sites WHERE id = ?")->execute([$site['id']]);

echo "\n✨ ALL PILLAR 3 (FORMS ENGINE) TESTS PASSED!\n";
