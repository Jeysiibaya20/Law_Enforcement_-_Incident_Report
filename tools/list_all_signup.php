<?php
require_once __DIR__ . '/../config/db_connect.php';

try {
    $stmt = $pdo->prepare("SELECT user_id, username, emailadd, role FROM signup ORDER BY user_id DESC LIMIT 50");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) {
        echo "No rows in signup table.\n";
        exit(0);
    }
    echo "user_id | username | email | role\n";
    echo str_repeat('-', 80) . "\n";
    foreach ($rows as $r) {
        echo sprintf("%6s | %-20s | %-25s | %-10s\n", $r['user_id'], $r['username'] ?? '', $r['emailadd'] ?? '', $r['role'] ?? '');
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>