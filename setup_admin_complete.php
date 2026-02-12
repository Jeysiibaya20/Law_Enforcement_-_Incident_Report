<?php
/**
 * Complete Admin Setup Script
 * 
 * This script:
 * 1. Adds role column if missing
 * 2. Creates/updates Jeyceebaya admin account
 * 3. Shows current database status
 */

require_once 'config/db_connect.php';

$messages = [];
$success = true;

try {
    // Step 1: Check and add role column
    $roleCheckSql = "SHOW COLUMNS FROM signup LIKE 'role'";
    $roleResult = $pdo->query($roleCheckSql)->fetch();

    if (!$roleResult) {
        // Add role column
        $addRoleSql = "ALTER TABLE signup ADD COLUMN role VARCHAR(50) DEFAULT 'User' AFTER emailadd";
        $pdo->query($addRoleSql);
        $messages[] = ['type' => 'success', 'text' => '✅ Role column added to signup table'];
    } else {
        $messages[] = ['type' => 'info', 'text' => 'ℹ️ Role column already exists'];
    }

    // Step 2: Check if Jeyceebaya exists
    $checkUserSql = "SELECT user_id, username, email_verified FROM signup WHERE username = 'Jeyceebaya'";
    $userResult = $pdo->query($checkUserSql)->fetch();

    if ($userResult) {
        // Update existing user
        $hashedPassword = password_hash('Admin123', PASSWORD_DEFAULT);
        $updateSql = "UPDATE signup SET password = ?, role = 'Admin', email_verified = 1, terms_accepted = 1 WHERE username = 'Jeyceebaya'";
        $stmt = $pdo->prepare($updateSql);
        $stmt->execute([$hashedPassword]);
        $messages[] = ['type' => 'success', 'text' => '✅ Jeyceebaya account updated with correct password and Admin role'];
    } else {
        // Create new admin user
        $hashedPassword = password_hash('Admin123', PASSWORD_DEFAULT);
        $createSql = "INSERT INTO signup (fullname, emailadd, username, password, role, email_verified, terms_accepted, created_at) 
                      VALUES ('Jeyceebaya Admin', 'admin@alertara.local', 'Jeyceebaya', ?, 'Admin', 1, 1, NOW())";
        $stmt = $pdo->prepare($createSql);
        $stmt->execute([$hashedPassword]);
        $messages[] = ['type' => 'success', 'text' => '✅ Jeyceebaya admin account created successfully'];
    }

    // Step 3: Verify the account
    $verifySql = "SELECT user_id, username, role, email_verified, password FROM signup WHERE username = 'Jeyceebaya' LIMIT 1";
    $verifyResult = $pdo->query($verifySql)->fetch();

    if ($verifyResult) {
        $messages[] = ['type' => 'success', 'text' => '✅ Account verified in database'];
        $messages[] = ['type' => 'info', 'text' => 'Username: <strong>Jeyceebaya</strong>'];
        $messages[] = ['type' => 'info', 'text' => 'Role: <strong>' . htmlspecialchars($verifyResult['role']) . '</strong>'];
        $messages[] = ['type' => 'info', 'text' => 'Email Verified: <strong>' . ($verifyResult['email_verified'] ? 'Yes' : 'No') . '</strong>'];
        $messages[] = ['type' => 'info', 'text' => 'Password: <strong>Hashed (' . substr($verifyResult['password'], 0, 20) . '...)</strong>'];
    } else {
        $messages[] = ['type' => 'danger', 'text' => '❌ Failed to verify account in database'];
        $success = false;
    }

} catch (Exception $e) {
    $messages[] = ['type' => 'danger', 'text' => '❌ Error: ' . htmlspecialchars($e->getMessage())];
    $success = false;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Setup - Complete</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body class="bg-light">
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-7">
            <div class="card shadow-lg">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-shield-check"></i> Complete Admin Setup</h5>
                </div>
                <div class="card-body">
                    <!-- Messages -->
                    <div class="setup-messages">
                        <?php foreach ($messages as $msg): ?>
                        <div class="alert alert-<?= $msg['type'] ?> alert-dismissible fade show mb-2" role="alert">
                            <?= $msg['text'] ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($success): ?>
                    <!-- Success Section -->
                    <div class="alert alert-success mt-4" role="alert">
                        <h5 class="mb-3"><i class="bi bi-check-circle"></i> Setup Complete!</h5>
                        <p class="mb-2"><strong>Your admin account is ready to use:</strong></p>
                        <ul class="mb-0">
                            <li><strong>Username:</strong> Jeyceebaya</li>
                            <li><strong>Password:</strong> Admin123</li>
                            <li><strong>Role:</strong> Admin</li>
                            <li><strong>Email Verified:</strong> Yes</li>
                        </ul>
                    </div>

                    <!-- Action Buttons -->
                    <div class="d-grid gap-2">
                        <a href="auth/login.php" class="btn btn-primary btn-lg">
                            <i class="bi bi-box-arrow-in-right"></i> Go to Login Page
                        </a>
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="bi bi-house"></i> Go to Home
                        </a>
                    </div>

                    <!-- Login Instructions -->
                    <div class="alert alert-info mt-4">
                        <h6 class="mb-2"><i class="bi bi-info-circle"></i> Next Steps:</h6>
                        <ol class="mb-0">
                            <li>Click "Go to Login Page" button above</li>
                            <li>Enter username: <code>Jeyceebaya</code></li>
                            <li>Enter password: <code>Admin123</code></li>
                            <li>Click Sign In</li>
                            <li>You'll see "Admin Panel" link in the sidebar</li>
                            <li>Click Admin Panel for full admin control</li>
                        </ol>
                    </div>

                    <?php else: ?>
                    <!-- Error Section -->
                    <div class="alert alert-danger mt-4">
                        <h5>Setup Failed</h5>
                        <p>There was an error during setup. Please check the messages above.</p>
                    </div>
                    <a href="setup_admin_complete.php" class="btn btn-primary">
                        <i class="bi bi-arrow-clockwise"></i> Try Again
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Debug Info -->
            <div class="card mt-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-terminal"></i> Database Status</h6>
                </div>
                <div class="card-body">
                    <?php
                    try {
                        $tableInfo = $pdo->query("DESCRIBE signup")->fetchAll(PDO::FETCH_ASSOC);
                        $hasRoleColumn = false;
                        foreach ($tableInfo as $col) {
                            if ($col['Field'] === 'role') {
                                $hasRoleColumn = true;
                                break;
                            }
                        }
                        echo '<p><strong>Role Column:</strong> ' . ($hasRoleColumn ? '✅ Present' : '❌ Missing') . '</p>';
                        
                        $userCount = $pdo->query("SELECT COUNT(*) as cnt FROM signup WHERE username = 'Jeyceebaya'")->fetch()['cnt'];
                        echo '<p><strong>Jeyceebaya Account:</strong> ' . ($userCount > 0 ? '✅ Exists' : '❌ Not Found') . '</p>';
                        
                        $totalUsers = $pdo->query("SELECT COUNT(*) as cnt FROM signup")->fetch()['cnt'];
                        echo '<p><strong>Total Users in Database:</strong> ' . $totalUsers . '</p>';
                    } catch (Exception $e) {
                        echo '<p class="text-danger">Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
