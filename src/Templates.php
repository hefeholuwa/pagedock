<?php

class Templates {
    private static ?array $cachedIndex = null;

    /**
     * Get all curated templates
     */
    public static function getAll(): array {
        return self::getIndex();
    }

    /**
     * Get template by slug with full HTML content
     */
    public static function get(string $slug): ?array {
        $all = self::getIndex();
        if (!isset($all[$slug])) {
            return null;
        }

        $template = $all[$slug];
        $filePath = DATA_DIR . '/templates/' . ($template['file'] ?? ($slug . '.html'));

        if (file_exists($filePath)) {
            $template['html'] = file_get_contents($filePath);
        } else {
            $template['html'] = '<h1>' . htmlspecialchars($template['title']) . '</h1>';
        }

        return $template;
    }

    /**
     * Get list of unique categories
     */
    public static function getCategories(): array {
        $categories = [];
        foreach (self::getIndex() as $template) {
            $cat = $template['category'];
            if (!in_array($cat, $categories)) {
                $categories[] = $cat;
            }
        }
        sort($categories);
        return $categories;
    }

    /**
     * Load index from data/templates_index.json
     */
    private static function getIndex(): array {
        if (self::$cachedIndex !== null) {
            return self::$cachedIndex;
        }

        $indexPath = DATA_DIR . '/templates_index.json';
        if (file_exists($indexPath)) {
            $json = file_get_contents($indexPath);
            self::$cachedIndex = json_decode($json, true) ?: [];
        } else {
            self::$cachedIndex = [];
        }

        return self::$cachedIndex;
    }
}
