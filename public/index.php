<?php
/**
 * PageDock — Static HTML Hosting & Live Editor
 */

// If running under PHP CLI built-in web server, serve static files directly if they exist
if (php_sapi_name() === 'cli-server') {
    $filePath = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (is_file($filePath)) {
        return false;
    }
}

require_once dirname(__DIR__) . '/config/config.php';

try {
    Router::handle();
} catch (Throwable $e) {
    http_response_code(500);
    echo "<h1>500 Internal Error</h1>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
}
