# 🔍 HTMLHost.co Deep Reverse-Engineering & Architecture Blueprint

> **Reverse-Engineering Assessment & Specification Report**  
> **Source Target:** `https://htmlhost.co`  
> **Investigation Methodology:** Live Network Probing, DOM & Next.js Bundle Inspection, NPM CLI (`htmlhost-cli@2.13.0`) & MCP (`htmlhost-mcp@1.6.0`) Decompilation, Protocol Analysis, and API Contract Mapping.  
> **Target Project:** PageDock (`/Users/user/Desktop/pagedock`)  
> **Generated:** November 2026  
> **Status:** Forensic Deconstruction Complete (Zero Implementation Executed per User Instruction).

---

## 1. Executive Summary & Tech Stack Deconstruction

HTMLHost.co is a minimalist, developer- and creator-focused static web hosting platform designed around instant deployment, client-side preprocessing, zero-backend forms, in-browser live split editing with AI, and autonomous AI agent integration (MCP).

### Target Tech Stack
- **Frontend / Application Framework:** Next.js (App Router, Turbopack, React Server Components)
- **Edge / CDN Infrastructure:** Vercel Global Edge Network with custom edge routing
- **Storage Layer:** **Cloudflare R2** (S3-compatible) with SHA-256 content-addressable deduplication & direct PUT presigned URLs
- **Client State & Offline Storage:** `Dexie.js` (IndexedDB wrapper for local draft persistence)
- **Browser Decompression:** `fflate` (`unzipSync`) running 100% in the user's browser before any file is uploaded
- **Editor & UI Components:** CodeMirror / Monaco core, responsive iframe viewport wrapper, Lucide icons
- **Authentication Engine:** `Auth.js` (NextAuth v5) supporting GitHub, Google, Magic Links, and RFC 8628 OAuth 2.0 Device Authorization Grant (`hh_live_*` bearer tokens)
- **Payment & GeoIP Routing:** Edge GeoIP detection (`/api/geo`) dynamically switching between **Stripe** (USD) and **Bachs / Paystack** (NGN)
- **API Token Architecture:** `hh_live_*` bearer tokens
- **Agent Protocol:** Model Context Protocol (MCP) Streamable HTTP + Local stdio server
- **Analytics:** Lightweight cookieless edge beacon (`/__hh/v`)

---

## 2. Core Platform Capabilities & Feature Matrix

### 2.1. Ingestion & Deployment Channels
1. **Client-Side ZIP & File Drop Ingestion:**
   - Multi-file projects, subfolders, CSS, JS, fonts (`.woff2`, `.ttf`), images (`.webp`, `.avif`, `.png`, `.svg`), video, and audio.
   - Decompressed client-side via `fflate.unzipSync`:
     - Text files (`.html`, `.css`, `.js`) decoded into string memory via `TextDecoder()`.
     - Binary assets converted to `Blob` and `URL.createObjectURL` for instant sandboxed live preview.
   - Auto-extracts archive root: if the ZIP contains a single enclosing folder, the platform unwraps it automatically so `index.html` sits at `/`.
2. **Raw HTML Paste (`/paste-html`):**
   - In-browser code paste with syntax highlighting and auto-title parsing.
3. **URL Importer (`/import-from-url`):**
   - Fetches public web pages, inlines or bundles relative assets, and clones them directly into an editable site project.
4. **WordPress to HTML (`/wordpress-to-html`):**
   - Ingests WordPress export archives or crawls public WordPress installs, converting them to clean static HTML while preserving `/?p=123` query parameters via redirect rules.
5. **Secure File Transfer (`/transfer`):**
   - WebRTC P2P direct browser-to-browser transfer (no server storage, no file size limit) via `/api/transfers/${slug}/signal` + temporary cloud link sharing up to 5GB with automatic expiration.
6. **Clipboard Sync (`/clipboard`):**
   - Fast device-to-device text and file sharing utility.
7. **Developer CLI (`npm i -g htmlhost-cli`):**
   - Terminal deploys: `htmlhost deploy [folder]`, `htmlhost pull [slug]`, `htmlhost clone [slug]`, `htmlhost versions`, `htmlhost rollback`, `htmlhost recipients`.
8. **Agentic MCP Integration (`npx -y htmlhost-mcp@latest` and `https://htmlhost.co/mcp`):**
   - Claude, Cursor, and Antigravity agent tools for autonomous deploy, draft preview, and draft publication.

---

### 2.2. Clean URL Routing & Redirect Engine
- **Clean Path Resolution:**
  - `index.html` → `/`
  - `about.html` → `/about` (HTTP 301 redirect from `/about.html` to `/about`)
  - `blog/index.html` → `/blog`
- **Relative Link Integrity:**
  - Serves `/blog/index.html` directly without redirect when requested explicitly so relative assets resolve within `blog/`.
- **Custom 404 Support:**
  - Detects `404.html` in the project root and renders it for missing routes.
- **Netlify-Style `_redirects` Engine:**
  - Reads `_redirects` at the root on deployment:
    ```text
    /old-page        /new-page        301
    /blog/*          /news/*          302
    /?p=123          /hello-world     301
    ```
  - Wildcards (`/*` → `/*`) and query parameter matching supported.
  - Automatically filters `_redirects` from being served publicly.

---

### 2.3. Zero-Backend Forms Engine (`/__hh/form`)
- **Submission Endpoint:** Relative POST to `/__hh/form`.
- **Form Grouping:** `<input type="hidden" name="_form" value="Contact">`.
- **Honeypot Anti-Spam:**
  - Auto-injected or author-declared:
    ```html
    <div aria-hidden="true" style="position:absolute;left:-10000px;width:1px;height:1px;overflow:hidden">
      <label>Leave this field empty <input type="text" name="_hh_hp" tabindex="-1" autocomplete="off"></label>
    </div>
    ```
- **Control Fields:**
  - `_form`: Form category name in dashboard.
  - `_success`: Custom success message text.
  - `_redirect`: Custom URL to navigate to after submission.
  - `_error`: Custom error notice.
- **Inline Feedback Binding:**
  - `<p data-hh-form-msg>` automatically populated with `data-state="success"|"error"`.
- **Export & Webhooks:**
  - Submissions downloadable as `form-submissions.csv`.
  - Live Google Sheets synchronization via `=IMPORTDATA("https://htmlhost.co/api/forms/export?token=...")`.
  - Outbound webhooks signed with `X-Htmlhost-Signature`.

---

### 2.4. Built-In Cookieless Analytics (`/__hh/v`)
- **Zero-Config Script Injection:**
  - Edge server injects a tiny, privacy-friendly tracking beacon before `</body>` on published HTML pages.
- **Metrics Tracked:**
  - Total Pageviews & Unique Visitors.
  - Top Performing Pages.
  - Referral Sources & Search Engines.
  - Geographic Countries (via GeoIP headers).
  - Device Types (Desktop / Mobile / Tablet).
- **Control & Export:**
  - Owner can toggle collection on/off in Site Settings.
  - Instant CSV export and branded PDF report generation with site favicon and reporting date range.

---

### 2.5. In-Browser Live Split Editor & AI Assistant
- **Three-Pane / Responsive Split View:**
  - Left: Code editor (HTML / CSS / JS tabs).
  - Right: Live sandboxed `iframe` preview with Desktop (1440px), Tablet (768px), and Mobile (390px) toggles.
- **Visual AI Element Editing (`Cmd + J` / `Ctrl + J`):**
  - Point-and-click element inspector: select any DOM element in the preview, describe the change in natural language, and AI applies the targeted patch.
- **Drafts vs. Published State:**
  - Edits saved as local/server drafts with hash verification in IndexedDB (`Dexie.js`).
  - Distinct "Publish" step required before changes go live to visitors.
- **Shared Components (`hh:shared`):**
  - Supports `<!-- hh:shared header active-class="active" -->` and `<!-- hh:shared footer -->`.
  - Normalization algorithm strips active navigation classes and normalizes relative URLs to root paths.
  - Automatic drift detection warns when shared blocks differ across pages.

---

### 2.6. Custom Domains & Security Policy
- **Apex & Subdomains:**
  - Apex domains (`example.com` via `A` / `AAAA` records).
  - Subdomains (`sub.example.com` via `CNAME` records).
  - Automated SSL certificate provisioning and HTTPS redirection.
- **Outbound Link Hygiene:**
  - Free/unverified sites have outbound links served with `rel="nofollow ugc"` to prevent platform abuse and spam networks.
  - Connecting an active custom domain restores the author’s original link attributes.
- **Search Engine Indexing Control:**
  - Toggle in settings to emit `X-Robots-Tag: noindex, nofollow`.

---

### 2.7. Complete API & MCP Contracts

#### Core REST Endpoints
| Method | Endpoint | Description |
| :--- | :--- | :--- |
| `POST` | `/api/cli-login` | Start RFC 8628 device flow (returns `deviceCode`, `userCode`, `verifyUrl`) |
| `POST` | `/api/cli-login/poll` | Poll device authorization status (`pending`, `approved`, `slow_down`) |
| `POST` | `/api/sites/upload-url` | Request presigned R2 upload URLs with SHA-256 deduplication |
| `POST` | `/api/sites` | Create or update site (`{html, title, slug, ttl, pages[], assets[]}`) |
| `GET` | `/api/sites?slug={slug}` | Get metadata, settings, and deployment status |
| `GET` | `/api/sites/pull?slug={slug}` | Download complete site source code bundle (inline HTML, R2 download URLs) |
| `DELETE`| `/api/sites?slug={slug}` | Permanently delete site |
| `POST` | `/api/sites/{slug}/unpublish` | Take site offline without deleting files |
| `GET` | `/api/sites/{slug}/versions` | List deployment revision history |
| `POST` | `/api/sites/{slug}/versions/{n}/restore` | Rollback to specific deployment revision |
| `GET` | `/api/sites/{slug}/recipients` | List form notification email recipients |
| `POST` | `/api/sites/{slug}/recipients` | Add new recipient email address |
| `DELETE`| `/api/sites/{slug}/recipients/{id}` | Remove recipient |
| `POST` | `/api/media/presign` | Request presigned Cloudflare R2 direct upload URL |
| `POST` | `/api/media/confirm` | Finalize media upload into user Media Library |
| `GET` | `/api/analytics/milestones` | Retrieve milestone view achievements |
| `GET/POST/DEL`| `/api/tokens` | Manage API tokens (`hh_live_*`) |
| `GET` | `/api/tokens/me` | Current user profile, plan, and token info |
| `GET` | `/api/geo` | GeoIP lookup for localized pricing & payment gateways |
| `POST` | `/api/wordpress-scan` | Pre-flight audit of WordPress site for static migration |

#### Model Context Protocol (MCP) Tools (`htmlhost-mcp`)
- `deploy`: Deploy folder or file from agent workspace with slug, title, and TTL.
- `deploy_html`: Deploy raw HTML string.
- `list_sites`: List all user and shared sites.
- `get_site`: Fetch single site configuration and asset roster.
- `pull_site`: Retrieve source files into local workspace for inspection/editing.
- `delete_site`: Permanently delete site.
- `unpublish_site`: Take site offline while preserving files.
- `list_versions`: Inspect revision timeline.
- `restore_version`: Instant rollback to previous revision.
- `login`: Interactive browser sign-in step.
- `whoami`: Current authenticated plan details.

---

### 2.8. Pricing & Monetization Model
- **Free Tier:**
  - Permanent static hosting (no inactivity timeouts).
  - Single HTML & ZIP uploads.
  - Basic form capture.
  - Subdomains on `htmlhost.co`.
- **Pro Tier:**
  - **Pricing:** $12/month or $120/year USD (Nigerian pricing: ₦10,000/month or ₦100,000/year NGN).
  - **Billing Philosophy:** **One-Time Pass Model** (users buy 1-month or 1-year passes without forced recurring subscriptions or surprise renewals).
  - **Pro Features:** Custom domains, cookieless analytics export, expanded storage/bandwidth, priority form email alerts, remove platform badges, unlimited AI edit packs.

---

## 3. Gap Analysis: HTMLHost.co vs. Current PageDock

| Feature Area | HTMLHost.co Implementation | Current PageDock State | Priority for PageDock |
| :--- | :--- | :--- | :--- |
| **Clean URLs** | Auto 301 redirects `/page.html` → `/page` (preserves query params) | Implemented & Verified (23 tests) | **Complete (Phase 1)** |
| **Redirects Engine** | Supports `_redirects` Netlify format (wildcards + query params) | Implemented & Verified | **Complete (Phase 1)** |
| **Form Endpoint** | Unified `/__hh/form` + honeypot + CSV/email/Google Sheets | Standardized to `/__pd/form` + honeypot + CSV | **Complete (Phase 2)** |
| **Cookieless Analytics**| Injected `/__hh/v` beacon + country/device stats | None | **High** |
| **Version History** | Revision history with 1-click restore | Basic file storage | **High** |
| **Storage Architecture**| Cloudflare R2 with SHA-256 deduplication & direct PUT | Local filesystem storage | **High** (Add S3/R2 adapter) |
| **Template Library** | 26 categorized starter templates | 26 exact templates implemented | **Complete (Parity)** |
| **In-Browser Editor** | Split live editor with iframe & Dexie.js drafts | Implemented (`/editor/{slug}`) | **Complete (Parity)** |
| **URL Importer** | `/import-from-url` cloning | None | **Medium** |
| **WordPress to HTML** | `/wordpress-to-html` converter + `/?p=` preserve | None | **Medium** |
| **P2P Transfer** | WebRTC P2P direct transfer (`/transfer`) | None | **Medium** |
| **Pricing Model** | Pass model ($12/mo or ₦10k/mo) | Not yet monetized | **Strategic** |
| **Developer CLI** | `htmlhost-cli` (`npm i -g htmlhost-cli`) | None | **High (Differentiator)** |
| **MCP Integration** | Native Streamable HTTP + stdio MCP server | None | **High (Differentiator)** |

---

## 4. Implementation Blueprint for PageDock

```
┌────────────────────────────────────────────────────────────────────────┐
│                   PageDock Core Architecture Blueprint                 │
├──────────────────────┬─────────────────────────────────────────────────┤
│ Domain / Layer       │ PageDock Blueprint                              │
├──────────────────────┼─────────────────────────────────────────────────┤
│ 1. Storage Backend   │ Hybrid Storage:                                 │
│                      │ - Local filesystem mode (default for self-host) │
│                      │ - Cloudflare R2 / S3 mode (for production)      │
│                      │ - SHA-256 content-addressable deduplication     │
├──────────────────────┼─────────────────────────────────────────────────┤
│ 2. Routing Engine    │ Clean URLs + Netlify `_redirects`:              │
│                      │ - Auto 301 `/page.html` -> `/page`              │
│                      │ - Preserve folder index paths for assets        │
│                      │ - Full `_redirects` wildcards & query matching  │
│                      │ - Custom `404.html` support                     │
├──────────────────────┼─────────────────────────────────────────────────┤
│ 3. Form Engine       │ Unified `/__pd/form`:                           │
│                      │ - Auto-injected honeypot (`_pd_hp`)             │
│                      │ - CSV export + webhook dispatcher               │
│                      │ - Email alerts to site owners & team recipients │
├──────────────────────┼─────────────────────────────────────────────────┤
│ 4. Analytics Engine  │ Built-in `/__pd/v`:                             │
│                      │ - Zero-cookie lightweight script beacon         │
│                      │ - Daily views, referrers, device, geo analytics │
│                      │ - PDF & CSV export                              │
├──────────────────────┼─────────────────────────────────────────────────┤
│ 5. Versioning        │ Snapshot Version Engine:                        │
│                      │ - Immutable revision history on deploy          │
│                      │ - Instant 1-click rollback via UI, CLI, & MCP   │
├──────────────────────┼─────────────────────────────────────────────────┤
│ 6. Developer CLI     │ `pagedock-cli` (`npm i -g pagedock-cli`):       │
│                      │ - `pagedock deploy`, `pull`, `clone`, `status`  │
│                      │ - Shared header/footer drift detection          │
│                      │ - `.pagedock` project linking                   │
├──────────────────────┼─────────────────────────────────────────────────┤
│ 7. Agentic MCP       │ `pagedock-mcp`:                                 │
│                      │ - Local stdio server for Claude, Cursor, AGY    │
│                      │ - Remote Streamable HTTP endpoint for agents    │
└──────────────────────┴─────────────────────────────────────────────────┘
```
