<?php
$pdo = new PDO('mysql:host=127.0.0.1;port=3306;charset=utf8mb4;dbname=law&inci', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
print_r($tables);
