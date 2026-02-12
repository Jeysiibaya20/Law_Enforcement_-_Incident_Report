<?php
session_start();
require_once '../config/db_connect.php';

$page_title = 'Email Verification';
require_once '../includes/header.php';

$message = '';
$message_type = '';

if (isset($_GET['token'])) {
    $token = trim($_GET['token']);
    
    try {
        // Check if token exists and is valid
        $stmt = $pdo->prepare("SELECT user_id, fullname, emailadd, token_expires FROM signup WHERE verification_token = ? AND email_verified = 0");
        $stmt->execute([$token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            $message = "Invalid verification token or email already verified.";
            $message_type = "danger";
        } elseif (strtotime($user['token_expires']) < time()) {
            $message = "Verification token has expired. Please sign up again.";
            $message_type = "danger";
        } else {
            // ✅ Mark email as verified
            $updateStmt = $pdo->prepare("UPDATE signup SET email_verified = 1, verification_token = NULL, token_expires = NULL WHERE user_id = ?");
            $updateStmt->execute([$user['user_id']]);
            
            $message = "✅ Email verified successfully! Your account is now active. You can log in now.";
            $message_type = "success";
        }
    } catch (Exception $e) {
        $message = "An error occurred during verification. Please try again.";
        $message_type = "danger";
        error_log("Email verification error: " . $e->getMessage());
    }
} else {
    $message = "No verification token provided.";
    $message_type = "warning";
}
?>

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
                <h1 class="login-title">Alertara PH</h1>
                <p class="login-subtitle">Email Verification</p>
            </div>
            
            <div class="login-form">
                <div class="alert alert-<?= htmlspecialchars($message_type) ?> text-center">
                    <strong><?= htmlspecialchars($message) ?></strong>
                </div>
                
                <div class="text-center mt-4">
                    <p class="text-muted">
                        <?php if ($message_type === 'success'): ?>
                            Redirecting to login in <span id="countdown">5</span> seconds...
                        <?php else: ?>
                            <a href="login.php" class="btn btn-primary">Back to Login</a>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($message_type === 'success'): ?>
<script>
    let countdown = 5;
    const countdownEl = document.getElementById('countdown');
    
    const timer = setInterval(() => {
        countdown--;
        countdownEl.textContent = countdown;
        
        if (countdown <= 0) {
            clearInterval(timer);
            window.location.href = 'login.php';
        }
    }, 1000);
</script>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
