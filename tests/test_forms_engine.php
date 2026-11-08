<?php
/**
 * Test Suite for HTMLHost Master Spec Section 7: Zero-Backend Forms Engine
 */

require_once dirname(__DIR__) . '/config/config.php';

$baseUrl = 'http://localhost:8080';
$results = [];

function assertTest($name, $condition, $details = '') {
    global $results;
    if ($condition) {
        $results[] = ['name' => $name, 'status' => 'PASS'];
        echo "✅ PASS: $name\n";
    } else {
        $results[] = ['name' => $name, 'status' => 'FAIL', 'details' => $details];
        echo "❌ FAIL: $name ($details)\n";
    }
}

echo "=== Running HTMLHost Spec Section 7: Forms Engine Tests ===\n\n";

// Setup Test Site
$testSlug = 'form-test-' . time();
$siteDir = SITES_DIR . '/' . $testSlug;
mkdir($siteDir, 0755, true);

$htmlWithForm = <<<HTML
<!DOCTYPE html>
<html>
<head><title>Form Test</title></head>
<body>
  <h1>Contact Page</h1>
  <form action="/__pd/form" method="post">
    <input type="hidden" name="_form" value="Sales Inquiry">
    <input type="text" name="name" value="Jane Doe">
    <input type="email" name="email" value="jane@example.com">
    <textarea name="message">Hello PageDock!</textarea>
    <button type="submit">Send Message</button>
  </form>
</body>
</html>
HTML;

file_put_contents($siteDir . '/index.html', $htmlWithForm);

$db = Database::getConnection();
$claimToken = 'claim-' . bin2hex(random_bytes(8));
$stmt = $db->prepare("INSERT INTO sites (claim_token, slug, title, entry_file, file_count, total_size_bytes) VALUES (?, ?, ?, ?, 1, 1024)");
$stmt->execute([$claimToken, $testSlug, 'Form Engine Test Site', 'index.html']);
$siteId = (int)$db->lastInsertId();

// 1. Injected Form Helper Script Test
echo "--- 1. Client Helper Script Injection ---\n";
$ch = curl_init("$baseUrl/s/$testSlug/");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$servedHtml = curl_exec($ch);
assertTest("Page contains form helper script", strpos($servedHtml, 'PageDock Zero-Backend Form Engine') !== false);
assertTest("Page helper script contains _pd_hp honeypot injector", strpos($servedHtml, '_pd_hp') !== false);
assertTest("Page helper script targets /__pd/form", strpos($servedHtml, '/__pd/form') !== false);

// 2. Universal AJAX POST /__pd/form (JSON)
echo "\n--- 2. Universal POST /__pd/form (JSON/Fetch) ---\n";
$postPayload = [
    '_site' => $testSlug,
    '_form' => 'Partner Request',
    'name' => 'Alice Johnson',
    'email' => 'alice@partner.org',
    'message' => 'Interested in reseller partnership.'
];

$ch = curl_init("$baseUrl/__pd/form");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postPayload));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json', "Referer: $baseUrl/s/$testSlug/"]);
$resJson = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$data = json_decode($resJson, true);

assertTest("POST /__pd/form returns HTTP 200", $code === 200, "Code: $code, Body: $resJson");
assertTest("Response has ok=true", ($data['ok'] ?? false) === true);
assertTest("Response includes submission_id", !empty($data['submission_id']));

// Verify DB record
$stmt = $db->prepare("SELECT * FROM form_submissions WHERE id = ?");
$stmt->execute([$data['submission_id']]);
$record = $stmt->fetch();
$stmt->closeCursor();
assertTest("DB recorded form_name correctly", ($record['form_name'] ?? '') === 'Partner Request');
assertTest("DB flagged is_spam = 0", ($record['is_spam'] ?? 1) === 0);
$storedData = json_decode($record['data_json'], true);
assertTest("Payload stored clean fields", ($storedData['name'] ?? '') === 'Alice Johnson');
assertTest("Control fields (_form, _site) stripped from payload", !isset($storedData['_form']) && !isset($storedData['_site']));

// 3. Honeypot Anti-Spam Trap (_pd_hp)
echo "\n--- 3. Honeypot Anti-Spam Trap (_pd_hp) ---\n";
$botPayload = [
    '_site' => $testSlug,
    '_form' => 'Spam Attempt',
    '_pd_hp' => 'I am a spam bot filling hidden inputs',
    'name' => 'Bad Bot',
    'email' => 'bot@spammer.ru',
    'message' => 'Buy cheap meds online!'
];

$ch = curl_init("$baseUrl/__pd/form");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($botPayload));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json', "Referer: $baseUrl/s/$testSlug/"]);
$resJson = curl_exec($ch);
$data = json_decode($resJson, true);

assertTest("Bot receives clean success response (deceptive OK)", ($data['ok'] ?? false) === true);
$botSubId = $data['submission_id'] ?? 0;
$stmt = $db->prepare("SELECT is_spam FROM form_submissions WHERE id = ?");
$stmt->execute([$botSubId]);
$botRecord = $stmt->fetch();
$stmt->closeCursor();
assertTest("Database marked submission as spam (is_spam = 1)", (int)($botRecord['is_spam'] ?? 0) === 1);

// 4. Control Overrides (_success and _redirect)
echo "\n--- 4. Control Overrides (_success, _redirect) ---\n";
$customPayload = [
    '_site' => $testSlug,
    '_success' => 'Custom Thank You Message Received!',
    '_redirect' => '/thank-you',
    'email' => 'custom@test.com'
];

$ch = curl_init("$baseUrl/__pd/form");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($customPayload));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json', "Referer: $baseUrl/s/$testSlug/"]);
$resJson = curl_exec($ch);
$data = json_decode($resJson, true);

assertTest("Response contains custom _success message", ($data['message'] ?? '') === 'Custom Thank You Message Received!');
assertTest("Response contains custom _redirect path", ($data['redirect'] ?? '') === '/thank-you');

// 5. Anti-Phishing Guard Rejection
echo "\n--- 5. Anti-Phishing & Sensitive Credentials Guard ---\n";
$phishingPayload = [
    '_site' => $testSlug,
    'username' => 'victim_user',
    'password' => 'secret_p@ss123'
];

$ch = curl_init("$baseUrl/__pd/form");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($phishingPayload));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json', "Referer: $baseUrl/s/$testSlug/"]);
$resJson = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$data = json_decode($resJson, true);

assertTest("Phishing submission rejected with HTTP 400", $code === 400);
assertTest("Rejection specifies credential security policy", strpos($data['error'] ?? '', 'prohibited') !== false);

// 6. CSV Export Endpoint
echo "\n--- 6. CSV Export Endpoint ---\n";
$ch = curl_init("$baseUrl/api/sites/$testSlug/forms/export");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$csv = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

assertTest("CSV export returns HTTP 200", $code === 200);
assertTest("CSV headers include form_name, name, email", strpos($csv, 'form_name') !== false && strpos($csv, 'email') !== false);
assertTest("CSV contains valid submission rows", strpos($csv, 'Alice Johnson') !== false);
assertTest("By default, spam submissions are excluded from export", strpos($csv, 'Bad Bot') === false);

// 7. Legacy Endpoint Backward Compatibility (/api/forms/{slug})
echo "\n--- 7. Legacy Endpoint Compatibility ---\n";
$ch = curl_init("$baseUrl/api/forms/$testSlug");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['message' => 'Legacy test']));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
$resJson = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$data = json_decode($resJson, true);

assertTest("Legacy endpoint /api/forms/{slug} returns HTTP 200", $code === 200);
assertTest("Legacy response indicates success", ($data['success'] ?? false) === true);

// Cleanup
$files = glob($siteDir . '/*');
foreach ($files as $f) unlink($f);
rmdir($siteDir);
usleep(100000); // 100ms
try {
    $db->exec("DELETE FROM form_submissions WHERE site_id = $siteId");
    $db->exec("DELETE FROM sites WHERE id = $siteId");
} catch (Exception $e) {
    // Retry once if busy
    sleep(1);
    $db->exec("DELETE FROM form_submissions WHERE site_id = $siteId");
    $db->exec("DELETE FROM sites WHERE id = $siteId");
}

echo "\n============================================\n";
$total = count($results);
$passed = count(array_filter($results, fn($r) => $r['status'] === 'PASS'));
echo "RESULTS: $passed / $total TESTS PASSED\n";
echo "============================================\n";
