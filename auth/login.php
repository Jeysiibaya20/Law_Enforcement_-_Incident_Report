<?php
/**
 *
 * 
 * @author 
 * @version 1.0.0
 */

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set page variables BEFORE including header
$page_title = 'Login';
$base_url = '../';

// Include required files
require_once '../config/db_connect.php';
if (!isset($pdo) || !($pdo instanceof PDO)) {
    $pdo = getDBConnection();
}

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    $current_role = strtolower(trim($_SESSION['role'] ?? 'user'));
    if ($current_role === 'admin') {
        header('Location: ../admin/reports.php');
    } else {
        header('Location: ../modules/my_reports.php');
    }
    exit();
}

// Initialize variables
$error_message = '';

// Handle form submission BEFORE outputting header
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            throw new Exception('Please enter both username and password');
        }

        // Try to get user from signup table first (by username or email)
        $sql = "SELECT user_id, fullname, emailadd, username, password, email_verified, role 
                FROM signup 
                WHERE username = ? OR emailadd = ? LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // If not found in signup, try the users table
        if (!$user) {
            // Fallback when account lives in `users` table: join with `signup` if available to get emailadd and role
            $sql_users = "SELECT u.user_id, COALESCE(s.fullname, u.username) AS fullname, s.emailadd, u.username, u.password_hash as password, u.email_verified, COALESCE(s.role, 'User') AS role 
                          FROM users u LEFT JOIN signup s ON s.user_id = u.user_id 
                          WHERE u.username = ? OR s.emailadd = ? LIMIT 1";
            $stmt_users = $pdo->prepare($sql_users);
            $stmt_users->execute([$username, $username]);
            $user = $stmt_users->fetch(PDO::FETCH_ASSOC);
        }

        if (!$user) {
            throw new Exception('Invalid username or password');
        }

        // Verify password using password_verify() for hashed passwords
        if (empty($user['password']) || !password_verify($password, $user['password'])) {
            throw new Exception('Invalid username or password');
        }

        // Note: Email verification check removed for local dev/admin setup
        // In production, uncomment the check below and ensure accounts are verified:
        // if (empty($user['email_verified'])) {
        //     throw new Exception('Please verify your email address before logging in.');
        // }

        // Login successful: create session and redirect to the app home
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['emailadd'] ?? $user['email'] ?? '';
        $_SESSION['role'] = strtolower(trim($user['role'] ?? 'user'));
        $_SESSION['first_name'] = trim(explode(' ', $user['fullname'] ?? $user['username'])[0]);

        if ($_SESSION['role'] === 'admin') {
            header('Location: ../admin/dashboard.php');
        } else {
            header('Location: ../modules/my_reports.php');
        }
        exit();

    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// NOW include header after all redirects are done
require_once '../includes/header.php';
?>

<!-- Login Page - -->
<div class="login-container">
    <div class="login-background">
        <div class="login-overlay"></div>
    </div>
    
    <div class="login-content">
        <div class="login-card">
            <!-- Logo Section -->
            <div class="login-header">
                <div class="login-logo">
                <img src="../assets/css/tara.png" alt="Alertara Logo" class="logo-image">
                </div>
                <h1 class="login-title">Alertara PH</h1>
                <p class="login-subtitle">Law Enforcement and Incident</p>

            </div>
            
            <!-- Login Form -->
            <div class="login-form-container">
                <?php if ($error_message): ?>
                    <div class="login-alert">
                        <i class="bi bi-exclamation-triangle"></i>
                        <span><?php echo htmlspecialchars($error_message); ?></span>
                    </div>
                <?php endif; ?>
                
                <form method="POST" id="loginForm" class="login-form">
                    <div class="form-group">
                        <label for="username" class="form-label">
                            <i class="bi bi-person"></i>
                            Username
                        </label>
                        <input type="text" class="form-control" id="username" name="username" 
                               value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" 
                               placeholder="Enter your username" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="password" class="form-label">
                            <i class="bi bi-lock"></i>
                            Password
                        </label>
                        <input type="password" class="form-control" id="password" name="password" 
                               placeholder="Enter your password" required>
                    </div>
                    
                    <button type="submit" class="login-btn">
                        <i class="bi bi-box-arrow-in-right"></i>
                        <span>Sign In</span>
                    </button>
                    
<div class="signup-container">
  <a href="signup_multistep.php" class="login-scd">
    <i class="bi bi-box-arrow-in-right"></i>
    <span>Sign up</span>
  </a>
</div>

  <p></p>
   <p></p>
  <p>Don't have an account?</p>

  <!-- Forgot Password Button -->
  <a href="forgot.php" class="forgot-password-btn">
    <i class="bi bi-key"></i>
    <span>Forgot Password?</span>
  </a>
</div>



                </form>
                
                <div class="login-footer">
                    <p class="help-text">
                        <i class="bi bi-info-circle"></i>
                        Need help?
                    </p>
                    <div class="login-links">
                        <a href="../index.php" class="back-link">
                            <i class="bi bi-arrow-left"></i>
                            Back to Home
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>

.login-container {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    background: var(--primary-bg);
    overflow: hidden;
    padding: 2rem;
    width: 100%;
    margin: 0;
    box-sizing: border-box;
}

.login-background {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(135deg, 
        rgba(76, 138, 137, 0.8) 0%, 
        rgba(58, 80, 107, 0.7) 50%, 
        rgba(28, 37, 65, 0.8) 100%);
    z-index: 1;
}

.login-background::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('../assets/css/QC.jpeg') center/90% no-repeat;
    opacity: 0.08;
    z-index: 1;
}

.login-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="%238B6F47" opacity="0.1"/><circle cx="75" cy="75" r="1" fill="%23D4A574" opacity="0.1"/><circle cx="50" cy="10" r="0.5" fill="%236B5B73" opacity="0.1"/><circle cx="10" cy="60" r="0.5" fill="%23D4A574" opacity="0.1"/><circle cx="90" cy="40" r="0.5" fill="%238B6F47" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
    z-index: 2;
}

.login-content {
    position: relative;
    z-index: 3;
    width: 100%;
    max-width: 450px;
    padding: 2rem;
    display: flex;
    justify-content: center;
    align-items: center;
}

.login-card {
    background: rgba(250, 250, 250, 0.95);
    border-radius: var(--border-radius-lg);
    box-shadow: var(--shadow-xl);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(0, 0, 0, 0.8);
    overflow: hidden;
    animation: slideInUp 0.8s ease-out;
    width: 100%;
    max-width: 450px;
}

.login-header {
    text-align: center;
    padding: 3rem 2rem 2rem;
    background: linear-gradient(135deg, 
        rgba(76, 138, 137, 0.9) 0%, 
        rgba(58, 80, 107, 0.8) 50%, 
        rgba(28, 37, 65, 0.9) 100%);
    color: var(--text-white);
    position: relative;
}

.login-header::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 20px;
    background: linear-gradient(to bottom, transparent, rgba(255, 255, 255, 0.1));
}

.login-logo {
    width: 80px;
    height: 80px;
    background: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1.5rem;
    font-size: 2.5rem;
    color: var(--text-white);
    animation: float;
    box-shadow: var(--shadow-lg);
    overflow: hidden;
}

.logo-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 50%;
}

.login-title {
    font-family: 'Libre Baskerville', serif;
    font-size: 2.5rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
    background: linear-gradient(135deg, #FFFFFF 0%, #FEFAF6 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.login-subtitle {
    font-size: 1.1rem;
    font-weight: 500;
    margin-bottom: 0.5rem;
    opacity: 0.9;
}

.login-description {
    font-size: 0.9rem;
    opacity: 0.8;
    margin: 0;
}

.login-form-container {
    padding: 2.5rem 2rem;
}

.login-alert {
    background: var(--danger-color);
    color: var(--text-white);
    padding: 1rem;
    border-radius: var(--border-radius-sm);
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.9rem;
    animation: fadeInDown 0.5s ease-out;
}

.login-form {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.login-form .form-group {
    position: relative;
}

.login-form .form-label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 500;
    color: var(--text-primary);
    margin-bottom: 0.5rem;
    font-size: 0.9rem;
}

.login-form .form-label i {
    color: var(--main-color);
    font-size: 1rem;
}

.login-form .form-control {
    width: 100%;
    padding: 1rem 1.25rem;
    border: 4px solid #e5e5e5;
    border-radius: var(--border-radius-sm);
    font-size: 1rem;
    background: rgba(255, 255, 255, 0.9);
    transition: var(--transition);
    font-family: 'Quicksand', sans-serif;
}

.login-form .form-control:focus {
    outline: none;
    border-color: var(--main-color);
    box-shadow: 0 0 0 3px rgba(139, 111, 71, 0.1);
    background: var(--text-white);
}

.login-form .form-control::placeholder {
    color: var(--text-light);
    font-style: italic;
}

.login-btn {
    background: #4c8a89;
    color: var(--text-white);
    border: none;
    padding: 1rem 2rem;
    border-radius: var(--border-radius-sm);
    font-size: 1rem;
    font-weight: 600;
    font-family: 'Quicksand', sans-serif;
    cursor: pointer;
    transition: var(--transition);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    margin-top: 1rem;
    box-shadow: var(--shadow-md);
}

.login-btn:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
    background: var(--gradient-accent);
}

.login-btn:active {
    transform: translateY(0);
}

.login-footer {
    text-align: center;
    margin-top: 2rem;
    padding-top: 1.5rem;
    border-top: 1px solid #e5e5e5;
}

.help-text {
    color: var(--text-light);
    font-size: 0.85rem;
    margin: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.help-text i {
    color: var(--main-color);
}

.login-links {
    margin-top: 1rem;
    text-align: center;
}

.back-link {
    color: var(--main-color);
    text-decoration: none;
    font-size: 0.9rem;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    transition: var(--transition);
}

.back-link:hover {
    color: var(--accent-color);
    text-decoration: underline;
}

.back-link i {
    font-size: 0.8rem;
}

.forgot-password-btn {
    color: #4c8a89;
    text-decoration: none;
    font-size: 0.9rem;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    transition: var(--transition);
}

.forgot-password-btn:hover {
    color: #3a506b;
    text-decoration: underline;
}

.forgot-password-btn i {
    font-size: 0.8rem;
}

/* Responsive Design */
@media (max-width: 768px) {
    .login-container {
        padding: 1rem;
        width: 100%;
    }
    
    .login-content {
        padding: 1rem;
        width: 100%;
        max-width: 100%;
    }
    
    .login-card {
        width: 100%;
        max-width: 100%;
    }
    
    .login-header {
        padding: 2rem 1.5rem 1.5rem;
    }
    
    .login-form-container {
        padding: 2rem 1.5rem;
    }
    
    .login-title {
        font-size: 2rem;
    }
    
    .login-logo {
        width: 60px;
        height: 60px;
        font-size: 2rem;
    }
}

/* Animation Keyframes */
@keyframes slideInUp {
    from {
        transform: translateY(30px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

@keyframes fadeInDown {
    from {
        transform: translateY(-10px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

@keyframes float {
    0%, 100% {
        transform: translateY(0px);
    }
    50% {
        transform: translateY(-10px);
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Form validation
    const form = document.getElementById('loginForm');
    form.addEventListener('submit', function(event) {
        if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
        }
        form.classList.add('was-validated');
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>

