# Elementor-Grade Visual Drag-and-Drop Builder Design Specification

**Date:** 2026-11-08  
**Status:** Approved  
**Author:** Antigravity & User  
**Target System:** PageDock AI HTML Editor  

---

## 1. Executive Summary & Goals

PageDock's AI HTML Editor combines a browser-based code editor (CodeMirror 5) with a live sandboxed preview canvas (`<iframe>`). While basic section reordering and library dragging existed previously, it lacked the granularity, ergonomics, and visual polish of modern website builders like Elementor.

This specification defines an **Elementor-grade visual drag-and-drop builder** implemented in pure, lightweight vanilla JavaScript.

### Core Goals:
1. **Dedicated Drag Handles & Overlays**: Decouple canvas element clicking and double-click inline text editing from dragging. Dragging is strictly initiated from a dedicated `⠿` grip on hover.
2. **One-Click Quick Actions**: Provide instant Duplicate (`⧉`), Add Below (`+`), and Delete (`✕`) actions directly on the canvas hover toolbar.
3. **Container-Level & Nested Dropping**: Enable dropping elements into containers (`<div class="grid">`, `<div class="flex">`, `<div class="container">`, `<form>`), not just macro `<section>` boundaries.
4. **Layout-Aware Drop Detection**: Support horizontal insertion (`left`/`right`) inside flex rows and vertical insertion (`top`/`bottom`) inside stacks, with void tag (`<img>`, `<input>`) protection.
5. **Animated Live Placeholders**: Provide an animated insertion slot previewing exactly where the element will land.
6. **Build-from-Scratch & Template Compatibility**: Support both complex existing multi-page templates and brand-new blank pages with an empty canvas drop zone.
7. **Deterministic CodeMirror Sync**: Synchronize visual canvas edits to the code buffer via transient element IDs (`data-pd-id`), guaranteeing zero regex index drift.

### Non-Goals:
- Heavy framework dependencies (no jQuery, React, or Vue).
- Complex canvas scaling/zooming outside responsive device viewport presets.

---

## 2. Architecture & Data Model

### 2.1 Element Hierarchy
The canvas classifies page elements into a three-tiered hierarchy:
1. **Sections (`SECTION`, `HEADER`, `NAV`, `FOOTER`, `MAIN`, or top-level layout bands)**: Full-width macro page blocks.
2. **Containers (`DIV.container`, `DIV.grid`, `DIV.flex`, `DIV.row`, `DIV.card`, `FORM`)**: Intermediate layout units that hold widgets or nested flex/grid children.
3. **Widgets / Elements (`H1-H6`, `P`, `BUTTON`, `A`, `IMG`, `INPUT`, `SPAN`, `UL`, `LI`)**: Leaf content elements.

### 2.2 Transient Element Identity (`data-pd-id`)
When the preview canvas loads, `pagedock-harness.js` assigns transient IDs to all interactive blocks:
```html
<section data-pd-id="pd_sec_1">
  <div class="container flex gap-4" data-pd-id="pd_con_2">
    <button data-pd-id="pd_btn_3">Click Me</button>
  </div>
</section>
```
* On CodeMirror buffer updates or save operations, `data-pd-id` attributes are preserved in active memory but stripped cleanly during live deployment/publishing.
* Messages dispatched across windows reference `targetId` and `sourceId`, making updates 100% deterministic.

---

## 3. Element Overlays & Hover Toolbars

### 3.1 Overlay Construction (`.pd-element-overlay`)
Inside `pagedock-harness.js`, every section and container receives an overlay wrapper:
```html
<div class="pd-element-overlay" data-pagedock-ui="overlay">
  <div class="pd-element-toolbar" data-pagedock-ui="toolbar">
    <button class="pd-tool-btn pd-tool-drag" title="Drag to Move" draggable="true">
      <svg width="12" height="12" viewBox="0 0 24 24">...</svg> <!-- ⠿ -->
    </button>
    <button class="pd-tool-btn pd-tool-duplicate" title="Duplicate">
      <svg width="12" height="12" viewBox="0 0 24 24">...</svg> <!-- ⧉ -->
    </button>
    <button class="pd-tool-btn pd-tool-add" title="Add Block Below">
      <svg width="12" height="12" viewBox="0 0 24 24">...</svg> <!-- + -->
    </button>
    <button class="pd-tool-btn pd-tool-delete" title="Delete">
      <svg width="12" height="12" viewBox="0 0 24 24">...</svg> <!-- ✕ -->
    </button>
  </div>
</div>
```

### 3.2 Interaction Rules
* Overlay wrapper has `pointer-events: none` and `position: absolute; inset-block-start: 0; inset-inline-end: 0;`.
* The toolbar has `pointer-events: all;` and is hidden (`display: none`) until the element is hovered or focused.
* Clicking inside the element directly triggers normal selection, link inspection, or double-click inline text editing.
* Dragging is **only** triggered when `dragstart` occurs on the `.pd-tool-drag` button.

---

## 4. Layout-Aware Dropping & Live Placeholders

### 4.1 Droppable Logic
When dragging a component over the canvas:
1. `document.elementFromPoint(x, y)` resolves the target element.
2. The engine inspects `getComputedStyle(target)`:
   - **Horizontal Mode**: If `display === 'flex'` and `flexDirection.includes('row')`, the cursor's `clientX` relative to `rect.left + rect.width / 2` determines `left` vs `right` placement.
   - **Vertical Mode**: For all other containers/elements, `clientY` relative to `rect.top + rect.height / 2` determines `top` vs `bottom` placement.
3. **Void Tag Protection**: If `target.tagName` matches `IMG`, `INPUT`, `HR`, `BR`, `EMBED`, the engine forbids `insertInside` and automatically defaults to `before` or `after`.
4. **Container Insertion**: If hovering over an empty container or directly into a container's padded area, the engine offers `insertInside` (`appendChild`).

### 4.2 Animated Insertion Slot (`.pd-drop-slot`)
* Replaces the static 3px line with an animated placeholder slot (`<div class="pd-drop-slot"></div>`).
* Transitions with `animation: pd-slot-pulse 400ms ease-out` and expands to give live visual preview of layout displacement.

### 4.3 Viewport Auto-Scrolling
* Dragging within 50px of the iframe top edge smoothly scrolls the preview window upward.
* Dragging within 50px of the iframe bottom edge smoothly scrolls downward.

---

## 5. Empty Canvas State ("Build from Scratch")

When a page contains no content inside `<body>`:
* The canvas renders a centered, styled empty drop zone:
  ```html
  <div class="pd-empty-canvas" data-pagedock-ui="empty-canvas">
    <div class="pd-empty-icon">+</div>
    <div class="pd-empty-title">Start Building Your Page</div>
    <div class="pd-empty-desc">Drag any section or element from the library into this space</div>
  </div>
  ```
* Dropping any item into this zone bootstraps the initial HTML structure cleanly and replaces the empty state.

---

## 6. CodeMirror Bidirectional Synchronization

### 6.1 Protocol Messages
* `element-reorder`: `{ type: 'element-reorder', sourceId, targetId, position: 'before'|'after' }`
* `element-duplicate`: `{ type: 'element-duplicate', targetId }`
* `element-delete`: `{ type: 'element-delete', targetId }`
* `library-drop`: `{ type: 'library-drop', snippet, name, targetId, position: 'before'|'after'|'inside' }`

### 6.2 Buffer Patching
* In `editor.js`, each message locates the target element by its exact `data-pd-id` attribute within the CodeMirror buffer.
* The update is applied as a single atomic operation in CodeMirror, automatically registered in the undo/redo history.

---

## 7. Verification Test Suite

1. `tests/test_editor_overlay_handles.js`:
   - Validates overlay and toolbar rendering on sections/containers.
   - Asserts dragging only triggers from the `.pd-tool-drag` handle and does not interfere with double-click text editing.
2. `tests/test_editor_container_dropping.js`:
   - Validates horizontal insertion detection in flex rows.
   - Validates void tag protection (`IMG`, `INPUT`) forcing insertion outside.
   - Validates container `inside` drop insertion.
3. `tests/test_editor_canvas_actions.js`:
   - Validates 1-click duplicate (`⧉`) cloning in CodeMirror.
   - Validates 1-click delete (`✕`) removal in CodeMirror with undo capability.
4. `tests/test_editor_empty_canvas.js`:
   - Validates rendering of the empty canvas drop zone on blank pages and bootstrap on drop.
