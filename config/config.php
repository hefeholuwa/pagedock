<?php
// PageDock Configuration

define('APP_NAME', 'PageDock');
define('APP_TAGLINE', 'Host your HTML. Get a link.');
define('APP_VERSION', '1.0.0');

define('ROOT_DIR', dirname(__DIR__));
define('PUBLIC_DIR', ROOT_DIR . '/public');
define('SITES_DIR', ROOT_DIR . '/sites');
define('DATA_DIR', ROOT_DIR . '/data');
define('VIEWS_DIR', ROOT_DIR . '/views');
define('SRC_DIR', ROOT_DIR . '/src');

define('DB_PATH', DATA_DIR . '/pagedock.sqlite');
define('MAX_UPLOAD_BYTES', 50 * 1024 * 1024); // 50MB
define('DEFAULT_EXPIRY_DAYS', 0); // 0 = never expires

// Start session securely
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Autoloader for src/ classes
 */
spl_autoload_register(function ($class) {
    $file = SRC_DIR . '/' . str_replace('\\', '/', $class) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

/**
 * Helper to generate relative or absolute app URLs
 */
function url(string $path = ''): string {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8080';
    return rtrim($protocol . $host, '/') . '/' . ltrim($path, '/');
}

/**
 * Redirect helper
 */
function redirect(string $path): void {
    header('Location: ' . url($path));
    exit;
}

/**
 * JSON response helper
 */
function json_response(array $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

/**
 * Render view with layout
 */
function render_view(string $viewName, array $data = [], bool $includeLayout = true): void {
    extract($data);
    
    if ($includeLayout) {
        require_once VIEWS_DIR . '/layouts/header.php';
    }
    
    $viewPath = VIEWS_DIR . '/' . $viewName . '.php';
    if (file_exists($viewPath)) {
        require $viewPath;
    } else {
        echo "<p>View not found: " . htmlspecialchars($viewName) . "</p>";
    }
    
    if ($includeLayout) {
        require_once VIEWS_DIR . '/layouts/footer.php';
    }
}
