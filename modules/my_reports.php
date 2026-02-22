<?php
$page_title = 'My Reports';
$base_url = '../';
require_once __DIR__ . '/../config/db_connect.php';

if (session_status() === PHP_SESSION_NONE) @session_start();
$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    header('Location: ../auth/login.php');
    exit();
}

// Check account verification status
$accountVerified = false;
$adminApprovalState = null;

try {
    $stmt = $pdo->prepare("SELECT email_verified, role FROM signup WHERE user_id = ?");
    $stmt->execute([$userId]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);
    $accountVerified = !empty($u['email_verified']);

    // Admin users are exempt from needing admin approval
    $userRole = strtolower($u['role'] ?? $_SESSION['role'] ?? '');
    if ($userRole === 'admin') {
        $accountVerified = true;
        $adminApprovalState = 1;
    }

    // Try to read admin approval column
    try {
        $stmt2 = $pdo->prepare("SELECT admin_approved FROM signup WHERE user_id = ?");
        $stmt2->execute([$userId]);
        $a = $stmt2->fetch(PDO::FETCH_ASSOC);
        if ($a && array_key_exists('admin_approved', $a)) {
            $adminApprovalState = (int)$a['admin_approved'];
            $accountVerified = ($adminApprovalState === 1);
        }
    } catch (Throwable $inner) {
        // ignore - column may not exist
        $adminApprovalState = null;
    }
} catch (Exception $e) {
    $accountVerified = false;
}

// Check banned status
try {
    $bStmt = $pdo->prepare("SELECT banned FROM signup WHERE user_id = ?");
    $bStmt->execute([$userId]);
    $bRow = $bStmt->fetch(PDO::FETCH_ASSOC);
    $isBanned = !empty($bRow['banned']);
} catch (Exception $e) {
    $isBanned = false;
}

if (!empty($isBanned)) {
    // Render banned notice in place of reports
    $myReports = [];
    $accountVerified = false; // also prevent filing reports
    $bannedNotice = true;
} else {
    $bannedNotice = false;
}

// Fetch user's incidents only if account is verified
$myReports = [];
if ($accountVerified) {
    try {
        $stmt = $pdo->prepare("SELECT id, case_no, incident_type, incident_date, status, created_at FROM incidents WHERE created_by = ? ORDER BY id DESC");
        $stmt->execute([$userId]);
        $myReports = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log('Error fetching user reports: ' . $e->getMessage());
        $myReports = [];
    }
}
// Now include header and navbar (after data fetching) so any redirects or header() calls can run first
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<div class="main-content">
    <div class="content-container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1>My Reports</h1>
                <p class="text-secondary">Your recent incident reports</p>
            </div>
            <div>
                <?php if (!empty($bannedNotice)): ?>
                    <button class="btn btn-secondary" disabled title="Account suspended">File Report</button>
                <?php elseif (!$accountVerified): ?>
                    <button class="btn btn-secondary" disabled title="Account must be approved by admin">File Report</button>
                <?php else: ?>
                    <a href="../modules/incident_report.php" class="btn btn-primary">File Report</a>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($bannedNotice)): ?>
            <div class="main-content">
                <div class="content-container">
                    <div class="alert alert-danger p-4 mb-4" role="alert" style="border-radius:6px;">
                        <h4 class="alert-heading">Account Suspended</h4>
                        <p class="mb-0">Your account has been banned by an administrator. You cannot access the reports until your account is unbanned. If you believe this is a mistake, contact the system administrator.</p>
                    </div>
                </div>
            </div>
        <?php elseif (!$accountVerified): ?>
            <!-- LOCKED STATE -->
            <div class="alert alert-warning mb-4">
                <strong>Account Verification Pending</strong>
                <p>Your account is currently under review by the admin. Your reports will be accessible once an admin approves your account.</p>
            </div>

            <div class="enhanced-card" style="position: relative;">
                <div style="position: absolute; inset: 0; background: rgba(255, 255, 255, 0.8); display: flex; align-items: center; justify-content: center; border-radius: 8px; z-index: 10;">
                    <div class="text-center">
                        <i class="bi bi-lock-fill" style="font-size: 48px; color: #dc3545; margin-bottom: 10px; display: block;"></i>
                        <h5 class="text-danger">Reports Locked</h5>
                        <p class="text-secondary">Waiting for admin approval</p>
                    </div>
                </div>

                <div class="table-responsive" style="opacity: 0.4;">
                    <table class="table table-sm align-middle">
                        <thead>
                            <tr>
                                <th>Case No</th>
                                <th>Type</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td colspan="5" class="text-center text-secondary">Reports hidden</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php else: ?>
            <!-- UNLOCKED STATE - Show reports -->
            <div class="enhanced-card">
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead>
                            <tr>
                                <th>Case No</th>
                                <th>Type</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($myReports)): ?>
                                <tr><td colspan="5" class="text-center text-secondary">You have not filed any reports yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($myReports as $r): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($r['case_no'] ?? '—'); ?></td>
                                        <td><?php echo htmlspecialchars($r['incident_type'] ?? '—'); ?></td>
                                        <td><?php echo htmlspecialchars($r['incident_date'] ?? '—'); ?></td>
                                        <td><?php echo htmlspecialchars($r['status'] ?? '—'); ?></td>
                                        <td><?php echo htmlspecialchars($r['created_at'] ?? '—'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
