<?php
// CLI: execute a SQL file using mysqli multi_query to avoid PDO unbuffered issues
if (php_sapi_name() !== 'cli') { echo "Run from CLI: php tools/exec_sql_file.php <file>\n"; exit(1); }
$file = $argv[1] ?? null;
if (!$file || !file_exists($file)) { echo "Specify an existing SQL file path.\n"; exit(2); }

// load .env
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
$user = $env['DB_USER'] ?? 'root';
$pass = $env['DB_PASS'] ?? '';
$db   = $env['DB_NAME'] ?? '';

echo "Executing SQL file: {$file}\n";
$sql = file_get_contents($file);
$mysqli = new mysqli($host, $user, $pass, $db);
if ($mysqli->connect_errno) { echo "MySQL connect error: " . $mysqli->connect_error . "\n"; exit(3); }

if ($mysqli->multi_query($sql)) {
    do {
        if ($res = $mysqli->store_result()) {
            // consume result
            $res->free();
        }
        if ($mysqli->more_results()) {
            // continue
        } else break;
    } while ($mysqli->next_result());
    if ($mysqli->errno) {
        echo "Error during execution: " . $mysqli->error . "\n";
        exit(4);
    }
    echo "Execution complete.\n";
    exit(0);
} else {
    echo "Execution failed: " . $mysqli->error . "\n";
    exit(5);
}
