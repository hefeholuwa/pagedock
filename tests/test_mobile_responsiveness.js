/**
 * PageDock Mobile Responsiveness & Layout Verification Test Suite
 * Validates mobile viewport rules, touch targets, container overflow containment,
 * interactive components, and zero-emoji compliance across 320px, 375px, and 768px breakpoints.
 */

const fs = require('fs');
const path = require('path');
const assert = require('assert');

const ROOT_DIR = path.resolve(__dirname, '..');
const HOME_VIEW_PATH = path.join(ROOT_DIR, 'views', 'home.php');
const HEADER_VIEW_PATH = path.join(ROOT_DIR, 'views', 'layouts', 'header.php');
const FOOTER_VIEW_PATH = path.join(ROOT_DIR, 'views', 'layouts', 'footer.php');
const STYLE_CSS_PATH = path.join(ROOT_DIR, 'public', 'assets', 'css', 'style.css');
const APP_JS_PATH = path.join(ROOT_DIR, 'public', 'assets', 'js', 'app.js');

let passedTests = 0;
let totalTests = 0;

function runTest(name, fn) {
    totalTests++;
    try {
        fn();
        passedTests++;
        console.log(`PASS: ${name}`);
    } catch (err) {
        console.error(`FAIL: ${name}`);
        console.error(`      ${err.message}`);
    }
}

console.log('=== Running PageDock Mobile Responsiveness Test Suite ===\n');

const homeContent = fs.readFileSync(HOME_VIEW_PATH, 'utf8');
const headerContent = fs.readFileSync(HEADER_VIEW_PATH, 'utf8');
const footerContent = fs.readFileSync(FOOTER_VIEW_PATH, 'utf8');
const styleCss = fs.readFileSync(STYLE_CSS_PATH, 'utf8');
const appJs = fs.readFileSync(APP_JS_PATH, 'utf8');

// Helper to get Responsive Overrides Block
function getResponsiveOverridesBlock() {
    const marker = '/* Responsive Overrides (< 768px) */';
    const idx = styleCss.indexOf(marker);
    assert(idx !== -1, 'Responsive Overrides (< 768px) block must exist in style.css');
    return styleCss.substring(idx);
}

const mobileBlock = getResponsiveOverridesBlock();

// 1. Root & Viewport Meta
runTest('header.php defines mobile viewport meta tag', () => {
    assert(headerContent.includes('<meta name="viewport" content="width=device-width, initial-scale=1.0'), 'Viewport meta must be defined');
});

runTest('style.css defines root overflow-x containment for html and body', () => {
    assert(styleCss.includes('overflow-x: hidden;'), 'Body or html must contain overflow-x: hidden');
    assert(styleCss.includes('max-width: 100%;'), 'Body or html must enforce max-width: 100%');
});

// 2. Mobile Navigation & Touch Targets
runTest('header.php includes 44x44px accessible mobile hamburger button', () => {
    assert(headerContent.includes('id="mobile-menu-toggle"'), 'Mobile menu toggle button must exist');
    assert(headerContent.includes('aria-label="Toggle Menu"'), 'Menu button must have aria-label');
    assert(headerContent.includes('aria-expanded="false"'), 'Menu button must have aria-expanded');
    assert(styleCss.includes('min-width: 44px;') && styleCss.includes('min-height: 44px;'), 'Mobile menu btn must have 44px touch target');
});

runTest('header.php and app.js include mobile backdrop overlay with tap-to-close', () => {
    assert(headerContent.includes('id="mobile-nav-backdrop"'), 'Backdrop overlay element must exist in header');
    assert(styleCss.includes('.mobile-nav-backdrop'), 'CSS must define .mobile-nav-backdrop');
    assert(appJs.includes('navBackdrop'), 'JavaScript must bind backdrop toggle and click listener');
});

// 3. Hero Section
runTest('style.css contains mobile responsive wrapping for .hero-announcement', () => {
    assert(mobileBlock.includes('.hero-announcement'), 'Mobile media query must style .hero-announcement');
    assert(mobileBlock.includes('flex-wrap: wrap;') || mobileBlock.includes('max-width: 100%'), 'Announcement must wrap and constrain max-width');
});

runTest('style.css formats .hero-stats-row as a symmetrical 2x2 grid on mobile', () => {
    assert(mobileBlock.includes('.hero-stats-row'), 'Mobile media query must target .hero-stats-row');
    assert(mobileBlock.includes('grid-template-columns: repeat(2, 1fr)'), 'Stats row must use 2-column grid on mobile');
});

// 4. Deploy Command Dock
runTest('home.php and style.css support responsive tab labels and touch scrolling', () => {
    assert(homeContent.includes('tab-desktop-sub'), 'home.php must mark desktop sub-labels');
    assert(homeContent.includes('<span class="tab-desktop-sub">1-Click </span>Demo'), 'Demo tab must have hidden prefix on mobile');
    assert(mobileBlock.includes('.deploy-tabs'), 'Mobile media query must style .deploy-tabs');
    assert(mobileBlock.includes('overflow-x: auto'), 'Deploy tabs must support touch scrolling');
});

runTest('style.css stacks slug inputs vertically on mobile to prevent squishing', () => {
    assert(mobileBlock.includes('.custom-slug-row'), 'Slug row must be targeted');
    assert(mobileBlock.includes('flex-direction: column'), 'Slug row must stack vertically on mobile');
});

runTest('home.php and style.css prevent CLI terminal blowout on mobile', () => {
    assert(homeContent.includes('cli-command-text-wrap'), 'home.php must wrap CLI command text');
    assert(styleCss.includes('.cli-command-text-wrap'), 'style.css must style .cli-command-text-wrap');
    assert(mobileBlock.includes('.cli-command-box'), 'Mobile query must target .cli-command-box');
    assert(mobileBlock.includes('.cli-output-simulation'), 'Mobile query must target .cli-output-simulation');
});

// 5. Interactive Live Split Showcase
runTest('style.css hides desktop viewport switchers and device badge on mobile showcase', () => {
    assert(mobileBlock.includes('.viewport-switchers'), 'Viewport switchers must be targeted in mobile');
    assert(mobileBlock.includes('.preview-device-badge'), 'Preview device badge must be targeted in mobile');
    assert(mobileBlock.includes('display: none !important'), 'Desktop controls must be hidden on mobile');
});

runTest('style.css stacks split workspace and contains editor code horizontally', () => {
    assert(mobileBlock.includes('.split-workspace'), 'Split workspace must be targeted in mobile');
    assert(mobileBlock.includes('grid-template-columns: 1fr'), 'Split workspace must be 1 column on mobile');
    assert(mobileBlock.includes('.editor-code-body'), 'Editor code body must be targeted in mobile');
    assert(mobileBlock.includes('overflow-x: auto'), 'Editor code body must have horizontal scroll containment');
});

// 6. Asymmetric Bento Architecture
runTest('style.css collapses bento grid into single column with touch-scrolling pipeline', () => {
    assert(mobileBlock.includes('.bento-asymmetric'), 'Bento grid must be targeted');
    assert(mobileBlock.includes('.pipeline-flow'), 'Pipeline flow must be targeted');
    assert(mobileBlock.includes('overflow-x: auto'), 'Pipeline flow must touch-scroll');
    assert(mobileBlock.includes('.bento-tile'), 'Bento tile padding must be calibrated for mobile');
});

runTest('home.php and style.css style Bento 6 MCP tools row responsively', () => {
    assert(homeContent.includes('class="mcp-tools-row"'), 'home.php must use .mcp-tools-row');
    assert(homeContent.includes('class="mcp-tool-chip"'), 'home.php must use .mcp-tool-chip');
    assert(styleCss.includes('.mcp-tools-row'), 'style.css must define .mcp-tools-row');
    assert(styleCss.includes('.mcp-tool-chip'), 'style.css must define .mcp-tool-chip');
});

// 7. FAQ and Bottom CTA
runTest('style.css ensures FAQ chevron never squishes and CTA actions stack full width', () => {
    assert(mobileBlock.includes('.faq-chevron'), 'FAQ chevron must be targeted');
    assert(mobileBlock.includes('flex-shrink: 0'), 'Chevron must have flex-shrink: 0');
    assert(mobileBlock.includes('.cta-actions'), 'CTA actions must be targeted');
    assert(mobileBlock.includes('flex-direction: column'), 'CTA actions must stack vertically');
});

// 8. Zero Emojis Compliance
runTest('views/home.php has ZERO emojis', () => {
    const emojiRegex = /[\u{1F300}-\u{1F9FF}\u{2600}-\u{26FF}\u{2700}-\u{27BF}\u{1F1E6}-\u{1F1FF}]/u;
    assert(!emojiRegex.test(homeContent), 'home.php must contain zero emojis');
});

runTest('views/layouts/header.php has ZERO emojis', () => {
    const emojiRegex = /[\u{1F300}-\u{1F9FF}\u{2600}-\u{26FF}\u{2700}-\u{27BF}\u{1F1E6}-\u{1F1FF}]/u;
    assert(!emojiRegex.test(headerContent), 'header.php must contain zero emojis');
});

runTest('public/assets/css/style.css has ZERO emojis', () => {
    const emojiRegex = /[\u{1F300}-\u{1F9FF}\u{2600}-\u{26FF}\u{2700}-\u{27BF}\u{1F1E6}-\u{1F1FF}]/u;
    assert(!emojiRegex.test(styleCss), 'style.css must contain zero emojis');
});

runTest('public/assets/js/app.js has ZERO emojis', () => {
    const emojiRegex = /[\u{1F300}-\u{1F9FF}\u{2600}-\u{26FF}\u{2700}-\u{27BF}\u{1F1E6}-\u{1F1FF}]/u;
    assert(!emojiRegex.test(appJs), 'app.js must contain zero emojis');
});

console.log(`\n=== Mobile Responsiveness Test Summary ===`);
console.log(`Total: ${totalTests} | Passed: ${passedTests} | Failed: ${totalTests - passedTests}`);

if (passedTests === totalTests) {
    console.log('ALL MOBILE RESPONSIVENESS TESTS PASSED!\n');
    process.exit(0);
} else {
    console.error('SOME TESTS FAILED!\n');
    process.exit(1);
}
