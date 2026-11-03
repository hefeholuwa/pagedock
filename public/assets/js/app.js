// PageDock Core Client Logic

document.addEventListener('DOMContentLoaded', () => {
    // 0. Mobile Navbar Drawer Toggle
    const menuToggle = document.getElementById('mobile-menu-toggle');
    const navDrawer = document.getElementById('mobile-nav-drawer');
    if (menuToggle && navDrawer) {
        menuToggle.addEventListener('click', () => {
            navDrawer.classList.toggle('hidden');
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

    // 2. Drag & Drop File Upload
    const dropzone = document.getElementById('dropzone-trigger');
    const fileInput = document.getElementById('file-input');
    const uploadForm = document.getElementById('upload-form');

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

        dropzone.addEventListener('drop', (e) => {
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                updateDropzoneDisplay(files[0].name);
            }
        });

        fileInput.addEventListener('change', () => {
            if (fileInput.files.length > 0) {
                updateDropzoneDisplay(fileInput.files[0].name);
            }
        });
    }

    function updateDropzoneDisplay(fileName) {
        const titleEl = dropzone.querySelector('.dropzone-title');
        if (titleEl) {
            titleEl.innerHTML = `Selected file: <strong style="color:var(--accent);">${escapeHtml(fileName)}</strong>`;
        }
    }

    // 3. Handle File Upload Submit
    if (uploadForm) {
        uploadForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (!fileInput.files || fileInput.files.length === 0) {
                alert('Please select an .html or .zip file first.');
                return;
            }

            const formData = new FormData(uploadForm);
            showDeployStatus('Uploading and extracting files...');

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

    // 4. Handle Paste HTML Submit
    const pasteForm = document.getElementById('paste-form');
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
    }

    function closePreviewModal() {
        if (!templateModal) return;
        templateModal.classList.add('hidden');
        if (modalIframe) modalIframe.src = 'about:blank';
    }

    document.querySelectorAll('.preview-overlay').forEach(el => {
        el.addEventListener('click', () => {
            const slug = el.getAttribute('data-preview-slug');
            const title = el.getAttribute('data-title');
            openPreviewModal(slug, title);
        });
    });

    document.querySelectorAll('.preview-modal-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const slug = btn.getAttribute('data-slug');
            const title = btn.getAttribute('data-title');
            openPreviewModal(slug, title);
        });
    });

    if (closeTemplateModalBtn) {
        closeTemplateModalBtn.addEventListener('click', closePreviewModal);
    }

    if (templateModal) {
        templateModal.addEventListener('click', (e) => {
            if (e.target === templateModal) closePreviewModal();
        });
    }

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
        if (btnElement) btnElement.textContent = 'Cloning...';

        try {
            const res = await fetch(`/api/templates/${slug}/use`, {
                method: 'POST'
            });
            const data = await res.json();
            if (data.success && data.editor_url) {
                window.location.href = data.editor_url;
            } else {
                alert(data.error || 'Failed to initialize template.');
                if (btnElement) btnElement.innerHTML = origText;
            }
        } catch (err) {
            alert('Network error initializing template.');
            if (btnElement) btnElement.innerHTML = origText;
        }
    }

    document.querySelectorAll('.use-template-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const slug = btn.getAttribute('data-slug');
            useTemplate(slug, btn);
        });
    });

    if (modalUseBtn) {
        modalUseBtn.addEventListener('click', () => {
            const slug = modalUseBtn.getAttribute('data-slug');
            useTemplate(slug, modalUseBtn);
        });
    }
});
