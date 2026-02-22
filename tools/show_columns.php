<?php
// CLI: show columns for given table(s)
if (php_sapi_name() !== 'cli') { echo "Run from CLI: php tools/show_columns.php <table>\n"; exit(1); }
$table = $argv[1] ?? null;
if (!$table) { echo "Provide table name(s) separated by comma. Example: php tools/show_columns.php signup,suspects\n"; exit(1); }

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

try {
    $pdo = new PDO("mysql:host={$host};dbname={$db};charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
} catch (Exception $e) {
    echo "DB connect failed: " . $e->getMessage() . "\n"; exit(2);
}

$tables = array_map('trim', explode(',', $table));
foreach ($tables as $t) {
    echo "\nTable: {$t}\n";
    $stmt = $pdo->prepare("SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
    $stmt->execute([$t]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) { echo "  (no such table or no columns)\n"; continue; }
    foreach ($rows as $r) {
        printf("  %-30s %-20s nullable=%-3s default=%s\n", $r['COLUMN_NAME'], $r['COLUMN_TYPE'], $r['IS_NULLABLE'], var_export($r['COLUMN_DEFAULT'], true));
    }
}
