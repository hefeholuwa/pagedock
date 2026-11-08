# htmlhost.co Reverse-Engineering Dossier

Status: public-surface reconstruction, November 2026

Scope: htmlhost.co's publicly documented product behavior and user-facing
contracts. This is a behavioral reconstruction for building an independent
tool. It does not copy private source code, proprietary assets, or branding.

## Evidence boundary

Observed directly from public pages:

- Homepage and product navigation
- HTML editor, paste, importer, templates, pricing, and documentation pages
- Published REST, CLI, MCP, forms, media, versioning, redirects, and plan docs

Not established in this pass:

- Private frontend source code or backend source code
- Exact database schema, deployment infrastructure, queueing, or CDN internals
- Runtime network traces from an authenticated editor session
- Exact AI prompts, model routing, or internal DOM patch algorithm

## Shipped CLI/MCP artifact inspection

The public npm registry currently exposes `htmlhost-cli@2.13.0` and
`htmlhost-mcp@1.6.0`. Their shipped JavaScript was downloaded and inspected
without signing in or calling mutating API operations.

The artifacts confirm the following client-side protocol details:

- CLI and MCP share `~/.htmlhostrc` and a bearer token.
- Requests send `Authorization: Bearer ...`; MCP additionally sends
  `X-HTMLHost-Client: mcp` and `X-HTMLHost-Client-Version`.
- Directory deploys call `POST /api/sites/upload-url` before the final
  `POST /api/sites` request when binary assets exist.
- The upload-url request includes asset path, byte size, MIME type, and
  SHA-256 hash. A returned `uploadUrl` is PUT directly by the client.
- The final directory payload includes `pages`, `assets`, `isDraft`,
  `confirmPublish`, optional `redirects`, `ttl`, `slug`, and `title`.
- Large media uses `POST /api/media/presign`, direct PUT to the returned URL,
  then `POST /api/media/confirm`.
- CLI and MCP retry network/5xx operations with exponential backoff but do not
  retry ordinary 4xx errors.
- MCP exposes deploy, deploy_html, list sites, get site, pull site, delete,
  unpublish, list versions, restore version, whoami, and login tools in the
  shipped server source.

These are stronger than documentation-only findings because they come from
the public client artifacts. They still describe what the clients send and
expect; they do not prove the private server implementation or storage vendor.

## Product thesis

htmlhost.co is a static-site publishing system with three tightly connected
surfaces:

1. A browser workflow for importing, previewing, editing, and publishing HTML.
2. A developer workflow for deploy, pull, clone, status, versions, and rollback.
3. An agent workflow through REST and MCP.

The central object is a site with working files, drafts, published versions,
assets, form configuration, and optional domain/expiry/security settings.

## Primary user flows

### Browser publish

1. User pastes HTML or drops a file, folder, or ZIP.
2. The system opens a private editable preview.
3. User edits code or selects elements in the live preview.
4. User signs in when publishing.
5. The system creates a shareable HTTPS site on a generated subdomain.
6. Later edits update the same site after an explicit publish action.

### CLI publish

`htmlhost login` authenticates in the browser and stores credentials locally.
`htmlhost deploy` publishes a file or directory, shows a file tree, confirms
the upload, and records a `.htmlhost` link for future redeploys.

Directory mapping is:

```text
index.html          -> /
about.html          -> /about
contact.html        -> /contact
blog/index.html     -> /blog
blog/post-1.html    -> /blog/post-1
```

### Sync and recovery

`status` compares local and remote state without writing. `pull` downloads
remote changes and does not delete local-only files. `clone` downloads a site
into a new directory. `versions` lists published versions and `rollback`
publishes an earlier version as a new version.

### AI editing

The editor supports two modes: AI Edit and direct Element Edit. A selected
element can be changed through natural language or through supported direct
controls for text, links, buttons, images, forms, maps, and videos. The result
remains a draft until the user publishes.

## Public contract map

### Live unauthenticated endpoint probes

Read-only GET requests were sent to the live host on 2026-11-06. These probes
did not include credentials and did not create or mutate data.

| Endpoint | Live result | What it establishes |
|---|---|---|
| `/api/sites` | HTTP 401, `{"error":"Not authenticated"}` | Route exists and requires authentication |
| `/api/sites/pull` | HTTP 401, `{"error":"Not authenticated"}` | Route exists and requires authentication |
| `/api/media` | HTTP 401, media-management sign-in error | Route exists and is account-protected |
| `/api/tokens/me` | HTTP 401, `{"error":"Invalid or expired token"}` | Token validation route exists |
| `/__hh/form` on the main host | HTTP 404 | Form handling is not exposed as a global main-host page; it is likely resolved in published-site context |

The responses also exposed `server: Vercel`, HSTS, `x-matched-path`, and
`x-vercel-cache` headers. This is direct infrastructure evidence for the
current public deployment, although it does not prove that every backend
component runs on Vercel.

Authenticated success responses, POST validation, rate limits, upload behavior,
draft state transitions, and site-scoped form submission still require a
legitimate test account or API token. No credentials were available, so those
behaviors remain documented-contract evidence rather than live-observed
behavior.

### REST API

All documented API calls use `Authorization: Bearer <token>`.

| Method | Endpoint | Behavior |
|---|---|---|
| GET | `/api/sites` | List sites and aggregate usage |
| POST | `/api/sites` | Create or redeploy a single-page or multi-page site |
| DELETE | `/api/sites?slug={slug}` | Delete a site |
| GET | `/api/sites/pull?slug={slug}` | Pull pages and assets |
| GET | `/api/sites/{slug}/versions` | List versions |
| POST | `/api/sites/{slug}/versions/{n}/restore` | Restore a version as a new version |
| GET | `/api/media` | List media |
| POST | `/api/media` | Upload media using multipart form data |
| DELETE | `/api/media?id={id}` | Delete media |
| GET/POST/DELETE | `/api/tokens` | Manage API tokens |
| GET | `/api/tokens/me` | Validate a token |

Additional endpoints confirmed by the shipped CLI/MCP clients:

| Method | Endpoint | Artifact-backed purpose |
|---|---|---|
| POST | `/api/sites/upload-url` | Hash-aware asset upload preparation |
| POST | `/api/media/presign` | Prepare a direct large-media upload |
| POST | `/api/media/confirm` | Confirm a completed direct media upload |
| POST | `/api/cli-login` | Start browser/device-style CLI login |
| POST | `/api/cli-login/poll` | Poll CLI login approval |
| POST | `/api/sites/{slug}/unpublish` | Unpublish while retaining site data |
| GET | `/api/sites/{slug}/recipients` | Read form recipients |
| POST/DELETE | `/api/sites/{slug}/recipients` | Add/remove form recipient entries |

The exact JSON response bodies for successful authenticated calls remain
unobserved because no account token was available.

Single-page deployment accepts `html`, optional `title`, `ttl`, and `slug`.
Multi-page deployment accepts `pages[]`, where each item contains `path`,
`html`, and optional `title`.

The API also documents `isDraft: true`, explicit publication confirmation,
working/draft/published pull sources, unpublish, and publication conflict
responses. These indicate a stateful publish boundary rather than direct file
replacement.

### CLI

Documented commands include:

```text
login
deploy
status
pull
clone
versions
rollback
recipients
media
agents
```

Deploy flags include `--ttl`, `--slug`, `--title`, `--new`, `--no-assets`,
`--no-pull-check`, and `--json`.

### MCP

The public docs describe an stdio MCP server installed with:

```bash
npx -y htmlhost-mcp@latest
```

Its documented responsibilities are deploy, list, pull, and delete. The
homepage also describes version, draft, and publish operations for compatible
AI clients, but the exact tool schemas were not exposed in the public page
captured here.

## Publishing and asset model

The platform distinguishes two asset paths:

- Single-file deploy: local CSS and JavaScript are inlined; binary assets are
  uploaded to the media library and HTML/CSS references are rewritten.
- Folder deploy: pages and assets remain a multi-file site deployment.

The docs state that CSS `@import` and `url()` references are scanned, local
images and responsive image references are processed, and fonts/media are
uploaded. CSS/JS files at or above 512 KB are kept external rather than
inlined; files over 10 MB are skipped.

The media library gives files permanent CDN URLs. Executable files such as
`.exe`, `.msi`, `.sh`, and `.bat` are disallowed.

## Version and draft model

Every publish creates a version. A version contains pages, CSS, JS, and assets.
Domain configuration, form settings, password, and expiry are outside the
version boundary and survive restore.

Restore does not mutate history: the old version is published again as a new
version labelled as restored. A version can also be privately previewed,
compared with live, edited as an unsaved draft, or named.

## Forms subsystem

Published forms post to `/__hh/form` and use a hidden `_form` field for grouping.
The platform injects a spam trap named `_hh_hp` into published forms.

Supported hidden controls:

```text
_form      form name
_success   confirmation message
_redirect  same-site path after success
_error     custom failure message
```

The published page receives background submission behavior plus a no-JavaScript
fallback. The editor adds or recognizes forms, messages are visible in a site
Messages area and a cross-site Inbox, and CSV export is supported.

Pro behavior includes file attachments, recipients, Google Sheets integration,
and signed webhooks using `X-Htmlhost-Signature` with HMAC-SHA256.

Documented safety rules include same-site origin checks, rate limits, hidden
spam detection, password-protected-site checks, and rejection of fields that
appear to request passwords or card numbers.

## Routing and redirects

Clean URLs map HTML filenames to paths. A `404.html` file is served for missing
pages. A root `_redirects` file is consumed during deploy and not published.
Dashboard redirects remain in addition to file-based redirects; CLI deploy
replaces the redirects previously set by that CLI deployment.

## Shared components

Shared headers and footers are implemented in plain HTML using comment markers:

```html
<!-- hh:shared header active-class="active" -->
...
<!-- /hh:shared header -->
```

Each page keeps a full copy, allowing the site to remain portable. The editor
updates selected copies and can apply a shared change across pages. Active
navigation and `aria-current="page"` are managed by the editor.

## Security and account model

- Sites live on generated `*.htmlhost.co` addresses.
- API tokens begin with `hh_live_`, are shown once, and are SHA-256 hashed.
- CLI credentials are stored in `~/.htmlhostrc` with owner-only permissions.
- Custom domains use either an A record for apex domains or CNAME for
  subdomains, with HTTPS provisioning described as automatic.
- Password protection and collaborators are plan-gated.
- Temporary sites can use 1-day, 7-day, or 30-day TTLs; expired sites return
  HTTP 410 while remaining in the dashboard.

## Pricing-derived capability boundaries

The public pricing page documents:

- Free: 3 sites, 500 MB storage, 10 AI edits/day, 50 form messages/month.
- Pro: $12/month, unlimited sites, 10 GB storage, 3,000 AI edits/month,
  5,000 form messages/month, version history, collaborators, and expanded
  analytics/media limits.

These are product limits, not evidence of the implementation architecture.

## Reimplementation priorities

For an independent equivalent, the smallest coherent build is:

1. Site record plus immutable published version records.
2. Private working/draft files separate from published files.
3. Multi-page path resolver and clean URL redirects.
4. Safe asset upload and path rewriting.
5. Explicit publish, unpublish, and rollback operations.
6. REST API with bearer tokens.
7. CLI sync using a local link file.
8. Forms endpoint with origin checks, spam trap, storage, and email delivery.
9. Editor preview and direct element editing.
10. AI patching, MCP, domains, collaboration, and billing after the core state
    model is reliable.

## Source references

- [Homepage](https://htmlhost.co/)
- [Documentation](https://htmlhost.co/docs)
- [Pricing](https://htmlhost.co/pricing)
- [Paste HTML](https://htmlhost.co/paste-html)
- [Website importer](https://htmlhost.co/import-from-url)
- [Templates](https://htmlhost.co/templates)
