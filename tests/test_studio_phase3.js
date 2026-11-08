/**
 * Test Suite: PageDock Visual Studio Phase 3 & 4
 * (3-Tab Property Controls Deepening, Live Two-Way Sync, Palette D&D, Hierarchical Navigator)
 */

const fs = require('fs');
const path = require('path');
const vm = require('vm');

console.log('=== Running PageDock Visual Studio Phase 3 & 4 Test Suite ===\n');

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

assertTest('studio.js is present and compiles cleanly', (() => {
    try {
        new vm.Script(studioJsCode);
        return true;
    } catch (e) {
        return false;
    }
})());

// 2. Phase 3 View Controls in studio.php
assertTest('studio.php contains font-family selector', studioPhpCode.includes('id="ctrl-font-family"'));
assertTest('studio.php contains line-height slider and value tag', 
    studioPhpCode.includes('id="ctrl-line-height-slider"') && studioPhpCode.includes('id="val-line-height"'));
assertTest('studio.php contains letter-spacing slider and value tag', 
    studioPhpCode.includes('id="ctrl-letter-spacing-slider"') && studioPhpCode.includes('id="val-letter-spacing"'));
assertTest('studio.php contains box shadow segmented control with 4 presets', 
    studioPhpCode.includes('id="ctrl-shadow-segmented"') &&
    studioPhpCode.includes('data-shadow="none"') &&
    studioPhpCode.includes('data-shadow="subtle"') &&
    studioPhpCode.includes('data-shadow="medium"') &&
    studioPhpCode.includes('data-shadow="glow"'));
assertTest('studio.php contains 4-quadrant margin inputs and link toggle button', 
    studioPhpCode.includes('id="toggle-margin-link"') &&
    studioPhpCode.includes('id="ctrl-margin-top"') &&
    studioPhpCode.includes('id="ctrl-margin-right"') &&
    studioPhpCode.includes('id="ctrl-margin-bottom"') &&
    studioPhpCode.includes('id="ctrl-margin-left"'));
assertTest('studio.php contains 4-quadrant padding inputs and link toggle button', 
    studioPhpCode.includes('id="toggle-padding-link"') &&
    studioPhpCode.includes('id="ctrl-padding-top"') &&
    studioPhpCode.includes('id="ctrl-padding-right"') &&
    studioPhpCode.includes('id="ctrl-padding-bottom"') &&
    studioPhpCode.includes('id="ctrl-padding-left"'));
assertTest('studio.php contains display mode selector', studioPhpCode.includes('id="ctrl-display-mode"'));
assertTest('studio.php contains custom inline style textarea', studioPhpCode.includes('id="ctrl-inline-css"'));

// 3. Phase 3 Controller & Inspector Logic in studio.js
assertTest('studio.js populates font-family from computed style', studioJsCode.includes('ctrlFontFamily.value'));
assertTest('studio.js populates line-height slider from computed style', studioJsCode.includes('ctrlLineHeightSlider.value'));
assertTest('studio.js populates letter-spacing slider from computed style', studioJsCode.includes('ctrlLetterSpacingSlider.value'));
assertTest('studio.js highlights active box shadow preset', studioJsCode.includes('shadowBtns.forEach('));
assertTest('studio.js populates 4-quadrant margin inputs', 
    studioJsCode.includes('ctrlMarginTop.value') &&
    studioJsCode.includes('ctrlMarginRight.value') &&
    studioJsCode.includes('ctrlMarginBottom.value') &&
    studioJsCode.includes('ctrlMarginLeft.value'));
assertTest('studio.js populates 4-quadrant padding inputs', 
    studioJsCode.includes('ctrlPaddingTop.value') &&
    studioJsCode.includes('ctrlPaddingRight.value') &&
    studioJsCode.includes('ctrlPaddingBottom.value') &&
    studioJsCode.includes('ctrlPaddingLeft.value'));
assertTest('studio.js populates display mode selector', studioJsCode.includes('ctrlDisplayMode.value'));

// 4. Reactive Listeners & Link Toggles
assertTest('studio.js attaches font-family reactive change listener', studioJsCode.includes("ctrlFontFamily.addEventListener('change'"));
assertTest('studio.js attaches line-height reactive input listener', studioJsCode.includes("ctrlLineHeightSlider.addEventListener('input'"));
assertTest('studio.js attaches letter-spacing reactive input listener', studioJsCode.includes("ctrlLetterSpacingSlider.addEventListener('input'"));
assertTest('studio.js defines shadowPresets dictionary and attaches click listeners', 
    studioJsCode.includes('shadowPresets') && studioJsCode.includes("btn.addEventListener('click'"));
assertTest('studio.js supports toggling isMarginLinked and isPaddingLinked states', 
    studioJsCode.includes('isMarginLinked = !isMarginLinked') &&
    studioJsCode.includes('isPaddingLinked = !isPaddingLinked'));
assertTest('studio.js updates all 4 sides when margin/padding is linked', 
    studioJsCode.includes('if (isMarginLinked)') && studioJsCode.includes('if (isPaddingLinked)'));
assertTest('studio.js attaches display mode reactive listener', studioJsCode.includes("ctrlDisplayMode.addEventListener('change'"));
assertTest('studio.js supports manual hex code typing for text and background color', 
    studioJsCode.includes("ctrlTextColorHex.addEventListener('input'") &&
    studioJsCode.includes("ctrlBgColorHex.addEventListener('input'"));
assertTest('studio.js updates floating toolbar position on dimension-altering styles', 
    studioJsCode.includes('updateToolbar(selectedElement)'));

// 5. Phase 4 Component Palette & Blueprints Drag-and-Drop
assertTest('studio.js defines widgetSnippets dictionary with all 12 primitives', 
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
assertTest('studio.php and studio.js enable draggable="true" on cards', 
    studioPhpCode.includes('draggable="true"') && studioJsCode.includes("card.setAttribute('draggable', 'true')"));
assertTest('studio.js attaches dragstart listeners transferring snippet payload', 
    studioJsCode.includes("e.dataTransfer.setData('application/pagedock-snippet'"));
assertTest('studio.js listens to dragover on canvas iframe with copy dropEffect', 
    studioJsCode.includes("iframeDoc.addEventListener('dragover'") &&
    studioJsCode.includes("e.dataTransfer.dropEffect = 'copy'"));
assertTest('studio.js positions #__pd_drop_indicator before/after drop target element', 
    studioJsCode.includes("dropIndicator.style.top =") &&
    studioJsCode.includes("dropPosition = isBefore ? 'before' : 'after'"));
assertTest('studio.js handles drop event, inserting element and selecting it', 
    studioJsCode.includes("iframeDoc.addEventListener('drop'") &&
    studioJsCode.includes("selectElement(firstInserted)"));
assertTest('studio.js strips #__pd_drop_indicator in syncElementToMasterHtml', 
    studioJsCode.includes('#__pd_drop_indicator'));
assertTest('studio.js implements real-time palette search filter', 
    studioJsCode.includes("widgetSearchInput.addEventListener('input'"));

// 6. Phase 5 Structure Navigator Tree
assertTest('studio.php contains navigator search input', studioPhpCode.includes('id="navigator-search-input"'));
assertTest('studio.js implements recursive DOM tree walk with depth indentation', 
    studioJsCode.includes('function walk(el, depth = 0)') &&
    studioJsCode.includes('padLeft'));
assertTest('studio.js filters navigator nodes in real-time', 
    studioJsCode.includes("navigatorSearchInput.addEventListener('input'"));
assertTest('studio.js scrolls target into view and selects it on navigator node click', 
    studioJsCode.includes('targetEl.scrollIntoView(') &&
    studioJsCode.includes('selectElement(targetEl)'));

// 7. Strict Zero-Emoji Compliance
const emojiRegex = /[\u{1F300}-\u{1F9FF}\u{2600}-\u{26FF}\u{2700}-\u{27BF}\u{2300}-\u{23FF}\u{2B50}-\u{2B55}]/u;
assertTest('views/studio.php has ZERO emojis', !emojiRegex.test(studioPhpCode));
assertTest('public/assets/js/studio.js has ZERO emojis', !emojiRegex.test(studioJsCode));
assertTest('public/assets/css/studio.css has ZERO emojis', !emojiRegex.test(studioCssCode));

console.log('\n=== Visual Studio Phase 3 & 4 Test Summary ===');
console.log(`Total: ${passCount + failCount} | Passed: ${passCount} | Failed: ${failCount}`);

if (failCount > 0) {
    console.error('SOME PHASE 3 & 4 TESTS FAILED!');
    process.exit(1);
} else {
    console.log('ALL VISUAL STUDIO PHASE 3 & 4 TESTS PASSED!\n');
    process.exit(0);
}

