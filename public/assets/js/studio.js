// PageDock Visual Studio Engine (Elementor-Style Architecture)

document.addEventListener('DOMContentLoaded', () => {
    const config = window.PAGEDOCK_STUDIO_CONFIG || {};
    const iframe = document.getElementById('studio-preview-iframe');
    const viewportWrapper = document.getElementById('studio-viewport-wrapper');
    const viewportBtns = document.querySelectorAll('.viewport-btn');
    const canvasDimTag = document.getElementById('canvas-dimensions-tag');
    const saveBtn = document.getElementById('save-studio-btn');
    const saveStatus = document.getElementById('save-status');
    const toast = document.getElementById('toast');

    // Panel tabs & views
    const tabWidgetsBtn = document.getElementById('tab-widgets-btn');
    const tabInspectorBtn = document.getElementById('tab-inspector-btn');
    const panelWidgetsView = document.getElementById('panel-widgets-view');
    const panelInspectorView = document.getElementById('panel-inspector-view');
    const inspectorDot = document.getElementById('inspector-active-dot');

    // Inspector Subtabs
    const subtabBtns = document.querySelectorAll('.subtab-btn');
    const subtabContent = document.getElementById('subtab-content');
    const subtabStyle = document.getElementById('subtab-style');
    const subtabAdvanced = document.getElementById('subtab-advanced');

    // Inspector Controls
    const inspectTagBadge = document.getElementById('inspect-tag-badge');
    const inspectClassBadge = document.getElementById('inspect-class-badge');
    const inspectBreadcrumbs = document.getElementById('inspect-breadcrumbs');
    const closeInspectorBtn = document.getElementById('close-inspector-btn');

    // Content Controls
    const ctrlContentText = document.getElementById('ctrl-content-text');
    const ctrlHtmlTag = document.getElementById('ctrl-html-tag');
    const ctrlLinkUrl = document.getElementById('ctrl-link-url');
    const ctrlImageSrc = document.getElementById('ctrl-image-src');
    const ctrlImageAlt = document.getElementById('ctrl-image-alt');
    const grpCtrlLink = document.getElementById('grp-ctrl-link');
    const grpCtrlImage = document.getElementById('grp-ctrl-image');
    const alignBtns = document.querySelectorAll('#ctrl-align-segmented .segment-btn');

    // Style Controls
    const ctrlTextColorPicker = document.getElementById('ctrl-text-color-picker');
    const ctrlTextColorHex = document.getElementById('ctrl-text-color-hex');
    const ctrlBgColorPicker = document.getElementById('ctrl-bg-color-picker');
    const ctrlBgColorHex = document.getElementById('ctrl-bg-color-hex');
    const ctrlFontFamily = document.getElementById('ctrl-font-family');
    const ctrlFontSizeSlider = document.getElementById('ctrl-font-size-slider');
    const valFontSize = document.getElementById('val-font-size');
    const ctrlFontWeight = document.getElementById('ctrl-font-weight');
    const ctrlLineHeightSlider = document.getElementById('ctrl-line-height-slider');
    const valLineHeight = document.getElementById('val-line-height');
    const ctrlLetterSpacingSlider = document.getElementById('ctrl-letter-spacing-slider');
    const valLetterSpacing = document.getElementById('val-letter-spacing');
    const ctrlBorderRadiusSlider = document.getElementById('ctrl-border-radius-slider');
    const valBorderRadius = document.getElementById('val-border-radius');
    const shadowBtns = document.querySelectorAll('#ctrl-shadow-segmented .segment-btn');

    // Advanced Controls
    const toggleMarginLink = document.getElementById('toggle-margin-link');
    const togglePaddingLink = document.getElementById('toggle-padding-link');
    const ctrlMarginTop = document.getElementById('ctrl-margin-top');
    const ctrlMarginRight = document.getElementById('ctrl-margin-right');
    const ctrlMarginBottom = document.getElementById('ctrl-margin-bottom');
    const ctrlMarginLeft = document.getElementById('ctrl-margin-left');
    const ctrlPaddingTop = document.getElementById('ctrl-padding-top');
    const ctrlPaddingRight = document.getElementById('ctrl-padding-right');
    const ctrlPaddingBottom = document.getElementById('ctrl-padding-bottom');
    const ctrlPaddingLeft = document.getElementById('ctrl-padding-left');
    const ctrlDisplayMode = document.getElementById('ctrl-display-mode');
    const ctrlCssClasses = document.getElementById('ctrl-css-classes');
    const ctrlElementId = document.getElementById('ctrl-element-id');
    const ctrlInlineCss = document.getElementById('ctrl-inline-css');
    const actDuplicateBtn = document.getElementById('act-duplicate-btn');
    const actDeleteBtn = document.getElementById('act-delete-btn');

    // Link states
    let isMarginLinked = true;
    let isPaddingLinked = true;

    // Topbar Actions
    const actUndoBtn = document.getElementById('act-undo-btn');
    const actRedoBtn = document.getElementById('act-redo-btn');

    // Navigator
    const toggleNavigatorBtn = document.getElementById('toggle-navigator-btn');
    const navigatorModal = document.getElementById('studio-navigator-modal');
    const closeNavigatorBtn = document.getElementById('close-navigator-btn');
    const navigatorTree = document.getElementById('navigator-tree-container');

    // State
    let masterHtml = config.initialHtml || '';
    let bufferCss = config.initialCss || '';
    let isDirty = false;
    let selectedElement = null;
    let hoveredElement = null;
    let insertionTarget = null;
    let insertionPosition = 'after';

    // Undo / Redo History Stack
    let undoStack = [];
    let redoStack = [];
    const MAX_HISTORY = 40;

    function pushHistoryState() {
        if (!masterHtml) return;
        if (undoStack.length === 0 || undoStack[undoStack.length - 1] !== masterHtml) {
            undoStack.push(masterHtml);
            if (undoStack.length > MAX_HISTORY) undoStack.shift();
            redoStack = [];
            updateUndoRedoUI();
        }
    }

    function undo() {
        if (undoStack.length > 0) {
            redoStack.push(masterHtml);
            masterHtml = undoStack.pop();
            deselectElement();
            markDirty();
            triggerStudioLivePreview(true);
            updateUndoRedoUI();
            showToast('Action undone');
        } else {
            showToast('Nothing to undo');
        }
    }

    function redo() {
        if (redoStack.length > 0) {
            undoStack.push(masterHtml);
            masterHtml = redoStack.pop();
            deselectElement();
            markDirty();
            triggerStudioLivePreview(true);
            updateUndoRedoUI();
            showToast('Action redone');
        } else {
            showToast('Nothing to redo');
        }
    }

    function updateUndoRedoUI() {
        if (actUndoBtn) {
            actUndoBtn.disabled = undoStack.length === 0;
            actUndoBtn.style.opacity = undoStack.length === 0 ? '0.4' : '1';
        }
        if (actRedoBtn) {
            actRedoBtn.disabled = redoStack.length === 0;
            actRedoBtn.style.opacity = redoStack.length === 0 ? '0.4' : '1';
        }
    }

    if (actUndoBtn) actUndoBtn.addEventListener('click', undo);
    if (actRedoBtn) actRedoBtn.addEventListener('click', redo);
    updateUndoRedoUI();

    // 1. Viewport Switcher
    viewportBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            viewportBtns.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            const targetWidth = btn.getAttribute('data-width') || '100%';
            if (viewportWrapper) {
                viewportWrapper.style.width = targetWidth;
            }
            if (canvasDimTag) {
                canvasDimTag.textContent = targetWidth;
            }
        });
    });

    // 2. Panel Tab Switching (Widgets vs Inspector)
    function switchPanelTab(tab) {
        if (tab === 'widgets') {
            tabWidgetsBtn.classList.add('active');
            tabInspectorBtn.classList.remove('active');
            panelWidgetsView.classList.remove('hidden');
            panelInspectorView.classList.add('hidden');
        } else if (tab === 'inspector') {
            tabWidgetsBtn.classList.remove('active');
            tabInspectorBtn.classList.add('active');
            panelWidgetsView.classList.add('hidden');
            panelInspectorView.classList.remove('hidden');
        }
    }

    if (tabWidgetsBtn) tabWidgetsBtn.addEventListener('click', () => switchPanelTab('widgets'));
    if (tabInspectorBtn) tabInspectorBtn.addEventListener('click', () => switchPanelTab('inspector'));

    // 3. Subtab Switching (Content vs Style vs Advanced)
    subtabBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            subtabBtns.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            const subtab = btn.getAttribute('data-subtab');
            if (subtabContent) subtabContent.classList.toggle('hidden', subtab !== 'content');
            if (subtabStyle) subtabStyle.classList.toggle('hidden', subtab !== 'style');
            if (subtabAdvanced) subtabAdvanced.classList.toggle('hidden', subtab !== 'advanced');
        });
    });

    // 4. Studio Live Preview Synthesis
    function buildStudioHtml() {
        let doc = masterHtml || '';
        const baseUrl = config.previewUrl ? (config.previewUrl.endsWith('/') ? config.previewUrl : config.previewUrl + '/') : '';

        // Base grounding
        if (baseUrl) {
            if (/<base\b[^>]*>/i.test(doc)) {
                doc = doc.replace(/<base\b[^>]*>/i, `<base href="${baseUrl}">`);
            } else if (doc.includes('<head>')) {
                doc = doc.replace('<head>', `<head>\n    <base href="${baseUrl}">`);
            } else {
                doc = `<base href="${baseUrl}">\n` + doc;
            }
        }

        // Hot style injection
        if (bufferCss) {
            const styleTag = `<style id="__pagedock_studio_css">\n${bufferCss}\n</style>`;
            if (doc.includes('</head>')) {
                doc = doc.replace('</head>', `    ${styleTag}\n</head>`);
            } else {
                doc = styleTag + '\n' + doc;
            }
        }

        // Overlay stylesheet
        const overlayCss = `
        <style id="__pagedock_overlay_css">
            .__pd_hover {
                outline: 2px dashed #C7F547 !important;
                outline-offset: 1px !important;
                cursor: pointer !important;
            }
            .__pd_selected {
                outline: 2px solid #C7F547 !important;
                outline-offset: 2px !important;
                box-shadow: 0 0 16px rgba(199, 245, 71, 0.4) !important;
            }
            [contenteditable="true"] {
                outline: 2px solid #60a5fa !important;
                cursor: text !important;
            }
            #__pd_element_toolbar {
                position: absolute !important;
                z-index: 9999999 !important;
                display: flex !important;
                align-items: center !important;
                gap: 3px !important;
                background: #101216 !important;
                border: 1px solid #262a34 !important;
                border-radius: 6px !important;
                padding: 3px 6px !important;
                box-shadow: 0 8px 24px rgba(0, 0, 0, 0.6) !important;
                pointer-events: auto !important;
                user-select: none !important;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important;
                font-size: 11px !important;
                line-height: 1 !important;
                box-sizing: border-box !important;
            }
            .__pd_tool_tag {
                background: rgba(199, 245, 71, 0.15) !important;
                color: #C7F547 !important;
                font-size: 10px !important;
                font-weight: 800 !important;
                padding: 3px 6px !important;
                border-radius: 4px !important;
                text-transform: uppercase !important;
                letter-spacing: 0.05em !important;
                margin-right: 3px !important;
            }
            .__pd_tool_btn {
                display: inline-flex !important;
                align-items: center !important;
                justify-content: center !important;
                width: 24px !important;
                height: 24px !important;
                border: none !important;
                background: transparent !important;
                color: #949ba8 !important;
                border-radius: 4px !important;
                cursor: pointer !important;
                transition: all 0.12s ease !important;
                padding: 0 !important;
            }
            .__pd_tool_btn:hover {
                color: #ffffff !important;
                background: #22262f !important;
            }
            .__pd_tool_danger:hover {
                color: #ef4444 !important;
                background: rgba(239, 68, 68, 0.15) !important;
            }
            .__pd_add_section_zone {
                margin: 28px auto !important;
                text-align: center !important;
                position: relative !important;
                padding: 12px 0 !important;
            }
            .__pd_inter_zone {
                height: 18px !important;
                opacity: 0 !important;
                margin: -9px auto !important;
                padding: 0 !important;
                transition: all 0.16s ease !important;
                z-index: 9999 !important;
            }
            .__pd_inter_zone:hover {
                height: 42px !important;
                opacity: 1 !important;
                margin: 12px auto !important;
                padding: 4px 0 !important;
            }
            .__pd_inter_zone .__pd_add_section_btn {
                background: #181b21 !important;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.5) !important;
            }
            .__pd_add_section_zone::before {
                content: '' !important;
                position: absolute !important;
                top: 50% !important;
                left: 10% !important;
                right: 10% !important;
                height: 1px !important;
                background: #262a34 !important;
                z-index: 1 !important;
            }
            .__pd_add_section_btn {
                position: relative !important;
                z-index: 2 !important;
                background: #101216 !important;
                color: #C7F547 !important;
                border: 1px solid rgba(199, 245, 71, 0.3) !important;
                padding: 6px 16px !important;
                border-radius: 999px !important;
                font-size: 11px !important;
                font-weight: 700 !important;
                cursor: pointer !important;
                transition: all 0.15s ease !important;
                font-family: inherit !important;
            }
            .__pd_add_section_btn:hover {
                background: #C7F547 !important;
                color: #000000 !important;
                transform: scale(1.04) !important;
                box-shadow: 0 0 16px rgba(199, 245, 71, 0.4) !important;
            }
            #__pd_drop_indicator {
                position: absolute !important;
                height: 3px !important;
                background: #C7F547 !important;
                box-shadow: 0 0 10px #C7F547 !important;
                z-index: 9999998 !important;
                pointer-events: none !important;
                border-radius: 2px !important;
                transition: top 0.05s ease, left 0.05s ease, width 0.05s ease !important;
            }
        </style>
        `;
        if (doc.includes('</head>')) {
            doc = doc.replace('</head>', overlayCss + '\n</head>');
        } else {
            doc = overlayCss + '\n' + doc;
        }

        return doc;
    }

    function triggerStudioLivePreview(immediate = false) {
        if (!iframe) return;
        iframe.srcdoc = buildStudioHtml();
    }

    // 5. Canvas Event Broker (Hover, Click, Double-Click)
    function attachIframeListeners() {
        try {
            const iframeDoc = iframe.contentDocument || (iframe.contentWindow ? iframe.contentWindow.document : null);
            if (!iframeDoc || !iframeDoc.body) return;

            // Hover Handler
            iframeDoc.body.addEventListener('mouseover', (e) => {
                const target = e.target;
                if (!target || target === iframeDoc.body || target === iframeDoc.documentElement) return;
                if (target.id === '__pagedock_overlay_css') return;

                if (hoveredElement && hoveredElement !== target && hoveredElement !== selectedElement) {
                    hoveredElement.classList.remove('__pd_hover');
                }
                hoveredElement = target;
                if (hoveredElement !== selectedElement) {
                    hoveredElement.classList.add('__pd_hover');
                }
            });

            iframeDoc.body.addEventListener('mouseout', (e) => {
                if (hoveredElement && hoveredElement !== selectedElement) {
                    hoveredElement.classList.remove('__pd_hover');
                    hoveredElement = null;
                }
            });

            // Click Handler (Select element & inspect)
            iframeDoc.body.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();

                const target = e.target;
                if (!target || target === iframeDoc.body || target === iframeDoc.documentElement) return;

                selectElement(target);
            });

            // Double Click Handler (Inline Text Edit)
            iframeDoc.body.addEventListener('dblclick', (e) => {
                const target = e.target;
                if (!target || target.id === '__pd_element_toolbar' || target.closest('#__pd_element_toolbar')) return;

                const textTags = ['H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'P', 'SPAN', 'A', 'BUTTON', 'LI'];
                if (textTags.includes(target.tagName)) {
                    e.preventDefault();
                    e.stopPropagation();

                    // Hide toolbar during inline text typing
                    const tb = iframeDoc.getElementById('__pd_element_toolbar');
                    if (tb) tb.style.display = 'none';

                    target.setAttribute('contenteditable', 'true');
                    target.focus();

                    function onInlineBlur() {
                        target.removeAttribute('contenteditable');
                        target.removeEventListener('blur', onInlineBlur);
                        syncElementToMasterHtml();
                        markDirty();
                        populateInspector(target);
                        updateToolbar(target);
                    }
                    target.addEventListener('blur', onInlineBlur);
                }
            });

            // Scroll & Resize Handler for Toolbar Tracking
            const win = iframe.contentWindow || iframeDoc.defaultView;
            if (win) {
                win.addEventListener('scroll', () => {
                    if (selectedElement) updateToolbar(selectedElement);
                }, { passive: true });
                win.addEventListener('resize', () => {
                    if (selectedElement) updateToolbar(selectedElement);
                }, { passive: true });
            }

            // Ensure Add Section drop zone is initialized
            ensureAddSectionZone(iframeDoc);

            // Canvas Drag & Drop Engine
            let dropIndicator = iframeDoc.getElementById('__pd_drop_indicator');
            if (!dropIndicator) {
                dropIndicator = iframeDoc.createElement('div');
                dropIndicator.id = '__pd_drop_indicator';
                dropIndicator.style.display = 'none';
                iframeDoc.body.appendChild(dropIndicator);
            }

            let dropTargetEl = null;
            let dropPosition = 'after';

            iframeDoc.addEventListener('dragover', (e) => {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'copy';

                const target = e.target;
                if (!target || target === iframeDoc.body || target === iframeDoc.documentElement || target.id === '__pd_drop_indicator' || target.id === '__pd_element_toolbar' || target.closest('#__pd_element_toolbar')) {
                    if (dropIndicator) dropIndicator.style.display = 'none';
                    dropTargetEl = null;
                    return;
                }

                const block = target.closest('header, nav, section, article, aside, footer, div, h1, h2, h3, h4, p, a, form') || target;
                if (block && block !== iframeDoc.body && block !== iframeDoc.documentElement) {
                    dropTargetEl = block;
                    const rect = block.getBoundingClientRect();
                    const win = iframe.contentWindow || iframeDoc.defaultView;
                    const scrollX = win ? (win.scrollX || win.pageXOffset || 0) : 0;
                    const scrollY = win ? (win.scrollY || win.pageYOffset || 0) : 0;

                    const isBefore = (e.clientY < rect.top + rect.height / 2);
                    dropPosition = isBefore ? 'before' : 'after';

                    const top = (isBefore ? rect.top : rect.bottom) + scrollY;
                    dropIndicator.style.top = `${top - 1}px`;
                    dropIndicator.style.left = `${rect.left + scrollX}px`;
                    dropIndicator.style.width = `${rect.width}px`;
                    dropIndicator.style.display = 'block';
                }
            });

            iframeDoc.addEventListener('dragleave', (e) => {
                if (!e.relatedTarget || e.relatedTarget.nodeName === 'HTML') {
                    if (dropIndicator) dropIndicator.style.display = 'none';
                }
            });

            iframeDoc.addEventListener('drop', (e) => {
                e.preventDefault();
                if (dropIndicator) dropIndicator.style.display = 'none';

                const snippet = e.dataTransfer.getData('application/pagedock-snippet') || e.dataTransfer.getData('text/plain');
                const title = e.dataTransfer.getData('application/pagedock-title') || 'Element';
                if (!snippet) return;

                pushHistoryState();

                if (dropTargetEl && dropTargetEl.parentNode) {
                    const range = dropTargetEl.ownerDocument.createRange();
                    const fragment = range.createContextualFragment(snippet);
                    const firstInserted = fragment.firstElementChild;
                    if (dropPosition === 'before') {
                        dropTargetEl.parentNode.insertBefore(fragment, dropTargetEl);
                    } else {
                        dropTargetEl.parentNode.insertBefore(fragment, dropTargetEl.nextSibling);
                    }
                    syncElementToMasterHtml();
                    markDirty();
                    if (firstInserted) {
                        selectElement(firstInserted);
                    }
                    ensureAddSectionZone(iframeDoc);
                    showToast(`Dropped ${title}`);
                } else {
                    insertHtmlSnippet(snippet);
                    showToast(`Inserted ${title}`);
                }
                dropTargetEl = null;
            });

        } catch (e) {
            console.warn('Iframe access warning:', e);
        }
    }

    iframe.addEventListener('load', () => {
        attachIframeListeners();
    });

    // 5b. Floating Action Toolbar Engine
    function updateToolbar(el) {
        try {
            const iframeDoc = iframe.contentDocument || (iframe.contentWindow ? iframe.contentWindow.document : null);
            if (!iframeDoc || !iframeDoc.body) return;

            let toolbar = iframeDoc.getElementById('__pd_element_toolbar');
            if (!el || !el.isConnected) {
                if (toolbar) toolbar.remove();
                return;
            }

            if (!toolbar) {
                toolbar = iframeDoc.createElement('div');
                toolbar.id = '__pd_element_toolbar';
                iframeDoc.body.appendChild(toolbar);
            }

            const tag = el.tagName.toLowerCase();
            const cls = el.className ? '.' + el.className.replace('__pd_selected', '').replace('__pd_hover', '').trim().split(' ')[0] : '';
            const tagLabel = cls ? `${tag}${cls}` : tag;

            toolbar.innerHTML = `
                <span class="__pd_tool_tag">${tagLabel}</span>
                <button type="button" class="__pd_tool_btn" data-act="edit" title="Edit in Inspector">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                </button>
                <button type="button" class="__pd_tool_btn" data-act="add" title="Add Widget After">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                </button>
                <button type="button" class="__pd_tool_btn" data-act="duplicate" title="Duplicate Element">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                </button>
                <button type="button" class="__pd_tool_btn __pd_tool_danger" data-act="delete" title="Delete Element">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                </button>
            `;

            // Position toolbar
            const rect = el.getBoundingClientRect();
            const win = iframe.contentWindow || iframeDoc.defaultView;
            const scrollX = win ? (win.scrollX || win.pageXOffset || 0) : 0;
            const scrollY = win ? (win.scrollY || win.pageYOffset || 0) : 0;

            let top = rect.top + scrollY - 32;
            if (top < scrollY + 4) {
                top = rect.bottom + scrollY + 4; // Flip below if near canvas ceiling
            }
            let left = Math.max(8, rect.left + scrollX);

            toolbar.style.top = `${top}px`;
            toolbar.style.left = `${left}px`;
            toolbar.style.display = 'flex';

            // Button actions
            toolbar.querySelector('[data-act="edit"]').onclick = (e) => {
                e.stopPropagation();
                switchPanelTab('inspector');
                if (ctrlContentText) ctrlContentText.focus();
            };

            toolbar.querySelector('[data-act="add"]').onclick = (e) => {
                e.stopPropagation();
                insertionTarget = el;
                insertionPosition = 'after';
                switchPanelTab('widgets');
                showToast(`Choose a widget to insert after <${tagLabel}>`);
            };

            toolbar.querySelector('[data-act="duplicate"]').onclick = (e) => {
                e.stopPropagation();
                pushHistoryState();
                const clone = el.cloneNode(true);
                clone.classList.remove('__pd_selected', '__pd_hover');
                el.parentNode.insertBefore(clone, el.nextSibling);
                selectElement(clone);
                syncElementToMasterHtml();
                markDirty();
                ensureAddSectionZone(iframeDoc);
                showToast('Element duplicated');
            };

            toolbar.querySelector('[data-act="delete"]').onclick = (e) => {
                e.stopPropagation();
                pushHistoryState();
                toolbar.remove();
                el.remove();
                deselectElement();
                syncElementToMasterHtml();
                markDirty();
                ensureAddSectionZone(iframeDoc);
                showToast('Element deleted');
            };

        } catch (e) {
            console.warn('Toolbar update warning:', e);
        }
    }

    // 5c. Inter-Section Drop Zone Engine
    function ensureAddSectionZone(iframeDoc) {
        if (!iframeDoc || !iframeDoc.body) return;

        // 1. Clean existing inter-section zones before refreshing
        iframeDoc.querySelectorAll('.__pd_inter_zone').forEach(z => z.remove());

        // 2. Query major layout sections
        const topLevelSections = Array.from(iframeDoc.body.children).filter(el => {
            if (!el || el.nodeType !== 1) return false;
            if (el.id === '__pd_element_toolbar' || el.id === '__pd_drop_indicator' || el.id === '__pd_add_section_bottom' || el.classList.contains('__pd_add_section_zone')) return false;
            const tag = el.tagName.toLowerCase();
            return ['header', 'nav', 'section', 'article', 'aside', 'footer', 'main', 'div'].includes(tag);
        });

        // 3. Inject hoverable inter-section drop handle before each section
        topLevelSections.forEach(sec => {
            const zone = iframeDoc.createElement('div');
            zone.className = '__pd_add_section_zone __pd_inter_zone';
            zone.innerHTML = `<button type="button" class="__pd_add_section_btn">+ Add Section Here</button>`;
            zone.onclick = (e) => {
                e.preventDefault();
                e.stopPropagation();
                insertionTarget = sec;
                insertionPosition = 'before';
                deselectElement();
                switchPanelTab('widgets');
                showToast(`Select a section or widget to insert before <${sec.tagName.toLowerCase()}>`);
            };
            iframeDoc.body.insertBefore(zone, sec);
        });

        // 4. Ensure bottom drop zone
        let zone = iframeDoc.getElementById('__pd_add_section_bottom');
        if (!zone) {
            zone = iframeDoc.createElement('div');
            zone.id = '__pd_add_section_bottom';
            zone.className = '__pd_add_section_zone';
            zone.innerHTML = `<button type="button" class="__pd_add_section_btn">+ Add New Section</button>`;
            zone.onclick = (e) => {
                e.preventDefault();
                e.stopPropagation();
                insertionTarget = null;
                insertionPosition = 'bottom';
                deselectElement();
                switchPanelTab('widgets');
                showToast('Select a section blueprint from the palette');
            };
            iframeDoc.body.appendChild(zone);
        }
    }

    // 6. Select Element & Populate Inspector
    function selectElement(el) {
        try {
            const iframeDoc = iframe.contentDocument || (iframe.contentWindow ? iframe.contentWindow.document : null);
            if (iframeDoc) {
                iframeDoc.querySelectorAll('.__pd_selected').forEach(n => n.classList.remove('__pd_selected'));
            }
        } catch (e) {}

        selectedElement = el;
        if (selectedElement) {
            selectedElement.classList.remove('__pd_hover');
            selectedElement.classList.add('__pd_selected');

            populateInspector(selectedElement);
            updateToolbar(selectedElement);
            switchPanelTab('inspector');
            if (inspectorDot) inspectorDot.classList.remove('hidden');
        }
    }

    function deselectElement() {
        if (selectedElement) {
            selectedElement.classList.remove('__pd_selected');
            selectedElement = null;
        }
        updateToolbar(null);
        if (inspectorDot) inspectorDot.classList.add('hidden');
        switchPanelTab('widgets');
    }

    if (closeInspectorBtn) {
        closeInspectorBtn.addEventListener('click', deselectElement);
    }

    // 7. Populate Inspector from Element
    function populateInspector(el) {
        if (!el) return;

        const tagName = el.tagName.toLowerCase();
        inspectTagBadge.textContent = el.tagName;
        inspectClassBadge.textContent = el.className ? '.' + el.className.replace('__pd_selected', '').replace('__pd_hover', '').trim() : '';

        // Breadcrumbs
        let crumbs = [];
        let curr = el;
        while (curr && curr.tagName && curr.tagName !== 'HTML') {
            crumbs.unshift(curr.tagName.toLowerCase());
            curr = curr.parentElement;
        }
        inspectBreadcrumbs.innerHTML = crumbs.map(c => `<span>${c}</span>`).join(' &gt; ');

        // Content
        ctrlContentText.value = el.innerText || el.textContent || '';
        ctrlHtmlTag.value = ['h1','h2','h3','h4','p','span','div','blockquote'].includes(tagName) ? tagName : 'div';

        // Links / Images
        if (tagName === 'a') {
            grpCtrlLink.style.display = '';
            ctrlLinkUrl.value = el.getAttribute('href') || '';
        } else {
            grpCtrlLink.style.display = 'none';
        }

        if (tagName === 'img') {
            grpCtrlImage.style.display = '';
            ctrlImageSrc.value = el.getAttribute('src') || '';
            ctrlImageAlt.value = el.getAttribute('alt') || '';
        } else {
            grpCtrlImage.style.display = 'none';
        }

        // Computed style values
        const win = iframe.contentWindow;
        if (win) {
            const comp = win.getComputedStyle(el);

            // Alignment
            const ta = comp.textAlign || 'left';
            alignBtns.forEach(b => {
                b.classList.toggle('active', b.getAttribute('data-align') === ta);
            });

            // Colors
            ctrlTextColorHex.value = comp.color || '#ffffff';
            ctrlBgColorHex.value = comp.backgroundColor || 'transparent';

            // Font family
            if (ctrlFontFamily) {
                const ff = (comp.fontFamily || '').toLowerCase();
                let matched = '';
                Array.from(ctrlFontFamily.options).forEach(opt => {
                    if (opt.value && ff.includes(opt.value.replace(/['"]/g, '').toLowerCase().split(',')[0].trim())) {
                        matched = opt.value;
                    }
                });
                ctrlFontFamily.value = matched;
            }

            // Font size
            const fs = parseInt(comp.fontSize, 10) || 16;
            if (ctrlFontSizeSlider) ctrlFontSizeSlider.value = fs;
            if (valFontSize) valFontSize.textContent = fs + 'px';

            // Font weight
            if (ctrlFontWeight) ctrlFontWeight.value = comp.fontWeight || '400';

            // Line height
            if (ctrlLineHeightSlider && valLineHeight) {
                let lhVal = 1.5;
                if (comp.lineHeight === 'normal') {
                    lhVal = 1.5;
                } else {
                    const lhPx = parseFloat(comp.lineHeight);
                    const fsPx = parseFloat(comp.fontSize) || 16;
                    if (!isNaN(lhPx) && fsPx > 0) {
                        lhVal = Math.round((lhPx / fsPx) * 10) / 10;
                    }
                }
                lhVal = Math.min(2.5, Math.max(0.8, lhVal));
                ctrlLineHeightSlider.value = lhVal;
                valLineHeight.textContent = lhVal.toFixed(1);
            }

            // Letter spacing
            if (ctrlLetterSpacingSlider && valLetterSpacing) {
                let lsVal = 0;
                if (comp.letterSpacing && comp.letterSpacing !== 'normal') {
                    lsVal = parseFloat(comp.letterSpacing) || 0;
                }
                lsVal = Math.min(10, Math.max(-2, lsVal));
                ctrlLetterSpacingSlider.value = lsVal;
                valLetterSpacing.textContent = lsVal + 'px';
            }

            // Border radius
            const br = parseInt(comp.borderRadius, 10) || 0;
            if (ctrlBorderRadiusSlider) ctrlBorderRadiusSlider.value = br;
            if (valBorderRadius) valBorderRadius.textContent = br + 'px';

            // Box shadow preset
            const bShadow = comp.boxShadow || 'none';
            shadowBtns.forEach(b => {
                const shadowType = b.getAttribute('data-shadow');
                let isMatch = false;
                if (shadowType === 'none' && (bShadow === 'none' || !bShadow)) isMatch = true;
                else if (shadowType === 'glow' && bShadow.includes('199, 245, 71')) isMatch = true;
                else if (shadowType === 'medium' && bShadow.includes('24px')) isMatch = true;
                else if (shadowType === 'subtle' && bShadow.includes('8px')) isMatch = true;
                b.classList.toggle('active', isMatch);
            });

            // Margins (Top, Right, Bottom, Left)
            if (ctrlMarginTop) ctrlMarginTop.value = parseInt(comp.marginTop, 10) || 0;
            if (ctrlMarginRight) ctrlMarginRight.value = parseInt(comp.marginRight, 10) || 0;
            if (ctrlMarginBottom) ctrlMarginBottom.value = parseInt(comp.marginBottom, 10) || 0;
            if (ctrlMarginLeft) ctrlMarginLeft.value = parseInt(comp.marginLeft, 10) || 0;

            // Paddings (Top, Right, Bottom, Left)
            if (ctrlPaddingTop) ctrlPaddingTop.value = parseInt(comp.paddingTop, 10) || 0;
            if (ctrlPaddingRight) ctrlPaddingRight.value = parseInt(comp.paddingRight, 10) || 0;
            if (ctrlPaddingBottom) ctrlPaddingBottom.value = parseInt(comp.paddingBottom, 10) || 0;
            if (ctrlPaddingLeft) ctrlPaddingLeft.value = parseInt(comp.paddingLeft, 10) || 0;

            // Display Mode
            if (ctrlDisplayMode) {
                const disp = comp.display || '';
                ctrlDisplayMode.value = ['block', 'flex', 'inline-block', 'grid', 'none'].includes(disp) ? disp : '';
            }
        }

        // Advanced
        ctrlCssClasses.value = el.className.replace('__pd_selected', '').replace('__pd_hover', '').trim();
        ctrlElementId.value = el.id || '';
        ctrlInlineCss.value = el.getAttribute('style') || '';
    }

    // 8. Reactive Updates (Inspector -> Element & Master HTML)
    function syncElementToMasterHtml() {
        try {
            const iframeDoc = iframe.contentDocument || (iframe.contentWindow ? iframe.contentWindow.document : null);
            if (iframeDoc && iframeDoc.body) {
                // Clone body to strip overlay classes and toolbars before serializing
                const clone = iframeDoc.documentElement.cloneNode(true);

                // 1. Remove editor toolbars, drop zones & drop indicators
                clone.querySelectorAll('#__pd_element_toolbar, .__pd_add_section_zone, #__pd_add_section_bottom, #__pd_drop_indicator').forEach(el => el.remove());

                // 2. Strip editor state classes
                clone.querySelectorAll('.__pd_selected, .__pd_hover').forEach(node => {
                    node.classList.remove('__pd_selected', '__pd_hover');
                    if (node.getAttribute('class') === '') node.removeAttribute('class');
                });

                // 3. Strip contenteditable
                clone.querySelectorAll('[contenteditable]').forEach(node => {
                    node.removeAttribute('contenteditable');
                });

                // 4. Strip injected editor overlays
                const styleOverlay = clone.querySelector('#__pagedock_overlay_css');
                if (styleOverlay) styleOverlay.remove();
                const studioCss = clone.querySelector('#__pagedock_studio_css');
                if (studioCss) studioCss.remove();

                masterHtml = '<!DOCTYPE html>\n' + clone.outerHTML;
            }
        } catch (e) {
            console.error('DOM serialize error:', e);
        }
    }

    function markDirty() {
        if (!isDirty) {
            isDirty = true;
            if (saveStatus) {
                saveStatus.textContent = 'Unsaved changes';
                saveStatus.className = 'save-status unsaved';
            }
        }
    }

    // Content input listener
    if (ctrlContentText) {
        ctrlContentText.addEventListener('input', () => {
            if (selectedElement) {
                selectedElement.textContent = ctrlContentText.value;
                syncElementToMasterHtml();
                markDirty();
            }
        });
    }

    // HTML tag switcher
    if (ctrlHtmlTag) {
        ctrlHtmlTag.addEventListener('change', () => {
            if (!selectedElement) return;
            const newTag = ctrlHtmlTag.value;
            const newEl = selectedElement.ownerDocument.createElement(newTag);
            newEl.innerHTML = selectedElement.innerHTML;
            Array.from(selectedElement.attributes).forEach(attr => {
                newEl.setAttribute(attr.name, attr.value);
            });
            selectedElement.parentNode.replaceChild(newEl, selectedElement);
            selectElement(newEl);
            syncElementToMasterHtml();
            markDirty();
        });
    }

    // Alignment Segmented Control
    alignBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            alignBtns.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            if (selectedElement) {
                selectedElement.style.textAlign = btn.getAttribute('data-align');
                syncElementToMasterHtml();
                markDirty();
            }
        });
    });

    // Font family selector
    if (ctrlFontFamily) {
        ctrlFontFamily.addEventListener('change', () => {
            if (selectedElement) {
                selectedElement.style.fontFamily = ctrlFontFamily.value;
                syncElementToMasterHtml();
                markDirty();
                updateToolbar(selectedElement);
            }
        });
    }

    // Font size slider
    if (ctrlFontSizeSlider) {
        ctrlFontSizeSlider.addEventListener('input', () => {
            const val = ctrlFontSizeSlider.value;
            valFontSize.textContent = val + 'px';
            if (selectedElement) {
                selectedElement.style.fontSize = val + 'px';
                syncElementToMasterHtml();
                markDirty();
                updateToolbar(selectedElement);
            }
        });
    }

    // Font weight selector
    if (ctrlFontWeight) {
        ctrlFontWeight.addEventListener('change', () => {
            if (selectedElement) {
                selectedElement.style.fontWeight = ctrlFontWeight.value;
                syncElementToMasterHtml();
                markDirty();
                updateToolbar(selectedElement);
            }
        });
    }

    // Line height slider
    if (ctrlLineHeightSlider) {
        ctrlLineHeightSlider.addEventListener('input', () => {
            const val = ctrlLineHeightSlider.value;
            if (valLineHeight) valLineHeight.textContent = val;
            if (selectedElement) {
                selectedElement.style.lineHeight = val;
                syncElementToMasterHtml();
                markDirty();
                updateToolbar(selectedElement);
            }
        });
    }

    // Letter spacing slider
    if (ctrlLetterSpacingSlider) {
        ctrlLetterSpacingSlider.addEventListener('input', () => {
            const val = ctrlLetterSpacingSlider.value;
            if (valLetterSpacing) valLetterSpacing.textContent = val + 'px';
            if (selectedElement) {
                selectedElement.style.letterSpacing = val + 'px';
                syncElementToMasterHtml();
                markDirty();
                updateToolbar(selectedElement);
            }
        });
    }

    // Border radius slider
    if (ctrlBorderRadiusSlider) {
        ctrlBorderRadiusSlider.addEventListener('input', () => {
            const val = ctrlBorderRadiusSlider.value;
            valBorderRadius.textContent = val + 'px';
            if (selectedElement) {
                selectedElement.style.borderRadius = val + 'px';
                syncElementToMasterHtml();
                markDirty();
                updateToolbar(selectedElement);
            }
        });
    }

    // Box shadow presets
    const shadowPresets = {
        none: 'none',
        subtle: '0 2px 8px rgba(0, 0, 0, 0.2)',
        medium: '0 8px 24px rgba(0, 0, 0, 0.4)',
        glow: '0 0 20px rgba(199, 245, 71, 0.35)'
    };
    shadowBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            shadowBtns.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            const shadowType = btn.getAttribute('data-shadow');
            if (selectedElement && shadowPresets[shadowType]) {
                selectedElement.style.boxShadow = shadowPresets[shadowType];
                syncElementToMasterHtml();
                markDirty();
                updateToolbar(selectedElement);
            }
        });
    });

    // Color Pickers & Hex Inputs
    if (ctrlTextColorPicker && ctrlTextColorHex) {
        ctrlTextColorPicker.addEventListener('input', () => {
            ctrlTextColorHex.value = ctrlTextColorPicker.value;
            if (selectedElement) {
                selectedElement.style.color = ctrlTextColorPicker.value;
                syncElementToMasterHtml();
                markDirty();
            }
        });
        ctrlTextColorHex.addEventListener('input', () => {
            if (selectedElement) {
                selectedElement.style.color = ctrlTextColorHex.value;
                if (/^#[0-9a-f]{6}$/i.test(ctrlTextColorHex.value)) {
                    ctrlTextColorPicker.value = ctrlTextColorHex.value;
                }
                syncElementToMasterHtml();
                markDirty();
            }
        });
    }

    if (ctrlBgColorPicker && ctrlBgColorHex) {
        ctrlBgColorPicker.addEventListener('input', () => {
            ctrlBgColorHex.value = ctrlBgColorPicker.value;
            if (selectedElement) {
                selectedElement.style.backgroundColor = ctrlBgColorPicker.value;
                syncElementToMasterHtml();
                markDirty();
            }
        });
        ctrlBgColorHex.addEventListener('input', () => {
            if (selectedElement) {
                selectedElement.style.backgroundColor = ctrlBgColorHex.value;
                if (/^#[0-9a-f]{6}$/i.test(ctrlBgColorHex.value)) {
                    ctrlBgColorPicker.value = ctrlBgColorHex.value;
                }
                syncElementToMasterHtml();
                markDirty();
            }
        });
    }

    // Toggle Margin / Padding Link states
    if (toggleMarginLink) {
        toggleMarginLink.addEventListener('click', () => {
            isMarginLinked = !isMarginLinked;
            toggleMarginLink.classList.toggle('active', isMarginLinked);
            toggleMarginLink.textContent = isMarginLinked ? 'Linked' : 'Unlinked';
        });
    }

    if (togglePaddingLink) {
        togglePaddingLink.addEventListener('click', () => {
            isPaddingLinked = !isPaddingLinked;
            togglePaddingLink.classList.toggle('active', isPaddingLinked);
            togglePaddingLink.textContent = isPaddingLinked ? 'Linked' : 'Unlinked';
        });
    }

    // Quad Margin Inputs
    const marginInputs = [ctrlMarginTop, ctrlMarginRight, ctrlMarginBottom, ctrlMarginLeft];
    marginInputs.forEach(input => {
        if (!input) return;
        input.addEventListener('input', () => {
            if (!selectedElement) return;
            const val = parseInt(input.value, 10) || 0;
            if (isMarginLinked) {
                marginInputs.forEach(inp => { if (inp) inp.value = val; });
                selectedElement.style.margin = `${val}px`;
            } else {
                selectedElement.style.marginTop = `${ctrlMarginTop ? ctrlMarginTop.value || 0 : 0}px`;
                selectedElement.style.marginRight = `${ctrlMarginRight ? ctrlMarginRight.value || 0 : 0}px`;
                selectedElement.style.marginBottom = `${ctrlMarginBottom ? ctrlMarginBottom.value || 0 : 0}px`;
                selectedElement.style.marginLeft = `${ctrlMarginLeft ? ctrlMarginLeft.value || 0 : 0}px`;
            }
            syncElementToMasterHtml();
            markDirty();
            updateToolbar(selectedElement);
        });
    });

    // Quad Padding Inputs
    const paddingInputs = [ctrlPaddingTop, ctrlPaddingRight, ctrlPaddingBottom, ctrlPaddingLeft];
    paddingInputs.forEach(input => {
        if (!input) return;
        input.addEventListener('input', () => {
            if (!selectedElement) return;
            const val = parseInt(input.value, 10) || 0;
            if (isPaddingLinked) {
                paddingInputs.forEach(inp => { if (inp) inp.value = val; });
                selectedElement.style.padding = `${val}px`;
            } else {
                selectedElement.style.paddingTop = `${ctrlPaddingTop ? ctrlPaddingTop.value || 0 : 0}px`;
                selectedElement.style.paddingRight = `${ctrlPaddingRight ? ctrlPaddingRight.value || 0 : 0}px`;
                selectedElement.style.paddingBottom = `${ctrlPaddingBottom ? ctrlPaddingBottom.value || 0 : 0}px`;
                selectedElement.style.paddingLeft = `${ctrlPaddingLeft ? ctrlPaddingLeft.value || 0 : 0}px`;
            }
            syncElementToMasterHtml();
            markDirty();
            updateToolbar(selectedElement);
        });
    });

    // Display mode selector
    if (ctrlDisplayMode) {
        ctrlDisplayMode.addEventListener('change', () => {
            if (selectedElement) {
                selectedElement.style.display = ctrlDisplayMode.value;
                syncElementToMasterHtml();
                markDirty();
                updateToolbar(selectedElement);
            }
        });
    }

    // Link URL input
    if (ctrlLinkUrl) {
        ctrlLinkUrl.addEventListener('input', () => {
            if (selectedElement && selectedElement.tagName === 'A') {
                selectedElement.setAttribute('href', ctrlLinkUrl.value);
                syncElementToMasterHtml();
                markDirty();
            }
        });
    }

    // Image Source & Alt inputs
    if (ctrlImageSrc) {
        ctrlImageSrc.addEventListener('input', () => {
            if (selectedElement && selectedElement.tagName === 'IMG') {
                selectedElement.setAttribute('src', ctrlImageSrc.value);
                syncElementToMasterHtml();
                markDirty();
                updateToolbar(selectedElement);
            }
        });
    }
    if (ctrlImageAlt) {
        ctrlImageAlt.addEventListener('input', () => {
            if (selectedElement && selectedElement.tagName === 'IMG') {
                selectedElement.setAttribute('alt', ctrlImageAlt.value);
                syncElementToMasterHtml();
                markDirty();
            }
        });
    }

    // Custom Inline CSS textarea
    if (ctrlInlineCss) {
        ctrlInlineCss.addEventListener('input', () => {
            if (selectedElement) {
                selectedElement.setAttribute('style', ctrlInlineCss.value);
                syncElementToMasterHtml();
                markDirty();
                updateToolbar(selectedElement);
            }
        });
    }

    // Classes & ID
    if (ctrlCssClasses) {
        ctrlCssClasses.addEventListener('input', () => {
            if (selectedElement) {
                selectedElement.className = ctrlCssClasses.value + ' __pd_selected';
                syncElementToMasterHtml();
                markDirty();
            }
        });
    }

    if (ctrlElementId) {
        ctrlElementId.addEventListener('input', () => {
            if (selectedElement) {
                selectedElement.id = ctrlElementId.value;
                syncElementToMasterHtml();
                markDirty();
            }
        });
    }

    // Duplicate & Delete Actions
    if (actDuplicateBtn) {
        actDuplicateBtn.addEventListener('click', () => {
            if (!selectedElement) return;
            const clone = selectedElement.cloneNode(true);
            clone.classList.remove('__pd_selected', '__pd_hover');
            selectedElement.parentNode.insertBefore(clone, selectedElement.nextSibling);
            selectElement(clone);
            syncElementToMasterHtml();
            markDirty();
            showToast('Element duplicated');
        });
    }

    if (actDeleteBtn) {
        actDeleteBtn.addEventListener('click', () => {
            if (!selectedElement) return;
            const parent = selectedElement.parentNode;
            selectedElement.remove();
            deselectElement();
            syncElementToMasterHtml();
            markDirty();
            showToast('Element deleted');
        });
    }

    // 9. Component Blueprints Insertion (1-Click Site Building)
    const blueprints = {
        navbar: `
        <header class="pd-navbar" style="display:flex; justify-content:space-between; align-items:center; padding:16px 32px; background:#101216; border-bottom:1px solid #262a34;">
            <div class="brand" style="font-weight:800; font-size:18px; color:#C7F547;">PageDock Site</div>
            <nav style="display:flex; gap:20px; font-size:14px;">
                <a href="#features" style="color:#f3f4f6;">Features</a>
                <a href="#pricing" style="color:#f3f4f6;">Pricing</a>
                <a href="#contact" style="color:#f3f4f6;">Contact</a>
            </nav>
            <a href="#contact" style="padding:8px 16px; background:#C7F547; color:#000; border-radius:6px; font-weight:700; font-size:13px; text-decoration:none;">Get Started</a>
        </header>
        `,
        hero: `
        <section class="pd-hero" style="padding:80px 24px; text-align:center; background:#090a0c; color:#f3f4f6;">
            <div style="max-width:800px; margin:0 auto;">
                <span style="display:inline-block; padding:4px 12px; background:rgba(199,245,71,0.12); color:#C7F547; border:1px solid rgba(199,245,71,0.25); border-radius:999px; font-size:12px; font-weight:700; margin-bottom:16px;">SOVEREIGN STATIC HOSTING</span>
                <h1 style="font-size:48px; font-weight:800; line-height:1.15; margin-bottom:16px; letter-spacing:-0.02em;">Build Fast. Ship Sovereign.</h1>
                <p style="font-size:18px; color:#949ba8; margin-bottom:32px; line-height:1.6;">Design high-performance static websites without server build steps, database bloat, or complex pipelines.</p>
                <div style="display:flex; justify-content:center; gap:12px;">
                    <a href="#contact" style="padding:12px 24px; background:#C7F547; color:#000; font-weight:700; border-radius:8px; text-decoration:none;">Start Building Free</a>
                    <a href="#features" style="padding:12px 24px; background:#181b21; color:#f3f4f6; border:1px solid #262a34; font-weight:600; border-radius:8px; text-decoration:none;">Explore Features</a>
                </div>
            </div>
        </section>
        `,
        'features-3': `
        <section id="features" class="pd-features" style="padding:60px 24px; background:#101216;">
            <div style="max-width:1100px; margin:0 auto;">
                <div style="text-align:center; margin-bottom:40px;">
                    <h2 style="font-size:32px; font-weight:800; color:#f3f4f6;">Engineered for Velocity</h2>
                    <p style="color:#949ba8; font-size:15px;">Everything you need to launch and scale static applications.</p>
                </div>
                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:20px;">
                    <div style="padding:24px; background:#181b21; border:1px solid #262a34; border-radius:12px;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#C7F547" stroke-width="2" style="margin-bottom:12px;"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                        <h3 style="font-size:18px; font-weight:700; margin-bottom:8px; color:#f3f4f6;">Instant Edge Delivery</h3>
                        <p style="color:#949ba8; font-size:14px; line-height:1.5;">Deploys in under 400ms across global CDN edge nodes with zero cold-starts.</p>
                    </div>
                    <div style="padding:24px; background:#181b21; border:1px solid #262a34; border-radius:12px;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#C7F547" stroke-width="2" style="margin-bottom:12px;"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        <h3 style="font-size:18px; font-weight:700; margin-bottom:8px; color:#f3f4f6;">Zero-Backend Forms</h3>
                        <p style="color:#949ba8; font-size:14px; line-height:1.5;">Built-in form collection endpoint directly routes leads into your dashboard inbox.</p>
                    </div>
                    <div style="padding:24px; background:#181b21; border:1px solid #262a34; border-radius:12px;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#C7F547" stroke-width="2" style="margin-bottom:12px;"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                        <h3 style="font-size:18px; font-weight:700; margin-bottom:8px; color:#f3f4f6;">Cookieless Analytics</h3>
                        <p style="color:#949ba8; font-size:14px; line-height:1.5;">Lightweight, privacy-first traffic monitoring with zero GDPR cookies required.</p>
                    </div>
                </div>
            </div>
        </section>
        `,
        pricing: `
        <section id="pricing" class="pd-pricing" style="padding:60px 24px; background:#090a0c;">
            <div style="max-width:900px; margin:0 auto; text-align:center;">
                <h2 style="font-size:32px; font-weight:800; color:#f3f4f6; margin-bottom:8px;">Transparent Pricing</h2>
                <p style="color:#949ba8; font-size:15px; margin-bottom:40px;">No auto-renewing traps. True sovereign ownership.</p>
                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(260px, 1fr)); gap:20px;">
                    <div style="padding:32px 24px; background:#181b21; border:1px solid #262a34; border-radius:12px; text-align:left;">
                        <h3 style="font-size:18px; font-weight:700; color:#f3f4f6;">Starter</h3>
                        <div style="font-size:36px; font-weight:800; color:#f3f4f6; margin:16px 0;">Free</div>
                        <p style="color:#949ba8; font-size:13px; margin-bottom:20px;">Permanent static hosting for personal projects.</p>
                        <a href="#contact" style="display:block; text-align:center; padding:10px; background:#22262f; color:#fff; border-radius:6px; font-weight:600; text-decoration:none;">Deploy Now</a>
                    </div>
                    <div style="padding:32px 24px; background:#181b21; border:2px solid #C7F547; border-radius:12px; text-align:left; position:relative;">
                        <span style="position:absolute; top:-12px; right:20px; background:#C7F547; color:#000; font-size:10px; font-weight:800; padding:2px 8px; border-radius:999px;">POPULAR</span>
                        <h3 style="font-size:18px; font-weight:700; color:#f3f4f6;">Pro Pass</h3>
                        <div style="font-size:36px; font-weight:800; color:#C7F547; margin:16px 0;">$12 <span style="font-size:14px; font-weight:400; color:#949ba8;">/ 1-mo pass</span></div>
                        <p style="color:#949ba8; font-size:13px; margin-bottom:20px;">Custom domains, analytics export &amp; unlimited revisions.</p>
                        <a href="#contact" style="display:block; text-align:center; padding:10px; background:#C7F547; color:#000; border-radius:6px; font-weight:700; text-decoration:none;">Get Pro Pass</a>
                    </div>
                </div>
            </div>
        </section>
        `,
        testimonials: `
        <section class="pd-testimonials" style="padding:60px 24px; background:#101216; text-align:center;">
            <div style="max-width:700px; margin:0 auto;">
                <p style="font-size:20px; font-style:italic; color:#f3f4f6; line-height:1.6; margin-bottom:20px;">"PageDock completely eliminated the friction of deploying static web landing pages. Our leads doubled within the first week."</p>
                <div style="font-weight:700; color:#C7F547;">Sarah Chen</div>
                <div style="font-size:13px; color:#949ba8;">Founder, Acme Studio</div>
            </div>
        </section>
        `,
        'contact-form': `
        <section id="contact" class="pd-contact" style="padding:60px 24px; background:#090a0c;">
            <div style="max-width:540px; margin:0 auto; background:#181b21; border:1px solid #262a34; padding:32px; border-radius:12px;">
                <h3 style="font-size:24px; font-weight:800; color:#f3f4f6; margin-bottom:8px;">Get in Touch</h3>
                <p style="color:#949ba8; font-size:14px; margin-bottom:24px;">Submissions route automatically to your PageDock leads inbox.</p>
                <form action="/api/forms/${config.slug || 'demo'}" method="POST" style="display:flex; flex-direction:column; gap:16px;">
                    <div>
                        <label style="display:block; font-size:12px; font-weight:600; color:#949ba8; margin-bottom:6px;">Your Name</label>
                        <input type="text" name="name" required style="width:100%; padding:10px; background:#101216; border:1px solid #262a34; border-radius:6px; color:#fff; font-size:14px; outline:none;">
                    </div>
                    <div>
                        <label style="display:block; font-size:12px; font-weight:600; color:#949ba8; margin-bottom:6px;">Email Address</label>
                        <input type="email" name="email" required style="width:100%; padding:10px; background:#101216; border:1px solid #262a34; border-radius:6px; color:#fff; font-size:14px; outline:none;">
                    </div>
                    <div>
                        <label style="display:block; font-size:12px; font-weight:600; color:#949ba8; margin-bottom:6px;">Message</label>
                        <textarea name="message" rows="4" required style="width:100%; padding:10px; background:#101216; border:1px solid #262a34; border-radius:6px; color:#fff; font-size:14px; outline:none;"></textarea>
                    </div>
                    <button type="submit" style="padding:12px; background:#C7F547; color:#000; border:none; border-radius:6px; font-weight:700; cursor:pointer;">Send Message</button>
                </form>
            </div>
        </section>
        `,
        footer: `
        <footer class="pd-footer" style="padding:40px 24px; background:#090a0c; border-top:1px solid #262a34; text-align:center; color:#949ba8; font-size:13px;">
            <div style="max-width:1100px; margin:0 auto; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
                <div style="color:#f3f4f6; font-weight:700;">PageDock Sovereign Deployment</div>
                <div>&copy; ${new Date().getFullYear()} All rights reserved.</div>
            </div>
        </footer>
        `
    };

    // 9b. Centralized Widget Snippets Dictionary
    const widgetSnippets = {
        container: '<div class="pd-container" style="padding:32px; background:#181b21; border:1px solid #262a34; border-radius:10px; margin:16px 0;"><p style="color:#949ba8; margin:0;">Empty container. Drag or insert elements here.</p></div>',
        'columns-2': '<div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin:16px 0;"><div style="padding:20px; background:#181b21; border-radius:8px; color:#f3f4f6;">Column 1</div><div style="padding:20px; background:#181b21; border-radius:8px; color:#f3f4f6;">Column 2</div></div>',
        'columns-3': '<div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:20px; margin:16px 0;"><div style="padding:20px; background:#181b21; border-radius:8px; color:#f3f4f6;">Column 1</div><div style="padding:20px; background:#181b21; border-radius:8px; color:#f3f4f6;">Column 2</div><div style="padding:20px; background:#181b21; border-radius:8px; color:#f3f4f6;">Column 3</div></div>',
        'columns-split': '<div style="display:grid; grid-template-columns:2fr 1fr; gap:20px; margin:16px 0;"><div style="padding:24px; background:#181b21; border-radius:8px; color:#f3f4f6;">Main Column (65%)</div><div style="padding:24px; background:#181b21; border-radius:8px; color:#f3f4f6;">Sidebar (35%)</div></div>',
        heading: '<h2 style="font-size:28px; font-weight:700; color:#f3f4f6; margin-bottom:12px;">Add Your Heading Here</h2>',
        text: '<p style="font-size:16px; color:#949ba8; line-height:1.6; margin-bottom:16px;">This is a paragraph text block. Double-click to edit or adjust typography in the inspector.</p>',
        button: '<a href="#" style="display:inline-block; padding:10px 20px; background:#C7F547; color:#000; border-radius:6px; font-weight:700; text-decoration:none;">Click Here</a>',
        image: '<img src="https://images.unsplash.com/photo-1618005182384-a83a8bd57fbe?w=800&auto=format&fit=crop&q=80" alt="Placeholder" style="width:100%; max-width:600px; border-radius:8px; display:block; margin:16px 0;">',
        video: '<div style="position:relative; padding-bottom:56.25%; height:0; overflow:hidden; border-radius:8px; margin:16px 0;"><iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ" title="Video embed" style="position:absolute; top:0; left:0; width:100%; height:100%; border:0;" allowfullscreen></iframe></div>',
        divider: '<hr style="border:none; border-top:1px solid #262a34; margin:32px 0;">',
        spacer: '<div style="height:48px;"></div>',
        'icon-box': '<div style="padding:24px; background:#181b21; border:1px solid #262a34; border-radius:10px;"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#C7F547" stroke-width="2" style="margin-bottom:8px;"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg><h4 style="font-size:18px; font-weight:700; color:#fff; margin-bottom:6px;">Feature Title</h4><p style="font-size:14px; color:#949ba8; margin:0;">Short description explaining the feature benefit.</p></div>'
    };

    // 9c. Palette Real-Time Search Filter
    const widgetSearchInput = document.getElementById('widget-search-input');
    if (widgetSearchInput) {
        widgetSearchInput.addEventListener('input', () => {
            const query = widgetSearchInput.value.trim().toLowerCase();
            const categories = document.querySelectorAll('.widget-category');

            categories.forEach(cat => {
                let hasVisible = false;
                const items = cat.querySelectorAll('.widget-card, .blueprint-card');
                items.forEach(item => {
                    const label = (item.textContent || '').toLowerCase();
                    const wType = (item.getAttribute('data-widget-type') || '').toLowerCase();
                    const bType = (item.getAttribute('data-blueprint-type') || '').toLowerCase();
                    const matches = !query || label.includes(query) || wType.includes(query) || bType.includes(query);
                    item.style.display = matches ? '' : 'none';
                    if (matches) hasVisible = true;
                });
                cat.style.display = hasVisible ? '' : 'none';
            });
        });
    }

    // 9d. Blueprints Click & Drag Setup
    document.querySelectorAll('.insert-bp-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            const card = e.target.closest('.blueprint-card');
            const type = card.getAttribute('data-blueprint-type');
            if (blueprints[type]) {
                insertHtmlSnippet(blueprints[type]);
                showToast(`Inserted ${type} blueprint`);
            }
        });
    });

    document.querySelectorAll('.blueprint-card').forEach(card => {
        card.setAttribute('draggable', 'true');
        card.addEventListener('dragstart', (e) => {
            const type = card.getAttribute('data-blueprint-type');
            const snippet = blueprints[type] || '';
            e.dataTransfer.setData('text/plain', snippet);
            e.dataTransfer.setData('application/pagedock-snippet', snippet);
            e.dataTransfer.setData('application/pagedock-title', `${type} blueprint`);
            e.dataTransfer.effectAllowed = 'copy';
        });
        card.addEventListener('click', (e) => {
            if (e.target.classList.contains('insert-bp-btn')) return;
            const type = card.getAttribute('data-blueprint-type');
            if (blueprints[type]) {
                insertHtmlSnippet(blueprints[type]);
                showToast(`Inserted ${type} blueprint`);
            }
        });
    });

    // 9e. Primitive Widgets Click & Drag Setup
    document.querySelectorAll('.widget-card').forEach(card => {
        card.setAttribute('draggable', 'true');
        card.addEventListener('dragstart', (e) => {
            const type = card.getAttribute('data-widget-type');
            const snippet = widgetSnippets[type] || '';
            e.dataTransfer.setData('text/plain', snippet);
            e.dataTransfer.setData('application/pagedock-snippet', snippet);
            e.dataTransfer.setData('application/pagedock-title', type);
            e.dataTransfer.effectAllowed = 'copy';
        });

        card.addEventListener('click', () => {
            const type = card.getAttribute('data-widget-type');
            const snippet = widgetSnippets[type];
            if (snippet) {
                insertHtmlSnippet(snippet);
                showToast(`Inserted ${type}`);
            }
        });
    });

    // 9f. Universal Snippet Insertion Engine
    function insertHtmlSnippet(snippet) {
        pushHistoryState();

        if (!masterHtml) {
            masterHtml = `<!DOCTYPE html>\n<html>\n<head>\n<title>${config.slug}</title>\n</head>\n<body>\n${snippet}\n</body>\n</html>`;
        } else {
            const iframeDoc = iframe.contentDocument || (iframe.contentWindow ? iframe.contentWindow.document : null);
            if (iframeDoc && iframeDoc.body) {
                const range = iframeDoc.createRange();
                const fragment = range.createContextualFragment(snippet);
                const firstInserted = fragment.firstElementChild;

                if (insertionTarget && insertionTarget.parentNode) {
                    if (insertionPosition === 'before') {
                        insertionTarget.parentNode.insertBefore(fragment, insertionTarget);
                    } else {
                        insertionTarget.parentNode.insertBefore(fragment, insertionTarget.nextSibling);
                    }
                    insertionTarget = null;
                    insertionPosition = 'after';
                } else if (selectedElement && selectedElement.parentNode) {
                    selectedElement.parentNode.insertBefore(fragment, selectedElement.nextSibling);
                } else {
                    const bottomZone = iframeDoc.getElementById('__pd_add_section_bottom');
                    if (bottomZone) {
                        iframeDoc.body.insertBefore(fragment, bottomZone);
                    } else {
                        iframeDoc.body.appendChild(fragment);
                    }
                }
                syncElementToMasterHtml();
                if (firstInserted) {
                    selectElement(firstInserted);
                }
                ensureAddSectionZone(iframeDoc);
            } else {
                if (masterHtml.includes('</body>')) {
                    masterHtml = masterHtml.replace('</body>', `\n${snippet}\n</body>`);
                } else {
                    masterHtml += '\n' + snippet;
                }
            }
        }
        markDirty();
        triggerStudioLivePreview(true);
    }

    // 10. Structure Navigator Tree (Hierarchical Depth & Filter)
    const navigatorSearchInput = document.getElementById('navigator-search-input');
    if (navigatorSearchInput) {
        navigatorSearchInput.addEventListener('input', () => {
            const q = navigatorSearchInput.value.trim().toLowerCase();
            document.querySelectorAll('#navigator-tree-container .nav-node').forEach(node => {
                const txt = (node.textContent || '').toLowerCase();
                node.style.display = (!q || txt.includes(q)) ? '' : 'none';
            });
        });
    }

    if (toggleNavigatorBtn) {
        toggleNavigatorBtn.addEventListener('click', () => {
            navigatorModal.classList.remove('hidden');
            if (navigatorSearchInput) {
                navigatorSearchInput.value = '';
            }
            renderNavigatorTree();
        });
    }

    if (closeNavigatorBtn) {
        closeNavigatorBtn.addEventListener('click', () => {
            navigatorModal.classList.add('hidden');
        });
    }

    function renderNavigatorTree() {
        if (!navigatorTree) return;
        try {
            const iframeDoc = iframe.contentDocument || (iframe.contentWindow ? iframe.contentWindow.document : null);
            if (!iframeDoc || !iframeDoc.body) {
                navigatorTree.innerHTML = '<div style="color:var(--fg-muted);">No DOM content loaded yet.</div>';
                return;
            }

            const nodes = [];
            function walk(el, depth = 0) {
                if (!el || el.nodeType !== 1) return;
                if (el.id === '__pd_element_toolbar' || el.classList.contains('__pd_add_section_zone') || el.id === '__pd_drop_indicator') return;

                const tag = el.tagName.toLowerCase();
                const relevantTags = ['header', 'nav', 'section', 'article', 'aside', 'footer', 'main', 'div', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'span', 'a', 'button', 'img', 'form', 'ul', 'li', 'table'];
                if (relevantTags.includes(tag)) {
                    nodes.push({ el, depth });
                }
                Array.from(el.children).forEach(child => walk(child, depth + 1));
            }

            walk(iframeDoc.body, 0);

            if (nodes.length === 0) {
                navigatorTree.innerHTML = '<div style="color:var(--fg-muted);">Empty document structure.</div>';
                return;
            }

            let html = '';
            nodes.forEach((item, idx) => {
                const el = item.el;
                const tag = el.tagName.toLowerCase();
                const cls = el.className.replace('__pd_selected', '').replace('__pd_hover', '').trim();
                const clsStr = cls ? `.${cls.split(' ')[0]}` : '';
                const idStr = el.id ? `#${el.id}` : '';
                const isActive = el === selectedElement ? 'active' : '';
                const padLeft = Math.min(item.depth * 14 + 8, 120);

                html += `
                <div class="nav-node ${isActive}" data-nav-idx="${idx}" style="padding-left:${padLeft}px;">
                    <span>&lt;${tag}${idStr}${clsStr}&gt;</span>
                    <span style="font-size:10px; color:var(--fg-muted); max-width:140px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${(el.innerText || el.textContent || '').trim().substring(0, 24)}</span>
                </div>
                `;
            });
            navigatorTree.innerHTML = html;

            navigatorTree.querySelectorAll('.nav-node').forEach((node, idx) => {
                node.addEventListener('click', () => {
                    const targetEl = nodes[idx] ? nodes[idx].el : null;
                    if (targetEl) {
                        selectElement(targetEl);
                        targetEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        navigatorModal.classList.add('hidden');
                    }
                });
            });

        } catch (e) {
            navigatorTree.innerHTML = '<div style="color:var(--danger);">Could not read DOM tree.</div>';
        }
    }

    // 11. Save Code & Publish
    async function saveStudioCode() {
        if (!config.canManage) {
            showToast('Read-only access: Cannot save changes');
            return;
        }

        syncElementToMasterHtml();
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<span>Saving...</span>';

        try {
            const res = await fetch(config.saveUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    html: masterHtml,
                    file: config.activeFile || 'index.html',
                    summary: 'Visual Studio update'
                })
            });

            const data = await res.json();
            if (data.success) {
                isDirty = false;
                if (saveStatus) {
                    saveStatus.textContent = data.version ? `Saved (v${data.version})` : 'Saved';
                    saveStatus.className = 'save-status saved';
                }
                showToast(data.version ? `Published revision v${data.version} successfully!` : 'Published successfully!');
            } else {
                showToast(data.error || 'Failed to save changes');
            }
        } catch (err) {
            showToast('Network error while saving');
        } finally {
            saveBtn.disabled = false;
            saveBtn.innerHTML = '<span>Save &amp; Publish</span>';
        }
    }

    if (saveBtn) {
        saveBtn.addEventListener('click', saveStudioCode);
    }

    // 12. Multi-Page Switcher & Code Editor Bridge
    const studioPageSwitcher = document.getElementById('studio-page-switcher');
    if (studioPageSwitcher) {
        studioPageSwitcher.addEventListener('change', () => {
            const nextFile = studioPageSwitcher.value;
            if (isDirty) {
                if (confirm('You have unsaved changes in Visual Studio. Discard and switch page?')) {
                    window.location.href = `?file=${encodeURIComponent(nextFile)}`;
                } else {
                    studioPageSwitcher.value = config.activeFile || 'index.html';
                }
            } else {
                window.location.href = `?file=${encodeURIComponent(nextFile)}`;
            }
        });
    }

    const gotoEditorBtn = document.getElementById('goto-editor-btn');
    if (gotoEditorBtn) {
        gotoEditorBtn.addEventListener('click', (e) => {
            if (isDirty) {
                if (!confirm('You have unsaved changes in Visual Studio. Discard and switch to the code editor?')) {
                    e.preventDefault();
                }
            }
        });
    }

    // Warn before navigating away if unsaved
    window.addEventListener('beforeunload', (e) => {
        if (isDirty) {
            e.preventDefault();
            e.returnValue = '';
        }
    });

    // Keyboard Shortcuts (Ctrl+S / Cmd+S, Undo/Redo, Escape)
    window.addEventListener('keydown', (e) => {
        if ((e.ctrlKey || e.metaKey) && e.key === 's') {
            e.preventDefault();
            saveStudioCode();
        }
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'z' && !e.shiftKey) {
            e.preventDefault();
            undo();
        }
        if ((e.ctrlKey || e.metaKey) && (e.key.toLowerCase() === 'y' || (e.key.toLowerCase() === 'z' && e.shiftKey))) {
            e.preventDefault();
            redo();
        }
        if (e.key === 'Escape') {
            deselectElement();
            if (navigatorModal) navigatorModal.classList.add('hidden');
        }
    });

    // Helper: Toast
    function showToast(msg) {
        if (!toast) return;
        toast.textContent = msg;
        toast.className = 'toast show';
        setTimeout(() => {
            toast.className = 'toast hidden';
        }, 3000);
    }

    // Initial Trigger
    triggerStudioLivePreview(true);
});

