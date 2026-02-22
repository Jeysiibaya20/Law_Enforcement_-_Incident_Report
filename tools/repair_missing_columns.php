<?php
// CLI: scan codebase for referenced DB columns and add missing columns to the database
// Usage: php tools/repair_missing_columns.php [--dry-run]
if (php_sapi_name() !== 'cli') { echo "Run from CLI: php tools/repair_missing_columns.php\n"; exit(1); }

$dry = in_array('--dry-run', $argv, true);

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

echo "Scanning codebase for referenced DB columns...\n";
$exts = ['php','inc','sql','js','html'];
$files = [];
$dir = new RecursiveDirectoryIterator(__DIR__ . '/..');
foreach (new RecursiveIteratorIterator($dir) as $f) {
    if (!$f->isFile()) continue;
    $path = $f->getPathname();
    $ext = pathinfo($path, PATHINFO_EXTENSION);
    if (!in_array(strtolower($ext), $exts, true)) continue;
    // skip vendor
    if (strpos($path, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR) !== false) continue;
    $files[] = $path;
}

$tableCols = [];

$insertRegex = '/INSERT\s+INTO\s+[`\"]?(\\w+)[`\"]?\s*\(([^\)]+)\)/i';
$updateRegex = '/UPDATE\s+[`\"]?(\\w+)[`\"]?\s+SET\s+([^;]+)/i';

foreach ($files as $file) {
    $c = file_get_contents($file);
    if (!$c) continue;
    // find INSERT INTO table (col1,col2,...)
    if (preg_match_all($insertRegex, $c, $m, PREG_SET_ORDER)) {
        foreach ($m as $mm) {
            $tbl = $mm[1];
            $cols = explode(',', $mm[2]);
            foreach ($cols as $col) {
                $col = trim($col);
                $col = trim($col, "`\" ");
                // ignore values like NOW() or placeholders
                if ($col === '') continue;
                if (!isset($tableCols[$tbl])) $tableCols[$tbl] = [];
                $tableCols[$tbl][$col] = true;
            }
        }
    }
    // find UPDATE table SET col = ..., col2 = ...
    if (preg_match_all($updateRegex, $c, $m2, PREG_SET_ORDER)) {
        foreach ($m2 as $mm) {
            $tbl = $mm[1];
            $set = $mm[2];
            // split by comma but avoid commas inside function calls by a simple approach
            $parts = preg_split('/,\s*/', $set);
            foreach ($parts as $p) {
                if (preg_match('/^\s*([`\"]?)([a-zA-Z0-9_]+)\1\s*=/', trim($p), $g)) {
                    $col = $g[2];
                    if (!isset($tableCols[$tbl])) $tableCols[$tbl] = [];
                    $tableCols[$tbl][$col] = true;
                }
            }
        }
    }
    // also catch occurrences like "sex' =>" or 'sex' => in PHP arrays
    if (preg_match_all('/["\'"`]?([a-zA-Z0-9_]+)["\'"`]?\s*=>/', $c, $m3)) {
        foreach ($m3[1] as $col) {
            // Heuristic: if file path contains auth or signup, map to signup table
            if (stripos($file, DIRECTORY_SEPARATOR . 'auth' . DIRECTORY_SEPARATOR) !== false) {
                $tbl = 'signup';
            } else {
                continue; // skip unknown
            }
            if (!isset($tableCols[$tbl])) $tableCols[$tbl] = [];
            $tableCols[$tbl][$col] = true;
        }
    }
}

if (empty($tableCols)) {
    echo "No referenced columns found. Exiting.\n";
    exit(0);
}

echo "Found referenced columns in code for " . count($tableCols) . " tables.\n";

$env = parseDotEnv(__DIR__ . '/../.env');
$host = $env['DB_HOST'] ?? '127.0.0.1';
$db   = $env['DB_NAME'] ?? '';
$user = $env['DB_USER'] ?? 'root';
$pass = $env['DB_PASS'] ?? '';

if (empty($db)) { echo "DB_NAME not set in .env\n"; exit(2); }

try {
    $pdo = new PDO("mysql:host={$host};dbname={$db};charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::MYSQL_ATTR_USE_BUFFERED_QUERY=>true]);
} catch (Exception $e) {
    echo "DB connect failed: " . $e->getMessage() . "\n";
    exit(3);
}

// fetch existing tables and columns
$existing = [];
$stmt = $pdo->query("SELECT TABLE_NAME, COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE()");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $existing[$r['TABLE_NAME']][$r['COLUMN_NAME']] = true;
}

$alterStmts = [];

// helper: guess column type
function guessColumnType($col) {
    $lname = strtolower($col);
    if (preg_match('/(_id$|^id$|_by$|_user$)/', $lname)) return 'INT NULL';
    if (in_array($lname, ['email','emailadd','email_address','username','user','name','fullname','first_name','last_name','middlename','middle_name'], true)) return 'VARCHAR(150) DEFAULT NULL';
    if (in_array($lname, ['phone','contact','contact_number','phone_number'], true)) return 'VARCHAR(50) DEFAULT NULL';
    if (in_array($lname, ['created_at','updated_at','when','token_expires','expires_at','expires'], true)) return 'DATETIME DEFAULT NULL';
    if (in_array($lname, ['dob','date_of_birth','birthdate'], true)) return 'DATE DEFAULT NULL';
    if (in_array($lname, ['role','status','type'], true)) return 'VARCHAR(50) DEFAULT NULL';
    if (in_array($lname, ['sex','gender'], true)) return "VARCHAR(20) DEFAULT NULL";
    if (preg_match('/_flag$|_enabled$|^enabled$|^terms_accepted$|^resident_qc$/', $lname)) return 'TINYINT(1) DEFAULT 0';
    if (preg_match('/(uploaded|path|file|image|photo|avatar)/', $lname)) return 'VARCHAR(255) DEFAULT NULL';
    // default fallback
    return 'VARCHAR(255) DEFAULT NULL';
}

foreach ($tableCols as $tbl => $cols) {
    // check table exists
    if (!isset($existing[$tbl])) {
        echo "Table {$tbl} referenced in code does not exist in DB; skipping.\n";
        continue;
    }
    foreach (array_keys($cols) as $col) {
        if (isset($existing[$tbl][$col])) continue;
        $type = guessColumnType($col);
        $stmt = "ALTER TABLE `{$tbl}` ADD COLUMN `{$col}` {$type}";
        $alterStmts[] = $stmt . ";";
    }
}

if (empty($alterStmts)) {
    echo "No missing columns detected.\n";
    exit(0);
}

$migrationFile = __DIR__ . '/../migrations/auto_add_columns_' . date('Ymd_His') . '.sql';
$content = "-- Auto-generated migration to add missing columns\n" . implode("\n", $alterStmts) . "\n";
file_put_contents($migrationFile, $content);
echo "Migration written to: {$migrationFile}\n";

if ($dry) {
    echo "Dry-run mode; not applying changes. Review the migration file.\n";
    exit(0);
}

// apply changes using PDO buffered queries
foreach ($alterStmts as $s) {
    try {
        echo "Applying: {$s}\n";
        $pdo->exec($s);
    } catch (Exception $e) {
        echo "Failed to apply statement: " . $e->getMessage() . "\n";
    }
}

echo "Done. Review the migration file: {$migrationFile}\n";
