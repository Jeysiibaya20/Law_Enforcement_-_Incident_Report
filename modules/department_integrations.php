<?php
require_once '../config/db_connect.php';
require_once '../modules/OperationalModuleIntegrator.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pdo = getDBConnection();
$integrator = new OperationalModuleIntegrator($pdo);

$page_title = 'Department Integrations Hub';
$base_url = '../';
require_once '../includes/header.php';

$message = '';
$messageType = '';

// Handle actions (Dispatching, simulation, manual sync)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'dispatch_cctv_to_group2') {
        $cctvData = [
            'request_id' => 'REQ-CCTV-' . date('Ymd') . '-' . rand(100, 999),
            'case_number' => trim($_POST['case_number'] ?? ('CASE-' . date('Ymd') . '-001')),
            'incident_type' => trim($_POST['incident_type'] ?? 'Vehicular Collision'),
            'location' => trim($_POST['location'] ?? 'Commonwealth Ave, Quezon City'),
            'camera_id' => trim($_POST['camera_id'] ?? 'CAM-G2-01'),
            'time_window_start' => date('Y-m-d H:i:s', strtotime('-2 hours')),
            'time_window_end' => date('Y-m-d H:i:s'),
            'reason' => trim($_POST['reason'] ?? 'Accident investigation cross-verification with Group 2.')
        ];
        try {
            $res = $integrator->dispatchCctvRequestToGroup2($cctvData);
            $message = "Successfully dispatched CCTV Request to Group 2! Request ID: #" . $res['request_id'];
            $messageType = "success";
        } catch (Exception $e) {
            $message = "Failed to dispatch CCTV Request: " . $e->getMessage();
            $messageType = "danger";
        }
    }

    if ($action === 'acknowledge_cctv_request') {
        $ackData = [
            'request_id' => trim($_POST['request_id'] ?? ''),
            'status' => 'Acknowledged by Group 2',
            'operator_name' => trim($_POST['operator_name'] ?? 'Officer Ramos (Group 2 Surveillance)'),
            'available_footage' => !empty($_POST['has_footage']),
            'footage_url' => trim($_POST['footage_url'] ?? 'https://storage.alertaraqc.com/group2/feeds/cctv_clip_882.mp4'),
            'notes' => trim($_POST['notes'] ?? 'Group 2 verified camera feed. 1080p footage available for handoff.')
        ];
        try {
            $res = $integrator->receiveCctvAcknowledgmentFromGroup2($ackData);
            $message = "Group 2 CCTV Request Acknowledgment recorded! Request Status: " . htmlspecialchars($ackData['status']);
            $messageType = "success";
        } catch (Exception $e) {
            $message = "Failed to record acknowledgment: " . $e->getMessage();
            $messageType = "danger";
        }
    }

    if ($action === 'dispatch_media_to_group7') {
        $evidenceId = intval($_POST['evidence_id'] ?? 0);
        $payload = [
            'evidence_id' => $evidenceId,
            'case_number' => trim($_POST['case_number'] ?? 'CASE-GEN-01'),
            'evidence_type' => trim($_POST['evidence_type'] ?? 'Photo & Video Package'),
            'file_name' => trim($_POST['file_name'] ?? 'evidence_scene_capture.mp4'),
            'file_url' => trim($_POST['file_url'] ?? 'https://storage.alertaraqc.com/group1/evidence/evidence_scene_capture.mp4'),
            'dispatched_by' => $_SESSION['admin_fullname'] ?? 'Group 1 Evidence Custodian',
            'timestamp' => date('Y-m-d H:i:s')
        ];
        try {
            $res = $integrator->dispatchEvidenceToGroup7Upload($payload);
            $message = "Dispatched Media Package to Group 7 Upload Endpoint! Response Status: " . ($res['status'] ?? 'Delivered');
            $messageType = "success";
        } catch (Exception $e) {
            $message = "Error sending media to Group 7: " . $e->getMessage();
            $messageType = "danger";
        }
    }
}

// Fetch integration metrics & logs
try {
    $totalAccidents = (int)$pdo->query("SELECT COUNT(*) FROM group2_accident_reports")->fetchColumn();
    $totalCctvRequests = (int)$pdo->query("SELECT COUNT(*) FROM group2_cctv_requests")->fetchColumn();
    $totalGroup7Uploads = (int)$pdo->query("SELECT COUNT(*) FROM group7_upload_dispatches")->fetchColumn();
    $totalIntegrationLogs = (int)$pdo->query("SELECT COUNT(*) FROM module_integration_logs")->fetchColumn();

    $recentAccidents = $pdo->query("SELECT * FROM group2_accident_reports ORDER BY created_at DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
    $recentCctvRequests = $pdo->query("SELECT * FROM group2_cctv_requests ORDER BY created_at DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
    $recentGroup7Dispatches = $pdo->query("SELECT * FROM group7_upload_dispatches ORDER BY created_at DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
    $recentIntegrationLogs = $pdo->query("SELECT * FROM module_integration_logs ORDER BY created_at DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $totalAccidents = 0; $totalCctvRequests = 0; $totalGroup7Uploads = 0; $totalIntegrationLogs = 0;
    $recentAccidents = []; $recentCctvRequests = []; $recentGroup7Dispatches = []; $recentIntegrationLogs = [];
}
?>

<style>
:root {
    --brand-emerald: #2e856e;
    --brand-dark-emerald: #1b5a56;
    --brand-light-emerald: #e6f4f1;
}

.integration-header {
    background: linear-gradient(135deg, var(--brand-dark-emerald) 0%, var(--brand-emerald) 100%);
    border-radius: 12px;
    color: #ffffff;
    padding: 24px;
    margin-bottom: 24px;
    box-shadow: 0 4px 15px rgba(46, 133, 110, 0.15);
}

.dept-card {
    border-radius: 12px;
    border: 1px solid rgba(46, 133, 110, 0.15);
    transition: all 0.25s ease-in-out;
    background: #ffffff;
}

.dept-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
    border-color: var(--brand-emerald);
}

.dept-icon-box {
    width: 48px;
    height: 48px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.4rem;
}

.status-online {
    background-color: #d1fae5;
    color: #065f46;
    border: 1px solid #a7f3d0;
}

.submodal-stack {
    z-index: 1060 !important;
}
</style>

<div class="main-content">
    <div class="content-container">
        <!-- Top Header Banner -->
        <div class="integration-header d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h2 class="h3 mb-1 fw-bold text-white"><i class="fas fa-network-wired me-2"></i>Department Integrations Hub</h2>
                <p class="mb-0 text-white-50">Designated child module connecting Group 1 (Law Enforcement) to partner operational departments.</p>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-light fw-bold" onclick="openLiveApiDispatchModal('group2')">
                    <i class="fas fa-bolt text-warning me-1"></i> Fast API Dispatch
                </button>
                <a href="../admin/external_integrations.php" class="btn btn-outline-light">
                    <i class="fas fa-sliders-h me-1"></i> Admin Settings
                </a>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?> alert-dismissible fade show shadow-sm" role="alert">
                <i class="fas <?= $messageType === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle' ?> me-2"></i>
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Quick Summary Metric Cards -->
        <div class="row g-3 mb-4">
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card dept-card h-100 p-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small fw-bold text-uppercase">Group 2 Inbound</span>
                        <div class="dept-icon-box bg-warning text-dark"><i class="fas fa-car-crash"></i></div>
                    </div>
                    <h3 class="fw-bold mb-0 text-dark"><?= $totalAccidents ?></h3>
                    <small class="text-muted">Accident tickets & violation reports</small>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card dept-card h-100 p-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small fw-bold text-uppercase">CCTV Handshakes</span>
                        <div class="dept-icon-box bg-info text-white"><i class="fas fa-video"></i></div>
                    </div>
                    <h3 class="fw-bold mb-0 text-dark"><?= $totalCctvRequests ?></h3>
                    <small class="text-muted">Group 1 ↔ Group 2 requests & acknowledgments</small>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card dept-card h-100 p-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small fw-bold text-uppercase">Group 7 Media Sent</span>
                        <div class="dept-icon-box bg-primary text-white"><i class="fas fa-cloud-upload-alt"></i></div>
                    </div>
                    <h3 class="fw-bold mb-0 text-dark"><?= $totalGroup7Uploads ?></h3>
                    <small class="text-muted">Photos & videos uploaded to Group 7</small>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card dept-card h-100 p-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small fw-bold text-uppercase">Total Dispatches</span>
                        <div class="dept-icon-box" style="background-color: var(--brand-emerald); color: #fff;"><i class="fas fa-sync-alt"></i></div>
                    </div>
                    <h3 class="fw-bold mb-0 text-dark"><?= $totalIntegrationLogs ?></h3>
                    <small class="text-muted">Live cross-department log entries</small>
                </div>
            </div>
        </div>

        <!-- Designated Departments Directory -->
        <h5 class="fw-bold text-dark mb-3"><i class="fas fa-building me-2 text-success"></i>Designated Partner Departments</h5>
        <div class="row g-3 mb-4">
            <!-- Group 2 Card -->
            <div class="col-md-6 col-lg-4">
                <div class="card dept-card h-100 p-3">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div class="d-flex align-items-center gap-2">
                            <div class="dept-icon-box bg-warning text-dark"><i class="fas fa-car"></i></div>
                            <div>
                                <h6 class="fw-bold mb-0 text-dark">Group 2: Accident & Violation</h6>
                                <small class="text-muted">Inbound Tickets & CCTV Feed</small>
                            </div>
                        </div>
                        <span class="badge status-online fw-bold">Online</span>
                    </div>
                    <p class="small text-muted mb-3">Receives live traffic incident tickets and coordinates bidirectional CCTV footage requests and acknowledgments.</p>
                    <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                        <span class="small text-muted">Channel: <code>api/receive_accident_report.php</code></span>
                        <button type="button" class="btn btn-sm btn-outline-success" onclick="openInspectDeptModal('group2')">
                            <i class="fas fa-eye me-1"></i> Manage
                        </button>
                    </div>
                </div>
            </div>

            <!-- Group 7 Card -->
            <div class="col-md-6 col-lg-4">
                <div class="card dept-card h-100 p-3">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div class="d-flex align-items-center gap-2">
                            <div class="dept-icon-box bg-primary text-white"><i class="fas fa-camera"></i></div>
                            <div>
                                <h6 class="fw-bold mb-0 text-dark">Group 7: Photo & Videos Upload</h6>
                                <small class="text-muted">Outbound Media & Evidence</small>
                            </div>
                        </div>
                        <span class="badge status-online fw-bold">Online</span>
                    </div>
                    <p class="small text-muted mb-3">Automatically dispatches collected scene photos, surveillance footage clips, and chain of custody digital evidence.</p>
                    <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                        <span class="small text-muted">Channel: <code>api/dispatch_evidence_group7.php</code></span>
                        <button type="button" class="btn btn-sm btn-outline-success" onclick="openInspectDeptModal('group7')">
                            <i class="fas fa-eye me-1"></i> Manage
                        </button>
                    </div>
                </div>
            </div>

            <!-- Group 3 Card -->
            <div class="col-md-6 col-lg-4">
                <div class="card dept-card h-100 p-3">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div class="d-flex align-items-center gap-2">
                            <div class="dept-icon-box bg-danger text-white"><i class="fas fa-ambulance"></i></div>
                            <div>
                                <h6 class="fw-bold mb-0 text-dark">Group 3: Resource & EMS Dispatch</h6>
                                <small class="text-muted">Emergency Services</small>
                            </div>
                        </div>
                        <span class="badge status-online fw-bold">Active</span>
                    </div>
                    <p class="small text-muted mb-3">Dispatches high-urgency incidents to medical first responders, ambulance units, and tactical police support.</p>
                    <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                        <span class="small text-muted">Channel: <code>api/dispatch_resource.php</code></span>
                        <button type="button" class="btn btn-sm btn-outline-success" onclick="openInspectDeptModal('group3')">
                            <i class="fas fa-eye me-1"></i> Manage
                        </button>
                    </div>
                </div>
            </div>

            <!-- Group 5 Card -->
            <div class="col-md-6 col-lg-6">
                <div class="card dept-card h-100 p-3">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div class="d-flex align-items-center gap-2">
                            <div class="dept-icon-box bg-info text-white"><i class="fas fa-map-marked-alt"></i></div>
                            <div>
                                <h6 class="fw-bold mb-0 text-dark">Group 5: Crime Mapping & Hotspots</h6>
                                <small class="text-muted">Geo-Spatial Analytics</small>
                            </div>
                        </div>
                        <span class="badge status-online fw-bold">Active</span>
                    </div>
                    <p class="small text-muted mb-3">Synchronizes geo-coordinates, incident classifications, and crime densities for district-wide heatmap generation.</p>
                    <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                        <span class="small text-muted">Channel: <code>api/sync_crimemap.php</code></span>
                        <button type="button" class="btn btn-sm btn-outline-success" onclick="openInspectDeptModal('group5')">
                            <i class="fas fa-eye me-1"></i> Manage
                        </button>
                    </div>
                </div>
            </div>

            <!-- Group 6 Card -->
            <div class="col-md-6 col-lg-6">
                <div class="card dept-card h-100 p-3">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div class="d-flex align-items-center gap-2">
                            <div class="dept-icon-box bg-secondary text-white"><i class="fas fa-bullhorn"></i></div>
                            <div>
                                <h6 class="fw-bold mb-0 text-dark">Group 6: Public Safety Bulletins</h6>
                                <small class="text-muted">Advisories & Community Alerts</small>
                            </div>
                        </div>
                        <span class="badge status-online fw-bold">Active</span>
                    </div>
                    <p class="small text-muted mb-3">Publishes verified incident warnings and community awareness campaigns to social and mobile channels.</p>
                    <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                        <span class="small text-muted">Channel: <code>api/publish_campaign.php</code></span>
                        <button type="button" class="btn btn-sm btn-outline-success" onclick="openInspectDeptModal('group6')">
                            <i class="fas fa-eye me-1"></i> Manage
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Master Tabs for Records & Dual Views (10 items / page & Carousel) -->
        <div class="card shadow-sm border-0 mb-4" style="border-radius: 12px; overflow: hidden; border: 1px solid rgba(46,133,110,0.2) !important;">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2 py-3 text-white" style="background: linear-gradient(135deg, #1b5a56, #2e856e) !important;">
                <div class="d-flex align-items-center gap-2">
                    <h5 class="mb-0 fw-bold text-white"><i class="fas fa-exchange-alt me-2"></i>Cross-Department Channel Records</h5>
                </div>

                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <!-- Search Input -->
                    <div class="input-group input-group-sm" style="width: 220px;">
                        <span class="input-group-text bg-white text-dark border-0"><i class="bi bi-search"></i></span>
                        <input type="text" id="deptSearchInput" class="form-control border-0" placeholder="Search records..." onkeyup="filterDeptRecords()">
                    </div>

                    <!-- Page Size Selector -->
                    <select id="deptPageSizeSelect" class="form-select form-select-sm border-0" style="width: auto;" onchange="changeDeptPageSize(this.value)">
                        <option value="10" selected>10 per page</option>
                        <option value="25">25 per page</option>
                        <option value="50">50 per page</option>
                        <option value="1000">Show All</option>
                    </select>

                    <!-- View Switcher -->
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" class="btn btn-light active fw-semibold" id="btnDeptTableView" onclick="switchDeptView('table')">
                            <i class="bi bi-table me-1"></i> Table View
                        </button>
                        <button type="button" class="btn btn-light fw-semibold" id="btnDeptCarouselView" onclick="switchDeptView('carousel')">
                            <i class="bi bi-view-stacked me-1"></i> Carousel (10/Slide)
                        </button>
                    </div>
                </div>
            </div>

            <!-- Tab Navigation -->
            <div class="bg-light px-3 pt-2 border-bottom">
                <ul class="nav nav-pills" id="deptTabNav" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active fw-bold py-2" id="tab-accidents-btn" data-bs-toggle="pill" data-bs-target="#tab-accidents" type="button" onclick="setActiveDeptTab('accidents')">
                            <i class="fas fa-car-crash me-1 text-warning"></i> Group 2 Accidents (<?= count($recentAccidents) ?>)
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link fw-bold py-2" id="tab-cctv-btn" data-bs-toggle="pill" data-bs-target="#tab-cctv" type="button" onclick="setActiveDeptTab('cctv')">
                            <i class="fas fa-video me-1 text-info"></i> CCTV Handshakes (<?= count($recentCctvRequests) ?>)
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link fw-bold py-2" id="tab-group7-btn" data-bs-toggle="pill" data-bs-target="#tab-group7" type="button" onclick="setActiveDeptTab('group7')">
                            <i class="fas fa-cloud-upload-alt me-1 text-primary"></i> Group 7 Media Sent (<?= count($recentGroup7Dispatches) ?>)
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link fw-bold py-2" id="tab-logs-btn" data-bs-toggle="pill" data-bs-target="#tab-logs" type="button" onclick="setActiveDeptTab('logs')">
                            <i class="fas fa-list-alt me-1 text-success"></i> Integration Logs (<?= count($recentIntegrationLogs) ?>)
                        </button>
                    </li>
                </ul>
            </div>

            <div class="card-body p-0">
                <div class="tab-content" id="deptTabContent">
                    <!-- ================= TAB 1: GROUP 2 ACCIDENTS ================= -->
                    <div class="tab-pane fade show active" id="tab-accidents" role="tabpanel">
                        <!-- Table View -->
                        <div id="accidentsTableView" class="p-3">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0" id="accidentsTable">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>Ticket #</th>
                                            <th>Case Ref</th>
                                            <th>Violator Name</th>
                                            <th>Violation Type</th>
                                            <th>Plate #</th>
                                            <th>Severity</th>
                                            <th>Fine</th>
                                            <th>Date Received</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="accidentsTableBody">
                                        <?php if (empty($recentAccidents)): ?>
                                            <tr><td colspan="10" class="text-center text-muted py-4">No Group 2 accident reports received yet.</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($recentAccidents as $idx => $acc): ?>
                                                <tr class="accident-row"
                                                    data-index="<?= $idx ?>"
                                                    data-ticket="<?= htmlspecialchars(strtolower($acc['ticket_number'])) ?>"
                                                    data-case="<?= htmlspecialchars(strtolower($acc['case_number'] ?? '')) ?>"
                                                    data-violator="<?= htmlspecialchars(strtolower($acc['violator_name'])) ?>"
                                                    data-violation="<?= htmlspecialchars(strtolower($acc['violation_type'])) ?>"
                                                    data-plate="<?= htmlspecialchars(strtolower($acc['plate_number'] ?? '')) ?>">
                                                    <td class="text-muted small fw-bold"><?= $idx + 1 ?></td>
                                                    <td><strong class="text-warning text-dark"><?= htmlspecialchars($acc['ticket_number']) ?></strong></td>
                                                    <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($acc['case_number'] ?? 'N/A') ?></span></td>
                                                    <td><?= htmlspecialchars($acc['violator_name']) ?></td>
                                                    <td><small><?= htmlspecialchars(substr($acc['violation_type'], 0, 30)) ?></small></td>
                                                    <td><span class="badge bg-secondary"><?= htmlspecialchars($acc['plate_number'] ?: 'N/A') ?></span></td>
                                                    <td><span class="badge bg-<?= ($acc['severity_level'] ?? '') === 'High' ? 'danger' : 'warning text-dark' ?>"><?= htmlspecialchars($acc['severity_level'] ?? 'Medium') ?></span></td>
                                                    <td><strong>₱<?= number_format($acc['fine_amount'] ?? 0, 2) ?></strong></td>
                                                    <td><small class="text-muted"><?= date('M d, Y', strtotime($acc['created_at'])) ?></small></td>
                                                    <td>
                                                        <div class="btn-group btn-group-sm">
                                                            <button type="button" class="btn btn-outline-success" onclick="inspectAccidentRecord(<?= htmlspecialchars(json_encode($acc)) ?>)" title="View Details">
                                                                <i class="fas fa-eye"></i>
                                                            </button>
                                                            <button type="button" class="btn btn-outline-primary" onclick="openPayloadViewerSubModal('Group 2 Inbound Accident', <?= htmlspecialchars(json_encode($acc)) ?>)" title="Raw JSON Payload">
                                                                <i class="fas fa-code"></i>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination Bar -->
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3 pt-2 border-top">
                                <div class="text-muted small" id="accidentsPaginationInfo">
                                    Showing 1 to 10 of <?= count($recentAccidents) ?> entries
                                </div>
                                <nav aria-label="Accidents pagination">
                                    <ul class="pagination pagination-sm mb-0" id="accidentsPaginationControls"></ul>
                                </nav>
                            </div>
                        </div>

                        <!-- Carousel View -->
                        <div id="accidentsCarouselView" class="p-3" style="display: none;">
                            <?php 
                                $accBatches = array_chunk($recentAccidents, 10);
                                $totalAccSlides = count($accBatches);
                            ?>
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3 p-2 bg-light rounded border">
                                <span class="badge text-white px-3 py-2 fs-6" style="background: #2e856e;">
                                    <i class="bi bi-view-stacked me-1"></i> <span id="accidentsCarouselSlideLabel">Slide 1 of <?= max(1, $totalAccSlides) ?></span>
                                </span>
                                <div class="d-flex align-items-center gap-2">
                                    <button class="btn btn-sm btn-success rounded-circle shadow-sm" type="button" data-bs-target="#accidentsCarousel" data-bs-slide="prev" style="width:34px; height:34px; background-color: #2e856e; border-color: #2e856e;">
                                        <i class="bi bi-chevron-left"></i>
                                    </button>
                                    <button class="btn btn-sm btn-success rounded-circle shadow-sm" type="button" data-bs-target="#accidentsCarousel" data-bs-slide="next" style="width:34px; height:34px; background-color: #2e856e; border-color: #2e856e;">
                                        <i class="bi bi-chevron-right"></i>
                                    </button>
                                </div>
                            </div>
                            <div id="accidentsCarousel" class="carousel slide" data-bs-interval="false">
                                <div class="carousel-inner">
                                    <?php if (empty($accBatches)): ?>
                                        <div class="carousel-item active"><div class="text-center py-5 text-muted">No accident records</div></div>
                                    <?php else: ?>
                                        <?php foreach ($accBatches as $sIdx => $batch): ?>
                                            <div class="carousel-item <?= $sIdx === 0 ? 'active' : '' ?>">
                                                <div class="row g-3">
                                                    <?php foreach ($batch as $item): ?>
                                                        <div class="col-md-6 col-lg-6">
                                                            <div class="card h-100 border shadow-sm">
                                                                <div class="card-header bg-light d-flex justify-content-between align-items-center py-2 px-3">
                                                                    <strong class="text-warning text-dark"><?= htmlspecialchars($item['ticket_number']) ?></strong>
                                                                    <span class="badge bg-success">₱<?= number_format($item['fine_amount'] ?? 0, 2) ?></span>
                                                                </div>
                                                                <div class="card-body p-3 small">
                                                                    <div class="row g-2">
                                                                        <div class="col-6"><strong>Violator:</strong> <?= htmlspecialchars($item['violator_name']) ?></div>
                                                                        <div class="col-6"><strong>Plate:</strong> <?= htmlspecialchars($item['plate_number'] ?: 'N/A') ?></div>
                                                                        <div class="col-12"><strong>Violation:</strong> <?= htmlspecialchars($item['violation_type']) ?></div>
                                                                        <div class="col-12"><strong>Location:</strong> <?= htmlspecialchars($item['location'] ?: 'N/A') ?></div>
                                                                    </div>
                                                                </div>
                                                                <div class="card-footer bg-light d-flex justify-content-end gap-1 py-2 px-3">
                                                                    <button type="button" class="btn btn-sm btn-outline-success" onclick="inspectAccidentRecord(<?= htmlspecialchars(json_encode($item)) ?>)"><i class="fas fa-eye me-1"></i>Inspect</button>
                                                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="openPayloadViewerSubModal('Group 2 Payload', <?= htmlspecialchars(json_encode($item)) ?>)"><i class="fas fa-code me-1"></i>JSON</button>
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

                    <!-- ================= TAB 2: CCTV HANDSHAKES ================= -->
                    <div class="tab-pane fade" id="tab-cctv" role="tabpanel">
                        <div class="p-3">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-video text-info me-2"></i>Group 1 ↔ Group 2 CCTV Workflow Handshakes</h6>
                                <button type="button" class="btn btn-sm btn-success" onclick="openNewCctvRequestSubModal()">
                                    <i class="fas fa-plus-circle me-1"></i> Request CCTV Footage from Group 2
                                </button>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>Request ID</th>
                                            <th>Case #</th>
                                            <th>Location</th>
                                            <th>Camera ID</th>
                                            <th>Status</th>
                                            <th>Footage URL</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($recentCctvRequests)): ?>
                                            <tr><td colspan="8" class="text-center text-muted py-4">No CCTV requests or acknowledgments logged.</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($recentCctvRequests as $idx => $cctv): ?>
                                                <tr>
                                                    <td><?= $idx + 1 ?></td>
                                                    <td><strong class="text-primary"><?= htmlspecialchars($cctv['request_id']) ?></strong></td>
                                                    <td><?= htmlspecialchars($cctv['case_number'] ?? 'N/A') ?></td>
                                                    <td><small><?= htmlspecialchars($cctv['location'] ?? 'N/A') ?></small></td>
                                                    <td><code><?= htmlspecialchars($cctv['camera_id'] ?? 'Auto') ?></code></td>
                                                    <td><span class="badge bg-<?= strpos($cctv['status'], 'Acknowledged') !== false ? 'success' : 'warning text-dark' ?>"><?= htmlspecialchars($cctv['status']) ?></span></td>
                                                    <td>
                                                        <?php if (!empty($cctv['footage_url'])): ?>
                                                            <button type="button" class="btn btn-sm btn-outline-info" onclick="openMediaPlayerSubModal('<?= htmlspecialchars($cctv['footage_url']) ?>', '<?= htmlspecialchars($cctv['request_id']) ?>')">
                                                                <i class="fas fa-play-circle me-1"></i> Watch
                                                            </button>
                                                        <?php else: ?>
                                                            <span class="text-muted small">Pending Stream</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <button type="button" class="btn btn-sm btn-outline-success" onclick="openCctvAcknowledgeSubModal('<?= htmlspecialchars($cctv['request_id']) ?>')">
                                                            <i class="fas fa-handshake me-1"></i> Acknowledge
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

                    <!-- ================= TAB 3: GROUP 7 MEDIA SENT ================= -->
                    <div class="tab-pane fade" id="tab-group7" role="tabpanel">
                        <div class="p-3">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-cloud-upload-alt text-primary me-2"></i>Outbound Photos & Videos Sent to Group 7</h6>
                                <button type="button" class="btn btn-sm btn-primary" onclick="openGroup7MediaUploadSubModal()">
                                    <i class="fas fa-upload me-1"></i> Dispatch New Media to Group 7
                                </button>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>Evidence Ref</th>
                                            <th>Case #</th>
                                            <th>File Name</th>
                                            <th>Media Type</th>
                                            <th>Dispatched By</th>
                                            <th>Status</th>
                                            <th>Date Dispatched</th>
                                            <th>Preview</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($recentGroup7Dispatches)): ?>
                                            <tr><td colspan="9" class="text-center text-muted py-4">No media dispatched to Group 7 yet.</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($recentGroup7Dispatches as $idx => $g7): ?>
                                                <tr>
                                                    <td><?= $idx + 1 ?></td>
                                                    <td><strong>#EV-<?= $g7['evidence_id'] ?></strong></td>
                                                    <td><?= htmlspecialchars($g7['case_number'] ?? 'N/A') ?></td>
                                                    <td><code><?= htmlspecialchars($g7['file_name'] ?? 'media_clip.mp4') ?></code></td>
                                                    <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($g7['evidence_type'] ?? 'Media') ?></span></td>
                                                    <td><small><?= htmlspecialchars($g7['dispatched_by'] ?? 'Officer') ?></small></td>
                                                    <td><span class="badge bg-success"><?= htmlspecialchars($g7['status'] ?? 'Delivered') ?></span></td>
                                                    <td><small class="text-muted"><?= date('M d, Y H:i', strtotime($g7['created_at'])) ?></small></td>
                                                    <td>
                                                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="openMediaPlayerSubModal('<?= htmlspecialchars($g7['file_url'] ?? '') ?>', '<?= htmlspecialchars($g7['file_name'] ?? '') ?>')">
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

                    <!-- ================= TAB 4: INTEGRATION LOGS ================= -->
                    <div class="tab-pane fade" id="tab-logs" role="tabpanel">
                        <div class="p-3">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>Source</th>
                                            <th>Target</th>
                                            <th>Action Type</th>
                                            <th>Status</th>
                                            <th>Payload Preview</th>
                                            <th>Timestamp</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($recentIntegrationLogs)): ?>
                                            <tr><td colspan="7" class="text-center text-muted py-4">No integration logs recorded.</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($recentIntegrationLogs as $idx => $log): ?>
                                                <tr>
                                                    <td><?= $idx + 1 ?></td>
                                                    <td><span class="badge bg-secondary"><?= htmlspecialchars($log['source_module'] ?? 'Group 1') ?></span></td>
                                                    <td><span class="badge bg-info text-white"><?= htmlspecialchars($log['target_module'] ?? 'Partner API') ?></span></td>
                                                    <td><code><?= htmlspecialchars($log['action_type'] ?? 'SYNC') ?></code></td>
                                                    <td><span class="badge bg-<?= ($log['status'] ?? '') === 'Success' ? 'success' : 'secondary' ?>"><?= htmlspecialchars($log['status'] ?? 'OK') ?></span></td>
                                                    <td>
                                                        <button type="button" class="btn btn-sm btn-outline-secondary py-0" onclick="openPayloadViewerSubModal('Integration Log #<?= $log['id'] ?>', <?= htmlspecialchars(json_encode(json_decode($log['payload'] ?? '{}', true) ?: $log['payload'])) ?>)">
                                                            <i class="fas fa-code me-1"></i> Inspect
                                                        </button>
                                                    </td>
                                                    <td><small class="text-muted"><?= date('M d, Y H:i:s', strtotime($log['created_at'])) ?></small></td>
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
</div>

<!-- ================= PARENT MODAL: INSPECT DEPARTMENT MODAL ================= -->
<div class="modal fade" id="inspectDepartmentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px; overflow: hidden;">
            <div class="modal-header text-white" style="background: linear-gradient(135deg, #1b5a56, #2e856e);">
                <h5 class="modal-title fw-bold" id="inspectDeptTitle"><i class="fas fa-building me-2"></i>Department Management</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" id="inspectDeptBody">
                <!-- Dynamically injected via JS -->
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-success" id="inspectDeptActionBtn"><i class="fas fa-bolt me-1"></i>Trigger Action</button>
            </div>
        </div>
    </div>
</div>

<!-- ================= SUB-MODAL 1: RAW JSON PAYLOAD VIEWER ================= -->
<div class="modal fade submodal-stack" id="payloadViewerSubModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px; overflow: hidden; border: 2px solid #2e856e !important;">
            <div class="modal-header text-white" style="background: #1e293b;">
                <h5 class="modal-title fw-bold" id="payloadViewerTitle"><i class="fas fa-code me-2 text-warning"></i>Raw JSON Payload Inspector</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 bg-dark text-light">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="small text-muted">Formatted JSON schema representation</span>
                    <button type="button" class="btn btn-sm btn-outline-warning" onclick="copyPayloadToClipboard()"><i class="fas fa-copy me-1"></i>Copy JSON</button>
                </div>
                <pre class="p-3 bg-black rounded text-success" id="payloadPreArea" style="max-height: 400px; overflow-y: auto; font-family: monospace; font-size: 0.85rem; border: 1px solid #334155;"></pre>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Back</button>
            </div>
        </div>
    </div>
</div>

<!-- ================= SUB-MODAL 2: LIVE API DISPATCH BUILDER ================= -->
<div class="modal fade submodal-stack" id="liveApiDispatchSubModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px; overflow: hidden; border: 2px solid #2e856e !important;">
            <div class="modal-header text-white" style="background: linear-gradient(135deg, #1b5a56, #2e856e);">
                <h5 class="modal-title fw-bold"><i class="fas fa-paper-plane me-2"></i>Live API Dispatcher</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="dispatch_cctv_to_group2">
                <div class="modal-body p-4">
                    <div class="alert alert-info py-2 small mb-3">
                        <i class="fas fa-info-circle me-1"></i> Dispatching live handshake payload from Group 1 (Law Enforcement) to Group 2 (Accident & Violation Reporting).
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Case Reference Number</label>
                            <input type="text" name="case_number" class="form-control" value="CASE-<?= date('Ymd') ?>-001" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Incident Classification</label>
                            <select name="incident_type" class="form-select">
                                <option value="Vehicular Collision">Vehicular Collision</option>
                                <option value="Hit and Run">Hit and Run</option>
                                <option value="Traffic Obstruction / Dispute">Traffic Obstruction / Dispute</option>
                                <option value="Reckless Imprudence">Reckless Imprudence</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Incident Geolocation</label>
                            <input type="text" name="location" class="form-control" value="Commonwealth Ave, Quezon City" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Target Camera ID</label>
                            <input type="text" name="camera_id" class="form-control" value="CAM-QC-D1-088" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">Reason for Request</label>
                            <textarea name="reason" class="form-control" rows="2" required>Cross-referencing surveillance feed with Group 2 accident report ticket.</textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success px-4 fw-bold" style="background-color: #2e856e; border-color: #2e856e;">
                        <i class="fas fa-send me-1"></i> Send Request to Group 2
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ================= SUB-MODAL 3: CCTV ACKNOWLEDGMENT SUB-MODAL ================= -->
<div class="modal fade submodal-stack" id="cctvAcknowledgeSubModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px; overflow: hidden; border: 2px solid #2e856e !important;">
            <div class="modal-header text-white" style="background: linear-gradient(135deg, #1b5a56, #2e856e);">
                <h5 class="modal-title fw-bold"><i class="fas fa-handshake me-2"></i>Group 2 CCTV Acknowledgment</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="acknowledge_cctv_request">
                <input type="hidden" name="request_id" id="ackRequestIdInput" value="">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Request Identifier</label>
                        <input type="text" id="ackRequestIdDisplay" class="form-control bg-light" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Group 2 Operator Name</label>
                        <input type="text" name="operator_name" class="form-control" value="Officer Ramos (Surveillance Dispatcher)" required>
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="has_footage" id="hasFootageCheck" value="1" checked>
                        <label class="form-check-label fw-bold" for="hasFootageCheck">Footage Available for Transmission</label>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Footage URL / Storage Link</label>
                        <input type="text" name="footage_url" class="form-control" value="https://storage.alertaraqc.com/group2/feeds/cctv_clip_sample.mp4">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Handshake Verification Notes</label>
                        <textarea name="notes" class="form-control" rows="2">Group 2 surveillance operator verified camera feed and prepared 1080p footage for Group 1 evidence vault.</textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success fw-bold" style="background-color: #2e856e; border-color: #2e856e;">
                        <i class="fas fa-check-circle me-1"></i> Save Acknowledgment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ================= SUB-MODAL 4: MEDIA PLAYER PREVIEWER ================= -->
<div class="modal fade submodal-stack" id="mediaPlayerSubModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg bg-dark text-white" style="border-radius: 12px; overflow: hidden; border: 2px solid #2e856e !important;">
            <div class="modal-header border-secondary">
                <h5 class="modal-title fw-bold text-success" id="mediaPlayerTitle"><i class="fas fa-play-circle me-2"></i>Media Evidence Stream</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <div class="ratio ratio-16x9 bg-black rounded border border-secondary mb-3 d-flex align-items-center justify-content-center">
                    <div class="p-5 text-center text-muted">
                        <i class="fas fa-film fa-3x mb-3 text-success"></i>
                        <h6 class="text-white" id="mediaPlayerSourceLabel">Live Digital Evidence Player</h6>
                        <p class="small text-muted mb-0">Secure authenticated playback stream connected to Group 1 & Group 7 Media Vault.</p>
                        <div class="mt-3">
                            <span class="badge bg-success"><i class="fas fa-shield-alt me-1"></i> Chain of Custody Verified</span>
                            <span class="badge bg-primary ms-1"><i class="fas fa-lock me-1"></i> 256-Bit Encrypted</span>
                        </div>
                    </div>
                </div>
                <div class="text-start bg-secondary bg-opacity-25 rounded p-3 small">
                    <strong>Stream Source:</strong> <code id="mediaPlayerUrlDisplay" class="text-warning"></code>
                </div>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- ================= SUB-MODAL 5: GROUP 7 MEDIA UPLOAD ================= -->
<div class="modal fade submodal-stack" id="group7MediaUploadSubModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px; overflow: hidden; border: 2px solid #2e856e !important;">
            <div class="modal-header text-white" style="background: linear-gradient(135deg, #1b5a56, #2e856e);">
                <h5 class="modal-title fw-bold"><i class="fas fa-cloud-upload-alt me-2"></i>Dispatch Media to Group 7</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="dispatch_media_to_group7">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Evidence ID</label>
                        <input type="number" name="evidence_id" class="form-control" value="<?= rand(100, 999) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Case Number</label>
                        <input type="text" name="case_number" class="form-control" value="CASE-<?= date('Ymd') ?>-007" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Media Type</label>
                        <select name="evidence_type" class="form-select">
                            <option value="Photo (High Resolution Scene Capture)">Photo (High Resolution Scene Capture)</option>
                            <option value="Video (Surveillance Clip)">Video (Surveillance Clip)</option>
                            <option value="Audio Recording (Sworn Testimony)">Audio Recording (Sworn Testimony)</option>
                            <option value="Complete Evidence Package">Complete Evidence Package</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">File Name</label>
                        <input type="text" name="file_name" class="form-control" value="scene_evidence_<?= date('Ymd_His') ?>.mp4" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Remote File Link</label>
                        <input type="text" name="file_url" class="form-control" value="https://storage.alertaraqc.com/group1/evidence/evidence_file.mp4" required>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold">
                        <i class="fas fa-paper-plane me-1"></i> Dispatch to Group 7
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// ================= DEPARTMENT RECORDS VIEW ENGINE =================
let currentDeptPage = 1;
let deptRowsPerPage = 10;
let activeDeptTab = 'accidents';
let filteredAccidentRows = [];

function initDeptCatalog() {
    const rows = document.querySelectorAll('#accidentsTableBody tr.accident-row');
    filteredAccidentRows = Array.from(rows);
    renderDeptPagination();
}

function switchDeptView(viewType) {
    const tableView = document.getElementById('accidentsTableView');
    const carouselView = document.getElementById('accidentsCarouselView');
    const btnTable = document.getElementById('btnDeptTableView');
    const btnCarousel = document.getElementById('btnDeptCarouselView');

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

function changeDeptPageSize(size) {
    deptRowsPerPage = parseInt(size) || 10;
    currentDeptPage = 1;
    renderDeptPagination();
}

function filterDeptRecords() {
    const query = (document.getElementById('deptSearchInput')?.value || '').toLowerCase().trim();
    const allRows = document.querySelectorAll('#accidentsTableBody tr.accident-row');

    filteredAccidentRows = [];
    allRows.forEach(row => {
        const text = (
            (row.getAttribute('data-ticket') || '') + ' ' +
            (row.getAttribute('data-case') || '') + ' ' +
            (row.getAttribute('data-violator') || '') + ' ' +
            (row.getAttribute('data-violation') || '') + ' ' +
            (row.getAttribute('data-plate') || '')
        ).toLowerCase();

        if (!query || text.includes(query)) {
            filteredAccidentRows.push(row);
        } else {
            row.style.display = 'none';
        }
    });

    currentDeptPage = 1;
    renderDeptPagination();
}

function renderDeptPagination() {
    const total = filteredAccidentRows.length;
    const totalPages = Math.ceil(total / deptRowsPerPage) || 1;
    if (currentDeptPage > totalPages) currentDeptPage = totalPages;
    if (currentDeptPage < 1) currentDeptPage = 1;

    const startIdx = (currentDeptPage - 1) * deptRowsPerPage;
    const endIdx = Math.min(startIdx + deptRowsPerPage, total);

    const allRows = document.querySelectorAll('#accidentsTableBody tr.accident-row');
    allRows.forEach(r => r.style.display = 'none');

    for (let i = startIdx; i < endIdx; i++) {
        if (filteredAccidentRows[i]) filteredAccidentRows[i].style.display = '';
    }

    const infoEl = document.getElementById('accidentsPaginationInfo');
    if (infoEl) {
        if (total === 0) {
            infoEl.textContent = 'Showing 0 to 0 of 0 entries';
        } else {
            infoEl.textContent = `Showing ${startIdx + 1} to ${endIdx} of ${total} entries`;
        }
    }

    const controls = document.getElementById('accidentsPaginationControls');
    if (!controls) return;

    let html = '';
    html += `<li class="page-item ${currentDeptPage === 1 ? 'disabled' : ''}">
        <a class="page-link" href="javascript:void(0)" onclick="goToDeptPage(${currentDeptPage - 1})"><i class="bi bi-chevron-left"></i></a>
    </li>`;

    for (let p = 1; p <= totalPages; p++) {
        if (totalPages > 7 && Math.abs(p - currentDeptPage) > 2 && p !== 1 && p !== totalPages) {
            if (p === 2 || p === totalPages - 1) {
                html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
            }
            continue;
        }
        html += `<li class="page-item ${p === currentDeptPage ? 'active' : ''}">
            <a class="page-link" href="javascript:void(0)" onclick="goToDeptPage(${p})">${p}</a>
        </li>`;
    }

    html += `<li class="page-item ${currentDeptPage >= totalPages ? 'disabled' : ''}">
        <a class="page-link" href="javascript:void(0)" onclick="goToDeptPage(${currentDeptPage + 1})"><i class="bi bi-chevron-right"></i></a>
    </li>`;

    controls.innerHTML = html;
}

function goToDeptPage(page) {
    currentDeptPage = page;
    renderDeptPagination();
}

function setActiveDeptTab(tabName) {
    activeDeptTab = tabName;
}

// ================= SUB-MODALS CONTROLLERS =================
let currentPayloadData = null;

function inspectAccidentRecord(acc) {
    const title = document.getElementById('inspectDeptTitle');
    const body = document.getElementById('inspectDeptBody');
    const btn = document.getElementById('inspectDeptActionBtn');

    title.innerHTML = '<i class="fas fa-car-crash me-2 text-warning"></i>Accident Ticket #' + (acc.ticket_number || 'N/A');
    
    let html = '<div class="row g-3">';
    html += '<div class="col-md-6"><span class="text-muted small text-uppercase fw-bold d-block">Violator Name</span><strong>' + (acc.violator_name || 'N/A') + '</strong></div>';
    html += '<div class="col-md-6"><span class="text-muted small text-uppercase fw-bold d-block">License Plate</span><span class="badge bg-secondary">' + (acc.plate_number || 'N/A') + '</span></div>';
    html += '<div class="col-md-6"><span class="text-muted small text-uppercase fw-bold d-block">Violation Type</span><span>' + (acc.violation_type || 'N/A') + '</span></div>';
    html += '<div class="col-md-6"><span class="text-muted small text-uppercase fw-bold d-block">Fine Amount</span><strong class="text-success">₱' + parseFloat(acc.fine_amount || 0).toLocaleString('en-PH', {minimumFractionDigits: 2}) + '</strong></div>';
    html += '<div class="col-12"><span class="text-muted small text-uppercase fw-bold d-block">Incident Location</span><span>' + (acc.location || 'N/A') + '</span></div>';
    html += '</div>';

    html += '<div class="mt-4 pt-3 border-top d-flex gap-2">';
    html += '<button type="button" class="btn btn-outline-primary btn-sm" onclick="openPayloadViewerSubModal(\'Ticket #' + acc.ticket_number + '\', ' + JSON.stringify(acc).replace(/"/g, '&quot;') + ')"><i class="fas fa-code me-1"></i>View Raw Payload</button>';
    html += '<button type="button" class="btn btn-outline-info btn-sm" onclick="openNewCctvRequestSubModal(\'' + (acc.location || '') + '\')"><i class="fas fa-video me-1"></i>Request CCTV for Location</button>';
    html += '</div>';

    body.innerHTML = html;
    btn.onclick = function() { openNewCctvRequestSubModal(acc.location || ''); };

    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('inspectDepartmentModal'));
    modal.show();
}

function openInspectDeptModal(dept) {
    const title = document.getElementById('inspectDeptTitle');
    const body = document.getElementById('inspectDeptBody');
    const btn = document.getElementById('inspectDeptActionBtn');

    if (dept === 'group2') {
        title.innerHTML = '<i class="fas fa-car me-2 text-warning"></i>Group 2: Accident & Violation Reporting';
        body.innerHTML = '<p><strong>Designated Capabilities:</strong> Inbound accident ticket receiver, live traffic violation syncing, CCTV request dispatcher & acknowledgment tracker.</p><div class="bg-light p-3 rounded"><h6>Active Endpoints:</h6><code>/api/receive_accident_report.php</code><br><code>/api/receive_cctv_acknowledgment.php</code></div>';
        btn.onclick = function() { openLiveApiDispatchModal('group2'); };
    } else if (dept === 'group7') {
        title.innerHTML = '<i class="fas fa-camera me-2 text-primary"></i>Group 7: Photo & Videos Upload';
        body.innerHTML = '<p><strong>Designated Capabilities:</strong> Outbound evidence dispatch, high-resolution photo archiving, video chain-of-custody.</p><div class="bg-light p-3 rounded"><h6>Active Endpoints:</h6><code>/api/dispatch_evidence_group7.php</code></div>';
        btn.onclick = function() { openGroup7MediaUploadSubModal(); };
    } else {
        title.innerHTML = '<i class="fas fa-building me-2 text-success"></i>Partner Department ' + dept.toUpperCase();
        body.innerHTML = '<p>Active automated bi-directional channel connected to Group 1 Incident Reporting engine.</p>';
        btn.onclick = function() { openLiveApiDispatchModal(dept); };
    }

    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('inspectDepartmentModal'));
    modal.show();
}

function openPayloadViewerSubModal(titleText, payloadObj) {
    currentPayloadData = payloadObj;
    document.getElementById('payloadViewerTitle').innerHTML = '<i class="fas fa-code me-2 text-warning"></i>' + titleText;
    document.getElementById('payloadPreArea').textContent = JSON.stringify(payloadObj, null, 2);
    
    const subModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('payloadViewerSubModal'));
    subModal.show();
}

function copyPayloadToClipboard() {
    if (currentPayloadData) {
        navigator.clipboard.writeText(JSON.stringify(currentPayloadData, null, 2)).then(() => {
            alert('Payload copied to clipboard!');
        });
    }
}

function openLiveApiDispatchModal(dept) {
    const subModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('liveApiDispatchSubModal'));
    subModal.show();
}

function openNewCctvRequestSubModal(defaultLocation = '') {
    const subModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('liveApiDispatchSubModal'));
    if (defaultLocation) {
        const locInput = document.querySelector('#liveApiDispatchSubModal input[name="location"]');
        if (locInput) locInput.value = defaultLocation;
    }
    subModal.show();
}

function openCctvAcknowledgeSubModal(requestId) {
    document.getElementById('ackRequestIdInput').value = requestId;
    document.getElementById('ackRequestIdDisplay').value = requestId;
    const subModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('cctvAcknowledgeSubModal'));
    subModal.show();
}

function openMediaPlayerSubModal(mediaUrl, title) {
    document.getElementById('mediaPlayerSourceLabel').textContent = title || 'Evidence Footage Playback';
    document.getElementById('mediaPlayerUrlDisplay').textContent = mediaUrl || 'N/A';
    const subModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('mediaPlayerSubModal'));
    subModal.show();
}

function openGroup7MediaUploadSubModal() {
    const subModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('group7MediaUploadSubModal'));
    subModal.show();
}

document.addEventListener('DOMContentLoaded', function(){
    initDeptCatalog();
});
</script>

<?php require_once '../includes/footer.php'; ?>
