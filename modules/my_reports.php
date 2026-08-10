<?php
$base_url = '../';
require_once __DIR__ . '/../includes/user_auth.php';
$page_title = 'My Blotter Reports';

require_once __DIR__ . '/../config/db_connect.php';
if (!isset($pdo) || !($pdo instanceof PDO)) {
    $pdo = getDBConnection();
}

$userId = $_SESSION['user_id'] ?? $_SESSION['resident_user_id'] ?? 0;
$fullname = $_SESSION['fullname'] ?? $_SESSION['first_name'] ?? 'Resident';

// ── Single optimized query for account status ──
$isBanned = false;
$userApproved = true;
$bannedNotice = false;
try {
    $aStmt = $pdo->prepare("SELECT banned, admin_approved FROM signup WHERE user_id = ? LIMIT 1");
    $aStmt->execute([$userId]);
    $aRow = $aStmt->fetch(PDO::FETCH_ASSOC);
    $isBanned = !empty($aRow['banned']);
    $userApproved = !empty($aRow['admin_approved']) && (int)$aRow['admin_approved'] === 1;
    $bannedNotice = $isBanned || !$userApproved;
} catch (Exception $e) {}

// ── Fetch Blotters where user is complainant or creator ──
$myBlotters = [];
$blotterCount = 0;
$resolvedCount = 0;
$pendingCount = 0;

if (!$bannedNotice) {
    try {
        $stmt = $pdo->prepare("SELECT id, blotter_no, complainant_name, respondent_name, incident_type, incident_date, incident_time, location, hearing_date, hearing_time, hearing_location, status, created_at FROM blotters WHERE complainant_name LIKE ? OR created_by = ? ORDER BY id DESC LIMIT 50");
        $searchName = '%' . ($fullname) . '%';
        $stmt->execute([$searchName, $userId]);
        $myBlotters = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $blotterCount = count($myBlotters);

        foreach ($myBlotters as $b) {
            $s = strtolower($b['status'] ?? '');
            if (in_array($s, ['resolved', 'settled', 'closed'])) {
                $resolvedCount++;
            } else {
                $pendingCount++;
            }
        }
    } catch (Exception $e) {}
}

// ── Include layout AFTER all data fetching ──
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="main-content">
    <div class="content-container">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h1 class="h3 fw-bold mb-1" style="font-family: 'Quicksand', sans-serif;">My Blotter Reports</h1>
                <p class="text-secondary small mb-0">Track all your filed blotter complaints and dispute proceedings</p>
            </div>
            <div class="d-flex gap-2">
                <a href="blotter_create.php" class="btn btn-primary btn-sm shadow-sm">
                    <i class="fas fa-pen-nib me-1"></i> Create Blotter
                </a>
                <a href="user_profile.php" class="btn btn-outline-primary btn-sm">
                    <i class="fas fa-user-circle me-1"></i> My Profile
                </a>
            </div>
        </div>

        <?php if ($bannedNotice): ?>
            <div class="alert <?php echo $isBanned ? 'alert-danger' : 'alert-warning'; ?> rounded-3 shadow-sm mb-4">
                <i class="fas <?php echo $isBanned ? 'fa-ban' : 'fa-user-clock'; ?> me-2"></i>
                <strong><?php echo $isBanned ? 'Account Suspended' : 'Pending Verification'; ?>:</strong>
                <?php echo $isBanned
                    ? 'Your account has been suspended. Reports and services are locked until reinstatement.'
                    : 'Your account is pending administrator approval. Services will be available after verification.'; ?>
            </div>
        <?php else: ?>

        <!-- Stat Cards Row -->
        <div class="row g-3 mb-4">
            <div class="col-12 col-md-4">
                <article class="dashboard-analytics-card analytics-tone-notif h-100">
                    <div class="dashboard-analytics-head">
                        <span class="dashboard-analytics-label">Total Blotters</span>
                        <span class="dashboard-analytics-icon"><i class="fas fa-clipboard-list"></i></span>
                    </div>
                    <div class="dashboard-analytics-value"><?php echo $blotterCount; ?></div>
                    <div class="dashboard-analytics-sub">Complaints recorded</div>
                </article>
            </div>
            <div class="col-12 col-md-4">
                <article class="dashboard-analytics-card analytics-tone-pending h-100">
                    <div class="dashboard-analytics-head">
                        <span class="dashboard-analytics-label">Pending / In Progress</span>
                        <span class="dashboard-analytics-icon"><i class="fas fa-clock"></i></span>
                    </div>
                    <div class="dashboard-analytics-value"><?php echo $pendingCount; ?></div>
                    <div class="dashboard-analytics-sub">Awaiting action or hearing</div>
                </article>
            </div>
            <div class="col-12 col-md-4">
                <article class="dashboard-analytics-card analytics-tone-subs h-100">
                    <div class="dashboard-analytics-head">
                        <span class="dashboard-analytics-label">Resolved / Settled</span>
                        <span class="dashboard-analytics-icon"><i class="fas fa-check-circle"></i></span>
                    </div>
                    <div class="dashboard-analytics-value"><?php echo $resolvedCount; ?></div>
                    <div class="dashboard-analytics-sub">Completed cases</div>
                </article>
            </div>
        </div>

        <!-- Blotters Table Card -->
        <div class="card shadow-sm border-0">
            <div class="card-header bg-card fw-bold d-flex justify-content-between align-items-center py-3">
                <span><i class="fas fa-list-alt me-2 text-primary"></i>Filed Blotter Complaints (<?php echo $blotterCount; ?>)</span>
                <a href="blotter_create.php" class="btn btn-sm btn-primary"><i class="fas fa-plus me-1"></i>New Blotter</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size: 0.875rem;">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Blotter No</th>
                                <th>Respondent</th>
                                <th>Incident Type</th>
                                <th>Incident Date</th>
                                <th>Hearing / Status</th>
                                <th>Status</th>
                                <th class="pe-3 text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($myBlotters)): ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-5">
                                        <i class="fas fa-clipboard-check fa-2x mb-3 text-secondary d-block"></i>
                                        <p class="mb-1 fw-semibold">No blotter complaints filed yet</p>
                                        <small class="text-muted">When you file a complaint, it will appear here for tracking.</small>
                                        <br>
                                        <a href="blotter_create.php" class="btn btn-sm btn-primary mt-3">
                                            <i class="fas fa-pen-nib me-1"></i>File New Blotter
                                        </a>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($myBlotters as $b):
                                    $bStatus = htmlspecialchars($b['status'] ?? 'Pending');
                                    $bsl = strtolower($bStatus);
                                    $bBadge = 'bg-secondary';
                                    if (in_array($bsl, ['resolved', 'settled', 'closed'])) $bBadge = 'bg-success';
                                    elseif (in_array($bsl, ['pending', 'new'])) $bBadge = 'bg-warning text-dark';
                                    elseif (in_array($bsl, ['hearing', 'scheduled', 'under investigation'])) $bBadge = 'bg-info text-dark';

                                    $hasHearing = !empty($b['hearing_date']);
                                ?>
                                <tr>
                                    <td class="ps-3 fw-bold text-primary"><?php echo htmlspecialchars($b['blotter_no'] ?? '—'); ?></td>
                                    <td>
                                        <div class="fw-semibold text-dark"><?php echo htmlspecialchars($b['respondent_name'] ?: 'Not Specified'); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars(mb_strimwidth($b['location'] ?? '', 0, 25, '...')); ?></small>
                                    </td>
                                    <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($b['incident_type'] ?? 'Complaint'); ?></span></td>
                                    <td><?php echo $b['incident_date'] ? date('M d, Y', strtotime($b['incident_date'])) : '—'; ?></td>
                                    <td>
                                        <?php if ($hasHearing): ?>
                                            <span class="badge bg-info text-dark"><i class="fas fa-calendar-alt me-1"></i><?php echo date('M d', strtotime($b['hearing_date'])); ?> <?php echo $b['hearing_time'] ? date('g:i A', strtotime($b['hearing_time'])) : ''; ?></span>
                                        <?php else: ?>
                                            <span class="text-muted small">No hearing set</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge <?php echo $bBadge; ?>"><?php echo $bStatus; ?></span></td>
                                    <td class="pe-3 text-end">
                                        <a href="blotter_view.php?id=<?php echo intval($b['id']); ?>" class="btn btn-sm btn-outline-primary py-0 px-2" style="font-size: 0.8rem;">
                                            <i class="fas fa-eye me-1"></i>View
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

