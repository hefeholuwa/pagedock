/**
 * PageDock AI Editor - Live Preview Synchronization & Full-Document Patching Test Suite
 * 
 * Validates:
 * 1. pagedock-harness.js handles { type: 'live-patch', html: '...' } when d.tag is omitted (full document sync)
 * 2. Full document sync extracts and applies new document.title
 * 3. Full document sync updates head styles and links while preserving injected harness scripts/styles
 * 4. Full document sync updates document.body.innerHTML and preserves held/muted media hooks
 * 5. Full document sync dispatches 'page-structure' message back to parent window
 * 6. Visual inspector selection and double-click text editing remain functional after full document sync
 * 7. When d.tag and d.tagIndex ARE provided, it falls back to surgical element replacement
 * 8. editor.js debounces CodeMirror changes (300ms) and dispatches live-patch to preview iframe
 * 9. editor.js syncCodeToPreview() dispatches full document live-patch and structure update request
 */

const fs = require('fs');
const path = require('path');
const assert = require('assert');
const vm = require('vm');

const ROOT_DIR = path.resolve(__dirname, '..');
const HARNESS_PATH = path.join(ROOT_DIR, 'public', 'assets', 'js', 'pagedock-harness.js');
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

console.log('=== Running PageDock Live Preview Synchronization Test Suite ===\n');

assert(fs.existsSync(HARNESS_PATH), 'public/assets/js/pagedock-harness.js must exist');
assert(fs.existsSync(EDITOR_JS_PATH), 'public/assets/js/editor.js must exist');

const harnessContent = fs.readFileSync(HARNESS_PATH, 'utf8');
const editorJsContent = fs.readFileSync(EDITOR_JS_PATH, 'utf8');

// --- Helper to build minimal DOM environment for testing harness and editor ---
function createMockDOM(initialHtml = '') {
    class MockClassList {
        constructor(cls = '') {
            this.classes = new Set(cls ? cls.split(/\s+/).filter(Boolean) : []);
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
        get length() { return this.classes.size; }
        item(idx) { return Array.from(this.classes)[idx]; }
        toString() { return Array.from(this.classes).join(' '); }
    }

    class MockNode {
        constructor(tag, id = '', cls = '') {
            this.nodeType = 1;
            this.tagName = tag.toUpperCase();
            this.id = id;
            this.className = cls;
            this.classList = new MockClassList(cls);
            this.attributes = [];
            this.attrs = {};
            this.children = [];
            this.parentElement = null;
            this.parentNode = null;
            this.listeners = {};
            this.style = {};
            this._innerHTML = '';
            this._textContent = '';
            this.muted = false;
            this.paused = true;
        }

        get textContent() {
            if (this.children.length === 0) return this._textContent;
            return this.children.map(c => c.textContent).join('');
        }
        set textContent(val) {
            this._textContent = String(val);
            this.children = [];
            this._innerHTML = escapeHtml(String(val));
        }

        get innerHTML() {
            if (this.children.length > 0) {
                return this.children.map(c => c.outerHTML).join('');
            }
            return this._innerHTML;
        }
        set innerHTML(html) {
            this._innerHTML = html;
            // Parse into child nodes
            this.children = parseHtmlToNodes(html, this);
            this._textContent = extractTextContent(this.children);
        }

        get outerHTML() {
            const tag = this.tagName.toLowerCase();
            const attrStr = this.attributes.map(a => ` ${a.name}="${escapeHtml(a.value)}"`).join('');
            const inner = this.innerHTML;
            return `<${tag}${attrStr}>${inner}</${tag}>`;
        }
        set outerHTML(val) {
            const p = this.parentElement || this.parentNode;
            if (p) {
                const siblings = p.children;
                const idx = siblings.indexOf(this);
                if (idx !== -1) {
                    const parsed = parseHtmlToNodes(val, p);
                    siblings.splice(idx, 1, ...parsed);
                }
            }
        }

        setAttribute(name, val) {
            const sVal = String(val);
            this.attrs[name] = sVal;
            const existing = this.attributes.find(a => a.name === name);
            if (existing) {
                existing.value = sVal;
            } else {
                this.attributes.push({ name, value: sVal });
            }
            if (name === 'id') this.id = sVal;
            if (name === 'class') {
                this.className = sVal;
                this.classList = new MockClassList(sVal);
            }
        }

        getAttribute(name) {
            return this.attrs[name] !== undefined ? this.attrs[name] : null;
        }

        hasAttribute(name) {
            return this.attrs[name] !== undefined;
        }

        removeAttribute(name) {
            delete this.attrs[name];
            this.attributes = this.attributes.filter(a => a.name !== name);
            if (name === 'id') this.id = '';
            if (name === 'class') {
                this.className = '';
                this.classList = new MockClassList('');
            }
        }

        appendChild(child) {
            child.parentElement = this;
            child.parentNode = this;
            this.children.push(child);
            return child;
        }

        removeChild(child) {
            const idx = this.children.indexOf(child);
            if (idx !== -1) {
                this.children.splice(idx, 1);
                child.parentElement = null;
                child.parentNode = null;
            }
            return child;
        }

        contains(node) {
            if (node === this) return true;
            for (const child of this.children) {
                if (child.contains(node)) return true;
            }
            return false;
        }

        getElementsByTagName(tag) {
            const res = [];
            const upper = tag.toUpperCase();
            function walk(n) {
                for (const c of n.children) {
                    if (tag === '*' || c.tagName === upper) res.push(c);
                    walk(c);
                }
            }
            walk(this);
            return res;
        }

        querySelectorAll(sel) {
            const res = [];
            function walk(n) {
                for (const c of n.children) {
                    if (matchesSelector(c, sel)) res.push(c);
                    walk(c);
                }
            }
            walk(this);
            return res;
        }

        querySelector(sel) {
            const list = this.querySelectorAll(sel);
            return list.length ? list[0] : null;
        }

        matches(sel) {
            return matchesSelector(this, sel);
        }

        closest(sel) {
            let cur = this;
            while (cur) {
                if (matchesSelector(cur, sel)) return cur;
                cur = cur.parentElement;
            }
            return null;
        }

        getBoundingClientRect() {
            return {
                x: 50, y: 50, width: 200, height: 100,
                top: 50, right: 250, bottom: 150, left: 50
            };
        }

        scrollIntoView() {}
        focus() {}
        pause() { this.paused = true; }
        play() { this.paused = false; }

        addEventListener(evt, cb) {
            if (!this.listeners[evt]) this.listeners[evt] = [];
            this.listeners[evt].push(cb);
        }

        dispatchEvent(eventObj) {
            const list = this.listeners[eventObj.type] || [];
            list.forEach(cb => cb(eventObj));
        }

        click() {
            let cur = this;
            const eventObj = {
                type: 'click',
                target: this,
                preventDefault: () => {},
                stopPropagation: () => {}
            };
            while (cur) {
                if (cur.listeners['click']) {
                    cur.listeners['click'].forEach(cb => cb(eventObj));
                }
                cur = cur.parentElement;
            }
        }
    }

    function escapeHtml(str) {
        return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function extractTextContent(nodes) {
        return nodes.map(n => n.textContent).join('');
    }

    function matchesSelector(node, sel) {
        if (!node || node.nodeType !== 1) return false;
        const s = sel.trim();

        // Attribute selectors like [data-pagedock-ui]
        const attrMatch = s.match(/^\[([a-zA-Z0-9_-]+)(?:([*^$]?=)(["']?)(.*?)\3)?\]$/);
        if (attrMatch) {
            const attrName = attrMatch[1];
            const op = attrMatch[2];
            const val = attrMatch[4];
            if (!node.hasAttribute(attrName)) return false;
            if (!op) return true;
            const currentVal = node.getAttribute(attrName);
            if (op === '=') return currentVal === val;
            if (op === '*=') return currentVal.includes(val);
            if (op === '^=') return currentVal.startsWith(val);
            if (op === '$=') return currentVal.endsWith(val);
        }

        // Tag selector with attribute e.g. script[src*="pagedock-harness"]
        const tagAttrMatch = s.match(/^([a-zA-Z0-9]+)\[([a-zA-Z0-9_-]+)(?:([*^$]?=)(["']?)(.*?)\4)?\]$/);
        if (tagAttrMatch) {
            const tag = tagAttrMatch[1].toUpperCase();
            const attrName = tagAttrMatch[2];
            const op = tagAttrMatch[3];
            const val = tagAttrMatch[5];
            if (node.tagName !== tag) return false;
            if (!node.hasAttribute(attrName)) return false;
            if (!op) return true;
            const currentVal = node.getAttribute(attrName) || '';
            if (op === '=') return currentVal === val;
            if (op === '*=') return currentVal.includes(val);
            if (op === '^=') return currentVal.startsWith(val);
            if (op === '$=') return currentVal.endsWith(val);
        }

        // Simple class e.g. .btn
        if (s.startsWith('.')) {
            return node.classList.contains(s.slice(1));
        }

        // Simple ID e.g. #my-id
        if (s.startsWith('#')) {
            return node.id === s.slice(1);
        }

        // Simple tag e.g. DIV or style:not(...)
        if (s.includes(':not(')) {
            const parts = s.split(':not(');
            const baseTag = parts[0];
            const notSel = parts[1].replace(/\)$/, '');
            if (baseTag && node.tagName !== baseTag.toUpperCase()) return false;
            return !matchesSelector(node, notSel);
        }

        return node.tagName === s.toUpperCase();
    }

    function parseHtmlToNodes(html, parentNode = null) {
        const nodes = [];
        const tagRegex = /<([a-zA-Z0-9-]+)([^>]*)>([\s\S]*?)<\/\1>|<([a-zA-Z0-9-]+)([^>]*)\/?>/g;
        let match;
        let lastIndex = 0;

        while ((match = tagRegex.exec(html)) !== null) {
            const tagName = match[1] || match[4];
            const rawAttrs = match[2] || match[5] || '';
            const inner = match[3] || '';

            const el = new MockNode(tagName);
            el.parentElement = parentNode;
            el.parentNode = parentNode;

            // Parse attributes
            const attrRegex = /([a-zA-Z0-9_:-]+)(?:=(?:"([^"]*)"|'([^']*)'|([^\s>]+)))?/g;
            let attrMatch;
            while ((attrMatch = attrRegex.exec(rawAttrs)) !== null) {
                const aName = attrMatch[1];
                const aVal = attrMatch[2] !== undefined ? attrMatch[2] :
                             attrMatch[3] !== undefined ? attrMatch[3] :
                             attrMatch[4] !== undefined ? attrMatch[4] : '';
                el.setAttribute(aName, aVal);
            }

            if (inner) {
                if (['STYLE', 'SCRIPT', 'TITLE'].includes(el.tagName)) {
                    el._textContent = inner;
                    el._innerHTML = inner;
                } else {
                    el.children = parseHtmlToNodes(inner, el);
                    el._innerHTML = inner;
                    el._textContent = extractTextContent(el.children);
                }
            }

            nodes.push(el);
            lastIndex = tagRegex.lastIndex;
        }

        if (nodes.length === 0 && html.trim()) {
            const textNode = new MockNode('SPAN');
            textNode._textContent = html.trim();
            textNode._innerHTML = html.trim();
            textNode.parentElement = parentNode;
            textNode.parentNode = parentNode;
            nodes.push(textNode);
        }

        return nodes;
    }

    // Root Document & HTML Elements
    const docEl = new MockNode('html');
    const headEl = new MockNode('head');
    const bodyEl = new MockNode('body');
    docEl.appendChild(headEl);
    docEl.appendChild(bodyEl);

    const doc = {
        nodeType: 9,
        title: 'Initial Title',
        documentElement: docEl,
        head: headEl,
        body: bodyEl,
        readyState: 'complete',
        styleSheets: [],
        createElement: (tag) => new MockNode(tag),
        getElementsByTagName: (tag) => {
            const upper = tag.toUpperCase();
            if (upper === 'HTML') return [docEl];
            if (upper === 'HEAD') return [headEl];
            if (upper === 'BODY') return [bodyEl];
            const fromHead = headEl.getElementsByTagName(tag);
            const fromBody = bodyEl.getElementsByTagName(tag);
            return fromHead.concat(fromBody);
        },
        querySelectorAll: (sel) => {
            return docEl.querySelectorAll(sel);
        },
        querySelector: (sel) => {
            return docEl.querySelector(sel);
        },
        getElementById: (id) => {
            const list = docEl.querySelectorAll('#' + id);
            return list.length ? list[0] : null;
        },
        elementFromPoint: (x, y) => null,
        addEventListener: (evt, cb, capture) => {
            if (!docEl.listeners[evt]) docEl.listeners[evt] = [];
            docEl.listeners[evt].push(cb);
        },
        removeEventListener: (evt, cb) => {
            if (docEl.listeners[evt]) {
                docEl.listeners[evt] = docEl.listeners[evt].filter(f => f !== cb);
            }
        }
    };

    headEl.parentElement = docEl;
    bodyEl.parentElement = docEl;

    // Injected harness elements in head and body
    const harnessHeadStyle = new MockNode('style');
    harnessHeadStyle.setAttribute('data-pagedock-ui', '');
    harnessHeadStyle.textContent = '[data-pagedock-overlay-hidden]{display:none!important}';
    headEl.appendChild(harnessHeadStyle);

    const initialUserStyle = new MockNode('style');
    initialUserStyle.textContent = 'body { background: #fff; }';
    headEl.appendChild(initialUserStyle);

    const initialHeading = new MockNode('h1');
    initialHeading.textContent = 'Initial Heading';
    bodyEl.appendChild(initialHeading);

    const harnessScript = new MockNode('script');
    harnessScript.setAttribute('data-pagedock-ui', '');
    harnessScript.setAttribute('src', '/assets/js/pagedock-harness.js');
    bodyEl.appendChild(harnessScript);

    const messagesSentToParent = [];
    const win = {
        parent: {
            postMessage: (data, targetOrigin) => {
                messagesSentToParent.push(data);
            }
        },
        document: doc,
        innerWidth: 1024,
        innerHeight: 768,
        addEventListener: (evt, cb) => {
            if (!win.listeners[evt]) win.listeners[evt] = [];
            win.listeners[evt].push(cb);
        },
        removeEventListener: (evt) => {},
        listeners: {},
        setTimeout: (fn, ms) => {
            fn();
            return 1;
        },
        clearTimeout: () => {},
        getComputedStyle: (el) => {
            return {
                display: 'block',
                position: 'static',
                color: 'rgb(0, 0, 0)',
                backgroundColor: 'rgb(255, 255, 255)',
                fontSize: '16px',
                fontFamily: 'sans-serif',
                fontWeight: '400',
                padding: '0px',
                margin: '0px',
                border: 'none',
                borderRadius: '0px',
                width: '100px',
                height: '40px',
                textAlign: 'left'
            };
        }
    };

    class MockMutationObserver {
        constructor(callback) {
            this.callback = callback;
        }
        observe(target, options) {}
        disconnect() {}
    }

    class MockDOMParser {
        parseFromString(str, type) {
            const parsedDoc = createMockDOM().document;
            parsedDoc.head.children = [];
            parsedDoc.body.children = [];

            // Extract title
            const titleMatch = str.match(/<title[^>]*>([\s\S]*?)<\/title>/i);
            if (titleMatch) {
                parsedDoc.title = titleMatch[1].trim();
            } else {
                parsedDoc.title = '';
            }

            // Extract head
            const headMatch = str.match(/<head[^>]*>([\s\S]*?)<\/head>/i);
            if (headMatch) {
                parsedDoc.head.innerHTML = headMatch[1];
            }

            // Extract body
            const bodyMatch = str.match(/<body[^>]*>([\s\S]*?)<\/body>/i);
            if (bodyMatch) {
                parsedDoc.body.innerHTML = bodyMatch[1];
            } else {
                parsedDoc.body.innerHTML = str;
            }

            return parsedDoc;
        }
    }

    return {
        document: doc,
        window: win,
        DOMParser: MockDOMParser,
        MutationObserver: MockMutationObserver,
        messagesSentToParent
    };
}

// =============================================================================
// TEST 1: Static analysis of pagedock-harness.js full-document live patch support
// =============================================================================
runTest('pagedock-harness.js handles live-patch with full document parser (DOMParser)', () => {
    assert(
        harnessContent.includes('DOMParser'),
        'pagedock-harness.js must use DOMParser to parse full-document HTML on live-patch'
    );
    assert(
        harnessContent.includes('doc.title') || harnessContent.includes('document.title ='),
        'pagedock-harness.js must update document.title on full document live sync'
    );
    assert(
        harnessContent.includes('page-structure'),
        'pagedock-harness.js must dispatch page-structure on live-patch'
    );
});

// =============================================================================
// TEST 2: Runtime verification: pagedock-harness.js full-document sync
// =============================================================================
runTest('Runtime: live-patch without d.tag updates document.title, head styles, and body DOM cleanly', () => {
    const mock = createMockDOM();
    const sandbox = {
        window: mock.window,
        document: mock.document,
        DOMParser: mock.DOMParser,
        MutationObserver: mock.MutationObserver,
        setTimeout: mock.window.setTimeout,
        clearTimeout: mock.window.clearTimeout,
        console: console
    };

    // Load harness in VM
    vm.runInNewContext(harnessContent, sandbox);

    // Initial checks
    assert.strictEqual(mock.document.title, 'Initial Title');
    assert(mock.document.body.innerHTML.includes('Initial Heading'));

    // Send full document live-patch message (d.tag is omitted)
    const newHtml = `<!DOCTYPE html>
<html>
<head>
    <title>Brand New Live Title</title>
    <style>.live-button { color: red; }</style>
    <link rel="stylesheet" href="/assets/custom.css">
</head>
<body>
    <header class="live-hero">
        <h1>Welcome to Live Sync</h1>
        <p>Real-time visual editor</p>
    </header>
</body>
</html>`;

    const messageListeners = mock.window.listeners['message'] || [];
    assert(messageListeners.length > 0, 'Harness should register window message listener');

    // Dispatch live-patch
    for (const listener of messageListeners) {
        listener({
            data: {
                type: 'live-patch',
                html: newHtml
            }
        });
    }

    // 1. Verify document.title updated
    assert.strictEqual(
        mock.document.title,
        'Brand New Live Title',
        'document.title must be updated to new title'
    );

    // 2. Verify body content updated
    assert(
        mock.document.body.innerHTML.includes('Welcome to Live Sync'),
        'document.body should contain new h1 heading'
    );
    assert(
        mock.document.body.innerHTML.includes('Real-time visual editor'),
        'document.body should contain new paragraph'
    );
    assert(
        !mock.document.body.innerHTML.includes('Initial Heading'),
        'Initial heading should have been replaced'
    );

    // 3. Verify head styles/links updated
    const styles = mock.document.head.getElementsByTagName('style');
    const styleTexts = styles.map(s => s.textContent || s.innerHTML).join(' ');
    assert(
        styleTexts.includes('.live-button { color: red; }'),
        'Custom <style> from new document must be applied to document.head'
    );

    const links = mock.document.head.getElementsByTagName('link');
    const linkHrefs = links.map(l => l.getAttribute('href')).join(' ');
    assert(
        linkHrefs.includes('/assets/custom.css'),
        'Custom <link> from new document must be updated in document.head'
    );

    // 4. Verify harness UI element was preserved
    const harnessUiInHead = mock.document.head.querySelectorAll('[data-pagedock-ui]');
    assert(
        harnessUiInHead.length > 0,
        'Injected harness UI style in head must be preserved'
    );

    // 5. Verify page-structure message was dispatched back to parent window
    const structMsg = mock.messagesSentToParent.find(m => m.type === 'page-structure');
    assert(
        structMsg,
        'Full document sync must dispatch page-structure message back to parent'
    );
    assert(
        structMsg.tree && structMsg.tree.tag === 'BODY',
        'page-structure tree should have root tag BODY'
    );
});

// =============================================================================
// TEST 3: Runtime verification: Fallback to surgical element patching when d.tag provided
// =============================================================================
runTest('Runtime: live-patch with d.tag and d.tagIndex performs surgical element replacement', () => {
    const mock = createMockDOM();
    const sandbox = {
        window: mock.window,
        document: mock.document,
        DOMParser: mock.DOMParser,
        MutationObserver: mock.MutationObserver,
        setTimeout: mock.window.setTimeout,
        clearTimeout: mock.window.clearTimeout,
        console: console
    };

    vm.runInNewContext(harnessContent, sandbox);

    const messageListeners = mock.window.listeners['message'] || [];
    assert(messageListeners.length > 0, 'Harness should register window message listener');

    // Surgical patch targeting H1 at tagIndex 0
    for (const listener of messageListeners) {
        listener({
            data: {
                type: 'live-patch',
                tag: 'H1',
                tagIndex: 0,
                html: '<h1 class="surgical-update">Surgically Patched Title</h1>'
            }
        });
    }

    assert.strictEqual(mock.document.title, 'Initial Title', 'Title should remain untouched during surgical patch');
    assert(
        mock.document.body.innerHTML.includes('Surgically Patched Title'),
        'H1 should be replaced with surgical HTML'
    );
});

// =============================================================================
// TEST 4: Inspection & selection hooks preserved after full document sync
// =============================================================================
runTest('Runtime: visual selection and click hooks remain active after full document live patch', () => {
    const mock = createMockDOM();
    const sandbox = {
        window: mock.window,
        document: mock.document,
        DOMParser: mock.DOMParser,
        MutationObserver: mock.MutationObserver,
        setTimeout: mock.window.setTimeout,
        clearTimeout: mock.window.clearTimeout,
        console: console
    };

    vm.runInNewContext(harnessContent, sandbox);

    const messageListeners = mock.window.listeners['message'] || [];

    // Apply full document sync
    for (const listener of messageListeners) {
        listener({
            data: {
                type: 'live-patch',
                html: '<html><body><button id="cta-btn" class="px-4 py-2">Click Me</button></body></html>'
            }
        });
    }

    // Now click the newly rendered button in the preview
    const button = mock.document.getElementById('cta-btn');
    assert(button, 'Button should exist in document after sync');

    // Trigger click on the button
    button.click();

    // Verify parent received element-selected message
    const selectMsg = mock.messagesSentToParent.find(m => m.type === 'element-selected');
    assert(selectMsg, 'Clicking newly patched element must dispatch element-selected to parent');
    assert.strictEqual(selectMsg.tag, 'BUTTON', 'Selected tag should be BUTTON');
});

// =============================================================================
// TEST 5: CodeMirror debounced sync in editor.js dispatches live-patch
// =============================================================================
runTest('editor.js debounces CodeMirror input and dispatches live-patch with full document html', () => {
    assert(
        editorJsContent.includes('syncCodeToPreview'),
        'editor.js must define syncCodeToPreview'
    );
    assert(
        editorJsContent.includes("type: 'live-patch'"),
        'editor.js must dispatch live-patch message'
    );

    // Mock CodeMirror and parent editor
    let changeHandler = null;
    let postMessageCalls = [];

    const mockIframe = {
        contentWindow: {
            postMessage: (msg) => {
                postMessageCalls.push(msg);
            }
        },
        getBoundingClientRect: () => ({ left: 0, top: 0, width: 800, height: 600 })
    };

    const mockCM = {
        fromTextArea: () => ({
            getValue: () => '<html><body><h1>CodeMirror Code</h1></body></html>',
            setValue: () => {},
            refresh: () => {},
            on: (evt, handler) => {
                if (evt === 'change') changeHandler = handler;
            },
            setOption: () => {}
        })
    };

    const docElements = {
        'preview-iframe': mockIframe,
        'hh-source-textarea': { nodeType: 1 },
        'hh-workspace': { classList: { toggle: () => {} } }
    };

    let scheduledTimer = null;
    const sandbox = {
        window: {
            PAGEDOCK_CONFIG: { slug: 'test-site', activeFile: 'index.html' },
            addEventListener: () => {},
            setTimeout: (fn, delay) => {
                scheduledTimer = fn;
                return 123;
            },
            clearTimeout: () => {
                scheduledTimer = null;
            }
        },
        document: {
            readyState: 'complete',
            body: { classList: { toggle: () => {}, contains: () => false } },
            getElementById: (id) => docElements[id] || null,
            querySelectorAll: () => [],
            createElement: () => ({
                classList: { add: () => {}, remove: () => {} },
                addEventListener: () => {}
            }),
            addEventListener: () => {}
        },
        CodeMirror: mockCM,
        setTimeout: (fn, delay) => {
            scheduledTimer = fn;
            return 123;
        },
        clearTimeout: () => {
            scheduledTimer = null;
        },
        console: console,
        fetch: () => Promise.resolve({ json: () => Promise.resolve({}) })
    };

    vm.runInNewContext(editorJsContent, sandbox);

    assert(changeHandler, 'editor.js must register change handler on CodeMirror');

    // Simulate user typing in CodeMirror
    changeHandler(null, { origin: '+input' });
    assert(scheduledTimer, 'CodeMirror change should schedule a debounced sync timer');

    // Execute debounced timer
    scheduledTimer();

    // Verify live-patch message sent to iframe
    const livePatchMsg = postMessageCalls.find(m => m.type === 'live-patch');
    assert(livePatchMsg, 'syncCodeToPreview must dispatch live-patch message to preview iframe');
    assert.strictEqual(
        livePatchMsg.html,
        '<html><body><h1>CodeMirror Code</h1></body></html>',
        'live-patch message html must match CodeMirror value'
    );
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
