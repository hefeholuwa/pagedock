<?php
/**
 * Test Suite: Pure HTML & Visual Editor (Zero-AI Edition)
 * Verifies:
 * 1. Zero AI bloat in views/editor.php and public/assets/js/editor.js
 * 2. Injected Preview Harness (public/assets/js/pagedock-preview-harness.js) protocol
 * 3. Left Panel 4-Tab Architecture (Code, Library, Structure, Shared)
 * 4. Direct Element Edit Popover (Text, Link, Image, Background, Video, Map, Form, Text Style, Parent/Duplicate/Code/Delete)
 * 5. Right-Click Context Menu (Copy, Cut, Paste After, Paste Inside, Paste Style, Move Up/Down, Hide in Editor, Delete)
 * 6. Built-in Deterministic Developer Tools Suite (public/assets/js/editor-tools.js)
 * 7. Keyboard Shortcuts Modal, Media Library Modal, Version History Modal
 * 8. Zero Emojis Policy across all modified editor files
 */

require_once dirname(__DIR__) . '/config/config.php';

function assertEditorTest(string $name, bool $condition, string $details = ''): void {
    if ($condition) {
        echo "[PASS] $name\n";
    } else {
        echo "[FAIL] $name" . ($details ? " ($details)" : "") . "\n";
        exit(1);
    }
}

echo "=== Testing PageDock Pure HTML & Visual Editor (Zero-AI Edition) ===\n\n";

$rootDir = dirname(__DIR__);
$editorPhpPath = $rootDir . '/views/editor.php';
$editorJsPath = $rootDir . '/public/assets/js/editor.js';
$harnessJsPath = $rootDir . '/public/assets/js/pagedock-preview-harness.js';
$toolsJsPath = $rootDir . '/public/assets/js/editor-tools.js';

assertEditorTest('views/editor.php exists', file_exists($editorPhpPath));
assertEditorTest('public/assets/js/editor.js exists', file_exists($editorJsPath));
assertEditorTest('public/assets/js/pagedock-preview-harness.js exists', file_exists($harnessJsPath));
assertEditorTest('public/assets/js/editor-tools.js exists', file_exists($toolsJsPath));

$editorPhp = file_get_contents($editorPhpPath);
$editorJs = file_get_contents($editorJsPath);
$harnessJs = file_get_contents($harnessJsPath);
$toolsJs = file_get_contents($toolsJsPath);

// 1. Verify Zero AI Bloat in Editor
$forbiddenAiPatterns = [
    '/api/ai/builtin',
    '/api/ai/task',
    '/api/ai/proxy',
    'ELEMENT_PROMPT',
    'PAGE_EDIT_PROMPT',
    'gemini-3.5-flash',
    'claude-sonnet',
    'AI Edit Popover'
];
foreach ($forbiddenAiPatterns as $pat) {
    assertEditorTest("Zero AI pattern '$pat' in editor.php", !str_contains($editorPhp, $pat));
    assertEditorTest("Zero AI pattern '$pat' in editor.js", !str_contains($editorJs, $pat));
}

// 2. Verify Top Toolbar Controls (Undo, Redo, Edit Mode On/Off, Tools Dropdown, History, Media, Shortcuts)
assertEditorTest('Toolbar has Undo button (#btn-undo)', str_contains($editorPhp, 'id="btn-undo"'));
assertEditorTest('Toolbar has Redo button (#btn-redo)', str_contains($editorPhp, 'id="btn-redo"'));
assertEditorTest('Toolbar has Edit Mode switch (#btn-edit-mode)', str_contains($editorPhp, 'id="btn-edit-mode"'));
assertEditorTest('Toolbar has Tools dropdown button (#btn-tools-menu)', str_contains($editorPhp, 'id="btn-tools-menu"'));
assertEditorTest('Toolbar has Media Library button (#btn-media-modal)', str_contains($editorPhp, 'id="btn-media-modal"'));
assertEditorTest('Toolbar has Version History button (#btn-history-modal)', str_contains($editorPhp, 'id="btn-history-modal"'));
assertEditorTest('Toolbar has Keyboard Shortcuts button (#btn-shortcuts-modal)', str_contains($editorPhp, 'id="btn-shortcuts-modal"'));

// 3. Verify Left Collapsible Panel 4-Tab Architecture (Code, Library, Structure, Shared)
assertEditorTest('Left panel has Code tab (data-panel-tab="code")', str_contains($editorPhp, 'data-panel-tab="code"'));
assertEditorTest('Left panel has Library tab (data-panel-tab="library")', str_contains($editorPhp, 'data-panel-tab="library"'));
assertEditorTest('Left panel has Structure tab (data-panel-tab="structure")', str_contains($editorPhp, 'data-panel-tab="structure"'));
assertEditorTest('Left panel has Shared tab (data-panel-tab="shared")', str_contains($editorPhp, 'data-panel-tab="shared"'));

// 4. Verify Direct Element Edit Popover (#direct-element-popover)
assertEditorTest('Direct Element Edit Popover container exists (#direct-element-popover)', str_contains($editorPhp, 'id="direct-element-popover"'));
assertEditorTest('Popover supports text editing & double-click in-place trigger', str_contains($editorPhp, 'id="btn-inline-text-edit"'));
assertEditorTest('Popover supports link href & target=_blank controls', str_contains($editorPhp, 'id="de-link-href"') && str_contains($editorPhp, 'id="de-link-blank"'));
assertEditorTest('Popover supports image replacement & alt text', str_contains($editorPhp, 'id="btn-de-replace-image"') && str_contains($editorPhp, 'id="de-image-alt"'));
assertEditorTest('Popover supports background photo replacement', str_contains($editorPhp, 'id="btn-de-replace-bg"'));
assertEditorTest('Popover supports video embed & map address controls', str_contains($editorPhp, 'id="de-video-url"') && str_contains($editorPhp, 'id="de-map-address"'));
assertEditorTest('Popover supports form connection & settings controls', str_contains($editorPhp, 'id="btn-de-connect-form"') && str_contains($editorPhp, 'id="de-form-name"'));
assertEditorTest('Popover supports text style controls (color, font-size, font-weight, text-align)', str_contains($editorPhp, 'id="de-style-color"') && str_contains($editorPhp, 'id="de-style-size"') && str_contains($editorPhp, 'id="de-style-weight"'));
assertEditorTest('Popover footer has Parent, Duplicate, Code, and Delete actions', str_contains($editorPhp, 'data-de-action="select-parent"') && str_contains($editorPhp, 'data-de-action="duplicate"') && str_contains($editorPhp, 'data-de-action="show-source"') && str_contains($editorPhp, 'data-de-action="delete"'));

// 5. Verify Right-Click Context Menu (#element-context-menu)
assertEditorTest('Right-Click Context Menu exists (#element-context-menu)', str_contains($editorPhp, 'id="element-context-menu"'));
foreach (['copy', 'cut', 'paste-after', 'paste-inside', 'paste-style', 'duplicate', 'move-up', 'move-down', 'select-parent', 'hide-element', 'show-source', 'reveal-structure', 'delete'] as $ctxAct) {
    assertEditorTest("Context menu includes action '$ctxAct'", str_contains($editorPhp, 'data-ctx-action="' . $ctxAct . '"'));
}

// 6. Verify Injected Preview Harness (pagedock-preview-harness.js)
foreach (['data-pagedock-ui', 'element-selected', 'element-context-menu', 'element-reorder', 'element-text-edit', 'page-structure', 'begin-text-edit', 'patch-element', 'live-patch', 'select-element', 'hover-element', 'reveal-element', 'hide-elements', 'form-preview-submit', 'iframe-key', 'library-drag', 'library-drop'] as $msgType) {
    assertEditorTest("Preview harness implements '$msgType'", str_contains($harnessJs, $msgType));
}

// 6b. Verify Drag-and-Drop Engine (Canvas Reorder + Library Drop + Structure Tree Drag + Token Adaptation)
foreach (['function mkGhost', 'function mkInd', 'function isRow', 'function calcDrop', 'function posInd', 'function edgeScroll', 'function canHold', 'function findDropTarget', 'function showLibDropInd', 'function pageTokens', "addEventListener('pointerdown'", "addEventListener('pointermove'", "addEventListener('pointerup'"] as $fnSig) {
    assertEditorTest("Preview harness implements drag-and-drop primitive '$fnSig'", str_contains($harnessJs, $fnSig));
}
foreach (['function reorderChildInSource', 'function adaptSnippetToTokens', 'function insertSnippetAtSlot', "case 'element-reorder':", "case 'library-drop':", 'card.draggable = true', 'row.draggable ='] as $edSig) {
    assertEditorTest("Editor controller implements drag-and-drop handler '$edSig'", str_contains($editorJs, $edSig));
}

// 7. Verify Deterministic Developer Tools & Component Library (editor-tools.js)
foreach (['format-code', 'compress-minify', 'clean-empty-tags', 'purge-unused-css', 'extract-inline-css', 'extract-inline-js', 'convert-monolith', 'inject-seo-boilerplate', 'generate-sitemap', 'normalize-links', 'force-https'] as $toolId) {
    assertEditorTest("Developer Tools suite includes '$toolId'", str_contains($toolsJs, $toolId));
}
assertEditorTest('Component Library includes Sections catalog (LIBRARY_SECTIONS)', str_contains($toolsJs, 'LIBRARY_SECTIONS'));
assertEditorTest('Component Library includes Elements catalog (LIBRARY_ELEMENTS)', str_contains($toolsJs, 'LIBRARY_ELEMENTS'));

// 8. Verify Zero Emojis across all editor files
$emojiRegex = '/[\x{1F300}-\x{1F9FF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}]/u';
$specMd = file_get_contents($rootDir . '/HTMLHOST_AI_EDITOR_REVERSE_ENGINEERED.md');
assertEditorTest('HTMLHOST_AI_EDITOR_REVERSE_ENGINEERED.md has ZERO emojis', !preg_match($emojiRegex, $specMd));
assertEditorTest('HTMLHOST_AI_EDITOR_REVERSE_ENGINEERED.md has ZERO /api/ai/ endpoints', !str_contains($specMd, '/api/ai/'));
assertEditorTest('views/editor.php has ZERO emojis', !preg_match($emojiRegex, $editorPhp));
assertEditorTest('public/assets/js/editor.js has ZERO emojis', !preg_match($emojiRegex, $editorJs));
assertEditorTest('public/assets/js/pagedock-preview-harness.js has ZERO emojis', !preg_match($emojiRegex, $harnessJs));
assertEditorTest('public/assets/js/editor-tools.js has ZERO emojis', !preg_match($emojiRegex, $toolsJs));

echo "\n[SUCCESS] ALL PURE HTML & VISUAL EDITOR TESTS PASSED!\n";
