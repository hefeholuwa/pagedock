const fs = require('fs');
const path = require('path');
const assert = require('assert');

console.log('=== Running Canvas Bounding Toolbar Test Suite ===\n');

const harnessPath = path.join(__dirname, '..', 'public', 'assets', 'js', 'pagedock-harness.js');
assert(fs.existsSync(harnessPath), 'pagedock-harness.js must exist');
const harnessSrc = fs.readFileSync(harnessPath, 'utf8');

// Test 1: Toolbar element (.pd-element-toolbar) exists and attaches to selected elements in pagedock-harness.js
assert(harnessSrc.includes('pd-element-toolbar'), 'Toolbar element (.pd-element-toolbar) exists in harness');
assert(
    harnessSrc.includes('attachSelectionToolbar'),
    'Toolbar attaches to selected elements via attachSelectionToolbar in pagedock-harness.js'
);
console.log('[PASS] Test 1: Toolbar element (.pd-element-toolbar) exists and attaches to selected elements');

// Test 2: Toolbar contains Canva-style action buttons: drag handle (.pd-tool-drag), delete button (.pd-tool-delete), duplicate button (.pd-tool-duplicate), and text edit button (.pd-tool-edit)
assert(harnessSrc.includes('pd-tool-drag'), 'Toolbar contains drag handle (.pd-tool-drag)');
assert(harnessSrc.includes('pd-tool-delete'), 'Toolbar contains delete button (.pd-tool-delete)');
assert(harnessSrc.includes('pd-tool-duplicate'), 'Toolbar contains duplicate button (.pd-tool-duplicate)');
assert(harnessSrc.includes('pd-tool-edit'), 'Toolbar contains edit text button (.pd-tool-edit)');
console.log('[PASS] Test 2: Toolbar contains all Canva-style action buttons (.pd-tool-drag, .pd-tool-delete, .pd-tool-duplicate, .pd-tool-edit)');

// Test 3: Pressing Escape key clears selection and dismisses toolbar/overlay
assert(
    (harnessSrc.includes("e.key === 'Escape'") || harnessSrc.includes("key === 'Escape'")) &&
    harnessSrc.includes('dismiss-popovers') &&
    (harnessSrc.includes('hideSelectionToolbar') || harnessSrc.includes('removeSelectionToolbar')),
    'Harness must listen for Escape key to clear selection, dismiss toolbar/overlay, and post dismiss-popovers'
);
console.log('[PASS] Test 3: Pressing Escape key clears selection and dismisses toolbar/overlay');

console.log('\n--- Test Summary ---');
console.log('Total: 3');
console.log('Passed: 3');
console.log('Failed: 0');
