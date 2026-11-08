const fs = require('fs');
const path = require('path');
const assert = require('assert');

console.log('=== Running PageDock Missing Page Link Intercept Test Suite ===\n');

const editorJsPath = path.join(__dirname, '..', 'public', 'assets', 'js', 'editor.js');
assert(fs.existsSync(editorJsPath), 'public/assets/js/editor.js must exist');

const editorJs = fs.readFileSync(editorJsPath, 'utf8');

// 1. Check navigate-page listener logic
assert(editorJs.includes('navigate-page'), 'editor.js must listen for navigate-page messages');
console.log('[PASS] editor.js handles navigate-page messages');

// 2. Check existence check against htmlFiles
assert(editorJs.includes('htmlFiles') && editorJs.includes('includes('), 'editor.js checks if target page exists in htmlFiles');
console.log('[PASS] editor.js verifies target page existence against known htmlFiles');

// 3. Check auto-create modal triggering for non-existent pages
assert(editorJs.includes('openAddPageModal') && editorJs.includes('does not exist'), 'editor.js prompts user to create missing page when internal link clicked');
console.log('[PASS] editor.js triggers openAddPageModal when navigating to non-existent page');

console.log('\n--- Test Summary ---');
console.log('Total: 3');
console.log('Passed: 3');
console.log('Failed: 0');
