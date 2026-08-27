<?php
session_start();
require_once __DIR__ . "/../config/db_connect.php";
if (!isset($pdo) || !($pdo instanceof PDO)) {
    $pdo = getDBConnection();
}

$error_msg = "";
$success_msg = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? '');

    if ($email === "") {
        $error_msg = "Please enter your email address.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_msg = "Please enter a valid email address.";
    } else {
        try {
            // Find user in signup table or users table
            $stmt = $pdo->prepare("SELECT user_id, fullname, emailadd FROM signup WHERE emailadd = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                $stmt_u = $pdo->prepare("SELECT u.user_id, COALESCE(s.fullname, u.username) AS fullname, s.emailadd FROM users u LEFT JOIN signup s ON s.user_id = u.user_id WHERE s.emailadd = ? OR u.email = ? LIMIT 1");
                $stmt_u->execute([$email, $email]);
                $user = $stmt_u->fetch(PDO::FETCH_ASSOC);
            }

            if ($user && !empty($user['user_id'])) {
                $user_id = $user['user_id'];
                $token = bin2hex(random_bytes(32));
                $expires = date("Y-m-d H:i:s", strtotime("+1 hour"));

                // Ensure reset_tokens table exists
                $pdo->exec("CREATE TABLE IF NOT EXISTS reset_tokens (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    token VARCHAR(64) NOT NULL,
                    expires DATETIME NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )");

                // Delete old tokens for this user
                $del = $pdo->prepare("DELETE FROM reset_tokens WHERE user_id = ?");
                $del->execute([$user_id]);

                // Save new token
                $ins = $pdo->prepare("INSERT INTO reset_tokens (user_id, token, expires) VALUES (?, ?, ?)");
                $ins->execute([$user_id, $token, $expires]);

                // Construct dynamic reset link
                $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
                $protocol = $isHttps ? 'https://' : 'http://';
                $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
                $scriptDir = rtrim(str_replace('\\', '/', $scriptDir), '/');
                $resetLink = $protocol . $host . $scriptDir . "/token.php?token=" . urlencode($token);

                // Send email via EmailSender
                require_once __DIR__ . '/../includes/EmailSender.php';
                $sender = new EmailSender();
                $subject = 'Password Reset Request - Alertara PH';
                $htmlBody = "
                <div style='font-family: Arial, sans-serif; max-width: 560px; margin: auto; padding: 25px; border: 1px solid #e2e8f0; border-radius: 12px; background: #ffffff;'>
                    <div style='text-align: center; margin-bottom: 24px;'>
                        <h2 style='color: #2e856e; margin: 0; font-size: 24px;'>Alertara PH</h2>
                        <p style='color: #64748b; margin: 4px 0 0 0; font-size: 14px;'>Law Enforcement & Incident Reporting System</p>
                    </div>
                    <div style='color: #334155; font-size: 15px; line-height: 1.6;'>
                        <p>Hello <strong>" . htmlspecialchars($user['fullname'] ?? 'User') . "</strong>,</p>
                        <p>We received a request to reset your password. Click the button below to choose a new password:</p>
                        <div style='text-align: center; margin: 28px 0;'>
                            <a href='{$resetLink}' style='background-color: #2e856e; color: #ffffff; padding: 12px 28px; text-decoration: none; border-radius: 8px; font-weight: bold; display: inline-block; font-size: 15px;'>Reset Password</a>
                        </div>
                        <p style='color: #64748b; font-size: 13px;'>This password reset link will expire in 1 hour. If you did not make this request, you can safely ignore this email.</p>
                        <hr style='border: none; border-top: 1px solid #f1f5f9; margin: 20px 0;'>
                        <p style='color: #94a3b8; font-size: 12px; word-break: break-all;'>If the button doesn't work, copy and paste this link into your browser:<br><a href='{$resetLink}' style='color: #2e856e;'>{$resetLink}</a></p>
                    </div>
                </div>";

                $res = $sender->send($email, $subject, $htmlBody);
                if ($res['success']) {
                    $success_msg = "A password reset link has been sent to " . htmlspecialchars($email) . ". Please check your inbox.";
                } else {
                    // In case mail server is offline or not configured in local dev, provide helpful link
                    $error_msg = "Notice: Mail delivery failed (" . ($res['error'] ?? 'SMTP Error') . ").";
                    // For local development test fallback:
                    $success_msg = "Reset token created. <a href='{$resetLink}' style='color: #2e856e; font-weight: bold;'>Click here to reset your password directly</a>.";
                }
            } else {
                $error_msg = "No account found with that email address.";
            }
        } catch (Exception $e) {
            $error_msg = "An error occurred: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Alertara PH</title>
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
                <h1 class="login-title">Forgot Password</h1>
                <p class="login-subtitle">Enter your email to receive a password reset link</p>
            </div>

            <div class="login-form-container">
                <?php if ($error_msg): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-circle-fill" style="font-size: 1.1rem; flex-shrink: 0;"></i>
                        <div><?php echo $error_msg; ?></div>
                    </div>
                <?php endif; ?>

                <?php if ($success_msg): ?>
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle-fill" style="font-size: 1.1rem; flex-shrink: 0;"></i>
                        <div><?php echo $success_msg; ?></div>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="form-group">
                        <label for="email" class="form-label">
                            <i class="bi bi-envelope"></i> Registered Email Address
                        </label>
                        <input type="email" class="form-control" id="email" name="email" 
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                               placeholder="e.g. resident@example.com" required>
                    </div>

                    <button type="submit" class="login-btn">
                        <i class="bi bi-send"></i> Send Reset Link
                    </button>
                </form>

                <div style="text-align: center;">
                    <a href="login.php" class="back-link">
                        <i class="bi bi-arrow-left"></i> Back to Login
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>