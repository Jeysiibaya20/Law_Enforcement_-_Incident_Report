<?php
/**
 * Quick Admin User Creation Script
 * This script creates/updates an admin user account
 */

require_once '../config/db_connect.php';

$username = 'Jeyceebaya';
$password = 'Admin123';
$fullname = 'Jeyceebaya Admin';
$email = 'admin@alertara.local';

try {
    // Check if user exists
    $checkStmt = $pdo->prepare("SELECT user_id FROM signup WHERE username = ?");
    $checkStmt->execute([$username]);
    $existingUser = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if ($existingUser) {
        // Update existing user to admin
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $updateStmt = $pdo->prepare("
            UPDATE signup 
            SET password = ?, role = 'Admin', email_verified = 1, terms_accepted = 1
            WHERE username = ?
        ");
        $updateStmt->execute([$hashedPassword, $username]);
        $message = "✅ Admin user '{$username}' updated successfully!";
        $status = 'success';
    } else {
        // Create new admin user
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("
            INSERT INTO signup 
            (fullname, email, username, password, role, email_verified, terms_accepted, created_at)
            VALUES (?, ?, ?, ?, 'Admin', 1, 1, NOW())
        ");
        $stmt->execute([$fullname, $email, $username, $hashedPassword]);
        $message = "✅ Admin user '{$username}' created successfully!";
        $status = 'success';
    }

    $success = true;
} catch (Exception $e) {
    $message = "❌ Error: " . $e->getMessage();
    $status = 'danger';
    $success = false;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Admin User</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body>
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-shield-check"></i> Add Admin User</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-<?= $status ?>" role="alert">
                        <?= $message ?>
                    </div>

                    <?php if ($success): ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <tr>
                                <th>Username</th>
                                <td><strong><?= htmlspecialchars($username) ?></strong></td>
                            </tr>
                            <tr>
                                <th>Password</th>
                                <td><strong><?= htmlspecialchars($password) ?></strong></td>
                            </tr>
                            <tr>
                                <th>Role</th>
                                <td><span class="badge bg-danger">Admin</span></td>
                            </tr>
                            <tr>
                                <th>Email Verified</th>
                                <td><span class="badge bg-success">Yes</span></td>
                            </tr>
                            <tr>
                                <th>Terms Accepted</th>
                                <td><span class="badge bg-success">Yes</span></td>
                            </tr>
                        </table>
                    </div>

                    <div class="alert alert-info mt-3">
                        <i class="bi bi-info-circle"></i>
                        <strong>Next Steps:</strong>
                        <ol class="mb-0 mt-2">
                            <li>Go to <a href="../auth/login.php" class="alert-link">Login Page</a></li>
                            <li>Login with username: <strong><?= htmlspecialchars($username) ?></strong></li>
                            <li>Password: <strong><?= htmlspecialchars($password) ?></strong></li>
                            <li>You'll see "Admin Panel" in the sidebar</li>
                            <li>Access full admin control</li>
                        </ol>
                    </div>

                    <div class="d-grid gap-2">
                        <a href="dashboard.php" class="btn btn-primary">
                            <i class="bi bi-speedometer2"></i> Go to Admin Dashboard
                        </a>
                        <a href="../auth/login.php" class="btn btn-outline-primary">
                            <i class="bi bi-box-arrow-in-right"></i> Go to Login
                        </a>
                        <a href="../index.php" class="btn btn-outline-secondary">
                            <i class="bi bi-house"></i> Go to Home
                        </a>
                    </div>

                    <hr class="my-4">

                    <p class="text-muted small">
                        <i class="bi bi-shield-exclamation"></i> 
                        This is a direct setup script. You can delete this file after first use.
                    </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
