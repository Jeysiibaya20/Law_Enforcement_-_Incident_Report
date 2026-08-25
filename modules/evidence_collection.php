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
    // 1. Ensure evidence_records columns
    $evidenceColumns = [
        'source_department' => 'VARCHAR(150) NULL',
        'received_from' => 'VARCHAR(150) NULL',
        'source_reference' => 'VARCHAR(100) NULL',
        'witness_name' => 'VARCHAR(150) NULL',
        'witness_description' => 'TEXT NULL',
        'collector_name' => 'VARCHAR(150) NULL',
        'location_found' => 'VARCHAR(255) NULL',
        'storage_location' => 'VARCHAR(255) NULL',
        'security_level' => "ENUM('Low', 'Medium', 'High', 'Confidential') DEFAULT 'Medium'",
        'status' => "ENUM('Collected', 'In Storage', 'In Transit', 'Released', 'Destroyed', 'Lost') DEFAULT 'Collected'"
    ];

    foreach ($evidenceColumns as $column => $definition) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'evidence_records' AND COLUMN_NAME = ?");
        $stmt->execute([$column]);
        if ((int) $stmt->fetchColumn() === 0) {
            $pdo->exec("ALTER TABLE evidence_records ADD COLUMN `{$column}` {$definition}");
        }
    }

    // 2. Ensure chain_of_custody columns
    $custodyColumns = [
        'from_person_name' => 'VARCHAR(150) NULL',
        'to_person_name' => 'VARCHAR(150) NULL',
        'witness_name' => 'VARCHAR(150) NULL',
        'witness_signature' => 'TEXT NULL',
        'purpose' => 'VARCHAR(255) NULL',
        'location' => 'VARCHAR(255) NULL',
        'notes' => 'TEXT NULL'
    ];

    foreach ($custodyColumns as $column => $definition) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'chain_of_custody' AND COLUMN_NAME = ?");
        $stmt->execute([$column]);
        if ((int) $stmt->fetchColumn() === 0) {
            $pdo->exec("ALTER TABLE chain_of_custody ADD COLUMN `{$column}` {$definition}");
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

            // Extract values with support for dropdowns and custom entries
            $witness_name = trim($_POST['witness_name_select'] ?? '');
            if ($witness_name === '__custom__') {
                $witness_name = trim($_POST['custom_witness_name'] ?? '');
            } elseif (empty($witness_name) && !empty($_POST['witness_name'])) {
                $witness_name = trim($_POST['witness_name']);
            }

            $source_dept = trim($_POST['source_department'] ?? '');
            if ($source_dept === 'Other' && !empty($_POST['custom_source_department'])) {
                $source_dept = trim($_POST['custom_source_department']);
            }

            $received_from = trim($_POST['received_from'] ?? '');
            if ($received_from === 'Other' && !empty($_POST['custom_received_from'])) {
                $received_from = trim($_POST['custom_received_from']);
            }

            $storage_loc = trim($_POST['storage_location'] ?? '');
            if ($storage_loc === 'Other' && !empty($_POST['custom_storage_location'])) {
                $storage_loc = trim($_POST['custom_storage_location']);
            }
            if (empty($storage_loc)) {
                $storage_loc = 'Evidence Room A-1 (High Security)';
            }

            $source_ref = trim($_POST['source_reference_select'] ?? '');
            if ($source_ref === '__custom__') {
                $source_ref = trim($_POST['custom_source_reference'] ?? '');
            } elseif (empty($source_ref) && !empty($_POST['source_reference'])) {
                $source_ref = trim($_POST['source_reference']);
            }

            $insert_stmt->execute([
                $evidence_number,
                $_POST['evidence_type'],
                $_POST['case_id'] ?: null,
                $_POST['case_number'] ?: null,
                $_POST['item_description'],
                $_POST['location_found'] ?: null,
                $source_dept ?: null,
                $received_from ?: null,
                $source_ref ?: null,
                $collection_datetime,
                $_POST['condition'],
                $storage_loc,
                $_POST['security_level'],
                $witness_name ?: null,
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

// Fetch registered witnesses for dropdown selection
$witnessesList = [];
try {
    $witStmt = $pdo->query("SELECT id, case_id, case_number, first_name, middle_name, last_name, witness_type, statement FROM witnesses ORDER BY created_at DESC");
    $witnessesList = $witStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $witnessesList = []; }

// Fetch available existing sources for Source Reference dropdown
$refAccidents = [];
try {
    $stmt = $pdo->query("SELECT id, ticket_number, report_id, violator_name, violation_type, location FROM received_accident_reports ORDER BY id DESC LIMIT 30");
    $refAccidents = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $refAccidents = []; }

$refCctvRequests = [];
try {
    $stmt = $pdo->query("SELECT id, request_id, case_number, incident_type, camera_location, vehicle_plate, status FROM cctv_requests ORDER BY id DESC LIMIT 30");
    $refCctvRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $refCctvRequests = []; }

$refBlotters = [];
try {
    $stmt = $pdo->query("SELECT id, blotter_no, incident_type, complainant_name, location FROM blotters WHERE status != 'Archived' ORDER BY id DESC LIMIT 30");
    $refBlotters = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $refBlotters = []; }

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

/* Carousel & Table View Enhancements */
.view-btn-group .btn {
    font-size: 0.82rem;
    font-weight: 600;
    padding: 5px 14px;
    background-color: #ffffff !important;
    color: #1b4332 !important;
    border: 1px solid rgba(255,255,255,0.4) !important;
}
.view-btn-group .btn.active {
    background-color: #1b4332 !important;
    color: #ffffff !important;
    border-color: #1b4332 !important;
}
.view-btn-group .btn:hover:not(.active) {
    background-color: #f0fdf4 !important;
    color: #1b4332 !important;
}
.filter-chip {
    font-size: 0.8rem;
    transition: all 0.2s ease-in-out;
}
.filter-chip.active {
    background-color: #1b4332 !important;
    border-color: #1b4332 !important;
    color: #ffffff !important;
}
.filter-chip:hover:not(.active) {
    background-color: #e6f4ea !important;
    color: #1b4332 !important;
}
.table-evidence {
    border-collapse: separate;
    border-spacing: 0;
}
.table-evidence thead th {
    background-color: #f8fafc;
    color: #334155;
    font-size: 0.76rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 700;
    padding: 12px 14px;
    border-bottom: 2px solid #e2e8f0;
    white-space: nowrap;
}
.table-evidence tbody td {
    padding: 12px 14px;
    vertical-align: middle;
    border-bottom: 1px solid #f1f5f9;
    color: #1e293b;
    font-size: 0.875rem;
}
.table-evidence tbody tr:hover {
    background-color: #f8fafc;
}
.tag-evidence-no {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-weight: 700;
    color: #1b4332;
    white-space: nowrap;
}
.evidence-card-item {
    border: 1px solid rgba(0,0,0,0.09);
    border-radius: 12px;
    transition: all 0.25s ease;
    background: #fff;
}
.evidence-card-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.08);
    border-color: #0d6efd;
}
.evidence-card-header {
    border-top-left-radius: 11px;
    border-top-right-radius: 11px;
    padding: 10px 14px;
    background: #f8fafc;
    border-bottom: 1px solid #eef2f6;
}
.evidence-card-body {
    padding: 12px 14px;
}
.evidence-field-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 8px;
    font-size: 0.82rem;
}
.evidence-field-item {
    background: #f8fafc;
    padding: 6px 10px;
    border-radius: 8px;
    border: 1px solid #f1f5f9;
}
.evidence-field-item .field-label {
    font-size: 0.72rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #64748b;
    font-weight: 700;
    display: block;
    margin-bottom: 2px;
}
.evidence-field-item .field-value {
    color: #1e293b;
    font-weight: 500;
}
.carousel-control-prev-custom,
.carousel-control-next-custom {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    background: #0d6efd;
    color: #fff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: none;
    transition: all 0.2s ease;
}
.carousel-control-prev-custom:hover,
.carousel-control-next-custom:hover {
    background: #0b5ed7;
    transform: scale(1.08);
}
.carousel-indicators-custom {
    display: flex;
    gap: 6px;
    align-items: center;
}
.carousel-indicator-chip {
    width: 28px;
    height: 28px;
    border-radius: 6px;
    border: 1px solid #cbd5e1;
    background: #fff;
    color: #334155;
    font-size: 0.78rem;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s;
}
.carousel-indicator-chip.active {
    background: #0d6efd;
    color: #fff;
    border-color: #0d6efd;
}
.pagination-custom .page-link {
    font-size: 0.84rem;
    padding: 6px 12px;
    border-radius: 6px;
    margin: 0 2px;
}
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
                        <span class="badge text-white px-3 py-2 shadow-sm" style="background-color: #2e856e !important;"><i class="bi bi-shield-check me-1"></i> Group 1<br><small>Law Enforcement (You)</small></span>
                        <div class="flow-arrow"><i class="bi bi-arrow-right-circle-fill"></i> Photo + Video</div>
                        <span class="badge bg-primary px-3 py-2"><i class="bi bi-cloud-upload me-1"></i> Group 7<br><small>Photo & Videos Upload</small></span>
                    </div>
                </div>
            </div>

            <!-- Evidence Records Card with Dual View: Table (10 per page) & Carousel (10 per slide) -->
            <div class="card shadow-sm border-0 rounded-3 overflow-hidden mb-4">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2 py-3 px-4" style="background: linear-gradient(135deg, #1b4332 0%, #2d6a4f 100%) !important; color: #ffffff !important;">
                    <div class="d-flex align-items-center gap-2">
                        <h5 class="mb-0 fw-bold text-white"><i class="bi bi-file-earmark-lock me-2 text-warning"></i> Evidence Catalog</h5>
                        <span class="badge bg-white text-success rounded-pill px-3 py-1.5 fw-bold shadow-sm" style="color: #1b4332 !important; background-color: #ffffff !important;" id="totalRecordsBadge"><?= count($evidence_records) ?> records</span>
                    </div>
                    
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <!-- Search Box -->
                        <div class="input-group input-group-sm" style="width: 230px;">
                            <span class="input-group-text bg-white text-muted border-0"><i class="bi bi-search"></i></span>
                            <input type="text" id="evidenceSearchInput" class="form-control border-0 shadow-sm" style="color: #1e293b !important; background: #ffffff !important;" placeholder="Search tag, case, keyword..." onkeyup="filterEvidenceRecords()">
                        </div>

                        <!-- Rows per page selector -->
                        <select id="pageSizeSelect" class="form-select form-select-sm border-0 shadow-sm fw-bold" style="width: auto; color: #1e293b !important; background-color: #ffffff !important;" onchange="changePageSize(this.value)">
                            <option value="10" selected>10 per page</option>
                            <option value="25">25 per page</option>
                            <option value="50">50 per page</option>
                            <option value="1000">Show All</option>
                        </select>

                        <!-- View Switcher -->
                        <div class="btn-group btn-group-sm view-btn-group shadow-sm" role="group">
                            <button type="button" class="btn btn-light active" id="btnTableView" onclick="switchEvidenceView('table')">
                                <i class="bi bi-table me-1"></i> Table View
                            </button>
                            <button type="button" class="btn btn-light" id="btnCarouselView" onclick="switchEvidenceView('carousel')">
                                <i class="bi bi-view-stacked me-1"></i> Carousel View (10/Slide)
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Quick Filter Chips Toolbar -->
                <div class="bg-light px-4 py-2 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-1.5 flex-wrap">
                        <span class="small fw-bold text-muted me-1"><i class="bi bi-funnel text-success me-1"></i>Filter:</span>
                        <button type="button" class="btn btn-xs btn-outline-secondary rounded-pill px-3 py-1 fw-bold filter-chip active" onclick="applyStatusFilter('all', this)">All (<?= count($evidence_records) ?>)</button>
                        <button type="button" class="btn btn-xs btn-outline-secondary rounded-pill px-3 py-1 fw-semibold filter-chip" onclick="applyStatusFilter('collected', this)">📥 Collected</button>
                        <button type="button" class="btn btn-xs btn-outline-secondary rounded-pill px-3 py-1 fw-semibold filter-chip" onclick="applyStatusFilter('in storage', this)">📦 In Storage</button>
                        <button type="button" class="btn btn-xs btn-outline-secondary rounded-pill px-3 py-1 fw-semibold filter-chip" onclick="applyStatusFilter('in transit', this)">🚚 In Transit</button>
                        <button type="button" class="btn btn-xs btn-outline-secondary rounded-pill px-3 py-1 fw-semibold filter-chip" onclick="applyStatusFilter('released', this)">✅ Released</button>
                    </div>
                    <small class="text-muted"><i class="bi bi-shield-check text-success me-1"></i>Official Evidentiary Custody Registry</small>
                </div>

                <div class="card-body p-0">
                    <!-- ================= TABLE VIEW ================= -->
                    <div id="evidenceTableView" class="p-3">
                        <div class="table-responsive">
                            <table class="table table-hover table-evidence align-middle mb-0" id="evidenceTable">
                                <thead>
                                    <tr>
                                        <th style="width: 40px;">#</th>
                                        <th style="min-width: 170px;">Evidence Tag</th>
                                        <th style="min-width: 220px;">Item Description</th>
                                        <th style="min-width: 180px;">Source / Incident Case</th>
                                        <th style="min-width: 170px;">Storage & Location</th>
                                        <th style="min-width: 140px;">Custodian & Date</th>
                                        <th style="min-width: 130px;">Status</th>
                                        <th style="min-width: 160px; text-align: center;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="evidenceTableBody">
                                    <?php if (empty($evidence_records)): ?>
                                        <tr class="no-records-row"><td colspan="8" class="text-center text-muted py-5"><i class="bi bi-inbox fs-2 d-block mb-2 text-secondary"></i> No evidence records found. Create one to get started.</td></tr>
                                    <?php else: ?>
                                    <?php foreach ($evidence_records as $idx => $evidence): ?>
                                        <tr class="evidence-row" 
                                            data-index="<?= $idx ?>"
                                            data-evidence-no="<?= htmlspecialchars(strtolower($evidence['evidence_number'])) ?>"
                                            data-case="<?= htmlspecialchars(strtolower($evidence['case_number'] ?: '')) ?>"
                                            data-type="<?= htmlspecialchars(strtolower($evidence['evidence_type'])) ?>"
                                            data-desc="<?= htmlspecialchars(strtolower($evidence['item_description'])) ?>"
                                            data-source="<?= htmlspecialchars(strtolower(($evidence['source_department'] ?? '') . ' ' . ($evidence['source_reference'] ?? ''))) ?>"
                                            data-witness="<?= htmlspecialchars(strtolower($evidence['witness_name'] ?? '')) ?>"
                                            data-status="<?= htmlspecialchars(strtolower($evidence['status'])) ?>">
                                            <td class="text-muted small fw-bold"><?= $idx + 1 ?></td>
                                            
                                            <!-- Evidence Tag & Security -->
                                            <td>
                                                <div class="d-flex align-items-center gap-1 mb-1">
                                                    <span class="tag-evidence-no fs-6"><?= htmlspecialchars($evidence['evidence_number']) ?></span>
                                                    <a href="javascript:void(0)" class="text-muted small" title="Copy Evidence Tag" onclick="copyEvidenceTag('<?= htmlspecialchars(addslashes($evidence['evidence_number'])) ?>', this)">
                                                        <i class="bi bi-clipboard"></i>
                                                    </a>
                                                </div>
                                                <div class="d-flex gap-1 flex-wrap">
                                                    <span class="badge bg-light text-dark border"><?= htmlspecialchars($evidence['evidence_type']) ?></span>
                                                    <span class="badge bg-<?= match($evidence['security_level'] ?? 'Medium') { 'High', 'Confidential' => 'danger', 'Medium' => 'warning text-dark', default => 'secondary' } ?>">
                                                        <?= htmlspecialchars($evidence['security_level'] ?? 'Medium') ?>
                                                    </span>
                                                </div>
                                            </td>

                                            <!-- Item Description -->
                                            <td>
                                                <div class="fw-bold text-dark text-truncate" style="max-width: 240px;" title="<?= htmlspecialchars($evidence['item_description']) ?>">
                                                    <?= htmlspecialchars($evidence['item_description']) ?>
                                                </div>
                                                <div class="small text-muted mt-0.5">
                                                    Condition: <span class="badge bg-light text-dark border"><?= htmlspecialchars($evidence['condition'] ?? 'Good') ?></span>
                                                </div>
                                            </td>

                                            <!-- Source / Incident Case -->
                                            <td>
                                                <div class="small fw-semibold text-dark text-truncate" style="max-width: 200px;" title="<?= htmlspecialchars($evidence['source_department'] ?: 'Internal Unit') ?>">
                                                    <i class="bi bi-building text-success me-1"></i><?= htmlspecialchars($evidence['source_department'] ?: 'Internal Unit') ?>
                                                </div>
                                                <?php if (!empty($evidence['case_number'])): ?>
                                                    <div class="small text-muted font-monospace"><i class="bi bi-folder text-warning me-1"></i><?= htmlspecialchars($evidence['case_number']) ?></div>
                                                <?php else: ?>
                                                    <small class="text-muted fst-italic">No linked case</small>
                                                <?php endif; ?>
                                                <?php if (!empty($evidence['source_reference'])): ?>
                                                    <small class="text-muted d-block text-truncate" style="max-width: 200px;">Ref: <code><?= htmlspecialchars($evidence['source_reference']) ?></code></small>
                                                <?php endif; ?>
                                            </td>

                                            <!-- Storage & Location -->
                                            <td>
                                                <div class="small text-dark text-truncate" style="max-width: 180px;" title="<?= htmlspecialchars($evidence['storage_location'] ?: 'Vault A-1') ?>">
                                                    <i class="bi bi-box-seam text-warning me-1"></i><strong><?= htmlspecialchars($evidence['storage_location'] ?: 'Vault A-1') ?></strong>
                                                </div>
                                                <?php if (!empty($evidence['location_found'])): ?>
                                                    <small class="text-muted d-block text-truncate" style="max-width: 180px;" title="<?= htmlspecialchars($evidence['location_found']) ?>">
                                                        <i class="bi bi-geo-alt text-danger me-1"></i><?= htmlspecialchars($evidence['location_found']) ?>
                                                    </small>
                                                <?php endif; ?>
                                            </td>

                                            <!-- Custodian & Date -->
                                            <td>
                                                <div class="small fw-semibold text-dark text-truncate" style="max-width: 140px;">
                                                    <i class="bi bi-person-badge text-primary me-1"></i><?= htmlspecialchars($evidence['collector_name'] ?: 'Duty Officer') ?>
                                                </div>
                                                <small class="text-muted d-block"><?= date('M d, Y', strtotime($evidence['collection_date'])) ?></small>
                                            </td>

                                            <!-- Status & Files -->
                                            <td>
                                                <span class="badge rounded-pill bg-<?= match($evidence['status']) {
                                                    'Collected' => 'primary',
                                                    'In Storage' => 'info text-dark',
                                                    'In Transit' => 'warning text-dark',
                                                    'Released' => 'success',
                                                    'Destroyed' => 'danger',
                                                    'Lost' => 'dark',
                                                    default => 'secondary'
                                                } ?> px-2.5 py-1 fw-semibold status-badge-<?= $evidence['id'] ?>"><?= htmlspecialchars($evidence['status']) ?></span>
                                                
                                                <?php if ($evidence['attachment_count'] > 0): ?>
                                                    <div class="small text-success fw-semibold mt-1">
                                                        <i class="bi bi-paperclip me-1"></i><?= $evidence['attachment_count'] ?> file(s)
                                                    </div>
                                                <?php else: ?>
                                                    <div class="small text-muted mt-1">0 files</div>
                                                <?php endif; ?>
                                            </td>

                                            <!-- Actions -->
                                            <td style="text-align: center;">
                                                <div class="d-inline-flex gap-1 align-items-center">
                                                    <button type="button" class="btn btn-sm btn-outline-success fw-bold px-2 py-1 shadow-sm" onclick="viewEvidence(<?= $evidence['id'] ?>)" title="View Full Evidence Details">
                                                        <i class="bi bi-eye me-1"></i>Details
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary px-2 py-1 shadow-sm" onclick="viewChainOfCustody(<?= $evidence['id'] ?>)" title="View Chain of Custody Log">
                                                        <i class="bi bi-link-45deg"></i>
                                                    </button>
                                                    <?php if ($evidence['attachment_count'] > 0): ?>
                                                    <button type="button" class="btn btn-sm btn-success px-2 py-1 shadow-sm fw-bold" style="background-color: #2e856e !important; border-color: #2e856e !important;" onclick="openSendToGroup7Modal(<?= $evidence['id'] ?>, '<?= htmlspecialchars(addslashes($evidence['evidence_number'])) ?>', '<?= htmlspecialchars(addslashes($evidence['case_number'] ?: 'N/A')) ?>', <?= intval($evidence['attachment_count']) ?>)" title="Forward Photos & Videos to Group 7">
                                                        <i class="bi bi-cloud-upload"></i>
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

                        <!-- Table Pagination Bar -->
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3 pt-2 border-top">
                            <div class="text-muted small" id="tablePaginationInfo">
                                Showing 1 to 10 of <?= count($evidence_records) ?> entries
                            </div>
                            <nav aria-label="Evidence table pagination">
                                <ul class="pagination pagination-sm pagination-custom mb-0" id="tablePaginationControls">
                                    <!-- Populated dynamically via JS -->
                                </ul>
                            </nav>
                        </div>
                    </div>

                    <!-- ================= CAROUSEL VIEW (10 Items per Slide) ================= -->
                    <div id="evidenceCarouselView" class="p-3" style="display: none;">
                        <?php 
                            $evidenceBatches = array_chunk($evidence_records, 10);
                            $totalSlides = count($evidenceBatches);
                        ?>

                        <!-- Carousel Header & Navigation Controls -->
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3 p-2 bg-light rounded border">
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-primary px-3 py-2 fs-6">
                                    <i class="bi bi-view-stacked me-1"></i> <span id="carouselSlideLabel">Slide 1 of <?= max(1, $totalSlides) ?></span>
                                </span>
                                <small class="text-muted" id="carouselItemRangeLabel">
                                    Showing <?= count($evidence_records) > 0 ? '1 - ' . min(10, count($evidence_records)) : '0' ?> of <?= count($evidence_records) ?> total records
                                </small>
                            </div>

                            <div class="d-flex align-items-center gap-2">
                                <button class="carousel-control-prev-custom" type="button" data-bs-target="#evidenceCatalogCarousel" data-bs-slide="prev" title="Previous Slide">
                                    <i class="bi bi-chevron-left"></i>
                                </button>
                                
                                <div class="carousel-indicators-custom" id="carouselCustomIndicators">
                                    <?php for ($s = 0; $s < $totalSlides; $s++): ?>
                                        <button class="carousel-indicator-chip <?= $s === 0 ? 'active' : '' ?>" type="button" data-bs-target="#evidenceCatalogCarousel" data-bs-slide-to="<?= $s ?>">
                                            <?= $s + 1 ?>
                                        </button>
                                    <?php endfor; ?>
                                </div>

                                <button class="carousel-control-next-custom" type="button" data-bs-target="#evidenceCatalogCarousel" data-bs-slide="next" title="Next Slide">
                                    <i class="bi bi-chevron-right"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Bootstrap Carousel -->
                        <div id="evidenceCatalogCarousel" class="carousel slide" data-bs-interval="false">
                            <div class="carousel-inner">
                                <?php if (empty($evidenceBatches)): ?>
                                    <div class="carousel-item active">
                                        <div class="text-center py-5 text-muted">
                                            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                            <h5>No Evidence Records in Catalog</h5>
                                            <p>Create an evidence record to see it in the carousel view.</p>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($evidenceBatches as $slideIdx => $batch): ?>
                                        <div class="carousel-item <?= $slideIdx === 0 ? 'active' : '' ?>" data-slide-index="<?= $slideIdx ?>">
                                            <div class="row g-3">
                                                <?php foreach ($batch as $cardIdx => $item): ?>
                                                    <div class="col-md-6 col-lg-6 evidence-carousel-card-col">
                                                        <div class="evidence-card-item h-100 d-flex flex-column">
                                                            <div class="evidence-card-header d-flex justify-content-between align-items-center">
                                                                <div>
                                                                    <strong class="text-primary fs-6"><?= htmlspecialchars($item['evidence_number']) ?></strong>
                                                                    <span class="badge bg-light text-dark border ms-1"><?= htmlspecialchars($item['evidence_type']) ?></span>
                                                                </div>
                                                                <span class="badge bg-<?= match($item['status']) {
                                                                    'Collected' => 'primary',
                                                                    'In Storage' => 'info',
                                                                    'In Transit' => 'warning text-dark',
                                                                    'Released' => 'success',
                                                                    'Destroyed' => 'danger',
                                                                    'Lost' => 'dark',
                                                                    default => 'secondary'
                                                                } ?>"><?= htmlspecialchars($item['status']) ?></span>
                                                            </div>
                                                            <div class="evidence-card-body flex-grow-1">
                                                                <h6 class="fw-bold mb-1 text-truncate" title="<?= htmlspecialchars($item['item_description']) ?>">
                                                                    <?= htmlspecialchars($item['item_description']) ?>
                                                                </h6>
                                                                <p class="text-muted small mb-2"><i class="bi bi-tag me-1"></i>Condition: <strong><?= htmlspecialchars($item['condition'] ?? 'Good') ?></strong> | Security: <span class="badge bg-<?= match($item['security_level'] ?? 'Medium') { 'High', 'Confidential' => 'danger', 'Medium' => 'warning text-dark', default => 'secondary' } ?>"><?= htmlspecialchars($item['security_level'] ?? 'Medium') ?></span></p>

                                                                <!-- All Columns Field Grid -->
                                                                <div class="evidence-field-grid">
                                                                    <div class="evidence-field-item">
                                                                        <span class="field-label"><i class="bi bi-folder me-1"></i>Case Number</span>
                                                                        <span class="field-value"><?= htmlspecialchars($item['case_number'] ?: 'N/A') ?></span>
                                                                    </div>
                                                                    <div class="evidence-field-item">
                                                                        <span class="field-label"><i class="bi bi-building me-1"></i>Source Dept</span>
                                                                        <span class="field-value text-info"><?= htmlspecialchars($item['source_department'] ?: 'N/A') ?></span>
                                                                    </div>
                                                                    <div class="evidence-field-item">
                                                                        <span class="field-label"><i class="bi bi-file-earmark-text me-1"></i>Source Ref</span>
                                                                        <span class="field-value"><code><?= htmlspecialchars($item['source_reference'] ?: 'None') ?></code></span>
                                                                    </div>
                                                                    <div class="evidence-field-item">
                                                                        <span class="field-label"><i class="bi bi-geo-alt me-1"></i>Location Found</span>
                                                                        <span class="field-value"><?= htmlspecialchars($item['location_found'] ?: 'Not specified') ?></span>
                                                                    </div>
                                                                    <div class="evidence-field-item">
                                                                        <span class="field-label"><i class="bi bi-box-seam me-1"></i>Storage Location</span>
                                                                        <span class="field-value"><?= htmlspecialchars($item['storage_location'] ?: 'Vault A-1') ?></span>
                                                                    </div>
                                                                    <div class="evidence-field-item">
                                                                        <span class="field-label"><i class="bi bi-person-check me-1"></i>Witness</span>
                                                                        <span class="field-value"><?= htmlspecialchars($item['witness_name'] ?: 'None') ?></span>
                                                                    </div>
                                                                    <div class="evidence-field-item">
                                                                        <span class="field-label"><i class="bi bi-person-badge me-1"></i>Collector</span>
                                                                        <span class="field-value"><?= htmlspecialchars($item['collector_name'] ?: 'Officer') ?></span>
                                                                    </div>
                                                                    <div class="evidence-field-item">
                                                                        <span class="field-label"><i class="bi bi-calendar me-1"></i>Collected Date</span>
                                                                        <span class="field-value"><?= date('M d, Y', strtotime($item['collection_date'])) ?></span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="p-2 px-3 bg-light border-top d-flex justify-content-between align-items-center">
                                                                <span class="badge bg-secondary"><i class="bi bi-paperclip me-1"></i><?= $item['attachment_count'] ?> files</span>
                                                                <div class="action-btn-group d-flex gap-1">
                                                                    <button class="btn btn-sm btn-outline-primary" onclick="viewEvidence(<?= $item['id'] ?>)">
                                                                        <i class="bi bi-eye"></i> View
                                                                    </button>
                                                                    <button class="btn btn-sm btn-outline-secondary" onclick="viewChainOfCustody(<?= $item['id'] ?>)">
                                                                        <i class="bi bi-link-45deg"></i> Chain
                                                                    </button>
                                                                    <?php if ($item['attachment_count'] > 0): ?>
                                                                    <button class="btn btn-sm btn-outline-success" onclick="openSendToGroup7Modal(<?= $item['id'] ?>, '<?= htmlspecialchars(addslashes($item['evidence_number'])) ?>', '<?= htmlspecialchars(addslashes($item['case_number'] ?: 'N/A')) ?>', <?= intval($item['attachment_count']) ?>)">
                                                                        <i class="bi bi-cloud-upload"></i> Group 7
                                                                    </button>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
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

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold"><i class="bi bi-person-check text-primary me-1"></i>Witness Name (if any)</label>
                            <select name="witness_name_select" id="witnessSelect" class="form-select" onchange="handleWitnessSelect(this)">
                                <option value="">-- No Witness / Not Applicable --</option>
                                <?php if (!empty($witnessesList)): ?>
                                    <optgroup label="Registered Case Witnesses">
                                        <?php foreach ($witnessesList as $wit): ?>
                                            <?php 
                                                $witFull = trim(($wit['first_name'] ?? '') . ' ' . ($wit['middle_name'] ? $wit['middle_name'] . ' ' : '') . ($wit['last_name'] ?? ''));
                                                $witCase = $wit['case_number'] ? ' (' . $wit['case_number'] . ')' : '';
                                                $witType = $wit['witness_type'] ? ' - ' . $wit['witness_type'] : '';
                                            ?>
                                            <option value="<?= htmlspecialchars($witFull) ?>" data-statement="<?= htmlspecialchars($wit['statement'] ?? '') ?>" data-case-number="<?= htmlspecialchars($wit['case_number'] ?? '') ?>">
                                                <?= htmlspecialchars($witFull . $witCase . $witType) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endif; ?>
                                <option value="__custom__">➕ Other / Non-registered Witness (Specify manually)</option>
                            </select>
                            <div id="customWitnessWrap" class="mt-2" style="display: none;">
                                <input type="text" name="custom_witness_name" id="customWitnessInput" class="form-control form-control-sm" placeholder="Enter witness full name...">
                            </div>
                            <small class="text-muted">Select registered case witness or specify a new witness</small>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold"><i class="bi bi-chat-quote text-secondary me-1"></i>Witness Description / Account</label>
                            <textarea name="witness_description" id="witnessDescriptionInput" class="form-control" rows="3" placeholder="Description or statement as given by the witness..."></textarea>
                            <small class="text-muted">Auto-populates when choosing a registered witness (optional)</small>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold"><i class="bi bi-geo-alt text-danger me-1"></i>Location Found</label>
                            <input type="text" name="location_found" class="form-control" placeholder="e.g., Corner Main St. & 5th Ave / Crime Scene Room 2">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label fw-bold"><i class="bi bi-building text-info me-1"></i>Source Department</label>
                            <select name="source_department" id="sourceDeptSelect" class="form-select" onchange="handleSourceDeptSelect(this)">
                                <option value="">-- Select Department --</option>
                                <option value="Group 2 - Accident & Violation Reporting">Group 2 (Accident & Violation Reporting)</option>
                                <option value="Group 4 - Citizen Complaints & Tips">Group 4 (Citizen Complaints & Tips)</option>
                                <option value="Group 5 - Crime Mapping & Surveillance">Group 5 (Crime Mapping & Surveillance)</option>
                                <option value="Group 7 - Photo & Video Upload Unit">Group 7 (Photo & Video Upload Unit)</option>
                                <option value="Digital Blotter Unit">Digital Blotter Unit / Records</option>
                                <option value="Patrol & Field Operations">Patrol & Field Operations</option>
                                <option value="CCTV Operations Room">CCTV Operations Room</option>
                                <option value="Barangay Public Safety (BPAT)">Barangay Public Safety (BPAT)</option>
                                <option value="Traffic Management Office">Traffic Management Office</option>
                                <option value="SOCO / Forensic Investigation">SOCO / Forensic Investigation</option>
                                <option value="External Law Enforcement">External Law Enforcement</option>
                                <option value="Other">Other (Specify Department)</option>
                            </select>
                            <div id="customSourceDeptWrap" class="mt-2" style="display: none;">
                                <input type="text" name="custom_source_department" class="form-control form-control-sm" placeholder="Specify department name...">
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label fw-bold"><i class="bi bi-person-badge text-primary me-1"></i>Received From</label>
                            <select name="received_from" id="receivedFromSelect" class="form-select" onchange="handleReceivedFromSelect(this)">
                                <option value="">-- Select Source / Role --</option>
                                <option value="Field Responding Officer">Field Responding Officer</option>
                                <option value="Duty Investigator / Desk Officer">Duty Investigator / Desk Officer</option>
                                <option value="CCTV Camera Operator">CCTV Camera Operator</option>
                                <option value="Traffic Enforcer / Unit">Traffic Enforcer / Unit</option>
                                <option value="Complainant / Citizen">Complainant / Citizen</option>
                                <option value="Witness / Informant">Witness / Informant</option>
                                <option value="Barangay Tanod / BPAT Official">Barangay Tanod / BPAT Official</option>
                                <option value="Hospital / Paramedic Staff">Hospital / Paramedic Staff</option>
                                <option value="Evidence Custodian">Evidence Custodian</option>
                                <option value="Other">Other (Specify Person)</option>
                            </select>
                            <div id="customReceivedFromWrap" class="mt-2" style="display: none;">
                                <input type="text" name="custom_received_from" class="form-control form-control-sm" placeholder="Specify person or role...">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold"><i class="bi bi-file-earmark-text text-secondary me-1"></i>Source Reference / Control No.</label>
                            <select name="source_reference_select" id="sourceReferenceSelect" class="form-select" onchange="handleSourceReferenceSelect(this)">
                                <option value="">-- Select Existing Source Reference --</option>
                                
                                <?php if (!empty($refAccidents)): ?>
                                    <optgroup label="Group 2: Accident Tickets & Reports">
                                        <?php foreach ($refAccidents as $acc): ?>
                                            <?php 
                                                $refCode = $acc['ticket_number'] ?: $acc['report_id'];
                                                $label = $acc['ticket_number'] . ($acc['report_id'] ? ' (' . $acc['report_id'] . ')' : '') . ' - ' . ($acc['violation_type'] ?: 'Traffic Incident') . ($acc['violator_name'] ? ' [' . $acc['violator_name'] . ']' : '');
                                            ?>
                                            <option value="<?= htmlspecialchars($refCode) ?>" 
                                                    data-source-dept="Group 2 - Accident & Violation Reporting"
                                                    data-location="<?= htmlspecialchars($acc['location'] ?? '') ?>">
                                                <?= htmlspecialchars($label) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endif; ?>

                                <?php if (!empty($refCctvRequests)): ?>
                                    <optgroup label="Group 2: CCTV Requests & Footage">
                                        <?php foreach ($refCctvRequests as $cctv): ?>
                                            <?php 
                                                $label = $cctv['request_id'] . ' - CCTV ' . ($cctv['camera_location'] ?: 'Surveillance') . ($cctv['case_number'] ? ' [' . $cctv['case_number'] . ']' : '');
                                            ?>
                                            <option value="<?= htmlspecialchars($cctv['request_id']) ?>"
                                                    data-source-dept="Group 2 - Accident & Violation Reporting"
                                                    data-case-no="<?= htmlspecialchars($cctv['case_number'] ?? '') ?>"
                                                    data-location="<?= htmlspecialchars($cctv['camera_location'] ?? '') ?>">
                                                <?= htmlspecialchars($label) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endif; ?>

                                <?php if (!empty($cases)): ?>
                                    <optgroup label="Assigned Investigation Cases">
                                        <?php foreach ($cases as $c): ?>
                                            <option value="<?= htmlspecialchars($c['case_number']) ?>"
                                                    data-case-no="<?= htmlspecialchars($c['case_number']) ?>"
                                                    data-source-dept="Digital Blotter Unit">
                                                <?= htmlspecialchars($c['case_number'] . ' - ' . $c['incident_type'] . ($c['complainant_name'] ? ' (' . $c['complainant_name'] . ')' : '')) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endif; ?>

                                <?php if (!empty($refBlotters)): ?>
                                    <optgroup label="Digital Blotter Entries">
                                        <?php foreach ($refBlotters as $b): ?>
                                            <option value="<?= htmlspecialchars($b['blotter_no']) ?>"
                                                    data-source-dept="Digital Blotter Unit"
                                                    data-location="<?= htmlspecialchars($b['location'] ?? '') ?>">
                                                <?= htmlspecialchars($b['blotter_no'] . ' - ' . ($b['incident_type'] ?? 'Blotter') . ($b['complainant_name'] ? ' (' . $b['complainant_name'] . ')' : '')) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endif; ?>

                                <option value="__custom__">➕ Other / Custom Control No. (Specify manually)</option>
                            </select>
                            <div id="customSourceRefWrap" class="mt-2" style="display: none;">
                                <input type="text" name="custom_source_reference" id="customSourceRefInput" class="form-control form-control-sm" placeholder="Enter custom control / reference number...">
                            </div>
                            <small class="text-muted">Select an existing ticket, CCTV request, case, or specify custom reference</small>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label fw-bold"><i class="bi bi-calendar-event me-1"></i>Collection Date *</label>
                            <input type="date" name="collection_date" class="form-control" required value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label fw-bold"><i class="bi bi-clock me-1"></i>Collection Time</label>
                            <input type="time" name="collection_time" class="form-control">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold"><i class="bi bi-shield-check text-success me-1"></i>Condition</label>
                            <select name="condition" class="form-select">
                                <option value="Excellent">Excellent</option>
                                <option value="Good" selected>Good</option>
                                <option value="Fair">Fair</option>
                                <option value="Poor">Poor</option>
                                <option value="Damaged">Damaged</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold"><i class="bi bi-box-seam text-warning me-1"></i>Storage Location *</label>
                            <select name="storage_location" id="storageLocSelect" class="form-select" required onchange="handleStorageLocSelect(this)">
                                <option value="">-- Select Storage Location --</option>
                                <option value="Evidence Room A-1 (High Security)" selected>Evidence Room A-1 (High Security)</option>
                                <option value="Evidence Room B-2 (General Storage)">Evidence Room B-2 (General Storage)</option>
                                <option value="Secure Locker C-1 (Narcotics & Firearms)">Secure Locker C-1 (Narcotics & Firearms)</option>
                                <option value="Secure Locker C-2 (Valuables & Currency)">Secure Locker C-2 (Valuables & Currency)</option>
                                <option value="Digital Evidence Vault (Server/Cloud)">Digital Evidence Vault (Server/Cloud)</option>
                                <option value="Forensic Refrigeration Unit">Forensic Refrigeration Unit</option>
                                <option value="Office Archive Cabinet">Office Archive Cabinet</option>
                                <option value="Temporary Holding Area">Temporary Holding Area</option>
                                <option value="Vehicle Impound Facility">Vehicle Impound Facility</option>
                                <option value="Other">Other (Specify Location)</option>
                            </select>
                            <div id="customStorageLocWrap" class="mt-2" style="display: none;">
                                <input type="text" name="custom_storage_location" class="form-control form-control-sm" placeholder="Enter custom storage location...">
                            </div>
                            <small class="text-muted">Files saved in: <code>uploads/evidence/</code></small>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold"><i class="bi bi-lock text-danger me-1"></i>Security Level</label>
                            <select name="security_level" class="form-select">
                                <option value="Low">Low</option>
                                <option value="Medium" selected>Medium</option>
                                <option value="High">High</option>
                                <option value="Confidential">Confidential</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold"><i class="bi bi-sticky me-1"></i>Operational Notes</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Additional notes or handling instructions..."></textarea>
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
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-3 overflow-hidden">
            <div class="modal-header py-3 px-4" style="background: linear-gradient(135deg, #1b4332 0%, #2d6a4f 100%) !important; color: white;">
                <h5 class="modal-title fw-bold"><i class="bi bi-eye text-warning me-2"></i> Evidence Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" id="evidenceDetails">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>
</div>

<!-- Chain of Custody Modal -->
<div class="modal fade" id="chainOfCustodyModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-3 overflow-hidden">
            <div class="modal-header py-3 px-4" style="background: linear-gradient(135deg, #1b4332 0%, #2d6a4f 100%) !important; color: white;">
                <h5 class="modal-title fw-bold"><i class="bi bi-link text-warning me-2"></i> Chain of Custody</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" id="chainOfCustodyDetails">
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
        <div class="modal-content border-0 shadow-lg rounded-3 overflow-hidden">
            <div class="modal-header py-3 px-4" style="background: linear-gradient(135deg, #1b4332 0%, #2d6a4f 100%) !important; color: white;">
                <h5 class="modal-title fw-bold"><i class="bi bi-cloud-upload text-warning me-2"></i>Forward Evidence to Group 7</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="text-center mb-3">
                    <div class="rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 64px; height: 64px; background: rgba(46, 133, 110, 0.12);">
                        <i class="bi bi-cloud-arrow-up" style="font-size: 2rem; color: #2e856e;"></i>
                    </div>
                </div>
                <div class="alert alert-info py-2 small">
                    <i class="bi bi-info-circle me-1"></i> This will dispatch all photo and video attachments to <strong>Group 7 (Photo and Videos Upload / Inspection)</strong> via their cloud API.
                </div>
                <div class="bg-light rounded p-3 mb-3 border">
                    <div class="detail-row d-flex justify-content-between mb-1">
                        <span class="detail-label text-muted">Evidence Number:</span>
                        <span class="detail-value fw-bold text-dark" id="g7ConfirmEvidenceNo">—</span>
                    </div>
                    <div class="detail-row d-flex justify-content-between mb-1">
                        <span class="detail-label text-muted">Case Number:</span>
                        <span class="detail-value text-dark" id="g7ConfirmCaseNo">—</span>
                    </div>
                    <div class="detail-row d-flex justify-content-between">
                        <span class="detail-label text-muted">Attachments:</span>
                        <span class="detail-value text-success fw-semibold" id="g7ConfirmAttachCount">—</span>
                    </div>
                </div>
                <div class="flow-arrow border rounded p-2 bg-white d-flex align-items-center justify-content-between">
                    <span class="badge text-white px-3 py-2" style="background-color: #2e856e;"><i class="bi bi-shield-check me-1"></i>Group 1 (You)</span>
                    <i class="bi bi-arrow-right-circle-fill text-success" style="font-size: 1.4rem;"></i>
                    <span class="badge bg-success bg-opacity-25 text-success border border-success-subtle px-3 py-2"><i class="bi bi-cloud-upload me-1"></i>Group 7 (Photo/Video)</span>
                </div>
                <div id="g7DispatchResult" class="mt-3" style="display: none;"></div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-outline-success px-4 fw-semibold" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success fw-bold shadow-sm px-4" style="background-color: #2e856e !important; border-color: #2e856e !important;" id="g7ConfirmSendBtn" onclick="confirmSendToGroup7()">
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
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary px-4 fw-semibold" data-bs-dismiss="modal"><i class="bi bi-x-lg me-1"></i> Close</button>
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
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary px-4 fw-semibold" data-bs-dismiss="modal"><i class="bi bi-x-lg me-1"></i> Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Received Accident Reports from Group 2 Modal -->
<div class="modal fade" id="receivedAccidentModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title fw-bold"><i class="bi bi-exclamation-triangle me-2"></i>Received Accident Tickets & Reports from Group 2</h5>
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
                                    <td><span class="badge bg-secondary"><?= htmlspecialchars($acc['plate_number'] ?: 'N/A') ?></span></td>
                                    <td><small><?= htmlspecialchars(substr($acc['violation_type'] ?: '', 0, 40)) ?></small></td>
                                    <td><span class="badge bg-<?= ($acc['severity_level'] ?? '') === 'Critical' ? 'danger' : (($acc['severity_level'] ?? '') === 'High' ? 'warning text-dark' : 'secondary') ?>"><?= htmlspecialchars($acc['severity_level'] ?? 'Medium') ?></span></td>
                                    <td><small><?= htmlspecialchars(substr($acc['location'] ?: '', 0, 40)) ?></small></td>
                                    <td>₱<?= number_format($acc['fine_amount'] ?? 0, 2) ?></td>
                                    <td><span class="badge bg-success"><?= htmlspecialchars($acc['status'] ?? 'Logged') ?></span></td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-warning" onclick="viewAccidentDetail(<?= $acc['id'] ?>)">
                                            <i class="bi bi-eye"></i> View
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary px-4 fw-semibold" data-bs-dismiss="modal"><i class="bi bi-x-lg me-1"></i> Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Accident Detail View Modal -->
<div class="modal fade" id="accidentDetailModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title fw-bold"><i class="bi bi-exclamation-triangle me-2"></i>Accident Report Detail</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="accidentDetailBody">
                <div class="text-center py-4"><div class="spinner-border text-warning" role="status"></div></div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary px-4 fw-semibold" data-bs-dismiss="modal"><i class="bi bi-x-lg me-1"></i> Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Group 7 Dispatch History Modal -->
<div class="modal fade" id="group7DispatchLogModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
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
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary px-4 fw-semibold" data-bs-dismiss="modal"><i class="bi bi-x-lg me-1"></i> Close</button>
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
    var modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('viewEvidenceModal'));
    modal.show();
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
    var modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('sendToGroup7ConfirmModal'));
    modal.show();
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
    var modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('chainOfCustodyModal'));
    modal.show();
    fetch(`evidence_ajax.php?action=chain&id=${evidenceId}`)
        .then(response => response.text())
        .then(data => {
            document.getElementById('chainOfCustodyDetails').innerHTML = data;
        });
}

function viewCctvDetail(cctvId) {
    var body = document.getElementById('cctvDetailBody');
    body.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-success" role="status"></div></div>';
    var modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('cctvDetailModal'));
    modal.show();
    
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
    var modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('accidentDetailModal'));
    modal.show();
    
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
        html += '<div class="detail-row d-flex justify-content-between"><span class="detail-label">Plate:</span><span class="detail-value"><span class="badge bg-secondary">' + (record.plate_number || 'N/A') + '</span></span></div>';
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

function submitUpdateStatusAjax(event, evidenceId) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Saving...';

    fetch('evidence_ajax.php?action=update_status', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
        if (data.success) {
            // Update status badge inside modal
            const badge = document.getElementById(`evidenceStatusBadge_${evidenceId}`);
            if (badge) {
                badge.textContent = data.new_status;
                badge.className = 'badge bg-' + (data.new_status === 'Released' ? 'success' : (data.new_status === 'Destroyed' ? 'danger' : 'primary'));
            }
            // Show inline success message
            const alertBox = document.getElementById(`updateStatusAlert_${evidenceId}`);
            if (alertBox) {
                alertBox.style.display = 'block';
                alertBox.className = 'alert alert-success py-2 small mb-2';
                alertBox.innerHTML = '<i class="bi bi-check-circle me-1"></i> ' + data.message;
                setTimeout(() => { alertBox.style.display = 'none'; }, 3500);
            }
            hideUpdateStatusForm(evidenceId);
            // Also refresh location if updated
            const locInput = form.querySelector('input[name="location"]');
            if (locInput) {
                const locDisplay = document.getElementById(`evidenceLocDisplay_${evidenceId}`);
                if (locDisplay) locDisplay.textContent = locInput.value;
            }
        } else {
            alert('Error: ' + (data.error || 'Failed to update status'));
        }
    })
    .catch(err => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
        alert('Network error while updating status.');
    });
}

function submitAddCustodyAjax(event, evidenceId) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Saving...';

    fetch('evidence_ajax.php?action=add_custody', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
        if (data.success) {
            const alertBox = document.getElementById(`addCustodyAlert_${evidenceId}`);
            if (alertBox) {
                alertBox.style.display = 'block';
                alertBox.className = 'alert alert-success py-2 small mb-2';
                alertBox.innerHTML = '<i class="bi bi-check-circle me-1"></i> ' + data.message;
                setTimeout(() => { alertBox.style.display = 'none'; }, 3500);
            }
            form.reset();
            hideAddCustodyForm(evidenceId);
        } else {
            alert('Error: ' + (data.error || 'Failed to add custody entry'));
        }
    })
    .catch(err => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
        alert('Network error while adding custody entry.');
    });
}

// Dropdown Dynamic Handlers for Evidence Creation
function handleWitnessSelect(select) {
    const customWrap = document.getElementById('customWitnessWrap');
    const customInput = document.getElementById('customWitnessInput');
    const descInput = document.getElementById('witnessDescriptionInput');
    
    if (select.value === '__custom__') {
        customWrap.style.display = 'block';
        customInput.required = true;
        customInput.focus();
    } else {
        customWrap.style.display = 'none';
        customInput.required = false;
        customInput.value = '';
        
        // Auto-fill witness description if registered witness selected
        const selectedOpt = select.options[select.selectedIndex];
        const statement = selectedOpt.getAttribute('data-statement') || '';
        if (statement && (!descInput.value || descInput.value.trim() === '')) {
            descInput.value = statement;
        }
    }
}

function handleSourceDeptSelect(select) {
    const wrap = document.getElementById('customSourceDeptWrap');
    if (select.value === 'Other') {
        wrap.style.display = 'block';
    } else {
        wrap.style.display = 'none';
    }
}

function handleReceivedFromSelect(select) {
    const wrap = document.getElementById('customReceivedFromWrap');
    if (select.value === 'Other') {
        wrap.style.display = 'block';
    } else {
        wrap.style.display = 'none';
    }
}

function handleStorageLocSelect(select) {
    const wrap = document.getElementById('customStorageLocWrap');
    if (select.value === 'Other') {
        wrap.style.display = 'block';
    } else {
        wrap.style.display = 'none';
    }
}

function handleSourceReferenceSelect(select) {
    const customWrap = document.getElementById('customSourceRefWrap');
    const customInput = document.getElementById('customSourceRefInput');
    
    if (select.value === '__custom__') {
        customWrap.style.display = 'block';
        customInput.required = true;
        customInput.focus();
    } else {
        customWrap.style.display = 'none';
        customInput.required = false;
        customInput.value = '';
        
        if (!select.value) return;
        
        const selectedOpt = select.options[select.selectedIndex];
        const sourceDept = selectedOpt.getAttribute('data-source-dept');
        const caseNo = selectedOpt.getAttribute('data-case-no');
        const location = selectedOpt.getAttribute('data-location');
        
        // Auto-select source department if available and department is not yet selected
        if (sourceDept) {
            const deptSelect = document.getElementById('sourceDeptSelect');
            if (deptSelect && (!deptSelect.value || deptSelect.value === '')) {
                for (let i = 0; i < deptSelect.options.length; i++) {
                    if (deptSelect.options[i].value.indexOf(sourceDept) !== -1 || sourceDept.indexOf(deptSelect.options[i].value) !== -1) {
                        deptSelect.selectedIndex = i;
                        break;
                    }
                }
            }
        }
        
        // Auto-fill location found if empty
        const locInput = document.querySelector('input[name="location_found"]');
        if (locInput && location && (!locInput.value || locInput.value.trim() === '')) {
            locInput.value = location;
        }
        
        // Auto-select related case if matching
        if (caseNo) {
            const caseSelect = document.querySelector('select[name="case_id"]');
            if (caseSelect) {
                for (let i = 0; i < caseSelect.options.length; i++) {
                    const optCase = caseSelect.options[i].getAttribute('data-case-number');
                    if (optCase === caseNo) {
                        caseSelect.selectedIndex = i;
                        updateCaseNumber(caseSelect);
                        break;
                    }
                }
            }
        }
    }
}

// ================= EVIDENCE VIEW ENGINE (PAGINATION, CAROUSEL, SEARCH) =================
let currentTablePage = 1;
let rowsPerPage = 10;
let filteredRows = [];

function initEvidenceCatalog() {
    const rows = document.querySelectorAll('#evidenceTableBody tr.evidence-row');
    filteredRows = Array.from(rows);
    renderTablePagination();
    
    // Set up Carousel slide change listener
    const carouselEl = document.getElementById('evidenceCatalogCarousel');
    if (carouselEl) {
        carouselEl.addEventListener('slide.bs.carousel', function(event) {
            const nextSlideIdx = event.to;
            const totalSlides = document.querySelectorAll('#evidenceCatalogCarousel .carousel-item').length;
            const totalRecs = parseInt('<?= count($evidence_records) ?>') || 0;
            
            // Update slide indicator badge
            const label = document.getElementById('carouselSlideLabel');
            if (label) label.textContent = `Slide ${nextSlideIdx + 1} of ${totalSlides}`;
            
            // Update range label
            const rangeLabel = document.getElementById('carouselItemRangeLabel');
            if (rangeLabel) {
                const startRec = (nextSlideIdx * 10) + 1;
                const endRec = Math.min((nextSlideIdx + 1) * 10, totalRecs);
                rangeLabel.textContent = `Showing ${startRec} - ${endRec} of ${totalRecs} total records`;
            }
            
            // Update indicator chips
            const chips = document.querySelectorAll('#carouselCustomIndicators .carousel-indicator-chip');
            chips.forEach((c, idx) => {
                if (idx === nextSlideIdx) c.classList.add('active');
                else c.classList.remove('active');
            });
        });
    }
}

function switchEvidenceView(viewType) {
    const tableView = document.getElementById('evidenceTableView');
    const carouselView = document.getElementById('evidenceCarouselView');
    const btnTable = document.getElementById('btnTableView');
    const btnCarousel = document.getElementById('btnCarouselView');
    
    if (viewType === 'carousel') {
        tableView.style.display = 'none';
        carouselView.style.display = 'block';
        btnTable.classList.remove('active');
        btnCarousel.classList.add('active');
    } else {
        carouselView.style.display = 'none';
        tableView.style.display = 'block';
        btnCarousel.classList.remove('active');
        btnTable.classList.add('active');
    }
}

function changePageSize(size) {
    rowsPerPage = parseInt(size) || 10;
    currentTablePage = 1;
    renderTablePagination();
}

let currentStatusFilter = 'all';

function applyStatusFilter(status, btn) {
    currentStatusFilter = (status || 'all').toLowerCase();
    
    // Update chip styling
    document.querySelectorAll('.filter-chip').forEach(c => {
        c.classList.remove('active');
        c.classList.remove('btn-success');
        c.classList.add('btn-outline-secondary');
    });
    if (btn) {
        btn.classList.add('active');
        btn.classList.remove('btn-outline-secondary');
    }
    
    filterEvidenceRecords();
}

function copyEvidenceTag(text, el) {
    if (!navigator.clipboard) return;
    navigator.clipboard.writeText(text).then(() => {
        const origTitle = el.getAttribute('title') || '';
        el.setAttribute('title', 'Copied!');
        const icon = el.querySelector('i');
        if (icon) {
            icon.className = 'bi bi-check-lg text-success';
            setTimeout(() => {
                icon.className = 'bi bi-clipboard text-muted';
                el.setAttribute('title', origTitle);
            }, 2000);
        }
    });
}

function filterEvidenceRecords() {
    const query = (document.getElementById('evidenceSearchInput')?.value || '').toLowerCase().trim();
    const allRows = document.querySelectorAll('#evidenceTableBody tr.evidence-row');
    const allCards = document.querySelectorAll('.evidence-carousel-card-col');
    
    filteredRows = [];
    allRows.forEach(row => {
        const rowStatus = (row.getAttribute('data-status') || '').toLowerCase();
        const text = (
            (row.getAttribute('data-evidence-no') || '') + ' ' +
            (row.getAttribute('data-case') || '') + ' ' +
            (row.getAttribute('data-type') || '') + ' ' +
            (row.getAttribute('data-desc') || '') + ' ' +
            (row.getAttribute('data-source') || '') + ' ' +
            (row.getAttribute('data-witness') || '') + ' ' +
            rowStatus
        ).toLowerCase();
        
        const matchesStatus = (currentStatusFilter === 'all' || rowStatus === currentStatusFilter);
        const matchesQuery = (!query || text.includes(query));
        
        if (matchesStatus && matchesQuery) {
            filteredRows.push(row);
        } else {
            row.style.display = 'none';
        }
    });
    
    // Filter carousel cards as well
    allCards.forEach(card => {
        const cardText = card.textContent.toLowerCase();
        const matchesQuery = (!query || cardText.includes(query));
        if (matchesQuery) {
            card.style.display = '';
        } else {
            card.style.display = 'none';
        }
    });
    
    currentTablePage = 1;
    renderTablePagination();
}

function renderTablePagination() {
    const total = filteredRows.length;
    const totalPages = Math.ceil(total / rowsPerPage) || 1;
    if (currentTablePage > totalPages) currentTablePage = totalPages;
    if (currentTablePage < 1) currentTablePage = 1;
    
    const startIdx = (currentTablePage - 1) * rowsPerPage;
    const endIdx = Math.min(startIdx + rowsPerPage, total);
    
    // Hide all rows first, show only current page slice
    const allRows = document.querySelectorAll('#evidenceTableBody tr.evidence-row');
    allRows.forEach(r => r.style.display = 'none');
    
    for (let i = startIdx; i < endIdx; i++) {
        if (filteredRows[i]) filteredRows[i].style.display = '';
    }
    
    // Update pagination info label
    const infoEl = document.getElementById('tablePaginationInfo');
    if (infoEl) {
        if (total === 0) {
            infoEl.textContent = 'Showing 0 to 0 of 0 entries';
        } else {
            infoEl.textContent = `Showing ${startIdx + 1} to ${endIdx} of ${total} entries`;
        }
    }
    
    // Render pagination buttons
    const controls = document.getElementById('tablePaginationControls');
    if (!controls) return;
    
    let html = '';
    // Previous button
    html += `<li class="page-item ${currentTablePage === 1 ? 'disabled' : ''}">
        <a class="page-link" href="javascript:void(0)" onclick="goToTablePage(${currentTablePage - 1})"><i class="bi bi-chevron-left"></i></a>
    </li>`;
    
    for (let p = 1; p <= totalPages; p++) {
        if (totalPages > 7 && Math.abs(p - currentTablePage) > 2 && p !== 1 && p !== totalPages) {
            if (p === 2 || p === totalPages - 1) {
                html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
            }
            continue;
        }
        html += `<li class="page-item ${p === currentTablePage ? 'active' : ''}">
            <a class="page-link" href="javascript:void(0)" onclick="goToTablePage(${p})">${p}</a>
        </li>`;
    }
    
    // Next button
    html += `<li class="page-item ${currentTablePage >= totalPages ? 'disabled' : ''}">
        <a class="page-link" href="javascript:void(0)" onclick="goToTablePage(${currentTablePage + 1})"><i class="bi bi-chevron-right"></i></a>
    </li>`;
    
    controls.innerHTML = html;
}

function goToTablePage(page) {
    currentTablePage = page;
    renderTablePagination();
}

document.addEventListener('DOMContentLoaded', function() {
    initEvidenceCatalog();
});
</script>

<?php include '../includes/footer.php'; ?>