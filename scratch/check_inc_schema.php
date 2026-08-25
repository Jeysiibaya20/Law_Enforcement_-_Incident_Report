<?php
require_once 'config/db_connect.php';
$pdo = getDBConnection();

$stmt = $pdo->query("SHOW FULL COLUMNS FROM incidents");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "{$row['Field']} : {$row['Type']}\n";
}
