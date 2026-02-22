<?php
// Quick DB connection test using .env values
$env = [];
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $ln) {
        $ln = trim($ln);
        if ($ln === '' || strpos($ln, '#') === 0) continue;
        if (strpos($ln, '=') === false) continue;
        list($k,$v) = explode('=', $ln, 2);
        $env[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
    }
}
$dbHost = $env['DB_HOST'] ?? 'localhost';
$dbPort = $env['DB_PORT'] ?? '3306';
$dbName = $env['DB_NAME'] ?? 'test';
$dbUser = $env['DB_USER'] ?? 'root';
$dbPass = $env['DB_PASS'] ?? '';
$charset = $env['DB_CHARSET'] ?? 'utf8mb4';
$dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset={$charset}";
try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    echo "OK: connected to {$dbName} at {$dbHost}:{$dbPort}\n";
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
