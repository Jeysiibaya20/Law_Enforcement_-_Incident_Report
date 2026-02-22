<?php
// Simple DB diagnostic page. Access is restricted by IP whitelist set in .env as DIAG_ALLOWED_IPS (comma-separated)
require_once __DIR__ . '/../config/db_connect.php';

$envFile = __DIR__ . '/../.env';
$env = [];
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $ln) {
        $ln = trim($ln);
        if ($ln === '' || strpos($ln, '#') === 0) continue;
        if (strpos($ln, '=') === false) continue;
        list($k,$v) = explode('=', $ln, 2);
        $env[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
    }
}
$allowed = $env['DIAG_ALLOWED_IPS'] ?? getenv('DIAG_ALLOWED_IPS') ?: '127.0.0.1,::1';
$allowedList = array_map('trim', explode(',', $allowed));
$remote = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
if (!in_array($remote, $allowedList)) {
    http_response_code(403);
    echo "Forbidden\n";
    exit;
}

echo "DB diagnostic for host: " . ($_SERVER['HTTP_HOST'] ?? 'unknown') . "\n";
try {
    $pdo = getDBConnection();
    $stmt = $pdo->query('SELECT DATABASE() as db');
    $row = $stmt->fetch();
    echo "Connected to database: " . ($row['db'] ?? 'unknown') . "\n";
    echo "DB_HOST=" . DB_HOST . "\n";
    echo "DB_NAME=" . DB_NAME . "\n";
    echo "DB_USER=" . DB_USER . "\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

?>