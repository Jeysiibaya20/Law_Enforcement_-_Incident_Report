<?php
/**
 * Admin User Creation & Management Page
 * Synced with EMERGENCY-COM standard design tokens
 */

require_once __DIR__ . '/admin_auth.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    $pdo = getDBConnection();
}

$base_url = '../';
$page_title = 'Create Admin Account';

$error_message = '';
$success_message = '';

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $fullname = trim($_POST['fullname'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = trim($_POST['role'] ?? 'Admin');

        if (empty($fullname) || empty($username) || empty($email) || empty($password)) {
            throw new Exception('Please fill in all required fields.');
        }

        if (strlen($password) < 6) {
            throw new Exception('Password must be at least 6 characters long.');
        }

        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        // Check if username or email already exists in `signup`
        $check_stmt = $pdo->prepare("SELECT user_id, role FROM signup WHERE username = ? OR emailadd = ? LIMIT 1");
        $check_stmt->execute([$username, $email]);
        $existing = $check_stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            // Update existing user's role to Admin and update password
            $update_stmt = $pdo->prepare("
                UPDATE signup 
                SET fullname = ?, emailadd = ?, username = ?, password = ?, role = ?, email_verified = 1 
                WHERE user_id = ?
            ");
            $update_stmt->execute([$fullname, $email, $username, $password_hash, $role, $existing['user_id']]);
            $success_message = "Account for <strong>" . htmlspecialchars($username) . "</strong> updated successfully to <strong>" . htmlspecialchars($role) . "</strong>!";
        } else {
            // Create new Admin user
            $insert_stmt = $pdo->prepare("
                INSERT INTO signup (fullname, emailadd, username, password, role, email_verified, terms_accepted, created_at) 
                VALUES (?, ?, ?, ?, ?, 1, 1, NOW())
            ");
            $insert_stmt->execute([$fullname, $email, $username, $password_hash, $role]);
            $success_message = "New account <strong>" . htmlspecialchars($username) . "</strong> (" . htmlspecialchars($role) . ") created successfully!";
        }
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Fetch list of current Admin & Officer accounts
try {
    $admins_stmt = $pdo->query("
        SELECT user_id, fullname, emailadd, username, role, email_verified, created_at 
        FROM signup 
        WHERE role IN ('Admin', 'Officer') 
        ORDER BY created_at DESC
    ");
    $admin_users = $admins_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $admin_users = [];
}

require_once '../includes/header.php';
?>

<div class="main-content py-4 px-3 px-md-4">
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 pb-3 border-bottom">
            <div>
                <h2 class="h3 font-weight-bold text-dark mb-1">
                    <i class="fas fa-user-shield text-primary me-2"></i>Create Admin / Officer Account
                </h2>
                <p class="text-secondary small mb-0">Provision administrative accounts with elevated privileges for system management.</p>
            </div>
            <div class="mt-3 mt-md-0">
                <a href="users.php" class="btn btn-outline-secondary btn-sm me-2">
                    <i class="fas fa-users me-1"></i> View All Users
                </a>
                <a href="dashboard.php" class="btn btn-primary btn-sm">
                    <i class="fas fa-chart-line me-1"></i> Dashboard
                </a>
            </div>
        </div>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show mb-4 shadow-sm" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show mb-4 shadow-sm" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <!-- Admin Creation Form -->
            <div class="col-lg-5">
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h5 class="card-title mb-0 font-weight-bold text-dark">
                            <i class="fas fa-user-plus text-primary me-2"></i>Account Creation Form
                        </h5>
                    </div>
                    <div class="card-body p-4">
                        <form action="add_admin.php" method="POST" autocomplete="off">
                            <div class="mb-3">
                                <label for="fullname" class="form-label font-weight-semibold">Full Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="fullname" name="fullname" placeholder="e.g. Joecel Garcia" required>
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label font-weight-semibold">Email Address <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="email" name="email" placeholder="e.g. admin@alertaraqc.com" required>
                            </div>

                            <div class="mb-3">
                                <label for="username" class="form-label font-weight-semibold">Username <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="username" name="username" placeholder="e.g. joecel_admin" required>
                            </div>

                            <div class="mb-3">
                                <label for="password" class="form-label font-weight-semibold">Password <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="password" name="password" placeholder="Enter secure password" required minlength="6">
                                    <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div class="form-text">Minimum 6 characters.</div>
                            </div>

                            <div class="mb-4">
                                <label for="role" class="form-label font-weight-semibold">Assigned Role <span class="text-danger">*</span></label>
                                <select class="form-select" id="role" name="role" required>
                                    <option value="Admin" selected>Admin (Full System Access)</option>
                                    <option value="Officer">Officer (Case & Incident Management)</option>
                                </select>
                            </div>

                            <button type="submit" class="btn btn-primary w-100 py-2 font-weight-semibold shadow-sm">
                                <i class="fas fa-user-shield me-2"></i>Create Administrative Account
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Existing Administrative Accounts Table -->
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0 font-weight-bold text-dark">
                            <i class="fas fa-users-cog text-primary me-2"></i>Administrative Accounts
                        </h5>
                        <span class="badge bg-primary rounded-pill"><?php echo count($admin_users); ?> Accounts</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-4">User</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Status</th>
                                        <th class="pe-4 text-end">Created</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($admin_users)): ?>
                                        <?php foreach ($admin_users as $u): ?>
                                            <tr>
                                                <td class="ps-4">
                                                    <div class="d-flex align-items-center">
                                                        <div class="avatar me-3 bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 36px; height: 36px; font-weight: 600;">
                                                            <?php echo strtoupper(substr($u['fullname'] ?: $u['username'], 0, 1)); ?>
                                                        </div>
                                                        <div>
                                                            <div class="font-weight-semibold text-dark"><?php echo htmlspecialchars($u['fullname'] ?: $u['username']); ?></div>
                                                            <small class="text-secondary">@<?php echo htmlspecialchars($u['username']); ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><small class="text-muted"><?php echo htmlspecialchars($u['emailadd']); ?></small></td>
                                                <td>
                                                    <span class="badge <?php echo strtolower($u['role']) === 'admin' ? 'bg-danger' : 'bg-primary'; ?>">
                                                        <?php echo htmlspecialchars($u['role']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-success-subtle text-success border border-success-subtle">Verified</span>
                                                </td>
                                                <td class="pe-4 text-end"><small class="text-muted"><?php echo date('M d, Y', strtotime($u['created_at'])); ?></small></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center py-4 text-muted">No administrative accounts found.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const toggleBtn = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');
    if (toggleBtn && passwordInput) {
        toggleBtn.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            this.querySelector('i').classList.toggle('fa-eye');
            this.querySelector('i').classList.toggle('fa-eye-slash');
        });
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>
