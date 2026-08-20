<?php
session_start();
require_once '../config/db_connect.php';
if (!isset($pdo) || !($pdo instanceof PDO)) {
    $pdo = getDBConnection();
}
require_once '../includes/suspect_witness_management.php';

// Check authorization
$adminId = $_SESSION['admin_user_id'] ?? $_SESSION['user_id'] ?? null;
if (empty($adminId)) {
    header('Location: ../admin/login.php');
    exit();
}

$userStmt = $pdo->prepare("SELECT user_id, fullname, role, emailadd FROM signup WHERE user_id = ?");
$userStmt->execute([$adminId]);
$currentUser = $userStmt->fetch(PDO::FETCH_ASSOC);

$roleStr = strtolower(trim($currentUser['role'] ?? $_SESSION['admin_role'] ?? ''));
$isPrivileged = (strpos($roleStr, 'admin') !== false || strpos($roleStr, 'chief') !== false || strpos($roleStr, 'lead') !== false);

// Privacy Masking toggle state (defaults to masked for privacy protection)
$showUnmasked = isset($_GET['unmask']) && $_GET['unmask'] === '1' && $isPrivileged;

if ($showUnmasked) {
    logPrivacyAudit($pdo, $adminId, 'UNMASK_VIEW', 'ALL_RECORDS', null, 'Investigator toggled unmasked PII view');
} else {
    logPrivacyAudit($pdo, $adminId, 'VIEW_MASKED', 'ALL_RECORDS', null, 'Viewed protected suspect & witness list');
}

// Handle Form Submissions
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['form_action'] ?? '';

    if ($action === 'create_suspect') {
        $photoPath = null;
        if (isset($_FILES['photo']) && !empty($_FILES['photo']['name'])) {
            $uDir = '../uploads/suspects/';
            if (!is_dir($uDir)) @mkdir($uDir, 0755, true);
            $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            $filename = 'suspect_' . time() . '_' . rand(100, 999) . '.' . $ext;
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $uDir . $filename)) {
                $photoPath = 'uploads/suspects/' . $filename;
            }
        }

        $res = createSuspect([
            'case_id' => $_POST['case_id'] ?: null,
            'case_number' => $_POST['case_number'] ?: null,
            'first_name' => trim($_POST['first_name']),
            'middle_name' => trim($_POST['middle_name'] ?? ''),
            'last_name' => trim($_POST['last_name']),
            'age' => intval($_POST['age'] ?? 0),
            'date_of_birth' => $_POST['date_of_birth'] ?: null,
            'gender' => $_POST['gender'] ?? 'Male',
            'address' => trim($_POST['address'] ?? ''),
            'barangay' => trim($_POST['barangay'] ?? ''),
            'city' => trim($_POST['city'] ?? 'Quezon City'),
            'province' => trim($_POST['province'] ?? 'Metro Manila'),
            'contact_number' => trim($_POST['contact_number'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'id_type' => $_POST['id_type'] ?? '',
            'id_number' => trim($_POST['id_number'] ?? ''),
            'physical_description' => trim($_POST['physical_description'] ?? ''),
            'known_aliases' => trim($_POST['known_aliases'] ?? ''),
            'criminal_history' => trim($_POST['criminal_history'] ?? ''),
            'remarks' => trim($_POST['remarks'] ?? ''),
            'status' => $_POST['status'] ?? 'Active',
            'photo_path' => $photoPath,
            'created_by' => $adminId
        ]);

        if ($res['success']) {
            logPrivacyAudit($pdo, $adminId, 'CREATE_SUSPECT', 'suspects', $res['suspect_id'], 'New suspect record registered');
            $message = 'Suspect record created successfully with strict data privacy protection!';
            $message_type = 'success';
        } else {
            $message = 'Error creating suspect: ' . ($res['error'] ?? 'Unknown error');
            $message_type = 'danger';
        }
    }

    if ($action === 'create_witness') {
        $res = createWitness([
            'case_id' => $_POST['case_id'] ?: null,
            'case_number' => $_POST['case_number'] ?: null,
            'first_name' => trim($_POST['first_name']),
            'middle_name' => trim($_POST['middle_name'] ?? ''),
            'last_name' => trim($_POST['last_name']),
            'age' => intval($_POST['age'] ?? 0),
            'date_of_birth' => $_POST['date_of_birth'] ?: null,
            'gender' => $_POST['gender'] ?? 'Male',
            'address' => trim($_POST['address'] ?? ''),
            'barangay' => trim($_POST['barangay'] ?? ''),
            'city' => trim($_POST['city'] ?? 'Quezon City'),
            'province' => trim($_POST['province'] ?? 'Metro Manila'),
            'contact_number' => trim($_POST['contact_number'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'id_type' => $_POST['id_type'] ?? '',
            'id_number' => trim($_POST['id_number'] ?? ''),
            'relationship_to_case' => trim($_POST['relationship_to_case'] ?? 'Witness'),
            'witness_type' => $_POST['witness_type'] ?? 'Direct Witness',
            'statement' => trim($_POST['statement'] ?? ''),
            'reliability' => $_POST['reliability'] ?? 'High',
            'available_for_court' => !empty($_POST['available_for_court']) ? 1 : 0,
            'protection_needed' => !empty($_POST['protection_needed']) ? 1 : 0,
            'remarks' => trim($_POST['remarks'] ?? ''),
            'created_by' => $adminId
        ]);

        if ($res['success']) {
            logPrivacyAudit($pdo, $adminId, 'CREATE_WITNESS', 'witnesses', $res['witness_id'], 'New witness record registered');
            $message = 'Witness record created successfully and flagged under Witness Protection Rules!';
            $message_type = 'success';
        } else {
            $message = 'Error creating witness: ' . ($res['error'] ?? 'Unknown error');
            $message_type = 'danger';
        }
    }
}

// Fetch Cases for Dropdown
$casesStmt = $pdo->query("SELECT id, case_number, incident_type FROM case_assignments ORDER BY created_at DESC");
$cases = $casesStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Suspects and Witnesses
$suspects = getSuspectsByCase(null);
$witnessesStmt = $pdo->query("SELECT * FROM witnesses ORDER BY created_at DESC");
$witnesses = $witnessesStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Privacy Audit Logs
$privacyLogs = getPrivacyAuditLogs($pdo, 15);

$page_title = 'Suspect & Witness Management - Data Privacy Protected';
$base_url = '../';
require_once '../includes/header.php';
require_once '../includes/navbar.php';
?>

<div class="main-content">
    <div class="content-container py-4">
        
        <!-- Header & Action Row -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h1 class="h2 mb-1"><i class="fas fa-user-shield text-success me-2"></i>Suspect & Witness Management</h1>
                <p class="text-muted small mb-0">Confidential Law Enforcement Repository &bull; RA 10173 Data Privacy Act Protected</p>
            </div>
            <div class="d-flex gap-2 align-items-center">
                <?php if ($isPrivileged): ?>
                    <?php if ($showUnmasked): ?>
                        <a href="Suspect&Witness.php" class="btn btn-sm btn-outline-warning fw-bold">
                            <i class="fas fa-eye-slash me-1"></i> Apply Privacy Masking
                        </a>
                    <?php else: ?>
                        <a href="Suspect&Witness.php?unmask=1" class="btn btn-sm btn-warning text-dark fw-bold" onclick="return confirm('Attention: Unmasking sensitive PII will be recorded in the Data Privacy Access Audit Log. Proceed?');">
                            <i class="fas fa-unlock me-1"></i> Unmask PII (Authorized Lead View)
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
                <button class="btn btn-sm btn-primary fw-bold" data-bs-toggle="modal" data-bs-target="#addSuspectModal">
                    <i class="fas fa-user-plus me-1"></i> Add Suspect
                </button>
                <button class="btn btn-sm btn-info text-white fw-bold" data-bs-toggle="modal" data-bs-target="#addWitnessModal">
                    <i class="fas fa-eye me-1"></i> Add Witness
                </button>
            </div>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i> <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Data Privacy Compliance Banner -->
        <div class="card mb-4 border-start border-success border-4 shadow-sm">
            <div class="card-body py-3">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="d-flex align-items-center">
                        <div class="bg-success text-white p-2 rounded-circle me-3">
                            <i class="fas fa-shield-alt fa-lg"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold text-dark mb-0">Data Privacy & Confidentiality Engine Active</h6>
                            <p class="text-muted small mb-0">
                                Mode: <strong><?= $showUnmasked ? '<span class="text-danger">UNMASKED (Audited Session)</span>' : '<span class="text-success">MASKED (Default PII Protection)</span>' ?></strong> 
                                &bull; Sensitive names, contact channels, and addresses are protected under RA 10173 standards.
                            </p>
                        </div>
                    </div>
                    <div>
                        <a href="../admin/suspects&witnesses.php" class="btn btn-xs btn-outline-secondary py-1 px-2 fw-semibold">
                            <i class="fas fa-book me-1"></i> View Privacy Policy
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Metrics KPI Cards -->
        <div class="row g-3 mb-4">
            <div class="col-12 col-sm-6 col-xl-3">
                <article class="dashboard-analytics-card analytics-tone-danger h-100">
                    <div class="dashboard-analytics-head">
                        <span class="dashboard-analytics-label">Active Suspects</span>
                        <span class="dashboard-analytics-icon"><i class="fas fa-user-ninja"></i></span>
                    </div>
                    <div class="dashboard-analytics-value"><?= count($suspects) ?></div>
                    <div class="dashboard-analytics-sub">Persons of interest on record</div>
                </article>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <article class="dashboard-analytics-card analytics-tone-info h-100">
                    <div class="dashboard-analytics-head">
                        <span class="dashboard-analytics-label">Registered Witnesses</span>
                        <span class="dashboard-analytics-icon"><i class="fas fa-eye"></i></span>
                    </div>
                    <div class="dashboard-analytics-value"><?= count($witnesses) ?></div>
                    <div class="dashboard-analytics-sub">Testimony & sworn statements</div>
                </article>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <article class="dashboard-analytics-card analytics-tone-subs h-100">
                    <div class="dashboard-analytics-head">
                        <span class="dashboard-analytics-label">Protected Witnesses</span>
                        <span class="dashboard-analytics-icon"><i class="fas fa-user-shield"></i></span>
                    </div>
                    <div class="dashboard-analytics-value"><?= count(array_filter($witnesses, fn($w) => !empty($w['protection_needed']))) ?></div>
                    <div class="dashboard-analytics-sub">High-confidentiality protective custody</div>
                </article>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <article class="dashboard-analytics-card analytics-tone-pending h-100">
                    <div class="dashboard-analytics-head">
                        <span class="dashboard-analytics-label">Privacy Access Logs</span>
                        <span class="dashboard-analytics-icon"><i class="fas fa-history"></i></span>
                    </div>
                    <div class="dashboard-analytics-value"><?= count($privacyLogs) ?></div>
                    <div class="dashboard-analytics-sub">Audit trail records recorded</div>
                </article>
            </div>
        </div>

        <!-- Main Navigation Tabs -->
        <ul class="nav nav-tabs nav-fill mb-4 bg-light p-1 rounded border" id="swTabs" role="tablist">
            <li class="nav-item">
                <button class="nav-link active fw-bold" id="suspects-tab" data-bs-toggle="tab" data-bs-target="#suspectsPane" type="button">
                    <i class="fas fa-user-ninja text-danger me-1"></i> Suspect Records (<?= count($suspects) ?>)
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link fw-bold" id="witnesses-tab" data-bs-toggle="tab" data-bs-target="#witnessesPane" type="button">
                    <i class="fas fa-eye text-info me-1"></i> Witness Records (<?= count($witnesses) ?>)
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link fw-bold" id="audit-tab" data-bs-toggle="tab" data-bs-target="#auditPane" type="button">
                    <i class="fas fa-user-shield text-success me-1"></i> Data Privacy Access Audit Trail
                </button>
            </li>
        </ul>

        <div class="tab-content" id="swTabContent">
            <!-- Suspects Pane -->
            <div class="tab-pane fade show active" id="suspectsPane">
                <div class="card shadow-sm">
                    <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0 fw-bold"><i class="fas fa-list me-2 text-danger"></i>Suspects & Persons of Interest</h5>
                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addSuspectModal">
                            <i class="fas fa-plus-circle me-1"></i> New Suspect
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" style="font-size: 0.88rem;">
                                <thead class="table-light">
                                    <tr>
                                        <th>Photo</th>
                                        <th>Name (PII Protected)</th>
                                        <th>Aliases / Remarks</th>
                                        <th>Case #</th>
                                        <th>Contact Channel</th>
                                        <th>Address</th>
                                        <th>Status</th>
                                        <th>Registered</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($suspects)): ?>
                                        <tr><td colspan="8" class="text-center text-muted py-4">No suspect records found.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($suspects as $s): 
                                            $fullName = trim($s['first_name'] . ' ' . ($s['middle_name'] ? $s['middle_name'] . ' ' : '') . $s['last_name']);
                                            $displayName = $showUnmasked ? $fullName : maskPersonalInfo($fullName, 'name');
                                            $displayContact = $showUnmasked ? ($s['contact_number'] ?: 'N/A') : maskPersonalInfo($s['contact_number'], 'contact');
                                            $displayAddress = $showUnmasked ? ($s['address'] ?: 'Quezon City') : maskPersonalInfo($s['address'] ?: 'Quezon City', 'address');
                                        ?>
                                            <tr>
                                                <td style="width: 60px;">
                                                    <?php if (!empty($s['photo_path'])): ?>
                                                        <img src="../<?= htmlspecialchars($s['photo_path']) ?>" alt="Suspect" class="rounded-circle border" style="width: 42px; height: 42px; object-fit: cover; <?= $showUnmasked ? '' : 'filter: blur(2px);' ?>" title="<?= $showUnmasked ? 'Verified Mugshot' : 'Mugshot Privacy Blurred' ?>">
                                                    <?php else: ?>
                                                        <div class="bg-secondary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                                                            <i class="fas fa-user"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <strong><?= htmlspecialchars($displayName) ?></strong>
                                                    <div class="small text-muted">Gender: <?= htmlspecialchars($s['gender'] ?: 'Male') ?> &bull; Age: <?= intval($s['age'] ?: 0) ?></div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-light text-dark border"><?= htmlspecialchars($s['known_aliases'] ?: 'None') ?></span>
                                                    <div class="small text-truncate text-muted" style="max-width: 220px;"><?= htmlspecialchars($s['physical_description'] ?: 'No physical marks noted') ?></div>
                                                </td>
                                                <td><span class="badge bg-secondary"><?= htmlspecialchars($s['case_number'] ?: 'Unassigned') ?></span></td>
                                                <td><code><?= htmlspecialchars($displayContact) ?></code></td>
                                                <td><small class="text-muted"><?= htmlspecialchars($displayAddress) ?></small></td>
                                                <td>
                                                    <span class="badge bg-<?= match($s['status']) {
                                                        'Arrested' => 'success',
                                                        'Active', 'Wanted' => 'danger',
                                                        'Under Investigation' => 'warning',
                                                        default => 'secondary'
                                                    } ?>"><?= htmlspecialchars($s['status'] ?: 'Active') ?></span>
                                                </td>
                                                <td><small class="text-muted"><?= date('M d, Y', strtotime($s['created_at'])) ?></small></td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary py-0 px-2" onclick='viewSuspectDetail(<?= json_encode($s) ?>, <?= $showUnmasked ? "true" : "false" ?>)' title="View Full Details">
                                                        <i class="fas fa-eye me-1"></i> View
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Witnesses Pane -->
            <div class="tab-pane fade" id="witnessesPane">
                <div class="card shadow-sm">
                    <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0 fw-bold"><i class="fas fa-eye me-2 text-info"></i>Witness Records & Statements</h5>
                        <button class="btn btn-sm btn-info text-white" data-bs-toggle="modal" data-bs-target="#addWitnessModal">
                            <i class="fas fa-plus-circle me-1"></i> New Witness
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" style="font-size: 0.88rem;">
                                <thead class="table-light">
                                    <tr>
                                        <th>Name (PII Protected)</th>
                                        <th>Witness Type</th>
                                        <th>Case #</th>
                                        <th>Contact Channel</th>
                                        <th>Protection Status</th>
                                        <th>Reliability</th>
                                        <th>Statement Preview</th>
                                        <th>Logged</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($witnesses)): ?>
                                        <tr><td colspan="8" class="text-center text-muted py-4">No witness records found.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($witnesses as $w): 
                                            $isProtected = !empty($w['protection_needed']);
                                            $fullName = trim($w['first_name'] . ' ' . ($w['middle_name'] ? $w['middle_name'] . ' ' : '') . $w['last_name']);
                                            $displayName = ($isProtected && !$showUnmasked) ? 'Witness Ref #' . str_pad($w['id'], 4, '0', STR_PAD_LEFT) : ($showUnmasked ? $fullName : maskPersonalInfo($fullName, 'name'));
                                            $displayContact = $showUnmasked ? ($w['contact_number'] ?: 'N/A') : maskPersonalInfo($w['contact_number'], 'contact');
                                            $displayStmt = $showUnmasked ? ($w['statement'] ?: 'No statement recorded') : maskPersonalInfo($w['statement'], 'statement');
                                        ?>
                                            <tr>
                                                <td>
                                                    <strong><?= htmlspecialchars($displayName) ?></strong>
                                                    <?php if ($isProtected): ?>
                                                        <span class="badge bg-danger ms-1"><i class="fas fa-shield-alt me-1"></i>PROTECTED</span>
                                                    <?php endif; ?>
                                                    <div class="small text-muted">Rel: <?= htmlspecialchars($w['relationship_to_case'] ?: 'Witness') ?></div>
                                                </td>
                                                <td><span class="badge bg-info text-dark"><?= htmlspecialchars($w['witness_type'] ?: 'Direct Witness') ?></span></td>
                                                <td><span class="badge bg-secondary"><?= htmlspecialchars($w['case_number'] ?: 'Unassigned') ?></span></td>
                                                <td><code><?= htmlspecialchars($displayContact) ?></code></td>
                                                <td>
                                                    <?= $isProtected ? '<span class="badge bg-danger">Protective Custody</span>' : '<span class="badge bg-light text-dark border">Standard Witness</span>' ?>
                                                </td>
                                                <td><span class="badge bg-success"><?= htmlspecialchars($w['reliability'] ?: 'Medium') ?></span></td>
                                                <td><small class="text-muted text-truncate d-block" style="max-width: 250px;"><?= htmlspecialchars($displayStmt) ?></small></td>
                                                <td><small class="text-muted"><?= date('M d, Y', strtotime($w['created_at'])) ?></small></td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-info py-0 px-2" onclick='viewWitnessDetail(<?= json_encode($w) ?>, <?= $showUnmasked ? "true" : "false" ?>)' title="View Full Details">
                                                        <i class="fas fa-eye me-1"></i> View
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Privacy Audit Trail Pane -->
            <div class="tab-pane fade" id="auditPane">
                <div class="card shadow-sm">
                    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0 fw-bold"><i class="fas fa-history me-2 text-warning"></i>Data Privacy Access Audit Trail (`suspect_witness_privacy_audit`)</h5>
                        <span class="badge bg-warning text-dark"><i class="fas fa-lock me-1"></i>NPC / RA 10173 Monitored</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover align-middle mb-0" style="font-size: 0.85rem;">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Action Taken</th>
                                        <th>Target Type</th>
                                        <th>Performer</th>
                                        <th>IP Address</th>
                                        <th>Audit Log Details</th>
                                        <th>Timestamp</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($privacyLogs)): ?>
                                        <tr><td colspan="7" class="text-center text-muted py-4">No privacy audit logs recorded yet.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($privacyLogs as $pl): ?>
                                            <tr>
                                                <td class="fw-bold">#<?= htmlspecialchars($pl['id']) ?></td>
                                                <td>
                                                    <span class="badge bg-<?= match($pl['action']) {
                                                        'UNMASK_VIEW' => 'warning text-dark',
                                                        'CREATE_SUSPECT', 'CREATE_WITNESS' => 'success',
                                                        default => 'secondary'
                                                    } ?>"><?= htmlspecialchars($pl['action']) ?></span>
                                                </td>
                                                <td><code><?= htmlspecialchars($pl['target_type']) ?></code></td>
                                                <td><strong><?= htmlspecialchars($pl['performer_name']) ?></strong> (<?= htmlspecialchars($pl['user_role'] ?: 'Officer') ?>)</td>
                                                <td><code><?= htmlspecialchars($pl['ip_address']) ?></code></td>
                                                <td><?= htmlspecialchars($pl['details']) ?></td>
                                                <td><?= date('M d, Y g:i:s a', strtotime($pl['created_at'])) ?></td>
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

    </div>
</div>

<!-- Modal: Add Suspect -->
<div class="modal fade" id="addSuspectModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i>Register Suspect (Data Privacy Protected)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="form_action" value="create_suspect">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Related Case</label>
                            <select name="case_id" class="form-select" onchange="var opt=this.options[this.selectedIndex]; document.getElementById('sCaseNo').value=opt.getAttribute('data-caseno')||'';">
                                <option value="">Select Case (Optional)</option>
                                <?php foreach ($cases as $c): ?>
                                    <option value="<?= $c['id'] ?>" data-caseno="<?= htmlspecialchars($c['case_number']) ?>"><?= htmlspecialchars($c['case_number'] . ' - ' . $c['incident_type']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="hidden" name="case_number" id="sCaseNo">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Status</label>
                            <select name="status" class="form-select">
                                <option value="Active" selected>Active / Identified</option>
                                <option value="Wanted">Wanted / At Large</option>
                                <option value="Arrested">Arrested / In Custody</option>
                                <option value="Under Investigation">Under Investigation</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">First Name *</label>
                            <input type="text" name="first_name" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Middle Name</label>
                            <input type="text" name="middle_name" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Last Name *</label>
                            <input type="text" name="last_name" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Gender</label>
                            <select name="gender" class="form-select">
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Age</label>
                            <input type="number" name="age" class="form-control" min="1" max="120">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Known Aliases / Monikers</label>
                            <input type="text" name="known_aliases" class="form-control" placeholder="e.g. Alyas 'Boy Tattoo'">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Contact Number</label>
                            <input type="text" name="contact_number" class="form-control" placeholder="0917-xxx-xxxx">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Residential Address</label>
                            <input type="text" name="address" class="form-control" placeholder="House #, Street, Barangay, City">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Physical Description & Marks</label>
                            <textarea name="physical_description" class="form-control" rows="2" placeholder="Height, build, tattoos, scars, distinguishing facial features..."></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Upload Photo / Mugshot</label>
                            <input type="file" name="photo" class="form-control" accept="image/*">
                            <small class="text-muted">Mugshot will be stored in encrypted privacy directory and blurred on default views.</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger fw-bold"><i class="fas fa-save me-1"></i> Save Suspect Record</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Add Witness -->
<div class="modal fade" id="addWitnessModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="fas fa-eye me-2"></i>Register Witness (Witness Protection Rules)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="form_action" value="create_witness">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Related Case</label>
                            <select name="case_id" class="form-select" onchange="var opt=this.options[this.selectedIndex]; document.getElementById('wCaseNo').value=opt.getAttribute('data-caseno')||'';">
                                <option value="">Select Case (Optional)</option>
                                <?php foreach ($cases as $c): ?>
                                    <option value="<?= $c['id'] ?>" data-caseno="<?= htmlspecialchars($c['case_number']) ?>"><?= htmlspecialchars($c['case_number'] . ' - ' . $c['incident_type']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="hidden" name="case_number" id="wCaseNo">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Witness Type</label>
                            <select name="witness_type" class="form-select">
                                <option value="Direct Witness">Direct Eyewitness</option>
                                <option value="Corroborating Witness">Corroborating Witness</option>
                                <option value="Expert Witness">Forensic / Expert Witness</option>
                                <option value="Character Witness">Character Witness</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">First Name *</label>
                            <input type="text" name="first_name" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Middle Name</label>
                            <input type="text" name="middle_name" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Last Name *</label>
                            <input type="text" name="last_name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Contact Number</label>
                            <input type="text" name="contact_number" class="form-control" placeholder="0917-xxx-xxxx">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Residential Address</label>
                            <input type="text" name="address" class="form-control">
                        </div>
                        <div class="col-12">
                            <div class="form-check p-3 bg-light border rounded">
                                <input class="form-check-input ms-0 me-2" type="checkbox" name="protection_needed" id="protCheck" value="1">
                                <label class="form-check-label fw-bold text-danger" for="protCheck">
                                    <i class="fas fa-shield-alt me-1"></i> Flag for Witness Protection Program (Full Pseudonymization)
                                </label>
                                <p class="small text-muted mb-0 mt-1">Hides personal identifying information across public views; assigns confidential witness reference number.</p>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Sworn Statement / Testimonial Account *</label>
                            <textarea name="statement" class="form-control" rows="3" required placeholder="Record detailed sworn statement as provided by witness..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info text-white fw-bold"><i class="fas fa-save me-1"></i> Save Witness Record</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: View Suspect Detail -->
<div class="modal fade" id="viewSuspectDetailModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header" style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white;">
                <h5 class="modal-title"><i class="fas fa-user-ninja me-2"></i>Suspect Profile (Data Privacy Protected)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="suspectDetailBody">
                <div class="text-center py-4"><div class="spinner-border text-danger" role="status"></div></div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: View Witness Detail -->
<div class="modal fade" id="viewWitnessDetailModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header" style="background: linear-gradient(135deg, #0dcaf0 0%, #0bacce 100%); color: white;">
                <h5 class="modal-title"><i class="fas fa-eye me-2"></i>Witness Profile (Privacy Protected)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="witnessDetailBody">
                <div class="text-center py-4"><div class="spinner-border text-info" role="status"></div></div>
            </div>
        </div>
    </div>
</div>

<script>
function maskPII(text, type) {
    if (!text || text === '') return 'N/A';
    if (type === 'name') {
        return text.split(' ').map(w => w.length > 2 ? w[0] + '*'.repeat(w.length - 2) + w[w.length-1] : w).join(' ');
    }
    if (type === 'contact') {
        return text.length > 4 ? text.substring(0, 3) + '-***-' + text.slice(-4) : '***';
    }
    if (type === 'address') {
        return '[REDACTED], ' + (text.split(',').pop() || 'QC').trim();
    }
    if (type === 'statement') {
        return text.length > 40 ? text.substring(0, 40) + '... [CONFIDENTIAL]' : text;
    }
    return text;
}

function viewSuspectDetail(s, unmasked) {
    var body = document.getElementById('suspectDetailBody');
    var fn = (s.first_name || '') + ' ' + (s.middle_name ? s.middle_name + ' ' : '') + (s.last_name || '');
    var displayName = unmasked ? fn.trim() : maskPII(fn.trim(), 'name');
    var displayContact = unmasked ? (s.contact_number || 'N/A') : maskPII(s.contact_number, 'contact');
    var displayAddress = unmasked ? (s.address || 'N/A') : maskPII(s.address, 'address');

    var html = '<div class="row">';
    html += '<div class="col-md-4 text-center mb-3">';
    if (s.photo_path) {
        html += '<img src="../' + s.photo_path + '" alt="Suspect" class="rounded-circle border shadow-sm" style="width: 120px; height: 120px; object-fit: cover; ' + (unmasked ? '' : 'filter: blur(3px);') + '">';
    } else {
        html += '<div class="bg-secondary text-white rounded-circle d-inline-flex align-items-center justify-content-center mx-auto" style="width: 120px; height: 120px;"><i class="fas fa-user" style="font-size: 3rem;"></i></div>';
    }
    html += '<h5 class="mt-3 fw-bold">' + displayName + '</h5>';
    var statusColor = {'Arrested':'success','Active':'danger','Wanted':'danger','Under Investigation':'warning'};
    html += '<span class="badge bg-' + (statusColor[s.status] || 'secondary') + '">' + (s.status || 'Active') + '</span>';
    html += '</div>';
    html += '<div class="col-md-8">';
    html += '<div class="bg-light rounded p-3 mb-3">';
    html += '<h6 class="fw-bold text-danger mb-2"><i class="fas fa-id-card me-1"></i>Personal Information</h6>';
    html += '<div class="row g-2" style="font-size: 0.88rem;">';
    html += '<div class="col-6"><strong>Gender:</strong> ' + (s.gender || 'N/A') + '</div>';
    html += '<div class="col-6"><strong>Age:</strong> ' + (s.age || 'N/A') + '</div>';
    html += '<div class="col-6"><strong>Contact:</strong> <code>' + displayContact + '</code></div>';
    html += '<div class="col-6"><strong>Case #:</strong> <span class="badge bg-secondary">' + (s.case_number || 'Unassigned') + '</span></div>';
    html += '<div class="col-12"><strong>Address:</strong> <span class="text-muted">' + displayAddress + '</span></div>';
    html += '<div class="col-12"><strong>Aliases:</strong> ' + (s.known_aliases || 'None on record') + '</div>';
    html += '</div></div>';

    html += '<div class="bg-light rounded p-3">';
    html += '<h6 class="fw-bold text-dark mb-2"><i class="fas fa-fingerprint me-1"></i>Physical Description & Marks</h6>';
    html += '<p class="small text-muted mb-1">' + (s.physical_description || 'No distinguishing features on record.') + '</p>';
    if (s.criminal_history) {
        html += '<h6 class="fw-bold text-danger mt-2 mb-1"><i class="fas fa-exclamation-triangle me-1"></i>Criminal History</h6>';
        html += '<p class="small text-muted mb-0">' + s.criminal_history + '</p>';
    }
    if (s.remarks) {
        html += '<h6 class="fw-bold mt-2 mb-1"><i class="fas fa-sticky-note me-1"></i>Remarks</h6>';
        html += '<p class="small text-muted mb-0">' + s.remarks + '</p>';
    }
    html += '</div>';
    html += '</div></div>';

    html += '<div class="alert alert-' + (unmasked ? 'danger' : 'success') + ' py-2 small mt-3 mb-0">';
    html += '<i class="fas fa-shield-alt me-1"></i> Data Privacy: <strong>' + (unmasked ? 'UNMASKED (Audited Session — RA 10173 Logged)' : 'MASKED (Default PII Protection Active)') + '</strong>';
    html += '</div>';

    body.innerHTML = html;
    new bootstrap.Modal(document.getElementById('viewSuspectDetailModal')).show();
}

function viewWitnessDetail(w, unmasked) {
    var body = document.getElementById('witnessDetailBody');
    var fn = (w.first_name || '') + ' ' + (w.middle_name ? w.middle_name + ' ' : '') + (w.last_name || '');
    var isProtected = !!w.protection_needed;
    var displayName = (isProtected && !unmasked) ? 'Witness Ref #' + String(w.id).padStart(4, '0') : (unmasked ? fn.trim() : maskPII(fn.trim(), 'name'));
    var displayContact = unmasked ? (w.contact_number || 'N/A') : maskPII(w.contact_number, 'contact');
    var displayAddress = unmasked ? (w.address || 'N/A') : maskPII(w.address, 'address');
    var displayStatement = unmasked ? (w.statement || 'No statement recorded') : maskPII(w.statement, 'statement');

    var html = '<div class="row">';
    html += '<div class="col-md-4 text-center mb-3">';
    html += '<div class="bg-info bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mx-auto" style="width: 100px; height: 100px;"><i class="fas fa-eye text-info" style="font-size: 2.5rem;"></i></div>';
    html += '<h5 class="mt-3 fw-bold">' + displayName + '</h5>';
    if (isProtected) {
        html += '<span class="badge bg-danger"><i class="fas fa-shield-alt me-1"></i>PROTECTED WITNESS</span> ';
    }
    html += '<span class="badge bg-info text-dark">' + (w.witness_type || 'Direct Witness') + '</span>';
    html += '</div>';

    html += '<div class="col-md-8">';
    html += '<div class="bg-light rounded p-3 mb-3">';
    html += '<h6 class="fw-bold text-info mb-2"><i class="fas fa-id-card me-1"></i>Witness Information</h6>';
    html += '<div class="row g-2" style="font-size: 0.88rem;">';
    html += '<div class="col-6"><strong>Gender:</strong> ' + (w.gender || 'N/A') + '</div>';
    html += '<div class="col-6"><strong>Age:</strong> ' + (w.age || 'N/A') + '</div>';
    html += '<div class="col-6"><strong>Contact:</strong> <code>' + displayContact + '</code></div>';
    html += '<div class="col-6"><strong>Case #:</strong> <span class="badge bg-secondary">' + (w.case_number || 'Unassigned') + '</span></div>';
    html += '<div class="col-12"><strong>Address:</strong> <span class="text-muted">' + displayAddress + '</span></div>';
    html += '<div class="col-6"><strong>Relationship:</strong> ' + (w.relationship_to_case || 'Witness') + '</div>';
    html += '<div class="col-6"><strong>Reliability:</strong> <span class="badge bg-success">' + (w.reliability || 'Medium') + '</span></div>';
    html += '</div></div>';

    html += '<div class="bg-light rounded p-3">';
    html += '<h6 class="fw-bold text-dark mb-2"><i class="fas fa-comment-dots me-1"></i>Sworn Statement / Testimony</h6>';
    html += '<p class="small text-muted mb-0" style="white-space: pre-wrap;">' + displayStatement + '</p>';
    if (w.remarks) {
        html += '<h6 class="fw-bold mt-2 mb-1"><i class="fas fa-sticky-note me-1"></i>Remarks</h6>';
        html += '<p class="small text-muted mb-0">' + w.remarks + '</p>';
    }
    html += '</div>';
    html += '</div></div>';

    html += '<div class="alert alert-' + (unmasked ? 'danger' : 'success') + ' py-2 small mt-3 mb-0">';
    html += '<i class="fas fa-shield-alt me-1"></i> Data Privacy: <strong>' + (unmasked ? 'UNMASKED (Audited Session)' : 'MASKED (RA 10173 PII Protection Active)') + '</strong>';
    if (isProtected) {
        html += ' | <span class="text-danger fw-bold">WITNESS PROTECTION PROGRAM FLAGGED</span>';
    }
    html += '</div>';

    body.innerHTML = html;
    new bootstrap.Modal(document.getElementById('viewWitnessDetailModal')).show();
}
</script>

<?php require_once '../includes/footer.php'; ?>
