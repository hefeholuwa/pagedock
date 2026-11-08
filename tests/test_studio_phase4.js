/**
 * Test Suite: PageDock Visual Studio Phase 4
 * (Inter-Section Drop Zones, Component Palette Deepening, Undo/Redo Engine & Drag-and-Drop)
 */

const fs = require('fs');
const path = require('path');
const vm = require('vm');

console.log('=== Running PageDock Visual Studio Phase 4 Test Suite ===\n');

let passCount = 0;
let failCount = 0;

function assertTest(desc, condition) {
    if (condition) {
        console.log(`PASS: ${desc}`);
        passCount++;
    } else {
        console.error(`FAIL: ${desc}`);
        failCount++;
    }
}

// 1. Files existence & compilation
const studioJsPath = path.join(__dirname, '../public/assets/js/studio.js');
const studioPhpPath = path.join(__dirname, '../views/studio.php');
const studioCssPath = path.join(__dirname, '../public/assets/css/studio.css');

const studioJsCode = fs.readFileSync(studioJsPath, 'utf8');
const studioPhpCode = fs.readFileSync(studioPhpPath, 'utf8');
const studioCssCode = fs.readFileSync(studioCssPath, 'utf8');

assertTest('studio.js compiles cleanly without syntax errors', (() => {
    try {
        new vm.Script(studioJsCode);
        return true;
    } catch (e) {
        return false;
    }
})());

// 2. Inter-Section Drop Zone Engine
assertTest('studio.js injects inter-section drop zones between layout sections', 
    studioJsCode.includes('__pd_inter_zone') &&
    studioJsCode.includes('+ Add Section Here'));
assertTest('studio.js sets insertionTarget and insertionPosition on inter-zone click', 
    studioJsCode.includes('insertionTarget = sec') &&
    studioJsCode.includes("insertionPosition = 'before'"));
assertTest('studio.js preserves bottom drop zone for appending to page end', 
    studioJsCode.includes('__pd_add_section_bottom') &&
    studioJsCode.includes('+ Add New Section'));
assertTest('studio.js injects hover expansion CSS for __pd_inter_zone in overlayCss', 
    studioJsCode.includes('.__pd_inter_zone {') &&
    studioJsCode.includes('.__pd_inter_zone:hover {'));

// 3. Toolbar Target Anchoring
assertTest('Toolbar + Add action sets insertionTarget to selected element', 
    studioJsCode.includes('insertionTarget = el') &&
    studioJsCode.includes("insertionPosition = 'after'"));

// 4. Universal Insertion Engine
assertTest('insertHtmlSnippet prioritizes insertionTarget before/after positioning', 
    studioJsCode.includes('if (insertionTarget && insertionTarget.parentNode)') &&
    studioJsCode.includes('insertionPosition === \'before\''));
assertTest('insertHtmlSnippet falls back to selectedElement nextSibling', 
    studioJsCode.includes('selectedElement.parentNode.insertBefore(fragment, selectedElement.nextSibling)'));
assertTest('insertHtmlSnippet falls back to __pd_add_section_bottom', 
    studioJsCode.includes('iframeDoc.body.insertBefore(fragment, bottomZone)'));
assertTest('insertHtmlSnippet pushes state to undoStack', 
    studioJsCode.includes('pushHistoryState()'));
assertTest('insertHtmlSnippet selects newly inserted element', 
    studioJsCode.includes('selectElement(firstInserted)'));
assertTest('insertHtmlSnippet refreshes inter-section drop zones', 
    studioJsCode.includes('ensureAddSectionZone(iframeDoc)'));

// 5. Undo / Redo History Stack Engine
assertTest('studio.js initializes undoStack, redoStack and MAX_HISTORY', 
    studioJsCode.includes('let undoStack = []') &&
    studioJsCode.includes('let redoStack = []') &&
    studioJsCode.includes('MAX_HISTORY = 40'));
assertTest('studio.js implements pushHistoryState, undo and redo functions', 
    studioJsCode.includes('function pushHistoryState()') &&
    studioJsCode.includes('function undo()') &&
    studioJsCode.includes('function redo()'));
assertTest('views/studio.php contains topbar Undo and Redo buttons', 
    studioPhpCode.includes('id="act-undo-btn"') &&
    studioPhpCode.includes('id="act-redo-btn"'));
assertTest('studio.js binds act-undo-btn and act-redo-btn to undo and redo', 
    studioJsCode.includes("actUndoBtn.addEventListener('click', undo)") &&
    studioJsCode.includes("actRedoBtn.addEventListener('click', redo)"));
assertTest('studio.js binds keyboard shortcuts Ctrl+Z and Ctrl+Y / Ctrl+Shift+Z', 
    studioJsCode.includes("e.key.toLowerCase() === 'z'") &&
    studioJsCode.includes("e.key.toLowerCase() === 'y'"));

// 6. 12 Atomic Primitives & 7 Blueprints
assertTest('widgetSnippets dictionary defines all 12 atomic primitives', 
    studioJsCode.includes('container:') &&
    studioJsCode.includes("'columns-2':") &&
    studioJsCode.includes("'columns-3':") &&
    studioJsCode.includes("'columns-split':") &&
    studioJsCode.includes('heading:') &&
    studioJsCode.includes('text:') &&
    studioJsCode.includes('button:') &&
    studioJsCode.includes('image:') &&
    studioJsCode.includes('video:') &&
    studioJsCode.includes('divider:') &&
    studioJsCode.includes('spacer:') &&
    studioJsCode.includes("'icon-box':"));
assertTest('blueprints dictionary defines all 7 modular landing page sections', 
    studioJsCode.includes('navbar:') &&
    studioJsCode.includes('hero:') &&
    studioJsCode.includes("'features-3':") &&
    studioJsCode.includes('pricing:') &&
    studioJsCode.includes('testimonials:') &&
    studioJsCode.includes("'contact-form':") &&
    studioJsCode.includes('footer:'));
assertTest('All widget and blueprint cards have draggable="true" and transfer snippet payloads', 
    studioPhpCode.includes('draggable="true"') &&
    studioJsCode.includes("e.dataTransfer.setData('application/pagedock-snippet'"));

// 7. Clean Artifact Stripping
assertTest('syncElementToMasterHtml strips all inter-section zones and editor markers', 
    studioJsCode.includes('#__pd_element_toolbar, .__pd_add_section_zone, #__pd_add_section_bottom, #__pd_drop_indicator'));

// 8. Strict Zero-Emoji Compliance
const emojiRegex = /[\u{1F300}-\u{1F9FF}\u{2600}-\u{26FF}\u{2700}-\u{27BF}\u{2300}-\u{23FF}\u{2B50}-\u{2B55}]/u;
assertTest('views/studio.php has ZERO emojis', !emojiRegex.test(studioPhpCode));
assertTest('public/assets/js/studio.js has ZERO emojis', !emojiRegex.test(studioJsCode));
assertTest('public/assets/css/studio.css has ZERO emojis', !emojiRegex.test(studioCssCode));

console.log('\n=== Visual Studio Phase 4 Test Summary ===');
console.log(`Total: ${passCount + failCount} | Passed: ${passCount} | Failed: ${failCount}`);

if (failCount > 0) {
    console.error('SOME PHASE 4 TESTS FAILED!');
    process.exit(1);
} else {
    console.log('ALL VISUAL STUDIO PHASE 4 TESTS PASSED!\n');
    process.exit(0);
}

