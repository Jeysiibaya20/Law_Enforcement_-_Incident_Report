<?php
require_once 'config/db_connect.php';
$pdo = getDBConnection();

$stmt = $pdo->query("SHOW CREATE TABLE external_integration_log");
$res = $stmt->fetch(PDO::FETCH_ASSOC);
echo $res['Create Table'] . "\n";
