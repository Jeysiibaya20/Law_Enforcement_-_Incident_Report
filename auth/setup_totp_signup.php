<?php
// Post-signup TOTP setup page
// Shown after user completes registration if they enabled TOTP option

session_start();
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/two_factor_auth.php';

// Ensure user has pending TOTP setup from signup
if (empty($_SESSION['pending_totp_setup'])) {
    header('Location: login.php');
    exit();
}

$setup = $_SESSION['pending_totp_setup'];
$userId = $setup['user_id'];
$secret = $setup['secret'];
$username = $setup['username'];

$tfa = new TwoFactorAuth($pdo);
$message = '';

// Handle confirmation POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm'])) {
    $code = trim($_POST['code'] ?? '');
    if (empty($code)) {
        $message = 'Please enter the 6-digit code from your authenticator app.';
    } else {
        // Verify code against generated secret
        if ($tfa->verifyTOTP($secret, $code)) {
            // Store secret and enable TOTP
            $ok = $tfa->enable2FA($userId, $secret, 'TOTP');
            if ($ok) {
                $message = '✓ Two-factor authentication enabled successfully!';
                // Redirect directly to login
                unset($_SESSION['pending_totp_setup']);
                $_SESSION['totp_enabled'] = 1;
                header('Location: login.php');
                exit();
            } else {
                $message = 'Failed to enable TOTP. Please try again.';
            }
        } else {
            $message = 'Invalid code. Ensure you scanned the QR and your device time is synchronized.';
        }
    }
}

// Generate otpauth URL for QR code
$issuer = 'Alertara';
$label = $username . '@alertara';
$otpauth = "otpauth://totp/" . urlencode($issuer) . ":" . urlencode($label) . "?secret=" . $secret . "&issuer=" . urlencode($issuer) . "&algorithm=SHA1&digits=6&period=30";
$qrUrl = 'https://chart.googleapis.com/chart?chs=250x250&chld=M|0&cht=qr&chl=' . urlencode($otpauth);

?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Authenticator App</title>
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

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Quicksand', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: var(--primary-bg);
    color: var(--text-primary);
    line-height: 1.6;
}

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
    max-width: 500px;
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
    max-width: 500px;
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
    font-size: 1.8rem;
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

.form-group {
    margin-bottom: 1.5rem;
    position: relative;
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
    padding: 0.75rem 1rem;
    border: 2px solid #e5e5e5;
    border-radius: var(--border-radius-sm);
    font-size: 1rem;
    background: rgba(255, 255, 255, 0.9);
    transition: var(--transition);
    font-family: 'Quicksand', sans-serif;
}

.form-control:focus {
    outline: none;
    border-color: var(--main-color);
    box-shadow: 0 0 0 3px rgba(139, 111, 71, 0.1);
    background: var(--text-white);
}

.form-control::placeholder {
    color: var(--text-light);
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
    box-shadow: var(--shadow-md);
    text-decoration: none;
}

.login-btn:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
    background: #3a506b;
}

.login-btn:active {
    transform: translateY(0);
}

.alert {
    padding: 1rem;
    border-radius: var(--border-radius-sm);
    margin-bottom: 1.5rem;
    font-size: 0.95rem;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert-info {
    background: #d1ecf1;
    color: #0c5460;
    border: 1px solid #bee5eb;
}

.qr-container {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
    align-items: center;
    margin: 1.5rem 0;
    text-align: center;
}

.qr-box {
    display: flex;
    justify-content: center;
}

.qr-box img {
    border: 2px solid #ddd;
    padding: 8px;
    background: #fff;
    border-radius: 8px;
}

.secret-box {
    width: 100%;
}

.secret-box strong {
    font-size: 1.1rem;
    color: var(--text-primary);
}

.secret-code {
    font-family: 'Courier New', monospace;
    font-size: 1.3rem;
    letter-spacing: 3px;
    margin: 0.75rem 0;
    padding: 1rem;
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 6px;
    word-break: break-all;
}

.text-muted {
    color: var(--text-light);
    font-size: 0.9rem;
}

.button-group {
    display: flex;
    gap: 0.5rem;
    margin-top: 1.5rem;
    flex-wrap: wrap;
}

.button-group button,
.button-group a {
    flex: 1;
    min-width: 150px;
}

.skip-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    background: #6c757d;
}

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

@keyframes float {
    0%, 100% {
        transform: translateY(0px);
    }
    50% {
        transform: translateY(-10px);
    }
}

@media (max-width: 768px) {
    .login-container {
        padding: 1rem;
    }
    
    .login-content {
        padding: 1rem;
        max-width: 100%;
    }
    
    .login-card {
        max-width: 100%;
    }
    
    .login-header {
        padding: 2rem 1.5rem 1.5rem;
    }
    
    .login-form-container {
        padding: 1.5rem;
    }
    
    .login-title {
        font-size: 1.5rem;
    }
    
    .login-logo {
        width: 60px;
        height: 60px;
        margin-bottom: 1rem;
    }
    
    .qr-container {
        gap: 1rem;
    }
    
    .button-group {
        flex-direction: column;
    }
    
    .button-group button,
    .button-group a {
        min-width: auto;
    }
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
                <h1 class="login-title">Setup Authenticator</h1>
                <p class="login-subtitle">Google Authenticator or Authy</p>
            </div>

            <div class="login-form-container">
                <?php if ($message && strpos($message, '✓') === 0): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
                <?php elseif ($message): ?>
                    <div class="alert alert-info"><?php echo htmlspecialchars($message); ?></div>
                <?php endif; ?>

                <div class="alert alert-info">
                    <strong>Next Step:</strong> Scan the QR code below with your authenticator app (Google Authenticator, Authy, Microsoft Authenticator, etc.), then enter the 6-digit code to confirm setup.
                </div>

                <div class="qr-container">
                    <div class="qr-box">
                        <img src="<?php echo $qrUrl; ?>" alt="QR Code for TOTP" style="width:250px;height:250px;border:3px solid #ddd;padding:10px;background:#fff;border-radius:8px;">
                    </div>
                    <div class="secret-box">
                        <strong>Can't scan QR code?</strong><br>
                        <p class="text-muted">Enter this secret manually in your authenticator app:</p>
                        <div class="secret-code"><?php echo htmlspecialchars($secret); ?></div>
                        <p class="text-muted"><small>Keep this code safe. You'll need it if you lose your phone.</small></p>
                    </div>
                </div>

                <form method="post">
                    <div class="form-group">
                        <label class="form-label">Enter the 6-digit code from your authenticator app:</label>
                        <input type="text" class="form-control" name="code" inputmode="numeric" pattern="\d*" maxlength="6" required autofocus style="font-size:1.3rem;letter-spacing:8px;text-align:center;">
                    </div>
                    <div class="button-group">
                        <button type="submit" name="confirm" class="login-btn">Confirm & Continue</button>
                        <a href="login.php" class="login-btn skip-link">Skip for Now</a>
                    </div>
                </form>

                <p style="margin-top:1.5rem;text-align:center;font-size:0.9rem;color:var(--text-light);">You'll still need to verify your email or phone to complete registration.</p>
            </div>
        </div>
    </div>
</div>
</body>
</html>
