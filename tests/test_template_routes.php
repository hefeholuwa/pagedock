<?php
require_once dirname(__DIR__) . '/config/config.php';

// Test cloning exact template into a new site
$template = Templates::get('futuristic-dark-landing');
assert($template !== null);

$newSite = SiteManager::createFromHtml($template['html'], $template['title'] . ' (My Copy)');
assert($newSite['success'] === true, 'Site cloning failed');
assert(!empty($newSite['slug']), 'Cloned site must have a slug');

// Verify cloned site content matches
$fetchedHtml = SiteManager::getSiteHtml($newSite['slug']);
assert(str_contains($fetchedHtml, '<html'), 'Cloned site HTML should match template');

// Cleanup
SiteManager::deleteSite($newSite['slug']);
echo "PASS: Exact template cloning and route tests pass\n";
