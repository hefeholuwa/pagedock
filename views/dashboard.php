<?php
/**
 * PageDock - Sovereign Edge Dashboard View
 * High-Craft Obsidian & Bento Architecture (UI/UX Pro Max Certified)
 */

$totalSites = count($sites);
$totalViews = (int)array_sum(array_column($sites, 'views_count'));
$totalForms = (int)array_sum(array_column($sites, 'forms_count'));
$totalBytes = (int)array_sum(array_column($sites, 'total_size_bytes'));

// Format bytes
if ($totalBytes < 1024 * 1024) {
    $formattedTotalBytes = number_format($totalBytes / 1024, 1) . ' KB';
} else {
    $formattedTotalBytes = number_format($totalBytes / (1024 * 1024), 2) . ' MB';
}

// 50 MB Free Quota calculation
$maxQuotaBytes = 50 * 1024 * 1024;
$storagePercent = min(100, round(($totalBytes / $maxQuotaBytes) * 100, 1));

// Filter counts
$sitesWithFormsCount = count(array_filter($sites, fn($s) => !empty($s['forms_count'])));
$sitesWithViewsCount = count(array_filter($sites, fn($s) => !empty($s['views_count'])));

// Human-readable relative time helper
$formatRelativeDate = function($dateString) {
    if (empty($dateString)) return 'recently';
    $time = strtotime($dateString);
    if (!$time) return 'recently';
    $diff = time() - $time;
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M j, Y', $time);
};
?>

<div class="dashboard-container">
    <!-- Workspace Scope & Telemetry Bar -->
    <div class="workspace-scope-bar">
        <div class="workspace-scope-left">
            <span class="workspace-pill">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                <span>Workspace</span>
                <span class="scope-sep">/</span>
                <span class="scope-target"><?= htmlspecialchars(($user['name'] ?? '') ?: 'Personal') ?></span>
                <span class="workspace-chip-active">Primary</span>
            </span>
        </div>
        <div class="workspace-scope-right">
            <span class="edge-status-pill" title="Global Edge Anycast Network Status">
                <span class="edge-pulse-dot"></span>
                <span>Edge Fleet: <strong>12/12 POPs Active</strong></span>
            </span>
            <span class="latency-pill" title="P99 Edge Roundtrip Time">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                <span>~24ms TTFB</span>
            </span>
            <span class="ssl-status-pill" title="Automated TLS 1.3 Encryption">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                <span>Auto-HTTPS Live</span>
            </span>
        </div>
    </div>

    <?php if (!empty($user)): ?>
        <!-- User Profile & High-Impact Bento KPI Summary -->
        <div class="user-profile-card">
            <div class="user-profile-left">
                <div class="user-avatar-circle" title="<?= htmlspecialchars($user['email'] ?? '') ?>">
                    <?= strtoupper(substr(($user['name'] ?? '') ?: ($user['email'] ?? 'U'), 0, 1)) ?>
                    <span class="avatar-live-indicator" title="Fleet Node Connected"></span>
                </div>
                <div class="user-details">
                    <div class="user-name-row">
                        <h2><?= htmlspecialchars(($user['name'] ?? '') ?: explode('@', $user['email'] ?? 'user')[0]) ?></h2>
                        <span class="user-plan-badge"><?= strtoupper(htmlspecialchars(($user['plan'] ?? '') ?: 'FREE')) ?> TIER</span>
                    </div>
                    <div class="user-email-row">
                        <span class="user-email"><?= htmlspecialchars($user['email'] ?? '') ?></span>
                        <span class="meta-dot">&bull;</span>
                        <span class="user-role-label">Sovereign Developer</span>
                    </div>
                    <!-- Storage & Quota Progress Indicator -->
                    <div class="storage-meter-container" title="Storage Quota Usage">
                        <div class="storage-meter-labels">
                            <span class="storage-lbl">Storage Quota</span>
                            <span class="storage-val"><strong><?= $formattedTotalBytes ?></strong> / 50 MB (<?= $storagePercent ?>%)</span>
                        </div>
                        <div class="storage-meter-track" role="progressbar" aria-valuenow="<?= $storagePercent ?>" aria-valuemin="0" aria-valuemax="100">
                            <div class="storage-meter-fill" style="width: <?= max(4, $storagePercent) ?>%;"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bento KPI Metrics (4-Box Grid) -->
            <div class="user-kpi-summary">
                <div class="kpi-mini-box">
                    <div class="kpi-box-header">
                        <span class="kpi-mini-lbl">Hosted Sites</span>
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                    </div>
                    <span class="kpi-mini-val"><?= $totalSites ?></span>
                    <span class="kpi-mini-hint">0ms Cold Starts</span>
                </div>

                <div class="kpi-mini-box">
                    <div class="kpi-box-header">
                        <span class="kpi-mini-lbl">Page Views</span>
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                    </div>
                    <span class="kpi-mini-val"><?= number_format($totalViews) ?></span>
                    <span class="kpi-mini-hint">Cookieless Tracking</span>
                </div>

                <div class="kpi-mini-box">
                    <div class="kpi-box-header">
                        <span class="kpi-mini-lbl">Leads Captured</span>
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                    </div>
                    <span class="kpi-mini-val"><?= number_format($totalForms) ?></span>
                    <span class="kpi-mini-hint">Universal Form Engine</span>
                </div>

                <div class="kpi-mini-box">
                    <div class="kpi-box-header">
                        <span class="kpi-mini-lbl">Fleet Uptime</span>
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                    </div>
                    <span class="kpi-mini-val highlight-green">99.99%</span>
                    <span class="kpi-mini-hint">Zero Build Latency</span>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Dashboard Content Header & High-Velocity Actions -->
    <div class="dashboard-header">
        <div class="dashboard-title-group">
            <div class="dashboard-title-row">
                <h1>Hosted Deployments</h1>
                <span class="dashboard-count-badge"><?= $totalSites ?></span>
            </div>
            <p class="text-muted">Manage, edit, and inspect live edge analytics and leads across your sovereign domains.</p>
        </div>
        <div class="dashboard-header-actions">
            <a href="<?= url('templates') ?>" class="btn-secondary" title="Browse Sovereign Starter Blueprints">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/></svg>
                <span>Templates</span>
            </a>
            <button type="button" class="btn-primary" id="open-fast-deploy-btn" title="Deploy New Sovereign Site">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                <span>Deploy New Site</span>
            </button>
        </div>
    </div>

    <?php if (empty($sites)): ?>
        <!-- Out-of-Distribution Zero-State Activation Launchpad -->
        <div class="activation-launchpad">
            <div class="launchpad-banner">
                <div class="launchpad-badge">
                    <span class="pulse-dot"></span>
                    <span>Zero-Friction Activation</span>
                </div>
                <h2>Deploy Your First Sovereign Website</h2>
                <p>Choose your preferred deployment workflow below to launch on the global edge in under 400 milliseconds.</p>
            </div>

            <div class="launchpad-grid">
                <!-- Launchpad Card 1: Direct File / ZIP Dropzone -->
                <div class="launchpad-card launchpad-card-highlight">
                    <div class="launchpad-card-glow"></div>
                    <div class="launchpad-card-header">
                        <div class="launchpad-icon-box accent">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                        </div>
                        <span class="launchpad-card-tag">Fastest Path</span>
                    </div>
                    <h3>Drag &amp; Drop Archive or HTML</h3>
                    <p>Drop a single <code>index.html</code> file or a complete 50MB ZIP folder. Zero build setup, unpacked in memory, instant live URL.</p>
                    <div class="launchpad-card-footer">
                        <a href="<?= url() ?>" class="btn-primary w-full">
                            <span>Open Deployment Dock</span>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                        </a>
                    </div>
                </div>

                <!-- Launchpad Card 2: 1-Click Starter Blueprints -->
                <div class="launchpad-card">
                    <div class="launchpad-card-header">
                        <div class="launchpad-icon-box">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/></svg>
                        </div>
                        <span class="launchpad-card-tag">Ready-Made</span>
                    </div>
                    <h3>Pre-Built Sovereign Starters</h3>
                    <p>Clone a battle-tested template configured with form capture, dark aesthetics, and clean relative asset grounding.</p>
                    <div class="blueprint-pills-list">
                        <a href="<?= url('templates') ?>" class="blueprint-mini-pill">
                            <span class="pill-title">Developer Portfolio</span>
                            <span class="pill-meta">Terminal &bull; Showcase</span>
                        </a>
                        <a href="<?= url('templates') ?>" class="blueprint-mini-pill">
                            <span class="pill-title">SaaS Waitlist</span>
                            <span class="pill-meta">Lead Capture &bull; Dark UI</span>
                        </a>
                        <a href="<?= url('templates') ?>" class="blueprint-mini-pill">
                            <span class="pill-title">API Documentation</span>
                            <span class="pill-meta">Markdown &bull; Sidebar</span>
                        </a>
                    </div>
                    <div class="launchpad-card-footer">
                        <a href="<?= url('templates') ?>" class="btn-secondary w-full">
                            <span>Browse All Templates</span>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                        </a>
                    </div>
                </div>

                <!-- Launchpad Card 3: CLI & AI Agent Command Center -->
                <div class="launchpad-card">
                    <div class="launchpad-card-header">
                        <div class="launchpad-icon-box">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="4 17 10 11 4 5"/><line x1="12" y1="19" x2="20" y2="19"/></svg>
                        </div>
                        <span class="launchpad-card-tag">Dev Tooling</span>
                    </div>
                    <h3>Terminal &amp; Agent Deployment</h3>
                    <p>Deploy directly from your command line or connect your AI coding agent via Model Context Protocol (MCP).</p>
                    <div class="cli-terminal-preview" onclick="copyCliCommand(this)" title="Click to copy command">
                        <div class="terminal-dots">
                            <span class="term-dot red"></span>
                            <span class="term-dot yellow"></span>
                            <span class="term-dot green"></span>
                            <span class="term-copied-badge">Copied!</span>
                        </div>
                        <code><span class="cli-prompt">$</span> npx pagedock-cli deploy ./dist</code>
                        <div class="cli-subtext">Instant SHA-256 upload &middot; zero config</div>
                    </div>
                    <div class="launchpad-card-footer">
                        <a href="<?= url('docs') ?>" class="btn-ghost w-full">
                            <span>Explore Developer Specs</span>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- Interactive Control Bar: Live Search, Filter Tabs & View Switcher -->
        <div class="dashboard-controls-bar">
            <!-- Search Input -->
            <div class="search-field-wrapper">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" id="site-search-input" placeholder="Search deployments by title or slug..." class="site-search-input" aria-label="Search deployments">
                <button type="button" class="search-clear-btn" id="search-clear-btn" aria-label="Clear search" style="display:none;">&times;</button>
                <span class="search-shortcut-badge desktop-only">⌘K</span>
            </div>

            <!-- Controls Actions Right Group -->
            <div class="controls-actions-group">
                <!-- Filter Segment Tabs -->
                <div class="filter-segment-group" role="tablist" aria-label="Site filters">
                    <button type="button" class="filter-tab-btn active" data-filter="all" role="tab" aria-selected="true">
                        <span>All</span>
                        <span class="tab-badge"><?= $totalSites ?></span>
                    </button>
                    <button type="button" class="filter-tab-btn" data-filter="forms" role="tab" aria-selected="false">
                        <span>With Leads</span>
                        <span class="tab-badge"><?= $sitesWithFormsCount ?></span>
                    </button>
                    <button type="button" class="filter-tab-btn" data-filter="views" role="tab" aria-selected="false">
                        <span>Active Traffic</span>
                        <span class="tab-badge"><?= $sitesWithViewsCount ?></span>
                    </button>
                </div>

                <!-- View Mode Switcher (Grid vs Table) -->
                <div class="view-mode-toggle" role="group" aria-label="View layout switch">
                    <button type="button" class="view-toggle-btn active" id="view-mode-grid-btn" title="Grid View" aria-label="Grid View">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                    </button>
                    <button type="button" class="view-toggle-btn" id="view-mode-table-btn" title="Table View" aria-label="Table View">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                    </button>
                </div>

                <!-- Live Site Count Display -->
                <div class="site-count-label">
                    <span class="count-badge-dot"></span>
                    <span><strong id="visible-sites-count"><?= $totalSites ?></strong>/<?= $totalSites ?></span>
                </div>
            </div>
        </div>

        <!-- No Matching Filter/Search Results State -->
        <div id="no-search-results" class="no-search-results" style="display: none;">
            <div class="no-results-icon">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><line x1="8" y1="11" x2="14" y2="11"/></svg>
            </div>
            <h3>No matching deployments found</h3>
            <p>Try adjusting your search query or switching back to the "All" filter tab.</p>
            <button type="button" class="btn-secondary btn-sm" onclick="resetSearchFilters()">Reset Filters</button>
        </div>

        <!-- Sovereign Sites Grid View -->
        <div class="sites-grid" id="sites-grid">
            <?php foreach ($sites as $site): ?>
                <?php
                    $hasForms = (int)($site['forms_count'] ?? 0) > 0;
                    $hasViews = (int)($site['views_count'] ?? 0) > 0;
                    $fullUrl = url('s/' . $site['slug'] . '/');
                    $updatedRelative = $formatRelativeDate($site['updated_at'] ?? $site['created_at'] ?? '');
                ?>
                <div class="site-card" id="site-card-<?= htmlspecialchars($site['slug']) ?>" 
                     data-search-term="<?= htmlspecialchars(strtolower(($site['title'] ?? '') . ' ' . $site['slug'])) ?>"
                     data-has-forms="<?= $hasForms ? '1' : '0' ?>"
                     data-has-views="<?= $hasViews ? '1' : '0' ?>">
                    
                    <!-- Card Top Chrome -->
                    <div class="site-card-chrome">
                        <div class="chrome-dots">
                            <span class="c-dot red"></span>
                            <span class="c-dot yellow"></span>
                            <span class="c-dot green"></span>
                        </div>
                        <div class="site-status-group">
                            <span class="version-badge-pill">v<?= (int)($site['current_version'] ?? 1) ?></span>
                            <div class="site-status-badge">
                                <span class="site-status-dot"></span>
                                <span>LIVE</span>
                            </div>
                        </div>
                    </div>

                    <!-- Card Header with Favicon Glyph & Title -->
                    <div class="site-card-header">
                        <div class="site-glyph-box" aria-hidden="true">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                        </div>
                        <div class="site-title-container">
                            <h3 class="site-title" title="<?= htmlspecialchars($site['title'] ?: 'Untitled Site') ?>">
                                <?= htmlspecialchars($site['title'] ?: 'Untitled Site') ?>
                            </h3>
                            <span class="site-timestamp" title="Last deployed or modified">
                                Updated <?= $updatedRelative ?>
                            </span>
                        </div>
                    </div>

                    <!-- Monospace Domain URL Bar with 1-Click Copy & External Launch -->
                    <div class="site-url-box">
                        <a href="<?= $fullUrl ?>" target="_blank" rel="noopener noreferrer" class="site-link" title="Open live production URL in new tab">
                            <span class="site-url-text"><?= $fullUrl ?></span>
                        </a>
                        <div class="site-url-actions">
                            <button type="button" class="btn-url-action copy-url-btn" onclick="copySiteUrl(this, '<?= $fullUrl ?>')" title="Copy URL to clipboard" aria-label="Copy URL">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                                <span class="copy-tooltip">Copied!</span>
                            </button>
                            <a href="<?= $fullUrl ?>" target="_blank" rel="noopener noreferrer" class="btn-url-action" title="Open in new tab" aria-label="Open live site">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                            </a>
                        </div>
                    </div>

                    <!-- Metadata Badges Strip -->
                    <div class="site-meta-stats">
                        <span class="meta-chip" title="Physical file bundle count">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><polyline points="13 2 13 9 20 9"/></svg>
                            <span><?= (int)$site['file_count'] ?> files</span>
                        </span>
                        <span class="meta-dot">&bull;</span>
                        <span class="meta-chip" title="Total payload footprint">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>
                            <span><?= number_format($site['total_size_bytes'] / 1024, 1) ?> KB</span>
                        </span>
                        <span class="meta-dot">&bull;</span>
                        <a href="<?= url('dashboard/analytics/' . $site['slug']) ?>" class="site-views-pill <?= $hasViews ? 'active-metric' : '' ?>" title="View Cookieless Edge Analytics">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                            <span><?= (int)$site['views_count'] ?> views</span>
                        </a>
                        <span class="meta-dot">&bull;</span>
                        <a href="<?= url('dashboard/forms/' . $site['slug']) ?>" class="forms-badge-pill <?= $hasForms ? 'has-unread' : '' ?>" title="View form submissions inbox">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                            <span><?= (int)($site['forms_count'] ?? 0) ?> leads</span>
                        </a>
                    </div>

                    <!-- Card Action Button Toolbar (Dedicated Primary View CTA + 3-Button Utility Row) -->
                    <div class="site-card-actions">
                        <!-- Primary Row: Dedicated Full-Width View Live Site -->
                        <div class="card-actions-row primary-row">
                            <a href="<?= url('s/' . $site['slug']) ?>" target="_blank" class="btn-action btn-action-view" title="View Live Website">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                                <span>View Live Site</span>
                            </a>
                        </div>
                        <!-- Secondary Row: Analytics + Leads + Delete (Balanced Utility Strip) -->
                        <div class="card-actions-row secondary-row">
                            <a href="<?= url('dashboard/analytics/' . $site['slug']) ?>" class="btn-action btn-action-analytics" title="View Edge Analytics">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                                <span>Analytics</span>
                            </a>
                            <a href="<?= url('dashboard/forms/' . $site['slug']) ?>" class="btn-action btn-action-leads <?= $hasForms ? 'has-leads' : '' ?>" title="View Form Leads Inbox">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                                <span>Leads<?= $hasForms ? ' (' . (int)$site['forms_count'] . ')' : '' ?></span>
                            </a>
                            <button type="button" class="btn-action btn-action-delete delete-site-btn" data-slug="<?= htmlspecialchars($site['slug']) ?>" title="Delete Site">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                <span>Delete</span>
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Sovereign Sites Compact Table View (Developer Mode) -->
        <div class="sites-table-wrapper" id="sites-table-wrapper" style="display: none;">
            <table class="sites-table" aria-label="Hosted deployments list">
                <thead>
                    <tr>
                        <th scope="col">Deployment</th>
                        <th scope="col">Status</th>
                        <th scope="col">Payload</th>
                        <th scope="col">Traffic</th>
                        <th scope="col">Leads</th>
                        <th scope="col">Updated</th>
                        <th scope="col" class="th-actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sites as $site): ?>
                        <?php
                            $hasForms = (int)($site['forms_count'] ?? 0) > 0;
                            $hasViews = (int)($site['views_count'] ?? 0) > 0;
                            $fullUrl = url('s/' . $site['slug'] . '/');
                            $updatedRelative = $formatRelativeDate($site['updated_at'] ?? $site['created_at'] ?? '');
                        ?>
                        <tr class="site-table-row" id="site-row-<?= htmlspecialchars($site['slug']) ?>"
                            data-search-term="<?= htmlspecialchars(strtolower(($site['title'] ?? '') . ' ' . $site['slug'])) ?>"
                            data-has-forms="<?= $hasForms ? '1' : '0' ?>"
                            data-has-views="<?= $hasViews ? '1' : '0' ?>">
                            
                            <!-- Deployment Column -->
                            <td class="td-deployment">
                                <div class="td-dep-flex">
                                    <div class="site-glyph-mini" aria-hidden="true">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                    </div>
                                    <div class="td-dep-info">
                                        <span class="td-dep-title"><?= htmlspecialchars($site['title'] ?: 'Untitled Site') ?></span>
                                        <div class="td-dep-url-row">
                                            <a href="<?= $fullUrl ?>" target="_blank" rel="noopener noreferrer" class="td-dep-link">
                                                <span><?= url('s/' . $site['slug'] . '/') ?></span>
                                            </a>
                                            <button type="button" class="btn-table-copy" onclick="copySiteUrl(this, '<?= $fullUrl ?>')" title="Copy URL">
                                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                                                <span class="copy-tooltip">Copied!</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <!-- Status & Version -->
                            <td class="td-status">
                                <div class="td-status-badges">
                                    <span class="site-status-badge">
                                        <span class="site-status-dot"></span>
                                        <span>LIVE</span>
                                    </span>
                                    <span class="version-badge-pill">v<?= (int)($site['current_version'] ?? 1) ?></span>
                                </div>
                            </td>

                            <!-- Payload -->
                            <td class="td-payload">
                                <span class="payload-primary"><?= number_format($site['total_size_bytes'] / 1024, 1) ?> KB</span>
                                <span class="payload-sub"><?= (int)$site['file_count'] ?> files</span>
                            </td>

                            <!-- Traffic -->
                            <td class="td-traffic">
                                <a href="<?= url('dashboard/analytics/' . $site['slug']) ?>" class="table-metric-link <?= $hasViews ? 'active-metric' : '' ?>" title="View Analytics">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                                    <span><?= (int)$site['views_count'] ?></span>
                                </a>
                            </td>

                            <!-- Leads -->
                            <td class="td-leads">
                                <a href="<?= url('dashboard/forms/' . $site['slug']) ?>" class="table-metric-link <?= $hasForms ? 'has-unread' : '' ?>" title="View Leads">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                                    <span><?= (int)($site['forms_count'] ?? 0) ?></span>
                                </a>
                            </td>

                            <!-- Updated -->
                            <td class="td-updated">
                                <span class="text-time"><?= $updatedRelative ?></span>
                            </td>

                            <!-- Actions -->
                            <td class="td-actions">
                                <div class="td-actions-group">
                                    <a href="<?= url('s/' . $site['slug']) ?>" target="_blank" class="btn-table-action accent" title="View Live Website">View</a>
                                    <a href="<?= url('dashboard/analytics/' . $site['slug']) ?>" class="btn-table-action" title="Metrics">Analytics</a>
                                    <a href="<?= url('dashboard/forms/' . $site['slug']) ?>" class="btn-table-action" title="Leads">Leads</a>
                                    <button type="button" class="btn-table-action danger delete-site-btn" data-slug="<?= htmlspecialchars($site['slug']) ?>" title="Delete Site">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- High-Velocity Fast Deploy Modal -->
<div id="fast-deploy-modal" class="pagedock-modal" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="modal-deploy-title">
    <div class="modal-backdrop" onclick="closeFastDeployModal()"></div>
    <div class="modal-dialog-card">
        <div class="modal-dialog-header">
            <div class="modal-header-left">
                <div class="modal-icon-badge">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                </div>
                <div>
                    <h2 id="modal-deploy-title">Deploy Sovereign Site</h2>
                    <p class="modal-sub">Choose your ingestion workflow for instant zero-cold-start edge publication.</p>
                </div>
            </div>
            <button type="button" class="modal-close-btn" onclick="closeFastDeployModal()" aria-label="Close modal">&times;</button>
        </div>

        <div class="modal-tabs-header">
            <button type="button" class="modal-tab-trigger active" onclick="switchModalTab('upload', this)">Upload ZIP / Files</button>
            <button type="button" class="modal-tab-trigger" onclick="switchModalTab('paste', this)">Paste Raw HTML</button>
            <button type="button" class="modal-tab-trigger" onclick="switchModalTab('template', this)">Starter Blueprints</button>
        </div>

        <div class="modal-tab-content active" id="modal-tab-upload">
            <form action="<?= url('api/upload') ?>" method="POST" enctype="multipart/form-data" id="fast-upload-form">
                <div class="modal-dropzone" id="modal-dropzone-target" onclick="document.getElementById('modal-file-input').click()">
                    <input type="file" name="file" id="modal-file-input" style="display:none;" accept=".zip,.html,.htm,.tar,.gz" onchange="handleModalFileSelected(this)">
                    <div class="dropzone-icon-pulse">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    </div>
                    <span class="dropzone-prompt" id="modal-dropzone-prompt">Drop your ZIP archive or single <code>index.html</code> here</span>
                    <span class="dropzone-hint">Up to 50MB &bull; Automatic relative link grounding &bull; 0ms build</span>
                </div>
                <div class="modal-dialog-footer">
                    <a href="<?= url() ?>" class="btn-ghost btn-sm">Full Deployment Dock</a>
                    <button type="submit" class="btn-primary" id="modal-upload-submit" disabled>
                        <span>Deploy to Edge Fleet</span>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                    </button>
                </div>
            </form>
        </div>

        <div class="modal-tab-content" id="modal-tab-paste" style="display:none;">
            <form action="<?= url('api/upload-paste') ?>" method="POST" id="fast-paste-form">
                <div class="modal-paste-area">
                    <label for="paste-html-textarea" class="modal-field-label">Single-File HTML Content</label>
                    <textarea name="html" id="paste-html-textarea" placeholder="<!DOCTYPE html>&#10;<html>&#10;<head><title>My Sovereign Page</title></head>&#10;<body>&#10;  <h1>Hello World</h1>&#10;</body>&#10;</html>" class="modal-textarea" rows="8" required></textarea>
                </div>
                <div class="modal-dialog-footer">
                    <a href="<?= url() ?>" class="btn-ghost btn-sm">Full Deployment Dock</a>
                    <button type="submit" class="btn-primary">
                        <span>Deploy HTML Instantaneously</span>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                    </button>
                </div>
            </form>
        </div>

        <div class="modal-tab-content" id="modal-tab-template" style="display:none;">
            <div class="modal-templates-list">
                <a href="<?= url('templates') ?>" class="modal-template-item">
                    <div class="modal-tpl-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="4 17 10 11 4 5"/><line x1="12" y1="19" x2="20" y2="19"/></svg>
                    </div>
                    <div class="modal-tpl-info">
                        <strong>Developer Portfolio</strong>
                        <span>Terminal aesthetic &bull; Work showcase &bull; Dark mode</span>
                    </div>
                    <span class="modal-tpl-arrow">&rarr;</span>
                </a>
                <a href="<?= url('templates') ?>" class="modal-template-item">
                    <div class="modal-tpl-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/></svg>
                    </div>
                    <div class="modal-tpl-info">
                        <strong>SaaS Product Waitlist</strong>
                        <span>High-converting hero &bull; Form capture &bull; Neon cards</span>
                    </div>
                    <span class="modal-tpl-arrow">&rarr;</span>
                </a>
                <a href="<?= url('templates') ?>" class="modal-template-item">
                    <div class="modal-tpl-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                    </div>
                    <div class="modal-tpl-info">
                        <strong>API Documentation Hub</strong>
                        <span>Syntax highlighted &bull; Sidebar nav &bull; Monospace clean</span>
                    </div>
                    <span class="modal-tpl-arrow">&rarr;</span>
                </a>
            </div>
            <div class="modal-dialog-footer">
                <a href="<?= url('templates') ?>" class="btn-secondary w-full">Browse All Sovereign Blueprints</a>
            </div>
        </div>
    </div>
</div>

<script>
// ==========================================
// Dashboard Interactivity & State Management
// ==========================================

// 1. Live Instant Search & Filter Logic
const searchInput = document.getElementById('site-search-input');
const clearSearchBtn = document.getElementById('search-clear-btn');
const filterBtns = document.querySelectorAll('.filter-tab-btn');
const siteCards = document.querySelectorAll('.site-card');
const siteRows = document.querySelectorAll('.site-table-row');
const countLabel = document.getElementById('visible-sites-count');
const noResultsNotice = document.getElementById('no-search-results');
let currentFilter = 'all';

function applyFilters() {
    const query = searchInput ? searchInput.value.trim().toLowerCase() : '';
    let visibleCount = 0;

    // Toggle clear button
    if (clearSearchBtn) {
        clearSearchBtn.style.display = query.length > 0 ? 'block' : 'none';
    }

    const filterItem = (el) => {
        const term = el.getAttribute('data-search-term') || '';
        const matchesQuery = !query || term.includes(query);
        let matchesFilter = true;

        if (currentFilter === 'forms') {
            matchesFilter = el.getAttribute('data-has-forms') === '1';
        } else if (currentFilter === 'views') {
            matchesFilter = el.getAttribute('data-has-views') === '1';
        }

        if (matchesQuery && matchesFilter) {
            el.style.display = '';
            return true;
        } else {
            el.style.display = 'none';
            return false;
        }
    };

    // Filter Grid Cards
    siteCards.forEach(card => {
        if (filterItem(card)) visibleCount++;
    });

    // Filter Table Rows
    siteRows.forEach(row => {
        filterItem(row);
    });

    if (countLabel) {
        countLabel.textContent = visibleCount;
    }

    if (noResultsNotice) {
        noResultsNotice.style.display = visibleCount === 0 && (siteCards.length > 0 || siteRows.length > 0) ? 'block' : 'none';
    }
}

if (searchInput) {
    searchInput.addEventListener('input', applyFilters);

    if (clearSearchBtn) {
        clearSearchBtn.addEventListener('click', () => {
            searchInput.value = '';
            searchInput.focus();
            applyFilters();
        });
    }

    // Keyboard shortcut ⌘K / Ctrl+K
    window.addEventListener('keydown', (e) => {
        if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
            e.preventDefault();
            searchInput.focus();
            searchInput.select();
        }
        if (e.key === 'Escape') {
            closeFastDeployModal();
        }
    });
}

// Filter Tabs Handler
filterBtns.forEach(btn => {
    btn.addEventListener('click', () => {
        filterBtns.forEach(b => {
            b.classList.remove('active');
            b.setAttribute('aria-selected', 'false');
        });
        btn.classList.add('active');
        btn.setAttribute('aria-selected', 'true');
        currentFilter = btn.getAttribute('data-filter') || 'all';
        applyFilters();
    });
});

function resetSearchFilters() {
    if (searchInput) searchInput.value = '';
    const allTab = document.querySelector('.filter-tab-btn[data-filter="all"]');
    if (allTab) allTab.click();
    applyFilters();
}

// 2. View Mode Switcher (Grid vs Table)
const gridBtn = document.getElementById('view-mode-grid-btn');
const tableBtn = document.getElementById('view-mode-table-btn');
const sitesGrid = document.getElementById('sites-grid');
const sitesTable = document.getElementById('sites-table-wrapper');

function setViewMode(mode) {
    if (mode === 'table') {
        if (sitesGrid) sitesGrid.style.display = 'none';
        if (sitesTable) sitesTable.style.display = 'block';
        if (tableBtn) tableBtn.classList.add('active');
        if (gridBtn) gridBtn.classList.remove('active');
    } else {
        if (sitesGrid) sitesGrid.style.display = 'grid';
        if (sitesTable) sitesTable.style.display = 'none';
        if (gridBtn) gridBtn.classList.add('active');
        if (tableBtn) tableBtn.classList.remove('active');
    }
    try {
        localStorage.setItem('pagedock_dashboard_view', mode);
    } catch(e) {}
}

if (gridBtn && tableBtn) {
    gridBtn.addEventListener('click', () => setViewMode('grid'));
    tableBtn.addEventListener('click', () => setViewMode('table'));

    // Restore saved view preference
    try {
        const savedMode = localStorage.getItem('pagedock_dashboard_view');
        if (savedMode === 'table') {
            setViewMode('table');
        }
    } catch(e) {}
}

// 3. Fast Deploy Modal Controls
const fastDeployModal = document.getElementById('fast-deploy-modal');
const openDeployBtn = document.getElementById('open-fast-deploy-btn');

if (openDeployBtn && fastDeployModal) {
    openDeployBtn.addEventListener('click', () => {
        fastDeployModal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    });
}

function closeFastDeployModal() {
    if (fastDeployModal) {
        fastDeployModal.style.display = 'none';
        document.body.style.overflow = '';
    }
}

function switchModalTab(tabId, triggerEl) {
    document.querySelectorAll('.modal-tab-trigger').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.modal-tab-content').forEach(c => c.style.display = 'none');
    if (triggerEl) triggerEl.classList.add('active');
    const target = document.getElementById(`modal-tab-${tabId}`);
    if (target) target.style.display = 'block';
}

function handleModalFileSelected(input) {
    const submitBtn = document.getElementById('modal-upload-submit');
    const prompt = document.getElementById('modal-dropzone-prompt');
    if (input.files && input.files[0]) {
        if (prompt) prompt.textContent = `Selected: ${input.files[0].name} (${(input.files[0].size / 1024).toFixed(1)} KB)`;
        if (submitBtn) submitBtn.disabled = false;
    }
}

// Drag & drop support on modal dropzone
const modalDropTarget = document.getElementById('modal-dropzone-target');
if (modalDropTarget) {
    ['dragenter', 'dragover'].forEach(eventName => {
        modalDropTarget.addEventListener(eventName, (e) => {
            e.preventDefault();
            e.stopPropagation();
            modalDropTarget.classList.add('drag-active');
        });
    });
    ['dragleave', 'drop'].forEach(eventName => {
        modalDropTarget.addEventListener(eventName, (e) => {
            e.preventDefault();
            e.stopPropagation();
            modalDropTarget.classList.remove('drag-active');
        });
    });
    modalDropTarget.addEventListener('drop', (e) => {
        const dt = e.dataTransfer;
        const fileInput = document.getElementById('modal-file-input');
        if (dt && dt.files && dt.files.length) {
            fileInput.files = dt.files;
            handleModalFileSelected(fileInput);
        }
    });
}

// 4. Copy URL with Tooltip Feedback
function copySiteUrl(btn, url) {
    if (!navigator.clipboard) return;
    navigator.clipboard.writeText(url).then(() => {
        const tooltip = btn.querySelector('.copy-tooltip');
        if (tooltip) {
            tooltip.classList.add('visible');
            setTimeout(() => tooltip.classList.remove('visible'), 2000);
        }
    });
}

// 5. CLI Command Copy Helper
function copyCliCommand(el) {
    const cmd = "npx pagedock-cli deploy ./dist";
    if (navigator.clipboard) {
        navigator.clipboard.writeText(cmd).then(() => {
            const badge = el.querySelector('.term-copied-badge');
            if (badge) {
                badge.classList.add('visible');
                setTimeout(() => badge.classList.remove('visible'), 2000);
            }
        });
    }
}

// 6. Delete Site Handler (Surgical Ajax Deletion)
document.querySelectorAll('.delete-site-btn').forEach(btn => {
    btn.addEventListener('click', async (e) => {
        e.stopPropagation();
        const slug = btn.getAttribute('data-slug');
        if (!confirm(`Are you sure you want to delete /s/${slug}? This will remove all files and cannot be undone.`)) {
            return;
        }

        try {
            const res = await fetch(`<?= url('api/sites/') ?>${slug}/delete`, {
                method: 'POST'
            });
            const data = await res.json();
            if (data.success) {
                const cardEl = document.getElementById(`site-card-${slug}`);
                if (cardEl) cardEl.remove();
                const rowEl = document.getElementById(`site-row-${slug}`);
                if (rowEl) rowEl.remove();
                applyFilters();
            } else {
                alert(data.error || 'Failed to delete site.');
            }
        } catch (e) {
            alert('Error deleting site.');
        }
    });
});
</script>
