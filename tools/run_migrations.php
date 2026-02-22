<?php
// Run SQL migrations from migrations/ folder against DB credentials from .env
// Usage: php tools/run_migrations.php [--db-host=] [--db-port=] [--db-name=] [--db-user=] [--db-pass=]

function parseDotEnv($path) {
    $result = [];
    if (!file_exists($path)) return $result;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (strpos($line, '=') === false) continue;
        list($k, $v) = explode('=', $line, 2);
        $k = trim($k);
        $v = trim($v);
        $v = preg_replace('/^"(.*)"$|^\'(.*)\'$/', '$1$2', $v);
        $result[$k] = $v;
    }
    return $result;
}

$env = parseDotEnv(__DIR__ . '/../.env');

// CLI overrides
$opts = getopt('', ['db-host::','db-port::','db-name::','db-user::','db-pass::']);
$dbHost = $opts['db-host'] ?? $env['DB_HOST'] ?? 'localhost';
$dbPort = $opts['db-port'] ?? $env['DB_PORT'] ?? 3306;
$dbName = $opts['db-name'] ?? $env['DB_NAME'] ?? '';
$dbUser = $opts['db-user'] ?? $env['DB_USER'] ?? '';
$dbPass = $opts['db-pass'] ?? $env['DB_PASS'] ?? '';

if (empty($dbName) || empty($dbUser)) {
    echo "Missing DB configuration. Provide via .env or CLI args.\n";
    echo "Example: php tools/run_migrations.php --db-name=law_inci --db-user=root --db-pass=secret\n";
    exit(1);
}

$dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
try {
    $pdoOptions = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        // Enable buffered queries for MySQL to allow multiple queries in sequence
        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true
    ];
    $pdo = new PDO($dsn, $dbUser, $dbPass, $pdoOptions);
} catch (Exception $e) {
    echo "Failed to connect to DB: " . $e->getMessage() . "\n";
    exit(1);
}

$migrations = [
    __DIR__ . '/../migrations/create_two_factor_codes.sql',
    __DIR__ . '/../migrations/alter_two_factor_codes.sql'
];

foreach ($migrations as $file) {
    if (!file_exists($file)) {
        echo "Migration file not found: {$file}\n";
        continue;
    }
    $sql = file_get_contents($file);
    $base = basename($file);
    // Special-case alter migration to avoid complex SQL statements that can cause unbuffered query issues
    if ($base === 'alter_two_factor_codes.sql') {
        try {
            // Check for existence of user_type_unique index
            $stmt = $pdo->prepare("SELECT COUNT(1) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'two_factor_codes' AND INDEX_NAME = ?");
            $stmt->execute(['user_type_unique']);
            $count = (int) $stmt->fetchColumn();
            if ($count > 0) {
                try { $pdo->exec("ALTER TABLE `two_factor_codes` DROP INDEX `user_type_unique`"); echo "Dropped index user_type_unique\n"; } catch (Exception $e) { echo "Ignored drop error: " . $e->getMessage() . "\n"; }
            } else {
                echo "Index user_type_unique not present, skipping drop.\n";
            }
            // Ensure non-unique index exists
            $stmt->execute(['idx_user_type']);
            $count2 = (int) $stmt->fetchColumn();
            if ($count2 === 0) {
                try { $pdo->exec("ALTER TABLE `two_factor_codes` ADD INDEX `idx_user_type` (`user_id`,`type`)"); echo "Added index idx_user_type\n"; } catch (Exception $e) { echo "Ignored add index error: " . $e->getMessage() . "\n"; }
            } else {
                echo "Index idx_user_type already present, skipping add.\n";
            }
            echo "Applied migration: " . $base . "\n";
            continue;
        } catch (Exception $e) {
            echo "Failed to apply migration " . $base . ": " . $e->getMessage() . "\n";
            continue;
        }
    }
    try {
        // Split statements by semicolon followed by newline. This is a simple splitter; avoid complex delimiter handling.
        $stmts = array_filter(array_map('trim', preg_split('/;\s*\n/', $sql)));
        foreach ($stmts as $stmt) {
            if ($stmt === '') continue;
            try {
                $pdo->exec($stmt);
            } catch (PDOException $e) {
                $mysqlCode = $e->errorInfo[1] ?? null;
                // Ignore common harmless errors: 1091 (can't drop index), 1061 (duplicate key name)
                if (in_array($mysqlCode, [1091, 1061], true)) {
                    echo "Warning (ignored) in migration " . basename($file) . ": " . $e->getMessage() . "\n";
                    continue;
                }
                echo "Failed to execute statement in migration " . basename($file) . ": " . $e->getMessage() . "\n";
                // stop processing this migration
                throw $e;
            }
        }
        echo "Applied migration: " . basename($file) . "\n";
    } catch (Exception $e) {
        echo "Failed to apply migration " . basename($file) . ": " . $e->getMessage() . "\n";
    }
}

echo "Done.\n";
