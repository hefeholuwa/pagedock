<div class="hero-section">
    <div class="hero-bg-grid"></div>
    <div class="hero-content">
        <span class="badge">Instant Static Web Hosting</span>
        <h1 class="hero-title">
            Host your HTML.<br>
            <span class="accent-text typing-text">Get a live link.</span>
        </h1>
        <p class="hero-subtitle">
            Upload files or folders, paste raw HTML, or edit directly in your browser. 
            Publish a shareable HTTPS link with zero configuration.
        </p>

        <!-- Main Deployment Terminal / Panel -->
        <div class="deploy-card">
            <div class="deploy-tabs">
                <button type="button" class="tab-btn active" data-tab="upload-tab">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    Upload Files (.html or .zip)
                </button>
                <button type="button" class="tab-btn" data-tab="paste-tab">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
                    Paste HTML
                </button>
                <button type="button" class="tab-btn" data-tab="demo-tab">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polygon points="10 8 16 12 10 16 10 8"/></svg>
                    One-Click Demo
                </button>
            </div>

            <!-- Tab 1: Upload Files -->
            <div id="upload-tab" class="tab-content active">
                <form id="upload-form" class="dropzone" enctype="multipart/form-data">
                    <input type="file" id="file-input" name="file" accept=".html,.htm,.zip" class="hidden-input">
                    <div class="dropzone-inner" id="dropzone-trigger">
                        <div class="upload-icon">
                            <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 14.899A7 7 0 1 1 15.71 8h1.79a4.5 4.5 0 0 1 2.5 8.242"/><path d="M12 12v9"/><path d="m8 16 4-4 4 4"/></svg>
                        </div>
                        <p class="dropzone-title">Click to browse or drop your <strong>.html</strong> or <strong>.zip</strong> archive</p>
                        <p class="dropzone-desc">Supports multi-page sites, images, CSS, and JS inside ZIP up to 50MB</p>
                    </div>

                    <div class="custom-slug-row">
                        <input type="text" name="title" id="upload-title" placeholder="Site title (optional)" class="input-text">
                        <div class="slug-input-wrapper">
                            <span class="slug-prefix">pagedock/s/</span>
                            <input type="text" name="slug" id="upload-slug" placeholder="custom-slug" class="input-slug">
                        </div>
                    </div>

                    <button type="submit" id="upload-submit-btn" class="btn-primary w-full">Deploy Website</button>
                </form>
            </div>

            <!-- Tab 2: Paste HTML -->
            <div id="paste-tab" class="tab-content">
                <form id="paste-form">
                    <div class="custom-slug-row mb-3">
                        <input type="text" name="title" id="paste-title" placeholder="Site title (e.g. My Portfolio)" class="input-text">
                        <div class="slug-input-wrapper">
                            <span class="slug-prefix">pagedock/s/</span>
                            <input type="text" name="slug" id="paste-slug" placeholder="custom-slug" class="input-slug">
                        </div>
                    </div>
                    <div class="code-editor-box">
                        <textarea name="html" id="paste-html" placeholder="<!DOCTYPE html>&#10;<html>&#10;<head>&#10;  <title>Hello World</title>&#10;</head>&#10;<body>&#10;  <h1>My PageDock Website</h1>&#10;</body>&#10;</html>" rows="12" class="textarea-code"></textarea>
                    </div>
                    <button type="submit" class="btn-primary w-full mt-3">Deploy Pasted HTML</button>
                </form>
            </div>

            <!-- Tab 3: Demo Template -->
            <div id="demo-tab" class="tab-content">
                <div class="demo-box">
                    <h3>Try a pre-made modern landing page</h3>
                    <p>Instantly deploy a clean developer portfolio with a working contact form to test how PageDock works.</p>
                    <button type="button" id="launch-demo-btn" class="btn-primary">Launch Demo Template Now</button>
                </div>
            </div>

            <!-- Progress / Status Bar -->
            <div id="deploy-status" class="deploy-status hidden">
                <div class="spinner"></div>
                <span id="deploy-status-text">Uploading and unpacking site...</span>
            </div>

            <!-- Success Card -->
            <div id="deploy-success" class="deploy-success hidden">
                <div class="success-header">
                    <div class="success-icon">✓</div>
                    <div>
                        <h4>Your website is live!</h4>
                        <p class="success-sub">Share your link or jump straight into the live editor.</p>
                    </div>
                </div>
                <div class="url-copy-box">
                    <input type="text" id="live-url-input" readonly value="" class="url-input">
                    <button type="button" id="copy-url-btn" class="btn-copy">Copy</button>
                </div>
                <div class="action-buttons">
                    <a href="#" id="view-site-link" target="_blank" class="btn-primary">View Live Site &rarr;</a>
                    <a href="#" id="open-editor-link" class="btn-secondary">Open Live Editor</a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Features Bento Grid -->
<section class="section-container">
    <div class="section-header">
        <h2>Built for Speed &amp; Simplicity</h2>
        <p>Everything you need to host, preview, and share your static HTML creations.</p>
    </div>

    <div class="bento-grid">
        <div class="bento-card">
            <div class="bento-icon">⚡</div>
            <h3>Instant Deployments</h3>
            <p>Upload a single <code>.html</code> file or a full <code>.zip</code> folder with styles, images, and fonts. It's live in milliseconds.</p>
        </div>

        <div class="bento-card">
            <div class="bento-icon">🛠️</div>
            <h3>Live In-Browser Editor</h3>
            <p>Make edits directly from your browser. View changes on responsive desktop and mobile viewports with immediate publish capability.</p>
        </div>

        <div class="bento-card">
            <div class="bento-icon">📬</div>
            <h3>Backendless Forms</h3>
            <p>Point any HTML <code>&lt;form&gt;</code> to your PageDock endpoint. We collect all submissions securely with zero backend coding required.</p>
        </div>

        <div class="bento-card">
            <div class="bento-icon">🔒</div>
            <h3>Never Expires &amp; HTTPS</h3>
            <p>Your hosted projects are served over secure HTTPS and stay online permanently without unexpected takedowns.</p>
        </div>
    </div>
</section>
