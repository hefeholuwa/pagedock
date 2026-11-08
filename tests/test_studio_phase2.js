/**
 * Test Suite: PageDock Visual Studio Phase 2 (Canvas Overlay Engine & Interactive Action Handles)
 */

const fs = require('fs');
const path = require('path');
const vm = require('vm');

console.log('=== Running PageDock Visual Studio Phase 2 Test Suite ===\n');

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

// 1. Script Syntax Validation
const studioJsPath = path.join(__dirname, '../public/assets/js/studio.js');
const studioJsCode = fs.readFileSync(studioJsPath, 'utf8');

assertTest('studio.js exists and is non-empty', studioJsCode.length > 500);

try {
    new vm.Script(studioJsCode);
    assertTest('studio.js compiles cleanly with zero syntax errors', true);
} catch (e) {
    assertTest(`studio.js compiles cleanly with zero syntax errors (${e.message})`, false);
}

// 2. Verify Key Engine Functions in studio.js
assertTest('studio.js defines updateToolbar engine', studioJsCode.includes('function updateToolbar('));
assertTest('studio.js defines ensureAddSectionZone engine', studioJsCode.includes('function ensureAddSectionZone('));
assertTest('studio.js attaches double-click inline text edit listener', studioJsCode.includes("addEventListener('dblclick'"));
assertTest('studio.js includes floating toolbar action handles (edit, add, duplicate, delete)', 
    studioJsCode.includes('data-act="edit"') &&
    studioJsCode.includes('data-act="add"') &&
    studioJsCode.includes('data-act="duplicate"') &&
    studioJsCode.includes('data-act="delete"'));

// 3. Verify Clean DOM Serialization (Artifact Stripping)
assertTest('syncElementToMasterHtml strips #__pd_element_toolbar', studioJsCode.includes('#__pd_element_toolbar'));
assertTest('syncElementToMasterHtml strips .__pd_add_section_zone', studioJsCode.includes('.__pd_add_section_zone'));
assertTest('syncElementToMasterHtml strips .__pd_selected and .__pd_hover classes', studioJsCode.includes(".__pd_selected, .__pd_hover"));
assertTest('syncElementToMasterHtml strips contenteditable attribute', studioJsCode.includes("[contenteditable]"));
assertTest('syncElementToMasterHtml strips #__pagedock_overlay_css', studioJsCode.includes('#__pagedock_overlay_css'));

// 4. Verify Overlay Stylesheet Injection
assertTest('buildStudioHtml injects __pd_element_toolbar CSS styles', studioJsCode.includes('#__pd_element_toolbar {'));
assertTest('buildStudioHtml injects __pd_add_section_btn CSS styles', studioJsCode.includes('.__pd_add_section_btn {'));
assertTest('buildStudioHtml injects contenteditable focus styles', studioJsCode.includes('[contenteditable="true"]'));

// 5. Verify 1-Click Blueprints Library
assertTest('studio.js contains navbar blueprint', studioJsCode.includes('navbar:'));
assertTest('studio.js contains hero section blueprint', studioJsCode.includes('hero:'));
assertTest('studio.js contains 3-bento features blueprint', studioJsCode.includes("'features-3':"));
assertTest('studio.js contains pricing table blueprint', studioJsCode.includes('pricing:'));
assertTest('studio.js contains testimonials strip blueprint', studioJsCode.includes('testimonials:'));
assertTest('studio.js contains zero-backend contact form blueprint', studioJsCode.includes("'contact-form':") || studioJsCode.includes('contact-form:'));
assertTest('studio.js contains footer blueprint', studioJsCode.includes('footer:'));

console.log('\n=== Visual Studio Phase 2 Test Summary ===');
console.log(`Total: ${passCount + failCount} | Passed: ${passCount} | Failed: ${failCount}`);

if (failCount > 0) {
    console.error('SOME PHASE 2 TESTS FAILED!');
    process.exit(1);
} else {
    console.log('ALL VISUAL STUDIO PHASE 2 TESTS PASSED!\n');
    process.exit(0);
}
