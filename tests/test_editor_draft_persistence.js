/**
 * PageDock AI Editor - Local Storage Auto-Save & Draft Safety Test Suite
 * 
 * Validates Task 4 requirements:
 * 1. Keystroke changes in CodeMirror are auto-saved to localStorage under `pagedock_draft_${slug}_${file}` after debounce (1000ms).
 * 2. Draft payload contains JSON string with `{ code, timestamp, slug, file }`.
 * 3. On editor init, if a local draft exists and is newer than initial load and has different code:
 *    - Displays a recovery notification or toast: "Restored unsaved draft from local storage"
 *    - Restores draft into CodeMirror and marks state as dirty.
 * 4. On editor init, if local draft is older than initial load, it does NOT restore and does NOT show recovery toast.
 * 5. On editor init, if local draft has identical code, it does NOT restore and does NOT show recovery toast.
 * 6. When user clicks "Save Draft" or "Publish", localStorage draft is updated with clean status or cleared so false recovery notifications don't fire.
 * 7. Multiple rapid keystrokes properly debounce and only fire one save after 1000ms idle.
 */

const fs = require('fs');
const path = require('path');
const assert = require('assert');
const vm = require('vm');

const ROOT_DIR = path.resolve(__dirname, '..');
const EDITOR_JS_PATH = path.join(ROOT_DIR, 'public', 'assets', 'js', 'editor.js');

const tests = [];

function test(name, fn) {
    tests.push({ name, fn });
}

console.log('=== Running PageDock Local Storage Auto-Save & Draft Safety Test Suite ===\n');

assert(fs.existsSync(EDITOR_JS_PATH), 'public/assets/js/editor.js must exist');
const editorJsContent = fs.readFileSync(EDITOR_JS_PATH, 'utf8');

// --- Helper Mocks ---

class MockLocalStorage {
    constructor() {
        this.store = {};
    }
    getItem(key) {
        return Object.prototype.hasOwnProperty.call(this.store, key) ? this.store[key] : null;
    }
    setItem(key, val) {
        this.store[key] = String(val);
    }
    removeItem(key) {
        delete this.store[key];
    }
    clear() {
        this.store = {};
    }
}

class MockClock {
    constructor(initialTime = 1000000) {
        this.nowTime = initialTime;
        this.timers = [];
        this.nextId = 1;
    }
    now() {
        return this.nowTime;
    }
    setTimeout(fn, delay = 0) {
        const id = this.nextId++;
        this.timers.push({ id, fn, triggerAt: this.nowTime + delay });
        return id;
    }
    clearTimeout(id) {
        this.timers = this.timers.filter(t => t.id !== id);
    }
    tick(ms) {
        const targetTime = this.nowTime + ms;
        while (this.timers.length > 0) {
            this.timers.sort((a, b) => a.triggerAt - b.triggerAt);
            const next = this.timers[0];
            if (next.triggerAt > targetTime) break;
            this.timers.shift();
            this.nowTime = next.triggerAt;
            next.fn();
        }
        this.nowTime = targetTime;
    }
}

function createMockEditorEnvironment(options = {}) {
    const slug = options.slug || 'my-test-site';
    const activeFile = options.activeFile || 'index.html';
    const initialHtml = options.initialHtml !== undefined ? options.initialHtml : '<html><body><h1>Initial Database HTML</h1></body></html>';
    const initialTimestamp = options.initialTimestamp !== undefined ? options.initialTimestamp : 1000000;
    const localStorage = options.localStorage || new MockLocalStorage();
    const clock = options.clock || new MockClock(initialTimestamp + 100);

    let cmInstance = null;
    let cmChangeHandler = null;

    const mockCM = {
        fromTextArea: (textarea, opts) => {
            cmInstance = {
                value: textarea ? textarea.value : initialHtml,
                options: opts || {},
                getValue: function () { return this.value; },
                setValue: function (val) {
                    this.value = val;
                    if (cmChangeHandler) {
                        cmChangeHandler(this, { from: 0, to: 0, text: [val] });
                    }
                },
                refresh: () => {},
                on: (evt, handler) => {
                    if (evt === 'change') cmChangeHandler = handler;
                },
                setOption: () => {},
                posFromIndex: (idx) => ({ line: 0, ch: idx }),
                setCursor: () => {},
                scrollIntoView: () => {},
                focus: () => {}
            };
            return cmInstance;
        }
    };

    const messagesToIframe = [];
    const mockIframe = {
        contentWindow: {
            postMessage: (msg) => messagesToIframe.push(msg)
        },
        getBoundingClientRect: () => ({ left: 0, top: 0, width: 800, height: 600 })
    };

    const elements = {};
    function getEl(id, tag = 'div') {
        if (!elements[id]) {
            elements[id] = {
                id,
                tagName: tag.toUpperCase(),
                children: [],
                classList: {
                    classes: new Set(),
                    add(c) { this.classes.add(c); },
                    remove(c) { this.classes.delete(c); },
                    toggle(c, f) {
                        if (f === true) this.classes.add(c);
                        else if (f === false) this.classes.delete(c);
                        else if (this.classes.has(c)) this.classes.delete(c);
                        else this.classes.add(c);
                    },
                    contains(c) { return this.classes.has(c); }
                },
                style: {},
                attrs: {},
                setAttribute(n, v) { this.attrs[n] = v; },
                getAttribute(n) { return this.attrs[n] !== undefined ? this.attrs[n] : null; },
                value: id === 'hh-source-textarea' ? initialHtml : '',
                textContent: '',
                listeners: {},
                appendChild(child) {
                    this.children.push(child);
                    return child;
                },
                removeChild(child) {
                    const idx = this.children.indexOf(child);
                    if (idx !== -1) this.children.splice(idx, 1);
                    return child;
                },
                addEventListener(evt, fn) {
                    if (!this.listeners[evt]) this.listeners[evt] = [];
                    this.listeners[evt].push(fn);
                },
                click() {
                    const list = this.listeners['click'] || [];
                    list.forEach(fn => fn({ preventDefault: () => {}, stopPropagation: () => {} }));
                }
            };
        }
        return elements[id];
    }

    // Pre-create critical elements
    getEl('hh-source-textarea', 'textarea');
    getEl('preview-iframe', 'iframe');
    getEl('hh-status-dot', 'span');
    getEl('hh-save-status-text', 'span');
    getEl('hh-savedraft-btn', 'button');
    getEl('hh-publish-btn', 'button');
    getEl('hh-toast', 'div');

    const mockDoc = {
        readyState: 'complete',
        body: {
            classList: { toggle: () => {}, add: () => {}, remove: () => {}, contains: () => false },
            style: {}
        },
        getElementById: (id) => {
            if (id === 'preview-iframe') return mockIframe;
            return getEl(id);
        },
        querySelectorAll: (sel) => [],
        createElement: (tag) => getEl(`mock-${Math.random()}`, tag),
        addEventListener: () => {},
        removeEventListener: () => {}
    };

    let fetchCalls = [];
    const mockFetch = (url, opts) => {
        fetchCalls.push({ url, opts });
        return Promise.resolve({
            json: () => Promise.resolve({ success: true })
        });
    };

    const RealDate = Date;
    function MockDate(...args) {
        if (!(this instanceof MockDate)) {
            return new RealDate(clock.now()).toString();
        }
        if (args.length) {
            return new RealDate(...args);
        }
        return new RealDate(clock.now());
    }
    MockDate.now = () => clock.now();
    MockDate.parse = RealDate.parse;
    MockDate.UTC = RealDate.UTC;

    const mockWin = {
        PAGEDOCK_CONFIG: {
            slug,
            activeFile,
            saveUrl: `/api/editor/${slug}`,
            initialTimestamp,
            siteUpdatedAt: initialTimestamp
        },
        localStorage,
        addEventListener: () => {},
        removeEventListener: () => {},
        setTimeout: (fn, delay) => clock.setTimeout(fn, delay),
        clearTimeout: (id) => clock.clearTimeout(id),
        Date: MockDate
    };

    const sandbox = {
        window: mockWin,
        document: mockDoc,
        CodeMirror: mockCM,
        localStorage: localStorage,
        setTimeout: (fn, delay) => clock.setTimeout(fn, delay),
        clearTimeout: (id) => clock.clearTimeout(id),
        fetch: mockFetch,
        console: console,
        Date: MockDate
    };

    return {
        sandbox,
        clock,
        localStorage,
        get cmInstance() { return cmInstance; },
        elements,
        fetchCalls,
        messagesToIframe,
        triggerKeystroke: (newCode) => {
            if (!cmInstance) throw new Error('CodeMirror not initialized yet');
            cmInstance.value = newCode;
            if (cmChangeHandler) {
                cmChangeHandler(cmInstance, { from: 0, to: 0, text: [newCode] });
            }
        }
    };
}

// =============================================================================
// TEST 1: Static analysis of editor.js for auto-save & draft persistence
// =============================================================================
test('editor.js contains localStorage draft persistence functions and storage key pattern', () => {
    assert(
        editorJsContent.includes('pagedock_draft_'),
        'editor.js must format draft keys using "pagedock_draft_" prefix with slug and file'
    );
    assert(
        editorJsContent.includes('Restored unsaved draft') && editorJsContent.includes('from local storage'),
        'editor.js must contain recovery toast message: "Restored unsaved draft ... from local storage"'
    );
    assert(
        editorJsContent.includes('localStorage'),
        'editor.js must access localStorage'
    );
});

// =============================================================================
// TEST 2: Keystroke changes are debounced (1000ms) before saving to localStorage
// =============================================================================
test('Keystroke changes in CodeMirror are auto-saved to localStorage under pagedock_draft_${slug}_${file} after 1000ms debounce', () => {
    const env = createMockEditorEnvironment({
        slug: 'alpha-site',
        activeFile: 'landing.html',
        initialHtml: '<div>Initial Version</div>'
    });

    vm.runInNewContext(editorJsContent, env.sandbox);

    const draftKey = 'pagedock_draft_alpha-site_html';
    assert.strictEqual(env.localStorage.getItem(draftKey), null, 'localStorage should initially have no draft');

    // Trigger typing change
    const updatedCode = '<div>Initial Version with User Additions</div>';
    env.triggerKeystroke(updatedCode);

    // 500ms elapsed: debounce is still pending, draft should NOT be saved yet
    env.clock.tick(500);
    assert.strictEqual(
        env.localStorage.getItem(draftKey),
        null,
        'Draft should not be saved before 1000ms debounce expires'
    );

    // Another 500ms elapsed (total 1000ms): debounce fired!
    env.clock.tick(500);
    const savedDraftRaw = env.localStorage.getItem(draftKey);
    assert(savedDraftRaw !== null, 'Draft should be saved to localStorage after 1000ms debounce');

    const draft = JSON.parse(savedDraftRaw);
    assert.strictEqual(draft.code, updatedCode, 'Saved draft must contain current CodeMirror code');
    assert.strictEqual(draft.slug, 'alpha-site', 'Draft must contain correct slug');
    assert.strictEqual(draft.file, 'landing.html', 'Draft must contain correct file');
    assert.strictEqual(draft.type, 'html', 'Draft must contain correct file type');
    assert(typeof draft.timestamp === 'number' && draft.timestamp > 0, 'Draft must contain timestamp number');
});

// =============================================================================
// TEST 3: Multiple rapid keystrokes reset debounce timer (only 1 save after 1000ms idle)
// =============================================================================
test('Multiple rapid keystrokes within debounce window only trigger single auto-save at idle', () => {
    const env = createMockEditorEnvironment({
        slug: 'beta-site',
        activeFile: 'index.html',
        initialHtml: '<p>Base</p>'
    });

    vm.runInNewContext(editorJsContent, env.sandbox);
    const draftKey = 'pagedock_draft_beta-site_html';

    // Keystroke 1 at t=0
    env.triggerKeystroke('<p>Base A</p>');
    env.clock.tick(400);

    // Keystroke 2 at t=400 (resets timer)
    env.triggerKeystroke('<p>Base AB</p>');
    env.clock.tick(400);

    // Keystroke 3 at t=800 (resets timer)
    env.triggerKeystroke('<p>Base ABC</p>');
    env.clock.tick(600); // Only 600ms since keystroke 3 (total 1400ms from start)

    assert.strictEqual(
        env.localStorage.getItem(draftKey),
        null,
        'Draft should not save while user continues typing within 1000ms debounce'
    );

    // User stops typing, wait remaining 400ms (1000ms since keystroke 3)
    env.clock.tick(400);

    const savedRaw = env.localStorage.getItem(draftKey);
    assert(savedRaw !== null, 'Draft should be saved after 1000ms idle');
    const draft = JSON.parse(savedRaw);
    assert.strictEqual(draft.code, '<p>Base ABC</p>', 'Final draft code should match last keystroke');
});

// =============================================================================
// TEST 4: Editor init restores newer draft, marks dirty, and displays toast
// =============================================================================
test('On editor init, if local draft exists, is newer than initial load and has different code: restores draft, marks dirty and toasts', () => {
    const storage = new MockLocalStorage();
    const slug = 'gamma-site';
    const activeFile = 'index.html';
    const draftKey = `pagedock_draft_${slug}_html`;

    const serverHtml = '<html><body><h1>Server Stored HTML</h1></body></html>';
    const recoveredHtml = '<html><body><h1>Unsaved Restored Work From Crash</h1></body></html>';

    // Seed draft in localStorage that is newer than server copy (server: 1,000,000, draft: 1,050,000)
    storage.setItem(draftKey, JSON.stringify({
        code: recoveredHtml,
        timestamp: 1050000,
        slug,
        type: 'html',
        file: activeFile
    }));

    const env = createMockEditorEnvironment({
        slug,
        activeFile,
        initialHtml: serverHtml,
        initialTimestamp: 1000000,
        localStorage: storage
    });

    vm.runInNewContext(editorJsContent, env.sandbox);

    // 1. CodeMirror should be populated with the restored draft
    assert.strictEqual(
        env.cmInstance.getValue(),
        recoveredHtml,
        'CodeMirror editor must be restored with draft code'
    );

    // 2. Recovery toast notification must be shown
    const toastEl = env.elements['hh-toast'];
    assert(
        toastEl && toastEl.textContent.includes('Restored unsaved draft') && toastEl.textContent.includes('from local storage'),
        `Toast should display recovery message. Got: "${toastEl ? toastEl.textContent : ''}"`
    );

    // 3. Status must be marked as dirty (unsaved changes)
    const statusDot = env.elements['hh-status-dot'];
    assert(
        statusDot && statusDot.attrs['class'] ? statusDot.attrs['class'].includes('dirty') : (statusDot.className || '').includes('dirty'),
        'Status dot must be marked as dirty after draft recovery'
    );
});

// =============================================================================
// TEST 5: Older or identical local draft does NOT trigger false recovery
// =============================================================================
test('On editor init, if local draft is older than server copy or identical, it does NOT restore', () => {
    const storage = new MockLocalStorage();
    const slug = 'delta-site';
    const activeFile = 'index.html';
    const draftKey = `pagedock_draft_${slug}_html`;
    const serverHtml = '<header>Canonical Server Header</header>';

    // Case A: Draft is OLDER than server copy (e.g. server was updated from another device)
    storage.setItem(draftKey, JSON.stringify({
        code: '<header>Old Stale Draft</header>',
        timestamp: 900000, // Older than initialTimestamp 1,000,000
        slug,
        type: 'html',
        file: activeFile
    }));

    const envA = createMockEditorEnvironment({
        slug,
        activeFile,
        initialHtml: serverHtml,
        initialTimestamp: 1000000,
        localStorage: storage
    });

    vm.runInNewContext(editorJsContent, envA.sandbox);

    assert.strictEqual(
        envA.cmInstance.getValue(),
        serverHtml,
        'CodeMirror should keep canonical server HTML when local draft is older'
    );
    assert.strictEqual(
        envA.elements['hh-toast'].textContent,
        '',
        'No recovery toast should be displayed for older draft'
    );

    // Case B: Draft is identical to server copy
    storage.setItem(draftKey, JSON.stringify({
        code: serverHtml,
        timestamp: 1050000,
        slug,
        type: 'html',
        file: activeFile
    }));

    const envB = createMockEditorEnvironment({
        slug,
        activeFile,
        initialHtml: serverHtml,
        initialTimestamp: 1000000,
        localStorage: storage
    });

    vm.runInNewContext(editorJsContent, envB.sandbox);

    assert.strictEqual(
        envB.cmInstance.getValue(),
        serverHtml,
        'CodeMirror should keep server HTML when draft code is identical'
    );
    assert.strictEqual(
        envB.elements['hh-toast'].textContent,
        '',
        'No recovery toast should be displayed for identical draft'
    );
});

// =============================================================================
// TEST 6: Save Draft clears/updates local draft so false recoveries don't fire
// =============================================================================
test('When user clicks Save Draft, localStorage draft is cleared/marked clean', async () => {
    const env = createMockEditorEnvironment({
        slug: 'save-site',
        activeFile: 'index.html',
        initialHtml: '<main>Initial</main>'
    });

    vm.runInNewContext(editorJsContent, env.sandbox);
    const draftKey = 'pagedock_draft_save-site_html';

    // Type new changes and wait for auto-save debounce
    env.triggerKeystroke('<main>New Saved Code</main>');
    env.clock.tick(1000);

    assert(env.localStorage.getItem(draftKey) !== null, 'Draft must exist prior to saving');

    // Click Save Draft button
    const saveBtn = env.elements['hh-savedraft-btn'];
    saveBtn.click();

    // Allow promise resolution
    await new Promise(resolve => setTimeout(resolve, 10));

    // Draft in localStorage should be cleared (or marked clean)
    const postSaveItem = env.localStorage.getItem(draftKey);
    const isClearedOrClean = !postSaveItem || JSON.parse(postSaveItem).clean === true;
    assert(
        isClearedOrClean,
        'localStorage draft must be cleared or marked clean on successful Save Draft'
    );
});

// =============================================================================
// TEST 7: Publish Site clears/updates local draft
// =============================================================================
test('When user clicks Publish, localStorage draft is cleared/marked clean', async () => {
    const env = createMockEditorEnvironment({
        slug: 'publish-site',
        activeFile: 'index.html',
        initialHtml: '<main>Ready To Publish</main>'
    });

    vm.runInNewContext(editorJsContent, env.sandbox);
    const draftKey = 'pagedock_draft_publish-site_html';

    // Type changes and wait for debounce
    env.triggerKeystroke('<main>Published Changes</main>');
    env.clock.tick(1000);
    assert(env.localStorage.getItem(draftKey) !== null, 'Draft must exist before publish');

    // Click Publish button
    const publishBtn = env.elements['hh-publish-btn'];
    publishBtn.click();

    await new Promise(resolve => setTimeout(resolve, 10));

    const postPublishItem = env.localStorage.getItem(draftKey);
    const isClearedOrClean = !postPublishItem || JSON.parse(postPublishItem).clean === true;
    assert(
        isClearedOrClean,
        'localStorage draft must be cleared or marked clean on successful Publish'
    );
});

// =============================================================================
// ASYNC TEST RUNNER
// =============================================================================
(async function runAll() {
    let passed = 0;
    let failed = 0;

    for (const t of tests) {
        try {
            await t.fn();
            passed++;
            console.log(`[PASS] ${t.name}`);
        } catch (err) {
            failed++;
            console.error(`[FAIL] ${t.name}`);
            console.error(`       Error: ${err.message}`);
        }
    }

    console.log('\n--- Test Summary ---');
    console.log(`Total: ${tests.length}`);
    console.log(`Passed: ${passed}`);
    console.log(`Failed: ${failed}`);

    if (failed > 0) {
        process.exit(1);
    }
})();
