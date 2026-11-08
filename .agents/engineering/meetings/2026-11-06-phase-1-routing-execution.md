# PageDock Council of Developers: Deliberation Minutes

**Date:** 2026-11-06  
**Session ID:** DC-2026-11-06-P1  
**Chair:** Chief Systems Architect & Lead Facilitator  
**Mandate:** Phase 1 Execution — Clean URLs, Netlify _redirects Engine, Custom 404, Query String Preservation, Production .htaccess  
**Status:** PASSED AND VERIFIED (23/23 Tests Passing)

---

## 1. Attendees (Full Bicameral Quorum)

- **Frontend Wing:**
  - Executive Design Director & Frontend Craft Lead
  - Design System & Component Token Specialist
  - Performance, Web Standards & Mobile Systems Engineer
  - Conversion & Growth UX Engineer
  - Visual Media & Creative Asset Specialist
  - Frontend QA, Reliability & Code Simplicity Lead
  - Frontend Implementation Engine (Subagents)

- **Backend Wing:**
  - Chief Systems Architect & Database Lead (Lead)
  - Application Security & Hardening Engineer
  - Web Scraping & Stealth Automation Specialist
  - QA, Reliability & Code Simplicity Lead (Anti-Bloat)
  - System Topology & Knowledge Graph Officer
  - Backend Implementation Engine (Subagents)

---

## 2. Deliberations & Technical Decisions

### [DECISION-01] Clean URL Query String Retention
- **Problem:** Incoming links with tracking parameters (e.g. `/s/slug/about.html?utm_source=twitter`) were losing query strings during the 301 canonical redirect.
- **Resolution:** Modified `src/Router.php::serveSiteFile()` to capture `$_SERVER['QUERY_STRING']` and append it to canonical 301 targets for both page routes (`/about.html` -> `/about`) and root index routes (`/index.html` -> `/`).

### [DECISION-02] Production Apache / cPanel .htaccess Deployment
- **Problem:** The repository lacked web server rewrite rules, causing clean URLs (`/s/demo/about`) to return server-level 404 errors on Apache/cPanel hosts.
- **Resolution:** Created `public/.htaccess` containing `mod_rewrite` directives forwarding non-file requests to `index.php [QSA,L]`, blocking `.env` and `_redirects` files, and enforcing security headers (`X-Content-Type-Options: nosniff`).

### [DECISION-03] Test Suite Expansion
- **Resolution:** Expanded `tests/test_clean_urls_and_redirects.php` from 17 to 23 tests, covering query preservation, index redirection, and `.htaccess` file validation.

---

## 3. Verification Log

```text
=== Running Step 1: Clean URLs & Redirects Tests ===

--- 1. RedirectManager Unit Tests ---
✅ PASS: Parsed 5 redirect rules
✅ PASS: Exact match /old-about -> /about with 301
✅ PASS: Temporary match /temp-promo -> /special with 302
✅ PASS: Wildcard match /blog/* -> /articles/tech/deep-dive
✅ PASS: Query string match /?p=42 -> /hello-world
✅ PASS: External redirect target https://example.com

--- 2. End-to-End HTTP Integration Tests ---
✅ PASS: Clean URL /about resolves with 200 OK
✅ PASS: Clean URL /about serves About Us content
✅ PASS: Visiting /about.html returns 301 Redirect
✅ PASS: Redirect target is clean URL /about
✅ PASS: Query string preserved on /about.html 301 redirect
✅ PASS: Visiting /index.html with query string redirects 301 to root preserving query
✅ PASS: _redirects rule returns 301 Redirect
✅ PASS: _redirects target is /contact
✅ PASS: Non-existent page returns HTTP 404
✅ PASS: Serves site's custom 404.html content
✅ PASS: Accessing /_redirects returns 404
✅ PASS: _redirects contents are not leaked
✅ PASS: Outbound link receives rel='nofollow ugc'
✅ PASS: Production .htaccess exists in public/
✅ PASS: .htaccess contains RewriteEngine On
✅ PASS: .htaccess contains index.php [QSA,L] routing rule
✅ PASS: .htaccess blocks direct access to _redirects

============================================
RESULTS: 23 / 23 TESTS PASSED
============================================
```

---

## 4. Next Phase Readiness

Phase 1 (Routing & URL Hygiene) is officially closed. The Council recommends proceeding to **Phase 2: Universal Form Engine (`/__pd/form`)**.

