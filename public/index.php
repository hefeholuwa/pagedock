<?php
/**
 * PageDock — Static HTML Hosting & Live Editor
 */

require_once dirname(__DIR__) . '/config/config.php';

try {
    Router::handle();
} catch (Throwable $e) {
    http_response_code(500);
    echo "<h1>500 Internal Error</h1>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
}
