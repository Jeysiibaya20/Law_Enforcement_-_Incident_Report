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

    if ($form_action === 'update_status') {
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

                $message = 'CCTV request has been recorded successfully.';
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
                <div class="card">
                    <div class="card-header bg-dark text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <strong>Request Records</strong>
                            <span class="badge bg-primary"><?php echo count($request_records); ?> record(s)</span>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table mb-0">
                                <thead class="table-dark">
                                    <tr>
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
                                <tbody>
                                    <?php if (!empty($request_records)): ?>
                                        <?php foreach ($request_records as $record): ?>
                                            <tr class="cctv-record-row" data-date="<?php echo htmlspecialchars($record['incident_date'] ?? ''); ?>">
                                                <td><?php echo 'REQ-' . str_pad((int)$record['id'], 3, '0', STR_PAD_LEFT); ?></td>
                                                <td><?php echo htmlspecialchars('Digital Blotter System'); ?></td>
                                                <td><?php echo htmlspecialchars($record['requester_name'] ?? 'Admin'); ?></td>
                                                <td><?php echo htmlspecialchars($record['camera_location'] ?: 'CAM-001'); ?></td>
                                                <td><?php echo htmlspecialchars(($record['incident_date'] ?: '') . ($record['incident_time'] ? ' ' . date('H:i', strtotime($record['incident_time'])) : '')); ?></td>
                                                <td><span class="badge bg-info text-dark"><?php echo htmlspecialchars($record['status'] ?? 'Pending'); ?></span></td>
                                                <td><?php echo htmlspecialchars(date('M d, Y', strtotime($record['requested_at']))); ?></td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-success me-1 btn-view" 
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
                                                        >View</button>

                                                    <button type="button" class="btn btn-sm btn-warning btn-manage" 
                                                        data-id="<?php echo (int)$record['id']; ?>"
                                                        data-status-val="<?php echo htmlspecialchars($record['status'] ?? 'Under Review'); ?>"
                                                        data-camera-val="<?php echo htmlspecialchars($record['camera_location'] ?: 'CAM-001'); ?>"
                                                        data-start="<?php echo htmlspecialchars($record['incident_time'] ?: ''); ?>"
                                                        data-end="<?php echo htmlspecialchars($record['incident_time'] ?: ''); ?>"
                                                        data-review-notes-val="<?php echo htmlspecialchars($record['monitoring_notes'] ?: ''); ?>"
                                                        >Manage</button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8" class="text-center text-muted">No request records yet. Submit a request to view it here.</td>
                                        </tr>
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

<?php require_once '../includes/footer.php'; ?>

    <!-- Details Modal -->
    <div class="modal fade" id="detailsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">CCTV Request Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-5">
                            <ul class="list-unstyled">
                                <li class="py-2"><strong>Request ID</strong></li>
                                <li class="py-2"><strong>Agency</strong></li>
                                <li class="py-2"><strong>Contact</strong></li>
                                <li class="py-2"><strong>Email</strong></li>
                                <li class="py-2"><strong>Case Reference</strong></li>
                                <li class="py-2"><strong>Purpose</strong></li>
                                <li class="py-2"><strong>Legal Basis</strong></li>
                                <li class="py-2"><strong>Incident Location</strong></li>
                                <li class="py-2"><strong>Camera</strong></li>
                                <li class="py-2"><strong>Footage Window</strong></li>
                                <li class="py-2"><strong>Incident Description</strong></li>
                                <li class="py-2"><strong>Delivery Method</strong></li>
                                <li class="py-2"><strong>Supporting Document</strong></li>
                                <li class="py-2"><strong>Status</strong></li>
                                <li class="py-2"><strong>Review Notes</strong></li>
                                <li class="py-2"><strong>Rejection Reason</strong></li>
                                <li class="py-2"><strong>Fulfillment Notes</strong></li>
                            </ul>
                        </div>
                        <div class="col-md-7">
                            <ul class="list-unstyled" id="detailsValues">
                                <li class="py-2" data-key="request-id"></li>
                                <li class="py-2" data-key="agency"></li>
                                <li class="py-2" data-key="contact"></li>
                                <li class="py-2" data-key="email"></li>
                                <li class="py-2" data-key="case-ref"></li>
                                <li class="py-2" data-key="purpose"></li>
                                <li class="py-2" data-key="legal"></li>
                                <li class="py-2" data-key="location"></li>
                                <li class="py-2" data-key="camera"></li>
                                <li class="py-2" data-key="footage-window"></li>
                                <li class="py-2" data-key="description"></li>
                                <li class="py-2" data-key="delivery"></li>
                                <li class="py-2" data-key="supporting"></li>
                                <li class="py-2" data-key="status"></li>
                                <li class="py-2" data-key="review-notes"></li>
                                <li class="py-2" data-key="rejection"></li>
                                <li class="py-2" data-key="fulfillment"></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Manage Modal -->
    <div class="modal fade" id="manageModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Manage CCTV Request</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="manageForm" method="POST">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="request_id" id="manage_request_id">
                        <div class="mb-3">
                            <label class="form-label">Status *</label>
                            <select id="manage_status" name="status" class="form-select">
                                <option value="Pending">Pending</option>
                                <option value="Under Review">Under Review</option>
                                <option value="Approved">Approved</option>
                                <option value="Rejected">Rejected</option>
                                <option value="Completed">Completed</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Approved Camera / Location</label>
                            <input type="text" id="manage_camera" name="camera_location" class="form-control" placeholder="e.g. CAM-001 - Main Entrance Camera">
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Actual Footage Start</label>
                                <input type="time" id="manage_start" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Actual Footage End</label>
                                <input type="time" id="manage_end" class="form-control">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Review Notes (internal)</label>
                            <textarea id="manage_notes" name="monitoring_notes" class="form-control" rows="4"></textarea>
                        </div>
                        <div class="d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-success">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function(){
        // details
        document.querySelectorAll('.btn-view').forEach(btn=>{
            btn.addEventListener('click', function(){
                const keys = ['request-id','agency','contact','email','case-ref','purpose','legal','location','camera','footage-window','description','delivery','supporting','status','review-notes','rejection','fulfillment'];
                // map data attributes to modal
                document.querySelectorAll('#detailsValues [data-key]').forEach(li=>{
                    const key = li.getAttribute('data-key');
                    const v = btn.getAttribute('data-' + key) || '';
                    li.textContent = v || '—';
                });
                var detailsModal = new bootstrap.Modal(document.getElementById('detailsModal'));
                detailsModal.show();
            });
        });

        // manage
        document.querySelectorAll('.btn-manage').forEach(btn=>{
            btn.addEventListener('click', function(){
                document.getElementById('manage_request_id').value = btn.getAttribute('data-id');
                document.getElementById('manage_status').value = btn.getAttribute('data-status-val') || 'Pending';
                document.getElementById('manage_camera').value = btn.getAttribute('data-camera-val') || 'CAM-001 - Main Entrance Camera';
                document.getElementById('manage_start').value = btn.getAttribute('data-start') || '';
                document.getElementById('manage_end').value = btn.getAttribute('data-end') || '';
                document.getElementById('manage_notes').value = btn.getAttribute('data-review-notes-val') || '';
                var manageModal = new bootstrap.Modal(document.getElementById('manageModal'));
                manageModal.show();
            });
        });

        // search & date filter
        const searchBox = document.getElementById('searchBox');
        const filterDate = document.getElementById('filterDate');
        function filterRows() {
            const query = (searchBox ? searchBox.value : '').toLowerCase().trim();
            const dateVal = (filterDate ? filterDate.value : '').trim();
            document.querySelectorAll('.cctv-record-row').forEach(row => {
                const text = row.innerText.toLowerCase();
                const rowDate = row.getAttribute('data-date') || '';
                const matchesQuery = !query || text.includes(query);
                const matchesDate = !dateVal || rowDate.includes(dateVal);
                row.style.display = (matchesQuery && matchesDate) ? '' : 'none';
            });
        }
        if (searchBox) searchBox.addEventListener('input', filterRows);
        if (filterDate) filterDate.addEventListener('change', filterRows);
    });
    </script>
