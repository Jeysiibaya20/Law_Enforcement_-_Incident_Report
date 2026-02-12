<?php
/**
 * Setup Officer Test Account
 * Creates or updates the "officer" account for testing
 */
require_once 'config/db_connect.php';

try {
    $username = 'officer';
    $password = 'Officer123';
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $fullname = 'Test Officer';
    $email = 'officer@alertara.local';
    
    // Check if officer account already exists in signup table
    $check_sql = "SELECT user_id FROM signup WHERE username = ?";
    $check_stmt = $pdo->prepare($check_sql);
    $check_stmt->execute([$username]);
    $existing = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        // Update existing account
        echo "Officer account found. Updating credentials...<br>";
        $update_sql = "UPDATE signup SET password = ?, email_verified = 1 WHERE username = ?";
        $update_stmt = $pdo->prepare($update_sql);
        $update_stmt->execute([$hashed_password, $username]);
        echo "✅ Officer account password updated and email verified<br>";
    } else {
        // Create new account
        echo "Officer account not found. Creating new account...<br>";
        $insert_sql = "INSERT INTO signup (fullname, emailadd, username, password, role, email_verified, terms_accepted, created_at) 
                       VALUES (?, ?, ?, ?, 'Officer', 1, 1, NOW())";
        $insert_stmt = $pdo->prepare($insert_sql);
        $insert_stmt->execute([$fullname, $email, $username, $hashed_password]);
        echo "✅ Officer account created successfully<br>";
    }
    
    // Verify the account is set up correctly
    $verify_sql = "SELECT user_id, username, email_verified FROM signup WHERE username = ?";
    $verify_stmt = $pdo->prepare($verify_sql);
    $verify_stmt->execute([$username]);
    $user = $verify_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        echo "<br><strong>Account Details:</strong><br>";
        echo "Username: " . htmlspecialchars($user['username']) . "<br>";
        echo "Email Verified: " . ($user['email_verified'] ? 'Yes ✓' : 'No ✗') . "<br>";
        echo "User ID: " . $user['user_id'] . "<br>";
        echo "<br>You can now login with:<br>";
        echo "<strong>Username: officer<br>";
        echo "Password: Officer123</strong><br>";
        echo "<br><a href='auth/login.php'>Go to Login</a>";
    } else {
        echo "❌ Error: Account could not be verified after setup";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . htmlspecialchars($e->getMessage());
    error_log("Officer setup error: " . $e->getMessage());
}
?>
