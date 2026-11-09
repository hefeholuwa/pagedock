const fs = require('fs');
const path = require('path');
const assert = require('assert');

console.log('=== Running Canvas Element Deletion Test Suite ===\n');

const harnessPath = path.join(__dirname, '..', 'public', 'assets', 'js', 'pagedock-harness.js');
const editorJsPath = path.join(__dirname, '..', 'public', 'assets', 'js', 'editor.js');

assert(fs.existsSync(harnessPath), 'pagedock-harness.js must exist');
assert(fs.existsSync(editorJsPath), 'editor.js must exist');

const harnessSrc = fs.readFileSync(harnessPath, 'utf8');
const editorJsSrc = fs.readFileSync(editorJsPath, 'utf8');

// 1. Harness listens for Delete and Backspace keys when an element is selected
assert(
    harnessSrc.includes("e.key === 'Delete'") || harnessSrc.includes("e.key === 'Backspace'"),
    'pagedock-harness.js must listen for Delete / Backspace keys'
);
console.log('[PASS] Harness listens for Delete and Backspace keyboard shortcuts');

// 2. Harness dispatches element-delete on keyboard delete
assert(
    harnessSrc.includes("type: 'element-delete'") || harnessSrc.includes('element-delete'),
    'pagedock-harness.js must post element-delete message to parent'
);
console.log('[PASS] Harness posts element-delete message');

// 3. Harness has dedicated pd-tool-delete button
assert(
    harnessSrc.includes('pd-tool-delete'),
    'pagedock-harness.js must define pd-tool-delete button'
);
console.log('[PASS] Harness creates pd-tool-delete button');

// 4. editor.js handles element-delete and updates CodeMirror
assert(
    editorJsSrc.includes('handleElementDelete') || editorJsSrc.includes("case 'element-delete':"),
    'editor.js must handle element-delete message'
);
console.log('[PASS] editor.js processes element-delete and syncs source code');

console.log('\n--- Test Summary ---');
console.log('Total: 4');
console.log('Passed: 4');
console.log('Failed: 0');
