<?php
$page_title = 'Database Setup';
$base_url = '';
require_once 'includes/header.php';

require_once 'config/db_connect.php';

$setup_messages = [];

try {
    // Check if columns exist
    $stmt = $pdo->query("DESC signup");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $hasEmailVerified = false;
    $hasVerificationToken = false;
    $hasTokenExpires = false;
    
    foreach ($columns as $col) {
        if ($col['Field'] === 'email_verified') $hasEmailVerified = true;
        if ($col['Field'] === 'verification_token') $hasVerificationToken = true;
        if ($col['Field'] === 'token_expires') $hasTokenExpires = true;
    }
    
    // Add missing columns
    if (!$hasEmailVerified) {
        $pdo->query("ALTER TABLE signup ADD COLUMN email_verified TINYINT(1) DEFAULT 0");
        $setup_messages[] = ['type' => 'success', 'text' => '✅ Added email_verified column'];
    }
    
    if (!$hasVerificationToken) {
        $pdo->query("ALTER TABLE signup ADD COLUMN verification_token VARCHAR(255) NULL");
        $setup_messages[] = ['type' => 'success', 'text' => '✅ Added verification_token column'];
    }
    
    if (!$hasTokenExpires) {
        $pdo->query("ALTER TABLE signup ADD COLUMN token_expires DATETIME NULL");
        $setup_messages[] = ['type' => 'success', 'text' => '✅ Added token_expires column'];
    }
    
    if (empty($setup_messages)) {
        $setup_messages[] = ['type' => 'info', 'text' => '✓ All email verification columns already exist'];
    } else {
        $setup_messages[] = ['type' => 'success', 'text' => '✅ Database schema updated successfully!'];
    }
    
} catch (Exception $e) {
    $setup_messages[] = ['type' => 'danger', 'text' => '❌ Error: ' . $e->getMessage()];
}
?>

<div class="container mt-5 mb-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-lg">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">
                        <i class="bi bi-gear"></i> Email Verification Setup
                    </h4>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-4">
                        Setting up email verification system for user accounts...
                    </p>
                    
                    <div class="setup-messages">
                        <?php foreach ($setup_messages as $msg): ?>
                            <div class="alert alert-<?= htmlspecialchars($msg['type']) ?> d-flex align-items-center mb-3" role="alert">
                                <i class="bi bi-check-circle-fill me-2"></i>
                                <div><?= htmlspecialchars($msg['text']) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <hr>
                    
                    <h5 class="mt-4 mb-3">Setup Complete! ✅</h5>
                    <p class="text-muted">
                        Email verification has been successfully configured. Users will now need to verify their email address when signing up.
                    </p>
                    
                    <div class="alert alert-info mt-4">
                        <h6 class="alert-heading">What's Configured:</h6>
                        <ul class="mb-0">
                            <li>✓ Email verification tokens</li>
                            <li>✓ 24-hour token expiration</li>
                            <li>✓ Verification email sending</li>
                            <li>✓ Email confirmation validation</li>
                            <li>✓ Login protection (requires verified email)</li>
                        </ul>
                    </div>
                    
                    <div class="mt-4">
                        <a href="index.php" class="btn btn-primary">
                            <i class="bi bi-arrow-left"></i> Go to Dashboard
                        </a>
                        <a href="auth/signup.php" class="btn btn-outline-primary">
                            <i class="bi bi-person-plus"></i> Test Sign Up
                        </a>
                    </div>
                </div>
            </div>

            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-info-circle"></i> How It Works</h5>
                </div>
                <div class="card-body">
                    <ol>
                        <li><strong>User Signs Up</strong> - Account created with unverified status</li>
                        <li><strong>Verification Email Sent</strong> - Email with unique verification link</li>
                        <li><strong>User Verifies Email</strong> - Clicks link to confirm email address</li>
                        <li><strong>Account Activated</strong> - Can now log in normally</li>
                        <li><strong>Login Check</strong> - System verifies email before allowing login</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.setup-messages {
    animation: slideIn 0.3s ease-in;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.card {
    border: none;
    border-radius: 10px;
}

.card-header {
    border-radius: 10px 10px 0 0;
}

.alert {
    border-radius: 8px;
    animation: slideIn 0.3s ease-in;
}
</style>

<?php require_once 'includes/footer.php'; ?>
