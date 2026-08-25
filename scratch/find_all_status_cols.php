<?php
require_once 'config/db_connect.php';
$pdo = getDBConnection();

$stmt = $pdo->query("SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME = 'status'");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "Table `{$row['TABLE_NAME']}`: Column `{$row['COLUMN_NAME']}` is `{$row['COLUMN_TYPE']}`\n";
}
