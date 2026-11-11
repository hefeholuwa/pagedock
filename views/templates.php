<div class="templates-header">
    <div class="templates-hero">
        <span class="badge">Ready-Made Website Templates</span>
        <h1>Pick a design.<br><span class="accent-text">Go live in one click.</span></h1>
        <p class="templates-subtitle">
            Clean, mobile-ready starting points for small businesses, portfolios, restaurants, clinics, and product launches.
            Preview any layout and make it yours immediately.
        </p>
    </div>

    <!-- Filter & Search Bar -->
    <div class="templates-filter-bar">
        <div class="category-pills">
            <button type="button" class="cat-pill active" data-cat="all">All</button>
            <?php foreach ($categories as $cat): ?>
                <button type="button" class="cat-pill" data-cat="<?= htmlspecialchars(strtolower($cat)) ?>">
                    <?= htmlspecialchars($cat) ?>
                </button>
            <?php endforeach; ?>
        </div>

        <div class="search-wrapper">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" id="template-search" placeholder="Search templates, tags, use-cases..." class="search-input">
        </div>
    </div>

    <div class="templates-count-indicator">
        Showing <span id="template-visible-count"><?= count($templates) ?></span> of <?= count($templates) ?> templates
    </div>
</div>

<!-- Templates Grid -->
<div class="templates-grid-container">
    <div class="templates-grid" id="templates-grid">
        <?php foreach ($templates as $t): ?>
            <div class="template-card" 
                 data-category="<?= htmlspecialchars(strtolower($t['category'])) ?>"
                 data-title="<?= htmlspecialchars(strtolower($t['title'])) ?>"
                 data-tags="<?= htmlspecialchars(strtolower(implode(' ', $t['tags']))) ?>"
                 data-slug="<?= htmlspecialchars($t['slug']) ?>">
                
                <div class="template-preview-box preview-trigger-area" 
                     data-slug="<?= htmlspecialchars($t['slug']) ?>" 
                     data-title="<?= htmlspecialchars($t['title']) ?>"
                     role="button"
                     tabindex="0"
                     aria-label="Preview <?= htmlspecialchars($t['title']) ?>">
                    <div class="card-browser-mockup">
                        <div class="mockup-dots">
                            <span class="m-dot dot-red"></span>
                            <span class="m-dot dot-yellow"></span>
                            <span class="m-dot dot-green"></span>
                        </div>
                        <span class="mockup-domain">pagedock.site/<?= htmlspecialchars($t['slug']) ?></span>
                    </div>
                    <div class="card-visual-hero">
                        <span class="visual-category-icon"><?= strtoupper(substr($t['category'], 0, 2)) ?></span>
                        <span class="visual-template-title"><?= htmlspecialchars($t['title']) ?></span>
                        <span class="btn-preview-pill">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                            Live Preview
                        </span>
                    </div>
                    <?php if (!empty($t['badge'])): ?>
                        <span class="template-badge"><?= htmlspecialchars($t['badge']) ?></span>
                    <?php endif; ?>
                </div>

                <div class="template-info">
                    <div class="template-title-row">
                        <h3><?= htmlspecialchars($t['title']) ?></h3>
                        <span class="template-cat-tag"><?= htmlspecialchars($t['category']) ?></span>
                    </div>
                    <p class="template-desc"><?= htmlspecialchars($t['description']) ?></p>

                    <div class="template-tags">
                        <?php foreach ($t['tags'] as $tag): ?>
                            <span class="t-tag">#<?= htmlspecialchars($tag) ?></span>
                        <?php endforeach; ?>
                    </div>

                    <div class="template-actions">
                        <button type="button" class="btn-sm btn-ghost preview-modal-btn" data-slug="<?= htmlspecialchars($t['slug']) ?>" data-title="<?= htmlspecialchars($t['title']) ?>">
                            Preview
                        </button>
                        <button type="button" class="btn-sm btn-accent use-template-btn" data-slug="<?= htmlspecialchars($t['slug']) ?>">
                            Use Template &rarr;
                        </button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Template Interactive Preview Modal -->
<div id="template-modal" class="modal-overlay hidden">
    <div class="modal-card template-modal-card">
        <div class="modal-header">
            <div class="modal-title-group">
                <h3 id="modal-template-title">Template Preview</h3>
                <span class="badge-pill desktop-only">Interactive Preview</span>
            </div>

            <!-- Viewport Resizers (Desktop/Tablet/Mobile) -->
            <div class="modal-viewport-controls desktop-only">
                <button type="button" class="m-viewport-btn active" data-width="100%" title="Desktop View">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="20" height="14" x="2" y="3" rx="2"/><line x1="8" x2="16" y1="21" y2="21"/><line x1="12" x2="12" y1="17" y2="21"/></svg>
                </button>
                <button type="button" class="m-viewport-btn" data-width="768px" title="Tablet View">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="16" height="20" x="4" y="2" rx="2"/><line x1="12" x2="12.01" y1="18" y2="18"/></svg>
                </button>
                <button type="button" class="m-viewport-btn" data-width="375px" title="Mobile View">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="14" height="20" x="5" y="2" rx="2"/><path d="M12 18h.01"/></svg>
                </button>
            </div>

            <div class="modal-header-actions">
                <button type="button" id="modal-use-btn" class="btn-sm btn-accent">Use Template &rarr;</button>
                <button type="button" class="modal-close" id="close-template-modal">&times;</button>
            </div>
        </div>

        <div class="modal-frame-wrapper">
            <iframe id="modal-preview-iframe" src="about:blank" class="modal-preview-frame"></iframe>
        </div>
    </div>
</div>
