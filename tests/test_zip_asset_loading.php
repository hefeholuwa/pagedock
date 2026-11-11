<?php
/**
 * Test Suite: PageDock ZIP Upload & Asset Resolution Engine
 * Validates:
 * 1. Nested ZIP unpacking & root folder unwrapping (e.g. folder/index.html -> index.html)
 * 2. OS metadata purging (__MACOSX, .DS_Store, Thumbs.db)
 * 3. HTML root-relative link rewriting (href="/style.css" -> href="[base]/style.css")
 * 4. Existing <base href="/"> replacement
 * 5. Common asset subfolder resolution (css/style.css resolved for style.css)
 * 6. Case-insensitive asset matching on Linux
 * 7. Zero emojis across all modified files
 */

require_once dirname(__DIR__) . '/config/config.php';

function assertZipTest(string $name, bool $condition, string $details = ''): void {
    if ($condition) {
        echo "[PASS] $name\n";
    } else {
        echo "[FAIL] $name" . ($details ? " ($details)" : "") . "\n";
        exit(1);
    }
}

echo "=== Testing PageDock ZIP Upload & Static Asset Resolution ===\n\n";

$tmpDir = sys_get_temp_dir() . '/pd_zip_test_' . uniqid();
mkdir($tmpDir, 0755, true);

// 1. Create a simulated Mac-generated ZIP with __MACOSX junk and nested root folder
$zipFile = $tmpDir . '/test_site.zip';
$zip = new ZipArchive();
if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
    // Add Mac OS metadata
    $zip->addEmptyDir('__MACOSX');
    $zip->addFromString('__MACOSX/._test_site', 'Mac binary metadata');
    $zip->addFromString('.DS_Store', 'DS_Store content');

    // Add nested root directory
    $zip->addEmptyDir('test-portfolio');
    $zip->addFromString('test-portfolio/index.html', '<!DOCTYPE html><html><head><base href="/"><link rel="stylesheet" href="/style.css"><script src="/js/main.js"></script></head><body><h1>Hello World</h1><img src="/img/hero.png"></body></html>');
    $zip->addEmptyDir('test-portfolio/css');
    $zip->addFromString('test-portfolio/css/style.css', 'body { background: #000; color: #fff; }');
    $zip->addEmptyDir('test-portfolio/js');
    $zip->addFromString('test-portfolio/js/main.js', 'console.log("ready");');
    $zip->addEmptyDir('test-portfolio/img');
    $zip->addFromString('test-portfolio/img/hero.png', 'fake png bytes');
    $zip->close();
}

assertZipTest('Test zip file generated', file_exists($zipFile));

// 2. Test SiteManager::createFromZip with nested structure
$site = SiteManager::createFromZip($zipFile, 'Asset Test Site');
assertZipTest('createFromZip succeeded', $site['success'] === true);
$slug = $site['slug'];
$siteDir = SITES_DIR . '/' . $slug;

// 3. Verify that OS junk was purged and the enclosing folder was completely unwrapped
assertZipTest('index.html exists at site root (unwrapped)', file_exists($siteDir . '/index.html'));
assertZipTest('__MACOSX was deleted', !file_exists($siteDir . '/__MACOSX'));
assertZipTest('.DS_Store was deleted', !file_exists($siteDir . '/.DS_Store'));
assertZipTest('Nested folder test-portfolio was removed', !file_exists($siteDir . '/test-portfolio'));
assertZipTest('css/style.css exists in site', file_exists($siteDir . '/css/style.css'));
assertZipTest('js/main.js exists in site', file_exists($siteDir . '/js/main.js'));
assertZipTest('img/hero.png exists in site', file_exists($siteDir . '/img/hero.png'));

// 4. Test HTML link rewriting in Router::outputFile
$reflector = new ReflectionClass('Router');
$outputFileMethod = $reflector->getMethod('outputFile');
$outputFileMethod->setAccessible(true);

ob_start();
$outputFileMethod->invoke(null, $siteDir . '/index.html', SiteManager::getSite($slug));
$renderedHtml = ob_get_clean();

$expectedBase = '/s/' . $slug . '/';
assertZipTest('HTML contains corrected site <base href="...">', str_contains($renderedHtml, '<base href="' . $expectedBase . '">'));
assertZipTest('Old <base href="/"> was replaced', !str_contains($renderedHtml, '<base href="/">'));
assertZipTest('Root-relative href="/style.css" was grounded', str_contains($renderedHtml, 'href="' . $expectedBase . 'style.css"'));
assertZipTest('Root-relative src="/js/main.js" was grounded', str_contains($renderedHtml, 'src="' . $expectedBase . 'js/main.js"'));
assertZipTest('Root-relative src="/img/hero.png" was grounded', str_contains($renderedHtml, 'src="' . $expectedBase . 'img/hero.png"'));

// 5. Test Common Subfolder Resolution (requesting style.css when file is in css/style.css)
$serveMethod = $reflector->getMethod('serveSiteFile');
$serveMethod->setAccessible(true);

ob_start();
$serveMethod->invoke(null, $slug, 'style.css');
$cssOutput = ob_get_clean();
assertZipTest('style.css resolved to css/style.css automatically', str_contains($cssOutput, 'background: #000'));

// 6. Test Case-Insensitive Lookup (requesting CSS/Style.css when file is css/style.css)
ob_start();
$serveMethod->invoke(null, $slug, 'CSS/Style.css');
$caseCssOutput = ob_get_clean();
assertZipTest('CSS/Style.css resolved case-insensitively', str_contains($caseCssOutput, 'background: #000'));

// 7. Test Stylesheet Name Aliasing (requesting styles.css when file is css/style.css)
ob_start();
$serveMethod->invoke(null, $slug, 'styles.css');
$aliasedOutput = ob_get_clean();
assertZipTest('styles.css resolved to aliased css/style.css', str_contains($aliasedOutput, 'background: #000'));

// 8. Test is_https_request() detection on reverse proxy public domain
$_SERVER['HTTP_HOST'] = 'pagedock.freedev.app';
$_SERVER['HTTPS'] = 'off';
$_SERVER['SERVER_PORT'] = '80';
assertZipTest('is_https_request() returns true on public production domain', is_https_request() === true);

$_SERVER['HTTP_HOST'] = 'localhost:8080';
assertZipTest('is_https_request() returns false on localhost without SSL', is_https_request() === false);

// 9. Cleanup test site and temp files
$siteRecord = SiteManager::getSite($slug);
if ($siteRecord) {
    SiteManager::deleteSite((int)$siteRecord['id']);
}
@unlink($zipFile);
@rmdir($tmpDir);

// 10. Zero Emojis Compliance
$emojiRegex = '/[\x{1F300}-\x{1F9FF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}]/u';
$siteManagerSrc = file_get_contents(dirname(__DIR__) . '/src/SiteManager.php');
$routerSrc = file_get_contents(dirname(__DIR__) . '/src/Router.php');
$configSrc = file_get_contents(dirname(__DIR__) . '/config/config.php');
$editorJsSrc = file_get_contents(dirname(__DIR__) . '/public/assets/js/editor.js');
assertZipTest('src/SiteManager.php has ZERO emojis', !preg_match($emojiRegex, $siteManagerSrc));
assertZipTest('src/Router.php has ZERO emojis', !preg_match($emojiRegex, $routerSrc));
assertZipTest('config/config.php has ZERO emojis', !preg_match($emojiRegex, $configSrc));
assertZipTest('public/assets/js/editor.js has ZERO emojis', !preg_match($emojiRegex, $editorJsSrc));

echo "\n[SUCCESS] ALL ZIP UPLOAD & STATIC ASSET RESOLUTION TESTS PASSED!\n";
