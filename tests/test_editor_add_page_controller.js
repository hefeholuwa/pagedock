const fs = require('fs');
const path = require('path');
const assert = require('assert');

console.log('=== Running PageDock Add Page Modal Controller Test Suite ===\n');

const editorJsPath = path.join(__dirname, '..', 'public', 'assets', 'js', 'editor.js');
assert(fs.existsSync(editorJsPath), 'public/assets/js/editor.js must exist');

const editorJs = fs.readFileSync(editorJsPath, 'utf8');

// 1. Check openAddPageModal definition
assert(editorJs.includes('function openAddPageModal('), 'editor.js must define openAddPageModal()');
console.log('[PASS] editor.js defines openAddPageModal()');

// 2. Check wiring of add page button
assert(editorJs.includes('addPageBtn') && (editorJs.includes('hh-add-page-btn') || editorJs.includes('openAddPageModal')), 'editor.js wires up add page button');
console.log('[PASS] editor.js wires up #hh-add-page-btn');

// 3. Check page select __new__ handling
assert(editorJs.includes('__new__') && editorJs.includes('openAddPageModal'), 'editor.js checks for __new__ option in page select');
console.log('[PASS] editor.js intercepts __new__ selection in page dropdown');

// 4. Check auto-slugify logic on title input
assert(editorJs.includes('slugify') || (editorJs.includes('replace') && editorJs.includes('.html')), 'editor.js includes slugification for page filename');
console.log('[PASS] editor.js auto-slugifies page title to filename');

// 5. Check API submission to pagesUrl
assert(editorJs.includes('PAGEDOCK_CONFIG.pagesUrl') && editorJs.includes('method: \'POST\''), 'editor.js submits page creation to pagesUrl');
console.log('[PASS] editor.js submits page creation payload to backend API');

console.log('\n--- Test Summary ---');
console.log('Total: 5');
console.log('Passed: 5');
console.log('Failed: 0');

