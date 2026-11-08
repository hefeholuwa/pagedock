const assert = require('assert');
const fs = require('fs');
const path = require('path');

console.log('=== Running PageDock Empty Canvas & Bootstrap Test Suite ===\n');
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
const editorCss = fs.readFileSync(path.join(__dirname, '../public/assets/css/editor.css'), 'utf8');
const editorCode = fs.readFileSync(path.join(__dirname, '../public/assets/js/editor.js'), 'utf8');

runTest('pagedock-harness.js detects empty page and displays empty canvas drop zone', () => {
    assert(harnessCode.includes('pd-empty-canvas') || harnessCode.includes('checkEmptyCanvas'),
        'pagedock-harness.js must render .pd-empty-canvas when page body is empty');
});

runTest('editor.css defines styles for .pd-empty-canvas', () => {
    assert(editorCss.includes('.pd-empty-canvas'), 'editor.css must style .pd-empty-canvas');
});

runTest('editor.js bootstraps clean HTML structure on empty canvas drop', () => {
    assert(editorCode.includes('bootstrapEmptyPage') || editorCode.includes('pd-empty-canvas') || editorCode.includes('insertSnippetIntoPage'),
        'editor.js must handle dropping onto empty canvas');
});

console.log('\n--- Test Summary ---');
console.log(`Total: ${passedTests + failedTests}`);
console.log(`Passed: ${passedTests}`);
console.log(`Failed: ${failedTests}`);

if (failedTests > 0) {
    process.exit(1);
}
