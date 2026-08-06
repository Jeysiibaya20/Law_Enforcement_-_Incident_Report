<?php
$base_url = '';
require_once "includes/user_auth.php";
$page_title = 'Resident Portal - Alertara';
$force_public_sidebar = true;

require_once "includes/header.php";
require_once "includes/navbar.php";
require_once __DIR__ . '/config/db_connect.php';

if (session_status() === PHP_SESSION_NONE) @session_start();
$userId = $_SESSION['user_id'] ?? null;

// Default counts
$myReports = 0;
$activeCases = 0;
$myClearances = 0;
$isBanned = false;
$userApproved = true;
$userNeedsApproval = false;

if ($userId) {
    try {
        $stmt = $pdo->prepare("SELECT role, fullname, banned, admin_approved FROM signup WHERE user_id = ?");
        $stmt->execute([$userId]);
        $u = $stmt->fetch(PDO::FETCH_ASSOC);
        $_SESSION['fullname'] = $u['fullname'] ?? $_SESSION['fullname'] ?? '';
        $isBanned = !empty($u['banned']);
        $userApproved = !empty($u['admin_approved']) && (int)$u['admin_approved'] === 1;
        $userNeedsApproval = !$isBanned && !$userApproved && strtolower($_SESSION['role'] ?? '') !== 'admin';
    } catch (Exception $e) {
        $isBanned = false;
        $userApproved = true;
        $userNeedsApproval = false;
    }

    if ($userNeedsApproval) {
        $myReports = 0;
        $activeCases = 0;
        $myClearances = 0;
    }

    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM incidents WHERE created_by = ?");
        $stmt->execute([$userId]);
        $myReports = (int)($stmt->fetchColumn() ?? 0);
    } catch (Throwable $e) { $myReports = 0; }

    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM case_assignments WHERE (assigned_to = ? OR assigned_by = ?) AND status NOT IN ('Closed','Resolved','Archived')");
        $stmt->execute([$userId, $userId]);
        $activeCases = (int)($stmt->fetchColumn() ?? 0);
    } catch (Throwable $e) { $activeCases = 0; }

    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM clearances WHERE user_id = ?");
        $stmt->execute([$userId]);
        $myClearances = (int)($stmt->fetchColumn() ?? 0);
    } catch (Throwable $e) { $myClearances = 0; }
}
?>

<div class="main-content">
    <div class="content-container">
        <!-- Header Title Section -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h2 fw-bold mb-1" style="font-family: 'Quicksand', 'Inter', sans-serif;">Resident Portal Overview</h1>
                <p class="text-secondary small mb-0">Track your reported incidents, service requests, and community safety updates</p>
            </div>
            <div>
                <a href="modules/Incident_report.php" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus-circle me-1"></i> File Incident Report
                </a>
            </div>
        </div>

        <?php if (!empty($isBanned)): ?>
            <div class="alert alert-danger mb-4 rounded-3 shadow-sm">
                <i class="fas fa-exclamation-triangle me-2"></i><strong>Account Suspended:</strong> Your account has been suspended by an administrator. Incident reporting and blotter access are currently locked.
            </div>
        <?php elseif (!empty($userNeedsApproval)): ?>
            <div class="alert alert-warning mb-4 rounded-3 shadow-sm">
                <i class="fas fa-user-clock me-2"></i><strong>Account Pending Verification:</strong> Your account is pending administrator approval. Module services will become fully available upon verification.
            </div>
        <?php endif; ?>

        <!-- Welcome Banner -->
        <div class="card border-0 text-white mb-4 shadow-sm" style="background: linear-gradient(135deg, #1b5a56 0%, #4c8a89 100%); border-radius: 12px; padding: 1.5rem;">
            <h4 class="fw-bold mb-1 text-white" style="font-family: 'Quicksand', sans-serif;">Welcome back, <?php echo htmlspecialchars($_SESSION['fullname'] ?? 'Resident'); ?>!</h4>
            <p class="mb-0 text-white-50 small">Track your filed reports, clearance requests, and stay updated on your local barangay public safety services.</p>
        </div>

        <!-- Stat Cards -->
        <div class="row g-3 mb-4">
            <div class="col-12 col-md-4">
                <div class="card h-100 border-start border-primary border-4 shadow-sm p-3 position-relative">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted small text-uppercase fw-bold">My Reports</span>
                            <div class="h2 fw-bold text-primary my-1" style="font-family: 'Quicksand', sans-serif;"><?php echo $myReports; ?></div>
                            <small class="text-muted">Total filed incidents</small>
                        </div>
                        <div class="text-primary opacity-50">
                            <i class="fas fa-folder-open fa-2x"></i>
                        </div>
                    </div>
                    <?php if (!empty($isBanned)): ?>
                        <div style="position:absolute;inset:0;background:rgba(255,255,255,0.75);display:flex;align-items:center;justify-content:center;border-radius:6px;">
                            <div class="text-center text-muted">
                                <i class="fas fa-lock text-danger" style="font-size:24px"></i>
                                <div class="small fw-semibold mt-1">Account Suspended</div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-12 col-md-4">
                <div class="card h-100 border-start border-warning border-4 shadow-sm p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted small text-uppercase fw-bold">Active Cases</span>
                            <div class="h2 fw-bold text-warning my-1" style="font-family: 'Quicksand', sans-serif;"><?php echo $activeCases; ?></div>
                            <small class="text-muted">Currently in progress</small>
                        </div>
                        <div class="text-warning opacity-50">
                            <i class="fas fa-spinner fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-4">
                <div class="card h-100 border-start border-success border-4 shadow-sm p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted small text-uppercase fw-bold">My Clearances</span>
                            <div class="h2 fw-bold text-success my-1" style="font-family: 'Quicksand', sans-serif;"><?php echo $myClearances; ?></div>
                            <small class="text-muted">Requested certificates</small>
                        </div>
                        <div class="text-success opacity-50">
                            <i class="fas fa-file-contract fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Reports Table Card -->
        <div class="card shadow-sm border-0">
            <div class="card-header bg-card fw-bold d-flex justify-content-between align-items-center">
                <span><i class="fas fa-history me-2 text-primary"></i>My Recent Reports</span>
                <a href="modules/my_reports.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size: 0.875rem;">
                        <thead class="table-light">
                            <tr>
                                <th>Case No</th>
                                <th>Incident Type</th>
                                <th>Date Reported</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if (!empty($isBanned)) {
                                echo '<tr><td colspan="4" class="text-center text-danger py-4">Your account has been suspended. Reports are unavailable until your account is reinstated.</td></tr>';
                            } elseif (!empty($userNeedsApproval)) {
                                echo '<tr><td colspan="4" class="text-center text-warning py-4">Your account is pending verification. Reporting and module access are locked until an administrator approves your account.</td></tr>';
                            } else {
                                try {
                                    if ($userId) {
                                        $stmt = $pdo->prepare("SELECT case_no, incident_type, incident_date, status FROM incidents WHERE created_by = ? ORDER BY id DESC LIMIT 5");
                                        $stmt->execute([$userId]);
                                        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                    } else {
                                        $rows = [];
                                    }
                                } catch (Exception $e) { $rows = []; }

                                if (empty($rows)) {
                                    echo '<tr><td colspan="4" class="text-center text-muted py-4"><i class="fas fa-info-circle me-2"></i>You have not filed any incident reports yet.</td></tr>';
                                } else {
                                    foreach ($rows as $r) {
                                        $status = htmlspecialchars($r['status'] ?? 'Pending');
                                        $badgeClass = 'bg-secondary';
                                        if (strcasecmp($status, 'Resolved') === 0) $badgeClass = 'bg-success';
                                        elseif (strcasecmp($status, 'Ongoing') === 0) $badgeClass = 'bg-info';
                                        elseif (strcasecmp($status, 'New') === 0 || strcasecmp($status, 'Pending') === 0) $badgeClass = 'bg-warning text-dark';

                                        echo '<tr>';
                                        echo '<td class="fw-semibold">'.htmlspecialchars($r['case_no'] ?? '—').'</td>';
                                        echo '<td>'.htmlspecialchars($r['incident_type'] ?? '—').'</td>';
                                        echo '<td>'.htmlspecialchars($r['incident_date'] ? date('M d, Y', strtotime($r['incident_date'])) : '—').'</td>';
                                        echo '<td><span class="badge '.$badgeClass.'">'.$status.'</span></td>';
                                        echo '</tr>';
                                    }
                                }
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
