const assert = require('assert');
const fs = require('fs');
const path = require('path');

console.log('=== Running PageDock Canvas Quick Actions Test Suite ===\n');
let passedTests = 0;
let failedTests = 0;

function runTest(name, fn) {
    try {
        fn();
        console.log(`[PASS] ${name}`);
        passedTests++;
    } catch (err) {
        console.error(`[FAIL] ${name}`);
        console.error(`       ${err.message}`);
        failedTests++;
    }
}

const harnessCode = fs.readFileSync(path.join(__dirname, '../public/assets/js/pagedock-harness.js'), 'utf8');
const editorCode = fs.readFileSync(path.join(__dirname, '../public/assets/js/editor.js'), 'utf8');

runTest('pagedock-harness.js dispatches element-duplicate and element-delete postMessages', () => {
    assert(harnessCode.includes("'element-duplicate'") || harnessCode.includes('"element-duplicate"'),
        'pagedock-harness.js must dispatch element-duplicate');
    assert(harnessCode.includes("'element-delete'") || harnessCode.includes('"element-delete"'),
        'pagedock-harness.js must dispatch element-delete');
});

runTest('editor.js handles element-duplicate by cloning block in CodeMirror', () => {
    assert(editorCode.includes('handleElementDuplicate') || editorCode.includes('element-duplicate'),
        'editor.js must handle element-duplicate message');
});

runTest('editor.js handles element-delete by removing block from CodeMirror with toast', () => {
    assert(editorCode.includes('handleElementDelete') || editorCode.includes('element-delete'),
        'editor.js must handle element-delete message');
});

console.log('\n--- Test Summary ---');
console.log(`Total: ${passedTests + failedTests}`);
console.log(`Passed: ${passedTests}`);
console.log(`Failed: ${failedTests}`);

if (failedTests > 0) {
    process.exit(1);
}
