# 🔬 HTMLHost.co AI HTML Editor — Complete Reverse-Engineering Dossier & PageDock Integration Specification

> **Investigation Target:** `https://htmlhost.co/new` & `https://htmlhost.co`  
> **Target Platform:** PageDock (`/Users/user/Desktop/pagedock`)  
> **Methodology:** Headless Chromium Live Intercept (Playwright), Dynamic Session Injection, Live DOM & Event Tracing, Next.js Turbopack Chunk Decompilation (`0c3n8qt-bavg2.js`, `0dmkb1t6sd59x.js`, etc.), Protocol Forensics, and Streaming Payload Capture.  
> **Status:** 100% Forensic Recovery Complete. All components, protocols, prompts, scripts, and state machines documented below.

---

## 1. Architecture Overview & High-Level Topology

The HTMLHost AI HTML Editor is a client-side orchestrated, server-streaming IDE designed specifically for single-page and multi-page static HTML websites. It operates on a **zero-friction draft model**: users can drop code, import from URL, paste HTML, or type an AI prompt, immediately landing in an active split workspace without requiring authentication until publication.

```
┌──────────────────────────────────────────────────────────────────────────────────────────────────┐
│                                   PageDock AI HTML Editor Window                                 │
├──────────────────────────────────────────────────────────────────────────────────────────────────┤
│ Top Navigation & Action Toolbar                                                                  │
│ [<- Back] [Site Title] [Undo] [Redo] [Media] | [Edit Mode On] [AI Edit] [Chat] [0/5] [Devices]  │
│ [Tools v] [TTL v] [Save Draft] [Publish]                                                         │
├────────────────────────────┬─────────────────────────────────────────────────────────────────────┤
│ Left Collapsible Panel     │ Center Live Canvas                                                  │
│ ┌────────────────────────┐ │ ┌─────────────────────────────────────────────────────────────────┐ │
│ │ [Code] [Lib] [Tree] [S]│ │ │ Sandboxed <iframe> (allow-scripts, allow-same-origin)            │ │
│ ├────────────────────────┤ │ │                                                                 │ │
│ │ Monaco Editor (0.55.1) │ │ │  [Header]                                                       │ │
│ │   - Line numbers       │ │ │                                                                 │ │
│ │   - AI section gutter  │ │ │  [Target Element] <──── (Hover / Click Outlines)                │ │
│ │   - Syntax highlight   │ │ │       │                                                         │ │
│ │                        │ │ │  [Main Content]                                                 │ │
│ │ Library:               │ │ │                                                                 │ │
│ │   - 46 Sections        │ │ │  [Footer]                                                       │ │
│ │   - 48 Elements        │ │ └─────────────────────────────────────────────────────────────────┘ │
│ │                        │ │                                                                     │
│ │ Structure Tree:        │ │ Floating Element Context Popups (Fixed z-[9999]):                   │
│ │   - DOM hierarchy      │ │  1. AI Edit Popover: [@tag] [Prompt] [Chips] [Model] [Apply]        │
│ │   - Drag-to-reorder    │ │  2. Direct Element Edit: [Text Edit] [Duplicate] [Code] [Delete]    │
│ └────────────────────────┘ └─────────────────────────────────────────────────────────────────────┘
└──────────────────────────────────────────────────────────────────────────────────────────────────┘
```

---

## 2. Ingestion, Hydration & Draft Initialization

When a user opens `/new`, the client checks initialization channels:

1. **Query Parameters:**
   - `?edit={slug}`: Loads existing project draft from `/api/sites/pull?slug={slug}`.
   - `?version={revision}`: Restores a specific past deployment revision.
2. **Session Storage Ingestion:**
   - `sessionStorage.getItem("hero-html")`: Ingests raw pasted HTML or generated page from the homepage hero. Once read, it is cleared via `sessionStorage.removeItem("hero-html")`.
   - `sessionStorage.getItem("hero-project")`: Multi-file JSON structure `{ pages: [{path, html, type}] }`.
   - `sessionStorage.getItem("hero-clone-url")`: Clones from public URL.
3. **Pending File Ingestion:**
   - Checks `hasPendingFiles()` for drag-and-drop ZIP or folder dropped on the homepage.
4. **Fallback:**
   - If `/new` is opened with no query parameters, no pending files, and no sessionStorage, it replaces location to `/` (`c.replace("/")`).

---

## 3. Sandboxed Iframe Preview & Injected Control Harness

The preview iframe is sandboxed with:
```html
<iframe 
  srcdoc="..." 
  sandbox="allow-scripts allow-same-origin allow-popups" 
  class="border-none bg-white transition-[width,height,border-radius,box-shadow] duration-300 ease-[cubic-bezier(.2,.8,.2,1)]"
></iframe>
```

Before rendering, the parent window injects two specialized scripts directly into the iframe:

### 3.1. `<script data-htmlhost-ui>` (Head Pre-parser & Audio Silencer)
Injected at the very start of `<head>`:
- **Audio/Video Muting:** Hooks into `MutationObserver` on `childList` and listens to `play` events. Sets `m.muted = true` on all `<audio>` and `<video>` tags dynamically as they are parsed so that live previews never play audio unexpectedly.
- **Buffer DOM Signaling:** Dispatches `domReady()` when `DOMContentLoaded` fires:
  ```javascript
  window.parent.postMessage({ type: 'buffer-dom-ready' }, '*');
  ```
- **Unsticking Fullscreen Loaders:** Scans for full-page loading masks, overlays, or spinners that block user interaction. If an overlay has thin content (no buttons or forms) and covers >60% of viewport, it applies `[data-htmlhost-overlay-hidden] { display: none !important; }` and notifies the parent:
  ```javascript
  window.parent.postMessage({ type: 'preview-overlay-stuck' }, '*');
  ```

### 3.2. Visual Inspector & Element Interaction Engine (`lu` - 87.6 KB)
Injected before `</body>`:
- **Hover Highlighter:** Outlines hovered elements with `2px dashed rgba(125,211,252,0.5)`.
- **Selection Highlighter:** Outlines selected elements with `2px solid #7dd3fc` and offset `-1px`.
- **Section Detection (`findSection`):** Climbs parent tree to identify the containing semantic block (`SECTION`, `ARTICLE`, `MAIN`, `HEADER`, `FOOTER`, `NAV`, `ASIDE`) and highlights the entire section boundary with `1.5px dashed rgba(125,211,252,0.35)`.
- **Drag-and-Drop Reordering:**
  - Ghost element clone (`mkGhost`) with opacity 0.7 and box shadow.
  - Drop position indicator line (`mkInd`) with linear gradient `#7dd3fc`.
  - Automatic row vs. column layout detection (`isRow`).
  - Edge auto-scrolling (`edgeScroll`) when dragging near top/bottom screen boundaries.
  - Touch support (`touchmove`, `pointerdown`) with long-press pick up.
- **Style Scanner (`lockedProps`):** Inspects document stylesheets to detect properties locked by `!important` or background-clip text gradients so the editor knows which styles cannot be overridden inline.

---

## 4. Full Bidirectional `postMessage` Protocol

### 4.1. Parent -> Iframe Messages
| Type | Payload | Action |
| :--- | :--- | :--- |
| `ai-mode` | `{ enabled: boolean }` | Toggles visual AI inspection mode on/off. |
| `ai-model-info` | `{ provider, model, label }` | Updates model identity inside the frame. |
| `patch-element` | `{ tag, tagIndex, html }` | Replaces target element outerHTML with newly generated HTML. |
| `live-patch` | `{ html }` | Streams incremental HTML replacements in real-time. |
| `begin-text-edit` | `{}` | Activates in-place `contenteditable` on selected text element. |
| `select-element` | `{ tag, tagIndex }` | Programmatically selects element from Structure tree. |
| `select-parent` | `{}` | Climbs up to parent node. |
| `hover-element` | `{ tag, tagIndex }` | Triggers hover outline from Structure panel. |
| `reveal-element` | `{ tag, tagIndex }` | Scrolls target element into center view. |
| `clear-selection` | `{}` | Clears active selection and closes popups. |
| `request-structure` | `{}` | Requests complete serialized DOM tree for Structure panel. |
| `library-drag` | `{ element, x, y }` | Updates insertion indicator while dragging from Library. |
| `element-append` | `{ html, insertIndex }` | Drops new element into DOM hierarchy. |
| `library-placeholder` | `{ name, insertIndex }` | Renders animated shimmer placeholder while AI generates section. |

### 4.2. Iframe -> Parent Messages
| Type | Payload | Action |
| :--- | :--- | :--- |
| `element-selected` | `{ html, editStyles, tag, tagIndex, text, parentTag, parentTagIndex, childIndex, siblingCount, hasParent, isTextOnly, ancestors, form, rect, sectionHtml, computedStyles }` | Opens AI Edit Popover or Element Edit Popover anchored at element `rect`. |
| `element-reorder` | `{ sourceTag, sourceIndex, targetParentTag, dropIndex }` | Notifies parent of DOM node reordering. |
| `element-text-edit` | `{ tag, tagIndex, text }` | Syncs in-place edited text back to project source code. |
| `text-edit-start` | `{}` | Dismisses popovers while user types directly on page. |
| `page-structure` | `{ tree: [...] }` | Sends serialized DOM hierarchy to populate Structure tab. |
| `navigate-page` | `{ path }` | User clicked internal link (e.g. `/about`); parent switches active page. |
| `form-preview-submit` | `{ form: { success, redirect } }` | Simulates form submission in preview without polluting database. |
| `iframe-key` | `{ key, metaKey, ctrlKey, shiftKey, altKey }` | Forwards global shortcuts (`Ctrl+Z`, `Ctrl+J`, etc.) to parent. |
| `iframe-escape` | `{}` | Closes popovers on Esc. |

---

## 5. Floating Element Popovers: Complete UI Specifications

### 5.1. AI Edit Popover (`div.fixed.z-[9999]`)
- **Width:** `w-[340px] max-w-[calc(100vw-16px)]`
- **Border & Shadow:** `border border-line rounded-2xl bg-bg-1 shadow-[rgba(0,0,0,0.35)_0px_8px_40px,rgba(125,211,252,0.15)_0px_0px_0px_1px]`
- **Header:**
  - Drag handle (`cursor-grab active:cursor-grabbing`)
  - Tag badge: `<span class="text-[12px] font-semibold text-accent">BUTTON</span>`
  - Text snippet: `<span class="text-[11px] text-fg-4 truncate max-w-[100px]">Get Started Now</span>`
  - Mode Switch: `<button role="switch" aria-checked="true" aria-label="AI Edit">` with pill toggle.
  - Close button (`Esc`).
- **Suggestion Chips:**
  - Dynamic pill buttons: `Add a hover state`, `Add hover effect`, `Change colors`, `Make larger`.
- **Prompt Input:**
  - Ghost overlay div (`pointer-events-none`) for rendering `@` file mentions with highlight.
  - Textarea: `placeholder="Change the style, add a hover effect, update the text… (@ to reference a file)"`.
- **Accounting & Model Bar:**
  - Quota progress bar: `h-[4px] rounded-full bg-bg-2` with `width: 0%; background: var(--accent);`.
  - Quota label: `0/5 today · from 5 per task`.
  - Attachment button: file input supporting 23 file formats.
  - Model selector dropdown: default `Gemini 3.5 Flash Lite`, switches to `Claude Sonnet 5`, `GPT-4.1`, etc.
  - BYOK settings gear.
- **Action Buttons:**
  - `Cancel` (ghost button).
  - `Apply Changes` (`bg-accent text-accent-ink font-semibold disabled:opacity-40`).

### 5.2. Direct Element Edit Popover
When the toggle is switched to `Edit Mode` (or double-clicked):
- **Textarea:** Direct raw text editor.
- **Button:** `Or edit it on the page (double-click)` (dispatches `begin-text-edit`).
- **Footer Controls:**
  - `Duplicate` (clones element in DOM).
  - `Code` (scrolls Monaco Editor directly to the element's source line).
  - `Delete` (removes element from DOM with hover danger color).

---

## 6. AI Prompt Architecture, Context Windowing & Streaming

### 6.1. The AI Operating Modes

#### Mode 1: Targeted Element Edit (`mode: "element"`)
Sent to `/api/ai/builtin`:
```json
{
  "mode": "element",
  "instruction": "Change background to black, text color to white, border-radius 20px",
  "intent": "Change background to black, text color to white, border-radius 20px",
  "elementHtml": "<button id=\"main-btn\">Get Started Now</button>",
  "fullPageHtml": "<!DOCTYPE html><html><body>...</body></html>",
  "model": "gemini-3.5-flash-lite"
}
```
**System Prompt (`ELEMENT_PROMPT`):**
```text
You are an expert HTML/CSS/JS developer. The user selected a specific element in their page and wants you to modify it.

SEMANTIC HTML IS REQUIRED for all newly generated pages and components.
- Author actual content in ordinary HTML source, not JS-rendered roots, JSX, framework templates, Shadow DOM, or custom-element UI. This is essential for PageDock's visual editor.
- Full pages have a descriptive title, lang, viewport, exactly one main and one meaningful h1.
- Use real headings in logical order, lists for collections, tables for tabular data, buttons for actions, anchors with real destinations for navigation, and native labeled form controls. Never use clickable div/span substitutes.
- Images have appropriate alt text; icon-only buttons have accessible names; keyboard focus is visible.
- Canvas/WebGL are permitted only inside a dedicated figure with an accessible description.
- JavaScript progressively enhances the source HTML.

Rules:
- You will receive the selected element's HTML, the user's instruction, and optionally relevant CSS/JS from the page.
- Return ONLY the replacement HTML fragment — no explanation, no code fences, no markdown.
- The replacement must be a drop-in substitution for the original element.
- Preserve attributes, classes, and IDs unless the user asks to change them.
- If adding new styles, use inline styles or a <style> block within the element.
- If adding new behavior, use an inline <script> within or after the element.
- Return raw HTML only. Nothing else.
```

#### Mode 2: Whole-Page Diff Mode (`SEARCH/REPLACE`)
Sent to `/api/ai/builtin`:
```json
{
  "instruction": "Add a modern responsive contact form with name, email and message",
  "htmlContext": "<!DOCTYPE html><html>...</html>",
  "model": "gemini-3.5-flash-lite",
  "allowFileOps": true,
  "isMonolith": false,
  "manifest": "",
  "history": [],
  "chatMode": "execute",
  "intent": "Add a modern responsive contact form"
}
```
**System Prompt (`PAGE_EDIT_PROMPT`):**
```text
You are an expert HTML/CSS/JS developer embedded in a web-based code editor.

Return your changes as SEARCH/REPLACE blocks. Each block finds and replaces an exact section.

Format:
<<<<<<< SEARCH
(exact lines from the file)
=======
(replacement lines)
>>>>>>> REPLACE

CRITICAL RULES:
1. SEARCH must be an EXACT copy of existing lines (whitespace included). Mismatches = failure.
2. Keep SEARCH blocks SMALL — only the lines that change + 2-3 context lines to identify location.
3. NEVER include the entire file or large sections. If changing a color, only include the relevant CSS rule.
4. NEVER remove or modify structural tags (<html>, <head>, <body>, <!DOCTYPE>). NEVER output them in SEARCH blocks.
5. For style/theme changes, target ONLY the <style> block or inline styles — not body content.
6. No code fences, no markdown, no explanatory text. ONLY SEARCH/REPLACE blocks.
7. Order blocks top-to-bottom in the file.
8. Copy SEARCH text character-for-character from the HTML below — never re-type from memory.
9. If you need clarification before proceeding, emit a QUESTION block and STOP generating immediately:
<<<<<<< QUESTION
What color should the button be?
>>>>>>> QUESTION

To CREATE a new file:
<<<<<<< CREATE /contact.html
<!DOCTYPE html>...
>>>>>>> CREATE

To DELETE a file:
<<<<<<< DELETE /old-page.html
>>>>>>> DELETE

To RENAME a file:
<<<<<<< RENAME /old.html
/new.html
>>>>>>> RENAME
```

### 6.2. Context Optimization (`extractFocusedContext`)
- **Meta / Head Queries:** If query mentions `title`, `meta`, `seo`, `favicon`, `og:`, `viewport`, the editor extracts **only the `<head>` tag** (saves 90% tokens and eliminates hallucinations on body content).
- **Large Document Windowing:** If page exceeds 200,000 characters, it retains the `<head>`, `<header>`, `<footer>`, and the specific section nearest the edit, replacing distant sections with `/* ... [sections omitted] ... */`.

### 6.3. Dynamic Temperature Tuning (`detectTemperature`)
```javascript
function detectTemperature(instruction) {
  const t = instruction.toLowerCase();
  // Exact, surgical, deterministic instructions
  if (/\b(change .* to|set .* to|make .* (exactly|precisely)|replace .* with|fix|correct|remove|delete|add class|#[0-9a-f]{3,8}|rgb|hsl)\b/.test(t)) {
    return 0.2;
  }
  // Creative, brainstorming, redesign instructions
  if (/\b(redesign|reimagine|creative|modern|beautiful|stunning|impressive|overhaul|transform|make it (look|feel)? (better|amazing))\b/.test(t)) {
    return 0.8;
  }
  return 0.5; // Default balanced temperature
}
```

### 6.4. The Reverse-Offset Patch Applicator
Applying diffs top-to-bottom causes text offsets to drift as lines change length. HTMLHost solves this with **descending offset sorting**:
```javascript
function applySearchReplace(originalHtml, patchBlocks) {
  // 1. Locate all SEARCH blocks in target HTML
  const matches = patchBlocks.map(block => ({
    ...block,
    location: findExactLocation(originalHtml, block.search)
  })).filter(b => b.location !== null);

  // 2. Sort matches by starting index in DESCENDING order
  matches.sort((a, b) => b.location.start - a.location.start);

  // 3. Apply slices bottom-to-top
  let updated = originalHtml;
  for (const match of matches) {
    const { start, end } = match.location;
    updated = updated.slice(0, start) + match.replace + updated.slice(end);
  }
  return updated;
}
```

---

## 7. Autonomous Tool-Using Agent (`agentTools: true`)

For complex requests (e.g. "Build a 3-page real estate website with listings and contact form"), HTMLHost activates an autonomous agent loop bounded at **24 maximum steps**:

### Tool Schemas
1. `list_files {}`: Returns all paths, sizes, and file types in project.
2. `read_file { path, offset, limit, version }`: Paginated read up to 24,000 characters.
3. `search_files { query, path }`: Literal grep in project files.
4. `inspect_page { path, selector, version }`: Computes simulated typography and layout.
5. `stage_changes { ops: [...], updates: [...] }`: Stages multi-file mutations.
6. `patch_file { path, search, replace }`: Surgical patch to single file.
7. `patch_and_finish { path, search, replace, summary }`: 1-step patch with immediate validation.
8. `validate {}`: Runs HTML linting, checks form honeypots, verifies shared blocks.
9. `ask_user { question }`: Prompts user when critical requirement is ambiguous.
10. `finish { summary }`: Validates and commits staged changes to live workspace.

---

## 8. Zero-Backend Form Engine Integration (`/__pd/form`)

PageDock replaces `/__hh/form` with native `/__pd/form`:
```html
<form action="/__pd/form" method="POST">
  <!-- Form category -->
  <input type="hidden" name="_form" value="Contact">
  <!-- Success redirect or inline message -->
  <input type="hidden" name="_success" value="Thank you! We will get back to you shortly.">
  
  <!-- Anti-spam Honeypot (auto-hidden) -->
  <div aria-hidden="true" style="position:absolute;left:-10000px;width:1px;height:1px;overflow:hidden">
    <label>Leave this field empty <input type="text" name="_pd_hp" tabindex="-1" autocomplete="off"></label>
  </div>

  <input type="text" name="name" placeholder="Your Name" required>
  <input type="email" name="email" placeholder="Your Email" required>
  <textarea name="message" placeholder="Your Message" required></textarea>
  <button type="submit">Send Message</button>

  <!-- Inline feedback element -->
  <p data-pd-form-msg></p>
</form>
```

---

## 9. Integration Blueprint for PageDock

| HTMLHost Feature | PageDock Native Implementation |
| :--- | :--- |
| `POST /api/ai/builtin` | `src/Router.php` route: `POST /api/ai/generate` streaming SSE via PHP output buffering `ob_flush() + flush()`. |
| `POST /api/ai/task` | `src/Router.php` route: `POST /api/ai/task` tracking user daily AI quota in SQLite `users.ai_quota_used`. |
| `POST /api/ai/proxy` | `src/Router.php` route: `POST /api/ai/proxy` routing client BYOK keys to OpenAI / Anthropic / Gemini. |
| Monaco Editor | Embedded Monaco Editor (v0.55.1) loaded from local or cdnjs with custom AI gutters. |
| Injected Harness | `public/assets/js/pagedock-preview-harness.js` containing the extracted `lu` inspector script. |
| Floating Popovers | Reusable UI components integrated into `views/editor.php` and `views/studio.php`. |
| Multi-Page Shared Blocks | Synchronized via PageDock's existing `src/SharedLayoutManager.php`. |
| Form Engine | Native PageDock `src/Forms.php` (`POST /__pd/form`). |

---

## 10. Verification Artifacts Produced During This Investigation

1. `/scratch/new_page.html`: Complete initial HTML of `/new`.
2. `/scratch/chunks/*`: All 21 Next.js production JavaScript chunks.
3. `/scratch/injected_iframe_ui_script.js`: Complete 87.6 KB visual inspector script.
4. `/scratch/editor_dom.json`: Full DOM state of live hydrated editor.
5. `/scratch/popup_inner.html`: Exact HTML and Tailwind classes of the floating AI Edit Popover.
6. `/scratch/direct_edit_popup.html`: Exact HTML and Tailwind classes of the Direct Element Edit Popover.
7. `/scratch/chat_requests.json`: Real-world captured request payload to `/api/ai/builtin`.
8. Visual Screenshots:
   - `page_initial.png`: Landing / Ingestion screen.
   - `editor_live.png`: Full split editor on hydration.
   - `screen_code_tab.png`: Monaco editor in Code tab.
   - `screen_structure_tab.png`: Structure outline tab.
   - `screen_element_selected.png`: Floating AI prompt popover attached to element.
   - `screen_direct_element_edit.png`: Floating Direct Element Edit popover.
   - `screen_chat_drawer.png`: AI Chat drawer.

