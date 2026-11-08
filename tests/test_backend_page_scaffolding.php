<?php
/**
 * Test Suite: SiteManager::createPage
 * Verifies smart page scaffolding, layout inheritance, and sanitization.
 */

require_once dirname(__DIR__) . '/config/config.php';

function assert_true($cond, $msg) {
    if (!$cond) {
        echo "[FAIL] $msg\n";
        exit(1);
    }
    echo "[PASS] $msg\n";
}

echo "=== Running SiteManager::createPage Test Suite ===\n\n";

$testSlug = 'test-scaffold-site-' . time();
$siteRes = SiteManager::createFromHtml(
    '<!DOCTYPE html><html><head><title>Original Title</title><link rel="stylesheet" href="style.css"></head><body><!-- hh:shared header --><nav><a href="/" class="active">Home</a><a href="/about">About</a></nav><!-- /hh:shared header --><main><h1>Home</h1></main><!-- hh:shared footer --><footer>&copy; 2026</footer><!-- /hh:shared footer --></body></html>',
    'Test Scaffold Site',
    $testSlug
);

assert_true($siteRes['success'], 'Created temporary test site');

// Test 1: Method exists and creates inherited page
assert_true(method_exists('SiteManager', 'createPage'), 'SiteManager::createPage method exists');

$resInherit = SiteManager::createPage($testSlug, 'about.html', 'About Us', 'inherit', 'index.html');
assert_true($resInherit['success'], 'createPage with layout=inherit succeeded');
assert_true($resInherit['file'] === 'about.html', 'Returned file is about.html');

$siteDir = realpath(SITES_DIR . '/' . $testSlug);
$aboutContent = file_get_contents($siteDir . '/about.html');

assert_true(str_contains($aboutContent, '<title>About Us — Test Scaffold Site</title>') || str_contains($aboutContent, '<title>About Us</title>'), 'Title updated in <head>');
assert_true(str_contains($aboutContent, '<link rel="stylesheet" href="style.css">'), 'Inherited stylesheet in <head>');
assert_true(str_contains($aboutContent, '<!-- hh:shared header -->'), 'Inherited shared header block');
assert_true(str_contains($aboutContent, '<!-- hh:shared footer -->'), 'Inherited shared footer block');
assert_true(str_contains($aboutContent, '<h1>About Us</h1>'), 'Created main section with heading');

// Test 2: Sanitization (omitted .html extension, spaces, directory traversal)
$resSanitize = SiteManager::createPage($testSlug, '../../dangerous path/contact us', 'Contact', 'inherit', 'index.html');
assert_true($resSanitize['success'], 'Sanitizes dangerous filename');
assert_true($resSanitize['file'] === 'contact-us.html', 'Sanitized filename is contact-us.html');
assert_true(file_exists($siteDir . '/contact-us.html'), 'Sanitized file created in site root');

// Test 3: Prevents duplicate creation
$resDup = SiteManager::createPage($testSlug, 'about.html', 'About Again', 'inherit');
assert_true(!$resDup['success'], 'Rejects creating already-existing page');
assert_true(str_contains(strtolower($resDup['error']), 'already exists'), 'Error message states page already exists');

// Cleanup temporary test site
SiteManager::deleteSite($testSlug);
assert_true(!file_exists(SITES_DIR . '/' . $testSlug), 'Cleaned up temporary site');

echo "\nAll SiteManager::createPage tests passed successfully!\n";
