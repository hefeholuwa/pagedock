<?php

class Database {
    private static ?PDO $instance = null;

    public static function getConnection(): PDO {
        if (self::$instance === null) {
            $driver = defined('DB_DRIVER') ? strtolower(DB_DRIVER) : 'sqlite';

            if ($driver === 'mysql') {
                $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
                self::initMysqlSchema();
            } else {
                $dir = dirname(DB_PATH);
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }

                self::$instance = new PDO('sqlite:' . DB_PATH);
                self::$instance->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                self::$instance->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                
                // Enable foreign keys & WAL mode for high performance
                self::$instance->exec('PRAGMA foreign_keys = ON;');
                self::$instance->exec('PRAGMA journal_mode = WAL;');
                self::$instance->exec('PRAGMA busy_timeout = 5000;');

                self::initSqliteSchema();
            }
        }

        return self::$instance;
    }

    private static function initSqliteSchema(): void {
        $sql = "
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT UNIQUE NOT NULL,
            password_hash TEXT NOT NULL,
            name TEXT,
            plan TEXT DEFAULT 'free',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS sites (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NULL,
            claim_token TEXT NOT NULL,
            slug TEXT UNIQUE NOT NULL,
            title TEXT,
            entry_file TEXT DEFAULT 'index.html',
            file_count INTEGER DEFAULT 1,
            total_size_bytes INTEGER DEFAULT 0,
            expires_at DATETIME NULL,
            views_count INTEGER DEFAULT 0,
            current_version INTEGER DEFAULT 1,
            webhook_url TEXT DEFAULT NULL,
            webhook_format TEXT DEFAULT 'generic',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        );

        CREATE INDEX IF NOT EXISTS idx_sites_slug ON sites(slug);
        CREATE INDEX IF NOT EXISTS idx_sites_user ON sites(user_id);
        CREATE INDEX IF NOT EXISTS idx_sites_token ON sites(claim_token);

        CREATE TABLE IF NOT EXISTS site_versions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            site_id INTEGER NOT NULL,
            version_number INTEGER NOT NULL,
            file_count INTEGER DEFAULT 1,
            total_size_bytes INTEGER DEFAULT 0,
            summary TEXT DEFAULT 'Published deployment',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE
        );

        CREATE INDEX IF NOT EXISTS idx_versions_site ON site_versions(site_id, version_number);

        CREATE TABLE IF NOT EXISTS form_submissions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            site_id INTEGER NOT NULL,
            form_name TEXT DEFAULT 'Contact',
            is_spam INTEGER DEFAULT 0,
            data_json TEXT NOT NULL,
            ip_address TEXT,
            user_agent TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE
        );

        CREATE INDEX IF NOT EXISTS idx_forms_site ON form_submissions(site_id);

        CREATE TABLE IF NOT EXISTS analytics_events (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            site_id INTEGER NOT NULL,
            path TEXT DEFAULT '/',
            visitor_hash TEXT NOT NULL,
            referrer TEXT,
            referrer_host TEXT,
            device_type TEXT DEFAULT 'desktop',
            country_code TEXT DEFAULT 'XX',
            is_unique INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE
        );

        CREATE INDEX IF NOT EXISTS idx_analytics_site_created ON analytics_events(site_id, created_at);
        CREATE INDEX IF NOT EXISTS idx_analytics_visitor_date ON analytics_events(site_id, visitor_hash, created_at);
        ";

        self::$instance->exec($sql);

        // Run migrations for existing SQLite databases before indexing added columns
        self::runSqliteMigrations();

        self::$instance->exec("CREATE INDEX IF NOT EXISTS idx_forms_spam ON form_submissions(is_spam);");
    }

    private static function runSqliteMigrations(): void {
        $cols = self::$instance->query("PRAGMA table_info(form_submissions)")->fetchAll(PDO::FETCH_COLUMN, 1);
        if (!in_array('form_name', $cols)) {
            self::$instance->exec("ALTER TABLE form_submissions ADD COLUMN form_name TEXT DEFAULT 'Contact'");
        }
        if (!in_array('is_spam', $cols)) {
            self::$instance->exec("ALTER TABLE form_submissions ADD COLUMN is_spam INTEGER DEFAULT 0");
        }
        if (!in_array('user_agent', $cols)) {
            self::$instance->exec("ALTER TABLE form_submissions ADD COLUMN user_agent TEXT DEFAULT NULL");
        }

        // Ensure analytics_events table exists
        self::$instance->exec("
            CREATE TABLE IF NOT EXISTS analytics_events (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                site_id INTEGER NOT NULL,
                path TEXT DEFAULT '/',
                visitor_hash TEXT NOT NULL,
                referrer TEXT,
                referrer_host TEXT,
                device_type TEXT DEFAULT 'desktop',
                country_code TEXT DEFAULT 'XX',
                is_unique INTEGER DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE
            );
            CREATE INDEX IF NOT EXISTS idx_analytics_site_created ON analytics_events(site_id, created_at);
            CREATE INDEX IF NOT EXISTS idx_analytics_visitor_date ON analytics_events(site_id, visitor_hash, created_at);
        ");

        // Ensure site_versions table and current_version column exist
        $siteCols = self::$instance->query("PRAGMA table_info(sites)")->fetchAll(PDO::FETCH_COLUMN, 1);
        if (!in_array('current_version', $siteCols)) {
            self::$instance->exec("ALTER TABLE sites ADD COLUMN current_version INTEGER DEFAULT 1");
        }
        if (!in_array('webhook_url', $siteCols)) {
            self::$instance->exec("ALTER TABLE sites ADD COLUMN webhook_url TEXT DEFAULT NULL");
        }
        if (!in_array('webhook_format', $siteCols)) {
            self::$instance->exec("ALTER TABLE sites ADD COLUMN webhook_format TEXT DEFAULT 'generic'");
        }

        if (!in_array('spam_reason', $cols)) {
            self::$instance->exec("ALTER TABLE form_submissions ADD COLUMN spam_reason TEXT DEFAULT NULL");
        }

        self::$instance->exec("
            CREATE TABLE IF NOT EXISTS site_versions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                site_id INTEGER NOT NULL,
                version_number INTEGER NOT NULL,
                file_count INTEGER DEFAULT 1,
                total_size_bytes INTEGER DEFAULT 0,
                summary TEXT DEFAULT 'Published deployment',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE
            );
            CREATE INDEX IF NOT EXISTS idx_versions_site ON site_versions(site_id, version_number);
        ");
    }

    private static function initMysqlSchema(): void {
        $sql = "
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            name VARCHAR(255),
            plan VARCHAR(50) DEFAULT 'free',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS sites (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            claim_token VARCHAR(255) NOT NULL,
            slug VARCHAR(255) UNIQUE NOT NULL,
            title VARCHAR(255),
            entry_file VARCHAR(255) DEFAULT 'index.html',
            file_count INT DEFAULT 1,
            total_size_bytes BIGINT DEFAULT 0,
            expires_at DATETIME NULL,
            views_count INT DEFAULT 0,
            current_version INT DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_sites_slug (slug),
            INDEX idx_sites_user (user_id),
            INDEX idx_sites_token (claim_token),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS form_submissions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            site_id INT NOT NULL,
            form_name VARCHAR(255) DEFAULT 'Contact',
            is_spam TINYINT DEFAULT 0,
            data_json MEDIUMTEXT NOT NULL,
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_forms_site (site_id),
            INDEX idx_forms_spam (is_spam),
            FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS site_versions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            site_id INT NOT NULL,
            version_number INT NOT NULL,
            file_count INT DEFAULT 1,
            total_size_bytes BIGINT DEFAULT 0,
            summary VARCHAR(255) DEFAULT 'Published deployment',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_versions_site (site_id, version_number),
            FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS analytics_events (
            id INT AUTO_INCREMENT PRIMARY KEY,
            site_id INT NOT NULL,
            path VARCHAR(255) DEFAULT '/',
            visitor_hash VARCHAR(64) NOT NULL,
            referrer VARCHAR(500),
            referrer_host VARCHAR(255),
            device_type VARCHAR(20) DEFAULT 'desktop',
            country_code VARCHAR(4) DEFAULT 'XX',
            is_unique TINYINT DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_analytics_site_created (site_id, created_at),
            INDEX idx_analytics_visitor_date (site_id, visitor_hash, created_at),
            FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";

        self::$instance->exec($sql);
    }
}
