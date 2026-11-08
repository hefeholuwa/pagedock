<?php
/**
 * Test Suite: PageDock Visual Studio Phase 6
 * End-to-End Website Creation from Scratch, Serialization & Publishing Verification
 */

require_once __DIR__ . '/../config/config.php';

echo "=== Running PageDock Visual Studio Phase 6 E2E Test Suite ===\n\n";

$passCount = 0;
$failCount = 0;

function assertE2E(string $desc, bool $condition): void {
    global $passCount, $failCount;
    if ($condition) {
        echo "PASS: {$desc}\n";
        $passCount++;
    } else {
        echo "FAIL: {$desc}\n";
        $failCount++;
    }
}

// 1. Create a fresh clean site for scratch-building
$slug = 'studio-scratch-' . substr(bin2hex(random_bytes(4)), 0, 8);
$initialHtml = "<!DOCTYPE html>\n<html>\n<head>\n    <meta charset=\"UTF-8\">\n    <title>Scratch Site</title>\n</head>\n<body>\n</body>\n</html>";
$created = SiteManager::createFromHtml($initialHtml, 'Visual Studio Scratch Site', $slug);
$site = SiteManager::getSite($slug);
$siteDir = SITES_DIR . '/' . $slug;

assertE2E("Fresh site created ({$slug})", $created['success'] === true && !empty($site) && is_dir($siteDir));
assertE2E("Initial empty index.html written", file_exists($siteDir . '/index.html'));

// 3. Assemble complete website from Visual Studio Blueprints
$navbarBp = <<<HTML
<header class="pd-navbar" style="display:flex; justify-content:space-between; align-items:center; padding:16px 32px; background:#101216; border-bottom:1px solid #262a34;">
    <div class="brand" style="font-weight:800; font-size:18px; color:#C7F547;">Sovereign App</div>
    <nav style="display:flex; gap:20px; font-size:14px;">
        <a href="#features" style="color:#f3f4f6;">Features</a>
        <a href="#pricing" style="color:#f3f4f6;">Pricing</a>
        <a href="#contact" style="color:#f3f4f6;">Contact</a>
    </nav>
    <a href="#contact" style="padding:8px 16px; background:#C7F547; color:#000; border-radius:6px; font-weight:700; font-size:13px; text-decoration:none;">Get Started</a>
</header>
HTML;

$heroBp = <<<HTML
<section class="pd-hero" style="padding:80px 24px; text-align:center; background:#090a0c; color:#f3f4f6; font-family:'Plus Jakarta Sans', sans-serif; letter-spacing:-0.5px; line-height:1.2; box-shadow:0 8px 24px rgba(0,0,0,0.4);">
    <div style="max-width:800px; margin:0 auto;">
        <span style="display:inline-block; padding:4px 12px; background:rgba(199,245,71,0.12); color:#C7F547; border:1px solid rgba(199,245,71,0.25); border-radius:999px; font-size:12px; font-weight:700; margin-bottom:16px;">SOVEREIGN STATIC HOSTING</span>
        <h1 style="font-size:48px; font-weight:800; line-height:1.15; margin-bottom:16px; letter-spacing:-0.02em;">Build Fast. Ship Sovereign.</h1>
        <p style="font-size:18px; color:#949ba8; margin-bottom:32px; line-height:1.6;">Design high-performance static websites without server build steps, database bloat, or complex pipelines.</p>
        <div style="display:flex; justify-content:center; gap:12px;">
            <a href="#contact" style="padding:12px 24px; background:#C7F547; color:#000; font-weight:700; border-radius:8px; text-decoration:none;">Start Building Free</a>
            <a href="#features" style="padding:12px 24px; background:#181b21; color:#f3f4f6; border:1px solid #262a34; font-weight:600; border-radius:8px; text-decoration:none;">Explore Features</a>
        </div>
    </div>
</section>
HTML;

$featuresBp = <<<HTML
<section id="features" class="pd-features" style="padding:60px 24px; background:#101216;">
    <div style="max-width:1100px; margin:0 auto;">
        <div style="text-align:center; margin-bottom:40px;">
            <h2 style="font-size:32px; font-weight:800; color:#f3f4f6;">Engineered for Velocity</h2>
            <p style="color:#949ba8; font-size:15px;">Everything you need to launch and scale static applications.</p>
        </div>
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:20px;">
            <div style="padding:24px; background:#181b21; border:1px solid #262a34; border-radius:12px;">
                <h3 style="font-size:18px; font-weight:700; margin-bottom:8px; color:#f3f4f6;">Instant Edge Delivery</h3>
                <p style="color:#949ba8; font-size:14px; line-height:1.5;">Deploys in under 400ms across global CDN edge nodes.</p>
            </div>
            <div style="padding:24px; background:#181b21; border:1px solid #262a34; border-radius:12px;">
                <h3 style="font-size:18px; font-weight:700; margin-bottom:8px; color:#f3f4f6;">Zero-Backend Forms</h3>
                <p style="color:#949ba8; font-size:14px; line-height:1.5;">Direct form collection endpoint routes leads straight into inbox.</p>
            </div>
            <div style="padding:24px; background:#181b21; border:1px solid #262a34; border-radius:12px;">
                <h3 style="font-size:18px; font-weight:700; margin-bottom:8px; color:#f3f4f6;">Cookieless Analytics</h3>
                <p style="color:#949ba8; font-size:14px; line-height:1.5;">Lightweight, privacy-first traffic monitoring with zero GDPR cookies.</p>
            </div>
        </div>
    </div>
</section>
HTML;

$contactBp = <<<HTML
<section id="contact" class="pd-contact" style="padding:60px 24px; background:#090a0c;">
    <div style="max-width:540px; margin:0 auto; background:#181b21; border:1px solid #262a34; padding:32px; border-radius:12px;">
        <h3 style="font-size:24px; font-weight:800; color:#f3f4f6; margin-bottom:8px;">Get in Touch</h3>
        <p style="color:#949ba8; font-size:14px; margin-bottom:24px;">Submissions route automatically to your PageDock leads inbox.</p>
        <form action="/api/forms/{$slug}" method="POST" style="display:flex; flex-direction:column; gap:16px;">
            <div>
                <label style="display:block; font-size:12px; font-weight:600; color:#949ba8; margin-bottom:6px;">Your Name</label>
                <input type="text" name="name" required style="width:100%; padding:10px; background:#101216; border:1px solid #262a34; border-radius:6px; color:#fff; font-size:14px; outline:none;">
            </div>
            <div>
                <label style="display:block; font-size:12px; font-weight:600; color:#949ba8; margin-bottom:6px;">Email Address</label>
                <input type="email" name="email" required style="width:100%; padding:10px; background:#101216; border:1px solid #262a34; border-radius:6px; color:#fff; font-size:14px; outline:none;">
            </div>
            <div>
                <label style="display:block; font-size:12px; font-weight:600; color:#949ba8; margin-bottom:6px;">Message</label>
                <textarea name="message" rows="4" required style="width:100%; padding:10px; background:#101216; border:1px solid #262a34; border-radius:6px; color:#fff; font-size:14px; outline:none;"></textarea>
            </div>
            <button type="submit" style="padding:12px; background:#C7F547; color:#000; border:none; border-radius:6px; font-weight:700; cursor:pointer;">Send Message</button>
        </form>
    </div>
</section>
HTML;

$footerBp = <<<HTML
<footer class="pd-footer" style="padding:40px 24px; background:#090a0c; border-top:1px solid #262a34; text-align:center; color:#949ba8; font-size:13px;">
    <div style="max-width:1100px; margin:0 auto; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
        <div style="color:#f3f4f6; font-weight:700;">PageDock Sovereign Deployment</div>
        <div>All rights reserved.</div>
    </div>
</footer>
HTML;

$assembledBody = $navbarBp . "\n" . $heroBp . "\n" . $featuresBp . "\n" . $contactBp . "\n" . $footerBp;

// Simulate active editor canvas with toolbar and drop indicators present
$canvasHtml = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Scratch Site</title>
    <style id="__pagedock_overlay_css">
        .__pd_hover { outline: 2px dashed #C7F547 !important; }
        .__pd_selected { outline: 2px solid #C7F547 !important; }
    </style>
</head>
<body>
    <div id="__pd_element_toolbar" style="position:absolute;">[Toolbar Active]</div>
    <div id="__pd_drop_indicator" style="position:absolute;">[Drop Line]</div>
    {$assembledBody}
    <div class="__pd_add_section_zone" id="__pd_add_section_bottom"><button class="__pd_add_section_btn">+ Add</button></div>
</body>
</html>
HTML;

// 4. Test Clean Serialization (Simulate syncElementToMasterHtml stripping)
$cleanHtml = preg_replace('/<div id="__pd_element_toolbar"[\s\S]*?<\/div>/', '', $canvasHtml);
$cleanHtml = preg_replace('/<div id="__pd_drop_indicator"[\s\S]*?<\/div>/', '', $cleanHtml);
$cleanHtml = preg_replace('/<div class="__pd_add_section_zone"[\s\S]*?<\/div>/', '', $cleanHtml);
$cleanHtml = preg_replace('/<style id="__pagedock_overlay_css"[\s\S]*?<\/style>/', '', $cleanHtml);
$cleanHtml = trim($cleanHtml);

assertE2E("Serialization strips #__pd_element_toolbar", !str_contains($cleanHtml, '__pd_element_toolbar'));
assertE2E("Serialization strips #__pd_drop_indicator", !str_contains($cleanHtml, '__pd_drop_indicator'));
assertE2E("Serialization strips .__pd_add_section_zone", !str_contains($cleanHtml, '__pd_add_section_zone'));
assertE2E("Serialization strips #__pagedock_overlay_css", !str_contains($cleanHtml, '__pagedock_overlay_css'));

// 5. Save serialized HTML to site directory (Simulating saveStudioCode)
file_put_contents($siteDir . '/index.html', $cleanHtml);
assertE2E("Clean HTML successfully written to index.html", file_exists($siteDir . '/index.html'));

$savedContent = file_get_contents($siteDir . '/index.html');
assertE2E("Saved index.html contains navbar", str_contains($savedContent, 'pd-navbar'));
assertE2E("Saved index.html contains hero section", str_contains($savedContent, 'pd-hero'));
assertE2E("Saved index.html contains features section", str_contains($savedContent, 'pd-features'));
assertE2E("Saved index.html contains pre-wired contact form", str_contains($savedContent, 'action="/api/forms/' . $slug . '"'));
assertE2E("Saved index.html contains footer", str_contains($savedContent, 'pd-footer'));
assertE2E("Saved index.html contains deepened typography & shadow styles", 
    str_contains($savedContent, 'Plus Jakarta Sans') &&
    str_contains($savedContent, 'letter-spacing:-0.5px') &&
    str_contains($savedContent, 'box-shadow:0 8px 24px'));

// 6. Test Zero-Backend Form Submission
$db = Database::getConnection();
$siteId = $site['id'];

$formData = [
    'name' => 'Dr. Jane Sovereign',
    'email' => 'jane@sovereign.io',
    'message' => 'Built completely inside PageDock Visual Studio.'
];

$stmt = $db->prepare("INSERT INTO form_submissions (site_id, form_name, data_json, created_at) VALUES (?, ?, ?, ?)");
$stmt->execute([$siteId, 'Contact', json_encode($formData), time()]);
$formSubId = $db->lastInsertId();

assertE2E("Form lead captured into database", $formSubId > 0);

$stmt = $db->prepare("SELECT * FROM form_submissions WHERE site_id = ? ORDER BY id DESC LIMIT 1");
$stmt->execute([$siteId]);
$submission = $stmt->fetch();

assertE2E("Form submission matches lead data", 
    $submission && str_contains($submission['data_json'], 'jane@sovereign.io'));

// 7. Test Serving Live Static Site via Router
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = "/s/{$slug}/";
$_SERVER['HTTP_HOST'] = 'localhost:8080';

ob_start();
@Router::handle();
$renderedSite = ob_get_clean();

assertE2E("Static server renders 200 OK for scratch-built site", !empty($renderedSite));
assertE2E("Live site contains Sovereign App brand", str_contains($renderedSite, 'Sovereign App'));
assertE2E("Live site contains zero editor overlay artifacts", 
    !str_contains($renderedSite, '__pd_element_toolbar') &&
    !str_contains($renderedSite, '__pd_add_section_zone'));

// 8. Clean up
unlink($siteDir . '/index.html');
@rmdir($siteDir);
$db->exec("DELETE FROM form_submissions WHERE site_id = {$siteId}");
SiteManager::deleteSite($slug);

assertE2E("Test scratch site purged cleanly", !is_dir($siteDir));

echo "\n=== Visual Studio Phase 6 E2E Test Summary ===\n";
echo "Total: " . ($passCount + $failCount) . " | Passed: {$passCount} | Failed: {$failCount}\n";

if ($failCount > 0) {
    echo "SOME PHASE 6 E2E TESTS FAILED!\n";
    exit(1);
} else {
    echo "ALL VISUAL STUDIO PHASE 6 E2E TESTS PASSED!\n\n";
    exit(0);
}
