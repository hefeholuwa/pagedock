<?php
/**
 * PageDock Studio — Pure HTML & Visual Editor (Zero-AI Edition)
 * Optimized for Desktop (resizable split + proportional device scaling + visual inspector),
 * Tablet, and Mobile workspaces.
 */
$slug = $site['slug'];
$liveUrl = url('s/' . $slug . '/');
$claimToken = $site['claim_token'];
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= htmlspecialchars($title ?? 'PageDock Studio') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="icon" type="image/svg+xml" href="<?= asset('favicon.svg') ?>">
    <link rel="shortcut icon" href="<?= asset('favicon.svg') ?>">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/ace/1.32.7/ace.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/ace/1.32.7/ext-language_tools.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <style>
        :root {
            --studio-bg: #f4f7f5;
            --studio-surface: #ffffff;
            --studio-surface-subtle: #f8faf9;
            --studio-border: #dce5e0;
            --studio-border-strong: #c5d4cc;
            --studio-accent: #047857;
            --studio-accent-hover: #065f46;
            --studio-accent-soft: #e6f4ee;
            --studio-text: #0b1411;
            --studio-muted: #52635c;
            --studio-faint: #7a8c84;
            --studio-danger: #dc2626;
            --studio-danger-soft: #fef2f2;
            --editor-bg: #0f1714;
            --editor-tab-bg: #14201b;
            --editor-border: #20332b;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html, body {
            width: 100%;
            height: 100%;
            height: 100dvh;
            overflow: hidden;
            background: var(--studio-bg);
            color: var(--studio-text);
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            -webkit-font-smoothing: antialiased;
        }
        .studio-container {
            display: flex;
            flex-direction: column;
            width: 100%;
            height: 100%;
            height: 100dvh;
            overflow: hidden;
        }

        /* 1. Top Navigation Header */
        .studio-header {
            height: 56px;
            background: var(--studio-surface);
            border-bottom: 1px solid var(--studio-border);
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            align-items: center;
            padding: 0 14px;
            gap: 10px;
            flex-shrink: 0;
            z-index: 40;
        }
        .studio-nav-left {
            display: flex;
            align-items: center;
            gap: 8px;
            min-width: 0;
        }
        .studio-nav-center {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .studio-nav-right {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 6px;
            min-width: 0;
        }
        .studio-back-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: var(--studio-muted);
            text-decoration: none;
            font-size: 12.5px;
            font-weight: 600;
            padding: 6px 11px;
            border-radius: 999px;
            border: 1px solid var(--studio-border);
            background: var(--studio-surface-subtle);
            transition: all 0.15s ease;
            flex-shrink: 0;
        }
        .studio-back-btn:hover {
            color: var(--studio-text);
            border-color: var(--studio-border-strong);
            background: #eef3f0;
        }
        .site-meta-badge {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            background: var(--studio-surface-subtle);
            padding: 5px 11px;
            border-radius: 999px;
            border: 1px solid var(--studio-border);
            font-size: 12.5px;
            min-width: 0;
            max-width: 280px;
        }
        .site-meta-title {
            font-weight: 700;
            color: var(--studio-text);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .site-meta-slug {
            color: var(--studio-accent);
            font-family: 'JetBrains Mono', monospace;
            font-size: 11px;
            font-weight: 600;
            white-space: nowrap;
        }
        .live-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.18);
            flex-shrink: 0;
        }
        .live-dot.unsaved {
            background: #f59e0b;
            box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.2);
        }

        /* Toolbar Icon & Action Pill Buttons */
        .toolbar-btn-group {
            display: inline-flex;
            align-items: center;
            background: var(--studio-bg);
            border: 1px solid var(--studio-border);
            border-radius: 999px;
            padding: 2px;
            gap: 2px;
        }
        .toolbar-icon-btn {
            background: transparent;
            border: none;
            color: var(--studio-muted);
            padding: 5px 9px;
            border-radius: 999px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            font-family: inherit;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.15s ease;
            white-space: nowrap;
        }
        .toolbar-icon-btn:hover:not(:disabled) {
            color: var(--studio-text);
            background: var(--studio-surface);
        }
        .toolbar-icon-btn:disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }
        .toolbar-pill-btn {
            background: var(--studio-surface-subtle);
            border: 1px solid var(--studio-border);
            color: var(--studio-muted);
            padding: 6px 11px;
            border-radius: 999px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            font-family: inherit;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.15s ease;
            white-space: nowrap;
            position: relative;
        }
        .toolbar-pill-btn:hover {
            color: var(--studio-text);
            border-color: var(--studio-border-strong);
            background: #eef3f0;
        }
        .toolbar-pill-btn.active {
            background: var(--studio-accent-soft);
            color: var(--studio-accent);
            border-color: #b4e0cd;
        }
        .edit-mode-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #94a3b8;
            transition: background 0.15s ease;
        }
        .toolbar-pill-btn.active .edit-mode-dot {
            background: var(--studio-accent);
        }

        /* Tools Dropdown Menu */
        .tools-dropdown-wrap {
            position: relative;
        }
        .tools-dropdown-menu {
            display: none;
            position: absolute;
            top: calc(100% + 6px);
            right: 0;
            width: 280px;
            max-height: 420px;
            overflow-y: auto;
            background: var(--studio-surface);
            border: 1px solid var(--studio-border-strong);
            border-radius: 14px;
            box-shadow: 0 18px 40px rgba(11, 20, 17, 0.16);
            padding: 6px;
            z-index: 90;
        }
        .tools-dropdown-menu.open {
            display: block;
        }
        .tools-dropdown-header {
            padding: 6px 10px 4px;
            font-size: 10.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--studio-faint);
        }
        .tool-menu-item {
            width: 100%;
            text-align: left;
            background: transparent;
            border: none;
            padding: 8px 10px;
            border-radius: 9px;
            cursor: pointer;
            display: flex;
            flex-direction: column;
            gap: 2px;
            font-family: inherit;
            transition: background 0.12s ease;
        }
        .tool-menu-item:hover {
            background: var(--studio-bg);
        }
        .tool-menu-item-title {
            font-size: 12.5px;
            font-weight: 700;
            color: var(--studio-text);
        }
        .tool-menu-item-desc {
            font-size: 11px;
            color: var(--studio-muted);
            line-height: 1.35;
        }

        /* Layout Mode Switcher (Split / Code / Preview) */
        .layout-switcher,
        .viewport-switcher {
            display: inline-flex;
            align-items: center;
            background: var(--studio-bg);
            border: 1px solid var(--studio-border);
            border-radius: 999px;
            padding: 3px;
            gap: 2px;
        }
        .layout-btn,
        .viewport-btn {
            background: transparent;
            border: none;
            color: var(--studio-muted);
            padding: 5px 11px;
            border-radius: 999px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            font-family: inherit;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.15s ease;
            white-space: nowrap;
        }
        .layout-btn:hover,
        .viewport-btn:hover {
            color: var(--studio-text);
        }
        .layout-btn.active,
        .viewport-btn.active {
            background: var(--studio-surface);
            color: var(--studio-accent);
            box-shadow: 0 1px 3px rgba(11, 20, 17, 0.08);
        }

        /* Header Right Buttons */
        .btn-open-site {
            color: var(--studio-text);
            background: var(--studio-surface);
            text-decoration: none;
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 6px 12px;
            border-radius: 999px;
            border: 1px solid var(--studio-border);
            transition: all 0.15s ease;
            white-space: nowrap;
        }
        .btn-open-site:hover {
            background: var(--studio-bg);
            border-color: var(--studio-border-strong);
        }
        .btn-publish {
            background: var(--studio-accent);
            color: #ffffff;
            border: none;
            padding: 7px 16px;
            border-radius: 999px;
            font-size: 12.5px;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            box-shadow: 0 2px 6px rgba(4, 120, 87, 0.22);
            transition: all 0.15s ease;
            white-space: nowrap;
        }
        .btn-publish:hover {
            background: var(--studio-accent-hover);
            transform: translateY(-1px);
        }
        .btn-publish:disabled {
            opacity: 0.55;
            cursor: not-allowed;
            transform: none;
        }

        /* 2. Main Workspace Split */
        .studio-main {
            display: flex;
            flex: 1;
            min-height: 0;
            width: 100%;
            position: relative;
            overflow: hidden;
        }
        .editor-pane {
            width: 46%;
            min-width: 300px;
            height: 100%;
            display: flex;
            flex-direction: column;
            background: var(--editor-bg);
            position: relative;
            min-height: 0;
        }
        .studio-resizer {
            width: 8px;
            background: var(--studio-border);
            cursor: col-resize;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.15s ease;
            z-index: 15;
            user-select: none;
        }
        .studio-resizer::after {
            content: '';
            width: 2px;
            height: 32px;
            border-radius: 2px;
            background: #8fa39a;
        }
        .studio-resizer:hover,
        .studio-resizer.dragging {
            background: var(--studio-accent);
        }
        .studio-resizer:hover::after,
        .studio-resizer.dragging::after {
            background: #ffffff;
        }
        .preview-pane {
            flex: 1;
            min-width: 300px;
            height: 100%;
            display: flex;
            flex-direction: column;
            background: var(--studio-bg);
            position: relative;
            overflow: hidden;
            min-height: 0;
        }

        /* Workspace Layout Modes */
        .studio-main.layout-code .editor-pane { width: 100% !important; }
        .studio-main.layout-code .studio-resizer,
        .studio-main.layout-code .preview-pane { display: none !important; }

        .studio-main.layout-preview .preview-pane { width: 100% !important; flex: 1 !important; }
        .studio-main.layout-preview .studio-resizer,
        .studio-main.layout-preview .editor-pane { display: none !important; }

        /* 3. Left Panel 4-Tab Bar (Code / Library / Structure / Shared) */
        .left-panel-tabs {
            height: 40px;
            background: #101915;
            border-bottom: 1px solid var(--editor-border);
            display: flex;
            align-items: center;
            padding: 0 8px;
            gap: 4px;
            flex-shrink: 0;
        }
        .panel-tab-btn {
            background: transparent;
            border: 1px solid transparent;
            color: #9cb3a9;
            padding: 5px 12px;
            border-radius: 7px;
            font-size: 12px;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.15s ease;
        }
        .panel-tab-btn:hover {
            color: #ffffff;
            background: rgba(255, 255, 255, 0.05);
        }
        .panel-tab-btn.active {
            color: #6ee7b7;
            background: rgba(16, 185, 129, 0.14);
            border-color: rgba(16, 185, 129, 0.35);
        }
        .panel-view {
            display: none;
            flex: 1;
            flex-direction: column;
            min-height: 0;
            width: 100%;
            overflow: hidden;
        }
        .panel-view.active {
            display: flex;
        }

        /* Code Tab: File Tabs Strip & Ace Editor */
        .file-tabs-strip {
            height: 40px;
            background: var(--editor-tab-bg);
            border-bottom: 1px solid var(--editor-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 10px;
            flex-shrink: 0;
            gap: 8px;
        }
        .file-tabs-list {
            display: flex;
            align-items: center;
            gap: 4px;
            overflow-x: auto;
            scrollbar-width: none;
            min-width: 0;
        }
        .file-tabs-list::-webkit-scrollbar { display: none; }
        .file-tab {
            padding: 5px 11px;
            font-size: 11.5px;
            font-family: 'JetBrains Mono', monospace;
            color: #9cb3a9;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid transparent;
            border-radius: 7px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
            transition: all 0.15s ease;
        }
        .file-tab:hover {
            color: #ffffff;
            background: rgba(255, 255, 255, 0.07);
        }
        .file-tab.active {
            color: #6ee7b7;
            background: rgba(16, 185, 129, 0.14);
            border-color: rgba(16, 185, 129, 0.35);
            font-weight: 600;
        }
        .editor-tools-right {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-shrink: 0;
        }
        .editor-tool-btn {
            background: rgba(255, 255, 255, 0.06);
            color: #c2d4cc;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 6px;
            padding: 4px 9px;
            font-size: 11px;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: all 0.15s ease;
        }
        .editor-tool-btn:hover {
            color: #ffffff;
            background: rgba(255, 255, 255, 0.12);
        }
        #ace-editor-container {
            flex: 1;
            width: 100%;
            min-height: 0;
            font-size: 13.5px;
            line-height: 1.6;
        }

        /* Library, Structure & Shared Light Surface Sub-Panels */
        .subpanel-surface {
            flex: 1;
            background: var(--studio-surface);
            color: var(--studio-text);
            display: flex;
            flex-direction: column;
            min-height: 0;
            overflow: hidden;
        }
        .subpanel-toolbar {
            padding: 10px 12px;
            border-bottom: 1px solid var(--studio-border);
            display: flex;
            flex-direction: column;
            gap: 8px;
            background: var(--studio-surface-subtle);
            flex-shrink: 0;
        }
        .subpanel-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
        }
        .subpanel-seg {
            display: inline-flex;
            background: var(--studio-bg);
            border: 1px solid var(--studio-border);
            border-radius: 8px;
            padding: 2px;
            gap: 2px;
        }
        .subpanel-seg-btn {
            background: transparent;
            border: none;
            padding: 5px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            color: var(--studio-muted);
            cursor: pointer;
            font-family: inherit;
        }
        .subpanel-seg-btn.active {
            background: var(--studio-surface);
            color: var(--studio-accent);
            box-shadow: 0 1px 2px rgba(0,0,0,0.06);
        }
        .subpanel-search {
            width: 100%;
            padding: 7px 11px;
            border-radius: 8px;
            border: 1px solid var(--studio-border);
            background: var(--studio-surface);
            font-size: 12.5px;
            font-family: inherit;
            color: var(--studio-text);
            outline: none;
        }
        .subpanel-search:focus {
            border-color: var(--studio-accent);
        }
        .category-pills {
            display: flex;
            gap: 4px;
            overflow-x: auto;
            scrollbar-width: none;
            padding-bottom: 2px;
        }
        .category-pills::-webkit-scrollbar { display: none; }
        .category-pill {
            background: var(--studio-surface);
            border: 1px solid var(--studio-border);
            color: var(--studio-muted);
            padding: 3px 9px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 600;
            cursor: pointer;
            white-space: nowrap;
            font-family: inherit;
        }
        .category-pill.active {
            background: var(--studio-accent);
            border-color: var(--studio-accent);
            color: #ffffff;
        }
        .subpanel-body {
            flex: 1;
            overflow-y: auto;
            padding: 12px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .library-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(190px, 1fr));
            gap: 10px;
        }
        .library-card {
            border: 1px solid var(--studio-border);
            border-radius: 10px;
            padding: 11px 12px;
            background: var(--studio-surface);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 8px;
            transition: all 0.14s ease;
            cursor: grab;
        }
        .library-card:active {
            cursor: grabbing;
        }
        .library-card:hover {
            border-color: var(--studio-accent);
            box-shadow: 0 4px 12px rgba(4, 120, 87, 0.08);
        }
        .library-card-cat {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--studio-accent);
        }
        .library-card-title {
            font-size: 13px;
            font-weight: 700;
            color: var(--studio-text);
        }
        .library-card-btn {
            align-self: flex-start;
            font-size: 11px;
            font-weight: 700;
            color: var(--studio-accent);
            background: var(--studio-accent-soft);
            border: none;
            padding: 4px 10px;
            border-radius: 6px;
            cursor: pointer;
            font-family: inherit;
        }

        /* Structure Tree Nodes */
        .structure-node {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 5px 8px;
            border-radius: 7px;
            font-size: 12px;
            cursor: pointer;
            border: 1px solid transparent;
            gap: 6px;
            transition: border-color 0.1s ease, background 0.1s ease;
        }
        .structure-node[draggable="true"] {
            cursor: grab;
        }
        .structure-node.dragging {
            opacity: 0.45;
        }
        .structure-node.drag-over-top {
            border-top: 2px solid var(--studio-accent);
        }
        .structure-node.drag-over-bottom {
            border-bottom: 2px solid var(--studio-accent);
        }
        .structure-node:hover {
            background: var(--studio-bg);
            border-color: var(--studio-border);
        }
        .structure-node.selected {
            background: var(--studio-accent-soft);
            border-color: #b4e0cd;
            color: var(--studio-accent);
            font-weight: 600;
        }
        .structure-node-left {
            display: flex;
            align-items: center;
            gap: 6px;
            min-width: 0;
            overflow: hidden;
        }
        .structure-tag-badge {
            font-family: 'JetBrains Mono', monospace;
            font-size: 11px;
            font-weight: 600;
            padding: 1px 6px;
            border-radius: 4px;
            background: var(--studio-bg);
            color: var(--studio-text);
            border: 1px solid var(--studio-border);
            flex-shrink: 0;
        }
        .structure-hint {
            color: var(--studio-muted);
            font-size: 11.5px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .structure-actions {
            display: flex;
            align-items: center;
            gap: 4px;
            flex-shrink: 0;
        }
        .structure-mini-btn {
            background: transparent;
            border: 1px solid var(--studio-border);
            color: var(--studio-muted);
            border-radius: 5px;
            padding: 2px 6px;
            font-size: 10.5px;
            font-weight: 600;
            cursor: pointer;
        }
        .structure-mini-btn:hover {
            color: var(--studio-text);
            background: var(--studio-surface);
        }

        /* 4. Preview Toolbar & Responsive Canvas Stage */
        .preview-toolbar {
            height: 40px;
            background: var(--studio-surface);
            border-bottom: 1px solid var(--studio-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 12px;
            gap: 8px;
            flex-shrink: 0;
        }
        .preview-toolbar-left,
        .preview-toolbar-right {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .preview-dim-badge {
            font-family: 'JetBrains Mono', monospace;
            font-size: 11px;
            font-weight: 600;
            color: var(--studio-muted);
            background: var(--studio-bg);
            border: 1px solid var(--studio-border);
            padding: 4px 10px;
            border-radius: 999px;
            white-space: nowrap;
        }
        .preview-icon-btn {
            background: var(--studio-surface-subtle);
            border: 1px solid var(--studio-border);
            color: var(--studio-muted);
            padding: 5px 10px;
            border-radius: 999px;
            font-size: 11.5px;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.15s ease;
            white-space: nowrap;
        }
        .preview-icon-btn:hover {
            color: var(--studio-text);
            border-color: var(--studio-border-strong);
            background: #eef3f0;
        }
        .preview-icon-btn.active {
            background: var(--studio-accent-soft);
            color: var(--studio-accent);
            border-color: #bfe3d2;
        }

        .preview-stage {
            flex: 1;
            width: 100%;
            min-height: 0;
            position: relative;
            overflow: auto;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #edf3f0;
            background-image: radial-gradient(#c8d6cf 1px, transparent 1px);
            background-size: 18px 18px;
            padding: 16px;
        }
        .preview-stage.stage-desktop-fluid {
            padding: 0;
        }
        .preview-scale-host {
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            width: 100%;
            height: 100%;
        }

        /* Device Frames */
        .preview-wrapper {
            background: #ffffff;
            display: flex;
            flex-direction: column;
            position: relative;
            transform-origin: center center;
            transition: width 0.22s ease, height 0.22s ease, border-radius 0.22s ease, box-shadow 0.22s ease;
            overflow: hidden;
        }
        .preview-wrapper.mode-desktop {
            width: 100%;
            height: 100%;
            border-radius: 0;
            border: none;
            box-shadow: none;
        }
        .preview-wrapper.mode-desktop.scaled-desktop {
            border-radius: 12px;
            border: 1px solid var(--studio-border-strong);
            box-shadow: 0 18px 40px rgba(11, 20, 17, 0.12);
        }
        .preview-wrapper.mode-tablet {
            width: 768px;
            height: 1024px;
            border-radius: 24px;
            border: 10px solid #1e2925;
            box-shadow: 0 24px 50px rgba(11, 20, 17, 0.2), 0 0 0 1px rgba(255,255,255,0.1) inset;
            flex-shrink: 0;
        }
        .preview-wrapper.mode-mobile {
            width: 375px;
            height: 780px;
            border-radius: 38px;
            border: 11px solid #141c19;
            box-shadow: 0 24px 50px rgba(11, 20, 17, 0.24), 0 0 0 1px rgba(255,255,255,0.12) inset;
            flex-shrink: 0;
        }
        .device-bezel-top {
            display: none;
            height: 22px;
            background: #141c19;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .preview-wrapper.mode-tablet .device-bezel-top,
        .preview-wrapper.mode-mobile .device-bezel-top {
            display: flex;
        }
        .device-notch {
            width: 56px;
            height: 5px;
            border-radius: 999px;
            background: #2c3a35;
        }
        .preview-iframe {
            width: 100%;
            flex: 1;
            height: 100%;
            border: none;
            background: #ffffff;
        }
        .iframe-drag-shield {
            display: none;
            position: absolute;
            inset: 0;
            z-index: 25;
            cursor: col-resize;
        }
        .studio-main.resizing .iframe-drag-shield {
            display: block;
        }

        /* 5. Floating Direct Element Edit Popover (#direct-element-popover) */
        .direct-element-popover {
            display: none;
            position: fixed;
            top: 72px;
            right: 24px;
            width: 340px;
            max-height: calc(100dvh - 96px);
            background: var(--studio-surface);
            border: 1px solid var(--studio-border-strong);
            border-radius: 16px;
            box-shadow: 0 24px 60px rgba(11, 20, 17, 0.22);
            flex-direction: column;
            z-index: 80;
            overflow: hidden;
        }
        .direct-element-popover.open {
            display: flex;
        }
        .de-popover-header {
            padding: 10px 12px;
            background: var(--studio-surface-subtle);
            border-bottom: 1px solid var(--studio-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            cursor: move;
            user-select: none;
        }
        .de-header-left {
            display: flex;
            align-items: center;
            gap: 6px;
            min-width: 0;
        }
        .de-tag-badge {
            font-family: 'JetBrains Mono', monospace;
            font-size: 11px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 6px;
            background: var(--studio-accent);
            color: #ffffff;
            text-transform: lowercase;
        }
        .de-tag-snippet {
            font-size: 11.5px;
            color: var(--studio-muted);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .de-close-btn {
            background: transparent;
            border: none;
            color: var(--studio-muted);
            cursor: pointer;
            padding: 4px;
            border-radius: 6px;
            display: inline-flex;
        }
        .de-close-btn:hover {
            background: var(--studio-bg);
            color: var(--studio-text);
        }
        .de-popover-body {
            padding: 12px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .de-section {
            display: flex;
            flex-direction: column;
            gap: 6px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--studio-border);
        }
        .de-section:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }
        .de-label {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--studio-muted);
        }
        .de-input,
        .de-textarea,
        .de-select {
            width: 100%;
            padding: 7px 10px;
            border-radius: 8px;
            border: 1px solid var(--studio-border);
            background: var(--studio-surface);
            color: var(--studio-text);
            font-size: 12.5px;
            font-family: inherit;
            outline: none;
        }
        .de-input:focus,
        .de-textarea:focus,
        .de-select:focus {
            border-color: var(--studio-accent);
        }
        .de-textarea {
            resize: vertical;
            min-height: 58px;
            line-height: 1.45;
        }
        .de-inline-btn {
            background: var(--studio-surface-subtle);
            border: 1px solid var(--studio-border);
            color: var(--studio-accent);
            padding: 6px 10px;
            border-radius: 8px;
            font-size: 11.5px;
            font-weight: 600;
            cursor: pointer;
            text-align: center;
            font-family: inherit;
        }
        .de-inline-btn:hover {
            background: var(--studio-accent-soft);
            border-color: #b4e0cd;
        }
        .de-row {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .de-checkbox-label {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            color: var(--studio-text);
            cursor: pointer;
        }
        .de-align-group {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 4px;
        }
        .de-align-btn {
            padding: 5px;
            border-radius: 6px;
            border: 1px solid var(--studio-border);
            background: var(--studio-surface-subtle);
            color: var(--studio-muted);
            font-size: 11px;
            font-weight: 600;
            cursor: pointer;
            font-family: inherit;
        }
        .de-align-btn.active {
            background: var(--studio-accent-soft);
            color: var(--studio-accent);
            border-color: var(--studio-accent);
        }
        .de-popover-footer {
            padding: 8px 10px;
            background: var(--studio-surface-subtle);
            border-top: 1px solid var(--studio-border);
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 5px;
        }
        .de-footer-btn {
            padding: 6px 6px;
            border-radius: 7px;
            border: 1px solid var(--studio-border);
            background: var(--studio-surface);
            color: var(--studio-text);
            font-size: 11px;
            font-weight: 600;
            cursor: pointer;
            font-family: inherit;
            text-align: center;
        }
        .de-footer-btn:hover {
            background: var(--studio-bg);
            border-color: var(--studio-border-strong);
        }
        .de-footer-btn.danger {
            color: var(--studio-danger);
            border-color: #fecaca;
            background: var(--studio-danger-soft);
        }

        /* 6. Right-Click Context Menu (#element-context-menu) */
        .element-context-menu {
            display: none;
            position: fixed;
            width: 215px;
            background: var(--studio-surface);
            border: 1px solid var(--studio-border-strong);
            border-radius: 12px;
            box-shadow: 0 18px 42px rgba(11, 20, 17, 0.22);
            padding: 5px;
            z-index: 95;
        }
        .element-context-menu.open {
            display: block;
        }
        .ctx-item {
            width: 100%;
            background: transparent;
            border: none;
            padding: 6px 10px;
            border-radius: 7px;
            font-size: 12px;
            font-weight: 600;
            color: var(--studio-text);
            display: flex;
            align-items: center;
            justify-content: space-between;
            cursor: pointer;
            font-family: inherit;
            text-align: left;
        }
        .ctx-item:hover:not(:disabled) {
            background: var(--studio-bg);
        }
        .ctx-item:disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }
        .ctx-item.danger {
            color: var(--studio-danger);
        }
        .ctx-item.danger:hover {
            background: var(--studio-danger-soft);
        }
        .ctx-kbd {
            font-family: 'JetBrains Mono', monospace;
            font-size: 10.5px;
            color: var(--studio-faint);
        }
        .ctx-divider {
            height: 1px;
            background: var(--studio-border);
            margin: 4px 0;
        }

        /* 7. Modals (Media Library, Version History, Keyboard Shortcuts, New Page) */
        .studio-modal-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(11, 20, 17, 0.52);
            backdrop-filter: blur(2px);
            align-items: center;
            justify-content: center;
            padding: 20px;
            z-index: 100;
        }
        .studio-modal-backdrop.open {
            display: flex;
        }
        .studio-modal {
            width: 100%;
            max-width: 680px;
            max-height: 85dvh;
            background: var(--studio-surface);
            border: 1px solid var(--studio-border-strong);
            border-radius: 18px;
            box-shadow: 0 28px 70px rgba(11, 20, 17, 0.28);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .studio-modal-header {
            padding: 14px 18px;
            border-bottom: 1px solid var(--studio-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: var(--studio-surface-subtle);
        }
        .studio-modal-title {
            font-size: 15px;
            font-weight: 800;
            color: var(--studio-text);
        }
        .studio-modal-body {
            padding: 18px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 14px;
        }
        .media-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 12px;
        }
        .media-item-card {
            border: 1px solid var(--studio-border);
            border-radius: 10px;
            overflow: hidden;
            background: var(--studio-surface-subtle);
            display: flex;
            flex-direction: column;
            cursor: pointer;
            transition: border-color 0.14s ease;
        }
        .media-item-card:hover {
            border-color: var(--studio-accent);
        }
        .media-thumb {
            height: 96px;
            background: #e2ebe6;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        .media-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .media-meta {
            padding: 7px 9px;
            font-size: 11px;
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .version-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 14px;
            border: 1px solid var(--studio-border);
            border-radius: 10px;
            gap: 12px;
        }
        .shortcuts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 16px;
        }
        .shortcut-group-title {
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--studio-accent);
            margin-bottom: 8px;
        }
        .shortcut-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 6px 0;
            border-bottom: 1px solid var(--studio-border);
            font-size: 12.5px;
        }
        .shortcut-keys {
            font-family: 'JetBrains Mono', monospace;
            font-size: 11px;
            background: var(--studio-bg);
            border: 1px solid var(--studio-border);
            padding: 2px 7px;
            border-radius: 5px;
            color: var(--studio-text);
        }

        /* 8. Toast Notification */
        .studio-toast {
            position: fixed;
            bottom: 24px;
            right: 24px;
            background: #0b1411;
            border: 1px solid var(--studio-accent);
            color: #ffffff;
            padding: 11px 18px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 12px 32px rgba(11, 20, 17, 0.22);
            opacity: 0;
            pointer-events: none;
            transform: translateY(10px);
            transition: all 0.22s ease;
            z-index: 999;
        }
        .studio-toast.show {
            opacity: 1;
            pointer-events: auto;
            transform: translateY(0);
        }

        /* 9. Tablet & Mobile Workspace Optimization */
        @media (max-width: 1120px) {
            .site-meta-slug { display: none; }
            .site-meta-badge { max-width: 180px; }
            .preview-dim-badge { display: none; }
            .toolbar-pill-btn span.hide-tablet { display: none; }
        }

        @media (max-width: 768px) {
            .studio-header {
                height: 52px;
                padding: 0 10px;
                gap: 6px;
                grid-template-columns: auto 1fr auto;
            }
            .studio-back-btn span { display: none; }
            .studio-back-btn { padding: 7px 9px; }
            .site-meta-badge { display: none; }
            .layout-btn[data-layout="split"] { display: none; }
            .btn-open-site span { display: none; }
            .btn-open-site { padding: 7px 10px; }
            .btn-publish { padding: 7px 14px; font-size: 12.5px; }
            .studio-resizer { display: none !important; }
            .editor-pane,
            .preview-pane {
                width: 100% !important;
                min-width: 0 !important;
            }
            .preview-toolbar {
                padding: 0 8px;
                overflow-x: auto;
            }
            .viewport-btn span { display: none; }
            .viewport-btn { padding: 5px 10px; }
            .direct-element-popover {
                left: 12px;
                right: 12px;
                bottom: 12px;
                top: auto;
                width: auto;
                max-height: 68dvh;
            }
            .studio-toast {
                left: 16px;
                right: 16px;
                bottom: 16px;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
<div class="studio-container">
    <!-- Top Header -->
    <header class="studio-header">
        <div class="studio-nav-left">
            <a href="<?= url('dashboard') ?>" class="studio-back-btn" title="Back to Dashboard">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                <span>Dashboard</span>
            </a>
            <div class="site-meta-badge">
                <span class="live-dot" id="studio-status-dot" title="All changes published"></span>
                <span class="site-meta-title"><?= htmlspecialchars($site['title'] ?: $slug) ?></span>
                <span class="site-meta-slug">/s/<?= htmlspecialchars($slug) ?></span>
            </div>
            <div class="toolbar-btn-group" role="group" aria-label="Undo and Redo">
                <button type="button" id="btn-undo" class="toolbar-icon-btn" title="Undo (Cmd/Ctrl+Z)" disabled>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M3 7v6h6"/><path d="M21 17a9 9 0 0 0-9-9 9 9 0 0 0-6 2.3L3 13"/></svg>
                </button>
                <button type="button" id="btn-redo" class="toolbar-icon-btn" title="Redo (Cmd/Ctrl+Shift+Z)" disabled>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M21 7v6h-6"/><path d="M3 17a9 9 0 0 1 9-9 9 9 0 0 1 6 2.3l3 2.7"/></svg>
                </button>
            </div>
        </div>

        <div class="studio-nav-center">
            <div class="layout-switcher" role="tablist" aria-label="Workspace View Mode">
                <button type="button" class="layout-btn active" data-layout="split" title="Split View (Code + Preview)">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="12" y1="3" x2="12" y2="21"/></svg>
                    <span>Split</span>
                </button>
                <button type="button" class="layout-btn" data-layout="code" title="Left Panel Only">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
                    <span>Code</span>
                </button>
                <button type="button" class="layout-btn" data-layout="preview" title="Live Preview Only">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    <span>Preview</span>
                </button>
            </div>

            <button type="button" id="btn-edit-mode" role="switch" aria-checked="true" class="toolbar-pill-btn active" title="Toggle Visual Click-to-Select &amp; Direct Editing (Cmd/Ctrl+Shift+E)">
                <span class="edit-mode-dot"></span>
                <span id="edit-mode-label">Edit Mode: On</span>
            </button>
        </div>

        <div class="studio-nav-right">
            <!-- Deterministic Developer Tools Menu -->
            <div class="tools-dropdown-wrap">
                <button type="button" id="btn-tools-menu" class="toolbar-pill-btn" title="HTML, CSS, JS &amp; SEO Developer Tools">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
                    <span class="hide-tablet">Tools</span>
                </button>
                <div id="tools-dropdown-menu" class="tools-dropdown-menu" role="menu">
                    <div class="tools-dropdown-header">Code &amp; SEO Utilities</div>
                    <div id="tools-menu-items"></div>
                </div>
            </div>

            <button type="button" id="btn-media-modal" class="toolbar-pill-btn" title="Site Media Library">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                <span class="hide-tablet">Media</span>
            </button>

            <button type="button" id="btn-history-modal" class="toolbar-pill-btn" title="Version History &amp; Rollback (Cmd/Ctrl+Shift+H)">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                <span class="hide-tablet">History</span>
            </button>

            <button type="button" id="btn-shortcuts-modal" class="toolbar-pill-btn" title="Keyboard Shortcuts (Cmd/Ctrl+/)">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M6 8h.01M10 8h.01M14 8h.01M18 8h.01M8 12h.01M12 12h.01M16 12h.01M7 16h10"/></svg>
            </button>

            <a href="<?= $liveUrl ?>" target="_blank" rel="noopener" class="btn-open-site" title="Open Live Website">
                <span>View Live</span>
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
            </a>

            <button type="button" id="btn-save-publish" class="btn-publish">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                <span>Publish</span>
            </button>
        </div>
    </header>

    <!-- Main Workspace -->
    <main class="studio-main" id="studio-main">
        <!-- Left Collapsible Panel (4-Tab Architecture: Code, Library, Structure, Shared) -->
        <section class="editor-pane" id="editor-pane">
            <div class="left-panel-tabs" role="tablist" aria-label="Editor Left Panel">
                <button type="button" class="panel-tab-btn active" data-panel-tab="code">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
                    <span>Code</span>
                </button>
                <button type="button" class="panel-tab-btn" data-panel-tab="library">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                    <span>Library</span>
                </button>
                <button type="button" class="panel-tab-btn" data-panel-tab="structure">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
                    <span>Structure</span>
                </button>
                <button type="button" class="panel-tab-btn" data-panel-tab="shared">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/><polyline points="16 6 12 2 8 6"/><line x1="12" y1="2" x2="12" y2="15"/></svg>
                    <span>Shared</span>
                </button>
            </div>

            <!-- Panel Tab 1: Code Editor -->
            <div class="panel-view active" id="panel-view-code" data-panel-view="code">
                <div class="file-tabs-strip">
                    <div class="file-tabs-list" id="file-tabs-container">
                        <?php foreach ($files as $f): ?>
                            <button type="button" class="file-tab <?= ($f === $initialFile) ? 'active' : '' ?>" data-file="<?= htmlspecialchars($f) ?>">
                                <?= htmlspecialchars($f) ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                    <div class="editor-tools-right">
                        <button type="button" class="editor-tool-btn" id="btn-new-file" title="Add New HTML Page or Asset File">
                            + File
                        </button>
                        <button type="button" class="editor-tool-btn" id="btn-toggle-editor-theme" title="Toggle Code Editor Theme (Dark / Light)">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/></svg>
                            <span id="editor-theme-label">Light Code</span>
                        </button>
                    </div>
                </div>
                <div id="ace-editor-container"></div>
            </div>

            <!-- Panel Tab 2: Component & Section Library -->
            <div class="panel-view" id="panel-view-library" data-panel-view="library">
                <div class="subpanel-surface">
                    <div class="subpanel-toolbar">
                        <div class="subpanel-row">
                            <div class="subpanel-seg">
                                <button type="button" class="subpanel-seg-btn active" data-lib-mode="sections">Sections</button>
                                <button type="button" class="subpanel-seg-btn" data-lib-mode="elements">Elements</button>
                            </div>
                            <span style="font-size:11px;color:var(--studio-muted);">Click any block to insert</span>
                        </div>
                        <input type="search" id="library-search-input" class="subpanel-search" placeholder="Search sections &amp; elements...">
                        <div class="category-pills" id="library-categories"></div>
                    </div>
                    <div class="subpanel-body">
                        <div class="library-grid" id="library-items-container"></div>
                    </div>
                </div>
            </div>

            <!-- Panel Tab 3: Live DOM Structure Tree -->
            <div class="panel-view" id="panel-view-structure" data-panel-view="structure">
                <div class="subpanel-surface">
                    <div class="subpanel-toolbar">
                        <div class="subpanel-row">
                            <span style="font-size:12px;font-weight:700;">DOM Outline</span>
                            <div style="display:flex;gap:6px;">
                                <button type="button" id="btn-unhide-all" class="structure-mini-btn" style="display:none;">Show Hidden</button>
                                <button type="button" id="btn-refresh-structure" class="structure-mini-btn">Refresh Tree</button>
                            </div>
                        </div>
                    </div>
                    <div class="subpanel-body" id="structure-tree-container">
                        <p style="font-size:12px;color:var(--studio-muted);">Loading live DOM structure...</p>
                    </div>
                </div>
            </div>

            <!-- Panel Tab 4: Shared Header, Footer & Cookie Banner Manager -->
            <div class="panel-view" id="panel-view-shared" data-panel-view="shared">
                <div class="subpanel-surface">
                    <div class="subpanel-toolbar">
                        <div class="subpanel-row">
                            <span style="font-size:12px;font-weight:700;">Multi-Page Shared Components</span>
                            <button type="button" id="btn-sync-shared" class="structure-mini-btn" style="color:var(--studio-accent);border-color:#b4e0cd;background:var(--studio-accent-soft);">Sync Across Pages</button>
                        </div>
                    </div>
                    <div class="subpanel-body" id="shared-blocks-container">
                        <div style="padding:12px;border:1px solid var(--studio-border);border-radius:10px;background:var(--studio-surface-subtle);">
                            <div style="font-size:12.5px;font-weight:700;margin-bottom:4px;">Shared Header &amp; Footer</div>
                            <p style="font-size:11.5px;color:var(--studio-muted);line-height:1.5;margin-bottom:10px;">
                                Propagate your primary navigation <code>&lt;header&gt;</code> and <code>&lt;footer&gt;</code> across every HTML page in this site.
                            </p>
                            <button type="button" id="btn-run-shared-sync" class="de-inline-btn" style="width:100%;">Sync Header &amp; Footer from Current Page</button>
                        </div>
                        <div style="padding:12px;border:1px solid var(--studio-border);border-radius:10px;background:var(--studio-surface-subtle);">
                            <div style="font-size:12.5px;font-weight:700;margin-bottom:4px;">Cookie Consent Notice</div>
                            <p style="font-size:11.5px;color:var(--studio-muted);line-height:1.5;margin-bottom:10px;">
                                Insert a lightweight, zero-dependency cookie consent banner that saves visitor preference in <code>localStorage</code>.
                            </p>
                            <button type="button" id="btn-insert-cookie-banner" class="de-inline-btn" style="width:100%;">Insert Cookie Consent Banner</button>
                        </div>
                        <div id="shared-blocks-status" style="font-size:12px;color:var(--studio-muted);"></div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Draggable Split Resizer -->
        <div class="studio-resizer" id="studio-resizer" title="Drag to resize Code &amp; Preview panes"></div>

        <!-- Right: Live Preview -->
        <section class="preview-pane" id="preview-pane">
            <div class="preview-toolbar">
                <div class="preview-toolbar-left">
                    <div class="viewport-switcher" role="group" aria-label="Device Viewport Switcher">
                        <button type="button" class="viewport-btn active" data-viewport="desktop" title="Desktop Preview (1280px)">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                            <span>Desktop</span>
                        </button>
                        <button type="button" class="viewport-btn" data-viewport="tablet" title="Tablet Preview (768 x 1024)">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="2" width="16" height="20" rx="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
                            <span>Tablet</span>
                        </button>
                        <button type="button" class="viewport-btn" data-viewport="mobile" title="Mobile Preview (375 x 780)">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
                            <span>Mobile</span>
                        </button>
                    </div>
                    <span class="preview-dim-badge" id="preview-dimensions">Desktop &bull; Fluid</span>
                </div>

                <div class="preview-toolbar-right">
                    <button type="button" class="preview-icon-btn active" id="btn-scale-toggle" title="Scale large viewports to fit inside pane">
                        Fit to Pane
                    </button>
                    <button type="button" class="preview-icon-btn" id="btn-refresh-preview" title="Refresh Live Preview">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
                        <span>Refresh</span>
                    </button>
                </div>
            </div>

            <div class="preview-stage stage-desktop-fluid" id="preview-stage">
                <div class="iframe-drag-shield"></div>
                <div class="preview-scale-host" id="preview-scale-host">
                    <div class="preview-wrapper mode-desktop" id="preview-wrapper">
                        <div class="device-bezel-top"><span class="device-notch"></span></div>
                        <iframe id="preview-iframe" class="preview-iframe" sandbox="allow-scripts allow-forms allow-same-origin allow-modals" src="<?= $liveUrl ?>"></iframe>
                    </div>
                </div>
            </div>
        </section>
    </main>
</div>

<!-- Direct Element Edit Popover (#direct-element-popover) -->
<aside id="direct-element-popover" class="direct-element-popover" aria-label="Selected Element Inspector">
    <div class="de-popover-header" id="de-popover-drag-handle">
        <div class="de-header-left">
            <span class="de-tag-badge" id="de-tag-badge">div</span>
            <span class="de-tag-snippet" id="de-tag-snippet">Selected Element</span>
        </div>
        <button type="button" class="de-close-btn" id="btn-close-de-popover" title="Close Inspector (Esc)">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
    </div>

    <div class="de-popover-body">
        <!-- 1. Text Editing Section -->
        <div class="de-section" id="de-section-text">
            <label class="de-label" for="de-text-input">Text Content</label>
            <textarea id="de-text-input" class="de-textarea" placeholder="Edit element text..."></textarea>
            <button type="button" id="btn-inline-text-edit" class="de-inline-btn">
                Or edit it on the page (double-click)
            </button>
        </div>

        <!-- 2. Button / Link Section -->
        <div class="de-section" id="de-section-link" style="display:none;">
            <label class="de-label" for="de-link-href">Link Destination (URL or #section)</label>
            <input type="text" id="de-link-href" class="de-input" placeholder="https://example.com or #contact">
            <label class="de-checkbox-label">
                <input type="checkbox" id="de-link-blank">
                <span>Open in a new tab</span>
            </label>
        </div>

        <!-- 3. Image Section -->
        <div class="de-section" id="de-section-image" style="display:none;">
            <label class="de-label">Image Source &amp; Alt Text</label>
            <div class="de-row">
                <input type="text" id="de-image-src" class="de-input" placeholder="https://... or /assets/photo.jpg">
                <button type="button" id="btn-de-replace-image" class="de-inline-btn" style="flex-shrink:0;">Media</button>
            </div>
            <input type="text" id="de-image-alt" class="de-input" placeholder="Descriptive alt text for accessibility">
        </div>

        <!-- 4. Background Photo Section -->
        <div class="de-section" id="de-section-bg" style="display:none;">
            <label class="de-label">Background Image</label>
            <div class="de-row">
                <input type="text" id="de-bg-url" class="de-input" placeholder="https://... background image URL">
                <button type="button" id="btn-de-replace-bg" class="de-inline-btn" style="flex-shrink:0;">Replace</button>
            </div>
        </div>

        <!-- 5. Video & Map Embed Section -->
        <div class="de-section" id="de-section-embed" style="display:none;">
            <label class="de-label" for="de-video-url">Video URL (YouTube / Vimeo / Embed)</label>
            <input type="text" id="de-video-url" class="de-input" placeholder="https://www.youtube.com/watch?v=...">
            <label class="de-label" for="de-map-address" style="margin-top:4px;">Google Map Address</label>
            <input type="text" id="de-map-address" class="de-input" placeholder="Victoria Island, Lagos">
        </div>

        <!-- 6. Form Delivery & Settings Section -->
        <div class="de-section" id="de-section-form" style="display:none;">
            <label class="de-label">Form Inbox Delivery</label>
            <button type="button" id="btn-de-connect-form" class="de-inline-btn">
                Send messages to my inbox
            </button>
            <input type="text" id="de-form-name" class="de-input" placeholder="Form Name (e.g. Contact)">
            <input type="text" id="de-form-success" class="de-input" placeholder="Success message shown after submit">
            <input type="text" id="de-form-redirect" class="de-input" placeholder="Optional redirect URL (/thank-you)">
            <input type="text" id="de-form-error" class="de-input" placeholder="Optional error message">
        </div>

        <!-- 7. Text Style Section -->
        <div class="de-section" id="de-section-style">
            <label class="de-label">Typography &amp; Color</label>
            <div class="de-row">
                <input type="color" id="de-style-color" value="#0b1411" style="width:34px;height:32px;border:1px solid var(--studio-border);border-radius:6px;background:transparent;cursor:pointer;">
                <input type="text" id="de-style-color-hex" class="de-input" placeholder="#0b1411" style="flex:1;">
                <input type="text" id="de-style-size" class="de-input" placeholder="16px" style="width:74px;" title="Font Size">
                <select id="de-style-weight" class="de-select" style="width:92px;" title="Font Weight">
                    <option value="400">Regular</option>
                    <option value="500">Medium</option>
                    <option value="600">SemiBold</option>
                    <option value="700">Bold</option>
                    <option value="800">ExtraBold</option>
                </select>
            </div>
            <div class="de-align-group" role="group" aria-label="Text Alignment">
                <button type="button" class="de-align-btn" data-de-align="left">Left</button>
                <button type="button" class="de-align-btn" data-de-align="center">Center</button>
                <button type="button" class="de-align-btn" data-de-align="right">Right</button>
                <button type="button" class="de-align-btn" data-de-align="justify">Justify</button>
            </div>
        </div>
    </div>

    <div class="de-popover-footer">
        <button type="button" class="de-footer-btn" data-de-action="select-parent" title="Select Parent Container (Alt+Left)">Parent</button>
        <button type="button" class="de-footer-btn" data-de-action="duplicate" title="Duplicate Element (Cmd/Ctrl+D)">Duplicate</button>
        <button type="button" class="de-footer-btn" data-de-action="show-source" title="Jump to Line in Code Editor">Code</button>
        <button type="button" class="de-footer-btn danger" data-de-action="delete" title="Delete Element (Delete)">Delete</button>
    </div>
</aside>

<!-- Right-Click Context Menu (#element-context-menu) -->
<div id="element-context-menu" class="element-context-menu" role="menu">
    <button type="button" class="ctx-item" data-ctx-action="copy"><span>Copy</span><span class="ctx-kbd">Cmd+C</span></button>
    <button type="button" class="ctx-item" data-ctx-action="cut"><span>Cut</span><span class="ctx-kbd">Cmd+X</span></button>
    <button type="button" class="ctx-item" data-ctx-action="paste-after"><span>Paste After</span><span class="ctx-kbd">Cmd+V</span></button>
    <button type="button" class="ctx-item" data-ctx-action="paste-inside"><span>Paste Inside</span></button>
    <button type="button" class="ctx-item" data-ctx-action="paste-style"><span>Paste Style</span><span class="ctx-kbd">Shift+Cmd+V</span></button>
    <div class="ctx-divider"></div>
    <button type="button" class="ctx-item" data-ctx-action="duplicate"><span>Duplicate</span><span class="ctx-kbd">Cmd+D</span></button>
    <button type="button" class="ctx-item" data-ctx-action="move-up"><span>Move Up</span><span class="ctx-kbd">Alt+Up</span></button>
    <button type="button" class="ctx-item" data-ctx-action="move-down"><span>Move Down</span><span class="ctx-kbd">Alt+Down</span></button>
    <button type="button" class="ctx-item" data-ctx-action="select-parent"><span>Select Parent</span><span class="ctx-kbd">Alt+Left</span></button>
    <div class="ctx-divider"></div>
    <button type="button" class="ctx-item" data-ctx-action="hide-element"><span>Hide in Editor</span></button>
    <button type="button" class="ctx-item" data-ctx-action="show-source"><span>Show in Code</span></button>
    <button type="button" class="ctx-item" data-ctx-action="reveal-structure"><span>Show in Structure</span></button>
    <div class="ctx-divider"></div>
    <button type="button" class="ctx-item danger" data-ctx-action="delete"><span>Delete</span><span class="ctx-kbd">Del</span></button>
</div>

<!-- Modal 1: Media Library -->
<div id="media-library-modal" class="studio-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="media-modal-title">
    <div class="studio-modal">
        <div class="studio-modal-header">
            <span class="studio-modal-title" id="media-modal-title">Site Media Library</span>
            <button type="button" class="de-close-btn" data-close-modal="media-library-modal">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="studio-modal-body">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                <span style="font-size:12.5px;color:var(--studio-muted);">Upload images or select an existing file to insert or replace.</span>
                <label class="btn-publish" style="cursor:pointer;padding:6px 14px;font-size:12px;">
                    <input type="file" id="media-upload-input" accept="image/*,video/*,audio/*,.svg,.pdf" style="display:none;">
                    <span>Upload Media</span>
                </label>
            </div>
            <div class="media-grid" id="media-library-grid">
                <p style="font-size:12.5px;color:var(--studio-muted);">No uploaded media files yet.</p>
            </div>
        </div>
    </div>
</div>

<!-- Modal 2: Version History & Rollback -->
<div id="version-history-modal" class="studio-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="history-modal-title">
    <div class="studio-modal">
        <div class="studio-modal-header">
            <span class="studio-modal-title" id="history-modal-title">Version History &amp; Rollback</span>
            <button type="button" class="de-close-btn" data-close-modal="version-history-modal">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="studio-modal-body" id="version-history-list">
            <p style="font-size:12.5px;color:var(--studio-muted);">Loading version snapshots...</p>
        </div>
    </div>
</div>

<!-- Modal 3: Keyboard Shortcuts Reference -->
<div id="shortcuts-help-modal" class="studio-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="shortcuts-modal-title">
    <div class="studio-modal">
        <div class="studio-modal-header">
            <span class="studio-modal-title" id="shortcuts-modal-title">Keyboard Shortcuts</span>
            <button type="button" class="de-close-btn" data-close-modal="shortcuts-help-modal">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="studio-modal-body">
            <div class="shortcuts-grid" id="shortcuts-modal-content"></div>
        </div>
    </div>
</div>

<!-- Toast Notification -->
<div id="studio-toast" class="studio-toast">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
    <span id="toast-message">Saved &amp; Published v1</span>
</div>

<script>
    window.STUDIO_CONFIG = {
        slug: <?= json_encode($slug) ?>,
        liveUrl: <?= json_encode($liveUrl) ?>,
        claimToken: <?= json_encode($claimToken) ?>,
        initialFile: <?= json_encode($initialFile) ?>,
        initialContent: <?= json_encode($initialContent) ?>,
        files: <?= json_encode($files) ?>,
        saveUrl: <?= json_encode(url('api/editor/' . $slug)) ?>,
        getFileUrl: <?= json_encode(url('api/sites/' . $slug . '/file')) ?>,
        versionsUrl: <?= json_encode(url('api/sites/' . $slug . '/versions')) ?>,
        mediaUrl: <?= json_encode(url('api/sites/' . $slug . '/media')) ?>,
        sharedBlocksUrl: <?= json_encode(url('api/sites/' . $slug . '/shared-blocks')) ?>,
        syncSharedUrl: <?= json_encode(url('api/sites/' . $slug . '/sync-shared')) ?>,
        pagesUrl: <?= json_encode(url('api/sites/' . $slug . '/pages')) ?>
    };
</script>
<script src="<?= asset('assets/js/pagedock-preview-harness.js') ?>"></script>
<script src="<?= asset('assets/js/editor-tools.js') ?>"></script>
<script src="<?= asset('assets/js/editor.js') ?>"></script>
</body>
</html>
