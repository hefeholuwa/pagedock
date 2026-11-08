const fs = require('fs');
const path = require('path');
const assert = require('assert');

console.log('=== Running PageDock Editor Pages UI Test Suite ===\n');

const editorViewPath = path.join(__dirname, '..', 'views', 'editor.php');
const editorCssPath = path.join(__dirname, '..', 'public', 'assets', 'css', 'editor.css');

assert(fs.existsSync(editorViewPath), 'views/editor.php must exist');
assert(fs.existsSync(editorCssPath), 'public/assets/css/editor.css must exist');

const viewContent = fs.readFileSync(editorViewPath, 'utf8');
const cssContent = fs.readFileSync(editorCssPath, 'utf8');

// 1. Check unconditional page select rendering
assert(!viewContent.includes('count($htmlFiles) > 1'), 'views/editor.php should not hide page select when count is 1');
assert(viewContent.includes('id="hh-page-select"'), 'views/editor.php must contain #hh-page-select');
console.log('[PASS] #hh-page-select is rendered unconditionally');

// 2. Check dedicated + Add Page button in topbar
assert(viewContent.includes('id="hh-add-page-btn"'), 'views/editor.php must contain #hh-add-page-btn');
console.log('[PASS] Topbar contains #hh-add-page-btn');

// 3. Check Add Page Modal markup and inputs
assert(viewContent.includes('id="hh-add-page-modal"'), 'views/editor.php must contain #hh-add-page-modal');
assert(viewContent.includes('id="hh-new-page-title"'), 'views/editor.php must contain #hh-new-page-title');
assert(viewContent.includes('id="hh-new-page-filename"'), 'views/editor.php must contain #hh-new-page-filename');
assert(viewContent.includes('id="hh-new-page-layout"'), 'views/editor.php must contain #hh-new-page-layout');
assert(viewContent.includes('id="hh-create-page-submit"'), 'views/editor.php must contain #hh-create-page-submit');
console.log('[PASS] views/editor.php contains complete #hh-add-page-modal structure');

// 4. Check PAGEDOCK_CONFIG
assert(viewContent.includes('pagesUrl:'), 'views/editor.php must configure pagesUrl');
assert(viewContent.includes('htmlFiles:'), 'views/editor.php must configure htmlFiles array');
console.log('[PASS] PAGEDOCK_CONFIG includes pagesUrl and htmlFiles');

// 5. Check CSS styling
assert(cssContent.includes('.hh-page-group') || cssContent.includes('.hh-btn-add-page'), 'editor.css must style page switcher group');
console.log('[PASS] editor.css contains page switcher styling');

console.log('\n--- Test Summary ---');
console.log('Total: 5');
console.log('Passed: 5');
console.log('Failed: 0');

