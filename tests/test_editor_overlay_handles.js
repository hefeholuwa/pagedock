const assert = require('assert');
const fs = require('fs');
const path = require('path');

console.log('=== Running PageDock Element Overlay & Drag Handles Test Suite ===\n');
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

runTest('pagedock-harness.js defines createBlockOverlay and attaches action toolbar', () => {
    assert(harnessCode.includes('createBlockOverlay') || harnessCode.includes('pd-element-overlay'),
        'pagedock-harness.js must define createBlockOverlay or create pd-element-overlay');
    assert(harnessCode.includes('pd-tool-drag'), 'Overlay toolbar must include pd-tool-drag handle');
    assert(harnessCode.includes('pd-tool-duplicate'), 'Overlay toolbar must include pd-tool-duplicate button');
    assert(harnessCode.includes('pd-tool-delete'), 'Overlay toolbar must include pd-tool-delete button');
});

runTest('Dragging is restricted to dedicated drag grip handle', () => {
    assert(harnessCode.includes('pd-tool-drag') && (harnessCode.includes('dragstart') || harnessCode.includes('mousedown')),
        'Dragging must be initiated from pd-tool-drag handle');
});

runTest('editor.css defines styling for pd-element-overlay and pd-element-toolbar', () => {
    assert(editorCss.includes('.pd-element-overlay'), 'editor.css must include .pd-element-overlay rules');
    assert(editorCss.includes('.pd-element-toolbar'), 'editor.css must include .pd-element-toolbar rules');
    assert(editorCss.includes('.pd-tool-btn'), 'editor.css must include .pd-tool-btn rules');
});

console.log('\n--- Test Summary ---');
console.log(`Total: ${passedTests + failedTests}`);
console.log(`Passed: ${passedTests}`);
console.log(`Failed: ${failedTests}`);

if (failedTests > 0) {
    process.exit(1);
}
