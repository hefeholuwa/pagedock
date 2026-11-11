<div class="auth-wrapper">
    <div class="auth-card" style="max-width: 460px;">
        <?php if (!empty($error)): ?>
            <div class="auth-header">
                <h2>Authorization Error</h2>
                <p class="text-muted">Could not complete the app connection request.</p>
            </div>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <a href="<?= url('dashboard') ?>" class="btn-secondary w-full mt-2" style="text-align:center; display:inline-block;">Back to Dashboard</a>
        <?php else: ?>
            <?php $clientName = htmlspecialchars($client['client_name'] ?? 'External AI App'); ?>
            <div class="auth-header">
                <div style="display:inline-flex; align-items:center; gap:8px; padding:5px 10px; border-radius:999px; background:var(--accent-soft, #eaf7f0); color:var(--accent-hover, #0d6e4e); font-size:12px; font-weight:700; margin-bottom:12px;">
                    OAuth 2.1 MCP Connection
                </div>
                <h2>Connect <?= $clientName ?></h2>
                <p class="text-muted">
                    Signed in as <strong><?= htmlspecialchars($user['email'] ?? '') ?></strong>
                </p>
            </div>

            <div style="background:var(--bg-subtle, #f8fafc); border:1px solid var(--border, #e2e8f0); border-radius:12px; padding:14px 16px; margin-bottom:18px; font-size:13.5px; line-height:1.55;">
                <p style="margin:0 0 8px 0; font-weight:600; color:var(--fg, #0f172a);"><?= $clientName ?> is requesting permission to:</p>
                <ul style="margin:0; padding-left:18px; color:var(--fg-muted, #475569);">
                    <li>List and read your PageDock hosted websites and source files</li>
                    <li>Publish new HTML sites and update files (creating version snapshots)</li>
                    <li>Inspect site analytics, version history, and form submissions</li>
                </ul>
            </div>

            <form action="<?= url('oauth/authorize') ?>" method="POST" class="auth-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '') ?>">
                <?php foreach (($params ?? []) as $k => $v): ?>
                    <input type="hidden" name="<?= htmlspecialchars($k) ?>" value="<?= htmlspecialchars((string)$v) ?>">
                <?php endforeach; ?>

                <div style="display:flex; gap:10px; margin-top:8px;">
                    <button type="submit" name="decision" value="deny" class="btn-secondary" style="flex:1;">Cancel</button>
                    <button type="submit" name="decision" value="approve" class="btn-primary" style="flex:2;">Approve Connection</button>
                </div>
            </form>

            <p class="auth-footer-text" style="margin-top:14px; font-size:12px;">
                You can revoke connected apps anytime from your PageDock Dashboard.
            </p>
        <?php endif; ?>
    </div>
</div>

