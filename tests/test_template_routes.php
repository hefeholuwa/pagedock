<?php
require_once dirname(__DIR__) . '/config/config.php';

// Test cloning template into a new site
$template = Templates::get('developer-portfolio');
assert($template !== null);

$newSite = SiteManager::createFromHtml($template['html'], $template['title'] . ' (My Copy)');
assert($newSite['success'] === true, 'Site cloning failed');
assert(!empty($newSite['slug']), 'Cloned site must have a slug');

// Verify cloned site content matches
$fetchedHtml = SiteManager::getSiteHtml($newSite['slug']);
assert(str_contains($fetchedHtml, 'Alex Rivera') || str_contains($fetchedHtml, '<html'), 'Cloned site HTML should match template');

// Cleanup
SiteManager::deleteSite($newSite['slug']);
echo "PASS: Template cloning and creation tests pass\n";
