<?php
require_once __DIR__ . '/../config/db_connect.php';
$pdo = getDBConnection();
$stmt = $pdo->query("SHOW CREATE TABLE incidents");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
echo $row['Create Table'] . "\n";
