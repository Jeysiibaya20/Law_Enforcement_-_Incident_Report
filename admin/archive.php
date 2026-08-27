<?php
/**
 * Archive & Data Retention Management Center
 * Enforces multi-year retention policies (5 to 10 years) before data deletion.
 */
require_once 'admin_auth.php';
if (!isset($pdo) || !($pdo instanceof PDO)) {
    $pdo = getDBConnection();
}

$base_url = '../';
$page_title = 'Archive & Data Retention';
require_once '../includes/audit_logger.php';

// Ensure schema migration for multi-year retention
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS system_retention_settings (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        default_retention_years INT NOT NULL DEFAULT 5,
        purge_requires_admin_override TINYINT(1) DEFAULT 1,
        auto_archive_resolved_days INT DEFAULT 365,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $chk = $pdo->query("SELECT COUNT(*) FROM system_retention_settings")->fetchColumn();
    if ($chk == 0) {
        $pdo->exec("INSERT INTO system_retention_settings (default_retention_years, purge_requires_admin_override, auto_archive_resolved_days) VALUES (5, 1, 365)");
    }

    // Add retention columns to blotters if not exist
    $chkBlotter = $pdo->query("SHOW COLUMNS FROM blotters LIKE 'retention_until'")->fetch();
    if (!$chkBlotter) {
        $pdo->exec("ALTER TABLE blotters 
            ADD COLUMN archived_at DATETIME NULL,
            ADD COLUMN retention_years INT DEFAULT 5,
            ADD COLUMN retention_until DATETIME NULL,
            ADD COLUMN archive_reason VARCHAR(255) NULL");
    }

    // Add retention columns to incidents if not exist
    $chkInc = $pdo->query("SHOW COLUMNS FROM incidents LIKE 'retention_until'")->fetch();
    if (!$chkInc) {
        $pdo->exec("ALTER TABLE incidents 
            ADD COLUMN archived_at DATETIME NULL,
            ADD COLUMN retention_years INT DEFAULT 5,
            ADD COLUMN retention_until DATETIME NULL,
            ADD COLUMN archive_reason VARCHAR(255) NULL");
    }
} catch (Exception $e) {
    error_log("Archive retention schema check notice: " . $e->getMessage());
}

// Fetch current retention settings
$retentionSetting = $pdo->query("SELECT * FROM system_retention_settings LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: [
    'default_retention_years' => 5,
    'purge_requires_admin_override' => 1,
    'auto_archive_resolved_days' => 365
];
$defaultRetentionYears = intval($retentionSetting['default_retention_years'] ?? 5);

// Handle POST actions (Restore, Update Policy, Purge with Protection)
$action = $_POST['action'] ?? '';
$flash = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'update_retention_policy') {
        $newYears = max(1, min(20, intval($_POST['default_retention_years'] ?? 5)));
        $stmt = $pdo->prepare("UPDATE system_retention_settings SET default_retention_years = ?, updated_at = NOW()");
        $stmt->execute([$newYears]);
        
        logAuditTrail('RETENTION_POLICY_UPDATE', 'Data Retention', 'GLOBAL', "Updated default archive retention to {$newYears} years.", 'SUCCESS', $pdo);
        $flash = ['type' => 'success', 'msg' => "Archive retention policy updated to {$newYears} Years."];
        $defaultRetentionYears = $newYears;
    } elseif ($action === 'restore_record') {
        $recordType = $_POST['record_type'] ?? 'blotter';
        $recordId = intval($_POST['record_id'] ?? 0);

        if ($recordType === 'blotter') {
            $stmt = $pdo->prepare("UPDATE blotters SET status = 'Under Investigation', archived_at = NULL, retention_until = NULL WHERE id = ?");
            $stmt->execute([$recordId]);
            logAuditTrail('ARCHIVE_RESTORE', 'Blotter Archive', "BLT-ID-{$recordId}", "Restored archived blotter back to active registry.", 'SUCCESS', $pdo);
            $flash = ['type' => 'success', 'msg' => "Blotter record restored to active registry successfully."];
        } elseif ($recordType === 'incident') {
            $stmt = $pdo->prepare("UPDATE incidents SET status = 'Under Review', archived_at = NULL, retention_until = NULL WHERE id = ?");
            $stmt->execute([$recordId]);
            logAuditTrail('ARCHIVE_RESTORE', 'Incident Archive', "INC-ID-{$recordId}", "Restored archived incident report back to active registry.", 'SUCCESS', $pdo);
            $flash = ['type' => 'success', 'msg' => "Incident record restored to active registry successfully."];
        }
    } elseif ($action === 'archive_record') {
        $recordType = $_POST['record_type'] ?? 'blotter';
        $recordId = intval($_POST['record_id'] ?? 0);
        $reason = trim($_POST['archive_reason'] ?? 'Routine case archival per barangay retention policy');
        $years = max(1, intval($_POST['retention_years'] ?? $defaultRetentionYears));

        if ($recordType === 'blotter') {
            $stmt = $pdo->prepare("UPDATE blotters SET 
                status = 'Archived', 
                archived_at = NOW(), 
                retention_years = ?, 
                retention_until = DATE_ADD(NOW(), INTERVAL ? YEAR),
                archive_reason = ? 
                WHERE id = ?");
            $stmt->execute([$years, $years, $reason, $recordId]);
            logAuditTrail('ARCHIVE_ENTRY', 'Blotter Archive', "BLT-ID-{$recordId}", "Archived blotter with {$years}-year retention policy.", 'SUCCESS', $pdo);
            $flash = ['type' => 'warning', 'msg' => "Blotter archived with {$years}-year retention protection."];
        }
    } elseif ($action === 'purge_record') {
        $recordType = $_POST['record_type'] ?? 'blotter';
        $recordId = intval($_POST['record_id'] ?? 0);
        $override = !empty($_POST['admin_override_confirm']);

        // Check retention status
        $isExpired = false;
        if ($recordType === 'blotter') {
            $row = $pdo->prepare("SELECT blotter_no, retention_until FROM blotters WHERE id = ?");
            $row->execute([$recordId]);
            $item = $row->fetch(PDO::FETCH_ASSOC);
            if ($item) {
                $isExpired = !empty($item['retention_until']) && strtotime($item['retention_until']) <= time();
                if ($isExpired || $override) {
                    $pdo->prepare("DELETE FROM blotters WHERE id = ?")->execute([$recordId]);
                    logAuditTrail('ARCHIVE_PURGE', 'Blotter Archive', $item['blotter_no'], "Permanently purged archived blotter. Override: " . ($override ? 'YES' : 'NO'), 'SUCCESS', $pdo);
                    $flash = ['type' => 'danger', 'msg' => "Record {$item['blotter_no']} permanently deleted."];
                } else {
                    $flash = ['type' => 'warning', 'msg' => "Deletion Blocked: Record is protected by multi-year retention policy until " . date('M d, Y', strtotime($item['retention_until']))];
                }
            }
        }
    }
}

// Fetch all archived records with retention countdown
$filter_type = trim($_GET['filter_type'] ?? '');
$search = trim($_GET['search'] ?? '');

$archivedBlotters = [];
$archivedIncidents = [];

try {
    $blotterSql = "SELECT id, 'blotter' AS rec_type, blotter_no AS ref_no, complainant_name AS party_1, respondent_name AS party_2, incident_type, incident_date, status, archived_at, retention_years, retention_until, archive_reason, created_at 
                   FROM blotters 
                   WHERE status = 'Archived'";
    if ($search !== '') {
        $blotterSql .= " AND (blotter_no LIKE :s OR complainant_name LIKE :s OR respondent_name LIKE :s OR incident_type LIKE :s)";
    }
    $blotterSql .= " ORDER BY archived_at DESC, id DESC";
    $bStmt = $pdo->prepare($blotterSql);
    if ($search !== '') $bStmt->bindValue(':s', "%$search%");
    $bStmt->execute();
    $archivedBlotters = $bStmt->fetchAll(PDO::FETCH_ASSOC);

    $incidentSql = "SELECT id, 'incident' AS rec_type, case_no AS ref_no, reporter_name AS party_1, suspect_name AS party_2, incident_type, incident_date, status, archived_at, retention_years, retention_until, archive_reason, created_at 
                     FROM incidents 
                     WHERE status = 'Archived'";
    if ($search !== '') {
        $incidentSql .= " AND (case_no LIKE :s OR reporter_name LIKE :s OR suspect_name LIKE :s OR incident_type LIKE :s)";
    }
    $incidentSql .= " ORDER BY archived_at DESC, id DESC";
    $iStmt = $pdo->prepare($incidentSql);
    if ($search !== '') $iStmt->bindValue(':s', "%$search%");
    $iStmt->execute();
    $archivedIncidents = $iStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Fetch archived error: " . $e->getMessage());
}

$allArchived = array_merge($archivedBlotters, $archivedIncidents);

// Summary counts
$totalArchived = count($allArchived);
$protectedCount = 0;
$eligibleForPurgeCount = 0;

foreach ($allArchived as &$row) {
    $archivedDate = !empty($row['archived_at']) ? strtotime($row['archived_at']) : strtotime($row['created_at']);
    $retYears = intval($row['retention_years'] ?: $defaultRetentionYears);
    
    if (empty($row['retention_until'])) {
        $retUntilTime = strtotime("+{$retYears} years", $archivedDate);
        $row['retention_until'] = date('Y-m-d H:i:s', $retUntilTime);
    } else {
        $retUntilTime = strtotime($row['retention_until']);
    }

    $now = time();
    $row['is_expired'] = ($retUntilTime <= $now);

    if ($row['is_expired']) {
        $eligibleForPurgeCount++;
        $row['countdown_label'] = 'Retention Expired (Eligible for Purge)';
        $row['countdown_class'] = 'bg-danger text-white';
    } else {
        $protectedCount++;
        $diffDays = ceil(($retUntilTime - $now) / 86400);
        $remYears = floor($diffDays / 365);
        $remDays = $diffDays % 365;
        $row['countdown_label'] = ($remYears > 0 ? "{$remYears}y " : "") . "{$remDays}d remaining";
        $row['countdown_class'] = 'bg-success text-white';
    }
}
unset($row);

require_once '../includes/header.php';
?>

<div class="main-content" style="background:#f8f9fa; min-height: 100vh;">
    <div class="content-container py-4">
        
        <!-- Header & Branding -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div class="d-flex align-items-center gap-3">
                <img src="../assets/css/tara.png" alt="Alertara Logo" style="height: 48px; width: auto;" class="rounded shadow-sm p-1 bg-white border">
                <div>
                    <h2 class="fw-bold mb-0 text-dark">Archive & Data Retention Management</h2>
                    <p class="text-muted mb-0 small">Enforce multi-year record retention policies and protect archived legal records from premature deletion.</p>
                </div>
            </div>
            <div>
                <button type="button" class="btn btn-outline-primary shadow-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#retentionPolicyModal">
                    <i class="bi bi-gear-fill me-1"></i> Retention Settings (<?= $defaultRetentionYears ?> Years)
                </button>
            </div>
        </div>

        <?php if ($flash): ?>
            <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show shadow-sm" role="alert">
                <i class="bi bi-info-circle-fill me-2"></i> <?= htmlspecialchars($flash['msg']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Stat Cards -->
        <div class="row g-3 mb-4">
            <div class="col-12 col-md-4">
                <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
                    <div class="card-body p-4 d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted fw-bold small text-uppercase">Total Archived Records</span>
                            <h2 class="fw-bold text-dark mb-0 mt-1"><?= $totalArchived ?></h2>
                            <small class="text-muted">Blotters & Incident logs in storage</small>
                        </div>
                        <div class="p-3 bg-primary-subtle text-primary rounded-circle fs-3">
                            <i class="bi bi-archive-fill"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-4">
                <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
                    <div class="card-body p-4 d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted fw-bold small text-uppercase">Protected by <?= $defaultRetentionYears ?>-Year Rule</span>
                            <h2 class="fw-bold text-success mb-0 mt-1"><?= $protectedCount ?></h2>
                            <small class="text-success fw-semibold"><i class="bi bi-shield-check me-1"></i>Protected from deletion</small>
                        </div>
                        <div class="p-3 bg-success-subtle text-success rounded-circle fs-3">
                            <i class="bi bi-shield-lock-fill"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-4">
                <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
                    <div class="card-body p-4 d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted fw-bold small text-uppercase">Eligible for Purge</span>
                            <h2 class="fw-bold text-danger mb-0 mt-1"><?= $eligibleForPurgeCount ?></h2>
                            <small class="text-muted">Retention expiry period reached</small>
                        </div>
                        <div class="p-3 bg-danger-subtle text-danger rounded-circle fs-3">
                            <i class="bi bi-trash3-fill"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Archive Table Card -->
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white">
            <div class="card-header bg-white py-3 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-safe2-fill text-primary fs-5"></i>
                    <h5 class="mb-0 fw-bold text-dark">Archived Registry</h5>
                    <span class="badge bg-secondary rounded-pill px-2.5"><?= count($allArchived) ?> Records</span>
                </div>
                <form method="GET" class="d-flex gap-2">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Search reference, name, type..." value="<?= htmlspecialchars($search) ?>">
                    <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-search"></i></button>
                    <?php if ($search !== ''): ?>
                        <a href="archive.php" class="btn btn-sm btn-outline-secondary">Reset</a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Record Type & Ref #</th>
                            <th>Parties / Complainant</th>
                            <th>Incident Type</th>
                            <th>Date Archived</th>
                            <th>Retention Expiry</th>
                            <th>Protection Status</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($allArchived)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="bi bi-archive display-6 d-block mb-2 text-secondary opacity-50"></i>
                                    No archived records found matching current criteria.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($allArchived as $item): ?>
                                <tr>
                                    <td class="ps-4">
                                        <?php if ($item['rec_type'] === 'blotter'): ?>
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle me-1">BLOTTER</span>
                                        <?php else: ?>
                                            <span class="badge bg-info-subtle text-info border border-info-subtle me-1">INCIDENT</span>
                                        <?php endif; ?>
                                        <strong class="text-dark"><?= htmlspecialchars($item['ref_no']) ?></strong>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-dark"><?= htmlspecialchars($item['party_1'] ?: 'N/A') ?></div>
                                        <?php if (!empty($item['party_2'])): ?>
                                            <small class="text-muted">vs. <?= htmlspecialchars($item['party_2']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border"><?= htmlspecialchars($item['incident_type'] ?: 'General') ?></span>
                                    </td>
                                    <td>
                                        <div class="small fw-semibold"><?= !empty($item['archived_at']) ? date('M d, Y', strtotime($item['archived_at'])) : date('M d, Y', strtotime($item['created_at'])) ?></div>
                                        <small class="text-muted"><?= intval($item['retention_years'] ?: $defaultRetentionYears) ?>-Yr Policy</small>
                                    </td>
                                    <td>
                                        <div class="small fw-bold text-dark"><?= date('M d, Y', strtotime($item['retention_until'])) ?></div>
                                        <small class="text-muted"><?= date('h:i A', strtotime($item['retention_until'])) ?></small>
                                    </td>
                                    <td>
                                        <span class="badge <?= $item['countdown_class'] ?> rounded-pill px-2.5 py-1">
                                            <?= $item['countdown_label'] ?>
                                        </span>
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="btn-group btn-group-sm">
                                            <!-- Restore Button -->
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Restore this record back to active registry?');">
                                                <input type="hidden" name="action" value="restore_record">
                                                <input type="hidden" name="record_type" value="<?= $item['rec_type'] ?>">
                                                <input type="hidden" name="record_id" value="<?= $item['id'] ?>">
                                                <button type="submit" class="btn btn-outline-success" title="Restore to Active Registry">
                                                    <i class="bi bi-arrow-counterclockwise me-1"></i>Restore
                                                </button>
                                            </form>

                                            <!-- Purge Button with Policy Check -->
                                            <?php if ($item['is_expired']): ?>
                                                <form method="POST" class="d-inline ms-1" onsubmit="return confirm('Retention period has expired. Permanently delete this record?');">
                                                    <input type="hidden" name="action" value="purge_record">
                                                    <input type="hidden" name="record_type" value="<?= $item['rec_type'] ?>">
                                                    <input type="hidden" name="record_id" value="<?= $item['id'] ?>">
                                                    <button type="submit" class="btn btn-outline-danger" title="Permanently Purge Record">
                                                        <i class="bi bi-trash3"></i> Purge
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-outline-secondary ms-1" onclick="alert('Record is protected under the <?= intval($item['retention_years'] ?: $defaultRetentionYears) ?>-year retention policy until <?= date('M d, Y', strtotime($item['retention_until'])) ?>. Deletion is blocked.');" title="Protected under multi-year retention">
                                                    <i class="bi bi-lock-fill text-muted"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
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

<!-- RETENTION POLICY MODAL -->
<div class="modal fade" id="retentionPolicyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content rounded-4 border-0 shadow">
            <form method="POST">
                <input type="hidden" name="action" value="update_retention_policy">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold"><i class="bi bi-shield-shaded me-2"></i>Data Retention Policy Settings</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="alert alert-info border-0 rounded-3">
                        <i class="bi bi-info-circle-fill me-2"></i>
                        <strong>Statutory Requirement:</strong> Philippine Barangay and Local Law Enforcement record regulations prescribe a minimum of <strong>5 to 10 years</strong> before records may be marked for destruction.
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Default Archive Retention Period</label>
                        <div class="input-group">
                            <input type="number" name="default_retention_years" class="form-control" min="1" max="20" value="<?= $defaultRetentionYears ?>" required>
                            <span class="input-group-text fw-bold">Years</span>
                        </div>
                        <small class="text-muted">Archived cases will be protected from deletion for this duration.</small>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold"><i class="bi bi-check2-circle me-1"></i>Save Retention Policy</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
