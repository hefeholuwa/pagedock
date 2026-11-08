<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title><?= htmlspecialchars($site['title']) ?> | PageDock AI HTML Editor</title>
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- CodeMirror Assets -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/theme/material-darker.min.css">
    <!-- PageDock Editor CSS -->
    <link rel="stylesheet" href="<?= asset('assets/css/editor.css') ?>">
</head>
<body class="hh-editor-body hh-mobile-mode-code">

    <!-- Top Navigation & Action Toolbar (1:1 htmlhost.co) -->
    <header class="hh-topbar" id="hh-topbar">
        <!-- Left Group -->
        <div class="hh-topbar-left">
            <a href="<?= url('dashboard') ?>" class="hh-btn-icon hh-back-btn" title="Back to Dashboard">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                <span class="hh-back-label">Back</span>
            </a>
            
            <div class="hh-project-badge" id="hh-project-badge">
                <span class="hh-badge-tag">PROJECT</span>
                <span class="hh-badge-dot">&middot;</span>
                <span class="hh-project-title" id="hh-project-title" title="Click to rename"><?= htmlspecialchars($site['title']) ?></span>
                
                <div class="hh-page-group">
                    <select id="hh-page-select" class="hh-page-switcher" title="Switch active page">
                        <?php 
                        $filesToList = !empty($htmlFiles) ? $htmlFiles : ['index.html'];
                        foreach ($filesToList as $f): 
                        ?>
                            <option value="<?= htmlspecialchars($f) ?>" <?= ($f === ($activeFile ?? 'index.html')) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($f) ?>
                            </option>
                        <?php endforeach; ?>
                        <option value="__new__">+ Add New Page...</option>
                    </select>
                    <button type="button" id="hh-add-page-btn" class="hh-btn-add-page" title="Create New Page">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>
                        <span>Page</span>
                    </button>
                </div>
            </div>

            <div class="hh-history-group">
                <button type="button" id="hh-undo-btn" class="hh-btn-icon" title="Undo (Ctrl+Z)" disabled>
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7v6h6"/><path d="M21 17a9 9 0 0 0-9-9 9 9 0 0 0-6 2.3L3 13"/></svg>
                </button>
                <button type="button" id="hh-redo-btn" class="hh-btn-icon" title="Redo (Ctrl+Y)" disabled>
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 7v6h-6"/><path d="M3 17a9 9 0 0 1 9-9 9 9 0 0 1 6 2.3l3 2.7"/></svg>
                </button>
            </div>

            <button type="button" id="hh-media-btn" class="hh-btn-ghost hh-media-trigger" title="Media & Asset Library">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                <span>Media</span>
            </button>
        </div>

        <!-- Center Controls -->
        <div class="hh-topbar-center">
            <div class="hh-divider"></div>

            <!-- Edit Mode On/Off Toggle -->
            <button type="button" id="hh-editmode-toggle" class="hh-toggle-pill active" title="Toggle Edit Mode (Ctrl+Shift+E)">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.83 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/></svg>
                <span id="hh-editmode-label">Edit Mode On</span>
            </button>

            <!-- AI Edit Switch Pill -->
            <button type="button" id="hh-aiedit-toggle" class="hh-aiedit-switch active" role="switch" aria-checked="true" aria-label="AI Edit" title="Toggle AI Edit vs Direct Edit (Ctrl+J)">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l2.4 7.2L22 12l-7.6 2.8L12 22l-2.4-7.2L2 12l7.6-2.8z"/></svg>
                <span>AI Edit</span>
                <span aria-hidden="true" class="hh-switch-track">
                    <span class="hh-switch-thumb"></span>
                </span>
            </button>

            <!-- AI Chat Drawer Trigger -->
            <button type="button" id="hh-chat-btn" class="hh-btn-ghost hh-chat-trigger" title="AI Assistant Chat (Ctrl+K)">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                <span>Chat</span>
            </button>

            <!-- Daily Free Quota Indicator -->
            <div class="hh-quota-pill" id="hh-quota-pill" title="Daily AI Edits: 0 of 5 used today">
                <span class="hh-quota-dot"></span>
                <span class="hh-quota-label" id="hh-quota-label">0/5</span>
            </div>

            <!-- Responsive Viewport Switcher -->
            <div class="hh-device-switcher">
                <button type="button" class="hh-device-btn active" data-device="desktop" data-width="100%" title="Desktop View (100%)">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="14" x="2" y="3" rx="2"/><line x1="8" x2="16" y1="21" y2="21"/><line x1="12" x2="12" y1="17" y2="21"/></svg>
                </button>
                <button type="button" class="hh-device-btn" data-device="tablet" data-width="768px" title="Tablet View (768px)">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="16" height="20" x="4" y="2" rx="2"/><line x1="12" x2="12.01" y1="18" y2="18"/></svg>
                </button>
                <button type="button" class="hh-device-btn" data-device="mobile" data-width="375px" title="Mobile View (375px)">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="14" height="20" x="5" y="2" rx="2"/><path d="M12 18h.01"/></svg>
                </button>
            </div>
        </div>

        <!-- Right Group -->
        <div class="hh-topbar-right">
            <!-- Tools Dropdown -->
            <div class="hh-dropdown">
                <button type="button" id="hh-tools-btn" class="hh-btn-ghost hh-dropdown-trigger" title="Tools & Utilities">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                    <span>Tools</span>
                    <svg width="8" height="5" viewBox="0 0 8 5" class="hh-chevron"><path d="M1 1l3 3 3-3" fill="none" stroke="currentColor" stroke-width="1.2"/></svg>
                </button>
                <div class="hh-dropdown-menu hidden" id="hh-tools-menu">
                    <button type="button" class="hh-menu-item" id="tool-format-code">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
                        <span>Format Code</span>
                    </button>
                    <button type="button" class="hh-menu-item" id="tool-clean-html">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                        <span>Clean HTML</span>
                    </button>
                    <button type="button" class="hh-menu-item" id="tool-revisions">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 14 14"/></svg>
                        <span>Version History (v<?= (int)($site['current_version'] ?? 1) ?>)</span>
                    </button>
                    <button type="button" class="hh-menu-item" id="tool-forms-inbox">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                        <span>Forms &amp; Inquiries</span>
                    </button>
                    <div class="hh-menu-divider"></div>
                    <a href="<?= url('s/' . $site['slug'] . '/') ?>" target="_blank" class="hh-menu-item">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                        <span>Open Live URL</span>
                    </a>
                </div>
            </div>

            <!-- TTL Expiry Dropdown -->
            <div class="hh-expiry-pill">
                <span class="hh-expiry-label">TTL</span>
                <select id="hh-expiry-select" class="hh-expiry-select" title="Site Expiry / TTL">
                    <option value="never" selected>Never</option>
                    <option value="24h">24 hours</option>
                    <option value="7d">7 days</option>
                    <option value="30d">30 days</option>
                </select>
            </div>

            <!-- Save Draft Button -->
            <button type="button" id="hh-savedraft-btn" class="hh-btn-ghost hh-save-btn" title="Save Draft (Ctrl+S)">
                <span class="hh-status-dot saved" id="hh-status-dot"></span>
                <span id="hh-save-status-text">Save Draft</span>
            </button>

            <!-- Publish Button -->
            <?php if ($canManage): ?>
                <button type="button" id="hh-publish-btn" class="hh-btn-publish" title="Publish and Deploy Changes Live">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                    <span>Publish</span>
                </button>
            <?php else: ?>
                <span class="hh-badge-readonly">Read Only</span>
            <?php endif; ?>
        </div>
    </header>

    <!-- Mobile Segmented View Switcher (Visible only on mobile/tablet <= 768px) -->
    <div class="hh-mobile-view-bar" id="hh-mobile-view-bar">
        <button type="button" class="hh-mobile-view-btn active" data-view="code" id="hh-mobile-btn-code">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
            <span>Code</span>
        </button>
        <button type="button" class="hh-mobile-view-btn" data-view="preview" id="hh-mobile-btn-preview">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polygon points="10 8 16 12 10 16 10 8"/></svg>
            <span>Preview</span>
        </button>
    </div>

    <!-- Main Workspace (Left Collapsible Drawer + Center Preview Canvas + Right Chat Drawer) -->
    <main class="hh-workspace" id="hh-workspace">
        
        <!-- Left Side Collapsible Panel (Code | Library | Structure | Shared) -->
        <aside class="hh-left-panel" id="hh-left-panel">
            
            <!-- Panel Tabs Strip -->
            <div class="hh-panel-tabs-bar">
                <div class="hh-panel-tabs">
                    <button type="button" class="hh-tab-btn active" data-tab="code" aria-label="Code" title="Source Code Editor">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
                        <span>Code</span>
                    </button>
                    <button type="button" class="hh-tab-btn" data-tab="library" aria-label="Library" title="Component Library">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                        <span>Library</span>
                    </button>
                    <button type="button" class="hh-tab-btn" data-tab="structure" aria-label="Structure" title="DOM Structure Tree">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="21" y1="6" x2="3" y2="6"/><line x1="15" y1="12" x2="3" y2="12"/><line x1="17" y1="18" x2="3" y2="18"/></svg>
                        <span>Structure</span>
                    </button>
                    <button type="button" class="hh-tab-btn" data-tab="shared" aria-label="Shared" title="Shared Multi-Page Blocks">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/><polyline points="16 6 12 2 8 6"/><line x1="12" y1="2" x2="12" y2="15"/></svg>
                        <span>Shared</span>
                    </button>
                </div>

                <button type="button" id="hh-collapse-panel-btn" class="hh-btn-icon-subtle" title="Toggle Side Panel (Ctrl+P)">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 3v18"/></svg>
                </button>
            </div>

            <!-- Tab 1: Code View -->
            <div class="hh-tab-content active" id="hh-tab-code">
                <div class="hh-code-subbar">
                    <div class="hh-code-files">
                        <button type="button" class="hh-file-chip active" data-file="html">
                            <span class="hh-chip-dot dot-html"></span>
                            <span><?= htmlspecialchars($activeFile ?? 'index.html') ?></span>
                        </button>
                        <button type="button" class="hh-file-chip" data-file="css">
                            <span class="hh-chip-dot dot-css"></span>
                            <span>style.css</span>
                        </button>
                        <button type="button" class="hh-file-chip" data-file="js">
                            <span class="hh-chip-dot dot-js"></span>
                            <span>script.js</span>
                        </button>
                    </div>
                    <div class="hh-code-lang-tag" id="hh-code-lang-tag">HTML</div>
                </div>
                <div class="hh-editor-mount" id="hh-editor-mount">
                    <textarea id="hh-source-textarea" spellcheck="false"><?= htmlspecialchars($html ?? '') ?></textarea>
                </div>
            </div>

            <!-- Tab 2: Library View (46 Sections & 48 Elements) -->
            <div class="hh-tab-content" id="hh-tab-library">
                <div class="hh-lib-header">
                    <div class="hh-lib-subtabs">
                        <button type="button" class="hh-lib-subtab active" data-lib-view="sections">
                            <span>Sections</span>
                            <span class="hh-pill-num">46</span>
                        </button>
                        <button type="button" class="hh-lib-subtab" data-lib-view="elements">
                            <span>Elements</span>
                            <span class="hh-pill-num">48</span>
                        </button>
                    </div>
                    <div class="hh-lib-search">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        <input type="text" id="hh-lib-search-input" placeholder="Search 46 sections &amp; 48 elements...">
                    </div>
                </div>

                <div class="hh-lib-notice">
                    Drag an element into any part of the preview. Most copy your page's own styles instantly; ✦ ones are designed by AI.
                </div>

                <!-- Sections Catalog -->
                <div class="hh-lib-scrollable" id="hh-lib-sections-container">
                    <!-- Populated dynamically via editor.js -->
                </div>

                <!-- Elements Catalog -->
                <div class="hh-lib-scrollable hidden" id="hh-lib-elements-container">
                    <!-- Populated dynamically via editor.js -->
                </div>
            </div>

            <!-- Tab 3: Structure Tree (Live DOM Hierarchy) -->
            <div class="hh-tab-content" id="hh-tab-structure">
                <div class="hh-structure-header">
                    <span class="hh-structure-title">Page Outline</span>
                    <button type="button" id="hh-refresh-tree-btn" class="hh-btn-icon-subtle" title="Refresh Tree">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 2v6h-6"/><path d="M3 12a9 9 0 0 1 15-6.7L21 8"/><path d="M3 22v-6h6"/><path d="M21 12a9 9 0 0 1-15 6.7L3 16"/></svg>
                    </button>
                </div>
                <div class="hh-tree-container" id="hh-tree-container">
                    <div class="hh-tree-empty">Analyzing page structure...</div>
                </div>
            </div>

            <!-- Tab 4: Shared Multi-Page Blocks -->
            <div class="hh-tab-content" id="hh-tab-shared">
                <div class="hh-shared-header">
                    <span class="hh-shared-title">Shared Layout Blocks</span>
                    <span class="hh-shared-desc">Sync headers and footers across all static pages</span>
                </div>
                <div class="hh-shared-list" id="hh-shared-list">
                    <?php if (!empty($sharedReport['blocks'])): ?>
                        <?php foreach ($sharedReport['blocks'] as $name => $block): ?>
                            <div class="hh-shared-card">
                                <div class="hh-shared-meta">
                                    <span class="hh-shared-name"><?= htmlspecialchars($name) ?></span>
                                    <span class="hh-shared-pages"><?= count($block['pages'] ?? []) ?> pages</span>
                                </div>
                                <button type="button" class="hh-btn-xs sync-shared-btn" data-block="<?= htmlspecialchars($name) ?>">Sync to All</button>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="hh-empty-shared">
                            <p>No <code>&lt;!-- pd:shared --&gt;</code> markers found.</p>
                            <span class="hh-shared-tip">Wrap your navigation bar or footer in <code>&lt;!-- pd:shared header --&gt;</code> to synchronize it across all pages automatically.</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </aside>

        <!-- Splitter Drag Resizer -->
        <div class="hh-splitter" id="hh-splitter"></div>

        <!-- Center Live Canvas Area -->
        <section class="hh-canvas-area" id="hh-canvas-area">
            
            <!-- Centered Device Viewport Wrapper -->
            <div class="hh-viewport-wrapper" id="hh-viewport-wrapper" style="width: 100%;">
                
                <!-- URL Pill & Security Indicator -->
                <div class="hh-canvas-url-bar desktop-only">
                    <div class="hh-canvas-ssl">
                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    </div>
                    <span class="hh-canvas-url"><?= url('s/' . $site['slug'] . '/') ?></span>
                    <span class="hh-canvas-pulse" title="Visual Inspector Active"></span>
                </div>

                <!-- Sandboxed Live Preview Iframe -->
                <div class="hh-iframe-container" id="hh-iframe-container">
                    <iframe 
                        id="preview-iframe" 
                        src="<?= url('s/' . $site['slug'] . '/') ?>?preview=1&t=<?= time() ?>" 
                        sandbox="allow-scripts allow-same-origin allow-popups" 
                        class="hh-preview-frame"
                        frameborder="0"
                    ></iframe>
                </div>

            </div>

            <!-- Floating Element Context Popovers (Fixed z-[9999] directly over iframe) -->
            <div id="hh-popover-anchor" class="hh-popover-anchor hidden">
                
                <!-- 1. Floating AI Edit Popover (1:1 with htmlhost.co popup_inner.html) -->
                <div id="hh-ai-popover" class="hh-popover-card hh-ai-popover-card">
                    <!-- Popover Header -->
                    <div class="hh-popover-header">
                        <div class="hh-popover-title-row">
                            <span class="hh-popover-tag" id="pop-ai-tag">BUTTON</span>
                            <span class="hh-popover-snippet" id="pop-ai-snippet">Get Started Now</span>
                        </div>
                        <div class="hh-popover-controls">
                            <!-- Toggle Mode Button -->
                            <button type="button" id="pop-mode-switch" role="switch" aria-checked="true" aria-label="Switch to Direct Element Edit" class="hh-popover-mode-pill active">
                                <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 2l2.4 7.2L22 12l-7.6 2.8L12 22l-2.4-7.2L2 12l7.6-2.8z"/></svg>
                                <span>AI Edit</span>
                                <span class="hh-popover-knob-track"><span class="hh-popover-knob-thumb"></span></span>
                            </button>
                            <button type="button" id="pop-close-btn" class="hh-popover-close-btn" aria-label="Close AI edit">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M18 6 6 18"/><path d="M6 6l12 12"/></svg>
                            </button>
                        </div>
                    </div>

                    <!-- Popover Body -->
                    <div class="hh-popover-body">
                        <label for="ai-edit-prompt" class="hh-popover-label">Describe what you want to change:</label>
                        
                        <!-- Quick Suggestion Action Chips -->
                        <div class="hh-popover-chips" id="pop-ai-chips">
                            <button type="button" class="hh-chip">Add a hover state</button>
                            <button type="button" class="hh-chip">Add hover effect</button>
                            <button type="button" class="hh-chip">Change colors</button>
                            <button type="button" class="hh-chip">Make larger</button>
                        </div>

                        <!-- Prompt Textarea -->
                        <div class="hh-popover-input-wrap">
                            <textarea id="ai-edit-prompt" placeholder="Change the style, add a hover effect, update the text… (@ to reference a file)" rows="2"></textarea>
                        </div>
                    </div>

                    <!-- Quota & Model Bar -->
                    <div class="hh-popover-footer-meta">
                        <div class="hh-popover-quota-row">
                            <div class="hh-popover-progress-bar">
                                <div class="hh-popover-progress-fill" id="pop-quota-progress" style="width: 0%;"></div>
                            </div>
                            <span class="hh-popover-quota-text" id="pop-quota-text">0/5 today · from 5 per task</span>
                        </div>

                        <div class="hh-popover-model-row">
                            <div class="hh-popover-model-select">
                                <button type="button" class="hh-model-select-btn" id="pop-model-btn">
                                    <span id="pop-model-label">Gemini 3.5 Flash Lite</span>
                                    <svg width="8" height="5" viewBox="0 0 8 5"><path d="M1 1l3 3 3-3" fill="none" stroke="currentColor" stroke-width="1.2"/></svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="hh-popover-actions">
                        <button type="button" class="hh-popover-btn-ghost" id="pop-cancel-btn">Cancel</button>
                        <button type="button" class="hh-popover-btn-accent" id="pop-apply-btn">
                            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 2l2.4 7.2L22 12l-7.6 2.8L12 22l-2.4-7.2L2 12l7.6-2.8z"/></svg>
                            <span>Apply Changes</span>
                        </button>
                    </div>
                </div>

                <!-- 2. Floating Direct Element Edit Popover (1:1 with htmlhost.co direct_edit_popup.html) -->
                <div id="hh-direct-popover" class="hh-popover-card hh-direct-popover-card hidden">
                    <!-- Popover Header -->
                    <div class="hh-popover-header">
                        <div class="hh-popover-title-row">
                            <span class="hh-popover-tag" id="pop-direct-tag">BUTTON</span>
                            <span class="hh-popover-snippet" id="pop-direct-snippet">Get Started Now</span>
                        </div>
                        <div class="hh-popover-controls">
                            <!-- Toggle Mode Button -->
                            <button type="button" id="pop-direct-mode-switch" role="switch" aria-checked="false" aria-label="Switch to AI Edit" class="hh-popover-mode-pill">
                                <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 2l2.4 7.2L22 12l-7.6 2.8L12 22l-2.4-7.2L2 12l7.6-2.8z"/></svg>
                                <span>AI Edit</span>
                                <span class="hh-popover-knob-track"><span class="hh-popover-knob-thumb off"></span></span>
                            </button>
                            <button type="button" id="pop-direct-close-btn" class="hh-popover-close-btn" aria-label="Close Element Edit">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M18 6 6 18"/><path d="M6 6l12 12"/></svg>
                            </button>
                        </div>
                    </div>

                    <!-- Direct Text Editor Section -->
                    <div class="hh-popover-body">
                        <h4 class="hh-section-micro-heading">Text</h4>
                        <div class="hh-direct-text-wrap">
                            <textarea id="direct-text-input" aria-label="Text" rows="2"></textarea>
                            <button type="button" id="direct-inline-edit-btn" class="hh-inline-edit-link">
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M17 3a2.83 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/></svg>
                                <span>Or edit it on the page (double-click)</span>
                            </button>
                        </div>
                    </div>

                    <!-- Direct Action Toolbar Footer -->
                    <div class="hh-direct-toolbar">
                        <button type="button" id="act-duplicate-btn" class="hh-direct-tool-btn">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                            <span>Duplicate</span>
                        </button>
                        <button type="button" id="act-code-btn" class="hh-direct-tool-btn">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="m16 18 6-6-6-6"/><path d="m8 6-6 6 6 6"/></svg>
                            <span>Code</span>
                        </button>
                        <span class="hh-toolbar-spacer"></span>
                        <button type="button" id="act-delete-btn" class="hh-direct-tool-btn danger">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
                            <span>Delete</span>
                        </button>
                    </div>
                </div>

            </div>

        </section>

        <!-- Right Sliding AI Chat Drawer (Slides in on Chat click or Ctrl+K) -->
        <aside class="hh-chat-drawer" id="hh-chat-drawer">
            <div class="hh-chat-header">
                <div class="hh-chat-title-group">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                    <span class="hh-chat-title">AI Assistant</span>
                    <span class="hh-model-badge">Gemini 3.5</span>
                </div>
                <button type="button" id="hh-close-chat-btn" class="hh-btn-icon-subtle" title="Close Chat (Esc)">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18"/><path d="M6 6l12 12"/></svg>
                </button>
            </div>

            <!-- Chat Stream History -->
            <div class="hh-chat-messages" id="hh-chat-messages">
                <div class="hh-chat-message assistant">
                    <div class="hh-msg-avatar">✦</div>
                    <div class="hh-msg-bubble">
                        <p>Hi! I can generate components, update page content, or rewrite styling across your site.</p>
                        <p class="hh-msg-tip">Tip: You can also click any element on the preview canvas to edit it with AI or modify text directly.</p>
                    </div>
                </div>
            </div>

            <!-- Chat Input Bar -->
            <div class="hh-chat-footer">
                <div class="hh-chat-input-wrapper">
                    <textarea id="hh-chat-input" placeholder="Ask AI to change anything on this page…" rows="2"></textarea>
                    <button type="button" id="hh-send-chat-btn" class="hh-send-btn" title="Send message">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                    </button>
                </div>
            </div>
        </aside>

    </main>

    <!-- Modals -->
    <!-- 1. Media Manager Modal -->
    <div id="hh-media-modal" class="hh-modal-overlay hidden">
        <div class="hh-modal-card hh-modal-card-lg">
            <div class="hh-modal-header">
                <h3>Media &amp; Asset Library</h3>
                <button type="button" class="hh-modal-close" data-close-modal>&times;</button>
            </div>
            <div class="hh-modal-body">
                <div class="hh-media-upload-zone" id="hh-media-dropzone">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    <p>Drop images or media files here to upload, or</p>
                    <input type="file" id="hh-media-file-input" multiple accept="image/*,video/*,.pdf" class="hidden">
                    <button type="button" class="hh-btn-xs" id="hh-media-browse-btn">Browse Files</button>
                    <span class="hh-media-help-text">PNG, JPG, SVG, WebP, GIF, MP4 (max 25MB)</span>
                </div>
                <div class="hh-media-gallery-header">
                    <span id="hh-media-count-label">Uploaded Assets</span>
                    <button type="button" class="hh-btn-icon-subtle" id="hh-media-refresh-btn" title="Refresh Media">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 4v6h-6M1 20v-6h6"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
                    </button>
                </div>
                <div class="hh-media-gallery" id="hh-media-gallery">
                    <div class="hh-empty-media">Loading assets...</div>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. Versions & Revisions Modal -->
    <div id="hh-versions-modal" class="hh-modal-overlay hidden">
        <div class="hh-modal-card hh-modal-card-md">
            <div class="hh-modal-header">
                <h3>Version History &mdash; <?= htmlspecialchars($site['title']) ?></h3>
                <button type="button" class="hh-modal-close" data-close-modal title="Close (Esc)">&times;</button>
            </div>
            <div class="hh-modal-body">
                <div id="hh-versions-list" class="hh-versions-timeline">
                    <div style="text-align:center; padding:20px; color:var(--fg-4);">Loading historical snapshots...</div>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. Form Submissions Modal -->
    <div id="hh-forms-modal" class="hh-modal-overlay hidden">
        <div class="hh-modal-card hh-modal-card-lg">
            <div class="hh-modal-header">
                <div class="hh-modal-title-group">
                    <h3>Form Submissions &mdash; <?= htmlspecialchars($site['title']) ?></h3>
                    <span class="hh-badge-count" id="hh-forms-count-badge">0</span>
                </div>
                <button type="button" class="hh-modal-close" data-close-modal title="Close (Esc)">&times;</button>
            </div>
            <div class="hh-modal-body">
                <div class="hh-form-endpoint-info">
                    <div class="hh-form-endpoint-row">
                        <span class="hh-endpoint-tag">Native Form Endpoint:</span>
                        <code><?= url('__pd/form') ?></code>
                    </div>
                    <p class="hh-endpoint-hint">Any HTML &lt;form action="/__pd/form" method="POST"&gt; submits directly here with zero backend code required.</p>
                </div>
                <div id="hh-forms-container">
                <?php
                $submissions = Forms::getSubmissions((int)$site['id']);
                if (empty($submissions)):
                ?>
                    <div class="hh-empty-submissions">
                        <p>No submissions received yet.</p>
                    </div>
                <?php else: ?>
                    <div class="hh-submissions-grid">
                        <?php foreach ($submissions as $sub): ?>
                            <div class="hh-submission-item">
                                <div class="hh-sub-header">
                                    <span><?= htmlspecialchars($sub['created_at']) ?></span>
                                    <span><?= htmlspecialchars($sub['ip_address']) ?></span>
                                </div>
                                <div class="hh-sub-body">
                                    <?php foreach ($sub['data'] as $k => $v): ?>
                                        <div class="hh-sub-row">
                                            <strong><?= htmlspecialchars($k) ?>:</strong>
                                            <span><?= nl2br(htmlspecialchars(is_array($v) ? json_encode($v) : $v)) ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Page Modal -->
    <div id="hh-add-page-modal" class="hh-modal-overlay hidden" data-modal="add-page">
        <div class="hh-modal-card hh-modal-card-md">
            <div class="hh-modal-header">
                <h3>Add New Page</h3>
                <button type="button" class="hh-modal-close" data-close-modal="add-page">&times;</button>
            </div>
            <div class="hh-modal-body">
                <form id="hh-add-page-form" onsubmit="return false;">
                    <div class="hh-form-group">
                        <label for="hh-new-page-title">Page Title</label>
                        <input type="text" id="hh-new-page-title" class="hh-input" placeholder="e.g. About Us, Contact, Pricing" required autofocus autocomplete="off">
                    </div>
                    <div class="hh-form-group">
                        <label for="hh-new-page-filename">Filename / URL Slug</label>
                        <div class="hh-input-slug-wrap">
                            <span class="hh-slug-prefix">/</span>
                            <input type="text" id="hh-new-page-filename" class="hh-input" placeholder="about.html" required autocomplete="off">
                        </div>
                        <span class="hh-input-hint">Will resolve to Clean URL e.g. /about</span>
                    </div>
                    <div class="hh-form-group">
                        <label for="hh-new-page-layout">Layout Template</label>
                        <select id="hh-new-page-layout" class="hh-select">
                            <option value="inherit" selected>Inherit Site Layout (Shared Header, Footer &amp; Styles)</option>
                            <option value="clone">Clone Current Page (<?= htmlspecialchars($activeFile ?? 'index.html') ?>)</option>
                            <option value="blank">Blank HTML5 Canvas</option>
                        </select>
                    </div>
                    <div class="hh-modal-actions">
                        <button type="button" class="hh-btn-secondary" data-close-modal="add-page">Cancel</button>
                        <button type="submit" id="hh-create-page-submit" class="hh-btn-primary">Create Page</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Toast Notification -->
    <div id="hh-toast" class="hh-toast hidden"></div>

    <!-- PageDock Global Configuration for Client JS -->
    <?php $editorTokenQuery = !empty($_GET['token']) ? ('?token=' . urlencode($_GET['token'])) : ''; ?>
    <script>
        window.PAGEDOCK_CONFIG = {
            slug: '<?= addslashes($site['slug']) ?>',
            saveUrl: '<?= url('api/editor/' . $site['slug']) . $editorTokenQuery ?>',
            previewUrl: '<?= url('s/' . $site['slug'] . '/') ?>',
            aiAvailabilityUrl: '<?= url('api/ai/availability') ?>',
            aiTaskUrl: '<?= url('api/ai/task') . $editorTokenQuery ?>',
            aiBuiltinUrl: '<?= url('api/ai/builtin') . $editorTokenQuery ?>',
            versionsUrl: '<?= url('api/sites/' . $site['slug'] . '/versions') . $editorTokenQuery ?>',
            rollbackUrl: '<?= url('api/sites/' . $site['slug'] . '/rollback') . $editorTokenQuery ?>',
            sharedBlocksUrl: '<?= url('api/sites/' . $site['slug'] . '/shared-blocks') . $editorTokenQuery ?>',
            syncSharedUrl: '<?= url('api/sites/' . $site['slug'] . '/sync-shared') . $editorTokenQuery ?>',
            mediaUrl: '<?= url('api/sites/' . $site['slug'] . '/media') . $editorTokenQuery ?>',
            mediaDeleteUrl: '<?= url('api/sites/' . $site['slug'] . '/media/delete') . $editorTokenQuery ?>',
            formsUrl: '<?= url('api/sites/' . $site['slug'] . '/forms') . $editorTokenQuery ?>',
            pagesUrl: '<?= url('api/sites/' . $site['slug'] . '/pages') . $editorTokenQuery ?>',
            pagesDeleteUrl: '<?= url('api/sites/' . $site['slug'] . '/pages/delete') . $editorTokenQuery ?>',
            htmlFiles: <?= json_encode(!empty($htmlFiles) ? $htmlFiles : ['index.html']) ?>,
            activeFile: '<?= addslashes($activeFile ?? 'index.html') ?>',
            currentVersion: <?= (int)($site['current_version'] ?? 1) ?>,
            initialTimestamp: <?= (int)(strtotime($site['updated_at'] ?? $site['created_at'] ?? 'now') * 1000) ?>,
            canManage: <?= $canManage ? 'true' : 'false' ?>,
            hasPhysicalCss: <?= file_exists(SITES_DIR . '/' . $site['slug'] . '/style.css') ? 'true' : 'false' ?>,
            hasPhysicalJs: <?= file_exists(SITES_DIR . '/' . $site['slug'] . '/script.js') ? 'true' : 'false' ?>,
            initialRedirects: <?= json_encode(@file_get_contents(SITES_DIR . '/' . $site['slug'] . '/_redirects') ?: '') ?>,
            initialCss: <?= json_encode(@file_get_contents(SITES_DIR . '/' . $site['slug'] . '/style.css') ?: '') ?>,
            initialJs: <?= json_encode(@file_get_contents(SITES_DIR . '/' . $site['slug'] . '/script.js') ?: '') ?>
        };
    </script>

    <!-- Dependencies -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/xml/xml.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/javascript/javascript.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/css/css.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/htmlmixed/htmlmixed.min.js"></script>
    
    <!-- PageDock 1:1 Editor Engine -->
    <script src="<?= asset('assets/js/editor.js') ?>"></script>
</body>
</html>
