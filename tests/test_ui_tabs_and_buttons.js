/**
 * Automated UI Tab & Button Interactivity Test Suite
 * Validates that all client UI controls on PageDock homepage function correctly:
 * - Deployment tabs (Upload, Paste HTML, CLI & Agents, 1-Click Demo)
 * - Interactive Split Showcase tabs (index.html, style.css, _redirects)
 * - Viewport toggles (1440px desktop, 768px tablet, 375px mobile)
 * - Preset starter chips (SaaS Waitlist, Developer Bio, Mini Docs)
 * - CLI copy button
 */

const fs = require('fs');
const path = require('path');
const vm = require('vm');

const results = [];
function assertTest(name, condition, details = '') {
    if (condition) {
        results.push({ name, status: 'PASS' });
        console.log(`PASS: ${name}`);
    } else {
        results.push({ name, status: 'FAIL', details });
        console.log(`FAIL: ${name} (${details})`);
    }
}

console.log('=== Running UI Tabs, Presets, and Interactivity Test Suite ===\n');

// 1. Syntax Check of app.js
console.log('--- 1. Script Syntax Validation ---');
const appJsPath = path.join(__dirname, '../public/assets/js/app.js');
const appJsCode = fs.readFileSync(appJsPath, 'utf8');

let syntaxError = null;
try {
    new vm.Script(appJsCode, { filename: 'app.js' });
} catch (e) {
    syntaxError = e.message;
}
assertTest('public/assets/js/app.js compiles with zero syntax errors', syntaxError === null, syntaxError);

// 2. Mock DOM Environment to execute app.js
console.log('\n--- 2. Mock DOM Execution & Event Binding ---');

class MockClassList {
    constructor() { this.classes = new Set(); }
    add(...names) { names.forEach(n => this.classes.add(n)); }
    remove(...names) { names.forEach(n => this.classes.delete(n)); }
    contains(name) { return this.classes.has(name); }
    toggle(name) { if (this.contains(name)) this.remove(name); else this.add(name); }
}

class MockElement {
    constructor(tagName, id = '', className = '') {
        this.tagName = tagName.toUpperCase();
        this.id = id;
        this.className = className;
        this.classList = new MockClassList();
        if (className) className.split(/\s+/).filter(Boolean).forEach(c => this.classList.add(c));
        this.attributes = {};
        this.listeners = {};
        this.children = [];
        this.style = {};
        this.innerHTML = '';
        this.value = '';
        this.textContent = '';
    }

    setAttribute(key, val) { this.attributes[key] = String(val); }
    getAttribute(key) { return this.attributes[key] !== undefined ? this.attributes[key] : null; }
    removeAttribute(key) { delete this.attributes[key]; }

    addEventListener(event, callback) {
        if (!this.listeners[event]) this.listeners[event] = [];
        this.listeners[event].push(callback);
    }

    dispatchEvent(event) {
        const type = typeof event === 'string' ? event : event.type;
        const list = this.listeners[type] || [];
        for (const cb of list) {
            cb(event);
        }
    }

    querySelector(selector) {
        return this.querySelectorAll(selector)[0] || null;
    }

    querySelectorAll(selector) {
        const res = [];
        const match = (el) => {
            if (selector.startsWith('.')) {
                if (el.classList.contains(selector.slice(1))) res.push(el);
            } else if (selector.startsWith('#')) {
                if (el.id === selector.slice(1)) res.push(el);
            } else if (selector.startsWith('[')) {
                const attrMatch = selector.match(/\[([a-zA-Z0-9_-]+)(?:="([^"]*)")?\]/);
                if (attrMatch) {
                    const attr = attrMatch[1];
                    const val = attrMatch[2];
                    if (val !== undefined) {
                        if (el.getAttribute(attr) === val) res.push(el);
                    } else if (el.getAttribute(attr) !== null) {
                        res.push(el);
                    }
                }
            } else if (el.tagName.toLowerCase() === selector.toLowerCase()) {
                res.push(el);
            }
            for (const child of el.children) match(child);
        };
        for (const child of this.children) match(child);
        return res;
    }

    closest(selector) {
        let curr = this;
        while (curr) {
            if (selector.startsWith('.') && curr.classList.contains(selector.slice(1))) return curr;
            if (selector.startsWith('#') && curr.id === selector.slice(1)) return curr;
            curr = curr.parentNode;
        }
        return null;
    }

    appendChild(child) {
        child.parentNode = this;
        this.children.push(child);
        return child;
    }
}

// Build Mock DOM for home page elements
const elements = {};
function createElement(tag, id, className, attrs = {}) {
    const el = new MockElement(tag, id, className);
    for (const [k, v] of Object.entries(attrs)) el.setAttribute(k, v);
    if (id) elements[id] = el;
    return el;
}

const doc = new MockElement('document');
const body = createElement('body', 'body');
doc.appendChild(body);

// Deployment tabs
const tabUploadBtn = createElement('button', 'btn-upload', 'tab-btn active', { 'data-tab': 'upload-tab' });
const tabPasteBtn = createElement('button', 'btn-paste', 'tab-btn', { 'data-tab': 'paste-tab' });
const tabCliBtn = createElement('button', 'btn-cli', 'tab-btn', { 'data-tab': 'cli-tab' });
const tabDemoBtn = createElement('button', 'btn-demo', 'tab-btn', { 'data-tab': 'demo-tab' });

const uploadTab = createElement('div', 'upload-tab', 'tab-content active');
const pasteTab = createElement('div', 'paste-tab', 'tab-content');
const cliTab = createElement('div', 'cli-tab', 'tab-content');
const demoTab = createElement('div', 'demo-tab', 'tab-content');

body.appendChild(tabUploadBtn);
body.appendChild(tabPasteBtn);
body.appendChild(tabCliBtn);
body.appendChild(tabDemoBtn);
body.appendChild(uploadTab);
body.appendChild(pasteTab);
body.appendChild(cliTab);
body.appendChild(demoTab);

// Paste Tab elements
const pasteForm = createElement('form', 'paste-form');
const pasteTitle = createElement('input', 'paste-title');
const pasteHtml = createElement('textarea', 'paste-html');
const presetWaitlist = createElement('button', '', 'preset-chip-btn', { 'data-preset': 'waitlist' });
const presetPortfolio = createElement('button', '', 'preset-chip-btn', { 'data-preset': 'portfolio' });
const presetDocs = createElement('button', '', 'preset-chip-btn', { 'data-preset': 'docs' });
pasteForm.appendChild(pasteTitle);
pasteForm.appendChild(pasteHtml);
pasteForm.appendChild(presetWaitlist);
pasteForm.appendChild(presetPortfolio);
pasteForm.appendChild(presetDocs);
pasteTab.appendChild(pasteForm);

// CLI Tab elements
const copyCliBtn = createElement('button', 'copy-cli-btn');
cliTab.appendChild(copyCliBtn);

// Split Showcase Elements
const tabHtml = createElement('div', '', 'editor-tab-item active', { 'data-editor-file': 'html' });
const tabCss = createElement('div', '', 'editor-tab-item', { 'data-editor-file': 'css' });
const tabRedirects = createElement('div', '', 'editor-tab-item', { 'data-editor-file': 'redirects' });

const showcaseCodeBody = createElement('div', 'showcase-code-body');
const showcasePreviewUrl = createElement('div', 'showcase-preview-url');
const showcaseStatusTag = createElement('div', 'showcase-status-tag');
const showcasePreviewTitle = createElement('div', 'showcase-preview-title');
const showcasePreviewDesc = createElement('div', 'showcase-preview-desc');
const showcasePreviewAction = createElement('div', 'showcase-preview-action');
const mockPreviewContainer = createElement('div', 'mock-preview-container');
const previewViewportLabel = createElement('div', 'preview-viewport-label');

const vpDesktop = createElement('button', '', 'viewport-btn active', { 'data-viewport': 'desktop' });
const vpTablet = createElement('button', '', 'viewport-btn', { 'data-viewport': 'tablet' });
const vpMobile = createElement('button', '', 'viewport-btn', { 'data-viewport': 'mobile' });

body.appendChild(tabHtml);
body.appendChild(tabCss);
body.appendChild(tabRedirects);
body.appendChild(showcaseCodeBody);
body.appendChild(showcasePreviewUrl);
body.appendChild(showcaseStatusTag);
body.appendChild(showcasePreviewTitle);
body.appendChild(showcasePreviewDesc);
body.appendChild(showcasePreviewAction);
body.appendChild(mockPreviewContainer);
body.appendChild(previewViewportLabel);
body.appendChild(vpDesktop);
body.appendChild(vpTablet);
body.appendChild(vpMobile);

// Setup Window and Document mock
const mockDocument = {
    addEventListener: (evt, cb) => {
        if (evt === 'DOMContentLoaded') {
            // execute immediately
            cb();
        }
    },
    getElementById: (id) => elements[id] || null,
    querySelectorAll: (selector) => doc.querySelectorAll(selector),
    querySelector: (selector) => doc.querySelector(selector),
    body: body
};

const mockWindow = {
    document: mockDocument,
    navigator: {
        clipboard: {
            writeText: async () => true
        }
    },
    PageDockPipeline: null
};

// Execute app.js in sandbox
const sandbox = {
    window: mockWindow,
    document: mockDocument,
    navigator: mockWindow.navigator,
    setTimeout: (cb) => { cb(); return 1; },
    clearTimeout: () => {},
    console: console,
    FormData: class { append() {} set() {} },
    alert: (msg) => console.log('Mock Alert:', msg)
};

vm.createContext(sandbox);
let runtimeError = null;
try {
    vm.runInContext(appJsCode, sandbox);
} catch (e) {
    runtimeError = e.message;
}

assertTest('app.js runs to completion on DOMContentLoaded without throwing', runtimeError === null, runtimeError);

// 3. Testing Deployment Tabs Interactivity
console.log('\n--- 3. Testing Deployment Tabs Interactivity ---');

// Click "Paste HTML" tab
tabPasteBtn.dispatchEvent({ type: 'click' });
assertTest('Clicking Paste HTML activates tab button', tabPasteBtn.classList.contains('active'));
assertTest('Clicking Paste HTML deactivates Upload tab button', !tabUploadBtn.classList.contains('active'));
assertTest('Clicking Paste HTML displays paste-tab content', pasteTab.classList.contains('active'));
assertTest('Clicking Paste HTML hides upload-tab content', !uploadTab.classList.contains('active'));

// Click "CLI & Agents" tab
tabCliBtn.dispatchEvent({ type: 'click' });
assertTest('Clicking CLI activates CLI tab button', tabCliBtn.classList.contains('active'));
assertTest('Clicking CLI displays cli-tab content', cliTab.classList.contains('active'));
assertTest('Clicking CLI hides paste-tab content', !pasteTab.classList.contains('active'));

// Click "1-Click Demo" tab
tabDemoBtn.dispatchEvent({ type: 'click' });
assertTest('Clicking 1-Click Demo activates Demo tab button', tabDemoBtn.classList.contains('active'));
assertTest('Clicking 1-Click Demo displays demo-tab content', demoTab.classList.contains('active'));

// Click "Upload Files" tab
tabUploadBtn.dispatchEvent({ type: 'click' });
assertTest('Clicking Upload restores upload-tab active state', uploadTab.classList.contains('active'));
assertTest('Clicking Upload hides demo-tab', !demoTab.classList.contains('active'));

// 4. Testing Preset Starter Chips in Paste Tab
console.log('\n--- 4. Testing Preset Starter Chips ---');
presetWaitlist.dispatchEvent({ type: 'click' });
assertTest('Clicking SaaS Waitlist chip populates paste HTML', pasteHtml.value.includes('Aero — The Future of Cloud Sync'));
assertTest('Clicking SaaS Waitlist chip sets title to SaaS Waitlist', pasteTitle.value === 'SaaS Waitlist');

presetPortfolio.dispatchEvent({ type: 'click' });
assertTest('Clicking Developer Bio chip populates portfolio HTML', pasteHtml.value.includes('Devon Vance'));

presetDocs.dispatchEvent({ type: 'click' });
assertTest('Clicking Mini Docs chip populates API doc HTML', pasteHtml.value.includes('API Quickstart'));

// 5. Testing Interactive Split Showcase Tabs (index.html, style.css, _redirects)
console.log('\n--- 5. Testing Interactive Showcase Tabs ---');

// Click style.css tab
tabCss.dispatchEvent({ type: 'click' });
assertTest('Clicking style.css tab activates style.css tab', tabCss.classList.contains('active'));
assertTest('Clicking style.css tab deactivates index.html tab', !tabHtml.classList.contains('active'));
assertTest('Showcase code body contains CSS tokens', showcaseCodeBody.innerHTML.includes(':root'));
assertTest('Showcase status tag updates to Live CSS Tokens Active', showcaseStatusTag.textContent === 'Live CSS Tokens Active');
assertTest('Showcase preview URL updates to style.css', showcasePreviewUrl.textContent.includes('style.css'));

// Click _redirects tab
tabRedirects.dispatchEvent({ type: 'click' });
assertTest('Clicking _redirects tab activates _redirects tab', tabRedirects.classList.contains('active'));
assertTest('Clicking _redirects tab deactivates style.css tab', !tabCss.classList.contains('active'));
assertTest('Showcase code body contains 301 rewrite rules', showcaseCodeBody.innerHTML.includes('/old-about'));
assertTest('Showcase preview URL updates with 301 redirect preview', showcasePreviewUrl.textContent.includes('301'));

// Click index.html tab
tabHtml.dispatchEvent({ type: 'click' });
assertTest('Clicking index.html tab re-activates index.html', tabHtml.classList.contains('active'));
assertTest('Showcase code body contains pd:shared header', showcaseCodeBody.innerHTML.includes('pd:shared header'));

// 6. Testing Viewport Switcher Toggles
console.log('\n--- 6. Testing Viewport Switcher Toggles ---');
vpTablet.dispatchEvent({ type: 'click' });
assertTest('Clicking tablet viewport activates tablet button', vpTablet.classList.contains('active'));
assertTest('Tablet viewport sets container max-width to 460px', mockPreviewContainer.style.maxWidth === '460px');
assertTest('Tablet viewport label updates to 768px x 1024px', previewViewportLabel.textContent.includes('768px'));

vpMobile.dispatchEvent({ type: 'click' });
assertTest('Mobile viewport sets container max-width to 320px', mockPreviewContainer.style.maxWidth === '320px');
assertTest('Mobile viewport label updates to 375px x 812px', previewViewportLabel.textContent.includes('375px'));

vpDesktop.dispatchEvent({ type: 'click' });
assertTest('Desktop viewport resets container max-width to 100%', mockPreviewContainer.style.maxWidth === '100%');
assertTest('Desktop viewport label updates to 1440px x 900px', previewViewportLabel.textContent.includes('1440px'));

// Summary
console.log('\n=== UI Test Results Summary ===');
const passed = results.filter(r => r.status === 'PASS').length;
const total = results.length;
console.log(`Total Tests: ${total} | Passed: ${passed} | Failed: ${total - passed}`);

if (passed === total) {
    console.log('ALL UI TABS & BUTTON TESTS PASSED!');
    process.exit(0);
} else {
    console.log('SOME TESTS FAILED!');
    process.exit(1);
}

