// PageDock Core Client Logic

document.addEventListener('DOMContentLoaded', () => {
    // 0. Mobile Navbar Drawer Toggle
    const menuToggle = document.getElementById('mobile-menu-toggle');
    const navDrawer = document.getElementById('mobile-nav-drawer');
    const navBackdrop = document.getElementById('mobile-nav-backdrop');

    function closeNavDrawer() {
        if (!navDrawer) return;
        navDrawer.classList.add('hidden');
        if (menuToggle) {
            menuToggle.classList.remove('active');
            menuToggle.setAttribute('aria-expanded', 'false');
            menuToggle.innerHTML = `<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>`;
        }
        if (navBackdrop) navBackdrop.classList.add('hidden');
        document.body.classList.remove('mobile-drawer-open');
    }

    function openNavDrawer() {
        if (!navDrawer) return;
        navDrawer.classList.remove('hidden');
        if (menuToggle) {
            menuToggle.classList.add('active');
            menuToggle.setAttribute('aria-expanded', 'true');
            menuToggle.innerHTML = `<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>`;
        }
        if (navBackdrop) navBackdrop.classList.remove('hidden');
        document.body.classList.add('mobile-drawer-open');
    }

    if (menuToggle && navDrawer) {
        menuToggle.addEventListener('click', (e) => {
            e.stopPropagation();
            const isOpen = !navDrawer.classList.contains('hidden');
            if (isOpen) {
                closeNavDrawer();
            } else {
                openNavDrawer();
            }
        });

        // Close on tap outside or backdrop
        if (navBackdrop) {
            navBackdrop.addEventListener('click', () => closeNavDrawer());
        }

        document.addEventListener('click', (e) => {
            if (!navDrawer.contains(e.target) && !menuToggle.contains(e.target)) {
                closeNavDrawer();
            }
        });

        // Close on link click inside drawer
        navDrawer.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', () => closeNavDrawer());
        });
    }

    // 1. Tab Switching
    const tabBtns = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');

    tabBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const targetId = btn.getAttribute('data-tab');
            tabBtns.forEach(b => b.classList.remove('active'));
            tabContents.forEach(c => c.classList.remove('active'));

            btn.classList.add('active');
            const targetContent = document.getElementById(targetId);
            if (targetContent) targetContent.classList.add('active');
        });
    });

    // 2. Drag & Drop File Upload with Client Ingestion Pipeline (fflate & Dexie)
    const dropzone = document.getElementById('dropzone-trigger');
    const fileInput = document.getElementById('file-input');
    const uploadForm = document.getElementById('upload-form');
    let stagedCleanBlob = null;
    let stagedFileName = null;
    let stagedUnpackResult = null;

    if (dropzone && fileInput) {
        dropzone.addEventListener('click', () => fileInput.click());

        ['dragenter', 'dragover'].forEach(eventName => {
            dropzone.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
                dropzone.classList.add('dragover');
            });
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropzone.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
                dropzone.classList.remove('dragover');
            });
        });

        dropzone.addEventListener('drop', async (e) => {
            const dt = e.dataTransfer;
            if (!dt) return;

            // Check for dropped folder
            if (dt.items && dt.items.length > 0 && typeof dt.items[0].webkitGetAsEntry === 'function') {
                const entry = dt.items[0].webkitGetAsEntry();
                if (entry && entry.isDirectory) {
                    if (window.PageDockPipeline) {
                        updateDropzoneStatus('Unpacking and indexing folder in browser...');
                        try {
                            const result = await window.PageDockPipeline.processDroppedFolder(entry);
                            handleUnpackSuccess(result);
                            return;
                        } catch (err) {
                            updateDropzoneError(err.message);
                            return;
                        }
                    }
                }
            }

            const files = dt.files;
            if (files.length > 0) {
                fileInput.files = files;
                handleSelectedFile(files[0]);
            }
        });

        fileInput.addEventListener('change', () => {
            if (fileInput.files.length > 0) {
                handleSelectedFile(fileInput.files[0]);
            }
        });
    }

    async function handleSelectedFile(file) {
        stagedCleanBlob = null;
        stagedFileName = null;
        stagedUnpackResult = null;

        const isZip = window.PageDockPipeline ? window.PageDockPipeline.isZip(file) : file.name.toLowerCase().endsWith('.zip');

        if (isZip && window.PageDockPipeline) {
            updateDropzoneStatus('Unpacking archive client-side (fflate)...');
            try {
                const result = await window.PageDockPipeline.processZip(file);
                handleUnpackSuccess(result);
            } catch (err) {
                updateDropzoneError(err.message || 'Error unpacking archive in browser.');
            }
        } else {
            updateDropzoneDisplay(file.name);
        }
    }

    function handleUnpackSuccess(result) {
        stagedCleanBlob = result.cleanBlob;
        stagedFileName = result.originalName.endsWith('.zip') ? result.originalName : (result.originalName + '.zip');
        stagedUnpackResult = result;

        const titleEl = dropzone.querySelector('.dropzone-title');
        const descEl = dropzone.querySelector('.dropzone-desc');
        const titleInput = document.getElementById('site-title');

        if (titleInput && !titleInput.value) {
            titleInput.value = result.originalName.replace(/\.(zip|html|htm)$/i, '');
        }

        const sizeKb = (result.totalBytes / 1024).toFixed(1);

        if (titleEl) {
            titleEl.innerHTML = `Selected: <strong style="color:var(--accent);">${escapeHtml(result.originalName)}</strong>`;
        }
        if (descEl) {
            if (result.hasIndex) {
                descEl.innerHTML = `<span class="preflight-badge success">&check; ${result.fileCount} files verified ${result.unwrapped ? '• Root folder unwrapped ' : ''}• index.html detected (${sizeKb} KB)</span>`;
            } else {
                descEl.innerHTML = `<span class="preflight-badge warning">&excl; ${result.fileCount} files unpacked • Warning: No index.html at root (${escapeHtml(result.entryFile)} will be entry)</span>`;
            }
        }
    }

    function updateDropzoneStatus(msg) {
        const descEl = dropzone.querySelector('.dropzone-desc');
        if (descEl) {
            descEl.innerHTML = `<span class="preflight-badge loading">${escapeHtml(msg)}</span>`;
        }
    }

    function updateDropzoneError(msg) {
        const descEl = dropzone.querySelector('.dropzone-desc');
        if (descEl) {
            descEl.innerHTML = `<span class="preflight-badge warning">&times; ${escapeHtml(msg)}</span>`;
        }
    }

    function updateDropzoneDisplay(fileName) {
        const titleEl = dropzone.querySelector('.dropzone-title');
        const descEl = dropzone.querySelector('.dropzone-desc');
        if (titleEl) {
            titleEl.innerHTML = `Selected file: <strong style="color:var(--accent);">${escapeHtml(fileName)}</strong>`;
        }
        if (descEl) {
            descEl.innerHTML = `<span class="preflight-badge success">&check; HTML file ready for instant deployment</span>`;
        }
    }

    // 3. Handle File Upload Submit
    if (uploadForm) {
        uploadForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            if ((!fileInput.files || fileInput.files.length === 0) && !stagedCleanBlob) {
                alert('Please select an .html or .zip file first.');
                return;
            }

            const formData = new FormData(uploadForm);

            // If client unzipped and normalized archive, send clean blob
            if (stagedCleanBlob) {
                formData.set('file', stagedCleanBlob, stagedFileName || 'archive.zip');
            }

            showDeployStatus('Deploying website to edge...');

            try {
                const res = await fetch('/api/upload', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                handleDeployResponse(data);
            } catch (err) {
                alert('Upload failed: Network or server error.');
                hideDeployStatus();
            }
        });
    }

    // 4. Handle Paste HTML Submit & IndexedDB Autosave
    const pasteForm = document.getElementById('paste-form');
    const pasteHtmlInput = document.getElementById('paste-html');
    const pasteTitleInput = document.getElementById('paste-title');

    if (pasteHtmlInput && window.PageDockPipeline) {
        let pasteDraftTimer = null;
        pasteHtmlInput.addEventListener('input', () => {
            clearTimeout(pasteDraftTimer);
            pasteDraftTimer = setTimeout(() => {
                window.PageDockPipeline.saveDraft('home_paste_draft', {
                    title: pasteTitleInput ? pasteTitleInput.value : '',
                    html: pasteHtmlInput.value
                });
            }, 500);
        });

        // Restore uncommitted paste draft on reload
        if (!pasteHtmlInput.value || pasteHtmlInput.value.trim() === '') {
            window.PageDockPipeline.getDraft('home_paste_draft').then(draft => {
                if (draft && draft.html && draft.html.trim().length > 0 && (!pasteHtmlInput.value || pasteHtmlInput.value.trim() === '')) {
                    pasteHtmlInput.value = draft.html;
                    if (pasteTitleInput && !pasteTitleInput.value && draft.title) {
                        pasteTitleInput.value = draft.title;
                    }
                }
            });
        }
    }
    if (pasteForm) {
        pasteForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const html = document.getElementById('paste-html').value.trim();
            if (!html) {
                alert('Please enter or paste HTML code.');
                return;
            }

            const formData = new FormData(pasteForm);
            showDeployStatus('Publishing your HTML...');

            try {
                const res = await fetch('/api/paste', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                handleDeployResponse(data);
            } catch (err) {
                alert('Deploy failed: Network or server error.');
                hideDeployStatus();
            }
        });
    }

    // 5. One-Click Demo Template Generator
    const demoBtn = document.getElementById('launch-demo-btn');
    if (demoBtn) {
        demoBtn.addEventListener('click', async () => {
            showDeployStatus('Crafting sample portfolio template...');
            
            const demoHtml = `<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alex Rivera — Senior Developer</title>
    <style>
        :root { --accent: #C7F547; --bg: #0d0f12; --card: #15181e; --text: #f0f2f5; --sub: #8b949e; }
        * { box-sizing: border-box; margin:0; padding:0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        body { background: var(--bg); color: var(--text); padding: 40px 20px; line-height: 1.6; }
        .container { max-width: 680px; margin: 0 auto; }
        header { margin-bottom: 40px; }
        .tag { color: var(--accent); font-weight: 600; font-size: 13px; text-transform: uppercase; letter-spacing: 1px; }
        h1 { font-size: 38px; margin: 8px 0; font-weight: 800; letter-spacing: -0.03em; }
        p.bio { color: var(--sub); font-size: 17px; }
        .card { background: var(--card); border: 1px solid #232730; border-radius: 12px; padding: 24px; margin-bottom: 24px; }
        h2 { font-size: 20px; margin-bottom: 12px; }
        input, textarea { width: 100%; background: #0d0f12; border: 1px solid #232730; color: #fff; padding: 12px; border-radius: 6px; margin-bottom: 12px; font-size: 14px; outline: none; }
        input:focus, textarea:focus { border-color: var(--accent); }
        button { background: var(--accent); color: #000; font-weight: 700; border: none; padding: 12px 20px; border-radius: 6px; cursor: pointer; width: 100%; }
        button:hover { opacity: 0.9; }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <span class="tag">Available for hire</span>
            <h1>Alex Rivera</h1>
            <p class="bio">Building high-performance web experiences, APIs, and developer tools. Hosted effortlessly on PageDock.</p>
        </header>

        <section class="card">
            <h2>Recent Projects</h2>
            <p style="color:var(--sub); margin-bottom: 12px;">• <strong>HyperScale</strong> — Distributed Redis cache orchestrator</p>
            <p style="color:var(--sub); margin-bottom: 12px;">• <strong>Zenith UI</strong> — Accessible web design tokens library</p>
        </section>

        <section class="card">
            <h2>Get in touch</h2>
            <form action="/api/forms/demo" method="POST">
                <input type="text" name="name" placeholder="Your Name" required>
                <input type="email" name="email" placeholder="Your Email" required>
                <textarea name="message" rows="4" placeholder="Let's build something..." required></textarea>
                <button type="submit">Send Message Directly</button>
            </form>
        </section>
    </div>
</body>
</html>`;

            const formData = new FormData();
            formData.append('html', demoHtml);
            formData.append('title', 'Developer Portfolio Demo');

            try {
                const res = await fetch('/api/paste', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                handleDeployResponse(data);
            } catch (err) {
                alert('Demo creation failed.');
                hideDeployStatus();
            }
        });
    }

    // 6. UI Helpers
    function showDeployStatus(msg) {
        const statusEl = document.getElementById('deploy-status');
        const textEl = document.getElementById('deploy-status-text');
        const successEl = document.getElementById('deploy-success');
        if (successEl) successEl.classList.add('hidden');
        if (textEl) textEl.textContent = msg;
        if (statusEl) statusEl.classList.remove('hidden');
    }

    function hideDeployStatus() {
        const statusEl = document.getElementById('deploy-status');
        if (statusEl) statusEl.classList.add('hidden');
    }

    function handleDeployResponse(data) {
        hideDeployStatus();
        if (data.success) {
            const successEl = document.getElementById('deploy-success');
            const urlInput = document.getElementById('live-url-input');
            const viewLink = document.getElementById('view-site-link');
            const editorLink = document.getElementById('open-editor-link');

            if (urlInput) urlInput.value = data.url;
            if (viewLink) viewLink.href = data.url;
            if (editorLink) editorLink.href = '/editor/' + data.slug;

            if (successEl) {
                successEl.classList.remove('hidden');
                successEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        } else {
            alert(data.error || 'Deploy failed.');
        }
    }

    // Copy to clipboard button
    const copyBtn = document.getElementById('copy-url-btn');
    if (copyBtn) {
        copyBtn.addEventListener('click', () => {
            const urlInput = document.getElementById('live-url-input');
            if (urlInput) {
                urlInput.select();
                navigator.clipboard.writeText(urlInput.value).then(() => {
                    copyBtn.textContent = 'Copied!';
                    setTimeout(() => copyBtn.textContent = 'Copy', 2000);
                });
            }
        });
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    // 7. Template Gallery Interactivity
    const templateCards = document.querySelectorAll('.template-card');
    const catPills = document.querySelectorAll('.cat-pill');
    const searchInput = document.getElementById('template-search');
    const countIndicator = document.getElementById('template-visible-count');
    const templateModal = document.getElementById('template-modal');
    const modalIframe = document.getElementById('modal-preview-iframe');
    const modalTitle = document.getElementById('modal-template-title');
    const modalUseBtn = document.getElementById('modal-use-btn');
    const closeTemplateModalBtn = document.getElementById('close-template-modal');
    const modalViewportBtns = document.querySelectorAll('.m-viewport-btn');

    let currentCat = 'all';
    let currentQuery = '';

    function filterTemplates() {
        let visibleCount = 0;

        templateCards.forEach(card => {
            const cardCat = card.getAttribute('data-category') || '';
            const cardTitle = card.getAttribute('data-title') || '';
            const cardTags = card.getAttribute('data-tags') || '';

            const matchesCat = (currentCat === 'all' || cardCat === currentCat);
            const matchesQuery = (currentQuery === '' || 
                                  cardTitle.includes(currentQuery) || 
                                  cardCat.includes(currentQuery) || 
                                  cardTags.includes(currentQuery));

            if (matchesCat && matchesQuery) {
                card.style.display = '';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        });

        if (countIndicator) {
            countIndicator.textContent = visibleCount;
        }
    }

    // Category pills
    catPills.forEach(pill => {
        pill.addEventListener('click', () => {
            catPills.forEach(p => p.classList.remove('active'));
            pill.classList.add('active');
            currentCat = pill.getAttribute('data-cat') || 'all';
            filterTemplates();
        });
    });

    // Real-time search
    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            currentQuery = e.target.value.trim().toLowerCase();
            filterTemplates();
        });
    }

    // Live Preview Modal
    function openPreviewModal(slug, title) {
        if (!templateModal || !modalIframe) return;
        modalTitle.textContent = title || 'Template Preview';
        modalIframe.src = `/templates/${slug}/preview`;
        if (modalUseBtn) modalUseBtn.setAttribute('data-slug', slug);
        templateModal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closePreviewModal() {
        if (!templateModal) return;
        templateModal.classList.add('hidden');
        if (modalIframe) modalIframe.src = 'about:blank';
        document.body.style.overflow = '';
    }

    // Event delegation on #templates-grid for preview & use clicks
    const templatesGrid = document.getElementById('templates-grid');
    if (templatesGrid) {
        templatesGrid.addEventListener('click', (e) => {
            // 1. Check if Use Template button was clicked
            const useBtn = e.target.closest('.use-template-btn');
            if (useBtn) {
                e.preventDefault();
                e.stopPropagation();
                const slug = useBtn.getAttribute('data-slug');
                useTemplate(slug, useBtn);
                return;
            }

            // 2. Check if preview button or card preview box was clicked
            const previewTrigger = e.target.closest('.preview-modal-btn, .preview-trigger-area');
            if (previewTrigger) {
                e.preventDefault();
                const slug = previewTrigger.getAttribute('data-slug');
                const title = previewTrigger.getAttribute('data-title');
                if (slug) openPreviewModal(slug, title);
                return;
            }
        });

        templatesGrid.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                const trigger = e.target.closest('.preview-trigger-area');
                if (trigger) {
                    e.preventDefault();
                    openPreviewModal(trigger.getAttribute('data-slug'), trigger.getAttribute('data-title'));
                }
            }
        });
    }

    // Modal close controls
    if (closeTemplateModalBtn) {
        closeTemplateModalBtn.addEventListener('click', closePreviewModal);
    }

    if (templateModal) {
        templateModal.addEventListener('click', (e) => {
            if (e.target === templateModal) closePreviewModal();
        });
    }

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && templateModal && !templateModal.classList.contains('hidden')) {
            closePreviewModal();
        }
    });

    // Viewport switchers in preview modal
    modalViewportBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            modalViewportBtns.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            const width = btn.getAttribute('data-width');
            if (modalIframe) modalIframe.style.width = width;
        });
    });

    // "Use Template" clone handler
    async function useTemplate(slug, btnElement) {
        if (!slug) return;
        const origText = btnElement ? btnElement.innerHTML : '';
        if (btnElement) {
            btnElement.textContent = 'Cloning...';
            btnElement.disabled = true;
        }

        try {
            const res = await fetch(`/api/templates/${slug}/use`, {
                method: 'POST'
            });
            const data = await res.json();
            if (data.success && data.editor_url) {
                window.location.href = data.editor_url;
            } else {
                alert(data.error || 'Failed to initialize template.');
                if (btnElement) {
                    btnElement.innerHTML = origText;
                    btnElement.disabled = false;
                }
            }
        } catch (err) {
            alert('Network error initializing template.');
            if (btnElement) {
                btnElement.innerHTML = origText;
                btnElement.disabled = false;
            }
        }
    }

    if (modalUseBtn) {
        modalUseBtn.addEventListener('click', () => {
            const slug = modalUseBtn.getAttribute('data-slug');
            useTemplate(slug, modalUseBtn);
        });
    }

    // 6. Preset HTML Starters for Paste Tab
    const presetButtons = document.querySelectorAll('.preset-chip-btn');
    const pasteTextarea = document.getElementById('paste-html');

    const presets = {
        waitlist: {
            title: 'SaaS Waitlist',
            html: `<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Aero — The Future of Cloud Sync</title>
  <style>
    :root { --accent: #C7F547; --bg: #090a0d; --card: #13161f; --text: #f8fafc; --muted: #94a3b8; }
    * { box-sizing: border-box; margin:0; padding:0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
    body { background: var(--bg); color: var(--text); min-height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 24px; text-align: center; }
    .badge { display: inline-block; padding: 4px 12px; background: rgba(199,245,71,0.12); color: var(--accent); border-radius: 50px; font-size: 12px; font-weight: 700; text-transform: uppercase; margin-bottom: 16px; }
    h1 { font-size: clamp(32px, 6vw, 56px); font-weight: 800; letter-spacing: -0.03em; margin-bottom: 16px; line-height: 1.1; }
    p { color: var(--muted); font-size: 16px; max-width: 480px; margin: 0 auto 32px; line-height: 1.6; }
    form { display: flex; gap: 8px; max-width: 420px; width: 100%; margin: 0 auto; }
    input { flex: 1; background: var(--card); border: 1px solid #262a34; color: #fff; padding: 12px 16px; border-radius: 8px; font-size: 14px; outline: none; }
    input:focus { border-color: var(--accent); }
    button { background: var(--accent); color: #000; font-weight: 700; border: none; padding: 12px 20px; border-radius: 8px; cursor: pointer; }
  </style>
</head>
<body>
  <span class="badge">Early Beta Access</span>
  <h1>Sync Everything.<br>Never Wait.</h1>
  <p>Autonomous edge state sync for modern engineering teams. Join 1,400+ developers on the waitlist.</p>
  <form action="/__pd/form" method="POST">
    <input type="hidden" name="_form" value="Early Waitlist">
    <input type="email" name="email" placeholder="you@company.com" required>
    <button type="submit">Join Waitlist</button>
  </form>
</body>
</html>`
        },
        portfolio: {
            title: 'Developer Portfolio',
            html: `<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Devon Vance — Systems Architect</title>
  <style>
    :root { --accent: #C7F547; --bg: #0b0d12; --card: #151822; --text: #f3f4f6; --muted: #8b949e; }
    * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
    body { background: var(--bg); color: var(--text); padding: 48px 20px; line-height: 1.6; }
    .container { max-width: 680px; margin: 0 auto; }
    .tag { color: var(--accent); font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; }
    h1 { font-size: 40px; margin: 8px 0; font-weight: 800; letter-spacing: -0.03em; }
    p.lead { color: var(--muted); font-size: 17px; margin-bottom: 32px; }
    .card { background: var(--card); border: 1px solid #232733; border-radius: 12px; padding: 24px; margin-bottom: 24px; }
    h2 { font-size: 18px; margin-bottom: 12px; }
    ul { list-style: none; }
    li { margin-bottom: 12px; color: var(--muted); }
    li strong { color: var(--text); }
  </style>
</head>
<body>
  <div class="container">
    <span class="tag">Available for Q4 Engagements</span>
    <h1>Devon Vance</h1>
    <p class="lead">Staff Infrastructure Engineer building resilient distributed databases, edge proxies, and developer tools.</p>
    <div class="card">
      <h2>Selected Works</h2>
      <ul>
        <li><strong>VectorProxy</strong> &mdash; 10M req/sec Redis-compatible gateway</li>
        <li><strong>StaticEdge</strong> &mdash; Distributed micro-host with 0ms build pipeline</li>
      </ul>
    </div>
  </div>
</body>
</html>`
        },
        docs: {
            title: 'Mini Documentation',
            html: `<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>API Quickstart Guide</title>
  <style>
    :root { --accent: #C7F547; --bg: #090a0d; --card: #13161f; --text: #f8fafc; --muted: #94a3b8; }
    * { box-sizing: border-box; margin:0; padding:0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
    body { background: var(--bg); color: var(--text); padding: 40px 20px; }
    .doc-wrap { max-width: 740px; margin: 0 auto; }
    h1 { font-size: 32px; font-weight: 800; margin-bottom: 12px; }
    p { color: var(--muted); margin-bottom: 20px; line-height: 1.6; }
    pre { background: #040507; border: 1px solid #222630; padding: 16px; border-radius: 8px; font-family: ui-monospace, monospace; font-size: 13px; color: #a5b4fc; overflow-x: auto; margin-bottom: 24px; }
  </style>
</head>
<body>
  <div class="doc-wrap">
    <h1>API Quickstart</h1>
    <p>Install the sovereign client library and authenticate via your bearer token.</p>
    <pre>npm install @pagedock/sdk
export PAGEDOCK_KEY=pd_live_99f2b8a0
pagedock deploy ./public</pre>
  </div>
</body>
</html>`
        }
    };

    presetButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            const presetKey = btn.getAttribute('data-preset');
            if (presets[presetKey] && pasteTextarea) {
                pasteTextarea.value = presets[presetKey].html;
                if (pasteTitleInput && !pasteTitleInput.value) {
                    pasteTitleInput.value = presets[presetKey].title;
                }
            }
        });
    });

    // 7. CLI Command Copy Button
    const copyCliBtn = document.getElementById('copy-cli-btn');
    if (copyCliBtn) {
        copyCliBtn.addEventListener('click', async () => {
            const cmd = 'npx pagedock-cli deploy ./dist --slug my-portfolio';
            try {
                await navigator.clipboard.writeText(cmd);
                copyCliBtn.innerHTML = '<span>Copied!</span>';
                setTimeout(() => {
                    copyCliBtn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg><span>Copy</span>';
                }, 2000);
            } catch (err) {
                // Clipboard fallback
            }
        });
    }

    // 8. Split Showcase Viewport Switcher
    const showcaseViewportBtns = document.querySelectorAll('.viewport-btn');
    const mockPreviewContainer = document.getElementById('mock-preview-container');
    const previewViewportLabel = document.getElementById('preview-viewport-label');

    showcaseViewportBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            showcaseViewportBtns.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            const mode = btn.getAttribute('data-viewport');
            if (mockPreviewContainer) {
                if (mode === 'desktop') {
                    mockPreviewContainer.style.maxWidth = '100%';
                    if (previewViewportLabel) previewViewportLabel.textContent = '1440px \u00D7 900px';
                } else if (mode === 'tablet') {
                    mockPreviewContainer.style.maxWidth = '460px';
                    if (previewViewportLabel) previewViewportLabel.textContent = '768px \u00D7 1024px';
                } else if (mode === 'mobile') {
                    mockPreviewContainer.style.maxWidth = '320px';
                    if (previewViewportLabel) previewViewportLabel.textContent = '375px \u00D7 812px';
                }
            }
        });
    });

    // 8b. Mobile Showcase Mode Switcher (Live Preview vs Source Code)
    const showcaseModeBtns = document.querySelectorAll('.mobile-switch-btn');
    const splitWorkspace = document.querySelector('.split-workspace');
    const switchToPreviewBtn = document.getElementById('showcase-switch-to-preview');
    const switchToCodeBtn = document.getElementById('showcase-switch-to-code');

    function setShowcaseMobileMode(mode) {
        if (!splitWorkspace) return;
        showcaseModeBtns.forEach(b => {
            if (b.getAttribute('data-showcase-mode') === mode) {
                b.classList.add('active');
            } else {
                b.classList.remove('active');
            }
        });
        if (mode === 'code') {
            splitWorkspace.classList.add('show-code');
        } else {
            splitWorkspace.classList.remove('show-code');
        }
    }

    showcaseModeBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const mode = btn.getAttribute('data-showcase-mode');
            setShowcaseMobileMode(mode);
        });
    });

    if (switchToPreviewBtn) {
        switchToPreviewBtn.addEventListener('click', () => setShowcaseMobileMode('preview'));
    }
    if (switchToCodeBtn) {
        switchToCodeBtn.addEventListener('click', () => setShowcaseMobileMode('code'));
    }

    // 9. FAQ Accordion Toggle
    const faqButtons = document.querySelectorAll('.faq-question-btn');
    faqButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            const item = btn.closest('.faq-item');
            if (item) {
                const isActive = item.classList.contains('active');
                document.querySelectorAll('.faq-item').forEach(i => i.classList.remove('active'));
                if (!isActive) {
                    item.classList.add('active');
                }
            }
        });
    });

    // 10. Split Showcase Editor Tabs Switcher (index.html / style.css / _redirects)
    const editorTabItems = document.querySelectorAll('.editor-tab-item');
    const showcaseCodeBody = document.getElementById('showcase-code-body');
    const showcasePreviewUrl = document.getElementById('showcase-preview-url');
    const showcaseStatusTag = document.getElementById('showcase-status-tag');
    const showcasePreviewTitle = document.getElementById('showcase-preview-title');
    const showcasePreviewDesc = document.getElementById('showcase-preview-desc');
    const showcasePreviewAction = document.getElementById('showcase-preview-action');

    const showcaseFiles = {
        html: {
            url: 'https://pagedock.freedev.app/s/acme-studio',
            tag: 'Live Sandbox',
            title: 'Next-Generation UI Systems',
            desc: 'Zero-build client-side previews with live responsive viewport toggles and built-in form capture.',
            actionHtml: '<input type="email" placeholder="you@domain.com" class="mock-input" value="lead@venture.com" readonly><button type="button" class="mock-btn">Claim Access &rarr;</button>',
            code: `<div class="code-line"><span class="line-num">1</span><span class="code-text"><span class="tok-comment">&lt;!-- pd:shared header --&gt;</span></span></div>
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
<div class="code-line"><span class="line-num">15</span><span class="code-text"><span class="tok-tag">&lt;/main&gt;</span></span></div>`
        },
        css: {
            url: 'https://pagedock.freedev.app/s/acme-studio/style.css',
            tag: 'Live CSS Tokens Active',
            title: 'Design Tokens & Variables',
            desc: 'CSS modifications apply at 60 FPS without losing form state, scroll position, or DOM focus.',
            actionHtml: '<div style="display:flex; gap:10px; align-items:center; background:rgba(199,245,71,0.08); padding:8px 12px; border-radius:6px; border:1px solid rgba(199,245,71,0.25);"><span style="display:inline-block; width:12px; height:12px; border-radius:50%; background:var(--accent); box-shadow:0 0 8px var(--accent);"></span><span style="font-family:var(--font-mono); font-size:11px; color:var(--accent); font-weight:600;">--accent: #C7F547 (Live Hot-Reload Active)</span></div>',
            code: `<div class="code-line"><span class="line-num">1</span><span class="code-text"><span class="tok-comment">/* PageDock Modern Design Tokens */</span></span></div>
<div class="code-line"><span class="line-num">2</span><span class="code-text"><span class="tok-tag">:root</span> {</span></div>
<div class="code-line"><span class="line-num">3</span><span class="code-text">&nbsp;&nbsp;<span class="tok-attr">--bg</span>: <span class="tok-str">#07080a</span>;</span></div>
<div class="code-line"><span class="line-num">4</span><span class="code-text">&nbsp;&nbsp;<span class="tok-attr">--accent</span>: <span class="tok-str">#C7F547</span>;</span></div>
<div class="code-line"><span class="line-num">5</span><span class="code-text">&nbsp;&nbsp;<span class="tok-attr">--accent-glow</span>: <span class="tok-str">rgba(199, 245, 71, 0.35)</span>;</span></div>
<div class="code-line"><span class="line-num">6</span><span class="code-text">&nbsp;&nbsp;<span class="tok-attr">--radius</span>: <span class="tok-str">10px</span>;</span></div>
<div class="code-line"><span class="line-num">7</span><span class="code-text">&nbsp;&nbsp;<span class="tok-attr">--font</span>: <span class="tok-str">'Plus Jakarta Sans', sans-serif</span>;</span></div>
<div class="code-line"><span class="line-num">8</span><span class="code-text">}</span></div>
<div class="code-line"><span class="line-num">9</span><span class="code-text"></span></div>
<div class="code-line"><span class="line-num">10</span><span class="code-text"><span class="tok-tag">.mock-btn</span> {</span></div>
<div class="code-line"><span class="line-num">11</span><span class="code-text">&nbsp;&nbsp;<span class="tok-attr">background</span>: <span class="tok-str">var(--accent)</span>;</span></div>
<div class="code-line"><span class="line-num">12</span><span class="code-text">&nbsp;&nbsp;<span class="tok-attr">box-shadow</span>: <span class="tok-str">0 0 24px var(--accent-glow)</span>;</span></div>
<div class="code-line"><span class="line-num">13</span><span class="code-text">&nbsp;&nbsp;<span class="tok-attr">transition</span>: <span class="tok-str">transform 0.15s ease</span>;</span></div>
<div class="code-line"><span class="line-num">14</span><span class="code-text">}</span></div>`
        },
        redirects: {
            url: 'https://pagedock.freedev.app/s/acme-studio/old-path \u2192 /about (301)',
            tag: '301 Canonical Rewrite Active',
            title: 'Netlify-Compatible Routing',
            desc: 'Seamless clean URLs, folder index resolution, and custom wildcard proxy redirects.',
            actionHtml: '<div style="font-family:var(--font-mono); font-size:11px; color:#34d399; background:rgba(52,211,153,0.1); padding:8px 12px; border-radius:6px; border:1px solid rgba(52,211,153,0.2);">&check; HTTP/1.1 301 Moved Permanently &rarr; /about</div>',
            code: `<div class="code-line"><span class="line-num">1</span><span class="code-text"><span class="tok-comment"># PageDock _redirects Configuration</span></span></div>
<div class="code-line"><span class="line-num">2</span><span class="code-text"><span class="tok-comment"># Canonical 301 Path Rewrites</span></span></div>
<div class="code-line"><span class="line-num">3</span><span class="code-text"><span class="tok-tag">/old-about</span>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<span class="tok-str">/about</span>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<span class="tok-attr">301</span></span></div>
<div class="code-line"><span class="line-num">4</span><span class="code-text"><span class="tok-tag">/temp-promo</span>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<span class="tok-str">/special</span>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<span class="tok-attr">302</span></span></div>
<div class="code-line"><span class="line-num">5</span><span class="code-text"></span></div>
<div class="code-line"><span class="line-num">6</span><span class="code-text"><span class="tok-comment"># Wildcard &amp; Splat Proxy Routing</span></span></div>
<div class="code-line"><span class="line-num">7</span><span class="code-text"><span class="tok-tag">/blog/*</span>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<span class="tok-str">/articles/:splat</span>&nbsp;&nbsp;&nbsp;<span class="tok-attr">301</span></span></div>
<div class="code-line"><span class="line-num">8</span><span class="code-text"><span class="tok-tag">/api/v1/*</span>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<span class="tok-str">https://api.io/:splat</span> <span class="tok-attr">200!</span></span></div>
<div class="code-line"><span class="line-num">9</span><span class="code-text"></span></div>
<div class="code-line"><span class="line-num">10</span><span class="code-text"><span class="tok-comment"># Single Page App (SPA) Fallback</span></span></div>
<div class="code-line"><span class="line-num">11</span><span class="code-text"><span class="tok-tag">/*</span>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<span class="tok-str">/index.html</span>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<span class="tok-attr">200</span></span></div>`
        }
    };

    editorTabItems.forEach(tab => {
        tab.addEventListener('click', () => {
            const fileKey = tab.getAttribute('data-editor-file');
            if (!fileKey || !showcaseFiles[fileKey]) return;

            editorTabItems.forEach(t => t.classList.remove('active'));
            tab.classList.add('active');

            const fileData = showcaseFiles[fileKey];
            if (showcaseCodeBody) showcaseCodeBody.innerHTML = fileData.code;
            if (showcasePreviewUrl) showcasePreviewUrl.textContent = fileData.url;
            if (showcaseStatusTag) showcaseStatusTag.textContent = fileData.tag;
            if (showcasePreviewTitle) showcasePreviewTitle.textContent = fileData.title;
            if (showcasePreviewDesc) showcasePreviewDesc.textContent = fileData.desc;
            if (showcasePreviewAction) showcasePreviewAction.innerHTML = fileData.actionHtml;
        });
    });
});
