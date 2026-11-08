# Interactive Canvas & Multi-Page Editing Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement full in-place visual text editing, drag-and-drop section reordering on the canvas, multi-page site switching with internal link routing, and library drag-to-position insertion for the PageDock Editor.

**Architecture:** Bi-directional postMessage protocol between `pagedock-harness.js` (inside preview iframe) and `editor.js` (parent window); tagIndex-based DOM reordering synced to CodeMirror; multi-page session state preservation across file switches; and HTML5 drag-and-drop boundary bridging.

**Tech Stack:** Vanilla JavaScript, HTML5 Drag and Drop API, CodeMirror 5, PHP 8.5, Node.js (test suites).

---

### Task 1: In-Place Live Text Editing (Double-Click & Mixed Inline Tag Support)

**Files:**
- Modify: `public/assets/js/pagedock-harness.js`
- Modify: `public/assets/js/editor.js`
- Create: `tests/test_editor_inline_text_edit.js`

- [ ] **Step 1: Write the failing inline text edit test**
Create `tests/test_editor_inline_text_edit.js` asserting that:
1. `isEditableTextNode(el)` in `pagedock-harness.js` returns true for text-bearing elements (`h1-h6`, `p`, `span`, `button`, `a`, `li`, `blockquote`, `label`) even when containing inline children (e.g. `<span>`, `<strong>`, `<em>`).
2. Double-click on a text-bearing element activates `contenteditable` and sets visual editing outline.
3. Hitting `Enter` commits the change, removes `contenteditable`, and dispatches `element-text-edit` to parent.
4. `editor.js` handles `element-text-edit` updating the exact text range in CodeMirror without erasing adjacent attributes or tags.

- [ ] **Step 2: Run test to verify it fails**
Run: `node tests/test_editor_inline_text_edit.js`
Expected: FAIL

- [ ] **Step 3: Implement enhanced in-place text editing**
1. Upgrade `isTextOnly` to `isEditableTextNode` in `public/assets/js/pagedock-harness.js`.
2. Support `Enter` (commit) and `Escape` (cancel/revert) keyboard events on `contenteditable` nodes.
3. In `public/assets/js/editor.js`, enhance `handleElementTextEdit` to replace innerHTML/textContent cleanly based on tag range.

- [ ] **Step 4: Run test to verify it passes**
Run: `node tests/test_editor_inline_text_edit.js`
Expected: PASS

- [ ] **Step 5: Git commit**
```bash
git add public/assets/js/pagedock-harness.js public/assets/js/editor.js tests/test_editor_inline_text_edit.js
git commit -m "feat(editor): in-place live text editing with mixed inline tag support"
```

---

### Task 2: Visual Section Drag-and-Drop Reordering in Live Preview

**Files:**
- Modify: `public/assets/js/pagedock-harness.js`
- Modify: `public/assets/js/editor.js`
- Create: `tests/test_editor_section_reorder.js`

- [ ] **Step 1: Write the failing section reordering test**
Create `tests/test_editor_section_reorder.js` asserting that:
1. `pagedock-harness.js` tracks drag operations on semantic sections (`SECTION`, `ARTICLE`, `HEADER`, `NAV`, `FOOTER`, `DIV.section`).
2. Renders dynamic insertion line guide (`mkInd`) indicating drop position above/below adjacent sibling sections.
3. Dispatches `{ type: 'element-reorder', sourceTag, sourceIndex, targetTag, targetIndex, position: 'before'|'after' }` upon drop.
4. `editor.js` handles `element-reorder` and swaps/repositions the corresponding outerHTML blocks in CodeMirror.

- [ ] **Step 2: Run test to verify it fails**
Run: `node tests/test_editor_section_reorder.js`
Expected: FAIL

- [ ] **Step 3: Implement section drag-and-drop in harness and editor**
1. Implement drag handles and pointer/mouse drag listeners in `public/assets/js/pagedock-harness.js`.
2. Add visual insertion guide line and ghost preview styling.
3. Implement `handleElementReorder` in `public/assets/js/editor.js` to slice and reassemble CodeMirror buffer.

- [ ] **Step 4: Run test to verify it passes**
Run: `node tests/test_editor_section_reorder.js`
Expected: PASS

- [ ] **Step 5: Git commit**
```bash
git add public/assets/js/pagedock-harness.js public/assets/js/editor.js tests/test_editor_section_reorder.js
git commit -m "feat(editor): visual section drag-and-drop reordering with live insertion guides"
```

---

### Task 3: Multi-Page Site Switching & Internal Link Navigation

**Files:**
- Modify: `views/editor.php`
- Modify: `public/assets/js/editor.js`
- Modify: `public/assets/js/pagedock-harness.js`
- Create: `tests/test_editor_multipage_navigation.js`

- [ ] **Step 1: Write the failing multi-page navigation test**
Create `tests/test_editor_multipage_navigation.js` asserting that:
1. `hh-page-select` change listener in `editor.js` navigates to `?file={page}&token={token}`.
2. Clicking an internal link (`<a href="about.html">`) in preview iframe dispatches `{ type: 'navigate-page', path: 'about.html' }`.
3. Editor intercepts `navigate-page` message and prompts/auto-saves draft before switching active file.
4. Topbar or subbar renders page switcher dropdown with all available HTML pages.

- [ ] **Step 2: Run test to verify it fails**
Run: `node tests/test_editor_multipage_navigation.js`
Expected: FAIL

- [ ] **Step 3: Implement multi-page switcher and link interceptor**
1. Wire up `hh-page-select` in `public/assets/js/editor.js`.
2. Add `navigate-page` message dispatch in `public/assets/js/pagedock-harness.js` when internal `.html` link is clicked.
3. Handle `navigate-page` in `public/assets/js/editor.js` to switch active page smoothly.

- [ ] **Step 4: Run test to verify it passes**
Run: `node tests/test_editor_multipage_navigation.js`
Expected: PASS

- [ ] **Step 5: Git commit**
```bash
git add views/editor.php public/assets/js/editor.js public/assets/js/pagedock-harness.js tests/test_editor_multipage_navigation.js
git commit -m "feat(editor): multi-page file switcher and preview internal link routing"
```

---

### Task 4: Library Drag-to-Position Insertion into Live Preview

**Files:**
- Modify: `views/editor.php`
- Modify: `public/assets/js/editor.js`
- Modify: `public/assets/js/pagedock-harness.js`
- Create: `tests/test_editor_library_drag_drop.js`

- [ ] **Step 1: Write the failing library drag-and-drop test**
Create `tests/test_editor_library_drag_drop.js` asserting that:
1. Library cards have `draggable="true"` and set data-snippet on dragstart.
2. Dragging over preview iframe sends position updates or triggers drop indicator line.
3. Dropping into preview iframe inserts snippet before/after target section instead of always appending to body.
4. CodeMirror buffer is updated and preview renders new element.

- [ ] **Step 2: Run test to verify it fails**
Run: `node tests/test_editor_library_drag_drop.js`
Expected: FAIL

- [ ] **Step 3: Implement library drag-and-drop to position**
1. Add `draggable="true"` and `dragstart` event listeners to library items in `public/assets/js/editor.js`.
2. Handle dragover and drop events in `public/assets/js/pagedock-harness.js` via cross-window messaging or overlay listener.
3. Insert snippet at drop target index and sync to CodeMirror.

- [ ] **Step 4: Run test to verify it passes**
Run: `node tests/test_editor_library_drag_drop.js`
Expected: PASS

- [ ] **Step 5: Git commit**
```bash
git add views/editor.php public/assets/js/editor.js public/assets/js/pagedock-harness.js tests/test_editor_library_drag_drop.js
git commit -m "feat(editor): drag-and-drop component insertion from library into canvas position"
```
