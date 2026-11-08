/**
 * PageDock AI Editor - Multi-Page Site Switching & Navigation Test Suite
 */

const fs = require('fs');
const path = require('path');
const assert = require('assert');

const ROOT_DIR = path.resolve(__dirname, '..');
const VIEWS_EDITOR_PATH = path.join(ROOT_DIR, 'views', 'editor.php');
const HARNESS_PATH = path.join(ROOT_DIR, 'public', 'assets', 'js', 'pagedock-harness.js');
const EDITOR_JS_PATH = path.join(ROOT_DIR, 'public', 'assets', 'js', 'editor.js');

let totalTests = 0;
let passedTests = 0;
let failedTests = 0;

function runTest(name, fn) {
    totalTests++;
    try {
        fn();
        passedTests++;
        console.log(`[PASS] ${name}`);
    } catch (err) {
        failedTests++;
        console.error(`[FAIL] ${name}`);
        console.error(`       Error: ${err.message}`);
    }
}

console.log('=== Running PageDock Multi-Page Site Switching & Navigation Test Suite ===\n');

assert(fs.existsSync(VIEWS_EDITOR_PATH), 'views/editor.php must exist');
assert(fs.existsSync(HARNESS_PATH), 'pagedock-harness.js must exist');
assert(fs.existsSync(EDITOR_JS_PATH), 'editor.js must exist');

const editorViewSrc = fs.readFileSync(VIEWS_EDITOR_PATH, 'utf8');
const harnessSrc = fs.readFileSync(HARNESS_PATH, 'utf8');
const editorJsSrc = fs.readFileSync(EDITOR_JS_PATH, 'utf8');

// Test 1: Page switcher select element is present in views/editor.php
runTest('views/editor.php contains page switcher select element', () => {
    assert(editorViewSrc.includes('id="hh-page-select"'), 'views/editor.php must have #hh-page-select');
    assert(editorViewSrc.includes('hh-page-switcher'), 'views/editor.php must have class hh-page-switcher');
});

// Test 2: editor.js wires up hh-page-select change listener
runTest('editor.js wires up hh-page-select to switch active page', () => {
    assert(editorJsSrc.includes('hh-page-select') || editorJsSrc.includes('pageSelect'), 'editor.js must reference page switcher select');
    assert(editorJsSrc.includes('switchPage') || editorJsSrc.includes('navigateToPage'), 'editor.js must have switchPage or navigateToPage function');
});

// Test 3: pagedock-harness.js intercepts internal link clicks and sends navigate-page
runTest('pagedock-harness.js intercepts internal .html links and dispatches navigate-page', () => {
    assert(harnessSrc.includes("type: 'navigate-page'"), 'pagedock-harness.js must dispatch navigate-page');
    assert(harnessSrc.includes('navigate-page') && harnessSrc.includes('path'), 'navigate-page message must include path');
});

// Test 4: editor.js listens for navigate-page from iframe
runTest('editor.js listens for navigate-page and switches active file', () => {
    assert(editorJsSrc.includes("case 'navigate-page':") || editorJsSrc.includes("type === 'navigate-page'"), 'editor.js must handle navigate-page message');
});

console.log(`\n--- Test Summary ---`);
console.log(`Total: ${totalTests}`);
console.log(`Passed: ${passedTests}`);
console.log(`Failed: ${failedTests}`);

if (failedTests > 0) {
    process.exit(1);
}

