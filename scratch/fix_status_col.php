<?php
require_once 'config/db_connect.php';
$pdo = getDBConnection();

$pdo->exec("ALTER TABLE cctv_requests MODIFY COLUMN status VARCHAR(100) NOT NULL DEFAULT 'Pending'");
$pdo->exec("ALTER TABLE external_integration_log MODIFY COLUMN status VARCHAR(100) NOT NULL DEFAULT 'logged'");
echo "Altered status columns to VARCHAR(100) successfully!\n";
