<?php
$pdo = new PDO("mysql:host=localhost;port=3306", "root", "");
$dbs = $pdo->query("SHOW DATABASES")->fetchAll(PDO::FETCH_COLUMN);

foreach ($dbs as $db) {
    if (in_array($db, ['information_schema', 'performance_schema', 'sys'])) continue;
    try {
        $pdo->exec("USE `$db`");
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        if (in_array('signup', $tables) || in_array('blotters', $tables) || in_array('incidents', $tables)) {
            echo "MATCH FOUND in Database: $db\n";
            echo "Tables in $db: " . implode(", ", $tables) . "\n\n";
        }
    } catch (Exception $e) {}
}
