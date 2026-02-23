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
// Also attempt to load mailer.env for deployments that use that file for settings
$mailerEnvPath = __DIR__ . '/../mailer.env';
if (file_exists($mailerEnvPath)) {
    $mailerEnv = parseDotEnv($mailerEnvPath);
    // merge without overwriting existing keys
    foreach ($mailerEnv as $k => $v) {
        if (!isset($env[$k])) $env[$k] = $v;
    }
}

// Application enable guard — prevents accidental deployment until configured
// Smart guard: allow local/CLI access and allow when explicit DB_* config is present,
// but still recommend ENABLE_APP=1 for production. Also allow hosts listed in ALLOWED_HOSTS.
$enableApp = $env['ENABLE_APP'] ?? getenv('ENABLE_APP');
$allowedHosts = array_map('trim', explode(',', $env['ALLOWED_HOSTS'] ?? getenv('ALLOWED_HOSTS') ?: ''));
$currentHost = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? '');

// Determine helpful flags
$hasDBConfig = (!empty($env['DB_HOST'] ?? getenv('DB_HOST')))
    && (!empty($env['DB_USER'] ?? getenv('DB_USER')));
$isLocalRequest = in_array(strtolower($currentHost), ['localhost', '127.0.0.1', '::1']) || php_sapi_name() === 'cli';

// If app is not explicitly enabled, allow when running locally, or when DB credentials are present,
// or when the current host matches ALLOWED_HOSTS. Otherwise block with clear instructions.
if ($enableApp !== '1') {
    $hostAllowed = false;
    if (!empty($currentHost)) {
        foreach ($allowedHosts as $ah) {
            if ($ah === '') continue;
            if (stripos($currentHost, $ah) !== false) { $hostAllowed = true; break; }
        }
    }

    if (!($isLocalRequest || $hasDBConfig || $hostAllowed)) {
        http_response_code(503);
        $msg = "Application is disabled for this host. To enable:\n";
        $msg .= "  1) In your .env set ENABLE_APP=1 and optionally ALLOWED_HOSTS=yourdomain.com\n";
        $msg .= "  2) Ensure DB_HOST, DB_USER and DB_PASS are set in .env or environment variables\n";
        $msg .= "  3) If you're testing locally you can run from localhost or CLI.\n";
        $msg .= "If you believe this is an error, add your domain to ALLOWED_HOSTS in .env.";
        die(nl2br(htmlspecialchars($msg)));
    }
}

// Database configuration (from .env or defaults)
define('DB_HOST', $env['DB_HOST'] ?? getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', $env['DB_PORT'] ?? getenv('DB_PORT') ?: '3306');
define('DB_NAME', $env['DB_NAME'] ?? getenv('DB_NAME') ?: 'emer_comms_test');
define('DB_USER', $env['DB_USER'] ?? getenv('DB_USER') ?: 'root');
define('DB_PASS', $env['DB_PASS'] ?? getenv('DB_PASS') ?: 'YsqnXk6q#145');
define('DB_CHARSET', $env['DB_CHARSET'] ?? getenv('DB_CHARSET') ?: 'utf8mb4');

/**
 * Create PDO database connection
 * @return PDO
 */
function getDBConnection() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ];
        
        $pdo = new PDO($dsn, username: DB_USER, password: DB_PASS, options: $options);
        return $pdo;
        
    } catch (PDOException $e) {
        // Log the detailed error for administrators
        $msg = "Database connection failed: " . $e->getMessage();
        error_log($msg);
        // also write to dedicated log file if logs directory is writable
        try {
            $logDir = __DIR__ . '/../logs';
            if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
            @file_put_contents($logDir . '/db_error.log', date('c') . " | " . $msg . "\n", FILE_APPEND | LOCK_EX);
        } catch (Exception $ex) {
            // ignore
        }
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



// Global PDO instance
$pdo = getDBConnection();
?>



