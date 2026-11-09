# Canva-Style Visual Canvas Editing & Instant Deletion Design

**Status:** Approved  
**Date:** 2026-11-09  
**Target:** PageDock Injected Preview Harness (`pagedock-harness.js`) & Editor Controller (`editor.js`)

---

## 1. Executive Summary & Goals

Users want a tactile, Canva-like direct editing experience inside PageDock:
1. **Instant Element Removal**: The ability to click any element (heading, paragraph, image, button, card, or container) and delete it immediately with one click (trash icon) or one keypress (`Delete` or `Backspace`).
2. **Fluid Drag-and-Drop Repositioning**: The ability to grab any element and drag it across the canvas to reposition it above, below, or beside other elements.
3. **Guaranteed Mobile Responsiveness**: Unlike raw print flyers that use broken fixed coordinates (`left: 850px`), PageDock's drag-and-drop engine compiles moves into semantic HTML and responsive CSS (Flexbox / Grid auto-reflow) so the layout looks flawless on desktop, tablet, and mobile.
4. **Non-Destructive Live Sync & Full Undo**: Immediate synchronization with the CodeMirror buffer and full undo/redo capability (`Cmd+Z` / `Ctrl+Z`).

---

## 2. Interaction & Architecture Model

```
+--------------------------------------------------------------------------+
| PageDock Main Editor Window (editor.js)                                  |
|                                                                          |
|  [Topbar: Undo | Redo | Device Views | Save Draft | Publish]             |
|                                                                          |
|  +--------------------+  +--------------------------------------------+  |
|  | Code / Sidebar     |  | Preview Iframe Canvas (pagedock-harness.js) |  |
|  |                    |  |                                            |  |
|  |                    |  |   +------------------------------------+   |  |
|  | CodeMirror HTML    |  |   | [ 🗑️ | ✥ | 📋 | ✏️ ] Micro-Toolbar  |   |  |
|  | Buffer             |  |   +------------------------------------+   |  |
|  |                    |  |   | [Active Blue Border + Drag Grip]   |   |  |
|  |                    |  |   |  <button>Book Tour Now</button>    |   |  |
|  |                    |  |   +------------------------------------+   |  |
|  |                    |  |                                            |  |
|  |                    |  |   [=== Glowing Blue Magnetic Slot ===]     |  |
|  |                    |  |   <div>Destination Cards Grid</div>        |  |
|  +--------------------+  +--------------------------------------------+  |
+--------------------------------------------------------------------------+
```

### 2.1 Micro-Toolbar & Selection Bounding Box
When an element is clicked in Edit Mode:
* The element receives an active selection outline (`2px solid #38bdf8`) with rounded corners and a subtle drop shadow.
* A floating micro-toolbar attaches directly to the top edge of the element with high z-index (`99999`):
  * **🗑️ Delete Button (`.pd-tool-delete`)**: Red hover highlight (`rgba(239, 68, 68, 0.2)`). Clicking it immediately removes the element.
  * **✥ Move Handle (`.pd-tool-drag`)**: Cursor becomes `grab` / `grabbing`. Dragging initiates element repositioning.
  * **📋 Duplicate Button (`.pd-tool-duplicate`)**: Clones the element immediately after its current position.
  * **✏️ In-Place Text Edit (`.pd-tool-edit`)**: Starts in-place contentEditable editing (also accessible via double-click).

### 2.2 Native Keyboard Shortcuts
* **`Delete` and `Backspace`**:
  * When any element is currently selected (and not actively editing text in an input/textarea/contentEditable), pressing `Delete` or `Backspace` executes an immediate element deletion.
  * Emits an `element-delete` message to the parent editor to update the source code and push a snapshot to the undo stack.
* **`Escape`**:
  * Deselects the current element and dismisses all active toolbars and drop slots.
  * If pressed while dragging, cancels the drag operation and restores the element to its original DOM position.
* **`Cmd+Z` / `Ctrl+Z` (Undo)**:
  * Restores the deleted or moved element with 100% fidelity.

---

## 3. Responsive Fluid Drag & Drop Engine

### 3.1 Drag Initiation & Ghost Preview
1. On `mousedown` or `touchstart` on the ✥ Move Handle (or dragging a selected element with `Shift` or dedicated drag area):
   * Creates a semi-transparent ghost replica (`opacity: 0.75`, `box-shadow: 0 12px 30px rgba(0, 0, 0, 0.35)`) that tracks cursor coordinates.
   * Marks the source element with `.pd-element-dragging` (semi-transparent placeholder in DOM).

### 3.2 Target Detection & Magnetic Insertion Slots
As the user moves their mouse over the canvas:
* Calculates pointer collision with sibling blocks and container sections.
* Renders a glowing magnetic drop guide (`.pd-drop-slot`):
  * **Vertical snap**: Horizontal blue slot indicating insertion before or after an element.
  * **Column snap (side-by-side)**: Vertical blue slot indicating creation or addition to a flex row (`display: flex; gap: 1.5rem; flex-wrap: wrap;`).
  * **Container snap**: Outer highlight indicating insertion into an empty or populated card/section.

### 3.3 Mobile-Safe Clean Layout Compilation
* **Banned**: Rigid pixel coordinates (e.g. `position: absolute; left: 850px; top: 320px;`).
* **Enforced**: Standard semantic HTML structure with responsive CSS classes or modern CSS styling (e.g. `display: flex; flex-direction: row; gap: 1rem;` that defaults to `flex-direction: column;` on mobile screens `<= 768px`).

---

## 4. Real-Time Bidirectional Synchronization

1. **Preview DOM Mutation**:
   * On deletion, the harness removes the node from the iframe document immediately for zero-latency feel.
   * Dispatches `element-delete` payload containing:
     ```json
     {
       "type": "element-delete",
       "targetTag": "BUTTON",
       "targetIndex": 2,
       "targetId": "pd-elem-1234",
       "html": "<button class=\"btn-primary\">Book Tour</button>"
     }
     ```
2. **Parent Editor Processing**:
   * `editor.js` records an undo snapshot in `history.pushState()`.
   * Strips the target HTML snippet from CodeMirror buffer cleanly.
   * Displays a lightweight toast notification: `Deleted <button> (Cmd+Z to undo)`.

---

## 5. Verification & Testing Plan

### 5.1 Automated Unit & Integration Tests
* `tests/test_canvas_element_deletion.js`:
  * Verify `pagedock-harness.js` listens to `Delete` and `Backspace` keys.
  * Verify keyboard deletion dispatches `element-delete` with correct tag and index.
  * Verify clicking `.pd-tool-delete` dispatches `element-delete`.
  * Verify `editor.js` strips target HTML from CodeMirror buffer and registers undo step.
* `tests/test_canvas_drag_drop_flow.js`:
  * Verify drag initialization creates magnetic indicator slots.
  * Verify drag drop repositions target node in DOM.
  * Verify `Escape` key cleanly cancels active drag and resets DOM without changes.
* `tests/test_undo_redo_recovery.js`:
  * Verify deleting an element and triggering undo restores the identical HTML.

### 5.2 Manual Browser Verification
* Load `http://localhost:8080/editor/adventure-travel-tours`.
* Click any button or paragraph → verify bounding box and trash button appear.
* Click trash button → verify element disappears instantly and CodeMirror updates.
* Press `Cmd+Z` → verify element returns instantly.
* Click another element, press `Delete` on keyboard → verify instant removal.
* Drag an element to a new location → verify smooth movement and check Mobile view (`hh-mobile-btn-preview`) to confirm no horizontal scroll or broken layout.
