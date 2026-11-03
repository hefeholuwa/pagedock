<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editor — <?= htmlspecialchars($site['title']) ?> | PageDock</title>
    <link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
    <link rel="stylesheet" href="<?= url('assets/css/editor.css') ?>">
</head>
<body class="editor-body">
    <!-- Editor Top Bar -->
    <header class="editor-topbar">
        <div class="topbar-left">
            <a href="<?= url('dashboard') ?>" class="btn-icon" title="Back to Dashboard">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 18-6-6 6-6"/></svg>
            </a>
            <div class="editor-site-info">
                <span class="editor-site-title"><?= htmlspecialchars($site['title']) ?></span>
                <span class="editor-site-slug">/s/<?= htmlspecialchars($site['slug']) ?></span>
                <span id="save-status" class="save-status saved">Saved</span>
            </div>
        </div>

        <!-- Viewport switchers -->
        <div class="viewport-controls">
            <button type="button" class="viewport-btn active" data-width="100%" title="Desktop View">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="20" height="14" x="2" y="3" rx="2"/><line x1="8" x2="16" y1="21" y2="21"/><line x1="12" x2="12" y1="17" y2="21"/></svg>
            </button>
            <button type="button" class="viewport-btn" data-width="768px" title="Tablet View">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="16" height="20" x="4" y="2" rx="2"/><line x1="12" x2="12.01" y1="18" y2="18"/></svg>
            </button>
            <button type="button" class="viewport-btn" data-width="375px" title="Mobile View">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="14" height="20" x="5" y="2" rx="2"/><path d="M12 18h.01"/></svg>
            </button>
        </div>

        <div class="topbar-right">
            <button type="button" id="forms-modal-btn" class="btn-sm btn-ghost">
                📬 Form Inquiries
            </button>
            <a href="<?= url('s/' . $site['slug']) ?>" target="_blank" class="btn-sm btn-ghost">
                Open Live ↗
            </a>
            <?php if ($canManage): ?>
                <button type="button" id="save-code-btn" class="btn-sm btn-accent">
                    Save &amp; Publish (Ctrl+S)
                </button>
            <?php else: ?>
                <span class="badge-read-only">Read Only Mode</span>
            <?php endif; ?>
        </div>
    </header>

    <!-- Main Workspace (Split View) -->
    <div class="editor-workspace">
        <!-- Left: Code Panel -->
        <div class="editor-pane code-pane">
            <div class="pane-header">
                <span><?= htmlspecialchars($site['entry_file'] ?: 'index.html') ?></span>
                <span class="pane-tag">HTML / CSS / JS</span>
            </div>
            <div class="textarea-container">
                <textarea id="code-editor" class="code-textarea" spellcheck="false" <?= $canManage ? '' : 'readonly' ?>><?= htmlspecialchars($html ?? '') ?></textarea>
            </div>
        </div>

        <!-- Resizer Divider -->
        <div class="pane-resizer" id="pane-resizer"></div>

        <!-- Right: Live Preview Pane -->
        <div class="editor-pane preview-pane">
            <div class="pane-header preview-header">
                <div class="preview-url-bar">
                    <span class="ssl-indicator">🔒</span>
                    <span class="url-text"><?= url('s/' . $site['slug']) ?></span>
                </div>
                <button type="button" id="refresh-preview-btn" class="btn-icon-sm" title="Refresh Preview">
                    ↻
                </button>
            </div>
            <div class="preview-container">
                <iframe id="preview-iframe" src="<?= url('s/' . $site['slug']) ?>?t=<?= time() ?>" class="preview-frame" frameborder="0"></iframe>
            </div>
        </div>
    </div>

    <!-- Forms Submission Modal -->
    <div id="forms-modal" class="modal-overlay hidden">
        <div class="modal-card">
            <div class="modal-header">
                <h3>Form Submissions for <?= htmlspecialchars($site['title']) ?></h3>
                <button type="button" class="modal-close" id="close-modal-btn">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-endpoint-hint">
                    <p>To receive contact form submissions here, set your HTML form action to:</p>
                    <code class="code-pill"><?= url('api/forms/' . $site['slug']) ?></code>
                </div>

                <?php
                $submissions = Forms::getSubmissions((int)$site['id']);
                if (empty($submissions)):
                ?>
                    <div class="empty-state">
                        <p>No messages received yet.</p>
                        <p class="text-muted">Test it by adding a form with <code>method="POST" action="<?= url('api/forms/' . $site['slug']) ?>"</code> to your site!</p>
                    </div>
                <?php else: ?>
                    <div class="submissions-list">
                        <?php foreach ($submissions as $sub): ?>
                            <div class="submission-card">
                                <div class="sub-meta">
                                    <span class="sub-date"><?= htmlspecialchars($sub['created_at']) ?></span>
                                    <span class="sub-ip">IP: <?= htmlspecialchars($sub['ip_address']) ?></span>
                                </div>
                                <div class="sub-data">
                                    <?php foreach ($sub['data'] as $key => $val): ?>
                                        <div class="sub-field">
                                            <strong><?= htmlspecialchars($key) ?>:</strong>
                                            <span><?= nl2br(htmlspecialchars(is_array($val) ? json_encode($val) : $val)) ?></span>
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

    <!-- Toast Notification -->
    <div id="toast" class="toast hidden"></div>

    <script>
        window.PAGEDOCK_CONFIG = {
            slug: '<?= addslashes($site['slug']) ?>',
            saveUrl: '<?= url('api/editor/' . $site['slug']) ?>',
            previewUrl: '<?= url('s/' . $site['slug']) ?>',
            canManage: <?= $canManage ? 'true' : 'false' ?>
        };
    </script>
    <script src="<?= url('assets/js/editor.js') ?>"></script>
</body>
</html>
