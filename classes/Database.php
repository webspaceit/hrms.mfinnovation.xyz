<?php
// ============================================================
// Database Class (Singleton)
// ============================================================

require_once __DIR__ . '/../config/config.php';

class Database {
    private static $instance = null;
    private $connection;

    // Tables the app uses — keep in sync with database.sql / portal_upgrade.sql.
    // The runtime prefixer below rewrites these as <DB_PREFIX>tables, so the
    // rest of the codebase keeps writing plain names ('users', 'invoices', ...).
    public static $tables = [
        'users', 'buildings', 'flats', 'tenants', 'leases', 'payments',
        'expenses', 'settings', 'translation_cache', 'invoices', 'invoice_sends',
    ];

    // Rewrite known table names with the configured DB_PREFIX.
    // A word-boundary lookaround keeps column/alias/constraint names
    // (flat_id, fk_flats_building, ...) untouched.
    public static function prefix($sql) {
        $p = defined('DB_PREFIX') ? DB_PREFIX : '';
        if ($p === '') {
            return $sql;
        }
        foreach (self::$tables as $t) {
            $sql = preg_replace(
                '/(?<![A-Za-z0-9_])' . preg_quote($t, '/') . '(?![A-Za-z0-9_])/',
                $p . $t,
                $sql
            );
        }
        return $sql;
    }

    private function __construct() {
        try {
            $this->connection = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->connection;
    }

    // Helper methods
    public function query($sql, $params = []) {
        $stmt = $this->connection->prepare(self::prefix($sql));
        $stmt->execute($params);
        return $stmt;
    }

    public function fetchAll($sql, $params = []) {
        return $this->query($sql, $params)->fetchAll();
    }

    public function fetch($sql, $params = []) {
        return $this->query($sql, $params)->fetch();
    }

    public function fetchColumn($sql, $params = []) {
        return $this->query($sql, $params)->fetchColumn();
    }

    public function execute($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    public function lastInsertId() {
        return $this->connection->lastInsertId();
    }

    private function __clone() {}
    public function __wakeup() {}
}
