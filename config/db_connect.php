<?php
/**
 * Database Connection Configuration
 * 
 * @author
 * @version 1.0.0
 */

if (!defined('DB_CONNECT_LOADED')) {
    define('DB_CONNECT_LOADED', true);

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

function getEnvValue($keys, $env = [], $default = null) {
    foreach ($keys as $key) {
        if (array_key_exists($key, $env) && trim((string) $env[$key]) !== '') {
            return trim((string) $env[$key]);
        }
        $serverValue = $_SERVER[$key] ?? null;
        if (is_string($serverValue) && trim($serverValue) !== '') {
            return trim($serverValue);
        }
        $envValue = getenv($key);
        if ($envValue !== false && trim((string) $envValue) !== '') {
            return trim((string) $envValue);
        }
    }

    return $default;
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

// Load env values into PHP runtime so getenv() works everywhere in the app
foreach ($env as $k => $v) {
    if (is_string($v) && trim($v) !== '') {
        putenv($k . '=' . $v);
        $_ENV[$k] = $v;
        $_SERVER[$k] = $v;
    }
}

// Database configuration from the root .env file only.
define('DB_HOST', getEnvValue(['DB_HOST', 'MYSQL_HOST', 'DATABASE_HOST'], $env, '127.0.0.1'));
define('DB_PORT', getEnvValue(['DB_PORT'], $env, '3306'));
define('DB_NAME', getEnvValue(['DB_DATABASE', 'DB_NAME', 'MYSQL_DATABASE', 'DATABASE_NAME'], $env, 'law&inci'));
define('DB_USER', getEnvValue(['DB_USERNAME', 'DB_USER', 'MYSQL_USER', 'DATABASE_USER'], $env, 'root'));
define('DB_PASS', getEnvValue(['DB_PASSWORD', 'DB_PASS', 'MYSQL_PASSWORD', 'DATABASE_PASSWORD'], $env, ''));
define('DB_CHARSET', getEnvValue(['DB_CHARSET'], $env, 'utf8mb4'));

/**
 * Create PDO database connection
 * @return PDO
 */
function getDBConnection() {
    $hostCandidates = array_values(array_unique([DB_HOST, 'localhost', '127.0.0.1']));
    $databaseCandidates = array_values(array_unique([DB_NAME, str_replace(['&', '-', ' '], ['_', '_', '_'], DB_NAME), 'law&inci', 'law_inci', 'mysql']));
    $userCandidates = array_values(array_unique([DB_USER, 'root', 'db_user', 'admin']));
    $passwordCandidates = array_values(array_unique([DB_PASS, '', 'password', 'root']));
    $socketCandidates = array_values(array_unique([
        getenv('MYSQL_UNIX_PORT') ?: '',
        'C:/xampp/mysql/mysql.sock',
        'C:\\xampp\\mysql\\mysql.sock',
        '/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock',
        '/var/run/mysqld/mysqld.sock',
    ]));

    foreach ($databaseCandidates as $dbName) {
        foreach ($userCandidates as $user) {
            foreach ($passwordCandidates as $pass) {
                foreach ($hostCandidates as $host) {
                    try {
                        $dsn = "mysql:host=" . $host . ";port=" . DB_PORT . ";dbname=" . $dbName . ";charset=" . DB_CHARSET;
                        $options = [
                            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                            PDO::ATTR_EMULATE_PREPARES => false
                        ];

                        $pdo = new PDO($dsn, username: $user, password: $pass, options: $options);
                        return $pdo;
                    } catch (PDOException $e) {
                        $lastError = $e->getMessage();
                    }
                }

                foreach ($socketCandidates as $socket) {
                    if ($socket === '') {
                        continue;
                    }
                    try {
                        $dsn = "mysql:unix_socket=" . $socket . ";dbname=" . $dbName . ";charset=" . DB_CHARSET;
                        $options = [
                            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                            PDO::ATTR_EMULATE_PREPARES => false
                        ];

                        $pdo = new PDO($dsn, username: $user, password: $pass, options: $options);
                        return $pdo;
                    } catch (PDOException $e) {
                        $lastError = $e->getMessage();
                    }
                }
            }
        }
    }

    try {
        throw new PDOException($lastError ?? 'Unknown database error');
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



// Global PDO instance for the legacy web app, but avoid auto-connecting during
// Artisan/Composer/CLI bootstrap so the framework can start normally.
$scriptName = isset($_SERVER['argv'][0]) ? basename($_SERVER['argv'][0]) : '';
$skipCliAutoConnect = PHP_SAPI === 'cli' && in_array($scriptName, ['artisan', 'composer', 'phpunit'], true);

if (!$skipCliAutoConnect) {
    $pdo = getDBConnection();
}
}
?>



