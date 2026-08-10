<?php
require_once 'admin_auth.php';

$base_url = '../';
$page_title = 'System Settings';
require_once '../includes/header.php';

// Handle settings update
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_email_settings') {
        $email_host = $_POST['email_host'] ?? 'smtp.gmail.com';
        $email_port = $_POST['email_port'] ?? '465';
        $email_user = $_POST['email_user'] ?? 'alertaraqc@gmail.com';
        
        // In a production system, these would be saved to a database or config file
        $_SESSION['admin_settings_updated'] = true;
        $message = 'Email settings updated successfully!';
        $messageType = 'success';
    }
    
    if ($action === 'update_system_settings') {
        // System settings would be saved here
        $_SESSION['system_settings_updated'] = true;
        $message = 'System settings updated successfully!';
        $messageType = 'success';
    }
}
?>

<div class="main-content">
    <div class="content-container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h2">System Settings</h1>
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Back
            </a>
        </div>

        <?php if (!empty($message)): ?>
        <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle"></i> <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <div class="row">
            <!-- Email Settings -->
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h5><i class="bi bi-envelope"></i> Email Settings</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="update_email_settings">
                            
                            <div class="mb-3">
                                <label for="email_host" class="form-label">SMTP Host</label>
                                <input type="text" class="form-control" id="email_host" name="email_host" 
                                       value="smtp.gmail.com" placeholder="smtp.gmail.com">
                                <small class="text-muted">The SMTP server address for sending emails</small>
                            </div>
                            
                            <div class="mb-3">
                                <label for="email_port" class="form-label">SMTP Port</label>
                                <input type="number" class="form-control" id="email_port" name="email_port" 
                                       value="465" placeholder="465">
                                <small class="text-muted">Usually 465 for SSL or 587 for TLS</small>
                            </div>
                            
                            <div class="mb-3">
                                <label for="email_user" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="email_user" name="email_user" 
                                       value="alertaraqc@gmail.com" placeholder="your-email@gmail.com">
                                <small class="text-muted">Email address used for sending notifications</small>
                            </div>
                            
                            <div class="alert alert-warning alert-sm" role="alert">
                                <i class="bi bi-exclamation-triangle"></i>
                                <strong>Note:</strong> Password is not shown here for security. 
                                <a href="#" data-bs-toggle="modal" data-bs-target="#changePasswordModal">
                                    Change password
                                </a>
                            </div>
                            
                            <button type="submit" class="btn btn-success w-100 shadow-sm" style="background-color: #2e856e !important; border-color: #2e856e !important; color: #ffffff !important;">
                                <i class="bi bi-save me-1"></i> Save Email Settings
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- System Settings -->
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h5><i class="bi bi-gear"></i> System Settings</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="update_system_settings">
                            
                            <div class="mb-3">
                                <label for="email_verification" class="form-label">Email Verification Required</label>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="email_verification" 
                                           name="email_verification" checked>
                                    <label class="form-check-label" for="email_verification">
                                        Users must verify email before login
                                    </label>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="terms_required" class="form-label">Terms Acceptance</label>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="terms_required" 
                                           name="terms_required" checked>
                                    <label class="form-check-label" for="terms_required">
                                        Users must accept terms and conditions
                                    </label>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="two_factor" class="form-label">Two-Factor Authentication</label>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="two_factor" 
                                           name="two_factor">
                                    <label class="form-check-label" for="two_factor">
                                        Enable 2FA for admin accounts
                                    </label>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="token_expiry" class="form-label">Email Token Expiry (hours)</label>
                                <input type="number" class="form-control" id="token_expiry" name="token_expiry" 
                                       value="24" placeholder="24" min="1">
                                <small class="text-muted">How long verification tokens remain valid</small>
                            </div>
                            
                            <button type="submit" class="btn btn-success w-100 shadow-sm" style="background-color: #2e856e !important; border-color: #2e856e !important; color: #ffffff !important;">
                                <i class="bi bi-save me-1"></i> Save System Settings
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Admin Users Management -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="bi bi-shield-lock"></i> Admin Users</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        try {
                            $admins = $pdo->query("SELECT user_id, fullname, emailadd AS email, username, created_at FROM signup WHERE role = 'Admin' ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
                        } catch (Exception $e) {
                            $admins = [];
                        }
                        ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Username</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($admins as $admin): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($admin['fullname']) ?></td>
                                        <td><?= htmlspecialchars($admin['email']) ?></td>
                                        <td><?= htmlspecialchars($admin['username']) ?></td>
                                        <td><?= date('M d, Y', strtotime($admin['created_at'])) ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-secondary" disabled>
                                                <i class="bi bi-person-check"></i> View
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Information -->
        <div class="row">
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="bi bi-info-circle"></i> System Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush">
                            <div class="list-group-item d-flex justify-content-between">
                                <span>PHP Version</span>
                                <span class="fw-bold"><?= PHP_VERSION ?></span>
                            </div>
                            <div class="list-group-item d-flex justify-content-between">
                                <span>Server</span>
                                <span class="fw-bold"><?= $_SERVER['SERVER_SOFTWARE'] ?? 'Apache' ?></span>
                            </div>
                            <div class="list-group-item d-flex justify-content-between">
                                <span>Database</span>
                                <span class="fw-bold">MySQL</span>
                            </div>
                            <div class="list-group-item d-flex justify-content-between">
                                <span>Framework</span>
                                <span class="fw-bold">Bootstrap 5</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="bi bi-database"></i> Database Information</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        try {
                            $dbInfo = $pdo->query("SELECT VERSION()")->fetchColumn();
                            $dbSize = $pdo->query("SELECT SUM(data_length + index_length) FROM information_schema.TABLES WHERE table_schema = 'law&inci'")->fetchColumn();
                            $dbSize = $dbSize ? round($dbSize / 1024 / 1024, 2) : 0;
                            $tableCount = $pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE table_schema = 'law&inci'")->fetchColumn();
                        } catch (Exception $e) {
                            $dbInfo = 'N/A';
                            $dbSize = 0;
                            $tableCount = 0;
                        }
                        ?>
                        <div class="list-group list-group-flush">
                            <div class="list-group-item d-flex justify-content-between">
                                <span>Database Name</span>
                                <span class="fw-bold">law&inci</span>
                            </div>
                            <div class="list-group-item d-flex justify-content-between">
                                <span>Version</span>
                                <span class="fw-bold"><?= htmlspecialchars($dbInfo) ?></span>
                            </div>
                            <div class="list-group-item d-flex justify-content-between">
                                <span>Database Size</span>
                                <span class="fw-bold"><?= $dbSize ?> MB</span>
                            </div>
                            <div class="list-group-item d-flex justify-content-between">
                                <span>Total Tables</span>
                                <span class="fw-bold"><?= $tableCount ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Change Password Modal -->
<div class="modal fade" id="changePasswordModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Change SMTP Password</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_email_password">
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i>
                        This is the Gmail app-specific password used for sending emails.
                    </div>
                    <div class="mb-3">
                        <label for="email_password" class="form-label">SMTP Password</label>
                        <input type="password" class="form-control" id="email_password" name="email_password" 
                               placeholder="Enter app-specific password" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
