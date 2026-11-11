<?php

class McpServer {
    private const SUPPORTED_PROTOCOLS = ['2025-06-18', '2025-03-26', '2024-11-05'];

    public static function sendCorsHeaders(): void {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Accept, Authorization, Mcp-Session-Id, Mcp-Protocol-Version');
        header('Access-Control-Expose-Headers: Mcp-Session-Id, WWW-Authenticate');
        header('Access-Control-Max-Age: 86400');
    }

    public static function handle(string $method): void {
        self::sendCorsHeaders();

        if ($method === 'OPTIONS') {
            http_response_code(204);
            exit;
        }

        // Require authenticated Bearer user (returns 401 + RFC 9728 WWW-Authenticate for OAuth discovery)
        $userId = Auth::id();
        if ($userId === null || Auth::hasInvalidBearer()) {
            $metadataUrl = OAuthServer::getBaseUrl() . '/.well-known/oauth-protected-resource';
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            header('WWW-Authenticate: Bearer realm="PageDock MCP", resource_metadata="' . $metadataUrl . '"');
            echo json_encode([
                'error' => 'unauthorized',
                'error_description' => 'Valid OAuth 2.1 Bearer access token or Personal Access Token required.',
            ]);
            exit;
        }

        if ($method === 'GET') {
            http_response_code(405);
            header('Allow: POST, DELETE, OPTIONS');
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Use HTTP POST for Streamable HTTP JSON-RPC requests.']);
            exit;
        }

        if ($method === 'DELETE') {
            json_response(['closed' => true]);
            return;
        }

        if ($method !== 'POST') {
            http_response_code(405);
            header('Allow: POST, DELETE, OPTIONS');
            exit;
        }

        $raw = file_get_contents('php://input');
        $payload = json_decode($raw ?: '', true);
        if (!is_array($payload)) {
            self::sendJsonRpc([
                'jsonrpc' => '2.0',
                'id' => null,
                'error' => ['code' => -32700, 'message' => 'Parse error: Invalid JSON'],
            ], 400);
            return;
        }

        $sessionId = $_SERVER['HTTP_MCP_SESSION_ID'] ?? ('pd_mcp_' . bin2hex(random_bytes(12)));
        header('Mcp-Session-Id: ' . $sessionId);

        // Distinguish batch array vs single JSON-RPC object
        $isBatch = array_is_list($payload);
        $messages = $isBatch ? $payload : [$payload];
        $responses = [];

        foreach ($messages as $msg) {
            if (!is_array($msg)) {
                $responses[] = [
                    'jsonrpc' => '2.0',
                    'id' => null,
                    'error' => ['code' => -32600, 'message' => 'Invalid Request'],
                ];
                continue;
            }
            $resp = self::dispatchMessage($msg);
            if ($resp !== null) {
                $responses[] = $resp;
            }
        }

        // Pure notifications (no response required) -> HTTP 202 Accepted
        if (empty($responses)) {
            http_response_code(202);
            exit;
        }

        self::sendJsonRpc($isBatch ? $responses : $responses[0], 200);
    }

    private static function dispatchMessage(array $msg): ?array {
        $hasId = array_key_exists('id', $msg);
        $id = $msg['id'] ?? null;
        $method = (string)($msg['method'] ?? '');
        $params = isset($msg['params']) && is_array($msg['params']) ? $msg['params'] : [];

        // Notifications without an id do not expect a JSON-RPC response
        if (!$hasId || str_starts_with($method, 'notifications/')) {
            return $hasId ? ['jsonrpc' => '2.0', 'id' => $id, 'result' => new stdClass()] : null;
        }

        if ($method === 'initialize') {
            $clientVersion = (string)($params['protocolVersion'] ?? '2025-03-26');
            $negotiated = in_array($clientVersion, self::SUPPORTED_PROTOCOLS, true)
                ? $clientVersion
                : '2025-03-26';

            return [
                'jsonrpc' => '2.0',
                'id' => $id,
                'result' => [
                    'protocolVersion' => $negotiated,
                    'capabilities' => [
                        'tools' => [
                            'listChanged' => false,
                        ],
                    ],
                    'serverInfo' => [
                        'name' => 'pagedock',
                        'title' => 'PageDock',
                        'version' => APP_VERSION,
                    ],
                    'instructions' => 'Manage and deploy static HTML websites on PageDock. Use list_sites to discover existing slugs, pull_site or get_site to inspect HTML/files, deploy_html to publish or overwrite a site, and update_site_file for surgical edits to index.html, CSS, or JS.',
                ],
            ];
        }

        if ($method === 'ping') {
            return [
                'jsonrpc' => '2.0',
                'id' => $id,
                'result' => new stdClass(),
            ];
        }

        if ($method === 'tools/list') {
            return [
                'jsonrpc' => '2.0',
                'id' => $id,
                'result' => [
                    'tools' => self::getToolDefinitions(),
                ],
            ];
        }

        if ($method === 'tools/call') {
            $name = (string)($params['name'] ?? '');
            $args = isset($params['arguments']) && is_array($params['arguments']) ? $params['arguments'] : [];
            $result = self::executeTool($name, $args);
            return [
                'jsonrpc' => '2.0',
                'id' => $id,
                'result' => $result,
            ];
        }

        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'error' => [
                'code' => -32601,
                'message' => "Method not found: {$method}",
            ],
        ];
    }

    public static function getToolDefinitions(): array {
        $emptyProps = new stdClass();
        return [
            [
                'name' => 'list_sites',
                'title' => 'List Hosted Sites',
                'description' => 'List all hosted sites on the authenticated user\'s PageDock account with slugs, titles, view counts, and live URLs.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => $emptyProps,
                ],
                'annotations' => [
                    'readOnlyHint' => true,
                    'destructiveHint' => false,
                    'idempotentHint' => true,
                    'openWorldHint' => false,
                ],
            ],
            [
                'name' => 'get_site',
                'title' => 'Get Site Metadata',
                'description' => 'Get full metadata, file list, size, and URLs for a specific hosted site by slug.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'slug' => ['type' => 'string', 'description' => 'Unique site slug identifier'],
                    ],
                    'required' => ['slug'],
                ],
                'annotations' => [
                    'readOnlyHint' => true,
                    'destructiveHint' => false,
                    'idempotentHint' => true,
                    'openWorldHint' => false,
                ],
            ],
            [
                'name' => 'list_site_files',
                'title' => 'List Site Files',
                'description' => 'List all relative file paths inside a hosted PageDock site.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'slug' => ['type' => 'string', 'description' => 'Unique site slug identifier'],
                    ],
                    'required' => ['slug'],
                ],
                'annotations' => [
                    'readOnlyHint' => true,
                    'destructiveHint' => false,
                    'idempotentHint' => true,
                    'openWorldHint' => false,
                ],
            ],
            [
                'name' => 'pull_site',
                'title' => 'Pull Site Source',
                'description' => 'Read the raw HTML or text file source code of a hosted PageDock site for inspection or editing.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'slug' => ['type' => 'string', 'description' => 'Unique site slug identifier'],
                        'path' => ['type' => 'string', 'description' => 'Relative file path inside the site (default: index.html)'],
                    ],
                    'required' => ['slug'],
                ],
                'annotations' => [
                    'readOnlyHint' => true,
                    'destructiveHint' => false,
                    'idempotentHint' => true,
                    'openWorldHint' => false,
                ],
            ],
            [
                'name' => 'deploy_html',
                'title' => 'Deploy HTML Website',
                'description' => 'Publish a new single-file HTML website or update an existing site on PageDock. Returns the live preview URL and editor URL.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'html' => ['type' => 'string', 'description' => 'Complete HTML document markup to deploy'],
                        'title' => ['type' => 'string', 'description' => 'Human-friendly title for the site'],
                        'slug' => ['type' => 'string', 'description' => 'Preferred slug for a new site, or existing owned slug to update'],
                    ],
                    'required' => ['html'],
                ],
                'annotations' => [
                    'readOnlyHint' => false,
                    'destructiveHint' => false,
                    'idempotentHint' => false,
                    'openWorldHint' => false,
                ],
            ],
            [
                'name' => 'update_site_file',
                'title' => 'Update Site File',
                'description' => 'Update or create a specific file (HTML, CSS, JS, etc.) on an owned PageDock site, creating an immutable version snapshot.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'slug' => ['type' => 'string', 'description' => 'Unique site slug identifier'],
                        'path' => ['type' => 'string', 'description' => 'Relative file path to update (default: index.html)'],
                        'content' => ['type' => 'string', 'description' => 'Full text/code content for the file'],
                        'summary' => ['type' => 'string', 'description' => 'Optional version history change summary'],
                    ],
                    'required' => ['slug', 'content'],
                ],
                'annotations' => [
                    'readOnlyHint' => false,
                    'destructiveHint' => false,
                    'idempotentHint' => true,
                    'openWorldHint' => false,
                ],
            ],
            [
                'name' => 'list_versions',
                'title' => 'List Site Versions',
                'description' => 'List historical immutable version snapshots for a PageDock site.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'slug' => ['type' => 'string', 'description' => 'Unique site slug identifier'],
                    ],
                    'required' => ['slug'],
                ],
                'annotations' => [
                    'readOnlyHint' => true,
                    'destructiveHint' => false,
                    'idempotentHint' => true,
                    'openWorldHint' => false,
                ],
            ],
            [
                'name' => 'rollback_site',
                'title' => 'Rollback Site Version',
                'description' => 'Restore an owned site to a previous version snapshot (defaults to immediate previous version if omitted).',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'slug' => ['type' => 'string', 'description' => 'Unique site slug identifier'],
                        'version' => ['type' => 'integer', 'description' => 'Optional target version number'],
                    ],
                    'required' => ['slug'],
                ],
                'annotations' => [
                    'readOnlyHint' => false,
                    'destructiveHint' => false,
                    'idempotentHint' => true,
                    'openWorldHint' => false,
                ],
            ],
            [
                'name' => 'get_site_analytics',
                'title' => 'Get Site Analytics',
                'description' => 'Get visitor views, unique visitors, referrers, and device metrics for an owned PageDock site.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'slug' => ['type' => 'string', 'description' => 'Unique site slug identifier'],
                        'days' => ['type' => 'integer', 'description' => 'Lookback window in days (default: 30)'],
                    ],
                    'required' => ['slug'],
                ],
                'annotations' => [
                    'readOnlyHint' => true,
                    'destructiveHint' => false,
                    'idempotentHint' => true,
                    'openWorldHint' => false,
                ],
            ],
            [
                'name' => 'list_form_submissions',
                'title' => 'List Form Submissions',
                'description' => 'List contact form leads captured from an owned PageDock site.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'slug' => ['type' => 'string', 'description' => 'Unique site slug identifier'],
                        'isSpam' => ['type' => 'boolean', 'description' => 'Filter by spam flag (default: false)'],
                    ],
                    'required' => ['slug'],
                ],
                'annotations' => [
                    'readOnlyHint' => true,
                    'destructiveHint' => false,
                    'idempotentHint' => true,
                    'openWorldHint' => false,
                ],
            ],
            [
                'name' => 'delete_site',
                'title' => 'Delete Site',
                'description' => 'Permanently delete an owned hosted site from PageDock.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'slug' => ['type' => 'string', 'description' => 'Unique site slug identifier to delete'],
                    ],
                    'required' => ['slug'],
                ],
                'annotations' => [
                    'readOnlyHint' => false,
                    'destructiveHint' => true,
                    'idempotentHint' => false,
                    'openWorldHint' => false,
                ],
            ],
        ];
    }

    private static function executeTool(string $name, array $args): array {
        try {
            switch ($name) {
                case 'list_sites': {
                    $rawSites = SiteManager::getUserSites();
                    $sites = array_map(function ($site) {
                        $slug = $site['slug'];
                        return [
                            'slug' => $slug,
                            'title' => $site['title'] ?: $slug,
                            'entry_file' => $site['entry_file'] ?: 'index.html',
                            'file_count' => (int)($site['file_count'] ?? 1),
                            'total_size_bytes' => (int)($site['total_size_bytes'] ?? 0),
                            'views' => (int)($site['views_count'] ?? 0),
                            'forms_count' => (int)($site['forms_count'] ?? 0),
                            'current_version' => (int)($site['current_version'] ?? 1),
                            'updated_at' => $site['updated_at'] ?? null,
                            'liveUrl' => url('s/' . $slug . '/'),
                            'editorUrl' => url('editor/' . $slug),
                        ];
                    }, $rawSites);

                    return self::toolOk(['success' => true, 'count' => count($sites), 'sites' => $sites]);
                }

                case 'get_site': {
                    $slug = trim((string)($args['slug'] ?? ''));
                    $site = self::requireManagedSite($slug);
                    $files = SiteManager::getSiteFileList($slug);
                    return self::toolOk([
                        'success' => true,
                        'site' => [
                            'slug' => $site['slug'],
                            'title' => $site['title'] ?: $site['slug'],
                            'entry_file' => $site['entry_file'] ?: 'index.html',
                            'file_count' => (int)($site['file_count'] ?? 1),
                            'total_size_bytes' => (int)($site['total_size_bytes'] ?? 0),
                            'views' => (int)($site['views_count'] ?? 0),
                            'current_version' => (int)($site['current_version'] ?? 1),
                            'files' => $files,
                            'liveUrl' => url('s/' . $site['slug'] . '/'),
                            'editorUrl' => url('editor/' . $site['slug']),
                        ],
                    ]);
                }

                case 'list_site_files': {
                    $slug = trim((string)($args['slug'] ?? ''));
                    self::requireManagedSite($slug);
                    $files = SiteManager::getSiteFileList($slug);
                    return self::toolOk(['success' => true, 'slug' => $slug, 'files' => $files]);
                }

                case 'pull_site': {
                    $slug = trim((string)($args['slug'] ?? ''));
                    $path = trim((string)($args['path'] ?? 'index.html')) ?: 'index.html';
                    self::requireManagedSite($slug);
                    $content = SiteManager::getSiteHtml($slug, $path);
                    if ($content === null) {
                        return self::toolError("File '{$path}' not found on site '{$slug}'.");
                    }
                    return [
                        'content' => [
                            ['type' => 'text', 'text' => $content],
                        ],
                    ];
                }

                case 'deploy_html': {
                    $html = (string)($args['html'] ?? '');
                    if (trim($html) === '') {
                        return self::toolError('html parameter is required and cannot be empty.');
                    }
                    $title = isset($args['title']) ? (string)$args['title'] : null;
                    $slug = isset($args['slug']) && trim((string)$args['slug']) !== '' ? trim((string)$args['slug']) : null;
                    $res = SiteManager::deployFromHtml($html, $title, $slug);
                    if (empty($res['success'])) {
                        return self::toolError($res['error'] ?? 'Failed to deploy site.');
                    }
                    $finalSlug = $res['slug'];
                    return self::toolOk([
                        'success' => true,
                        'slug' => $finalSlug,
                        'title' => $res['title'] ?? $finalSlug,
                        'updated' => !empty($res['updated']),
                        'version' => $res['version'] ?? 1,
                        'liveUrl' => url('s/' . $finalSlug . '/'),
                        'editorUrl' => url('editor/' . $finalSlug),
                    ]);
                }

                case 'update_site_file': {
                    $slug = trim((string)($args['slug'] ?? ''));
                    $path = ltrim(trim((string)($args['path'] ?? 'index.html')) ?: 'index.html', '/');
                    $content = (string)($args['content'] ?? '');
                    $summary = trim((string)($args['summary'] ?? '')) ?: 'Updated via ChatGPT MCP';
                    self::requireManagedSite($slug);
                    $res = SiteManager::updateSiteFiles($slug, [$path => $content], $summary);
                    if (empty($res['success'])) {
                        return self::toolError($res['error'] ?? 'Failed to update file.');
                    }
                    return self::toolOk([
                        'success' => true,
                        'slug' => $slug,
                        'path' => $path,
                        'version' => $res['version'] ?? null,
                        'liveUrl' => url('s/' . $slug . '/'),
                    ]);
                }

                case 'list_versions': {
                    $slug = trim((string)($args['slug'] ?? ''));
                    $site = self::requireManagedSite($slug);
                    $versions = VersionManager::getVersions((int)$site['id']);
                    return self::toolOk([
                        'success' => true,
                        'slug' => $slug,
                        'current_version' => (int)($site['current_version'] ?? 1),
                        'versions' => $versions,
                    ]);
                }

                case 'rollback_site': {
                    $slug = trim((string)($args['slug'] ?? ''));
                    $site = self::requireManagedSite($slug);
                    $targetVersion = isset($args['version']) ? (int)$args['version'] : 0;
                    if ($targetVersion <= 0) {
                        $current = (int)($site['current_version'] ?? 1);
                        $targetVersion = max(1, $current - 1);
                    }
                    $res = VersionManager::restoreVersion($site, $targetVersion);
                    if (empty($res['success'])) {
                        return self::toolError($res['error'] ?? 'Failed to rollback site.');
                    }
                    return self::toolOk($res);
                }

                case 'get_site_analytics': {
                    $slug = trim((string)($args['slug'] ?? ''));
                    $days = isset($args['days']) ? max(1, (int)$args['days']) : 30;
                    $site = self::requireManagedSite($slug);
                    $data = Analytics::getSiteAnalytics((int)$site['id'], $days);
                    return self::toolOk(['success' => true, 'slug' => $slug, 'analytics' => $data]);
                }

                case 'list_form_submissions': {
                    $slug = trim((string)($args['slug'] ?? ''));
                    $site = self::requireManagedSite($slug);
                    $isSpam = array_key_exists('isSpam', $args) ? (bool)$args['isSpam'] : false;
                    $submissions = Forms::getSubmissions((int)$site['id'], $isSpam);
                    return self::toolOk([
                        'success' => true,
                        'slug' => $slug,
                        'isSpam' => $isSpam,
                        'count' => count($submissions),
                        'submissions' => $submissions,
                    ]);
                }

                case 'delete_site': {
                    $slug = trim((string)($args['slug'] ?? ''));
                    self::requireManagedSite($slug);
                    $res = SiteManager::deleteSite($slug);
                    if (empty($res['success'])) {
                        return self::toolError($res['error'] ?? 'Failed to delete site.');
                    }
                    return self::toolOk($res);
                }

                default:
                    return self::toolError("Unknown tool: {$name}");
            }
        } catch (Throwable $e) {
            return self::toolError($e->getMessage());
        }
    }

    private static function requireManagedSite(string $slug): array {
        if ($slug === '') {
            throw new RuntimeException('Site slug is required.');
        }
        $site = SiteManager::getSite($slug);
        if (!$site) {
            throw new RuntimeException("Site '{$slug}' not found.");
        }
        if (!SiteManager::canManage($site)) {
            throw new RuntimeException("You do not have permission to access site '{$slug}'.");
        }
        return $site;
    }

    private static function toolOk(array $data): array {
        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                ],
            ],
        ];
    }

    private static function toolError(string $message): array {
        return [
            'isError' => true,
            'content' => [
                ['type' => 'text', 'text' => $message],
            ],
        ];
    }

    private static function sendJsonRpc(array $body, int $status = 200): void {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($body, JSON_UNESCAPED_SLASHES);
        exit;
    }
}

