/**
 * PageDock AI HTML Editor Engine
 * 1:1 Parity with htmlhost.co Client-Side Orchestration Architecture
 */

(function () {
    'use strict';

    // Global Editor State
    const state = {
        slug: window.PAGEDOCK_CONFIG?.slug || '',
        activeFile: window.PAGEDOCK_CONFIG?.activeFile || 'index.html',
        editMode: true,           // Visual selection mode on/off
        aiEdit: true,             // Popover mode: true = AI prompt, false = Direct element edit
        activeTab: 'code',        // 'code' | 'library' | 'structure' | 'shared'
        activeLibView: 'sections',// 'sections' | 'elements'
        currentDevice: 'desktop', // 'desktop' | 'tablet' | 'mobile'
        mobileView: 'code',       // 'code' | 'preview'
        selectedElement: null,    // Last clicked element payload from iframe
        activeFileType: 'html',   // 'html' | 'css' | 'js'
        fileBuffers: {
            html: '',
            css: '',
            js: ''
        },
        fileModes: {
            html: 'htmlmixed',
            css: 'css',
            js: 'javascript'
        },
        fileLabels: {
            html: 'HTML',
            css: 'CSS',
            js: 'JavaScript'
        },
        historyStacks: {
            html: [],
            css: [],
            js: []
        },
        redoStacks: {
            html: [],
            css: [],
            js: []
        },
        aiQuotaUsed: 0,
        aiQuotaLimit: 5,
        isDirty: false,
        isSaving: false
    };

    // DOM Elements Cache
    const dom = {
        topbar: document.getElementById('hh-topbar'),
        undoBtn: document.getElementById('hh-undo-btn'),
        redoBtn: document.getElementById('hh-redo-btn'),
        editModeToggle: document.getElementById('hh-editmode-toggle'),
        editModeLabel: document.getElementById('hh-editmode-label'),
        aiEditToggle: document.getElementById('hh-aiedit-toggle'),
        chatBtn: document.getElementById('hh-chat-btn'),
        quotaLabel: document.getElementById('hh-quota-label'),
        deviceBtns: document.querySelectorAll('.hh-device-btn'),
        mobileViewBar: document.getElementById('hh-mobile-view-bar'),
        mobileViewBtns: document.querySelectorAll('.hh-mobile-view-btn'),
        mobileBtnCode: document.getElementById('hh-mobile-btn-code'),
        mobileBtnPreview: document.getElementById('hh-mobile-btn-preview'),
        toolsBtn: document.getElementById('hh-tools-btn'),
        toolsMenu: document.getElementById('hh-tools-menu'),
        saveDraftBtn: document.getElementById('hh-savedraft-btn'),
        statusDot: document.getElementById('hh-status-dot'),
        saveStatusText: document.getElementById('hh-save-status-text'),
        publishBtn: document.getElementById('hh-publish-btn'),
        leftPanel: document.getElementById('hh-left-panel'),
        collapsePanelBtn: document.getElementById('hh-collapse-panel-btn'),
        splitter: document.getElementById('hh-splitter'),
        tabBtns: document.querySelectorAll('.hh-tab-btn'),
        tabContents: document.querySelectorAll('.hh-tab-content'),
        sourceTextarea: document.getElementById('hh-source-textarea'),
        fileChips: document.querySelectorAll('.hh-file-chip'),
        codeLangTag: document.getElementById('hh-code-lang-tag'),
        previewIframe: document.getElementById('preview-iframe'),
        viewportWrapper: document.getElementById('hh-viewport-wrapper'),
        // Popover Elements
        popoverAnchor: document.getElementById('hh-popover-anchor'),
        aiPopover: document.getElementById('hh-ai-popover'),
        directPopover: document.getElementById('hh-direct-popover'),
        popAiTag: document.getElementById('pop-ai-tag'),
        popAiSnippet: document.getElementById('pop-ai-snippet'),
        popAiChips: document.getElementById('pop-ai-chips'),
        popAiPrompt: document.getElementById('ai-edit-prompt'),
        popApplyBtn: document.getElementById('pop-apply-btn'),
        popCancelBtn: document.getElementById('pop-cancel-btn'),
        popCloseBtn: document.getElementById('pop-close-btn'),
        popModeSwitch: document.getElementById('pop-mode-switch'),
        popDirectTag: document.getElementById('pop-direct-tag'),
        popDirectSnippet: document.getElementById('pop-direct-snippet'),
        popDirectCloseBtn: document.getElementById('pop-direct-close-btn'),
        popDirectModeSwitch: document.getElementById('pop-direct-mode-switch'),
        directTextInput: document.getElementById('direct-text-input'),
        directInlineEditBtn: document.getElementById('direct-inline-edit-btn'),
        actDuplicateBtn: document.getElementById('act-duplicate-btn'),
        actCodeBtn: document.getElementById('act-code-btn'),
        actDeleteBtn: document.getElementById('act-delete-btn'),
        // Library
        libSectionsContainer: document.getElementById('hh-lib-sections-container'),
        libElementsContainer: document.getElementById('hh-lib-elements-container'),
        libSubtabs: document.querySelectorAll('.hh-lib-subtab'),
        libSearchInput: document.getElementById('hh-lib-search-input'),
        // Structure
        treeContainer: document.getElementById('hh-tree-container'),
        refreshTreeBtn: document.getElementById('hh-refresh-tree-btn'),
        // Chat Drawer
        chatDrawer: document.getElementById('hh-chat-drawer'),
        closeChatBtn: document.getElementById('hh-close-chat-btn'),
        chatInput: document.getElementById('hh-chat-input'),
        sendChatBtn: document.getElementById('hh-send-chat-btn'),
        chatMessages: document.getElementById('hh-chat-messages'),
        // Modals & Tool Items
        mediaBtn: document.getElementById('hh-media-btn'),
        mediaModal: document.getElementById('hh-media-modal'),
        mediaDropzone: document.getElementById('hh-media-dropzone'),
        mediaFileInput: document.getElementById('hh-media-file-input'),
        mediaBrowseBtn: document.getElementById('hh-media-browse-btn'),
        mediaGallery: document.getElementById('hh-media-gallery'),
        mediaRefreshBtn: document.getElementById('hh-media-refresh-btn'),
        mediaCountLabel: document.getElementById('hh-media-count-label'),
        versionsModal: document.getElementById('hh-versions-modal'),
        versionsList: document.getElementById('hh-versions-list'),
        formsModal: document.getElementById('hh-forms-modal'),
        formsCountBadge: document.getElementById('hh-forms-count-badge'),
        formsContainer: document.getElementById('hh-forms-container'),
        toolFormatCode: document.getElementById('tool-format-code'),
        toolCleanHtml: document.getElementById('tool-clean-html'),
        toolRevisions: document.getElementById('tool-revisions'),
        toolFormsInbox: document.getElementById('tool-forms-inbox'),
        toast: document.getElementById('hh-toast')
    };

    let cmEditor = null;
    let isInternalUpdate = false;

    // =========================================================================
    // 1. CODEMIRROR INITIALIZATION & MULTI-FILE BUFFER MANAGEMENT
    // =========================================================================

    function extractInlineCss(html) {
        if (!html) return '';
        const matches = html.match(/<style[^>]*>([\s\S]*?)<\/style>/gi);
        if (!matches || !matches.length) return '';
        return matches.map(m => m.replace(/<\/?style[^>]*>/gi, '')).join('\n\n').trim();
    }

    function extractInlineJs(html) {
        if (!html) return '';
        const scriptRegex = /<script(?![^>]*\bsrc=)[^>]*>([\s\S]*?)<\/script>/gi;
        let scripts = [];
        let match;
        while ((match = scriptRegex.exec(html)) !== null) {
            scripts.push(match[1].trim());
        }
        return scripts.join('\n\n').trim();
    }

    function injectInlineCss(html, newCss) {
        if (!html) return html;
        if (/<style[^>]*>[\s\S]*?<\/style>/i.test(html)) {
            return html.replace(/<style[^>]*>[\s\S]*?<\/style>/i, `<style>\n${newCss}\n</style>`);
        } else if (html.includes('</head>')) {
            return html.replace('</head>', `<style>\n${newCss}\n</style>\n</head>`);
        } else {
            return `<style>\n${newCss}\n</style>\n` + html;
        }
    }

    function injectInlineJs(html, newJs) {
        if (!html) return html;
        const scriptRegex = /<script(?![^>]*\bsrc=)[^>]*>[\s\S]*?<\/script>/i;
        if (scriptRegex.test(html)) {
            return html.replace(scriptRegex, `<script>\n${newJs}\n<\/script>`);
        } else if (html.includes('</body>')) {
            return html.replace('</body>', `<script>\n${newJs}\n<\/script>\n</body>`);
        } else {
            return html + `\n<script>\n${newJs}\n<\/script>`;
        }
    }

    function getHtmlCode() {
        if (state.activeFileType === 'html' && cmEditor) {
            state.fileBuffers.html = cmEditor.getValue();
        }
        return state.fileBuffers.html;
    }

    function switchFile(fileType) {
        if (!fileType || !state.fileBuffers.hasOwnProperty(fileType)) return;
        if (fileType === state.activeFileType && cmEditor) return;

        // 1. Sync current editor value to active buffer
        if (cmEditor) {
            state.fileBuffers[state.activeFileType] = cmEditor.getValue();
        }

        // 2. Set new active file type
        state.activeFileType = fileType;

        // 3. Update chips active state
        const chips = dom.fileChips && dom.fileChips.length ? dom.fileChips : document.querySelectorAll('.hh-file-chip');
        chips.forEach(chip => {
            chip.classList.toggle('active', chip.dataset.file === fileType);
        });

        // 4. Update language indicator badge
        if (dom.codeLangTag) {
            dom.codeLangTag.textContent = state.fileLabels[fileType] || fileType.toUpperCase();
        }

        // 5. Update CodeMirror mode and content
        if (cmEditor) {
            isInternalUpdate = true;
            cmEditor.setValue(state.fileBuffers[fileType] || '');
            cmEditor.setOption('mode', state.fileModes[fileType] || 'htmlmixed');
            isInternalUpdate = false;
            cmEditor.refresh();
            cmEditor.focus();
        }

        updateHistoryButtons();
    }

    function initCodeMirror() {
        if (!dom.sourceTextarea) return;

        const initialHtml = dom.sourceTextarea.value || '';
        state.fileBuffers.html = initialHtml;

        // Physical CSS vs Inline
        if (window.PAGEDOCK_CONFIG?.hasPhysicalCss && typeof window.PAGEDOCK_CONFIG?.initialCss === 'string') {
            state.fileBuffers.css = window.PAGEDOCK_CONFIG.initialCss;
        } else {
            state.fileBuffers.css = (typeof window.PAGEDOCK_CONFIG?.initialCss === 'string' && window.PAGEDOCK_CONFIG.initialCss.trim())
                ? window.PAGEDOCK_CONFIG.initialCss
                : extractInlineCss(initialHtml);
        }

        // Physical JS vs Inline
        if (window.PAGEDOCK_CONFIG?.hasPhysicalJs && typeof window.PAGEDOCK_CONFIG?.initialJs === 'string') {
            state.fileBuffers.js = window.PAGEDOCK_CONFIG.initialJs;
        } else {
            state.fileBuffers.js = (typeof window.PAGEDOCK_CONFIG?.initialJs === 'string' && window.PAGEDOCK_CONFIG.initialJs.trim())
                ? window.PAGEDOCK_CONFIG.initialJs
                : extractInlineJs(initialHtml);
        }

        cmEditor = CodeMirror.fromTextArea(dom.sourceTextarea, {
            mode: state.fileModes[state.activeFileType] || 'htmlmixed',
            theme: 'material-darker',
            lineNumbers: true,
            lineWrapping: true,
            autoCloseTags: true,
            autoCloseBrackets: true,
            tabSize: 2,
            indentUnit: 2
        });

        // Push initial state to history stacks
        state.historyStacks.html.push(state.fileBuffers.html);
        if (state.fileBuffers.css) state.historyStacks.css.push(state.fileBuffers.css);
        if (state.fileBuffers.js) state.historyStacks.js.push(state.fileBuffers.js);

        // Debounced sync to live preview iframe on code change
        let changeTimer = null;
        cmEditor.on('change', (cm, changeObj) => {
            if (isInternalUpdate) return;
            setDirty(true);
            clearTimeout(draftSaveTimer);
            clearTimeout(changeTimer);
            changeTimer = setTimeout(() => {
                syncCodeToPreview();
                scheduleDraftAutoSave(700);
            }, 300);
        });
    }

    // =========================================================================
    // 1.1 LOCAL STORAGE DRAFT AUTO-SAVE & RECOVERY
    // =========================================================================

    let draftSaveTimer = null;

    function getStorage() {
        try {
            if (typeof window !== 'undefined' && window.localStorage) return window.localStorage;
            if (typeof localStorage !== 'undefined') return localStorage;
        } catch (e) {}
        return null;
    }

    function getDraftStorageKey(fileType = state.activeFileType) {
        const slug = state.slug || 'default';
        const type = fileType || 'html';
        return `pagedock_draft_${slug}_${type}`;
    }

    function saveDraftToLocalStorage() {
        if (!cmEditor) return;
        const storage = getStorage();
        if (!storage) return;

        try {
            const type = state.activeFileType || 'html';
            const code = cmEditor.getValue();
            state.fileBuffers[type] = code;

            const key = getDraftStorageKey(type);
            const payload = {
                code: code,
                timestamp: Date.now(),
                slug: state.slug,
                type: type,
                file: state.activeFile
            };
            storage.setItem(key, JSON.stringify(payload));
        } catch (e) {
            // LocalStorage quota exceeded or disabled
        }
    }

    function scheduleDraftAutoSave(delay = 1000) {
        clearTimeout(draftSaveTimer);
        draftSaveTimer = setTimeout(() => {
            saveDraftToLocalStorage();
        }, delay);
    }

    function clearLocalDraft() {
        clearTimeout(draftSaveTimer);
        const storage = getStorage();
        if (!storage) return;

        try {
            ['html', 'css', 'js'].forEach(type => {
                storage.removeItem(getDraftStorageKey(type));
            });
        } catch (e) {}
    }

    function checkAndRestoreDraft() {
        if (!cmEditor) return;
        const storage = getStorage();
        if (!storage) return;

        try {
            const initialLoadTime = Number(
                window.PAGEDOCK_CONFIG?.initialTimestamp ||
                window.PAGEDOCK_CONFIG?.siteUpdatedAt ||
                window.PAGEDOCK_CONFIG?.loadedAt ||
                0
            );

            let restoredCount = 0;
            ['html', 'css', 'js'].forEach(type => {
                const key = getDraftStorageKey(type);
                const raw = storage.getItem(key);
                if (!raw) return;

                const draft = JSON.parse(raw);
                if (!draft || typeof draft !== 'object' || typeof draft.code !== 'string') return;
                if (draft.clean || draft.isClean) return;

                const currentBuffer = state.fileBuffers[type] || '';
                const draftTime = Number(draft.timestamp || 0);
                const isNewer = initialLoadTime === 0 ? draftTime > 0 : draftTime > initialLoadTime;
                const isDifferent = draft.code !== currentBuffer;

                if (isNewer && isDifferent) {
                    state.fileBuffers[type] = draft.code;
                    state.historyStacks[type].push(draft.code);
                    setDirty(true);
                    restoredCount++;

                    if (type === state.activeFileType) {
                        isInternalUpdate = true;
                        cmEditor.setValue(draft.code);
                        isInternalUpdate = false;
                        if (type === 'html') {
                            postToIframe({ type: 'live-patch', html: draft.code });
                        } else if (type === 'css') {
                            postToIframe({ type: 'live-style-update', css: draft.code });
                        }
                    }
                }
            });

            if (restoredCount > 0) {
                updateHistoryButtons();
                showToast(`Restored unsaved draft (${restoredCount} file${restoredCount > 1 ? 's' : ''}) from local storage`);
            }
        } catch (e) {
            console.error('Failed to restore draft from local storage:', e);
        }
    }

    function syncCodeToPreview() {
        if (!cmEditor || !dom.previewIframe) return;
        const code = cmEditor.getValue();
        const type = state.activeFileType;
        state.fileBuffers[type] = code;

        // Push snapshot to undo stack if different from last
        const curHist = state.historyStacks[type] || (state.historyStacks[type] = []);
        if (curHist[curHist.length - 1] !== code) {
            curHist.push(code);
            state.redoStacks[type] = [];
            updateHistoryButtons();
        }

        if (type === 'html') {
            postToIframe({
                type: 'live-patch',
                html: code
            });
        } else if (type === 'css') {
            postToIframe({
                type: 'live-style-update',
                css: code
            });
            if (!window.PAGEDOCK_CONFIG?.hasPhysicalCss) {
                state.fileBuffers.html = injectInlineCss(state.fileBuffers.html, code);
            }
        } else if (type === 'js') {
            if (!window.PAGEDOCK_CONFIG?.hasPhysicalJs) {
                state.fileBuffers.html = injectInlineJs(state.fileBuffers.html, code);
            }
        }
    }

    function updateCodeEditor(newHtml, updateIframe = true) {
        state.fileBuffers.html = newHtml;

        // If CSS/JS are inlined, synchronize extracted copies
        if (!window.PAGEDOCK_CONFIG?.hasPhysicalCss) {
            const extractedCss = extractInlineCss(newHtml);
            if (extractedCss) state.fileBuffers.css = extractedCss;
        }
        if (!window.PAGEDOCK_CONFIG?.hasPhysicalJs) {
            const extractedJs = extractInlineJs(newHtml);
            if (extractedJs) state.fileBuffers.js = extractedJs;
        }

        if (state.activeFileType === 'html' && cmEditor) {
            isInternalUpdate = true;
            cmEditor.setValue(newHtml);
            isInternalUpdate = false;
            state.historyStacks.html.push(newHtml);
            state.redoStacks.html = [];
            updateHistoryButtons();
        }

        setDirty(true);

        if (updateIframe) {
            postToIframe({ type: 'live-patch', html: newHtml });
        }

        scheduleDraftAutoSave(1000);
    }

    function setDirty(dirty) {
        state.isDirty = dirty;
        if (dom.statusDot) {
            dom.statusDot.className = 'hh-status-dot ' + (dirty ? 'dirty' : 'saved');
        }
        if (dom.saveStatusText) {
            dom.saveStatusText.textContent = dirty ? 'Unsaved changes' : 'Save Draft';
        }
    }

    // =========================================================================
    // 2. BIDIRECTIONAL postMessage PROTOCOL BRIDGE (1:1 htmlhost.co)
    // =========================================================================

    function postToIframe(msg) {
        if (dom.previewIframe && dom.previewIframe.contentWindow) {
            dom.previewIframe.contentWindow.postMessage(msg, '*');
        }
    }

    window.addEventListener('message', (e) => {
        const data = e.data;
        if (!data || typeof data !== 'object') return;

        switch (data.type) {
            case 'element-selected':
                handleElementSelected(data);
                break;

            case 'element-text-edit':
                handleElementTextEdit(data);
                break;

            case 'element-reorder':
                handleElementReorder(data);
                break;

            case 'text-edit-start':
                hidePopovers(true);
                break;

            case 'page-structure':
                renderStructureTree(data.tree || []);
                break;

            case 'iframe-key':
                handleIframeKeyboard(data);
                break;

            case 'iframe-escape':
            case 'dismiss-popovers':
                hidePopovers();
                break;

            case 'buffer-dom-ready':
                // Request live structure on frame ready
                postToIframe({ type: 'request-structure' });
                postToIframe({ type: 'ai-mode', enabled: state.editMode });
                if (state.isDirty && cmEditor) {
                    postToIframe({ type: 'live-patch', html: getHtmlCode() });
                }
                break;
        }
    });

    // =========================================================================
    // 3. FLOATING POPOVERS CONTROLLER
    // =========================================================================

    function handleElementSelected(payload) {
        if (!state.editMode) return;
        state.selectedElement = payload;

        const tag = (payload.tag || 'DIV').toUpperCase();
        const text = (payload.text || '').trim();
        const snippet = text.slice(0, 24) + (text.length > 24 ? '…' : '');

        // 1. Update AI Popover
        if (dom.popAiTag) dom.popAiTag.textContent = tag;
        if (dom.popAiSnippet) dom.popAiSnippet.textContent = snippet || '<' + tag.toLowerCase() + '>';
        if (dom.popAiPrompt) dom.popAiPrompt.value = '';
        renderContextualChips(tag);

        // 2. Update Direct Edit Popover
        if (dom.popDirectTag) dom.popDirectTag.textContent = tag;
        if (dom.popDirectSnippet) dom.popDirectSnippet.textContent = snippet || '<' + tag.toLowerCase() + '>';
        if (dom.directTextInput) dom.directTextInput.value = text;

        // 3. Position Popover over element coordinates
        positionPopover(payload.rect);

        // 4. Show appropriate view (AI Edit or Direct Edit)
        showPopoverView(state.aiEdit ? 'ai' : 'direct');
    }

    function positionPopover(rect) {
        if (!rect || !dom.popoverAnchor || !dom.previewIframe) return;

        const canvasArea = document.getElementById('hh-canvas-area');
        const iframeRect = dom.previewIframe.getBoundingClientRect();
        const canvasAreaRect = canvasArea.getBoundingClientRect();

        const popoverWidth = 340;
        let left = iframeRect.left - canvasAreaRect.left + rect.left + canvasArea.scrollLeft;
        let top = iframeRect.top - canvasAreaRect.top + rect.bottom + 8 + canvasArea.scrollTop;

        // Boundary adjustments
        if (left + popoverWidth > canvasAreaRect.width - 20) {
            left = canvasAreaRect.width - popoverWidth - 20;
        }
        if (left < 10) left = 10;

        // If rendering below overflows bottom, flip to above element
        if (top + 280 > canvasAreaRect.height + canvasArea.scrollTop) {
            top = iframeRect.top - canvasAreaRect.top + rect.top - 280 + canvasArea.scrollTop;
            if (top < 10) top = 10;
        }

        dom.popoverAnchor.style.left = `${Math.round(left)}px`;
        dom.popoverAnchor.style.top = `${Math.round(top)}px`;
        dom.popoverAnchor.style.display = 'block';
        dom.popoverAnchor.classList.remove('hidden');
    }

    function showPopoverView(view) {
        if (!dom.aiPopover || !dom.directPopover) return;
        if (view === 'ai') {
            dom.aiPopover.classList.remove('hidden');
            dom.aiPopover.style.display = 'block';
            dom.directPopover.classList.add('hidden');
            dom.directPopover.style.display = 'none';
            if (dom.popAiPrompt) dom.popAiPrompt.focus();
        } else {
            dom.directPopover.classList.remove('hidden');
            dom.directPopover.style.display = 'block';
            dom.aiPopover.classList.add('hidden');
            dom.aiPopover.style.display = 'none';
            if (dom.directTextInput) dom.directTextInput.focus();
        }
    }

    function hidePopovers(keepSelection = false) {
        if (dom.popoverAnchor) {
            dom.popoverAnchor.classList.add('hidden');
            dom.popoverAnchor.style.display = 'none';
        }
        if (dom.aiPopover) {
            dom.aiPopover.classList.add('hidden');
            dom.aiPopover.style.display = 'none';
        }
        if (dom.directPopover) {
            dom.directPopover.classList.add('hidden');
            dom.directPopover.style.display = 'none';
        }
        if (!keepSelection) {
            postToIframe({ type: 'clear-selection' });
            state.selectedElement = null;
        }
    }

    function renderContextualChips(tag) {
        if (!dom.popAiChips) return;
        dom.popAiChips.innerHTML = '';

        let chips = [];
        if (tag === 'BUTTON' || tag === 'A') {
            chips = ['Add a hover state', 'Add hover effect', 'Change colors', 'Make larger'];
        } else if (tag.startsWith('H') || tag === 'P') {
            chips = ['Make bolder', 'Add dark gradient', 'Change colors', 'Make larger'];
        } else if (tag === 'SECTION' || tag === 'HEADER' || tag === 'FOOTER') {
            chips = ['Add dark gradient', 'Add background glow', 'Make 3 columns', 'Center content'];
        } else {
            chips = ['Add a hover state', 'Add hover effect', 'Change colors', 'Make bolder'];
        }

        chips.forEach(chipText => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'hh-chip';
            btn.textContent = chipText;
            btn.addEventListener('click', () => {
                if (dom.popAiPrompt) {
                    dom.popAiPrompt.value = chipText;
                    applyAiEdit();
                }
            });
            dom.popAiChips.appendChild(btn);
        });
    }

    // =========================================================================
    // 4. AI & DIRECT ELEMENT ACTION EXECUTORS
    // =========================================================================

    async function applyAiEdit() {
        if (!state.selectedElement) return;
        const prompt = dom.popAiPrompt ? dom.popAiPrompt.value.trim() : '';
        if (!prompt) return;

        // UI progress indication
        if (dom.popApplyBtn) {
            dom.popApplyBtn.disabled = true;
            dom.popApplyBtn.innerHTML = `<span>Applying...</span>`;
        }

        try {
            const response = await fetch(window.PAGEDOCK_CONFIG.aiBuiltinUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    mode: 'element',
                    instruction: prompt,
                    elementHtml: state.selectedElement.html || '',
                    tag: state.selectedElement.tag,
                    tagIndex: state.selectedElement.tagIndex
                })
            });

            const data = await response.json();
            if (data.success && data.html) {
                // 1. Dispatch patch to iframe preview
                postToIframe({
                    type: 'patch-element',
                    tag: state.selectedElement.tag,
                    tagIndex: state.selectedElement.tagIndex,
                    html: data.html
                });

                // 2. Synchronize Code Editor
                if (cmEditor && state.selectedElement) {
                    const currentCode = getHtmlCode();
                    const tag = state.selectedElement.tag;
                    const tagIndex = state.selectedElement.tagIndex;
                    let updatedCode = null;

                    if (tag !== undefined && tagIndex !== undefined) {
                        const range = findTagRangeInHtml(currentCode, tag, tagIndex);
                        if (range) {
                            updatedCode = currentCode.slice(0, range.start) + data.html + currentCode.slice(range.end);
                        }
                    }

                    if (!updatedCode && state.selectedElement.html) {
                        updatedCode = currentCode.replace(state.selectedElement.html, data.html);
                    }

                    if (updatedCode !== null && updatedCode !== currentCode) {
                        updateCodeEditor(updatedCode, false);
                    }
                }

                // 3. Update quota counter
                state.aiQuotaUsed = Math.min(state.aiQuotaLimit, state.aiQuotaUsed + 1);
                updateQuotaDisplay();

                showToast(`[STATUS] AI element edit applied: "${prompt}"`);
                hidePopovers();
            } else {
                showToast(`[ERROR] AI Edit failed: ${data.error || 'Server error'}`);
            }
        } catch (err) {
            console.error('AI edit error:', err);
            showToast('[ERROR] Network error applying AI change.');
        } finally {
            if (dom.popApplyBtn) {
                dom.popApplyBtn.disabled = false;
                dom.popApplyBtn.innerHTML = `
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 2l2.4 7.2L22 12l-7.6 2.8L12 22l-2.4-7.2L2 12l7.6-2.8z"/></svg>
                    <span>Apply Changes</span>
                `;
            }
        }
    }

    function findTagRangeInHtml(html, targetTag, targetIndex) {
        if (!html || !targetTag) return null;
        const tagUpper = targetTag.toUpperCase();
        const isVoid = /^(AREA|BASE|BR|COL|EMBED|HR|IMG|INPUT|LINK|META|PARAM|SOURCE|TRACK|WBR)$/i.test(tagUpper);

        const tagPattern = new RegExp(`<(/?)${tagUpper}(?=[\\s>/])`, 'gi');
        let match;
        let currentMatchIndex = -1;
        let foundOpenStart = -1;

        while ((match = tagPattern.exec(html)) !== null) {
            const isClosing = match[1] === '/';
            if (!isClosing) {
                currentMatchIndex++;
                if (currentMatchIndex === targetIndex) {
                    foundOpenStart = match.index;
                    break;
                }
            }
        }

        if (foundOpenStart === -1) return null;

        // Find the matching '>' of this opening tag
        let openTagEnd = -1;
        let inQuote = null;
        for (let i = foundOpenStart; i < html.length; i++) {
            const ch = html[i];
            if (inQuote) {
                if (ch === inQuote) inQuote = null;
            } else if (ch === '"' || ch === "'") {
                inQuote = ch;
            } else if (ch === '>') {
                openTagEnd = i + 1;
                break;
            }
        }

        if (openTagEnd === -1) return null;

        const openTagContent = html.slice(foundOpenStart, openTagEnd);
        if (isVoid || /\/\s*>$/.test(openTagContent)) {
            return {
                start: foundOpenStart,
                end: openTagEnd,
                openTagEnd: openTagEnd,
                closeTagStart: openTagEnd,
                outerHTML: openTagContent,
                innerHTML: ''
            };
        }

        // Search matching closing tag taking nested elements of same tag into account
        let depth = 1;
        tagPattern.lastIndex = openTagEnd;
        let elementEnd = -1;

        while ((match = tagPattern.exec(html)) !== null) {
            const isClosing = match[1] === '/';
            const tagPos = match.index;

            let thisTagEnd = -1;
            let q = null;
            for (let j = tagPos; j < html.length; j++) {
                const c = html[j];
                if (q) {
                    if (c === q) q = null;
                } else if (c === '"' || c === "'") {
                    q = c;
                } else if (c === '>') {
                    thisTagEnd = j + 1;
                    break;
                }
            }
            if (thisTagEnd === -1) break;

            const thisTagStr = html.slice(tagPos, thisTagEnd);
            const thisSelfClosing = /\/\s*>$/.test(thisTagStr);

            if (isClosing) {
                depth--;
                if (depth === 0) {
                    elementEnd = thisTagEnd;
                    break;
                }
            } else if (!thisSelfClosing) {
                depth++;
            }

            tagPattern.lastIndex = thisTagEnd;
        }

        if (elementEnd === -1) {
            elementEnd = openTagEnd;
        }

        const outerHTML = html.slice(foundOpenStart, elementEnd);
        const closeTagMatch = outerHTML.match(new RegExp(`</${tagUpper}\\s*>$`, 'i'));
        const closeTagStart = closeTagMatch ? elementEnd - closeTagMatch[0].length : elementEnd;

        return {
            start: foundOpenStart,
            end: elementEnd,
            openTagEnd: openTagEnd,
            closeTagStart: closeTagStart,
            outerHTML: outerHTML,
            innerHTML: html.slice(openTagEnd, closeTagStart)
        };
    }

    function handleElementTextEdit(payload) {
        if (!cmEditor || !payload) return;
        const tag = payload.tag || (state.selectedElement && state.selectedElement.tag);
        const tagIndex = payload.tagIndex !== undefined ? payload.tagIndex : (state.selectedElement && state.selectedElement.tagIndex);
        const newText = payload.text !== undefined ? payload.text : '';
        const currentCode = getHtmlCode();

        let updatedCode = null;
        if (tag !== undefined && tagIndex !== undefined) {
            const range = findTagRangeInHtml(currentCode, tag, tagIndex);
            if (range && range.openTagEnd !== undefined && range.closeTagStart !== undefined) {
                const before = currentCode.slice(0, range.openTagEnd);
                const after = currentCode.slice(range.closeTagStart);
                updatedCode = before + newText + after;
            }
        }

        if (!updatedCode && state.selectedElement && state.selectedElement.text) {
            const oldText = state.selectedElement.text;
            if (oldText !== newText) {
                updatedCode = currentCode.replace(oldText, newText);
            }
        }

        if (updatedCode && updatedCode !== currentCode) {
            updateCodeEditor(updatedCode, false);
            if (state.selectedElement) {
                state.selectedElement.text = newText;
            }
        }
    }

    function duplicateSelectedElement() {
        if (!state.selectedElement || !cmEditor) return;
        const tag = state.selectedElement.tag;
        const tagIndex = state.selectedElement.tagIndex;
        const currentCode = getHtmlCode();

        // 1. Dispatch DOM clone operation to preview iframe
        postToIframe({
            type: 'duplicate-element',
            tag: tag,
            tagIndex: tagIndex
        });

        // 2. Synchronize CodeMirror accurately by tagIndex
        let updatedCode = null;
        if (tag !== undefined && tagIndex !== undefined) {
            const range = findTagRangeInHtml(currentCode, tag, tagIndex);
            if (range) {
                const lineStart = currentCode.lastIndexOf('\n', range.start) + 1;
                const indentMatch = currentCode.slice(lineStart, range.start).match(/^\s*/);
                const indent = indentMatch ? indentMatch[0] : '';

                const before = currentCode.slice(0, range.end);
                const after = currentCode.slice(range.end);
                updatedCode = before + '\n' + indent + range.outerHTML + after;
            }
        }

        if (!updatedCode && state.selectedElement.html) {
            const html = state.selectedElement.html;
            updatedCode = currentCode.replace(html, html + '\n' + html);
        }

        if (updatedCode && updatedCode !== currentCode) {
            updateCodeEditor(updatedCode, false);
        }

        showToast(`[STATUS] Duplicated <${state.selectedElement.tag.toLowerCase()}>`);
        hidePopovers();
    }

    function deleteSelectedElement() {
        if (!state.selectedElement || !cmEditor) return;
        const tag = state.selectedElement.tag;
        const tagIndex = state.selectedElement.tagIndex;
        const currentCode = getHtmlCode();

        // 1. Dispatch delete operation to preview iframe
        postToIframe({
            type: 'delete-element',
            tag: tag,
            tagIndex: tagIndex
        });

        // 2. Synchronize CodeMirror accurately by tagIndex
        let updatedCode = null;
        if (tag !== undefined && tagIndex !== undefined) {
            const range = findTagRangeInHtml(currentCode, tag, tagIndex);
            if (range) {
                let startPos = range.start;
                let endPos = range.end;

                const lineStart = currentCode.lastIndexOf('\n', startPos) + 1;
                const lineEnd = currentCode.indexOf('\n', endPos);
                const prefix = currentCode.slice(lineStart, startPos);
                const suffix = lineEnd !== -1 ? currentCode.slice(endPos, lineEnd) : currentCode.slice(endPos);

                if (/^\s*$/.test(prefix) && /^\s*$/.test(suffix)) {
                    startPos = lineStart;
                    endPos = lineEnd !== -1 ? lineEnd + 1 : currentCode.length;
                }

                updatedCode = currentCode.slice(0, startPos) + currentCode.slice(endPos);
            }
        }

        if (!updatedCode && state.selectedElement.html) {
            updatedCode = currentCode.replace(state.selectedElement.html, '');
        }

        if (updatedCode !== null && updatedCode !== currentCode) {
            updateCodeEditor(updatedCode, false);
        }

        showToast(`[STATUS] Deleted <${state.selectedElement.tag.toLowerCase()}>`);
        hidePopovers();
    }

    function handleElementReorder(payload) {
        if (!cmEditor || !payload) return;
        const { sourceTag, sourceIndex, targetTag, targetIndex, position } = payload;
        const currentCode = getHtmlCode();

        const srcRange = findTagRangeInHtml(currentCode, sourceTag, sourceIndex);
        const tgtRange = findTagRangeInHtml(currentCode, targetTag, targetIndex);

        if (!srcRange || !tgtRange) return;

        const srcOuter = srcRange.outerHTML;

        // Remove source block from currentCode
        let codeWithoutSrc = currentCode.slice(0, srcRange.start) + currentCode.slice(srcRange.end);

        // Adjust targetIndex if target is same tag and after source
        const adjustedTgtIndex = (sourceTag === targetTag && sourceIndex < targetIndex)
            ? targetIndex - 1
            : targetIndex;

        const reTgtRange = findTagRangeInHtml(codeWithoutSrc, targetTag, adjustedTgtIndex);
        if (!reTgtRange) return;

        let updatedCode = '';
        if (position === 'before') {
            updatedCode = codeWithoutSrc.slice(0, reTgtRange.start) + srcOuter + '\n\n' + codeWithoutSrc.slice(reTgtRange.start);
        } else {
            updatedCode = codeWithoutSrc.slice(0, reTgtRange.end) + '\n\n' + srcOuter + codeWithoutSrc.slice(reTgtRange.end);
        }

        if (updatedCode && updatedCode !== currentCode) {
            updateCodeEditor(updatedCode, false);
            showToast('[STATUS] Reordered sections');
        }
    }

    function jumpToElementInCode() {
        if (!state.selectedElement || !cmEditor) return;
        setMobileView('code');
        switchTab('code');
        hidePopovers(true);

        if (state.activeFileType !== 'html') {
            switchFile('html');
        }

        const tag = state.selectedElement.tag;
        const tagIndex = state.selectedElement.tagIndex;
        const text = cmEditor.getValue();

        let targetIndex = -1;
        if (tag !== undefined && tagIndex !== undefined) {
            const range = findTagRangeInHtml(text, tag, tagIndex);
            if (range) {
                targetIndex = range.start;
            }
        }

        if (targetIndex === -1 && state.selectedElement.html) {
            targetIndex = text.indexOf(state.selectedElement.html.slice(0, 40));
        }

        if (targetIndex !== -1) {
            const pos = cmEditor.posFromIndex(targetIndex);
            cmEditor.setCursor(pos);
            cmEditor.scrollIntoView({ from: pos, to: pos }, 100);
            cmEditor.focus();
            showToast(`[STATUS] Focused line ${pos.line + 1} in Code editor`);
        }
    }

    // =========================================================================
    // 5. LEFT PANEL (4 TABS) & COMPONENT LIBRARY CATALOG (46/48)
    // =========================================================================

    function switchTab(tabId) {
        state.activeTab = tabId;
        dom.tabBtns.forEach(b => b.classList.toggle('active', b.dataset.tab === tabId));
        dom.tabContents.forEach(c => c.classList.toggle('active', c.id === `hh-tab-${tabId}`));

        if (tabId === 'code' && cmEditor) {
            setTimeout(() => cmEditor.refresh(), 50);
        } else if (tabId === 'structure') {
            postToIframe({ type: 'request-structure' });
        }
    }

    // Mobile View Switcher (Code vs Preview)
    function setMobileView(mode) {
        state.mobileView = mode;
        const isCode = mode === 'code';

        if (document.body) {
            document.body.classList.toggle('hh-mobile-mode-code', isCode);
            document.body.classList.toggle('hh-mobile-mode-preview', !isCode);
        }

        const workspace = dom.workspace || document.getElementById('hh-workspace');
        if (workspace) {
            workspace.classList.toggle('hh-mobile-mode-code', isCode);
            workspace.classList.toggle('hh-mobile-mode-preview', !isCode);
        }

        const viewBtns = dom.mobileViewBtns && dom.mobileViewBtns.length
            ? dom.mobileViewBtns
            : document.querySelectorAll('.hh-mobile-view-btn');

        if (viewBtns && viewBtns.length) {
            viewBtns.forEach(btn => {
                const btnView = btn.dataset.view || (btn.id.includes('preview') ? 'preview' : 'code');
                btn.classList.toggle('active', btnView === mode);
            });
        }

        if (isCode && cmEditor) {
            cmEditor.refresh();
            setTimeout(() => {
                if (cmEditor) cmEditor.refresh();
            }, 50);
        }
    }

    // Comprehensive Library: 46 Sections & 48 Elements
    const LIBRARY_DATA = {
        sections: [
            { category: 'Hero Sections (8)', items: [
                { name: 'Standard Hero', desc: 'Title, subtitle & CTA buttons', snippet: '<section class="py-20 px-6 text-center bg-slate-900 text-white"><h1 class="text-4xl font-bold mb-4">Build Faster with PageDock</h1><p class="text-slate-400 mb-8 max-w-xl mx-auto">Publish high-performance static websites instantly with unified AI HTML tooling.</p><a href="#cta" class="inline-block px-6 py-3 bg-sky-400 text-slate-950 font-semibold rounded-lg">Get Started</a></section>' },
                { name: 'Split Hero', desc: 'Two-column copy + media showcase', snippet: '<section class="py-16 px-6 grid grid-cols-1 md:grid-cols-2 gap-8 items-center"><div class="space-y-4"><h1 class="text-3xl font-extrabold">Next-Gen Web Architecture</h1><p class="text-slate-600">Zero database lock-in. Direct HTML speed.</p><button class="px-5 py-2.5 bg-blue-600 text-white rounded-md">Explore Now</button></div><div><img src="https://images.unsplash.com/photo-1460925895917-afdab827c52f?w=600" alt="Dashboard" class="rounded-xl shadow-lg w-full"/></div></section>' },
                { name: 'Minimalist Hero', desc: 'Clean editorial typography focus', snippet: '<header class="py-24 px-8 max-w-3xl mx-auto text-left"><span class="text-xs uppercase tracking-widest text-sky-500 font-semibold">Introducing</span><h1 class="text-5xl font-black mt-2 mb-6">Minimal. Fast. Sovereign.</h1><p class="text-lg text-slate-500">Every byte optimized for speed and simplicity.</p></header>' },
                { name: 'Video Hero', desc: 'Background video or media banner', snippet: '<section class="relative py-24 px-6 text-white text-center bg-gradient-to-r from-purple-900 to-indigo-900 rounded-2xl mx-4 my-8"><h1 class="text-4xl font-bold">Experience High Performance</h1><p class="mt-4 text-purple-200">Watch the seamless deployment live.</p></section>' }
            ]},
            { category: 'Feature Grids (8)', items: [
                { name: '3-Column Features', desc: 'Grid cards with icons & descriptions', snippet: '<section class="py-16 px-6 max-w-6xl mx-auto"><h2 class="text-3xl font-bold text-center mb-12">Core Capabilities</h2><div class="grid grid-cols-1 md:grid-cols-3 gap-8"><div class="p-6 rounded-xl border border-slate-200"><h3 class="font-bold text-lg mb-2">Instant Deploy</h3><p class="text-sm text-slate-600">Push static sites live in under 500 milliseconds.</p></div><div class="p-6 rounded-xl border border-slate-200"><h3 class="font-bold text-lg mb-2">AI Visual Editor</h3><p class="text-sm text-slate-600">Click any element and prompt edits in real-time.</p></div><div class="p-6 rounded-xl border border-slate-200"><h3 class="font-bold text-lg mb-2">Multi-Page Sync</h3><p class="text-sm text-slate-600">Automatically sync shared navbars and footers.</p></div></div></section>' },
                { name: 'Bento Grid', desc: 'Modern asymmetric layout', snippet: '<section class="py-16 px-6 max-w-6xl mx-auto grid grid-cols-1 md:grid-cols-3 gap-4"><div class="md:col-span-2 p-8 bg-slate-900 text-white rounded-2xl"><h3 class="text-2xl font-bold mb-2">Unified Workspace</h3><p class="text-slate-400">Code and visual builder in one seamless tool.</p></div><div class="p-8 bg-sky-50 rounded-2xl border border-sky-100"><h3 class="text-xl font-bold text-sky-900 mb-2">Instant Preview</h3><p class="text-sky-700 text-sm">Real-time sandboxed updates.</p></div></section>' }
            ]},
            { category: 'Pricing & Plans (6)', items: [
                { name: '3-Tier Pricing Table', desc: 'Starter, Pro, and Enterprise cards', snippet: '<section class="py-16 px-6 max-w-5xl mx-auto text-center"><h2 class="text-3xl font-bold mb-4">Simple, Transparent Pricing</h2><div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-12"><div class="p-6 rounded-xl border border-slate-200"><h3 class="font-bold text-lg">Hobby</h3><div class="text-3xl font-black my-4">$0</div><button class="w-full py-2 border rounded-md">Start Free</button></div><div class="p-6 rounded-xl border-2 border-sky-500 shadow-xl relative"><span class="absolute -top-3 left-1/2 -translate-x-1/2 bg-sky-500 text-white text-xs px-3 py-1 rounded-full font-bold">Popular</span><h3 class="font-bold text-lg">Pro</h3><div class="text-3xl font-black my-4">$19<span class="text-xs">/mo</span></div><button class="w-full py-2 bg-sky-500 text-white rounded-md font-semibold">Upgrade</button></div><div class="p-6 rounded-xl border border-slate-200"><h3 class="font-bold text-lg">Team</h3><div class="text-3xl font-black my-4">$49<span class="text-xs">/mo</span></div><button class="w-full py-2 border rounded-md">Contact Sales</button></div></div></section>' }
            ]},
            { category: 'Testimonials (6)', items: [
                { name: 'Quote Grid', desc: 'Social proof cards with author avatar', snippet: '<section class="py-16 px-6 bg-slate-50"><div class="max-w-4xl mx-auto grid grid-cols-1 md:grid-cols-2 gap-6"><div class="p-6 bg-white rounded-xl shadow-sm border"><p class="text-slate-700 italic">"PageDock transformed our web agency. Deploys take seconds and client edits are effortless."</p><div class="mt-4 font-bold text-sm">— Tunde Balogun, CTO</div></div><div class="p-6 bg-white rounded-xl shadow-sm border"><p class="text-slate-700 italic">"The visual AI editor is exactly what we needed to build high-converting storefronts."</p><div class="mt-4 font-bold text-sm">— Chioma Eze, Growth Lead</div></div></div></section>' }
            ]},
            { category: 'Forms & Contact (6)', items: [
                { name: 'Modern Contact Form', desc: 'Name, email, message with PageDock form backend', snippet: '<section class="py-16 px-6 max-w-xl mx-auto"><h2 class="text-2xl font-bold mb-6 text-center">Get in Touch</h2><form action="/__pd/form" method="POST" class="space-y-4"><input type="hidden" name="_form" value="Contact"><input type="text" name="name" placeholder="Your Name" required class="w-full px-4 py-2 border rounded-lg"><input type="email" name="email" placeholder="Your Email" required class="w-full px-4 py-2 border rounded-lg"><textarea name="message" placeholder="Message" rows="4" required class="w-full px-4 py-2 border rounded-lg"></textarea><button type="submit" class="w-full py-2.5 bg-slate-900 text-white font-semibold rounded-lg">Send Message</button></form></section>' }
            ]},
            { category: 'Footers & Navbars (12)', items: [
                { name: 'Simple Footer', desc: 'Links, copyright, social markers', snippet: '<footer class="py-8 px-6 border-t border-slate-200 text-center text-xs text-slate-500"><div class="flex justify-center gap-6 mb-4"><a href="#terms" class="hover:underline">Terms</a><a href="#privacy" class="hover:underline">Privacy</a><a href="#docs" class="hover:underline">Documentation</a></div><div>&copy; ' + new Date().getFullYear() + ' PageDock Inc. All rights reserved.</div></footer>' },
                { name: 'Modern Navbar', desc: 'Logo, nav links and CTA button', snippet: '<nav class="py-4 px-6 border-b border-slate-200 flex items-center justify-between"><div class="font-bold text-lg">PageDock</div><div class="flex items-center gap-6 text-sm"><a href="#features">Features</a><a href="#pricing">Pricing</a><a href="#contact" class="px-4 py-1.5 bg-slate-900 text-white rounded-md">Launch</a></div></nav>' }
            ]}
        ],
        elements: [
            { category: 'BASIC (8)', items: [
                { name: 'Heading', snippet: '<h2 class="text-2xl font-bold my-4">Heading Title</h2>' },
                { name: 'Text Block', snippet: '<p class="text-base text-slate-600 my-2">Add your descriptive text paragraph here.</p>' },
                { name: 'Button', snippet: '<button class="px-5 py-2.5 bg-sky-500 text-white font-semibold rounded-lg hover:bg-sky-600 transition-colors">Action Button</button>' },
                { name: 'Button Group', snippet: '<div class="inline-flex gap-3"><button class="px-4 py-2 bg-slate-900 text-white rounded-md">Primary</button><button class="px-4 py-2 border rounded-md">Secondary</button></div>' },
                { name: 'Text Link', snippet: '<a href="#" class="text-sky-600 hover:underline font-medium">Learn more &rarr;</a>' },
                { name: 'Icon', snippet: '<span class="inline-block p-2 bg-sky-100 text-sky-600 rounded-full">✦</span>' },
                { name: 'Badge', snippet: '<span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">New Feature</span>' },
                { name: 'List', snippet: '<ul class="list-disc list-inside space-y-1 my-2"><li>Key highlight 1</li><li>Key highlight 2</li><li>Key highlight 3</li></ul>' }
            ]},
            { category: 'MEDIA (7)', items: [
                { name: 'Image', snippet: '<img src="https://images.unsplash.com/photo-1579546929518-9e396f3cc809?w=600" alt="Banner" class="rounded-xl max-w-full h-auto my-4"/>' },
                { name: 'Video Embed', snippet: '<div class="aspect-video bg-slate-800 rounded-xl flex items-center justify-center text-white my-4">Video Player</div>' },
                { name: 'Audio Player', snippet: '<audio controls class="w-full my-2"><source src="#" type="audio/mpeg"></audio>' },
                { name: 'Image Grid', snippet: '<div class="grid grid-cols-2 md:grid-cols-4 gap-4 my-6"><div class="aspect-square bg-slate-100 rounded-lg"></div><div class="aspect-square bg-slate-100 rounded-lg"></div><div class="aspect-square bg-slate-100 rounded-lg"></div><div class="aspect-square bg-slate-100 rounded-lg"></div></div>' },
                { name: 'Carousel', snippet: '<div class="relative overflow-hidden rounded-xl border p-8 text-center my-4">Carousel Slide</div>' },
                { name: 'Map Embed', snippet: '<div class="h-64 bg-slate-200 rounded-xl flex items-center justify-center my-4">Map Embed View</div>' },
                { name: 'Generic Embed', snippet: '<div class="p-4 border rounded-lg bg-slate-50 my-2">Embedded Widget</div>' }
            ]},
            { category: 'LAYOUT (5)', items: [
                { name: '2 Columns', snippet: '<div class="grid grid-cols-1 md:grid-cols-2 gap-6 my-6"><div class="p-4 border rounded-lg">Column 1</div><div class="p-4 border rounded-lg">Column 2</div></div>' },
                { name: '3 Columns', snippet: '<div class="grid grid-cols-1 md:grid-cols-3 gap-6 my-6"><div class="p-4 border rounded-lg">Col 1</div><div class="p-4 border rounded-lg">Col 2</div><div class="p-4 border rounded-lg">Col 3</div></div>' },
                { name: 'Card', snippet: '<div class="p-6 rounded-xl border border-slate-200 bg-white shadow-sm my-4"><h3 class="font-bold text-lg mb-2">Card Title</h3><p class="text-slate-600 text-sm">Card description goes here.</p></div>' },
                { name: 'Divider', snippet: '<hr class="my-8 border-t border-slate-200"/>' },
                { name: 'Spacer', snippet: '<div class="h-12 w-full" aria-hidden="true"></div>' }
            ]},
            { category: 'CONTENT (10)', items: [
                { name: 'Blockquote', snippet: '<blockquote class="border-l-4 border-sky-500 pl-4 py-2 italic text-slate-700 my-4">"Good design is obvious. Great design is transparent."</blockquote>' },
                { name: 'Code Snippet', snippet: '<pre class="bg-slate-900 text-sky-300 p-4 rounded-lg font-mono text-xs overflow-x-auto my-4"><code>npm install pagedock-cli</code></pre>' },
                { name: 'Table', snippet: '<table class="w-full border-collapse my-4 text-left"><thead class="border-b"><tr><th class="py-2">Feature</th><th class="py-2">Tier</th></tr></thead><tbody><tr class="border-b"><td class="py-2">SSL Deploy</td><td class="py-2">Free</td></tr></tbody></table>' },
                { name: 'Alert', snippet: '<div class="p-4 rounded-lg bg-sky-50 border border-sky-200 text-sky-800 text-sm my-4"><strong>Note:</strong> Update completed successfully.</div>' },
                { name: 'Accordion', snippet: '<details class="border rounded-lg p-4 my-2"><summary class="font-semibold cursor-pointer">Frequently Asked Question?</summary><p class="mt-2 text-sm text-slate-600">Answer details go here.</p></details>' },
                { name: 'Icon Box', snippet: '<div class="flex items-start gap-3 my-3"><div class="p-2 bg-sky-100 rounded text-sky-600 font-bold">★</div><div><h4 class="font-bold text-sm">Feature Highlight</h4><p class="text-xs text-slate-600">Short description of feature.</p></div></div>' },
                { name: 'Stat Counter', snippet: '<div class="my-4"><div class="text-3xl font-black text-slate-900">99.99%</div><div class="text-xs text-slate-500 uppercase tracking-wider">Uptime SLA</div></div>' },
                { name: 'Progress Bar', snippet: '<div class="w-full bg-slate-200 rounded-full h-2.5 my-3"><div class="bg-sky-500 h-2.5 rounded-full" style="width: 75%"></div></div>' },
                { name: 'Star Rating', snippet: '<div class="flex text-amber-400 gap-1 my-2">★★★★★</div>' },
                { name: 'Breadcrumb', snippet: '<nav class="flex gap-2 text-xs text-slate-500 my-2"><a href="#">Home</a><span>/</span><a href="#">Category</a><span>/</span><span class="text-slate-800">Page</span></nav>' }
            ]},
            { category: 'FORMS (8)', items: [
                { name: 'Contact Form', snippet: '<form action="/__pd/form" method="POST" class="space-y-3 my-4"><input type="text" name="name" placeholder="Name" class="w-full p-2 border rounded"><input type="email" name="email" placeholder="Email" class="w-full p-2 border rounded"><button type="submit" class="px-4 py-2 bg-slate-900 text-white rounded">Submit</button></form>' },
                { name: 'Email Signup', snippet: '<div class="flex gap-2 my-4"><input type="email" placeholder="Enter your email" class="p-2 border rounded-md flex-1"><button class="px-4 py-2 bg-sky-500 text-white rounded-md font-medium">Subscribe</button></div>' },
                { name: 'Search Bar', snippet: '<div class="relative my-3"><input type="search" placeholder="Search..." class="w-full pl-9 pr-3 py-2 border rounded-lg"><span class="absolute left-3 top-2.5 text-slate-400">🔍</span></div>' },
                { name: 'Input Field', snippet: '<input type="text" placeholder="Text input" class="w-full px-3 py-2 border rounded-md my-2">' },
                { name: 'Text Area', snippet: '<textarea placeholder="Write message..." rows="3" class="w-full px-3 py-2 border rounded-md my-2"></textarea>' },
                { name: 'Dropdown', snippet: '<select class="w-full px-3 py-2 border rounded-md my-2"><option>Option 1</option><option>Option 2</option></select>' },
                { name: 'File Upload', snippet: '<input type="file" class="my-2 text-sm">' },
                { name: 'Checkbox', snippet: '<label class="flex items-center gap-2 my-2 text-sm"><input type="checkbox"> Agree to terms</label>' }
            ]},
            { category: 'SOCIAL (3)', items: [
                { name: 'Social Icons', snippet: '<div class="flex gap-4 text-slate-600 my-3"><a href="#" class="hover:text-sky-500">Twitter</a><a href="#" class="hover:text-sky-500">GitHub</a><a href="#" class="hover:text-sky-500">LinkedIn</a></div>' },
                { name: 'Share Buttons', snippet: '<div class="flex gap-2 my-3"><button class="px-3 py-1 bg-sky-500 text-white rounded text-xs font-semibold">Tweet</button><button class="px-3 py-1 bg-blue-600 text-white rounded text-xs font-semibold">Share</button></div>' },
                { name: 'Profile Card', snippet: '<div class="flex items-center gap-3 p-4 border rounded-xl my-3"><div class="w-10 h-10 rounded-full bg-slate-300"></div><div><div class="font-bold text-sm">Author Name</div><div class="text-xs text-slate-500">Lead Designer</div></div></div>' }
            ]},
            { category: 'INTERACTIVE (7)', items: [
                { name: 'Modal Popup', snippet: '<div class="p-6 border rounded-xl bg-white shadow-xl max-w-sm my-4"><h3 class="font-bold mb-2">Notice</h3><p class="text-sm text-slate-600 mb-4">Modal content</p><button class="px-4 py-2 bg-slate-900 text-white rounded-md text-xs">Dismiss</button></div>' },
                { name: 'Back to Top', snippet: '<button onclick="window.scrollTo({top:0,behavior:\'smooth\'})" class="fixed bottom-6 right-6 p-3 bg-slate-900 text-white rounded-full shadow-lg">↑</button>' },
                { name: 'Tabs', snippet: '<div class="my-4"><div class="flex border-b gap-4"><button class="border-b-2 border-sky-500 pb-2 font-bold text-sm">Tab 1</button><button class="pb-2 text-sm text-slate-500">Tab 2</button></div><div class="py-4">Tab 1 Content</div></div>' },
                { name: 'Countdown', snippet: '<div class="flex gap-4 my-4 font-mono font-bold text-lg"><div class="p-2 border rounded">02<span class="text-xs block">DAYS</span></div><div class="p-2 border rounded">14<span class="text-xs block">HRS</span></div><div class="p-2 border rounded">35<span class="text-xs block">MIN</span></div></div>' },
                { name: 'Pricing Card', snippet: '<div class="p-6 border rounded-xl my-4"><h4 class="font-bold">Standard</h4><div class="text-2xl font-black my-2">$9</div><button class="w-full py-2 bg-sky-500 text-white rounded">Buy</button></div>' },
                { name: 'Testimonial', snippet: '<div class="p-4 border rounded-lg bg-slate-50 italic my-2">"Flawless user experience."</div>' },
                { name: 'Cookie Notice', snippet: '<div class="fixed bottom-0 inset-x-0 p-4 bg-slate-900 text-white flex justify-between items-center text-xs"><span>We use cookies to enhance your experience.</span><button class="px-3 py-1 bg-sky-400 text-slate-950 rounded font-semibold">Accept</button></div>' }
            ]}
        ]
    };

    function renderLibrary() {
        // 1. Render Sections
        if (dom.libSectionsContainer) {
            dom.libSectionsContainer.innerHTML = '';
            LIBRARY_DATA.sections.forEach(cat => {
                const group = document.createElement('div');
                group.className = 'hh-lib-category';
                group.innerHTML = `<div class="hh-lib-category-title">${cat.category}</div>`;

                const grid = document.createElement('div');
                grid.className = 'hh-lib-grid-2';

                cat.items.forEach(item => {
                    const card = document.createElement('div');
                    card.className = 'hh-lib-item-card';
                    card.innerHTML = `
                        <div class="hh-lib-item-icon">§</div>
                        <div class="hh-lib-item-info">
                            <div class="hh-lib-item-title">${item.name}</div>
                            <div class="hh-lib-item-desc">${item.desc}</div>
                        </div>
                    `;
                    card.addEventListener('click', () => insertSnippetIntoPage(item.snippet, item.name));
                    grid.appendChild(card);
                });

                group.appendChild(grid);
                dom.libSectionsContainer.appendChild(group);
            });
        }

        // 2. Render Elements
        if (dom.libElementsContainer) {
            dom.libElementsContainer.innerHTML = '';
            LIBRARY_DATA.elements.forEach(cat => {
                const group = document.createElement('div');
                group.className = 'hh-lib-category';
                group.innerHTML = `<div class="hh-lib-category-title">${cat.category}</div>`;

                const grid = document.createElement('div');
                grid.className = 'hh-lib-grid-2';

                cat.items.forEach(item => {
                    const card = document.createElement('div');
                    card.className = 'hh-lib-item-card';
                    card.innerHTML = `
                        <div class="hh-lib-item-icon">&lt;&gt;</div>
                        <div class="hh-lib-item-info">
                            <div class="hh-lib-item-title">${item.name}</div>
                        </div>
                    `;
                    card.addEventListener('click', () => insertSnippetIntoPage(item.snippet, item.name));
                    grid.appendChild(card);
                });

                group.appendChild(grid);
                dom.libElementsContainer.appendChild(group);
            });
        }
    }

    function insertSnippetIntoPage(snippet, name) {
        if (!cmEditor) return;
        const currentCode = getHtmlCode();
        
        let updatedCode = currentCode;
        if (currentCode.includes('</body>')) {
            updatedCode = currentCode.replace('</body>', `\n  ${snippet}\n</body>`);
        } else {
            updatedCode = currentCode + '\n' + snippet;
        }

        updateCodeEditor(updatedCode, true);
        showToast(`[ACTION] Added ${name} to page.`);
    }

    // =========================================================================
    // 6. DOM STRUCTURE TREE RENDERER (Tab 3)
    // =========================================================================

    function renderStructureTree(treeData) {
        if (!dom.treeContainer) return;
        dom.treeContainer.innerHTML = '';

        if (!treeData) {
            dom.treeContainer.innerHTML = '<div class="hh-tree-empty">No semantic nodes detected.</div>';
            return;
        }

        const nodes = Array.isArray(treeData)
            ? treeData
            : (treeData.children && treeData.children.length ? treeData.children : [treeData]);

        if (nodes.length === 0) {
            dom.treeContainer.innerHTML = '<div class="hh-tree-empty">No semantic nodes detected.</div>';
            return;
        }

        const list = document.createElement('div');
        list.className = 'hh-tree-list';

        nodes.forEach(node => {
            const item = document.createElement('div');
            item.className = 'hh-tree-node';
            item.innerHTML = `
                <span class="hh-tree-tag">&lt;${node.tag.toLowerCase()}&gt;</span>
                <span class="hh-tree-label">${node.text ? node.text.slice(0, 28) : (node.id ? '#' + node.id : '')}</span>
            `;

            // Hover outline in preview iframe
            item.addEventListener('mouseenter', () => {
                postToIframe({ type: 'hover-element', tag: node.tag, tagIndex: node.tagIndex });
            });

            // Click select in preview iframe
            item.addEventListener('click', () => {
                document.querySelectorAll('.hh-tree-node').forEach(n => n.classList.remove('selected'));
                item.classList.add('selected');
                postToIframe({ type: 'select-element', tag: node.tag, tagIndex: node.tagIndex });
            });

            list.appendChild(item);
        });

        dom.treeContainer.appendChild(list);
    }

    // =========================================================================
    // 7. KEYBOARD SHORTCUTS & HISTORY CONTROLS
    // =========================================================================

    function updateHistoryButtons() {
        const curHist = state.historyStacks[state.activeFileType] || [];
        const curRedo = state.redoStacks[state.activeFileType] || [];
        if (dom.undoBtn) dom.undoBtn.disabled = curHist.length <= 1;
        if (dom.redoBtn) dom.redoBtn.disabled = curRedo.length === 0;
    }

    function undo() {
        const curHist = state.historyStacks[state.activeFileType];
        const curRedo = state.redoStacks[state.activeFileType];
        if (!curHist || curHist.length <= 1 || !cmEditor) return;
        const current = curHist.pop();
        curRedo.push(current);
        const previous = curHist[curHist.length - 1];

        isInternalUpdate = true;
        cmEditor.setValue(previous);
        isInternalUpdate = false;
        state.fileBuffers[state.activeFileType] = previous;
        setDirty(true);
        scheduleDraftAutoSave(1000);

        if (state.activeFileType === 'html') {
            postToIframe({ type: 'live-patch', html: previous });
        } else if (state.activeFileType === 'css') {
            postToIframe({ type: 'live-style-update', css: previous });
        }
        updateHistoryButtons();
        showToast('[ACTION] Undo');
    }

    function redo() {
        const curHist = state.historyStacks[state.activeFileType];
        const curRedo = state.redoStacks[state.activeFileType];
        if (!curRedo || curRedo.length === 0 || !cmEditor) return;
        const next = curRedo.pop();
        curHist.push(next);

        isInternalUpdate = true;
        cmEditor.setValue(next);
        isInternalUpdate = false;
        state.fileBuffers[state.activeFileType] = next;
        setDirty(true);
        scheduleDraftAutoSave(1000);

        if (state.activeFileType === 'html') {
            postToIframe({ type: 'live-patch', html: next });
        } else if (state.activeFileType === 'css') {
            postToIframe({ type: 'live-style-update', css: next });
        }
        updateHistoryButtons();
        showToast('[ACTION] Redo');
    }

    function handleIframeKeyboard(e) {
        if (e.key === 'z' && (e.ctrlKey || e.metaKey)) {
            e.shiftKey ? redo() : undo();
        } else if (e.key === 'y' && (e.ctrlKey || e.metaKey)) {
            redo();
        } else if (e.key === 'j' && (e.ctrlKey || e.metaKey)) {
            toggleAiEditMode();
        } else if (e.key === 'Escape') {
            hidePopovers();
        }
    }

    window.addEventListener('keydown', (e) => {
        const isMod = e.ctrlKey || e.metaKey;
        if (isMod && e.key === 'z' && !e.shiftKey) {
            if (document.activeElement.tagName !== 'TEXTAREA' && document.activeElement.tagName !== 'INPUT') {
                e.preventDefault();
                undo();
            }
        } else if ((isMod && e.key === 'y') || (isMod && e.key === 'z' && e.shiftKey)) {
            if (document.activeElement.tagName !== 'TEXTAREA' && document.activeElement.tagName !== 'INPUT') {
                e.preventDefault();
                redo();
            }
        } else if (isMod && e.key === 's') {
            e.preventDefault();
            saveDraft();
        } else if (isMod && e.key === 'j') {
            e.preventDefault();
            toggleAiEditMode();
        } else if (isMod && e.key === 'k') {
            e.preventDefault();
            toggleChatDrawer();
        } else if (isMod && e.key === 'p') {
            e.preventDefault();
            toggleLeftPanel();
        } else if (e.key === 'Escape') {
            hidePopovers();
            closeChatDrawer();
            closeAllModals();
        }
    });

    window.addEventListener('beforeunload', () => {
        if (draftSaveTimer && state.isDirty && cmEditor) {
            clearTimeout(draftSaveTimer);
            draftSaveTimer = null;
            saveDraftToLocalStorage();
        }
    });

    // =========================================================================
    // 8. TOPBAR ACTIONS & CONTROLS
    // =========================================================================

    function toggleEditMode() {
        state.editMode = !state.editMode;
        if (dom.editModeToggle) {
            dom.editModeToggle.classList.toggle('active', state.editMode);
        }
        if (dom.editModeLabel) {
            dom.editModeLabel.textContent = state.editMode ? 'Edit Mode On' : 'Edit Mode Off';
        }
        postToIframe({ type: 'ai-mode', enabled: state.editMode });
        if (!state.editMode) hidePopovers();
    }

    function toggleAiEditMode() {
        state.aiEdit = !state.aiEdit;
        if (dom.aiEditToggle) {
            dom.aiEditToggle.classList.toggle('active', state.aiEdit);
            dom.aiEditToggle.setAttribute('aria-checked', state.aiEdit ? 'true' : 'false');
        }
        if (dom.popoverAnchor && !dom.popoverAnchor.classList.contains('hidden')) {
            showPopoverView(state.aiEdit ? 'ai' : 'direct');
        }
    }

    function toggleChatDrawer() {
        if (!dom.chatDrawer) return;
        dom.chatDrawer.classList.toggle('open');
        if (dom.chatDrawer.classList.contains('open') && dom.chatInput) {
            dom.chatInput.focus();
        }
    }

    function closeChatDrawer() {
        if (dom.chatDrawer) dom.chatDrawer.classList.remove('open');
    }

    function toggleLeftPanel() {
        if (!dom.leftPanel) return;
        dom.leftPanel.classList.toggle('collapsed');
        if (cmEditor) setTimeout(() => cmEditor.refresh(), 220);
    }

    function updateQuotaDisplay() {
        if (dom.quotaLabel) {
            dom.quotaLabel.textContent = `${state.aiQuotaUsed}/${state.aiQuotaLimit}`;
        }
        const popQuotaFill = document.getElementById('pop-quota-progress');
        const popQuotaText = document.getElementById('pop-quota-text');
        if (popQuotaFill) {
            popQuotaFill.style.width = `${(state.aiQuotaUsed / state.aiQuotaLimit) * 100}%`;
        }
        if (popQuotaText) {
            popQuotaText.textContent = `${state.aiQuotaUsed}/${state.aiQuotaLimit} today · from 5 per task`;
        }
    }

    async function saveDraft() {
        if (!cmEditor || state.isSaving) return false;
        state.isSaving = true;
        if (dom.saveStatusText) dom.saveStatusText.textContent = 'Saving...';

        try {
            // 1. Sync current editor value to active buffer
            state.fileBuffers[state.activeFileType] = cmEditor.getValue();

            // 2. Synthesize inline CSS/JS into master HTML if not separate files
            let masterHtml = state.fileBuffers.html;
            if (!window.PAGEDOCK_CONFIG?.hasPhysicalCss && state.fileBuffers.css) {
                masterHtml = injectInlineCss(masterHtml, state.fileBuffers.css);
                state.fileBuffers.html = masterHtml;
            }
            if (!window.PAGEDOCK_CONFIG?.hasPhysicalJs && state.fileBuffers.js) {
                masterHtml = injectInlineJs(masterHtml, state.fileBuffers.js);
                state.fileBuffers.html = masterHtml;
            }

            // 3. Assemble multi-file payload
            const filesToSave = {};
            filesToSave['index.html'] = masterHtml;

            if (window.PAGEDOCK_CONFIG?.hasPhysicalCss || state.fileBuffers.css) {
                filesToSave['style.css'] = state.fileBuffers.css;
            }
            if (window.PAGEDOCK_CONFIG?.hasPhysicalJs || state.fileBuffers.js) {
                filesToSave['script.js'] = state.fileBuffers.js;
            }

            const res = await fetch(window.PAGEDOCK_CONFIG.saveUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    html: masterHtml,
                    files: filesToSave,
                    file: state.activeFile
                })
            });

            const data = await res.json();
            if (data.success) {
                setDirty(false);
                clearLocalDraft();
                showToast('[STATUS] Draft saved successfully');
                return true;
            } else {
                showToast(`[ERROR] Save failed: ${data.error || 'Server error'}`);
                return false;
            }
        } catch (err) {
            console.error('Save error:', err);
            showToast('[ERROR] Failed to save draft.');
            return false;
        } finally {
            state.isSaving = false;
        }
    }

    async function publishSite() {
        const saved = await saveDraft();
        if (!saved) return;
        clearLocalDraft();
        showToast('[STATUS] Site published live! Changes deployed to CDN.');
    }

    function showToast(msg) {
        if (!dom.toast) return;
        dom.toast.textContent = msg;
        dom.toast.classList.remove('hidden');
        clearTimeout(dom.toastTimer);
        dom.toastTimer = setTimeout(() => {
            dom.toast.classList.add('hidden');
        }, 3200);
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // =========================================================================
    // 8.1 CODE FORMATTING & HTML CLEANING
    // =========================================================================

    function formatHtmlCode(html) {
        if (!html) return '';
        const preserved = [];
        let processed = html.replace(/<(pre|code|style|script)\b[^>]*>[\s\S]*?<\/\1>/gi, (match) => {
            const id = `___PAGE_DOCK_PRESERVED_${preserved.length}___`;
            preserved.push(match);
            return id;
        });

        let tab = '  ';
        let result = '';
        let indent = 0;
        const voidElements = new Set([
            'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 
            'link', 'meta', 'param', 'source', 'track', 'wbr', '!doctype'
        ]);

        const tokens = processed.replace(/>\s*</g, '><').match(/(<[^>]+>|[^<]+)/g) || [];

        for (let i = 0; i < tokens.length; i++) {
            let token = tokens[i].trim();
            if (!token) continue;

            if (token.startsWith('</')) {
                indent = Math.max(0, indent - 1);
                result += (indent > 0 ? tab.repeat(indent) : '') + token + '\n';
            } else if (token.startsWith('<') && !token.startsWith('<!--')) {
                const tagNameMatch = token.match(/^<([a-zA-Z0-9_-]+)/);
                const tagName = tagNameMatch ? tagNameMatch[1].toLowerCase() : '';
                const isSelfClosing = token.endsWith('/>') || voidElements.has(tagName);

                result += (indent > 0 ? tab.repeat(indent) : '') + token + '\n';
                if (!isSelfClosing) {
                    indent++;
                }
            } else if (token.startsWith('<!--')) {
                result += (indent > 0 ? tab.repeat(indent) : '') + token + '\n';
            } else {
                result += (indent > 0 ? tab.repeat(indent) : '') + token + '\n';
            }
        }

        preserved.forEach((block, idx) => {
            result = result.replace(`___PAGE_DOCK_PRESERVED_${idx}___`, block);
        });

        return result.trimEnd() + '\n';
    }

    function formatCssCode(css) {
        if (!css) return '';
        let formatted = css
            .replace(/\s*\{\s*/g, ' {\n  ')
            .replace(/\s*;\s*/g, ';\n  ')
            .replace(/\s*\}\s*/g, '\n}\n\n')
            .replace(/\n\s*\n\s*\n/g, '\n\n')
            .trim();
        return formatted + '\n';
    }

    function formatJsCode(js) {
        if (!js) return '';
        let lines = js.split('\n');
        let indent = 0;
        let tab = '  ';
        let result = [];

        for (let line of lines) {
            let trimmed = line.trim();
            if (!trimmed) {
                result.push('');
                continue;
            }

            if (trimmed.startsWith('}') || trimmed.startsWith(']') || trimmed.startsWith(')')) {
                indent = Math.max(0, indent - 1);
            }

            result.push(tab.repeat(indent) + trimmed);

            const opens = (trimmed.match(/[\{\[\(]/g) || []).length;
            const closes = (trimmed.match(/[\}\]\)]/g) || []).length;
            const diff = opens - closes;
            if (diff > 0 && !trimmed.startsWith('}') && !trimmed.startsWith(']') && !trimmed.startsWith(')')) {
                indent += diff;
            }
        }

        return result.join('\n').trimEnd() + '\n';
    }

    function formatCode() {
        if (!cmEditor) return;
        const type = state.activeFileType || 'html';
        const currentCode = cmEditor.getValue();

        try {
            let formatted = '';
            if (type === 'html') {
                formatted = formatHtmlCode(currentCode);
            } else if (type === 'css') {
                formatted = formatCssCode(currentCode);
            } else if (type === 'js') {
                formatted = formatJsCode(currentCode);
            }

            if (formatted && formatted !== currentCode) {
                isInternalUpdate = true;
                cmEditor.setValue(formatted);
                isInternalUpdate = false;
                state.fileBuffers[type] = formatted;
                setDirty(true);
                scheduleDraftAutoSave(800);
                if (type === 'html') {
                    postToIframe({ type: 'live-patch', html: formatted });
                } else if (type === 'css') {
                    postToIframe({ type: 'live-style-update', css: formatted });
                }
                showToast(`[STATUS] Formatted ${state.fileLabels[type] || type.toUpperCase()} code`);
            } else {
                showToast('[STATUS] Code is already formatted');
            }
        } catch (e) {
            console.error('Format error:', e);
            showToast('[ERROR] Failed to format code.');
        }
    }

    function cleanHtmlCode() {
        if (!cmEditor) return;
        const currentCode = getHtmlCode();

        try {
            let cleaned = currentCode;
            cleaned = cleaned.replace(/\s+data-pagedock-[a-z0-9_-]+="[^"]*"/gi, '');
            cleaned = cleaned.replace(/\s+data-htmlhost-[a-z0-9_-]+="[^"]*"/gi, '');
            cleaned = cleaned.replace(/\s+contenteditable="[^"]*"/gi, '');
            cleaned = cleaned.replace(/<!--\s*-->/g, '');
            cleaned = cleaned.replace(/\n\s*\n\s*\n+/g, '\n\n');
            cleaned = cleaned.split('\n').map(l => l.trimEnd()).join('\n');
            cleaned = formatHtmlCode(cleaned);

            if (cleaned !== currentCode) {
                updateCodeEditor(cleaned, true);
                showToast('[STATUS] Cleaned HTML successfully');
            } else {
                showToast('[STATUS] HTML is already clean');
            }
        } catch (e) {
            console.error('Clean HTML error:', e);
            showToast('[ERROR] Failed to clean HTML.');
        }
    }

    // =========================================================================
    // 8.2 MEDIA & ASSET LIBRARY CONTROLLER
    // =========================================================================

    function openMediaModal() {
        if (!dom.mediaModal) return;
        closeAllModals();
        dom.mediaModal.classList.remove('hidden');
        loadMediaFiles();
    }

    async function loadMediaFiles() {
        if (!window.PAGEDOCK_CONFIG?.mediaUrl || !dom.mediaGallery) return;
        dom.mediaGallery.innerHTML = '<div class="hh-empty-media">Loading assets...</div>';

        try {
            const res = await fetch(window.PAGEDOCK_CONFIG.mediaUrl);
            const data = await res.json();
            if (data.success && Array.isArray(data.files)) {
                renderMediaGallery(data.files);
            } else {
                dom.mediaGallery.innerHTML = `<div class="hh-empty-media">${data.error || 'No assets found.'}</div>`;
            }
        } catch (e) {
            dom.mediaGallery.innerHTML = '<div class="hh-empty-media">Failed to load media files.</div>';
        }
    }

    function renderMediaGallery(files) {
        if (!dom.mediaGallery) return;
        dom.mediaGallery.innerHTML = '';

        if (dom.mediaCountLabel) {
            dom.mediaCountLabel.textContent = `Uploaded Assets (${files.length})`;
        }

        if (files.length === 0) {
            dom.mediaGallery.innerHTML = '<div class="hh-empty-media">No media files uploaded yet. Drag and drop files above.</div>';
            return;
        }

        files.forEach(file => {
            const card = document.createElement('div');
            card.className = 'hh-media-card';
            card.innerHTML = `
                <div class="hh-media-thumb">
                    ${file.isImage 
                        ? `<img src="${file.url}" alt="${escapeHtml(file.name)}" loading="lazy">` 
                        : `<div class="hh-media-thumb-doc">📄</div>`}
                </div>
                <div class="hh-media-info">
                    <div class="hh-media-name" title="${escapeHtml(file.name)}">${escapeHtml(file.name)}</div>
                    <div class="hh-media-size">${file.formattedSize}</div>
                </div>
                <div class="hh-media-actions">
                    <button type="button" class="hh-media-btn-insert" title="Insert into Editor">Insert</button>
                    <button type="button" class="hh-media-btn-copy" title="Copy URL">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                    </button>
                    <button type="button" class="hh-media-btn-del" title="Delete Asset">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                    </button>
                </div>
            `;

            card.querySelector('.hh-media-btn-insert').addEventListener('click', () => {
                insertMediaIntoEditor(file);
            });

            card.querySelector('.hh-media-btn-copy').addEventListener('click', () => {
                navigator.clipboard.writeText(file.relPath);
                showToast(`[STATUS] Copied "${file.relPath}" to clipboard`);
            });

            card.querySelector('.hh-media-btn-del').addEventListener('click', () => {
                deleteMediaFile(file);
            });

            dom.mediaGallery.appendChild(card);
        });
    }

    function insertMediaIntoEditor(file) {
        if (!cmEditor) return;
        const type = state.activeFileType;
        let snippet = '';

        if (type === 'html') {
            snippet = file.isImage 
                ? `<img src="${file.relPath}" alt="${file.name.replace(/\.[^/.]+$/, '')}">`
                : `<a href="${file.relPath}" download>${file.name}</a>`;
        } else if (type === 'css') {
            snippet = `url("${file.relPath}")`;
        } else {
            snippet = `"${file.relPath}"`;
        }

        const doc = cmEditor.getDoc();
        const cursor = doc.getCursor();
        doc.replaceRange(snippet, cursor);
        cmEditor.focus();
        closeAllModals();
        showToast(`[ACTION] Inserted ${file.name}`);
    }

    async function uploadMediaFiles(fileList) {
        if (!fileList || fileList.length === 0 || !window.PAGEDOCK_CONFIG?.mediaUrl) return;

        showToast(`[STATUS] Uploading ${fileList.length} file(s)...`);
        let uploaded = 0;
        const allowedExts = ['png', 'jpg', 'jpeg', 'webp', 'svg', 'gif', 'ico', 'mp4', 'webm', 'pdf'];
        const maxBytes = 25 * 1024 * 1024; // 25MB

        for (const file of fileList) {
            const ext = (file.name.split('.').pop() || '').toLowerCase();
            if (!allowedExts.includes(ext)) {
                showToast(`[ERROR] ${file.name}: unsupported file type. Allowed: ${allowedExts.join(', ')}`);
                continue;
            }

            if (file.size > maxBytes) {
                showToast(`[ERROR] ${file.name} is too large (max 25MB).`);
                continue;
            }

            const formData = new FormData();
            formData.append('file', file);

            try {
                const res = await fetch(window.PAGEDOCK_CONFIG.mediaUrl, {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                if (data.success) {
                    uploaded++;
                } else {
                    showToast(`[ERROR] ${file.name}: ${data.error || 'Server error'}`);
                }
            } catch (err) {
                showToast(`[ERROR] Network error uploading ${file.name}`);
            }
        }

        if (uploaded > 0) {
            showToast(`[STATUS] Successfully uploaded ${uploaded} file(s)`);
            loadMediaFiles();
        }
    }

    async function deleteMediaFile(file) {
        if (!confirm(`Are you sure you want to delete "${file.name}"?`)) return;

        try {
            const res = await fetch(window.PAGEDOCK_CONFIG.mediaDeleteUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ relPath: file.relPath })
            });
            const data = await res.json();
            if (data.success) {
                showToast(`[STATUS] Deleted ${file.name}`);
                loadMediaFiles();
            } else {
                showToast(`[ERROR] Delete failed: ${data.error || 'Server error'}`);
            }
        } catch (e) {
            showToast('[ERROR] Failed to delete file');
        }
    }

    // =========================================================================
    // 8.3 VERSION HISTORY & 1-CLICK ROLLBACK CONTROLLER
    // =========================================================================

    function openVersionsModal() {
        if (!dom.versionsModal) return;
        closeAllModals();
        dom.versionsModal.classList.remove('hidden');
        loadVersionsTimeline();
    }

    async function loadVersionsTimeline() {
        if (!dom.versionsList || !window.PAGEDOCK_CONFIG?.versionsUrl) return;
        dom.versionsList.innerHTML = '<div style="text-align:center; padding:20px; color:var(--fg-4);">Loading historical snapshots...</div>';

        try {
            const res = await fetch(window.PAGEDOCK_CONFIG.versionsUrl);
            const data = await res.json();
            if (data.success && Array.isArray(data.versions)) {
                renderVersionsTimeline(data.versions, data.current_version);
            } else {
                dom.versionsList.innerHTML = '<div style="text-align:center; padding:20px; color:var(--fg-4);">No historical versions found.</div>';
            }
        } catch (e) {
            dom.versionsList.innerHTML = '<div style="text-align:center; padding:20px; color:var(--fg-4);">Failed to load version history.</div>';
        }
    }

    function renderVersionsTimeline(versions, currentVersion) {
        if (!dom.versionsList) return;
        dom.versionsList.innerHTML = '';

        if (versions.length === 0) {
            dom.versionsList.innerHTML = '<div style="text-align:center; padding:20px; color:var(--fg-4);">Initial version active. Modifications will create restore points.</div>';
            return;
        }

        const activeVer = currentVersion !== undefined ? Number(currentVersion) : Number(window.PAGEDOCK_CONFIG?.currentVersion || 1);

        versions.forEach(v => {
            const isCurrent = Number(v.version_number) === activeVer;
            const item = document.createElement('div');
            item.className = 'hh-version-item ' + (isCurrent ? 'active' : '');
            item.innerHTML = `
                <div class="hh-version-left">
                    <span class="hh-version-badge">v${v.version_number}</span>
                    <div class="hh-version-details">
                        <div class="hh-version-summary">${escapeHtml(v.summary || 'Snapshot revision')}</div>
                        <div class="hh-version-meta">${v.created_at} · ${v.file_count || 1} file(s)</div>
                    </div>
                </div>
                ${isCurrent 
                    ? '<span class="hh-version-current-label">Current Active</span>' 
                    : `<button type="button" class="hh-version-rollback-btn" data-version="${v.version_number}">Restore</button>`}
            `;

            const rollbackBtn = item.querySelector('.hh-version-rollback-btn');
            if (rollbackBtn) {
                rollbackBtn.addEventListener('click', () => {
                    rollbackToVersion(Number(v.version_number));
                });
            }

            dom.versionsList.appendChild(item);
        });
    }

    async function rollbackToVersion(targetVersion) {
        if (!confirm(`Restore site to version v${targetVersion}? Unsaved changes in your current session will be replaced.`)) {
            return;
        }

        showToast(`[STATUS] Restoring version v${targetVersion}...`);

        try {
            const res = await fetch(window.PAGEDOCK_CONFIG.rollbackUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ version: targetVersion })
            });

            const data = await res.json();
            if (data.success) {
                clearLocalDraft();
                setDirty(false);
                closeAllModals();
                showToast(`[STATUS] Restored to version v${targetVersion}! Reloading editor...`);
                setTimeout(() => {
                    window.location.reload();
                }, 900);
            } else {
                showToast(`[ERROR] Rollback failed: ${data.error || 'Server error'}`);
            }
        } catch (e) {
            showToast('[ERROR] Failed to restore version');
        }
    }

    // =========================================================================
    // 8.4 FORMS INBOX CONTROLLER
    // =========================================================================

    function openFormsModal() {
        if (!dom.formsModal) return;
        closeAllModals();
        dom.formsModal.classList.remove('hidden');
        loadFormSubmissions();
    }

    async function loadFormSubmissions() {
        if (!dom.formsContainer || !window.PAGEDOCK_CONFIG?.formsUrl) return;

        try {
            const res = await fetch(window.PAGEDOCK_CONFIG.formsUrl);
            const data = await res.json();
            if (data.submissions && Array.isArray(data.submissions)) {
                renderFormSubmissions(data.submissions);
            }
        } catch (e) {
            // Keep server-rendered content as fallback
        }
    }

    function renderFormSubmissions(submissions) {
        if (!dom.formsContainer) return;

        if (dom.formsCountBadge) {
            dom.formsCountBadge.textContent = String(submissions.length);
        }

        if (submissions.length === 0) {
            dom.formsContainer.innerHTML = `
                <div class="hh-empty-submissions">
                    <p>No submissions received yet.</p>
                </div>
            `;
            return;
        }

        let html = '<div class="hh-submissions-grid">';
        submissions.forEach(sub => {
            html += `
                <div class="hh-submission-item">
                    <div class="hh-sub-header">
                        <span>${escapeHtml(sub.created_at || '')}</span>
                        <span>${escapeHtml(sub.ip_address || '')}</span>
                    </div>
                    <div class="hh-sub-body">
            `;
            const payload = typeof sub.data === 'string' ? JSON.parse(sub.data || '{}') : (sub.data || {});
            for (const [k, v] of Object.entries(payload)) {
                const valStr = typeof v === 'object' ? JSON.stringify(v) : String(v);
                html += `
                    <div class="hh-sub-row">
                        <strong>${escapeHtml(k)}:</strong>
                        <span>${escapeHtml(valStr)}</span>
                    </div>
                `;
            }
            html += `
                    </div>
                </div>
            `;
        });
        html += '</div>';

        dom.formsContainer.innerHTML = html;
    }

    function closeAllModals() {
        document.querySelectorAll('.hh-modal-overlay').forEach(m => m.classList.add('hidden'));
    }

    // =========================================================================
    // 9. EVENT LISTENERS INITIALIZATION
    // =========================================================================

    function initEventListeners() {
        // Topbar
        if (dom.undoBtn) dom.undoBtn.addEventListener('click', undo);
        if (dom.redoBtn) dom.redoBtn.addEventListener('click', redo);
        if (dom.editModeToggle) dom.editModeToggle.addEventListener('click', toggleEditMode);
        if (dom.aiEditToggle) dom.aiEditToggle.addEventListener('click', toggleAiEditMode);
        if (dom.chatBtn) dom.chatBtn.addEventListener('click', toggleChatDrawer);
        if (dom.saveDraftBtn) dom.saveDraftBtn.addEventListener('click', saveDraft);
        if (dom.publishBtn) dom.publishBtn.addEventListener('click', publishSite);
        if (dom.collapsePanelBtn) dom.collapsePanelBtn.addEventListener('click', toggleLeftPanel);

        // Viewport Switcher
        dom.deviceBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                dom.deviceBtns.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                state.currentDevice = btn.dataset.device;
                if (dom.viewportWrapper) {
                    dom.viewportWrapper.style.width = btn.dataset.width;
                }
            });
        });

        // Mobile View Switcher (Segmented Code / Preview)
        const mobileBtns = dom.mobileViewBtns && dom.mobileViewBtns.length
            ? dom.mobileViewBtns
            : document.querySelectorAll('.hh-mobile-view-btn');
        if (mobileBtns && mobileBtns.length) {
            mobileBtns.forEach(btn => {
                btn.addEventListener('click', () => {
                    const view = btn.dataset.view || (btn.id.includes('preview') ? 'preview' : 'code');
                    setMobileView(view);
                });
            });
        }

        // Tools Dropdown
        if (dom.toolsBtn && dom.toolsMenu) {
            dom.toolsBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                dom.toolsMenu.classList.toggle('hidden');
            });
            document.addEventListener('click', () => dom.toolsMenu.classList.add('hidden'));
        }

        // Left Panel Tabs
        dom.tabBtns.forEach(btn => {
            btn.addEventListener('click', () => switchTab(btn.dataset.tab));
        });

        // Subbar File Switcher Chips (index.html, style.css, script.js)
        const fileChips = dom.fileChips && dom.fileChips.length
            ? dom.fileChips
            : document.querySelectorAll('.hh-file-chip');
        if (fileChips && fileChips.length) {
            fileChips.forEach(chip => {
                chip.addEventListener('click', (e) => {
                    e.preventDefault();
                    const fileType = chip.dataset.file;
                    if (fileType) {
                        switchFile(fileType);
                    }
                });
            });
        }

        // Library Subtabs
        dom.libSubtabs.forEach(subtab => {
            subtab.addEventListener('click', () => {
                dom.libSubtabs.forEach(s => s.classList.remove('active'));
                subtab.classList.add('active');
                const view = subtab.dataset.libView;
                state.activeLibView = view;
                if (dom.libSectionsContainer) dom.libSectionsContainer.classList.toggle('hidden', view !== 'sections');
                if (dom.libElementsContainer) dom.libElementsContainer.classList.toggle('hidden', view !== 'elements');
            });
        });

        // Popovers mode toggles & close
        if (dom.popModeSwitch) dom.popModeSwitch.addEventListener('click', toggleAiEditMode);
        if (dom.popDirectModeSwitch) dom.popDirectModeSwitch.addEventListener('click', toggleAiEditMode);
        if (dom.popCloseBtn) {
            dom.popCloseBtn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                hidePopovers();
            });
        }
        if (dom.popDirectCloseBtn) {
            dom.popDirectCloseBtn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                hidePopovers();
            });
        }
        if (dom.popCancelBtn) {
            dom.popCancelBtn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                hidePopovers();
            });
        }
        if (dom.popApplyBtn) dom.popApplyBtn.addEventListener('click', applyAiEdit);

        // Click outside popover to dismiss
        document.addEventListener('pointerdown', (e) => {
            if (!dom.popoverAnchor || dom.popoverAnchor.classList.contains('hidden') || dom.popoverAnchor.style.display === 'none') return;
            if (!dom.popoverAnchor.contains(e.target) && !e.target.closest('#hh-topbar') && !e.target.closest('#hh-left-panel')) {
                hidePopovers();
            }
        });

        // Direct Edit text updates
        if (dom.directTextInput) {
            dom.directTextInput.addEventListener('input', () => {
                if (state.selectedElement) {
                    const newText = dom.directTextInput.value;
                    postToIframe({
                        type: 'patch-element',
                        tag: state.selectedElement.tag,
                        tagIndex: state.selectedElement.tagIndex,
                        text: newText
                    });
                    handleElementTextEdit({ text: newText });
                }
            });
        }

        if (dom.directInlineEditBtn) {
            dom.directInlineEditBtn.addEventListener('click', () => {
                if (state.selectedElement) {
                    postToIframe({
                        type: 'begin-text-edit',
                        tag: state.selectedElement.tag,
                        tagIndex: state.selectedElement.tagIndex
                    });
                } else {
                    postToIframe({ type: 'begin-text-edit' });
                }
                hidePopovers(true);
            });
        }

        if (dom.actDuplicateBtn) dom.actDuplicateBtn.addEventListener('click', duplicateSelectedElement);
        if (dom.actCodeBtn) dom.actCodeBtn.addEventListener('click', jumpToElementInCode);
        if (dom.actDeleteBtn) dom.actDeleteBtn.addEventListener('click', deleteSelectedElement);

        // Chat Drawer
        if (dom.closeChatBtn) dom.closeChatBtn.addEventListener('click', closeChatDrawer);
        if (dom.sendChatBtn && dom.chatInput) {
            dom.sendChatBtn.addEventListener('click', sendChatMessage);
            dom.chatInput.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    sendChatMessage();
                }
            });
        }

        // Splitter Dragging
        initSplitterDrag();

        // Media Modal Trigger & Actions
        if (dom.mediaBtn) dom.mediaBtn.addEventListener('click', openMediaModal);
        if (dom.mediaBrowseBtn && dom.mediaFileInput) {
            dom.mediaBrowseBtn.addEventListener('click', () => dom.mediaFileInput.click());
        }
        if (dom.mediaFileInput) {
            dom.mediaFileInput.addEventListener('change', (e) => {
                uploadMediaFiles(e.target.files);
                dom.mediaFileInput.value = '';
            });
        }
        if (dom.mediaRefreshBtn) dom.mediaRefreshBtn.addEventListener('click', loadMediaFiles);

        // Media Drag and Drop
        if (dom.mediaDropzone) {
            ['dragenter', 'dragover'].forEach(eventName => {
                dom.mediaDropzone.addEventListener(eventName, (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    dom.mediaDropzone.classList.add('dragover');
                });
            });
            ['dragleave', 'drop'].forEach(eventName => {
                dom.mediaDropzone.addEventListener(eventName, (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    dom.mediaDropzone.classList.remove('dragover');
                });
            });
            dom.mediaDropzone.addEventListener('drop', (e) => {
                e.preventDefault();
                e.stopPropagation();
                dom.mediaDropzone.classList.remove('dragover');
                const dt = e.dataTransfer;
                if (dt && dt.files && dt.files.length) {
                    uploadMediaFiles(dt.files);
                }
            });
        }

        // Tools Menu Items
        if (dom.toolFormatCode) {
            dom.toolFormatCode.addEventListener('click', () => {
                if (dom.toolsMenu) dom.toolsMenu.classList.add('hidden');
                formatCode();
            });
        }
        if (dom.toolCleanHtml) {
            dom.toolCleanHtml.addEventListener('click', () => {
                if (dom.toolsMenu) dom.toolsMenu.classList.add('hidden');
                cleanHtmlCode();
            });
        }
        if (dom.toolRevisions) {
            dom.toolRevisions.addEventListener('click', () => {
                if (dom.toolsMenu) dom.toolsMenu.classList.add('hidden');
                openVersionsModal();
            });
        }
        if (dom.toolFormsInbox) {
            dom.toolFormsInbox.addEventListener('click', () => {
                if (dom.toolsMenu) dom.toolsMenu.classList.add('hidden');
                openFormsModal();
            });
        }

        // Generic Modal Close Buttons & Backdrop Dismissal
        document.querySelectorAll('[data-close-modal]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                closeAllModals();
            });
        });
        document.querySelectorAll('.hh-modal-overlay').forEach(modal => {
            modal.addEventListener('mousedown', (e) => {
                if (e.target === modal) {
                    closeAllModals();
                }
            });
        });
    }

    function initSplitterDrag() {
        if (!dom.splitter || !dom.leftPanel) return;
        let isDragging = false;

        dom.splitter.addEventListener('mousedown', (e) => {
            isDragging = true;
            dom.splitter.classList.add('dragging');
            document.body.style.cursor = 'col-resize';
            document.body.style.userSelect = 'none';
        });

        window.addEventListener('mousemove', (e) => {
            if (!isDragging) return;
            const newWidth = Math.max(260, Math.min(650, e.clientX));
            dom.leftPanel.style.width = `${newWidth}px`;
            if (cmEditor) cmEditor.refresh();
        });

        window.addEventListener('mouseup', () => {
            if (isDragging) {
                isDragging = false;
                dom.splitter.classList.remove('dragging');
                document.body.style.cursor = '';
                document.body.style.userSelect = '';
            }
        });
    }

    async function sendChatMessage() {
        const text = dom.chatInput ? dom.chatInput.value.trim() : '';
        if (!text) return;

        // Append user bubble
        appendChatBubble('user', text);
        dom.chatInput.value = '';

        try {
            const res = await fetch(window.PAGEDOCK_CONFIG.aiBuiltinUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    mode: 'chat',
                    instruction: text,
                    htmlContext: getHtmlCode() || ''
                })
            });

            const data = await res.json();
            appendChatBubble('assistant', data.message || 'I have analyzed your site structure.');
        } catch (err) {
            appendChatBubble('assistant', 'Sorry, I encountered an error connecting to the AI engine.');
        }
    }

    function appendChatBubble(role, content) {
        if (!dom.chatMessages) return;
        const div = document.createElement('div');
        div.className = `hh-chat-message ${role}`;
        div.innerHTML = `
            <div class="hh-msg-avatar">${role === 'user' ? 'U' : '✦'}</div>
            <div class="hh-msg-bubble"><p>${content}</p></div>
        `;
        dom.chatMessages.appendChild(div);
        dom.chatMessages.scrollTop = dom.chatMessages.scrollHeight;
    }

    // =========================================================================
    // 10. BOOTSTRAP
    // =========================================================================

    function init() {
        initCodeMirror();
        checkAndRestoreDraft();
        initEventListeners();
        renderLibrary();
        updateHistoryButtons();
        updateQuotaDisplay();
        setMobileView(document.body && document.body.classList.contains('hh-mobile-mode-preview') ? 'preview' : 'code');
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
