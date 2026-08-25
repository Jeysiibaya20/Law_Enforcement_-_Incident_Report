<?php
require_once __DIR__ . '/../config/db_connect.php';
$pdo = getDBConnection();
try {
    $pdo->exec("ALTER TABLE chain_of_custody MODIFY COLUMN action_type VARCHAR(100) NOT NULL DEFAULT 'Transferred'");
    echo "ALTER_SUCCESS\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
