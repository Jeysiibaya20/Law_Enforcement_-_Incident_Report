<?php
require_once 'config/db_connect.php';
$pdo = getDBConnection();
$stmt = $pdo->query("DESCRIBE cctv_footage_received");
echo "=== cctv_footage_received columns ===\n";
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "{$row['Field']} ({$row['Type']})\n";
}
