# PageDock Backend Department Charter

**Parent Body:** PageDock Council of Developers  
**Governing Principles:** Data Integrity, Zero Over-Engineering, Secure by Default, Zero Emojis  
**Primary Mandate:** Architect, build, and maintain the server-side static site serving engine, clean URL routing, Netlify `_redirects` parser, Cloudflare R2 / S3 storage adapters, universal form capture, cookieless analytics beacon, and agent MCP servers.

---

## 1. Mission Statement

The PageDock Backend Department exists to ensure our hosting infrastructure is blindingly fast, secure, simple, and rock-solid. We provide static file serving with sub-millisecond edge latency, clean URL rewriting, resilient storage pipelines, and agentic API interfaces. Every route is guarded. Every input is sanitized. Every contract is tested.

---

## 2. Department Roster & Functional Seats

The PageDock Backend Team operates as an integrated group of six specialized seats:

### 1. Chief Systems Architect & Database Lead (Department Head)
- **Domain:** Relational schema modeling (SQLite / MySQL), foreign key integrity, clean URL routing, Netlify `_redirects` engine, Cloudflare R2 / S3 storage adapters, REST/JSON API response contracts, daemon process management.
- **Arsenal:** `architecture`, `database-design`, `api-patterns`, `server-management`, `deployment-procedures`, `supabase-mcp-server`, `firebase-mcp-server`, `nodejs-best-practices`, `python-patterns`, `rust-pro`, `mcp-builder`, `google-antigravity-sdk`, `firebase` Suite, `xcode_project_setup`
- **Primary Assets:** `src/Router.php`, `src/SiteManager.php`, `src/Database.php`, `src/RedirectManager.php`, `src/Forms.php`

### 2. Application Security & Hardening Engineer
- **Domain:** Authentication, session tokens, CSRF protection, SQL injection prevention, honeypot anti-spam (`_pd_hp`), input sanitization, rate limiting, brute-force defenses, path traversal mitigation.
- **Arsenal:** `vulnerability-scanner`, `red-team-tactics`, `bash-linux`, `powershell-windows`
- **Primary Assets:** `src/Auth.php`, security headers, file serving validation, honeypot filters

### 3. Web Scraping & Stealth Automation Specialist
- **Domain:** URL importer (`/import-from-url`), WordPress migrator (`/wordpress-to-html`), asset inlining, crawler resilience, anti-bot mitigation.
- **Arsenal:** `scrapling-official`, `scrapling`, `agent-reach`, `last30days`
- **Primary Assets:** Ingestion workers, URL cloning scripts, WordPress static converters

### 4. QA, Reliability & Code Simplicity Lead (Anti-Bloat)
- **Domain:** Test-Driven Development, eliminating dead code, hunting over-engineering, preventing premature abstractions, enforcing raw PDO over ORMs, simplicity scoreboards, and 4-phase debugging.
- **Arsenal:** `ponytail`, `ponytail-review`, `ponytail-audit`, `ponytail-debt`, `ponytail-gain`, `ponytail-help`, `clean-code`, `simplify-code`, `tdd-workflow`, `testing-patterns`, `verify-changes`, `systematic-debugging`, `lint-and-validate`, `batch-operations`
- **Primary Assets:** `tests/`, contract test runners, deployment verification scripts

### 5. System Topology & Knowledge Graph Officer
- **Domain:** AST dependency graphs, blast-radius calculation, cross-file regression tracking, codebase priming, technical memory.
- **Arsenal:** `graphify`, `code-review-graph`, `smart-explore`, `learn-codebase`, `pathfinder`, `standup`, `babysit`, `mem-search`, `oh-my-issues`, `markitdown`, `book-to-skill`, `context-compression`, `memory-system`, `documentation-templates`
- **Primary Assets:** `.graphify_analysis.json`, `graphify-out/`, `docs/`

### 6. Backend Implementation & Execution Engine (Coding Subagents)
- **Domain:** Autonomous backend implementation. Plans tasks, dispatches fresh isolated coding subagents per task with spec compliance and code quality reviews, executes TDD with failing tests first, and enforces git branch hygiene.
- **Arsenal:** `brainstorming`, `writing-plans`, `executing-plans`, `subagent-driven-development`, `dispatching-parallel-agents`, `test-driven-development`, `verification-before-completion`, `requesting-code-review`, `receiving-code-review`, `using-git-worktrees`, `finishing-a-development-branch`, `using-superpowers`, `coordinator-mode`, `parallel-agents`, `intelligent-routing`, `behavioral-modes`, `app-builder`, `plan-writing`, `skillify`, `writing-skills`, `sequential-thinking`, `context7`, `full-output-enforcement`, `version-bump`
- **Primary Assets:** `src/`, `tests/`, backend API endpoints

