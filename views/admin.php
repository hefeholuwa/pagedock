<?php
/**
 * PageDock - Platform Administrator Console
 * Sovereign Edge Architecture & Global Fleet Telemetry
 */

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

<div class="dashboard-container admin-console-container">
    <!-- Telemetry & Edge Network Status Bar -->
    <div class="workspace-scope-bar">
        <div class="workspace-scope-left">
            <span class="workspace-pill" style="border-color: rgba(4, 120, 87, 0.4); background: rgba(4, 120, 87, 0.08);">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
                <span style="color: var(--accent); font-weight: 700;">Platform Administrator</span>
                <span class="scope-sep">/</span>
                <span class="scope-target"><?= htmlspecialchars(($user['name'] ?? '') ?: 'Root') ?></span>
                <span class="workspace-chip-active" style="background: var(--accent);">Superuser</span>
            </span>
        </div>
        <div class="workspace-scope-right">
            <span class="edge-status-pill" title="Hosting Infrastructure">
                <span class="edge-pulse-dot"></span>
                <span>Fleet Status: <strong>Operational</strong></span>
            </span>
            <span class="latency-pill" title="Cloud Storage Engine">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>
                <span>Storage: <strong><?= strtoupper(htmlspecialchars($stats['system']['storage_driver'])) ?></strong></span>
            </span>
            <span class="ssl-status-pill" title="Server Software">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="8" rx="2" ry="2"/><rect x="2" y="14" width="20" height="8" rx="2" ry="2"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/></svg>
                <span>PHP <?= htmlspecialchars($stats['system']['php_version']) ?></span>
            </span>
        </div>
    </div>

    <!-- Admin Console Header -->
    <div class="admin-header-row" style="margin-bottom: 24px; display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 16px;">
        <div>
            <h1 style="font-size: 28px; font-weight: 800; letter-spacing: -0.02em; margin-bottom: 4px; color: var(--fg);">Platform Oversight & Fleet Telemetry</h1>
            <p style="color: var(--fg-muted); font-size: 14px;">Real-time governance, visitor analytics, storage accounting, and security moderation across all hosted sites.</p>
        </div>
        <div style="display: flex; gap: 8px;">
            <button type="button" class="btn-sm btn-ghost" onclick="syncDbToR2(this)" title="Backup SQLite Database to Cloudflare R2">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 4px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                Sync DB to R2
            </button>
            <button type="button" class="btn-sm btn-ghost" onclick="cleanupExpiredSites(this)" title="Purge expired guest deployments">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 4px;"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                Clean Expired
            </button>
            <a href="<?= url('dashboard') ?>" class="btn-sm btn-accent">
                User Dashboard &rarr;
            </a>
        </div>
    </div>

    <!-- Bento KPI Summary Grid -->
    <div class="dashboard-bento-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 28px;">
        <!-- Sites Hosted Card -->
        <div class="bento-card" style="background: var(--bg-1); border: 1px solid var(--border); border-radius: var(--radius); padding: 20px;">
            <div style="font-size: 11px; text-transform: uppercase; font-weight: 700; color: var(--fg-muted); letter-spacing: 0.05em; margin-bottom: 8px;">Total Sites Hosted</div>
            <div style="font-size: 32px; font-weight: 800; color: var(--fg); line-height: 1; margin-bottom: 10px;"><?= number_format($stats['sites']['total']) ?></div>
            <div style="font-size: 12px; color: var(--fg-muted); display: flex; gap: 12px; flex-wrap: wrap;">
                <span>Active: <strong style="color: var(--accent);"><?= number_format($stats['sites']['active']) ?></strong></span>
                <span>Suspended: <strong style="color: var(--danger);"><?= number_format($stats['sites']['suspended']) ?></strong></span>
            </div>
            <div style="font-size: 11px; color: var(--fg-muted); margin-top: 6px;">
                Claimed: <?= number_format($stats['sites']['claimed']) ?> | Guest: <?= number_format($stats['sites']['guest']) ?>
            </div>
        </div>

        <!-- Global Storage Card -->
        <div class="bento-card" style="background: var(--bg-1); border: 1px solid var(--border); border-radius: var(--radius); padding: 20px;">
            <div style="font-size: 11px; text-transform: uppercase; font-weight: 700; color: var(--fg-muted); letter-spacing: 0.05em; margin-bottom: 8px;">Storage Volume</div>
            <div style="font-size: 32px; font-weight: 800; color: var(--fg); line-height: 1; margin-bottom: 10px;"><?= htmlspecialchars($stats['sites']['total_size_formatted']) ?></div>
            <div style="font-size: 12px; color: var(--fg-muted);">
                Engine: <strong style="color: var(--accent);"><?= htmlspecialchars(strtoupper($stats['system']['storage_driver'])) ?></strong>
                <?php if ($stats['system']['r2_configured']): ?>
                    (R2 Connected)
                <?php else: ?>
                    (Local FS)
                <?php endif; ?>
            </div>
            <div style="font-size: 11px; color: var(--fg-muted); margin-top: 6px;">
                SQLite Database: <?= htmlspecialchars($stats['system']['db_size_formatted']) ?>
            </div>
        </div>

        <!-- Global Visitor Traffic -->
        <div class="bento-card" style="background: var(--bg-1); border: 1px solid var(--border); border-radius: var(--radius); padding: 20px;">
            <div style="font-size: 11px; text-transform: uppercase; font-weight: 700; color: var(--fg-muted); letter-spacing: 0.05em; margin-bottom: 8px;">Visitor Traffic</div>
            <div style="font-size: 32px; font-weight: 800; color: var(--fg); line-height: 1; margin-bottom: 10px;"><?= number_format($stats['traffic']['all_time_views']) ?></div>
            <div style="font-size: 12px; color: var(--fg-muted); display: flex; gap: 12px;">
                <span>24h: <strong style="color: var(--fg);"><?= number_format($stats['traffic']['views_24h']) ?></strong></span>
                <span>7d: <strong style="color: var(--fg);"><?= number_format($stats['traffic']['views_7d']) ?></strong></span>
            </div>
            <div style="font-size: 11px; color: var(--fg-muted); margin-top: 6px;">
                Unique Visitors: <?= number_format($stats['traffic']['unique_visitors']) ?>
            </div>
        </div>

        <!-- Contact Form Inquiries -->
        <div class="bento-card" style="background: var(--bg-1); border: 1px solid var(--border); border-radius: var(--radius); padding: 20px;">
            <div style="font-size: 11px; text-transform: uppercase; font-weight: 700; color: var(--fg-muted); letter-spacing: 0.05em; margin-bottom: 8px;">Form Submissions</div>
            <div style="font-size: 32px; font-weight: 800; color: var(--fg); line-height: 1; margin-bottom: 10px;"><?= number_format($stats['forms']['total']) ?></div>
            <div style="font-size: 12px; color: var(--fg-muted); display: flex; gap: 12px;">
                <span>Verified: <strong style="color: var(--accent);"><?= number_format($stats['forms']['clean']) ?></strong></span>
                <span>Spam Filtered: <strong style="color: var(--danger);"><?= number_format($stats['forms']['spam']) ?></strong></span>
            </div>
            <div style="font-size: 11px; color: var(--fg-muted); margin-top: 6px;">
                Snapshots Logged: <?= number_format($stats['versions']['total']) ?>
            </div>
        </div>

        <!-- Platform Users -->
        <div class="bento-card" style="background: var(--bg-1); border: 1px solid var(--border); border-radius: var(--radius); padding: 20px;">
            <div style="font-size: 11px; text-transform: uppercase; font-weight: 700; color: var(--fg-muted); letter-spacing: 0.05em; margin-bottom: 8px;">Registered Users</div>
            <div style="font-size: 32px; font-weight: 800; color: var(--fg); line-height: 1; margin-bottom: 10px;"><?= number_format($stats['users']['total']) ?></div>
            <div style="font-size: 12px; color: var(--fg-muted); display: flex; gap: 12px;">
                <span>Admins: <strong style="color: var(--accent);"><?= number_format($stats['users']['admins']) ?></strong></span>
                <span>Members: <strong style="color: var(--fg);"><?= number_format($stats['users']['standard']) ?></strong></span>
            </div>
            <div style="font-size: 11px; color: var(--fg-muted); margin-top: 6px;">
                RAM Peak: <?= htmlspecialchars($stats['system']['peak_memory_formatted']) ?>
            </div>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="admin-tabs" style="display: flex; gap: 8px; border-bottom: 1px solid var(--border); margin-bottom: 24px; padding-bottom: 2px;">
        <button type="button" class="admin-tab-btn active" onclick="switchAdminTab('sites')" id="tab-btn-sites" style="background: none; border: none; font-size: 14px; font-weight: 700; padding: 8px 16px; cursor: pointer; color: var(--accent); border-bottom: 2px solid var(--accent);">
            Sites Oversight (<?= count($sites) ?>)
        </button>
        <button type="button" class="admin-tab-btn" onclick="switchAdminTab('users')" id="tab-btn-users" style="background: none; border: none; font-size: 14px; font-weight: 600; padding: 8px 16px; cursor: pointer; color: var(--fg-muted);">
            Users Management (<?= count($users) ?>)
        </button>
        <button type="button" class="admin-tab-btn" onclick="switchAdminTab('activity')" id="tab-btn-activity" style="background: none; border: none; font-size: 14px; font-weight: 600; padding: 8px 16px; cursor: pointer; color: var(--fg-muted);">
            Activity Audit Feed (<?= count($activity) ?>)
        </button>
        <button type="button" class="admin-tab-btn" onclick="switchAdminTab('system')" id="tab-btn-system" style="background: none; border: none; font-size: 14px; font-weight: 600; padding: 8px 16px; cursor: pointer; color: var(--fg-muted);">
            System Diagnostics
        </button>
    </div>

    <!-- TAB 1: SITES OVERSIGHT -->
    <div class="admin-tab-pane" id="pane-sites">
        <!-- Search and Filter Bar -->
        <div style="display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 16px; flex-wrap: wrap;">
            <div style="flex: 1; min-width: 260px; position: relative;">
                <input type="text" id="site-search-input" placeholder="Search by slug, title, user email, or claim token..." oninput="filterSitesTable()" style="width: 100%; padding: 10px 14px; border: 1px solid var(--border); border-radius: var(--radius); background: var(--bg-1); color: var(--fg); font-size: 14px;">
            </div>
            <div style="display: flex; gap: 6px;">
                <button type="button" class="btn-sm btn-ghost filter-chip active" data-filter="all" onclick="setSiteFilter('all', this)">All</button>
                <button type="button" class="btn-sm btn-ghost filter-chip" data-filter="active" onclick="setSiteFilter('active', this)">Active</button>
                <button type="button" class="btn-sm btn-ghost filter-chip" data-filter="suspended" onclick="setSiteFilter('suspended', this)">Suspended</button>
                <button type="button" class="btn-sm btn-ghost filter-chip" data-filter="claimed" onclick="setSiteFilter('claimed', this)">User Owned</button>
                <button type="button" class="btn-sm btn-ghost filter-chip" data-filter="guest" onclick="setSiteFilter('guest', this)">Guest</button>
            </div>
        </div>

        <!-- Sites Table -->
        <div style="overflow-x: auto; background: var(--bg-1); border: 1px solid var(--border); border-radius: var(--radius);">
            <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 13px;" id="admin-sites-table">
                <thead>
                    <tr style="background: var(--bg-2); border-bottom: 1px solid var(--border); color: var(--fg-muted); text-transform: uppercase; font-size: 11px; letter-spacing: 0.04em;">
                        <th style="padding: 12px 16px;">Site / Slug</th>
                        <th style="padding: 12px 16px;">Owner</th>
                        <th style="padding: 12px 16px;">Files & Size</th>
                        <th style="padding: 12px 16px;">Views</th>
                        <th style="padding: 12px 16px;">Status</th>
                        <th style="padding: 12px 16px;">Created</th>
                        <th style="padding: 12px 16px; text-align: right;">Administrative Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($sites)): ?>
                        <tr>
                            <td colspan="7" style="padding: 32px; text-align: center; color: var(--fg-muted);">No sites found on the platform.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($sites as $s): ?>
                            <tr class="admin-site-row" data-slug="<?= htmlspecialchars(strtolower($s['slug'])) ?>" data-title="<?= htmlspecialchars(strtolower($s['title'])) ?>" data-owner="<?= htmlspecialchars(strtolower($s['owner_email'] ?? 'guest')) ?>" data-status="<?= $s['is_suspended'] ? 'suspended' : 'active' ?>" data-ownership="<?= $s['owner_email'] ? 'claimed' : 'guest' ?>" style="border-bottom: 1px solid var(--border);">
                                <td style="padding: 14px 16px;">
                                    <div style="font-weight: 700; color: var(--fg);">
                                        <a href="<?= htmlspecialchars($s['liveUrl']) ?>" target="_blank" style="color: var(--fg); text-decoration: none;">
                                            <?= htmlspecialchars($s['slug']) ?>
                                        </a>
                                    </div>
                                    <div style="font-size: 12px; color: var(--fg-muted);"><?= htmlspecialchars($s['title']) ?></div>
                                </td>
                                <td style="padding: 14px 16px;">
                                    <?php if (!empty($s['owner_email'])): ?>
                                        <div style="font-weight: 600; color: var(--fg);"><?= htmlspecialchars($s['owner_email']) ?></div>
                                        <div style="font-size: 11px; color: var(--accent);">User ID #<?= htmlspecialchars($s['user_id']) ?></div>
                                    <?php else: ?>
                                        <span style="display: inline-block; padding: 2px 8px; border-radius: 4px; background: var(--bg-2); color: var(--fg-muted); font-size: 11px; font-family: var(--font-mono);">
                                            Guest / Unclaimed
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 14px 16px;">
                                    <div><?= htmlspecialchars($s['total_size_formatted']) ?></div>
                                    <div style="font-size: 11px; color: var(--fg-muted);"><?= (int)$s['file_count'] ?> files (v<?= (int)$s['current_version'] ?>)</div>
                                </td>
                                <td style="padding: 14px 16px;">
                                    <div style="font-weight: 700;"><?= number_format($s['views_count']) ?></div>
                                    <div style="font-size: 11px; color: var(--fg-muted);"><?= (int)$s['forms_count'] ?> leads</div>
                                </td>
                                <td style="padding: 14px 16px;">
                                    <?php if ($s['is_suspended']): ?>
                                        <span class="status-badge-suspended" style="display: inline-block; padding: 3px 8px; border-radius: 4px; background: rgba(198, 40, 40, 0.12); color: var(--danger); font-size: 11px; font-weight: 700;">
                                            Suspended
                                        </span>
                                    <?php else: ?>
                                        <span class="status-badge-active" style="display: inline-block; padding: 3px 8px; border-radius: 4px; background: rgba(4, 120, 87, 0.12); color: var(--accent); font-size: 11px; font-weight: 700;">
                                            Active
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 14px 16px; font-size: 12px; color: var(--fg-muted);" title="<?= htmlspecialchars($s['created_at']) ?>">
                                    <?= $formatRelativeDate($s['created_at']) ?>
                                </td>
                                <td style="padding: 14px 16px; text-align: right;">
                                    <div style="display: inline-flex; gap: 6px; align-items: center;">
                                        <a href="<?= htmlspecialchars($s['liveUrl']) ?>" target="_blank" class="btn-sm btn-ghost" title="Open live site">
                                            View
                                        </a>
                                        <a href="<?= htmlspecialchars($s['editorUrl']) ?>" target="_blank" class="btn-sm btn-ghost" title="Inspect or edit in Studio">
                                            Studio
                                        </a>
                                        <button type="button" class="btn-sm <?= $s['is_suspended'] ? 'btn-accent' : 'btn-ghost' ?>" onclick="toggleSiteSuspension('<?= htmlspecialchars($s['slug']) ?>', this)" style="<?= $s['is_suspended'] ? '' : 'color: var(--danger);' ?>">
                                            <?= $s['is_suspended'] ? 'Unsuspend' : 'Suspend' ?>
                                        </button>
                                        <button type="button" class="btn-sm btn-ghost" onclick="adminDeleteSite('<?= htmlspecialchars($s['slug']) ?>', this)" style="color: var(--danger);">
                                            Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- TAB 2: USERS MANAGEMENT -->
    <div class="admin-tab-pane" id="pane-users" style="display: none;">
        <div style="display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 16px; flex-wrap: wrap;">
            <div style="flex: 1; min-width: 260px;">
                <input type="text" id="user-search-input" placeholder="Search by user email or name..." oninput="filterUsersTable()" style="width: 100%; padding: 10px 14px; border: 1px solid var(--border); border-radius: var(--radius); background: var(--bg-1); color: var(--fg); font-size: 14px;">
            </div>
            <div style="font-size: 13px; color: var(--fg-muted);">
                Showing <?= count($users) ?> registered accounts
            </div>
        </div>

        <div style="overflow-x: auto; background: var(--bg-1); border: 1px solid var(--border); border-radius: var(--radius);">
            <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 13px;" id="admin-users-table">
                <thead>
                    <tr style="background: var(--bg-2); border-bottom: 1px solid var(--border); color: var(--fg-muted); text-transform: uppercase; font-size: 11px; letter-spacing: 0.04em;">
                        <th style="padding: 12px 16px;">User</th>
                        <th style="padding: 12px 16px;">Role</th>
                        <th style="padding: 12px 16px;">Plan</th>
                        <th style="padding: 12px 16px;">Sites Hosted</th>
                        <th style="padding: 12px 16px;">Total Views</th>
                        <th style="padding: 12px 16px;">Total Storage</th>
                        <th style="padding: 12px 16px;">Joined</th>
                        <th style="padding: 12px 16px; text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr class="admin-user-row" data-email="<?= htmlspecialchars(strtolower($u['email'])) ?>" data-name="<?= htmlspecialchars(strtolower($u['name'])) ?>" style="border-bottom: 1px solid var(--border);">
                            <td style="padding: 14px 16px;">
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <div style="width: 28px; height: 28px; border-radius: 50%; background: var(--bg-3); color: var(--fg); font-weight: 800; display: inline-flex; align-items: center; justify-content: center; font-size: 12px;">
                                        <?= strtoupper(substr($u['name'] ?: $u['email'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <div style="font-weight: 700; color: var(--fg);"><?= htmlspecialchars($u['name']) ?></div>
                                        <div style="font-size: 12px; color: var(--fg-muted);"><?= htmlspecialchars($u['email']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td style="padding: 14px 16px;">
                                <?php if ($u['role'] === 'admin'): ?>
                                    <span style="display: inline-block; padding: 2px 8px; border-radius: 4px; background: rgba(4, 120, 87, 0.12); color: var(--accent); font-size: 11px; font-weight: 800; text-transform: uppercase;">
                                        Admin
                                    </span>
                                <?php else: ?>
                                    <span style="display: inline-block; padding: 2px 8px; border-radius: 4px; background: var(--bg-2); color: var(--fg-muted); font-size: 11px; font-weight: 600; text-transform: uppercase;">
                                        User
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 14px 16px;">
                                <span style="text-transform: uppercase; font-size: 11px; font-weight: 700; color: var(--fg-muted);">
                                    <?= htmlspecialchars($u['plan']) ?>
                                </span>
                            </td>
                            <td style="padding: 14px 16px; font-weight: 700;">
                                <?= number_format($u['sites_count']) ?>
                            </td>
                            <td style="padding: 14px 16px;">
                                <?= number_format($u['total_views']) ?>
                            </td>
                            <td style="padding: 14px 16px; color: var(--fg-muted);">
                                <?= htmlspecialchars($u['total_size_formatted']) ?>
                            </td>
                            <td style="padding: 14px 16px; font-size: 12px; color: var(--fg-muted);" title="<?= htmlspecialchars($u['created_at']) ?>">
                                <?= $formatRelativeDate($u['created_at']) ?>
                            </td>
                            <td style="padding: 14px 16px; text-align: right;">
                                <?php if ((int)$u['id'] !== (int)$user['id']): ?>
                                    <button type="button" class="btn-sm btn-ghost" onclick="toggleUserRole(<?= (int)$u['id'] ?>, '<?= htmlspecialchars($u['email']) ?>', this)">
                                        <?= $u['role'] === 'admin' ? 'Demote to User' : 'Promote to Admin' ?>
                                    </button>
                                <?php else: ?>
                                    <span style="font-size: 12px; color: var(--fg-muted); font-style: italic;">Current session</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- TAB 3: ACTIVITY AUDIT FEED -->
    <div class="admin-tab-pane" id="pane-activity" style="display: none;">
        <div style="background: var(--bg-1); border: 1px solid var(--border); border-radius: var(--radius); padding: 20px;">
            <h3 style="font-size: 16px; font-weight: 700; margin-bottom: 16px;">Platform Audit Stream</h3>
            <div class="activity-timeline" style="display: flex; flex-direction: column; gap: 16px;">
                <?php if (empty($activity)): ?>
                    <p style="color: var(--fg-muted); font-size: 14px;">No recent platform activity logged.</p>
                <?php else: ?>
                    <?php foreach ($activity as $item): ?>
                        <div style="display: flex; gap: 16px; align-items: flex-start; padding-bottom: 16px; border-bottom: 1px solid var(--border);">
                            <div style="width: 32px; height: 32px; border-radius: 8px; background: var(--bg-2); border: 1px solid var(--border); display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 13px; font-weight: 700; color: var(--accent);">
                                <?php if ($item['type'] === 'deployment'): ?>
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                <?php elseif ($item['type'] === 'form_lead'): ?>
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                                <?php else: ?>
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
                                <?php endif; ?>
                            </div>
                            <div style="flex: 1;">
                                <div style="display: flex; justify-content: space-between; align-items: baseline; gap: 8px;">
                                    <div style="font-weight: 700; color: var(--fg); font-size: 14px;">
                                        <?= htmlspecialchars($item['title']) ?>
                                    </div>
                                    <div style="font-size: 11px; color: var(--fg-muted);" title="<?= htmlspecialchars($item['timestamp']) ?>">
                                        <?= $formatRelativeDate($item['timestamp']) ?>
                                    </div>
                                </div>
                                <div style="font-size: 13px; color: var(--fg-muted); margin-top: 2px;">
                                    <?= htmlspecialchars($item['detail']) ?>
                                </div>
                                <div style="font-size: 11px; color: var(--accent); margin-top: 4px;">
                                    <?= htmlspecialchars($item['meta']) ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- TAB 4: SYSTEM DIAGNOSTICS -->
    <div class="admin-tab-pane" id="pane-system" style="display: none;">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px;">
            <!-- Infrastructure Panel -->
            <div style="background: var(--bg-1); border: 1px solid var(--border); border-radius: var(--radius); padding: 20px;">
                <h3 style="font-size: 16px; font-weight: 700; margin-bottom: 16px; border-bottom: 1px solid var(--border); padding-bottom: 8px;">Storage & Engine Topology</h3>
                <div style="display: flex; flex-direction: column; gap: 12px; font-size: 13px;">
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: var(--fg-muted);">Storage Driver</span>
                        <span style="font-family: var(--font-mono); font-weight: 700; color: var(--accent);"><?= htmlspecialchars(strtoupper($stats['system']['storage_driver'])) ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: var(--fg-muted);">Cloudflare R2 Bucket</span>
                        <span style="font-family: var(--font-mono);"><?= htmlspecialchars($stats['system']['r2_bucket'] ?: '(Not configured)') ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: var(--fg-muted);">R2 SigV4 Status</span>
                        <span><?= $stats['system']['r2_configured'] ? '<strong style="color:var(--accent);">Online / Ready</strong>' : '<span style="color:var(--fg-muted);">Disabled (Local Mode)</span>' ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: var(--fg-muted);">Database Driver</span>
                        <span style="font-family: var(--font-mono);"><?= htmlspecialchars(strtoupper($stats['system']['db_driver'])) ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: var(--fg-muted);">SQLite Database File</span>
                        <span style="font-family: var(--font-mono);"><?= htmlspecialchars($stats['system']['db_size_formatted']) ?></span>
                    </div>
                </div>
            </div>

            <!-- Server Environment -->
            <div style="background: var(--bg-1); border: 1px solid var(--border); border-radius: var(--radius); padding: 20px;">
                <h3 style="font-size: 16px; font-weight: 700; margin-bottom: 16px; border-bottom: 1px solid var(--border); padding-bottom: 8px;">Runtime Environment</h3>
                <div style="display: flex; flex-direction: column; gap: 12px; font-size: 13px;">
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: var(--fg-muted);">PHP Version</span>
                        <span style="font-family: var(--font-mono); font-weight: 700;"><?= htmlspecialchars($stats['system']['php_version']) ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: var(--fg-muted);">Operating System</span>
                        <span style="font-family: var(--font-mono);"><?= htmlspecialchars($stats['system']['server_os']) ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: var(--fg-muted);">Web Server / Host</span>
                        <span style="font-family: var(--font-mono);"><?= htmlspecialchars($stats['system']['server_software']) ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: var(--fg-muted);">Current Memory Allocated</span>
                        <span style="font-family: var(--font-mono);"><?= htmlspecialchars($stats['system']['memory_usage_formatted']) ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: var(--fg-muted);">Peak Memory Usage</span>
                        <span style="font-family: var(--font-mono);"><?= htmlspecialchars($stats['system']['peak_memory_formatted']) ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: var(--fg-muted);">Server UTC Time</span>
                        <span style="font-family: var(--font-mono);"><?= htmlspecialchars($stats['system']['server_time']) ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Admin Toast Notification -->
<div id="admin-toast" style="position: fixed; bottom: 24px; right: 24px; background: var(--fg); color: var(--bg); padding: 12px 20px; border-radius: var(--radius); font-size: 13px; font-weight: 600; box-shadow: 0 4px 12px rgba(0,0,0,0.15); display: none; z-index: 9999;"></div>

<script>
function showAdminToast(msg, isError = false) {
    const t = document.getElementById('admin-toast');
    t.textContent = msg;
    t.style.background = isError ? 'var(--danger)' : 'var(--fg)';
    t.style.display = 'block';
    setTimeout(() => { t.style.display = 'none'; }, 3500);
}

function switchAdminTab(tabName) {
    document.querySelectorAll('.admin-tab-btn').forEach(b => {
        b.classList.remove('active');
        b.style.color = 'var(--fg-muted)';
        b.style.borderBottom = 'none';
    });
    document.querySelectorAll('.admin-tab-pane').forEach(p => p.style.display = 'none');

    const activeBtn = document.getElementById('tab-btn-' + tabName);
    if (activeBtn) {
        activeBtn.classList.add('active');
        activeBtn.style.color = 'var(--accent)';
        activeBtn.style.borderBottom = '2px solid var(--accent)';
    }

    const activePane = document.getElementById('pane-' + tabName);
    if (activePane) {
        activePane.style.display = 'block';
    }
}

let activeFilter = 'all';

function setSiteFilter(filter, el) {
    activeFilter = filter;
    document.querySelectorAll('.filter-chip').forEach(c => {
        c.classList.remove('active');
        c.style.background = 'none';
        c.style.color = 'var(--fg-muted)';
    });
    el.classList.add('active');
    el.style.background = 'var(--bg-3)';
    el.style.color = 'var(--fg)';
    filterSitesTable();
}

function filterSitesTable() {
    const query = (document.getElementById('site-search-input').value || '').toLowerCase().trim();
    const rows = document.querySelectorAll('.admin-site-row');

    rows.forEach(r => {
        const slug = r.getAttribute('data-slug') || '';
        const title = r.getAttribute('data-title') || '';
        const owner = r.getAttribute('data-owner') || '';
        const status = r.getAttribute('data-status') || '';
        const ownership = r.getAttribute('data-ownership') || '';

        const matchesQuery = !query || slug.includes(query) || title.includes(query) || owner.includes(query);
        let matchesFilter = true;

        if (activeFilter === 'active') matchesFilter = (status === 'active');
        else if (activeFilter === 'suspended') matchesFilter = (status === 'suspended');
        else if (activeFilter === 'claimed') matchesFilter = (ownership === 'claimed');
        else if (activeFilter === 'guest') matchesFilter = (ownership === 'guest');

        r.style.display = (matchesQuery && matchesFilter) ? '' : 'none';
    });
}

function filterUsersTable() {
    const query = (document.getElementById('user-search-input').value || '').toLowerCase().trim();
    const rows = document.querySelectorAll('.admin-user-row');

    rows.forEach(r => {
        const email = r.getAttribute('data-email') || '';
        const name = r.getAttribute('data-name') || '';
        const matchesQuery = !query || email.includes(query) || name.includes(query);
        r.style.display = matchesQuery ? '' : 'none';
    });
}

async function toggleSiteSuspension(slug, btn) {
    btn.disabled = true;
    try {
        const res = await fetch('<?= url("api/admin/sites/") ?>' + encodeURIComponent(slug) + '/toggle-status', {
            method: 'POST',
            headers: { 'Accept': 'application/json' }
        });
        const data = await res.json();
        if (data.success) {
            showAdminToast(`Site '${slug}' is now ${data.status_label}.`);
            setTimeout(() => location.reload(), 800);
        } else {
            showAdminToast(data.error || 'Failed to update site status.', true);
            btn.disabled = false;
        }
    } catch (e) {
        showAdminToast('Network error while toggling site status.', true);
        btn.disabled = false;
    }
}

async function adminDeleteSite(slug, btn) {
    if (!confirm(`Are you sure you want to permanently delete '${slug}' and purge all associated files? This action cannot be undone.`)) {
        return;
    }
    btn.disabled = true;
    try {
        const res = await fetch('<?= url("api/admin/sites/") ?>' + encodeURIComponent(slug) + '/delete', {
            method: 'POST',
            headers: { 'Accept': 'application/json' }
        });
        const data = await res.json();
        if (data.success) {
            showAdminToast(`Site '${slug}' deleted successfully.`);
            setTimeout(() => location.reload(), 800);
        } else {
            showAdminToast(data.error || 'Failed to delete site.', true);
            btn.disabled = false;
        }
    } catch (e) {
        showAdminToast('Network error while deleting site.', true);
        btn.disabled = false;
    }
}

async function toggleUserRole(userId, email, btn) {
    if (!confirm(`Toggle administrative access for user '${email}'?`)) {
        return;
    }
    btn.disabled = true;
    try {
        const res = await fetch('<?= url("api/admin/users/") ?>' + userId + '/toggle-role', {
            method: 'POST',
            headers: { 'Accept': 'application/json' }
        });
        const data = await res.json();
        if (data.success) {
            showAdminToast(`User role updated to ${data.new_role}.`);
            setTimeout(() => location.reload(), 800);
        } else {
            showAdminToast(data.error || 'Failed to update user role.', true);
            btn.disabled = false;
        }
    } catch (e) {
        showAdminToast('Network error while updating user role.', true);
        btn.disabled = false;
    }
}

async function syncDbToR2(btn) {
    btn.disabled = true;
    const oldText = btn.innerHTML;
    btn.textContent = 'Syncing...';
    try {
        const res = await fetch('<?= url("api/admin/maintenance/sync-db") ?>', {
            method: 'POST',
            headers: { 'Accept': 'application/json' }
        });
        const data = await res.json();
        showAdminToast(data.message || (data.success ? 'DB synced to R2' : 'Failed to sync'));
    } catch (e) {
        showAdminToast('Network error during R2 backup.', true);
    } finally {
        btn.innerHTML = oldText;
        btn.disabled = false;
    }
}

async function cleanupExpiredSites(btn) {
    if (!confirm('Scan and delete all expired guest deployments?')) {
        return;
    }
    btn.disabled = true;
    const oldText = btn.innerHTML;
    btn.textContent = 'Scanning...';
    try {
        const res = await fetch('<?= url("api/admin/maintenance/cleanup-expired") ?>', {
            method: 'POST',
            headers: { 'Accept': 'application/json' }
        });
        const data = await res.json();
        showAdminToast(`Cleanup finished: ${data.deleted} expired sites removed.`);
        if (data.deleted > 0) {
            setTimeout(() => location.reload(), 1000);
        }
    } catch (e) {
        showAdminToast('Network error during cleanup.', true);
    } finally {
        btn.innerHTML = oldText;
        btn.disabled = false;
    }
}
</script>
