<?php
/**
 * Diagnose and Fix Officer Account Setup
 * Checks actual table structure and creates officer account appropriately
 */
require_once 'config/db_connect.php';

try {
    echo "<h3>Diagnosing signup table structure...</h3>";
    
    // Get actual columns in signup table
    $columns_sql = "DESCRIBE signup";
    $columns_stmt = $pdo->query($columns_sql);
    $columns = $columns_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<strong>Actual columns in signup table:</strong><br>";
    $column_names = [];
    foreach ($columns as $col) {
        echo "- " . $col['Field'] . " (" . $col['Type'] . ")<br>";
        $column_names[] = $col['Field'];
    }
    
    echo "<br><strong>Checking for required columns:</strong><br>";
    $has_fullname = in_array('fullname', $column_names);
    $has_email = in_array('emailadd', $column_names) || in_array('email', $column_names);
    $has_username = in_array('username', $column_names);
    $has_password = in_array('password', $column_names) || in_array('password_hash', $column_names);
    
    echo "- fullname: " . ($has_fullname ? "✓" : "✗ MISSING") . "<br>";
    echo "- emailadd/email: " . ($has_email ? "✓" : "✗ MISSING") . "<br>";
    echo "- username: " . ($has_username ? "✓" : "✗ MISSING") . "<br>";
    echo "- password: " . ($has_password ? "✓" : "✗ MISSING") . "<br>";
    
    // If fullname is missing, add it
    if (!$has_fullname) {
        echo "<br><strong>Adding missing fullname column...</strong><br>";
        $pdo->query("ALTER TABLE signup ADD COLUMN fullname VARCHAR(150) NOT NULL DEFAULT 'User'");
        echo "✅ fullname column added<br>";
    }
    
    // If email_verified is missing, add it
    if (!in_array('email_verified', $column_names)) {
        echo "<strong>Adding missing email_verified column...</strong><br>";
        $pdo->query("ALTER TABLE signup ADD COLUMN email_verified TINYINT(1) DEFAULT 0");
        echo "✅ email_verified column added<br>";
    }
    
    // If role is missing, add it
    if (!in_array('role', $column_names)) {
        echo "<strong>Adding missing role column...</strong><br>";
        $pdo->query("ALTER TABLE signup ADD COLUMN role VARCHAR(50) DEFAULT 'User'");
        echo "✅ role column added<br>";
    }
    
    // If terms_accepted is missing, add it
    if (!in_array('terms_accepted', $column_names)) {
        echo "<strong>Adding missing terms_accepted column...</strong><br>";
        $pdo->query("ALTER TABLE signup ADD COLUMN terms_accepted TINYINT(1) DEFAULT 0");
        echo "✅ terms_accepted column added<br>";
    }
    
    echo "<br><hr><h3>Setting up officer account...</h3>";
    
    // Now create or update the officer account
    $username = 'officer';
    $password = 'Officer123';
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $fullname = 'Test Officer';
    $email = 'officer@alertara.local';
    
    $check_sql = "SELECT user_id FROM signup WHERE username = ?";
    $check_stmt = $pdo->prepare($check_sql);
    $check_stmt->execute([$username]);
    $existing = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        echo "Officer account exists. Updating...<br>";
        $update_sql = "UPDATE signup SET password = ?, fullname = ?, emailadd = ?, email_verified = 1, role = 'Officer', terms_accepted = 1 WHERE username = ?";
        $update_stmt = $pdo->prepare($update_sql);
        $update_stmt->execute([$hashed_password, $fullname, $email, $username]);
        echo "✅ Officer account updated<br>";
    } else {
        echo "Officer account does not exist. Creating...<br>";
        $insert_sql = "INSERT INTO signup (fullname, emailadd, username, password, email_verified, role, terms_accepted, created_at) 
                       VALUES (?, ?, ?, ?, 1, 'Officer', 1, NOW())";
        $insert_stmt = $pdo->prepare($insert_sql);
        $insert_stmt->execute([$fullname, $email, $username, $hashed_password]);
        echo "✅ Officer account created<br>";
    }
    
    // Verify
    $verify_sql = "SELECT user_id, username, fullname, emailadd, email_verified, role FROM signup WHERE username = ?";
    $verify_stmt = $pdo->prepare($verify_sql);
    $verify_stmt->execute([$username]);
    $user = $verify_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        echo "<br><strong>✅ Account Setup Complete!</strong><br>";
        echo "User ID: " . $user['user_id'] . "<br>";
        echo "Username: " . htmlspecialchars($user['username']) . "<br>";
        echo "Full Name: " . htmlspecialchars($user['fullname']) . "<br>";
        echo "Email: " . htmlspecialchars($user['emailadd']) . "<br>";
        echo "Role: " . htmlspecialchars($user['role']) . "<br>";
        echo "Email Verified: " . ($user['email_verified'] ? "Yes ✓" : "No ✗") . "<br>";
        echo "<br><strong>Login Credentials:</strong><br>";
        echo "Username: <code>officer</code><br>";
        echo "Password: <code>Officer123</code><br>";
        echo "<br><a href='auth/login.php' class='btn btn-primary'>Go to Login →</a>";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . htmlspecialchars($e->getMessage()) . "<br>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    error_log("Officer setup error: " . $e->getMessage());
}
?>
<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    h3 { color: #333; }
    code { background: #f0f0f0; padding: 2px 6px; border-radius: 3px; }
    a.btn { display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; margin-top: 10px; }
    a.btn:hover { background: #0056b3; }
</style>
