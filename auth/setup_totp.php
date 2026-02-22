<?php
// Setup TOTP (Google Authenticator) for logged-in user
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/two_factor_auth.php';

$tfa = new TwoFactorAuth($pdo);

// Allow either a logged-in user or a one-time setup access granted from verify_2fa
$oneTimeMode = false;
$userId = $_SESSION['user_id'] ?? null;
if (empty($userId)) {
    if (!empty($_SESSION['one_time_setup_user'])) {
        $userId = $_SESSION['one_time_setup_user'];
        $oneTimeMode = true;
        // If pending username exists, copy it for labeling purposes
        if (!empty($_SESSION['pending_2fa_username'])) {
            $_SESSION['username'] = $_SESSION['pending_2fa_username'];
        }
    } else {
        header('Location: login.php');
        exit();
    }
}

// Helper: generate base32 secret
function generate_base32_secret($length = 16) {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret = '';
    try {
        for ($i = 0; $i < $length; $i++) {
            $secret .= $chars[random_int(0, strlen($chars) - 1)];
        }
    } catch (Exception $e) {
        for ($i = 0; $i < $length; $i++) {
            $secret .= $chars[mt_rand(0, strlen($chars) - 1)];
        }
    }
    return $secret;
}

// Get username/email for label
$username = $_SESSION['username'] ?? null;
if (!$username) {
    try {
        $stmt = $pdo->prepare('SELECT username, emailadd FROM signup WHERE user_id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        $username = $r['username'] ?? $r['emailadd'] ?? ('user' . $userId);
    } catch (Exception $e) {
        $username = 'user' . $userId;
    }
}

$message = '';

// If user already has a secret, show it
$existing = $tfa->getUserSecret($userId);
if (!empty($existing)) {
    $secret = $existing;
} else {
    $secret = generate_base32_secret(16);
}

// Handle confirmation POST: user submits TOTP code to enable
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm'])) {
    $code = trim($_POST['code'] ?? '');
    if (empty($code)) {
        $message = 'Please enter the 6-digit code from your authenticator app.';
    } else {
        // verify code against generated secret
        if ($tfa->verifyTOTP($secret, $code)) {
            // store secret in DB and enable TOTP
            $ok = $tfa->enable2FA($userId, $secret, 'TOTP');
            if ($ok) {
                $message = 'Two-factor authentication enabled successfully.';
                // reload existing secret from DB
                $existing = $tfa->getUserSecret($userId);
                // If this setup was performed via one-time access, finalize session login and cleanup
                if (!empty($oneTimeMode)) {
                    // Log the user in (establish session user_id) and remove the one-time token
                    $_SESSION['user_id'] = $userId;
                    unset($_SESSION['one_time_setup_user']);
                    // Remove any pending 2FA session values used during login
                    unset($_SESSION['pending_2fa_user'], $_SESSION['pending_2fa_method'], $_SESSION['pending_2fa_email'], $_SESSION['pending_2fa_phone'], $_SESSION['pending_2fa_username'], $_SESSION['pending_2fa_role']);
                }
            } else {
                $message = 'Failed to enable 2FA. Please try again.';
            }
        } else {
            $message = 'Invalid code. Ensure you scanned the QR and your device time is correct.';
        }
    }
}

// otpauth URL
$issuer = 'Alertara';
$label = $username;
$otpauth = "otpauth://totp/" . urlencode($issuer) . ":" . urlencode($label) . "?secret=" . $secret . "&issuer=" . urlencode($issuer) . "&algorithm=SHA1&digits=6&period=30";
$qrUrl = 'https://chart.googleapis.com/chart?chs=250x250&chld=M|0&cht=qr&chl=' . urlencode($otpauth);

?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Setup Authenticator App</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div style="max-width:640px;margin:3rem auto;padding:1.5rem;border:1px solid #ddd;border-radius:8px;">
        <h2>Enable Authenticator App (TOTP)</h2>
        <?php if ($message): ?>
            <div style="padding:0.5rem;background:#d4edda;border:1px solid #c3e6cb;margin-bottom:1rem;"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <?php if (empty($existing)): ?>
            <p>Scan this QR code with your authenticator app (Google Authenticator, Authy, etc.). If your app cannot scan, enter this secret manually:</p>
            <div style="display:flex;gap:1rem;align-items:center;">
                <img src="<?php echo $qrUrl; ?>" alt="QR Code" style="border:1px solid #ccc;padding:8px;background:#fff;width:250px;height:250px;">
                <div>
                    <strong>Secret:</strong>
                    <div style="font-family:monospace;font-size:1.1rem;margin-top:0.5rem;"><?php echo htmlspecialchars($secret); ?></div>
                </div>
            </div>
            <form method="post" style="margin-top:1rem;">
                <label for="code">Enter the 6-digit code from your app to confirm and enable:</label><br>
                <input id="code" name="code" pattern="\d*" inputmode="numeric" maxlength="6" required style="padding:0.5rem;margin-top:0.5rem;">
                <div style="margin-top:1rem;"><button type="submit" name="confirm" style="padding:0.6rem;background:#28a745;color:#fff;border:none;border-radius:4px;">Confirm & Enable</button></div>
            </form>
        <?php else: ?>
            <p>TOTP is already enabled for your account. If you want to re-provision (generate a new secret), disable first in your account settings and then re-run this setup.</p>
        <?php endif; ?>

        <p style="margin-top:1rem;"><a href="../index.php">Back</a></p>
    </div>
</body>
</html>
