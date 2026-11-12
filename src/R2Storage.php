<?php
/**
 * PageDock Cloudflare R2 S3-Compatible Storage Engine
 * Zero third-party dependencies (implements AWS Signature Version 4 via pure PHP curl and hash_hmac).
 */
class R2Storage {
    /**
     * Check if Cloudflare R2 credentials are fully configured
     */
    public static function isConfigured(): bool {
        return !empty(R2_ACCOUNT_ID) &&
               !empty(R2_ACCESS_KEY_ID) &&
               !empty(R2_SECRET_ACCESS_KEY) &&
               !empty(R2_BUCKET);
    }

    /**
     * Upload an object to Cloudflare R2
     */
    public static function putObject(string $key, string $data, string $contentType = 'application/octet-stream'): bool {
        if (!self::isConfigured()) return false;

        $key = ltrim($key, '/');
        $res = self::sendRequest('PUT', '/' . R2_BUCKET . '/' . $key, [], $data, $contentType);
        return $res['status'] >= 200 && $res['status'] < 300;
    }

    /**
     * Download an object from Cloudflare R2
     */
    public static function getObject(string $key): ?string {
        if (!self::isConfigured()) return null;

        $key = ltrim($key, '/');
        $res = self::sendRequest('GET', '/' . R2_BUCKET . '/' . $key);
        if ($res['status'] === 200) {
            return $res['body'];
        }
        return null;
    }

    /**
     * Delete an object from Cloudflare R2
     */
    public static function deleteObject(string $key): bool {
        if (!self::isConfigured()) return false;

        $key = ltrim($key, '/');
        $res = self::sendRequest('DELETE', '/' . R2_BUCKET . '/' . $key);
        return $res['status'] >= 200 && $res['status'] < 300;
    }

    /**
     * List object keys matching a prefix from Cloudflare R2
     */
    public static function listObjects(string $prefix = ''): array {
        if (!self::isConfigured()) return [];

        $query = ['list-type' => '2'];
        if ($prefix !== '') {
            $query['prefix'] = ltrim($prefix, '/');
        }

        $res = self::sendRequest('GET', '/' . R2_BUCKET, $query);
        if ($res['status'] !== 200) {
            return [];
        }

        $keys = [];
        if (preg_match_all('#<Key>(.*?)</Key>#', $res['body'], $matches)) {
            foreach ($matches[1] as $k) {
                $keys[] = htmlspecialchars_decode($k);
            }
        }
        return $keys;
    }

    /**
     * Delete all objects matching a prefix from Cloudflare R2
     */
    public static function deletePrefix(string $prefix): int {
        if (!self::isConfigured()) return 0;

        $keys = self::listObjects($prefix);
        $deleted = 0;
        foreach ($keys as $key) {
            if (self::deleteObject($key)) {
                $deleted++;
            }
        }
        return $deleted;
    }

    /**
     * Synchronize a local directory to Cloudflare R2 under a specified prefix
     */
    public static function syncDirectoryToR2(string $localDir, string $remotePrefix): int {
        if (!self::isConfigured() || !is_dir($localDir)) return 0;

        $localDir = rtrim($localDir, '/');
        $remotePrefix = trim($remotePrefix, '/');

        $uploaded = 0;
        $iter = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($localDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

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

        foreach ($iter as $file) {
            if ($file->isFile()) {
                $path = $file->getPathname();
                $rel = str_replace('\\', '/', substr($path, strlen($localDir) + 1));
                if (str_starts_with($rel, '.') || str_starts_with($rel, '__MACOSX')) {
                    continue;
                }

                $ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
                $contentType = $mimeTypes[$ext] ?? 'application/octet-stream';
                $data = file_get_contents($path);

                $targetKey = $remotePrefix !== '' ? ($remotePrefix . '/' . $rel) : $rel;
                if (self::putObject($targetKey, $data, $contentType)) {
                    $uploaded++;
                }
            }
        }

        return $uploaded;
    }

    /**
     * Synchronize remote objects from Cloudflare R2 to a local directory
     */
    public static function syncR2ToDirectory(string $remotePrefix, string $localDir): int {
        if (!self::isConfigured()) return 0;

        $remotePrefix = trim($remotePrefix, '/');
        $localDir = rtrim($localDir, '/');

        $keys = self::listObjects($remotePrefix);
        $downloaded = 0;

        foreach ($keys as $key) {
            $rel = substr($key, strlen($remotePrefix));
            $rel = ltrim($rel, '/');
            if ($rel === '') continue;

            $data = self::getObject($key);
            if ($data !== null) {
                $targetFile = $localDir . '/' . $rel;
                $targetDir = dirname($targetFile);
                if (!is_dir($targetDir)) {
                    mkdir($targetDir, 0755, true);
                }
                file_put_contents($targetFile, $data);
                $downloaded++;
            }
        }

        return $downloaded;
    }

    /**
     * Send an AWS Signature Version 4 signed HTTP request to Cloudflare R2
     */
    private static function sendRequest(string $method, string $path, array $queryParams = [], string $payload = '', ?string $contentType = null): array {
        $host = R2_ACCOUNT_ID . '.r2.cloudflarestorage.com';
        $region = 'auto';
        $service = 's3';

        $now = time();
        $amzDate = gmdate('Ymd\THis\Z', $now);
        $dateStamp = gmdate('Ymd', $now);

        $payloadHash = hash('sha256', $payload);

        // Normalize path
        $canonicalUri = '/' . ltrim($path, '/');

        // Query string sorting
        ksort($queryParams);
        $queryParts = [];
        foreach ($queryParams as $k => $v) {
            $queryParts[] = rawurlencode((string)$k) . '=' . rawurlencode((string)$v);
        }
        $canonicalQueryString = implode('&', $queryParts);

        // Canonical headers
        $canonicalHeaders = [];
        $signedHeadersList = [];

        if ($contentType !== null) {
            $canonicalHeaders['content-type'] = strtolower(trim($contentType));
            $signedHeadersList[] = 'content-type';
        }

        $canonicalHeaders['host'] = $host;
        $signedHeadersList[] = 'host';

        $canonicalHeaders['x-amz-content-sha256'] = $payloadHash;
        $signedHeadersList[] = 'x-amz-content-sha256';

        $canonicalHeaders['x-amz-date'] = $amzDate;
        $signedHeadersList[] = 'x-amz-date';

        ksort($canonicalHeaders);
        sort($signedHeadersList);

        $canonicalHeadersStr = '';
        foreach ($canonicalHeaders as $hKey => $hVal) {
            $canonicalHeadersStr .= $hKey . ':' . $hVal . "\n";
        }
        $signedHeadersStr = implode(';', $signedHeadersList);

        // Canonical Request
        $canonicalRequest = $method . "\n"
            . $canonicalUri . "\n"
            . $canonicalQueryString . "\n"
            . $canonicalHeadersStr . "\n"
            . $signedHeadersStr . "\n"
            . $payloadHash;

        // String to Sign
        $algorithm = 'AWS4-HMAC-SHA256';
        $credentialScope = $dateStamp . '/' . $region . '/' . $service . '/aws4_request';
        $stringToSign = $algorithm . "\n"
            . $amzDate . "\n"
            . $credentialScope . "\n"
            . hash('sha256', $canonicalRequest);

        // Signing Key Derivation
        $kDate = hash_hmac('sha256', $dateStamp, 'AWS4' . R2_SECRET_ACCESS_KEY, true);
        $kRegion = hash_hmac('sha256', $region, $kDate, true);
        $kService = hash_hmac('sha256', $service, $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);

        // Signature Calculation
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);

        // Authorization Header
        $authHeader = $algorithm . ' '
            . 'Credential=' . R2_ACCESS_KEY_ID . '/' . $credentialScope . ', '
            . 'SignedHeaders=' . $signedHeadersStr . ', '
            . 'Signature=' . $signature;

        $url = 'https://' . $host . $canonicalUri;
        if ($canonicalQueryString !== '') {
            $url .= '?' . $canonicalQueryString;
        }

        $httpHeaders = [
            'Host: ' . $host,
            'x-amz-date: ' . $amzDate,
            'x-amz-content-sha256: ' . $payloadHash,
            'Authorization: ' . $authHeader,
        ];
        if ($contentType !== null) {
            $httpHeaders[] = 'Content-Type: ' . $contentType;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $httpHeaders);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

        if ($method === 'PUT' || $method === 'POST') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }

        $responseBody = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'status' => (int)$statusCode,
            'body'   => is_string($responseBody) ? $responseBody : ''
        ];
    }
}
