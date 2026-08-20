<?php
session_start();
require_once '../config/db_connect.php';
if (!isset($pdo) || !($pdo instanceof PDO)) {
    $pdo = getDBConnection();
}
require_once '../config/LanguageManager.php';
// Check if user is logged in and is admin/officer
$adminId = $_SESSION['admin_user_id'] ?? $_SESSION['user_id'] ?? null;
if (empty($adminId)) {
    header('Location: ../admin/login.php');
    exit();
}

// Check admin/officer role
$adminCheck = $pdo->prepare("SELECT role, fullname FROM signup WHERE user_id = ?");
$adminCheck->execute([$adminId]);
$userRole = $adminCheck->fetch(PDO::FETCH_ASSOC);

$roleStr = strtolower(trim($userRole['role'] ?? $_SESSION['admin_role'] ?? $_SESSION['role'] ?? ''));
if (strpos($roleStr, 'admin') === false && strpos($roleStr, 'officer') === false && strpos($roleStr, 'official') === false) {
    header('Location: ../admin/login.php');
    exit();
}

$_SESSION['user_id'] = $adminId;
$_SESSION['admin_user_id'] = $adminId;

$current_lang = LanguageManager::getCurrentLanguage();

function ensureEvidenceRecordColumns(PDO $pdo): void
{
    $columns = [
        'source_department' => 'VARCHAR(150) NULL AFTER location_found',
        'received_from' => 'VARCHAR(150) NULL AFTER source_department',
        'source_reference' => 'VARCHAR(100) NULL AFTER received_from',
    ];

    foreach ($columns as $column => $definition) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'evidence_records' AND COLUMN_NAME = ?");
        $stmt->execute([$column]);
        if ((int) $stmt->fetchColumn() === 0) {
            $pdo->exec("ALTER TABLE evidence_records ADD COLUMN {$column} {$definition}");
        }
    }
}
require_once '../includes/navbar.php';
try {
    ensureEvidenceRecordColumns($pdo);
} catch (Exception $e) {
    error_log('Evidence record columns check failed: ' . $e->getMessage());
}

// Handle form submission
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['create_evidence'])) {
            // Generate evidence number
            $evidence_number = 'EVD-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);

            // Get collector name
            $collector_stmt = $pdo->prepare("SELECT fullname FROM signup WHERE user_id = ?");
            $collector_stmt->execute([$_SESSION['user_id']]);
            $collector = $collector_stmt->fetch(PDO::FETCH_ASSOC);
            $collector_name = $collector ? $collector['fullname'] : 'Unknown';

            // Insert evidence record
            $insert_stmt = $pdo->prepare("\n                INSERT INTO evidence_records\n                (evidence_number, evidence_type, case_id, case_number, item_description,\n                 location_found, source_department, received_from, source_reference, collection_date, `condition`, storage_location,\n                 security_level, witness_name, witness_description, notes, collected_by, collector_name, status)\n                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Collected')\n            ");
            // Combine date and time for collection_date
            $collection_datetime = $_POST['collection_date'];
            if (!empty($_POST['collection_time'])) {
                $collection_datetime .= ' ' . $_POST['collection_time'] . ':00';
            } else {
                $collection_datetime .= ' 00:00:00';
            }

            $insert_stmt->execute([
                $evidence_number,
                $_POST['evidence_type'],
                $_POST['case_id'] ?: null,
                $_POST['case_number'] ?: null,
                $_POST['item_description'],
                $_POST['location_found'] ?: null,
                $_POST['source_department'] ?: null,
                $_POST['received_from'] ?: null,
                $_POST['source_reference'] ?: null,
                $collection_datetime,
                $_POST['condition'],
                $_POST['storage_location'],
                $_POST['security_level'],
                $_POST['witness_name'] ?: null,
                $_POST['witness_description'] ?: null,
                $_POST['notes'] ?: null,
                $_SESSION['user_id'],
                $collector_name
            ]);

            $evidence_id = $pdo->lastInsertId();

            // Handle file uploads
            if (isset($_FILES['evidence_files']) && !empty($_FILES['evidence_files']['name'][0])) {
                $upload_dir = '../uploads/evidence/';

                // Create directory if it doesn't exist
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }

                foreach ($_FILES['evidence_files']['tmp_name'] as $key => $tmp_name) {
                    if (!empty($tmp_name)) {
                        $original_name = $_FILES['evidence_files']['name'][$key];
                        $file_extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));

                        // Generate unique filename
                        $new_filename = 'evidence_' . $evidence_id . '_' . time() . '_' . $key . '.' . $file_extension;
                        $file_path = $upload_dir . $new_filename;

                        if (move_uploaded_file($tmp_name, $file_path)) {
                            // Insert attachment record
                            $attachment_stmt = $pdo->prepare("
                                INSERT INTO evidence_attachments
                                (evidence_id, original_filename, stored_filename, file_path, file_type, file_size, mime_type, uploaded_by)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                            ");

                            $attachment_stmt->execute([
                                $evidence_id,
                                $original_name,
                                $new_filename,
                                $file_path,
                                $_FILES['evidence_files']['type'][$key],
                                $_FILES['evidence_files']['size'][$key],
                                $_FILES['evidence_files']['type'][$key],
                                $_SESSION['user_id']
                            ]);
                        }
                    }
                }
            }

            // Add initial chain of custody entry
            $custody_stmt = $pdo->prepare("
                INSERT INTO chain_of_custody
                (evidence_id, action_type, action_date, location, purpose, notes, performed_by)
                VALUES (?, 'Collected', NOW(), ?, 'Initial collection', ?, ?)
            ");
            $custody_stmt->execute([
                $evidence_id,
                $_POST['storage_location'],
                'Evidence collected and stored',
                $_SESSION['user_id']
            ]);

            $message = "Evidence record created successfully with number: " . $evidence_number;
            $message_type = 'success';
        } elseif (isset($_POST['update_status'])) {
            // Update evidence status
            $stmt = $pdo->prepare("UPDATE evidence_records SET status = ? WHERE id = ?");
            $stmt->execute([$_POST['new_status'], $_POST['evidence_id']]);

            // Add chain of custody entry for status change
            $custody_stmt = $pdo->prepare("
                INSERT INTO chain_of_custody
                (evidence_id, action_type, action_date, location, purpose, notes, performed_by)
                VALUES (?, 'Status Changed', NOW(), ?, ?, ?, ?)
            ");
            $custody_stmt->execute([
                $_POST['evidence_id'],
                $_POST['location'] ?: 'System Update',
                'Status changed to ' . $_POST['new_status'],
                $_POST['status_notes'],
                $_SESSION['user_id']
            ]);

            $message = "Evidence status updated successfully";
            $message_type = 'success';
        } elseif (isset($_POST['add_custody_entry'])) {
            // Add manual chain of custody entry
            $custody_stmt = $pdo->prepare("
                INSERT INTO chain_of_custody
                (evidence_id, action_type, from_person_name, to_person_name, action_date,
                 location, purpose, notes, performed_by, witness_name)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $custody_stmt->execute([
                $_POST['evidence_id'],
                $_POST['action_type'],
                $_POST['from_person'],
                $_POST['to_person'],
                $_POST['action_date'] . ' ' . ($_POST['action_time'] ?: '00:00:00'),
                $_POST['location'],
                $_POST['purpose'],
                $_POST['custody_notes'],
                $_SESSION['user_id'],
                $_POST['witness']
            ]);

            $message = "Chain of custody entry added successfully";
            $message_type = 'success';
        } elseif (isset($_POST['request_group2_cctv'])) {
            require_once '../modules/OperationalModuleIntegrator.php';
            $integrator = new OperationalModuleIntegrator($pdo);

            $cctvData = [
                'case_number' => trim($_POST['case_number'] ?? ''),
                'incident_type' => trim($_POST['incident_type'] ?? 'Traffic Accident / Violation'),
                'camera_location' => trim($_POST['camera_location'] ?? 'Quezon City'),
                'incident_date' => $_POST['incident_date'] ?? date('Y-m-d'),
                'incident_time' => $_POST['incident_time'] ?? date('H:i:s'),
                'vehicle_plate' => trim($_POST['vehicle_plate'] ?? ''),
                'priority' => $_POST['priority'] ?? 'High',
                'reason' => trim($_POST['reason'] ?? 'Evidence collection for accident & incident investigation')
            ];

            $res = $integrator->dispatchCctvRequestToGroup2($cctvData);
            $message = "CCTV retrieval request successfully dispatched to Group 2 (Accident & Violation Reporting)! Request ID: " . htmlspecialchars($res['request_id']);
            $message_type = 'success';
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $message_type = 'error';
    }
}

// Get cases for dropdown
$cases_stmt = $pdo->query("SELECT id, case_number, incident_type, complainant_name FROM case_assignments ORDER BY created_at DESC");
$cases = $cases_stmt->fetchAll();

// Get evidence records (moved here so new records appear immediately)
$evidence_stmt = $pdo->query("
    SELECT e.*, COUNT(a.id) as attachment_count
    FROM evidence_records e
    LEFT JOIN evidence_attachments a ON e.id = a.evidence_id AND a.is_deleted = 0
    GROUP BY e.id
    ORDER BY e.created_at DESC
");
$evidence_records = $evidence_stmt->fetchAll();

// Fetch received CCTV evidence from Group 2
$receivedCctvEvidence = [];
try {
    $cctvStmt = $pdo->query("SELECT * FROM cctv_requests WHERE status LIKE '%Acknowledged%' OR status LIKE '%Dispatched%' OR acknowledged_at IS NOT NULL ORDER BY id DESC LIMIT 15");
    $receivedCctvEvidence = $cctvStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $receivedCctvEvidence = []; }

// Fetch received accident reports from Group 2
$receivedAccidents = [];
try {
    $accStmt = $pdo->query("SELECT * FROM received_accident_reports ORDER BY id DESC LIMIT 10");
    $receivedAccidents = $accStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $receivedAccidents = []; }

// Fetch recent integration logs for Group 7 dispatch
$group7DispatchLogs = [];
try {
    $g7Stmt = $pdo->query("SELECT * FROM external_integration_log WHERE direction LIKE '%group7%' ORDER BY id DESC LIMIT 10");
    $group7DispatchLogs = $g7Stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $group7DispatchLogs = []; }

$page_title = "Evidence Collection & Chain of Custody";
include '../includes/header.php';
?>

<style>
.integration-status-card {
    border-radius: 12px;
    padding: 18px 20px;
    transition: all 0.3s ease;
    border: 1px solid rgba(0,0,0,0.08);
    cursor: pointer;
}
.integration-status-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.12);
}
.integration-status-card .status-dot {
    width: 10px; height: 10px;
    border-radius: 50%;
    display: inline-block;
    animation: pulse-dot 1.5s infinite;
}
.integration-status-card .status-dot.connected { background: #28a745; }
.integration-status-card .status-dot.pending { background: #ffc107; }
@keyframes pulse-dot {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.5; transform: scale(1.3); }
}
.dept-badge {
    font-size: 0.72rem;
    padding: 3px 10px;
    border-radius: 50px;
    font-weight: 600;
    letter-spacing: 0.3px;
}
.action-btn-group .btn {
    padding: 3px 8px;
    font-size: 0.78rem;
    border-radius: 6px;
}
.modal-body .detail-row {
    padding: 8px 0;
    border-bottom: 1px solid #f0f0f0;
}
.modal-body .detail-row:last-child { border-bottom: none; }
.modal-body .detail-label {
    font-weight: 600;
    color: #495057;
    font-size: 0.85rem;
}
.modal-body .detail-value {
    color: #212529;
    font-size: 0.88rem;
}
.flow-arrow {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    font-size: 0.85rem;
    font-weight: 600;
    color: #6c757d;
    padding: 6px 0;
}
.flow-arrow i { font-size: 1.1rem; color: #0d6efd; }
</style>

        <!-- Main Content -->
        <div class="col-md-10 main-content">
            <div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
                <div>
                    <h2 class="h2 mb-2"><i class="bi bi-file-earmark-lock"></i> Evidence Collection & Chain of Custody</h2>
                    <p class="text-muted mb-0">Manage evidence records, send photos/videos to Group 7, and request CCTV from Group 2</p>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <button class="btn btn-outline-info fw-bold" data-bs-toggle="modal" data-bs-target="#receivedCctvEvidenceModal">
                        <i class="bi bi-inbox me-1"></i> Received Evidence <span class="badge bg-info text-white ms-1"><?= count($receivedCctvEvidence) ?></span>
                    </button>
                    <button class="btn btn-outline-success fw-bold" data-bs-toggle="modal" data-bs-target="#requestGroup2CctvModal">
                        <i class="bi bi-camera-video me-1"></i> Request CCTV (Group 2)
                    </button>
                    <button class="btn btn-primary fw-bold" data-bs-toggle="modal" data-bs-target="#createEvidenceModal">
                        <i class="bi bi-plus-circle me-1"></i> Create Evidence Record
                    </button>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
                    <i class="bi bi-<?php echo $message_type === 'success' ? 'check-circle-fill' : 'exclamation-triangle-fill'; ?> me-2"></i>
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Inter-Department Integration Status Cards -->
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="integration-status-card bg-white shadow-sm" data-bs-toggle="modal" data-bs-target="#receivedAccidentModal">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="dept-badge bg-warning text-dark">FROM GROUP 2</span>
                            <span class="status-dot <?= count($receivedAccidents) > 0 ? 'connected' : 'pending' ?>"></span>
                        </div>
                        <h6 class="fw-bold mb-1"><i class="bi bi-arrow-down-circle text-warning me-1"></i> Accident Ticket & Report</h6>
                        <p class="text-muted small mb-1">Data Received: <strong>Accident Ticket</strong>, <strong>Report</strong></p>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-success"><?= count($receivedAccidents) ?> received</span>
                            <small class="text-muted">Accident & Violation Reporting</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="integration-status-card bg-white shadow-sm" onclick="document.querySelector('#requestGroup2CctvModal .btn-close')?.click(); new bootstrap.Modal(document.getElementById('requestGroup2CctvModal')).show();">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="dept-badge bg-success text-white">TO/FROM GROUP 2</span>
                            <span class="status-dot <?= count($receivedCctvEvidence) > 0 ? 'connected' : 'pending' ?>"></span>
                        </div>
                        <h6 class="fw-bold mb-1"><i class="bi bi-camera-video text-success me-1"></i> CCTV Request & Evidence</h6>
                        <p class="text-muted small mb-1">Request CCTV → Group 2 Acknowledges → <strong>Receive Evidence, Photo, Video</strong></p>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-info"><?= count($receivedCctvEvidence) ?> requests</span>
                            <small class="text-muted">CCTV Surveillance Desk</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="integration-status-card bg-white shadow-sm" data-bs-toggle="modal" data-bs-target="#group7DispatchLogModal">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="dept-badge bg-primary text-white">TO GROUP 7</span>
                            <span class="status-dot <?= count($group7DispatchLogs) > 0 ? 'connected' : 'pending' ?>"></span>
                        </div>
                        <h6 class="fw-bold mb-1"><i class="bi bi-cloud-upload text-primary me-1"></i> Photo & Video Upload</h6>
                        <p class="text-muted small mb-1">Data Sent: <strong>Photo</strong>, <strong>Video</strong></p>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-primary"><?= count($group7DispatchLogs) ?> dispatched</span>
                            <small class="text-muted">Photo and Videos Upload</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Data Flow Visualization -->
            <div class="card shadow-sm mb-4 border-0" style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);">
                <div class="card-body py-3">
                    <div class="d-flex align-items-center justify-content-center gap-3 flex-wrap">
                        <span class="badge bg-warning text-dark px-3 py-2"><i class="bi bi-building me-1"></i> Group 2<br><small>Accident & Violation</small></span>
                        <div class="flow-arrow"><i class="bi bi-arrow-right-circle-fill"></i> Accident Ticket + Report</div>
                        <span class="badge bg-dark text-white px-3 py-2"><i class="bi bi-shield-check me-1"></i> Group 1<br><small>Law Enforcement (You)</small></span>
                        <div class="flow-arrow"><i class="bi bi-arrow-right-circle-fill"></i> Photo + Video</div>
                        <span class="badge bg-primary px-3 py-2"><i class="bi bi-cloud-upload me-1"></i> Group 7<br><small>Photo & Videos Upload</small></span>
                    </div>
                </div>
            </div>

            <!-- Evidence Records Table -->
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-file-earmark-lock me-1"></i> Evidence Records</h5>
                    <span class="badge bg-white text-primary"><?= count($evidence_records) ?> total records</span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Evidence #</th>
                                    <th>Type</th>
                                    <th>Case</th>
                                    <th>Description</th>
                                    <th>Source Dept.</th>
                                    <th>Status</th>
                                    <th>Collected By</th>
                                    <th>Date</th>
                                    <th>Files</th>
                                    <th style="min-width: 220px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($evidence_records)): ?>
                                    <tr><td colspan="10" class="text-center text-muted py-4"><i class="bi bi-inbox me-1"></i> No evidence records found. Create one to get started.</td></tr>
                                <?php else: ?>
                                <?php foreach ($evidence_records as $evidence): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($evidence['evidence_number']); ?></strong></td>
                                        <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($evidence['evidence_type']); ?></span></td>
                                        <td><?php echo htmlspecialchars($evidence['case_number'] ?: 'N/A'); ?></td>
                                        <td style="max-width: 200px;"><span class="text-truncate d-block" style="max-width: 200px;"><?php echo htmlspecialchars($evidence['item_description']); ?></span></td>
                                        <td><small><?php echo htmlspecialchars($evidence['source_department'] ?: 'N/A'); ?></small></td>
                                        <td>
                                            <span class="badge bg-<?php
                                                echo match($evidence['status']) {
                                                    'Collected' => 'primary',
                                                    'In Storage' => 'info',
                                                    'In Transit' => 'warning',
                                                    'Released' => 'success',
                                                    'Destroyed' => 'danger',
                                                    'Lost' => 'dark',
                                                    default => 'secondary'
                                                };
                                            ?>"><?php echo htmlspecialchars($evidence['status']); ?></span>
                                        </td>
                                        <td><small><?php echo htmlspecialchars($evidence['collector_name']); ?></small></td>
                                        <td><small><?php echo date('M d, Y', strtotime($evidence['collection_date'])); ?></small></td>
                                        <td><span class="badge bg-secondary"><?php echo $evidence['attachment_count']; ?> files</span></td>
                                        <td>
                                            <div class="action-btn-group d-flex gap-1 flex-wrap">
                                                <button class="btn btn-sm btn-outline-primary" onclick="viewEvidence(<?php echo $evidence['id']; ?>)" title="View Details">
                                                    <i class="bi bi-eye"></i> View
                                                </button>
                                                <button class="btn btn-sm btn-outline-secondary" onclick="viewChainOfCustody(<?php echo $evidence['id']; ?>)" title="Chain of Custody">
                                                    <i class="bi bi-link-45deg"></i> Chain
                                                </button>
                                                <?php if ($evidence['attachment_count'] > 0): ?>
                                                <button class="btn btn-sm btn-outline-success" onclick="openSendToGroup7Modal(<?= $evidence['id'] ?>, '<?= htmlspecialchars(addslashes($evidence['evidence_number'])) ?>', '<?= htmlspecialchars(addslashes($evidence['case_number'] ?: 'N/A')) ?>', <?= intval($evidence['attachment_count']) ?>)" title="Send Photos & Videos to Group 7">
                                                    <i class="bi bi-cloud-upload"></i> Group 7
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
    </div>
</div>

<!-- Create Evidence Modal -->
<div class="modal fade" id="createEvidenceModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Create Evidence Record</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Evidence Type *</label>
                                <select name="evidence_type" class="form-select" required>
                                    <option value="">Select Type</option>
                                        <option value="Physical">Physical</option>
                                        <option value="Biological">Biological (blood, tissue)</option>
                                        <option value="Forensic Sample">Forensic Sample (DNA, toxicology)</option>
                                        <option value="Trace">Trace Evidence (fibers, hair)</option>
                                        <option value="Fingerprint">Fingerprint / Latent Print</option>
                                        <option value="Weapon">Weapon / Tool</option>
                                        <option value="Controlled Substance">Controlled Substance</option>
                                        <option value="Currency">Currency</option>
                                        <option value="Digital">Digital / Electronic</option>
                                        <option value="Computer Forensics">Computer Forensics (PC, HDD)</option>
                                        <option value="Mobile Device">Mobile Device (phone, tablet)</option>
                                        <option value="Network Data">Network / Server Logs</option>
                                        <option value="Document">Document</option>
                                        <option value="Photo">Photo</option>
                                        <option value="Video">Video</option>
                                        <option value="Audio">Audio</option>
                                        <option value="Clothing">Clothing / Textile</option>
                                        <option value="Toolmark">Toolmark / Impression</option>
                                        <option value="Other">Other</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Related Case</label>
                                <select name="case_id" class="form-select" onchange="updateCaseNumber(this)">
                                    <option value="">Select Case (Optional)</option>
                                    <?php foreach ($cases as $case): ?>
                                        <option value="<?php echo $case['id']; ?>" data-case-number="<?php echo htmlspecialchars($case['case_number']); ?>">
                                            <?php echo htmlspecialchars($case['case_number'] . ' - ' . $case['incident_type']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="hidden" name="case_number" id="case_number">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Item Description *</label>
                        <textarea name="item_description" class="form-control" rows="3" required></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Witness Name (if any)</label>
                        <input type="text" name="witness_name" class="form-control" placeholder="Name of witness who described the evidence">
                        <small class="text-muted">Provide the witness who described the evidence (optional)</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Witness Description</label>
                        <textarea name="witness_description" class="form-control" rows="3" placeholder="Description as given by the witness"></textarea>
                        <small class="text-muted">Record the witness's account describing the evidence (optional)</small>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Location Found</label>
                                <input type="text" name="location_found" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Source Department</label>
                                <input type="text" name="source_department" class="form-control" placeholder="e.g., CCTV Department">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Received From</label>
                                <input type="text" name="received_from" class="form-control" placeholder="e.g., Officer / Desk / Unit">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Source Reference / Control No.</label>
                                <input type="text" name="source_reference" class="form-control" placeholder="e.g., CCTV Ticket No. / Request Slip">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Collection Date *</label>
                                <input type="date" name="collection_date" class="form-control" required value="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Collection Time</label>
                                <input type="time" name="collection_time" class="form-control">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Condition</label>
                                <select name="condition" class="form-select">
                                    <option value="Excellent">Excellent</option>
                                    <option value="Good" selected>Good</option>
                                    <option value="Fair">Fair</option>
                                    <option value="Poor">Poor</option>
                                    <option value="Damaged">Damaged</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Storage Location *</label>
                                <input type="text" name="storage_location" class="form-control" list="storageLocationOptions" required placeholder="e.g., Evidence Room A-1">
                                <datalist id="storageLocationOptions">
                                    <option value="Evidence Room A-1"></option>
                                    <option value="Evidence Room B-2"></option>
                                    <option value="Evidence Locker C-1"></option>
                                    <option value="Digital Evidence Vault"></option>
                                    <option value="Office Archive Cabinet"></option>
                                    <option value="Temporary Holding Area"></option>
                                </datalist>
                                <small class="text-muted">Files are stored in the evidence upload folder: uploads/evidence/</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Security Level</label>
                                <select name="security_level" class="form-select">
                                    <option value="Low">Low</option>
                                    <option value="Medium" selected>Medium</option>
                                    <option value="High">High</option>
                                    <option value="Confidential">Confidential</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><strong>Upload Files (Photos, Videos, Documents)</strong></label>
                        <div class="p-3 bg-light rounded">
                            <input type="file" name="evidence_files[]" class="form-control" multiple accept="image/*,video/*,audio/*,.pdf,.doc,.docx,.txt">
                            <small class="text-muted d-block mt-2">
                                <i class="bi bi-info-circle"></i>
                                Allowed: Images, Videos, Audio, PDF, Documents<br>
                                Multiple files can be selected
                            </small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="create_evidence" class="btn btn-primary">Create Evidence Record</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Evidence Modal -->
<div class="modal fade" id="viewEvidenceModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-eye"></i> Evidence Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="evidenceDetails">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>
</div>

<!-- Chain of Custody Modal -->
<div class="modal fade" id="chainOfCustodyModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-link"></i> Chain of Custody</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="chainOfCustodyDetails">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>
</div>

<!-- Request CCTV from Group 2 Modal -->
<div class="modal fade" id="requestGroup2CctvModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="bi bi-camera-video"></i> Request CCTV from Group 2 (Accident & Violation Reporting)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="request_group2_cctv" value="1">
                <div class="modal-body">
                    <div class="alert alert-info py-2 small">
                        <i class="bi bi-info-circle me-1"></i> Dispatches an official CCTV request to Group 2's Surveillance and Accident Unit. Group 2 will acknowledge and transmit fulfilled evidence, photos, and video recordings.
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Related Case Number</label>
                            <input type="text" name="case_number" class="form-control" placeholder="e.g. CASE-2026-0042">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Incident Type</label>
                            <input type="text" name="incident_type" class="form-control" value="Traffic Accident / Violation">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Camera Location / Intersection *</label>
                            <input type="text" name="camera_location" class="form-control" placeholder="e.g. Quezon Ave. cor. EDSA, Barangay Central" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Vehicle Plate / Description</label>
                            <input type="text" name="vehicle_plate" class="form-control" placeholder="e.g. ABC-1234 (Black Sedan)">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Incident Date</label>
                            <input type="date" name="incident_date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Estimated Time</label>
                            <input type="time" name="incident_time" class="form-control" value="<?php echo date('H:i'); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Urgency Priority</label>
                            <select name="priority" class="form-select">
                                <option value="High" selected>High (Accident / Hit & Run)</option>
                                <option value="Critical">Critical (Severe Casualties)</option>
                                <option value="Medium">Medium</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Specific Request Reason / Instructions *</label>
                            <textarea name="reason" class="form-control" rows="3" required placeholder="Describe vehicle movement, direction of travel, and camera angle requested..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success fw-bold"><i class="bi bi-send me-1"></i> Dispatch Request to Group 2</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Forward to Group 7 Confirmation Modal -->
<div class="modal fade" id="sendToGroup7ConfirmModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header" style="background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%); color: white;">
                <h5 class="modal-title"><i class="bi bi-cloud-upload me-2"></i>Forward Evidence to Group 7</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-3">
                    <div class="bg-primary bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 64px; height: 64px;">
                        <i class="bi bi-cloud-arrow-up" style="font-size: 2rem; color: #0d6efd;"></i>
                    </div>
                </div>
                <div class="alert alert-info py-2 small">
                    <i class="bi bi-info-circle me-1"></i> This will dispatch all photo and video attachments to <strong>Group 7 (Photo and Videos Upload / Inspection)</strong> via their cloud API.
                </div>
                <div class="bg-light rounded p-3 mb-3">
                    <div class="detail-row d-flex justify-content-between">
                        <span class="detail-label">Evidence Number:</span>
                        <span class="detail-value fw-bold" id="g7ConfirmEvidenceNo">—</span>
                    </div>
                    <div class="detail-row d-flex justify-content-between">
                        <span class="detail-label">Case Number:</span>
                        <span class="detail-value" id="g7ConfirmCaseNo">—</span>
                    </div>
                    <div class="detail-row d-flex justify-content-between">
                        <span class="detail-label">Attachments:</span>
                        <span class="detail-value" id="g7ConfirmAttachCount">—</span>
                    </div>
                </div>
                <div class="flow-arrow border rounded p-2 bg-white">
                    <span class="badge bg-dark"><i class="bi bi-shield-check me-1"></i>Group 1 (You)</span>
                    <i class="bi bi-arrow-right-circle-fill text-primary" style="font-size: 1.3rem;"></i>
                    <span class="badge bg-primary"><i class="bi bi-cloud-upload me-1"></i>Group 7 (Photo/Video)</span>
                </div>
                <div id="g7DispatchResult" class="mt-3" style="display: none;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary fw-bold" id="g7ConfirmSendBtn" onclick="confirmSendToGroup7()">
                    <i class="bi bi-send me-1"></i> Dispatch to Group 7
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Received CCTV Evidence from Group 2 Modal -->
<div class="modal fade" id="receivedCctvEvidenceModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content border-0 shadow">
            <div class="modal-header" style="background: linear-gradient(135deg, #198754 0%, #157347 100%); color: white;">
                <h5 class="modal-title"><i class="bi bi-inbox me-2"></i>Received Evidence & CCTV from Group 2</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-success py-2 small mb-3">
                    <i class="bi bi-check-circle me-1"></i> Evidence, photos, and videos received from <strong>Group 2 (Accident and Violation Reporting)</strong> after CCTV requests are acknowledged.
                </div>
                <?php if (empty($receivedCctvEvidence)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-inbox" style="font-size: 3rem; color: #ccc;"></i>
                        <p class="text-muted mt-2">No CCTV requests or received evidence yet.<br>Use "Request CCTV (Group 2)" to start.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-sm align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Camera Location</th>
                                    <th>Priority</th>
                                    <th>Status</th>
                                    <th>Acknowledged By</th>
                                    <th>Notes</th>
                                    <th>Evidence</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($receivedCctvEvidence as $cctv): ?>
                                <tr>
                                    <td><strong>#<?= $cctv['id'] ?></strong></td>
                                    <td><?= htmlspecialchars($cctv['camera_location'] ?: 'N/A') ?></td>
                                    <td><span class="badge bg-<?= ($cctv['priority'] ?? '') === 'Critical' ? 'danger' : (($cctv['priority'] ?? '') === 'High' ? 'warning text-dark' : 'secondary') ?>"><?= htmlspecialchars($cctv['priority'] ?? 'Normal') ?></span></td>
                                    <td><span class="badge bg-<?= strpos($cctv['status'], 'Acknowledged') !== false ? 'success' : 'info' ?>"><?= htmlspecialchars($cctv['status']) ?></span></td>
                                    <td><small><?= htmlspecialchars($cctv['acknowledged_by'] ?: 'Pending...') ?></small></td>
                                    <td><small class="text-muted"><?= htmlspecialchars(substr($cctv['acknowledgement_notes'] ?: $cctv['reason'] ?: '', 0, 60)) ?>...</small></td>
                                    <td>
                                        <?php if (!empty($cctv['fulfilled_photo_url'])): ?>
                                            <a href="<?= htmlspecialchars($cctv['fulfilled_photo_url']) ?>" target="_blank" class="btn btn-xs btn-outline-success py-0 px-1"><i class="bi bi-image"></i></a>
                                        <?php endif; ?>
                                        <?php if (!empty($cctv['fulfilled_video_url'])): ?>
                                            <a href="<?= htmlspecialchars($cctv['fulfilled_video_url']) ?>" target="_blank" class="btn btn-xs btn-outline-primary py-0 px-1"><i class="bi bi-camera-video"></i></a>
                                        <?php endif; ?>
                                        <?php if (empty($cctv['fulfilled_photo_url']) && empty($cctv['fulfilled_video_url'])): ?>
                                            <span class="text-muted small">Awaiting</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-info" onclick="viewCctvDetail(<?= $cctv['id'] ?>)" title="View Details">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- CCTV Detail View Modal -->
<div class="modal fade" id="cctvDetailModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="bi bi-camera-video me-2"></i>CCTV Request Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="cctvDetailBody">
                <div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></div>
            </div>
        </div>
    </div>
</div>

<!-- Received Accident Reports from Group 2 Modal -->
<div class="modal fade" id="receivedAccidentModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content border-0 shadow">
            <div class="modal-header" style="background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%); color: #212529;">
                <h5 class="modal-title"><i class="bi bi-exclamation-triangle me-2"></i>Received Accident Tickets & Reports from Group 2</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning py-2 small mb-3">
                    <i class="bi bi-info-circle me-1"></i> These accident tickets and reports were received from <strong>Group 2 (Accident & Violation Reporting)</strong> and automatically classified into the Incident Logging module.
                </div>
                <?php if (empty($receivedAccidents)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-inbox" style="font-size: 3rem; color: #ccc;"></i>
                        <p class="text-muted mt-2">No accident reports received yet from Group 2.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-sm align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Ticket #</th>
                                    <th>Violator</th>
                                    <th>Plate</th>
                                    <th>Violation</th>
                                    <th>Severity</th>
                                    <th>Location</th>
                                    <th>Fine</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($receivedAccidents as $acc): ?>
                                <tr>
                                    <td><strong>#<?= $acc['id'] ?></strong></td>
                                    <td><code><?= htmlspecialchars($acc['ticket_number'] ?: 'N/A') ?></code></td>
                                    <td><?= htmlspecialchars($acc['violator_name'] ?: 'Unknown') ?></td>
                                    <td><span class="badge bg-dark"><?= htmlspecialchars($acc['plate_number'] ?: 'N/A') ?></span></td>
                                    <td><small><?= htmlspecialchars(substr($acc['violation_type'] ?: '', 0, 40)) ?></small></td>
                                    <td><span class="badge bg-<?= ($acc['severity_level'] ?? '') === 'Critical' ? 'danger' : (($acc['severity_level'] ?? '') === 'High' ? 'warning text-dark' : 'secondary') ?>"><?= htmlspecialchars($acc['severity_level'] ?? 'Medium') ?></span></td>
                                    <td><small><?= htmlspecialchars(substr($acc['location'] ?: '', 0, 40)) ?></small></td>
                                    <td>₱<?= number_format($acc['fine_amount'] ?? 0, 2) ?></td>
                                    <td><span class="badge bg-success"><?= htmlspecialchars($acc['status'] ?? 'Logged') ?></span></td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-warning" onclick="viewAccidentDetail(<?= $acc['id'] ?>)">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Accident Detail View Modal -->
<div class="modal fade" id="accidentDetailModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title"><i class="bi bi-exclamation-triangle me-2"></i>Accident Report Detail</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="accidentDetailBody">
                <div class="text-center py-4"><div class="spinner-border text-warning" role="status"></div></div>
            </div>
        </div>
    </div>
</div>

<!-- Group 7 Dispatch History Modal -->
<div class="modal fade" id="group7DispatchLogModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header" style="background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%); color: white;">
                <h5 class="modal-title"><i class="bi bi-cloud-upload me-2"></i>Group 7 Photo & Video Dispatch History</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-primary py-2 small mb-3">
                    <i class="bi bi-info-circle me-1"></i> History of photos and videos dispatched to <strong>Group 7 (Photo and Videos Upload / Inspection)</strong>.
                </div>
                <?php if (empty($group7DispatchLogs)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-cloud-slash" style="font-size: 3rem; color: #ccc;"></i>
                        <p class="text-muted mt-2">No dispatches to Group 7 yet. Use the "Group 7" button on evidence records to send photos and videos.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-sm align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Direction</th>
                                    <th>Target</th>
                                    <th>Status</th>
                                    <th>Timestamp</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($group7DispatchLogs as $g7): ?>
                                <tr>
                                    <td>#<?= $g7['id'] ?></td>
                                    <td><span class="badge bg-primary">Outgoing</span></td>
                                    <td><small class="text-truncate d-block" style="max-width: 250px;"><?= htmlspecialchars($g7['target_url'] ?? '') ?></small></td>
                                    <td><span class="badge bg-<?= $g7['status'] === 'success' ? 'success' : 'info' ?>"><?= htmlspecialchars($g7['status']) ?></span></td>
                                    <td><small><?= date('M d, Y g:i a', strtotime($g7['created_at'])) ?></small></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
var _g7PendingEvidenceId = null;

function updateCaseNumber(select) {
    const selectedOption = select.options[select.selectedIndex];
    const caseNumber = selectedOption.getAttribute('data-case-number');
    document.getElementById('case_number').value = caseNumber || '';
}

function viewEvidence(evidenceId) {
    document.getElementById('evidenceDetails').innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div><p class="mt-2 text-muted">Loading evidence details...</p></div>';
    new bootstrap.Modal(document.getElementById('viewEvidenceModal')).show();
    fetch(`evidence_ajax.php?action=view&id=${evidenceId}`)
        .then(response => response.text())
        .then(data => {
            document.getElementById('evidenceDetails').innerHTML = data;
        });
}

function openSendToGroup7Modal(evidenceId, evidenceNo, caseNo, attachCount) {
    _g7PendingEvidenceId = evidenceId;
    document.getElementById('g7ConfirmEvidenceNo').textContent = evidenceNo;
    document.getElementById('g7ConfirmCaseNo').textContent = caseNo;
    document.getElementById('g7ConfirmAttachCount').textContent = attachCount + ' file(s)';
    document.getElementById('g7DispatchResult').style.display = 'none';
    document.getElementById('g7ConfirmSendBtn').disabled = false;
    document.getElementById('g7ConfirmSendBtn').innerHTML = '<i class="bi bi-send me-1"></i> Dispatch to Group 7';
    new bootstrap.Modal(document.getElementById('sendToGroup7ConfirmModal')).show();
}

function confirmSendToGroup7() {
    if (!_g7PendingEvidenceId) return;
    var btn = document.getElementById('g7ConfirmSendBtn');
    var resultDiv = document.getElementById('g7DispatchResult');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Dispatching...';
    resultDiv.style.display = 'block';
    resultDiv.className = 'alert alert-info py-2 small mt-3';
    resultDiv.innerHTML = '<i class="bi bi-arrow-repeat"></i> Sending photos & videos to Group 7...';

    fetch(`evidence_ajax.php?action=send_to_group7&id=${_g7PendingEvidenceId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                resultDiv.className = 'alert alert-success py-2 small mt-3';
                resultDiv.innerHTML = `<i class="bi bi-check-circle-fill me-1"></i> <strong>Successfully dispatched!</strong> ${data.photos_count} photo(s) and ${data.videos_count} video(s) sent to Group 7.`;
                btn.innerHTML = '<i class="bi bi-check-circle me-1"></i> Sent!';
                btn.classList.replace('btn-primary', 'btn-success');
            } else {
                resultDiv.className = 'alert alert-danger py-2 small mt-3';
                resultDiv.innerHTML = `<i class="bi bi-exclamation-circle me-1"></i> Error: ${data.error || 'Failed to dispatch'}`;
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-send me-1"></i> Retry';
            }
        })
        .catch(err => {
            resultDiv.className = 'alert alert-danger py-2 small mt-3';
            resultDiv.innerHTML = '<i class="bi bi-exclamation-circle me-1"></i> Network error. Check connection and try again.';
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-send me-1"></i> Retry';
        });
}

function forwardToGroup7(evidenceId) {
    var alertBox = document.getElementById(`group7StatusAlert_${evidenceId}`);
    if (alertBox) {
        alertBox.style.display = 'block';
        alertBox.className = 'alert alert-info py-1 px-2 small mb-2';
        alertBox.innerHTML = '<i class="bi bi-arrow-repeat spin"></i> Forwarding Photos & Videos to Group 7...';
    }

    fetch(`evidence_ajax.php?action=send_to_group7&id=${evidenceId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (alertBox) {
                    alertBox.className = 'alert alert-success py-1 px-2 small mb-2';
                    alertBox.innerHTML = `<i class="bi bi-check-circle-fill me-1"></i> Sent to Group 7! (${data.photos_count} photos, ${data.videos_count} videos)`;
                }
            } else {
                if (alertBox) {
                    alertBox.className = 'alert alert-danger py-1 px-2 small mb-2';
                    alertBox.innerHTML = `<i class="bi bi-exclamation-circle me-1"></i> Error: ${data.error || 'Failed to dispatch'}`;
                }
            }
        })
        .catch(err => {
            if (alertBox) {
                alertBox.className = 'alert alert-danger py-1 px-2 small mb-2';
                alertBox.innerHTML = `<i class="bi bi-exclamation-circle me-1"></i> Network/API error.`;
            }
        });
}

function viewChainOfCustody(evidenceId) {
    document.getElementById('chainOfCustodyDetails').innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div><p class="mt-2 text-muted">Loading chain of custody...</p></div>';
    new bootstrap.Modal(document.getElementById('chainOfCustodyModal')).show();
    fetch(`evidence_ajax.php?action=chain&id=${evidenceId}`)
        .then(response => response.text())
        .then(data => {
            document.getElementById('chainOfCustodyDetails').innerHTML = data;
        });
}

function viewCctvDetail(cctvId) {
    var body = document.getElementById('cctvDetailBody');
    body.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-success" role="status"></div></div>';
    new bootstrap.Modal(document.getElementById('cctvDetailModal')).show();
    
    // Build detail from existing PHP data
    <?php echo "var cctvData = " . json_encode($receivedCctvEvidence) . ";"; ?>
    var record = cctvData.find(r => r.id == cctvId);
    if (record) {
        var html = '<div class="bg-light rounded p-3">';
        html += '<div class="detail-row d-flex justify-content-between"><span class="detail-label">Request ID:</span><span class="detail-value"><strong>#' + record.id + '</strong></span></div>';
        html += '<div class="detail-row d-flex justify-content-between"><span class="detail-label">Camera Location:</span><span class="detail-value">' + (record.camera_location || 'N/A') + '</span></div>';
        html += '<div class="detail-row d-flex justify-content-between"><span class="detail-label">Priority:</span><span class="detail-value">' + (record.priority || 'Normal') + '</span></div>';
        html += '<div class="detail-row d-flex justify-content-between"><span class="detail-label">Status:</span><span class="detail-value"><span class="badge bg-success">' + record.status + '</span></span></div>';
        html += '<div class="detail-row d-flex justify-content-between"><span class="detail-label">Acknowledged By:</span><span class="detail-value">' + (record.acknowledged_by || 'Pending') + '</span></div>';
        html += '<div class="detail-row d-flex justify-content-between"><span class="detail-label">Operator:</span><span class="detail-value">' + (record.assigned_camera_operator || 'N/A') + '</span></div>';
        html += '<div class="detail-row"><span class="detail-label">Notes:</span><br><span class="detail-value text-muted small">' + (record.acknowledgement_notes || record.reason || 'No notes') + '</span></div>';
        if (record.fulfilled_photo_url) {
            html += '<div class="detail-row"><span class="detail-label"><i class="bi bi-image me-1"></i>Photo Evidence:</span><br><a href="' + record.fulfilled_photo_url + '" target="_blank" class="btn btn-sm btn-outline-success mt-1"><i class="bi bi-download me-1"></i>View/Download Photo</a></div>';
        }
        if (record.fulfilled_video_url) {
            html += '<div class="detail-row"><span class="detail-label"><i class="bi bi-camera-video me-1"></i>Video Evidence:</span><br><a href="' + record.fulfilled_video_url + '" target="_blank" class="btn btn-sm btn-outline-primary mt-1"><i class="bi bi-download me-1"></i>View/Download Video</a></div>';
        }
        html += '</div>';
        body.innerHTML = html;
    }
}

function viewAccidentDetail(accId) {
    var body = document.getElementById('accidentDetailBody');
    body.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-warning" role="status"></div></div>';
    new bootstrap.Modal(document.getElementById('accidentDetailModal')).show();
    
    <?php echo "var accData = " . json_encode($receivedAccidents) . ";"; ?>
    var record = accData.find(r => r.id == accId);
    if (record) {
        var html = '<div class="row"><div class="col-md-6">';
        html += '<div class="bg-light rounded p-3 mb-3">';
        html += '<h6 class="fw-bold text-warning mb-2"><i class="bi bi-ticket-detailed me-1"></i>Ticket & Report</h6>';
        html += '<div class="detail-row d-flex justify-content-between"><span class="detail-label">Report ID:</span><span class="detail-value"><code>' + (record.report_id || 'N/A') + '</code></span></div>';
        html += '<div class="detail-row d-flex justify-content-between"><span class="detail-label">Ticket #:</span><span class="detail-value"><strong>' + (record.ticket_number || 'N/A') + '</strong></span></div>';
        html += '<div class="detail-row d-flex justify-content-between"><span class="detail-label">Severity:</span><span class="detail-value"><span class="badge bg-' + (record.severity_level === 'Critical' ? 'danger' : record.severity_level === 'High' ? 'warning text-dark' : 'secondary') + '">' + (record.severity_level || 'Medium') + '</span></span></div>';
        html += '<div class="detail-row d-flex justify-content-between"><span class="detail-label">Status:</span><span class="detail-value"><span class="badge bg-success">' + (record.status || 'Logged') + '</span></span></div>';
        html += '</div></div>';
        
        html += '<div class="col-md-6">';
        html += '<div class="bg-light rounded p-3 mb-3">';
        html += '<h6 class="fw-bold text-danger mb-2"><i class="bi bi-person me-1"></i>Violator Info</h6>';
        html += '<div class="detail-row d-flex justify-content-between"><span class="detail-label">Name:</span><span class="detail-value">' + (record.violator_name || 'Unknown') + '</span></div>';
        html += '<div class="detail-row d-flex justify-content-between"><span class="detail-label">Plate:</span><span class="detail-value"><span class="badge bg-dark">' + (record.plate_number || 'N/A') + '</span></span></div>';
        html += '<div class="detail-row d-flex justify-content-between"><span class="detail-label">Vehicle:</span><span class="detail-value">' + (record.vehicle_details || 'N/A') + '</span></div>';
        html += '<div class="detail-row d-flex justify-content-between"><span class="detail-label">Fine:</span><span class="detail-value"><strong>₱' + parseFloat(record.fine_amount || 0).toLocaleString('en-PH', {minimumFractionDigits: 2}) + '</strong></span></div>';
        html += '</div></div></div>';
        
        html += '<div class="bg-light rounded p-3 mb-3">';
        html += '<h6 class="fw-bold mb-2"><i class="bi bi-geo-alt me-1"></i>Incident Details</h6>';
        html += '<div class="detail-row"><span class="detail-label">Violation Type:</span> ' + (record.violation_type || 'N/A') + '</div>';
        html += '<div class="detail-row"><span class="detail-label">Collision Type:</span> ' + (record.collision_type || 'N/A') + '</div>';
        html += '<div class="detail-row"><span class="detail-label">Location:</span> ' + (record.location || 'N/A') + '</div>';
        html += '<div class="detail-row"><span class="detail-label">Casualties:</span> ' + (record.casualties_count || 0) + ' | <span class="detail-label">Property Damage:</span> ₱' + parseFloat(record.property_damage_estimate || 0).toLocaleString('en-PH', {minimumFractionDigits: 2}) + '</div>';
        html += '<div class="detail-row"><span class="detail-label">Narrative:</span><br><span class="text-muted small">' + (record.narrative || 'No narrative provided') + '</span></div>';
        html += '</div>';
        
        body.innerHTML = html;
    }
}

function showUpdateStatusForm(evidenceId) {
    document.getElementById(`updateStatusForm_${evidenceId}`).style.display = 'block';
}

function hideUpdateStatusForm(evidenceId) {
    document.getElementById(`updateStatusForm_${evidenceId}`).style.display = 'none';
}

function showAddCustodyForm(evidenceId) {
    document.getElementById(`addCustodyForm_${evidenceId}`).style.display = 'block';
}

function hideAddCustodyForm(evidenceId) {
    document.getElementById(`addCustodyForm_${evidenceId}`).style.display = 'none';
}
</script>

<?php include '../includes/footer.php'; ?>