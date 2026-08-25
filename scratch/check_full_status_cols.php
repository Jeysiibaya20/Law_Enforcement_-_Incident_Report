<?php
require_once 'config/db_connect.php';
$pdo = getDBConnection();

$tables = ['cctv_requests', 'external_integration_log', 'incidents'];
foreach ($tables as $t) {
    echo "=== Table `$t` Full Columns ===\n";
    $stmt = $pdo->query("SHOW FULL COLUMNS FROM $t");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($row['Field'] === 'status') {
            echo "  Field: {$row['Field']} | Type: {$row['Type']} | Collation: {$row['Collation']} | Null: {$row['Null']} | Default: {$row['Default']}\n";
        }
    }
}
