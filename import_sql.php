<?php
$pdo = new PDO('mysql:host=127.0.0.1;port=3306;charset=utf8mb4;dbname=law&inci', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$sql = file_get_contents(__DIR__ . '/law_inci_import.sql');
$pdo->exec($sql);
echo "SQL imported\n";
