<div class="dashboard-container">
    <div class="dashboard-header">
        <div>
            <h1>Your Hosted Sites</h1>
            <p class="text-muted">Manage, edit, or check analytics for all your PageDock deployments.</p>
        </div>
        <a href="<?= url() ?>" class="btn-primary">+ Deploy New Site</a>
    </div>

    <?php if (empty($sites)): ?>
        <div class="empty-dashboard-card">
            <div class="empty-icon">🚀</div>
            <h3>No sites deployed yet</h3>
            <p>Upload a zip archive, an HTML file, or paste code to launch your first website in seconds.</p>
            <a href="<?= url() ?>" class="btn-primary mt-3">Deploy Your First Site</a>
        </div>
    <?php else: ?>
        <div class="sites-grid">
            <?php foreach ($sites as $site): ?>
                <div class="site-card" id="site-card-<?= htmlspecialchars($site['slug']) ?>">
                    <div class="site-card-header">
                        <div class="site-status-dot"></div>
                        <h3 class="site-title"><?= htmlspecialchars($site['title'] ?: 'Untitled Site') ?></h3>
                        <span class="site-views"><?= (int)$site['views_count'] ?> views</span>
                    </div>

                    <div class="site-url-box">
                        <a href="<?= url('s/' . $site['slug']) ?>" target="_blank" class="site-link">
                            <?= url('s/' . $site['slug']) ?> ↗
                        </a>
                    </div>

                    <div class="site-meta-stats">
                        <span>📁 <?= (int)$site['file_count'] ?> files</span>
                        <span>📦 <?= number_format($site['total_size_bytes'] / 1024, 1) ?> KB</span>
                        <span>🕒 <?= date('M j, Y', strtotime($site['created_at'])) ?></span>
                    </div>

                    <div class="site-card-actions">
                        <a href="<?= url('editor/' . $site['slug']) ?>" class="btn-sm btn-secondary">Open Editor</a>
                        <a href="<?= url('s/' . $site['slug']) ?>" target="_blank" class="btn-sm btn-ghost">View Live</a>
                        <button type="button" class="btn-sm btn-danger delete-site-btn" data-slug="<?= htmlspecialchars($site['slug']) ?>">Delete</button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
document.querySelectorAll('.delete-site-btn').forEach(btn => {
    btn.addEventListener('click', async () => {
        const slug = btn.getAttribute('data-slug');
        if (!confirm(`Are you sure you want to delete /s/${slug}? This cannot be undone.`)) {
            return;
        }

        try {
            const res = await fetch(`<?= url('api/sites/') ?>${slug}/delete`, {
                method: 'POST'
            });
            const data = await res.json();
            if (data.success) {
                document.getElementById(`site-card-${slug}`)?.remove();
            } else {
                alert(data.error || 'Failed to delete site.');
            }
        } catch (e) {
            alert('Error deleting site.');
        }
    });
});
</script>
