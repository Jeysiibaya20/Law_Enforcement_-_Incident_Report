<?php
/**
 * Database Connection Configuration
 * 
 * @author
 * @version 1.0.0
 */

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'law&inci');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

/**
 * Create PDO database connection
 * @return PDO
 */
function getDBConnection() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ];
        
        $pdo = new PDO($dsn, username: DB_USER, password: DB_PASS, options: $options);
        return $pdo;
        
    } catch (PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        die("Database connection failed. Please contact system administrator.");
    }
}

/**
 * Test database connection
 * @return bool
 */
function testDBConnection() {
    try {
        $pdo = getDBConnection();
        return $pdo !== null;
    } catch (Exception $e) {
        return false;
    }
}

// Load mail environment (SMTP) for PHPMailer/email 2FA
@require_once __DIR__ . '/mail_env.php';

// Global PDO instance
$pdo = getDBConnection();
?>



