<?php
/**
 * Setup Script: Add Role Column to Signup Table
 * 
 * This script adds the role column to the signup table if it doesn't exist.
 */

require_once 'config/db_connect.php';

$message = '';
$messageType = '';
$success = false;

try {
    // Check if role column exists
    $sql = "SHOW COLUMNS FROM signup LIKE 'role'";
    $result = $pdo->query($sql)->fetch();

    if ($result) {
        $message = '✅ Role column already exists in signup table';
        $messageType = 'success';
        $success = true;
    } else {
        // Add role column after emailadd
        $sql = "ALTER TABLE signup ADD COLUMN role VARCHAR(50) DEFAULT 'User' AFTER emailadd";
        $pdo->query($sql);
        
        $message = '✅ Role column successfully added to signup table!';
        $messageType = 'success';
        $success = true;

        // Update Jeyceebaya to Admin
        $updateSql = "UPDATE signup SET role = 'Admin' WHERE username = 'Jeyceebaya'";
        $pdo->query($updateSql);
        
        $message .= '<br><strong>✅ Jeyceebaya account updated to Admin role</strong>';
    }

} catch (Exception $e) {
    $message = '❌ Error: ' . htmlspecialchars($e->getMessage());
    $messageType = 'danger';
    $success = false;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup - Role Column</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body class="bg-light">
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow-lg">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-gear"></i> Database Setup</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
                        <?= $message ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>

                    <?php if ($success): ?>
                    <div class="alert alert-info mt-3">
                        <i class="bi bi-info-circle"></i>
                        <strong>Setup Complete!</strong>
                        <p class="mt-2 mb-0">You can now log in with your admin account:</p>
                        <ul class="mt-2 mb-0">
                            <li><strong>Username:</strong> Jeyceebaya</li>
                            <li><strong>Password:</strong> Admin123</li>
                        </ul>
                    </div>

                    <div class="d-grid gap-2 mt-4">
                        <a href="auth/login.php" class="btn btn-primary btn-lg">
                            <i class="bi bi-box-arrow-in-right"></i> Go to Login
                        </a>
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="bi bi-house"></i> Go to Home
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
