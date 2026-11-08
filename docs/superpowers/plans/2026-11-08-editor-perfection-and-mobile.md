# Editor Perfection & Mobile Responsiveness Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Transform the PageDock AI HTML Editor into a rock-solid, bug-free, mobile-responsive static web studio before activating any AI generation.

**Architecture:** Responsive mobile layout with dynamic `[ Code | Preview ]` view switching under 768px; full-document live DOM synchronization in `pagedock-harness.js`; tagIndex-based DOM manipulation for duplicate/delete/inline-text-edit; and local draft persistence via `localStorage`.

**Tech Stack:** PHP 8.5, Vanilla JavaScript, CodeMirror 5, CSS3 Custom Properties, Node.js (test runner).

---

### Task 1: Mobile-Responsive Viewport Switcher & Layout Overhaul

**Files:**
- Modify: `views/editor.php`
- Modify: `public/assets/css/editor.css`
- Modify: `public/assets/js/editor.js`
- Create: `tests/test_editor_mobile_responsive.js`

- [ ] **Step 1: Write the failing mobile test suite**
Create `tests/test_editor_mobile_responsive.js` validating:
1. Mobile segmented view switcher `[ Code | Preview ]` exists in `views/editor.php`.
2. Responsive CSS rules exist in `public/assets/css/editor.css` under `@media (max-width: 768px)`.
3. Left panel collapses to 100% width or hides based on active mobile view mode.
4. Topbar buttons satisfy 40px+ touch target size.
5. Popovers have `max-width: calc(100vw - 20px)`.

- [ ] **Step 2: Run test to verify it fails**
Run: `node tests/test_editor_mobile_responsive.js`  
Expected: FAIL (missing mobile switcher and responsive media queries)

- [ ] **Step 3: Implement mobile markup, styles, and controller**
1. Add mobile view toggle bar (`hh-mobile-view-bar` with Code and Preview buttons) to `views/editor.php`.
2. Add `@media (max-width: 768px)` and `@media (max-width: 480px)` styles in `public/assets/css/editor.css`.
3. Wire up mobile view switcher click handlers in `public/assets/js/editor.js`.

- [ ] **Step 4: Run test to verify it passes**
Run: `node tests/test_editor_mobile_responsive.js`  
Expected: PASS

- [ ] **Step 5: Git commit**
```bash
git add views/editor.php public/assets/css/editor.css public/assets/js/editor.js tests/test_editor_mobile_responsive.js
git commit -m "feat(editor): implement mobile-first responsive layout and view switcher"
```

---

### Task 2: Live Preview Synchronization & Full-Document Patching Engine

**Files:**
- Modify: `public/assets/js/pagedock-harness.js`
- Modify: `public/assets/js/editor.js`
- Create: `tests/test_editor_live_sync.js`

- [x] **Step 1: Write the failing live sync test**
Create `tests/test_editor_live_sync.js` asserting that:
1. `pagedock-harness.js` accepts `{ type: 'live-patch', html: '...' }` without requiring `d.tag`.
2. Full document sync updates title, styles, and body DOM cleanly without destroying the harness.
3. Debounced typing in CodeMirror dispatches live-patch to iframe.

- [x] **Step 2: Run test to verify it fails**
Run: `node tests/test_editor_live_sync.js`  
Expected: FAIL (harness requires d.tag on live-patch)

- [x] **Step 3: Implement full-document live patch in `pagedock-harness.js`**
Update `live-patch` handler in `public/assets/js/pagedock-harness.js` to parse full document and update body/head DOM when `d.tag` is not specified.

- [x] **Step 4: Run test to verify it passes**
Run: `node tests/test_editor_live_sync.js`  
Expected: PASS

- [x] **Step 5: Git commit**
```bash
git add public/assets/js/pagedock-harness.js public/assets/js/editor.js tests/test_editor_live_sync.js
git commit -m "fix(editor): support full-document live sync in preview harness"
```

---

### Task 3: Direct Visual Editing Reliability (Text Edit, Duplicate, Delete, Code Jump)

**Files:**
- Modify: `public/assets/js/pagedock-harness.js`
- Modify: `public/assets/js/editor.js`
- Create: `tests/test_editor_direct_manipulation.js`

- [ ] **Step 1: Write the failing test**
Create `tests/test_editor_direct_manipulation.js` asserting:
1. Duplicate dispatches DOM clone or replaces exact tagIndex.
2. Delete removes exact tagIndex.
3. Escape key dismisses popovers cleanly.
4. Jump to code resolves exact line position.

- [ ] **Step 2: Run test to verify it fails**
Run: `node tests/test_editor_direct_manipulation.js`  
Expected: FAIL

- [ ] **Step 3: Implement reliable DOM manipulation & popover dismissals**
1. Add `duplicate-element` and `delete-element` protocol messages in `pagedock-harness.js`.
2. Update `editor.js` to trigger iframe DOM operations and sync back updated HTML.
3. Ensure popover dismissal on Escape key or outside click.

- [ ] **Step 4: Run test to verify it passes**
Run: `node tests/test_editor_direct_manipulation.js`  
Expected: PASS

- [ ] **Step 5: Git commit**
```bash
git add public/assets/js/pagedock-harness.js public/assets/js/editor.js tests/test_editor_direct_manipulation.js
git commit -m "feat(editor): reliable tagIndex-based DOM manipulation and popover handling"
```

---

### Task 4: Local Storage Auto-Save & Draft Safety

**Files:**
- Modify: `public/assets/js/editor.js`
- Create: `tests/test_editor_draft_persistence.js`

- [ ] **Step 1: Write failing draft persistence test**
Create `tests/test_editor_draft_persistence.js` testing:
1. Keystroke changes are saved to `localStorage` under `pagedock_draft_${slug}_${file}`.
2. Recovery prompt or auto-restore loads newer local draft on initial mount.
3. Save Draft clears or updates local draft timestamp.

- [ ] **Step 2: Run test to verify it fails**
Run: `node tests/test_editor_draft_persistence.js`  
Expected: FAIL

- [ ] **Step 3: Implement localStorage draft persistence in `editor.js`**
1. Save debounced draft to `localStorage`.
2. Check on load if draft exists and is newer than database copy; restore cleanly.
3. Clear draft on successful Publish or save.

- [ ] **Step 4: Run test to verify it passes**
Run: `node tests/test_editor_draft_persistence.js`  
Expected: PASS

- [ ] **Step 5: Git commit**
```bash
git add public/assets/js/editor.js tests/test_editor_draft_persistence.js
git commit -m "feat(editor): add local draft auto-save and crash recovery"
```
