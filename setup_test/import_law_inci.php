<?php
try {
    $pdo = new PDO("mysql:host=localhost;port=3306", "root", "");
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `law_inci` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
    $pdo->exec("USE `law_inci`;");
    echo "Database `law_inci` created/selected successfully.\n";

    $sqlFile = __DIR__ . '/../database2/law&inci.sql';
    if (!file_exists($sqlFile)) {
        $sqlFile = __DIR__ . '/../wag/law_inci.sql';
    }

    if (file_exists($sqlFile)) {
        echo "Importing SQL file $sqlFile into `law_inci`...\n";
        $cmd = "C:\\xampp\\mysql\\bin\\mysql.exe -u root law_inci < \"" . addslashes($sqlFile) . "\"";
        exec($cmd, $out, $ret);
        echo "Import command exit code: $ret\n";
    }

    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables now in `law_inci`: " . implode(", ", $tables) . "\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
