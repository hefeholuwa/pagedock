<?php
/**
 * PageDock Zero-Backend Forms Engine
 * Reconstructed strictly to match HTMLHost.co Master Specification Section 7:
 * - Universal relative endpoint (/__pd/form)
 * - Honeypot anti-spam trapping (_pd_hp)
 * - Form categories (_form)
 * - Control overrides (_success, _redirect, _error)
 * - Anti-phishing / credential harvesting protection
 * - CSV export
 */

class Forms {
    /**
     * Luhn algorithm check to detect credit card numbers in field values
     */
    public static function luhnCheck(string $number): bool {
        $sum = 0;
        $numDigits = strlen($number);
        $parity = $numDigits % 2;
        for ($i = 0; $i < $numDigits; $i++) {
            $digit = (int)$number[$i];
            if ($i % 2 === $parity) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }
            $sum += $digit;
        }
        return ($sum % 10) === 0;
    }

    /**
     * Check if form data contains prohibited credential or payment harvesting fields
     */
    public static function containsSensitiveCredentials(array $data): bool {
        $prohibitedKeys = [
            'password', 'passwd', 'pass', 'pwd', 'secret',
            'pin', 'cvv', 'cvc', 'ssn', 'social_security',
            'credit_card', 'card_number', 'cardnumber', 'cc_num',
            'iban', 'seed_phrase', 'private_key', 'wallet_key',
            'auth_token', 'access_token'
        ];

        foreach ($data as $k => $v) {
            $cleanKey = strtolower(trim((string)$k));
            foreach ($prohibitedKeys as $bad) {
                if (str_contains($cleanKey, $bad)) {
                    return true;
                }
            }

            // Inspect string values for possible credit card numbers (13-19 digits)
            if (is_string($v)) {
                $digitsOnly = preg_replace('/\D/', '', $v);
                if (strlen($digitsOnly) >= 13 && strlen($digitsOnly) <= 19) {
                    if (self::luhnCheck($digitsOnly)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Detect honeypot field population
     */
    public static function isHoneypotTriggered(array $data): bool {
        if (isset($data['_pd_hp']) && trim((string)$data['_pd_hp']) !== '') {
            return true;
        }
        if (isset($data['_hh_hp']) && trim((string)$data['_hh_hp']) !== '') {
            return true;
        }
        return false;
    }

    /**
     * Resolve site record from request context (Referer, Origin, or explicit parameter)
     */
    public static function resolveSiteFromRequest(array $postData = []): ?array {
        // 1. Explicit slug parameter if supplied
        if (!empty($postData['_site'])) {
            $s = SiteManager::getSite($postData['_site']);
            if ($s) return $s;
        }
        if (!empty($postData['slug'])) {
            $s = SiteManager::getSite($postData['slug']);
            if ($s) return $s;
        }

        // 2. Resolve from HTTP Referer
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        if ($referer) {
            $path = parse_url($referer, PHP_URL_PATH) ?? '';
            // Match /s/{slug}
            if (preg_match('#/s/([a-zA-Z0-9_\-]+)#', $path, $matches)) {
                $s = SiteManager::getSite($matches[1]);
                if ($s) return $s;
            }
        }

        // 3. Fallback to latest demo site if running in demo environment
        $db = Database::getConnection();
        $stmt = $db->query("SELECT * FROM sites WHERE title LIKE '%Demo%' ORDER BY id DESC LIMIT 1");
        $demo = $stmt ? $stmt->fetch() : null;
        if ($demo) return $demo;

        // Fallback to first available site
        $stmt = $db->query("SELECT * FROM sites ORDER BY id DESC LIMIT 1");
        return $stmt ? $stmt->fetch() : null;
    }

    /**
     * Store a form submission with HTMLHost.co compliant validation & controls
     */
    public static function submit(string $slug, array $postData): array {
        $site = SiteManager::getSite($slug);
        if (!$site && $slug === 'demo') {
            $site = self::resolveSiteFromRequest();
        }

        if (!$site) {
            return [
                'success' => false,
                'ok' => false,
                'error' => 'Site not found for form submission.',
                'status_code' => 404
            ];
        }

        // 1. Anti-Phishing Guard: Outright rejection of credentials or payment cards
        if (self::containsSensitiveCredentials($postData)) {
            return [
                'success' => false,
                'ok' => false,
                'error' => 'Submissions collecting passwords, PINs, payment details, or credentials are prohibited.',
                'status_code' => 400
            ];
        }

        // 2. Check Honeypot Anti-Spam Trap
        $isSpam = self::isHoneypotTriggered($postData) ? 1 : 0;

        // 3. Extract and sanitize control fields
        $formName = !empty($postData['_form']) ? trim((string)$postData['_form']) : 'Contact';
        $successMsg = !empty($postData['_success']) ? trim((string)$postData['_success']) : 'Thanks! Your message has been sent.';
        $errorMsg = !empty($postData['_error']) ? trim((string)$postData['_error']) : null;

        // Redirect URL validation: must start with single / and not external (// or http)
        $redirectUrl = null;
        if (!empty($postData['_redirect'])) {
            $rawRedir = trim((string)$postData['_redirect']);
            if (str_starts_with($rawRedir, '/') && !str_starts_with($rawRedir, '//') && !preg_match('#^https?://#i', $rawRedir)) {
                $redirectUrl = $rawRedir;
            }
        }

        // Clean internal and control parameters from stored submission payload
        $cleanedData = $postData;
        unset(
            $cleanedData['_token'],
            $cleanedData['_redirect'],
            $cleanedData['_success'],
            $cleanedData['_error'],
            $cleanedData['_form'],
            $cleanedData['_pd_hp'],
            $cleanedData['_hh_hp'],
            $cleanedData['_site'],
            $cleanedData['slug']
        );

        if (empty($cleanedData) && !$isSpam) {
            return [
                'success' => false,
                'ok' => false,
                'error' => $errorMsg ?: 'No form data submitted.',
                'status_code' => 400
            ];
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $json = json_encode($cleanedData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $db = Database::getConnection();
        $stmt = $db->prepare("INSERT INTO form_submissions (site_id, form_name, is_spam, data_json, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$site['id'], $formName, $isSpam, $json, $ip, $ua]);
        $subId = (int)$db->lastInsertId();

        // If honeypot caught a bot, return success response so the bot doesn't adapt, but keep it in spam
        return [
            'success' => true,
            'ok' => true,
            'message' => $successMsg,
            'redirect' => $redirectUrl,
            'submission_id' => $subId,
            'is_spam' => (bool)$isSpam
        ];
    }

    /**
     * Process universal form submission from /__pd/form
     */
    public static function handleUniversalSubmit(array $postData): array {
        $site = self::resolveSiteFromRequest($postData);
        if (!$site) {
            return [
                'success' => false,
                'ok' => false,
                'error' => 'Could not determine the target PageDock site. Post from a valid hosted site.',
                'status_code' => 404
            ];
        }

        return self::submit($site['slug'], $postData);
    }

    /**
     * Retrieve form submissions for a site
     * @param int $siteId
     * @param bool|null $onlySpam false = valid only, true = spam only, null = all
     */
    public static function getSubmissions(int $siteId, ?bool $onlySpam = false): array {
        $db = Database::getConnection();
        if ($onlySpam === null) {
            $sql = "SELECT * FROM form_submissions WHERE site_id = ? ORDER BY created_at DESC";
            $stmt = $db->prepare($sql);
            $stmt->execute([$siteId]);
        } else {
            $spamVal = $onlySpam ? 1 : 0;
            $sql = "SELECT * FROM form_submissions WHERE site_id = ? AND is_spam = ? ORDER BY created_at DESC";
            $stmt = $db->prepare($sql);
            $stmt->execute([$siteId, $spamVal]);
        }
        $rows = $stmt->fetchAll();

        foreach ($rows as &$row) {
            $row['data'] = json_decode($row['data_json'], true) ?: [];
        }

        return $rows;
    }

    /**
     * Generate RFC 4180 CSV export of form submissions for a site
     */
    public static function exportCsv(int $siteId, bool $includeSpam = false): string {
        $submissions = self::getSubmissions($siteId, $includeSpam ? null : false);
        if (empty($submissions)) {
            return "id,created_at,form_name,is_spam,ip_address\n";
        }

        // Collect all dynamic form field keys across all submissions
        $allKeys = [];
        foreach ($submissions as $sub) {
            foreach (array_keys($sub['data']) as $k) {
                if (!in_array($k, $allKeys, true)) {
                    $allKeys[] = $k;
                }
            }
        }

        $headers = array_merge(['id', 'created_at', 'form_name', 'is_spam', 'ip_address'], $allKeys);

        $out = fopen('php://temp', 'r+');
        fputcsv($out, $headers);

        foreach ($submissions as $sub) {
            $row = [
                $sub['id'],
                $sub['created_at'],
                $sub['form_name'],
                $sub['is_spam'] ? '1' : '0',
                $sub['ip_address']
            ];
            foreach ($allKeys as $k) {
                $val = $sub['data'][$k] ?? '';
                if (is_array($val)) {
                    $val = json_encode($val, JSON_UNESCAPED_UNICODE);
                }
                $row[] = (string)$val;
            }
            fputcsv($out, $row);
        }

        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return $csv;
    }

    /**
     * Get count statistics for a site's form submissions
     */
    public static function getSubmissionStats(int $siteId): array {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN is_spam = 0 THEN 1 ELSE 0 END) as valid,
                SUM(CASE WHEN is_spam = 1 THEN 1 ELSE 0 END) as spam
            FROM form_submissions 
            WHERE site_id = ?
        ");
        $stmt->execute([$siteId]);
        $row = $stmt->fetch();
        return [
            'total' => (int)($row['total'] ?? 0),
            'valid' => (int)($row['valid'] ?? 0),
            'spam' => (int)($row['spam'] ?? 0),
        ];
    }

    /**
     * Delete a single form submission
     */
    public static function deleteSubmission(int $id, ?int $siteId = null): bool {
        $db = Database::getConnection();
        if ($siteId !== null) {
            $stmt = $db->prepare("DELETE FROM form_submissions WHERE id = ? AND site_id = ?");
            return $stmt->execute([$id, $siteId]);
        }
        $stmt = $db->prepare("DELETE FROM form_submissions WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Toggle spam status of a submission
     */
    public static function toggleSpam(int $id, ?int $siteId = null): bool {
        $db = Database::getConnection();
        $sql = "UPDATE form_submissions SET is_spam = CASE WHEN is_spam = 1 THEN 0 ELSE 1 END WHERE id = ?";
        $params = [$id];
        if ($siteId !== null) {
            $sql .= " AND site_id = ?";
            $params[] = $siteId;
        }
        $stmt = $db->prepare($sql);
        return $stmt->execute($params);
    }
}
