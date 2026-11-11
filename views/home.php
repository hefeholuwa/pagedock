<!-- PageDock Homepage: light, trust-first layout for business owners (styles: public/assets/css/home.css) -->

<!-- 1. Hero: split layout, copy left, live publishing card right -->
<section class="pdh-hero">
    <div class="pdh-container pdh-hero-grid">
        <div class="pdh-hero-copy" data-reveal>
            <span class="pdh-pill">
                <span class="pdh-pill-dot"></span>
                No coding needed
            </span>
            <h1 class="pdh-hero-title">Put your business online. <span class="pdh-hl">Live in seconds.</span></h1>
            <p class="pdh-hero-sub">Pick a template or upload your page. Get a secure link with customer forms and visitor counts built in.</p>
            <a href="<?= url('templates') ?>" class="pdh-text-link">
                Browse templates
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
            </a>
        </div>

        <!-- Main Website Launch Card (all IDs/classes are hooked by app.js) -->
        <div class="deploy-card pdh-card" data-reveal>
            <div class="deploy-tabs pdh-tabs" role="tablist">
                <button type="button" class="tab-btn active" data-tab="upload-tab">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    <span>Upload</span>
                </button>
                <button type="button" class="tab-btn" data-tab="demo-tab">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                    <span>Starter</span>
                </button>
                <button type="button" class="tab-btn" data-tab="paste-tab">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/></svg>
                    <span>Paste</span>
                </button>
                <button type="button" class="tab-btn" data-tab="cli-tab">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="4 17 10 11 4 5"/><line x1="12" y1="19" x2="20" y2="19"/></svg>
                    <span>Developers</span>
                </button>
            </div>

            <!-- Tab 1: Upload -->
            <div id="upload-tab" class="tab-content active">
                <form id="upload-form" class="dropzone" enctype="multipart/form-data">
                    <input type="file" id="file-input" name="file" accept=".html,.htm,.zip" class="hidden-input">
                    <div class="dropzone-inner" id="dropzone-trigger" role="button" tabindex="0" aria-label="Choose a website file to upload">
                        <div class="upload-icon">
                            <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 14.899A7 7 0 1 1 15.71 8h1.79a4.5 4.5 0 0 1 2.5 8.242"/><path d="M12 12v9"/><path d="m8 16 4-4 4 4"/></svg>
                        </div>
                        <p class="dropzone-title">Drop your <strong>.html</strong> or <strong>.zip</strong> file here</p>
                        <p class="dropzone-desc">Or tap to choose from your computer or phone. Up to 50MB.</p>
                    </div>

                    <div class="pdh-field-row">
                        <label class="pdh-field">
                            <span class="pdh-label">Page name</span>
                            <input type="text" name="title" id="upload-title" placeholder="e.g. Glow Skincare" class="input-text">
                        </label>
                        <label class="pdh-field">
                            <span class="pdh-label">Your link</span>
                            <span class="slug-input-wrapper">
                                <span class="slug-prefix">/s/</span>
                                <input type="text" name="slug" id="upload-slug" placeholder="your-brand" class="input-slug">
                            </span>
                        </label>
                    </div>

                    <button type="submit" id="upload-submit-btn" class="btn-primary w-full pdh-btn-lg">Publish Now</button>
                </form>
            </div>

            <!-- Tab 2: 1-Click Starter -->
            <div id="demo-tab" class="tab-content">
                <div class="demo-box">
                    <h3>No file yet? Start with a sample page.</h3>
                    <p>We will publish a ready-made business page with a working quote form so you can see exactly how it works.</p>
                    <div class="pdh-btn-row">
                        <button type="button" id="launch-demo-btn" class="btn-primary pdh-btn-lg">Launch Sample Page</button>
                        <a href="<?= url('templates') ?>" class="btn-secondary pdh-btn-lg">Browse templates</a>
                    </div>
                </div>
            </div>

            <!-- Tab 3: Paste -->
            <div id="paste-tab" class="tab-content">
                <form id="paste-form">
                    <div class="preset-chips-row">
                        <span class="preset-label">Try a starter:</span>
                        <button type="button" class="preset-chip-btn" data-preset="waitlist">Launch waitlist</button>
                        <button type="button" class="preset-chip-btn" data-preset="portfolio">Services page</button>
                        <button type="button" class="preset-chip-btn" data-preset="docs">Store info</button>
                    </div>
                    <div class="pdh-field-row">
                        <label class="pdh-field">
                            <span class="pdh-label">Page name</span>
                            <input type="text" name="title" id="paste-title" placeholder="e.g. Glow Skincare" class="input-text">
                        </label>
                        <label class="pdh-field">
                            <span class="pdh-label">Your link</span>
                            <span class="slug-input-wrapper">
                                <span class="slug-prefix">/s/</span>
                                <input type="text" name="slug" id="paste-slug" placeholder="your-brand" class="input-slug">
                            </span>
                        </label>
                    </div>
                    <label class="pdh-field">
                        <span class="pdh-label">Page code</span>
                        <textarea name="html" id="paste-html" placeholder="Paste the page code from ChatGPT, Claude, Canva or your designer here" rows="7" class="textarea-code"></textarea>
                    </label>
                    <button type="submit" class="btn-primary w-full pdh-btn-lg mt-3">Publish Now</button>
                </form>
            </div>

            <!-- Tab 4: Developers & AI -->
            <div id="cli-tab" class="tab-content">
                <div class="cli-container">
                    <p class="cli-desc">Working with a developer or an AI assistant? Publish straight from the terminal.</p>
                    <div class="cli-command-box">
                        <div class="cli-command-text-wrap">
                            <span class="cli-prompt">$</span>
                            <span id="cli-copy-text">npx pagedock-cli deploy ./dist --slug my-business</span>
                        </div>
                        <button type="button" id="copy-cli-btn" class="cli-btn-copy">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                            <span>Copy</span>
                        </button>
                    </div>
                    <p class="pdh-cli-note">Connect Cursor, Claude or other AI tools too. <a href="<?= url('docs#mcp') ?>">Read the guide</a></p>
                </div>
            </div>

            <!-- Publishing progress -->
            <div id="deploy-status" class="deploy-status hidden" role="status" aria-live="polite">
                <div class="spinner"></div>
                <span id="deploy-status-text">Publishing your website...</span>
            </div>

            <!-- Success -->
            <div id="deploy-success" class="deploy-success hidden" role="status" aria-live="polite">
                <div class="success-header">
                    <div class="success-icon">&check;</div>
                    <div>
                        <h4>Your website is live!</h4>
                        <p class="success-sub">Secure link, customer forms and visitor counts are all switched on.</p>
                    </div>
                </div>
                <div class="url-copy-box">
                    <input type="text" id="live-url-input" readonly value="" class="url-input" aria-label="Your live website link">
                    <button type="button" id="copy-url-btn" class="btn-copy">Copy Link</button>
                </div>
                <div class="action-buttons">
                    <a href="#" id="view-site-link" target="_blank" class="btn-primary">View Website</a>
                    <a href="<?= url('dashboard') ?>" id="open-editor-link" class="btn-secondary">Open Dashboard</a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- 2. Benefit strip: divided row, not cards -->
<section class="pdh-strip" aria-label="What you get">
    <div class="pdh-container pdh-strip-grid">
        <div class="pdh-strip-item">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            <span>Live in seconds</span>
        </div>
        <div class="pdh-strip-item">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            <span>Free secure link</span>
        </div>
        <div class="pdh-strip-item">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
            <span>Customer forms built in</span>
        </div>
        <div class="pdh-strip-item">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
            <span>See who visits</span>
        </div>
    </div>
</section>

<!-- 3. Who it's for: photo + use-case grid -->
<section class="pdh-section">
    <div class="pdh-container pdh-split">
        <figure class="pdh-photo" data-reveal>
            <img src="<?= asset('assets/img/home/owner-boutique.jpg') ?>" alt="A boutique owner smiling at her phone in her fabric shop" width="896" height="1200" loading="lazy">
        </figure>
        <div class="pdh-split-copy" data-reveal>
            <h2 class="pdh-h2">Made for people who sell, not people who code.</h2>
            <p class="pdh-lead">If you can send a WhatsApp message, you can launch a page on PageDock. Pick what you need and go.</p>
            <ul class="pdh-usecases">
                <li><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>Shops &amp; boutiques</li>
                <li><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M18 8h1a4 4 0 0 1 0 8h-1"/><path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"/><line x1="6" y1="1" x2="6" y2="4"/><line x1="10" y1="1" x2="10" y2="4"/><line x1="14" y1="1" x2="14" y2="4"/></svg>Restaurants &amp; food</li>
                <li><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>Events &amp; weddings</li>
                <li><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>Coaches &amp; consultants</li>
                <li><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>Real estate listings</li>
                <li><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>Portfolios &amp; link-in-bio</li>
            </ul>
        </div>
    </div>
</section>

<!-- 4. Studio showcase (interactive; IDs/classes hooked by app.js) -->
<section class="pdh-section pdh-section-soft showcase-section">
    <div class="pdh-container">
        <div class="pdh-center-head" data-reveal>
            <span class="pdh-eyebrow">The Studio</span>
            <h2 class="pdh-h2">Change anything. See it instantly.</h2>
            <p class="pdh-lead">Edit your words, prices and colours, then check how it looks on a phone before you share.</p>
        </div>

        <div class="showcase-window pdh-window" data-reveal>
            <div class="window-chrome">
                <div class="window-controls">
                    <div class="win-dot red"></div>
                    <div class="win-dot yellow"></div>
                    <div class="win-dot green"></div>
                </div>
                <div class="window-center-title desktop-only">
                    <span>PageDock Studio</span>
                    <span class="window-badge">Auto-saved</span>
                </div>

                <div class="showcase-mobile-switcher mobile-only" id="showcase-mobile-switcher">
                    <button type="button" class="mobile-switch-btn active" data-showcase-mode="preview"><span>Preview</span></button>
                    <button type="button" class="mobile-switch-btn" data-showcase-mode="code"><span>Editor</span></button>
                </div>

                <div class="viewport-switchers desktop-only">
                    <button type="button" class="viewport-btn active" data-viewport="desktop">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                        <span>Laptop</span>
                    </button>
                    <button type="button" class="viewport-btn" data-viewport="tablet">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="4" y="2" width="16" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
                        <span>Tablet</span>
                    </button>
                    <button type="button" class="viewport-btn" data-viewport="mobile">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
                        <span>Phone</span>
                    </button>
                </div>
            </div>

            <div class="split-workspace">
                <div class="editor-pane">
                    <div class="editor-tabs-strip">
                        <div class="editor-tab-item active" data-editor-file="html">Page content</div>
                        <div class="editor-tab-item" data-editor-file="css">Colours</div>
                        <div class="editor-tab-item" data-editor-file="redirects">Short links</div>
                    </div>
                    <div class="editor-code-body" id="showcase-code-body">
                        <div class="code-line"><span class="line-num">1</span><span class="code-text"><span class="tok-comment">&lt;!-- Top Navigation Bar --&gt;</span></span></div>
                        <div class="code-line"><span class="line-num">2</span><span class="code-text"><span class="tok-tag">&lt;header</span> <span class="tok-attr">class</span>=<span class="tok-str">"navbar"</span><span class="tok-tag">&gt;</span></span></div>
                        <div class="code-line"><span class="line-num">3</span><span class="code-text">&nbsp;&nbsp;<span class="tok-tag">&lt;a</span> <span class="tok-attr">href</span>=<span class="tok-str">"/"</span> <span class="tok-attr">class</span>=<span class="tok-str">"brand"</span><span class="tok-tag">&gt;</span>Lumina Studio<span class="tok-tag">&lt;/a&gt;</span></span></div>
                        <div class="code-line"><span class="line-num">4</span><span class="code-text">&nbsp;&nbsp;<span class="tok-tag">&lt;span</span> <span class="tok-attr">class</span>=<span class="tok-str">"pill"</span><span class="tok-tag">&gt;</span>Open for Bookings<span class="tok-tag">&lt;/span&gt;</span></span></div>
                        <div class="code-line"><span class="line-num">5</span><span class="code-text"><span class="tok-tag">&lt;/header&gt;</span></span></div>
                        <div class="code-line"><span class="line-num">6</span><span class="code-text"></span></div>
                        <div class="code-line"><span class="line-num">7</span><span class="code-text"><span class="tok-comment">&lt;!-- Main Headline &amp; Customer Inquiry Form --&gt;</span></span></div>
                        <div class="code-line"><span class="line-num">8</span><span class="code-text"><span class="tok-tag">&lt;main</span> <span class="tok-attr">class</span>=<span class="tok-str">"hero"</span><span class="tok-tag">&gt;</span></span></div>
                        <div class="code-line"><span class="line-num">9</span><span class="code-text">&nbsp;&nbsp;<span class="tok-tag">&lt;h1&gt;</span>Grow Your Business With a Great Landing Page<span class="tok-tag">&lt;/h1&gt;</span></span></div>
                        <div class="code-line"><span class="line-num">10</span><span class="code-text">&nbsp;&nbsp;<span class="tok-tag">&lt;form</span> <span class="tok-attr">action</span>=<span class="tok-str">"/__pd/form"</span> <span class="tok-attr">method</span>=<span class="tok-str">"POST"</span><span class="tok-tag">&gt;</span></span></div>
                        <div class="code-line"><span class="line-num">11</span><span class="code-text">&nbsp;&nbsp;&nbsp;&nbsp;<span class="tok-tag">&lt;input</span> <span class="tok-attr">type</span>=<span class="tok-str">"hidden"</span> <span class="tok-attr">name</span>=<span class="tok-str">"_form"</span> <span class="tok-attr">value</span>=<span class="tok-str">"Customer Inquiry"</span><span class="tok-tag">&gt;</span></span></div>
                        <div class="code-line"><span class="line-num">12</span><span class="code-text">&nbsp;&nbsp;&nbsp;&nbsp;<span class="tok-tag">&lt;input</span> <span class="tok-attr">type</span>=<span class="tok-str">"email"</span> <span class="tok-attr">name</span>=<span class="tok-str">"email"</span> <span class="tok-attr">placeholder</span>=<span class="tok-str">"Enter your email address"</span> <span class="tok-attr">required</span><span class="tok-tag">&gt;</span></span></div>
                        <div class="code-line"><span class="line-num">13</span><span class="code-text">&nbsp;&nbsp;&nbsp;&nbsp;<span class="tok-tag">&lt;button</span> <span class="tok-attr">type</span>=<span class="tok-str">"submit"</span><span class="tok-tag">&gt;</span>Get Free Quote &amp;rarr;<span class="tok-tag">&lt;/button&gt;</span></span></div>
                        <div class="code-line"><span class="line-num">14</span><span class="code-text">&nbsp;&nbsp;<span class="tok-tag">&lt;/form&gt;</span></span></div>
                        <div class="code-line"><span class="line-num">15</span><span class="code-text"><span class="tok-tag">&lt;/main&gt;</span></span></div>
                    </div>
                    <div class="mobile-pane-footer mobile-only">
                        <button type="button" class="mobile-switch-action-btn" id="showcase-switch-to-preview">View live preview &rarr;</button>
                    </div>
                </div>

                <div class="preview-pane">
                    <div class="preview-address-bar">
                        <div class="address-bar-pill">
                            <span class="preview-ssl-icon">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                            </span>
                            <span class="preview-url-text" id="showcase-preview-url">https://pagedock.freedev.app/s/lumina-studio</span>
                        </div>
                        <span class="preview-device-badge desktop-only" id="preview-viewport-label">Laptop View</span>
                    </div>
                    <div class="preview-viewport-content" id="mock-preview-container">
                        <div class="mock-browser-canvas">
                            <div class="mock-navbar">
                                <div class="mock-nav-brand">
                                    <span class="mock-brand-dot"></span>
                                    <span>Lumina Studio</span>
                                </div>
                                <div class="mock-nav-links">
                                    <span class="mock-nav-pill">Open for Bookings</span>
                                </div>
                            </div>
                            <div class="mock-hero-body">
                                <span class="mock-tag" id="showcase-status-tag">Live Page Preview</span>
                                <h3 class="mock-h1" id="showcase-preview-title">Grow Your Business With a Great Landing Page</h3>
                                <p class="mock-p" id="showcase-preview-desc">See every text and color edit immediately, test on phone and laptop screens, and collect customer inquiries automatically.</p>
                                <div class="mock-form-row" id="showcase-preview-action">
                                    <input type="email" placeholder="Enter your email address" class="mock-input" value="hello@mybusiness.com" readonly aria-label="Sample email field">
                                    <button type="button" class="mock-btn">Get Free Quote &rarr;</button>
                                </div>
                                <div class="mock-form-indicator">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                                    <span>Messages go straight to your Leads Inbox</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mobile-pane-footer mobile-only">
                        <button type="button" class="mobile-switch-action-btn" id="showcase-switch-to-code">Open the editor &rarr;</button>
                    </div>
                </div>
            </div>
        </div>

        <ul class="pdh-feature-row" data-reveal>
            <li><strong>Ready-made templates</strong><span>20+ designs for shops, events, food and services.</span></li>
            <li><strong>Phone preview</strong><span>Check every screen size before you share.</span></li>
            <li><strong>Drafts save themselves</strong><span>Your edits stay safe if the network drops.</span></li>
            <li><strong>One-click undo</strong><span>Every update keeps a backup you can restore.</span></li>
        </ul>
    </div>
</section>

<!-- 5. Benefits bento: 4 cells, mixed backgrounds -->
<section class="pdh-section">
    <div class="pdh-container">
        <h2 class="pdh-h2 pdh-h2-wide" data-reveal>Everything you need to win customers.</h2>

        <div class="pdh-bento">
            <!-- Leads (wide, tinted) -->
            <article class="pdh-tile pdh-tile-wide pdh-tile-tint" data-reveal>
                <div class="pdh-tile-copy">
                    <h3>Collect orders, enquiries and RSVPs</h3>
                    <p>Every form on your page sends messages to your Leads Inbox. Spam is blocked and you can download everything to Excel.</p>
                </div>
                <!-- sample data for illustration -->
                <ul class="pdh-leads" aria-label="Example leads inbox">
                    <li><span class="pdh-avatar">AO</span><span class="pdh-lead-text"><strong>Amaka O.</strong><span>Do you deliver to Lekki?</span></span><time>2m</time></li>
                    <li><span class="pdh-avatar">TB</span><span class="pdh-lead-text"><strong>Tunde B.</strong><span>Price for 3 cakes on Saturday?</span></span><time>18m</time></li>
                    <li><span class="pdh-avatar">FK</span><span class="pdh-lead-text"><strong>Fatima K.</strong><span>RSVP: 2 guests for the launch</span></span><time>1h</time></li>
                </ul>
            </article>

            <!-- Visitors (deep emerald) -->
            <article class="pdh-tile pdh-tile-deep" data-reveal>
                <h3>See who visits</h3>
                <p>Daily views, where visitors came from and which phones they use.</p>
                <!-- sample data for illustration -->
                <div class="pdh-stat"><span class="pdh-stat-num">2,410</span><span class="pdh-stat-label">views this week (example)</span></div>
                <div class="pdh-spark" aria-hidden="true">
                    <span style="height:30%"></span><span style="height:46%"></span><span style="height:38%"></span><span style="height:64%"></span><span style="height:55%"></span><span style="height:82%"></span><span style="height:100%"></span>
                </div>
            </article>

            <!-- Share links (photo) -->
            <article class="pdh-tile pdh-tile-photo" data-reveal>
                <img src="<?= asset('assets/img/home/owner-bakery.jpg') ?>" alt="A bakery owner showing his phone to a customer" width="1200" height="896" loading="lazy">
                <div class="pdh-tile-photo-copy">
                    <h3>A link worth sharing</h3>
                    <p>Clean links like <code>/s/your-brand</code> for WhatsApp, Instagram and flyers.</p>
                </div>
            </article>

            <!-- Works with (wide) -->
            <article class="pdh-tile pdh-tile-wide" data-reveal>
                <div class="pdh-tile-copy">
                    <h3>Works with what you already use</h3>
                    <p>Made a page with ChatGPT, Canva or a designer? Paste it in. Working with a developer? Our terminal and AI tools are built in.</p>
                </div>
                <ul class="pdh-chips">
                    <li>20+ templates</li>
                    <li>ChatGPT &amp; Claude pages</li>
                    <li>Canva exports</li>
                    <li>Designer ZIP files</li>
                    <li>Developer CLI</li>
                </ul>
            </article>
        </div>
    </div>
</section>

<!-- 6. How it works: numbered line, no cards -->
<section class="pdh-section pdh-section-soft">
    <div class="pdh-container">
        <h2 class="pdh-h2 pdh-h2-wide" data-reveal>Three steps to your first customer.</h2>
        <ol class="pdh-steps">
            <li data-reveal>
                <span class="pdh-step-num">1</span>
                <h3>Pick or upload</h3>
                <p>Choose a template, paste a page you made with AI, or upload your files.</p>
            </li>
            <li data-reveal>
                <span class="pdh-step-num">2</span>
                <h3>Make it yours</h3>
                <p>Change your name, prices and colours in the Studio and preview it on a phone.</p>
            </li>
            <li data-reveal>
                <span class="pdh-step-num">3</span>
                <h3>Share and get leads</h3>
                <p>Post your link anywhere. Read messages and track visitors from your dashboard.</p>
            </li>
        </ol>
    </div>
</section>

<!-- 7. FAQ: headline left, accordion right -->
<section class="pdh-section faq-section">
    <div class="pdh-container pdh-faq-grid">
        <div class="pdh-faq-head" data-reveal>
            <span class="pdh-eyebrow">Questions</span>
            <h2 class="pdh-h2">Simple answers.</h2>
            <p class="pdh-lead">Still unsure? Launch a sample page from the card above. It takes one click.</p>
        </div>
        <div class="faq-list" data-reveal>
            <div class="faq-item">
                <button type="button" class="faq-question-btn">
                    <span>Do I need to know how to code?</span>
                    <span class="faq-chevron" aria-hidden="true">&darr;</span>
                </button>
                <div class="faq-answer">
                    No. Pick one of our <a href="<?= url('templates') ?>">ready-made templates</a>, click <strong>Use Template</strong> and edit the words in your browser. You can also ask ChatGPT or Claude to design a page for your business and paste it in.
                </div>
            </div>
            <div class="faq-item">
                <button type="button" class="faq-question-btn">
                    <span>How do I get messages from customers?</span>
                    <span class="faq-chevron" aria-hidden="true">&darr;</span>
                </button>
                <div class="faq-answer">
                    Every form on your page sends messages to the <strong>Leads Inbox</strong> in your dashboard. You can read them online, download them for Excel or Google Sheets, or get alerts in Slack or Discord.
                </div>
            </div>
            <div class="faq-item">
                <button type="button" class="faq-question-btn">
                    <span>Will my page stay online?</span>
                    <span class="faq-chevron" aria-hidden="true">&darr;</span>
                </button>
                <div class="faq-answer">
                    Yes. Pages saved to your free account stay online with a secure <code>https://</code> link. You can update your page anytime without changing the link.
                </div>
            </div>
            <div class="faq-item">
                <button type="button" class="faq-question-btn">
                    <span>What kind of pages can I host?</span>
                    <span class="faq-chevron" aria-hidden="true">&darr;</span>
                </button>
                <div class="faq-answer">
                    Business landing pages, product pages, event and wedding RSVPs, restaurant menus, property listings, coaching pages and portfolios. Upload a single <code>.html</code> page or a <code>.zip</code> folder up to 50MB.
                </div>
            </div>
            <div class="faq-item">
                <button type="button" class="faq-question-btn">
                    <span>Can my designer or developer help manage it?</span>
                    <span class="faq-chevron" aria-hidden="true">&darr;</span>
                </button>
                <div class="faq-answer">
                    Yes. PageDock also includes a full code editor, version history with one-click undo, a terminal tool (<code>pagedock-cli</code>) and AI assistant support.
                </div>
            </div>
        </div>
    </div>
</section>

<!-- 8. Closing CTA: deep emerald block -->
<section class="pdh-cta-wrap">
    <div class="pdh-container">
        <div class="pdh-cta" data-reveal>
            <h2>Ready to put your business online?</h2>
            <p>Free to start. No card and no coding needed.</p>
            <div class="pdh-btn-row">
                <a href="#top" class="pdh-btn-light" onclick="window.scrollTo({top: 0, behavior: 'smooth'}); return false;">Publish Now</a>
                <a href="<?= url('templates') ?>" class="pdh-btn-outline-light">Browse templates</a>
            </div>
        </div>
    </div>
</section>
