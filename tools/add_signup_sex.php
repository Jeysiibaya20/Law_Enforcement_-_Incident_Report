<?php
// CLI: add 'sex' column to signup table if missing
if (php_sapi_name() !== 'cli') { echo "Run from CLI: php tools/add_signup_sex.php\n"; exit(1); }

function parseDotEnv($path) {
    $r = [];
    if (!file_exists($path)) return $r;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $ln) {
        $ln = trim($ln);
        if ($ln === '' || $ln[0] === '#') continue;
        if (strpos($ln, '=') === false) continue;
        list($k,$v) = explode('=', $ln, 2);
        $r[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
    }
    return $r;
}

$env = parseDotEnv(__DIR__ . '/../.env');
$host = $env['DB_HOST'] ?? '127.0.0.1';
$db   = $env['DB_NAME'] ?? '';
$user = $env['DB_USER'] ?? 'root';
$pass = $env['DB_PASS'] ?? '';

if (empty($db)) { echo "DB_NAME not set in .env\n"; exit(2); }

try {
    $dsn = "mysql:host={$host};dbname={$db};charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::MYSQL_ATTR_USE_BUFFERED_QUERY=>true]);
} catch (Exception $e) {
    echo "DB connect failed: " . $e->getMessage() . "\n";
    exit(3);
}

$stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE table_schema = DATABASE() AND table_name = 'signup' AND column_name = 'sex'");
$stmt->execute();
$cnt = (int)$stmt->fetchColumn();
if ($cnt > 0) { echo "Column 'sex' already exists on signup.\n"; exit(0); }

try {
    $pdo->exec("ALTER TABLE `signup` ADD COLUMN `sex` VARCHAR(20) DEFAULT NULL");
    echo "Added column 'sex' to signup table.\n";
    exit(0);
} catch (Exception $e) {
    echo "Failed to add column: " . $e->getMessage() . "\n";
    exit(4);
}
