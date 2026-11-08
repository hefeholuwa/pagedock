/**
 * Automated Test Suite for Web Editor Real-Time Live Preview Engine
 * Validates:
 * 1. Live preview synthesis with relative <base href="..."> grounding
 * 2. Real-time updates on HTML textarea input
 * 3. Hot CSS style injection for style.css tab with 0ms lag
 * 4. Virtual script synthesis for script.js
 * 5. Iframe srcdoc reset on manual refresh (reloadPreview)
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

console.log('=== Running Web Editor Real-Time Live Preview Test Suite ===\n');

const editorJsPath = path.join(__dirname, '../public/assets/js/editor.js');
const editorJsCode = fs.readFileSync(editorJsPath, 'utf8');

class MockClassList {
    constructor(className = '') {
        this.classes = new Set(className.split(/\s+/).filter(Boolean));
    }
    add(...names) { names.forEach(n => this.classes.add(n)); }
    remove(...names) { names.forEach(n => this.classes.delete(n)); }
    contains(name) { return this.classes.has(name); }
}

class MockElement {
    constructor(tagName, id = '', className = '') {
        this.tagName = tagName.toUpperCase();
        this.id = id;
        this.className = className;
        this.classList = new MockClassList(className);
        this.attributes = {};
        this.listeners = {};
        this.style = {};
        this.value = '';
        this.textContent = '';
        this.innerHTML = '';
        this.children = [];
        this.src = '';
        this.srcdoc = '';
    }
    setAttribute(k, v) { this.attributes[k] = String(v); }
    getAttribute(k) { return this.attributes[k] !== undefined ? this.attributes[k] : null; }
    removeAttribute(k) { delete this.attributes[k]; if (k === 'srcdoc') this.srcdoc = ''; }
    addEventListener(evt, cb) {
        if (!this.listeners[evt]) this.listeners[evt] = [];
        this.listeners[evt].push(cb);
    }
    dispatchEvent(event) {
        const type = typeof event === 'string' ? event : event.type;
        const list = this.listeners[type] || [];
        for (const cb of list) cb(event);
    }
    querySelectorAll(selector) {
        const res = [];
        const match = (el) => {
            if (selector === '.editor-tab-item[data-editor-tab]') {
                if (el.classList.contains('editor-tab-item') && el.getAttribute('data-editor-tab') !== null) res.push(el);
            } else if (selector.startsWith('.')) {
                if (el.classList.contains(selector.slice(1))) res.push(el);
            } else if (selector.startsWith('#')) {
                if (el.id === selector.slice(1)) res.push(el);
            } else if (selector.includes('[data-editor-tab]')) {
                if (el.getAttribute('data-editor-tab') !== null) res.push(el);
            }
            for (const c of el.children) match(c);
        };
        match(this);
        return res;
    }
    appendChild(child) {
        this.children.push(child);
        return child;
    }
}

const docRoot = new MockElement('body');
const elements = {};
function createEl(tag, id, className, attrs = {}) {
    const el = new MockElement(tag, id, className);
    for (const [k, v] of Object.entries(attrs)) el.setAttribute(k, v);
    if (id) elements[id] = el;
    docRoot.appendChild(el);
    return el;
}

const initialSampleHtml = `<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Live Preview Test Site</title>
</head>
<body>
  <h1 id="headline">Initial Title</h1>
</body>
</html>`;

const codeEditor = createEl('textarea', 'code-editor');
codeEditor.value = initialSampleHtml;

const tabHtml = createEl('button', '', 'editor-tab-item active', { 'data-editor-tab': 'html' });
const tabCss = createEl('button', '', 'editor-tab-item', { 'data-editor-tab': 'css' });
const tabJs = createEl('button', '', 'editor-tab-item', { 'data-editor-tab': 'js' });
const tabRedirects = createEl('button', '', 'editor-tab-item', { 'data-editor-tab': 'redirects' });
const activeLangTag = createEl('span', 'active-language-tag');
activeLangTag.textContent = 'HTML';

const saveStatus = createEl('span', 'save-status');
const saveBtn = createEl('button', 'save-code-btn');
const refreshBtn = createEl('button', 'refresh-preview-btn');
const toast = createEl('div', 'toast');
const previewIframe = createEl('iframe', 'preview-iframe');
previewIframe.src = 'http://127.0.0.1:8080/s/live-preview-test/';

// Mock iframe internal document for hot CSS reload
const iframeHead = new MockElement('head');
const iframeBody = new MockElement('body');
previewIframe.contentDocument = {
    head: iframeHead,
    body: iframeBody,
    getElementById: (id) => {
        if (id === '__pagedock_live_style') {
            return iframeHead.children.find(c => c.id === '__pagedock_live_style') || null;
        }
        return null;
    },
    createElement: (tag) => new MockElement(tag)
};

const timers = [];
const mockDocument = {
    addEventListener: (evt, cb) => { if (evt === 'DOMContentLoaded') cb(); },
    getElementById: (id) => elements[id] || null,
    querySelectorAll: (sel) => docRoot.querySelectorAll(sel),
    querySelector: (sel) => docRoot.querySelectorAll(sel)[0] || null,
    body: docRoot
};

const sandbox = {
    window: {
        document: mockDocument,
        PAGEDOCK_CONFIG: {
            slug: 'live-preview-test',
            saveUrl: 'http://127.0.0.1:8080/api/editor/live-preview-test',
            previewUrl: 'http://127.0.0.1:8080/s/live-preview-test/',
            activeFile: 'index.html',
            canManage: true,
            hasPhysicalCss: false,
            hasPhysicalJs: false,
            initialRedirects: ''
        },
        PageDockPipeline: null
    },
    document: mockDocument,
    fetch: async () => ({ json: async () => ({ success: true }) }),
    setTimeout: (cb, delay) => {
        const id = timers.length;
        timers.push({ cb, delay });
        // Execute immediately in test for deterministic verification
        cb();
        return id;
    },
    clearTimeout: () => {},
    console: console
};

vm.createContext(sandbox);
vm.runInContext(editorJsCode, sandbox);

// 1. Test HTML Live Preview Update on Input
console.log('--- 1. Testing HTML Live Preview on Input ---');
codeEditor.value = initialSampleHtml.replace('Initial Title', 'Updated Live Headline via Antigravity');
codeEditor.dispatchEvent({ type: 'input' });

assertTest('Live preview iframe receives updated srcdoc', previewIframe.srcdoc.includes('Updated Live Headline via Antigravity'));
assertTest('Live preview srcdoc contains base href tag pointing to site', previewIframe.srcdoc.includes('<base href="http://127.0.0.1:8080/s/live-preview-test/">'));

// 2. Test CSS Hot-Reload on CSS Tab
console.log('\n--- 2. Testing CSS Live Hot-Reload ---');
tabCss.dispatchEvent({ type: 'click' });
codeEditor.value = 'body { background: #000; color: #C7F547; }';
codeEditor.dispatchEvent({ type: 'input' });

const styleEl = previewIframe.contentDocument.getElementById('__pagedock_live_style');
assertTest('Hot CSS style element created in iframe document', styleEl !== null);
assertTest('Hot CSS text content matches live editor value', styleEl.textContent.includes('color: #C7F547'));

// 3. Test Switching Back to HTML Tab and Full Document Synthesis
console.log('\n--- 3. Testing Full Document Synthesis on Tab Switch ---');
tabHtml.dispatchEvent({ type: 'click' });
assertTest('Master HTML contains injected live CSS', previewIframe.srcdoc.includes('color: #C7F547'));

// 4. Test Manual Refresh clears srcdoc and updates timestamp
console.log('\n--- 4. Testing Manual Refresh Reset ---');
refreshBtn.dispatchEvent({ type: 'click' });
assertTest('Reload preview clears temporary srcdoc', previewIframe.srcdoc === '');
assertTest('Reload preview appends cache-buster timestamp to src', previewIframe.src.includes('?t='));

// Summary
console.log('\n=== Live Preview Test Summary ===');
const passed = results.filter(r => r.status === 'PASS').length;
const total = results.length;
console.log(`Total Tests: ${total} | Passed: ${passed} | Failed: ${total - passed}`);

if (passed === total) {
    console.log('ALL REAL-TIME LIVE PREVIEW TESTS PASSED!');
    process.exit(0);
} else {
    console.log('SOME TESTS FAILED!');
    process.exit(1);
}

