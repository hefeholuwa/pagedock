<?php

class Router {
    public static function handle(): void {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        // Trim slashes
        $path = trim($uri, '/');
        $parts = $path === '' ? [] : explode('/', $path);

        // 0. Favicon handler (/favicon.svg & /favicon.ico)
        if ($path === 'favicon.svg' || $path === 'favicon.ico') {
            $favPath = PUBLIC_DIR . '/favicon.svg';
            if (is_file($favPath)) {
                header('Content-Type: image/svg+xml; charset=utf-8');
                header('Cache-Control: public, max-age=86400');
                readfile($favPath);
                return;
            }
        }

        // 0a-health. Fast Health Check & Keep-Alive Endpoint
        if ($path === 'health' || $path === 'ping') {
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-cache, no-store, must-revalidate');
            echo json_encode([
                'status' => 'ok',
                'app' => APP_NAME,
                'version' => APP_VERSION,
                'storage' => defined('STORAGE_DRIVER') ? STORAGE_DRIVER : 'local',
                'timestamp' => time()
            ]);
            return;
        }

        // 0b. Remote MCP Endpoint (ChatGPT Apps / Connectors & AI Clients)
        if ($path === 'mcp') {
            McpServer::handle($method);
            return;
        }

        // 0c. OAuth 2.1 Discovery & Dynamic Client Registration for MCP
        if ($method === 'OPTIONS' && (!empty($parts) && (in_array($parts[0], ['.well-known', 'oauth'], true) || $path === 'openapi.json'))) {
            OAuthServer::sendCorsHeaders();
            http_response_code(204);
            exit;
        }

        if (($path === '.well-known/oauth-protected-resource' || $path === '.well-known/oauth-protected-resource/mcp') && $method === 'GET') {
            OAuthServer::handleProtectedResourceMetadata();
            return;
        }

        if (($path === '.well-known/oauth-authorization-server' || $path === '.well-known/oauth-authorization-server/mcp' || $path === '.well-known/openid-configuration' || $path === '.well-known/openid-configuration/mcp') && $method === 'GET') {
            OAuthServer::handleAuthServerMetadata();
            return;
        }

        if ($path === '.well-known/jwks.json' && $method === 'GET') {
            OAuthServer::handleJwks();
            return;
        }

        if (($path === 'oauth/register' || $path === 'mcp/register' || $path === 'register_client') && $method === 'POST') {
            OAuthServer::handleRegister();
            return;
        }

        if (($path === 'oauth/authorize' || $path === 'mcp/authorize') && ($method === 'GET' || $method === 'POST')) {
            OAuthServer::handleAuthorize($method);
            return;
        }

        if (($path === 'oauth/token' || $path === 'mcp/token') && $method === 'POST') {
            OAuthServer::handleToken();
            return;
        }

        if ($path === 'openapi.json' && $method === 'GET') {
            self::renderOpenApiSpec();
            return;
        }

        // 0d. Layer 3b Safety Net: If a stray root-relative asset request arrives at /{asset} with an /s/{slug} Referer
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($ext && in_array($ext, ['css', 'js', 'png', 'jpg', 'jpeg', 'svg', 'webp', 'gif', 'ico', 'woff', 'woff2', 'ttf', 'json', 'map'], true)) {
            $publicCandidate = realpath(PUBLIC_DIR . '/' . $path);
            if (!$publicCandidate || !is_file($publicCandidate)) {
                $referer = $_SERVER['HTTP_REFERER'] ?? '';
                if ($referer && preg_match('#/s/([a-zA-Z0-9_-]+)#', $referer, $refMatch)) {
                    $refSlug = $refMatch[1];
                    self::serveSiteFile($refSlug, $path);
                    return;
                }
            }
        }

        // 1. Serving Hosted Static Sites: /s/{slug} or /s/{slug}/{filepath}
        if (!empty($parts) && $parts[0] === 's') {
            $slug = $parts[1] ?? null;
            if (!$slug) {
                http_response_code(404);
                echo "Site slug missing";
                return;
            }

            // Layer 3 Safety Net: If a stray relative request arrives at /s/{asset} or /s/{subpath}
            $slugExt = strtolower(pathinfo($slug, PATHINFO_EXTENSION));
            if ($slugExt && in_array($slugExt, ['css', 'js', 'png', 'jpg', 'jpeg', 'svg', 'webp', 'gif', 'ico', 'woff', 'woff2', 'ttf', 'json', 'map'], true)) {
                $referer = $_SERVER['HTTP_REFERER'] ?? '';
                if ($referer && preg_match('#/s/([a-zA-Z0-9_-]+)#', $referer, $refMatch)) {
                    $refSlug = $refMatch[1];
                    $strayPath = implode('/', array_slice($parts, 1));
                    self::serveSiteFile($refSlug, $strayPath);
                    return;
                }
            }

            $subpath = implode('/', array_slice($parts, 2));

            // Layer 1: Canonical trailing slash redirect for site root URLs in browsers
            $pathOnly = parse_url($uri, PHP_URL_PATH) ?? '';
            if ($subpath === '' && !str_ends_with($pathOnly, '/')) {
                $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
                if (str_contains($accept, 'text/html') && empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                    $qs = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
                    header('Location: ' . url('s/' . $slug . '/') . $qs, true, 301);
                    exit;
                }
            }

            self::serveSiteFile($slug, $subpath);
            return;
        }

        // 2a. CORS Pre-Flight for Forms & API
        if (($path === '__pd/form' || $path === '__hh/form' || (!empty($parts) && ($parts[0] === 'f' || $parts[0] === 'api'))) && $method === 'OPTIONS') {
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Accept, Authorization, X-Requested-With');
            header('Access-Control-Max-Age: 86400');
            exit;
        }

        // 2a-1. Clean Form Endpoint: POST /f/{slug}
        if (!empty($parts) && $parts[0] === 'f' && isset($parts[1]) && $method === 'POST') {
            $slug = $parts[1];
            $data = $_POST;
            if (empty($data)) {
                $raw = file_get_contents('php://input');
                $data = json_decode($raw, true) ?: [];
            }

            $res = Forms::submit($slug, $data);
            $statusCode = $res['status_code'] ?? ($res['success'] ? 200 : 400);

            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Headers: Content-Type, Accept');

            if (isset($_POST['_redirect']) && !empty($res['redirect'])) {
                redirect($res['redirect']);
                return;
            }

            $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
            $isJson = str_contains($accept, 'application/json') || (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
            if ($isJson || empty($_POST)) {
                json_response($res, $statusCode);
                return;
            }

            // Standard HTML fallback page
            if ($res['success']) {
                echo "<!DOCTYPE html><html lang='en'><head><meta charset='UTF-8'><title>Message Sent — PageDock</title><style>body{background:#0d0f12;color:#fff;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;} .box{background:#15181e;border:1px solid #232730;padding:40px;border-radius:12px;text-align:center;max-width:440px;} h1{color:#C7F547;font-size:22px;margin-bottom:12px;} p{color:#8b949e;font-size:15px;} a{color:#C7F547;text-decoration:none;font-weight:600;display:inline-block;margin-top:20px;}</style></head><body><div class='box'><h1>" . htmlspecialchars($res['message']) . "</h1><p>Your response has been securely recorded.</p><p><a href='javascript:history.back()'>&larr; Return to website</a></p></div></body></html>";
                return;
            }
            http_response_code($statusCode);
            echo "<!DOCTYPE html><html lang='en'><head><meta charset='UTF-8'><title>Submission Error — PageDock</title><style>body{background:#0d0f12;color:#fff;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;} .box{background:#15181e;border:1px solid #e5484d;padding:40px;border-radius:12px;text-align:center;max-width:440px;} h1{color:#e5484d;font-size:22px;margin-bottom:12px;} p{color:#8b949e;font-size:15px;} a{color:#e5484d;text-decoration:none;font-weight:600;display:inline-block;margin-top:20px;}</style></head><body><div class='box'><h1>Submission Error</h1><p>" . htmlspecialchars($res['error']) . "</p><p><a href='javascript:history.back()'>&larr; Go back and correct</a></p></div></body></html>";
            return;
        }

        // 2a-2. Universal Form Endpoint: POST /__pd/form or /__hh/form (HTMLHost legacy alias)
        if (($path === '__pd/form' || $path === '__hh/form') && $method === 'POST') {
            header('Access-Control-Allow-Origin: *');
            self::handleUniversalForm();
            return;
        }

        // 2a-3. Form Endpoint Developer Guide & Status: GET /__pd/form, /__hh/form, /f, or /f/{slug}
        if (($path === '__pd/form' || $path === '__hh/form' || $path === 'f' || (!empty($parts) && $parts[0] === 'f')) && $method === 'GET') {
            header('Access-Control-Allow-Origin: *');
            $targetSlug = (!empty($parts) && $parts[0] === 'f' && isset($parts[1])) ? $parts[1] : null;
            self::renderFormEndpointGuide($path, $targetSlug);
            return;
        }

        // 2a-2. Cookieless Edge Analytics Beacon: POST /__pd/v or /__hh/v
        if (($path === '__pd/v' || $path === '__hh/v') && $method === 'POST') {
            $raw = file_get_contents('php://input');
            $payload = json_decode($raw, true) ?: $_POST;
            $res = Analytics::recordView($payload, $_SERVER);
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Headers: Content-Type');
            json_response($res, $res['ok'] ? 200 : 400);
            return;
        }

        // 2a-3. Site Analytics JSON API: GET /api/sites/{slug}/analytics
        if (!empty($parts) && $parts[0] === 'api' && ($parts[1] ?? '') === 'sites' && ($parts[3] ?? '') === 'analytics') {
            $slug = $parts[2] ?? '';
            $site = SiteManager::getSite($slug);
            if (!$site) {
                json_response(['error' => 'Site not found'], 404);
                return;
            }
            if (!SiteManager::canManage($site)) {
                json_response(['error' => 'Unauthorized'], 403);
                return;
            }
            $days = isset($_GET['days']) ? (int)$_GET['days'] : 30;
            $data = Analytics::getSiteAnalytics((int)$site['id'], $days);
            header('Access-Control-Allow-Origin: *');
            json_response(['success' => true, 'site' => $site['slug'], 'analytics' => $data]);
            return;
        }

        // 2b-0. Form Submissions JSON API: GET /api/sites/{slug}/forms
        if (!empty($parts) && $parts[0] === 'api' && ($parts[1] ?? '') === 'sites' && ($parts[3] ?? '') === 'forms' && !isset($parts[4]) && $method === 'GET') {
            $slug = $parts[2] ?? '';
            $site = SiteManager::getSite($slug);
            if (!$site) {
                json_response(['error' => 'Site not found'], 404);
                return;
            }
            if (!SiteManager::canManage($site)) {
                json_response(['error' => 'Unauthorized'], 403);
                return;
            }
            $isSpam = null;
            if (isset($_GET['is_spam'])) {
                $isSpam = filter_var($_GET['is_spam'], FILTER_VALIDATE_BOOLEAN);
            } elseif (isset($_GET['spam'])) {
                $isSpam = filter_var($_GET['spam'], FILTER_VALIDATE_BOOLEAN);
            }
            $submissions = Forms::getSubmissions((int)$site['id'], $isSpam);
            $stats = Forms::getSubmissionStats((int)$site['id']);
            header('Access-Control-Allow-Origin: *');
            json_response([
                'success' => true,
                'site' => $site['slug'],
                'count' => count($submissions),
                'stats' => $stats,
                'submissions' => $submissions
            ]);
            return;
        }

        // 2b. Form Submissions CSV Export: GET /api/sites/{slug}/forms/export
        if (!empty($parts) && $parts[0] === 'api' && ($parts[1] ?? '') === 'sites' && ($parts[3] ?? '') === 'forms' && ($parts[4] ?? '') === 'export') {
            $slug = $parts[2] ?? '';
            $site = SiteManager::getSite($slug);
            if (!$site) {
                json_response(['error' => 'Site not found'], 404);
            }
            $includeSpam = isset($_GET['spam']) && $_GET['spam'] === '1';
            $csv = Forms::exportCsv((int)$site['id'], $includeSpam);
            header('Access-Control-Allow-Origin: *');
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="form-submissions-' . $slug . '.csv"');
            echo $csv;
            return;
        }

        // 2c. Delete Form Submission: POST /api/forms/{id}/delete
        if (!empty($parts) && $parts[0] === 'api' && ($parts[1] ?? '') === 'forms' && ($parts[3] ?? '') === 'delete' && $method === 'POST') {
            $id = (int)($parts[2] ?? 0);
            $db = Database::getConnection();
            $stmt = $db->prepare("SELECT s.* FROM form_submissions f JOIN sites s ON f.site_id = s.id WHERE f.id = ?");
            $stmt->execute([$id]);
            $site = $stmt->fetch();
            if (!$site) {
                json_response(['success' => false, 'error' => 'Submission not found'], 404);
                return;
            }
            if (!SiteManager::canManage($site)) {
                json_response(['success' => false, 'error' => 'Unauthorized'], 403);
                return;
            }
            $res = Forms::deleteSubmission($id, (int)$site['id']);
            json_response(['success' => (bool)$res]);
            return;
        }

        // 2d. Toggle Form Spam Status: POST /api/forms/{id}/toggle-spam
        if (!empty($parts) && $parts[0] === 'api' && ($parts[1] ?? '') === 'forms' && ($parts[3] ?? '') === 'toggle-spam' && $method === 'POST') {
            $id = (int)($parts[2] ?? 0);
            $db = Database::getConnection();
            $stmt = $db->prepare("SELECT s.* FROM form_submissions f JOIN sites s ON f.site_id = s.id WHERE f.id = ?");
            $stmt->execute([$id]);
            $site = $stmt->fetch();
            if (!$site) {
                json_response(['success' => false, 'error' => 'Submission not found'], 404);
                return;
            }
            if (!SiteManager::canManage($site)) {
                json_response(['success' => false, 'error' => 'Unauthorized'], 403);
                return;
            }
            $res = Forms::toggleSpam($id, (int)$site['id']);
            json_response(['success' => (bool)$res]);
            return;
        }

        // 2e. Legacy Form submission endpoint: /api/forms/{slug}
        if (!empty($parts) && $parts[0] === 'api' && ($parts[1] ?? '') === 'forms') {
            $slug = $parts[2] ?? null;
            if (!$slug || $method !== 'POST') {
                json_response(['success' => false, 'error' => 'Invalid form submission request'], 400);
            }

            $data = $_POST;
            if (empty($data)) {
                $raw = file_get_contents('php://input');
                $data = json_decode($raw, true) ?: [];
            }

            $res = Forms::submit($slug, $data);
            $statusCode = $res['status_code'] ?? ($res['success'] ? 200 : 400);
            
            // If submitted from standard HTML form with redirect
            if (isset($_POST['_redirect']) && !empty($res['redirect'])) {
                redirect($res['redirect']);
            }

            json_response($res, $statusCode);
            return;
        }

        // 2e-admin. Platform Administration API: /api/admin/*
        if (!empty($parts) && $parts[0] === 'api' && ($parts[1] ?? '') === 'admin') {
            if (!Auth::isAdmin()) {
                json_response(['success' => false, 'error' => 'Administrator access required'], 403);
                return;
            }

            $adminAction = $parts[2] ?? '';

            // POST /api/admin/sites/{slug}/toggle-status
            if ($adminAction === 'sites' && ($parts[4] ?? '') === 'toggle-status' && $method === 'POST') {
                $slug = $parts[3] ?? '';
                $res = Admin::toggleSiteSuspension($slug);
                json_response($res, $res['success'] ? 200 : 400);
                return;
            }

            // POST /api/admin/sites/{slug}/delete
            if ($adminAction === 'sites' && ($parts[4] ?? '') === 'delete' && $method === 'POST') {
                $slug = $parts[3] ?? '';
                $res = Admin::deleteSite($slug);
                json_response($res, $res['success'] ? 200 : 400);
                return;
            }

            // POST /api/admin/users/{id}/toggle-role
            if ($adminAction === 'users' && ($parts[4] ?? '') === 'toggle-role' && $method === 'POST') {
                $userId = (int)($parts[3] ?? 0);
                $res = Admin::toggleUserRole($userId);
                json_response($res, $res['success'] ? 200 : 400);
                return;
            }

            // POST /api/admin/maintenance/sync-db
            if ($adminAction === 'maintenance' && ($parts[3] ?? '') === 'sync-db' && $method === 'POST') {
                $ok = Database::syncDatabaseToR2();
                json_response(['success' => $ok, 'message' => $ok ? 'Database backup synced to Cloudflare R2' : 'Storage unconfigured or backup failed']);
                return;
            }

            // POST /api/admin/maintenance/cleanup-expired
            if ($adminAction === 'maintenance' && ($parts[3] ?? '') === 'cleanup-expired' && $method === 'POST') {
                $res = Admin::cleanupExpiredSites();
                json_response($res);
                return;
            }

            json_response(['error' => 'Unknown admin endpoint'], 404);
            return;
        }

        // 2f. Surge / Redoost Unified cURL & Agent Deploy Endpoint: POST/PUT /api/deploy
        if ($path === 'api/deploy' && ($method === 'POST' || $method === 'PUT')) {
            self::handleApiDeploy();
            return;
        }

        // 3. API Upload: POST /api/upload
        if ($path === 'api/upload' && $method === 'POST') {
            self::handleApiUpload();
            return;
        }

        // 4. API Paste: POST /api/paste
        if ($path === 'api/paste' && $method === 'POST') {
            $raw = file_get_contents('php://input');
            $data = json_decode($raw, true) ?: $_POST;
            $html = $data['html'] ?? '';
            $title = $data['title'] ?? null;
            $slug = $data['slug'] ?? null;
            $res = SiteManager::createFromHtml($html, $title, $slug);
            json_response($res, $res['success'] ? 200 : 400);
            return;
        }

        // 5. API Update Site HTML / Files: POST /api/editor/{slug}
        if (!empty($parts) && $parts[0] === 'api' && ($parts[1] ?? '') === 'editor') {
            $slug = $parts[2] ?? null;
            $raw = file_get_contents('php://input');
            $data = json_decode($raw, true) ?: $_POST;

            if (isset($data['files']) && is_array($data['files'])) {
                $res = SiteManager::updateSiteFiles($slug, $data['files'], $data['summary'] ?? null);
            } else {
                $html = $data['html'] ?? '';
                $file = $data['file'] ?? $_GET['file'] ?? null;
                $res = SiteManager::updateSiteHtml($slug, $html, $file);
            }
            json_response($res, $res['success'] ? 200 : 400);
            return;
        }

        // 5b. API Tokens (Personal Access Tokens for ChatGPT Actions / API / CLI)
        if (!empty($parts) && $parts[0] === 'api' && ($parts[1] ?? '') === 'tokens') {
            $userId = Auth::id();
            if (!$userId) {
                json_response(['success' => false, 'error' => 'Unauthorized'], 401);
                return;
            }
            if (count($parts) === 2 && $method === 'GET') {
                json_response(['success' => true, 'tokens' => Auth::listApiTokens($userId)]);
                return;
            }
            if (count($parts) === 2 && $method === 'POST') {
                $raw = file_get_contents('php://input');
                $data = json_decode($raw ?: '', true) ?: $_POST;
                $name = trim((string)($data['name'] ?? 'ChatGPT / API Key'));
                $created = Auth::createApiToken($userId, $name);
                json_response(['success' => true, 'token' => $created], 201);
                return;
            }
            if (count($parts) === 4 && ($parts[3] ?? '') === 'revoke' && $method === 'POST') {
                $tokenId = (int)$parts[2];
                $revoked = Auth::revokeApiToken($userId, $tokenId);
                json_response(['success' => $revoked], $revoked ? 200 : 404);
                return;
            }
        }

        // 5c. List Sites / Get Site Metadata: GET /api/sites and GET /api/sites/{slug}
        if ($path === 'api/sites' && $method === 'GET') {
            $slugParam = isset($_GET['slug']) ? trim((string)$_GET['slug']) : '';
            if ($slugParam !== '') {
                $site = SiteManager::getSite($slugParam);
                if (!$site) {
                    json_response(['success' => false, 'error' => 'Site not found'], 404);
                    return;
                }
                if (!SiteManager::canManage($site)) {
                    json_response(['success' => false, 'error' => 'Unauthorized'], 403);
                    return;
                }
                $site['liveUrl'] = url('s/' . $site['slug'] . '/');
                $site['editorUrl'] = url('editor/' . $site['slug']);
                $site['files'] = SiteManager::getSiteFileList($site['slug']);
                json_response(['success' => true, 'site' => $site]);
                return;
            }

            if (!Auth::check() && empty(Auth::getActiveGuestTokens())) {
                json_response(['success' => false, 'error' => 'Unauthorized'], 401);
                return;
            }
            $sites = SiteManager::getUserSites();
            foreach ($sites as &$s) {
                $s['liveUrl'] = url('s/' . $s['slug'] . '/');
                $s['editorUrl'] = url('editor/' . $s['slug']);
            }
            unset($s);
            json_response(['success' => true, 'count' => count($sites), 'sites' => $sites]);
            return;
        }

        if (!empty($parts) && $parts[0] === 'api' && ($parts[1] ?? '') === 'sites' && count($parts) === 3 && $method === 'GET') {
            $slug = $parts[2] ?? '';
            $site = SiteManager::getSite($slug);
            if (!$site) {
                json_response(['success' => false, 'error' => 'Site not found'], 404);
                return;
            }
            if (!SiteManager::canManage($site)) {
                json_response(['success' => false, 'error' => 'Unauthorized'], 403);
                return;
            }
            $site['liveUrl'] = url('s/' . $site['slug'] . '/');
            $site['editorUrl'] = url('editor/' . $site['slug']);
            $site['files'] = SiteManager::getSiteFileList($site['slug']);
            json_response(['success' => true, 'site' => $site]);
            return;
        }

        // 6. API Delete Site: POST /api/sites/{slug}/delete
        if (!empty($parts) && $parts[0] === 'api' && ($parts[1] ?? '') === 'sites' && ($parts[3] ?? '') === 'delete') {
            $slug = $parts[2] ?? null;
            $res = SiteManager::deleteSite($slug);
            json_response($res, $res['success'] ? 200 : 400);
            return;
        }

        // 6c-1. List Site Versions: GET /api/sites/{slug}/versions
        if (!empty($parts) && $parts[0] === 'api' && ($parts[1] ?? '') === 'sites' && ($parts[3] ?? '') === 'versions' && count($parts) === 4 && $method === 'GET') {
            $slug = $parts[2] ?? '';
            $site = SiteManager::getSite($slug);
            if (!$site) {
                json_response(['success' => false, 'error' => 'Site not found'], 404);
                return;
            }
            if (!SiteManager::canManage($site)) {
                json_response(['success' => false, 'error' => 'Unauthorized'], 403);
                return;
            }
            $versions = VersionManager::getVersions((int)$site['id']);
            json_response([
                'success' => true,
                'slug' => $site['slug'],
                'current_version' => (int)($site['current_version'] ?? 1),
                'versions' => $versions
            ]);
            return;
        }

        // 6c-2. Restore Specific Version: POST /api/sites/{slug}/versions/{n}/restore
        if (!empty($parts) && $parts[0] === 'api' && ($parts[1] ?? '') === 'sites' && ($parts[3] ?? '') === 'versions' && ($parts[5] ?? '') === 'restore' && $method === 'POST') {
            $slug = $parts[2] ?? '';
            $versionNum = (int)($parts[4] ?? 0);
            $site = SiteManager::getSite($slug);
            if (!$site) {
                json_response(['success' => false, 'error' => 'Site not found'], 404);
                return;
            }
            if (!SiteManager::canManage($site)) {
                json_response(['success' => false, 'error' => 'Unauthorized'], 403);
                return;
            }
            $res = VersionManager::restoreVersion($site, $versionNum);
            json_response($res, $res['success'] ? 200 : 400);
            return;
        }

        // 6c-3. Get Specific File Content: GET /api/sites/{slug}/file?path=...
        if (!empty($parts) && $parts[0] === 'api' && ($parts[1] ?? '') === 'sites' && ($parts[3] ?? '') === 'file' && $method === 'GET') {
            $slug = $parts[2] ?? '';
            $site = SiteManager::getSite($slug);
            if (!$site || !SiteManager::canManage($site)) {
                json_response(['success' => false, 'error' => 'Unauthorized or site not found'], 403);
                return;
            }
            $relPath = $_GET['path'] ?? 'index.html';
            $content = SiteManager::getSiteHtml($slug, $relPath);
            if ($content === null) {
                json_response(['success' => false, 'error' => 'File not found'], 404);
                return;
            }
            json_response(['success' => true, 'path' => $relPath, 'content' => $content]);
            return;
        }

        // 6c-4. List All Site Files: GET /api/sites/{slug}/files
        if (!empty($parts) && $parts[0] === 'api' && ($parts[1] ?? '') === 'sites' && ($parts[3] ?? '') === 'files' && $method === 'GET') {
            $slug = $parts[2] ?? '';
            $site = SiteManager::getSite($slug);
            if (!$site || !SiteManager::canManage($site)) {
                json_response(['success' => false, 'error' => 'Unauthorized or site not found'], 403);
                return;
            }
            $files = SiteManager::getSiteFileList($slug);
            json_response(['success' => true, 'slug' => $slug, 'files' => $files]);
            return;
        }

        // 6c-3. 1-Click Rollback Endpoint: POST /api/sites/{slug}/rollback
        if (!empty($parts) && $parts[0] === 'api' && ($parts[1] ?? '') === 'sites' && ($parts[3] ?? '') === 'rollback' && $method === 'POST') {
            $slug = $parts[2] ?? '';
            $site = SiteManager::getSite($slug);
            if (!$site) {
                json_response(['success' => false, 'error' => 'Site not found'], 404);
                return;
            }
            if (!SiteManager::canManage($site)) {
                json_response(['success' => false, 'error' => 'Unauthorized'], 403);
                return;
            }

            $raw = file_get_contents('php://input');
            $data = json_decode($raw, true) ?: $_POST;
            $targetVersion = isset($data['version']) ? (int)$data['version'] : 0;

            if ($targetVersion <= 0) {
                $current = (int)($site['current_version'] ?? 1);
                $targetVersion = max(1, $current - 1);
            }

            $res = VersionManager::restoreVersion($site, $targetVersion);
            json_response($res, $res['success'] ? 200 : 400);
            return;
        }

        // 6c-4. Inspect Shared Blocks & Drift: GET /api/sites/{slug}/shared-blocks
        if (!empty($parts) && $parts[0] === 'api' && ($parts[1] ?? '') === 'sites' && ($parts[3] ?? '') === 'shared-blocks' && count($parts) === 4 && $method === 'GET') {
            $slug = $parts[2] ?? '';
            $site = SiteManager::getSite($slug);
            if (!$site) {
                json_response(['success' => false, 'error' => 'Site not found'], 404);
                return;
            }
            if (!SiteManager::canManage($site)) {
                json_response(['success' => false, 'error' => 'Unauthorized'], 403);
                return;
            }

            $res = SharedLayoutManager::inspectSite($slug);
            json_response($res, $res['success'] ? 200 : 400);
            return;
        }

        // 6c-5. Synchronize Shared Block: POST /api/sites/{slug}/sync-shared
        if (!empty($parts) && $parts[0] === 'api' && ($parts[1] ?? '') === 'sites' && ($parts[3] ?? '') === 'sync-shared' && $method === 'POST') {
            $slug = $parts[2] ?? '';
            $site = SiteManager::getSite($slug);
            if (!$site) {
                json_response(['success' => false, 'error' => 'Site not found'], 404);
                return;
            }
            if (!SiteManager::canManage($site)) {
                json_response(['success' => false, 'error' => 'Unauthorized'], 403);
                return;
            }

            $raw = file_get_contents('php://input');
            $data = json_decode($raw, true) ?: $_POST;

            $blockName = trim($data['block_name'] ?? '');
            if (!$blockName) {
                json_response(['success' => false, 'error' => 'block_name is required.'], 400);
                return;
            }

            $content = $data['content'] ?? null;
            $sourcePage = $data['source_page'] ?? null;
            $attributes = $data['attributes'] ?? [];

            // If content is not explicitly provided, extract from source_page or consensus
            if ($content === null) {
                $inspection = SharedLayoutManager::inspectSite($slug);
                if (!empty($inspection['blocks'][$blockName]['canonical_sample'])) {
                    $content = $inspection['blocks'][$blockName]['canonical_sample'];
                    if (empty($attributes) && !empty($inspection['blocks'][$blockName]['canonical_attributes'])) {
                        $attributes = $inspection['blocks'][$blockName]['canonical_attributes'];
                    }
                } else {
                    json_response(['success' => false, 'error' => "No template content found for block '{$blockName}'."], 400);
                    return;
                }
            }

            $res = SharedLayoutManager::syncBlock($slug, $blockName, $content, $sourcePage, $attributes);
            json_response($res, $res['success'] ? 200 : 400);
            return;
        }

        // 6c-6. Site Media API: GET /api/sites/{slug}/media
        if (!empty($parts) && $parts[0] === 'api' && ($parts[1] ?? '') === 'sites' && ($parts[3] ?? '') === 'media' && count($parts) === 4 && $method === 'GET') {
            $slug = $parts[2] ?? '';
            $site = SiteManager::getSite($slug);
            if (!$site) {
                json_response(['success' => false, 'error' => 'Site not found'], 404);
                return;
            }
            if (!SiteManager::canManage($site)) {
                json_response(['success' => false, 'error' => 'Unauthorized'], 403);
                return;
            }

            $siteDir = realpath(SITES_DIR . '/' . $slug);
            if (!$siteDir || !is_dir($siteDir)) {
                json_response(['success' => false, 'error' => 'Site directory not found'], 404);
                return;
            }

            $mediaExts = ['png', 'jpg', 'jpeg', 'webp', 'svg', 'gif', 'ico', 'mp4', 'webm', 'pdf'];
            $files = [];

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($siteDir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $item) {
                if ($item->isFile()) {
                    $relPath = ltrim(substr($item->getPathname(), strlen($siteDir)), '/\\');
                    $relPath = str_replace('\\', '/', $relPath);

                    if (str_starts_with($relPath, '.') || str_contains($relPath, '/.')) {
                        continue;
                    }

                    $ext = strtolower(pathinfo($relPath, PATHINFO_EXTENSION));
                    if (in_array($ext, $mediaExts, true)) {
                        $size = $item->getSize();
                        $formattedSize = $size >= 1048576 
                            ? round($size / 1048576, 1) . ' MB' 
                            : ($size >= 1024 ? round($size / 1024, 1) . ' KB' : $size . ' B');

                        $files[] = [
                            'name' => basename($relPath),
                            'relPath' => $relPath,
                            'url' => url('s/' . $slug . '/' . $relPath),
                            'size' => $size,
                            'formattedSize' => $formattedSize,
                            'isImage' => in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'svg', 'gif', 'ico'], true),
                            'updatedAt' => date('Y-m-d H:i:s', $item->getMTime())
                        ];
                    }
                }
            }

            usort($files, fn($a, $b) => strcmp($b['updatedAt'], $a['updatedAt']));

            json_response([
                'success' => true,
                'slug' => $slug,
                'files' => $files
            ]);
            return;
        }

        // 6c-7. Site Media Upload: POST /api/sites/{slug}/media
        if (!empty($parts) && $parts[0] === 'api' && ($parts[1] ?? '') === 'sites' && ($parts[3] ?? '') === 'media' && count($parts) === 4 && $method === 'POST') {
            $slug = $parts[2] ?? '';
            $site = SiteManager::getSite($slug);
            if (!$site) {
                json_response(['success' => false, 'error' => 'Site not found'], 404);
                return;
            }
            if (!SiteManager::canManage($site)) {
                json_response(['success' => false, 'error' => 'Unauthorized'], 403);
                return;
            }

            $siteDir = realpath(SITES_DIR . '/' . $slug);
            if (!$siteDir || !is_dir($siteDir)) {
                json_response(['success' => false, 'error' => 'Site directory not found'], 404);
                return;
            }

            $assetsDir = $siteDir . '/assets';
            if (!is_dir($assetsDir)) {
                mkdir($assetsDir, 0755, true);
            }

            $uploadedFile = $_FILES['file'] ?? null;
            if (!$uploadedFile || $uploadedFile['error'] !== UPLOAD_ERR_OK) {
                $errCode = $uploadedFile['error'] ?? null;
                $iniUploadMax = ini_get('upload_max_filesize') ?: '2M';
                $iniPostMax = ini_get('post_max_size') ?: '8M';

                if ($errCode === UPLOAD_ERR_INI_SIZE || $errCode === UPLOAD_ERR_FORM_SIZE) {
                    $errorMsg = "File size exceeds server upload limit (max {$iniUploadMax}).";
                } elseif ($errCode === UPLOAD_ERR_PARTIAL) {
                    $errorMsg = "File upload was only partially received.";
                } elseif ($errCode === UPLOAD_ERR_NO_FILE || empty($_FILES)) {
                    $errorMsg = "No file received. If the file is large, it may exceed server post_max_size ({$iniPostMax}).";
                } elseif ($errCode === UPLOAD_ERR_NO_TMP_DIR) {
                    $errorMsg = "Server temporary upload folder is missing.";
                } elseif ($errCode === UPLOAD_ERR_CANT_WRITE) {
                    $errorMsg = "Failed to write uploaded file to disk.";
                } else {
                    $errorMsg = "Upload error occurred (code {$errCode}).";
                }
                json_response(['success' => false, 'error' => $errorMsg], 400);
                return;
            }

            $origName = basename($uploadedFile['name']);
            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
            $allowedExts = ['png', 'jpg', 'jpeg', 'webp', 'svg', 'gif', 'ico', 'mp4', 'webm', 'pdf'];

            if (!in_array($ext, $allowedExts, true)) {
                json_response(['success' => false, 'error' => 'Unsupported file type. Allowed: ' . implode(', ', $allowedExts)], 400);
                return;
            }

            $rawName = pathinfo($origName, PATHINFO_FILENAME);
            $cleanName = preg_replace('/[^a-zA-Z0-9_-]/', '-', strtolower($rawName));
            $cleanName = trim($cleanName, '-');
            if (empty($cleanName)) $cleanName = 'asset_' . time();
            $targetFileName = $cleanName . '.' . $ext;
            $targetPath = $assetsDir . '/' . $targetFileName;

            $counter = 1;
            while (file_exists($targetPath)) {
                $targetFileName = $cleanName . '-' . $counter . '.' . $ext;
                $targetPath = $assetsDir . '/' . $targetFileName;
                $counter++;
            }

            if (!move_uploaded_file($uploadedFile['tmp_name'], $targetPath)) {
                json_response(['success' => false, 'error' => 'Failed to save uploaded file.'], 500);
                return;
            }

            $size = filesize($targetPath);
            $formattedSize = $size >= 1048576 
                ? round($size / 1048576, 1) . ' MB' 
                : ($size >= 1024 ? round($size / 1024, 1) . ' KB' : $size . ' B');

            $relPath = 'assets/' . $targetFileName;

            json_response([
                'success' => true,
                'file' => [
                    'name' => $targetFileName,
                    'relPath' => $relPath,
                    'url' => url('s/' . $slug . '/' . $relPath),
                    'size' => $size,
                    'formattedSize' => $formattedSize,
                    'isImage' => in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'svg', 'gif', 'ico'], true)
                ]
            ]);
            return;
        }

        // 6c-8. Site Media Delete: POST /api/sites/{slug}/media/delete
        if (!empty($parts) && $parts[0] === 'api' && ($parts[1] ?? '') === 'sites' && ($parts[3] ?? '') === 'media' && ($parts[4] ?? '') === 'delete' && $method === 'POST') {
            $slug = $parts[2] ?? '';
            $site = SiteManager::getSite($slug);
            if (!$site) {
                json_response(['success' => false, 'error' => 'Site not found'], 404);
                return;
            }
            if (!SiteManager::canManage($site)) {
                json_response(['success' => false, 'error' => 'Unauthorized'], 403);
                return;
            }

            $siteDir = realpath(SITES_DIR . '/' . $slug);
            $raw = file_get_contents('php://input');
            $data = json_decode($raw, true) ?: $_POST;
            $relPath = ltrim($data['relPath'] ?? $data['file'] ?? '', '/\\');

            if (empty($relPath)) {
                json_response(['success' => false, 'error' => 'File path required'], 400);
                return;
            }

            $targetPath = realpath($siteDir . '/' . $relPath);
            if (!$targetPath || !str_starts_with($targetPath, $siteDir) || !is_file($targetPath)) {
                json_response(['success' => false, 'error' => 'File not found or invalid path'], 404);
                return;
            }

            @unlink($targetPath);
            json_response(['success' => true, 'message' => 'File deleted']);
            return;
        }

        // 6c-9. Site Pages List: GET /api/sites/{slug}/pages
        if (!empty($parts) && $parts[0] === 'api' && ($parts[1] ?? '') === 'sites' && ($parts[3] ?? '') === 'pages' && count($parts) === 4 && $method === 'GET') {
            $slug = $parts[2] ?? '';
            $site = SiteManager::getSite($slug);
            if (!$site) {
                json_response(['success' => false, 'error' => 'Site not found'], 404);
                return;
            }
            if (!SiteManager::canManage($site)) {
                json_response(['success' => false, 'error' => 'Unauthorized'], 403);
                return;
            }

            $siteDir = realpath(SITES_DIR . '/' . $slug);
            if (!$siteDir || !is_dir($siteDir)) {
                json_response(['success' => false, 'error' => 'Site directory not found'], 404);
                return;
            }

            $allFiles = SharedLayoutManager::findAllHtmlFiles($slug);
            $entryFile = $site['entry_file'] ?: 'index.html';
            $pages = [];

            foreach ($allFiles as $f) {
                $fullPath = $siteDir . '/' . $f;
                $size = file_exists($fullPath) ? filesize($fullPath) : 0;
                $formattedSize = $size >= 1048576 
                    ? round($size / 1048576, 1) . ' MB' 
                    : ($size >= 1024 ? round($size / 1024, 1) . ' KB' : $size . ' B');

                $pageTitle = ucfirst(preg_replace('/\.html?$/i', '', basename($f)));
                if (file_exists($fullPath)) {
                    $content = @file_get_contents($fullPath);
                    if ($content && preg_match('/<title\b[^>]*>(.*?)<\/title>/is', $content, $tMatch)) {
                        $rawT = trim(strip_tags($tMatch[1]));
                        if ($rawT) $pageTitle = $rawT;
                    }
                }

                $cleanRoute = preg_replace('/\.html?$/i', '', $f);
                $cleanRoute = ($cleanRoute === 'index') ? '' : $cleanRoute;

                $pages[] = [
                    'file' => $f,
                    'relPath' => $f,
                    'title' => $pageTitle,
                    'url' => url('s/' . $slug . ($cleanRoute ? '/' . $cleanRoute : '')),
                    'size' => $size,
                    'formattedSize' => $formattedSize,
                    'isEntry' => ($f === $entryFile || ($f === 'index.html' && !$site['entry_file']))
                ];
            }

            json_response(['success' => true, 'pages' => $pages]);
            return;
        }

        // 6c-10. Site Page Create: POST /api/sites/{slug}/pages
        if (!empty($parts) && $parts[0] === 'api' && ($parts[1] ?? '') === 'sites' && ($parts[3] ?? '') === 'pages' && count($parts) === 4 && $method === 'POST') {
            $slug = $parts[2] ?? '';
            $site = SiteManager::getSite($slug);
            if (!$site) {
                json_response(['success' => false, 'error' => 'Site not found'], 404);
                return;
            }
            if (!SiteManager::canManage($site)) {
                json_response(['success' => false, 'error' => 'Unauthorized'], 403);
                return;
            }

            $raw = file_get_contents('php://input');
            $data = json_decode($raw, true) ?: $_POST;

            $title = trim($data['title'] ?? '');
            $filename = trim($data['filename'] ?? $data['file'] ?? '');
            $layout = trim($data['layout'] ?? 'inherit');
            $sourceFile = trim($data['sourceFile'] ?? $data['source_file'] ?? 'index.html');

            if (empty($filename) && !empty($title)) {
                $filename = preg_replace('/[^a-zA-Z0-9_-]/', '-', strtolower($title)) . '.html';
            }

            if (empty($filename)) {
                json_response(['success' => false, 'error' => 'Filename or title required.'], 400);
                return;
            }

            $res = SiteManager::createPage($slug, $filename, $title, $layout, $sourceFile);
            json_response($res, $res['success'] ? 200 : 400);
            return;
        }

        // 6c-11. Site Page Delete: POST /api/sites/{slug}/pages/delete
        if (!empty($parts) && $parts[0] === 'api' && ($parts[1] ?? '') === 'sites' && ($parts[3] ?? '') === 'pages' && ($parts[4] ?? '') === 'delete' && $method === 'POST') {
            $slug = $parts[2] ?? '';
            $site = SiteManager::getSite($slug);
            if (!$site) {
                json_response(['success' => false, 'error' => 'Site not found'], 404);
                return;
            }
            if (!SiteManager::canManage($site)) {
                json_response(['success' => false, 'error' => 'Unauthorized'], 403);
                return;
            }

            $siteDir = realpath(SITES_DIR . '/' . $slug);
            $raw = file_get_contents('php://input');
            $data = json_decode($raw, true) ?: $_POST;
            $relPath = ltrim($data['file'] ?? $data['relPath'] ?? '', '/\\');

            if (empty($relPath)) {
                json_response(['success' => false, 'error' => 'File path required'], 400);
                return;
            }

            $entryFile = $site['entry_file'] ?: 'index.html';
            if ($relPath === $entryFile || $relPath === 'index.html') {
                json_response(['success' => false, 'error' => 'Cannot delete the entry page of the site.'], 400);
                return;
            }

            $targetPath = realpath($siteDir . '/' . $relPath);
            if (!$targetPath || !str_starts_with($targetPath, $siteDir) || !is_file($targetPath)) {
                json_response(['success' => false, 'error' => 'File not found or invalid path'], 404);
                return;
            }

            @unlink($targetPath);
            json_response(['success' => true, 'message' => 'Page deleted']);
            return;
        }

        // 6b. API Clone Template to New Site: POST /api/templates/{slug}/use
        if (!empty($parts) && $parts[0] === 'api' && ($parts[1] ?? '') === 'templates' && ($parts[3] ?? '') === 'use' && $method === 'POST') {
            $templateSlug = $parts[2] ?? '';
            $template = Templates::get($templateSlug);
            if (!$template) {
                json_response(['success' => false, 'error' => 'Template not found'], 404);
            }

            $preferredSlug = $_POST['slug'] ?? null;
            $res = SiteManager::createFromHtml($template['html'], $template['title'], $preferredSlug);
            json_response($res, $res['success'] ? 200 : 400);
            return;
        }

        // 6c. Template Live Preview: GET /templates/{slug}/preview
        if (!empty($parts) && $parts[0] === 'templates' && ($parts[2] ?? '') === 'preview') {
            $templateSlug = $parts[1] ?? '';
            $template = Templates::get($templateSlug);
            if (!$template) {
                http_response_code(404);
                echo "Template not found";
                return;
            }
            header('Content-Type: text/html; charset=utf-8');
            echo $template['html'];
            return;
        }

        // 6d. Templates Gallery Page: GET /templates
        if ($path === 'templates') {
            $allTemplates = Templates::getAll();
            $categories = Templates::getCategories();
            render_view('templates', [
                'templates' => $allTemplates,
                'categories' => $categories,
                'title' => 'HTML Website Templates — PageDock'
            ]);
            return;
        }

        // 7. Studio & Responsive Canvas: /editor/{slug}
        if (!empty($parts) && ($parts[0] === 'editor' || $parts[0] === 'studio')) {
            $slug = $parts[1] ?? '';
            if (!$slug) {
                redirect('dashboard');
                return;
            }
            $site = SiteManager::getSite($slug);
            if (!$site) {
                render_view('404', ['title' => 'Site Not Found']);
                return;
            }
            if (!SiteManager::canManage($site)) {
                redirect('login?redirect=editor/' . $slug);
                return;
            }
            $files = SiteManager::getSiteFileList($slug);
            $initialFile = $files[0] ?? 'index.html';
            $initialContent = SiteManager::getSiteHtml($slug, $initialFile) ?? '';
            render_view('editor', [
                'site' => $site,
                'files' => $files,
                'initialFile' => $initialFile,
                'initialContent' => $initialContent,
                'title' => 'Studio — ' . ($site['title'] ?: $site['slug'])
            ], false);
            return;
        }

        // 8. Dashboard (Authentication Required)
        if ($path === 'dashboard') {
            if (!Auth::hasSession()) {
                redirect('login?redirect=dashboard');
                return;
            }

            $sites = SiteManager::getUserSites();
            $apiTokens = Auth::listApiTokens((int)Auth::id());
            render_view('dashboard', [
                'sites' => $sites,
                'apiTokens' => $apiTokens,
                'user' => Auth::user(),
                'title' => 'Dashboard — PageDock'
            ]);
            return;
        }

        // 8b. Dashboard Forms Inbox: /dashboard/forms/{slug}
        if (!empty($parts) && $parts[0] === 'dashboard' && ($parts[1] ?? '') === 'forms') {
            $slug = $parts[2] ?? null;
            $site = $slug ? SiteManager::getSite($slug) : null;
            if (!$site) {
                render_view('404', ['title' => 'Site Not Found']);
                return;
            }

            if (!Auth::check() && !isset($_GET['token']) && empty(Auth::getActiveGuestTokens())) {
                redirect('login?redirect=' . urlencode('dashboard/forms/' . $slug));
                return;
            }

            if (!SiteManager::canManage($site)) {
                http_response_code(403);
                render_view('404', ['title' => 'Forbidden — Unauthorized']);
                return;
            }

            $showSpam = isset($_GET['spam']) && $_GET['spam'] === '1';
            $submissions = Forms::getSubmissions((int)$site['id'], $showSpam);
            $stats = Forms::getSubmissionStats((int)$site['id']);

            render_view('forms_inbox', [
                'site' => $site,
                'submissions' => $submissions,
                'stats' => $stats,
                'showSpam' => $showSpam,
                'title' => 'Forms Inbox — ' . htmlspecialchars($site['title'] ?: $site['slug'])
            ]);
            return;
        }

        // 8c. Dashboard Site Analytics: /dashboard/analytics/{slug}
        if (!empty($parts) && $parts[0] === 'dashboard' && ($parts[1] ?? '') === 'analytics') {
            $slug = $parts[2] ?? null;
            $site = $slug ? SiteManager::getSite($slug) : null;
            if (!$site) {
                render_view('404', ['title' => 'Site Not Found']);
                return;
            }

            if (!Auth::check() && !isset($_GET['token']) && empty(Auth::getActiveGuestTokens())) {
                redirect('login?redirect=' . urlencode('dashboard/analytics/' . $slug));
                return;
            }

            if (!SiteManager::canManage($site)) {
                http_response_code(403);
                render_view('404', ['title' => 'Forbidden — Unauthorized']);
                return;
            }

            $days = isset($_GET['days']) ? (int)$_GET['days'] : 30;
            $analytics = Analytics::getSiteAnalytics((int)$site['id'], $days);

            render_view('analytics', [
                'site' => $site,
                'analytics' => $analytics,
                'days' => $days,
                'title' => 'Analytics — ' . htmlspecialchars($site['title'] ?: $site['slug'])
            ]);
            return;
        }

        // 8c. Claim Site Endpoint: /claim?token=...
        if ($path === 'claim' || (!empty($parts) && $parts[0] === 'claim')) {
            $token = $_REQUEST['token'] ?? null;
            if (!$token) {
                redirect('dashboard');
                return;
            }
            if (!Auth::check()) {
                redirect('login?redirect=' . urlencode('claim?token=' . $token));
                return;
            }
            $res = SiteManager::claimSite($token, Auth::id());
            if ($res['success']) {
                redirect('dashboard?claimed=' . urlencode($res['slug']));
            } else {
                redirect('dashboard?error=' . urlencode($res['error']));
            }
            return;
        }

        // 8d. Admin Platform Console: /admin
        if ($path === 'admin') {
            Auth::requireAdmin();

            $stats = Admin::getPlatformStats();
            $sites = Admin::getAllSites('', '', 100);
            $users = Admin::getAllUsers('', 100);
            $activity = Admin::getRecentActivity(25);

            render_view('admin', [
                'stats' => $stats,
                'sites' => $sites,
                'users' => $users,
                'activity' => $activity,
                'user' => Auth::user(),
                'title' => 'Admin Console — PageDock'
            ]);
            return;
        }

        // 9. Auth Routes
        if ($path === 'login') {
            if (Auth::hasSession()) {
                redirect('dashboard');
                return;
            }

            $redirectTarget = $_REQUEST['redirect'] ?? 'dashboard';

            if ($method === 'POST') {
                $email = $_POST['email'] ?? '';
                $password = $_POST['password'] ?? '';
                $res = Auth::login($email, $password);
                if ($res['success']) {
                    $rawDest = (string)($_POST['redirect'] ?? '');
                    $dest = (!empty($rawDest) && !str_starts_with($rawDest, 'http') && !str_starts_with($rawDest, '//')) ? ltrim($rawDest, '/') : 'dashboard';
                    redirect($dest);
                } else {
                    render_view('login', [
                        'error' => $res['error'],
                        'redirect' => $redirectTarget,
                        'title' => 'Sign In — PageDock'
                    ]);
                }
                return;
            }
            render_view('login', [
                'redirect' => $redirectTarget,
                'title' => 'Sign In — PageDock'
            ]);
            return;
        }

        if ($path === 'register') {
            if (Auth::hasSession()) {
                redirect('dashboard');
                return;
            }

            $redirectTarget = $_REQUEST['redirect'] ?? 'dashboard';

            if ($method === 'POST') {
                $email = $_POST['email'] ?? '';
                $password = $_POST['password'] ?? '';
                $name = $_POST['name'] ?? '';
                $res = Auth::register($email, $password, $name);
                if ($res['success']) {
                    $rawDest = (string)($_POST['redirect'] ?? '');
                    $dest = (!empty($rawDest) && !str_starts_with($rawDest, 'http') && !str_starts_with($rawDest, '//')) ? ltrim($rawDest, '/') : 'dashboard';
                    redirect($dest);
                } else {
                    render_view('register', [
                        'error' => $res['error'],
                        'redirect' => $redirectTarget,
                        'title' => 'Create Account — PageDock'
                    ]);
                }
                return;
            }
            render_view('register', [
                'redirect' => $redirectTarget,
                'title' => 'Create Account — PageDock'
            ]);
            return;
        }

        if ($path === 'logout') {
            Auth::logout();
            redirect('');
            return;
        }

        // 10. Documentation
        if ($path === 'docs') {
            render_view('docs', ['title' => 'Documentation — PageDock']);
            return;
        }

        // 11. Home page / Default
        if ($path === '') {
            render_view('home', [
                'title' => 'PageDock — Put your business online in seconds',
                'bodyClass' => 'page-home'
            ]);
            return;
        }

        // 404 Fallback
        http_response_code(404);
        render_view('404', ['title' => 'Page Not Found']);
    }

    /**
     * Handle HTMLHost Universal Form Submissions: POST /__pd/form
     */
    private static function handleUniversalForm(): void {
        $data = $_POST;
        if (empty($data)) {
            $raw = file_get_contents('php://input');
            $data = json_decode($raw, true) ?: [];
        }

        $res = Forms::handleUniversalSubmit($data);
        $statusCode = $res['status_code'] ?? ($res['success'] ? 200 : 400);

        // Check if request expects JSON (Fetch, XHR, or client form script)
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $isJson = str_contains($accept, 'application/json') || (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

        if ($isJson) {
            json_response($res, $statusCode);
            return;
        }

        // Standard HTML Form submission (No-JS fallback)
        if ($res['success']) {
            if (!empty($res['redirect'])) {
                // If redirect is provided, resolve against site referrer or path
                $referer = $_SERVER['HTTP_REFERER'] ?? '';
                if ($referer) {
                    $base = preg_replace('#(/s/[^/]+).*$#', '$1', $referer);
                    header('Location: ' . rtrim($base, '/') . '/' . ltrim($res['redirect'], '/'));
                    exit;
                }
                redirect($res['redirect']);
                return;
            }
            // Clean confirmation page
            echo "<!DOCTYPE html><html lang='en'><head><meta charset='UTF-8'><title>Message Sent — PageDock</title><style>body{background:#0d0f12;color:#fff;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;} .box{background:#15181e;border:1px solid #232730;padding:40px;border-radius:12px;text-align:center;max-width:440px;} h1{color:#C7F547;font-size:22px;margin-bottom:12px;} p{color:#8b949e;font-size:15px;} a{color:#C7F547;text-decoration:none;font-weight:600;display:inline-block;margin-top:20px;}</style></head><body><div class='box'><h1>" . htmlspecialchars($res['message']) . "</h1><p>Your response has been securely recorded.</p><p><a href='javascript:history.back()'>&larr; Return to website</a></p></div></body></html>";
            return;
        }

        // Error response for standard HTML submit
        http_response_code($statusCode);
        echo "<!DOCTYPE html><html lang='en'><head><meta charset='UTF-8'><title>Submission Error — PageDock</title><style>body{background:#0d0f12;color:#fff;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;} .box{background:#15181e;border:1px solid #e5484d;padding:40px;border-radius:12px;text-align:center;max-width:440px;} h1{color:#e5484d;font-size:22px;margin-bottom:12px;} p{color:#8b949e;font-size:15px;} a{color:#e5484d;text-decoration:none;font-weight:600;display:inline-block;margin-top:20px;}</style></head><body><div class='box'><h1>Submission Error</h1><p>" . htmlspecialchars($res['error']) . "</p><p><a href='javascript:history.back()'>&larr; Go back and correct</a></p></div></body></html>";
    }

    /**
     * Render Developer Endpoint Guide & Status for GET requests on form endpoints
     */
    private static function renderFormEndpointGuide(string $endpoint, ?string $targetSlug = null): void {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $isJson = str_contains($accept, 'application/json') || (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || (isset($_GET['format']) && $_GET['format'] === 'json');

        if ($isJson) {
            json_response([
                'status' => 'operational',
                'service' => 'PageDock Zero-Backend Form Engine',
                'endpoint' => '/' . ltrim($endpoint, '/'),
                'method' => 'POST',
                'target_slug' => $targetSlug,
                'aliases' => ['/__pd/form', '/__hh/form', '/f/{slug}'],
                'description' => 'Endpoint active and ready to receive HTTP POST form submissions.',
                'docs' => url('docs#forms')
            ]);
            return;
        }

        render_view('form_guide', [
            'endpoint' => '/' . ltrim($endpoint, '/'),
            'targetSlug' => $targetSlug,
            'title' => 'Form Engine Endpoint — PageDock'
        ]);
    }

    /**
     * Handle file or zip upload
     */
    private static function handleApiUpload(): void {
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $raw = file_get_contents('php://input');
            $data = json_decode($raw, true) ?: [];
            if (!empty($data['html'])) {
                $res = SiteManager::createFromHtml($data['html'], $data['title'] ?? null, $data['slug'] ?? null);
                json_response($res, $res['success'] ? 200 : 400);
                return;
            }
            json_response(['success' => false, 'error' => 'No file uploaded or upload error.'], 400);
            return;
        }

        $file = $_FILES['file'];
        $origName = $file['name'];
        $tmpPath = $file['tmp_name'];
        $size = $file['size'];

        if ($size > MAX_UPLOAD_BYTES) {
            json_response(['success' => false, 'error' => 'File exceeds 50MB limit.'], 400);
        }

        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        $preferredSlug = $_POST['slug'] ?? null;
        $title = $_POST['title'] ?? pathinfo($origName, PATHINFO_FILENAME);

        if ($ext === 'zip') {
            $res = SiteManager::createFromZip($tmpPath, $title, $preferredSlug);
            json_response($res, $res['success'] ? 200 : 400);
        } elseif ($ext === 'html' || $ext === 'htm') {
            $content = file_get_contents($tmpPath);
            $res = SiteManager::createFromHtml($content, $title, $preferredSlug);
            json_response($res, $res['success'] ? 200 : 400);
        } else {
            json_response(['success' => false, 'error' => 'Unsupported file type. Please upload .html or .zip.'], 400);
        }
    }

    /**
     * Unified cURL & Agent Deployment Endpoint: POST /api/deploy
     * Synthesized from sintaxi/surge & whyredfire/redoost
     */
    private static function handleApiDeploy(): void {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: POST, PUT, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Project-Slug, X-Claim-Token, X-Title');

        $ua = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
        $isCurl = str_contains($ua, 'curl') || str_contains($ua, 'httpie') || str_contains($ua, 'wget');

        $preferredSlug = $_POST['slug'] ?? $_GET['slug'] ?? $_SERVER['HTTP_X_PROJECT_SLUG'] ?? null;
        $title = $_POST['title'] ?? $_GET['title'] ?? $_SERVER['HTTP_X_TITLE'] ?? null;
        $res = null;

        if (!empty($_FILES['file']['tmp_name'])) {
            $file = $_FILES['file'];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                self::renderDeployResponse(['success' => false, 'error' => 'File upload failed with error code ' . $file['error']], 400, $isCurl);
                return;
            }

            $origName = strtolower($file['name']);
            if (str_ends_with($origName, '.zip')) {
                $res = SiteManager::deployFromZip($file['tmp_name'], $title, $preferredSlug);
            } else {
                $content = file_get_contents($file['tmp_name']);
                $res = SiteManager::deployFromHtml($content, $title, $preferredSlug);
            }
        } else {
            $rawBody = file_get_contents('php://input');
            if (empty($rawBody)) {
                self::renderDeployResponse([
                    'success' => false,
                    'error' => 'No files or body provided. Example: curl -F "file=@site.zip" ' . url('api/deploy')
                ], 400, $isCurl);
                return;
            }

            $jsonData = json_decode($rawBody, true);
            if (is_array($jsonData) && !empty($jsonData['html'])) {
                $res = SiteManager::deployFromHtml($jsonData['html'], $jsonData['title'] ?? $title, $jsonData['slug'] ?? $preferredSlug);
            } elseif (str_starts_with($rawBody, "PK\x03\x04") || str_starts_with($rawBody, "PK\x05\x06")) {
                $tmpZip = tempnam(sys_get_temp_dir(), 'pd_deploy_') . '.zip';
                file_put_contents($tmpZip, $rawBody);
                $res = SiteManager::deployFromZip($tmpZip, $title, $preferredSlug);
                @unlink($tmpZip);
            } else {
                $res = SiteManager::deployFromHtml($rawBody, $title, $preferredSlug);
            }
        }

        $status = ($res && !empty($res['success'])) ? 200 : ($res['status_code'] ?? 400);
        self::renderDeployResponse($res ?: ['success' => false, 'error' => 'Deployment processing failed.'], $status, $isCurl);
    }

    private static function renderDeployResponse(array $res, int $statusCode, bool $isCurl): void {
        http_response_code($statusCode);
        if ($isCurl) {
            header('Content-Type: text/plain; charset=utf-8');
            if (!empty($res['success'])) {
                $slug = $res['slug'] ?? '';
                $url = $res['url'] ?? url('s/' . $slug);
                $editUrl = url('editor/' . $slug);
                $claim = $res['claim_token'] ?? '';
                $action = !empty($res['updated']) ? 'Updated' : 'Published';
                echo "\n[SUCCESS] {$action} to PageDock successfully!\n\n";
                echo "Live URL:    {$url}\n";
                echo "Edit Online: {$editUrl}\n";
                if ($claim) {
                    echo "Claim Token: {$claim}\n";
                }
                echo "\nTo deploy updates:\n";
                echo "  curl -F \"file=@dist.zip\" -H \"X-Claim-Token: {$claim}\" " . url('api/deploy') . "?slug={$slug}\n\n";
            } else {
                echo "\n[ERROR] Deployment Failed: " . ($res['error'] ?? 'Unknown error') . "\n\n";
            }
            exit;
        }

        json_response($res, $statusCode);
    }

    /**
     * Safely serve static files from sites/{slug}/ with Clean URLs, _redirects, and custom 404
     */
    private static function serveSiteFile(string $slug, string $subpath): void {
        $site = SiteManager::getSite($slug);
        if (!$site) {
            http_response_code(404);
            echo "<h1>404 — Site not found</h1><p>The requested PageDock site does not exist.</p>";
            return;
        }

        if (!empty($site['is_suspended'])) {
            http_response_code(451);
            echo "<!DOCTYPE html><html lang=\"en\"><head><meta charset=\"UTF-8\"><meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\"><title>Site Suspended — PageDock</title><style>body{font-family:sans-serif;background:#0b0f19;color:#fff;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;}div{text-align:center;max-width:480px;padding:32px;background:#161d2f;border:1px solid #2d3748;border-radius:12px;}h1{color:#ef4444;font-size:24px;margin-bottom:8px;}p{color:#94a3b8;font-size:14px;line-height:1.6;}</style></head><body><div><h1>Site Suspended</h1><p>This deployment has been suspended by the platform administrator for policy review.</p></div></body></html>";
            return;
        }

        $siteDir = realpath(SITES_DIR . '/' . $slug);
        if (!$siteDir || !is_dir($siteDir)) {
            if (class_exists('R2Storage') && R2Storage::isConfigured()) {
                $targetDir = SITES_DIR . '/' . $slug;
                R2Storage::syncR2ToDirectory('sites/' . $slug, $targetDir);
                $siteDir = realpath($targetDir);
            }
        }
        if (!$siteDir || !is_dir($siteDir)) {
            http_response_code(404);
            echo "<h1>Site files missing</h1>";
            return;
        }

        // Auto-heal sites where files were nested inside a folder or contain OS junk
        if (!file_exists($siteDir . '/index.html') && !file_exists($siteDir . '/index.htm')) {
            SiteManager::normalizeExtractedSite($siteDir);
        }

        $trimmedSubpath = trim($subpath, '/');
        $lowerSubpath = strtolower($trimmedSubpath);

        // 1. Security: Never serve _redirects or hidden files/folders (.snapshots, .env, .git, etc.) publicly
        $hasHiddenSegment = false;
        foreach (explode('/', $lowerSubpath) as $seg) {
            if (str_starts_with($seg, '.')) {
                $hasHiddenSegment = true;
                break;
            }
        }
        if ($lowerSubpath === '_redirects' || $hasHiddenSegment) {
            http_response_code(404);
            self::renderSite404($siteDir, $site);
            return;
        }

        // 2. Netlify-Style _redirects Check
        $redirectRules = RedirectManager::parse($siteDir);
        if (!empty($redirectRules)) {
            $currentReqPath = '/' . $trimmedSubpath;
            $matched = RedirectManager::match($redirectRules, $currentReqPath, $_GET);
            if ($matched) {
                $target = $matched['target'];
                $status = $matched['status'];
                if (preg_match('#^https?://#i', $target)) {
                    header('Location: ' . $target, true, $status);
                    exit;
                }
                $siteBaseUrl = url('s/' . $slug);
                $finalUrl = rtrim($siteBaseUrl, '/') . '/' . ltrim($target, '/');
                header('Location: ' . $finalUrl, true, $status);
                exit;
            }
        }

        // 3. Clean URL Handling (Strip .html from URLs with 301 Redirect, preserving query strings)
        if ($trimmedSubpath !== '') {
            $ext = strtolower(pathinfo($trimmedSubpath, PATHINFO_EXTENSION));
            if ($ext === 'html' || $ext === 'htm') {
                $qs = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
                if ($lowerSubpath === 'index.html' || $lowerSubpath === 'index.htm') {
                    header('Location: ' . url('s/' . $slug . '/') . $qs, true, 301);
                    exit;
                }
                // Do not redirect subfolder index files e.g. blog/index.html so relative assets resolve
                if (!str_ends_with($lowerSubpath, '/index.html') && !str_ends_with($lowerSubpath, '/index.htm')) {
                    $cleanSubpath = preg_replace('/\.(html|htm)$/i', '', $trimmedSubpath);
                    header('Location: ' . url('s/' . $slug . '/' . $cleanSubpath) . $qs, true, 301);
                    exit;
                }
            }
        }

        // 4. File Resolution Strategy
        $fileToServe = null;

        if ($trimmedSubpath === '') {
            $entry = $site['entry_file'] ?: 'index.html';
            $candidate = realpath($siteDir . '/' . $entry);
            if ($candidate && str_starts_with($candidate, $siteDir) && is_file($candidate)) {
                $fileToServe = $candidate;
            } elseif (file_exists($siteDir . '/index.html')) {
                $fileToServe = realpath($siteDir . '/index.html');
            } elseif (file_exists($siteDir . '/index.htm')) {
                $fileToServe = realpath($siteDir . '/index.htm');
            } else {
                $fileToServe = self::findFileCaseInsensitive($siteDir, $entry);
            }
        } else {
            // A. Direct file exists (e.g. style.css, script.js, image.png)
            $directCandidate = realpath($siteDir . '/' . $trimmedSubpath);
            if ($directCandidate && str_starts_with($directCandidate, $siteDir) && is_file($directCandidate)) {
                $fileToServe = $directCandidate;
            }
            // B. Clean URL resolution: path.html (e.g. /about -> about.html)
            elseif (file_exists($siteDir . '/' . $trimmedSubpath . '.html')) {
                $htmlCandidate = realpath($siteDir . '/' . $trimmedSubpath . '.html');
                if ($htmlCandidate && str_starts_with($htmlCandidate, $siteDir) && is_file($htmlCandidate)) {
                    $fileToServe = $htmlCandidate;
                }
            }
            // C. Folder index resolution: path/index.html (e.g. /blog -> blog/index.html)
            elseif (file_exists($siteDir . '/' . $trimmedSubpath . '/index.html')) {
                $folderCandidate = realpath($siteDir . '/' . $trimmedSubpath . '/index.html');
                if ($folderCandidate && str_starts_with($folderCandidate, $siteDir) && is_file($folderCandidate)) {
                    $fileToServe = $folderCandidate;
                }
            }
            // D. Relative to entry file directory (if entry file is in a subfolder e.g. dist/index.html)
            if (!$fileToServe && !empty($site['entry_file'])) {
                $entryDir = dirname($site['entry_file']);
                if ($entryDir !== '.' && $entryDir !== '') {
                    $subCandidate = realpath($siteDir . '/' . $entryDir . '/' . $trimmedSubpath);
                    if ($subCandidate && str_starts_with($subCandidate, $siteDir) && is_file($subCandidate)) {
                        $fileToServe = $subCandidate;
                    }
                }
            }
            // E. Case-insensitive lookup (vital for Linux / cPanel hosting)
            if (!$fileToServe) {
                $ciCandidate = self::findFileCaseInsensitive($siteDir, $trimmedSubpath);
                if ($ciCandidate) {
                    $fileToServe = $ciCandidate;
                }
            }
            // F. Common subfolder search (e.g. requested 'style.css' but file is in 'css/style.css' or 'assets/style.css')
            if (!$fileToServe && !str_contains($trimmedSubpath, '/')) {
                foreach (['css', 'assets', 'assets/css', 'js', 'assets/js', 'img', 'images', 'assets/img'] as $commonDir) {
                    $nested = realpath($siteDir . '/' . $commonDir . '/' . $trimmedSubpath);
                    if ($nested && str_starts_with($nested, $siteDir) && is_file($nested)) {
                        $fileToServe = $nested;
                        break;
                    }
                    $ciNested = self::findFileCaseInsensitive($siteDir, $commonDir . '/' . $trimmedSubpath);
                    if ($ciNested) {
                        $fileToServe = $ciNested;
                        break;
                    }
                }
            }

            // G. Common stylesheet and script name aliasing (e.g. style.css <-> styles.css, app.js <-> main.js)
            if (!$fileToServe && !str_contains($trimmedSubpath, '/')) {
                $subExt = strtolower(pathinfo($trimmedSubpath, PATHINFO_EXTENSION));
                $aliases = [];
                if ($subExt === 'css') {
                    $aliases = ['style.css', 'styles.css', 'main.css', 'app.css'];
                } elseif ($subExt === 'js') {
                    $aliases = ['script.js', 'scripts.js', 'main.js', 'app.js', 'bundle.js'];
                }
                foreach ($aliases as $alias) {
                    if (strtolower($alias) === strtolower($trimmedSubpath)) continue;
                    $candidate = realpath($siteDir . '/' . $alias);
                    if ($candidate && str_starts_with($candidate, $siteDir) && is_file($candidate)) {
                        $fileToServe = $candidate;
                        break;
                    }
                    $ciCandidate = self::findFileCaseInsensitive($siteDir, $alias);
                    if ($ciCandidate) {
                        $fileToServe = $ciCandidate;
                        break;
                    }
                    foreach (['css', 'assets', 'assets/css', 'js', 'assets/js'] as $sub) {
                        $subCandidate = realpath($siteDir . '/' . $sub . '/' . $alias);
                        if ($subCandidate && str_starts_with($subCandidate, $siteDir) && is_file($subCandidate)) {
                            $fileToServe = $subCandidate;
                            break 2;
                        }
                    }
                }
            }
        }

        // 5. Fallback: Render custom 404.html or default 404
        if (!$fileToServe) {
            http_response_code(404);
            self::renderSite404($siteDir, $site);
            return;
        }

        self::outputFile($fileToServe, $site);
    }

    /**
     * Case-insensitive file finder for Linux hosts
     */
    private static function findFileCaseInsensitive(string $dir, string $relative): ?string {
        $parts = explode('/', trim($relative, '/'));
        $current = $dir;
        foreach ($parts as $part) {
            if (!is_dir($current)) return null;
            $entries = scandir($current);
            $found = false;
            $lowerPart = strtolower($part);
            foreach ($entries as $e) {
                if (strtolower($e) === $lowerPart) {
                    $current .= '/' . $e;
                    $found = true;
                    break;
                }
            }
            if (!$found) return null;
        }
        $real = realpath($current);
        return ($real && str_starts_with($real, $dir) && is_file($real)) ? $real : null;
    }

    /**
     * Render site-specific custom 404.html or clean PageDock 404
     */
    private static function renderSite404(string $siteDir, array $site): void {
        $custom404 = realpath($siteDir . '/404.html');
        if ($custom404 && str_starts_with($custom404, $siteDir) && is_file($custom404)) {
            self::outputFile($custom404, $site, 404);
            return;
        }

        echo "<!DOCTYPE html><html lang='en'><head><meta charset='UTF-8'><title>404 — Page Not Found</title>";
        echo "<style>body{background:#0d0f12;color:#fff;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;text-align:center;} .box{background:#15181e;border:1px solid #232730;padding:40px;border-radius:12px;max-width:440px;} h1{color:#C7F547;margin-bottom:8px;font-size:24px;} p{color:#8b949e;font-size:15px;line-height:1.5;} a{color:#C7F547;text-decoration:none;font-weight:600;}</style></head>";
        echo "<body><div class='box'><h1>404 — Page Not Found</h1><p>The page you are looking for does not exist on this site.</p><p style='margin-top:20px;'><a href='" . url('s/' . $site['slug']) . "'>&larr; Return to site home</a></p></div></body></html>";
    }

    /**
     * Output a file with proper MIME type, link hygiene, and PageDock badge
     */
    private static function outputFile(string $filePath, array $site, int $statusCode = 200): void {
        http_response_code($statusCode);

        $mimeTypes = [
            'html' => 'text/html; charset=utf-8',
            'htm'  => 'text/html; charset=utf-8',
            'css'  => 'text/css; charset=utf-8',
            'js'   => 'application/javascript; charset=utf-8',
            'json' => 'application/json',
            'png'  => 'image/png',
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif'  => 'image/gif',
            'svg'  => 'image/svg+xml',
            'webp' => 'image/webp',
            'ico'  => 'image/x-icon',
            'ttf'  => 'font/ttf',
            'woff' => 'font/woff',
            'woff2'=> 'font/woff2',
            'txt'  => 'text/plain; charset=utf-8',
            'pdf'  => 'application/pdf',
        ];

        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $contentType = $mimeTypes[$ext] ?? 'application/octet-stream';

        header('Content-Type: ' . $contentType);
        header('Cache-Control: public, max-age=3600');

        if ($ext === 'html' || $ext === 'htm') {
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');

            $content = file_get_contents($filePath);

            // Layer 4 Grounding: ensure relative links (style.css, script.js, images/) resolve to the site directory
            // Use root-relative base (/s/{slug}/) to eliminate mixed-content blocking across HTTP/HTTPS and proxy boundaries
            $siteBase = '/s/' . $site['slug'] . '/';
            if (preg_match('/<base\b[^>]*>/i', $content)) {
                $content = preg_replace('/<base\b[^>]*>/i', "<base href=\"{$siteBase}\">", $content, 1);
            } else {
                if (stripos($content, '<head>') !== false) {
                    $content = str_ireplace('<head>', "<head>\n    <base href=\"{$siteBase}\">", $content);
                } elseif (stripos($content, '<head') !== false) {
                    $content = preg_replace('/(<head[^>]*>)/i', "$1\n    <base href=\"{$siteBase}\">", $content, 1);
                } else {
                    $content = "<base href=\"{$siteBase}\">\n" . $content;
                }
            }

            // Rewrite root-relative asset URLs (e.g. href="/style.css" or src="/script.js") to site-relative URLs
            $content = preg_replace_callback(
                '/\b(href|src)=(["\'])\/([^\/"\'][^"\']*)\2/i',
                function ($m) use ($siteBase) {
                    $attr = $m[1];
                    $quote = $m[2];
                    $relPath = $m[3];
                    if (str_starts_with($relPath, '__pd/') || 
                        str_starts_with($relPath, '__hh/') || 
                        str_starts_with($relPath, 'f/') || 
                        str_starts_with($relPath, 's/') || 
                        str_starts_with($relPath, 'api/')) {
                        return $m[0];
                    }
                    return "{$attr}={$quote}{$siteBase}{$relPath}{$quote}";
                },
                $content
            );

            // Critical CSS Delivery: If primary stylesheet exists on disk and is <= 256KB,
            // inline it directly into <head> so the page renders instantly with zero FOUC,
            // zero proxy/cookie drops, and zero mixed-content blocks across all browsers and proxies.
            $siteDir = realpath(SITES_DIR . '/' . $site['slug']);
            if ($siteDir && is_dir($siteDir)) {
                $primaryCssCandidates = ['styles.css', 'style.css', 'css/styles.css', 'css/style.css', 'assets/css/style.css', 'assets/style.css'];
                $foundCssContent = null;
                foreach ($primaryCssCandidates as $cssRel) {
                    $cssPath = realpath($siteDir . '/' . $cssRel);
                    if ($cssPath && str_starts_with($cssPath, $siteDir) && is_file($cssPath)) {
                        $cssSize = filesize($cssPath);
                        if ($cssSize > 0 && $cssSize <= 256 * 1024) {
                            $foundCssContent = file_get_contents($cssPath);
                            break;
                        }
                    }
                }
                if ($foundCssContent !== null) {
                    $inlineCssTag = "\n    <style id=\"pagedock-direct-css\">\n" . $foundCssContent . "\n    </style>\n";
                    if (stripos($content, '</head>') !== false) {
                        $content = str_ireplace('</head>', $inlineCssTag . '</head>', $content);
                    } else {
                        $content = $inlineCssTag . $content;
                    }
                }
            }

            // Outbound Link Hygiene: add rel="nofollow ugc" to external links on unverified sites
            $content = preg_replace_callback('/<a\s+([^>]*href=["\'](https?:\/\/[^"\']+)["\'][^>]*)>/i', function($matches) {
                $tag = $matches[0];
                $attrs = $matches[1];
                if (preg_match('/rel=["\']([^"\']*)["\']/i', $attrs, $relMatch)) {
                    $existingRel = $relMatch[1];
                    if (!str_contains($existingRel, 'nofollow')) {
                        $newRel = trim($existingRel . ' nofollow ugc');
                        return str_replace($relMatch[0], 'rel="' . $newRel . '"', $tag);
                    }
                    return $tag;
                }
                return '<a ' . $attrs . ' rel="nofollow ugc">';
            }, $content);

            // Injected Unobtrusive Form Script for /__pd/form (HTMLHost Master Spec Section 7)
            $formScript = '';
            if (stripos($content, '<form') !== false) {
                $formScript = '
                <!-- PageDock Zero-Backend Form Engine Injected Helper -->
                <script>
                (function(){
                    document.addEventListener("DOMContentLoaded", function(){
                        var forms = document.querySelectorAll("form[action*=\"/__pd/form\"], form[action*=\"/__hh/form\"], form[action*=\"/f/\"], form[action*=\"/api/forms\"], form[data-pd-form]");
                        forms.forEach(function(f){
                            if (!f.querySelector("input[name=\"_pd_hp\"]")) {
                                var hp = document.createElement("div");
                                hp.setAttribute("aria-hidden", "true");
                                hp.style.cssText = "position:absolute;left:-10000px;width:1px;height:1px;overflow:hidden";
                                hp.innerHTML = "<label>Leave this field empty <input type=\"text\" name=\"_pd_hp\" tabindex=\"-1\" autocomplete=\"off\"></label>";
                                f.appendChild(hp);
                            }
                            if (!f.querySelector("input[name=\"_pd_ts\"]")) {
                                var ts = document.createElement("input");
                                ts.type = "hidden";
                                ts.name = "_pd_ts";
                                ts.value = Math.floor(Date.now() / 1000);
                                f.appendChild(ts);
                            }
                            f.addEventListener("submit", function(e){
                                if (e.defaultPrevented) return;
                                e.preventDefault();
                                var btn = f.querySelector("[type=\"submit\"]");
                                if (btn) btn.disabled = true;
                                var msg = f.querySelector("[data-pd-form-msg]");
                                if (!msg) {
                                    msg = document.createElement("p");
                                    msg.setAttribute("data-pd-form-msg", "");
                                    msg.setAttribute("role", "status");
                                    msg.style.cssText = "margin-top:12px;font-size:14px;";
                                    f.appendChild(msg);
                                }
                                msg.removeAttribute("data-state");
                                msg.textContent = "Sending...";
                                var fd = new FormData(f);
                                var targetAction = f.getAttribute("action") || "' . url('__pd/form') . '";
                                fetch(targetAction, {
                                    method: "POST",
                                    headers: { "Accept": "application/json" },
                                    body: fd
                                })
                                .then(function(r){ return r.json(); })
                                .then(function(d){
                                    if (btn) btn.disabled = false;
                                    if (d.ok || d.success) {
                                        msg.setAttribute("data-state", "success");
                                        msg.textContent = d.message || "Thanks! Your message has been sent.";
                                        f.reset();
                                        if (d.redirect) {
                                            setTimeout(function(){ window.location.href = d.redirect; }, 600);
                                        }
                                    } else {
                                        msg.setAttribute("data-state", "error");
                                        msg.textContent = d.error || "Failed to send message.";
                                    }
                                })
                                .catch(function(){
                                    if (btn) btn.disabled = false;
                                    msg.setAttribute("data-state", "error");
                                    msg.textContent = "Network error. Please try again.";
                                });
                            });
                        });
                    });
                })();
                </script>
                ';
            }

            // Floating PageDock badge
            $badge = '
            <!-- PageDock Badge -->
            <div id="pagedock-badge" style="position:fixed;bottom:max(12px,env(safe-area-inset-bottom,12px));right:max(12px,env(safe-area-inset-right,12px));z-index:999999;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif;font-size:11px;box-shadow:0 4px 14px rgba(0,0,0,0.3);touch-action:manipulation;">
                <a href="' . url() . '" target="_blank" style="display:flex;align-items:center;gap:6px;background:rgba(20,20,22,0.92);backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);color:#ededed;padding:5px 9px;border-radius:6px;border:1px solid #2a2a2e;text-decoration:none;font-weight:500;">
                    <span style="display:inline-block;width:6px;height:6px;border-radius:50%;background:#C7F547;"></span>
                    <span>Hosted on <strong>PageDock</strong></span>
                </a>
            </div>
            ';

            // Privacy-Preserving Cookieless Analytics Edge Beacon (HTMLHost Master Spec Section 8)
            $analyticsBeacon = '
            <!-- PageDock Cookieless Analytics Edge Beacon -->
            <script>
            (function(){
                try {
                    var p = {
                        _site: ' . json_encode($site['slug']) . ',
                        path: window.location.pathname,
                        referrer: document.referrer || ""
                    };
                    var u = ' . json_encode(url('__pd/v'), JSON_UNESCAPED_SLASHES) . ';
                    var payload = JSON.stringify(p);
                    if (navigator.sendBeacon) {
                        navigator.sendBeacon(u, new Blob([payload], { type: "application/json" }));
                    } else if (window.fetch) {
                        fetch(u, { method: "POST", headers: { "Content-Type": "application/json" }, body: payload, keepalive: true }).catch(function(){});
                    }
                } catch(e){}
            })();
            </script>
            ';

            $injectedPayload = $formScript . $badge . $analyticsBeacon;

            if (stripos($content, '</body>') !== false) {
                $content = str_ireplace('</body>', $injectedPayload . '</body>', $content);
            } else {
                $content .= $injectedPayload;
            }

            echo $content;
            return;
        }

        readfile($filePath);
    }

    private static function renderOpenApiSpec(): void {
        OAuthServer::sendCorsHeaders();
        $base = rtrim(url(), '/');
        json_response([
            'openapi' => '3.1.0',
            'info' => [
                'title' => 'PageDock API',
                'version' => APP_VERSION,
                'description' => 'Deploy static HTML sites, edit files, inspect versions, roll back, and read analytics/form leads on PageDock.',
            ],
            'servers' => [['url' => $base]],
            'components' => [
                'securitySchemes' => [
                    'bearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                    ],
                ],
            ],
            'security' => [['bearerAuth' => []]],
            'paths' => [
                '/api/sites' => [
                    'get' => [
                        'operationId' => 'listSites',
                        'summary' => 'List all websites belonging to the authenticated account',
                        'responses' => ['200' => ['description' => 'List of sites']],
                    ],
                ],
                '/api/sites/{slug}' => [
                    'get' => [
                        'operationId' => 'getSite',
                        'summary' => 'Get metadata and file list for a site',
                        'parameters' => [['name' => 'slug', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']]],
                        'responses' => ['200' => ['description' => 'Site details']],
                    ],
                ],
                '/api/deploy' => [
                    'post' => [
                        'operationId' => 'deployHtmlSite',
                        'summary' => 'Publish a new HTML site or update an existing site by slug',
                        'requestBody' => [
                            'required' => true,
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'required' => ['html'],
                                        'properties' => [
                                            'html' => ['type' => 'string', 'description' => 'Complete HTML document'],
                                            'title' => ['type' => 'string'],
                                            'slug' => ['type' => 'string'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'responses' => ['200' => ['description' => 'Published or updated site metadata']],
                    ],
                ],
                '/api/editor/{slug}' => [
                    'post' => [
                        'operationId' => 'updateSiteFiles',
                        'summary' => 'Update HTML or multiple files in an existing site',
                        'parameters' => [['name' => 'slug', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']]],
                        'requestBody' => [
                            'required' => true,
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'html' => ['type' => 'string'],
                                            'file' => ['type' => 'string'],
                                            'files' => ['type' => 'object', 'additionalProperties' => ['type' => 'string']],
                                            'summary' => ['type' => 'string'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'responses' => ['200' => ['description' => 'Updated files']],
                    ],
                ],
                '/api/sites/{slug}/files' => [
                    'get' => [
                        'operationId' => 'listSiteFiles',
                        'summary' => 'List all files inside a site',
                        'parameters' => [['name' => 'slug', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']]],
                        'responses' => ['200' => ['description' => 'File paths array']],
                    ],
                ],
                '/api/sites/{slug}/file' => [
                    'get' => [
                        'operationId' => 'getSiteFile',
                        'summary' => 'Read a specific file from a site',
                        'parameters' => [
                            ['name' => 'slug', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
                            ['name' => 'path', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string', 'default' => 'index.html']],
                        ],
                        'responses' => ['200' => ['description' => 'File contents']],
                    ],
                ],
                '/api/sites/{slug}/versions' => [
                    'get' => [
                        'operationId' => 'listSiteVersions',
                        'summary' => 'List deployment version snapshots',
                        'parameters' => [['name' => 'slug', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']]],
                        'responses' => ['200' => ['description' => 'Version snapshots']],
                    ],
                ],
                '/api/sites/{slug}/rollback' => [
                    'post' => [
                        'operationId' => 'rollbackSiteVersion',
                        'summary' => 'Rollback a site to a previous version snapshot',
                        'parameters' => [['name' => 'slug', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']]],
                        'requestBody' => [
                            'required' => false,
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'properties' => ['version' => ['type' => 'integer']],
                                    ],
                                ],
                            ],
                        ],
                        'responses' => ['200' => ['description' => 'Rollback result']],
                    ],
                ],
                '/api/sites/{slug}/analytics' => [
                    'get' => [
                        'operationId' => 'getSiteAnalytics',
                        'summary' => 'Get visitor analytics for a site',
                        'parameters' => [
                            ['name' => 'slug', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
                            ['name' => 'days', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'integer', 'default' => 30]],
                        ],
                        'responses' => ['200' => ['description' => 'Analytics metrics']],
                    ],
                ],
                '/api/sites/{slug}/forms' => [
                    'get' => [
                        'operationId' => 'listFormSubmissions',
                        'summary' => 'List captured form submissions for a site',
                        'parameters' => [['name' => 'slug', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']]],
                        'responses' => ['200' => ['description' => 'Form submissions']],
                    ],
                ],
            ],
        ]);
    }
}
