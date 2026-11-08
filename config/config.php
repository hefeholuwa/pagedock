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

// Lightweight .env parser
$envFile = ROOT_DIR . '/.env';
if (file_exists($envFile)) {
    $envLines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($envLines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (str_contains($line, '=')) {
            [$k, $v] = explode('=', $line, 2);
            $k = trim($k);
            $v = trim($v);
            if ((str_starts_with($v, '"') && str_ends_with($v, '"')) ||
                (str_starts_with($v, "'") && str_ends_with($v, "'"))) {
                $v = substr($v, 1, -1);
            }
            if (!array_key_exists($k, $_SERVER) && !array_key_exists($k, $_ENV)) {
                putenv("{$k}={$v}");
                $_ENV[$k] = $v;
                $_SERVER[$k] = $v;
            }
        }
    }
}

// Database configuration
define('DB_DRIVER', getenv('DB_DRIVER') ?: 'sqlite');
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'pagedock');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');

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
    // Dynamically use current browser host if available (localhost:8080 or production domain)
    if (!empty($_SERVER['HTTP_HOST'])) {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
        return rtrim($protocol . $_SERVER['HTTP_HOST'], '/') . '/' . ltrim($path, '/');
    }

    $appUrl = getenv('APP_URL');
    if (!empty($appUrl)) {
        return rtrim($appUrl, '/') . '/' . ltrim($path, '/');
    }

    return '/' . ltrim($path, '/');
}

/**
 * Helper to generate versioned asset URLs for cache busting
 */
function asset(string $path): string {
    $filePath = PUBLIC_DIR . '/' . ltrim($path, '/');
    $version = file_exists($filePath) ? filemtime($filePath) : '1.0';
    return url($path) . '?v=' . $version;
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
