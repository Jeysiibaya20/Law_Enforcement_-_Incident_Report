<?php
require_once __DIR__ . '/../config/db_connect.php';

$pdo = getDBConnection();
$stmt = $pdo->query("SELECT user_id, fullname, emailadd, username, password, role FROM signup");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total Signup Accounts: " . count($users) . "\n";
foreach ($users as $u) {
    echo "ID: {$u['user_id']} | Email: {$u['emailadd']} | Username: {$u['username']} | Role: {$u['role']} | PassHashLen: " . strlen($u['password']) . "\n";
}
