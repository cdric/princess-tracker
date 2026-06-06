<?php

declare(strict_types=1);

function db_driver(): string
{
    return strtolower(env_value('DB_DRIVER', 'sqlite') ?? 'sqlite');
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $driver = db_driver();

    if ($driver === 'mysql') {
        if (!extension_loaded('pdo_mysql')) {
            throw new RuntimeException('PHP extension pdo_mysql is required for DB_DRIVER=mysql. Enable it in cPanel/PHP extensions.');
        }
        $dsn = env_value('DB_DSN');
        if (!$dsn) {
            $host = env_value('DB_HOST', 'localhost');
            $name = env_value('DB_NAME');
            if (!$name) {
                throw new RuntimeException('DB_NAME is required when DB_DRIVER=mysql.');
            }
            $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";
        }
        $pdo = new PDO($dsn, env_value('DB_USER', ''), env_value('DB_PASSWORD', ''), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        return $pdo;
    }

    if ($driver !== 'sqlite') {
        throw new RuntimeException('Unsupported DB_DRIVER: ' . $driver);
    }
    if (!extension_loaded('pdo_sqlite')) {
        throw new RuntimeException('PHP extension pdo_sqlite is required for DB_DRIVER=sqlite. Enable it in cPanel/PHP extensions or switch DB_DRIVER=mysql.');
    }

    $path = private_path(env_value('PRINCESS_DB_PATH', 'storage/tracker.sqlite') ?? 'storage/tracker.sqlite');
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0700, true);
    }

    $pdo = new PDO('sqlite:' . $path, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');
    return $pdo;
}

function init_db(): void
{
    $pdo = db();
    if (db_driver() === 'mysql') {
        init_db_mysql($pdo);
    } else {
        init_db_sqlite($pdo);
    }
}

function init_db_sqlite(PDO $pdo): void
{
    $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS raw_api_responses (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    checked_at TEXT NOT NULL,
    source TEXT NOT NULL,
    cruise_id TEXT NOT NULL,
    request_payload TEXT NOT NULL,
    response_json TEXT NOT NULL
)
SQL);

    $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS price_checks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    checked_at TEXT NOT NULL,
    cruise_id TEXT NOT NULL,
    product_id TEXT,
    cabin_code TEXT NOT NULL,
    cabin_name TEXT NOT NULL,
    status TEXT NOT NULL,
    status_message TEXT,
    category_id TEXT,
    category_status TEXT,
    availability TEXT,
    available_cabins TEXT,
    currency TEXT,
    fare_guest_1 REAL,
    fare_guest_2 REAL,
    fare_per_person REAL,
    taxes_fees_per_person REAL,
    total_per_person REAL,
    total_for_two REAL,
    raw_json TEXT NOT NULL,
    raw_response_id INTEGER,
    FOREIGN KEY(raw_response_id) REFERENCES raw_api_responses(id) ON DELETE SET NULL
)
SQL);

    $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS watches (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    cruise_id TEXT NOT NULL,
    cabin_code TEXT NOT NULL,
    target_price_per_person REAL NOT NULL,
    email_to TEXT NOT NULL,
    enabled INTEGER NOT NULL DEFAULT 1,
    last_alert_price REAL,
    last_alert_at TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
)
SQL);

    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_price_checks_cruise_time ON price_checks(cruise_id, checked_at)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_watches_enabled ON watches(enabled, cruise_id, cabin_code)');
}

function init_db_mysql(PDO $pdo): void
{
    $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS raw_api_responses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    checked_at VARCHAR(40) NOT NULL,
    source VARCHAR(40) NOT NULL,
    cruise_id VARCHAR(32) NOT NULL,
    request_payload MEDIUMTEXT NOT NULL,
    response_json MEDIUMTEXT NOT NULL,
    INDEX idx_raw_cruise_time (cruise_id, checked_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

    $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS price_checks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    checked_at VARCHAR(40) NOT NULL,
    cruise_id VARCHAR(32) NOT NULL,
    product_id VARCHAR(64),
    cabin_code VARCHAR(8) NOT NULL,
    cabin_name VARCHAR(64) NOT NULL,
    status VARCHAR(32) NOT NULL,
    status_message VARCHAR(255),
    category_id VARCHAR(16),
    category_status VARCHAR(16),
    availability VARCHAR(32),
    available_cabins VARCHAR(32),
    currency VARCHAR(8),
    fare_guest_1 DECIMAL(12,2),
    fare_guest_2 DECIMAL(12,2),
    fare_per_person DECIMAL(12,2),
    taxes_fees_per_person DECIMAL(12,2),
    total_per_person DECIMAL(12,2),
    total_for_two DECIMAL(12,2),
    raw_json MEDIUMTEXT NOT NULL,
    raw_response_id BIGINT UNSIGNED NULL,
    INDEX idx_price_checks_cruise_time (cruise_id, checked_at),
    INDEX idx_price_checks_cabin (cruise_id, cabin_code),
    CONSTRAINT fk_price_raw_response FOREIGN KEY(raw_response_id) REFERENCES raw_api_responses(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

    $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS watches (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cruise_id VARCHAR(32) NOT NULL,
    cabin_code VARCHAR(8) NOT NULL,
    target_price_per_person DECIMAL(12,2) NOT NULL,
    email_to VARCHAR(255) NOT NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    last_alert_price DECIMAL(12,2),
    last_alert_at VARCHAR(40),
    created_at VARCHAR(40) NOT NULL,
    INDEX idx_watches_enabled (enabled, cruise_id, cabin_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
}

function insert_raw_response(string $source, string $cruiseId, array $payload, array $response, string $checkedAt): int
{
    $stmt = db()->prepare('INSERT INTO raw_api_responses (checked_at, source, cruise_id, request_payload, response_json) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([
        $checkedAt,
        $source,
        $cruiseId,
        json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
    ]);
    return (int)db()->lastInsertId();
}

function save_price_rows(array $rows, string $checkedAt, ?int $rawResponseId = null): void
{
    $stmt = db()->prepare(<<<SQL
INSERT INTO price_checks (
    checked_at, cruise_id, product_id, cabin_code, cabin_name, status, status_message,
    category_id, category_status, availability, available_cabins, currency,
    fare_guest_1, fare_guest_2, fare_per_person, taxes_fees_per_person,
    total_per_person, total_for_two, raw_json, raw_response_id
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
SQL);

    foreach ($rows as $row) {
        $stmt->execute([
            $checkedAt,
            $row['cruise_id'],
            $row['product_id'],
            $row['cabin_code'],
            $row['cabin_name'],
            $row['status'],
            $row['status_message'],
            $row['category_id'],
            $row['category_status'],
            $row['availability'],
            $row['available_cabins'],
            $row['currency'],
            $row['fare_guest_1'],
            $row['fare_guest_2'],
            $row['fare_per_person'],
            $row['taxes_fees_per_person'],
            $row['total_per_person'],
            $row['total_for_two'],
            $row['raw_json'],
            $rawResponseId,
        ]);
    }
}

function latest_history(?string $cruiseId = null, int $limit = 300): array
{
    if ($cruiseId) {
        $stmt = db()->prepare('SELECT * FROM price_checks WHERE cruise_id = ? ORDER BY checked_at DESC, cabin_code LIMIT ?');
        $stmt->bindValue(1, $cruiseId);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    $stmt = db()->prepare('SELECT * FROM price_checks ORDER BY checked_at DESC, cruise_id, cabin_code LIMIT ?');
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function distinct_cruise_ids(): array
{
    return db()->query('SELECT DISTINCT cruise_id FROM price_checks ORDER BY cruise_id')->fetchAll(PDO::FETCH_COLUMN) ?: [];
}

function get_watches(): array
{
    return db()->query('SELECT * FROM watches ORDER BY enabled DESC, cruise_id, cabin_code, id DESC')->fetchAll();
}

function add_watch(string $cruiseId, string $cabinCode, float $targetPrice, string $emailTo): void
{
    $stmt = db()->prepare('INSERT INTO watches (cruise_id, cabin_code, target_price_per_person, email_to, enabled, created_at) VALUES (?, ?, ?, ?, 1, ?)');
    $stmt->execute([$cruiseId, $cabinCode, $targetPrice, $emailTo, now_iso()]);
}

function set_watch_enabled(int $watchId, bool $enabled): void
{
    $stmt = db()->prepare('UPDATE watches SET enabled = ? WHERE id = ?');
    $stmt->execute([$enabled ? 1 : 0, $watchId]);
}

function delete_watch(int $watchId): void
{
    $stmt = db()->prepare('DELETE FROM watches WHERE id = ?');
    $stmt->execute([$watchId]);
}

function active_watch_cruise_ids(): array
{
    $rows = db()->query('SELECT DISTINCT cruise_id FROM watches WHERE enabled = 1 ORDER BY cruise_id')->fetchAll(PDO::FETCH_COLUMN);
    return $rows ?: [];
}
