<?php
require_once __DIR__ . '/../config/db_connect.php';
$pdo = getDBConnection();
$stmt = $pdo->query("SHOW COLUMNS FROM incidents");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['Field'] . " => " . $row['Type'] . "\n";
}
