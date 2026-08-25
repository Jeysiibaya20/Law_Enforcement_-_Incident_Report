<?php
require_once 'config/db_connect.php';
$pdo = getDBConnection();

$stmt = $pdo->query("SHOW CREATE TABLE cctv_requests");
$res = $stmt->fetch(PDO::FETCH_ASSOC);
echo $res['Create Table'] . "\n";
