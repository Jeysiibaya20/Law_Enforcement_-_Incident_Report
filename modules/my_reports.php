<?php
$base_url = '../';
require_once __DIR__ . '/../includes/user_auth.php';
$page_title = 'My Reports & Blotters';

require_once __DIR__ . '/../config/db_connect.php';
if (!isset($pdo) || !($pdo instanceof PDO)) {
    $pdo = getDBConnection();
}

$userId = $_SESSION['user_id'];
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

// ── Fetch data only if account is active ──
$myIncidents = [];
$myBlotters = [];
$incidentCount = 0;
$blotterCount = 0;
$resolvedCount = 0;
$pendingCount = 0;

if (!$bannedNotice) {
    try {
        // Incidents filed by user
        $stmt = $pdo->prepare("SELECT id, case_no, incident_type, incident_date, status, location, created_at FROM incidents WHERE created_by = ? ORDER BY id DESC LIMIT 50");
        $stmt->execute([$userId]);
        $myIncidents = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $incidentCount = count($myIncidents);

        // Count by status
        foreach ($myIncidents as $inc) {
            $s = strtolower($inc['status'] ?? '');
            if (in_array($s, ['resolved', 'closed'])) $resolvedCount++;
            elseif (in_array($s, ['pending', 'new', 'open'])) $pendingCount++;
        }
    } catch (Exception $e) {}

    try {
        // Blotters where user is complainant
        $stmt2 = $pdo->prepare("SELECT id, blotter_no, respondent_name, incident_type, status, created_at FROM blotters WHERE complainant_name LIKE ? OR created_by = ? ORDER BY id DESC LIMIT 50");
        $searchName = '%' . ($fullname) . '%';
        $stmt2->execute([$searchName, $userId]);
        $myBlotters = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        $blotterCount = count($myBlotters);
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
                <h1 class="h3 fw-bold mb-1" style="font-family: 'Quicksand', sans-serif;">My Reports & Blotters</h1>
                <p class="text-secondary small mb-0">Track all your filed incident reports and blotter complaints</p>
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
            <div class="col-6 col-md-3">
                <div class="card h-100 border-start border-primary border-4 shadow-sm p-3">
                    <span class="text-muted small text-uppercase fw-bold">Incidents</span>
                    <div class="h3 fw-bold text-primary my-1"><?php echo $incidentCount; ?></div>
                    <small class="text-muted">Total filed</small>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card h-100 border-start border-info border-4 shadow-sm p-3">
                    <span class="text-muted small text-uppercase fw-bold">Blotters</span>
                    <div class="h3 fw-bold text-info my-1"><?php echo $blotterCount; ?></div>
                    <small class="text-muted">Complaints filed</small>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card h-100 border-start border-warning border-4 shadow-sm p-3">
                    <span class="text-muted small text-uppercase fw-bold">Pending</span>
                    <div class="h3 fw-bold text-warning my-1"><?php echo $pendingCount; ?></div>
                    <small class="text-muted">Awaiting action</small>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card h-100 border-start border-success border-4 shadow-sm p-3">
                    <span class="text-muted small text-uppercase fw-bold">Resolved</span>
                    <div class="h3 fw-bold text-success my-1"><?php echo $resolvedCount; ?></div>
                    <small class="text-muted">Completed cases</small>
                </div>
            </div>
        </div>

        <!-- Tab Navigation -->
        <ul class="nav nav-tabs mb-0" id="reportTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active fw-semibold" id="incidents-tab" data-bs-toggle="tab" data-bs-target="#incidents" type="button" role="tab">
                    <i class="fas fa-exclamation-triangle me-1"></i> Incident Reports <span class="badge bg-primary ms-1"><?php echo $incidentCount; ?></span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-semibold" id="blotters-tab" data-bs-toggle="tab" data-bs-target="#blotters" type="button" role="tab">
                    <i class="fas fa-clipboard-list me-1"></i> Blotter Complaints <span class="badge bg-info ms-1"><?php echo $blotterCount; ?></span>
                </button>
            </li>
        </ul>

        <div class="tab-content">
            <!-- Incidents Tab -->
            <div class="tab-pane fade show active" id="incidents" role="tabpanel">
                <div class="card border-top-0 rounded-top-0 shadow-sm">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" style="font-size: 0.875rem;">
                                <thead class="table-light">
                                    <tr>
                                        <th>Case No</th>
                                        <th>Incident Type</th>
                                        <th>Location</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                        <th>Filed</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($myIncidents)): ?>
                                        <tr><td colspan="6" class="text-center text-muted py-4">
                                            <i class="fas fa-info-circle me-2"></i>You have not filed any reports yet.
                                            <br><a href="blotter_create.php" class="btn btn-sm btn-primary mt-2"><i class="fas fa-pen-nib me-1"></i>Create Blotter</a>
                                        </td></tr>
                                    <?php else: ?>
                                        <?php foreach ($myIncidents as $r):
                                            $status = htmlspecialchars($r['status'] ?? 'Pending');
                                            $sl = strtolower($status);
                                            $badgeClass = 'bg-secondary';
                                            if (in_array($sl, ['resolved', 'closed'])) $badgeClass = 'bg-success';
                                            elseif (in_array($sl, ['ongoing', 'in progress', 'investigating'])) $badgeClass = 'bg-info';
                                            elseif (in_array($sl, ['new', 'pending', 'open'])) $badgeClass = 'bg-warning text-dark';
                                            elseif ($sl === 'rejected') $badgeClass = 'bg-danger';
                                        ?>
                                        <tr>
                                            <td class="fw-semibold"><?php echo htmlspecialchars($r['case_no'] ?? '—'); ?></td>
                                            <td><?php echo htmlspecialchars($r['incident_type'] ?? '—'); ?></td>
                                            <td class="text-muted small"><?php echo htmlspecialchars(mb_strimwidth($r['location'] ?? '—', 0, 30, '...')); ?></td>
                                            <td><?php echo $r['incident_date'] ? date('M d, Y', strtotime($r['incident_date'])) : '—'; ?></td>
                                            <td><span class="badge <?php echo $badgeClass; ?>"><?php echo $status; ?></span></td>
                                            <td class="text-muted small"><?php echo $r['created_at'] ? date('M d, g:i a', strtotime($r['created_at'])) : '—'; ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Blotters Tab -->
            <div class="tab-pane fade" id="blotters" role="tabpanel">
                <div class="card border-top-0 rounded-top-0 shadow-sm">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" style="font-size: 0.875rem;">
                                <thead class="table-light">
                                    <tr>
                                        <th>Blotter No</th>
                                        <th>Respondent</th>
                                        <th>Type</th>
                                        <th>Status</th>
                                        <th>Filed</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($myBlotters)): ?>
                                        <tr><td colspan="5" class="text-center text-muted py-4">
                                            <i class="fas fa-info-circle me-2"></i>No blotter complaints found under your name.
                                        </td></tr>
                                    <?php else: ?>
                                        <?php foreach ($myBlotters as $b):
                                            $bStatus = htmlspecialchars($b['status'] ?? 'Pending');
                                            $bsl = strtolower($bStatus);
                                            $bBadge = 'bg-secondary';
                                            if (in_array($bsl, ['resolved', 'settled', 'closed'])) $bBadge = 'bg-success';
                                            elseif (in_array($bsl, ['pending', 'new'])) $bBadge = 'bg-warning text-dark';
                                            elseif (in_array($bsl, ['hearing', 'scheduled'])) $bBadge = 'bg-info';
                                        ?>
                                        <tr>
                                            <td class="fw-semibold"><?php echo htmlspecialchars($b['blotter_no'] ?? '—'); ?></td>
                                            <td><?php echo htmlspecialchars($b['respondent_name'] ?? '—'); ?></td>
                                            <td><?php echo htmlspecialchars($b['incident_type'] ?? '—'); ?></td>
                                            <td><span class="badge <?php echo $bBadge; ?>"><?php echo $bStatus; ?></span></td>
                                            <td class="text-muted small"><?php echo $b['created_at'] ? date('M d, Y g:i a', strtotime($b['created_at'])) : '—'; ?></td>
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

        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
