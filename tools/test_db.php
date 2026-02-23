<?php
// Quick DB connection test using .env values
$env = [];
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $ln) {
        $ln = trim($ln);
        // Skip empty lines and comments
        if ($ln === '' || strpos($ln, '#') === 0) continue;
        if (strpos($ln, '=') === false) continue;
        
        list($k, $v) = explode('=', $ln, 2);
        $k = trim($k);
        $v = trim($v);
        
        // Remove surrounding quotes if present
        if ((strpos($v, '"') === 0 && strrpos($v, '"') === strlen($v) - 1) ||
            (strpos($v, "'") === 0 && strrpos($v, "'") === strlen($v) - 1)) {
            $v = substr($v, 1, -1);
        }
        
        $env[$k] = trim($v);
    }
}

// Get values from .env with defaults
$dbHost = $env['DB_HOST'] ?? '127.0.0.1';
$dbPort = $env['DB_PORT'] ?? '3306';
$dbName = $env['DB_NAME'] ?? 'law&inci';
$dbUser = $env['DB_USER'] ?? 'root';
$dbPass = $env['DB_PASS'] ?? 'qweryt123';
$charset = $env['DB_CHARSET'] ?? 'utf8mb4';

// Build DSN: mysql:host=localhost;port=3306;dbname=testdb;charset=utf8mb4
$dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset={$charset}";

// Output connection info
echo "Connection Info:\n";
echo "  Host: {$dbHost}\n";
echo "  Port: {$dbPort}\n";
echo "  Database: {$dbName}\n";
echo "  User: {$dbUser}\n";
echo "  DSN: {$dsn}\n\n";

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    echo "✓ SUCCESS: Connected to '{$dbName}' at {$dbHost}:{$dbPort}\n";
    
    // Test query
    $result = $pdo->query("SELECT VERSION() as version");
    $row = $result->fetch(PDO::FETCH_ASSOC);
    echo "MySQL Version: " . $row['version'] . "\n";
} catch (PDOException $e) {
    echo "✗ ERROR: " . $e->getMessage() . "\n";
    exit(1);   
}
