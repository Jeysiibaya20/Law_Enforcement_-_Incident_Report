<?php
// CLI: remove clearly-invalid columns added by an earlier automated run
// Usage: php tools/cleanup_garbage_columns.php
if (php_sapi_name() !== 'cli') { echo "Run from CLI: php tools/cleanup_garbage_columns.php\n"; exit(1); }

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

if (empty($db)) { echo "DB_NAME not set in .env\n"; exit(2); }

try {
    $pdo = new PDO("mysql:host={$host};dbname={$db};charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::MYSQL_ATTR_USE_BUFFERED_QUERY=>true]);
} catch (Exception $e) {
    echo "DB connect failed: " . $e->getMessage() . "\n"; exit(3);
}

// whitelist for signup table
$originalSignup = ['user_id','fullname','emailadd','role','username','password','created_at','email_verified','verification_token','token_expires','terms_accepted','terms_accepted_date','email'];
$allowedExtra = ['sex','phone','dob','address','resident_qc','id_type','uploaded_front','uploaded_back'];
$signupAllowed = array_merge($originalSignup, $allowedExtra);

// scan signup and drop columns not in whitelist
function dropColumns($pdo, $table, $cols) {
    foreach ($cols as $col) {
        try {
            echo "Dropping {$table}.{$col} ... ";
            $pdo->exec("ALTER TABLE `{$table}` DROP COLUMN `{$col}`");
            echo "OK\n";
        } catch (Exception $e) {
            echo "Failed: " . $e->getMessage() . "\n";
        }
    }
}

// get columns for table
function getColumns($pdo, $table) {
    $stmt = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
    $stmt->execute([$table]);
    return array_map(function($r){return $r['COLUMN_NAME'];}, $stmt->fetchAll(PDO::FETCH_ASSOC));
}

// cleanup signup
$signupCols = getColumns($pdo, 'signup');
$toDrop = [];
foreach ($signupCols as $c) {
    if (!in_array($c, $signupAllowed, true)) $toDrop[] = $c;
}
if (!empty($toDrop)) {
    echo "Will drop " . count($toDrop) . " unexpected columns from signup:\n" . implode(', ', $toDrop) . "\n";
    dropColumns($pdo, 'signup', $toDrop);
} else {
    echo "No unexpected signup columns found.\n";
}

// cleanup other tables: drop columns with invalid names (non-alnum/_)
$tablesToCheck = ['blotters','suspects','incidents'];
foreach ($tablesToCheck as $t) {
    $cols = getColumns($pdo, $t);
    $bad = [];
    foreach ($cols as $c) {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $c)) {
            // avoid dropping core columns accidentally: ensure name contains at least one non-word character
            $bad[] = $c;
        }
    }
    if (!empty($bad)) {
        echo "Table {$t} has invalid-named columns: " . implode(', ', $bad) . "\n";
        dropColumns($pdo, $t, $bad);
    } else {
        echo "Table {$t} has no invalid-named columns.\n";
    }
}

echo "Cleanup complete.\n";
