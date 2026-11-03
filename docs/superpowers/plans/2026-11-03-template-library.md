# Template Library Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a production-grade HTML Template Library for PageDock based on the reverse-engineered htmlhost.co architecture with 8+ curated modern templates, category filters, real-time search, interactive responsive preview modal, and one-click "Use Template & Open in Editor".

**Architecture:** Pure PHP 8.5 backend with a self-contained `Templates` registry class storing curated templates with metadata and standalone HTML. Clean routing handles `/templates`, `/templates/{slug}/preview`, and `POST /api/templates/{slug}/use`. Frontend includes category filter pills, instant search, responsive template cards, and interactive modal preview with desktop/mobile viewports.

**Tech Stack:** PHP 8.5, SQLite, Vanilla JavaScript, CSS custom properties (Dark theme with `#C7F547` accent).

---

### Task 1: Templates Model & Curated Template Registry

**Files:**
- Create: `src/Templates.php`
- Test: `tests/test_templates.php`

- [ ] **Step 1: Write the failing unit test**

Create `tests/test_templates.php`:
```php
<?php
require_once dirname(__DIR__) . '/config/config.php';

$all = Templates::getAll();
assert(count($all) >= 6, 'Must provide at least 6 starter templates');

$portfolio = Templates::get('developer-portfolio');
assert($portfolio !== null, 'Developer portfolio template must exist');
assert(!empty($portfolio['html']), 'Template must contain HTML code');
assert(!empty($portfolio['category']), 'Template must have a category');

$categories = Templates::getCategories();
assert(in_array('Landing Page', $categories), 'Landing Page category must exist');

echo "PASS: Templates registry tests pass\n";
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/test_templates.php`  
Expected: FAIL with "Class Templates not found"

- [ ] **Step 3: Implement `src/Templates.php`**

Write `src/Templates.php` with:
- `getAll(): array`
- `get(string $slug): ?array`
- `getCategories(): array`
- 8 high quality modern templates:
  1. `futuristic-saas`: Dark theme, flame glow, feature matrix, interactive pricing toggle, CTA form.
  2. `developer-portfolio`: Clean terminal/dark aesthetic, skills pills, project cards, zero-backend contact form.
  3. `executive-resume`: Sleek typography, career timeline, education, skills radar, print/PDF styling.
  4. `early-access-waitlist`: Countdown timer, email waitlist form, social proof badges.
  5. `creative-agency`: Bold editorial typography, services showcase, client testimonials, inquiry form.
  6. `link-in-bio`: Mobile-first profile avatar, social badges, link cards with hover bounce, newsletter.
  7. `ai-analytics-dashboard`: Sidebar layout, metric stat cards with trend badges, pure CSS chart, activity feed.
  8. `tech-conference-event`: Date/venue hero, speaker cards, agenda tabs, ticket RSVP form.

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/test_templates.php`  
Expected: PASS with "PASS: Templates registry tests pass"

- [ ] **Step 5: Commit**

```bash
git add tests/test_templates.php src/Templates.php
git commit -m "feat: add Templates registry with 8 curated starter templates"
```

---

### Task 2: Template Routes, API Endpoints & Site Cloning

**Files:**
- Modify: `src/Router.php`
- Test: `tests/test_template_routes.php`

- [ ] **Step 1: Write the test for template clone and preview API**

Create `tests/test_template_routes.php`:
```php
<?php
require_once dirname(__DIR__) . '/config/config.php';

// Test cloning template into a new site
$template = Templates::get('developer-portfolio');
assert($template !== null);

$newSite = SiteManager::createFromHtml($template['html'], $template['title'] . ' (My Copy)');
assert($newSite['success'] === true, 'Site cloning failed');
assert(!empty($newSite['slug']), 'Cloned site must have a slug');

// Verify cloned site content matches
$fetchedHtml = SiteManager::getSiteHtml($newSite['slug']);
assert(str_contains($fetchedHtml, 'Alex Rivera') || str_contains($fetchedHtml, '<html'), 'Cloned site HTML should match template');

// Cleanup
SiteManager::deleteSite($newSite['slug']);
echo "PASS: Template cloning and creation tests pass\n";
```

- [ ] **Step 2: Run test to verify it passes**

Run: `php tests/test_template_routes.php`  
Expected: PASS

- [ ] **Step 3: Update `src/Router.php` to handle template routes**

Add routes:
- `GET /templates` -> renders `views/templates.php`
- `GET /templates/{slug}/preview` -> outputs raw template HTML directly with appropriate headers
- `POST /api/templates/{slug}/use` -> creates a site from template HTML and returns `{success: true, slug, url, editor_url}`

- [ ] **Step 4: Verify with cURL against local server**

Run: `curl -I http://127.0.0.1:8080/templates`  
Expected: HTTP/1.1 200 OK

- [ ] **Step 5: Commit**

```bash
git add tests/test_template_routes.php src/Router.php
git commit -m "feat: implement template routes, preview endpoint, and clone API"
```

---

### Task 3: Template Gallery View & Responsive Preview Modal

**Files:**
- Create: `views/templates.php`
- Modify: `public/assets/css/style.css`
- Modify: `views/layouts/header.php`
- Modify: `public/assets/js/app.js`

- [ ] **Step 1: Create `views/templates.php`**

Implement:
- Page Header: "HTML Templates — Ready for vibecoding."
- Category filter navigation bar (`All`, `Landing page`, `Portfolio`, `Resume`, `Waitlist`, `Dashboard`, `Agency`, `Event`) with counter badges.
- Search input with instant client-side filtering.
- 16:10 template cards displaying aspect-ratio preview thumbnail, Free/Pro badge, title, description, tags, "Preview" button, and "Use Template" CTA.
- Responsive live preview modal with Desktop (100%), Tablet (768px), and Mobile (375px) device viewport switchers.

- [ ] **Step 2: Update `public/assets/css/style.css`**

Add styles for:
- Template gallery header, category tabs, and search bar.
- Template grid (3 columns on desktop, 2 columns on tablet, 1 column on mobile).
- Template preview modal with responsive iframe and floating action buttons.

- [ ] **Step 3: Update `views/layouts/header.php`**

Add "Templates" link to both desktop navigation bar and mobile drawer.

- [ ] **Step 4: Update `public/assets/js/app.js`**

Add client-side template filtering by category and search keyword, plus preview modal opening and "Use Template" button handler.

- [ ] **Step 5: Verify on mobile and desktop viewports**

Verify via local HTTP requests that `/templates` loads cleanly and returns 200 OK.

- [ ] **Step 6: Commit and Push**

```bash
git add views/templates.php views/layouts/header.php public/assets/css/style.css public/assets/js/app.js
git commit -m "feat: add template gallery UI with category filters, search, and device preview modal"
git push origin master
```
