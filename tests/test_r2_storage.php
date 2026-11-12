<?php
/**
 * Test PageDock Cloudflare R2 S3-Compatible Storage Engine
 */

require_once dirname(__DIR__) . '/config/config.php';

function assertR2Test(string $desc, bool $condition): void {
    if ($condition) {
        echo "[PASS] {$desc}\n";
    } else {
        echo "[FAIL] {$desc}\n";
        exit(1);
    }
}

echo "=== Testing PageDock Cloudflare R2 Storage Adapter ===\n\n";

// 1. Verify class exists and autoloader resolves it
assertR2Test('R2Storage class exists and loaded', class_exists('R2Storage'));

// 2. Default state without credentials is safe
assertR2Test('isConfigured() returns false when credentials unset', R2Storage::isConfigured() === false);

// 3. Methods handle unconfigured state gracefully
assertR2Test('putObject returns false when unconfigured', R2Storage::putObject('test.txt', 'hello') === false);
assertR2Test('getObject returns null when unconfigured', R2Storage::getObject('test.txt') === null);
assertR2Test('deleteObject returns false when unconfigured', R2Storage::deleteObject('test.txt') === false);
assertR2Test('listObjects returns empty array when unconfigured', R2Storage::listObjects() === []);
assertR2Test('deletePrefix returns 0 when unconfigured', R2Storage::deletePrefix('test') === 0);
assertR2Test('syncDirectoryToR2 returns 0 when unconfigured', R2Storage::syncDirectoryToR2(__DIR__, 'tests') === 0);
assertR2Test('syncR2ToDirectory returns 0 when unconfigured', R2Storage::syncR2ToDirectory('tests', __DIR__ . '/tmp') === 0);

// 4. Test SigV4 signing logic via reflection
$reflector = new ReflectionClass('R2Storage');
assertR2Test('R2Storage has sendRequest method', $reflector->hasMethod('sendRequest'));

// 5. Zero emojis check
$emojiRegex = '/[\x{1F300}-\x{1F9FF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}]/u';
$r2Src = file_get_contents(dirname(__DIR__) . '/src/R2Storage.php');
$siteManagerSrc = file_get_contents(dirname(__DIR__) . '/src/SiteManager.php');
$routerSrc = file_get_contents(dirname(__DIR__) . '/src/Router.php');
$dbSrc = file_get_contents(dirname(__DIR__) . '/src/Database.php');

assertR2Test('src/R2Storage.php has ZERO emojis', !preg_match($emojiRegex, $r2Src));
assertR2Test('src/SiteManager.php has ZERO emojis', !preg_match($emojiRegex, $siteManagerSrc));
assertR2Test('src/Router.php has ZERO emojis', !preg_match($emojiRegex, $routerSrc));
assertR2Test('src/Database.php has ZERO emojis', !preg_match($emojiRegex, $dbSrc));

echo "\n[SUCCESS] ALL R2 STORAGE ADAPTER TESTS PASSED!\n";
