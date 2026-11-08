/**
 * PageDock AI Editor - Library Drag-to-Position Insertion Test Suite
 */

const fs = require('fs');
const path = require('path');
const assert = require('assert');

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

console.log('=== Running PageDock Library Drag-to-Position Insertion Test Suite ===\n');

assert(fs.existsSync(HARNESS_PATH), 'pagedock-harness.js must exist');
assert(fs.existsSync(EDITOR_JS_PATH), 'editor.js must exist');

const harnessSrc = fs.readFileSync(HARNESS_PATH, 'utf8');
const editorJsSrc = fs.readFileSync(EDITOR_JS_PATH, 'utf8');

// Test 1: Library cards are rendered with draggable="true"
runTest('editor.js renders library cards with draggable="true" and dragstart handler', () => {
    assert(editorJsSrc.includes('draggable = true') || editorJsSrc.includes("card.draggable = true") || editorJsSrc.includes('setAttribute(\'draggable\''), 'library cards must be marked draggable');
    assert(editorJsSrc.includes('dragstart'), 'library cards must have dragstart listener');
});

// Test 2: pagedock-harness.js handles dragover and drop for library snippets
runTest('pagedock-harness.js listens for library dragover and drop events', () => {
    assert(harnessSrc.includes("'dragover'") || harnessSrc.includes('"dragover"'), 'harness must listen for dragover');
    assert(harnessSrc.includes("'drop'") || harnessSrc.includes('"drop"'), 'harness must listen for drop');
    assert(harnessSrc.includes('library-drop') || harnessSrc.includes('element-append') || harnessSrc.includes('snippet'), 'harness must process dropped snippet');
});

// Test 3: editor.js handles snippet insertion at targeted position in code
runTest('editor.js inserts snippet at targeted section/cursor position or via message', () => {
    assert(
        editorJsSrc.includes('insertSnippetAtTarget') || editorJsSrc.includes('insertSnippetIntoPage') || editorJsSrc.includes('handleLibraryDrop'),
        'editor.js must have snippet insertion logic'
    );
    assert(
        editorJsSrc.includes('library-drop') || editorJsSrc.includes('element-append') || editorJsSrc.includes('dragTarget'),
        'editor.js must support positional snippet drop'
    );
});

console.log(`\n--- Test Summary ---`);
console.log(`Total: ${totalTests}`);
console.log(`Passed: ${passedTests}`);
console.log(`Failed: ${failedTests}`);

if (failedTests > 0) {
    process.exit(1);
}
