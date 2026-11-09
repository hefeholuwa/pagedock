const assert = require('assert');
const fs = require('fs');
const path = require('path');

console.log('=== Running Toolbar Singleton & Multi-page Preview Test Suite ===\n');
let passedTests = 0;
let failedTests = 0;

function runTest(name, fn) {
    try {
        fn();
        console.log(`[PASS] ${name}`);
        passedTests++;
    } catch (err) {
        console.error(`[FAIL] ${name}`);
        console.error(`       ${err.message}`);
        failedTests++;
    }
}

const harnessCode = fs.readFileSync(path.join(__dirname, '../public/assets/js/pagedock-harness.js'), 'utf8');
const editorCss = fs.readFileSync(path.join(__dirname, '../public/assets/css/editor.css'), 'utf8');
const editorPhp = fs.readFileSync(path.join(__dirname, '../views/editor.php'), 'utf8');

runTest('Test 1: Static block overlay (.pd-element-overlay) is hidden and does not show on hover', () => {
    // In harness styles:
    assert(harnessCode.includes('.pd-element-overlay {\n        display: none !important;'),
        'pagedock-harness.js must suppress .pd-element-overlay with display: none !important');
    assert(!harnessCode.includes('.pd-has-overlay:hover > .pd-element-overlay .pd-element-toolbar'),
        'pagedock-harness.js must NOT show block toolbar on hover');

    // In editor.css:
    assert(editorCss.includes('.pd-element-overlay {\n    display: none !important;'),
        'editor.css must suppress .pd-element-overlay with display: none !important');
    assert(!editorCss.includes('.pd-has-overlay:hover > .pd-element-overlay .pd-element-toolbar'),
        'editor.css must NOT show block toolbar on hover');
});

runTest('Test 2: Canva selection toolbar is the sole active floating micro-toolbar', () => {
    assert(harnessCode.includes('.pd-selection-overlay .pd-element-toolbar'),
        'pagedock-harness.js must define .pd-selection-overlay .pd-element-toolbar');
    assert(harnessCode.includes('pd-selection-overlay'),
        'pagedock-harness.js must use pd-selection-overlay for active element selection');
});

runTest('Test 3: Selection toolbar includes Add Component Below (.pd-tool-add)', () => {
    assert(harnessCode.includes('pd-tool-add') && harnessCode.includes('Add Component Below'),
        'selection toolbar must include pd-tool-add button');
    assert(harnessCode.includes('open-library-insert'),
        'pd-tool-add button must dispatch open-library-insert message');
});

runTest('Test 4: views/editor.php dynamically binds preview iframe and URL bar to $activeFile', () => {
    assert(editorPhp.includes('$previewSubPath = (!empty($activeFile) && $activeFile !== \'index.html\')'),
        'views/editor.php must compute $previewSubPath based on $activeFile');
    assert(editorPhp.includes('preview-iframe') && editorPhp.includes('src="<?= htmlspecialchars($previewSrc) ?>"'),
        'preview-iframe src must use $previewSrc');
    assert(editorPhp.includes('<span class="hh-canvas-url"><?= htmlspecialchars($previewBaseUrl) ?></span>'),
        'hh-canvas-url must display $previewBaseUrl');
    assert(editorPhp.includes('previewUrl: \'<?= addslashes($previewBaseUrl) ?>\''),
        'window.PAGEDOCK_CONFIG.previewUrl must match $previewBaseUrl');
});

console.log('\n--- Test Summary ---');
console.log(`Total: ${passedTests + failedTests}`);
console.log(`Passed: ${passedTests}`);
console.log(`Failed: ${failedTests}`);

if (failedTests > 0) {
    process.exit(1);
}
