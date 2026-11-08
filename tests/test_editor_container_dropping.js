const assert = require('assert');
const fs = require('fs');
const path = require('path');

console.log('=== Running PageDock Layout-Aware Container Dropping Test Suite ===\n');
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

runTest('pagedock-harness.js supports layout-aware direction detection (horizontal in flex rows)', () => {
    assert(harnessCode.includes('flex') || harnessCode.includes('flexDirection') || harnessCode.includes('isFlexRow'),
        'pagedock-harness.js must inspect computed flex styles for horizontal insertion');
});

runTest('pagedock-harness.js enforces void tag protection (never insert inside img, input, hr, br)', () => {
    assert(harnessCode.includes('VOID_TAGS') || /IMG|INPUT|HR|BR/i.test(harnessCode),
        'pagedock-harness.js must guard against dropping inside void tags');
});

runTest('editor.css defines animated live drop slot (.pd-drop-slot)', () => {
    assert(editorCss.includes('.pd-drop-slot'), 'editor.css must style .pd-drop-slot');
});

runTest('editor.js handleLibraryDrop supports position: inside (container appending)', () => {
    assert(editorCode.includes("'inside'") || editorCode.includes('"inside"'),
        'handleLibraryDrop in editor.js must support inserting inside a container');
});

console.log('\n--- Test Summary ---');
console.log(`Total: ${passedTests + failedTests}`);
console.log(`Passed: ${passedTests}`);
console.log(`Failed: ${failedTests}`);

if (failedTests > 0) {
    process.exit(1);
}
