<?php
/**
 * PageDock Scheduled Task: Prune Expired Guest Sites (Pillar 5)
 * Inspired by anvilnine/artifacts expiry lifecycle
 * Run via cron or background daemon: php scripts/cleanup_expired_sites.php
 */

require_once dirname(__DIR__) . '/config/config.php';

$count = SiteManager::cleanupExpiredSites();
$timestamp = date('Y-m-d H:i:s');
echo "[{$timestamp}] PageDock Cleanup: Pruned {$count} expired guest sites.\n";

