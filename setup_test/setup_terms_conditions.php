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
    
    $hasTermsAccepted = false;
    $hasTermsAcceptedDate = false;
    
    foreach ($columns as $col) {
        if ($col['Field'] === 'terms_accepted') $hasTermsAccepted = true;
        if ($col['Field'] === 'terms_accepted_date') $hasTermsAcceptedDate = true;
    }
    
    // Add missing columns
    if (!$hasTermsAccepted) {
        $pdo->query("ALTER TABLE signup ADD COLUMN terms_accepted TINYINT(1) DEFAULT 0");
        $setup_messages[] = ['type' => 'success', 'text' => '✅ Added terms_accepted column'];
    }
    
    if (!$hasTermsAcceptedDate) {
        $pdo->query("ALTER TABLE signup ADD COLUMN terms_accepted_date DATETIME NULL");
        $setup_messages[] = ['type' => 'success', 'text' => '✅ Added terms_accepted_date column'];
    }
    
    if (empty($setup_messages)) {
        $setup_messages[] = ['type' => 'info', 'text' => '✓ All terms and conditions columns already exist'];
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
                <div class="card-header bg-success text-white">
                    <h4 class="mb-0">
                        <i class="bi bi-check-circle"></i> Terms and Conditions Setup
                    </h4>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-4">
                        Setting up terms and conditions approval system...
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
                        Terms and Conditions approval has been successfully configured. Users will now be required to accept terms before signing up.
                    </p>
                    
                    <div class="alert alert-success mt-4">
                        <h6 class="alert-heading">What's Configured:</h6>
                        <ul class="mb-0">
                            <li>✓ Terms and Conditions page</li>
                            <li>✓ Data Privacy Policy page</li>
                            <li>✓ Terms acceptance checkbox on signup</li>
                            <li>✓ Database tracking of acceptance</li>
                            <li>✓ Server-side validation</li>
                        </ul>
                    </div>
                    
                    <div class="alert alert-info">
                        <h6 class="alert-heading">Pages Created:</h6>
                        <ul class="mb-0">
                            <li><strong>Terms and Conditions:</strong> <code>auth/terms_conditions.php</code></li>
                            <li><strong>Data Privacy Policy:</strong> <code>auth/data_privacy.php</code></li>
                            <li><strong>Signup Form:</strong> Updated with checkbox</li>
                        </ul>
                    </div>
                    
                    <div class="mt-4">
                        <a href="index.php" class="btn btn-primary">
                            <i class="bi bi-arrow-left"></i> Go to Dashboard
                        </a>
                        <a href="auth/signup.php" class="btn btn-outline-primary">
                            <i class="bi bi-person-plus"></i> Test Sign Up
                        </a>
                        <a href="auth/terms_conditions.php" class="btn btn-outline-secondary" target="_blank">
                            <i class="bi bi-file-text"></i> View Terms
                        </a>
                        <a href="auth/data_privacy.php" class="btn btn-outline-secondary" target="_blank">
                            <i class="bi bi-shield-lock"></i> View Privacy
                        </a>
                    </div>
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
