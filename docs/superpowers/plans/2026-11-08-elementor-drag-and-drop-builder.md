# Elementor-Grade Visual Drag-and-Drop Builder Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement an Elementor-grade visual builder in PageDock featuring hover toolbars with dedicated drag handles (`⠿`), 1-click duplicate (`⧉`) and delete (`✕`), layout-aware dropping (horizontal flex rows, vertical stacks, void tag protection), animated live insertion slots, and empty-canvas build-from-scratch support.

**Architecture:** Decoordinated interaction inside `pagedock-harness.js` via `.pd-element-overlay` toolbars and layout-aware dropping engine (`html5Droppable`), communicating with parent `editor.js` over bidirectional postMessage protocol with deterministic `data-pd-id` CodeMirror buffer synchronization.

**Tech Stack:** Vanilla JavaScript (ES6+), CSS3 Flexbox/Grid animations, HTML5 Drag and Drop API, CodeMirror 5, Node.js test runner.

---

### Task 1: Element Overlays, Action Toolbars & Dedicated Drag Handles

**Files:**
- Modify: `public/assets/js/pagedock-harness.js`
- Modify: `public/assets/css/editor.css`
- Create: `tests/test_editor_overlay_handles.js`

- [ ] **Step 1: Write the failing overlay handles test**
Create `tests/test_editor_overlay_handles.js`:
```javascript
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
```

- [ ] **Step 2: Run test to verify it fails**
Run: `node tests/test_editor_overlay_handles.js`
Expected: FAIL

- [ ] **Step 3: Implement element overlays and action toolbars**
1. In `public/assets/js/pagedock-harness.js`:
   - Implement `createBlockOverlay(el)` attaching `.pd-element-overlay` with buttons: `.pd-tool-drag` (title "Drag to Move", draggable="true"), `.pd-tool-duplicate` (title "Duplicate"), `.pd-tool-add` (title "Add Block Below"), `.pd-tool-delete` (title "Delete").
   - Scan top-level sections (`SECTION`, `HEADER`, `NAV`, `FOOTER`, `MAIN`, `ARTICLE`) and major container blocks (`DIV.container`, `DIV.grid`, `DIV.card`) on DOM ready, assigning transient `data-pd-id` and attaching overlays.
   - Refactor `mousedown` drag initiation so section reordering only triggers when dragging from `.pd-tool-drag`, completely eliminating accidental drags during text selection or double-click edits.
2. In `public/assets/css/editor.css`:
   - Add `.pd-element-overlay` styles: `position: absolute; inset-inline-end: 8px; inset-block-start: -14px; z-index: 99999; pointer-events: none;`.
   - Add `.pd-element-toolbar` styles: `display: inline-flex; align-items: center; background: #0f172a; border: 1px solid #334155; border-radius: 6px; box-shadow: 0 4px 12px rgba(0,0,0,0.5); pointer-events: all; padding: 2px; gap: 2px; opacity: 0; transition: opacity 0.15s ease;`.
   - Show toolbar on hover: `.pd-has-overlay:hover > .pd-element-overlay .pd-element-toolbar, .pd-element-selected > .pd-element-overlay .pd-element-toolbar { opacity: 1; }`.
   - Style `.pd-tool-btn` with hover states, SVG icons, and `cursor: grab`.

- [ ] **Step 4: Run test to verify it passes**
Run: `node tests/test_editor_overlay_handles.js`
Expected: PASS

- [ ] **Step 5: Git commit**
```bash
git add public/assets/js/pagedock-harness.js public/assets/css/editor.css tests/test_editor_overlay_handles.js
git commit -m "feat(editor): element overlays and dedicated drag handles"
```

---

### Task 2: Instant Canvas Actions (Duplicate & Delete Synchronized with CodeMirror)

**Files:**
- Modify: `public/assets/js/pagedock-harness.js`
- Modify: `public/assets/js/editor.js`
- Create: `tests/test_editor_canvas_actions.js`

- [ ] **Step 1: Write the failing canvas actions test**
Create `tests/test_editor_canvas_actions.js`:
```javascript
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
```

- [ ] **Step 2: Run test to verify it fails**
Run: `node tests/test_editor_canvas_actions.js`
Expected: FAIL

- [ ] **Step 3: Implement element duplicate and delete actions**
1. In `public/assets/js/pagedock-harness.js`:
   - Add click listener for `.pd-tool-duplicate`: clones target element in the preview DOM immediately, assigns a fresh `data-pd-id`, and posts `{ type: 'element-duplicate', targetId: id, targetTag: el.tagName, targetIndex: tagIndex(el) }` to parent.
   - Add click listener for `.pd-tool-delete`: removes element from preview DOM and posts `{ type: 'element-delete', targetId: id, targetTag: el.tagName, targetIndex: tagIndex(el) }` to parent.
   - Add click listener for `.pd-tool-add`: posts `{ type: 'open-library-insert', targetId: id, targetTag: el.tagName, targetIndex: tagIndex(el) }` to parent, switching the left panel to the Library tab and focusing the search bar.
2. In `public/assets/js/editor.js`:
   - Wire up `element-duplicate` in the message listener: calls `handleElementDuplicate(data)` which locates the element in CodeMirror, extracts `outerHTML`, inserts a duplicate directly after, and updates CodeMirror.
   - Wire up `element-delete` in the message listener: calls `handleElementDelete(data)` which locates the element in CodeMirror, slices it out, updates CodeMirror, and displays toast `[STATUS] Block deleted (Ctrl+Z to undo)`.
   - Wire up `open-library-insert`: switches left panel to 'library' tab and records insertion target position.

- [ ] **Step 4: Run test to verify it passes**
Run: `node tests/test_editor_canvas_actions.js`
Expected: PASS

- [ ] **Step 5: Git commit**
```bash
git add public/assets/js/pagedock-harness.js public/assets/js/editor.js tests/test_editor_canvas_actions.js
git commit -m "feat(editor): 1-click duplicate, delete, and add-below canvas actions"
```

---

### Task 3: Layout-Aware Dropping & Animated Live Insertion Slot

**Files:**
- Modify: `public/assets/js/pagedock-harness.js`
- Modify: `public/assets/css/editor.css`
- Modify: `public/assets/js/editor.js`
- Create: `tests/test_editor_container_dropping.js`

- [ ] **Step 1: Write the failing container dropping test**
Create `tests/test_editor_container_dropping.js`:
```javascript
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
```

- [ ] **Step 2: Run test to verify it fails**
Run: `node tests/test_editor_container_dropping.js`
Expected: FAIL

- [ ] **Step 3: Implement layout-aware dropping and animated live slot**
1. In `public/assets/js/pagedock-harness.js`:
   - Implement `detectDropPosition(target, clientX, clientY)`:
     - Check `getComputedStyle(target)`. If flex row, evaluate `clientX < rect.left + rect.width / 2 ? 'before' : 'after'` (horizontal). Otherwise evaluate vertical mid-point.
     - Detect if target is a container (`isContainer(target)`) and cursor is within inner 60% box: return `position: 'inside'`.
     - Guard against void tags (`IMG, INPUT, HR, BR, EMBED`): force `position: 'before'` or `'after'`.
   - Upgrade drop indicator to `.pd-drop-slot` element rendered directly in DOM hierarchy: expands smoothly with CSS animation, giving true layout preview.
   - Implement viewport auto-scrolling when drag coordinate is within 50px of top/bottom edges of the window.
2. In `public/assets/css/editor.css`:
   - Add `.pd-drop-slot` styling: `box-sizing: border-box; background: rgba(56, 189, 248, 0.2); border: 2px dashed #38bdf8; border-radius: 6px; transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); animation: pd-slot-pulse 1s infinite alternate;`.
   - For vertical stacks: `height: 24px; width: 100%; margin: 8px 0;`.
   - For horizontal flex rows: `width: 24px; min-height: 40px; margin: 0 8px; align-self: stretch;`.
3. In `public/assets/js/editor.js`:
   - Extend `handleLibraryDrop(data)` to handle `position === 'inside'`: inserts snippet right before the closing tag of the target container in CodeMirror.

- [ ] **Step 4: Run test to verify it passes**
Run: `node tests/test_editor_container_dropping.js`
Expected: PASS

- [ ] **Step 5: Git commit**
```bash
git add public/assets/js/pagedock-harness.js public/assets/css/editor.css public/assets/js/editor.js tests/test_editor_container_dropping.js
git commit -m "feat(editor): layout-aware dropping and animated live insertion slots"
```

---

### Task 4: Empty Canvas State & Build-From-Scratch Bootstrap

**Files:**
- Modify: `public/assets/js/pagedock-harness.js`
- Modify: `public/assets/css/editor.css`
- Modify: `public/assets/js/editor.js`
- Create: `tests/test_editor_empty_canvas.js`

- [ ] **Step 1: Write the failing empty canvas test**
Create `tests/test_editor_empty_canvas.js`:
```javascript
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
```

- [ ] **Step 2: Run test to verify it fails**
Run: `node tests/test_editor_empty_canvas.js`
Expected: FAIL

- [ ] **Step 3: Implement empty canvas state and build-from-scratch bootstrap**
1. In `public/assets/js/pagedock-harness.js`:
   - Implement `checkEmptyCanvas()`: If `document.body` has no visible child elements (or only whitespace/comments), inject `.pd-empty-canvas` drop zone with a dashed border, plus icon, "Start Building Your Page" title, and "Drag sections or elements here" prompt.
   - When a component is dragged over `.pd-empty-canvas`, activate drop indicator and handle drop cleanly.
2. In `public/assets/css/editor.css`:
   - Style `.pd-empty-canvas`: Centered flexbox container with dashed 2px border `#38bdf8`, dark translucent background, rounded corners, padding 60px, and subtle hover pulse.
3. In `public/assets/js/editor.js`:
   - In `handleLibraryDrop`: If current code is blank or has no body content, generate clean boilerplate:
     ```html
     <!DOCTYPE html>
     <html lang="en">
     <head>
       <meta charset="UTF-8">
       <meta name="viewport" content="width=device-width, initial-scale=1.0">
       <title>New Page</title>
       <script src="https://cdn.tailwindcss.com"></script>
     </head>
     <body class="bg-slate-950 text-slate-100 min-h-screen">
       ${snippet}
     </body>
     </html>
     ```

- [ ] **Step 4: Run test to verify it passes**
Run: `node tests/test_editor_empty_canvas.js`
Expected: PASS

- [ ] **Step 5: Git commit**
```bash
git add public/assets/js/pagedock-harness.js public/assets/css/editor.css public/assets/js/editor.js tests/test_editor_empty_canvas.js
git commit -m "feat(editor): empty canvas drop zone and build-from-scratch bootstrapping"
```
