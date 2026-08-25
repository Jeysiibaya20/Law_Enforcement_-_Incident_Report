<?php
require_once 'config/db_connect.php';
$pdo = getDBConnection();

$tables = ['cctv_requests', 'external_integration_log'];
foreach ($tables as $t) {
    echo "=== Table `$t` Columns ===\n";
    $stmt = $pdo->query("DESCRIBE $t");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (strpos($row['Field'], 'status') !== false || $row['Field'] === 'status') {
            echo "  Field: {$row['Field']} | Type: {$row['Type']} | Null: {$row['Null']} | Default: {$row['Default']}\n";
        }
    }
}
