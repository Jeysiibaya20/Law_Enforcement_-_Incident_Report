<?php
/**
 * TOTP Authenticator Setup (Google Authenticator / Microsoft Authenticator)
 * 100% Offline 2-Factor Authentication - Zero Email / SMS required
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/two_factor_auth.php';

$pdo = getDBConnection();
$tfa = new TwoFactorAuth($pdo);

// Determine the user setting up 2FA
$userId = $_SESSION['user_id'] ?? $_SESSION['resident_user_id'] ?? $_SESSION['pending_2fa_user'] ?? $_SESSION['one_time_setup_user'] ?? null;
$username = $_SESSION['username'] ?? $_SESSION['pending_2fa_username'] ?? 'User';

if (!$userId) {
    header('Location: login.php');
    exit();
}

$message = '';
$messageType = '';

// Check if user already has a secret in session or database, or generate a fresh one
if (empty($_SESSION['setup_totp_secret'])) {
    $existingSecret = $tfa->getUserSecret($userId);
    $_SESSION['setup_totp_secret'] = $existingSecret ?: $tfa->generateSecret(16);
}

$secret = $_SESSION['setup_totp_secret'];
$qrCodeUrl = $tfa->getQRCodeUrl($username, $secret, 'Alertara QC');

// Handle verification form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['code'] ?? '');
    
    if (empty($code)) {
        $message = "Please enter the 6-digit code from your authenticator app.";
        $messageType = "danger";
    } else {
        // Verify the code against the secret
        if ($tfa->verifyTOTP($secret, $code)) {
            // Save to database
            $tfa->enable2FA($userId, $secret, 'TOTP');
            
            // If user was in pending login state, complete login
            if (!empty($_SESSION['pending_2fa_user']) || !empty($_SESSION['one_time_setup_user'])) {
                $role = strtolower($_SESSION['pending_2fa_role'] ?? 'user');
                $_SESSION['user_id'] = $userId;
                
                if (strpos($role, 'admin') !== false || strpos($role, 'officer') !== false) {
                    $_SESSION['admin_user_id'] = $userId;
                    $_SESSION['admin_fullname'] = $_SESSION['pending_2fa_username'] ?? 'Admin';
                    $_SESSION['admin_username'] = $_SESSION['pending_2fa_username'] ?? 'Admin';
                    $_SESSION['admin_role'] = ucfirst($role);
                    $redirectUrl = '../admin/dashboard.php';
                } else {
                    $_SESSION['resident_user_id'] = $userId;
                    $_SESSION['fullname'] = $_SESSION['pending_2fa_username'] ?? 'Resident';
                    $_SESSION['username'] = $_SESSION['pending_2fa_username'] ?? 'Resident';
                    $redirectUrl = '../modules/my_reports.php';
                }
                
                unset($_SESSION['pending_2fa_user'], $_SESSION['pending_2fa_method'], $_SESSION['setup_totp_secret'], $_SESSION['one_time_setup_user'], $_SESSION['pending_2fa_role'], $_SESSION['pending_2fa_username'], $_SESSION['pending_2fa_email']);
                header("Location: {$redirectUrl}");
                exit();
            }
            
            unset($_SESSION['setup_totp_secret']);
            $message = "Authenticator App 2FA successfully configured and enabled!";
            $messageType = "success";
        } else {
            $message = "Invalid 6-digit code. Please verify that your phone's time is synchronized and try again.";
            $messageType = "danger";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set Up Authenticator App - Alertara</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: #0f172a;
            color: #f8fafc;
            font-family: 'Segoe UI', system-ui, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .setup-card {
            background: #1e293b;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.4);
            border: 1px solid rgba(46, 133, 110, 0.3);
            max-width: 540px;
            width: 100%;
            overflow: hidden;
        }
        .setup-header {
            background: linear-gradient(135deg, #1b5a56, #2e856e);
            padding: 24px;
            text-align: center;
        }
        .qr-box {
            background: #ffffff;
            padding: 16px;
            border-radius: 12px;
            display: inline-block;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        .secret-badge {
            background: #0f172a;
            color: #38bdf8;
            font-family: monospace;
            font-size: 1.15rem;
            letter-spacing: 2px;
            padding: 8px 16px;
            border-radius: 8px;
            border: 1px dashed #38bdf8;
            display: inline-block;
            user-select: all;
        }
        .btn-brand {
            background: #2e856e;
            border-color: #2e856e;
            color: #ffffff;
            font-weight: 600;
        }
        .btn-brand:hover {
            background: #236c59;
            border-color: #236c59;
            color: #ffffff;
        }
        .step-circle {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: #2e856e;
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 0.85rem;
            margin-right: 8px;
        }
    </style>
</head>
<body>

<div class="setup-card">
    <div class="setup-header">
        <h4 class="fw-bold mb-1"><i class="fas fa-shield-alt me-2"></i>Set Up Authenticator 2FA</h4>
        <p class="small text-white-50 mb-0">100% Offline 2-Factor Authentication (No Email Needed)</p>
    </div>

    <div class="p-4">
        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?> py-2 small mb-3">
                <i class="fas <?= $messageType === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle' ?> me-1"></i>
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <!-- Step 1: Install App -->
        <div class="mb-4">
            <h6 class="fw-bold text-white d-flex align-items-center">
                <span class="step-circle">1</span> Get an Authenticator App
            </h6>
            <p class="small text-muted ms-4 mb-0">
                Install <strong>Google Authenticator</strong>, <strong>Microsoft Authenticator</strong>, or <strong>Authy</strong> on your mobile phone from the App Store or Google Play Store.
            </p>
        </div>

        <!-- Step 2: Scan QR Code -->
        <div class="mb-4">
            <h6 class="fw-bold text-white d-flex align-items-center">
                <span class="step-circle">2</span> Scan the QR Code
            </h6>
            <div class="text-center my-3">
                <div class="qr-box">
                    <img src="<?= htmlspecialchars($qrCodeUrl) ?>" alt="TOTP QR Code" width="180" height="180">
                </div>
            </div>
            <p class="small text-center text-muted mb-1">Cannot scan the code? Enter this secret key manually:</p>
            <div class="text-center">
                <span class="secret-badge"><?= htmlspecialchars($secret) ?></span>
            </div>
        </div>

        <!-- Step 3: Enter 6-digit Code -->
        <form method="POST">
            <div class="mb-3">
                <h6 class="fw-bold text-white d-flex align-items-center mb-2">
                    <span class="step-circle">3</span> Enter the 6-Digit Code
                </h6>
                <p class="small text-muted ms-4 mb-2">Enter the 6-digit verification code displayed in your authenticator app to activate 2FA:</p>
                <div class="ms-4">
                    <input type="text" name="code" class="form-control form-control-lg text-center fw-bold" placeholder="000000" maxlength="6" pattern="\d{6}" autocomplete="off" required style="letter-spacing: 6px; font-size: 1.5rem; background: #0f172a; color: #38bdf8; border-color: #334155;">
                </div>
            </div>

            <div class="d-grid gap-2 mt-4">
                <button type="submit" class="btn btn-brand btn-lg">
                    <i class="fas fa-check-circle me-1"></i> Verify & Activate 2FA
                </button>
                <a href="login.php" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-arrow-left me-1"></i> Back to Login
                </a>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
