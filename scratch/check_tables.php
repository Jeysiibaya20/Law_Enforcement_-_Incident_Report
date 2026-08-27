<?php
require_once __DIR__ . '/../config/db_connect.php';
$pdo = getDBConnection();
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
foreach ($tables as $t) {
    if (strpos($t, 'received_') === 0 || strpos($t, 'cctv_') === 0 || strpos($t, 'external_') === 0) {
        echo $t . "\n";
    }
}
