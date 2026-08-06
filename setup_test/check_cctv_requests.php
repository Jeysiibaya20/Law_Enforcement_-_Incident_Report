<?php
try {
    $pdo = new PDO("mysql:host=localhost;port=3306", "root", "");
    $dbs = $pdo->query("SHOW DATABASES")->fetchAll(PDO::FETCH_COLUMN);
    echo "Databases found: " . implode(", ", $dbs) . "\n\n";

    foreach ($dbs as $db) {
        if (in_array($db, ['information_schema', 'performance_schema', 'phpmyadmin', 'sys'])) continue;
        echo "=== DB: $db ===\n";
        try {
            $pdo->exec("USE `$db`");
            $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            echo "Tables: " . implode(", ", $tables) . "\n";
            if (in_array('cctv_requests', $tables)) {
                $count = $pdo->query("SELECT COUNT(*) FROM cctv_requests")->fetchColumn();
                echo "-> cctv_requests count: $count\n";
                $rows = $pdo->query("SELECT * FROM cctv_requests")->fetchAll(PDO::FETCH_ASSOC);
                print_r($rows);
            }
        } catch (Exception $ex) {
            echo "Error: " . $ex->getMessage() . "\n";
        }
        echo "\n";
    }
} catch (Exception $e) {
    echo "Connection error: " . $e->getMessage() . "\n";
}
