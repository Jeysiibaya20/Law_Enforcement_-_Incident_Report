<?php
require_once __DIR__ . '/../config/db_connect.php';
$pdo = getDBConnection();
$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
echo "=== ALL DATABASE TABLES ===\n";
foreach ($tables as $t) {
    echo "- $t\n";
}
