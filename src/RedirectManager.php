<?php
/**
 * PageDock Redirects Engine
 * Parses Netlify-style _redirects files and matches incoming requests.
 */

class RedirectManager {
    /**
     * Parse a _redirects file into structured rules
     */
    public static function parse(string $siteDir): array {
        $file = $siteDir . '/_redirects';
        if (!is_file($file)) {
            return [];
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $rules = [];

        foreach ($lines as $line) {
            $line = trim($line);
            // Skip comments and empty lines
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            // Syntax: <from> <to> [status]
            $parts = preg_split('/\s+/', $line);
            if (count($parts) < 2) {
                continue;
            }

            $from = $parts[0];
            $to = $parts[1];
            $status = isset($parts[2]) ? (int)$parts[2] : 301;
            if ($status !== 302 && $status !== 301) {
                $status = 301;
            }

            // Parse optional query params from <from> e.g. /?p=123
            $fromPath = $from;
            $requiredQuery = [];
            if (str_contains($from, '?')) {
                [$fromPath, $queryStr] = explode('?', $from, 2);
                parse_str($queryStr, $requiredQuery);
            }

            $rules[] = [
                'from_raw' => $from,
                'from_path' => '/' . ltrim($fromPath, '/'),
                'to' => $to,
                'status' => $status,
                'is_wildcard' => str_ends_with($fromPath, '/*'),
                'required_query' => $requiredQuery
            ];
        }

        return $rules;
    }

    /**
     * Match a requested path and query params against site redirect rules
     */
    public static function match(array $rules, string $requestedPath, array $queryParams = []): ?array {
        $cleanPath = '/' . ltrim($requestedPath, '/');

        foreach ($rules as $rule) {
            // Check required query parameters if any
            if (!empty($rule['required_query'])) {
                $queryMatches = true;
                foreach ($rule['required_query'] as $k => $v) {
                    if (!isset($queryParams[$k]) || (string)$queryParams[$k] !== (string)$v) {
                        $queryMatches = false;
                        break;
                    }
                }
                if (!$queryMatches) {
                    continue;
                }
            }

            // 1. Wildcard match e.g. /blog/* -> /news/*
            if ($rule['is_wildcard']) {
                $prefix = rtrim($rule['from_path'], '*'); // e.g. /blog/
                if (str_starts_with($cleanPath, $prefix)) {
                    $splat = substr($cleanPath, strlen($prefix));
                    $target = $rule['to'];
                    if (str_ends_with($target, '/*')) {
                        $target = rtrim($target, '*') . $splat;
                    }
                    return [
                        'target' => $target,
                        'status' => $rule['status']
                    ];
                }
            }

            // 2. Exact path match
            if ($rule['from_path'] === $cleanPath) {
                return [
                    'target' => $rule['to'],
                    'status' => $rule['status']
                ];
            }
        }

        return null;
    }
}
