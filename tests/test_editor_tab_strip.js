/**
 * Automated Test Suite for Web Editor Tab-Strip & Virtual Slicing
 * Validates:
 * 1. Extraction of CSS from single-file HTML (<style>...</style>)
 * 2. Extraction of JS from single-file HTML (<script>...</script>)
 * 3. Bidirectional state synchronization between virtual tabs and master document
 * 4. Injection of updated CSS back into <style> upon switching or saving
 * 5. Injection of updated JS back into <script> upon switching or saving
 * 6. Independent Netlify _redirects tab buffer management
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

console.log('=== Running Web Editor Tab-Strip & Virtual Slicing Test Suite ===\n');

// 1. Compile Check
console.log('--- 1. Script Syntax Validation ---');
const editorJsPath = path.join(__dirname, '../public/assets/js/editor.js');
const editorJsCode = fs.readFileSync(editorJsPath, 'utf8');

let compileErr = null;
try {
    new vm.Script(editorJsCode, { filename: 'editor.js' });
} catch (e) {
    compileErr = e.message;
}
assertTest('public/assets/js/editor.js compiles with zero syntax errors', compileErr === null, compileErr);

// 2. Mock DOM Environment
console.log('\n--- 2. Mock DOM Execution & Tab-Strip Binding ---');

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
    }
    setAttribute(k, v) { this.attributes[k] = String(v); }
    getAttribute(k) { return this.attributes[k] !== undefined ? this.attributes[k] : null; }
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

// Build Editor DOM
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
  <title>Commanding Your Morning</title>
  <style>
    :root { --navy: #101b2d; --gold: #d6ad60; }
    body { background: #faf7f0; color: var(--navy); }
  </style>
</head>
<body>
  <h1>Commanding Your Morning</h1>
  <script>
    console.log("Morning devotions loaded.");
  </script>
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
const toast = createEl('div', 'toast');
const previewIframe = createEl('iframe', 'preview-iframe');

// Sandbox & Execute
const savedPosts = [];
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
            slug: 'commanding-morning',
            saveUrl: 'http://127.0.0.1:8080/api/editor/commanding-morning',
            previewUrl: 'http://127.0.0.1:8080/s/commanding-morning',
            activeFile: 'index.html',
            canManage: true,
            hasPhysicalCss: false,
            hasPhysicalJs: false,
            initialRedirects: '/old /new 301'
        },
        PageDockPipeline: null
    },
    document: mockDocument,
    fetch: async (url, opts) => {
        if (opts && opts.body) {
            savedPosts.push({ url, body: JSON.parse(opts.body) });
        }
        return {
            json: async () => ({ success: true, version: 2 }),
            text: async () => initialSampleHtml
        };
    },
    setTimeout: (cb) => 1,
    clearTimeout: () => {},
    console: console
};

vm.createContext(sandbox);
let runErr = null;
try {
    vm.runInContext(editorJsCode, sandbox);
} catch (e) {
    runErr = e.message;
}
assertTest('editor.js executes without errors on DOMContentLoaded', runErr === null, runErr);

// 3. Testing Tab Slicing & Switching to style.css
console.log('\n--- 3. Testing style.css Virtual Slicing ---');

tabCss.dispatchEvent({ type: 'click' });
assertTest('Clicking style.css activates tab button', tabCss.classList.contains('active'));
assertTest('Clicking style.css deactivates html tab button', !tabHtml.classList.contains('active'));
assertTest('Language indicator updates to CSS', activeLangTag.textContent === 'CSS');
assertTest('Editor value contains extracted CSS rules', codeEditor.value.includes('--navy: #101b2d;'));
assertTest('Editor value does NOT contain <style> tags in CSS view', !codeEditor.value.includes('<style>') && !codeEditor.value.includes('</style>'));

// Edit CSS in style.css view
codeEditor.value += '\n.hero-cta { background: var(--gold); }';

// 4. Testing Slicing & Switching to script.js
console.log('\n--- 4. Testing script.js Virtual Slicing ---');

tabJs.dispatchEvent({ type: 'click' });
assertTest('Clicking script.js activates JS tab button', tabJs.classList.contains('active'));
assertTest('Language indicator updates to JavaScript', activeLangTag.textContent === 'JavaScript');
assertTest('Editor value contains extracted script logic', codeEditor.value.includes('Morning devotions loaded.'));
assertTest('Editor value does NOT contain <script> tags in JS view', !codeEditor.value.includes('<script>') && !codeEditor.value.includes('</script>'));

// Edit JS in script.js view
codeEditor.value += '\nconsole.log("Audio player initialized.");';

// 5. Testing Switching back to index.html & Verifying Automatic Injection
console.log('\n--- 5. Testing Master HTML Re-Synthesis ---');

tabHtml.dispatchEvent({ type: 'click' });
assertTest('Clicking index.html re-activates HTML tab', tabHtml.classList.contains('active'));
assertTest('Language indicator restores to HTML', activeLangTag.textContent === 'HTML');
assertTest('Master HTML contains newly added CSS rule', codeEditor.value.includes('.hero-cta { background: var(--gold); }'));
assertTest('Master HTML contains newly added JS logic', codeEditor.value.includes('Audio player initialized.'));
assertTest('CSS remains cleanly wrapped in <style>', codeEditor.value.includes('<style>') && codeEditor.value.includes('</style>'));
assertTest('JS remains cleanly wrapped in <script>', codeEditor.value.includes('<script>') && codeEditor.value.includes('</script>'));

// 6. Testing _redirects Tab Buffer
console.log('\n--- 6. Testing _redirects Tab ---');

tabRedirects.dispatchEvent({ type: 'click' });
assertTest('Clicking _redirects activates redirects tab', tabRedirects.classList.contains('active'));
assertTest('Language indicator updates to Netlify _redirects', activeLangTag.textContent.includes('redirects'));
assertTest('_redirects editor displays initial redirect rule', codeEditor.value.includes('/old /new 301'));

// Append new rule to _redirects
codeEditor.value += '\n/book/* https://amazon.com/:splat 302';

// 7. Testing Save & Publish across Multi-Layer Buffers
console.log('\n--- 7. Testing Save & Publish Multi-Layer Propagation ---');

saveBtn.dispatchEvent({ type: 'click' });

// Give microtask tick for async fetch calls
setTimeout(() => {
    assertTest('Save triggers POST to saveUrl', savedPosts.length >= 1);
    const postBody = savedPosts[0]?.body || {};
    const files = postBody.files || {};
    const htmlContent = files['index.html'] || savedPosts.find(p => p.body.file === 'index.html')?.body.html || '';
    assertTest('POST body contains synthesized master HTML', htmlContent.includes('.hero-cta'));
    const redirectsContent = files['_redirects'] || savedPosts.find(p => p.body.file === '_redirects')?.body.html || '';
    assertTest('POST body contains updated _redirects rules', redirectsContent.includes('/book/*'));

    // Summary
    console.log('\n=== Test Results Summary ===');
    const passed = results.filter(r => r.status === 'PASS').length;
    const total = results.length;
    console.log(`Total Tests: ${total} | Passed: ${passed} | Failed: ${total - passed}`);

    if (passed === total) {
        console.log('ALL WEB EDITOR TAB-STRIP TESTS PASSED!');
        process.exit(0);
    } else {
        console.log('SOME TESTS FAILED!');
        process.exit(1);
    }
}, 50);
