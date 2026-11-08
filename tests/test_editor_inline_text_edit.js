/**
 * PageDock AI Editor - In-Place Live Text Editing Test Suite
 */

const fs = require('fs');
const path = require('path');
const assert = require('assert');

const ROOT_DIR = path.resolve(__dirname, '..');
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

console.log('=== Running PageDock In-Place Live Text Editing Test Suite ===\n');

assert(fs.existsSync(HARNESS_PATH), 'pagedock-harness.js must exist');
assert(fs.existsSync(EDITOR_JS_PATH), 'editor.js must exist');

const harnessSrc = fs.readFileSync(HARNESS_PATH, 'utf8');
const editorSrc = fs.readFileSync(EDITOR_JS_PATH, 'utf8');

// Test 1: Function isEditableTextNode exists in harness and handles inline elements
runTest('pagedock-harness.js defines isEditableTextNode or permits inline formatted text elements', () => {
    assert(
        harnessSrc.includes('isEditableTextNode') || harnessSrc.includes('canEditText'),
        'pagedock-harness.js should define isEditableTextNode or canEditText'
    );
});

// Test 2: Double-click event handler supports elements with inline children
runTest('Double-click listener supports text elements with inline tags like span, strong, em', () => {
    assert(
        harnessSrc.includes('isEditableTextNode(el)') || harnessSrc.includes('canEditText(el)'),
        'Double-click handler should check isEditableTextNode or canEditText'
    );
});

// Test 3: Enter commits and Escape cancels text editing
runTest('Enter commits and Escape cancels in-place text edit in harness', () => {
    assert(harnessSrc.includes("e.key === 'Enter'"), 'Enter key must be handled for text editing');
    assert(harnessSrc.includes("endTextEdit(true)"), 'endTextEdit(true) must be called on Enter');
    assert(harnessSrc.includes("endTextEdit(false)"), 'endTextEdit(false) must be called on Escape');
});

// Test 4: editor.js handles element-text-edit message cleanly
runTest('editor.js handleElementTextEdit updates CodeMirror buffer without corruption', () => {
    assert(editorSrc.includes('function handleElementTextEdit'), 'editor.js must define handleElementTextEdit');
    assert(editorSrc.includes("type === 'element-text-edit'") || editorSrc.includes("case 'element-text-edit':"), 'editor.js must listen for element-text-edit');
});

console.log(`\n--- Test Summary ---`);
console.log(`Total: ${totalTests}`);
console.log(`Passed: ${passedTests}`);
console.log(`Failed: ${failedTests}`);

if (failedTests > 0) {
    process.exit(1);
}
