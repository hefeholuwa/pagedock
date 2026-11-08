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

            // Layer 3 Safety Net: If a stray relative request for style.css / script.js arrives at /s/{asset}
            if (in_array($slug, ['style.css', 'script.js'], true)) {
                $referer = $_SERVER['HTTP_REFERER'] ?? '';
                if ($referer && preg_match('#/s/([a-zA-Z0-9_-]+)#', $referer, $refMatch)) {
                    $refSlug = $refMatch[1];
                    self::serveSiteFile($refSlug, $slug);
                    return;
                }
            }

            $subpath = implode('/', array_slice($parts, 2));

            // Layer 1: Canonical trailing slash redirect for site root URLs in browsers
            if ($subpath === '' && !str_ends_with($uri, '/')) {
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

        // 2a. HTMLHost Universal Form Endpoint: POST /__pd/form
        if ($path === '__pd/form' && $method === 'POST') {
            self::handleUniversalForm();
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

        // 5b. AI HTML Editor API Endpoints (1:1 htmlhost.co Protocol)
        // 5b-1. Check AI Availability & Quotas: GET /api/ai/availability
        if (!empty($parts) && $parts[0] === 'api' && ($parts[1] ?? '') === 'ai' && ($parts[2] ?? '') === 'availability') {
            json_response([
                'available' => true,
                'quota' => [
                    'used' => 0,
                    'limit' => 5,
                    'remaining' => 5
                ],
                'models' => [
                    ['id' => 'gemini-3.5-flash-lite', 'label' => 'Gemini 3.5 Flash Lite', 'provider' => 'google'],
                    ['id' => 'claude-3-7-sonnet', 'label' => 'Claude 3.7 Sonnet', 'provider' => 'anthropic'],
                    ['id' => 'gpt-4.1', 'label' => 'GPT-4.1', 'provider' => 'openai']
                ]
            ]);
            return;
        }

        // 5b-2. Start / Track AI Task: POST /api/ai/task
        if (!empty($parts) && $parts[0] === 'api' && ($parts[1] ?? '') === 'ai' && ($parts[2] ?? '') === 'task') {
            $raw = file_get_contents('php://input');
            $data = json_decode($raw, true) ?: [];
            json_response([
                'success' => true,
                'taskId' => 'task_' . bin2hex(random_bytes(6)),
                'status' => 'started',
                'model' => $data['model'] ?? 'gemini-3.5-flash-lite'
            ]);
            return;
        }

        // 5b-3. Execute AI Generator / Targeted Element Edit: POST /api/ai/builtin
        if (!empty($parts) && $parts[0] === 'api' && ($parts[1] ?? '') === 'ai' && ($parts[2] ?? '') === 'builtin') {
            $raw = file_get_contents('php://input');
            $data = json_decode($raw, true) ?: [];
            $mode = $data['mode'] ?? 'chat';
            $instruction = trim($data['instruction'] ?? $data['intent'] ?? '');
            $elementHtml = $data['elementHtml'] ?? '';

            if ($mode === 'element') {
                $patched = SiteManager::applyElementAiPatch($elementHtml, $instruction);
                json_response([
                    'success' => true,
                    'html' => $patched,
                    'summary' => 'Applied: ' . $instruction
                ]);
                return;
            }

            // Whole-page / Chat assistant response
            json_response([
                'success' => true,
                'message' => 'Processed: ' . $instruction,
                'diffs' => []
            ]);
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
                json_response(['success' => false, 'error' => 'No file uploaded or upload error occurred.'], 400);
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

            if (!empty($_GET['token']) && is_string($_GET['token'])) {
                if (!isset($_SESSION['guest_claim_tokens']) || !is_array($_SESSION['guest_claim_tokens'])) {
                    $_SESSION['guest_claim_tokens'] = [];
                }
                if (!in_array($_GET['token'], $_SESSION['guest_claim_tokens'], true)) {
                    $_SESSION['guest_claim_tokens'][] = $_GET['token'];
                }
            }

            $activeFile = $_GET['file'] ?? ($site['entry_file'] ?: 'index.html');
            $canManage = SiteManager::canManage($site);

            // If visitor can manage and file is HTML, decouple inlined styles/scripts so index.html holds only HTML
            if ($canManage && (str_ends_with(strtolower($activeFile), '.html') || str_ends_with(strtolower($activeFile), '.htm'))) {
                SiteManager::decoupleAssets($slug, $activeFile);
                $site = SiteManager::getSite($slug);
            }

            $html = SiteManager::getSiteHtml($slug, $activeFile);

            $allHtmlFiles = SharedLayoutManager::findAllHtmlFiles($slug);
            $sharedReport = SharedLayoutManager::inspectSite($slug);

            render_view('editor', [
                'site' => $site,
                'html' => $html,
                'activeFile' => $activeFile,
                'htmlFiles' => $allHtmlFiles,
                'sharedReport' => $sharedReport,
                'canManage' => $canManage,
                'title' => 'Editor — ' . htmlspecialchars($site['title'])
            ], false); // Don't include global header/footer for the full-screen editor
            return;
        }

        // 7b. Visual Studio (Elementor-Style Builder): /studio/{slug}
        if (!empty($parts) && $parts[0] === 'studio') {
            $slug = $parts[1] ?? null;
            $site = $slug ? SiteManager::getSite($slug) : null;
            if (!$site) {
                render_view('404', ['title' => 'Site Not Found']);
                return;
            }

            if (!empty($_GET['token']) && is_string($_GET['token'])) {
                if (!isset($_SESSION['guest_claim_tokens']) || !is_array($_SESSION['guest_claim_tokens'])) {
                    $_SESSION['guest_claim_tokens'] = [];
                }
                if (!in_array($_GET['token'], $_SESSION['guest_claim_tokens'], true)) {
                    $_SESSION['guest_claim_tokens'][] = $_GET['token'];
                }
            }

            $activeFile = $_GET['file'] ?? ($site['entry_file'] ?: 'index.html');
            $canManage = SiteManager::canManage($site);

            if ($canManage && (str_ends_with(strtolower($activeFile), '.html') || str_ends_with(strtolower($activeFile), '.htm'))) {
                SiteManager::decoupleAssets($slug, $activeFile);
                $site = SiteManager::getSite($slug);
            }

            $html = SiteManager::getSiteHtml($slug, $activeFile);
            $allHtmlFiles = SharedLayoutManager::findAllHtmlFiles($slug);

            render_view('studio', [
                'site' => $site,
                'html' => $html,
                'activeFile' => $activeFile,
                'htmlFiles' => $allHtmlFiles,
                'canManage' => $canManage,
                'title' => 'Visual Studio — ' . htmlspecialchars($site['title'])
            ], false); // Dedicated full-screen studio
            return;
        }

        // 8. Dashboard (Authentication Required)
        if ($path === 'dashboard') {
            if (!Auth::check()) {
                redirect('login?redirect=dashboard');
                return;
            }

            $sites = SiteManager::getUserSites();
            render_view('dashboard', [
                'sites' => $sites,
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

        // 9. Auth Routes
        if ($path === 'login') {
            if (Auth::check()) {
                redirect('dashboard');
                return;
            }

            $redirectTarget = $_REQUEST['redirect'] ?? 'dashboard';

            if ($method === 'POST') {
                $email = $_POST['email'] ?? '';
                $password = $_POST['password'] ?? '';
                $res = Auth::login($email, $password);
                if ($res['success']) {
                    $dest = !empty($_POST['redirect']) && !str_starts_with($_POST['redirect'], 'http') ? ltrim($_POST['redirect'], '/') : 'dashboard';
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
            if (Auth::check()) {
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
                    $dest = !empty($_POST['redirect']) && !str_starts_with($_POST['redirect'], 'http') ? ltrim($_POST['redirect'], '/') : 'dashboard';
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
                'title' => 'PageDock — Host your HTML. Get a link.'
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
     * Safely serve static files from sites/{slug}/ with Clean URLs, _redirects, and custom 404
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
                    header('Location: ' . url('s/' . $slug) . $qs, true, 301);
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
            $content = file_get_contents($filePath);

            // Layer 4 Grounding: ensure relative links (style.css, script.js, images/) resolve to the site directory
            $siteBase = url('s/' . $site['slug'] . '/');
            if (!preg_match('/<base\b[^>]*>/i', $content)) {
                if (stripos($content, '<head>') !== false) {
                    $content = str_ireplace('<head>', "<head>\n    <base href=\"{$siteBase}\">", $content);
                } elseif (stripos($content, '<head') !== false) {
                    $content = preg_replace('/(<head[^>]*>)/i', "$1\n    <base href=\"{$siteBase}\">", $content, 1);
                } else {
                    $content = "<base href=\"{$siteBase}\">\n" . $content;
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
                        var forms = document.querySelectorAll("form[action*=\"/__pd/form\"], form[action*=\"/api/forms\"], form[data-pd-form]");
                        forms.forEach(function(f){
                            if (!f.querySelector("input[name=\"_pd_hp\"]")) {
                                var hp = document.createElement("div");
                                hp.setAttribute("aria-hidden", "true");
                                hp.style.cssText = "position:absolute;left:-10000px;width:1px;height:1px;overflow:hidden";
                                hp.innerHTML = "<label>Leave this field empty <input type=\"text\" name=\"_pd_hp\" tabindex=\"-1\" autocomplete=\"off\"></label>";
                                f.appendChild(hp);
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

            // Check if request is an editor preview iframe
            $isEditorPreview = isset($_GET['preview']) || isset($_GET['t']) || 
                (!empty($_SERVER['HTTP_REFERER']) && (str_contains($_SERVER['HTTP_REFERER'], '/editor/') || str_contains($_SERVER['HTTP_REFERER'], '/studio/')));

            $harnessScript = '';
            if ($isEditorPreview) {
                // 1. Audio Muter & DOM Ready signal in <head>
                $headHarness = '<script data-htmlhost-ui>
                (function(){
                    window.addEventListener("DOMContentLoaded", function(){
                        try { window.parent.postMessage({ type: "buffer-dom-ready" }, "*"); } catch(e){}
                    });
                    try {
                        var obs = new MutationObserver(function(muts){
                            muts.forEach(function(m){
                                m.addedNodes.forEach(function(n){
                                    if (n.tagName === "AUDIO" || n.tagName === "VIDEO") n.muted = true;
                                });
                            });
                        });
                        obs.observe(document.documentElement, { childList: true, subtree: true });
                    } catch(e){}
                })();
                </script>';
                if (stripos($content, '<head>') !== false) {
                    $content = str_ireplace('<head>', "<head>\n" . $headHarness, $content);
                }

                // 2. Visual Inspector & Element Interaction Engine before </body>
                $harnessUrl = asset('assets/js/pagedock-harness.js');
                $harnessScript = '<script src="' . $harnessUrl . '"></script>';
                $badge = ''; // Suppress external badge inside editor preview
            }

            $injectedPayload = $formScript . $badge . $analyticsBeacon . $harnessScript;

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
}
