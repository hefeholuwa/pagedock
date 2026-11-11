/**
 * PageDock Studio Controller — Pure HTML & Visual Editor (Zero-AI Edition)
 * Coordinates:
 * 1. Ace Code Editor + Multi-File Buffer Cache + Undo/Redo History Stack
 * 2. Sandboxed Iframe Preview Harness & Bidirectional postMessage Bridge
 * 3. Left Panel 4-Tab Architecture (Code, Library, Structure, Shared)
 * 4. Floating Direct Element Edit Popover (#direct-element-popover)
 * 5. Right-Click Context Menu (#element-context-menu) & Element Clipboard
 * 6. Deterministic Developer Tools Suite (Format, Minify, CSS/JS Extract, SEO, Sitemap)
 * 7. Media Library Modal, Version History Modal, and Keyboard Shortcuts Modal
 */

document.addEventListener('DOMContentLoaded', () => {
    const config = window.STUDIO_CONFIG || {};
    const {
        slug,
        liveUrl,
        claimToken,
        initialFile,
        initialContent,
        files,
        saveUrl,
        getFileUrl,
        versionsUrl,
        mediaUrl,
        sharedBlocksUrl,
        syncSharedUrl,
        pagesUrl
    } = config;

    const previewHarness = window.PAGEDOCK_PREVIEW_HARNESS || null;
    const editorTools = window.PAGEDOCK_EDITOR_TOOLS || {
        LIBRARY_SECTIONS: [],
        LIBRARY_ELEMENTS: [],
        DEVELOPER_TOOLS: [],
        SHORTCUT_GROUPS: []
    };

    // 1. In-memory file buffer cache & Undo/Redo stacks
    const fileCache = {};
    if (initialFile && initialContent !== undefined) {
        fileCache[initialFile] = initialContent;
    }
    const knownFiles = Array.isArray(files) ? [...files] : ['index.html'];

    let activeFile = initialFile || 'index.html';
    let isSaving = false;
    let isDirty = false;
    let isDarkEditor = true;
    let editModeEnabled = true;
    let currentViewport = 'desktop';
    let scaleToFit = true;
    let currentLayout = window.innerWidth <= 768 ? 'code' : 'split';
    let userPickedLayout = false;
    let activePanelTab = 'code';

    // Visual selection & clipboard state
    let selectedElement = null;
    let elementClipboard = null; // { html, style, className }
    let hiddenFingerprints = [];
    let mediaPickerCallback = null;

    // Undo / Redo Stacks
    const undoStack = [];
    const redoStack = [];
    let isApplyingHistory = false;

    // DOM References
    const studioMain = document.getElementById('studio-main');
    const editorPane = document.getElementById('editor-pane');
    const previewPane = document.getElementById('preview-pane');
    const resizer = document.getElementById('studio-resizer');
    const statusDot = document.getElementById('studio-status-dot');
    const previewStage = document.getElementById('preview-stage');
    const previewWrapper = document.getElementById('preview-wrapper');
    const previewDimensions = document.getElementById('preview-dimensions');
    const btnScaleToggle = document.getElementById('btn-scale-toggle');
    const btnRefreshPreview = document.getElementById('btn-refresh-preview');
    const iframe = document.getElementById('preview-iframe');

    const btnUndo = document.getElementById('btn-undo');
    const btnRedo = document.getElementById('btn-redo');
    const btnEditMode = document.getElementById('btn-edit-mode');
    const editModeLabel = document.getElementById('edit-mode-label');
    const btnToolsMenu = document.getElementById('btn-tools-menu');
    const toolsDropdownMenu = document.getElementById('tools-dropdown-menu');
    const toolsMenuItems = document.getElementById('tools-menu-items');

    function authHeaders(extra = {}) {
        const h = { ...extra };
        if (claimToken) {
            h['X-Claim-Token'] = claimToken;
        }
        return h;
    }

    function setDirtyState(dirty) {
        isDirty = dirty;
        if (statusDot) {
            statusDot.classList.toggle('unsaved', dirty);
            statusDot.title = dirty ? 'Unsaved edits in Studio buffer' : 'All changes published';
        }
    }

    function updateUndoRedoButtons() {
        if (btnUndo) btnUndo.disabled = undoStack.length === 0;
        if (btnRedo) btnRedo.disabled = redoStack.length === 0;
    }

    function pushUndoSnapshot(file = activeFile, content = getCurrentContent()) {
        if (isApplyingHistory) return;
        const last = undoStack[undoStack.length - 1];
        if (last && last.file === file && last.content === content) return;
        undoStack.push({ file, content });
        if (undoStack.length > 60) undoStack.shift();
        redoStack.length = 0;
        updateUndoRedoButtons();
    }

    function performUndo() {
        if (undoStack.length === 0) return;
        isApplyingHistory = true;
        const current = { file: activeFile, content: getCurrentContent() };
        redoStack.push(current);
        const prev = undoStack.pop();
        fileCache[prev.file] = prev.content;
        if (prev.file === activeFile) {
            setCurrentContent(prev.content, prev.file, true);
        }
        renderLivePreviewBuffer();
        updateUndoRedoButtons();
        isApplyingHistory = false;
        showToast('Undid last change');
    }

    function performRedo() {
        if (redoStack.length === 0) return;
        isApplyingHistory = true;
        const current = { file: activeFile, content: getCurrentContent() };
        undoStack.push(current);
        const next = redoStack.pop();
        fileCache[next.file] = next.content;
        if (next.file === activeFile) {
            setCurrentContent(next.content, next.file, true);
        }
        renderLivePreviewBuffer();
        updateUndoRedoButtons();
        isApplyingHistory = false;
        showToast('Redid change');
    }

    if (btnUndo) btnUndo.addEventListener('click', performUndo);
    if (btnRedo) btnRedo.addEventListener('click', performRedo);

    // 2. Initialize Ace Editor
    const editorContainer = document.getElementById('ace-editor-container');
    let editor = null;

    function getModeForFile(filename) {
        const ext = (filename || '').split('.').pop().toLowerCase();
        switch (ext) {
            case 'html': case 'htm': return 'ace/mode/html';
            case 'css': return 'ace/mode/css';
            case 'js': case 'mjs': return 'ace/mode/javascript';
            case 'json': return 'ace/mode/json';
            case 'md': case 'markdown': return 'ace/mode/markdown';
            case 'svg': case 'xml': return 'ace/mode/xml';
            default: return 'ace/mode/text';
        }
    }

    let livePreviewTimer = null;
    function scheduleLivePreview() {
        setDirtyState(true);
        if (livePreviewTimer) clearTimeout(livePreviewTimer);
        livePreviewTimer = setTimeout(() => {
            renderLivePreviewBuffer();
        }, 320);
    }

    if (typeof ace !== 'undefined' && editorContainer) {
        editor = ace.edit('ace-editor-container');
        editor.setTheme('ace/theme/tomorrow_night');
        editor.session.setMode(getModeForFile(activeFile));
        editor.setOptions({
            enableBasicAutocompletion: true,
            enableLiveAutocompletion: true,
            showPrintMargin: false,
            wrap: true,
            fontSize: window.innerWidth <= 768 ? '14px' : '13.5px',
            tabSize: 2,
            useSoftTabs: true,
            scrollPastEnd: 0.3
        });
        editor.setValue(initialContent || '', -1);
        editor.session.on('change', () => {
            if (isApplyingHistory) return;
            fileCache[activeFile] = editor.getValue();
            scheduleLivePreview();
        });
    } else if (editorContainer) {
        editorContainer.innerHTML = '<textarea id="fallback-textarea" style="width:100%;height:100%;background:#0f1714;color:#f0f6f3;font-family:\'JetBrains Mono\',monospace;padding:16px;border:none;resize:none;outline:none;font-size:14px;line-height:1.6;"></textarea>';
        const ta = document.getElementById('fallback-textarea');
        ta.value = initialContent || '';
        ta.addEventListener('input', () => {
            if (isApplyingHistory) return;
            fileCache[activeFile] = ta.value;
            scheduleLivePreview();
        });
    }

    function getCurrentContent() {
        if (editor) return editor.getValue();
        const ta = document.getElementById('fallback-textarea');
        return ta ? ta.value : (fileCache[activeFile] || '');
    }

    function setCurrentContent(content, filename = activeFile, keepDirty = false) {
        const prevHistoryFlag = isApplyingHistory;
        isApplyingHistory = true;
        fileCache[filename] = content;
        if (filename === activeFile) {
            if (editor) {
                editor.session.setMode(getModeForFile(filename));
                const cursor = editor.getCursorPosition();
                editor.setValue(content, -1);
                editor.moveCursorToPosition(cursor);
                editor.resize();
            } else {
                const ta = document.getElementById('fallback-textarea');
                if (ta) ta.value = content;
            }
        }
        isApplyingHistory = prevHistoryFlag;
        if (keepDirty) setDirtyState(true);
    }

    function getActiveHtmlFile() {
        return /\.html?$/i.test(activeFile) ? activeFile : 'index.html';
    }

    function getActiveHtmlContent() {
        const target = getActiveHtmlFile();
        if (target === activeFile) return getCurrentContent();
        return fileCache[target] || '';
    }

    function updateActiveHtmlContent(newHtml, shouldRerender = true) {
        const target = getActiveHtmlFile();
        pushUndoSnapshot(target, getActiveHtmlContent());
        fileCache[target] = newHtml;
        if (target === activeFile) {
            setCurrentContent(newHtml, target, true);
        } else {
            setDirtyState(true);
        }
        if (shouldRerender) {
            renderLivePreviewBuffer();
        }
    }

    // Editor Theme Toggle (Dark vs Light Code)
    const btnToggleEditorTheme = document.getElementById('btn-toggle-editor-theme');
    const editorThemeLabel = document.getElementById('editor-theme-label');
    if (btnToggleEditorTheme) {
        btnToggleEditorTheme.addEventListener('click', () => {
            isDarkEditor = !isDarkEditor;
            if (editor) {
                editor.setTheme(isDarkEditor ? 'ace/theme/tomorrow_night' : 'ace/theme/chrome');
            }
            if (editorPane) {
                editorPane.style.background = isDarkEditor ? '#0f1714' : '#ffffff';
            }
            if (editorThemeLabel) {
                editorThemeLabel.textContent = isDarkEditor ? 'Light Code' : 'Dark Code';
            }
        });
    }

    // 3. Edit Mode Toggle (On / Off)
    function setEditMode(enabled) {
        editModeEnabled = !!enabled;
        if (btnEditMode) {
            btnEditMode.classList.toggle('active', editModeEnabled);
            btnEditMode.setAttribute('aria-checked', editModeEnabled ? 'true' : 'false');
        }
        if (editModeLabel) {
            editModeLabel.textContent = editModeEnabled ? 'Edit Mode: On' : 'Edit Mode: Off';
        }
        if (!editModeEnabled) {
            closeDirectPopover();
            closeContextMenu();
        }
        postToIframe({ type: 'edit-mode', enabled: editModeEnabled });
    }

    if (btnEditMode) {
        btnEditMode.addEventListener('click', () => {
            setEditMode(!editModeEnabled);
        });
    }

    function postToIframe(payload) {
        if (iframe && iframe.contentWindow) {
            try {
                iframe.contentWindow.postMessage(payload, '*');
            } catch (e) {}
        }
    }

    // 4. Left Panel 4-Tab Switching (Code, Library, Structure, Shared)
    const panelTabBtns = document.querySelectorAll('.panel-tab-btn');
    const panelViews = document.querySelectorAll('.panel-view');

    function switchLeftPanelTab(tabName) {
        activePanelTab = tabName;
        panelTabBtns.forEach(btn => {
            btn.classList.toggle('active', btn.getAttribute('data-panel-tab') === tabName);
        });
        panelViews.forEach(view => {
            view.classList.toggle('active', view.getAttribute('data-panel-view') === tabName);
        });
        if (currentLayout === 'preview') {
            applyLayoutMode('split');
        }
        if (tabName === 'code' && editor) {
            setTimeout(() => editor.resize(), 30);
        } else if (tabName === 'structure') {
            postToIframe({ type: 'request-structure' });
        }
    }

    panelTabBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const tab = btn.getAttribute('data-panel-tab') || 'code';
            switchLeftPanelTab(tab);
        });
    });

    // 5. Workspace Layout Mode Switcher (Split / Code / Preview)
    const layoutBtns = document.querySelectorAll('.layout-btn');

    function applyLayoutMode(mode) {
        currentLayout = mode;
        if (studioMain) {
            studioMain.classList.remove('layout-split', 'layout-code', 'layout-preview');
            studioMain.classList.add(`layout-${mode}`);
        }
        layoutBtns.forEach(btn => {
            btn.classList.toggle('active', btn.getAttribute('data-layout') === mode);
        });
        setTimeout(() => {
            if (editor) editor.resize();
            updatePreviewScale();
        }, 40);
    }

    layoutBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            userPickedLayout = true;
            const mode = btn.getAttribute('data-layout') || 'split';
            applyLayoutMode(mode);
        });
    });

    applyLayoutMode(currentLayout);

    window.addEventListener('resize', () => {
        if (window.innerWidth <= 768 && currentLayout === 'split') {
            applyLayoutMode('code');
        } else if (window.innerWidth > 768 && !userPickedLayout && currentLayout !== 'split') {
            applyLayoutMode('split');
        } else {
            if (editor) editor.resize();
            updatePreviewScale();
        }
    });

    // 6. Draggable Split Resizer
    if (resizer && studioMain && editorPane) {
        let isDragging = false;

        resizer.addEventListener('mousedown', (e) => {
            if (currentLayout !== 'split') return;
            isDragging = true;
            resizer.classList.add('dragging');
            studioMain.classList.add('resizing');
            e.preventDefault();
        });

        window.addEventListener('mousemove', (e) => {
            if (!isDragging) return;
            const rect = studioMain.getBoundingClientRect();
            const offsetX = e.clientX - rect.left;
            const pct = Math.min(75, Math.max(25, (offsetX / rect.width) * 100));
            editorPane.style.width = `${pct}%`;
            if (editor) editor.resize();
            updatePreviewScale();
        });

        window.addEventListener('mouseup', () => {
            if (!isDragging) return;
            isDragging = false;
            resizer.classList.remove('dragging');
            studioMain.classList.remove('resizing');
            if (editor) editor.resize();
            updatePreviewScale();
        });
    }

    // 7. File Tabs & New File Creation
    const tabsContainer = document.getElementById('file-tabs-container');
    const btnNewFile = document.getElementById('btn-new-file');

    function ensureFileTab(filename) {
        if (!knownFiles.includes(filename)) {
            knownFiles.push(filename);
        }
        if (!tabsContainer) return;
        const exists = tabsContainer.querySelector(`.file-tab[data-file="${CSS.escape(filename)}"]`);
        if (!exists) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'file-tab';
            btn.setAttribute('data-file', filename);
            btn.textContent = filename;
            tabsContainer.appendChild(btn);
        }
    }

    async function switchToFile(targetFile) {
        if (!targetFile || targetFile === activeFile) return;
        fileCache[activeFile] = getCurrentContent();
        ensureFileTab(targetFile);

        document.querySelectorAll('.file-tab').forEach(t => {
            t.classList.toggle('active', t.getAttribute('data-file') === targetFile);
        });
        activeFile = targetFile;

        if (fileCache[targetFile] !== undefined) {
            setCurrentContent(fileCache[targetFile], targetFile, isDirty);
        } else {
            try {
                const res = await fetch(`${getFileUrl}?path=${encodeURIComponent(targetFile)}`, {
                    headers: authHeaders()
                });
                const data = await res.json();
                if (data.success && data.content !== undefined) {
                    fileCache[targetFile] = data.content;
                    setCurrentContent(data.content, targetFile, isDirty);
                }
            } catch (err) {
                showToast('Failed to load file: ' + err.message);
            }
        }
        if (/\.html?$/i.test(targetFile)) {
            renderLivePreviewBuffer();
        }
    }

    if (tabsContainer) {
        tabsContainer.addEventListener('click', (e) => {
            const btn = e.target.closest('.file-tab');
            if (!btn) return;
            switchToFile(btn.getAttribute('data-file'));
        });
    }

    if (btnNewFile) {
        btnNewFile.addEventListener('click', () => {
            const name = window.prompt('Enter new file or page name (e.g. about.html, style.css, script.js):', 'about.html');
            if (!name) return;
            const clean = name.trim().replace(/^\/+/, '');
            if (!clean) return;
            if (fileCache[clean] === undefined) {
                if (/\.html?$/i.test(clean)) {
                    const pageTitle = clean.replace(/\.html?$/i, '');
                    fileCache[clean] = `<!DOCTYPE html>\n<html lang="en">\n<head>\n  <meta charset="UTF-8">\n  <meta name="viewport" content="width=device-width, initial-scale=1.0">\n  <title>${pageTitle}</title>\n</head>\n<body>\n  <main style="max-width:960px;margin:0 auto;padding:64px 24px;">\n    <h1>${pageTitle}</h1>\n    <p>Start editing your new page.</p>\n  </main>\n</body>\n</html>`;
                } else {
                    fileCache[clean] = '';
                }
            }
            ensureFileTab(clean);
            setDirtyState(true);
            switchToFile(clean);
            showToast(`Created ${clean}`);
        });
    }

    // 8. Viewport Switcher & Proportional Scaling Engine
    const viewportBtns = document.querySelectorAll('.viewport-btn');

    function updatePreviewScale() {
        if (!previewStage || !previewWrapper) return;
        const stageRect = previewStage.getBoundingClientRect();
        if (stageRect.width === 0 || stageRect.height === 0) return;

        previewWrapper.style.transform = '';
        previewWrapper.style.width = '';
        previewWrapper.style.height = '';
        previewWrapper.classList.remove('scaled-desktop');

        if (currentViewport === 'desktop') {
            const availW = Math.max(280, stageRect.width - 28);
            const availH = Math.max(240, stageRect.height - 28);
            const virtualDesktopWidth = 1280;

            if (scaleToFit && stageRect.width < 1120) {
                previewStage.classList.remove('stage-desktop-fluid');
                previewWrapper.classList.add('scaled-desktop');
                const scale = Math.min(1, availW / virtualDesktopWidth);
                const scaledHeight = Math.round(availH / scale);
                previewWrapper.style.width = `${virtualDesktopWidth}px`;
                previewWrapper.style.height = `${scaledHeight}px`;
                previewWrapper.style.transform = `scale(${scale.toFixed(3)})`;
                if (previewDimensions) {
                    previewDimensions.innerHTML = `Desktop &bull; 1280px (${Math.round(scale * 100)}%)`;
                }
            } else {
                previewStage.classList.add('stage-desktop-fluid');
                previewWrapper.style.width = '100%';
                previewWrapper.style.height = '100%';
                if (previewDimensions) {
                    previewDimensions.innerHTML = `Desktop &bull; ${Math.round(stageRect.width)}px (100%)`;
                }
            }
        } else if (currentViewport === 'tablet') {
            previewStage.classList.remove('stage-desktop-fluid');
            const targetW = 768;
            const targetH = 1024;
            const availW = Math.max(240, stageRect.width - 32);
            const availH = Math.max(240, stageRect.height - 32);
            const scale = scaleToFit ? Math.min(1, availW / (targetW + 20), availH / (targetH + 20)) : 1;
            previewWrapper.style.width = `${targetW}px`;
            previewWrapper.style.height = `${targetH}px`;
            previewWrapper.style.transform = `scale(${scale.toFixed(3)})`;
            if (previewDimensions) {
                previewDimensions.innerHTML = `Tablet &bull; 768 &times; 1024 (${Math.round(scale * 100)}%)`;
            }
        } else if (currentViewport === 'mobile') {
            previewStage.classList.remove('stage-desktop-fluid');
            const targetW = 375;
            const targetH = 780;
            const availW = Math.max(220, stageRect.width - 28);
            const availH = Math.max(240, stageRect.height - 28);
            const scale = scaleToFit ? Math.min(1, availW / (targetW + 22), availH / (targetH + 22)) : 1;
            previewWrapper.style.width = `${targetW}px`;
            previewWrapper.style.height = `${targetH}px`;
            previewWrapper.style.transform = `scale(${scale.toFixed(3)})`;
            if (previewDimensions) {
                previewDimensions.innerHTML = `Mobile &bull; 375 &times; 780 (${Math.round(scale * 100)}%)`;
            }
        }
    }

    function setDeviceViewport(vp) {
        currentViewport = vp || 'desktop';
        viewportBtns.forEach(b => b.classList.toggle('active', b.getAttribute('data-viewport') === currentViewport));
        if (previewWrapper) {
            previewWrapper.className = `preview-wrapper mode-${currentViewport}`;
        }
        updatePreviewScale();
    }

    viewportBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            setDeviceViewport(btn.getAttribute('data-viewport') || 'desktop');
        });
    });

    if (btnScaleToggle) {
        btnScaleToggle.addEventListener('click', () => {
            scaleToFit = !scaleToFit;
            btnScaleToggle.classList.toggle('active', scaleToFit);
            btnScaleToggle.textContent = scaleToFit ? 'Fit to Pane' : 'Actual 100%';
            updatePreviewScale();
        });
    }

    if (typeof ResizeObserver !== 'undefined' && previewStage) {
        const ro = new ResizeObserver(() => updatePreviewScale());
        ro.observe(previewStage);
    }

    // 9. Live Preview Buffer Synthesis with Sandboxed Inspector Harness Injection
    function renderLivePreviewBuffer() {
        if (!iframe) return;
        const targetHtmlFile = getActiveHtmlFile();
        let html = fileCache[targetHtmlFile];
        if (html === undefined && targetHtmlFile === activeFile) {
            html = getCurrentContent();
        }
        if (html === undefined) {
            const cleanUrl = liveUrl.split('?')[0];
            iframe.src = `${cleanUrl}?_reload=${Date.now()}`;
            return;
        }

        const baseHref = liveUrl.endsWith('/') ? liveUrl : `${liveUrl}/`;
        let doc = html;

        if (/<base\b[^>]*>/i.test(doc)) {
            doc = doc.replace(/<base\b[^>]*>/i, `<base href="${baseHref}">`);
        } else if (/<head[^>]*>/i.test(doc)) {
            doc = doc.replace(/<head[^>]*>/i, (m) => `${m}\n<base href="${baseHref}">`);
        } else {
            doc = `<base href="${baseHref}">\n` + doc;
        }

        if (fileCache['style.css'] !== undefined) {
            const inlineStyle = `<style id="__pd_live_css">\n${fileCache['style.css']}\n</style>`;
            if (/<\/head>/i.test(doc)) {
                doc = doc.replace(/<\/head>/i, `${inlineStyle}\n</head>`);
            } else {
                doc = inlineStyle + '\n' + doc;
            }
        }

        if (previewHarness && typeof previewHarness.injectIntoHtml === 'function') {
            doc = previewHarness.injectIntoHtml(doc);
        }

        iframe.srcdoc = doc;
    }

    if (btnRefreshPreview) {
        btnRefreshPreview.addEventListener('click', () => {
            fileCache[activeFile] = getCurrentContent();
            renderLivePreviewBuffer();
            showToast('Preview refreshed');
        });
    }

    // 10. Source HTML DOM Mutator by (tag, tagIndex)
    function mutateHtmlByTagIndex(tag, tagIndex, mutatorFn) {
        const rawHtml = getActiveHtmlContent();
        if (!rawHtml) return null;

        const parser = new DOMParser();
        const doc = parser.parseFromString(rawHtml, 'text/html');
        const list = doc.getElementsByTagName(tag || 'DIV');
        const target = list[tagIndex || 0];
        if (!target) return null;

        const result = mutatorFn(target, doc);
        if (result === false) return null;

        const hasDoctype = /^\s*<!doctype/i.test(rawHtml);
        const serialized = (hasDoctype ? '<!DOCTYPE html>\n' : '') + doc.documentElement.outerHTML;
        updateActiveHtmlContent(serialized, result && result.fullReload !== false);
        return { serialized, elementHtml: target.outerHTML };
    }

    function insertHtmlSnippet(snippetHtml) {
        const rawHtml = getActiveHtmlContent();
        const parser = new DOMParser();
        const doc = parser.parseFromString(rawHtml || '<!DOCTYPE html><html><head></head><body></body></html>', 'text/html');
        const temp = doc.createElement('template');
        temp.innerHTML = (snippetHtml || '').trim();
        const node = temp.content.firstElementChild;
        if (!node) return;

        let anchor = null;
        if (selectedElement && selectedElement.tag) {
            const list = doc.getElementsByTagName(selectedElement.tag);
            anchor = list[selectedElement.tagIndex || 0];
        }
        if (anchor && anchor !== doc.body && anchor.parentElement) {
            anchor.insertAdjacentElement('afterend', node);
        } else {
            const footer = doc.body.querySelector('footer');
            if (footer) {
                footer.insertAdjacentElement('beforebegin', node);
            } else {
                doc.body.appendChild(node);
            }
        }

        const serialized = '<!DOCTYPE html>\n' + doc.documentElement.outerHTML;
        updateActiveHtmlContent(serialized, true);
    }

    // 11. Component Library Panel (Sections & Elements)
    const libModeBtns = document.querySelectorAll('[data-lib-mode]');
    const libSearchInput = document.getElementById('library-search-input');
    const libCategoriesEl = document.getElementById('library-categories');
    const libItemsContainer = document.getElementById('library-items-container');
    let currentLibMode = 'sections';
    let currentLibCategory = 'All';

    function renderLibraryPanel() {
        if (!libItemsContainer || !libCategoriesEl) return;
        const source = currentLibMode === 'sections'
            ? (editorTools.LIBRARY_SECTIONS || [])
            : (editorTools.LIBRARY_ELEMENTS || []);
        const catField = currentLibMode === 'sections' ? 'category' : 'group';

        const categories = ['All'];
        source.forEach(item => {
            const c = item[catField] || 'Other';
            if (!categories.includes(c)) categories.push(c);
        });

        libCategoriesEl.innerHTML = '';
        categories.forEach(cat => {
            const pill = document.createElement('button');
            pill.type = 'button';
            pill.className = 'category-pill' + (cat === currentLibCategory ? ' active' : '');
            pill.textContent = cat;
            pill.addEventListener('click', () => {
                currentLibCategory = cat;
                renderLibraryPanel();
            });
            libCategoriesEl.appendChild(pill);
        });

        const query = (libSearchInput ? libSearchInput.value : '').trim().toLowerCase();
        const filtered = source.filter(item => {
            const cat = item[catField] || 'Other';
            if (currentLibCategory !== 'All' && cat !== currentLibCategory) return false;
            if (query && !item.name.toLowerCase().includes(query) && !cat.toLowerCase().includes(query)) return false;
            return true;
        });

        libItemsContainer.innerHTML = '';
        filtered.forEach(item => {
            const card = document.createElement('div');
            card.className = 'library-card';
            const cat = item[catField] || 'Component';
            card.innerHTML = `
                <div>
                    <div class="library-card-cat">${cat}</div>
                    <div class="library-card-title">${item.name}</div>
                </div>
                <button type="button" class="library-card-btn">+ Insert</button>
            `;
            card.addEventListener('click', () => {
                insertHtmlSnippet(item.html);
                showToast(`Inserted ${item.name}`);
            });
            libItemsContainer.appendChild(card);
        });
    }

    libModeBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            currentLibMode = btn.getAttribute('data-lib-mode') || 'sections';
            currentLibCategory = 'All';
            libModeBtns.forEach(b => b.classList.toggle('active', b === btn));
            renderLibraryPanel();
        });
    });

    if (libSearchInput) {
        libSearchInput.addEventListener('input', renderLibraryPanel);
    }

    renderLibraryPanel();

    // 12. Structure Tree Panel
    const structureTreeContainer = document.getElementById('structure-tree-container');
    const btnRefreshStructure = document.getElementById('btn-refresh-structure');
    const btnUnhideAll = document.getElementById('btn-unhide-all');

    function renderStructureTree(rootNode) {
        if (!structureTreeContainer) return;
        if (!rootNode) {
            structureTreeContainer.innerHTML = '<p style="font-size:12px;color:var(--studio-muted);">No DOM nodes available.</p>';
            return;
        }
        structureTreeContainer.innerHTML = '';

        function renderNode(node, depth) {
            if (!node) return;
            if (node.tag !== 'BODY') {
                const row = document.createElement('div');
                row.className = 'structure-node';
                if (selectedElement && selectedElement.tag === node.tag && selectedElement.tagIndex === node.tagIndex) {
                    row.classList.add('selected');
                }
                row.style.marginLeft = `${Math.min(depth - 1, 6) * 12}px`;

                const left = document.createElement('div');
                left.className = 'structure-node-left';

                const badge = document.createElement('span');
                badge.className = 'structure-tag-badge';
                badge.textContent = node.label || node.tag.toLowerCase();
                left.appendChild(badge);

                if (node.hint) {
                    const hint = document.createElement('span');
                    hint.className = 'structure-hint';
                    hint.textContent = node.hint;
                    left.appendChild(hint);
                }

                row.appendChild(left);

                row.addEventListener('click', () => {
                    postToIframe({ type: 'select-element', tag: node.tag, tagIndex: node.tagIndex });
                });
                row.addEventListener('mouseenter', () => {
                    postToIframe({ type: 'hover-element', tag: node.tag, tagIndex: node.tagIndex });
                });
                row.addEventListener('mouseleave', () => {
                    postToIframe({ type: 'hover-element', tag: null });
                });

                structureTreeContainer.appendChild(row);
            }

            if (Array.isArray(node.children)) {
                node.children.forEach(c => renderNode(c, depth + 1));
            }
        }

        renderNode(rootNode, 0);
    }

    if (btnRefreshStructure) {
        btnRefreshStructure.addEventListener('click', () => {
            postToIframe({ type: 'request-structure' });
        });
    }

    if (btnUnhideAll) {
        btnUnhideAll.addEventListener('click', () => {
            hiddenFingerprints = [];
            btnUnhideAll.style.display = 'none';
            postToIframe({ type: 'hide-elements', fps: [] });
            showToast('Restored all hidden elements in editor');
        });
    }

    // 13. Shared Header, Footer & Cookie Notice Panel
    const btnSyncShared = document.getElementById('btn-sync-shared');
    const btnRunSharedSync = document.getElementById('btn-run-shared-sync');
    const btnInsertCookieBanner = document.getElementById('btn-insert-cookie-banner');
    const sharedBlocksStatus = document.getElementById('shared-blocks-status');

    async function syncSharedAcrossPages() {
        if (!syncSharedUrl) return;
        try {
            const rawHtml = getActiveHtmlContent();
            const parser = new DOMParser();
            const doc = parser.parseFromString(rawHtml, 'text/html');
            const headerEl = doc.querySelector('header, nav');
            const footerEl = doc.querySelector('footer');

            if (!headerEl && !footerEl) {
                showToast('Add a <header> or <footer> element on this page first');
                return;
            }

            if (headerEl) {
                const resH = await fetch(syncSharedUrl, {
                    method: 'POST',
                    headers: authHeaders({ 'Content-Type': 'application/json' }),
                    body: JSON.stringify({ role: 'header', html: headerEl.outerHTML })
                });
                await resH.json();
            }
            if (footerEl) {
                const resF = await fetch(syncSharedUrl, {
                    method: 'POST',
                    headers: authHeaders({ 'Content-Type': 'application/json' }),
                    body: JSON.stringify({ role: 'footer', html: footerEl.outerHTML })
                });
                await resF.json();
            }
            if (sharedBlocksStatus) {
                sharedBlocksStatus.textContent = 'Shared header and footer synchronized across site pages.';
            }
            showToast('Synced shared header and footer across pages');
        } catch (err) {
            showToast('Failed to sync shared layout');
        }
    }

    if (btnSyncShared) btnSyncShared.addEventListener('click', syncSharedAcrossPages);
    if (btnRunSharedSync) btnRunSharedSync.addEventListener('click', syncSharedAcrossPages);

    if (btnInsertCookieBanner) {
        btnInsertCookieBanner.addEventListener('click', () => {
            const cookieItem = (editorTools.LIBRARY_ELEMENTS || []).find(i => i.id === 'cookie');
            if (cookieItem) {
                insertHtmlSnippet(cookieItem.html);
                showToast('Inserted Cookie Consent Banner');
            }
        });
    }

    // 14. Deterministic Developer Tools Dropdown Menu
    if (toolsMenuItems && Array.isArray(editorTools.DEVELOPER_TOOLS)) {
        editorTools.DEVELOPER_TOOLS.forEach(tool => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'tool-menu-item';
            btn.setAttribute('data-tool-id', tool.id);
            btn.innerHTML = `
                <span class="tool-menu-item-title">${tool.label}</span>
                <span class="tool-menu-item-desc">${tool.description}</span>
            `;
            btn.addEventListener('click', () => {
                toolsDropdownMenu.classList.remove('open');
                const currentHtml = getActiveHtmlContent();
                const res = tool.run(currentHtml, fileCache, { liveUrl, files: knownFiles });
                if (res && typeof res.html === 'string') {
                    updateActiveHtmlContent(res.html, true);
                }
                Object.keys(fileCache).forEach(f => ensureFileTab(f));
                showToast(res && res.summary ? res.summary : `Applied ${tool.label}`);
            });
            toolsMenuItems.appendChild(btn);
        });
    }

    if (btnToolsMenu && toolsDropdownMenu) {
        btnToolsMenu.addEventListener('click', (e) => {
            e.stopPropagation();
            toolsDropdownMenu.classList.toggle('open');
        });
        document.addEventListener('click', (e) => {
            if (!toolsDropdownMenu.contains(e.target) && e.target !== btnToolsMenu) {
                toolsDropdownMenu.classList.remove('open');
            }
        });
    }

    // 15. Direct Element Edit Popover (#direct-element-popover)
    const dePopover = document.getElementById('direct-element-popover');
    const deDragHandle = document.getElementById('de-popover-drag-handle');
    const deTagBadge = document.getElementById('de-tag-badge');
    const deTagSnippet = document.getElementById('de-tag-snippet');
    const btnCloseDePopover = document.getElementById('btn-close-de-popover');

    const deSectionText = document.getElementById('de-section-text');
    const deTextInput = document.getElementById('de-text-input');
    const btnInlineTextEdit = document.getElementById('btn-inline-text-edit');

    const deSectionLink = document.getElementById('de-section-link');
    const deLinkHref = document.getElementById('de-link-href');
    const deLinkBlank = document.getElementById('de-link-blank');

    const deSectionImage = document.getElementById('de-section-image');
    const deImageSrc = document.getElementById('de-image-src');
    const deImageAlt = document.getElementById('de-image-alt');
    const btnDeReplaceImage = document.getElementById('btn-de-replace-image');

    const deSectionBg = document.getElementById('de-section-bg');
    const deBgUrl = document.getElementById('de-bg-url');
    const btnDeReplaceBg = document.getElementById('btn-de-replace-bg');

    const deSectionEmbed = document.getElementById('de-section-embed');
    const deVideoUrl = document.getElementById('de-video-url');
    const deMapAddress = document.getElementById('de-map-address');

    const deSectionForm = document.getElementById('de-section-form');
    const btnDeConnectForm = document.getElementById('btn-de-connect-form');
    const deFormName = document.getElementById('de-form-name');
    const deFormSuccess = document.getElementById('de-form-success');
    const deFormRedirect = document.getElementById('de-form-redirect');
    const deFormError = document.getElementById('de-form-error');

    const deStyleColor = document.getElementById('de-style-color');
    const deStyleColorHex = document.getElementById('de-style-color-hex');
    const deStyleSize = document.getElementById('de-style-size');
    const deStyleWeight = document.getElementById('de-style-weight');
    const deAlignBtns = document.querySelectorAll('[data-de-align]');
    const deFooterBtns = document.querySelectorAll('[data-de-action]');

    function closeDirectPopover() {
        selectedElement = null;
        if (dePopover) dePopover.classList.remove('open');
        postToIframe({ type: 'clear-selection' });
    }

    if (btnCloseDePopover) {
        btnCloseDePopover.addEventListener('click', closeDirectPopover);
    }

    // Draggable Direct Element Popover
    if (deDragHandle && dePopover) {
        let draggingPopover = false;
        let popStartX = 0, popStartY = 0, popOrigLeft = 0, popOrigTop = 0;

        deDragHandle.addEventListener('mousedown', (e) => {
            if (e.target.closest('button')) return;
            draggingPopover = true;
            const r = dePopover.getBoundingClientRect();
            popStartX = e.clientX;
            popStartY = e.clientY;
            popOrigLeft = r.left;
            popOrigTop = r.top;
            dePopover.style.right = 'auto';
            dePopover.style.bottom = 'auto';
            dePopover.style.left = `${popOrigLeft}px`;
            dePopover.style.top = `${popOrigTop}px`;
            e.preventDefault();
        });

        window.addEventListener('mousemove', (e) => {
            if (!draggingPopover) return;
            const nx = Math.max(8, Math.min(window.innerWidth - 350, popOrigLeft + (e.clientX - popStartX)));
            const ny = Math.max(60, Math.min(window.innerHeight - 120, popOrigTop + (e.clientY - popStartY)));
            dePopover.style.left = `${nx}px`;
            dePopover.style.top = `${ny}px`;
        });

        window.addEventListener('mouseup', () => {
            draggingPopover = false;
        });
    }

    function openDirectPopover(payload) {
        selectedElement = payload;
        if (!dePopover) return;

        const tag = (payload.tag || 'DIV').toUpperCase();
        if (deTagBadge) deTagBadge.textContent = tag.toLowerCase();
        if (deTagSnippet) deTagSnippet.textContent = payload.text ? payload.text.slice(0, 32) : (payload.attrs && payload.attrs.id ? `#${payload.attrs.id}` : tag.toLowerCase());

        // 1. Text Section
        const showText = payload.isTextOnly || (payload.text && payload.text.length > 0 && tag !== 'FORM');
        if (deSectionText) deSectionText.style.display = showText ? 'flex' : 'none';
        if (deTextInput) deTextInput.value = payload.text || '';

        // 2. Link Section
        const isLinkOrBtn = tag === 'A' || tag === 'BUTTON' || (payload.attrs && payload.attrs.href);
        if (deSectionLink) deSectionLink.style.display = isLinkOrBtn ? 'flex' : 'none';
        if (deLinkHref) deLinkHref.value = (payload.attrs && payload.attrs.href) || '';
        if (deLinkBlank) deLinkBlank.checked = (payload.attrs && payload.attrs.target) === '_blank';

        // 3. Image Section
        const isImg = tag === 'IMG';
        if (deSectionImage) deSectionImage.style.display = isImg ? 'flex' : 'none';
        if (deImageSrc) deImageSrc.value = (payload.attrs && payload.attrs.src) || '';
        if (deImageAlt) deImageAlt.value = (payload.attrs && payload.attrs.alt) || '';

        // 4. Background Photo Section
        const hasBg = !!(payload.bgUrl) || tag === 'SECTION' || tag === 'HEADER' || tag === 'DIV';
        if (deSectionBg) deSectionBg.style.display = hasBg && !isImg ? 'flex' : 'none';
        if (deBgUrl) deBgUrl.value = payload.bgUrl || '';

        // 5. Video & Map Embed Section
        const isEmbed = tag === 'IFRAME' || tag === 'VIDEO';
        if (deSectionEmbed) deSectionEmbed.style.display = isEmbed ? 'flex' : 'none';
        if (deVideoUrl) deVideoUrl.value = payload.mediaUrl || '';

        // 6. Form Section
        const hasForm = !!payload.form || tag === 'FORM';
        if (deSectionForm) deSectionForm.style.display = hasForm ? 'flex' : 'none';
        if (payload.form) {
            if (deFormName) deFormName.value = payload.form.name || 'Contact';
            if (deFormSuccess) deFormSuccess.value = payload.form.success || '';
            if (deFormRedirect) deFormRedirect.value = payload.form.redirect || '';
            if (deFormError) deFormError.value = payload.form.error || '';
            if (btnDeConnectForm) {
                btnDeConnectForm.textContent = payload.form.delivered ? 'Connected to Site Inbox (/f/)' : 'Send messages to my inbox';
            }
        }

        // 7. Style Section
        if (payload.computedStyles) {
            const hex = payload.computedStyles.color || '#0b1411';
            if (deStyleColor && /^#[0-9a-fA-F]{6}$/.test(hex)) deStyleColor.value = hex;
            if (deStyleColorHex) deStyleColorHex.value = hex;
            if (deStyleSize) deStyleSize.value = payload.computedStyles.fontSize || '16px';
            if (deStyleWeight) deStyleWeight.value = payload.computedStyles.fontWeight || '400';
            deAlignBtns.forEach(b => {
                b.classList.toggle('active', b.getAttribute('data-de-align') === payload.computedStyles.textAlign);
            });
        }

        dePopover.classList.add('open');
    }

    // Popover Live Controls
    if (deTextInput) {
        deTextInput.addEventListener('change', () => {
            if (!selectedElement) return;
            const val = deTextInput.value;
            const res = mutateHtmlByTagIndex(selectedElement.tag, selectedElement.tagIndex, (el) => {
                el.textContent = val;
                return { fullReload: false };
            });
            if (res) {
                postToIframe({ type: 'patch-element', tag: selectedElement.tag, tagIndex: selectedElement.tagIndex, html: res.elementHtml });
            }
        });
    }

    if (btnInlineTextEdit) {
        btnInlineTextEdit.addEventListener('click', () => {
            postToIframe({ type: 'begin-text-edit' });
        });
    }

    function applyLinkChanges() {
        if (!selectedElement) return;
        const href = deLinkHref ? deLinkHref.value.trim() : '';
        const blank = deLinkBlank ? deLinkBlank.checked : false;
        const res = mutateHtmlByTagIndex(selectedElement.tag, selectedElement.tagIndex, (el) => {
            const linkTarget = el.tagName === 'A' ? el : (el.closest('a') || el);
            if (href) linkTarget.setAttribute('href', href);
            if (blank) {
                linkTarget.setAttribute('target', '_blank');
                linkTarget.setAttribute('rel', 'noopener');
            } else {
                linkTarget.removeAttribute('target');
            }
            return { fullReload: false };
        });
        if (res) {
            postToIframe({ type: 'patch-element', tag: selectedElement.tag, tagIndex: selectedElement.tagIndex, html: res.elementHtml });
        }
    }

    if (deLinkHref) deLinkHref.addEventListener('change', applyLinkChanges);
    if (deLinkBlank) deLinkBlank.addEventListener('change', applyLinkChanges);

    function applyImageChanges() {
        if (!selectedElement) return;
        const src = deImageSrc ? deImageSrc.value.trim() : '';
        const alt = deImageAlt ? deImageAlt.value.trim() : '';
        const res = mutateHtmlByTagIndex(selectedElement.tag, selectedElement.tagIndex, (el) => {
            if (src) el.setAttribute('src', src);
            el.setAttribute('alt', alt);
            return { fullReload: false };
        });
        if (res) {
            postToIframe({ type: 'patch-element', tag: selectedElement.tag, tagIndex: selectedElement.tagIndex, html: res.elementHtml });
        }
    }

    if (deImageSrc) deImageSrc.addEventListener('change', applyImageChanges);
    if (deImageAlt) deImageAlt.addEventListener('change', applyImageChanges);

    if (btnDeReplaceImage) {
        btnDeReplaceImage.addEventListener('click', () => {
            openMediaLibraryModal((url) => {
                if (deImageSrc) deImageSrc.value = url;
                applyImageChanges();
            });
        });
    }

    function applyBgChanges() {
        if (!selectedElement) return;
        const url = deBgUrl ? deBgUrl.value.trim() : '';
        const res = mutateHtmlByTagIndex(selectedElement.tag, selectedElement.tagIndex, (el) => {
            if (url) {
                el.style.backgroundImage = `url('${url}')`;
                el.style.backgroundSize = 'cover';
                el.style.backgroundPosition = 'center';
            } else {
                el.style.backgroundImage = '';
            }
            return { fullReload: false };
        });
        if (res) {
            postToIframe({ type: 'patch-element', tag: selectedElement.tag, tagIndex: selectedElement.tagIndex, html: res.elementHtml });
        }
    }

    if (deBgUrl) deBgUrl.addEventListener('change', applyBgChanges);
    if (btnDeReplaceBg) {
        btnDeReplaceBg.addEventListener('click', () => {
            openMediaLibraryModal((url) => {
                if (deBgUrl) deBgUrl.value = url;
                applyBgChanges();
            });
        });
    }

    function parseVideoEmbedUrl(rawUrl) {
        const u = (rawUrl || '').trim();
        const ytMatch = u.match(/(?:youtube\.com\/(?:watch\?v=|shorts\/|embed\/)|youtu\.be\/)([a-zA-Z0-9_-]{6,})/i);
        if (ytMatch) return `https://www.youtube.com/embed/${ytMatch[1]}`;
        const vimeoMatch = u.match(/vimeo\.com\/(?:video\/)?(\d+)/i);
        if (vimeoMatch) return `https://player.vimeo.com/video/${vimeoMatch[1]}`;
        return u;
    }

    if (deVideoUrl) {
        deVideoUrl.addEventListener('change', () => {
            if (!selectedElement) return;
            const embedUrl = parseVideoEmbedUrl(deVideoUrl.value);
            deVideoUrl.value = embedUrl;
            const res = mutateHtmlByTagIndex(selectedElement.tag, selectedElement.tagIndex, (el) => {
                el.setAttribute('src', embedUrl);
                return { fullReload: false };
            });
            if (res) {
                postToIframe({ type: 'patch-element', tag: selectedElement.tag, tagIndex: selectedElement.tagIndex, html: res.elementHtml });
            }
        });
    }

    if (deMapAddress) {
        deMapAddress.addEventListener('change', () => {
            if (!selectedElement) return;
            const q = deMapAddress.value.trim();
            if (!q) return;
            const mapSrc = `https://maps.google.com/maps?q=${encodeURIComponent(q)}&z=14&output=embed`;
            const res = mutateHtmlByTagIndex(selectedElement.tag, selectedElement.tagIndex, (el) => {
                el.setAttribute('src', mapSrc);
                return { fullReload: false };
            });
            if (res) {
                postToIframe({ type: 'patch-element', tag: selectedElement.tag, tagIndex: selectedElement.tagIndex, html: res.elementHtml });
            }
        });
    }

    // Form Connection & Hidden Inputs
    function updateSelectedForm(connectInbox = false) {
        if (!selectedElement) return;
        const formIdx = selectedElement.form ? selectedElement.form.tagIndex : selectedElement.tagIndex;
        mutateHtmlByTagIndex('FORM', formIdx, (formEl, doc) => {
            if (connectInbox) {
                formEl.setAttribute('action', `/f/${slug}`);
                formEl.setAttribute('method', 'POST');
            }
            function setHidden(name, value) {
                let inp = formEl.querySelector(`input[name="${name}"]`);
                if (value) {
                    if (!inp) {
                        inp = doc.createElement('input');
                        inp.type = 'hidden';
                        inp.name = name;
                        formEl.insertBefore(inp, formEl.firstChild);
                    }
                    inp.value = value;
                } else if (inp) {
                    inp.remove();
                }
            }
            if (deFormName) setHidden('_form', deFormName.value.trim());
            if (deFormSuccess) setHidden('_success', deFormSuccess.value.trim());
            if (deFormRedirect) setHidden('_redirect', deFormRedirect.value.trim());
            if (deFormError) setHidden('_error', deFormError.value.trim());
            return { fullReload: true };
        });
        showToast('Updated form inbox settings');
    }

    if (btnDeConnectForm) btnDeConnectForm.addEventListener('click', () => updateSelectedForm(true));
    if (deFormName) deFormName.addEventListener('change', () => updateSelectedForm(false));
    if (deFormSuccess) deFormSuccess.addEventListener('change', () => updateSelectedForm(false));
    if (deFormRedirect) deFormRedirect.addEventListener('change', () => updateSelectedForm(false));
    if (deFormError) deFormError.addEventListener('change', () => updateSelectedForm(false));

    // Typography & Style Controls
    function applyStyleProp(prop, val) {
        if (!selectedElement) return;
        const res = mutateHtmlByTagIndex(selectedElement.tag, selectedElement.tagIndex, (el) => {
            el.style[prop] = val;
            return { fullReload: false };
        });
        if (res) {
            postToIframe({ type: 'patch-element', tag: selectedElement.tag, tagIndex: selectedElement.tagIndex, html: res.elementHtml });
        }
    }

    if (deStyleColor) {
        deStyleColor.addEventListener('input', () => {
            if (deStyleColorHex) deStyleColorHex.value = deStyleColor.value;
            applyStyleProp('color', deStyleColor.value);
        });
    }
    if (deStyleColorHex) {
        deStyleColorHex.addEventListener('change', () => {
            if (deStyleColor && /^#[0-9a-fA-F]{6}$/.test(deStyleColorHex.value)) {
                deStyleColor.value = deStyleColorHex.value;
            }
            applyStyleProp('color', deStyleColorHex.value);
        });
    }
    if (deStyleSize) {
        deStyleSize.addEventListener('change', () => {
            let v = deStyleSize.value.trim();
            if (/^\d+$/.test(v)) v += 'px';
            applyStyleProp('fontSize', v);
        });
    }
    if (deStyleWeight) {
        deStyleWeight.addEventListener('change', () => {
            applyStyleProp('fontWeight', deStyleWeight.value);
        });
    }
    deAlignBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const align = btn.getAttribute('data-de-align') || 'left';
            deAlignBtns.forEach(b => b.classList.toggle('active', b === btn));
            applyStyleProp('textAlign', align);
        });
    });

    // 16. Element Actions (Duplicate, Move, Delete, Copy/Cut/Paste, Show in Code)
    function executeElementAction(action) {
        if (!selectedElement) return;
        const { tag, tagIndex, hideFp } = selectedElement;

        if (action === 'select-parent') {
            postToIframe({ type: 'select-parent' });
            return;
        }

        if (action === 'show-source') {
            switchLeftPanelTab('code');
            if (editor && selectedElement.text) {
                editor.find(selectedElement.text.slice(0, 32), { backwards: false, wrap: true, caseSensitive: false });
            }
            return;
        }

        if (action === 'reveal-structure') {
            switchLeftPanelTab('structure');
            return;
        }

        if (action === 'hide-element') {
            if (hideFp && !hiddenFingerprints.includes(hideFp)) {
                hiddenFingerprints.push(hideFp);
                if (btnUnhideAll) btnUnhideAll.style.display = 'inline-block';
                postToIframe({ type: 'hide-elements', fps: hiddenFingerprints });
                showToast('Hidden element in editor preview');
            }
            return;
        }

        if (action === 'copy') {
            elementClipboard = {
                html: selectedElement.html,
                style: (selectedElement.attrs && selectedElement.attrs.style) || '',
                className: (selectedElement.attrs && selectedElement.attrs.className) || ''
            };
            showToast('Copied element');
            return;
        }

        if (action === 'cut') {
            elementClipboard = {
                html: selectedElement.html,
                style: (selectedElement.attrs && selectedElement.attrs.style) || '',
                className: (selectedElement.attrs && selectedElement.attrs.className) || ''
            };
            mutateHtmlByTagIndex(tag, tagIndex, (el) => {
                el.remove();
            });
            closeDirectPopover();
            showToast('Cut element');
            return;
        }

        if (action === 'paste-after' && elementClipboard && elementClipboard.html) {
            mutateHtmlByTagIndex(tag, tagIndex, (el, doc) => {
                const t = doc.createElement('template');
                t.innerHTML = elementClipboard.html.trim();
                const clone = t.content.firstElementChild;
                if (clone && el.parentElement) {
                    el.insertAdjacentElement('afterend', clone);
                }
            });
            showToast('Pasted element after selection');
            return;
        }

        if (action === 'paste-inside' && elementClipboard && elementClipboard.html) {
            mutateHtmlByTagIndex(tag, tagIndex, (el, doc) => {
                const t = doc.createElement('template');
                t.innerHTML = elementClipboard.html.trim();
                const clone = t.content.firstElementChild;
                if (clone) {
                    el.appendChild(clone);
                }
            });
            showToast('Pasted element inside selection');
            return;
        }

        if (action === 'paste-style' && elementClipboard) {
            mutateHtmlByTagIndex(tag, tagIndex, (el) => {
                if (elementClipboard.className) el.className = elementClipboard.className;
                if (elementClipboard.style) el.setAttribute('style', elementClipboard.style);
            });
            showToast('Pasted element style');
            return;
        }

        if (action === 'duplicate') {
            mutateHtmlByTagIndex(tag, tagIndex, (el) => {
                const clone = el.cloneNode(true);
                if (clone.id) clone.removeAttribute('id');
                if (el.parentElement) el.insertAdjacentElement('afterend', clone);
            });
            showToast('Duplicated element');
            return;
        }

        if (action === 'move-up') {
            mutateHtmlByTagIndex(tag, tagIndex, (el) => {
                const prev = el.previousElementSibling;
                if (prev && el.parentElement) {
                    el.parentElement.insertBefore(el, prev);
                }
            });
            return;
        }

        if (action === 'move-down') {
            mutateHtmlByTagIndex(tag, tagIndex, (el) => {
                const next = el.nextElementSibling;
                if (next && el.parentElement) {
                    el.parentElement.insertBefore(next, el);
                }
            });
            return;
        }

        if (action === 'delete') {
            mutateHtmlByTagIndex(tag, tagIndex, (el) => {
                el.remove();
            });
            closeDirectPopover();
            showToast('Deleted element');
        }
    }

    deFooterBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            executeElementAction(btn.getAttribute('data-de-action'));
        });
    });

    // 17. Right-Click Context Menu (#element-context-menu)
    const contextMenu = document.getElementById('element-context-menu');
    const ctxButtons = document.querySelectorAll('[data-ctx-action]');

    function closeContextMenu() {
        if (contextMenu) contextMenu.classList.remove('open');
    }

    function openContextMenu(payload) {
        selectedElement = payload;
        if (!contextMenu || !iframe) return;
        const iframeRect = iframe.getBoundingClientRect();
        const x = payload.screenPos ? payload.screenPos.x : (iframeRect.left + (payload.clientX || 120));
        const y = payload.screenPos ? payload.screenPos.y : (iframeRect.top + (payload.clientY || 120));

        contextMenu.style.left = `${Math.min(window.innerWidth - 225, Math.max(8, x))}px`;
        contextMenu.style.top = `${Math.min(window.innerHeight - 360, Math.max(8, y))}px`;
        contextMenu.classList.add('open');
    }

    ctxButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            const act = btn.getAttribute('data-ctx-action');
            closeContextMenu();
            executeElementAction(act);
        });
    });

    document.addEventListener('click', (e) => {
        if (contextMenu && !contextMenu.contains(e.target)) {
            closeContextMenu();
        }
    });

    // 18. Bidirectional postMessage Bridge Listener
    window.addEventListener('message', (e) => {
        const d = e.data;
        if (!d || typeof d !== 'object' || !d.type) return;

        switch (d.type) {
            case 'buffer-dom-ready':
                postToIframe({ type: 'edit-mode', enabled: editModeEnabled });
                if (hiddenFingerprints.length > 0) {
                    postToIframe({ type: 'hide-elements', fps: hiddenFingerprints });
                }
                break;

            case 'element-selected':
                closeContextMenu();
                openDirectPopover(d);
                break;

            case 'element-context-menu':
                openDirectPopover(d);
                openContextMenu(d);
                break;

            case 'element-text-edit':
                mutateHtmlByTagIndex(d.tag, d.tagIndex, (el) => {
                    el.textContent = d.text || '';
                    return { fullReload: false };
                });
                if (selectedElement && selectedElement.tag === d.tag && selectedElement.tagIndex === d.tagIndex && deTextInput) {
                    deTextInput.value = d.text || '';
                }
                showToast('Updated text in place');
                break;

            case 'page-structure':
                renderStructureTree(d.tree);
                break;

            case 'form-preview-submit':
                showToast((d.form && d.form.success) ? d.form.success : 'Form submission simulated in preview');
                break;

            case 'navigate-page':
                if (d.path) {
                    const target = d.path.replace(/^\/+/, '') || 'index.html';
                    const fileCandidate = /\.html?$/i.test(target) ? target : `${target}.html`;
                    if (knownFiles.includes(fileCandidate)) {
                        switchToFile(fileCandidate);
                    }
                }
                break;

            case 'iframe-escape':
                closeContextMenu();
                closeDirectPopover();
                break;

            case 'iframe-key':
                handleShortcutKey(d);
                break;
        }
    });

    // 19. Modals: Media Library, Version History, and Keyboard Shortcuts
    function closeAllModals() {
        document.querySelectorAll('.studio-modal-backdrop').forEach(m => m.classList.remove('open'));
    }

    document.querySelectorAll('[data-close-modal]').forEach(btn => {
        btn.addEventListener('click', () => {
            const id = btn.getAttribute('data-close-modal');
            const modal = document.getElementById(id);
            if (modal) modal.classList.remove('open');
        });
    });

    document.querySelectorAll('.studio-modal-backdrop').forEach(backdrop => {
        backdrop.addEventListener('click', (e) => {
            if (e.target === backdrop) backdrop.classList.remove('open');
        });
    });

    // Media Library Modal
    const mediaModal = document.getElementById('media-library-modal');
    const btnMediaModal = document.getElementById('btn-media-modal');
    const mediaUploadInput = document.getElementById('media-upload-input');
    const mediaGrid = document.getElementById('media-library-grid');

    async function loadMediaLibrary() {
        if (!mediaGrid || !mediaUrl) return;
        try {
            const res = await fetch(mediaUrl, { headers: authHeaders() });
            const data = await res.json();
            const list = Array.isArray(data.media) ? data.media : (Array.isArray(data.files) ? data.files : []);
            if (list.length === 0) {
                mediaGrid.innerHTML = '<p style="font-size:12.5px;color:var(--studio-muted);">No media files uploaded yet.</p>';
                return;
            }
            mediaGrid.innerHTML = '';
            list.forEach(item => {
                const fileUrl = item.url || `${liveUrl.replace(/\/$/, '')}/${item.path || item.name}`;
                const name = item.name || item.path || 'media';
                const card = document.createElement('div');
                card.className = 'media-item-card';
                card.innerHTML = `
                    <div class="media-thumb"><img src="${fileUrl}" alt="${name}" loading="lazy"></div>
                    <div class="media-meta">${name}</div>
                `;
                card.addEventListener('click', () => {
                    if (typeof mediaPickerCallback === 'function') {
                        mediaPickerCallback(fileUrl);
                        mediaPickerCallback = null;
                    } else {
                        insertHtmlSnippet(`<img src="${fileUrl}" alt="${name}" style="max-width:100%;height:auto;border-radius:12px;display:block;">`);
                        showToast(`Inserted ${name}`);
                    }
                    if (mediaModal) mediaModal.classList.remove('open');
                });
                mediaGrid.appendChild(card);
            });
        } catch (err) {
            mediaGrid.innerHTML = '<p style="font-size:12.5px;color:var(--studio-muted);">Could not load media library.</p>';
        }
    }

    function openMediaLibraryModal(onPick = null) {
        mediaPickerCallback = onPick;
        if (mediaModal) mediaModal.classList.add('open');
        loadMediaLibrary();
    }

    if (btnMediaModal) {
        btnMediaModal.addEventListener('click', () => openMediaLibraryModal(null));
    }

    if (mediaUploadInput && mediaUrl) {
        mediaUploadInput.addEventListener('change', async () => {
            const file = mediaUploadInput.files && mediaUploadInput.files[0];
            if (!file) return;
            const fd = new FormData();
            fd.append('file', file);
            try {
                const res = await fetch(mediaUrl, {
                    method: 'POST',
                    headers: authHeaders(),
                    body: fd
                });
                const data = await res.json();
                if (data.success) {
                    showToast(`Uploaded ${file.name}`);
                    loadMediaLibrary();
                } else {
                    showToast(data.error || 'Upload failed');
                }
            } catch (err) {
                showToast('Media upload error');
            }
            mediaUploadInput.value = '';
        });
    }

    // Version History Modal
    const historyModal = document.getElementById('version-history-modal');
    const btnHistoryModal = document.getElementById('btn-history-modal');
    const versionHistoryList = document.getElementById('version-history-list');

    async function openVersionHistoryModal() {
        if (!historyModal) return;
        historyModal.classList.add('open');
        if (!versionHistoryList || !versionsUrl) return;
        versionHistoryList.innerHTML = '<p style="font-size:12.5px;color:var(--studio-muted);">Loading version snapshots...</p>';
        try {
            const res = await fetch(versionsUrl, { headers: authHeaders() });
            const data = await res.json();
            const versions = Array.isArray(data.versions) ? data.versions : [];
            if (versions.length === 0) {
                versionHistoryList.innerHTML = '<p style="font-size:12.5px;color:var(--studio-muted);">No version history recorded yet.</p>';
                return;
            }
            versionHistoryList.innerHTML = '';
            versions.forEach(v => {
                const vNum = v.version_number || v.version;
                const row = document.createElement('div');
                row.className = 'version-row';
                row.innerHTML = `
                    <div>
                        <div style="font-size:13px;font-weight:700;">Version v${vNum}</div>
                        <div style="font-size:11.5px;color:var(--studio-muted);">${v.change_summary || 'Snapshot'} &bull; ${v.created_at || ''}</div>
                    </div>
                    <button type="button" class="de-inline-btn">Restore v${vNum}</button>
                `;
                const restoreBtn = row.querySelector('button');
                restoreBtn.addEventListener('click', async () => {
                    try {
                        const rRes = await fetch(`${versionsUrl}/${vNum}/restore`, {
                            method: 'POST',
                            headers: authHeaders({ 'Content-Type': 'application/json' })
                        });
                        const rData = await rRes.json();
                        if (rData.success) {
                            showToast(`Restored to v${vNum}. Reloading...`);
                            setTimeout(() => window.location.reload(), 600);
                        } else {
                            showToast(rData.error || 'Restore failed');
                        }
                    } catch (e) {
                        showToast('Failed to restore version');
                    }
                });
                versionHistoryList.appendChild(row);
            });
        } catch (err) {
            versionHistoryList.innerHTML = '<p style="font-size:12.5px;color:var(--studio-muted);">Could not load version history.</p>';
        }
    }

    if (btnHistoryModal) {
        btnHistoryModal.addEventListener('click', openVersionHistoryModal);
    }

    // Keyboard Shortcuts Modal
    const shortcutsModal = document.getElementById('shortcuts-help-modal');
    const btnShortcutsModal = document.getElementById('btn-shortcuts-modal');
    const shortcutsContent = document.getElementById('shortcuts-modal-content');

    function renderShortcutsModal() {
        if (!shortcutsContent) return;
        shortcutsContent.innerHTML = '';
        (editorTools.SHORTCUT_GROUPS || []).forEach(group => {
            const col = document.createElement('div');
            let rowsHtml = `<div class="shortcut-group-title">${group.title}</div>`;
            (group.items || []).forEach(it => {
                rowsHtml += `
                    <div class="shortcut-row">
                        <span>${it.label}</span>
                        <kbd class="shortcut-keys">${it.keys}</kbd>
                    </div>
                `;
            });
            col.innerHTML = rowsHtml;
            shortcutsContent.appendChild(col);
        });
    }

    renderShortcutsModal();

    if (btnShortcutsModal && shortcutsModal) {
        btnShortcutsModal.addEventListener('click', () => {
            shortcutsModal.classList.add('open');
        });
    }

    // 20. Save & Publish
    const btnPublish = document.getElementById('btn-save-publish');

    async function saveAndPublish() {
        if (isSaving) return;
        isSaving = true;

        if (btnPublish) {
            btnPublish.disabled = true;
            btnPublish.innerHTML = `<svg class="spinner" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10" stroke-dasharray="32" stroke-dashoffset="10"/></svg> <span>Publishing...</span>`;
        }

        fileCache[activeFile] = getCurrentContent();

        try {
            const res = await fetch(saveUrl, {
                method: 'POST',
                headers: authHeaders({ 'Content-Type': 'application/json' }),
                body: JSON.stringify({
                    files: fileCache,
                    summary: `Studio web update (${activeFile})`
                })
            });
            const data = await res.json();

            if (data.success) {
                setDirtyState(false);
                const version = data.version ? `v${data.version}` : 'updates';
                showToast(`Saved & Published ${version}`);
                renderLivePreviewBuffer();
            } else {
                showToast('Error: ' + (data.error || 'Failed to publish'));
            }
        } catch (err) {
            showToast('Network error while publishing');
        } finally {
            isSaving = false;
            if (btnPublish) {
                btnPublish.disabled = false;
                btnPublish.innerHTML = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg> <span>Publish</span>`;
            }
        }
    }

    if (btnPublish) {
        btnPublish.addEventListener('click', saveAndPublish);
    }

    // 21. Unified Keyboard Shortcuts Handler
    function handleShortcutKey(e) {
        const cmd = !!(e.metaKey || e.ctrlKey);
        const shift = !!e.shiftKey;
        const alt = !!e.altKey;
        const key = (e.key || '').toLowerCase();

        if (cmd && key === 's') {
            if (e.preventDefault) e.preventDefault();
            saveAndPublish();
            return;
        }
        if (cmd && !shift && key === 'z') {
            if (e.preventDefault) e.preventDefault();
            performUndo();
            return;
        }
        if (cmd && shift && key === 'z') {
            if (e.preventDefault) e.preventDefault();
            performRedo();
            return;
        }
        if (cmd && shift && key === 'e') {
            if (e.preventDefault) e.preventDefault();
            setEditMode(!editModeEnabled);
            return;
        }
        if (cmd && !shift && key === 'i') {
            if (e.preventDefault) e.preventDefault();
            switchLeftPanelTab('structure');
            return;
        }
        if (cmd && shift && key === 'l') {
            if (e.preventDefault) e.preventDefault();
            switchLeftPanelTab('library');
            return;
        }
        if (cmd && shift && key === 'm') {
            if (e.preventDefault) e.preventDefault();
            const order = ['desktop', 'tablet', 'mobile'];
            const next = order[(order.indexOf(currentViewport) + 1) % order.length];
            setDeviceViewport(next);
            return;
        }
        if (cmd && shift && key === 'h') {
            if (e.preventDefault) e.preventDefault();
            openVersionHistoryModal();
            return;
        }
        if (cmd && (key === '/' || key === '?')) {
            if (e.preventDefault) e.preventDefault();
            if (shortcutsModal) shortcutsModal.classList.toggle('open');
            return;
        }

        if (key === 'escape') {
            closeAllModals();
            closeContextMenu();
            closeDirectPopover();
            return;
        }

        // Selected element shortcuts
        if (!selectedElement) return;
        if (cmd && key === 'd') {
            if (e.preventDefault) e.preventDefault();
            executeElementAction('duplicate');
        } else if (alt && key === 'arrowup') {
            if (e.preventDefault) e.preventDefault();
            executeElementAction('move-up');
        } else if (alt && key === 'arrowdown') {
            if (e.preventDefault) e.preventDefault();
            executeElementAction('move-down');
        } else if (alt && key === 'arrowleft') {
            if (e.preventDefault) e.preventDefault();
            executeElementAction('select-parent');
        } else if (cmd && shift && key === 'v') {
            if (e.preventDefault) e.preventDefault();
            executeElementAction('paste-style');
        }
    }

    window.addEventListener('keydown', (e) => {
        const tag = e.target && e.target.tagName;
        const isTyping = tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || (e.target && e.target.isContentEditable);
        if (isTyping && !(e.metaKey || e.ctrlKey) && e.key !== 'Escape') return;
        handleShortcutKey(e);
    });

    // 22. Toast Notifications
    const toast = document.getElementById('studio-toast');
    const toastMsg = document.getElementById('toast-message');
    let toastTimeout = null;

    function showToast(msg) {
        if (!toast) return;
        if (toastMsg) toastMsg.textContent = msg;
        toast.classList.add('show');
        if (toastTimeout) clearTimeout(toastTimeout);
        toastTimeout = setTimeout(() => {
            toast.classList.remove('show');
        }, 3000);
    }

    // Initial boot: synthesize live preview buffer with injected inspector harness
    updatePreviewScale();
    renderLivePreviewBuffer();
});
