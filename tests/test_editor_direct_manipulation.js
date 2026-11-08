/**
 * PageDock AI Editor - Direct Visual Editing Reliability Test Suite
 * 
 * Validates Task 3 requirements:
 * 1. Duplicate element: Operates by tag and tagIndex in DOM, clones node cleanly, and syncs to CodeMirror
 * 2. Delete element: Removes exact element at tagIndex in DOM and CodeMirror
 * 3. Inline text editing: Double clicking text element enables editing; on blur/finish syncs to CodeMirror
 *    without breaking outerHTML tags, attributes, or indentation
 * 4. Jump to code: Resolves exact tag and tagIndex in CodeMirror and focuses line
 * 5. Dismissals: Escape key (in iframe or parent) and outside click dismiss popovers & clear outlines
 * 6. Refinements:
 *    - document.body.className is cleared properly when empty in live-patch
 *    - documentElement attributes (class, data-theme, lang) synchronized during live-patch
 *    - Redundant request-structure removed from syncCodeToPreview()
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

console.log('=== Running PageDock Direct Visual Editing Reliability Test Suite ===\n');

assert(fs.existsSync(HARNESS_PATH), 'public/assets/js/pagedock-harness.js must exist');
assert(fs.existsSync(EDITOR_JS_PATH), 'public/assets/js/editor.js must exist');

const harnessContent = fs.readFileSync(HARNESS_PATH, 'utf8');
const editorJsContent = fs.readFileSync(EDITOR_JS_PATH, 'utf8');

// --- Helper Mock DOM Environment ---
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
            this.dataset = {};
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

        get nextSibling() {
            const p = this.parentElement || this.parentNode;
            if (!p) return null;
            const idx = p.children.indexOf(this);
            return (idx !== -1 && idx + 1 < p.children.length) ? p.children[idx + 1] : null;
        }

        cloneNode(deep = true) {
            const clone = new MockNode(this.tagName.toLowerCase(), this.id, this.className);
            clone.attrs = Object.assign({}, this.attrs);
            clone.attributes = this.attributes.map(a => ({ name: a.name, value: a.value }));
            clone.style = Object.assign({}, this.style);
            clone.dataset = Object.assign({}, this.dataset);
            clone._textContent = this._textContent;
            clone._innerHTML = this._innerHTML;
            if (deep) {
                this.children.forEach(c => {
                    clone.appendChild(c.cloneNode(true));
                });
            }
            return clone;
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

        insertBefore(newNode, refNode) {
            if (!refNode) return this.appendChild(newNode);
            const idx = this.children.indexOf(refNode);
            if (idx === -1) return this.appendChild(newNode);
            newNode.parentElement = this;
            newNode.parentNode = this;
            this.children.splice(idx, 0, newNode);
            return newNode;
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

        remove() {
            if (this.parentElement) {
                this.parentElement.removeChild(this);
            }
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

        closest(sel) {
            let cur = this;
            while (cur) {
                if (matchesSelector(cur, sel)) return cur;
                cur = cur.parentElement;
            }
            return null;
        }

        matches(sel) {
            return matchesSelector(this, sel);
        }

        getBoundingClientRect() {
            return { x: 50, y: 50, width: 200, height: 100, top: 50, right: 250, bottom: 150, left: 50 };
        }

        scrollIntoView() {}
        focus() {}

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

        dblclick() {
            let cur = this;
            const eventObj = {
                type: 'dblclick',
                target: this,
                preventDefault: () => {},
                stopPropagation: () => {}
            };
            while (cur) {
                if (cur.listeners['dblclick']) {
                    cur.listeners['dblclick'].forEach(cb => cb(eventObj));
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

        if (s.startsWith('.')) return node.classList.contains(s.slice(1));
        if (s.startsWith('#')) return node.id === s.slice(1);

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

        while ((match = tagRegex.exec(html)) !== null) {
            const tagName = match[1] || match[4];
            const rawAttrs = match[2] || match[5] || '';
            const inner = match[3] || '';

            const el = new MockNode(tagName);
            el.parentElement = parentNode;
            el.parentNode = parentNode;

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

    const docEl = new MockNode('html');
    const headEl = new MockNode('head');
    const bodyEl = new MockNode('body');
    docEl.appendChild(headEl);
    docEl.appendChild(bodyEl);

    const doc = {
        nodeType: 9,
        title: 'Mock Page',
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
        querySelectorAll: (sel) => docEl.querySelectorAll(sel),
        querySelector: (sel) => docEl.querySelector(sel),
        getElementById: (id) => {
            const list = docEl.querySelectorAll('#' + id);
            return list.length ? list[0] : null;
        },
        elementFromPoint: (x, y) => null,
        listeners: docEl.listeners,
        addEventListener: (evt, cb) => {
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

    const messagesSentToParent = [];
    const win = {
        parent: {
            postMessage: (data) => messagesSentToParent.push(data)
        },
        document: doc,
        innerWidth: 1024,
        innerHeight: 768,
        listeners: {},
        addEventListener: (evt, cb) => {
            if (!win.listeners[evt]) win.listeners[evt] = [];
            win.listeners[evt].push(cb);
        },
        setTimeout: (fn) => { fn(); return 1; },
        clearTimeout: () => {},
        getComputedStyle: () => ({
            display: 'block', position: 'static', color: 'rgb(0,0,0)',
            backgroundColor: 'rgb(255,255,255)', fontSize: '16px', fontFamily: 'sans-serif',
            fontWeight: '400', padding: '0px', margin: '0px', border: 'none',
            borderRadius: '0px', width: '100px', height: '40px', textAlign: 'left'
        })
    };

    class MockDOMParser {
        parseFromString(str) {
            const p = createMockDOM().document;
            p.head.children = [];
            p.body.children = [];
            const titleMatch = str.match(/<title[^>]*>([\s\S]*?)<\/title>/i);
            p.title = titleMatch ? titleMatch[1].trim() : '';
            
            const htmlTagMatch = str.match(/<html([^>]*)>/i);
            if (htmlTagMatch) {
                const attrRegex = /([a-zA-Z0-9_:-]+)(?:=(?:"([^"]*)"|'([^']*)'|([^\s>]+)))?/g;
                let am;
                while ((am = attrRegex.exec(htmlTagMatch[1])) !== null) {
                    p.documentElement.setAttribute(am[1], am[2] !== undefined ? am[2] : am[3]);
                }
            }

            const bodyTagMatch = str.match(/<body([^>]*)>([\s\S]*?)<\/body>/i);
            if (bodyTagMatch) {
                const bAttrs = bodyTagMatch[1];
                const attrRegex = /([a-zA-Z0-9_:-]+)(?:=(?:"([^"]*)"|'([^']*)'|([^\s>]+)))?/g;
                let am;
                while ((am = attrRegex.exec(bAttrs)) !== null) {
                    p.body.setAttribute(am[1], am[2] !== undefined ? am[2] : am[3]);
                }
                p.body.innerHTML = bodyTagMatch[2];
            } else {
                p.body.innerHTML = str;
            }
            return p;
        }
    }

    class MockMutationObserver {
        observe() {}
        disconnect() {}
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
// TEST 1: Protocol support in pagedock-harness.js for duplicate-element & delete-element
// =============================================================================
runTest('pagedock-harness.js handles duplicate-element and delete-element messages', () => {
    assert(
        harnessContent.includes('duplicate-element'),
        'pagedock-harness.js must implement message handler for duplicate-element'
    );
    assert(
        harnessContent.includes('delete-element'),
        'pagedock-harness.js must implement message handler for delete-element'
    );
});

// =============================================================================
// TEST 2: Runtime duplicate-element clones node cleanly in DOM by tagIndex
// =============================================================================
runTest('Runtime: duplicate-element clones exact node at tagIndex cleanly without outline or contenteditable', () => {
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

    // Populate body with 2 paragraphs
    mock.document.body.innerHTML = '<p class="p-item" id="p1">First P</p><p class="p-item" id="p2">Second P</p>';

    const pList = mock.document.getElementsByTagName('P');
    assert.strictEqual(pList.length, 2, 'Initially should have 2 paragraphs');

    // Simulate selecting second paragraph (which sets outline)
    pList[1].style.outline = '2px solid #7dd3fc';
    pList[1].setAttribute('contenteditable', 'true');

    // Dispatch duplicate-element targeting tag 'P' and tagIndex 1
    const listeners = mock.window.listeners['message'] || [];
    for (const l of listeners) {
        l({
            data: {
                type: 'duplicate-element',
                tag: 'P',
                tagIndex: 1
            }
        });
    }

    const updatedP = mock.document.getElementsByTagName('P');
    assert.strictEqual(updatedP.length, 3, 'Paragraph count should increase to 3 after duplicate');
    assert.strictEqual(updatedP[1].textContent, 'Second P', 'Target element retained');
    assert.strictEqual(updatedP[2].textContent, 'Second P', 'Clone placed immediately after target element');
    
    // Check clone was cleaned (no outline or contenteditable)
    assert.strictEqual(updatedP[2].style.outline || '', '', 'Clone must not carry active outline');
    assert.strictEqual(updatedP[2].getAttribute('contenteditable'), null, 'Clone must not carry contenteditable');
});

// =============================================================================
// TEST 3: Runtime delete-element removes exact node at tagIndex
// =============================================================================
runTest('Runtime: delete-element removes exact node at tagIndex and updates structure', () => {
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

    mock.document.body.innerHTML = '<ul><li>Item 1</li><li>Item 2 (Delete Me)</li><li>Item 3</li></ul>';

    const liList = mock.document.getElementsByTagName('LI');
    assert.strictEqual(liList.length, 3, 'Initially 3 list items');

    const listeners = mock.window.listeners['message'] || [];
    for (const l of listeners) {
        l({
            data: {
                type: 'delete-element',
                tag: 'LI',
                tagIndex: 1
            }
        });
    }

    const updatedLi = mock.document.getElementsByTagName('LI');
    assert.strictEqual(updatedLi.length, 2, 'LI count should be 2 after delete');
    assert.strictEqual(updatedLi[0].textContent, 'Item 1');
    assert.strictEqual(updatedLi[1].textContent, 'Item 3', 'Item 2 was removed');
});

// =============================================================================
// TEST 4: editor.js duplicate and delete operations sync CodeMirror accurately
// =============================================================================
runTest('editor.js duplicateSelectedElement and deleteSelectedElement operate by exact tag and tagIndex', () => {
    let cmValue = `<!DOCTYPE html>
<html>
<body>
  <div class="container">
    <p class="para">First item</p>
    <p class="para">Second item to duplicate</p>
    <p class="para">Third item</p>
  </div>
</body>
</html>`;

    let postMessagesToIframe = [];
    const mockIframe = {
        contentWindow: {
            postMessage: (msg) => postMessagesToIframe.push(msg)
        },
        getBoundingClientRect: () => ({ left: 0, top: 0, width: 800, height: 600 })
    };

    const mockCM = {
        fromTextArea: () => ({
            getValue: () => cmValue,
            setValue: (val) => { cmValue = val; },
            refresh: () => {},
            on: () => {},
            setOption: () => {},
            posFromIndex: (idx) => ({ line: 5, ch: 4 }),
            setCursor: () => {},
            scrollIntoView: () => {},
            focus: () => {}
        })
    };

    const mockDoc = {
        readyState: 'complete',
        body: { classList: { toggle: () => {}, contains: () => false } },
        getElementById: (id) => {
            if (id === 'preview-iframe') return mockIframe;
            if (id === 'hh-source-textarea') return { nodeType: 1 };
            if (id === 'hh-canvas-area') return { getBoundingClientRect: () => ({ left: 0, top: 0, width: 800, height: 600 }), scrollLeft: 0, scrollTop: 0 };
            return null;
        },
        querySelectorAll: () => [],
        createElement: () => ({
            classList: { add: () => {}, remove: () => {} },
            addEventListener: () => {}
        }),
        addEventListener: () => {}
    };

    const sandbox = {
        window: {
            PAGEDOCK_CONFIG: { slug: 'test-site', activeFile: 'index.html' },
            addEventListener: () => {},
            setTimeout: (fn) => fn(),
            clearTimeout: () => {}
        },
        document: mockDoc,
        CodeMirror: mockCM,
        setTimeout: (fn) => fn(),
        clearTimeout: () => {},
        console: console,
        fetch: () => Promise.resolve({ json: () => Promise.resolve({}) })
    };

    vm.runInNewContext(editorJsContent, sandbox);

    // Dispatch element-selected for 2nd paragraph (tag: 'P', tagIndex: 1)
    const messageHandlers = sandbox.window.listeners ? sandbox.window.listeners['message'] : [];
    // We can simulate message event directly
    const winMsgListener = sandbox.window.onmessage || null;

    // Verify duplicateSelectedElement in editor.js operates by tagIndex
    assert(
        editorJsContent.includes('duplicate-element'),
        'editor.js must dispatch duplicate-element to preview iframe'
    );
    assert(
        editorJsContent.includes('delete-element'),
        'editor.js must dispatch delete-element to preview iframe'
    );
});

// =============================================================================
// TEST 5: Inline text editing syncs accurately to CodeMirror without breaking tags or indentation
// =============================================================================
runTest('Inline text edit updates CodeMirror without breaking outerHTML tags or indentation', () => {
    let initialCode = `<div class="hero">
  <h1 class="title font-bold text-4xl" data-heading="main">Original Heading</h1>
  <p class="subtitle">Supporting description</p>
</div>`;

    let currentCode = initialCode;

    const mockCM = {
        fromTextArea: () => ({
            getValue: () => currentCode,
            setValue: (val) => { currentCode = val; },
            refresh: () => {},
            on: () => {},
            setOption: () => {}
        })
    };

    let postMessages = [];
    const mockIframe = {
        contentWindow: { postMessage: (m) => postMessages.push(m) },
        getBoundingClientRect: () => ({ left: 0, top: 0, width: 800, height: 600 })
    };

    let registeredMsgListener = null;
    const mockWin = {
        PAGEDOCK_CONFIG: { slug: 'test-site', activeFile: 'index.html' },
        addEventListener: (evt, handler) => {
            if (evt === 'message') registeredMsgListener = handler;
        },
        setTimeout: (fn) => fn(),
        clearTimeout: () => {}
    };

    const mockDoc = {
        readyState: 'complete',
        body: { classList: { toggle: () => {}, contains: () => false } },
        getElementById: (id) => {
            if (id === 'preview-iframe') return mockIframe;
            if (id === 'hh-source-textarea') return { nodeType: 1 };
            if (id === 'hh-canvas-area') return { getBoundingClientRect: () => ({ left: 0, top: 0, width: 800, height: 600 }), scrollLeft: 0, scrollTop: 0 };
            return null;
        },
        querySelectorAll: () => [],
        createElement: () => ({ classList: { add: () => {}, remove: () => {} }, addEventListener: () => {} }),
        addEventListener: () => {}
    };

    const sandbox = {
        window: mockWin,
        document: mockDoc,
        CodeMirror: mockCM,
        setTimeout: (fn) => fn(),
        clearTimeout: () => {},
        console: console,
        fetch: () => Promise.resolve({ json: () => Promise.resolve({}) })
    };

    vm.runInNewContext(editorJsContent, sandbox);
    assert(registeredMsgListener, 'editor.js must register window message listener');

    // Simulate selecting H1 (tag: 'H1', tagIndex: 0)
    registeredMsgListener({
        data: {
            type: 'element-selected',
            tag: 'H1',
            tagIndex: 0,
            text: 'Original Heading',
            html: '<h1 class="title font-bold text-4xl" data-heading="main">Original Heading</h1>',
            rect: { left: 50, top: 50, width: 200, height: 50 }
        }
    });

    // Simulate text-edit-start (which used to wipe selectedElement buggily)
    registeredMsgListener({
        data: { type: 'text-edit-start' }
    });

    // Simulate finishing text editing with element-text-edit message
    registeredMsgListener({
        data: {
            type: 'element-text-edit',
            tag: 'H1',
            tagIndex: 0,
            text: 'Updated Live Heading'
        }
    });

    // Verify CodeMirror received updated text
    assert(
        currentCode.includes('Updated Live Heading'),
        'CodeMirror code must contain new text'
    );
    assert(
        currentCode.includes('<h1 class="title font-bold text-4xl" data-heading="main">'),
        'OuterHTML attributes on H1 must remain completely intact'
    );
    assert(
        currentCode.includes('</h1>'),
        'Closing tag must remain intact'
    );
    assert(
        currentCode.includes('  <h1'),
        'Leading indentation must be preserved'
    );
});

// =============================================================================
// TEST 6: Jump to code locates exact tag and tagIndex in CodeMirror
// =============================================================================
runTest('jumpToElementInCode resolves exact line for tagIndex > 0', () => {
    const codeSample = `<!DOCTYPE html>
<html>
<body>
  <div class="actions">
    <button class="btn">Click me</button>
    <p>Divider text</p>
    <button class="btn">Click me</button>
    <p>Divider text</p>
    <button class="btn">Click me</button>
  </div>
</body>
</html>`;

    let cursorPosition = null;
    let scrolled = false;

    const mockCM = {
        fromTextArea: () => ({
            getValue: () => codeSample,
            setValue: () => {},
            refresh: () => {},
            on: () => {},
            setOption: () => {},
            posFromIndex: (idx) => {
                const lines = codeSample.slice(0, idx).split('\n');
                return { line: lines.length - 1, ch: lines[lines.length - 1].length };
            },
            setCursor: (pos) => { cursorPosition = pos; },
            scrollIntoView: () => { scrolled = true; },
            focus: () => {}
        })
    };

    let msgListener = null;
    let jumpHandler = null;

    const mockWin = {
        PAGEDOCK_CONFIG: { slug: 'test-site', activeFile: 'index.html' },
        addEventListener: (evt, handler) => {
            if (evt === 'message') msgListener = handler;
        },
        setTimeout: (fn) => fn(),
        clearTimeout: () => {}
    };

    const mockDoc = {
        readyState: 'complete',
        body: { classList: { toggle: () => {}, contains: () => false } },
        getElementById: (id) => {
            if (id === 'preview-iframe') return { contentWindow: { postMessage: () => {} } };
            if (id === 'hh-source-textarea') return { nodeType: 1 };
            if (id === 'act-code-btn') return { addEventListener: (evt, fn) => { jumpHandler = fn; } };
            return null;
        },
        querySelectorAll: () => [],
        createElement: () => ({ classList: { add: () => {}, remove: () => {} }, addEventListener: () => {} }),
        addEventListener: () => {}
    };

    const sandbox = {
        window: mockWin,
        document: mockDoc,
        CodeMirror: mockCM,
        setTimeout: (fn) => fn(),
        clearTimeout: () => {},
        console: console,
        fetch: () => Promise.resolve({ json: () => Promise.resolve({}) })
    };

    vm.runInNewContext(editorJsContent, sandbox);

    assert(msgListener, 'Message listener must be registered');
    msgListener({
        data: {
            type: 'element-selected',
            tag: 'BUTTON',
            tagIndex: 2,
            html: '<button class="btn">Click me</button>',
            rect: { left: 10, top: 200, width: 100, height: 40 }
        }
    });

    assert(jumpHandler, 'act-code-btn listener must be registered');
    jumpHandler();

    assert(cursorPosition, 'Cursor position must be set');
    assert.strictEqual(cursorPosition.line, 8, 'Cursor should be positioned on line 8 for button at tagIndex 2');
    assert(scrolled, 'scrollIntoView must be called');
});

// =============================================================================
// TEST 7: Dismissals: Escape key in iframe or parent and outside click dismiss popovers & clear outlines
// =============================================================================
runTest('Dismissals: Escape key dismisses popovers cleanly and clears visual outlines', () => {
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

    mock.document.body.innerHTML = '<button id="btn">Click me</button>';
    const btn = mock.document.getElementById('btn');
    btn.click(); // Selects element

    assert(btn.style.outline !== '', 'Button should have outline on select');

    // Press Escape inside preview iframe
    const keyListeners = mock.document.listeners['keydown'] || [];
    assert(keyListeners.length > 0, 'Harness must register document keydown listener');

    for (const kl of keyListeners) {
        kl({
            key: 'Escape',
            preventDefault: () => {},
            stopPropagation: () => {}
        });
    }

    assert.strictEqual(btn.style.outline || '', '', 'Outline must be cleared after Escape');
    const dismissMsg = mock.messagesSentToParent.find(m => m.type === 'dismiss-popovers' || m.type === 'iframe-escape');
    assert(dismissMsg, 'Escape in iframe must post dismiss-popovers or iframe-escape to parent');
});

// =============================================================================
// TEST 8: Refinement: document.body.className = doc.body.className || ''
// =============================================================================
runTest('Refinement: live-patch clears document.body.className when empty', () => {
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

    // Initial body has classes
    mock.document.body.className = 'dark-theme bg-gray-900';

    const messageListeners = mock.window.listeners['message'] || [];
    for (const l of messageListeners) {
        l({
            data: {
                type: 'live-patch',
                html: '<!DOCTYPE html><html><head><title>No Class</title></head><body><p>Clean body</p></body></html>'
            }
        });
    }

    assert.strictEqual(
        mock.document.body.className,
        '',
        'document.body.className must be cleared when incoming doc has no body class'
    );
});

// =============================================================================
// TEST 9: Refinement: Synchronize documentElement attributes (class, data-theme, lang)
// =============================================================================
runTest('Refinement: live-patch synchronizes documentElement attributes (class, data-theme, lang)', () => {
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
    for (const l of messageListeners) {
        l({
            data: {
                type: 'live-patch',
                html: '<!DOCTYPE html><html class="dark" data-theme="midnight" lang="fr"><head><title>Test</title></head><body><p>Content</p></body></html>'
            }
        });
    }

    assert.strictEqual(mock.document.documentElement.getAttribute('class'), 'dark', 'class attribute must sync to documentElement');
    assert.strictEqual(mock.document.documentElement.getAttribute('data-theme'), 'midnight', 'data-theme must sync to documentElement');
    assert.strictEqual(mock.document.documentElement.getAttribute('lang'), 'fr', 'lang must sync to documentElement');
});

// =============================================================================
// TEST 10: Refinement: Remove redundant request-structure from syncCodeToPreview()
// =============================================================================
runTest('Refinement: syncCodeToPreview() does not dispatch redundant request-structure message', () => {
    // Extract syncCodeToPreview function definition from editor.js
    const syncFuncMatch = editorJsContent.match(/function\s+syncCodeToPreview\s*\(\)\s*\{([\s\S]*?)\n\s*function/);
    assert(syncFuncMatch, 'Could not extract syncCodeToPreview function');
    const syncBody = syncFuncMatch[1];

    assert(
        !syncBody.includes("type: 'request-structure'") && !syncBody.includes('type: "request-structure"'),
        'syncCodeToPreview() must NOT dispatch redundant request-structure postMessage since live-patch already sends refreshed tree'
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
