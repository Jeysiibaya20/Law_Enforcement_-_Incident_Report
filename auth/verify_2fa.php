<?php
/**
 * Verify 2FA Code (Email) - Hotel HR Management System - HR 1&2
 * 
 * @author HR System
 * @version 1.0.0
 */

$page_title = 'Two-Factor Verification';
$base_url = '../';

require_once '../config/db_connect.php';
require_once '../includes/header.php';
require_once '../includes/two_factor_auth.php';

if (!isset($_SESSION['pending_2fa_user'])) {
    header('Location: login.php');
    exit();
}

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['code'] ?? '');
    try {
        if ($code === '' || !preg_match('/^\d{6}$/', $code)) {
            throw new Exception('Please enter the 6-digit code sent to your email');
        }
        $userId = $_SESSION['pending_2fa_user'];
        $tfa = new TwoFactorAuth($pdo);
        if (!$tfa->verify2FACode($userId, $code, 'EMAIL')) {
            throw new Exception('Invalid or expired code. Please try again.');
        }
        // Finalize login
        $_SESSION['user_id'] = $userId;
        $_SESSION['employee_id'] = $_SESSION['pending_2fa_employee_id'] ?? null;
        $_SESSION['username'] = $_SESSION['pending_2fa_username'] ?? '';
        $_SESSION['role'] = $_SESSION['pending_2fa_role'] ?? 'Employee';
        $_SESSION['email'] = $_SESSION['pending_2fa_email'] ?? '';
        // Cleanup
        unset($_SESSION['pending_2fa_user'], $_SESSION['pending_2fa_role'], $_SESSION['pending_2fa_employee_id'], $_SESSION['pending_2fa_username'], $_SESSION['pending_2fa_email']);
        
        // Redirect based on role
        $redirect_url = '../index.php';
        if ($_SESSION['role'] === 'Employee') {
            $redirect_url = '../index.php'; // HR-1&2 uses index.php for all users
        }
        header('Location: ' . $redirect_url);
        exit();
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}
?>

<div class="main-content">
    <div class="content-container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="card enhanced-card">
                    <div class="card-header text-center">
                        <div class="verification-icon">
                            <i class="bi bi-shield-check"></i>
                        </div>
                        <h4 class="card-title">Two-Factor Verification</h4>
                        <p class="text-muted">Enter the 6-digit code sent to your email</p>
                    </div>
                    
                    <div class="card-body">
                        <?php if ($error_message): ?>
                            <div class="alert alert-danger">
                                <i class="bi bi-exclamation-triangle"></i>
                                <?php echo htmlspecialchars($error_message); ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" id="verifyForm">
                            <div class="form-group mb-4">
                                <label for="code" class="form-label">
                                    <i class="bi bi-key"></i>
                                    Verification Code
                                </label>
                                <input type="text" 
                                       class="form-control text-center" 
                                       id="code" 
                                       name="code" 
                                       placeholder="000000" 
                                       maxlength="6" 
                                       pattern="[0-9]{6}"
                                       required>
                                <div class="form-text">
                                    <i class="bi bi-info-circle"></i>
                                    Check your email for the verification code
                                </div>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="bi bi-check-circle"></i>
                                    Verify & Continue
                                </button>
                            </div>
                        </form>
                        
                        <div class="text-center mt-4">
                            <p class="text-muted small">
                                <i class="bi bi-clock"></i>
                                Code expires in 5 minutes
                            </p>
                            <a href="login.php" class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-arrow-left"></i>
                                Back to Login
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.verification-icon {
    width: 80px;
    height: 80px;
    background: var(--gradient-primary);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1.5rem;
    font-size: 2rem;
    color: var(--text-white);
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% {
        transform: scale(1);
    }
    50% {
        transform: scale(1.05);
    }
    100% {
        transform: scale(1);
    }
}

.form-control.text-center {
    font-size: 1.5rem;
    letter-spacing: 0.5rem;
    font-weight: 600;
    padding: 1rem;
}

.form-control.text-center:focus {
    border-color: var(--main-color);
    box-shadow: 0 0 0 0.2rem rgba(139, 111, 71, 0.25);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const codeInput = document.getElementById('code');
    
    // Auto-format code input
    codeInput.addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, '');
        if (value.length > 6) {
            value = value.substring(0, 6);
        }
        e.target.value = value;
        
        // Auto-submit when 6 digits are entered
        if (value.length === 6) {
            setTimeout(() => {
                document.getElementById('verifyForm').submit();
            }, 500);
        }
    });
    
    // Focus on input
    codeInput.focus();
    
    // Form validation
    const form = document.getElementById('verifyForm');
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

