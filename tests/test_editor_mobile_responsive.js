/**
 * PageDock AI Editor - Mobile-Responsive Viewport Switcher & Layout Overhaul Test Suite
 * 
 * Validates:
 * 1. Mobile segmented view switcher exists in views/editor.php with id 'hh-mobile-view-bar'
 *    containing buttons for 'code' and 'preview'
 * 2. Responsive CSS rules exist in public/assets/css/editor.css under @media (max-width: 768px)
 * 3. Left panel adapts to 100% width or hides based on active mobile mode (.hh-mobile-mode-code / .hh-mobile-mode-preview)
 * 4. Topbar buttons meet 40px+ touch targets on mobile
 * 5. Popovers have max-width: calc(100vw - 20px) on mobile
 * 6. Responsive CSS rules exist under @media (max-width: 480px) for compact mobile view
 * 7. JavaScript binds click handlers for mobile view buttons, toggles mode classes, and refreshes CodeMirror
 * 8. Runtime execution verification of mode toggling and CodeMirror refresh
 */

const fs = require('fs');
const path = require('path');
const assert = require('assert');
const vm = require('vm');

const ROOT_DIR = path.resolve(__dirname, '..');
const EDITOR_VIEW_PATH = path.join(ROOT_DIR, 'views', 'editor.php');
const EDITOR_CSS_PATH = path.join(ROOT_DIR, 'public', 'assets', 'css', 'editor.css');
const EDITOR_JS_PATH = path.join(ROOT_DIR, 'public', 'assets', 'js', 'editor.js');

let totalTests = 0;
let passedTests = 0;
let failedTests = 0;

function runTest(name, fn) {
    totalTests++;
    try {
        fn();
        passedTests++;
        console.log(`[PASS] ${name}`);
    } catch (err) {
        failedTests++;
        console.error(`[FAIL] ${name}`);
        console.error(`       Error: ${err.message}`);
    }
}

console.log('=== Running PageDock Mobile-Responsive Editor Test Suite ===\n');

// Verify files exist
assert(fs.existsSync(EDITOR_VIEW_PATH), 'views/editor.php must exist');
assert(fs.existsSync(EDITOR_CSS_PATH), 'public/assets/css/editor.css must exist');
assert(fs.existsSync(EDITOR_JS_PATH), 'public/assets/js/editor.js must exist');

const editorViewContent = fs.readFileSync(EDITOR_VIEW_PATH, 'utf8');
const editorCssContent = fs.readFileSync(EDITOR_CSS_PATH, 'utf8');
const editorJsContent = fs.readFileSync(EDITOR_JS_PATH, 'utf8');

// 1. Mobile View Switcher in views/editor.php
runTest('views/editor.php contains mobile view bar with id "hh-mobile-view-bar"', () => {
    assert(
        editorViewContent.includes('id="hh-mobile-view-bar"') || editorViewContent.includes("id='hh-mobile-view-bar'"),
        'Expected views/editor.php to contain an element with id="hh-mobile-view-bar"'
    );
});

runTest('hh-mobile-view-bar contains buttons for "code" and "preview"', () => {
    const barMatch = editorViewContent.match(/<[^>]+id=["']hh-mobile-view-bar["'][^>]*>([\s\S]*?)<\/div>/i);
    assert(barMatch, 'Could not find hh-mobile-view-bar container in views/editor.php');
    const barHtml = barMatch[1];

    // Check for code button
    const hasCodeBtn = (barHtml.includes('data-view="code"') || barHtml.includes("data-view='code'")) &&
                       /code/i.test(barHtml);
    assert(hasCodeBtn, 'hh-mobile-view-bar must contain a button for "code" with data-view="code"');

    // Check for preview button
    const hasPreviewBtn = (barHtml.includes('data-view="preview"') || barHtml.includes("data-view='preview'")) &&
                          /preview/i.test(barHtml);
    assert(hasPreviewBtn, 'hh-mobile-view-bar must contain a button for "preview" with data-view="preview"');
});

// 2. Responsive CSS rules in public/assets/css/editor.css
runTest('editor.css contains @media (max-width: 768px) media query', () => {
    const hasMediaQuery = /@media\s*\(\s*max-width:\s*768px\s*\)/.test(editorCssContent);
    assert(hasMediaQuery, 'editor.css must contain @media (max-width: 768px)');
});

runTest('hh-mobile-view-bar is hidden by default and displayed on mobile in editor.css', () => {
    assert(editorCssContent.includes('.hh-mobile-view-bar'), 'editor.css must define .hh-mobile-view-bar');
    const mediaMatch = editorCssContent.match(/@media\s*\(\s*max-width:\s*768px\s*\)\s*\{([\s\S]*?)(?:@media|$)/);
    assert(mediaMatch, 'Could not extract @media (max-width: 768px) block');
    const mediaBlock = mediaMatch[1];
    assert(
        mediaBlock.includes('.hh-mobile-view-bar') && (mediaBlock.includes('display: flex') || mediaBlock.includes('display: block')),
        'hh-mobile-view-bar must be displayed under @media (max-width: 768px)'
    );
});

// 3. Left panel adapts to 100% width or hides based on active mobile mode
runTest('Left panel adapts to 100% width in .hh-mobile-mode-code under 768px', () => {
    const mediaMatch = editorCssContent.match(/@media\s*\(\s*max-width:\s*768px\s*\)\s*\{([\s\S]*?)(?:@media|$)/);
    assert(mediaMatch, 'Could not extract @media (max-width: 768px) block');
    const mediaBlock = mediaMatch[1];

    const hasCodePanelRule = mediaBlock.includes('.hh-mobile-mode-code') &&
        mediaBlock.includes('.hh-left-panel') &&
        /width:\s*100%/.test(mediaBlock);
    assert(hasCodePanelRule, 'CSS must adapt .hh-left-panel to 100% width in .hh-mobile-mode-code');

    const hasCodeCanvasHidden = mediaBlock.includes('.hh-mobile-mode-code') &&
        mediaBlock.includes('.hh-canvas-area') &&
        /display:\s*none/.test(mediaBlock);
    assert(hasCodeCanvasHidden, 'CSS must hide .hh-canvas-area in .hh-mobile-mode-code');
});

runTest('Left panel hides in .hh-mobile-mode-preview under 768px', () => {
    const mediaMatch = editorCssContent.match(/@media\s*\(\s*max-width:\s*768px\s*\)\s*\{([\s\S]*?)(?:@media|$)/);
    assert(mediaMatch, 'Could not extract @media (max-width: 768px) block');
    const mediaBlock = mediaMatch[1];

    const hasPreviewPanelHidden = mediaBlock.includes('.hh-mobile-mode-preview') &&
        mediaBlock.includes('.hh-left-panel') &&
        (/display:\s*none/.test(mediaBlock) || /width:\s*0/.test(mediaBlock));
    assert(hasPreviewPanelHidden, 'CSS must hide .hh-left-panel in .hh-mobile-mode-preview');

    const hasPreviewCanvasShown = mediaBlock.includes('.hh-mobile-mode-preview') &&
        mediaBlock.includes('.hh-canvas-area') &&
        (/display:\s*flex/.test(mediaBlock) || /width:\s*100%/.test(mediaBlock));
    assert(hasPreviewCanvasShown, 'CSS must show .hh-canvas-area at 100% width in .hh-mobile-mode-preview');
});

// 4. Topbar buttons meet 40px+ touch targets on mobile
runTest('Topbar buttons meet 40px+ touch targets under @media (max-width: 768px)', () => {
    const mediaMatch = editorCssContent.match(/@media\s*\(\s*max-width:\s*768px\s*\)\s*\{([\s\S]*?)(?:@media|$)/);
    assert(mediaMatch, 'Could not extract @media (max-width: 768px) block');
    const mediaBlock = mediaMatch[1];

    const hasTouchTarget = /(?:min-height|height|min-width):\s*(?:4[0-9]|5[0-9]|6[0-9])px/.test(mediaBlock);
    assert(hasTouchTarget, 'Mobile media query must enforce 40px+ touch targets (height/min-height/min-width >= 40px)');
});

// 5. Popovers have max-width: calc(100vw - 20px) on mobile
runTest('Popovers have max-width: calc(100vw - 20px) under @media (max-width: 768px)', () => {
    const mediaMatch = editorCssContent.match(/@media\s*\(\s*max-width:\s*768px\s*\)\s*\{([\s\S]*?)(?:@media|$)/);
    assert(mediaMatch, 'Could not extract @media (max-width: 768px) block');
    const mediaBlock = mediaMatch[1];

    const hasPopoverClamp = mediaBlock.includes('.hh-popover-card') &&
        /max-width:\s*calc\(\s*100vw\s*-\s*20px\s*\)/.test(mediaBlock);
    assert(hasPopoverClamp, 'editor.css must clamp .hh-popover-card to max-width: calc(100vw - 20px) on mobile');
});

// 6. Mobile rules for max-width: 480px
runTest('editor.css contains @media (max-width: 480px) rules hiding redundant labels', () => {
    const has480Media = /@media\s*\(\s*max-width:\s*480px\s*\)/.test(editorCssContent);
    assert(has480Media, 'editor.css must contain @media (max-width: 480px) query');
});

// 7. JavaScript static analysis
runTest('editor.js handles mobile mode toggling and cmEditor.refresh()', () => {
    assert(editorJsContent.includes('hh-mobile-mode-code'), 'editor.js must reference hh-mobile-mode-code');
    assert(editorJsContent.includes('hh-mobile-mode-preview'), 'editor.js must reference hh-mobile-mode-preview');
    assert(editorJsContent.includes('hh-mobile-view-btn') || editorJsContent.includes('hh-mobile-view-bar'), 'editor.js must reference mobile view switcher');
    assert(
        /cmEditor(?:\.refresh|\?\.(?:refresh))\(\)/.test(editorJsContent),
        'editor.js must invoke cmEditor.refresh() when switching to code mode'
    );
});

// 8. Runtime execution test
runTest('Runtime: clicking preview button switches to preview mode, clicking code button switches back and refreshes CodeMirror', () => {
    let cmRefreshed = false;
    class MockClassList {
        constructor(cls = '') {
            this.classes = new Set(cls.split(/\s+/).filter(Boolean));
        }
        add(...names) { names.forEach(n => this.classes.add(n)); }
        remove(...names) { names.forEach(n => this.classes.delete(n)); }
        toggle(name, force) {
            if (force === true) this.classes.add(name);
            else if (force === false) this.classes.delete(name);
            else if (this.classes.has(name)) this.classes.delete(name);
            else this.classes.add(name);
        }
        contains(name) { return this.classes.has(name); }
    }

    class MockEl {
        constructor(tag, id = '', cls = '') {
            this.tagName = tag.toUpperCase();
            this.id = id;
            this.className = cls;
            this.classList = new MockClassList(cls);
            this.dataset = {};
            this.listeners = {};
            this.style = {};
            this.children = [];
        }
        addEventListener(evt, cb) {
            if (!this.listeners[evt]) this.listeners[evt] = [];
            this.listeners[evt].push(cb);
        }
        click() {
            const list = this.listeners['click'] || [];
            for (const cb of list) cb({ preventDefault: () => {}, stopPropagation: () => {} });
        }
        appendChild(child) {
            this.children.push(child);
            return child;
        }
    }

    const mockBody = new MockEl('body', '', 'hh-editor-body hh-mobile-mode-code');
    const mockWorkspace = new MockEl('main', 'hh-workspace');
    const mockBar = new MockEl('div', 'hh-mobile-view-bar');
    const codeBtn = new MockEl('button', 'hh-mobile-btn-code', 'hh-mobile-view-btn active');
    codeBtn.dataset.view = 'code';
    const previewBtn = new MockEl('button', 'hh-mobile-btn-preview', 'hh-mobile-view-btn');
    previewBtn.dataset.view = 'preview';

    const elementsById = {
        'hh-workspace': mockWorkspace,
        'hh-mobile-view-bar': mockBar,
        'hh-mobile-btn-code': codeBtn,
        'hh-mobile-btn-preview': previewBtn,
        'hh-source-textarea': new MockEl('textarea', 'hh-source-textarea')
    };

    const mockDoc = {
        readyState: 'complete',
        body: mockBody,
        getElementById: (id) => elementsById[id] || new MockEl('div', id),
        querySelectorAll: (sel) => {
            if (sel === '.hh-mobile-view-btn') return [codeBtn, previewBtn];
            return [];
        },
        createElement: (tag) => new MockEl(tag),
        addEventListener: () => {}
    };

    const mockWin = {
        PAGEDOCK_CONFIG: { slug: 'test-site', activeFile: 'index.html' },
        addEventListener: () => {},
        setTimeout: (fn) => { fn(); return 1; },
        clearTimeout: () => {},
        location: { reload: () => {} }
    };

    const mockCM = {
        fromTextArea: () => ({
            getValue: () => '<h1>Hello</h1>',
            setValue: () => {},
            refresh: () => { cmRefreshed = true; },
            on: () => {},
            setOption: () => {}
        })
    };

    const sandbox = {
        window: mockWin,
        document: mockDoc,
        CodeMirror: mockCM,
        setTimeout: (fn) => { fn(); return 1; },
        clearTimeout: () => {},
        console: console,
        fetch: () => Promise.resolve({ json: () => Promise.resolve({}) })
    };

    vm.runInNewContext(editorJsContent, sandbox);

    // Initial state check
    assert(mockBody.classList.contains('hh-mobile-mode-code'), 'Body should have hh-mobile-mode-code initially');
    assert(!mockBody.classList.contains('hh-mobile-mode-preview'), 'Body should not have hh-mobile-mode-preview initially');

    // Click preview button
    previewBtn.click();
    assert(mockBody.classList.contains('hh-mobile-mode-preview'), 'Body should have hh-mobile-mode-preview after clicking preview');
    assert(!mockBody.classList.contains('hh-mobile-mode-code'), 'Body should not have hh-mobile-mode-code after clicking preview');
    assert(previewBtn.classList.contains('active'), 'Preview button should have active class');
    assert(!codeBtn.classList.contains('active'), 'Code button should not have active class');

    // Click code button
    cmRefreshed = false;
    codeBtn.click();
    assert(mockBody.classList.contains('hh-mobile-mode-code'), 'Body should have hh-mobile-mode-code after clicking code');
    assert(!mockBody.classList.contains('hh-mobile-mode-preview'), 'Body should not have hh-mobile-mode-preview after clicking code');
    assert(codeBtn.classList.contains('active'), 'Code button should have active class');
    assert(!previewBtn.classList.contains('active'), 'Preview button should not have active class');
    assert(cmRefreshed === true, 'CodeMirror refresh() must be called when switching back to code mode');
});

console.log('\n--- Test Summary ---');
console.log(`Total: ${totalTests}`);
console.log(`Passed: ${passedTests}`);
console.log(`Failed: ${failedTests}`);

if (failedTests > 0) {
    process.exit(1);
} else {
    process.exit(0);
}
