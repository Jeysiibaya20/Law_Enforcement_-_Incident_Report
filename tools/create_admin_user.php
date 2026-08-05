<?php
/**
 * Script to create/provision Admin Account
 * Email: joecelgarcia1@gmail.com
 * Usernames: joecelgarcia1@gmail.com AND joecelgarcia1
 * Password: Admin#123
 * Role: Admin
 */

require_once __DIR__ . '/../config/db_connect.php';

$pdo = getDBConnection();

$email = 'joecelgarcia1@gmail.com';
$usernames = ['joecelgarcia1@gmail.com', 'joecelgarcia1'];
$fullname = 'Joecel Garcia';
$password = 'Admin#123';
$password_hash = password_hash($password, PASSWORD_DEFAULT);
$role = 'Admin';

foreach ($usernames as $username) {
    try {
        $stmt = $pdo->prepare("SELECT user_id FROM signup WHERE emailadd = ? OR username = ? LIMIT 1");
        $stmt->execute([$email, $username]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $update = $pdo->prepare("UPDATE signup SET fullname = ?, emailadd = ?, username = ?, password = ?, role = 'Admin', email_verified = 1 WHERE user_id = ?");
            $update->execute([$fullname, $email, $username, $password_hash, $existing['user_id']]);
            echo "SUCCESS: Updated Admin user '{$username}' (ID: {$existing['user_id']})\n";
        } else {
            $insert = $pdo->prepare("INSERT INTO signup (fullname, emailadd, username, password, role, email_verified, created_at) VALUES (?, ?, ?, ?, 'Admin', 1, NOW())");
            $insert->execute([$fullname, $email, $username, $password_hash]);
            $new_id = $pdo->lastInsertId();
            echo "SUCCESS: Created Admin user '{$username}' (ID: {$new_id})\n";
        }
    } catch (Exception $e) {
        echo "ERROR for {$username}: " . $e->getMessage() . "\n";
    }
}
