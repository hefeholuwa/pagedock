# Multi-Page Site Management & Smart Page Creation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Enable seamless multi-page static site management in PageDock with an always-visible page switcher, "+ Add Page" scaffolding modal, backend layout inheritance (shared headers/footers), and preview canvas missing-page auto-create interception.

**Architecture:** Extend `SiteManager` and `SharedLayoutManager` with smart layout-inheriting page scaffolding that copies `<head>` assets, shared headers/footers, and tailors active link states. Expose REST endpoints in `Router.php` (`/api/sites/{slug}/pages`). Update the editor topbar to always show the page switcher with a "+ Page" modal, and wire `pagedock-harness.js` to detect clicks on non-existent pages, prompting the user to 1-click create them on the fly.

**Tech Stack:** PHP 8+, JavaScript (ES6), HTML5/CSS3, Node.js test harness.

---

### Task 1: Backend Page Creation & Layout Scaffolding Engine

**Files:**
- Modify: `src/SiteManager.php`
- Test: `tests/test_backend_page_scaffolding.php`

- [ ] **Step 1: Write the failing test for `SiteManager::createPage`**

Create `tests/test_backend_page_scaffolding.php` testing `createPage()`:
1. Creating a page with `layout: 'inherit'` extracts head, shared header, shared footer from `index.html` and creates `about.html`.
2. Validates filename sanitization (adds `.html` if omitted, prevents directory traversal).
3. Creating with `layout: 'clone'` makes a full copy.
4. Prevents overwriting existing pages unless explicitly requested.

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/test_backend_page_scaffolding.php`
Expected: FAIL with "Call to undefined method SiteManager::createPage()"

- [ ] **Step 3: Implement `SiteManager::createPage()` in `src/SiteManager.php`**

Add `createPage(string $slug, string $filename, string $title, string $layout = 'inherit', string $sourceFile = 'index.html'): array`:
- Sanitize filename: lowercased, only `a-z0-9_-`, ends in `.html`.
- If already exists, return `['success' => false, 'error' => 'Page already exists']`.
- Scaffolding logic:
  - When `inherit`: Read source HTML (`index.html`). Extract `<head>...</head>`, update `<title>`, extract `<!-- hh:shared header -->...<!-- /hh:shared header -->` (and `pd:shared`), extract `<!-- hh:shared footer -->...<!-- /hh:shared footer -->`.
  - Assemble:
    ```html
    <!DOCTYPE html>
    <html lang="en">
    <head>
      [Extracted head with new title]
    </head>
    <body [extracted body classes/attributes]>
      [Shared header tailored for new route via SharedLayoutManager::tailorBlockForPage]
      <main id="main" class="container">
        <section class="py-16">
          <h1>[Title]</h1>
          <p>Welcome to the [Title] page.</p>
        </section>
      </main>
      [Shared footer]
    </body>
    </html>
    ```
  - When `clone`: Copy source file content directly.
  - When `blank`: Minimal HTML5 skeleton.
- Write file to `sites/{slug}/{filename}`.
- Auto-create snapshot and return `{ success: true, file: filename, path: relPath }`.

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/test_backend_page_scaffolding.php`
Expected: PASS with all assertions successful.

- [ ] **Step 5: Commit**

```bash
git add src/SiteManager.php tests/test_backend_page_scaffolding.php
git commit -m "feat(backend): add smart page creation and layout scaffolding engine"
```

---

### Task 2: Backend REST API Endpoints for Pages Management

**Files:**
- Modify: `src/Router.php`
- Test: `tests/test_api_pages_management.php`

- [ ] **Step 1: Write the failing test for `/api/sites/{slug}/pages`**

Create `tests/test_api_pages_management.php` testing:
1. `GET /api/sites/{slug}/pages` returns list of all HTML pages with filename, route, size, isEntry.
2. `POST /api/sites/{slug}/pages` creates a new page and returns 200 with new page info.
3. `POST /api/sites/{slug}/pages/delete` deletes a secondary page, but forbids deleting `entry_file` (`index.html`).

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/test_api_pages_management.php`
Expected: FAIL (routes not recognized / 404).

- [ ] **Step 3: Implement API routes in `src/Router.php`**

Add routes in `src/Router.php`:
1. `GET /api/sites/{slug}/pages` (authenticated/canManage): Calls `SharedLayoutManager::findAllHtmlFiles($slug)` and maps to metadata objects.
2. `POST /api/sites/{slug}/pages` (authenticated/canManage): Parses JSON or POST payload (`title`, `filename`, `layout`, `sourceFile`), calls `SiteManager::createPage($slug, $filename, $title, $layout, $sourceFile)`.
3. `POST /api/sites/{slug}/pages/delete` (authenticated/canManage): Deletes specified page if not entry file.

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/test_api_pages_management.php`
Expected: PASS with all tests passing.

- [ ] **Step 5: Commit**

```bash
git add src/Router.php tests/test_api_pages_management.php
git commit -m "feat(api): add pages list, create, and delete endpoints"
```

---

### Task 3: Always-Visible Page Switcher & "+ Add Page" Modal in Editor UI

**Files:**
- Modify: `views/editor.php`
- Modify: `public/assets/css/editor.css`
- Test: `tests/test_editor_pages_ui.js`

- [ ] **Step 1: Write the failing test for editor pages UI**

Create `tests/test_editor_pages_ui.js` testing:
1. `#hh-page-select` is rendered in `views/editor.php` unconditionally (even when `count($htmlFiles) === 1`).
2. Topbar contains a dedicated "+ Page" button (`#hh-add-page-btn`).
3. Modal `#hh-add-page-modal` exists with inputs for Title (`#hh-new-page-title`), Filename (`#hh-new-page-filename`), Layout select (`#hh-new-page-layout`), and submit button (`#hh-create-page-submit`).
4. `PAGEDOCK_CONFIG.pagesUrl` and `PAGEDOCK_CONFIG.htmlFiles` are exposed.

- [ ] **Step 2: Run test to verify it fails**

Run: `node tests/test_editor_pages_ui.js`
Expected: FAIL with missing elements.

- [ ] **Step 3: Implement UI elements in `views/editor.php` and `editor.css`**

- In `views/editor.php`:
  - Update topbar to render `.hh-page-group` with `#hh-page-select` and `#hh-add-page-btn` always.
  - Add `#hh-add-page-modal` with clean PageDock dark glassmorphic styling:
    - Page Title input (`#hh-new-page-title`)
    - Filename input with prefix (`/`) and suffix (`.html`) (`#hh-new-page-filename`)
    - Layout radio/select options:
      - `inherit`: Inherit Site Layout (Shared Header, Footer & Styles)
      - `clone`: Clone Current Page
      - `blank`: Blank HTML5 Canvas
    - Submit (`#hh-create-page-submit`) & Cancel buttons.
  - Pass `pagesUrl` and `htmlFiles` in `window.PAGEDOCK_CONFIG`.
- In `public/assets/css/editor.css`:
  - Add styling for `.hh-page-group`, `.hh-btn-add-page`, and modal layout.

- [ ] **Step 4: Run test to verify it passes**

Run: `node tests/test_editor_pages_ui.js`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add views/editor.php public/assets/css/editor.css tests/test_editor_pages_ui.js
git commit -m "feat(editor): always-visible page switcher and add page modal markup"
```

---

### Task 4: Editor Client Controller & Auto-Slugify Modal Handling

**Files:**
- Modify: `public/assets/js/editor.js`
- Test: `tests/test_editor_add_page_controller.js`

- [ ] **Step 1: Write the failing test for page creation controller**

Create `tests/test_editor_add_page_controller.js` verifying:
1. Clicking `#hh-add-page-btn` or choosing `__new__` in `#hh-page-select` opens `#hh-add-page-modal`.
2. Typing in title input auto-slugifies into filename input (e.g. "About Us" -> "about-us.html").
3. Form submission calls `POST /api/sites/{slug}/pages`.
4. On success, updates select options and switches editor to the newly created page.

- [ ] **Step 2: Run test to verify it fails**

Run: `node tests/test_editor_add_page_controller.js`
Expected: FAIL.

- [ ] **Step 3: Implement controller in `public/assets/js/editor.js`**

- Wire up `#hh-add-page-btn` and `hh-page-select` `__new__` option.
- Implement `openAddPageModal(prefillTitle, prefillFilename)`:
  - Resets inputs, sets focus.
- Add `input` listener on `#hh-new-page-title` to auto-populate `#hh-new-page-filename` if user hasn't manually edited it.
- Wire up form submit:
  - Validates filename.
  - Sends `POST` to `window.PAGEDOCK_CONFIG.pagesUrl`.
  - Shows success toast.
  - Calls `switchPage(res.file)`.

- [ ] **Step 4: Run test to verify it passes**

Run: `node tests/test_editor_add_page_controller.js`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add public/assets/js/editor.js tests/test_editor_add_page_controller.js
git commit -m "feat(editor): add page modal controller with auto-slugify and api submission"
```

---

### Task 5: Canvas Missing-Page Link Interception & 1-Click Auto-Create Prompt

**Files:**
- Modify: `public/assets/js/pagedock-harness.js`
- Modify: `public/assets/js/editor.js`
- Test: `tests/test_editor_missing_page_intercept.js`

- [ ] **Step 1: Write the failing test for missing page interception**

Create `tests/test_editor_missing_page_intercept.js` verifying:
1. When canvas harness dispatches `navigate-page` for `about.html`, editor checks if `about.html` exists in `htmlFiles`.
2. If `about.html` does NOT exist, editor intercepts navigation and opens `#hh-add-page-modal` prefilled with:
   - Title: "About"
   - Filename: "about.html"
   - Layout: "inherit"
3. Toast or notice informs user: `[ACTION] Page about.html does not exist yet. Create it below.`

- [ ] **Step 2: Run test to verify it fails**

Run: `node tests/test_editor_missing_page_intercept.js`
Expected: FAIL.

- [ ] **Step 3: Implement missing-page interception in `editor.js`**

In `editor.js`:
- In the `navigate-page` message listener:
  ```javascript
  const knownFiles = window.PAGEDOCK_CONFIG?.htmlFiles || ['index.html'];
  const targetPath = data.path;
  if (!knownFiles.includes(targetPath)) {
      const derivedTitle = targetPath.replace(/\.html?$/i, '').replace(/[-_]/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
      openAddPageModal(derivedTitle, targetPath);
      showToast(`[ACTION] "${targetPath}" does not exist yet. Create it below with your site layout.`);
      return;
  }
  switchPage(targetPath);
  ```

- [ ] **Step 4: Run test to verify it passes**

Run: `node tests/test_editor_missing_page_intercept.js`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add public/assets/js/editor.js public/assets/js/pagedock-harness.js tests/test_editor_missing_page_intercept.js
git commit -m "feat(editor): intercept canvas links to non-existent pages with auto-create prompt"
```

---

### Task 6: Full Regression Verification & Live Browser Test

- [ ] **Step 1: Run all unit and integration test suites**
- [ ] **Step 2: Test creating `about.html` and `contact.html` on `everyday-imports-storefront`**
- [ ] **Step 3: Verify clean URL serving `http://localhost:8080/s/everyday-imports-storefront/about`**
- [ ] **Step 4: Final verification and commit**
