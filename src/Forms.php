<?php

class Forms {
    /**
     * Store a form submission from a hosted site
     */
    public static function submit(string $slug, array $postData): array {
        $site = SiteManager::getSite($slug);
        if (!$site) {
            return ['success' => false, 'error' => 'Site not found.'];
        }

        // Clean internal params
        unset($postData['_token'], $postData['_redirect']);

        if (empty($postData)) {
            return ['success' => false, 'error' => 'No form data submitted.'];
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $json = json_encode($postData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $db = Database::getConnection();
        $stmt = $db->prepare("INSERT INTO form_submissions (site_id, data_json, ip_address) VALUES (?, ?, ?)");
        $stmt->execute([$site['id'], $json, $ip]);

        return [
            'success' => true,
            'message' => 'Thank you! Your message has been received.',
            'submission_id' => $db->lastInsertId()
        ];
    }

    /**
     * Retrieve all form submissions for a site
     */
    public static function getSubmissions(int $siteId): array {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM form_submissions WHERE site_id = ? ORDER BY created_at DESC");
        $stmt->execute([$siteId]);
        $rows = $stmt->fetchAll();

        foreach ($rows as &$row) {
            $row['data'] = json_decode($row['data_json'], true) ?: [];
        }

        return $rows;
    }
}
