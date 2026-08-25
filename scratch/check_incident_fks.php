<?php
require_once __DIR__ . '/../config/db_connect.php';
$pdo = getDBConnection();
$stmt = $pdo->query("
    SELECT TABLE_NAME, COLUMN_NAME, CONSTRAINT_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE REFERENCED_TABLE_NAME = 'incidents' AND TABLE_SCHEMA = DATABASE()
");
$fks = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($fks);
