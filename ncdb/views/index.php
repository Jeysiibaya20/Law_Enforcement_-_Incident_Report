<?php
/**
 * NCDB Verification Interface
 * Main page for verifying records against national crime databases
 */

session_start();
require_once '../../config/db_connect.php';
require_once '../config/ncdb_config.php';
require_once '../services/NCDatabaseService.php';
require_once '../services/DuplicateDetectionService.php';
require_once '../services/AccessAuditLogger.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../auth/login.php');
    exit;
}

// Check permission
$allowed_roles = ['Officer', 'Admin'];
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed_roles)) {
    echo "<div class='alert alert-danger'>Access Denied. This feature is only available for Officers and Administrators.</div>";
    require_once '../includes/footer.php';
    exit;
}

// Initialize services
$ncdb_service = new NCDatabaseService($pdo);
$duplicate_service = new DuplicateDetectionService($pdo);
$audit_logger = new AccessAuditLogger($pdo);

$page_title = 'NCDB Verification';
$base_url = '../../';

// Initialize variables
$verification_result = null;
$error_message = null;
$success_message = null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $record_type = $_POST['record_type'] ?? null;
        $record_id = intval($_POST['record_id'] ?? 0);
        $verification_type = $_POST['verification_type'] ?? 'IDENTITY_VERIFICATION';
        
        if (!$record_type || $record_id <= 0) {
            throw new Exception('Invalid record selection');
        }
        
        // Verify record against NCDB
        $verification_result = $ncdb_service->verifyRecord($record_type, $record_id, $verification_type);
        
        // Check for duplicates
        $duplicate_check = $duplicate_service->checkForDuplicates(
            $record_type,
            $verification_result['record']
        );
        
        $verification_result['duplicates'] = $duplicate_check;
        
        $success_message = 'Record verified successfully!';
        
        $audit_logger->logAccess(
            'VERIFY',
            $verification_type,
            ['record_type' => $record_type, 'record_id' => $record_id],
            count($verification_result['ncdb_matches']),
            null,
            'SUCCESS'
        );
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
        $audit_logger->logAccess(
            'VERIFY',
            $_POST['verification_type'] ?? 'UNKNOWN',
            ['record_type' => $record_type ?? null, 'record_id' => $record_id ?? null],
            null,
            null,
            'FAILED',
            $error_message
        );
    }
}

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';
?>

<div class="main-content">
    <div class="content-container">
        <h1 class="h2 mb-4">National Crime Database Verification</h1>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger alert-dismissible">
                <i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($error_message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success alert-dismissible">
                <i class="bi bi-check-circle"></i> <?= htmlspecialchars($success_message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <!-- Verification Form -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h5>Search and Verify Records</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="verification-form">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Record Type <span class="text-danger">*</span></label>
                                <select name="record_type" class="form-select" id="record_type" required onchange="loadRecords()">
                                    <option value="">-- Select Record Type --</option>
                                    <option value="BLOTTER">Blotter</option>
                                    <option value="CASE">Case</option>
                                    <option value="SUSPECT">Suspect</option>
                                    <option value="WITNESS">Witness</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Record <span class="text-danger">*</span></label>
                                <select name="record_id" class="form-select" id="record_id" required>
                                    <option value="">-- Select Record --</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Verification Type <span class="text-danger">*</span></label>
                                <select name="verification_type" class="form-select" required>
                                    <option value="IDENTITY_VERIFICATION">Identity Verification</option>
                                    <option value="CRIMINAL_HISTORY">Criminal History Check</option>
                                    <option value="WARRANT_CHECK">Warrant Check</option>
                                    <option value="CASE_LOOKUP">Case Lookup</option>
                                </select>
                                <small class="text-muted d-block mt-2">Select the type of verification to perform</small>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Search Options</label>
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" name="check_duplicates" id="check_duplicates" checked>
                                    <label class="form-check-label" for="check_duplicates">
                                        Check for duplicates
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" name="include_ncdb" id="include_ncdb" checked>
                                    <label class="form-check-label" for="include_ncdb">
                                        Include NCDB results
                                    </label>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary btn-lg w-100">
                                <i class="bi bi-search"></i> Verify Record
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h5>NCDB Statistics</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        try {
                            $dup_stats = $duplicate_service->getDuplicateStatistics();
                            $enabled_dbs = count(NCDBConfig::getEnabledDatabases());
                        } catch (Exception $e) {
                            $dup_stats = [];
                            $enabled_dbs = 0;
                        }
                        ?>
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="stat-box">
                                    <div class="stat-value"><?= $dup_stats['total_potential_duplicates'] ?? 0 ?></div>
                                    <div class="stat-label">Potential Duplicates</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="stat-box">
                                    <div class="stat-value"><?= $dup_stats['exact_matches'] ?? 0 ?></div>
                                    <div class="stat-label">Exact Matches</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="stat-box">
                                    <div class="stat-value"><?= $dup_stats['confirmed_duplicates'] ?? 0 ?></div>
                                    <div class="stat-label">Confirmed Duplicates</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="stat-box">
                                    <div class="stat-value"><?= $dup_stats['pending_review'] ?? 0 ?></div>
                                    <div class="stat-label">Pending Review</div>
                                </div>
                            </div>
                        </div>

                        <hr class="my-4">

                        <h6 class="mb-3">Connected Databases</h6>
                        <small class="text-muted">
                            <?php if ($enabled_dbs > 0): ?>
                                <span class="badge bg-success"><?= $enabled_dbs ?> Database(s) Enabled</span>
                            <?php else: ?>
                                <span class="badge bg-warning">No databases configured</span>
                            <?php endif; ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Verification Results -->
        <?php if (!empty($verification_result)): ?>
            <div class="row g-4 mt-4">
                <!-- Record Details -->
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h5>Record Details</h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm">
                                <tbody>
                                    <?php foreach ($verification_result['record'] as $key => $value): ?>
                                        <tr>
                                            <td class="fw-bold"><?= ucfirst(str_replace('_', ' ', $key)) ?></td>
                                            <td><?= htmlspecialchars(substr($value, 0, 100)) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- NCDB Matches -->
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h5>NCDB Matches (<?= count($verification_result['ncdb_matches']) ?>)</h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($verification_result['ncdb_matches'])): ?>
                                <div class="list-group">
                                    <?php foreach ($verification_result['ncdb_matches'] as $match): ?>
                                        <div class="list-group-item">
                                            <h6><?= htmlspecialchars($match['case_number'] ?? $match['id'] ?? 'Unknown') ?></h6>
                                            <p class="mb-1 small"><?= htmlspecialchars($match['case_title'] ?? $match['case_type'] ?? 'N/A') ?></p>
                                            <small class="text-muted">Status: <?= htmlspecialchars($match['status'] ?? 'Unknown') ?></small>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-muted">No matches found in NCDB</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Duplicates -->
                <?php if (!empty($verification_result['duplicates']['matches'])): ?>
                    <div class="col-12">
                        <div class="card border-warning">
                            <div class="card-header bg-warning-light">
                                <h5><i class="bi bi-exclamation-triangle"></i> Potential Duplicates Found</h5>
                            </div>
                            <div class="card-body">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Type</th>
                                            <th>Reference</th>
                                            <th>Confidence</th>
                                            <th>Score</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($verification_result['duplicates']['matches'] as $dup): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($dup['type']) ?></td>
                                                <td><?= htmlspecialchars($dup['ncdb_reference']) ?></td>
                                                <td>
                                                    <span class="badge bg-<?= $dup['confidence'] === 'HIGH' ? 'danger' : 'warning' ?>">
                                                        <?= htmlspecialchars($dup['confidence']) ?>
                                                    </span>
                                                </td>
                                                <td><?= round($dup['score'] * 100, 1) ?>%</td>
                                                <td>
                                                    <button class="btn btn-sm btn-danger" onclick="flagDuplicate(<?= $dup['ncdb_id'] ?>)">
                                                        <i class="bi bi-flag"></i> Flag
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<link rel="stylesheet" href="../css/style.css">

<script>
function loadRecords() {
    const recordType = document.getElementById('record_type').value;
    const recordSelect = document.getElementById('record_id');
    
    if (!recordType) {
        recordSelect.innerHTML = '<option value="">-- Select Record --</option>';
        return;
    }
    
    // AJAX to load records
    fetch('query.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=load_records&type=' + encodeURIComponent(recordType)
    })
    .then(response => response.json())
    .then(data => {
        let html = '<option value="">-- Select Record --</option>';
        if (data.records) {
            data.records.forEach(record => {
                html += `<option value="${record.id}">${record.label}</option>`;
            });
        }
        recordSelect.innerHTML = html;
    })
    .catch(error => console.error('Error:', error));
}

function flagDuplicate(recordId) {
    if (confirm('Mark this as a duplicate?')) {
        fetch('query.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=flag_duplicate&record_id=' + encodeURIComponent(recordId)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Duplicate flagged successfully');
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => console.error('Error:', error));
    }
}
</script>

<?php require_once '../../includes/footer.php'; ?>
