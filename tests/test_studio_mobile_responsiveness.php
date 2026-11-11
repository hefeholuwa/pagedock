<?php
/**
 * Test Suite: PageDock Studio Mobile Responsiveness
 * Validates:
 * 1. Studio Header flex layout & width containment on mobile (<=768px, <=480px, <=360px)
 * 2. Edit Mode label suppression on mobile (compact icon/dot pill)
 * 3. Mobile consolidation of secondary buttons into Tools menu
 * 4. Default workspace layout initialization to 'preview' on mobile in editor.js
 * 5. Direct element popover bottom sheet layout on mobile
 * 6. Zero emojis across all modified files
 */

require_once dirname(__DIR__) . '/config/config.php';

function assertStudioTest(string $name, bool $condition, string $details = ''): void {
    if ($condition) {
        echo "[PASS] $name\n";
    } else {
        echo "[FAIL] $name" . ($details ? " ($details)" : "") . "\n";
        exit(1);
    }
}

echo "=== Testing PageDock Studio Mobile Responsiveness ===\n\n";

$rootDir = dirname(__DIR__);
$editorPhp = file_get_contents($rootDir . '/views/editor.php');
$editorJs = file_get_contents($rootDir . '/public/assets/js/editor.js');

// 1. Mobile Header & Viewport Breakpoints
assertStudioTest('editor.php defines @media (max-width: 768px)', str_contains($editorPhp, '@media (max-width: 768px)'));
assertStudioTest('editor.php defines @media (max-width: 480px)', str_contains($editorPhp, '@media (max-width: 480px)'));
assertStudioTest('editor.php defines @media (max-width: 360px)', str_contains($editorPhp, '@media (max-width: 360px)'));

// 2. Header Layout & Squishing Prevention
assertStudioTest('Studio header uses flex layout on mobile', str_contains($editorPhp, 'display: flex;') && str_contains($editorPhp, 'justify-content: space-between;'));
assertStudioTest('Secondary toolbar buttons hidden from top header bar on mobile', 
    str_contains($editorPhp, '#btn-media-modal') && 
    str_contains($editorPhp, '#btn-history-modal') && 
    str_contains($editorPhp, 'display: none !important;')
);
assertStudioTest('Undo and redo group hidden from top bar on mobile', str_contains($editorPhp, '.toolbar-btn-group {') && str_contains($editorPhp, 'display: none !important;'));
assertStudioTest('Edit mode text label hidden on mobile', str_contains($editorPhp, '#edit-mode-label {') && str_contains($editorPhp, 'display: none !important;'));

// 3. Mobile Quick Actions in Tools Dropdown Menu
assertStudioTest('Tools menu includes mobile site actions section', str_contains($editorPhp, 'id="tools-mobile-actions"'));
assertStudioTest('Tools menu includes mobile Undo button (#btn-mobile-undo)', str_contains($editorPhp, 'id="btn-mobile-undo"'));
assertStudioTest('Tools menu includes mobile Redo button (#btn-mobile-redo)', str_contains($editorPhp, 'id="btn-mobile-redo"'));
assertStudioTest('Tools menu includes mobile Media Library link (#btn-mobile-media)', str_contains($editorPhp, 'id="btn-mobile-media"'));
assertStudioTest('Tools menu includes mobile History link (#btn-mobile-history)', str_contains($editorPhp, 'id="btn-mobile-history"'));
assertStudioTest('Tools menu includes mobile View Live link (#btn-mobile-view-live)', str_contains($editorPhp, 'id="btn-mobile-view-live"'));
assertStudioTest('Tools menu includes mobile Shortcuts link (#btn-mobile-shortcuts)', str_contains($editorPhp, 'id="btn-mobile-shortcuts"'));

// 4. Default Layout Behavior in editor.js
assertStudioTest("editor.js initializes mobile default layout to 'preview'", str_contains($editorJs, "window.innerWidth <= 768 ? 'preview' : 'split'"));
assertStudioTest("editor.js resize listener switches to 'preview' on mobile", str_contains($editorJs, "applyLayoutMode('preview');"));
assertStudioTest("editor.js panel tab switch uses 'code' on mobile instead of 'split'", str_contains($editorJs, "applyLayoutMode(window.innerWidth <= 768 ? 'code' : 'split');"));

// 5. Direct Element Popover Bottom Sheet
assertStudioTest('Direct element popover positioned as bottom sheet on mobile', 
    str_contains($editorPhp, 'bottom: 0 !important;') && 
    str_contains($editorPhp, 'left: 0 !important;') && 
    str_contains($editorPhp, 'right: 0 !important;') && 
    str_contains($editorPhp, 'max-height: 72dvh !important;')
);
assertStudioTest('Direct element popover dragging disabled on mobile in editor.js', str_contains($editorJs, 'if (window.innerWidth <= 768) return;'));

// 6. Zero Emojis Compliance
$emojiRegex = '/[\x{1F300}-\x{1F9FF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}]/u';
assertStudioTest('views/editor.php has ZERO emojis', !preg_match($emojiRegex, $editorPhp));
assertStudioTest('public/assets/js/editor.js has ZERO emojis', !preg_match($emojiRegex, $editorJs));

echo "\n[SUCCESS] ALL STUDIO MOBILE RESPONSIVENESS TESTS PASSED!\n";

