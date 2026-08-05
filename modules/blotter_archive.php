<?php
require '../config/db_connect.php';
if (!isset($pdo) || !($pdo instanceof PDO)) {
    $pdo = getDBConnection();
}

$pdo->prepare("UPDATE blotters SET status='Archived' WHERE id=?")
    ->execute([$_POST['id']]);

echo "archived";
