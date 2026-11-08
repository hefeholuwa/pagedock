<div class="dashboard-container">
    <div class="inbox-breadcrumb">
        <a href="<?= url('dashboard') ?>">&larr; Back to Dashboard</a>
        <span class="sep">/</span>
        <span class="current">Form Submissions</span>
    </div>

    <div class="dashboard-header">
        <div>
            <h1><?= htmlspecialchars($site['title'] ?: 'Untitled Site') ?> &mdash; Forms Inbox</h1>
            <p class="text-muted">Live submissions captured via <code>/__pd/form</code> for <a href="<?= url('s/' . $site['slug']) ?>" target="_blank" class="accent-text"><?= url('s/' . $site['slug']) ?> &rarr;</a></p>
        </div>
        <div class="inbox-header-actions">
            <a href="<?= url('api/sites/' . $site['slug'] . '/forms/export') ?>" class="btn-primary btn-sm">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Export CSV
            </a>
            <button type="button" id="copy-sheets-btn" class="btn-secondary btn-sm" data-url="<?= url('api/sites/' . $site['slug'] . '/forms/export') ?>">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                Google Sheets Formula
            </button>
        </div>
    </div>

    <!-- Quick Stat Counters -->
    <div class="inbox-stats-row">
        <div class="inbox-stat-card">
            <span class="stat-num"><?= $stats['valid'] ?></span>
            <span class="stat-label">Verified Leads</span>
        </div>
        <div class="inbox-stat-card">
            <span class="stat-num"><?= $stats['spam'] ?></span>
            <span class="stat-label">Honeypot Trapped (Spam)</span>
        </div>
        <div class="inbox-stat-card">
            <span class="stat-num"><?= $stats['total'] ?></span>
            <span class="stat-label">Total Submissions</span>
        </div>
    </div>

    <!-- Filter Tabs (Inbox vs Spam) -->
    <div class="inbox-tabs">
        <a href="<?= url('dashboard/forms/' . $site['slug']) ?>" class="inbox-tab <?= !$showSpam ? 'active' : '' ?>">
            Valid Submissions (<?= $stats['valid'] ?>)
        </a>
        <a href="<?= url('dashboard/forms/' . $site['slug'] . '?spam=1') ?>" class="inbox-tab <?= $showSpam ? 'active' : '' ?>">
            Spam Quarantine (<?= $stats['spam'] ?>)
        </a>
    </div>

    <?php if (empty($submissions)): ?>
        <div class="empty-inbox-card">
            <div class="empty-icon-box">
                <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
            </div>
            <h3>No <?= $showSpam ? 'spam' : 'form' ?> submissions yet</h3>
            <p>Add a standard form pointing to <code>/__pd/form</code> anywhere on your site to collect submissions automatically:</p>
            <div class="code-snippet-box">
<pre>&lt;form action="/__pd/form" method="POST"&gt;
  &lt;input type="hidden" name="_form" value="Contact Us"&gt;
  &lt;input type="text" name="name" placeholder="Your Name" required&gt;
  &lt;input type="email" name="email" placeholder="Your Email" required&gt;
  &lt;textarea name="message" placeholder="Message"&gt;&lt;/textarea&gt;
  &lt;button type="submit"&gt;Send Message&lt;/button&gt;
&lt;/form&gt;</pre>
            </div>
        </div>
    <?php else: ?>
        <div class="submissions-list">
            <?php foreach ($submissions as $sub): ?>
                <div class="submission-card <?= $sub['is_spam'] ? 'is-spam' : '' ?>" id="sub-row-<?= (int)$sub['id'] ?>">
                    <div class="sub-card-header">
                        <div class="sub-header-left">
                            <span class="sub-form-tag"><?= htmlspecialchars($sub['form_name'] ?: 'Contact') ?></span>
                            <?php if ($sub['is_spam']): ?>
                                <span class="sub-spam-tag">Honeypot Triggered</span>
                            <?php endif; ?>
                            <span class="sub-date"><?= date('M j, Y \a\t g:i A', strtotime($sub['created_at'])) ?></span>
                        </div>
                        <div class="sub-header-actions">
                            <button type="button" class="btn-toggle-spam btn-icon" data-id="<?= (int)$sub['id'] ?>" title="<?= $sub['is_spam'] ? 'Mark as Valid' : 'Mark as Spam' ?>">
                                <?= $sub['is_spam'] ? 'Move to Inbox' : 'Flag as Spam' ?>
                            </button>
                            <button type="button" class="btn-delete-sub btn-icon danger" data-id="<?= (int)$sub['id'] ?>" title="Delete Submission">
                                Delete
                            </button>
                        </div>
                    </div>

                    <div class="sub-fields-grid">
                        <?php foreach ($sub['data'] as $fieldKey => $fieldVal): ?>
                            <div class="sub-field-item">
                                <span class="field-key"><?= htmlspecialchars($fieldKey) ?>:</span>
                                <span class="field-val">
                                    <?php if (is_array($fieldVal)): ?>
                                        <code><?= htmlspecialchars(json_encode($fieldVal)) ?></code>
                                    <?php elseif (filter_var($fieldVal, FILTER_VALIDATE_EMAIL)): ?>
                                        <a href="mailto:<?= htmlspecialchars($fieldVal) ?>" class="accent-text"><?= htmlspecialchars($fieldVal) ?></a>
                                    <?php else: ?>
                                        <?= nl2br(htmlspecialchars((string)$fieldVal)) ?>
                                    <?php endif; ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="sub-footer-meta">
                        <span>IP: <code><?= htmlspecialchars($sub['ip_address'] ?: 'unknown') ?></code></span>
                        <?php if (!empty($sub['user_agent'])): ?>
                            <span>&bull;</span>
                            <span class="user-agent-text" title="<?= htmlspecialchars($sub['user_agent']) ?>"><?= htmlspecialchars(substr($sub['user_agent'], 0, 48)) ?>...</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Copy Google Sheets formula
    const sheetsBtn = document.getElementById('copy-sheets-btn');
    if (sheetsBtn) {
        sheetsBtn.addEventListener('click', async () => {
            const exportUrl = sheetsBtn.getAttribute('data-url');
            const formula = `=IMPORTDATA("${exportUrl}")`;
            try {
                await navigator.clipboard.writeText(formula);
                const originalHtml = sheetsBtn.innerHTML;
                sheetsBtn.innerHTML = '<span>Copied Formula!</span>';
                setTimeout(() => { sheetsBtn.innerHTML = originalHtml; }, 2500);
            } catch (err) {
                alert(`Copy this formula for Google Sheets:\n${formula}`);
            }
        });
    }

    // Delete submission
    document.querySelectorAll('.btn-delete-sub').forEach(btn => {
        btn.addEventListener('click', async () => {
            const id = btn.getAttribute('data-id');
            if (!confirm('Are you sure you want to permanently delete this submission?')) {
                return;
            }

            try {
                const res = await fetch(`<?= url('api/forms/') ?>${id}/delete`, {
                    method: 'POST'
                });
                const data = await res.json();
                if (data.success) {
                    document.getElementById(`sub-row-${id}`)?.remove();
                } else {
                    alert(data.error || 'Failed to delete submission.');
                }
            } catch (e) {
                alert('Network error deleting submission.');
            }
        });
    });

    // Toggle spam status
    document.querySelectorAll('.btn-toggle-spam').forEach(btn => {
        btn.addEventListener('click', async () => {
            const id = btn.getAttribute('data-id');
            try {
                const res = await fetch(`<?= url('api/forms/') ?>${id}/toggle-spam`, {
                    method: 'POST'
                });
                const data = await res.json();
                if (data.success) {
                    window.location.reload();
                } else {
                    alert(data.error || 'Failed to update spam status.');
                }
            } catch (e) {
                alert('Network error updating spam status.');
            }
        });
    });
});
</script>

