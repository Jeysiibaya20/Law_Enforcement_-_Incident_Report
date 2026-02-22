<?php
/**
 * Database Connection Configuration
 * 
 * @author
 * @version 1.0.0
 */

// Load .env (simple parser)
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
        // remove surrounding quotes
        $v = preg_replace('/^\"(.*)\"$|^\'(.*)\'$/', '$1$2', $v);
        $result[$k] = $v;
    }
    return $result;
}

$env = parseDotEnv(__DIR__ . '/../.env');

// Application enable guard — prevents accidental deployment until configured
$enableApp = $env['ENABLE_APP'] ?? getenv('ENABLE_APP') ?: '0';
$allowedHosts = array_map('trim', explode(',', $env['ALLOWED_HOSTS'] ?? getenv('ALLOWED_HOSTS') ?: ''));
$currentHost = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? '');
// If app is not enabled and current host is not in allowed list, block execution
if ($enableApp !== '1') {
    $hostAllowed = false;
    if (!empty($currentHost)) {
        foreach ($allowedHosts as $ah) {
            if ($ah === '') continue;
            if (stripos($currentHost, $ah) !== false) { $hostAllowed = true; break; }
        }
    }
    if (!$hostAllowed) {
        // Show a safe message instead of trying to connect to DB
        http_response_code(503);
        die("Application is disabled. Configure the .env file (set ENABLE_APP=1 and DB_* values) before deploying to your domain.");
    }
}

// Database configuration (from .env or defaults)
define('DB_HOST', $env['DB_HOST'] ?? getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', $env['DB_NAME'] ?? getenv('DB_NAME') ?: 'law&inci');
define('DB_USER', $env['DB_USER'] ?? getenv('DB_USER') ?: 'root');
define('DB_PASS', $env['DB_PASS'] ?? getenv('DB_PASS') ?: '');
define('DB_CHARSET', $env['DB_CHARSET'] ?? getenv('DB_CHARSET') ?: 'utf8mb4');

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
        // Log the detailed error for administrators
        error_log("Database connection failed: " . $e->getMessage());
        // If running in development mode, show the underlying error to help debugging
        $appEnv = $env['APP_ENV'] ?? getenv('APP_ENV');
        if ($appEnv === 'development' || $appEnv === 'local') {
            die("Database connection failed: " . $e->getMessage());
        }
        // In production don't expose details
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



