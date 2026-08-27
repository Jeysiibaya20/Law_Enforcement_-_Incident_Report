<?php
session_start();
require_once __DIR__ . "/../config/db_connect.php";
if (!isset($pdo) || !($pdo instanceof PDO)) {
    $pdo = getDBConnection();
}

$token = trim($_GET["token"] ?? '');
$is_valid = false;
$user_id = null;
$error_msg = "";
$success_msg = "";

if (empty($token)) {
    $error_msg = "No password reset token provided. Please request a new link.";
} else {
    try {
        // Ensure reset_tokens table exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS reset_tokens (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token VARCHAR(64) NOT NULL,
            expires DATETIME NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        $stmt = $pdo->prepare("SELECT user_id, expires FROM reset_tokens WHERE token = ? LIMIT 1");
        $stmt->execute([$token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            $error_msg = "This password reset link is invalid or has already been used.";
        } elseif (strtotime($row['expires']) < time()) {
            $error_msg = "This password reset link has expired. Please request a new one.";
        } else {
            $is_valid = true;
            $user_id = $row['user_id'];
        }
    } catch (Exception $e) {
        $error_msg = "Database error: " . $e->getMessage();
    }
}

if ($is_valid && $_SERVER["REQUEST_METHOD"] === "POST") {
    $password = $_POST["password"] ?? '';
    $confirmpassword = $_POST["confirmpassword"] ?? '';

    if (strlen($password) < 8 || strpos($password, ' ') !== false) {
        $error_msg = "Password must be at least 8 characters with no spaces.";
    } elseif ($password !== $confirmpassword) {
        $error_msg = "Passwords do not match.";
    } else {
        try {
            $newPassHash = password_hash($password, PASSWORD_DEFAULT);

            // Update signup table
            $upd1 = $pdo->prepare("UPDATE signup SET password = ? WHERE user_id = ?");
            $upd1->execute([$newPassHash, $user_id]);

            // Also update users table if account exists there
            try {
                $upd2 = $pdo->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
                $upd2->execute([$newPassHash, $user_id]);
            } catch (Exception $ex) {
                // Ignore if users table not present or column mismatch
            }

            // Remove used token
            $del = $pdo->prepare("DELETE FROM reset_tokens WHERE user_id = ?");
            $del->execute([$user_id]);

            $is_valid = false; // Form completed
            $success_msg = "Your password has been successfully updated. You may now log in.";
        } catch (Exception $e) {
            $error_msg = "Failed to update password: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Alertara PH</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: 'Quicksand', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.login-container {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    background: #0f172a;
    padding: 2rem 1rem;
}

.login-background {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(135deg, 
        rgba(76, 138, 137, 0.85) 0%, 
        rgba(58, 80, 107, 0.8) 50%, 
        rgba(28, 37, 65, 0.9) 100%);
    z-index: 1;
}

.login-background::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('../assets/css/QC.jpeg') center/cover no-repeat;
    opacity: 0.08;
    z-index: 1;
}

.login-content {
    position: relative;
    z-index: 3;
    width: 100%;
    max-width: 440px;
}

.login-card {
    background: rgba(255, 255, 255, 0.97);
    border-radius: 16px;
    box-shadow: 0 20px 45px rgba(0, 0, 0, 0.25);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.4);
    overflow: hidden;
}

.login-header {
    text-align: center;
    padding: 2.5rem 2rem 1.8rem;
    background: linear-gradient(135deg, 
        rgba(76, 138, 137, 0.95) 0%, 
        rgba(58, 80, 107, 0.9) 50%, 
        rgba(28, 37, 65, 0.95) 100%);
    color: #ffffff;
}

.login-logo {
    width: 76px;
    height: 76px;
    background: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1.25rem;
    box-shadow: 0 8px 20px rgba(0,0,0,0.18);
    overflow: hidden;
    padding: 4px;
}

.logo-image {
    width: 100%;
    height: 100%;
    object-fit: contain;
    border-radius: 50%;
}

.login-title {
    font-family: 'Libre Baskerville', serif, 'Georgia';
    font-size: 1.8rem;
    font-weight: 700;
    margin: 0 0 0.35rem;
    color: #ffffff;
}

.login-subtitle {
    font-size: 0.95rem;
    margin: 0;
    opacity: 0.9;
    color: #e2e8f0;
}

.login-form-container {
    padding: 2rem;
}

.form-group {
    margin-bottom: 1.25rem;
}

.form-label {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-weight: 600;
    color: #2d3748;
    margin-bottom: 0.5rem;
    font-size: 0.92rem;
}

.form-control {
    width: 100%;
    padding: 0.85rem 1.15rem;
    border: 1.5px solid #cbd5e1;
    border-radius: 8px;
    font-size: 0.95rem;
    background: #ffffff;
    transition: all 0.2s ease;
    box-sizing: border-box;
    color: #1e293b;
}

.form-control:focus {
    outline: none;
    border-color: #2e856e;
    box-shadow: 0 0 0 3px rgba(46, 133, 110, 0.18);
}

.login-btn {
    background: #2e856e;
    color: #ffffff;
    border: none;
    padding: 0.85rem 1.5rem;
    border-radius: 8px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    width: 100%;
    box-shadow: 0 4px 12px rgba(46, 133, 110, 0.25);
    text-decoration: none;
}

.login-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(46, 133, 110, 0.35);
    background: #246d5a;
}

.alert {
    padding: 0.85rem 1rem;
    border-radius: 8px;
    margin-bottom: 1.25rem;
    font-size: 0.92rem;
    display: flex;
    align-items: flex-start;
    gap: 0.6rem;
    line-height: 1.4;
}

.alert-danger {
    background: #fef2f2;
    color: #991b1b;
    border: 1px solid #fecaca;
}

.alert-success {
    background: #f0fdf4;
    color: #166534;
    border: 1px solid #bbf7d0;
}

.back-link {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    color: #2e856e;
    text-decoration: none;
    font-weight: 600;
    font-size: 0.92rem;
    margin-top: 1.25rem;
    transition: color 0.2s;
}

.back-link:hover {
    color: #246d5a;
    text-decoration: underline;
}
    </style>
</head>
<body>
<div class="login-container">
    <div class="login-background"></div>
    <div class="login-content">
        <div class="login-card">
            <div class="login-header">
                <div class="login-logo">
                    <img src="../assets/css/tara.png" alt="Alertara Logo" class="logo-image">
                </div>
                <h1 class="login-title">Create New Password</h1>
                <p class="login-subtitle">Choose a secure password for your account</p>
            </div>

            <div class="login-form-container">
                <?php if ($error_msg): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-circle-fill" style="font-size: 1.1rem; flex-shrink: 0;"></i>
                        <div><?php echo htmlspecialchars($error_msg); ?></div>
                    </div>
                <?php endif; ?>

                <?php if ($success_msg): ?>
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle-fill" style="font-size: 1.1rem; flex-shrink: 0;"></i>
                        <div><?php echo $success_msg; ?></div>
                    </div>
                    <div style="text-align: center; margin-top: 1.5rem;">
                        <a href="login.php" class="login-btn">
                            <i class="bi bi-box-arrow-in-right"></i> Go to Sign In
                        </a>
                    </div>
                <?php endif; ?>

                <?php if ($is_valid): ?>
                    <form method="POST" id="resetForm">
                        <div class="form-group">
                            <label for="password" class="form-label">
                                <i class="bi bi-lock"></i> New Password *
                            </label>
                            <input type="password" class="form-control" id="password" name="password" 
                                   placeholder="Min 8 characters, no spaces" required>
                        </div>

                        <div class="form-group">
                            <label for="confirmpassword" class="form-label">
                                <i class="bi bi-lock-fill"></i> Confirm New Password *
                            </label>
                            <input type="password" class="form-control" id="confirmpassword" name="confirmpassword" 
                                   placeholder="Re-type new password" required>
                            <small id="passwordMatch" style="font-size: 0.85rem; color: #64748b; margin-top: 4px; display: block;"></small>
                        </div>

                        <button type="submit" class="login-btn">
                            <i class="bi bi-check2-circle"></i> Save New Password
                        </button>
                    </form>
                <?php elseif (!$success_msg): ?>
                    <div style="text-align: center; margin-top: 1rem;">
                        <a href="forgot.php" class="login-btn" style="background: #64748b;">
                            <i class="bi bi-arrow-clockwise"></i> Request New Reset Link
                        </a>
                    </div>
                <?php endif; ?>

                <div style="text-align: center;">
                    <a href="login.php" class="back-link">
                        <i class="bi bi-arrow-left"></i> Back to Login
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const passInput = document.getElementById('password');
const confirmInput = document.getElementById('confirmpassword');
const matchEl = document.getElementById('passwordMatch');

if (passInput && confirmInput && matchEl) {
    function checkMatch() {
        if (!confirmInput.value) {
            matchEl.textContent = '';
            return;
        }
        if (passInput.value === confirmInput.value) {
            matchEl.textContent = '✓ Passwords match';
            matchEl.style.color = '#16a34a';
        } else {
            matchEl.textContent = '✗ Passwords do not match';
            matchEl.style.color = '#dc2626';
        }
    }
    passInput.addEventListener('input', checkMatch);
    confirmInput.addEventListener('input', checkMatch);
}
</script>
</body>
</html>