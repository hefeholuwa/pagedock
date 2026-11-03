<?php
require_once dirname(__DIR__) . '/config/config.php';

$all = Templates::getAll();
assert(count($all) === 26, 'Must provide all 26 exact htmlhost templates');

$futuristic = Templates::get('futuristic-dark-landing');
assert($futuristic !== null, 'Futuristic Dark Landing template must exist');
assert(!empty($futuristic['html']), 'Template must contain HTML code');
assert(!empty($futuristic['category']), 'Template must have a category');

$categories = Templates::getCategories();
assert(count($categories) >= 5, 'Multiple categories must exist');

echo "PASS: All 26 exact templates loaded successfully!\n";
