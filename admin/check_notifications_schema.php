<?php
require_once __DIR__ . '/../config/db_connect.php';
header('Content-Type: text/plain');

try {
    // Columns
    $cols = $pdo->query("SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_KEY FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notifications'")->fetchAll(PDO::FETCH_ASSOC);
    echo "COLUMNS:\n";
    foreach ($cols as $c) {
        echo " - {$c['COLUMN_NAME']} | {$c['COLUMN_TYPE']} | NULLABLE: {$c['IS_NULLABLE']} | KEY: {$c['COLUMN_KEY']}\n";
    }

    // Foreign keys
    $fks = $pdo->query("SELECT CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notifications' AND REFERENCED_TABLE_NAME IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC);
    echo "\nFOREIGN KEYS:\n";
    foreach ($fks as $fk) {
        echo " - {$fk['CONSTRAINT_NAME']} : {$fk['COLUMN_NAME']} -> {$fk['REFERENCED_TABLE_NAME']}({$fk['REFERENCED_COLUMN_NAME']})\n";
    }

    echo "\nOK\n";
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
