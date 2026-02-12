<?php
require '../config/db_connect.php';

$pdo->prepare("UPDATE blotters SET status='Archived' WHERE id=?")
    ->execute([$_POST['id']]);

echo "archived";
