<?php
/**
 * Admin Panel Setup Script
 * 
 * This script helps set up an admin account in your system.
 * Run this once to create or verify admin user setup.
 * 
 * Access at: /admin/setup.php
 */

require_once '../config/db_connect.php';

$setup_complete = false;
$message = '';
$message_type = '';
$admin_users = [];

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'make_admin') {
        $user_id = (int)$_POST['user_id'] ?? 0;
        
        if ($user_id > 0) {
            try {
                $stmt = $pdo->prepare("UPDATE signup SET role = 'Admin' WHERE user_id = ?");
                $stmt->execute([$user_id]);
                
                $message = 'User has been promoted to Admin successfully!';
                $message_type = 'success';
                $setup_complete = true;
            } catch (Exception $e) {
                $message = 'Error: ' . $e->getMessage();
                $message_type = 'danger';
            }
        } else {
            $message = 'Please select a valid user.';
            $message_type = 'warning';
        }
    }
}

// Fetch all users who are not admins
try {
    $stmt = $pdo->query("SELECT user_id, fullname, emailadd AS email, username, created_at FROM signup WHERE role != 'Admin' ORDER BY created_at DESC");
    $admin_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $message = 'Database error: ' . $e->getMessage();
    $message_type = 'danger';
}

// Fetch current admins
try {
    $stmt = $pdo->query("SELECT user_id, fullname, emailadd AS email, username, created_at FROM signup WHERE role = 'Admin' ORDER BY created_at DESC");
    $current_admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $current_admins = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Setup - Law Enforcement Report System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 40px 20px;
        }
        .setup-container {
            max-width: 800px;
            margin: 0 auto;
        }
        .card {
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            border: none;
        }
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
        }
        .badge-admin {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
    </style>
</head>
<body>
<div class="setup-container">
    <div class="text-center mb-4">
        <h1 class="text-white mb-2"><i class="bi bi-shield-check"></i> Admin Setup</h1>
        <p class="text-white-50">Configure administrator accounts for your system</p>
    </div>

    <?php if (!empty($message)): ?>
    <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
        <i class="bi bi-<?= $message_type === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
        <?= htmlspecialchars($message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Current Admins -->
    <?php if (!empty($current_admins)): ?>
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-people-fill"></i> Current Admin Users (<?= count($current_admins) ?>)</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Username</th>
                            <th>Created</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($current_admins as $admin): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($admin['fullname']) ?></strong></td>
                            <td><?= htmlspecialchars($admin['email']) ?></td>
                            <td><?= htmlspecialchars($admin['username']) ?></td>
                            <td><?= date('M d, Y H:i', strtotime($admin['created_at'])) ?></td>
                            <td><span class="badge badge-admin">Admin</span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Promote User to Admin -->
    <?php if (!empty($admin_users)): ?>
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-person-plus"></i> Promote User to Admin</h5>
        </div>
        <div class="card-body">
            <form method="POST" action="">
                <input type="hidden" name="action" value="make_admin">
                
                <div class="mb-3">
                    <label for="user_id" class="form-label">Select User to Promote</label>
                    <select class="form-select form-select-lg" id="user_id" name="user_id" required>
                        <option value="">Choose a user...</option>
                        <?php foreach ($admin_users as $user): ?>
                        <option value="<?= $user['user_id'] ?>">
                            <?= htmlspecialchars($user['fullname']) ?> (<?= htmlspecialchars($user['email']) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">This will grant the selected user admin privileges.</small>
                </div>

                <div class="alert alert-info" role="alert">
                    <i class="bi bi-info-circle"></i>
                    <strong>Note:</strong> Only users with email verification and terms acceptance should be promoted to admin.
                </div>

                <button type="submit" class="btn btn-primary btn-lg w-100">
                    <i class="bi bi-shield-check"></i> Promote to Admin
                </button>
            </form>
        </div>
    </div>
    <?php else: ?>
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-info-circle"></i> No Users Available</h5>
        </div>
        <div class="card-body">
            <p class="mb-0">There are no regular users available to promote. Please create user accounts first by:</p>
            <ol class="mt-3">
                <li>Going to the signup page</li>
                <li>Creating new user accounts</li>
                <li>Coming back to this setup page</li>
            </ol>
            <a href="../auth/signup.php" class="btn btn-primary mt-3">
                <i class="bi bi-person-plus"></i> Create New User
            </a>
        </div>
    </div>
    <?php endif; ?>

    <!-- Instructions -->
    <div class="card mt-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-book"></i> Instructions</h5>
        </div>
        <div class="card-body">
            <h6>How to Access Admin Panel:</h6>
            <ol>
                <li>Once a user is promoted to Admin, they can log in</li>
                <li>Admin users will see "Admin Panel" link in the sidebar</li>
                <li>Click the Admin Panel link to access: <code>/admin/dashboard.php</code></li>
            </ol>

            <h6 class="mt-3">Admin Panel Features:</h6>
            <ul>
                <li><strong>Dashboard:</strong> Overview with KPI cards and recent activity</li>
                <li><strong>User Management:</strong> View, verify, and manage user accounts</li>
                <li><strong>Blotter Records:</strong> View and filter all incident reports</li>
                <li><strong>Reports & Analytics:</strong> System statistics and analytics</li>
                <li><strong>Settings:</strong> Configure system email and features</li>
            </ul>

            <h6 class="mt-3">Security Notes:</h6>
            <ul>
                <li>Only promote trusted users to admin</li>
                <li>Admin users have full system access</li>
                <li>Keep admin credentials secure</li>
                <li>Regularly monitor admin activity</li>
            </ul>
        </div>
    </div>

    <!-- Back Link -->
    <div class="text-center mt-4">
        <a href="../index.php" class="btn btn-light btn-lg">
            <i class="bi bi-arrow-left"></i> Back to Dashboard
        </a>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
