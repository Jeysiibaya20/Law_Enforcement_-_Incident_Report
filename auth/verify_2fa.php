<?php
// Two-factor verification page (EMAIL or SMS OTP)
if (session_status() === PHP_SESSION_NONE) session_start();

// Required includes
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/two_factor_auth.php';

$tfa = new TwoFactorAuth($pdo);

// Ensure we have a pending 2FA request
if (empty($_SESSION['pending_2fa_user'])) {
    header('Location: login.php');
    exit();
}

$pendingUser = $_SESSION['pending_2fa_user'];
$method = $_SESSION['pending_2fa_method'] ?? 'EMAIL';
$contact = ($method === 'EMAIL') ? ($_SESSION['pending_2fa_email'] ?? '') : ($_SESSION['pending_2fa_phone'] ?? '');
$message = '';

// Helper: check if we can offer one-time setup access (admins without an existing TOTP secret)
$pendingRole = $_SESSION['pending_2fa_role'] ?? null;
$canOneTimeSetup = false;
if ($method === 'TOTP' && in_array(strtolower($pendingRole ?? ''), ['admin', 'administrator'], true)) {
    $existingSecret = $tfa->getUserSecret($pendingUser);
    if (empty($existingSecret)) {
        $canOneTimeSetup = true;
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // One-time access request for admins without TOTP configured
    if (isset($_POST['one_time_setup']) && $canOneTimeSetup) {
        // Allow access to setup_totp.php without full login only for this pending user
        $_SESSION['one_time_setup_user'] = $pendingUser;
        // Keep the pending vars so user may be redirected back after setup if needed
        header('Location: setup_totp.php');
        exit();
    }
    // Resend OTP (only for EMAIL or SMS methods)
    if (isset($_POST['resend'])) {
        if ($method === 'EMAIL') {
            $code = $tfa->generateEmailCode();
            $tfa->storeEmailCode($pendingUser, $code);
            $sent = $tfa->sendEmailCode($contact, $code);
            $message = $sent ? 'Verification code resent to your email.' : 'Failed to deliver email; session fallback available.';
        } elseif ($method === 'SMS') {
            $code = $tfa->generateSMSCode();
            $tfa->storeSMSCode($pendingUser, $code);
            $sent = $tfa->sendSMSCode($contact, $code);
            $message = $sent ? 'Verification code resent to your phone.' : 'Failed to deliver SMS; check server logs.';
        } else {
            $message = 'TOTP codes cannot be resent. Please use your authenticator app.';
        }
    }

    // Verify OTP / TOTP
    if (isset($_POST['verify'])) {
        $code = trim($_POST['code'] ?? '');
        if (empty($code)) {
            $message = 'Please enter the verification code.';
        } else {
            $ok = $tfa->verify2FACode($pendingUser, $code, $method);
            if ($ok) {
                // Mark user as logged in
                $_SESSION['user_id'] = $pendingUser;
                // Optionally copy username/email into session for convenience
                if (!empty($_SESSION['pending_2fa_username'])) $_SESSION['username'] = $_SESSION['pending_2fa_username'];
                if (!empty($_SESSION['pending_2fa_email'])) $_SESSION['email'] = $_SESSION['pending_2fa_email'];
                // Clean up pending vars
                unset($_SESSION['pending_2fa_user'], $_SESSION['pending_2fa_method'], $_SESSION['pending_2fa_email'], $_SESSION['pending_2fa_phone'], $_SESSION['pending_2fa_username']);
                // Redirect to dashboard or home
                header('Location: ../index.php');
                exit();
            } else {
                if ($method === 'TOTP') {
                    $message = 'Invalid or expired TOTP code. Ensure your device time is correct.';
                } else {
                    $message = 'Invalid or expired code. Please try again or resend the code.';
                }
            }
        }
    }
}

// Simple HTML output (no heavy templating here)
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Two-Factor Verification</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
:root {
    --primary-bg: #0f1419;
    --text-white: #ffffff;
    --text-primary: #1a1a1a;
    --text-light: #6c757d;
    --main-color: #8b6f47;
    --accent-color: #d4a574;
    --danger-color: #c0392b;
    --success-color: #16a34a;
    --border-radius-sm: 6px;
    --border-radius-lg: 12px;
    --shadow-md: 0 4px 12px rgba(0,0,0,0.15);
    --shadow-lg: 0 8px 24px rgba(0,0,0,0.2);
    --shadow-xl: 0 12px 40px rgba(0,0,0,0.25);
    --transition: all 0.3s ease;
    --gradient-accent: linear-gradient(135deg, #d4a574 0%, #c9956f 100%);
}

* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: 'Quicksand', 'Segoe UI', sans-serif; background: var(--primary-bg); color: var(--text-primary); }

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
    background: linear-gradient(135deg, rgba(76, 138, 137, 0.8) 0%, rgba(58, 80, 107, 0.7) 50%, rgba(28, 37, 65, 0.8) 100%);
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
    background: linear-gradient(135deg, rgba(76, 138, 137, 0.9) 0%, rgba(58, 80, 107, 0.8) 50%, rgba(28, 37, 65, 0.9) 100%);
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
    animation: float 6s ease-in-out infinite;
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
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
    background: linear-gradient(135deg, #FFFFFF 0%, #FEFAF6 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.login-subtitle {
    font-size: 1rem;
    font-weight: 500;
    margin-bottom: 0.5rem;
    opacity: 0.9;
}

.login-form-container {
    padding: 2.5rem 2rem;
}

.login-alert {
    background: #d1ecf1;
    color: #0c5460;
    border: 1px solid #bee5eb;
    padding: 1rem;
    border-radius: var(--border-radius-sm);
    margin-bottom: 1.5rem;
    animation: fadeInDown 0.5s ease-out;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-label {
    display: block;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 0.5rem;
    font-size: 0.95rem;
}

.form-control {
    width: 100%;
    padding: 1rem 1.25rem;
    border: 2px solid #e5e5e5;
    border-radius: var(--border-radius-sm);
    font-size: 1rem;
    background: rgba(255, 255, 255, 0.9);
    transition: var(--transition);
    font-family: 'Quicksand', sans-serif;
    text-align: center;
    letter-spacing: 8px;
}

.form-control:focus {
    outline: none;
    border-color: var(--main-color);
    box-shadow: 0 0 0 3px rgba(139, 111, 71, 0.1);
    background: var(--text-white);
}

.login-btn {
    background: #4c8a89;
    color: var(--text-white);
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: var(--border-radius-sm);
    font-size: 1rem;
    font-weight: 600;
    font-family: 'Quicksand', sans-serif;
    cursor: pointer;
    transition: var(--transition);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    margin-top: 0.5rem;
    box-shadow: var(--shadow-md);
}

.login-btn:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
    background: #3a506b;
}

.login-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.button-group {
    display: flex;
    gap: 0.5rem;
    margin-top: 1rem;
}

.button-group button {
    flex: 1;
}

.back-link {
    color: var(--main-color);
    text-decoration: none;
    font-size: 0.9rem;
    display: inline-block;
    margin-top: 1rem;
    transition: var(--transition);
}

.back-link:hover {
    color: var(--accent-color);
    text-decoration: underline;
}

@keyframes slideInUp {
    from { transform: translateY(30px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

@keyframes fadeInDown {
    from { transform: translateY(-10px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

@keyframes float {
    0%, 100% { transform: translateY(0px); }
    50% { transform: translateY(-10px); }
}

@media (max-width: 768px) {
    .login-container { padding: 1rem; }
    .login-content { padding: 1rem; max-width: 100%; }
    .login-card { max-width: 100%; }
    .login-header { padding: 2rem 1.5rem 1.5rem; }
    .login-form-container { padding: 2rem 1.5rem; }
    .login-title { font-size: 1.5rem; }
    .login-logo { width: 60px; height: 60px; }
}
    </style>
</head>
<body>
<div class="login-container">
    <div class="login-background">
        <div class="login-overlay"></div>
    </div>
    
    <div class="login-content">
        <div class="login-card">
            <div class="login-header">
                <div class="login-logo">
                    <img src="../assets/css/tara.png" alt="Alertara Logo" class="logo-image">
                </div>
                <h1 class="login-title">Two-Factor Verification</h1>
                <p class="login-subtitle">Verify Your Identity</p>
            </div>

            <div class="login-form-container">
                <?php if ($message): ?>
                    <div class="login-alert"><?php echo htmlspecialchars($message); ?></div>
                <?php endif; ?>

                <?php if ($method === 'TOTP'): ?>
                    <p style="color:var(--text-primary);margin-bottom:1.5rem;line-height:1.6;">
                        Two-factor authentication via Authenticator App (TOTP). Open your authenticator app (Google Authenticator, Authy, Microsoft Authenticator, etc.) and enter the 6-digit code shown for your account.
                    </p>
                <?php else: ?>
                    <p style="color:var(--text-primary);margin-bottom:1.5rem;line-height:1.6;">
                        A verification code was sent via <strong><?php echo htmlspecialchars($method); ?></strong> to <strong><?php echo htmlspecialchars(substr($contact, 0, 3) . '***' . substr($contact, -4)); ?></strong>.
                    </p>
                <?php endif; ?>

                <?php if ($canOneTimeSetup): ?>
                    <div class="login-alert" style="background:#fff3cd;border-color:#ffeeba;color:#856404;">
                        Admin account does not have Authenticator App configured. You may use a one-time access to set it up now.
                        <form method="post" style="display:inline-block;margin-left:1rem;">
                            <button type="submit" name="one_time_setup" class="login-btn" style="background:#17a2b8;">One-time Setup Access</button>
                        </form>
                    </div>
                <?php endif; ?>

                <form method="post">
                    <div class="form-group">
                        <label for="code" class="form-label">Enter 6-digit code</label>
                        <input type="text" class="form-control" id="code" name="code" pattern="\d*" inputmode="numeric" maxlength="6" required autofocus>
                    </div>

                    <div class="button-group">
                        <button type="submit" name="verify" class="login-btn">Verify</button>
                        <?php if ($method !== 'TOTP'): ?>
                            <button type="submit" name="resend" class="login-btn" style="background:#6c757d;">Resend Code</button>
                        <?php else: ?>
                            <button type="button" disabled class="login-btn" style="background:#6c757d;">Resend (not available)</button>
                        <?php endif; ?>
                    </div>
                </form>

                <a href="login.php" class="back-link">← Back to login</a>
            </div>
        </div>
    </div>
</div>
</body>
</html>
