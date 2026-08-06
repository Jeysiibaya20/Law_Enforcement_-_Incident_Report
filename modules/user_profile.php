<?php
$base_url = '../';
require_once __DIR__ . '/../includes/user_auth.php';
$page_title = 'User Profile';

require_once __DIR__ . '/../config/db_connect.php';
if (!isset($pdo) || !($pdo instanceof PDO)) {
    $pdo = getDBConnection();
}

$userId = $_SESSION['user_id'] ?? null;
$message = '';
$messageType = '';

// Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $fullname = trim($_POST['fullname'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $barangay = trim($_POST['barangay'] ?? '');
    $address = trim($_POST['address'] ?? '');

    try {
        $updateStmt = $pdo->prepare("UPDATE signup SET fullname = ?, phone = ?, barangay = ?, address = ? WHERE user_id = ?");
        $updateStmt->execute([$fullname, $phone, $barangay, $address, $userId]);
        $_SESSION['fullname'] = $fullname;
        $message = 'Profile updated successfully!';
        $messageType = 'success';
    } catch (Exception $e) {
        $message = 'Failed to update profile: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

// Handle Password Change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($currentPassword) || empty($newPassword)) {
        $message = 'Please fill in all password fields.';
        $messageType = 'danger';
    } elseif ($newPassword !== $confirmPassword) {
        $message = 'New passwords do not match.';
        $messageType = 'danger';
    } elseif (strlen($newPassword) < 8) {
        $message = 'Password must be at least 8 characters long.';
        $messageType = 'danger';
    } else {
        try {
            $userStmt = $pdo->prepare("SELECT password FROM signup WHERE user_id = ?");
            $userStmt->execute([$userId]);
            $u = $userStmt->fetch(PDO::FETCH_ASSOC);

            if ($u && password_verify($currentPassword, $u['password'])) {
                $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
                $passUpdate = $pdo->prepare("UPDATE signup SET password = ? WHERE user_id = ?");
                $passUpdate->execute([$newHash, $userId]);
                $message = 'Password changed successfully!';
                $messageType = 'success';
            } else {
                $message = 'Incorrect current password.';
                $messageType = 'danger';
            }
        } catch (Exception $e) {
            $message = 'Error updating password: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

// Fetch Current User Details
$userData = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM signup WHERE user_id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Exception $e) {}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="main-content">
    <div class="content-container">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h1 class="h3 fw-bold mb-1" style="font-family: 'Quicksand', sans-serif;">User Profile</h1>
                <p class="text-secondary small mb-0">Manage your personal details and security settings</p>
            </div>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show rounded-3 shadow-sm mb-4" role="alert">
                <i class="fas <?php echo $messageType === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> me-2"></i>
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <!-- Profile Summary Card -->
            <div class="col-12 col-lg-4">
                <div class="card shadow-sm border-0 rounded-3 text-center p-4">
                    <div class="mb-3">
                        <img src="<?php echo htmlspecialchars($_SESSION['user_picture'] ?? 'https://ui-avatars.com/api/?name=' . urlencode($userData['fullname'] ?? 'User') . '&background=4c8a89&color=fff&size=128'); ?>" 
                             alt="Profile Picture" class="rounded-circle shadow-sm" style="width: 100px; height: 100px; object-fit: cover;">
                    </div>
                    <h5 class="fw-bold mb-1"><?php echo htmlspecialchars($userData['fullname'] ?? 'Resident'); ?></h5>
                    <p class="text-muted small mb-2">@<?php echo htmlspecialchars($userData['username'] ?? 'user'); ?></p>
                    
                    <div class="d-flex justify-content-center gap-2 mb-3">
                        <span class="badge bg-primary px-3 py-2"><?php echo htmlspecialchars(ucfirst($userData['role'] ?? 'User')); ?></span>
                        <?php if (!empty($userData['admin_approved'])): ?>
                            <span class="badge bg-success px-3 py-2"><i class="fas fa-check-circle me-1"></i>Verified</span>
                        <?php else: ?>
                            <span class="badge bg-warning text-dark px-3 py-2"><i class="fas fa-clock me-1"></i>Pending</span>
                        <?php endif; ?>
                    </div>

                    <ul class="list-group list-group-flush text-start small border-top pt-3">
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span class="text-muted"><i class="fas fa-envelope me-2"></i>Email</span>
                            <span class="fw-semibold text-break"><?php echo htmlspecialchars($userData['emailadd'] ?? '—'); ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span class="text-muted"><i class="fas fa-phone me-2"></i>Contact</span>
                            <span class="fw-semibold"><?php echo htmlspecialchars($userData['phone'] ?? '—'); ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span class="text-muted"><i class="fas fa-map-marker-alt me-2"></i>Barangay</span>
                            <span class="fw-semibold"><?php echo htmlspecialchars($userData['barangay'] ?? '—'); ?></span>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Profile Edit Forms -->
            <div class="col-12 col-lg-8">
                <!-- Personal Info Form -->
                <div class="card shadow-sm border-0 rounded-3 mb-4">
                    <div class="card-header bg-card fw-bold py-3">
                        <i class="fas fa-user-edit me-2 text-primary"></i>Personal Information
                    </div>
                    <div class="card-body p-4">
                        <form method="POST" action="user_profile.php">
                            <input type="hidden" name="action" value="update_profile">
                            <div class="row g-3">
                                <div class="col-12 col-md-6">
                                    <label class="form-label small fw-semibold">Full Name</label>
                                    <input type="text" class="form-control" name="fullname" value="<?php echo htmlspecialchars($userData['fullname'] ?? ''); ?>" required>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label small fw-semibold">Phone Number</label>
                                    <input type="text" class="form-control" name="phone" value="<?php echo htmlspecialchars($userData['phone'] ?? ''); ?>">
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label small fw-semibold">Barangay</label>
                                    <input type="text" class="form-control" name="barangay" value="<?php echo htmlspecialchars($userData['barangay'] ?? ''); ?>">
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label small fw-semibold">Address / Street</label>
                                    <input type="text" class="form-control" name="address" value="<?php echo htmlspecialchars($userData['address'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="mt-4 text-end">
                                <button type="submit" class="btn btn-primary btn-sm px-4 shadow-sm">
                                    <i class="fas fa-save me-1"></i> Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Password Change Form -->
                <div class="card shadow-sm border-0 rounded-3">
                    <div class="card-header bg-card fw-bold py-3">
                        <i class="fas fa-key me-2 text-warning"></i>Security & Password
                    </div>
                    <div class="card-body p-4">
                        <form method="POST" action="user_profile.php">
                            <input type="hidden" name="action" value="change_password">
                            <div class="row g-3">
                                <div class="col-12 col-md-4">
                                    <label class="form-label small fw-semibold">Current Password</label>
                                    <input type="password" class="form-control" name="current_password" required>
                                </div>
                                <div class="col-12 col-md-4">
                                    <label class="form-label small fw-semibold">New Password</label>
                                    <input type="password" class="form-control" name="new_password" required>
                                </div>
                                <div class="col-12 col-md-4">
                                    <label class="form-label small fw-semibold">Confirm Password</label>
                                    <input type="password" class="form-control" name="confirm_password" required>
                                </div>
                            </div>
                            <div class="mt-4 text-end">
                                <button type="submit" class="btn btn-warning btn-sm px-4 text-dark shadow-sm">
                                    <i class="fas fa-lock me-1"></i> Update Password
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
