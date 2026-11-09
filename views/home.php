<!-- PageDock Sovereign Static Web Platform Landing View -->

<!-- Hero Section -->
<div class="hero-section">
    <div class="hero-bg-grid"></div>
    <div class="hero-content">
        <!-- Live Edge Status Pill -->
        <div class="hero-announcement">
            <span class="pulse-dot"></span>
            <span class="announcement-text">Instant Static Web Hosting &middot; Zero Build Queues</span>
            <a href="<?= url('docs') ?>" class="announcement-link">Architecture Spec &rarr;</a>
        </div>

        <h1 class="hero-title">
            Host your HTML.<br>
            <span class="accent-text">Deploy in 400 milliseconds.</span>
        </h1>
        <p class="hero-subtitle">
            The sovereign developer host for static websites, landing pages, and AI-generated frontends. 
            Drop a single file or a 50MB ZIP folder. Client-side unpacked in milliseconds with zero backend required.
        </p>

        <!-- Technical Credibility Stat Chips -->
        <div class="hero-stats-row">
            <span class="hero-stat-chip">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                0ms Queue Latency
            </span>
            <span class="hero-stat-chip">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                Permanent Free HTTPS
            </span>
            <span class="hero-stat-chip">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                Universal Form Engine
            </span>
            <span class="hero-stat-chip">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                Cookieless Analytics
            </span>
        </div>

        <!-- Main Deployment Command Dock -->
        <div class="deploy-card">
            <div class="deploy-tabs">
                <button type="button" class="tab-btn active" data-tab="upload-tab">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    <span>Upload <span class="tab-desktop-sub">Files / ZIP</span></span>
                </button>
                <button type="button" class="tab-btn" data-tab="paste-tab">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
                    <span>Paste <span class="tab-desktop-sub">HTML</span></span>
                </button>
                <button type="button" class="tab-btn" data-tab="cli-tab">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="4 17 10 11 4 5"/><line x1="12" y1="19" x2="20" y2="19"/></svg>
                    <span>CLI <span class="tab-desktop-sub">&amp; Agents</span></span>
                </button>
                <button type="button" class="tab-btn" data-tab="demo-tab">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polygon points="10 8 16 12 10 16 10 8"/></svg>
                    <span><span class="tab-desktop-sub">1-Click </span>Demo</span>
                </button>
            </div>

            <!-- Tab 1: Upload Files & ZIP -->
            <div id="upload-tab" class="tab-content active">
                <form id="upload-form" class="dropzone" enctype="multipart/form-data">
                    <input type="file" id="file-input" name="file" accept=".html,.htm,.zip" class="hidden-input">
                    <div class="dropzone-inner" id="dropzone-trigger">
                        <div class="upload-icon">
                            <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 14.899A7 7 0 1 1 15.71 8h1.79a4.5 4.5 0 0 1 2.5 8.242"/><path d="M12 12v9"/><path d="m8 16 4-4 4 4"/></svg>
                        </div>
                        <p class="dropzone-title">Drop <strong>.html</strong>, <strong>.zip</strong> archive, or folder</p>
                        <p class="dropzone-desc">Automatic client-side root unwrapping. Multi-page sites, CSS, JS, fonts, and images up to 50MB.</p>
                    </div>

                    <div class="custom-slug-row">
                        <input type="text" name="title" id="upload-title" placeholder="Site title (optional)" class="input-text">
                        <div class="slug-input-wrapper">
                            <span class="slug-prefix">/s/</span>
                            <input type="text" name="slug" id="upload-slug" placeholder="custom-slug" class="input-slug">
                        </div>
                    </div>

                    <button type="submit" id="upload-submit-btn" class="btn-primary w-full">Deploy Website to Edge</button>
                </form>
            </div>

            <!-- Tab 2: Paste HTML -->
            <div id="paste-tab" class="tab-content">
                <form id="paste-form">
                    <div class="preset-chips-row">
                        <span class="preset-label">Insert Starter:</span>
                        <button type="button" class="preset-chip-btn" data-preset="waitlist">SaaS Waitlist</button>
                        <button type="button" class="preset-chip-btn" data-preset="portfolio">Developer Bio</button>
                        <button type="button" class="preset-chip-btn" data-preset="docs">Mini Docs</button>
                    </div>
                    <div class="custom-slug-row mb-3">
                        <input type="text" name="title" id="paste-title" placeholder="Site title (e.g. Acme Studio)" class="input-text">
                        <div class="slug-input-wrapper">
                            <span class="slug-prefix">/s/</span>
                            <input type="text" name="slug" id="paste-slug" placeholder="custom-slug" class="input-slug">
                        </div>
                    </div>
                    <div class="code-editor-box">
                        <textarea name="html" id="paste-html" placeholder="<!DOCTYPE html>&#10;<html lang=&quot;en&quot;>&#10;<head>&#10;  <meta charset=&quot;UTF-8&quot;>&#10;  <title>Acme Studio</title>&#10;  <style>body { font-family: system-ui; background: #0c0e12; color: #fff; padding: 40px; text-align: center; }</style>&#10;</head>&#10;<body>&#10;  <h1>Built with PageDock</h1>&#10;  <p>Static hosting with zero configuration.</p>&#10;</body>&#10;</html>" rows="10" class="textarea-code"></textarea>
                    </div>
                    <button type="submit" class="btn-primary w-full mt-3">Deploy Pasted HTML</button>
                </form>
            </div>

            <!-- Tab 3: CLI & Agent Deployment -->
            <div id="cli-tab" class="tab-content">
                <div class="cli-container">
                    <p class="cli-desc">Deploy directly from your local terminal, CI/CD pipeline, or AI coding assistant (Cursor, Claude Code, Antigravity) via the Model Context Protocol (MCP).</p>
                    <div class="cli-command-box">
                        <div class="cli-command-text-wrap">
                            <span class="cli-prompt">$</span>
                            <span id="cli-copy-text">npx pagedock-cli deploy ./dist --slug my-portfolio</span>
                        </div>
                        <button type="button" id="copy-cli-btn" class="cli-btn-copy">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                            <span>Copy</span>
                        </button>
                    </div>
                    <div class="cli-output-simulation">
                        <div>[PageDock CLI v1.2] Scanning directory ./dist (8 files, 412 KB)...</div>
                        <div>[Deduplication] Content-addressable SHA-256 match found: 6/8 cached.</div>
                        <div>[Upload] Transferred 2 modified assets directly to edge storage.</div>
                        <div class="cli-line-success">[Deploy OK] Live at: <span class="cli-line-url">https://pagedock.freedev.app/s/my-portfolio</span> (380ms)</div>
                    </div>
                </div>
            </div>

            <!-- Tab 4: 1-Click Demo -->
            <div id="demo-tab" class="tab-content">
                <div class="demo-box">
                    <h3>Deploy a Complete Developer Portfolio</h3>
                    <p>Includes a clean dark-mode layout, responsive projects showcase, and an active zero-backend contact form connected to <code>/__pd/form</code>.</p>
                    <button type="button" id="launch-demo-btn" class="btn-primary">Launch Demo Template Now</button>
                </div>
            </div>

            <!-- Upload & Unpack Progress Bar -->
            <div id="deploy-status" class="deploy-status hidden">
                <div class="spinner"></div>
                <span id="deploy-status-text">Uploading and unpacking site...</span>
            </div>

            <!-- Deployment Success Card -->
            <div id="deploy-success" class="deploy-success hidden">
                <div class="success-header">
                    <div class="success-icon">&check;</div>
                    <div>
                        <h4>Your static site is live on the edge!</h4>
                        <p class="success-sub">Permanently hosted with HTTPS, zero expiration, and active form capture.</p>
                    </div>
                </div>
                <div class="url-copy-box">
                    <input type="text" id="live-url-input" readonly value="" class="url-input">
                    <button type="button" id="copy-url-btn" class="btn-copy">Copy Link</button>
                </div>
                <div class="action-buttons">
                    <a href="#" id="view-site-link" target="_blank" class="btn-primary">View Live Site &rarr;</a>
                    <a href="<?= url('dashboard') ?>" id="open-editor-link" class="btn-secondary">Manage Deployments</a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Section: Interactive Live Split-Screen Workspace Showcase -->
<section class="showcase-section">
    <div class="section-header">
        <span class="badge">In-Browser Live Workspace</span>
        <h2>Inspect &amp; Edit at 60 FPS</h2>
        <p>Edit HTML, tweak CSS variables, test forms, and preview multi-viewport responsive behavior with zero reload delay.</p>
    </div>

    <div class="showcase-window">
        <!-- Window Chrome Bar -->
        <div class="window-chrome">
            <div class="window-controls">
                <div class="win-dot red"></div>
                <div class="win-dot yellow"></div>
                <div class="win-dot green"></div>
            </div>
            <div class="window-center-title desktop-only">
                <span>pagedock-live-editor</span>
                <span class="window-badge">IndexedDB Sync</span>
            </div>

            <!-- Mobile Mode Switcher (Live Preview vs Source Code) -->
            <div class="showcase-mobile-switcher mobile-only" id="showcase-mobile-switcher">
                <button type="button" class="mobile-switch-btn active" data-showcase-mode="preview">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                    <span>Preview</span>
                </button>
                <button type="button" class="mobile-switch-btn" data-showcase-mode="code">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
                    <span>Code</span>
                </button>
            </div>

            <div class="viewport-switchers desktop-only">
                <button type="button" class="viewport-btn active" data-viewport="desktop">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                    <span>1440px</span>
                </button>
                <button type="button" class="viewport-btn" data-viewport="tablet">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="2" width="16" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
                    <span>768px</span>
                </button>
                <button type="button" class="viewport-btn" data-viewport="mobile">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
                    <span>375px</span>
                </button>
            </div>
        </div>

        <!-- Split Workspace: Code on Left, Live Render on Right -->
        <div class="split-workspace">
            <div class="editor-pane">
                <div class="editor-tabs-strip">
                    <div class="editor-tab-item active" data-editor-file="html">index.html</div>
                    <div class="editor-tab-item" data-editor-file="css">style.css</div>
                    <div class="editor-tab-item" data-editor-file="redirects">_redirects</div>
                </div>
                <div class="editor-code-body" id="showcase-code-body">
                    <div class="code-line"><span class="line-num">1</span><span class="code-text"><span class="tok-comment">&lt;!-- pd:shared header --&gt;</span></span></div>
                    <div class="code-line"><span class="line-num">2</span><span class="code-text"><span class="tok-tag">&lt;header</span> <span class="tok-attr">class</span>=<span class="tok-str">"navbar"</span><span class="tok-tag">&gt;</span></span></div>
                    <div class="code-line"><span class="line-num">3</span><span class="code-text">&nbsp;&nbsp;<span class="tok-tag">&lt;a</span> <span class="tok-attr">href</span>=<span class="tok-str">"/"</span> <span class="tok-attr">class</span>=<span class="tok-str">"brand"</span><span class="tok-tag">&gt;</span>Studio<span class="tok-tag">&lt;/a&gt;</span></span></div>
                    <div class="code-line"><span class="line-num">4</span><span class="code-text">&nbsp;&nbsp;<span class="tok-tag">&lt;span</span> <span class="tok-attr">class</span>=<span class="tok-str">"pill"</span><span class="tok-tag">&gt;</span>VIP Waitlist<span class="tok-tag">&lt;/span&gt;</span></span></div>
                    <div class="code-line"><span class="line-num">5</span><span class="code-text"><span class="tok-tag">&lt;/header&gt;</span></span></div>
                    <div class="code-line"><span class="line-num">6</span><span class="code-text"><span class="tok-comment">&lt;!-- /pd:shared header --&gt;</span></span></div>
                    <div class="code-line"><span class="line-num">7</span><span class="code-text"></span></div>
                    <div class="code-line"><span class="line-num">8</span><span class="code-text"><span class="tok-tag">&lt;main</span> <span class="tok-attr">class</span>=<span class="tok-str">"hero"</span><span class="tok-tag">&gt;</span></span></div>
                    <div class="code-line"><span class="line-num">9</span><span class="code-text">&nbsp;&nbsp;<span class="tok-tag">&lt;h1&gt;</span>Next-Generation UI Systems<span class="tok-tag">&lt;/h1&gt;</span></span></div>
                    <div class="code-line"><span class="line-num">10</span><span class="code-text">&nbsp;&nbsp;<span class="tok-tag">&lt;form</span> <span class="tok-attr">action</span>=<span class="tok-str">"/__pd/form"</span> <span class="tok-attr">method</span>=<span class="tok-str">"POST"</span><span class="tok-tag">&gt;</span></span></div>
                    <div class="code-line"><span class="line-num">11</span><span class="code-text">&nbsp;&nbsp;&nbsp;&nbsp;<span class="tok-tag">&lt;input</span> <span class="tok-attr">type</span>=<span class="tok-str">"hidden"</span> <span class="tok-attr">name</span>=<span class="tok-str">"_form"</span> <span class="tok-attr">value</span>=<span class="tok-str">"VIP Waitlist"</span><span class="tok-tag">&gt;</span></span></div>
                    <div class="code-line"><span class="line-num">12</span><span class="code-text">&nbsp;&nbsp;&nbsp;&nbsp;<span class="tok-tag">&lt;input</span> <span class="tok-attr">type</span>=<span class="tok-str">"email"</span> <span class="tok-attr">name</span>=<span class="tok-str">"email"</span> <span class="tok-attr">placeholder</span>=<span class="tok-str">"you@domain.com"</span> <span class="tok-attr">required</span><span class="tok-tag">&gt;</span></span></div>
                    <div class="code-line"><span class="line-num">13</span><span class="code-text">&nbsp;&nbsp;&nbsp;&nbsp;<span class="tok-tag">&lt;button</span> <span class="tok-attr">type</span>=<span class="tok-str">"submit"</span><span class="tok-tag">&gt;</span>Claim Access &amp;rarr;<span class="tok-tag">&lt;/button&gt;</span></span></div>
                    <div class="code-line"><span class="line-num">14</span><span class="code-text">&nbsp;&nbsp;<span class="tok-tag">&lt;/form&gt;</span></span></div>
                    <div class="code-line"><span class="line-num">15</span><span class="code-text"><span class="tok-tag">&lt;/main&gt;</span></span></div>
                </div>
                <div class="mobile-pane-footer mobile-only">
                    <button type="button" class="mobile-switch-action-btn" id="showcase-switch-to-preview">
                        View Live Preview &rarr;
                    </button>
                </div>
            </div>

            <div class="preview-pane">
                <div class="preview-address-bar">
                    <div class="address-bar-pill">
                        <span class="preview-ssl-icon">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        </span>
                        <span class="preview-url-text" id="showcase-preview-url">https://pagedock.freedev.app/s/acme-studio</span>
                    </div>
                    <span class="preview-device-badge desktop-only" id="preview-viewport-label">1440px &times; 900px</span>
                </div>
                <div class="preview-viewport-content" id="mock-preview-container">
                    <div class="mock-browser-canvas">
                        <div class="mock-navbar">
                            <div class="mock-nav-brand">
                                <span class="mock-brand-dot"></span>
                                <span>Studio</span>
                            </div>
                            <div class="mock-nav-links">
                                <span class="mock-nav-pill">VIP Waitlist</span>
                            </div>
                        </div>
                        <div class="mock-hero-body">
                            <span class="mock-tag" id="showcase-status-tag">Live Sandbox</span>
                            <h3 class="mock-h1" id="showcase-preview-title">Next-Generation UI Systems</h3>
                            <p class="mock-p" id="showcase-preview-desc">Zero-build client-side previews with live responsive viewport toggles and built-in form capture.</p>
                            <div class="mock-form-row" id="showcase-preview-action">
                                <input type="email" placeholder="you@domain.com" class="mock-input" value="lead@venture.com" readonly>
                                <button type="button" class="mock-btn">Claim Access &rarr;</button>
                            </div>
                            <div class="mock-form-indicator">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                                <span>Endpoint: <code>/__pd/form</code> &middot; Instant CSV Capture</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mobile-pane-footer mobile-only">
                    <button type="button" class="mobile-switch-action-btn" id="showcase-switch-to-code">
                        View HTML Source &rarr;
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- 4 High-Craft Capability Cards -->
    <div class="showcase-feature-strip">
        <div class="showcase-chip-card">
            <div class="showcase-chip-icon">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
            </div>
            <div>
                <h4>Client-Side Ingestion</h4>
                <p>Browser unzips archives and scans assets in memory via <code>fflate</code>. No server upload limit choke.</p>
            </div>
        </div>

        <div class="showcase-chip-card">
            <div class="showcase-chip-icon">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>
            </div>
            <div>
                <h4>IndexedDB Draft Cache</h4>
                <p>Full offline working state cached in browser memory. Disconnects will never destroy your work.</p>
            </div>
        </div>

        <div class="showcase-chip-card">
            <div class="showcase-chip-icon">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            </div>
            <div>
                <h4>Multi-Page Shared Blocks</h4>
                <p><code>&lt;!-- pd:shared --&gt;</code> markers sync navigation bars and footers across every static page.</p>
            </div>
        </div>

        <div class="showcase-chip-card">
            <div class="showcase-chip-icon">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg>
            </div>
            <div>
                <h4>Immutable Snapshots</h4>
                <p>Revisions are versioned as discrete builds. One-click instant rollback with zero data loss.</p>
            </div>
        </div>
    </div>
</section>

<!-- Section: Asymmetric 6-Card Sovereign Bento Grid -->
<section class="bento-section">
    <div class="section-header">
        <span class="badge">The Architecture</span>
        <h2>Engineered for Static Supremacy</h2>
        <p>Everything your static websites need, built directly into the edge router.</p>
    </div>

    <div class="bento-asymmetric">
        <!-- Bento Tile 1: Zero-Build Architecture (2-column span) -->
        <div class="bento-tile bento-span-2">
            <div class="bento-top-row">
                <span class="bento-badge-tag">Zero Server Build Step</span>
                <div class="bento-icon-box">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                </div>
            </div>
            <h3>Skip the 90-Second Build Queue</h3>
            <p>
                Static hosts force static HTML and compiled CSS through heavy Node.js or Webpack containers. 
                PageDock executes archive decompression client-side, runs asset deduplication, and pushes directly to edge storage. 
                Your site is globally reachable in under 400 milliseconds.
            </p>
            <div class="pipeline-flow">
                <div class="pipeline-node">
                    <span class="pipeline-badge">Raw ZIP</span>
                    <span class="pipeline-sub">50MB Archive</span>
                </div>
                <span class="pipeline-arrow">&rarr;</span>
                <div class="pipeline-node active">
                    <span class="pipeline-badge">Browser fflate</span>
                    <span class="pipeline-sub">In-Memory (0ms)</span>
                </div>
                <span class="pipeline-arrow">&rarr;</span>
                <div class="pipeline-node">
                    <span class="pipeline-badge">SHA-256 Dedupe</span>
                    <span class="pipeline-sub">Skip Existing</span>
                </div>
                <span class="pipeline-arrow">&rarr;</span>
                <div class="pipeline-node active">
                    <span class="pipeline-badge">Edge CDN</span>
                    <span class="pipeline-sub">Global 200 OK</span>
                </div>
            </div>
        </div>

        <!-- Bento Tile 2: Universal Forms Engine -->
        <div class="bento-tile">
            <div class="bento-top-row">
                <span class="bento-badge-tag">POST /__pd/form</span>
                <div class="bento-icon-box">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                </div>
            </div>
            <h3>Zero-Backend Forms</h3>
            <p>
                Point any standard HTML <code>&lt;form&gt;</code> to PageDock. Includes automated honeypot spam traps, anti-phishing credit card guards, and instant CSV / Google Sheets export.
            </p>
            <div class="interactive-feature-widget">
                <div style="color:var(--accent); font-weight:700; margin-bottom:4px;">&check; Honeypot Anti-Spam Active</div>
                <div style="color:var(--fg-muted);">Spam filtered silently. 1-click export: <code>form-submissions.csv</code></div>
            </div>
        </div>

        <!-- Bento Tile 3: Cookieless Privacy Analytics -->
        <div class="bento-tile">
            <div class="bento-top-row">
                <span class="bento-badge-tag">GET /__pd/v</span>
                <div class="bento-icon-box">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                </div>
            </div>
            <h3>Cookieless Analytics</h3>
            <p>
                Lightweight edge beacon injected automatically. Track daily unique visitors, top referral domains, countries, and devices without intrusive cookie consent banners.
            </p>
            <div class="interactive-feature-widget">
                <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                    <span style="color:var(--fg-muted);">Live Views</span>
                    <span style="color:var(--accent); font-weight:700;">+2,410 today</span>
                </div>
                <div class="analytics-sparkline">
                    <div class="spark-bar" style="height:30%;"></div>
                    <div class="spark-bar" style="height:45%;"></div>
                    <div class="spark-bar" style="height:35%;"></div>
                    <div class="spark-bar" style="height:70%;"></div>
                    <div class="spark-bar" style="height:55%;"></div>
                    <div class="spark-bar" style="height:85%;"></div>
                    <div class="spark-bar accent" style="height:100%;"></div>
                </div>
            </div>
        </div>

        <!-- Bento Tile 4: Multi-Page Shared Components -->
        <div class="bento-tile">
            <div class="bento-top-row">
                <span class="bento-badge-tag">&lt;!-- pd:shared --&gt;</span>
                <div class="bento-icon-box">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="8" rx="2" ry="2"/><rect x="2" y="14" width="20" height="8" rx="2" ry="2"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/></svg>
                </div>
            </div>
            <h3>Multi-Page Layout Sync</h3>
            <p>
                Static websites often require copying navigation and footer HTML across 10 different files. 
                Our normalization engine detects shared blocks and offers 1-click synchronization across your whole site.
            </p>
            <div class="interactive-feature-widget">
                <span style="color:var(--accent);">Auto-sync:</span> <code>&lt;!-- pd:shared header --&gt;</code><br>
                <span style="color:var(--fg-muted);">Propagates edits to /about, /blog, /contact instantly.</span>
            </div>
        </div>

        <!-- Bento Tile 5: Clean URLs & _redirects -->
        <div class="bento-tile">
            <div class="bento-top-row">
                <span class="bento-badge-tag">301 Canonical Engine</span>
                <div class="bento-icon-box">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                </div>
            </div>
            <h3>Clean URLs &amp; _redirects</h3>
            <p>
                Automatic 301 rewrites from <code>/about.html</code> to clean <code>/about</code>, preserving query strings. 
                Full Netlify-compatible <code>_redirects</code> support with wildcards (<code>/blog/* /posts/:splat 301</code>).
            </p>
            <div class="interactive-feature-widget">
                <div style="color:var(--fg);"><code>/page.html</code> &rarr; <code>/page</code> (HTTP 301)</div>
                <div style="color:var(--fg-muted); margin-top:2px;">Custom <code>404.html</code> served with 404 header.</div>
            </div>
        </div>

        <!-- Bento Tile 6: AI Agent MCP Protocol & CLI (Full 3-column span) -->
        <div class="bento-tile bento-span-3">
            <div class="bento-top-row">
                <span class="bento-badge-tag">MCP &middot; Model Context Protocol</span>
                <div class="bento-icon-box">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2a10 10 0 1 0 10 10H12V2z"/><path d="M12 2a10 10 0 0 1 10 10h-10V2z"/><path d="M12 12L2.1 12a10 10 0 0 0 19.8 0H12z"/></svg>
                </div>
            </div>
            <h3>Built for Autonomous AI Coding Agents</h3>
            <p>
                PageDock exposes a first-class Model Context Protocol (MCP) server for Cursor, Claude Code, Antigravity, and custom LLM workflows. 
                Your AI agent can create sites, deploy multi-asset codebases, inspect visitor analytics, and pull live form submissions through standardized tool calls.
            </p>
            <div class="mcp-tools-row">
                <span class="mcp-tool-chip">
                    <code>pagedock_deploy_site</code>
                </span>
                <span class="mcp-tool-chip">
                    <code>pagedock_get_analytics</code>
                </span>
                <span class="mcp-tool-chip">
                    <code>pagedock_export_forms</code>
                </span>
                <span class="mcp-tool-chip">
                    <code>pagedock_rollback_version</code>
                </span>
            </div>
        </div>
    </div>
</section>

<!-- Section: How PageDock Works (3-Step Visual Flow) -->
<section class="how-it-works-section">
    <div class="section-header">
        <span class="badge">Workflow</span>
        <h2>How PageDock Works</h2>
        <p>From local HTML or ZIP to a globally distributed HTTPS link in three effortless steps.</p>
    </div>

    <div class="steps-grid">
        <!-- Step 1 -->
        <div class="step-card">
            <div class="step-header">
                <span class="step-number">01</span>
                <span class="step-badge">Instant Ingestion</span>
            </div>
            <div class="step-visual">
                <div class="step-icon-box">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                </div>
                <div class="step-pills">
                    <span class="file-pill">index.html</span>
                    <span class="file-pill">style.css</span>
                    <span class="file-pill">bundle.zip</span>
                </div>
            </div>
            <h3>Drop Your Code</h3>
            <p>Upload a standalone HTML page, paste code directly, or drag in a 50MB ZIP folder. Your files are decompressed client-side in your browser in milliseconds.</p>
        </div>

        <!-- Step 2 -->
        <div class="step-card">
            <div class="step-header">
                <span class="step-number">02</span>
                <span class="step-badge">Real-Time Canvas</span>
            </div>
            <div class="step-visual">
                <div class="step-icon-box">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
                </div>
                <div class="step-preview-tag">
                    <span>60 FPS Live Sync</span>
                </div>
            </div>
            <h3>Inspect &amp; Tweak Live</h3>
            <p>Fine-tune CSS, test responsive viewports on desktop and mobile, and connect contact forms without spinning up a local development server.</p>
        </div>

        <!-- Step 3 -->
        <div class="step-card">
            <div class="step-header">
                <span class="step-number">03</span>
                <span class="step-badge">Production Edge</span>
            </div>
            <div class="step-visual">
                <div class="step-icon-box">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                </div>
                <div class="step-url-pill">
                    <span>https://pagedock.freedev.app/s/live</span>
                </div>
            </div>
            <h3>Share Your Live Link</h3>
            <p>Publish an instant, permanent HTTPS link with built-in form capture and cookieless visitor analytics. Zero subscription lock-in, zero build delays.</p>
        </div>
    </div>
</section>

<!-- Section: Developer FAQ Accordion -->
<section class="faq-section">
    <div class="section-header">
        <span class="badge">FAQ</span>
        <h2>Frequently Asked Questions</h2>
        <p>Everything you need to know about hosting and managing static sites on PageDock.</p>
    </div>

    <div class="faq-list">
        <div class="faq-item">
            <button type="button" class="faq-question-btn">
                <span>Do hosted sites ever expire?</span>
                <span class="faq-chevron">&darr;</span>
            </button>
            <div class="faq-answer">
                No. By default, all websites deployed on PageDock remain active permanently with HTTPS. We only apply expiration if you explicitly pass a temporary time-to-live flag (e.g. <code>--ttl 1d</code> or <code>--ttl 7d</code>) via our CLI or API for ephemeral preview deployments.
            </div>
        </div>

        <div class="faq-item">
            <button type="button" class="faq-question-btn">
                <span>How do forms work without a backend?</span>
                <span class="faq-chevron">&darr;</span>
            </button>
            <div class="faq-answer">
                Simply point your form's <code>action</code> attribute to <code>/__pd/form</code> with <code>method="POST"</code>. PageDock handles submission capture, validates honeypot spam protection, blocks credit card harvesting, and stores submissions securely. You can view submissions in your dashboard or download them as an RFC 4180 CSV export at any time.
            </div>
        </div>

        <div class="faq-item">
            <button type="button" class="faq-question-btn">
                <span>What file types and sizes are supported?</span>
                <span class="faq-chevron">&darr;</span>
            </button>
            <div class="faq-answer">
                You can upload standalone <code>.html</code> files or <code>.zip</code> archives up to 50MB containing HTML, CSS, JavaScript, WebP/PNG/SVG images, audio files, and web fonts (WOFF2). Archives are automatically decompressed in your browser and unwrapped if they contain a root folder.
            </div>
        </div>

        <div class="faq-item">
            <button type="button" class="faq-question-btn">
                <span>How do clean URLs and 301 redirects function?</span>
                <span class="faq-chevron">&darr;</span>
            </button>
            <div class="faq-answer">
                PageDock enforces modern URL hygiene automatically: requests to <code>/about.html</code> issue a canonical HTTP 301 redirect to <code>/about</code> while retaining all query strings. In addition, you can drop a standard Netlify-compatible <code>_redirects</code> file into your project to configure custom 301/302 rewrites and wildcard proxy rules.
            </div>
        </div>

        <div class="faq-item">
            <button type="button" class="faq-question-btn">
                <span>Can AI agents deploy sites autonomously?</span>
                <span class="faq-chevron">&darr;</span>
            </button>
            <div class="faq-answer">
                Yes. PageDock features first-class Model Context Protocol (MCP) support. AI assistants like Cursor, Claude Code, and Antigravity can connect via our stdio or HTTP MCP server to generate code, push deployments, query submission inboxes, and inspect analytics programmatically.
            </div>
        </div>
    </div>
</section>

<!-- Section: Bottom Call to Action -->
<section class="bottom-cta-section">
    <div class="cta-banner-card">
        <h2>Deploy Your Static Project in 10 Seconds</h2>
        <p>No credit card required. Drop your HTML or ZIP archive into the dock above or launch our developer CLI.</p>
        <div class="cta-actions">
            <a href="#upload-tab" class="btn-primary" onclick="window.scrollTo({top: 0, behavior: 'smooth'}); return false;">Deploy a Website Now &rarr;</a>
            <a href="<?= url('templates') ?>" class="btn-secondary">Browse HTML Templates</a>
        </div>
    </div>
</section>
