<?php
$base_url = '';
require_once "includes/user_auth.php";
$page_title = 'Resident Portal - Alertara';

require_once __DIR__ . '/config/db_connect.php';
if (!isset($pdo) || !($pdo instanceof PDO)) {
    $pdo = getDBConnection();
}

$userId = $_SESSION['user_id'];
$fullname = $_SESSION['fullname'] ?? $_SESSION['first_name'] ?? 'Resident';

// ── Single query for account status + stats ──
$myReports = 0;
$activeCases = 0;
$myClearances = 0;
$isBanned = false;
$userApproved = true;
$userNeedsApproval = false;

try {
    $stmt = $pdo->prepare("SELECT fullname, banned, admin_approved FROM signup WHERE user_id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($u) {
        $_SESSION['fullname'] = $u['fullname'] ?? $fullname;
        $fullname = $_SESSION['fullname'];
        $isBanned = !empty($u['banned']);
        $userApproved = !empty($u['admin_approved']) && (int)$u['admin_approved'] === 1;
        $userNeedsApproval = !$isBanned && !$userApproved;
    }
} catch (Exception $e) {}

if (!$isBanned && !$userNeedsApproval) {
    try { 
        $s = $pdo->prepare("SELECT COUNT(*) FROM blotters WHERE complainant_name LIKE ? OR created_by = ?");
        $s->execute(['%' . $fullname . '%', $userId]);
        $myReports = (int)$s->fetchColumn(); 
    } catch (Throwable $e) { $myReports = 0; }

    try { 
        $s = $pdo->prepare("SELECT COUNT(*) FROM case_assignments WHERE (assigned_to = ? OR assigned_by = ?) AND status NOT IN ('Closed','Resolved','Archived')"); 
        $s->execute([$userId, $userId]); 
        $activeCases = (int)$s->fetchColumn(); 
    } catch (Throwable $e) { $activeCases = 0; }

    try { 
        $s = $pdo->prepare("SELECT COUNT(*) FROM clearances WHERE user_id = ?"); 
        $s->execute([$userId]); 
        $myClearances = (int)$s->fetchColumn(); 
    } catch (Throwable $e) { $myClearances = 0; }
}

// Recent reports for table
$recentReports = [];
if (!$isBanned && !$userNeedsApproval) {
    try {
        $s = $pdo->prepare("SELECT id, blotter_no, incident_type, incident_date, status, respondent_name FROM blotters WHERE complainant_name LIKE ? OR created_by = ? ORDER BY id DESC LIMIT 5");
        $s->execute(['%' . $fullname . '%', $userId]);
        $recentReports = $s->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}

// ── Include layout AFTER data fetching ──
require_once "includes/header.php";
require_once "includes/navbar.php";
?>

<div class="main-content">
    <div class="content-container">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h1 class="h2 fw-bold mb-1" style="font-family: 'Quicksand', 'Inter', sans-serif;">Resident Portal</h1>
                <p class="text-secondary small mb-0">Track your filed blotter complaints, hearing schedules, and barangay services</p>
            </div>
            <a href="modules/blotter_create.php" class="btn btn-primary btn-sm shadow-sm">
                <i class="fas fa-pen-nib me-1"></i> Create Blotter
            </a>
        </div>

        <?php if ($isBanned): ?>
            <div class="alert alert-danger mb-4 rounded-3 shadow-sm">
                <i class="fas fa-ban me-2"></i><strong>Account Suspended:</strong> Blotter filing and services are locked until reinstatement.
            </div>
        <?php elseif ($userNeedsApproval): ?>
            <div class="alert alert-warning mb-4 rounded-3 shadow-sm">
                <i class="fas fa-user-clock me-2"></i><strong>Pending Verification:</strong> Your account is pending administrator approval.
            </div>
        <?php endif; ?>

        <!-- Welcome Banner -->
        <div class="card border-0 text-white mb-4 shadow-sm" style="background: linear-gradient(135deg, #1b5a56 0%, #4c8a89 100%); border-radius: 12px; padding: 1.5rem;">
            <h4 class="fw-bold mb-1 text-white" style="font-family: 'Quicksand', sans-serif;">Welcome back, <?php echo htmlspecialchars($fullname); ?>!</h4>
            <p class="mb-0 text-white-50 small">Track your filed blotter complaints, clearance requests, and stay updated on your local barangay services.</p>
        </div>

        <!-- Stat Cards -->
        <div class="row g-3 mb-4">
            <div class="col-12 col-md-4">
                <a href="modules/my_reports.php" class="text-decoration-none">
                    <article class="dashboard-analytics-card analytics-tone-notif h-100">
                        <div class="dashboard-analytics-head">
                            <span class="dashboard-analytics-label">My Blotters</span>
                            <span class="dashboard-analytics-icon"><i class="fas fa-folder-open"></i></span>
                        </div>
                        <div class="dashboard-analytics-value"><?php echo $myReports; ?></div>
                        <div class="dashboard-analytics-sub">Total filed complaints</div>
                    </article>
                </a>
            </div>
            <div class="col-12 col-md-4">
                <article class="dashboard-analytics-card analytics-tone-pending h-100">
                    <div class="dashboard-analytics-head">
                        <span class="dashboard-analytics-label">Active Cases</span>
                        <span class="dashboard-analytics-icon"><i class="fas fa-spinner"></i></span>
                    </div>
                    <div class="dashboard-analytics-value"><?php echo $activeCases; ?></div>
                    <div class="dashboard-analytics-sub">Currently in progress</div>
                </article>
            </div>
            <div class="col-12 col-md-4">
                <article class="dashboard-analytics-card analytics-tone-subs h-100">
                    <div class="dashboard-analytics-head">
                        <span class="dashboard-analytics-label">My Clearances</span>
                        <span class="dashboard-analytics-icon"><i class="fas fa-file-contract"></i></span>
                    </div>
                    <div class="dashboard-analytics-value"><?php echo $myClearances; ?></div>
                    <div class="dashboard-analytics-sub">Requested certificates</div>
                </article>
            </div>
        </div>

        <!-- Recent Reports -->
        <div class="card shadow-sm border-0">
            <div class="card-header bg-card fw-bold d-flex justify-content-between align-items-center">
                <span><i class="fas fa-history me-2 text-primary"></i>My Recent Blotter Complaints</span>
                <a href="modules/my_reports.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size: 0.875rem;">
                        <thead class="table-light">
                            <tr><th>Blotter No</th><th>Respondent</th><th>Incident Type</th><th>Date</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                            <?php if ($isBanned || $userNeedsApproval): ?>
                                <tr><td colspan="5" class="text-center text-muted py-4"><i class="fas fa-lock me-2"></i>Access locked</td></tr>
                            <?php elseif (empty($recentReports)): ?>
                                <tr><td colspan="5" class="text-center text-muted py-4"><i class="fas fa-info-circle me-2"></i>No blotter complaints filed yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($recentReports as $r):
                                    $status = htmlspecialchars($r['status'] ?? 'Pending');
                                    $sl = strtolower($status);
                                    $bc = 'bg-secondary';
                                    if (in_array($sl, ['resolved','closed','settled'])) $bc = 'bg-success';
                                    elseif (in_array($sl, ['ongoing','in progress','hearing','scheduled'])) $bc = 'bg-info text-dark';
                                    elseif (in_array($sl, ['new','pending'])) $bc = 'bg-warning text-dark';
                                ?>
                                <tr>
                                    <td class="fw-semibold text-primary"><?php echo htmlspecialchars($r['blotter_no'] ?? '—'); ?></td>
                                    <td><?php echo htmlspecialchars($r['respondent_name'] ?: 'Not Specified'); ?></td>
                                    <td><?php echo htmlspecialchars($r['incident_type'] ?? '—'); ?></td>
                                    <td><?php echo $r['incident_date'] ? date('M d, Y', strtotime($r['incident_date'])) : '—'; ?></td>
                                    <td><span class="badge <?php echo $bc; ?>"><?php echo $status; ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
