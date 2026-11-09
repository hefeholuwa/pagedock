const fs = require('fs');
const path = require('path');
const assert = require('assert');

console.log('=== Running Canva UX Engine & Precision Canvas Test Suite ===\n');

const harnessPath = path.join(__dirname, '..', 'public', 'assets', 'js', 'pagedock-harness.js');
const editorJsPath = path.join(__dirname, '..', 'public', 'assets', 'js', 'editor.js');

assert(fs.existsSync(harnessPath), 'pagedock-harness.js must exist');
assert(fs.existsSync(editorJsPath), 'editor.js must exist');

const harnessSrc = fs.readFileSync(harnessPath, 'utf8');
const editorJsSrc = fs.readFileSync(editorJsPath, 'utf8');

let totalTests = 0;
let passedTests = 0;

function runCheck(description, fn) {
    totalTests++;
    try {
        fn();
        passedTests++;
        console.log(`[PASS] ${description}`);
    } catch (err) {
        console.error(`[FAIL] ${description}`);
        console.error(`       ${err.message}`);
    }
}

// 1. isInert checks closest('[data-pagedock-ui]') to prevent harness internals from polluting DOM queries
runCheck('Test 1: isInert ignores children of [data-pagedock-ui]', () => {
    assert(
        harnessSrc.includes("closest('[data-pagedock-ui]')") || harnessSrc.includes('closest("[data-pagedock-ui]")'),
        'isInert must check el.closest("[data-pagedock-ui]")'
    );
});

// 2. tagIndex filters out inert harness elements
runCheck('Test 2: tagIndex ignores inert harness elements', () => {
    assert(
        harnessSrc.includes('isInert(all[i])') || harnessSrc.includes('isInert(c)'),
        'tagIndex must skip inert elements to avoid desyncing with CodeMirror'
    );
});

// 3. Harness duplicate action immediately clones in preview DOM
runCheck('Test 3: Duplicate button clones element in preview DOM', () => {
    assert(
        harnessSrc.includes('.cloneNode(true)') &&
        (harnessSrc.includes('insertBefore') || harnessSrc.includes('after')),
        'Duplicate button in harness must clone node directly in DOM'
    );
});

// 4. Drag handle only initiation (no accidental drag on plain element clicks)
runCheck('Test 4: Dragging is initiated via drag handle (.pd-tool-drag)', () => {
    assert(
        harnessSrc.includes('handleSelectionDragGripStart') || harnessSrc.includes('handleDragGripStart'),
        'Harness must bind drag start to handle'
    );
    // Ensure clicking element body does not start dragging without handle
    assert(
        !harnessSrc.includes('dragPotential = {\n          startX: pt.clientX,\n          startY: pt.clientY,\n          sourceSec: selectedEl'),
        'Element body must not start drag on simple clicks'
    );
});

// 5. Decouple selection from popover modal
runCheck('Test 5: Selecting element does not force popoverAnchor to block the canvas', () => {
    // editor.js should not force popoverAnchor display block unless user requests it
    assert(
        editorJsSrc.includes('autoShowPopover') ||
        !editorJsSrc.includes("dom.popoverAnchor.classList.remove('hidden');\n    }\n\n    function showPopoverView"),
        'handleElementSelected must not unconditionally unhide popoverAnchor over canvas'
    );
});

console.log('\n--- Test Summary ---');
console.log(`Total: ${totalTests}`);
console.log(`Passed: ${passedTests}`);
console.log(`Failed: ${totalTests - passedTests}`);

if (passedTests !== totalTests) {
    process.exit(1);
}
