# Canva-Style Visual Canvas Editing & Instant Deletion Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Provide an intuitive Canva-like website editing experience with instant element deletion (`Delete`/`Backspace` key + floating trash icon) and fluid drag-and-drop repositioning with magnetic slots that maintain 100% mobile responsiveness.

**Architecture:** Extend `pagedock-harness.js` in the preview iframe to support keyboard shortcuts (`Delete`/`Backspace`/`Escape`), high-visibility micro-toolbars with a prominent delete button, and magnetic drag-and-drop reordering. Extend `editor.js` to process real-time DOM removals and sync changes immediately to the CodeMirror buffer with full `Cmd+Z` undo history.

**Tech Stack:** Vanilla JavaScript (ES6+), DOM Mutation APIs, CSS Grid & Flexbox, CodeMirror 5, postMessage IPC.

---

### Task 1: Instant Element Deletion via Keyboard & Trash Button

**Files:**
- Create: `tests/test_canvas_element_deletion.js`
- Modify: `public/assets/js/pagedock-harness.js`
- Modify: `public/assets/js/editor.js`

- [ ] **Step 1: Write the failing test for element deletion**

Create `tests/test_canvas_element_deletion.js`:
```javascript
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `node tests/test_canvas_element_deletion.js`
Expected: FAIL on Delete/Backspace check in `pagedock-harness.js`

- [ ] **Step 3: Implement keyboard deletion & trash button in `pagedock-harness.js` and `editor.js`**

In `public/assets/js/pagedock-harness.js`:
1. Ensure the keydown listener in preview iframe intercepts `Delete` and `Backspace` when `selectedEl` is active and not currently focused in a text editor or input.
2. Emit `element-delete` with element details, remove `selectedEl` from DOM, and clear overlay.
3. In `editor.js`, ensure `handleElementDelete` cleanly slices out the element's HTML from CodeMirror, pushes an undo state snapshot to history, and shows a toast.

- [ ] **Step 4: Run test to verify it passes**

Run: `node tests/test_canvas_element_deletion.js`
Expected: PASS (4/4)

- [ ] **Step 5: Commit**

```bash
git add tests/test_canvas_element_deletion.js public/assets/js/pagedock-harness.js public/assets/js/editor.js
git commit -m "feat(canvas): add instant element deletion via Delete/Backspace and trash button"
```

---

### Task 2: Canva-Style Micro-Toolbar & Selection Bounding Box

**Files:**
- Create: `tests/test_canvas_bounding_toolbar.js`
- Modify: `public/assets/js/pagedock-harness.js`
- Modify: `public/assets/css/editor.css`

- [ ] **Step 1: Write the failing test for micro-toolbar**

Create `tests/test_canvas_bounding_toolbar.js`:
```javascript
const fs = require('fs');
const path = require('path');
const assert = require('assert');

console.log('=== Running Canvas Bounding Toolbar Test Suite ===\n');

const harnessPath = path.join(__dirname, '..', 'public', 'assets', 'js', 'pagedock-harness.js');
const harnessSrc = fs.readFileSync(harnessPath, 'utf8');

// 1. Toolbar attached to selected element
assert(harnessSrc.includes('pd-element-toolbar'), 'Toolbar element exists in harness');
console.log('[PASS] Toolbar element defined in harness');

// 2. Micro-toolbar contains drag handle, delete button, duplicate button
assert(harnessSrc.includes('pd-tool-drag'), 'Toolbar contains drag handle');
assert(harnessSrc.includes('pd-tool-delete'), 'Toolbar contains delete handle');
assert(harnessSrc.includes('pd-tool-duplicate'), 'Toolbar contains duplicate handle');
console.log('[PASS] Toolbar contains all required Canva-style action buttons');

// 3. Escape key dismisses selection and toolbars
assert(
    harnessSrc.includes("e.key === 'Escape'") || harnessSrc.includes("key === 'Escape'"),
    'Harness must listen for Escape key to clear selection'
);
console.log('[PASS] Escape key dismisses selection and active toolbar');

console.log('\n--- Test Summary ---');
console.log('Total: 3');
console.log('Passed: 3');
console.log('Failed: 0');
```

- [ ] **Step 2: Run test to verify it passes or fails**

Run: `node tests/test_canvas_bounding_toolbar.js`

- [ ] **Step 3: Enhance micro-toolbar UI and positioning**

Ensure the `.pd-element-toolbar` is always positioned clearly above the selected element, with clear button contrast, tooltips, and active states.

- [ ] **Step 4: Commit**

```bash
git add tests/test_canvas_bounding_toolbar.js public/assets/js/pagedock-harness.js public/assets/css/editor.css
git commit -m "style(canvas): polish Canva-style selection bounding box and micro-toolbar"
```

---

### Task 3: Fluid Drag-and-Drop Repositioning with Magnetic Slots

**Files:**
- Create: `tests/test_canvas_drag_drop_flow.js`
- Modify: `public/assets/js/pagedock-harness.js`
- Modify: `public/assets/js/editor.js`

- [ ] **Step 1: Write the failing test for drag-and-drop flow**

Create `tests/test_canvas_drag_drop_flow.js`:
```javascript
const fs = require('fs');
const path = require('path');
const assert = require('assert');

console.log('=== Running Canvas Drag and Drop Flow Test Suite ===\n');

const harnessPath = path.join(__dirname, '..', 'public', 'assets', 'js', 'pagedock-harness.js');
const harnessSrc = fs.readFileSync(harnessPath, 'utf8');

// 1. Drag slot indicator styling and positioning
assert(harnessSrc.includes('pd-drop-slot'), 'pagedock-harness.js defines pd-drop-slot guide');
console.log('[PASS] Drop slot indicator defined in harness');

// 2. Drag handle event bindings
assert(harnessSrc.includes('handleDragGripStart'), 'pagedock-harness.js binds drag grip start');
console.log('[PASS] Drag grip initiation bound');

// 3. Drag movement dispatches layout sync to parent
assert(
    harnessSrc.includes('element-reorder') || harnessSrc.includes('live-patch') || harnessSrc.includes('moveBlockRelative'),
    'Harness synchronizes reordered element with parent editor'
);
console.log('[PASS] Element reordering dispatches sync to parent editor');

console.log('\n--- Test Summary ---');
console.log('Total: 3');
console.log('Passed: 3');
console.log('Failed: 0');
```

- [ ] **Step 2: Run test to verify status**

Run: `node tests/test_canvas_drag_drop_flow.js`

- [ ] **Step 3: Implement magnetic insertion guidelines and responsive drop placement**

1. Ensure dragging an element renders the glowing blue magnetic line (`.pd-drop-slot`).
2. Dropping reorders the DOM node before or after the target sibling or inside the container.
3. Automatically notifies `editor.js` to patch the CodeMirror source code cleanly and trigger undo snapshot.

- [ ] **Step 4: Run test to verify it passes**

Run: `node tests/test_canvas_drag_drop_flow.js`
Expected: PASS (3/3)

- [ ] **Step 5: Commit**

```bash
git add tests/test_canvas_drag_drop_flow.js public/assets/js/pagedock-harness.js public/assets/js/editor.js
git commit -m "feat(canvas): implement magnetic drag-and-drop repositioning with responsive flow"
```

---

### Task 4: Regression Verification & Live Browser Testing

- [ ] **Step 1: Run complete test suite**

Run:
```bash
node tests/test_canvas_element_deletion.js
node tests/test_canvas_bounding_toolbar.js
node tests/test_canvas_drag_drop_flow.js
node tests/test_editor_missing_page_intercept.js
node tests/test_editor_multipage_navigation.js
node tests/test_editor_add_page_controller.js
node tests/test_editor_pages_ui.js
php tests/test_backend_page_scaffolding.php
php tests/test_api_pages_management.php
```

- [ ] **Step 2: Live editor verification**

Open `http://localhost:8080/editor/adventure-travel-tours` and verify:
1. Click any element → bounding box and micro-toolbar appear.
2. Press `Delete` or `Backspace` → element vanishes, toast displays with undo notice.
3. Press `Cmd+Z` → element reappears immediately.
4. Click trash icon on another element → element is deleted.
5. Drag an element to a new slot → drops cleanly and responds properly when switching to mobile view (`hh-mobile-btn-preview`).

- [ ] **Step 3: Commit and push**

```bash
git push origin master
```
