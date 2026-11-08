const assert = require('assert');
const fs = require('fs');
const path = require('path');

console.log('=== Running PageDock Mobile Touch & 1-Click Reorder Test Suite ===\n');
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
const routerCode = fs.readFileSync(path.join(__dirname, '../src/Router.php'), 'utf8');

runTest('pagedock-harness.js defines ensureHarnessStyles for self-contained iframe styling', () => {
    assert(harnessCode.includes('ensureHarnessStyles'), 'pagedock-harness.js must define ensureHarnessStyles');
    assert(harnessCode.includes('pd-element-toolbar') && harnessCode.includes('pd-drop-slot'),
        'ensureHarnessStyles must inject styles for toolbar and drop slot');
});

runTest('Router.php links editor.css in preview head', () => {
    assert(routerCode.includes("asset('assets/css/editor.css')") || routerCode.includes('editor.css'),
        'Router.php must inject editor stylesheet link into preview iframe');
});

runTest('pagedock-harness.js creates pd-tool-up and pd-tool-down 1-click reorder buttons', () => {
    assert(harnessCode.includes('pd-tool-up'), 'Toolbar must include pd-tool-up button');
    assert(harnessCode.includes('pd-tool-down'), 'Toolbar must include pd-tool-down button');
    assert(harnessCode.includes('moveBlockRelative'), 'harness must define moveBlockRelative');
});

runTest('pagedock-harness.js supports touch events for mobile drag reordering', () => {
    assert(harnessCode.includes('touchstart') && harnessCode.includes('touchmove') && harnessCode.includes('touchend'),
        'harness must register touchstart, touchmove, and touchend handlers for mobile drag');
});

runTest('editor.js supports pendingInsertTarget for 1-click tap-to-insert below targeted block', () => {
    assert(editorCode.includes('pendingInsertTarget'),
        'editor.js must track pendingInsertTarget when user taps + Add Block Below');
    assert(editorCode.includes('draggedLibrarySnippet'),
        'editor.js must maintain fallback to state.draggedLibrarySnippet if cross-frame dataTransfer is sanitized');
});

console.log('\n--- Test Summary ---');
console.log(`Total: ${passedTests + failedTests}`);
console.log(`Passed: ${passedTests}`);
console.log(`Failed: ${failedTests}`);

if (failedTests > 0) {
    process.exit(1);
}
