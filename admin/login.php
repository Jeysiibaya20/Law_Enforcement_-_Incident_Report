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
// Redirect if already logged in as Admin
if (!empty($_SESSION['admin_user_id'])) {
    header('Location: dashboard.php');
    exit();
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

        // Enforce mandatory TOTP OTP authentication for ALL Admin/Officer logins
        $_SESSION['pending_2fa_user'] = $user['user_id'];
        $_SESSION['pending_2fa_username'] = $user['username'];
        $_SESSION['pending_2fa_email'] = $user['emailadd'] ?? '';
        $_SESSION['pending_2fa_method'] = 'TOTP';
        $_SESSION['pending_2fa_role'] = $userRole;

        require_once __DIR__ . '/../includes/two_factor_auth.php';
        $tfa = new TwoFactorAuth($pdo);

        if ($tfa->is2FAEnabled($user['user_id'])) {
            // Admin already configured TOTP - verify rotating code
            header('Location: ../auth/verify_2fa.php');
        } else {
            // First time admin login - require scanning QR code and verifying OTP before access
            $_SESSION['one_time_setup_user'] = $user['user_id'];
            header('Location: ../auth/setup_totp.php');
        }
        exit();

    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.admin-login-wrapper {
    min-height: 100vh;
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1.5rem;
}

.admin-login-card {
    width: 100%;
    max-width: 440px;
    background: #ffffff;
    border-radius: 16px;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
    overflow: hidden;
}

.admin-login-header {
    background: linear-gradient(135deg, #1b5a56 0%, #113d3a 100%);
    padding: 2.25rem 2rem 1.75rem;
    text-align: center;
    color: #ffffff;
}

.admin-login-header img {
    height: 58px;
    margin-bottom: 0.75rem;
    filter: drop-shadow(0 4px 6px rgba(0,0,0,0.2));
}

.admin-login-header h4 {
    font-weight: 700;
    letter-spacing: 0.5px;
    margin-bottom: 0.25rem;
    font-size: 1.35rem;
}

.admin-badge {
    background: rgba(239, 68, 68, 0.2);
    border: 1px solid rgba(239, 68, 68, 0.4);
    color: #fca5a5;
    font-size: 0.7rem;
    font-weight: 700;
    letter-spacing: 1px;
    padding: 0.25rem 0.85rem;
    border-radius: 50px;
    display: inline-block;
}

.admin-login-body {
    padding: 2rem;
    background: #ffffff;
}

.admin-input-group {
    position: relative;
    margin-bottom: 1.25rem;
}

.admin-input-group label {
    display: block;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #475569;
    margin-bottom: 0.4rem;
}

.admin-field-wrap {
    position: relative;

}

.admin-field-icon {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: #64748b;
    font-size: 1rem;
    z-index: 10;
}

.admin-field-input {
    width: 100%;
    padding: 0.75rem 1rem 0.75rem 2.6rem;
    background: #f8fafc;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    font-size: 0.95rem;
    color: #0f172a !important;
    transition: all 0.2s ease;
}

.admin-field-input:focus {
    outline: none;
    background: #ffffff;
    border-color: #1b5a56;
    box-shadow: 0 0 0 3px rgba(27, 90, 86, 0.15);
}

.admin-submit-btn {
    width: 100%;
    padding: 0.85rem;
    background: linear-gradient(135deg, #1b5a56 0%, #113d3a 100%);
    color: #ffffff !important;
    border: none;
    border-radius: 8px;
    font-weight: 700;
    font-size: 0.95rem;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(27, 90, 86, 0.3);
    transition: all 0.2s ease;
    margin-top: 0.5rem;
}

.admin-submit-btn:hover {
    background: linear-gradient(135deg, #23746f 0%, #185551 100%);
    box-shadow: 0 6px 16px rgba(27, 90, 86, 0.4);
    transform: translateY(-1px);
}

.admin-login-footer {
    background: #f1f5f9;
    padding: 1rem 1.5rem;
    text-align: center;
    border-top: 1px solid #e2e8f0;
    font-size: 0.85rem;
    color: #64748b;
}

.admin-login-footer a {
    color: #1b5a56;
    font-weight: 700;
    text-decoration: none;
}

.admin-login-footer a:hover {
    text-decoration: underline;
}

.admin-alert {
    background: #fef2f2;
    border: 1px solid #fecaca;
    color: #991b1b;
    padding: 0.85rem 1rem;
    border-radius: 8px;
    font-size: 0.85rem;
    margin-bottom: 1.25rem;
}
</style>

<div class="admin-login-wrapper">
    <div class="admin-login-card">
        <!-- Header -->
        <div class="admin-login-header">
            <img src="../assets/images/logo.svg" alt="Alertara PH Logo">
            <h4 style="font-family: 'Quicksand', sans-serif;">ADMIN PORTAL</h4>
            <span class="admin-badge"><i class="fas fa-lock me-1"></i> AUTHORIZED PERSONNEL ONLY</span>
        </div>

        <!-- Form Body -->
        <div class="admin-login-body">
            <?php if (!empty($error_message)): ?>
                <div class="admin-alert">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fas fa-exclamation-circle text-danger"></i>
                        <span><?php echo htmlspecialchars($error_message); ?></span>
                    </div>
                    <?php if (strpos($error_message, 'Resident accounts') !== false): ?>
                        <div class="mt-2 text-center">
                            <a href="../auth/login.php" class="btn btn-sm btn-outline-danger fw-bold text-decoration-none mt-1">
                                <i class="fas fa-user me-1"></i> Go to Resident Sign In
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php" autocomplete="off">
                <div class="admin-input-group">
                    <label>Username or Email</label>
                    <div class="admin-field-wrap">
                        <i class="fas fa-user-shield admin-field-icon"></i>
                        <input type="text" name="username" class="admin-field-input" placeholder="Enter admin username" required autofocus>
                    </div>
                </div>

                <div class="admin-input-group">
                    <label>Password</label>
                    <div class="admin-field-wrap">
                        <i class="fas fa-key admin-field-icon"></i>
                        <input type="password" name="password" class="admin-field-input" placeholder="Enter password" required>
                    </div>
                </div>

                <button type="submit" class="admin-submit-btn">
                    <i class="fas fa-sign-in-alt me-2"></i> Sign In to Admin Portal
                </button>
            </form>
        </div>

        <!-- Footer -->
        <div class="admin-login-footer">
            Resident / Citizen user? <a href="../auth/login.php">Go to Resident Sign In</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
