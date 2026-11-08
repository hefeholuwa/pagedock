# PageDock MCP Server & Agent Tools Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a production-grade Model Context Protocol (MCP) server for PageDock (`pagedock-mcp`) allowing autonomous AI agents (Claude Code, Cursor, Antigravity) to deploy, inspect, patch, rollback, and manage static sites and form leads via standard MCP tools.

**Architecture:** Node.js ES-Module MCP Server using `@modelcontextprotocol/sdk` with `StdioServerTransport`. Connects to PageDock via REST API (`http://localhost:8080`) or local filesystem/SQLite fallback, exposing 10 high-precision tools with strict JSON schemas.

**Tech Stack:** Node.js (>=18), `@modelcontextprotocol/sdk`, Zod schemas, Vitest / Node native test runner.

---

### File Structure Map
```
packages/pagedock-mcp/
├── package.json               # Package manifests, bin runner: npx pagedock-mcp
├── src/
│   ├── index.js               # CLI entrypoint and stdio transport setup
│   ├── server.js              # McpServer instance & tool registrations
│   ├── client.js              # PageDock REST API client & auth wrapper
│   └── tools/
│       ├── deploy.js          # deploy_html, deploy_directory
│       ├── sites.js           # list_sites, get_site, pull_site, delete_site
│       ├── patch.js           # update_site_file, patch_site_element
│       ├── versions.js        # list_versions, rollback_site
│       ├── analytics.js       # get_site_analytics
│       └── forms.js           # list_form_submissions
└── tests/
    ├── test_mcp_client.js     # API client unit tests
    └── test_mcp_tools.js      # Mock agent tool call tests
```

---

### Task 1: MCP Server Scaffold & API Client

**Files:**
- Create: `packages/pagedock-mcp/package.json`
- Create: `packages/pagedock-mcp/src/client.js`
- Test: `packages/pagedock-mcp/tests/test_mcp_client.js`

- [ ] **Step 1: Write failing client test**
Create `packages/pagedock-mcp/tests/test_mcp_client.js`:
Tests that `PageDockClient` connects to PageDock REST API, handles bearer authentication token or claim tokens, and handles error states gracefully.

- [ ] **Step 2: Run test to verify it fails**
Run: `node packages/pagedock-mcp/tests/test_mcp_client.js`  
Expected: FAIL (module not found)

- [ ] **Step 3: Implement `package.json` and `src/client.js`**
Implement `PageDockClient` with methods:
- `deployHtml({ html, title, slug })`
- `listSites()`
- `getSite(slug)`
- `pullSite(slug)`
- `listVersions(slug)`
- `rollbackSite(slug, version)`
- `getAnalytics(slug)`
- `getFormSubmissions(slug, isSpam)`

- [ ] **Step 4: Run test to verify it passes**
Run: `node packages/pagedock-mcp/tests/test_mcp_client.js`  
Expected: PASS

- [ ] **Step 5: Git commit**
```bash
git add packages/pagedock-mcp/package.json packages/pagedock-mcp/src/client.js packages/pagedock-mcp/tests/test_mcp_client.js
git commit -m "feat(mcp): scaffold pagedock-mcp package and API client"
```

---

### Task 2: Core Site Management & Deployment Tools

**Files:**
- Create: `packages/pagedock-mcp/src/tools/deploy.js`
- Create: `packages/pagedock-mcp/src/tools/sites.js`
- Modify: `packages/pagedock-mcp/src/server.js`
- Test: `packages/pagedock-mcp/tests/test_deploy_tools.js`

- [ ] **Step 1: Write failing deploy tools test**
Create `packages/pagedock-mcp/tests/test_deploy_tools.js` testing tool schema validation and mock tool execution for `deploy_html`, `list_sites`, `get_site`, `pull_site`.

- [ ] **Step 2: Run test to verify it fails**
Run: `node packages/pagedock-mcp/tests/test_deploy_tools.js`  
Expected: FAIL

- [ ] **Step 3: Implement deploy and sites tools**
Implement:
1. `deploy_html`: Takes `html`, optional `title`, `slug`, `ttl`. Deploys and returns live preview URL and dashboard URL.
2. `list_sites`: Returns array of user's sites with slug, title, views, and live URLs.
3. `get_site`: Fetches single site metadata, entry file, size, and settings.
4. `pull_site`: Downloads the HTML source code of a hosted site for agent analysis.
5. `delete_site`: Removes site from PageDock.

- [ ] **Step 4: Run test to verify it passes**
Run: `node packages/pagedock-mcp/tests/test_deploy_tools.js`  
Expected: PASS

- [ ] **Step 5: Git commit**
```bash
git add packages/pagedock-mcp/src/tools/deploy.js packages/pagedock-mcp/src/tools/sites.js packages/pagedock-mcp/src/server.js packages/pagedock-mcp/tests/test_deploy_tools.js
git commit -m "feat(mcp): implement deploy_html, list_sites, get_site, pull_site tools"
```

---

### Task 3: Patching, Versioning & Rollback Tools

**Files:**
- Create: `packages/pagedock-mcp/src/tools/patch.js`
- Create: `packages/pagedock-mcp/src/tools/versions.js`
- Test: `packages/pagedock-mcp/tests/test_patch_tools.js`

- [ ] **Step 1: Write failing patch & rollback test**
Create `packages/pagedock-mcp/tests/test_patch_tools.js` testing `update_site_file`, `list_versions`, `rollback_site`.

- [ ] **Step 2: Run test to verify it fails**
Run: `node packages/pagedock-mcp/tests/test_patch_tools.js`  
Expected: FAIL

- [ ] **Step 3: Implement surgical patching & version rollback**
1. `update_site_file`: Updates `index.html` or other project files with fresh code, auto-creating a new version snapshot.
2. `list_versions`: Returns the immutable snapshot timeline (`v1`, `v2`, `v3`) with timestamps and change summaries.
3. `rollback_site`: Reverts live site index to specified snapshot version.

- [ ] **Step 4: Run test to verify it passes**
Run: `node packages/pagedock-mcp/tests/test_patch_tools.js`  
Expected: PASS

- [ ] **Step 5: Git commit**
```bash
git add packages/pagedock-mcp/src/tools/patch.js packages/pagedock-mcp/src/tools/versions.js packages/pagedock-mcp/tests/test_patch_tools.js
git commit -m "feat(mcp): implement update_site_file, list_versions, and rollback_site tools"
```

---

### Task 4: Analytics & Form Leads Tools

**Files:**
- Create: `packages/pagedock-mcp/src/tools/analytics.js`
- Create: `packages/pagedock-mcp/src/tools/forms.js`
- Test: `packages/pagedock-mcp/tests/test_insights_tools.js`

- [ ] **Step 1: Write failing analytics & forms test**
Create `packages/pagedock-mcp/tests/test_insights_tools.js` asserting schema and execution for `get_site_analytics` and `list_form_submissions`.

- [ ] **Step 2: Run test to verify it fails**
Run: `node packages/pagedock-mcp/tests/test_insights_tools.js`  
Expected: FAIL

- [ ] **Step 3: Implement analytics and forms tools**
1. `get_site_analytics`: Returns total views, unique visitors, device breakdown, top pages, and referrers.
2. `list_form_submissions`: Fetches lead submissions with optional spam filtering (`is_spam: false`).

- [ ] **Step 4: Run test to verify it passes**
Run: `node packages/pagedock-mcp/tests/test_insights_tools.js`  
Expected: PASS

- [ ] **Step 5: Git commit**
```bash
git add packages/pagedock-mcp/src/tools/analytics.js packages/pagedock-mcp/src/tools/forms.js packages/pagedock-mcp/tests/test_insights_tools.js
git commit -m "feat(mcp): implement get_site_analytics and list_form_submissions tools"
```

---

### Task 5: Stdio Server Entrypoint & Client Integration Config

**Files:**
- Create: `packages/pagedock-mcp/src/index.js`
- Create: `packages/pagedock-mcp/README.md`
- Test: `packages/pagedock-mcp/tests/test_stdio_protocol.js`

- [ ] **Step 1: Write test for MCP stdio protocol handshake**
Verify `index.js` initializes `StdioServerTransport` and responds to `tools/list` and `tools/call`.

- [ ] **Step 2: Run test to verify it fails**
Run: `node packages/pagedock-mcp/tests/test_stdio_protocol.js`  
Expected: FAIL

- [ ] **Step 3: Wire up StdioServerTransport and generate client configs**
1. Create `src/index.js` connecting `server.js` with `StdioServerTransport`.
2. Write `README.md` with 1-click configuration for:
   - Claude Desktop (`claude_desktop_config.json`)
   - Claude Code (`claude mcp add pagedock ...`)
   - Cursor (`mcp.json`)
   - Antigravity (`agy mcp add pagedock ...`)

- [ ] **Step 4: Run test to verify it passes**
Run: `node packages/pagedock-mcp/tests/test_stdio_protocol.js`  
Expected: PASS

- [ ] **Step 5: Git commit**
```bash
git add packages/pagedock-mcp/src/index.js packages/pagedock-mcp/README.md packages/pagedock-mcp/tests/test_stdio_protocol.js
git commit -m "feat(mcp): complete stdio protocol entrypoint and client configuration docs"
```
