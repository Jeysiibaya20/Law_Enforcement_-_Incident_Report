<?php
/**
 * Script to create/provision Admin Account
 * Email: joecelgarcia1@gmail.com
 * Password: Admin#123
 * Role: Admin
 */

require_once __DIR__ . '/../config/db_connect.php';

$pdo = getDBConnection();

$email = 'joecelgarcia1@gmail.com';
$username = 'joecelgarcia1@gmail.com';
$fullname = 'Joecel Garcia';
$password = 'Admin#123';
$password_hash = password_hash($password, PASSWORD_DEFAULT);
$role = 'Admin';
$email_verified = 1;

try {
    // Check if account exists by email or username
    $stmt = $pdo->prepare("SELECT user_id FROM signup WHERE emailadd = ? OR username = ? LIMIT 1");
    $stmt->execute([$email, $username]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        // Update existing user to Admin with new password hash and email_verified=1
        $update = $pdo->prepare("UPDATE signup SET fullname = ?, emailadd = ?, username = ?, password = ?, role = ?, email_verified = 1 WHERE user_id = ?");
        $update->execute([$fullname, $email, $username, $password_hash, $role, $existing['user_id']]);
        echo "SUCCESS: Admin account updated for {$email} (User ID: {$existing['user_id']})\n";
    } else {
        // Insert new Admin user into signup table
        $insert = $pdo->prepare("INSERT INTO signup (fullname, emailadd, username, password, role, email_verified, created_at) VALUES (?, ?, ?, ?, ?, 1, NOW())");
        $insert->execute([$fullname, $email, $username, $password_hash, $role]);
        $new_id = $pdo->lastInsertId();
        echo "SUCCESS: Admin account created for {$email} (User ID: {$new_id})\n";
    }

    // Also check if `users` table exists and update/insert for compatibility
    $check_users = $pdo->query("SHOW TABLES LIKE 'users'")->rowCount();
    if ($check_users > 0) {
        $u_stmt = $pdo->prepare("SELECT user_id FROM users WHERE username = ? LIMIT 1");
        $u_stmt->execute([$username]);
        $u_exist = $u_stmt->fetch(PDO::FETCH_ASSOC);

        if ($u_exist) {
            $u_up = $pdo->prepare("UPDATE users SET password_hash = ?, email_verified = 1 WHERE user_id = ?");
            $u_up->execute([$password_hash, $u_exist['user_id']]);
        }
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
