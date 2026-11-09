const fs = require('fs');
const path = require('path');
const assert = require('assert');

console.log('=== Running Canvas Drag and Drop Flow Test Suite ===\n');

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

// 1. Drag slot indicator styling and positioning
runCheck('Test 1: pd-drop-slot guide element is created, styled, and positioned during drag', () => {
    assert(harnessSrc.includes('pd-drop-slot'), 'pagedock-harness.js must define pd-drop-slot guide element');
    assert(harnessSrc.includes('showDropIndicator'), 'pagedock-harness.js must have showDropIndicator function');
    assert(harnessSrc.includes('ensureDropIndicator'), 'pagedock-harness.js must have ensureDropIndicator function');
});

// 2. Drag handle event bindings
runCheck('Test 2: handleDragGripStart or drag initialization binds to .pd-tool-drag and touch/mouse events', () => {
    assert(harnessSrc.includes('handleDragGripStart'), 'pagedock-harness.js binds drag grip start');
    assert(harnessSrc.includes('pd-tool-drag'), 'pagedock-harness.js must define pd-tool-drag');
    assert(
        (harnessSrc.includes('mousedown') && harnessSrc.includes('touchstart')),
        'Drag initiation must listen to both mousedown and touchstart'
    );
});

// 3. Drag movement dispatches layout sync to parent
runCheck('Test 3: Dropping an element dispatches layout sync (element-reorder) to parent editor', () => {
    assert(
        harnessSrc.includes('element-reorder') || harnessSrc.includes('live-patch') || harnessSrc.includes('moveBlockRelative'),
        'Harness synchronizes reordered element with parent editor'
    );
    assert(
        harnessSrc.includes("type: 'element-reorder'") || harnessSrc.includes('"type": "element-reorder"'),
        'Harness posts element-reorder message on drop'
    );
});

// 4. Ghost indicator and drag cancellation with Escape key
runCheck('Test 4: Drag features semi-transparent ghost indicator and Escape cancels active drag', () => {
    assert(
        harnessSrc.includes('pd-drag-ghost') || harnessSrc.includes('dragGhost'),
        'pagedock-harness.js must create a floating ghost indicator during drag'
    );
    assert(
        (harnessSrc.includes("key === 'Escape'") || harnessSrc.includes("e.key === 'Escape'")) &&
        (harnessSrc.includes('dragState') || harnessSrc.includes('activeDrag')),
        'Escape key cancels active drag'
    );
});

// 5. editor.js handles element-reorder with undo snapshot and status toast
runCheck('Test 5: editor.js captures undo snapshot and shows Cmd+Z toast on element-reorder', () => {
    assert(
        editorJsSrc.includes('handleElementReorder') || editorJsSrc.includes("case 'element-reorder':"),
        'editor.js must handle element-reorder message'
    );
    const reorderFnMatch = editorJsSrc.match(/function\s+handleElementReorder\s*\([^)]*\)\s*\{([\s\S]*?)\n\s*function/);
    assert(reorderFnMatch, 'handleElementReorder function must be found in editor.js');
    const reorderFnBody = reorderFnMatch[1];
    assert(
        reorderFnBody.includes('historyStacks') || reorderFnBody.includes('push'),
        'handleElementReorder must push undo snapshot before modifying code'
    );
    assert(
        reorderFnBody.includes('Reordered') && reorderFnBody.includes('Cmd+Z to undo'),
        'handleElementReorder toast must include "[STATUS] Reordered <tag> (Cmd+Z to undo)"'
    );
});

console.log('\n--- Test Summary ---');
console.log(`Total: ${totalTests}`);
console.log(`Passed: ${passedTests}`);
console.log(`Failed: ${totalTests - passedTests}`);

if (passedTests !== totalTests) {
    process.exit(1);
}
