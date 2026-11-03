<?php

class Router {
    public static function handle(): void {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        // Trim slashes
        $path = trim($uri, '/');
        $parts = $path === '' ? [] : explode('/', $path);

        // 1. Serving Hosted Static Sites: /s/{slug} or /s/{slug}/{filepath}
        if (!empty($parts) && $parts[0] === 's') {
            $slug = $parts[1] ?? null;
            if (!$slug) {
                http_response_code(404);
                echo "Site slug missing";
                return;
            }

            $subpath = implode('/', array_slice($parts, 2));
            self::serveSiteFile($slug, $subpath);
            return;
        }

        // 2. Form submission endpoint: /api/forms/{slug}
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
            
            // If submitted from standard HTML form with redirect
            if (isset($_POST['_redirect'])) {
                redirect($_POST['_redirect']);
            }

            json_response($res, $res['success'] ? 200 : 400);
            return;
        }

        // 3. API Upload: POST /api/upload
        if ($path === 'api/upload' && $method === 'POST') {
            self::handleApiUpload();
            return;
        }

        // 4. API Paste: POST /api/paste
        if ($path === 'api/paste' && $method === 'POST') {
            $html = $_POST['html'] ?? '';
            $title = $_POST['title'] ?? null;
            $slug = $_POST['slug'] ?? null;
            $res = SiteManager::createFromHtml($html, $title, $slug);
            json_response($res, $res['success'] ? 200 : 400);
            return;
        }

        // 5. API Update Site HTML: POST /api/editor/{slug}
        if (!empty($parts) && $parts[0] === 'api' && ($parts[1] ?? '') === 'editor') {
            $slug = $parts[2] ?? null;
            $raw = file_get_contents('php://input');
            $data = json_decode($raw, true) ?: $_POST;
            $html = $data['html'] ?? '';

            $res = SiteManager::updateSiteHtml($slug, $html);
            json_response($res, $res['success'] ? 200 : 400);
            return;
        }

        // 6. API Delete Site: POST /api/sites/{slug}/delete
        if (!empty($parts) && $parts[0] === 'api' && ($parts[1] ?? '') === 'sites' && ($parts[3] ?? '') === 'delete') {
            $slug = $parts[2] ?? null;
            $res = SiteManager::deleteSite($slug);
            json_response($res, $res['success'] ? 200 : 400);
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
            if ($res['success']) {
                $res['editor_url'] = url('editor/' . $res['slug']);
            }
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

        // 7. Editor view: /editor/{slug}
        if (!empty($parts) && $parts[0] === 'editor') {
            $slug = $parts[1] ?? null;
            $site = $slug ? SiteManager::getSite($slug) : null;
            if (!$site) {
                render_view('404', ['title' => 'Site Not Found']);
                return;
            }

            $html = SiteManager::getSiteHtml($slug);
            $canManage = SiteManager::canManage($site);

            render_view('editor', [
                'site' => $site,
                'html' => $html,
                'canManage' => $canManage,
                'title' => 'Editor — ' . htmlspecialchars($site['title'])
            ], false); // Don't include global header/footer for the full-screen editor
            return;
        }

        // 8. Dashboard
        if ($path === 'dashboard') {
            $sites = SiteManager::getUserSites();
            render_view('dashboard', [
                'sites' => $sites,
                'user' => Auth::user(),
                'title' => 'Dashboard — PageDock'
            ]);
            return;
        }

        // 9. Auth Routes
        if ($path === 'login') {
            if ($method === 'POST') {
                $email = $_POST['email'] ?? '';
                $password = $_POST['password'] ?? '';
                $res = Auth::login($email, $password);
                if ($res['success']) {
                    redirect('dashboard');
                } else {
                    render_view('login', ['error' => $res['error'], 'title' => 'Sign In — PageDock']);
                }
                return;
            }
            render_view('login', ['title' => 'Sign In — PageDock']);
            return;
        }

        if ($path === 'register') {
            if ($method === 'POST') {
                $email = $_POST['email'] ?? '';
                $password = $_POST['password'] ?? '';
                $name = $_POST['name'] ?? '';
                $res = Auth::register($email, $password, $name);
                if ($res['success']) {
                    redirect('dashboard');
                } else {
                    render_view('register', ['error' => $res['error'], 'title' => 'Create Account — PageDock']);
                }
                return;
            }
            render_view('register', ['title' => 'Create Account — PageDock']);
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
                'title' => 'PageDock — Host your HTML. Get a link.'
            ]);
            return;
        }

        // 404 Fallback
        http_response_code(404);
        render_view('404', ['title' => 'Page Not Found']);
    }

    /**
     * Handle file or zip upload
     */
    private static function handleApiUpload(): void {
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            json_response(['success' => false, 'error' => 'No file uploaded or upload error.'], 400);
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
     * Safely serve static files from sites/{slug}/
     */
    private static function serveSiteFile(string $slug, string $subpath): void {
        $site = SiteManager::getSite($slug);
        if (!$site) {
            http_response_code(404);
            echo "<h1>404 — Site not found</h1><p>The requested PageDock site does not exist.</p>";
            return;
        }

        $siteDir = realpath(SITES_DIR . '/' . $slug);
        if (!$siteDir || !is_dir($siteDir)) {
            http_response_code(404);
            echo "<h1>Site files missing</h1>";
            return;
        }

        // If no subpath provided, use entry file (index.html)
        $subpath = trim($subpath, '/');
        if ($subpath === '') {
            $subpath = $site['entry_file'] ?: 'index.html';
        }

        $targetFile = realpath($siteDir . '/' . $subpath);

        // Security check: must reside inside siteDir
        if (!$targetFile || !str_starts_with($targetFile, $siteDir) || !is_file($targetFile)) {
            http_response_code(404);
            echo "<h1>404 — File not found</h1>";
            return;
        }

        // MIME types map
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

        $ext = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
        $contentType = $mimeTypes[$ext] ?? 'application/octet-stream';

        header('Content-Type: ' . $contentType);
        header('Cache-Control: public, max-age=3600');

        // Increment view count if HTML
        if ($ext === 'html' || $ext === 'htm') {
            SiteManager::incrementView((int)$site['id']);

            $content = file_get_contents($targetFile);
            
            // Inject subtle PageDock floating badge before </body>
            $badge = '
            <!-- PageDock Badge -->
            <div id="pagedock-badge" style="position:fixed;bottom:12px;right:12px;z-index:999999;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif;font-size:12px;box-shadow:0 4px 14px rgba(0,0,0,0.25);">
                <a href="' . url() . '" target="_blank" style="display:flex;align-items:center;gap:6px;background:#141416;color:#ededed;padding:5px 10px;border-radius:6px;border:1px solid #2a2a2e;text-decoration:none;font-weight:500;transition:all 0.15s ease;">
                    <span style="display:inline-block;width:7px;height:7px;border-radius:50%;background:#C7F547;"></span>
                    <span>Hosted on <strong>PageDock</strong></span>
                </a>
            </div>
            ';

            if (stripos($content, '</body>') !== false) {
                $content = str_ireplace('</body>', $badge . '</body>', $content);
            } else {
                $content .= $badge;
            }

            echo $content;
            return;
        }

        readfile($targetFile);
    }
}
