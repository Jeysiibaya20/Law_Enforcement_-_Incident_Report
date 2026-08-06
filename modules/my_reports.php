<?php
$base_url = '../';
require_once __DIR__ . '/../includes/user_auth.php';
$page_title = 'My Reports';
require_once __DIR__ . '/../config/db_connect.php';
if (!isset($pdo) || !($pdo instanceof PDO)) {
    $pdo = getDBConnection();
}

if (session_status() === PHP_SESSION_NONE) @session_start();
$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    header('Location: ../auth/login.php');
    exit();
}

// Check account verification status
$bannedNotice = false;
$userNeedsApproval = false;
try {
    $bStmt = $pdo->prepare("SELECT banned, admin_approved FROM signup WHERE user_id = ?");
    $bStmt->execute([$userId]);
    $bRow = $bStmt->fetch(PDO::FETCH_ASSOC);
    $isBanned = !empty($bRow['banned']);
    $userApproved = !empty($bRow['admin_approved']) && (int)$bRow['admin_approved'] === 1;
    $userNeedsApproval = !$isBanned && !$userApproved && strtolower($_SESSION['role'] ?? '') !== 'admin';
} catch (Exception $e) {
    $isBanned = false;
    $userApproved = true;
    $userNeedsApproval = false;
}

if (!empty($isBanned) || $userNeedsApproval) {
    $myReports = [];
    $bannedNotice = true;
} else {
    $bannedNotice = false;
}

if (!empty($isBanned) || (!$userApproved && strtolower($_SESSION['role'] ?? '') !== 'admin')) {
    $myReports = [];
    $bannedNotice = true;
} else {
    $bannedNotice = false;
}

// Fetch user's incidents unless the account is suspended
$myReports = [];
if (!$bannedNotice) {
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

            </div>
        </div>

        <?php if (!empty($bannedNotice)): ?>
            <div class="alert alert-danger p-4 mb-4" role="alert" style="border-radius:6px;">
                <h4 class="alert-heading"><?php echo !empty($userNeedsApproval) ? 'Access Locked' : 'Account Suspended'; ?></h4>
                <p class="mb-0"><?php echo !empty($userNeedsApproval) ? 'Your account is pending administrator approval. The reports and blotter access are locked until an administrator approves your account.' : 'Your account has been banned by an administrator. You cannot access the reports until your account is unbanned. If you believe this is a mistake, contact the system administrator.'; ?></p>
            </div>
        <?php else: ?>
            <!-- Show reports -->
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
