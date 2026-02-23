<?php
/**
 * Database Connection Diagnostic Tool
 * Helps identify why database connection is failing
 */

echo "=== Database Connection Diagnostic ===\n\n";

// Parse .env
$env = [];
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $ln) {
        $ln = trim($ln);
        if ($ln === '' || strpos($ln, '#') === 0) continue;
        if (strpos($ln, '=') === false) continue;
        list($k, $v) = explode('=', $ln, 2);
        $k = trim($k);
        $v = trim($v);
        // Remove quotes
        $v = preg_replace('/^"(.*)"$|^\'(.*)\'$/', '$1$2', $v);
        $env[$k] = $v;
    }
}

$dbHost = $env['DB_HOST'] ?? 'localhost';
$dbPort = $env['DB_PORT'] ?? '3306';
$dbName = $env['DB_NAME'] ?? 'law&inci';
$dbUser = $env['DB_USER'] ?? 'root';
$dbPass = $env['DB_PASS'] ?? 'qwerty123';
$charset = $env['DB_CHARSET'] ?? 'utf8mb4';
$appEnv = $env['APP_ENV'] ?? 'development';
$enableApp = $env['ENABLE_APP'] ?? '0';

echo "CONFIGURATION LOADED FROM .env:\n";
echo "  DB_HOST:     {$dbHost}\n";
echo "  DB_PORT:     {$dbPort}\n";
echo "  DB_NAME:     {$dbName}\n";
echo "  DB_USER:     {$dbUser}\n";
echo "  DB_PASS:     " . (strlen($dbPass) > 0 ? "***" : "(empty)") . "\n";
echo "  DB_CHARSET:  {$charset}\n";
echo "  APP_ENV:     {$appEnv}\n";
echo "  ENABLE_APP:  {$enableApp}\n\n";

// Build DSN
$dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset={$charset}";
echo "DSN: {$dsn}\n\n";

// Test connection
echo "ATTEMPTING CONNECTION...\n";
try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 3
    ]);
    echo "✓ SUCCESS: Connected!\n\n";
    
    // Test query
    $result = $pdo->query("SELECT VERSION() as version");
    $row = $result->fetch(PDO::FETCH_ASSOC);
    echo "MySQL Version: " . $row['version'] . "\n";
    
    // List databases
    $result = $pdo->query("SHOW DATABASES");
    $dbs = $result->fetchAll(PDO::FETCH_COLUMN);
    echo "Available databases: " . implode(", ", $dbs) . "\n";
    
} catch (PDOException $e) {
    echo "✗ CONNECTION FAILED\n";
    echo "Error: " . $e->getMessage() . "\n\n";
    echo "TROUBLESHOOTING STEPS:\n";
    
    $msg = $e->getMessage();
    
    if (strpos($msg, 'unknown host') !== false || strpos($msg, 'Name or service not known') !== false) {
        echo "1. HOST NOT FOUND - Check that DB_HOST is correct\n";
        echo "   - For local/XAMPP: use '127.0.0.1' or 'localhost'\n";
        echo "   - For domain server: use the actual database server IP/hostname\n";
        echo "   - Example: 192.168.1.100 or db.yourdomain.com\n";
    } elseif (strpos($msg, 'Connection refused') !== false) {
        echo "1. PORT NOT ACCESSIBLE - Check DB_PORT and MySQL is running\n";
        echo "   - Typical port: 3306\n";
        echo "   - Verify MySQL is running on that port\n";
        echo "   - Check firewall allows connection to that port\n";
    } elseif (strpos($msg, 'Access denied') !== false) {
        echo "1. AUTHENTICATION FAILED - Check username/password\n";
        echo "   - Current user: {$dbUser}\n";
        echo "   - Check DB_USER and DB_PASS are correct\n";
        echo "   - Verify user has permission on '{$dbName}' from '{$dbHost}'\n";
    } elseif (strpos($msg, 'Unknown database') !== false) {
        echo "1. DATABASE NOT FOUND - Database '{$dbName}' doesn't exist\n";
        echo "   - Create the database first\n";
        echo "   - Or check DB_NAME spelling\n";
    }
    
    echo "\n2. COMMON FIXES:\n";
    echo "   - For DOMAIN SERVER (e.g., cPanel, Plesk):\n";
    echo "     DB_HOST=localhost (or your provider's DB host)\n";
    echo "     DB_USER=your_db_user\n";
    echo "     DB_PASS=your_db_password\n";
    echo "     DB_NAME=your_database_name\n";
    echo "\n";
    echo "   - Then update .env and test again\n";
}
?>
