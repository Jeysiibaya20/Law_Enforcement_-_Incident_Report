<?php
$base_url = '../';
require_once __DIR__ . '/../admin/admin_auth.php';
require_once '../config/db_connect.php';
require_once '../config/LanguageManager.php';

$page_title = 'CCTV / Service Request Form';
$base_url = '../';
$current_page = 'request_form';
require_once '../includes/header.php';
require_once '../includes/navbar.php';

$message = '';
$message_type = 'info';
$submitted = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_action = $_POST['action'] ?? 'create_request';

    if ($form_action === 'dispatch_record_cctv') {
        $req_id = (int)($_POST['request_id'] ?? 0);
        if ($req_id > 0) {
            try {
                $stmt_r = $pdo->prepare("SELECT * FROM cctv_requests WHERE id = ?");
                $stmt_r->execute([$req_id]);
                $rec = $stmt_r->fetch(PDO::FETCH_ASSOC);
                if ($rec) {
                    require_once __DIR__ . '/OperationalModuleIntegrator.php';
                    $integrator = new OperationalModuleIntegrator($pdo);
                    $res = $integrator->dispatchToPartnerCctvApi([
                        'request_id' => 'REQ-CCTV-' . str_pad($rec['id'], 3, '0', STR_PAD_LEFT),
                        'incident_id' => 'INC-CCTV-' . $rec['id'],
                        'location' => $rec['camera_location'] ?: 'Unspecified Location',
                        'timestamp_range' => [
                            'start_time' => ($rec['incident_date'] ?: date('Y-m-d')) . ' ' . ($rec['incident_time'] ?: date('H:i:s')),
                            'end_time' => date('Y-m-d H:i:s')
                        ],
                        'reason' => $rec['reason'],
                        'monitoring_office' => $rec['monitoring_office'],
                        'delivery_method' => $rec['delivery_method'],
                        'notes' => $rec['monitoring_notes']
                    ]);
                    $message = "Dispatched Request #REQ-" . str_pad($rec['id'], 3, '0', STR_PAD_LEFT) . " to Partner Surveillance API (" . htmlspecialchars($res['endpoint']) . "). Result: " . ($res['success'] ? 'Success (200 OK)' : 'Target Endpoint Saved');
                    $message_type = $res['success'] ? 'success' : 'info';
                }
            } catch (Exception $e) {
                $message = "Could not dispatch request: " . htmlspecialchars($e->getMessage());
                $message_type = "danger";
            }
        }
    } elseif ($form_action === 'update_status') {
        $req_id = (int)($_POST['request_id'] ?? 0);
        $status_val = trim($_POST['status'] ?? 'Pending');
        $camera_val = trim($_POST['camera_location'] ?? '');
        $notes_val = trim($_POST['monitoring_notes'] ?? '');

        if ($req_id > 0) {
            try {
                $up_stmt = $pdo->prepare("UPDATE cctv_requests SET status = ?, camera_location = ?, monitoring_notes = ?, updated_at = NOW() WHERE id = ?");
                $up_stmt->execute([$status_val, $camera_val, $notes_val, $req_id]);
                $message = "CCTV Request #REQ-" . str_pad($req_id, 3, '0', STR_PAD_LEFT) . " updated successfully!";
                $message_type = "success";
            } catch (Exception $e) {
                $message = "Could not update request: " . htmlspecialchars($e->getMessage());
                $message_type = "danger";
            }
        }
    } else {
        $request_type = trim($_POST['request_type'] ?? '');
        $camera_location = trim($_POST['camera_location'] ?? '');
        $incident_date = trim($_POST['incident_date'] ?? '');
        $incident_time = trim($_POST['incident_time'] ?? '');
        $priority = trim($_POST['priority'] ?? 'Normal');
        $reason = trim($_POST['reason'] ?? '');
        $additional_details = trim($_POST['additional_details'] ?? '');
        $monitoring_office = trim($_POST['monitoring_office'] ?? '');
        $delivery_method = trim($_POST['delivery_method'] ?? '');
        $monitoring_notes = trim($_POST['monitoring_notes'] ?? '');

        if ($request_type === '' || $reason === '') {
            $message = 'Please select a request type and provide the reason for the request.';
            $message_type = 'danger';
        } else {
            try {
                $create_sql = "CREATE TABLE IF NOT EXISTS cctv_requests (
                    id int(11) NOT NULL AUTO_INCREMENT,
                    requested_by int(11) DEFAULT NULL,
                    request_type varchar(50) NOT NULL DEFAULT 'Footage',
                    camera_location varchar(255) DEFAULT NULL,
                    incident_date date DEFAULT NULL,
                    incident_time time DEFAULT NULL,
                    priority varchar(50) NOT NULL DEFAULT 'Normal',
                    reason text NOT NULL,
                    additional_details text DEFAULT NULL,
                    monitoring_office varchar(100) DEFAULT NULL,
                    delivery_method varchar(100) DEFAULT NULL,
                    monitoring_notes text DEFAULT NULL,
                    status varchar(50) NOT NULL DEFAULT 'Pending',
                    requested_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at datetime DEFAULT NULL,
                    PRIMARY KEY (id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
                $pdo->exec($create_sql);

                $columnChecks = [
                    'monitoring_office' => "ALTER TABLE cctv_requests ADD COLUMN monitoring_office varchar(100) DEFAULT NULL",
                    'delivery_method' => "ALTER TABLE cctv_requests ADD COLUMN delivery_method varchar(100) DEFAULT NULL",
                    'monitoring_notes' => "ALTER TABLE cctv_requests ADD COLUMN monitoring_notes text DEFAULT NULL"
                ];

                foreach ($columnChecks as $column => $alterSql) {
                    $checkStmt = $pdo->query("SHOW COLUMNS FROM cctv_requests LIKE '$column'");
                    if (!$checkStmt->fetch()) {
                        $pdo->exec($alterSql);
                    }
                }

                $insert_stmt = $pdo->prepare("INSERT INTO cctv_requests
                    (requested_by, request_type, camera_location, incident_date, incident_time, priority, reason, additional_details, monitoring_office, delivery_method, monitoring_notes)
                    VALUES (:requested_by, :request_type, :camera_location, :incident_date, :incident_time, :priority, :reason, :additional_details, :monitoring_office, :delivery_method, :monitoring_notes)");

                $activeUserId = $_SESSION['admin_user_id'] ?? $_SESSION['user_id'] ?? 1;

                $insert_stmt->execute([
                    ':requested_by' => $activeUserId,
                    ':request_type' => $request_type,
                    ':camera_location' => $camera_location !== '' ? $camera_location : null,
                    ':incident_date' => $incident_date !== '' ? $incident_date : null,
                    ':incident_time' => $incident_time !== '' ? $incident_time : null,
                    ':priority' => in_array($priority, ['High', 'Normal', 'Low'], true) ? $priority : 'Normal',
                    ':reason' => $reason,
                    ':additional_details' => $additional_details !== '' ? $additional_details : null,
                    ':monitoring_office' => $monitoring_office !== '' ? $monitoring_office : null,
                    ':delivery_method' => $delivery_method !== '' ? $delivery_method : null,
                    ':monitoring_notes' => $monitoring_notes !== '' ? $monitoring_notes : null,
                ]);
                $newRequestId = $pdo->lastInsertId();

                require_once __DIR__ . '/OperationalModuleIntegrator.php';
                $integrator = new OperationalModuleIntegrator($pdo);
                $cctvDispatch = $integrator->dispatchToPartnerCctvApi([
                    'request_id' => 'REQ-CCTV-' . str_pad($newRequestId, 3, '0', STR_PAD_LEFT),
                    'incident_id' => 'INC-CCTV-' . $newRequestId,
                    'location' => $camera_location ?: 'Unspecified Location',
                    'timestamp_range' => [
                        'start_time' => ($incident_date ?: date('Y-m-d')) . ' ' . ($incident_time ?: date('H:i:s')),
                        'end_time' => date('Y-m-d H:i:s')
                    ],
                    'reason' => $reason,
                    'media_type' => $request_type === 'Capture Photo' ? 'image/jpeg' : 'video/mp4',
                    'action' => 'fetch_surveillance_feed'
                ]);

                $message = 'CCTV request recorded and dispatched to Partner Surveillance API (' . htmlspecialchars($cctvDispatch['endpoint']) . '). Status: ' . ($cctvDispatch['success'] ? 'HTTP 200 Sent' : 'Target Configured');
                $message_type = 'success';
                $submitted = true;
                $_POST = [];
            } catch (Exception $e) {
                $message = 'Could not submit CCTV request: ' . htmlspecialchars($e->getMessage());
                $message_type = 'danger';
            }
        }
    }
}

try {
    $records_stmt = $pdo->prepare("SELECT r.id, r.request_type, r.camera_location, r.incident_date, r.incident_time, r.priority, r.reason, r.additional_details, r.monitoring_office, r.delivery_method, r.monitoring_notes, r.status, r.requested_at, COALESCE(s.fullname, s.emailadd, 'Admin') as requester_name FROM cctv_requests r LEFT JOIN signup s ON r.requested_by = s.user_id ORDER BY r.requested_at DESC");
    $records_stmt->execute();
    $request_records = $records_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $request_records = [];
}
?>

<div class="main-content">
    <div class="content-container container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h2"><i class="bi bi-camera-reels me-2"></i>CCTV Request</h1>
            <div class="d-flex gap-2">
                <input type="search" id="searchBox" class="form-control" placeholder="Search by request ID, agency, contact, or location..." style="min-width:320px;">
                <input type="date" id="filterDate" class="form-control" placeholder="mm/dd/yyyy" style="max-width:170px;">
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <p class="text-secondary">Use this form to request CCTV footage or a captured still image from the monitoring system.</p>

                        <?php if ($message): ?>
                            <div class="alert alert-<?php echo htmlspecialchars($message_type); ?>" role="alert">
                                <?php echo htmlspecialchars($message); ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" class="row g-3">
                        <div class="col-md-6">
                            <label for="request_type" class="form-label">Request Type <span class="text-danger">*</span></label>
                            <select id="request_type" name="request_type" class="form-select" required>
                                <option value="" <?php echo empty($_POST['request_type']) ? 'selected' : ''; ?>>Select request type</option>
                                <option value="Footage" <?php echo ($_POST['request_type'] ?? '') === 'Footage' ? 'selected' : ''; ?>>Footage</option>
                                <option value="Capture Photo" <?php echo ($_POST['request_type'] ?? '') === 'Capture Photo' ? 'selected' : ''; ?>>Capture Photo</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="priority" class="form-label">Priority</label>
                            <select id="priority" name="priority" class="form-select">
                                <option value="High" <?php echo ($_POST['priority'] ?? '') === 'High' ? 'selected' : ''; ?>>High</option>
                                <option value="Normal" <?php echo ($_POST['priority'] ?? '') === 'Normal' ? 'selected' : ''; ?>>Normal</option>
                                <option value="Low" <?php echo ($_POST['priority'] ?? '') === 'Low' ? 'selected' : ''; ?>>Low</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="camera_location" class="form-label">Camera Location</label>
                            <input type="text" id="camera_location" name="camera_location" class="form-control" placeholder="e.g. Entrance Gate, Main Lobby" value="<?php echo htmlspecialchars($_POST['camera_location'] ?? ''); ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="incident_date" class="form-label">Incident Date</label>
                            <input type="date" id="incident_date" name="incident_date" class="form-control" value="<?php echo htmlspecialchars($_POST['incident_date'] ?? ''); ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="incident_time" class="form-label">Incident Time</label>
                            <input type="time" id="incident_time" name="incident_time" class="form-control" value="<?php echo htmlspecialchars($_POST['incident_time'] ?? ''); ?>">
                        </div>
                        <div class="col-12">
                            <label for="reason" class="form-label">Reason for Request <span class="text-danger">*</span></label>
                            <textarea id="reason" name="reason" class="form-control" rows="4" required><?php echo htmlspecialchars($_POST['reason'] ?? ''); ?></textarea>
                        </div>
                        <div class="col-12">
                            <label for="additional_details" class="form-label">Additional Details</label>
                            <textarea id="additional_details" name="additional_details" class="form-control" rows="3"><?php echo htmlspecialchars($_POST['additional_details'] ?? ''); ?></textarea>
                        </div>
                        <div class="col-12">
                            <div class="border rounded p-3 bg-light">
                                <h6 class="fw-semibold mb-3"><i class="bi bi-broadcast-pin me-2"></i>Monitoring Intake Details</h6>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="monitoring_office" class="form-label">Receiving Office</label>
                                        <select id="monitoring_office" name="monitoring_office" class="form-select">
                                            <option value="" <?php echo empty($_POST['monitoring_office']) ? 'selected' : ''; ?>>Select receiving office</option>
                                            <option value="Control Room" <?php echo ($_POST['monitoring_office'] ?? '') === 'Control Room' ? 'selected' : ''; ?>>Control Room</option>
                                            <option value="Operations Center" <?php echo ($_POST['monitoring_office'] ?? '') === 'Operations Center' ? 'selected' : ''; ?>>Operations Center</option>
                                            <option value="Records Unit" <?php echo ($_POST['monitoring_office'] ?? '') === 'Records Unit' ? 'selected' : ''; ?>>Records Unit</option>
                                            <option value="Security Desk" <?php echo ($_POST['monitoring_office'] ?? '') === 'Security Desk' ? 'selected' : ''; ?>>Security Desk</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="delivery_method" class="form-label">Delivery Method</label>
                                        <select id="delivery_method" name="delivery_method" class="form-select">
                                            <option value="" <?php echo empty($_POST['delivery_method']) ? 'selected' : ''; ?>>Select delivery method</option>
                                            <option value="Email" <?php echo ($_POST['delivery_method'] ?? '') === 'Email' ? 'selected' : ''; ?>>Email</option>
                                            <option value="Portal" <?php echo ($_POST['delivery_method'] ?? '') === 'Portal' ? 'selected' : ''; ?>>Portal</option>
                                            <option value="Physical Copy" <?php echo ($_POST['delivery_method'] ?? '') === 'Physical Copy' ? 'selected' : ''; ?>>Physical Copy</option>
                                            <option value="Other" <?php echo ($_POST['delivery_method'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
                                        </select>
                                    </div>
                                    <div class="col-12">
                                        <label for="monitoring_notes" class="form-label">Monitoring Notes</label>
                                        <textarea id="monitoring_notes" name="monitoring_notes" class="form-control" rows="3" placeholder="Add any handling notes, reference numbers, or follow-up instructions for the monitoring team."><?php echo htmlspecialchars($_POST['monitoring_notes'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 d-flex justify-content-between align-items-center">
                            <a href="../admin/dashboard.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left me-2"></i>Back to Dashboard
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-send me-2"></i>Submit Request
                            </button>
                        </div>
                        </form>

                        <?php if ($submitted): ?>
                            <div class="mt-4 alert alert-info border">
                            <h6 class="fw-semibold mb-2"><i class="bi bi-broadcast-pin me-2"></i>Monitoring Request Received</h6>
                            <p class="mb-2">Your request for <strong><?php echo htmlspecialchars($request_type ?: 'CCTV media'); ?></strong> has been recorded and sent to the monitoring team.</p>
                            <div class="row g-3 mt-1">
                                <div class="col-md-6">
                                    <div class="border rounded p-3 bg-white">
                                        <h6 class="fw-semibold mb-2">Requested Media</h6>
                                        <p class="mb-1"><strong>Type:</strong> <?php echo htmlspecialchars($request_type ?: 'Pending selection'); ?></p>
                                        <p class="mb-1"><strong>Location:</strong> <?php echo htmlspecialchars($camera_location ?: 'Not specified'); ?></p>
                                        <p class="mb-0"><strong>Incident:</strong> <?php echo htmlspecialchars(($incident_date ?: 'Not specified') . ($incident_time ? ' at ' . $incident_time : '')); ?></p>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="border rounded p-3 bg-white">
                                        <h6 class="fw-semibold mb-2">Monitoring Delivery Area</h6>
                                        <p class="mb-1"><strong>Status:</strong> Pending Review</p>
                                        <p class="mb-1"><strong>Receiving Office:</strong> <?php echo htmlspecialchars($monitoring_office ?: 'Not assigned'); ?></p>
                                        <p class="mb-0"><strong>Delivery Method:</strong> <?php echo htmlspecialchars($delivery_method ?: 'To be confirmed'); ?></p>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="border rounded p-3 bg-white">
                                        <h6 class="fw-semibold mb-2">Footage / Capture Details</h6>
                                        <p class="mb-2 text-muted">The monitoring team will add the footage, capture reference, or related evidence details here once the request is processed.</p>
                                        <ul class="mb-0 ps-3">
                                            <li><strong>Reason for request:</strong> <?php echo htmlspecialchars($reason ?: 'No reason provided'); ?></li>
                                            <li><strong>Monitoring notes:</strong> <?php echo htmlspecialchars($monitoring_notes ?: 'No additional monitoring notes provided'); ?></li>
                                            <li><strong>Additional details:</strong> <?php echo htmlspecialchars($additional_details ?: 'No additional details provided'); ?></li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-12">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center flex-wrap gap-2 py-3">
                        <div class="d-flex align-items-center gap-2">
                            <h5 class="mb-0 fw-bold text-white"><i class="bi bi-camera-video text-warning me-2"></i>Request Records</h5>
                            <span class="badge bg-primary fw-bold" id="reqTotalBadge"><?php echo count($request_records); ?> record(s)</span>
                        </div>

                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <!-- Search Filter -->
                            <div class="input-group input-group-sm" style="width: 220px;">
                                <span class="input-group-text bg-light text-dark border-0"><i class="bi bi-search"></i></span>
                                <input type="text" id="reqSearchInput" class="form-control border-0" placeholder="Search requests..." onkeyup="filterRequestRecords()">
                            </div>

                            <!-- Page Size Selector -->
                            <select id="reqPageSizeSelect" class="form-select form-select-sm" style="width: auto;" onchange="changeReqPageSize(this.value)">
                                <option value="10" selected>10 per page</option>
                                <option value="25">25 per page</option>
                                <option value="50">50 per page</option>
                                <option value="1000">Show All</option>
                            </select>

                            <!-- View Switcher -->
                            <div class="btn-group btn-group-sm" role="group">
                                <button type="button" class="btn btn-light active" id="btnReqTableView" onclick="switchReqView('table')">
                                    <i class="bi bi-table me-1"></i> Table View
                                </button>
                                <button type="button" class="btn btn-light" id="btnReqCarouselView" onclick="switchReqView('carousel')">
                                    <i class="bi bi-view-stacked me-1"></i> Carousel View (10/Slide)
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="card-body p-0">
                        <!-- ================= TABLE VIEW ================= -->
                        <div id="reqTableView" class="p-3">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0" id="reqTable">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>REQUEST ID</th>
                                            <th>AGENCY</th>
                                            <th>CONTACT</th>
                                            <th>LOCATION / CAMERA</th>
                                            <th>FOOTAGE WINDOW</th>
                                            <th>STATUS</th>
                                            <th>SUBMITTED</th>
                                            <th>ACTIONS</th>
                                        </tr>
                                    </thead>
                                    <tbody id="reqTableBody">
                                        <?php if (!empty($request_records)): ?>
                                            <?php foreach ($request_records as $idx => $record): ?>
                                                <tr class="req-record-row" 
                                                    data-index="<?php echo $idx; ?>"
                                                    data-req-id="<?php echo strtolower('REQ-' . str_pad((int)$record['id'], 3, '0', STR_PAD_LEFT)); ?>"
                                                    data-contact="<?php echo strtolower(htmlspecialchars($record['requester_name'] ?? 'Admin')); ?>"
                                                    data-location="<?php echo strtolower(htmlspecialchars($record['camera_location'] ?: '')); ?>"
                                                    data-status="<?php echo strtolower(htmlspecialchars($record['status'] ?? 'Pending')); ?>"
                                                    data-date="<?php echo htmlspecialchars($record['incident_date'] ?? ''); ?>">
                                                    <td class="text-muted small fw-bold"><?php echo $idx + 1; ?></td>
                                                    <td><strong class="text-primary"><?php echo 'REQ-' . str_pad((int)$record['id'], 3, '0', STR_PAD_LEFT); ?></strong></td>
                                                    <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars('Digital Blotter System'); ?></span></td>
                                                    <td><?php echo htmlspecialchars($record['requester_name'] ?? 'Admin'); ?></td>
                                                    <td><i class="bi bi-camera-video text-secondary me-1"></i><?php echo htmlspecialchars($record['camera_location'] ?: 'CAM-001'); ?></td>
                                                    <td><small><?php echo htmlspecialchars(($record['incident_date'] ?: '') . ($record['incident_time'] ? ' ' . date('H:i', strtotime($record['incident_time'])) : '')); ?></small></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo match($record['status'] ?? 'Pending') {
                                                            'Approved', 'Completed', 'Dispatched' => 'success',
                                                            'Under Review', 'Processing' => 'info',
                                                            'Rejected', 'Cancelled' => 'danger',
                                                            default => 'warning text-dark'
                                                        }; ?>"><?php echo htmlspecialchars($record['status'] ?? 'Pending'); ?></span>
                                                    </td>
                                                    <td><small class="text-muted"><?php echo htmlspecialchars(date('M d, Y', strtotime($record['requested_at']))); ?></small></td>
                                                    <td>
                                                        <div class="d-flex gap-1 flex-wrap">
                                                            <button type="button" class="btn btn-sm btn-outline-success btn-view" 
                                                                style="font-weight: 600;"
                                                                data-id="<?php echo (int)$record['id']; ?>"
                                                                data-request-id="<?php echo 'CCTV-REQ-' . date('Y') . '-' . str_pad((int)$record['id'],3,'0',STR_PAD_LEFT); ?>"
                                                                data-agency="<?php echo htmlspecialchars('Digital Blotter System'); ?>"
                                                                data-contact="<?php echo htmlspecialchars($record['requester_name'] ?? 'Admin'); ?>"
                                                                data-email="<?php echo htmlspecialchars($_SESSION['user_email'] ?? ''); ?>"
                                                                data-case-ref="<?php echo htmlspecialchars($record['additional_details'] ?: ''); ?>"
                                                                data-purpose="<?php echo htmlspecialchars($record['reason'] ?: ''); ?>"
                                                                data-legal="<?php echo htmlspecialchars('Blotter referral'); ?>"
                                                                data-location="<?php echo htmlspecialchars($record['camera_location'] ?: ''); ?>"
                                                                data-camera="<?php echo htmlspecialchars($record['camera_location'] ?: 'CAM-001'); ?>"
                                                                data-footage-window="<?php echo htmlspecialchars(($record['incident_date'] ?: '') . ($record['incident_time'] ? ' ' . date('H:i', strtotime($record['incident_time'])) : '')); ?>"
                                                                data-description="<?php echo htmlspecialchars($record['reason'] ?: ''); ?>"
                                                                data-delivery="<?php echo htmlspecialchars($record['delivery_method'] ?: 'pickup'); ?>"
                                                                data-supporting=""
                                                                data-status="<?php echo htmlspecialchars($record['status'] ?? 'Under Review'); ?>"
                                                                data-review-notes="<?php echo htmlspecialchars($record['monitoring_notes'] ?: ''); ?>"
                                                                ><i class="bi bi-eye me-1"></i>View</button>

                                                            <button type="button" class="btn btn-sm btn-outline-warning btn-manage" 
                                                                style="font-weight: 600;"
                                                                data-id="<?php echo (int)$record['id']; ?>"
                                                                data-status-val="<?php echo htmlspecialchars($record['status'] ?? 'Under Review'); ?>"
                                                                data-camera-val="<?php echo htmlspecialchars($record['camera_location'] ?: 'CAM-001'); ?>"
                                                                data-start="<?php echo htmlspecialchars($record['incident_time'] ?: ''); ?>"
                                                                data-end="<?php echo htmlspecialchars($record['incident_time'] ?: ''); ?>"
                                                                data-review-notes-val="<?php echo htmlspecialchars($record['monitoring_notes'] ?: ''); ?>"
                                                                ><i class="bi bi-sliders me-1"></i>Manage</button>

                                                            <form method="POST" class="d-inline" onsubmit="return confirm('Dispatch request #REQ-<?php echo str_pad((int)$record['id'], 3, '0', STR_PAD_LEFT); ?> directly to Partner CCTV API?');">
                                                                <input type="hidden" name="action" value="dispatch_record_cctv">
                                                                <input type="hidden" name="request_id" value="<?php echo (int)$record['id']; ?>">
                                                                <button type="submit" class="btn btn-sm btn-success text-white shadow-sm" title="Dispatch CCTV request to partner API">
                                                                    <i class="fas fa-paper-plane me-1"></i>Dispatch
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="9" class="text-center text-muted py-4">No request records yet. Submit a request to view it here.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Table Pagination Bar -->
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3 pt-2 border-top">
                                <div class="text-muted small" id="reqPaginationInfo">
                                    Showing 1 to 10 of <?php echo count($request_records); ?> entries
                                </div>
                                <nav aria-label="Request table pagination">
                                    <ul class="pagination pagination-sm mb-0" id="reqPaginationControls">
                                        <!-- Populated dynamically via JS -->
                                    </ul>
                                </nav>
                            </div>
                        </div>

                        <!-- ================= CAROUSEL VIEW (10 Items per Slide) ================= -->
                        <div id="reqCarouselView" class="p-3" style="display: none;">
                            <?php 
                                $reqBatches = array_chunk($request_records, 10);
                                $totalReqSlides = count($reqBatches);
                            ?>

                            <!-- Carousel Header & Navigation Controls -->
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3 p-2 bg-light rounded border">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge bg-primary px-3 py-2 fs-6">
                                        <i class="bi bi-view-stacked me-1"></i> <span id="reqCarouselSlideLabel">Slide 1 of <?php echo max(1, $totalReqSlides); ?></span>
                                    </span>
                                    <small class="text-muted" id="reqCarouselRangeLabel">
                                        Showing <?php echo count($request_records) > 0 ? '1 - ' . min(10, count($request_records)) : '0'; ?> of <?php echo count($request_records); ?> requests
                                    </small>
                                </div>

                                <div class="d-flex align-items-center gap-2">
                                    <button class="btn btn-sm btn-primary rounded-circle" type="button" data-bs-target="#requestRecordsCarousel" data-bs-slide="prev" style="width:34px; height:34px;">
                                        <i class="bi bi-chevron-left"></i>
                                    </button>
                                    
                                    <div class="d-flex gap-1" id="reqCarouselIndicators">
                                        <?php for ($s = 0; $s < $totalReqSlides; $s++): ?>
                                            <button class="btn btn-sm <?php echo $s === 0 ? 'btn-primary' : 'btn-outline-secondary'; ?> fw-bold py-1 px-2" type="button" data-bs-target="#requestRecordsCarousel" data-bs-slide-to="<?php echo $s; ?>" style="font-size: 0.75rem;">
                                                <?php echo $s + 1; ?>
                                            </button>
                                        <?php endfor; ?>
                                    </div>

                                    <button class="btn btn-sm btn-primary rounded-circle" type="button" data-bs-target="#requestRecordsCarousel" data-bs-slide="next" style="width:34px; height:34px;">
                                        <i class="bi bi-chevron-right"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- Bootstrap Carousel -->
                            <div id="requestRecordsCarousel" class="carousel slide" data-bs-interval="false">
                                <div class="carousel-inner">
                                    <?php if (empty($reqBatches)): ?>
                                        <div class="carousel-item active">
                                            <div class="text-center py-5 text-muted">
                                                <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                                <h5>No Request Records</h5>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($reqBatches as $slideIdx => $batch): ?>
                                            <div class="carousel-item <?php echo $slideIdx === 0 ? 'active' : ''; ?>" data-slide-index="<?php echo $slideIdx; ?>">
                                                <div class="row g-3">
                                                    <?php foreach ($batch as $cardIdx => $rec): ?>
                                                        <div class="col-md-6 col-lg-6 req-carousel-card-col">
                                                            <div class="card h-100 border shadow-sm">
                                                                <div class="card-header bg-light d-flex justify-content-between align-items-center py-2 px-3">
                                                                    <div>
                                                                        <strong class="text-primary"><?php echo 'REQ-' . str_pad((int)$rec['id'], 3, '0', STR_PAD_LEFT); ?></strong>
                                                                        <span class="badge bg-light text-dark border ms-1"><?php echo htmlspecialchars('Digital Blotter'); ?></span>
                                                                    </div>
                                                                    <span class="badge bg-<?php echo match($rec['status'] ?? 'Pending') {
                                                                        'Approved', 'Completed', 'Dispatched' => 'success',
                                                                        'Under Review', 'Processing' => 'info',
                                                                        'Rejected', 'Cancelled' => 'danger',
                                                                        default => 'warning text-dark'
                                                                    }; ?>"><?php echo htmlspecialchars($rec['status'] ?? 'Pending'); ?></span>
                                                                </div>
                                                                <div class="card-body p-3">
                                                                    <div class="row g-2 small">
                                                                        <div class="col-6">
                                                                            <span class="text-muted d-block text-uppercase" style="font-size:0.7rem; font-weight:700;">Requester</span>
                                                                            <strong class="text-dark"><?php echo htmlspecialchars($rec['requester_name'] ?? 'Admin'); ?></strong>
                                                                        </div>
                                                                        <div class="col-6">
                                                                            <span class="text-muted d-block text-uppercase" style="font-size:0.7rem; font-weight:700;">Location / Camera</span>
                                                                            <span><i class="bi bi-camera-video me-1 text-secondary"></i><?php echo htmlspecialchars($rec['camera_location'] ?: 'CAM-001'); ?></span>
                                                                        </div>
                                                                        <div class="col-6">
                                                                            <span class="text-muted d-block text-uppercase" style="font-size:0.7rem; font-weight:700;">Footage Window</span>
                                                                            <span><?php echo htmlspecialchars(($rec['incident_date'] ?: '') . ($rec['incident_time'] ? ' ' . date('H:i', strtotime($rec['incident_time'])) : '')); ?></span>
                                                                        </div>
                                                                        <div class="col-6">
                                                                            <span class="text-muted d-block text-uppercase" style="font-size:0.7rem; font-weight:700;">Submitted Date</span>
                                                                            <span><?php echo htmlspecialchars(date('M d, Y', strtotime($rec['requested_at']))); ?></span>
                                                                        </div>
                                                                        <div class="col-12">
                                                                            <span class="text-muted d-block text-uppercase" style="font-size:0.7rem; font-weight:700;">Reason</span>
                                                                            <span class="text-truncate d-block" style="max-width: 100%;"><?php echo htmlspecialchars($rec['reason'] ?: 'No reason provided'); ?></span>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="card-footer bg-light d-flex justify-content-end gap-1 py-2 px-3">
                                                                    <button type="button" class="btn btn-sm btn-outline-success btn-view"
                                                                        data-id="<?php echo (int)$rec['id']; ?>"
                                                                        data-request-id="<?php echo 'CCTV-REQ-' . date('Y') . '-' . str_pad((int)$rec['id'],3,'0',STR_PAD_LEFT); ?>"
                                                                        data-agency="<?php echo htmlspecialchars('Digital Blotter System'); ?>"
                                                                        data-contact="<?php echo htmlspecialchars($rec['requester_name'] ?? 'Admin'); ?>"
                                                                        data-email="<?php echo htmlspecialchars($_SESSION['user_email'] ?? ''); ?>"
                                                                        data-case-ref="<?php echo htmlspecialchars($rec['additional_details'] ?: ''); ?>"
                                                                        data-purpose="<?php echo htmlspecialchars($rec['reason'] ?: ''); ?>"
                                                                        data-legal="<?php echo htmlspecialchars('Blotter referral'); ?>"
                                                                        data-location="<?php echo htmlspecialchars($rec['camera_location'] ?: ''); ?>"
                                                                        data-camera="<?php echo htmlspecialchars($rec['camera_location'] ?: 'CAM-001'); ?>"
                                                                        data-footage-window="<?php echo htmlspecialchars(($rec['incident_date'] ?: '') . ($rec['incident_time'] ? ' ' . date('H:i', strtotime($rec['incident_time'])) : '')); ?>"
                                                                        data-description="<?php echo htmlspecialchars($rec['reason'] ?: ''); ?>"
                                                                        data-delivery="<?php echo htmlspecialchars($rec['delivery_method'] ?: 'pickup'); ?>"
                                                                        data-supporting=""
                                                                        data-status="<?php echo htmlspecialchars($rec['status'] ?? 'Under Review'); ?>"
                                                                        data-review-notes="<?php echo htmlspecialchars($rec['monitoring_notes'] ?: ''); ?>"
                                                                        ><i class="bi bi-eye me-1"></i>View</button>
                                                                    <button type="button" class="btn btn-sm btn-outline-warning btn-manage"
                                                                        data-id="<?php echo (int)$rec['id']; ?>"
                                                                        data-status-val="<?php echo htmlspecialchars($rec['status'] ?? 'Under Review'); ?>"
                                                                        data-camera-val="<?php echo htmlspecialchars($rec['camera_location'] ?: 'CAM-001'); ?>"
                                                                        data-start="<?php echo htmlspecialchars($rec['incident_time'] ?: ''); ?>"
                                                                        data-end="<?php echo htmlspecialchars($rec['incident_time'] ?: ''); ?>"
                                                                        data-review-notes-val="<?php echo htmlspecialchars($rec['monitoring_notes'] ?: ''); ?>"
                                                                        ><i class="bi bi-sliders me-1"></i>Manage</button>
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
</div>

<!-- Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title text-white"><i class="fas fa-video me-2 text-warning"></i>CCTV Request Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3">
                    <div class="col-md-5">
                        <ul class="list-unstyled mb-0">
                            <li class="py-2 border-bottom"><strong>Request ID</strong></li>
                            <li class="py-2 border-bottom"><strong>Agency</strong></li>
                            <li class="py-2 border-bottom"><strong>Contact</strong></li>
                            <li class="py-2 border-bottom"><strong>Email</strong></li>
                            <li class="py-2 border-bottom"><strong>Case Reference</strong></li>
                            <li class="py-2 border-bottom"><strong>Purpose</strong></li>
                            <li class="py-2 border-bottom"><strong>Legal Basis</strong></li>
                            <li class="py-2 border-bottom"><strong>Incident Location</strong></li>
                            <li class="py-2 border-bottom"><strong>Camera</strong></li>
                            <li class="py-2 border-bottom"><strong>Footage Window</strong></li>
                            <li class="py-2 border-bottom"><strong>Incident Description</strong></li>
                            <li class="py-2 border-bottom"><strong>Delivery Method</strong></li>
                            <li class="py-2 border-bottom"><strong>Supporting Document</strong></li>
                            <li class="py-2 border-bottom"><strong>Status</strong></li>
                            <li class="py-2 border-bottom"><strong>Review Notes</strong></li>
                            <li class="py-2 border-bottom"><strong>Rejection Reason</strong></li>
                            <li class="py-2"><strong>Fulfillment Notes</strong></li>
                        </ul>
                    </div>
                    <div class="col-md-7">
                        <ul class="list-unstyled mb-0" id="detailsValues">
                            <li class="py-2 border-bottom text-muted" data-key="request-id"></li>
                            <li class="py-2 border-bottom text-muted" data-key="agency"></li>
                            <li class="py-2 border-bottom text-muted" data-key="contact"></li>
                            <li class="py-2 border-bottom text-muted" data-key="email"></li>
                            <li class="py-2 border-bottom text-muted" data-key="case-ref"></li>
                            <li class="py-2 border-bottom text-muted" data-key="purpose"></li>
                            <li class="py-2 border-bottom text-muted" data-key="legal"></li>
                            <li class="py-2 border-bottom text-muted" data-key="location"></li>
                            <li class="py-2 border-bottom text-muted" data-key="camera"></li>
                            <li class="py-2 border-bottom text-muted" data-key="footage-window"></li>
                            <li class="py-2 border-bottom text-muted" data-key="description"></li>
                            <li class="py-2 border-bottom text-muted" data-key="delivery"></li>
                            <li class="py-2 border-bottom text-muted" data-key="supporting"></li>
                            <li class="py-2 border-bottom text-muted" data-key="status"></li>
                            <li class="py-2 border-bottom text-muted" data-key="review-notes"></li>
                            <li class="py-2 border-bottom text-muted" data-key="rejection"></li>
                            <li class="py-2 text-muted" data-key="fulfillment"></li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary px-4 fw-semibold" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i> Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Manage Modal -->
<div class="modal fade" id="manageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title text-white"><i class="fas fa-tasks me-2"></i>Manage CCTV Request</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form id="manageForm" method="POST">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="request_id" id="manage_request_id">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Status *</label>
                        <select id="manage_status" name="status" class="form-select">
                            <option value="Pending">Pending</option>
                            <option value="Under Review">Under Review</option>
                            <option value="Approved">Approved</option>
                            <option value="Rejected">Rejected</option>
                            <option value="Completed">Completed</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Approved Camera / Location</label>
                        <input type="text" id="manage_camera" name="camera_location" class="form-control" placeholder="e.g. CAM-001 - Main Entrance Camera">
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Actual Footage Start</label>
                            <input type="time" id="manage_start" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Actual Footage End</label>
                            <input type="time" id="manage_end" class="form-control">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Review Notes (internal)</label>
                        <textarea id="manage_notes" name="monitoring_notes" class="form-control" rows="4"></textarea>
                    </div>
                    <div class="d-flex justify-content-end gap-2 pt-2 border-top">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success fw-bold"><i class="fas fa-save me-1"></i> Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// ================= REQUEST RECORDS VIEW ENGINE =================
let currentReqPage = 1;
let reqRowsPerPage = 10;
let filteredReqRows = [];

function initReqCatalog() {
    const rows = document.querySelectorAll('#reqTableBody tr.req-record-row');
    filteredReqRows = Array.from(rows);
    renderReqPagination();

    const carouselEl = document.getElementById('requestRecordsCarousel');
    if (carouselEl) {
        carouselEl.addEventListener('slide.bs.carousel', function(event) {
            const nextSlideIdx = event.to;
            const totalSlides = document.querySelectorAll('#requestRecordsCarousel .carousel-item').length;
            const totalRecs = parseInt('<?php echo count($request_records); ?>') || 0;

            const label = document.getElementById('reqCarouselSlideLabel');
            if (label) label.textContent = `Slide ${nextSlideIdx + 1} of ${totalSlides}`;

            const rangeLabel = document.getElementById('reqCarouselRangeLabel');
            if (rangeLabel) {
                const startRec = (nextSlideIdx * 10) + 1;
                const endRec = Math.min((nextSlideIdx + 1) * 10, totalRecs);
                rangeLabel.textContent = `Showing ${startRec} - ${endRec} of ${totalRecs} requests`;
            }

            const indicators = document.querySelectorAll('#reqCarouselIndicators button');
            indicators.forEach((b, idx) => {
                if (idx === nextSlideIdx) {
                    b.classList.replace('btn-outline-secondary', 'btn-primary');
                } else {
                    b.classList.replace('btn-primary', 'btn-outline-secondary');
                }
            });
        });
    }
}

function switchReqView(viewType) {
    const tableView = document.getElementById('reqTableView');
    const carouselView = document.getElementById('reqCarouselView');
    const btnTable = document.getElementById('btnReqTableView');
    const btnCarousel = document.getElementById('btnReqCarouselView');

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

function changeReqPageSize(size) {
    reqRowsPerPage = parseInt(size) || 10;
    currentReqPage = 1;
    renderReqPagination();
}

function filterRequestRecords() {
    const query = (document.getElementById('reqSearchInput')?.value || '').toLowerCase().trim();
    const allRows = document.querySelectorAll('#reqTableBody tr.req-record-row');
    const allCards = document.querySelectorAll('.req-carousel-card-col');

    filteredReqRows = [];
    allRows.forEach(row => {
        const text = (
            (row.getAttribute('data-req-id') || '') + ' ' +
            (row.getAttribute('data-contact') || '') + ' ' +
            (row.getAttribute('data-location') || '') + ' ' +
            (row.getAttribute('data-status') || '') + ' ' +
            (row.getAttribute('data-date') || '')
        ).toLowerCase();

        if (!query || text.includes(query)) {
            filteredReqRows.push(row);
        } else {
            row.style.display = 'none';
        }
    });

    allCards.forEach(card => {
        const cardText = card.textContent.toLowerCase();
        if (!query || cardText.includes(query)) {
            card.style.display = '';
        } else {
            card.style.display = 'none';
        }
    });

    currentReqPage = 1;
    renderReqPagination();
}

function renderReqPagination() {
    const total = filteredReqRows.length;
    const totalPages = Math.ceil(total / reqRowsPerPage) || 1;
    if (currentReqPage > totalPages) currentReqPage = totalPages;
    if (currentReqPage < 1) currentReqPage = 1;

    const startIdx = (currentReqPage - 1) * reqRowsPerPage;
    const endIdx = Math.min(startIdx + reqRowsPerPage, total);

    const allRows = document.querySelectorAll('#reqTableBody tr.req-record-row');
    allRows.forEach(r => r.style.display = 'none');

    for (let i = startIdx; i < endIdx; i++) {
        if (filteredReqRows[i]) filteredReqRows[i].style.display = '';
    }

    const infoEl = document.getElementById('reqPaginationInfo');
    if (infoEl) {
        if (total === 0) {
            infoEl.textContent = 'Showing 0 to 0 of 0 entries';
        } else {
            infoEl.textContent = `Showing ${startIdx + 1} to ${endIdx} of ${total} entries`;
        }
    }

    const controls = document.getElementById('reqPaginationControls');
    if (!controls) return;

    let html = '';
    html += `<li class="page-item ${currentReqPage === 1 ? 'disabled' : ''}">
        <a class="page-link" href="javascript:void(0)" onclick="goToReqPage(${currentReqPage - 1})"><i class="bi bi-chevron-left"></i></a>
    </li>`;

    for (let p = 1; p <= totalPages; p++) {
        if (totalPages > 7 && Math.abs(p - currentReqPage) > 2 && p !== 1 && p !== totalPages) {
            if (p === 2 || p === totalPages - 1) {
                html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
            }
            continue;
        }
        html += `<li class="page-item ${p === currentReqPage ? 'active' : ''}">
            <a class="page-link" href="javascript:void(0)" onclick="goToReqPage(${p})">${p}</a>
        </li>`;
    }

    html += `<li class="page-item ${currentReqPage >= totalPages ? 'disabled' : ''}">
        <a class="page-link" href="javascript:void(0)" onclick="goToReqPage(${currentReqPage + 1})"><i class="bi bi-chevron-right"></i></a>
    </li>`;

    controls.innerHTML = html;
}

function goToReqPage(page) {
    currentReqPage = page;
    renderReqPagination();
}

document.addEventListener('DOMContentLoaded', function(){
    initReqCatalog();

    // details modal handler
    document.querySelectorAll('.btn-view').forEach(btn=>{
        btn.addEventListener('click', function(){
            const keys = ['request-id','agency','contact','email','case-ref','purpose','legal','location','camera','footage-window','description','delivery','supporting','status','review-notes','rejection','fulfillment'];
            document.querySelectorAll('#detailsValues [data-key]').forEach(li=>{
                const key = li.getAttribute('data-key');
                const v = btn.getAttribute('data-' + key) || '';
                li.textContent = v || '—';
            });
            var modalEl = document.getElementById('detailsModal');
            var detailsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
            detailsModal.show();
        });
    });

    // manage modal handler
    document.querySelectorAll('.btn-manage').forEach(btn=>{
        btn.addEventListener('click', function(){
            document.getElementById('manage_request_id').value = btn.getAttribute('data-id');
            document.getElementById('manage_status').value = btn.getAttribute('data-status-val') || 'Pending';
            document.getElementById('manage_camera').value = btn.getAttribute('data-camera-val') || 'CAM-001 - Main Entrance Camera';
            document.getElementById('manage_start').value = btn.getAttribute('data-start') || '';
            document.getElementById('manage_end').value = btn.getAttribute('data-end') || '';
            document.getElementById('manage_notes').value = btn.getAttribute('data-review-notes-val') || '';
            var modalEl = document.getElementById('manageModal');
            var manageModal = bootstrap.Modal.getOrCreateInstance(modalEl);
            manageModal.show();
        });
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>
