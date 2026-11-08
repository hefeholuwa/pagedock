<?php
/**
 * PageDock Multi-Page Layout Synchronization & Drift Engine
 * Implements HTMLHost.co Master Specification Section 6:
 * - HTML Marker Comments: <!-- pd:shared [name] --> and <!-- hh:shared [name] -->
 * - Normalization algorithm (comparable) stripping active classes, aria-current, and relative link depths
 * - Drift detection across multi-page static websites
 * - Automatic cross-page block propagation with route-aware active link injection
 */

class SharedLayoutManager {
    /**
     * Regex pattern matching opening and closing shared block comments
     */
    private const BLOCK_REGEX = '/<!--\s*(?:pd|hh):shared\s+([a-zA-Z0-9_-]+)(?:\s+([^>]*))?\s*-->([\s\S]*?)<!--\s*\/(?:pd|hh):shared\s+\1\s*-->/i';

    /**
     * Find all HTML files in a site directory (excluding .snapshots and OS metadata)
     */
    public static function findAllHtmlFiles(string $slug): array {
        $siteDir = realpath(SITES_DIR . '/' . $slug);
        if (!$siteDir || !is_dir($siteDir)) {
            return [];
        }

        $htmlFiles = [];
        self::scanHtmlRecursive($siteDir, $siteDir, $htmlFiles);
        sort($htmlFiles);
        return $htmlFiles;
    }

    private static function scanHtmlRecursive(string $rootDir, string $currentDir, array &$results): void {
        $items = scandir($currentDir);
        if (!$items) return;

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            if ($item === '.snapshots' || $item === '__MACOSX' || $item === '.git') continue;

            $fullPath = $currentDir . '/' . $item;
            if (is_dir($fullPath)) {
                self::scanHtmlRecursive($rootDir, $fullPath, $results);
            } else {
                $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
                if ($ext === 'html' || $ext === 'htm') {
                    $rel = ltrim(substr($fullPath, strlen($rootDir)), '/');
                    $results[] = $rel;
                }
            }
        }
    }

    /**
     * Parse all shared blocks from raw HTML content
     */
    public static function parseBlocks(string $html): array {
        $blocks = [];
        if (preg_match_all(self::BLOCK_REGEX, $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $name = strtolower(trim($m[1]));
                $attrRaw = trim($m[2] ?? '');
                $attributes = self::parseAttributes($attrRaw);
                $content = $m[3];

                $blocks[$name] = [
                    'name' => $name,
                    'attributes_raw' => $attrRaw,
                    'attributes' => $attributes,
                    'content' => $content,
                    'full_match' => $m[0]
                ];
            }
        }
        return $blocks;
    }

    /**
     * Parse attributes from marker comment e.g. active-class="active" aria-current="page"
     */
    public static function parseAttributes(string $attrStr): array {
        $attrs = [
            'active-class' => 'active',
            'aria-current' => 'page'
        ];

        if (empty($attrStr)) {
            return $attrs;
        }

        if (preg_match_all('/([a-zA-Z0-9_-]+)(?:=["\']([^"\']*)["\'])?/i', $attrStr, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $key = strtolower($m[1]);
                $val = isset($m[2]) ? $m[2] : 'true';
                $attrs[$key] = $val;
            }
        }

        return $attrs;
    }

    /**
     * Normalization Algorithm (comparable) matching HTMLHost Spec Section 6:
     * - Strips aria-current attributes
     * - Strips active classes (active, is-active, current-page, nav-active, etc.)
     * - Normalizes relative URLs across directory depths to root paths
     * - Collapses contiguous whitespace
     */
    public static function normalize(string $blockHtml, string $pageRelPath = '', array $options = []): string {
        $activeClass = strtolower($options['active-class'] ?? 'active');

        // 1. Strip aria-current attributes (e.g. aria-current="page", aria-current='true', aria-current)
        $clean = preg_replace('/\s*aria-current(?:=["\'][^"\']*["\'])?/i', '', $blockHtml);

        // 2. Strip active classes from class attributes
        $activePatterns = ['active', 'is-active', 'current', 'current-page', 'nav-active'];
        if ($activeClass && !in_array($activeClass, $activePatterns, true)) {
            $activePatterns[] = $activeClass;
        }

        $clean = preg_replace_callback('/\bclass=["\']([^"\']*)["\']/i', function ($matches) use ($activePatterns) {
            $classes = preg_split('/\s+/', trim($matches[1]));
            $filtered = [];
            foreach ($classes as $c) {
                if ($c === '') continue;
                if (!in_array(strtolower($c), $activePatterns, true)) {
                    $filtered[] = $c;
                }
            }
            if (empty($filtered)) {
                return '';
            }
            sort($filtered);
            return 'class="' . implode(' ', $filtered) . '"';
        }, $clean);

        // 3. Normalize link URLs to root-relative paths
        $depth = 0;
        if (!empty($pageRelPath)) {
            $dirname = dirname($pageRelPath);
            if ($dirname !== '.' && $dirname !== '') {
                $depth = substr_count($dirname, '/') + 1;
            }
        }

        $clean = preg_replace_callback('/\b(href|src)=["\']([^"\']*)["\']/i', function ($matches) use ($depth) {
            $attr = $matches[1];
            $url = $matches[2];

            // Ignore external URLs, anchors, mailto, tel, javascript
            if (preg_match('~^(https?://|//|mailto:|tel:|javascript:|#)~i', $url)) {
                return $matches[0];
            }

            // Resolve leading ../ relative paths
            $cleanUrl = $url;
            if (str_starts_with($cleanUrl, './')) {
                $cleanUrl = substr($cleanUrl, 2);
            }

            // If relative and depth > 0, resolve leading ../
            while (str_starts_with($cleanUrl, '../')) {
                $cleanUrl = substr($cleanUrl, 3);
            }

            // Ensure leading slash for root comparison
            $cleanUrl = '/' . ltrim($cleanUrl, '/');

            // Strip .html / .htm extension for clean comparison (e.g. /about.html -> /about)
            $cleanUrl = preg_replace('/\.html?$/i', '', $cleanUrl);
            if ($cleanUrl === '/index' || $cleanUrl === '') {
                $cleanUrl = '/';
            }

            return $attr . '="' . $cleanUrl . '"';
        }, $clean);

        // 4. Collapse whitespace & normalize tag delimiters
        $clean = preg_replace('/\s+/', ' ', trim($clean));
        $clean = preg_replace('/\s+>/', '>', $clean);
        $clean = preg_replace('/<\s+/', '<', $clean);

        return $clean;
    }

    /**
     * Inspect all HTML files in a site and analyze shared blocks and drift
     */
    public static function inspectSite(string $slug): array {
        $siteDir = realpath(SITES_DIR . '/' . $slug);
        if (!$siteDir || !is_dir($siteDir)) {
            return [
                'success' => false,
                'error' => 'Site directory not found.'
            ];
        }

        $files = self::findAllHtmlFiles($slug);
        $blockOccurrences = []; // [block_name => [relPath => parsedData]]

        foreach ($files as $relPath) {
            $fullPath = $siteDir . '/' . $relPath;
            $html = file_get_contents($fullPath);
            $parsed = self::parseBlocks($html);

            foreach ($parsed as $name => $data) {
                if (!isset($blockOccurrences[$name])) {
                    $blockOccurrences[$name] = [];
                }
                $normalized = self::normalize($data['content'], $relPath, $data['attributes']);
                $blockOccurrences[$name][$relPath] = [
                    'attributes' => $data['attributes'],
                    'attributes_raw' => $data['attributes_raw'],
                    'content' => $data['content'],
                    'normalized' => $normalized
                ];
            }
        }

        $blocksReport = [];
        $totalDriftedBlocks = 0;

        foreach ($blockOccurrences as $blockName => $pages) {
            // Count occurrences of each normalized hash to identify consensus
            $variants = [];
            foreach ($pages as $p => $info) {
                $hash = md5($info['normalized']);
                if (!isset($variants[$hash])) {
                    $variants[$hash] = [
                        'normalized' => $info['normalized'],
                        'pages' => [],
                        'sample_content' => $info['content'],
                        'sample_attributes' => $info['attributes'],
                        'sample_page' => $p
                    ];
                }
                $variants[$hash]['pages'][] = $p;
            }

            // Sort variants by frequency (majority is consensus)
            uasort($variants, fn($a, $b) => count($b['pages']) <=> count($a['pages']));
            $canonicalHash = array_key_first($variants);
            $canonical = $variants[$canonicalHash];

            $matchingCount = count($canonical['pages']);
            $totalCount = count($pages);
            $hasDrift = count($variants) > 1;

            if ($hasDrift) {
                $totalDriftedBlocks++;
                $driftedPages = [];
                foreach ($variants as $h => $v) {
                    if ($h !== $canonicalHash) {
                        $driftedPages = array_merge($driftedPages, $v['pages']);
                    }
                }
                $driftSummary = sprintf(
                    "The shared %s differs on %d page%s: %s (vs %d matching)",
                    $blockName,
                    count($driftedPages),
                    count($driftedPages) === 1 ? '' : 's',
                    implode(', ', $driftedPages),
                    $matchingCount
                );
            } else {
                $driftSummary = sprintf("All %d pages in sync for shared %s", $totalCount, $blockName);
            }

            $blocksReport[$blockName] = [
                'name' => $blockName,
                'total_pages' => $totalCount,
                'matching_pages_count' => $matchingCount,
                'has_drift' => $hasDrift,
                'drift_summary' => $driftSummary,
                'canonical_pages' => $canonical['pages'],
                'canonical_sample' => $canonical['sample_content'],
                'canonical_attributes' => $canonical['sample_attributes'],
                'pages' => array_keys($pages)
            ];
        }

        return [
            'success' => true,
            'slug' => $slug,
            'total_html_files' => count($files),
            'files' => $files,
            'has_drift' => $totalDriftedBlocks > 0,
            'drifted_blocks_count' => $totalDriftedBlocks,
            'blocks' => $blocksReport
        ];
    }

    /**
     * Synchronize a shared block across all HTML pages in the site
     */
    public static function syncBlock(string $slug, string $blockName, string $sourceContent, ?string $sourcePage = null, array $attributes = []): array {
        $site = SiteManager::getSite($slug);
        if (!$site) {
            return ['success' => false, 'error' => 'Site not found.'];
        }

        $siteDir = realpath(SITES_DIR . '/' . $slug);
        if (!$siteDir || !is_dir($siteDir)) {
            return ['success' => false, 'error' => 'Site directory not found.'];
        }

        $blockName = strtolower(trim($blockName));
        $files = self::findAllHtmlFiles($slug);

        $affectedPages = [];
        $activeClass = $attributes['active-class'] ?? 'active';
        $ariaCurrent = $attributes['aria-current'] ?? 'page';

        $searchPattern = '/<!--\s*(?:pd|hh):shared\s+' . preg_quote($blockName, '/') . '(?:\s+[^>]*)?\s*-->([\s\S]*?)<!--\s*\/(?:pd|hh):shared\s+' . preg_quote($blockName, '/') . '\s*-->/i';

        foreach ($files as $relPath) {
            $fullPath = $siteDir . '/' . $relPath;
            $html = file_get_contents($fullPath);

            if (!preg_match($searchPattern, $html, $existingMatch)) {
                continue;
            }

            // Derive target page canonical route e.g. /about or /
            $cleanRoute = '/' . preg_replace('/\.html?$/i', '', $relPath);
            if ($cleanRoute === '/index' || $cleanRoute === '') {
                $cleanRoute = '/';
            }

            // Generate customized block content for this specific page (active link classes)
            $customizedContent = self::tailorBlockForPage($sourceContent, $cleanRoute, $relPath, $activeClass, $ariaCurrent);

            // Construct new marker block preserving custom attributes if defined
            $attrStr = '';
            if ($activeClass !== 'active') $attrStr .= ' active-class="' . htmlspecialchars($activeClass) . '"';
            if ($ariaCurrent !== 'page') $attrStr .= ' aria-current="' . htmlspecialchars($ariaCurrent) . '"';

            $newBlock = "<!-- pd:shared {$blockName}{$attrStr} -->\n" . trim($customizedContent) . "\n<!-- /pd:shared {$blockName} -->";

            $updatedHtml = preg_replace($searchPattern, $newBlock, $html, 1);
            file_put_contents($fullPath, $updatedHtml);
            $affectedPages[] = $relPath;
        }

        if (empty($affectedPages)) {
            return [
                'success' => false,
                'error' => "No pages contained the shared block '{$blockName}'."
            ];
        }

        // Create immutable snapshot recording layout sync revision
        $summary = sprintf("Synchronized shared block '%s' across %d pages", $blockName, count($affectedPages));
        $newVersion = VersionManager::createSnapshot((int)$site['id'], $slug, $summary);

        return [
            'success' => true,
            'slug' => $slug,
            'block_name' => $blockName,
            'synced_pages' => $affectedPages,
            'synced_count' => count($affectedPages),
            'new_version' => $newVersion,
            'message' => $summary
        ];
    }

    /**
     * Tailor shared block HTML for a specific page:
     * - Injects active-class and aria-current on links matching the page's route
     * - Strips active markers from non-matching links
     */
    public static function tailorBlockForPage(string $blockHtml, string $pageRoute, string $relPath, string $activeClass, string $ariaCurrent): string {
        $tailored = $blockHtml;

        // Strip existing active classes and aria-current
        $activePatterns = ['active', 'is-active', 'current', 'current-page', 'nav-active'];
        if ($activeClass && !in_array($activeClass, $activePatterns, true)) {
            $activePatterns[] = $activeClass;
        }

        $tailored = preg_replace('/\s*aria-current(?:=["\'][^"\']*["\'])?/i', '', $tailored);

        $tailored = preg_replace_callback('/\bclass=["\']([^"\']*)["\']/i', function ($matches) use ($activePatterns) {
            $classes = preg_split('/\s+/', trim($matches[1]));
            $filtered = [];
            foreach ($classes as $c) {
                if ($c === '') continue;
                if (!in_array(strtolower($c), $activePatterns, true)) {
                    $filtered[] = $c;
                }
            }
            return empty($filtered) ? '' : 'class="' . implode(' ', $filtered) . '"';
        }, $tailored);

        // Match all <a> tags and evaluate href match with pageRoute
        $tailored = preg_replace_callback('/<a\b([^>]*)>/i', function ($matches) use ($pageRoute, $activeClass, $ariaCurrent) {
            $tagAttrs = $matches[1];

            if (!preg_match('/\bhref=["\']([^"\']*)["\']/i', $tagAttrs, $hrefMatch)) {
                return $matches[0];
            }

            $rawHref = $hrefMatch[1];
            // Normalize href path
            $normHref = preg_replace('/\.html?$/i', '', $rawHref);
            if ($normHref === '/index' || $normHref === 'index' || $normHref === '') {
                $normHref = '/';
            }
            if (!str_starts_with($normHref, '/') && !preg_match('~^(https?://|//|#|mailto:|tel:)~i', $normHref)) {
                $normHref = '/' . $normHref;
            }

            $isMatch = false;
            if ($pageRoute === '/' && ($normHref === '/' || $normHref === '/index')) {
                $isMatch = true;
            } elseif ($pageRoute !== '/' && $normHref === $pageRoute) {
                $isMatch = true;
            }

            if (!$isMatch) {
                return $matches[0];
            }

            // Append activeClass to existing class or add class="$activeClass"
            if (preg_match('/\bclass=["\']([^"\']*)["\']/i', $tagAttrs, $cm)) {
                $newClasses = trim($cm[1]) . ' ' . $activeClass;
                $newTagAttrs = preg_replace('/\bclass=["\'][^"\']*["\']/i', 'class="' . trim($newClasses) . '"', $tagAttrs);
            } else {
                $newTagAttrs = $tagAttrs . ' class="' . $activeClass . '"';
            }

            // Add aria-current
            $newTagAttrs .= ' aria-current="' . htmlspecialchars($ariaCurrent) . '"';

            return '<a' . $newTagAttrs . '>';
        }, $tailored);

        return $tailored;
    }
}
