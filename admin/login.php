<?php
/**
 * Dedicated Administration Portal Login
 * Strictly restricted to Admin, Officer, and Official credentials.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$page_title = 'Admin Portal Sign In';
$base_url = '../';

require_once __DIR__ . '/../config/db_connect.php';
if (!isset($pdo) || !($pdo instanceof PDO)) {
    $pdo = getDBConnection();
}

// Redirect if already logged in as Admin
if (isset($_SESSION['user_id'])) {
    $current_role = strtolower(trim($_SESSION['role'] ?? 'user'));
    if (strpos($current_role, 'admin') !== false || strpos($current_role, 'officer') !== false || strpos($current_role, 'official') !== false) {
        header('Location: dashboard.php');
        exit();
    } else {
        header('Location: ../modules/my_reports.php');
        exit();
    }
}

$error_message = '';
if (isset($_SESSION['flash']['message'])) {
    $error_message = $_SESSION['flash']['message'];
    unset($_SESSION['flash']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            throw new Exception('Please enter your administrator username/email and password.');
        }

        // Search in signup table first
        $stmt = $pdo->prepare("SELECT user_id, fullname, emailadd, username, password, role FROM signup WHERE username = ? OR emailadd = ? LIMIT 1");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Fallback to users table
        if (!$user) {
            $stmt_u = $pdo->prepare("SELECT u.user_id, COALESCE(s.fullname, u.username) AS fullname, s.emailadd, u.username, u.password_hash AS password, COALESCE(s.role, 'User') AS role FROM users u LEFT JOIN signup s ON s.user_id = u.user_id WHERE u.username = ? OR s.emailadd = ? LIMIT 1");
            $stmt_u->execute([$username, $username]);
            $user = $stmt_u->fetch(PDO::FETCH_ASSOC);
        }

        if (!$user) {
            throw new Exception('Invalid administrative credentials.');
        }

        // Verify password
        $isValidPass = false;
        if (!empty($user['password'])) {
            if (password_verify($password, $user['password']) || $user['password'] === $password) {
                $isValidPass = true;
                if ($user['password'] === $password) {
                    $new_hash = password_hash($password, PASSWORD_DEFAULT);
                    $rehash_stmt = $pdo->prepare("UPDATE signup SET password = ? WHERE user_id = ?");
                    $rehash_stmt->execute([$new_hash, $user['user_id']]);
                }
            }
        }

        if (!$isValidPass) {
            throw new Exception('Invalid administrative credentials.');
        }

        $userRole = strtolower(trim($user['role'] ?? 'user'));
        $isAdminRole = (strpos($userRole, 'admin') !== false || strpos($userRole, 'officer') !== false || strpos($userRole, 'official') !== false);

        if (!$isAdminRole) {
            throw new Exception('Access Denied: Resident accounts cannot sign in through the Admin Portal.');
        }

        // Session setup for Admin
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['emailadd'] ?? '';
        $_SESSION['role'] = $userRole;
        $_SESSION['first_name'] = trim(explode(' ', $user['fullname'] ?? $user['username'])[0]);
        $_SESSION['fullname'] = $user['fullname'] ?? $user['username'];

        header('Location: dashboard.php');
        exit();

    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex align-items-center justify-content-center min-vh-100 bg-dark bg-gradient p-3">
    <div class="card border-0 shadow-lg rounded-4 overflow-hidden" style="max-width: 440px; width: 100%; background: #1e293b; color: #f8fafc;">
        <!-- Card Header Banner -->
        <div class="p-4 text-center border-bottom border-secondary" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);">
            <div class="mb-3">
                <img src="../assets/images/logo.svg" alt="Alertara Emblem" style="height: 60px;">
            </div>
            <h4 class="fw-bold text-white mb-1" style="font-family: 'Quicksand', sans-serif;">ADMIN PORTAL</h4>
            <span class="badge bg-danger text-uppercase px-3 py-1" style="font-size: 0.7rem; letter-spacing: 1px;">Authorized Personnel Only</span>
        </div>

        <!-- Form Body -->
        <div class="card-body p-4 p-md-5">
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger rounded-3 p-3 small mb-4">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span><?php echo htmlspecialchars($error_message); ?></span>
                    </div>
                    <?php if (strpos($error_message, 'Resident accounts') !== false): ?>
                        <div class="mt-2 text-center">
                            <a href="../auth/login.php" class="btn btn-sm btn-light fw-bold text-dark text-decoration-none shadow-sm" style="font-size: 0.8rem;">
                                <i class="fas fa-user me-1"></i> Go to Resident Sign In
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php" autocomplete="off">
                <div class="mb-3">
                    <label class="form-label small text-uppercase text-secondary fw-bold">Username or Email</label>
                    <div class="input-group">
                        <span class="input-group-text bg-secondary text-light border-secondary"><i class="fas fa-user-shield"></i></span>
                        <input type="text" name="username" class="form-control bg-dark text-white border-secondary" placeholder="Enter admin username" required autofocus>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label small text-uppercase text-secondary fw-bold">Password</label>
                    <div class="input-group">
                        <span class="input-group-text bg-secondary text-light border-secondary"><i class="fas fa-lock"></i></span>
                        <input type="password" name="password" class="form-control bg-dark text-white border-secondary" placeholder="Enter password" required>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100 py-2.5 fw-bold shadow-sm rounded-3">
                    <i class="fas fa-sign-in-alt me-2"></i> Sign In to Admin Portal
                </button>
            </form>
        </div>

        <!-- Footer -->
        <div class="card-footer bg-dark border-top border-secondary text-center py-3">
            <small class="text-secondary">Resident / Citizen user? <a href="../auth/login.php" class="text-primary fw-semibold text-decoration-none">Go to Resident Sign In</a></small>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
