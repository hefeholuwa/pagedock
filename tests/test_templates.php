<?php
require_once dirname(__DIR__) . '/config/config.php';

$all = Templates::getAll();
assert(count($all) >= 6, 'Must provide at least 6 starter templates');

$portfolio = Templates::get('developer-portfolio');
assert($portfolio !== null, 'Developer portfolio template must exist');
assert(!empty($portfolio['html']), 'Template must contain HTML code');
assert(!empty($portfolio['category']), 'Template must have a category');

$categories = Templates::getCategories();
assert(in_array('Landing Page', $categories), 'Landing Page category must exist');

echo "PASS: Templates registry tests pass\n";
