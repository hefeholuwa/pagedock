#  HTMLHost.co Pure HTML & Visual Editor — Reverse-Engineering Dossier & PageDock Specification (No-AI Edition)

> **Investigation Targets:** `https://htmlhost.co/new` & `https://htmlhost.co/docs`  
> **Target Platform:** PageDock (`/Users/user/Desktop/pagedock`)  
> **Methodology:** Scrapling Stealth Extraction (`scrapling extract get --ai-targeted` & `FetcherSession`), REA JavaScript Application Graph & AST Decompilation (`analyze_javascript_application`, `inspect_analysis_view`), Next.js Turbopack Chunk Analysis (`0a476qra.ffal.js`, `0-8xiie3~s09k.js`, `0t89xnml0ly_9.js`, `0.h1ya389337y.js`, `130vygiferm._.js`, `01yg_h3ezghnr.js`), and Bidirectional `postMessage` Protocol Forensics.  
> **REA Evidence IDs:** `ev_3b837c1ab98e8286e38bc3cc50c39a5dc3ae07c7c70c53b4e06d524bef5cb3bb` (Parent Graph), `ev_645f9b8cd263ac98f84c910723d24fe3047c20a6c737219c0303af0685721691` (Summary View), `ev_a19f70e5b537e62ec7d59835b7fb7d42c1c754d75b1cc8e882a02fd6bb21f271` (Module Page View).  
> **Scope:** 100% Pure HTML/CSS/JS Code & Visual Editor. All AI prompts, LLM streaming endpoints, credit meters, BYOK keys, and AI chat drawers have been stripped.

---

## 1. Architecture Overview & Pure HTML Editor Topology

The HTMLHost Editor ( stripped of AI layers) is a client-orchestrated, deterministic visual and code IDE built for single-page and multi-page static HTML websites. It operates on a **zero-friction draft model**: users can drop a file/folder/ZIP, import from a URL, paste raw HTML, or pick a starter template, immediately entering a split code-and-canvas workspace without requiring authentication until publication.

```
┌──────────────────────────────────────────────────────────────────────────────────────────────────┐
│                                    PageDock HTML Editor Window                                   │
├──────────────────────────────────────────────────────────────────────────────────────────────────┤
│ Top Navigation & Action Toolbar                                                                  │
│ [<- Back] [Site Title] [Page Switcher v] [Undo] [Redo] [Media] | [Edit Mode On/Off] [Devices]    │
│ [Shared Components] [Tools v] [History] [TTL v] [Save Draft] [Publish]                           │
├────────────────────────────┬─────────────────────────────────────────────────────────────────────┤
│ Left Collapsible Panel     │ Center Live Canvas                                                  │
│ ┌────────────────────────┐ │ ┌─────────────────────────────────────────────────────────────────┐ │
│ │ [Code][Lib][Tree][Shr] │ │ │ Sandboxed <iframe> (allow-scripts allow-same-origin)            │ │
│ ├────────────────────────┤ │ │                                                                 │ │
│ │ Code Editor (Monaco)   │ │ │  [Shared Header]                                                │ │
│ │   - Multi-file tabs    │ │ │                                                                 │ │
│ │   - Syntax highlight   │ │ │  [Selected Element] <──── (Hover / Click Outlines)              │ │
│ │   - Find / Format      │ │ │       │                                                         │ │
│ │                        │ │ │  [Main Content Sections]                                        │ │
│ │ Library:               │ │ │                                                                 │ │
│ │   - 46 Sections        │ │ │  [Shared Footer]                                                │ │
│ │   - 48 Elements        │ │ └─────────────────────────────────────────────────────────────────┘ │
│ │                        │ │                                                                     │
│ │ Structure Tree:        │ │ Floating Element Overlays (Fixed z-[9999]):                         │
│ │   - DOM hierarchy      │ │  1. Direct Element Edit Popover:                                    │
│ │   - Drag-to-reorder    │ │     [Text] [Button/Link] [Image/Bg] [Video/Map] [Form] [Text Style] │
│ │   - Hide in editor     │ │     [Parent] [Duplicate] [Code] [Delete]                            │
│ │                        │ │  2. Right-Click Context Menu:                                       │
│ │ Shared Components:     │ │     [Copy/Cut/Paste/Paste Style] [Move Up/Down] [Hide] [Delete]     │
│ │   - Header / Footer    │ │                                                                     │
│ │   - Cookie Banner      │ │                                                                     │
│ └────────────────────────┘ └─────────────────────────────────────────────────────────────────────┘
└──────────────────────────────────────────────────────────────────────────────────────────────────┘
```

---

## 2. Ingestion, Hydration & Draft/Publish State Model

When `/new` (or PageDock's `/editor`) opens, the client initializes project files from four channels (`01yg_h3ezghnr.js` & `0a476qra.ffal.js`):

1. **Query Parameters:**
   - `?edit={slug}`: Loads the working copy (including saved drafts) from `GET /api/sites/pull?slug={slug}&source=working`.
   - `?version={revision}`: Loads a specific historical deployment snapshot into the editor as an unsaved draft.
2. **Session Storage Ingestion:**
   - `sessionStorage.getItem("hero-html")`: Ingests raw pasted HTML from the homepage or `/paste-html`, then clears `sessionStorage.removeItem("hero-html")`.
   - `sessionStorage.getItem("hero-project")`: Ingests multi-file JSON `{ pages: [{ path, html, type }] }`.
   - `sessionStorage.getItem("hero-clone-url")`: Clones a public URL into an editable project.
3. **Pending File Drop Ingestion (`consumePendingFiles`):**
   - Checks `window.__htmlhost_pending_files` via `hasPendingFiles()` for files, folders, or ZIP archives dropped on the landing page (`fileListToEntries` / `importProjectFromFiles`).
4. **Local IndexedDB Draft Persistence (`Dexie.js`) & Publish Boundary:**
   - Working edits are persisted locally in IndexedDB and synced to the server with `isDraft: true` so changes do not overwrite the live site until the user clicks **Publish** / **Update** (`confirmPublish: true`).
   - Every explicit publish creates an immutable version (`v1`, `v2`, …) containing pages, CSS, JS, and assets, while leaving domain, form settings, password, and TTL outside the version boundary.

---

## 3. Sandboxed Iframe Preview & Injected Control Harness

The preview canvas uses a dual-buffered/sandboxed `<iframe>` (`0-8xiie3~s09k.js` & `0a476qra.ffal.js`):

```html
<iframe
  srcdoc="..."
  sandbox="allow-scripts allow-same-origin allow-popups"
  class="border-none bg-white transition-[width,height,border-radius,box-shadow] duration-300 ease-[cubic-bezier(.2,.8,.2,1)]"
></iframe>
```

Before rendering `srcdoc`, the parent window injects two scripts into the document:

### 3.1. `<script data-htmlhost-ui>` (Head Pre-Parser, History Guard & Audio Silencer)
Injected at the start of `<head>` via `injectAtHeadStart(html, script)` (`0-8xiie3~s09k.js`):
- **History & Hash Navigation Guard (`PREVIEW_HISTORY_GUARD_SCRIPT`):** Wraps `history.pushState` and `history.replaceState` and intercepts `window.navigation.addEventListener('navigate', ...)` so hash changes (`#section`) and pushState calls inside `srcdoc` do not corrupt the parent editor's undo/navigation history. Clears `location.hash` on `beforeunload`.
- **Audio/Video Auto-Muting:** Observes `childList` via `MutationObserver` and captures `play` events to set `m.muted = true` on all `<audio>` and `<video>` elements so live previews never play unexpected sound.
- **Buffer DOM Ready Signaling:** Fires `window.parent.postMessage({ type: 'buffer-dom-ready' }, '*')` on `DOMContentLoaded` for smooth double-buffered frame swapping (eliminating white flashes on code updates).
- **Fullscreen Loader Unsticker:** Detects full-screen preloader overlays covering >60% of the viewport that have no interactive controls and remain stuck after load, hiding them via `[data-htmlhost-overlay-hidden] { display: none !important; }` and notifying the parent (`{ type: 'preview-overlay-stuck' }`).

### 3.2. Visual Inspector & Direct Manipulation Engine (87.6 KB Injected Script)
Injected before `</body>` (`0a476qra.ffal.js`, offsets `425632`–`513234`, 1,500 lines):
- **Hover Highlighter:** Outlines hovered elements with `2px dashed rgba(125,211,252,0.5)`.
- **Selection Highlighter:** Outlines the active element with `2px solid #7dd3fc` (`outlineOffset: '-1px'`).
- **Section Boundary Highlighter (`findSection`):** Walks up the DOM to the nearest semantic section (`SECTION`, `ARTICLE`, `MAIN`, `HEADER`, `FOOTER`, `NAV`, `ASIDE`) and outlines it with `1.5px dashed rgba(125,211,252,0.35)`.
- **In-Place Text Editing (`begin-text-edit` / Double-Click):**
  - Double-clicking any text-only element (or pressing `Enter` while selected) activates in-place `contenteditable="true"`.
  - Pressing `Enter` commits the text (`element-text-edit`), `Shift+Enter` inserts a line break, and `Esc` cancels and restores the original text.
- **Drag-and-Drop DOM Reordering:**
  - Creates a translucent ghost clone (`mkGhost`, `opacity: 0.7`) and a directional drop line indicator (`mkInd`, `#7dd3fc`).
  - Automatically detects flex/grid row vs. column flow (`isRow`) to show vertical or horizontal insertion markers.
  - Auto-scrolls the iframe viewport (`edgeScroll`) when dragging near the top or bottom edges, with full mouse and touch (`pointerdown` long-press) support.
- **Three-Way Live DOM Patching (`hhMerge`, `hhAttrs`, `hhSameKids`):**
  - When the user edits an element while inspecting an interactive state (e.g., an open mobile menu, modal, or tab), `live-patch` compares the source HTML before the edit (`a`), after the edit (`b`), and the live DOM node (`live`).
  - Only the delta between `a` and `b` is mutated on `live`, preserving runtime classes, open states, and JavaScript event listeners attached by the page's own scripts.
- **Stylesheet Lock Scanner (`lockedProps`):**
  - Scans `document.styleSheets` for `!important` declarations or `background-clip: text` gradients targeting the selected element so the Direct Element Edit popover only exposes inline style controls that will actually take effect.
- **Temporary Editor Hiding (`hideFp` / `hide-elements`):**
  - Computes a structural fingerprint (`fpOf(el)`) for sticky headers, popups, or overlays so the user can hide them inside the editor preview (`[data-hh-editor-hidden] { display: none !important; }`) to click elements underneath without modifying the site's actual HTML source.

---

## 4. Full Bidirectional `postMessage` Protocol (Pure HTML Editor)

### 4.1. Parent -> Iframe Messages
| Type | Payload | Action |
| :--- | :--- | :--- |
| `edit-mode` | `{ enabled: boolean }` | Toggles visual element inspection vs. normal interactive browsing mode (`⇧⌘E`). |
| `patch-element` | `{ tag, tagIndex, html }` | Replaces target element `outerHTML` directly in the DOM. |
| `live-patch` | `{ seq, tag, tagIndex, id, before, after }` | Performs three-way live DOM merge (`hhMerge`) preserving runtime script state. |
| `begin-text-edit` | `{}` | Activates in-place `contenteditable` on the currently selected element. |
| `select-element` | `{ tag, tagIndex }` | Selects an element from the Structure tree or breadcrumb bar. |
| `select-parent` | `{}` | Selects the parent element (`⌥←`) and re-centers the popover. |
| `hover-element` | `{ tag, tagIndex }` | Highlights an element when hovering its row in the Structure panel. |
| `reveal-element` | `{ tag, tagIndex }` | Scrolls the target element smoothly into the center of the viewport. |
| `clear-selection` | `{}` | Clears active element selection and removes outlines. |
| `mark-selected` | `{ tag, tagIndex }` | Restores the selection outline after a context menu closes. |
| `request-structure` | `{}` | Requests a fresh serialized DOM tree for the Structure panel. |
| `structure-context-menu` | `{ tag, tagIndex, x, y }` | Opens the unified element context menu when right-clicking a row in Structure. |
| `library-drag` | `{ element, x, y }` | Updates the live drop indicator while dragging a Section or Element from the Library. |
| `library-drop` | `{ html, x, y }` | Drops a Library component at the hovered insertion point. |
| `element-append` | `{ html, insertIndex }` | Appends a new element into the DOM and scrolls it into view. |
| `insert-section` | `{ html, insertIndex }` | Inserts a pre-built section block between existing page sections. |
| `hide-elements` | `{ fps: [...] }` | Hides elements matching the supplied fingerprints in the editor canvas only. |

### 4.2. Iframe -> Parent Messages
| Type | Payload | Action |
| :--- | :--- | :--- |
| `element-selected` | `{ html, editStyles, tag, tagIndex, text, parentTag, parentTagIndex, childIndex, siblingCount, hasParent, isTextOnly, ancestors, form, rect, sectionHtml, computedStyles, mediaUrl }` | Opens the **Direct Element Edit Popover** anchored to `rect`. |
| `element-context-menu` | `{ html, tag, tagIndex, text, hasChildren, hasParent, siblingCount, childIndex, parentTag, parentTagIndex, isTextOnly, ancestors, form, hideFp, rect, screenPos }` | Opens the **Right-Click Context Menu** at cursor/touch coordinates. |
| `element-reorder` | `{ sourceTag, sourceIndex, targetParentTag, targetParentIndex, dropIndex }` | Applies drag-and-drop DOM reordering to the underlying HTML source. |
| `element-text-edit` | `{ tag, tagIndex, text }` | Commits in-place `contenteditable` text edits back to the HTML source buffer. |
| `text-edit-start` | `{}` | Hides floating popovers while the user types directly on the canvas. |
| `live-patch-result` | `{ seq, ok: boolean }` | Reports whether three-way in-place DOM merge succeeded (`ok: false` triggers buffer reload). |
| `page-structure` | `{ tree: [...] }` | Sends the serialized DOM hierarchy (with form `` and hidden states) to the Structure tab. |
| `navigate-page` | `{ path }` | Intercepts clicks on internal links (e.g. `/about`) and switches the active page in the editor. |
| `form-preview-submit` | `{ form: { name, success, redirect } }` | Simulates form submission in preview mode (displaying the confirmation state without storing test spam). |
| `iframe-key` | `{ key, metaKey, ctrlKey, shiftKey, altKey }` | Forwards keyboard shortcuts (`⌘S`, `⌘Z`, `⌘D`, `⌘C`, `⌘V`, `Del`, etc.) to the parent editor. |
| `iframe-escape` | `{}` | Closes open popovers, menus, or fullscreen preview when `Esc` is pressed inside the iframe. |

---

## 5. Direct Element Edit Popover & Right-Click Context Menu

With AI removed, clicking any element in **Edit Mode** immediately opens the **Direct Element Edit Popover** (`0a476qra.ffal.js`, offsets `374000`–`392000`).

### 5.1. Direct Element Edit Popover (`div.fixed.z-[9999]`)
- **Dimensions & Styling:** `w-[320px] max-h-[min(640px,calc(100vh-24px))] flex flex-col border border-line rounded-2xl bg-bg-1 overflow-hidden shadow-[0_8px_40px_rgba(0,0,0,0.35),0_0_0_1px_rgba(125,211,252,0.15)]`
- **Draggable Header:**
  - Drag handle (`cursor-grab active:cursor-grabbing`)
  - Tag badge (`<span class="text-[12px] font-semibold text-accent">BUTTON</span>`)
  - Truncated text snippet (`max-w-[110px]`)
  - Close button (`Esc`)
- **Contextual Inspector Sections (`iI`):**
  1. **Text (`R.text`):**
     - Extracts each text node inside the element (`ih(html)`), allowing multi-part text editing while preserving nested `<strong>`, `<em>`, `<a>`, and `<svg>` tags.
     - Commits changes with HTML entity escaping (`escapeHtmlText`).
     - Quick action button: `"Or edit it on the page (double-click)"` (triggers `begin-text-edit`).
  2. **Button (`R.buttonLabel`):**
     - Edits `<input type="submit|button">` `value` attribute.
  3. **Link (`R.link`):**
     - **Link URL (`href`):** Monospace input (`placeholder="https://… or /page"`).
     - **Open in a new tab:** Checkbox toggling `target="_blank"` and merging `rel="noopener"`.
  4. **Image (`R.image`):**
     - 64×64 thumbnail preview + **"Replace image…"** button (opens Media Library modal or local file upload).
     - **Alt text:** Input updating the `alt` attribute for accessibility and SEO.
  5. **Background Photo (`R.background`):**
     - Appears on blocks ≥160×100px with a CSS `background-image: url(...)`.
     - Thumbnail preview + **"Replace photo…"** button updating inline `background-image`.
  6. **Video & Audio (`R.media` / `R.video`):**
     - For `<video>` / `<audio>` tags: **"Replace video…"** / **"Replace audio…"** via Media Library.
     - For embedded video `<iframe>`: **"Video link"** input that automatically parses YouTube (`youtube.com/watch?v=`, `youtu.be/`, `youtube.com/shorts/`) and Vimeo URLs into clean embed URLs (`https://www.youtube.com/embed/{id}` or `https://player.vimeo.com/video/{id}`).
  7. **Map (`R.map`):**
     - Detects Google Maps `<iframe src="...maps?q=...">` and exposes a **"Map address"** text input that updates the `q` parameter and refreshes the embedded map.
  8. **Form (`R.form`):**
     - If the `<form>` is not yet wired to PageDock: displays *"This form doesn't send anywhere yet."* with a 1-click **"Send messages to my inbox"** button that sets `action="/__pd/form" method="post"` and auto-infers `<input type="hidden" name="_form" value="Contact|Booking|Newsletter|Form">`.
     - If already wired: displays **"Form settings…"** (configuring `_form` name, `_success` inline confirmation message, `_redirect` thank-you page path, and `_error` fallback message).
  9. **Text Style (`R.textStyles`):**
     - **Colour (`color`):** Native `<input type="color">` swatch + hex/CSS value input + 1-click reset to page default.
     - **Size (`font-size`):** Numeric pixel input (`px`) showing computed fallback size + reset button.
     - **Weight (`font-weight`):** Dropdown (`Page default`, `300`, `400`, `500`, `600`, `700`, `800`) + reset button.
     - **Align (`text-align`):** 4-way segmented control (`left`, `center`, `right`, `justify`) + reset button.
     - **Override Protection:** After applying an inline style, the editor verifies via `computedStyles` whether an external `!important` rule blocked the change; if blocked, it automatically reverts the redundant inline style and notifies the user.
- **Footer Action Bar:**
  - `Parent` (`select-parent` — climbs up one DOM level)
  - `Duplicate` (`duplicate` — clones element immediately after itself)
  - `Code` (`show-source` — opens the Code tab and scrolls Monaco directly to the element's exact line range)
  - `Delete` (`delete` — removes element from DOM)

### 5.2. Right-Click Context Menu (`element-context-menu` & `structure-context-menu`)
Right-clicking any element on the canvas or in the Structure tree opens a unified portal menu (`0a476qra.ffal.js`, offsets `699000`–`708500`):
- **Shared Component Actions:** `Edit Shared Header/Footer`, `Unlink Header/Footer`, or `Share this header/footer across pages…`
- **Code & Structure:** `Show Source Code`, `Reveal in Structure`
- **Clipboard & Style Transfer:**
  - `Copy Element` (`⌘C` / `Ctrl+C`)
  - `Cut Element` (`⌘X` / `Ctrl+X`)
  - `Paste After` (`⌘V` / `Ctrl+V`)
  - `Paste Inside` (when target is a container element)
  - `Paste Style` (`⇧⌘V` / `Ctrl+Shift+V` — copies `class` and inline `style` attributes from the copied element onto the target element!)
- **Element Actions:** `Duplicate` (`⌘D`), `Replace Image…` (on `<img>`), `Form Settings…` / `Send to my inbox` (inside `<form>`), `Edit Text` (`dbl-click`)
- **DOM Ordering & Hierarchy:** `Move Up` (`⌥↑`), `Move Down` (`⌥↓`), `Select Parent` (`⌥←`)
- **Canvas Visibility:** `Hide in Editor` / `Show in Editor` (toggles temporary editor-only hiding via `hideFp`)
- **Destructive:** `Delete Element` (`Del`)

---

## 6. Component Library (46 Sections & 48 Elements) & Style-Token Adaptation

Extracted from `0t89xnml0ly_9.js` (`ComponentLibrary` & `LIBRARY_ELEMENTS`):

### 6.1. Automatic Page Style-Token Sampling (`exemplars` & `tokens`)
When a user drags an element or section from the Library onto the page, the editor samples the page's existing computed styles:
- **Color Tokens:** Background (`bg`), text (`fg`), muted text (`muted`), accent (`accent`), and border (`border`).
- **Exemplar Cloning:** Inspects existing `<button>`, `<h1>`–`<h3>`, `<p>`, `<input>`, and `<label>` elements on the page and copies their authored CSS classes (`authoredClassName`, stripping transient runtime classes like `.active`, `.open`, `.aos-animate`) or computed inline styles so newly dropped components automatically inherit the page's fonts, button shapes, and colors without AI!

### 6.2. The 48 Library Elements (`LIBRARY_ELEMENTS` across 7 Groups)
1. **Basic (8):** `Heading`, `Text Block`, `Button`, `Button Group`, `Text Link`, `Icon` (opens searchable 200+ SVG Icon Picker modal `iF` with size `24`/`32`/`48`/`64`px and `Site accent` / `Match text` color), `Badge`, `List`
2. **Media (7):** `Image` (opens Media Library picker), `Video`, `Audio`, `Image Grid` (Gallery), `Carousel`, `Map` (Google Maps embed), `Embed` (custom HTML/iframe)
3. **Layout (5):** `2 Columns`, `3 Columns`, `Card`, `Divider`, `Spacer`
4. **Content (10):** `Blockquote`, `Code Snippet`, `Table`, `Alert`, `Accordion` (`<details><summary>`), `Icon Box`, `Stat`, `Progress Bar`, `Star Rating`, `Breadcrumb`
5. **Forms (8):** `Contact Form` (pre-wired to `/__pd/form`), `Email Signup` (Newsletter), `Search Bar`, `Input Field`, `Text Area`, `Dropdown` (`<select>`), `File Upload`, `Checkbox`
6. **Social (3):** `Social Icons` (SVG row for X, Instagram, LinkedIn, Facebook, YouTube, GitHub), `Share Buttons` (native share-intent links + copy-link button), `Profile Card`
7. **Interactive (7):** `Popup` (native `<dialog>` + trigger button), `Back to Top` (floating anchor button), `Tabs` (accessible `role="tablist"` with inline keyboard/click switcher), `Countdown` (live JS countdown timer reading `data-target-date`), `Pricing Card`, `Testimonial`, `Cookie Notice` (`<aside id="cookie-consent" data-pd-cookie-consent>`)

### 6.3. The 46 Pre-Built Sections (Across 8 Categories)
All 46 sections use deterministic, semantic HTML templates that adapt to the page's CSS custom properties and typography:
1. **Hero (6):** `Centered Hero`, `Split Hero`, `Hero with Form`, `Video Hero`, `Minimal Hero`, `App / SaaS Hero`
2. **Features (6):** `Feature Grid (3-col)`, `Alternating Features`, `Bento Grid`, `Icon Feature List`, `Comparison Table`, `How It Works (Steps)`
3. **Content (7):** `Rich Text / Article`, `FAQ Accordion`, `Timeline`, `Stats / Metrics Row`, `Photo Gallery`, `Video Showcase`, `Blog Post Grid`
4. **Social Proof (5):** `Testimonials Grid`, `Single Quote Highlight`, `Logo Cloud`, `Press & Awards`, `Case Study Highlight`
5. **Commerce (6):** `Pricing (3 Plans)`, `Product Grid`, `Product Showcase`, `Services Cards`, `Restaurant / Café Menu`, `Appointment Booking`
6. **Conversion (5):** `Call to Action Banner`, `Contact Form + Details`, `Newsletter Signup`, `App Download`, `Waitlist / Coming Soon`
7. **Company (5):** `About / Story`, `Team Grid`, `Mission & Values`, `Careers / Open Roles`, `Location & Opening Hours`
8. **Navigation (6):** `Navbar`, `Centered Navbar`, `Announcement Bar`, `Multi-Column Footer`, `Simple One-Row Footer`, `Breadcrumbs Bar`

---

## 7. Structure Tree (`Navigator`) & Multi-Page Shared Components

### 7.1. Structure Tree Panel (`0a476qra.ffal.js`, offset `102000`)
- Displays the live collapsible DOM hierarchy of `<body>`.
- **Hover Sync:** Hovering a node in the tree highlights the element on the canvas (`hover-element`).
- **Drag-to-Reorder:** Nodes can be dragged to reorder siblings or move between containers, with a visual depth line (`tA`) and quick `Move up` / `Move down` chevron buttons.
- **Status Badges:** Shows `` on `<form>` elements connected to the inbox, and an `IconEyeOff` toggle on elements temporarily hidden in the editor.

### 7.2. Shared Header, Footer & Cookie Consent Engine (`0.h1ya389337y.js`)
- **Portable HTML Comment Markers:**
  ```html
  <!-- pd:shared header active-class="active" -->
  <header>
    <nav><a href="/">Home</a> <a href="/about" class="active" aria-current="page">About</a></nav>
  </header>
  <!-- /pd:shared header -->
  ```
- **Automatic Per-Page Link & Active State Normalization (`refreshSharedBlocks`):**
  - When a shared header or footer is saved across $N$ pages, each page keeps a full static HTML copy inside the comment markers (ensuring 100% portability for CLI/ZIP downloads).
  - The engine automatically adjusts relative links for subfolder depth (e.g. `../style.css` on `/blog/post-1`) and moves `active-class` + `aria-current="page"` to the link matching each page's path.
- **Scoped CSS/JS Extraction (`planSharedCss` / `planSharedAssets`):**
  - Extracts CSS rules and inline scripts that only touch the shared header/footer into `/shared/header.css` and `/shared/header.js`, verifying layout stability before moving rules.
- **Isolated Shared Component Preview:**
  - Choosing **Edit Shared Header** or **Edit Shared Footer** hides the rest of the page in the preview canvas so the user can edit the shared block cleanly and click **Save to N pages**.

---

## 8. Built-In Deterministic Developer Tools Suite (15 Zero-AI Tools)

Extracted from `0a476qra.ffal.js` (`aW`, offsets `273000`–`286000`). Every tool is 100% deterministic, client/server code-driven, and undoable:

| Tool ID | Label | Deterministic Behavior |
| :--- | :--- | :--- |
| `format-code` | **Format & Beautify** | Re-indents HTML and places block elements on clean lines while verifying via hidden render (`az`) that layout geometry does not shift. |
| `compress-minify` | **Compress & Minify** | Strips HTML comments and collapses redundant whitespace without altering rendered layout. |
| `clean-empty-tags` | **Clean Up Empty Tags** | Removes empty attribute-less tags (`<div></div>`, `<span></span>`, `<p></p>`) unless removing them alters element bounding boxes (spacers). |
| `purge-unused-css` | **Purge Unused CSS** | Parses stylesheets and removes CSS rules whose classes/IDs never appear in any HTML page or `.js` script (`0.h1ya389337y.js`). |
| `extract-inline-css` | **Extract Inline CSS** | Moves `<style>` blocks into linked `.css` files (`style.css` for shared rules, per-page `.css` for page-specific rules). |
| `extract-inline-js` | **Extract Inline JS** | Moves inline `<script>` blocks into external `.js` files while keeping order-dependent inline scripts untouched. |
| `convert-monolith` | **Convert to Monolith** | Inlines linked project `.css` and `.js` files back into each HTML page for single-file portability. |
| `extract-inline-images` | **Extract Inline Images** | Converts embedded `data:image/*;base64,...` URIs into deduplicated binary files under `/assets/images/` (SHA-256 hashed). |
| `download-remote-images` | **Download Remote Images** | Downloads hotlinked external images into `/assets/images/` and rewrites `src`, `srcset`, and CSS `url()` references. |
| `upgrade-webp` | **Upgrade Images to WebP** | Converts project PNG/JPG assets to WebP in-browser via `<canvas>` (`createImageBitmap` + `r.toBlob(..., "image/webp")`) when smaller, updating all references. |
| `localize-fonts` | **Localize External Fonts** | Downloads Google Fonts, Bunny Fonts, Fontshare, and CDNFonts `.woff2` files into `/assets/fonts/` and rewrites `@font-face` rules for zero third-party requests. |
| `inject-seo-boilerplate` | **Add Missing SEO Tags** | Generates missing `<title>`, `<meta name="description">`, Open Graph (`og:*`), Twitter Card, and `<link rel="canonical">` tags deterministically from each page's `<h1>`, first `<p>`, and hero `<img>`. |
| `generate-sitemap` | **Generate Sitemap & Robots** | Builds `sitemap.xml` from all non-404, non-`noindex` pages and creates/updates `robots.txt`. |
| `normalize-links` | **Normalize & Fix Links** | Rewrites `.html` links (`about.html`, `../index.html`) to clean URLs (`/about`, `/`) and reports any broken internal links. |
| `force-https` | **Force HTTPS Links** | Upgrades `http://` image, script, stylesheet, iframe, and media URLs to `https://`. |

---

## 9. Keyboard Shortcuts (`130vygiferm._.js`), Version History & Forms

### 9.1. Elementor-Compatible Keyboard Shortcuts
| Action | Mac Shortcut | Windows / Linux | Context |
| :--- | :--- | :--- | :--- |
| **Save / Publish** | `⌘ S` | `Ctrl + S` | Global (including Code editor) |
| **Undo / Redo** | `⌘ Z` / `⇧ ⌘ Z` | `Ctrl + Z` / `Ctrl + Y` | Global (canvas history vs. text field) |
| **Edit Mode On / Off** | `⇧ ⌘ E` | `Ctrl + Shift + E` | Toggles interactive preview vs. element selection |
| **Structure Panel** | `⌘ I` | `Ctrl + I` | Opens DOM Structure tree (Elementor Navigator) |
| **Component Library** | `⇧ ⌘ L` | `Ctrl + Shift + L` | Opens Sections & Elements Library |
| **Show / Hide Side Panel** | `⌘ P` | `Ctrl + P` | Collapses or expands left panel |
| **Switch Device Viewport** | `⇧ ⌘ M` | `Ctrl + Shift + M` | Cycles Desktop -> Tablet -> Phone |
| **Version History** | `⇧ ⌘ H` | `Ctrl + Shift + H` | Opens Version History & DiffViewer |
| **Finder (Tools & Pages)** | `⇧ ⌘ F` | `Ctrl + Shift + F` | Quick command palette for tools, pages, and files |
| **Switch Page** | `⌘ E` | `Ctrl + E` | Opens page switcher |
| **Fullscreen Preview** | `⇧ ⌘ Enter` | `Ctrl + Shift + Enter` | Toggles distraction-free preview |
| **Search in Project** | `⇧ ⌘ P` | `Ctrl + Shift + P` | Multi-file text search |
| **Keyboard Shortcuts Help** | `⌘ /` or `?` | `Ctrl + /` or `?` | Opens Keyboard Shortcuts modal |
| **Copy / Cut / Paste Element** | `⌘ C` / `⌘ X` / `⌘ V` | `Ctrl + C` / `X` / `V` | Selected element in preview |
| **Paste Style** | `⇧ ⌘ V` | `Ctrl + Shift + V` | Copies classes & inline styles to selected element |
| **Duplicate Element** | `⌘ D` | `Ctrl + D` | Selected element in preview |
| **Delete Element** | `Delete` / `⌫` | `Delete` / `Backspace` | Selected element in preview |
| **Move Element Up / Down** | `⌥ ↑` / `⌥ ↓` | `Alt + ↑` / `Alt + ↓` | Reorders element among siblings |
| **Select Parent Element** | `⌥ ←` | `Alt + ←` | Climbs one level up the DOM tree |
| **Edit Text in Place** | `Enter` or `Double-click` | `Enter` or `Double-click` | Activates `contenteditable` on text elements |

### 9.2. Version History & DiffViewer (`0-8xiie3~s09k.js`)
- `VersionsPanel` lists every published version (`GET /api/sites/{slug}/versions`), showing timestamp, author, version label, and changed files (`added`, `modified`, `removed`).
- `DiffViewer` (`compareVersions`) renders side-by-side or unified line-by-line diffs between any historical version and the current working copy or live site.
- **Edit as Draft** loads an older version into the editor without publishing; **Restore** (`POST /api/sites/{slug}/versions/{n}/restore`) republishes that snapshot as a new version (`vN+1`).

### 9.3. Zero-Backend Form Engine Integration (`/__pd/form`)
- Forms point to `POST /__pd/form` (with legacy `/__hh/form` compatibility in `src/Router.php`).
- Hidden control inputs: `_form` (inbox grouping name), `_success` (inline confirmation text), `_redirect` (same-site path starting with `/`), `_error` (custom failure message), and `_pd_hp` (auto-injected honeypot spam trap).
- Inline confirmation message renders inside `<p data-pd-form-msg data-state="success|error">`.

---

## 10. PageDock Native Implementation Blueprint (Zero-AI)

| Editor Subsystem | Source Artifact Reference | PageDock Target File(s) |
| :--- | :--- | :--- |
| **Injected Preview Harness** | `0-8xiie3~s09k.js` (`injectAtHeadStart`) + `0a476qra.ffal.js` (87.6 KB inspector script) | `public/assets/js/pagedock-preview-harness.js` |
| **Direct Element Edit Popover** | `0a476qra.ffal.js` (`374000`–`392000`) | `views/editor.php` & `public/assets/js/editor.js` |
| **Right-Click Context Menu** | `0a476qra.ffal.js` (`699000`–`708500`) | `public/assets/js/editor.js` |
| **Component Library (46 Sections + 48 Elements)** | `0t89xnml0ly_9.js` (`ComponentLibrary`, `LIBRARY_ELEMENTS`, `ICONS`) | `public/assets/js/editor-library.js` |
| **Structure Tree (`Navigator`)** | `0a476qra.ffal.js` (`102000`–`106000`) | `public/assets/js/editor.js` |
| **Shared Header/Footer/Cookie Engine** | `0.h1ya389337y.js` (`SHARED_KINDS`, `refreshSharedBlocks`) | `src/SharedLayoutManager.php` & `public/assets/js/editor.js` |
| **15 Deterministic Developer Tools** | `0a476qra.ffal.js` (`273000`–`286000`) & `0.h1ya389337y.js` | `public/assets/js/editor-tools.js` |
| **Keyboard Shortcuts & Finder** | `130vygiferm._.js` (`SHORTCUTS`, `SHORTCUT_GROUPS`) | `public/assets/js/editor.js` |
| **Version History & DiffViewer** | `0-8xiie3~s09k.js` (`VersionsPanel`, `DiffViewer`) | `src/VersionManager.php` & `views/editor.php` |
| **Media Library & Asset Picker** | `0-8xiie3~s09k.js` (`MediaLibrary`) & `01yg_h3ezghnr.js` | `src/Router.php` (`/api/media`) & `public/assets/js/editor.js` |
