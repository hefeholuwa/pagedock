/**
 * PageDock AI Editor - Visual Section Drag-and-Drop Reordering Test Suite
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

console.log('=== Running PageDock Visual Section Drag-and-Drop Reordering Test Suite ===\n');

assert(fs.existsSync(HARNESS_PATH), 'pagedock-harness.js must exist');
assert(fs.existsSync(EDITOR_JS_PATH), 'editor.js must exist');

const harnessSrc = fs.readFileSync(HARNESS_PATH, 'utf8');
const editorSrc = fs.readFileSync(EDITOR_JS_PATH, 'utf8');

// Test 1: Section drag handles or pointerdown section dragging in harness
runTest('pagedock-harness.js has section drag tracking and drop indicator line', () => {
    assert(
        harnessSrc.includes('element-reorder') || harnessSrc.includes('dragState'),
        'pagedock-harness.js must have drag state tracking'
    );
    assert(
        harnessSrc.includes('mkInd') || harnessSrc.includes('drop-indicator') || harnessSrc.includes('insertion-indicator'),
        'pagedock-harness.js must render a drop insertion indicator line'
    );
});

// Test 2: Harness dispatches element-reorder with source and target tag indices
runTest('pagedock-harness.js dispatches element-reorder postMessage with source and target info', () => {
    assert(
        harnessSrc.includes("type: 'element-reorder'"),
        'pagedock-harness.js must dispatch element-reorder postMessage'
    );
    assert(
        harnessSrc.includes('sourceTag') && harnessSrc.includes('targetTag'),
        'element-reorder message must include sourceTag and targetTag'
    );
});

// Test 3: editor.js handles element-reorder message
runTest('editor.js handles element-reorder and updates CodeMirror buffer', () => {
    assert(
        editorSrc.includes("case 'element-reorder':") || editorSrc.includes("type === 'element-reorder'"),
        'editor.js must listen for element-reorder message'
    );
    assert(
        editorSrc.includes('handleElementReorder') || editorSrc.includes('reorderElementsInHtml'),
        'editor.js must have function to reorder elements in code'
    );
});

console.log(`\n--- Test Summary ---`);
console.log(`Total: ${totalTests}`);
console.log(`Passed: ${passedTests}`);
console.log(`Failed: ${failedTests}`);

if (failedTests > 0) {
    process.exit(1);
}

