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

// AI assistant metadata resolver
$detectAiClient = function(string $tokenName, string $prefix): array {
    $lower = strtolower($tokenName);
    $isOAuth = str_starts_with($prefix, 'pd_oauth_') || str_starts_with($lower, 'oauth:');
    
    $cleanName = $tokenName;
    if (str_starts_with($cleanName, 'OAuth: ')) {
        $cleanName = substr($cleanName, 7);
    }

    $provider = 'AI Assistant';
    $protocol = $isOAuth ? 'Remote MCP (OAuth 2.1)' : 'Personal Access Token (PAT)';

    if (str_contains($lower, 'chatgpt') || str_contains($lower, 'openai')) {
        $provider = 'ChatGPT (OpenAI)';
    } elseif (str_contains($lower, 'claude') || str_contains($lower, 'anthropic')) {
        $provider = 'Claude (Anthropic)';
    } elseif (str_contains($lower, 'cursor')) {
        $provider = 'Cursor IDE';
    } elseif (str_contains($lower, 'copilot')) {
        $provider = 'GitHub Copilot';
    } elseif ($isOAuth) {
        $provider = $cleanName ?: 'MCP AI Client';
    }

    return [
        'name' => $cleanName ?: $provider,
        'provider' => $provider,
        'protocol' => $protocol,
        'isOAuth' => $isOAuth,
    ];
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
            <span class="edge-status-pill" title="Hosting Network Status">
                <span class="edge-pulse-dot"></span>
                <span>Network Status: <strong>All Systems Online</strong></span>
            </span>
            <span class="latency-pill" title="Fast Page Loading">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                <span>Instant Load</span>
            </span>
            <span class="ssl-status-pill" title="Automatic HTTPS Encryption">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                <span>SSL Padlock Active</span>
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
                        <span class="user-role-label">Workspace Owner</span>
                    </div>
                    <!-- Storage & Quota Progress Indicator -->
                    <div class="storage-meter-container" title="Storage Space Used">
                        <div class="storage-meter-labels">
                            <span class="storage-lbl">Storage Used</span>
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
                        <span class="kpi-mini-lbl">Live Websites</span>
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                    </div>
                    <span class="kpi-mini-val"><?= $totalSites ?></span>
                    <span class="kpi-mini-hint">Always online</span>
                </div>

                <div class="kpi-mini-box">
                    <div class="kpi-box-header">
                        <span class="kpi-mini-lbl">Total Visits</span>
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                    </div>
                    <span class="kpi-mini-val"><?= number_format($totalViews) ?></span>
                    <span class="kpi-mini-hint">Privacy-first stats</span>
                </div>

                <div class="kpi-mini-box">
                    <div class="kpi-box-header">
                        <span class="kpi-mini-lbl">Customer Leads</span>
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                    </div>
                    <span class="kpi-mini-val"><?= number_format($totalForms) ?></span>
                    <span class="kpi-mini-hint">Built-in forms</span>
                </div>

                <div class="kpi-mini-box">
                    <div class="kpi-box-header">
                        <span class="kpi-mini-lbl">Reliability</span>
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                    </div>
                    <span class="kpi-mini-val highlight-green">99.99%</span>
                    <span class="kpi-mini-hint">No setup needed</span>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Connected AI Assistants & Remote MCP Overview Panel -->
    <div class="ai-connections-panel" style="margin-bottom: 28px; background: var(--bg-1); border: 1px solid var(--border); border-radius: var(--radius); padding: 20px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 12px;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <div style="width: 32px; height: 32px; border-radius: 8px; background: rgba(4, 120, 87, 0.1); border: 1px solid rgba(4, 120, 87, 0.2); display: flex; align-items: center; justify-content: center; color: var(--accent);">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2a2 2 0 0 1 2 2v2a2 2 0 0 1-2 2 2 2 0 0 1-2-2V4a2 2 0 0 1 2-2z"/><rect x="4" y="8" width="16" height="12" rx="2"/><circle cx="9" cy="13" r="1"/><circle cx="15" cy="13" r="1"/><path d="M9 17h6"/></svg>
                </div>
                <div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <h2 style="font-size: 16px; font-weight: 700; color: var(--fg); margin: 0;">Connected AI Assistants</h2>
                        <span style="font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 999px; background: <?= empty($apiTokens) ? 'var(--bg-2)' : 'rgba(4, 120, 87, 0.12)' ?>; color: <?= empty($apiTokens) ? 'var(--fg-muted)' : 'var(--accent)' ?>;">
                            <?= count($apiTokens) ?> <?= count($apiTokens) === 1 ? 'Connected' : 'Connected' ?>
                        </span>
                    </div>
                    <p style="font-size: 12.5px; color: var(--fg-muted); margin: 2px 0 0 0;">AI agents with remote MCP access to deploy, edit, and inspect your websites.</p>
                </div>
            </div>
            <div style="display: flex; gap: 8px;">
                <button type="button" class="btn-sm btn-ghost" onclick="openMcpToolsModal()" title="View the 11 tools accessible to connected AI agents">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 4px;"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
                    Exposed Tools (11)
                </button>
                <button type="button" class="btn-sm btn-secondary" onclick="openConnectionsModal()">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 4px;"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Connect ChatGPT / MCP
                </button>
            </div>
        </div>

        <?php if (!empty($apiTokens)): ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 12px;" id="dashboard-ai-tokens-grid">
                <?php foreach ($apiTokens as $tok): ?>
                    <?php $meta = $detectAiClient($tok['name'], $tok['token_prefix']); ?>
                    <div class="ai-assistant-card" id="ai-card-<?= (int)$tok['id'] ?>" style="background: var(--bg-2); border: 1px solid var(--border); border-radius: 10px; padding: 14px; display: flex; flex-direction: column; justify-content: space-between; gap: 12px;">
                        <div>
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 8px; margin-bottom: 6px;">
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <div style="width: 24px; height: 24px; border-radius: 50%; background: var(--bg-1); border: 1px solid var(--border); display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 800; color: var(--accent);">
                                        <?= strtoupper(substr($meta['provider'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <div style="font-weight: 700; font-size: 14px; color: var(--fg);"><?= htmlspecialchars($meta['name']) ?></div>
                                        <?php if ($meta['provider'] !== $meta['name']): ?>
                                            <div style="font-size: 11px; color: var(--fg-muted);"><?= htmlspecialchars($meta['provider']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <span style="display: inline-flex; align-items: center; gap: 5px; font-size: 11px; font-weight: 700; color: var(--accent); background: rgba(4, 120, 87, 0.1); padding: 2px 7px; border-radius: 4px;">
                                    <span style="width: 6px; height: 6px; border-radius: 50%; background: var(--accent);"></span>
                                    Active
                                </span>
                            </div>
                            <div style="font-size: 11.5px; color: var(--fg-muted); display: flex; flex-direction: column; gap: 3px;">
                                <div>Protocol: <strong style="color: var(--fg);"><?= htmlspecialchars($meta['protocol']) ?></strong></div>
                                <div>Permissions: <span style="color: var(--accent);">Full MCP Studio &amp; Deploy Access</span></div>
                                <div style="font-family: var(--font-mono); font-size: 11px; color: var(--fg-muted); margin-top: 4px;">
                                    Key: <?= htmlspecialchars($tok['token_prefix']) ?>
                                    <?php if (!empty($tok['last_used_at'])): ?>
                                        &middot; Active <?= $formatRelativeDate($tok['last_used_at']) ?>
                                    <?php else: ?>
                                        &middot; Created <?= $formatRelativeDate($tok['created_at']) ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div style="display: flex; justify-content: flex-end; align-items: center; border-top: 1px solid var(--border); padding-top: 10px;">
                            <button type="button" class="btn-sm btn-ghost" onclick="revokeApiTokenFromCard(<?= (int)$tok['id'] ?>)" style="color: var(--danger); font-size: 12px; padding: 3px 8px;">
                                Revoke Connection
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <!-- Zero Connections Onboarding Callout -->
            <div style="background: var(--bg-2); border: 1px dashed var(--border); border-radius: 10px; padding: 18px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px;">
                <div style="display: flex; align-items: center; gap: 14px;">
                    <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(4, 120, 87, 0.08); border: 1px solid rgba(4, 120, 87, 0.2); display: flex; align-items: center; justify-content: center; color: var(--accent); flex-shrink: 0;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2a2 2 0 0 1 2 2v2a2 2 0 0 1-2 2 2 2 0 0 1-2-2V4a2 2 0 0 1 2-2z"/><rect x="4" y="8" width="16" height="12" rx="2"/><circle cx="9" cy="13" r="1"/><circle cx="15" cy="13" r="1"/><path d="M9 17h6"/></svg>
                    </div>
                    <div>
                        <div style="font-weight: 700; font-size: 14px; color: var(--fg); margin-bottom: 2px;">No AI Assistants Connected Yet</div>
                        <div style="font-size: 12.5px; color: var(--fg-muted);">Link ChatGPT, Claude Desktop, or Cursor to publish, update, and inspect your sites straight from conversation.</div>
                    </div>
                </div>
                <button type="button" class="btn-sm btn-accent" onclick="openConnectionsModal()">
                    Connect ChatGPT via MCP &rarr;
                </button>
            </div>
        <?php endif; ?>
    </div>

    <!-- Dashboard Content Header & High-Velocity Actions -->
    <div class="dashboard-header">
        <div class="dashboard-title-group">
            <div class="dashboard-title-row">
                <h1>My Websites</h1>
                <span class="dashboard-count-badge"><?= $totalSites ?></span>
            </div>
            <p class="text-muted">Edit your pages, check visitor traffic, and view customer form messages in one place.</p>
        </div>
        <div class="dashboard-header-actions">
            <button type="button" class="btn-secondary" id="open-connections-btn" onclick="openConnectionsModal()" title="Connect ChatGPT & AI Assistants">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                <span>Connect ChatGPT</span>
            </button>
            <a href="<?= url('templates') ?>" class="btn-secondary" title="Browse Ready-Made Templates">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/></svg>
                <span>Templates</span>
            </a>
            <button type="button" class="btn-primary" id="open-fast-deploy-btn" title="Launch New Website">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                <span>New Website</span>
            </button>
        </div>
    </div>

    <?php if (empty($sites)): ?>
        <!-- Out-of-Distribution Zero-State Activation Launchpad -->
        <div class="activation-launchpad">
            <div class="launchpad-banner">
                <div class="launchpad-badge">
                    <span class="pulse-dot"></span>
                    <span>Quick Start</span>
                </div>
                <h2>Launch Your First Website</h2>
                <p>Choose how you want to start below and get a shareable live web link in seconds.</p>
            </div>

            <div class="launchpad-grid">
                <!-- Launchpad Card 1: Direct File / ZIP Dropzone -->
                <div class="launchpad-card launchpad-card-highlight">
                    <div class="launchpad-card-glow"></div>
                    <div class="launchpad-card-header">
                        <div class="launchpad-icon-box accent">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                        </div>
                        <span class="launchpad-card-tag">Fastest</span>
                    </div>
                    <h3>Upload Your Page or ZIP</h3>
                    <p>Drop a single <code>index.html</code> file or a zipped website folder up to 50MB. No setup needed—your link goes live right away.</p>
                    <div class="launchpad-card-footer">
                        <a href="<?= url() ?>" class="btn-primary w-full">
                            <span>Upload &amp; Publish</span>
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
                    <h3>Start From a Template</h3>
                    <p>Pick a ready-made design for your business, product launch, or portfolio with working contact forms already built in.</p>
                    <div class="blueprint-pills-list">
                        <a href="<?= url('templates') ?>" class="blueprint-mini-pill">
                            <span class="pill-title">Business Landing Page</span>
                            <span class="pill-meta">Modern &bull; Mobile Ready</span>
                        </a>
                        <a href="<?= url('templates') ?>" class="blueprint-mini-pill">
                            <span class="pill-title">Product Waitlist</span>
                            <span class="pill-meta">Lead Capture &bull; Clean</span>
                        </a>
                        <a href="<?= url('templates') ?>" class="blueprint-mini-pill">
                            <span class="pill-title">Personal Portfolio</span>
                            <span class="pill-meta">Showcase &bull; Fast</span>
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
                        <span class="launchpad-card-tag">For Builders</span>
                    </div>
                    <h3>Publish From Terminal or AI</h3>
                    <p>Ship sites directly from your command line or let your AI coding assistant publish for you automatically.</p>
                    <div class="cli-terminal-preview" onclick="copyCliCommand(this)" title="Click to copy command">
                        <div class="terminal-dots">
                            <span class="term-dot red"></span>
                            <span class="term-dot yellow"></span>
                            <span class="term-dot green"></span>
                            <span class="term-copied-badge">Copied!</span>
                        </div>
                        <code><span class="cli-prompt">$</span> npx pagedock-cli deploy ./dist</code>
                        <div class="cli-subtext">Instant upload &middot; zero config</div>
                    </div>
                    <div class="launchpad-card-footer">
                        <a href="<?= url('docs') ?>" class="btn-ghost w-full">
                            <span>Read Guides &amp; Docs</span>
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
                        <!-- Primary Row: Open Studio + View Live Site -->
                        <div class="card-actions-row primary-row">
                            <a href="<?= url('editor/' . $site['slug']) ?>" class="btn-action btn-action-edit" title="Open Studio & Code Editor">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                                <span>Studio</span>
                            </a>
                            <a href="<?= url('s/' . $site['slug']) ?>" target="_blank" class="btn-action btn-action-view" title="View Live Website">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                                <span>Live</span>
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
                                    <a href="<?= url('editor/' . $site['slug']) ?>" class="btn-table-action" title="Open Studio & Editor">Studio</a>
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
                    <h2 id="modal-deploy-title">Launch a New Website</h2>
                    <p class="modal-sub">Upload your files, paste HTML, or pick a template to publish right away.</p>
                </div>
            </div>
            <button type="button" class="modal-close-btn" onclick="closeFastDeployModal()" aria-label="Close modal">&times;</button>
        </div>

        <div class="modal-tabs-header">
            <button type="button" class="modal-tab-trigger active" onclick="switchModalTab('upload', this)">Upload ZIP / Files</button>
            <button type="button" class="modal-tab-trigger" onclick="switchModalTab('paste', this)">Paste HTML</button>
            <button type="button" class="modal-tab-trigger" onclick="switchModalTab('template', this)">Templates</button>
        </div>

        <div class="modal-tab-content active" id="modal-tab-upload">
            <form action="<?= url('api/upload') ?>" method="POST" enctype="multipart/form-data" id="fast-upload-form">
                <div class="modal-dropzone" id="modal-dropzone-target" onclick="document.getElementById('modal-file-input').click()">
                    <input type="file" name="file" id="modal-file-input" style="display:none;" accept=".zip,.html,.htm,.tar,.gz" onchange="handleModalFileSelected(this)">
                    <div class="dropzone-icon-pulse">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    </div>
                    <span class="dropzone-prompt" id="modal-dropzone-prompt">Drop your ZIP folder or <code>index.html</code> file here</span>
                    <span class="dropzone-hint">Up to 50MB &bull; Instant live link &bull; Free SSL included</span>
                </div>
                <div class="modal-dialog-footer">
                    <a href="<?= url() ?>" class="btn-ghost btn-sm">Open Homepage Dock</a>
                    <button type="submit" class="btn-primary" id="modal-upload-submit" disabled>
                        <span>Publish Website</span>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                    </button>
                </div>
            </form>
        </div>

        <div class="modal-tab-content" id="modal-tab-paste" style="display:none;">
            <form action="<?= url('api/upload-paste') ?>" method="POST" id="fast-paste-form">
                <div class="modal-paste-area">
                    <label for="paste-html-textarea" class="modal-field-label">Paste Your HTML Page</label>
                    <textarea name="html" id="paste-html-textarea" placeholder="<!DOCTYPE html>&#10;<html>&#10;<head><title>My Business Page</title></head>&#10;<body>&#10;  <h1>Welcome</h1>&#10;</body>&#10;</html>" class="modal-textarea" rows="8" required></textarea>
                </div>
                <div class="modal-dialog-footer">
                    <a href="<?= url() ?>" class="btn-ghost btn-sm">Open Homepage Dock</a>
                    <button type="submit" class="btn-primary">
                        <span>Publish Page Now</span>
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
                        <strong>Portfolio &amp; Showcase</strong>
                        <span>Clean layout &bull; Project gallery &bull; Contact form</span>
                    </div>
                    <span class="modal-tpl-arrow">&rarr;</span>
                </a>
                <a href="<?= url('templates') ?>" class="modal-template-item">
                    <div class="modal-tpl-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/></svg>
                    </div>
                    <div class="modal-tpl-info">
                        <strong>Product Launch &amp; Waitlist</strong>
                        <span>Clear headline &bull; Lead capture form &bull; Crisp cards</span>
                    </div>
                    <span class="modal-tpl-arrow">&rarr;</span>
                </a>
                <a href="<?= url('templates') ?>" class="modal-template-item">
                    <div class="modal-tpl-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                    </div>
                    <div class="modal-tpl-info">
                        <strong>Help Center &amp; Guides</strong>
                        <span>Easy navigation &bull; Clean reading layout &bull; Fast search</span>
                    </div>
                    <span class="modal-tpl-arrow">&rarr;</span>
                </a>
            </div>
            <div class="modal-dialog-footer">
                <a href="<?= url('templates') ?>" class="btn-secondary w-full">Browse All Templates</a>
            </div>
        </div>
    </div>
</div>

<!-- Connect ChatGPT / Remote MCP & API Tokens Modal -->
<div id="connections-modal" class="pagedock-modal" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="modal-connections-title">
    <div class="modal-backdrop" onclick="closeConnectionsModal()"></div>
    <div class="modal-dialog-card" style="max-width: 580px;">
        <div class="modal-dialog-header">
            <div class="modal-header-left">
                <div class="modal-icon-badge">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                </div>
                <div>
                    <h2 id="modal-connections-title">Connect ChatGPT &amp; AI Tools</h2>
                    <p class="modal-sub">Use ChatGPT directly with your PageDock websites via OAuth MCP or API keys.</p>
                </div>
            </div>
            <button type="button" class="modal-close-btn" onclick="closeConnectionsModal()" aria-label="Close modal">&times;</button>
        </div>

        <div class="modal-tabs-header">
            <button type="button" class="modal-tab-trigger active" onclick="switchConnTab('mcp', this)">ChatGPT App (OAuth MCP)</button>
            <button type="button" class="modal-tab-trigger" onclick="switchConnTab('keys', this)">API Keys &amp; Custom GPT</button>
        </div>

        <div class="modal-tab-content active" id="conn-tab-mcp">
            <div style="background:var(--bg-subtle, #f8fafc); border:1px solid var(--border, #e2e8f0); border-radius:12px; padding:14px; margin-bottom:14px;">
                <div style="font-size:12px; font-weight:700; color:var(--fg-muted, #64748b); text-transform:uppercase; letter-spacing:0.04em; margin-bottom:6px;">Remote MCP Server URL</div>
                <div style="display:flex; gap:8px; align-items:center;">
                    <input type="text" readonly value="<?= htmlspecialchars(url('mcp')) ?>" id="mcp-endpoint-url" class="input-text" style="font-family:'JetBrains Mono', monospace; font-size:13px; flex:1;">
                    <button type="button" class="btn-secondary btn-sm" onclick="copyTextField('mcp-endpoint-url', this)">Copy</button>
                </div>
            </div>
            <ol style="margin:0 0 14px 18px; padding:0; font-size:13.5px; line-height:1.65; color:var(--fg-muted, #475569);">
                <li>In <strong>ChatGPT</strong>, open <strong>Settings &rarr; Apps &amp; Connectors</strong> (enable <em>Developer mode</em> under Advanced settings if needed).</li>
                <li>Click <strong>Create</strong>, give it the name <code>PageDock</code>, and paste the MCP URL above.</li>
                <li>Leave Authentication on <strong>OAuth</strong> and click <strong>Create</strong> — ChatGPT will open PageDock to sign in and approve access automatically.</li>
            </ol>
            <p style="margin:0; font-size:12.5px; color:var(--fg-muted, #64748b);">
                Tools exposed: <code>list_sites</code>, <code>get_site</code>, <code>pull_site</code>, <code>deploy_html</code>, <code>update_site_file</code>, <code>list_versions</code>, <code>rollback_site</code>, <code>get_site_analytics</code>, <code>list_form_submissions</code>.
            </p>
        </div>

        <div class="modal-tab-content" id="conn-tab-keys" style="display:none;">
            <div style="background:var(--bg-subtle, #f8fafc); border:1px solid var(--border, #e2e8f0); border-radius:12px; padding:12px 14px; margin-bottom:14px;">
                <div style="font-size:12px; font-weight:700; color:var(--fg-muted, #64748b); text-transform:uppercase; margin-bottom:6px;">Custom GPT OpenAPI Schema URL</div>
                <div style="display:flex; gap:8px; align-items:center;">
                    <input type="text" readonly value="<?= htmlspecialchars(url('openapi.json')) ?>" id="openapi-endpoint-url" class="input-text" style="font-family:'JetBrains Mono', monospace; font-size:12.5px; flex:1;">
                    <button type="button" class="btn-secondary btn-sm" onclick="copyTextField('openapi-endpoint-url', this)">Copy</button>
                </div>
            </div>

            <form onsubmit="createApiToken(event)" style="display:flex; gap:8px; margin-bottom:12px;">
                <input type="text" id="new-token-name" placeholder="Token label (e.g. Custom GPT / Responses API)" class="input-text" style="flex:1;" maxlength="80">
                <button type="submit" class="btn-primary btn-sm" id="create-token-btn">Generate Bearer Key</button>
            </form>

            <div id="newly-created-token-box" style="display:none; background:#ecfdf5; border:1px solid #a7f3d0; border-radius:10px; padding:12px; margin-bottom:12px;">
                <div style="font-size:12px; font-weight:700; color:#065f46; margin-bottom:6px;">Copy your new Bearer token now (shown once):</div>
                <div style="display:flex; gap:8px; align-items:center;">
                    <input type="text" readonly id="newly-created-token-val" class="input-text" style="font-family:'JetBrains Mono', monospace; font-size:12.5px; flex:1; background:#fff;">
                    <button type="button" class="btn-secondary btn-sm" onclick="copyTextField('newly-created-token-val', this)">Copy</button>
                </div>
            </div>

            <div style="font-size:12px; font-weight:700; color:var(--fg-muted, #64748b); text-transform:uppercase; margin-bottom:6px;">Active Connections &amp; Tokens</div>
            <div id="api-tokens-list" style="max-height:200px; overflow-y:auto; display:flex; flex-direction:column; gap:8px;">
                <?php if (empty($apiTokens)): ?>
                    <div id="no-tokens-msg" style="font-size:13px; color:var(--fg-muted, #64748b); padding:10px 0;">No active OAuth sessions or API tokens yet.</div>
                <?php else: ?>
                    <?php foreach ($apiTokens as $tok): ?>
                        <div class="api-token-row" id="token-row-<?= (int)$tok['id'] ?>" style="display:flex; justify-content:space-between; align-items:center; gap:10px; padding:9px 12px; border:1px solid var(--border, #e2e8f0); border-radius:10px; font-size:13px;">
                            <div>
                                <div style="font-weight:600; color:var(--fg, #0f172a);"><?= htmlspecialchars($tok['name']) ?></div>
                                <div style="font-size:11.5px; color:var(--fg-muted, #64748b); font-family:'JetBrains Mono', monospace;">
                                    <?= htmlspecialchars($tok['token_prefix']) ?>
                                    <?php if (!empty($tok['last_used_at'])): ?>
                                        &middot; Last used <?= htmlspecialchars($tok['last_used_at']) ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <button type="button" class="btn-table-action danger" onclick="revokeApiToken(<?= (int)$tok['id'] ?>)">Revoke</button>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal: 11 Exposed MCP Tools -->
<div id="mcp-tools-modal" class="pagedock-modal" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="modal-mcp-tools-title">
    <div class="modal-backdrop" onclick="closeMcpToolsModal()"></div>
    <div class="modal-dialog-card" style="max-width: 620px;">
        <div class="modal-dialog-header">
            <div class="modal-header-left">
                <div class="modal-icon-badge">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
                </div>
                <div>
                    <h2 id="modal-mcp-tools-title">Exposed MCP Tools Catalog</h2>
                    <p class="modal-sub">The 11 tools currently available to authorized AI clients connected to your account.</p>
                </div>
            </div>
            <button type="button" class="modal-close-btn" onclick="closeMcpToolsModal()" aria-label="Close modal">&times;</button>
        </div>
        <div class="modal-dialog-body" style="padding: 18px 24px; max-height: 420px; overflow-y: auto;">
            <div style="display: flex; flex-direction: column; gap: 10px; font-size: 13px;">
                <div style="padding: 10px 12px; background: var(--bg-2); border-radius: 8px; border: 1px solid var(--border);">
                    <div style="font-weight: 700; color: var(--accent); font-family: var(--font-mono);">deploy_html</div>
                    <div style="color: var(--fg-muted); font-size: 12px;">Publish a new HTML website or overwrite an existing site with live preview URL.</div>
                </div>
                <div style="padding: 10px 12px; background: var(--bg-2); border-radius: 8px; border: 1px solid var(--border);">
                    <div style="font-weight: 700; color: var(--accent); font-family: var(--font-mono);">update_site_file</div>
                    <div style="color: var(--fg-muted); font-size: 12px;">Surgically update or create an individual file (HTML, CSS, JS) with automatic version snapshot.</div>
                </div>
                <div style="padding: 10px 12px; background: var(--bg-2); border-radius: 8px; border: 1px solid var(--border);">
                    <div style="font-weight: 700; color: var(--accent); font-family: var(--font-mono);">pull_site</div>
                    <div style="color: var(--fg-muted); font-size: 12px;">Read raw HTML or code source of a hosted site for inspection or debugging.</div>
                </div>
                <div style="padding: 10px 12px; background: var(--bg-2); border-radius: 8px; border: 1px solid var(--border);">
                    <div style="font-weight: 700; color: var(--accent); font-family: var(--font-mono);">list_sites</div>
                    <div style="color: var(--fg-muted); font-size: 12px;">List all hosted sites on your account with slugs, titles, and live URLs.</div>
                </div>
                <div style="padding: 10px 12px; background: var(--bg-2); border-radius: 8px; border: 1px solid var(--border);">
                    <div style="font-weight: 700; color: var(--accent); font-family: var(--font-mono);">get_site</div>
                    <div style="color: var(--fg-muted); font-size: 12px;">Get full site metadata, file list, storage size, and live endpoints.</div>
                </div>
                <div style="padding: 10px 12px; background: var(--bg-2); border-radius: 8px; border: 1px solid var(--border);">
                    <div style="font-weight: 700; color: var(--accent); font-family: var(--font-mono);">list_site_files</div>
                    <div style="color: var(--fg-muted); font-size: 12px;">List all relative file paths inside a multi-file deployment.</div>
                </div>
                <div style="padding: 10px 12px; background: var(--bg-2); border-radius: 8px; border: 1px solid var(--border);">
                    <div style="font-weight: 700; color: var(--accent); font-family: var(--font-mono);">list_versions</div>
                    <div style="color: var(--fg-muted); font-size: 12px;">Inspect historical immutable version snapshots with timestamps and summaries.</div>
                </div>
                <div style="padding: 10px 12px; background: var(--bg-2); border-radius: 8px; border: 1px solid var(--border);">
                    <div style="font-weight: 700; color: var(--accent); font-family: var(--font-mono);">rollback_site</div>
                    <div style="color: var(--fg-muted); font-size: 12px;">Restore site to an earlier version snapshot with 1-click rollback.</div>
                </div>
                <div style="padding: 10px 12px; background: var(--bg-2); border-radius: 8px; border: 1px solid var(--border);">
                    <div style="font-weight: 700; color: var(--accent); font-family: var(--font-mono);">get_site_analytics</div>
                    <div style="color: var(--fg-muted); font-size: 12px;">Query visitor view counts, unique visitors, referrers, and device breakdown.</div>
                </div>
                <div style="padding: 10px 12px; background: var(--bg-2); border-radius: 8px; border: 1px solid var(--border);">
                    <div style="font-weight: 700; color: var(--accent); font-family: var(--font-mono);">list_form_submissions</div>
                    <div style="color: var(--fg-muted); font-size: 12px;">Retrieve customer inquiries and contact form leads with spam filtering.</div>
                </div>
                <div style="padding: 10px 12px; background: var(--bg-2); border-radius: 8px; border: 1px solid var(--border);">
                    <div style="font-weight: 700; color: var(--accent); font-family: var(--font-mono);">delete_site</div>
                    <div style="color: var(--fg-muted); font-size: 12px;">Permanently delete a hosted site and purge on-disk and R2 cloud files.</div>
                </div>
            </div>
        </div>
        <div class="modal-dialog-footer" style="padding: 12px 24px; border-top: 1px solid var(--border); display: flex; justify-content: flex-end;">
            <button type="button" class="btn-secondary btn-sm" onclick="closeMcpToolsModal()">Close</button>
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

// 7. Connections Modal (ChatGPT Remote MCP & Bearer API Tokens)
const connectionsModal = document.getElementById('connections-modal');
function openConnectionsModal() {
    if (connectionsModal) {
        connectionsModal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
}
function closeConnectionsModal() {
    if (connectionsModal) {
        connectionsModal.style.display = 'none';
        document.body.style.overflow = '';
    }
}
function switchConnTab(tabId, btnEl) {
    if (!connectionsModal) return;
    connectionsModal.querySelectorAll('.modal-tab-trigger').forEach(b => b.classList.remove('active'));
    connectionsModal.querySelectorAll('.modal-tab-content').forEach(c => c.style.display = 'none');
    if (btnEl) btnEl.classList.add('active');
    const target = document.getElementById(`conn-tab-${tabId}`);
    if (target) target.style.display = 'block';
}
function copyTextField(inputId, btn) {
    const el = document.getElementById(inputId);
    if (!el || !navigator.clipboard) return;
    navigator.clipboard.writeText(el.value).then(() => {
        const orig = btn.textContent;
        btn.textContent = 'Copied!';
        setTimeout(() => { btn.textContent = orig; }, 1600);
    });
}
async function createApiToken(e) {
    e.preventDefault();
    const nameInput = document.getElementById('new-token-name');
    const btn = document.getElementById('create-token-btn');
    const name = (nameInput?.value || '').trim() || 'ChatGPT / API Key';
    btn.disabled = true;
    try {
        const res = await fetch('<?= url('api/tokens') ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name })
        });
        const data = await res.json();
        if (data.success && data.token) {
            document.getElementById('newly-created-token-val').value = data.token.token;
            document.getElementById('newly-created-token-box').style.display = 'block';
            if (nameInput) nameInput.value = '';
            const noMsg = document.getElementById('no-tokens-msg');
            if (noMsg) noMsg.remove();
            const list = document.getElementById('api-tokens-list');
            const row = document.createElement('div');
            row.className = 'api-token-row';
            row.id = `token-row-${data.token.id}`;
            row.style.cssText = 'display:flex; justify-content:space-between; align-items:center; gap:10px; padding:9px 12px; border:1px solid var(--border, #e2e8f0); border-radius:10px; font-size:13px;';
            const info = document.createElement('div');
            const title = document.createElement('div');
            title.style.cssText = 'font-weight:600; color:var(--fg, #0f172a);';
            title.textContent = data.token.name;
            const meta = document.createElement('div');
            meta.style.cssText = "font-size:11.5px; color:var(--fg-muted, #64748b); font-family:'JetBrains Mono', monospace;";
            meta.textContent = data.token.token_prefix;
            info.appendChild(title);
            info.appendChild(meta);
            const delBtn = document.createElement('button');
            delBtn.type = 'button';
            delBtn.className = 'btn-table-action danger';
            delBtn.textContent = 'Revoke';
            delBtn.onclick = () => revokeApiToken(data.token.id);
            row.appendChild(info);
            row.appendChild(delBtn);
            list.prepend(row);
        } else {
            alert(data.error || 'Could not generate API key.');
        }
    } catch (err) {
        alert('Error creating API key.');
    } finally {
        btn.disabled = false;
    }
}
async function revokeApiToken(tokenId) {
    if (!confirm('Revoke this connection token? Connected apps using it will stop working.')) return;
    try {
        const res = await fetch(`<?= url('api/tokens/') ?>${tokenId}/revoke`, { method: 'POST' });
        const data = await res.json();
        if (data.success) {
            const row = document.getElementById(`token-row-${tokenId}`);
            if (row) row.remove();
            const card = document.getElementById(`ai-card-${tokenId}`);
            if (card) card.remove();
            const remaining = document.querySelectorAll('.ai-assistant-card');
            if (remaining.length === 0 && document.getElementById('dashboard-ai-tokens-grid')) {
                location.reload();
            }
        } else {
            alert('Failed to revoke token.');
        }
    } catch (e) {
        alert('Error revoking token.');
    }
}

function openMcpToolsModal() {
    const m = document.getElementById('mcp-tools-modal');
    if (m) m.style.display = 'flex';
}

function closeMcpToolsModal() {
    const m = document.getElementById('mcp-tools-modal');
    if (m) m.style.display = 'none';
}

function revokeApiTokenFromCard(tokenId) {
    revokeApiToken(tokenId);
}
</script>
