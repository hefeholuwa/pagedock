# PageDock MCP Server (`pagedock-mcp`)

The official **Model Context Protocol (MCP)** server for **PageDock**, enabling autonomous AI agents (Claude Code, Cursor, Antigravity, Gemini CLI, Claude Desktop, and other MCP clients) to deploy, manage, patch, inspect, rollback, and monitor static websites and form lead submissions.

---

## 🌟 Highlights

- **Standard MCP Protocol**: Full JSON-RPC 2.0 stdio server compliant with `@modelcontextprotocol/sdk`.
- **Instant Previews**: Deploy single-file HTML sites with one tool call and receive live URLs and live editor links.
- **Surgical Patching**: Modify individual site files (`index.html`, `style.css`, etc.) with automatic immutable version snapshots.
- **1-Click Rollback**: Inspect version history and instantly revert live sites to any prior snapshot or the immediate previous version.
- **Actionable Insights**: Query visitor analytics, page views, unique referrers, and filter incoming contact form leads.

---

## 🚀 Quickstart & Installation

Run directly with `npx` or install locally into your workspace:

```bash
# Run directly via NPX
npx pagedock-mcp

# Or install as a dependency
npm install pagedock-mcp
```

### Environment Variables

| Variable | Description | Default |
| :--- | :--- | :--- |
| `PAGEDOCK_BASE_URL` | Base URL of the PageDock server | `http://localhost:8080` |
| `PAGEDOCK_API_TOKEN` | Bearer token for user authentication | `null` (guest mode) |
| `PAGEDOCK_CLAIM_TOKEN` | Site claim token for guest ownership | `null` |

---

## 🔌 Client Integration Configurations

### 1. Claude Desktop (`claude_desktop_config.json`)

On macOS: `~/Library/Application Support/Claude/claude_desktop_config.json`  
On Windows: `%APPDATA%\Claude\claude_desktop_config.json`

```json
{
  "mcpServers": {
    "pagedock": {
      "command": "npx",
      "args": ["-y", "pagedock-mcp"],
      "env": {
        "PAGEDOCK_BASE_URL": "http://localhost:8080",
        "PAGEDOCK_API_TOKEN": "your_api_token_here"
      }
    }
  }
}
```

If running from local source repository:
```json
{
  "mcpServers": {
    "pagedock": {
      "command": "node",
      "args": ["/path/to/pagedock/packages/pagedock-mcp/src/index.js"],
      "env": {
        "PAGEDOCK_BASE_URL": "http://localhost:8080"
      }
    }
  }
}
```

---

### 2. Cursor (`.cursor/mcp.json`)

Add to `.cursor/mcp.json` in your workspace or global Cursor settings:

```json
{
  "mcpServers": {
    "pagedock": {
      "command": "npx",
      "args": ["-y", "pagedock-mcp"],
      "env": {
        "PAGEDOCK_BASE_URL": "http://localhost:8080"
      }
    }
  }
}
```

---

### 3. Antigravity & Gemini CLI (`~/.gemini/antigravity-cli/config.json` or CLI)

Add using the Antigravity CLI:

```bash
agy mcp add pagedock npx -y pagedock-mcp --env PAGEDOCK_BASE_URL=http://localhost:8080
```

Or configure manually in your MCP settings file:

```json
{
  "mcpServers": {
    "pagedock": {
      "command": "npx",
      "args": ["-y", "pagedock-mcp"],
      "env": {
        "PAGEDOCK_BASE_URL": "http://localhost:8080"
      }
    }
  }
}
```

---

### 4. Claude Code CLI

Add to Claude Code with the `claude mcp` command:

```bash
claude mcp add pagedock npx -y pagedock-mcp --env PAGEDOCK_BASE_URL=http://localhost:8080
```

---

## 🛠️ Tool Reference (10 Production Tools)

| Tool | Category | Description | Example Arguments |
| :--- | :--- | :--- | :--- |
| `deploy_html` | Deployment | Deploy a single-file HTML website or update an existing site. Returns preview and editor URLs. | `{"html": "<!DOCTYPE html><html><body><h1>Hello World</h1></body></html>", "slug": "my-landing", "title": "My Landing Page"}` |
| `list_sites` | Site Management | List all hosted sites on PageDock with view statistics, slugs, and live URLs. | `{}` |
| `get_site` | Site Management | Get full metadata, entry file, file count, storage size, and URLs for a specific site. | `{"slug": "my-landing"}` |
| `pull_site` | Site Management | Pull raw HTML source code of a hosted site on PageDock for inspection or editing. | `{"slug": "my-landing"}` |
| `delete_site` | Site Management | Permanently delete a hosted site from PageDock. | `{"slug": "my-landing"}` |
| `update_site_file` | Surgical Patching | Update or patch a specific file (`index.html`, `style.css`, etc.) and create an immutable snapshot. | `{"slug": "my-landing", "path": "index.html", "content": "<h1>Updated Hero</h1>", "summary": "Update hero header"}` |
| `list_versions` | Versioning | List historical immutable snapshots for a site with timestamps and change summaries. | `{"slug": "my-landing"}` |
| `rollback_site` | Versioning | Rollback site to a previous version. If `version` is omitted, performs 1-click rollback to immediate previous version. | `{"slug": "my-landing", "version": 2}` |
| `get_site_analytics` | Insights | Query visitor analytics, page views, unique visitors, top referrers, and device breakdown. | `{"slug": "my-landing", "days": 30}` |
| `list_form_submissions`| Insights | List contact form submissions and lead captures, with optional spam filtering. | `{"slug": "my-landing", "isSpam": false}` |

---

## 📖 Detailed Tool Documentation & Examples

### 1. `deploy_html`
Deploy a static HTML site directly to PageDock.

**Parameters:**
- `html` (string, **required**): Raw HTML markup of the website.
- `title` (string, optional): Human-friendly title of the site.
- `slug` (string, optional): Custom URL slug (alphanumeric and hyphens).
- `ttl` (string, optional): Expiry duration (e.g., `"7d"`, `"30d"`, `"never"`).

**Example Response:**
```json
{
  "success": true,
  "slug": "portfolio-preview",
  "liveUrl": "http://localhost:8080/s/portfolio-preview/",
  "editorUrl": "http://localhost:8080/editor/portfolio-preview",
  "message": "Site deployed successfully to PageDock. Live preview ready."
}
```

---

### 2. `list_sites`
List all sites currently hosted on the PageDock instance.

**Parameters:** None required.

**Example Response:**
```json
{
  "success": true,
  "count": 2,
  "sites": [
    {
      "slug": "ai-portfolio",
      "title": "Agent Portfolio",
      "views": 142,
      "liveUrl": "http://localhost:8080/s/ai-portfolio/",
      "editorUrl": "http://localhost:8080/editor/ai-portfolio"
    }
  ]
}
```

---

### 3. `get_site`
Inspect detailed metadata and storage statistics for a site.

**Parameters:**
- `slug` (string, **required**): Unique site slug.

---

### 4. `pull_site`
Retrieve the full HTML source code of a live site for agent analysis or refactoring.

**Parameters:**
- `slug` (string, **required**): Unique site slug.

---

### 5. `delete_site`
Permanently remove a site and its hosted assets.

**Parameters:**
- `slug` (string, **required**): Unique site slug.

---

### 6. `update_site_file`
Surgically patch a single file in a hosted site without re-uploading everything, creating an automated version snapshot.

**Parameters:**
- `slug` (string, **required**): Unique site slug.
- `content` (string, **required**): New file contents.
- `path` (string, optional): File path within site (default: `"index.html"`).
- `summary` (string, optional): Snapshot change summary.

---

### 7. `list_versions`
Retrieve snapshot history for auditing or rollback decision-making.

**Parameters:**
- `slug` (string, **required**): Unique site slug.

---

### 8. `rollback_site`
Revert live site to a previous snapshot.

**Parameters:**
- `slug` (string, **required**): Unique site slug.
- `version` (integer, optional): Specific version number to restore. If omitted, restores immediate previous version.

---

### 9. `get_site_analytics`
Query traffic metrics and visitor engagement.

**Parameters:**
- `slug` (string, **required**): Unique site slug.
- `days` (integer, optional): Lookback window in days (default: `30`).

---

### 10. `list_form_submissions`
Fetch captured contact form inquiries and submissions.

**Parameters:**
- `slug` (string, **required**): Unique site slug.
- `isSpam` (boolean, optional): Set to `false` for genuine leads, `true` for spam leads (default: `false`).

---

## 🧪 Testing

Run all unit and stdio protocol integration tests:

```bash
npm test
```

Or run individual test suites:
```bash
# Test PageDock REST API Client
node tests/test_mcp_client.js

# Test Deploy & Site Tools
node tests/test_deploy_tools.js

# Test Patch & Version Tools
node tests/test_patch_tools.js

# Test Analytics & Form Lead Tools
node tests/test_insights_tools.js

# Test MCP Stdio JSON-RPC Protocol & 10 Tools Listing
node tests/test_stdio_protocol.js
```

---

## 📄 License

MIT © [PageDock Team](https://github.com/your-org/pagedock)
