// PageDock In-Browser Split Editor Logic

document.addEventListener('DOMContentLoaded', () => {
    const config = window.PAGEDOCK_CONFIG || {};
    const codeEditor = document.getElementById('code-editor');
    const previewIframe = document.getElementById('preview-iframe');
    const saveBtn = document.getElementById('save-code-btn');
    const saveStatus = document.getElementById('save-status');
    const refreshBtn = document.getElementById('refresh-preview-btn');
    const viewportBtns = document.querySelectorAll('.viewport-btn');
    const toast = document.getElementById('toast');

    let isDirty = false;

    // 1. Mark dirty on code edit
    if (codeEditor) {
        codeEditor.addEventListener('input', () => {
            if (!isDirty) {
                isDirty = true;
                if (saveStatus) {
                    saveStatus.textContent = 'Unsaved changes';
                    saveStatus.className = 'save-status unsaved';
                }
            }
        });

        // Tab key support in textarea
        codeEditor.addEventListener('keydown', (e) => {
            if (e.key === 'Tab') {
                e.preventDefault();
                const start = codeEditor.selectionStart;
                const end = codeEditor.selectionEnd;
                codeEditor.value = codeEditor.value.substring(0, start) + '  ' + codeEditor.value.substring(end);
                codeEditor.selectionStart = codeEditor.selectionEnd = start + 2;
            }

            // Ctrl+S or Cmd+S shortcut to save
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                saveCode();
            }
        });
    }

    // 2. Save Code function
    async function saveCode() {
        if (!config.canManage) {
            showToast('Read-only view. You do not have permission to edit this site.');
            return;
        }

        const html = codeEditor.value;
        if (saveBtn) saveBtn.textContent = 'Saving...';

        try {
            const res = await fetch(config.saveUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ html })
            });
            const data = await res.json();

            if (data.success) {
                isDirty = false;
                if (saveStatus) {
                    saveStatus.textContent = 'Saved';
                    saveStatus.className = 'save-status saved';
                }
                reloadPreview();
                showToast('Published changes successfully!');
            } else {
                showToast(data.error || 'Save failed.');
            }
        } catch (err) {
            showToast('Network error while saving.');
        } finally {
            if (saveBtn) saveBtn.textContent = 'Save & Publish (Ctrl+S)';
        }
    }

    if (saveBtn) {
        saveBtn.addEventListener('click', saveCode);
    }

    // 3. Reload preview iframe
    function reloadPreview() {
        if (previewIframe) {
            const currentSrc = previewIframe.src.split('?')[0];
            previewIframe.src = currentSrc + '?t=' + Date.now();
        }
    }

    if (refreshBtn) {
        refreshBtn.addEventListener('click', reloadPreview);
    }

    // 4. Viewport Switcher (Desktop, Tablet, Mobile)
    viewportBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            viewportBtns.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            const width = btn.getAttribute('data-width');
            if (previewIframe) {
                previewIframe.style.width = width;
            }
        });
    });

    // 5. Draggable Pane Resizer
    const resizer = document.getElementById('pane-resizer');
    const codePane = document.querySelector('.code-pane');
    const previewPane = document.querySelector('.preview-pane');

    if (resizer && codePane && previewPane) {
        let isResizing = false;

        resizer.addEventListener('mousedown', (e) => {
            isResizing = true;
            resizer.classList.add('resizing');
            document.body.style.cursor = 'col-resize';
            document.body.style.userSelect = 'none';
        });

        window.addEventListener('mousemove', (e) => {
            if (!isResizing) return;
            const containerWidth = document.querySelector('.editor-workspace').offsetWidth;
            const newCodeWidth = (e.clientX / containerWidth) * 100;

            if (newCodeWidth > 20 && newCodeWidth < 80) {
                codePane.style.width = newCodeWidth + '%';
                previewPane.style.width = (100 - newCodeWidth) + '%';
            }
        });

        window.addEventListener('mouseup', () => {
            if (isResizing) {
                isResizing = false;
                resizer.classList.remove('resizing');
                document.body.style.cursor = '';
                document.body.style.userSelect = '';
            }
        });
    }

    // 6. Forms Modal
    const formsBtn = document.getElementById('forms-modal-btn');
    const formsModal = document.getElementById('forms-modal');
    const closeModalBtn = document.getElementById('close-modal-btn');

    if (formsBtn && formsModal) {
        formsBtn.addEventListener('click', () => formsModal.classList.remove('hidden'));
        if (closeModalBtn) {
            closeModalBtn.addEventListener('click', () => formsModal.classList.add('hidden'));
        }
        formsModal.addEventListener('click', (e) => {
            if (e.target === formsModal) formsModal.classList.add('hidden');
        });
    }

    // 7. Toast helper
    function showToast(msg) {
        if (!toast) return;
        toast.textContent = msg;
        toast.classList.remove('hidden');
        setTimeout(() => toast.classList.add('hidden'), 3000);
    }
});
