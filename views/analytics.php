<div class="dashboard-container">
    <div class="inbox-breadcrumb">
        <a href="<?= url('dashboard') ?>">&larr; Back to Dashboard</a>
        <span class="sep">/</span>
        <span class="current">Site Analytics</span>
    </div>

    <div class="dashboard-header">
        <div>
            <h1><?= htmlspecialchars($site['title'] ?: 'Untitled Site') ?> &mdash; Analytics</h1>
            <p class="text-muted">Privacy-friendly visitor stats for <a href="<?= url('s/' . $site['slug']) ?>" target="_blank" class="accent-text"><?= url('s/' . $site['slug']) ?> &rarr;</a></p>
        </div>
        <div class="analytics-range-selector">
            <a href="<?= url('dashboard/analytics/' . $site['slug'] . '?days=7') ?>" class="range-btn <?= $days === 7 ? 'active' : '' ?>">Last 7 Days</a>
            <a href="<?= url('dashboard/analytics/' . $site['slug'] . '?days=30') ?>" class="range-btn <?= $days === 30 ? 'active' : '' ?>">Last 30 Days</a>
            <a href="<?= url('dashboard/analytics/' . $site['slug'] . '?days=90') ?>" class="range-btn <?= $days === 90 ? 'active' : '' ?>">Last 90 Days</a>
        </div>
    </div>

    <!-- KPI Metric Cards -->
    <div class="analytics-kpi-grid">
        <div class="analytics-kpi-card">
            <span class="kpi-label">Total Views</span>
            <span class="kpi-value text-accent"><?= number_format($analytics['total_views']) ?></span>
            <span class="kpi-sub"><?= number_format($analytics['today_views']) ?> recorded today</span>
        </div>
        <div class="analytics-kpi-card">
            <span class="kpi-label">Unique Visitors</span>
            <span class="kpi-value"><?= number_format($analytics['unique_visitors']) ?></span>
            <span class="kpi-sub"><?= number_format($analytics['today_uniques']) ?> unique today</span>
        </div>
        <div class="analytics-kpi-card">
            <span class="kpi-label">Primary Device</span>
            <?php 
                $topDev = !empty($analytics['devices']) ? $analytics['devices'][0] : ['device' => 'None', 'percentage' => 0];
            ?>
            <span class="kpi-value"><?= htmlspecialchars($topDev['device']) ?></span>
            <span class="kpi-sub"><?= $topDev['percentage'] ?>% of traffic</span>
        </div>
        <div class="analytics-kpi-card">
            <span class="kpi-label">Top Location</span>
            <?php 
                $topCountry = !empty($analytics['top_countries']) ? $analytics['top_countries'][0] : ['country' => 'N/A', 'views' => 0, 'percentage' => 0];
            ?>
            <span class="kpi-value"><?= htmlspecialchars($topCountry['country']) ?></span>
            <span class="kpi-sub"><?= $topCountry['percentage'] ?>% of traffic</span>
        </div>
    </div>

    <!-- Time Series Activity Chart -->
    <div class="analytics-section-card">
        <div class="card-header-flex">
            <div>
                <h3>Traffic Over Time</h3>
                <p class="text-muted">Daily page views and unique visitors over the selected period</p>
            </div>
            <div class="chart-legend">
                <span class="legend-item"><span class="legend-dot accent"></span> Views</span>
                <span class="legend-item"><span class="legend-dot muted"></span> Uniques</span>
            </div>
        </div>

        <?php 
            $maxViews = 1;
            foreach ($analytics['time_series'] as $pt) {
                if ($pt['views'] > $maxViews) $maxViews = $pt['views'];
            }
        ?>

        <div class="chart-bars-wrapper">
            <div class="chart-bars-container">
                <?php foreach ($analytics['time_series'] as $pt): 
                    $vHeight = round(($pt['views'] / $maxViews) * 100);
                    $uHeight = round(($pt['uniques'] / $maxViews) * 100);
                ?>
                    <div class="chart-col-group" title="<?= htmlspecialchars($pt['label']) ?>: <?= $pt['views'] ?> views, <?= $pt['uniques'] ?> uniques">
                        <div class="chart-col-track">
                            <div class="chart-bar-fill views-fill" style="height: <?= max(4, $vHeight) ?>%;"></div>
                            <div class="chart-bar-fill uniques-fill" style="height: <?= max(2, $uHeight) ?>%;"></div>
                        </div>
                        <span class="chart-date-label"><?= htmlspecialchars(substr($pt['label'], 0, 3)) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Breakdown Grid -->
    <div class="analytics-two-col-grid">
        <!-- Top Pages -->
        <div class="analytics-section-card">
            <h3>Top Pages</h3>
            <p class="text-muted">Most frequented pages on your website</p>

            <?php if (empty($analytics['top_pages'])): ?>
                <div class="empty-breakdown-card">
                    <p>No page visits recorded in this period.</p>
                </div>
            <?php else: ?>
                <div class="breakdown-list">
                    <?php foreach ($analytics['top_pages'] as $page): ?>
                        <div class="breakdown-row">
                            <div class="breakdown-row-info">
                                <span class="breakdown-name font-mono"><?= htmlspecialchars($page['path']) ?></span>
                                <span class="breakdown-count"><?= number_format($page['views']) ?> views (<?= $page['percentage'] ?>%)</span>
                            </div>
                            <div class="breakdown-bar-track">
                                <div class="breakdown-bar-fill accent" style="width: <?= $page['percentage'] ?>%;"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Top Referrers -->
        <div class="analytics-section-card">
            <h3>Traffic Referrers</h3>
            <p class="text-muted">Origins and search engines routing visitors</p>

            <?php if (empty($analytics['top_referrers'])): ?>
                <div class="empty-breakdown-card">
                    <p>No referrer data recorded yet.</p>
                </div>
            <?php else: ?>
                <div class="breakdown-list">
                    <?php foreach ($analytics['top_referrers'] as $ref): ?>
                        <div class="breakdown-row">
                            <div class="breakdown-row-info">
                                <span class="breakdown-name"><?= htmlspecialchars($ref['host']) ?></span>
                                <span class="breakdown-count"><?= number_format($ref['views']) ?> views (<?= $ref['percentage'] ?>%)</span>
                            </div>
                            <div class="breakdown-bar-track">
                                <div class="breakdown-bar-fill" style="width: <?= $ref['percentage'] ?>%;"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Secondary Breakdown Grid: Devices and Countries -->
    <div class="analytics-two-col-grid">
        <!-- Device Breakdown -->
        <div class="analytics-section-card">
            <h3>Devices</h3>
            <p class="text-muted">Breakdown by visitor screen and form factor</p>

            <?php if (empty($analytics['devices'])): ?>
                <div class="empty-breakdown-card">
                    <p>No device data available.</p>
                </div>
            <?php else: ?>
                <div class="breakdown-list">
                    <?php foreach ($analytics['devices'] as $dev): ?>
                        <div class="breakdown-row">
                            <div class="breakdown-row-info">
                                <span class="breakdown-name"><?= htmlspecialchars($dev['device']) ?></span>
                                <span class="breakdown-count"><?= number_format($dev['count']) ?> visits (<?= $dev['percentage'] ?>%)</span>
                            </div>
                            <div class="breakdown-bar-track">
                                <div class="breakdown-bar-fill accent" style="width: <?= $dev['percentage'] ?>%;"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Geographic Origins -->
        <div class="analytics-section-card">
            <h3>Geographic Origins</h3>
            <p class="text-muted">Visitor locations via edge network headers</p>

            <?php if (empty($analytics['top_countries'])): ?>
                <div class="empty-breakdown-card">
                    <p>No geographic data available.</p>
                </div>
            <?php else: ?>
                <div class="breakdown-list">
                    <?php foreach ($analytics['top_countries'] as $c): ?>
                        <div class="breakdown-row">
                            <div class="breakdown-row-info">
                                <span class="breakdown-name font-mono"><?= htmlspecialchars($c['country']) ?></span>
                                <span class="breakdown-count"><?= number_format($c['views']) ?> visits (<?= $c['percentage'] ?>%)</span>
                            </div>
                            <div class="breakdown-bar-track">
                                <div class="breakdown-bar-fill" style="width: <?= $c['percentage'] ?>%;"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

