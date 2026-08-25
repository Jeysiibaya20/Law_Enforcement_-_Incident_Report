<?php
require_once 'config/db_connect.php';
$pdo = getDBConnection();

$stmt = $pdo->query("SHOW TRIGGERS");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "Trigger `{$row['Trigger']}` on Table `{$row['Table']}` Event `{$row['Event']}`:\n{$row['Statement']}\n\n";
}
