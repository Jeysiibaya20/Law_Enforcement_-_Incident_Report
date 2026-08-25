<?php
require_once 'config/db_connect.php';
$pdo = getDBConnection();
$cols = $pdo->query('SHOW COLUMNS FROM blotters')->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $col) {
    echo $col['Field'] . " (" . $col['Type'] . ")\n";
}
