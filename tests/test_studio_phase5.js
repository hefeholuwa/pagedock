/**
 * Test Suite: PageDock Visual Studio Phase 5
 * (Bidirectional Code & Visual Sync Polish, Dirty-State Guards & Revision Badges)
 */

const fs = require('fs');
const path = require('path');
const vm = require('vm');

console.log('=== Running PageDock Visual Studio Phase 5 Test Suite ===\n');

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
const editorJsPath = path.join(__dirname, '../public/assets/js/editor.js');
const studioPhpPath = path.join(__dirname, '../views/studio.php');
const editorPhpPath = path.join(__dirname, '../views/editor.php');
const studioCssPath = path.join(__dirname, '../public/assets/css/studio.css');

const studioJsCode = fs.readFileSync(studioJsPath, 'utf8');
const editorJsCode = fs.readFileSync(editorJsPath, 'utf8');
const studioPhpCode = fs.readFileSync(studioPhpPath, 'utf8');
const editorPhpCode = fs.readFileSync(editorPhpPath, 'utf8');
const studioCssCode = fs.readFileSync(studioCssPath, 'utf8');

assertTest('studio.js compiles cleanly without syntax errors', (() => {
    try {
        new vm.Script(studioJsCode);
        return true;
    } catch (e) {
        return false;
    }
})());

assertTest('editor.js compiles cleanly without syntax errors', (() => {
    try {
        new vm.Script(editorJsCode);
        return true;
    } catch (e) {
        return false;
    }
})());

// 2. Multi-Page Support in Visual Studio Topbar
assertTest('views/studio.php contains #studio-page-switcher dropdown when multi-page', 
    studioPhpCode.includes('id="studio-page-switcher"') &&
    studioPhpCode.includes('count($htmlFiles) > 1'));
assertTest('studio.css defines .studio-page-select styling', 
    studioCssCode.includes('.studio-page-select {'));
assertTest('studio.js binds #studio-page-switcher with dirty state check', 
    studioJsCode.includes("document.getElementById('studio-page-switcher')") &&
    studioJsCode.includes('confirm(\'You have unsaved changes in Visual Studio. Discard and switch page?\')'));

// 3. Bidirectional File Anchor Preservation
assertTest('views/studio.php links to code editor preserving active ?file= parameter', 
    studioPhpCode.includes('id="goto-editor-btn"') &&
    studioPhpCode.includes('editor/\' . $site[\'slug\'] . \'?file=\' . urlencode($activeFile'));
assertTest('views/editor.php links to visual studio preserving active ?file= parameter', 
    editorPhpCode.includes('id="goto-studio-btn"') &&
    editorPhpCode.includes('studio/\' . $site[\'slug\'] . \'?file=\' . urlencode($activeFile'));

// 4. Dirty-State Guards & Navigation Protection
assertTest('studio.js warns user before switching to code editor if dirty', 
    studioJsCode.includes("gotoEditorBtn.addEventListener('click'") &&
    studioJsCode.includes('confirm(\'You have unsaved changes in Visual Studio. Discard and switch to the code editor?\')'));
assertTest('studio.js registers window beforeunload listener when isDirty is true', 
    studioJsCode.includes("window.addEventListener('beforeunload'") &&
    studioJsCode.includes('if (isDirty)'));
assertTest('editor.js warns user before switching to visual studio if dirty', 
    editorJsCode.includes("gotoStudioBtn.addEventListener('click'") &&
    editorJsCode.includes('confirm(\'You have unsaved changes in the code editor. Discard and switch to Visual Studio?\')'));
assertTest('editor.js registers window beforeunload listener when isDirty is true', 
    editorJsCode.includes("window.addEventListener('beforeunload'") &&
    editorJsCode.includes('if (isDirty)'));

// 5. Revision Badge Feedback on Save
assertTest('studio.js updates saveStatus with version number (Saved (vN))', 
    studioJsCode.includes('data.version ? `Saved (v${data.version})` : \'Saved\''));
assertTest('studio.js flashes toast with published revision number', 
    studioJsCode.includes('data.version ? `Published revision v${data.version} successfully!` : \'Published successfully!\''));

// 6. Strict Zero-Emoji Compliance Across All Studio & Editor Files
const emojiRegex = /[\u{1F300}-\u{1F9FF}\u{2600}-\u{26FF}\u{2700}-\u{27BF}\u{2300}-\u{23FF}\u{2B50}-\u{2B55}]/u;
assertTest('views/studio.php has ZERO emojis', !emojiRegex.test(studioPhpCode));
assertTest('views/editor.php has ZERO emojis', !emojiRegex.test(editorPhpCode));
assertTest('public/assets/js/studio.js has ZERO emojis', !emojiRegex.test(studioJsCode));
assertTest('public/assets/js/editor.js has ZERO emojis', !emojiRegex.test(editorJsCode));
assertTest('public/assets/css/studio.css has ZERO emojis', !emojiRegex.test(studioCssCode));

console.log('\n=== Visual Studio Phase 5 Test Summary ===');
console.log(`Total: ${passCount + failCount} | Passed: ${passCount} | Failed: ${failCount}`);

if (failCount > 0) {
    console.error('SOME PHASE 5 TESTS FAILED!');
    process.exit(1);
} else {
    console.log('ALL VISUAL STUDIO PHASE 5 TESTS PASSED!\n');
    process.exit(0);
}

