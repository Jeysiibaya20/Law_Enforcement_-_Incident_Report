<?php
$base_url = '../';
require_once __DIR__ . '/../admin/admin_auth.php';
require_once '../config/db_connect.php';
require_once '../config/LanguageManager.php';
require_once __DIR__ . '/OperationalModuleIntegrator.php';

$page_title = 'CCTV Footage Request';
$base_url = '../';
$current_page = 'request_form';
require_once '../includes/header.php';
require_once '../includes/navbar.php';

$pdo = getDBConnection();
$integrator = new OperationalModuleIntegrator($pdo);

$message = '';
$message_type = 'info';
$submitted = false;

// Handle Form Submissions & Modal Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_action = $_POST['action'] ?? 'create_request';

    // 1. Direct CCTV Dispatch to Partner API (Marto's Group)
    if ($form_action === 'dispatch_record_cctv') {
        $req_id = (int)($_POST['request_id'] ?? 0);
        if ($req_id > 0) {
            try {
                $stmt_r = $pdo->prepare("SELECT * FROM cctv_requests WHERE id = ?");
                $stmt_r->execute([$req_id]);
                $rec = $stmt_r->fetch(PDO::FETCH_ASSOC);
                if ($rec) {
                    $reqCode = $rec['request_id_code'] ?: ('CCTV-REQ-' . date('Y') . '-' . str_pad($rec['id'], 3, '0', STR_PAD_LEFT));
                    $res = $integrator->dispatchToPartnerCctvApi([
                        'request_id' => $reqCode,
                        'incident_id' => 'INC-' . $rec['id'],
                        'requesting_agency' => $rec['requesting_agency'] ?: 'Digital Blotter System',
                        'contact_person' => $rec['contact_person'] ?: 'Admin Requester',
                        'contact_number' => $rec['contact_number'] ?: '',
                        'email_address' => $rec['email_address'] ?: '',
                        'case_reference' => $rec['case_reference'] ?: '',
                        'legal_basis' => $rec['legal_basis'] ?: 'Law enforcement request',
                        'location' => $rec['incident_location'] ?: ($rec['camera_location'] ?: 'Quezon City'),
                        'camera' => $rec['camera_id'] ?: 'CAM-001 — Main Entrance Camera',
                        'incident_date' => $rec['incident_date'] ?: date('Y-m-d'),
                        'incident_type' => $rec['incident_type'] ?: 'Footage',
                        'timestamp_range' => [
                            'start_time' => ($rec['incident_date'] ?: date('Y-m-d')) . ' ' . ($rec['footage_start_time'] ?: ($rec['incident_time'] ?: '00:00:00')),
                            'end_time' => ($rec['incident_date'] ?: date('Y-m-d')) . ' ' . ($rec['footage_end_time'] ?: '23:59:59')
                        ],
                        'purpose' => $rec['purpose_reason'] ?: $rec['reason'],
                        'incident_description' => $rec['incident_description'] ?: $rec['reason'],
                        'delivery_method' => $rec['delivery_method'] ?: 'Secure download link',
                        'notes' => $rec['monitoring_notes']
                    ]);

                    $stmtUp = $pdo->prepare("UPDATE cctv_requests SET status = 'Dispatched', updated_at = NOW() WHERE id = ?");
                    $stmtUp->execute([$req_id]);

                    $message = "Dispatched Request #" . htmlspecialchars($reqCode) . " to Partner CCTV API (" . htmlspecialchars($res['endpoint']) . "). Result: " . ($res['success'] ? 'Success (200 OK Sent)' : 'Target Endpoint Recorded');
                    $message_type = $res['success'] ? 'success' : 'info';
                }
            } catch (Exception $e) {
                $message = "Could not dispatch request: " . htmlspecialchars($e->getMessage());
                $message_type = "danger";
            }
        }
    }

    // 2. Update Status / Approve / Reject
    elseif ($form_action === 'update_status' || $form_action === 'quick_status_change') {
        $req_id = (int)($_POST['request_id'] ?? 0);
        $status_val = trim($_POST['status'] ?? 'Pending');
        $camera_val = trim($_POST['camera_id'] ?? $_POST['camera_location'] ?? '');
        $notes_val = trim($_POST['review_notes'] ?? $_POST['monitoring_notes'] ?? '');
        $reject_val = trim($_POST['rejection_reason'] ?? '');

        if ($req_id > 0) {
            try {
                $up_stmt = $pdo->prepare("UPDATE cctv_requests SET 
                    status = ?, 
                    camera_id = COALESCE(NULLIF(?, ''), camera_id),
                    review_notes = COALESCE(NULLIF(?, ''), review_notes),
                    monitoring_notes = COALESCE(NULLIF(?, ''), monitoring_notes),
                    rejection_reason = COALESCE(NULLIF(?, ''), rejection_reason),
                    updated_at = NOW() 
                    WHERE id = ?");
                $up_stmt->execute([$status_val, $camera_val, $notes_val, $notes_val, $reject_val, $req_id]);
                $message = "CCTV Request #REQ-" . str_pad($req_id, 3, '0', STR_PAD_LEFT) . " status updated to '{$status_val}' successfully!";
                $message_type = "success";
            } catch (Exception $e) {
                $message = "Could not update request: " . htmlspecialchars($e->getMessage());
                $message_type = "danger";
            }
        }
    }

    // 3. Create CCTV Footage Request (Matching 4 Sections from Screenshots)
    elseif ($form_action === 'create_request') {
        // Section 1: Requesting Agency
        $requesting_agency = trim($_POST['requesting_agency'] ?? 'Digital Blotter System');
        $contact_person = trim($_POST['contact_person'] ?? '');
        $position_designation = trim($_POST['position_designation'] ?? '');
        $contact_number = trim($_POST['contact_number'] ?? '');
        $email_address = trim($_POST['email_address'] ?? '');
        $office_unit = trim($_POST['office_unit'] ?? '');

        // Section 2: Case Information
        $case_reference = trim($_POST['case_reference'] ?? '');
        $related_complaint_id = trim($_POST['related_complaint_id'] ?? '');
        $legal_basis = trim($_POST['legal_basis'] ?? '');
        $purpose_reason = trim($_POST['purpose_reason'] ?? '');

        // Section 3: Footage Requested
        $incident_location = trim($_POST['incident_location'] ?? '');
        $camera_id = trim($_POST['camera_id'] ?? '');
        $location_description = trim($_POST['location_description'] ?? '');
        $incident_date = trim($_POST['incident_date'] ?? '');
        $incident_type = trim($_POST['incident_type'] ?? 'Footage');
        $footage_start_time = trim($_POST['footage_start_time'] ?? '');
        $footage_end_time = trim($_POST['footage_end_time'] ?? '');
        $incident_description = trim($_POST['incident_description'] ?? '');

        // Section 4: Delivery & Acknowledgment
        $delivery_method = trim($_POST['delivery_method'] ?? 'Secure download link');
        $official_use_confirmed = !empty($_POST['official_use_confirmed']) ? 1 : 0;
        $privacy_terms_agreed = !empty($_POST['privacy_terms_agreed']) ? 1 : 0;

        // Handle Supporting Document File Upload
        $supporting_document = null;
        if (!empty($_FILES['supporting_document']['name']) && $_FILES['supporting_document']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../uploads/cctv_docs/';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0755, true);
            }
            $fileExt = pathinfo($_FILES['supporting_document']['name'], PATHINFO_EXTENSION);
            $newFileName = 'DOC_' . date('Ymd_His') . '_' . rand(100, 999) . '.' . $fileExt;
            $destPath = $uploadDir . $newFileName;
            if (move_uploaded_file($_FILES['supporting_document']['tmp_name'], $destPath)) {
                $supporting_document = $newFileName;
            }
        }

        if (empty($requesting_agency) || empty($contact_person) || empty($contact_number) || empty($legal_basis) || empty($purpose_reason) || empty($incident_location) || empty($incident_date) || empty($footage_start_time) || empty($footage_end_time) || empty($incident_description)) {
            $message = 'Please fill in all required fields marked with an asterisk (*).';
            $message_type = 'danger';
        } else {
            try {
                $reqCode = 'CCTV-REQ-' . date('Y') . '-' . rand(1000, 9999);
                $activeUserId = $_SESSION['admin_user_id'] ?? $_SESSION['user_id'] ?? 1;

                $insert_stmt = $pdo->prepare("INSERT INTO cctv_requests
                    (request_id_code, requested_by, requesting_agency, contact_person, position_designation, contact_number, email_address, office_unit,
                     case_reference, related_complaint_id, legal_basis, purpose_reason, supporting_document,
                     incident_location, camera_id, location_description, incident_date, incident_time, incident_type,
                     footage_start_time, footage_end_time, incident_description, delivery_method, official_use_confirmed,
                     privacy_terms_agreed, reason, camera_location, status, requested_at)
                    VALUES
                    (:request_id_code, :requested_by, :requesting_agency, :contact_person, :position_designation, :contact_number, :email_address, :office_unit,
                     :case_reference, :related_complaint_id, :legal_basis, :purpose_reason, :supporting_document,
                     :incident_location, :camera_id, :location_description, :incident_date, :incident_time, :incident_type,
                     :footage_start_time, :footage_end_time, :incident_description, :delivery_method, :official_use_confirmed,
                     :privacy_terms_agreed, :reason, :camera_location, 'Pending', NOW())");

                $insert_stmt->execute([
                    ':request_id_code' => $reqCode,
                    ':requested_by' => $activeUserId,
                    ':requesting_agency' => $requesting_agency,
                    ':contact_person' => $contact_person,
                    ':position_designation' => $position_designation ?: null,
                    ':contact_number' => $contact_number,
                    ':email_address' => $email_address ?: null,
                    ':office_unit' => $office_unit ?: null,
                    ':case_reference' => $case_reference ?: null,
                    ':related_complaint_id' => $related_complaint_id ?: null,
                    ':legal_basis' => $legal_basis,
                    ':purpose_reason' => $purpose_reason,
                    ':supporting_document' => $supporting_document,
                    ':incident_location' => $incident_location,
                    ':camera_id' => $camera_id ?: null,
                    ':location_description' => $location_description ?: null,
                    ':incident_date' => $incident_date,
                    ':incident_time' => $footage_start_time,
                    ':incident_type' => $incident_type,
                    ':footage_start_time' => $footage_start_time,
                    ':footage_end_time' => $footage_end_time,
                    ':incident_description' => $incident_description,
                    ':delivery_method' => $delivery_method,
                    ':official_use_confirmed' => $official_use_confirmed,
                    ':privacy_terms_agreed' => $privacy_terms_agreed,
                    ':reason' => $purpose_reason,
                    ':camera_location' => $incident_location
                ]);
                $newRequestId = $pdo->lastInsertId();

                // Dispatch to Partner CCTV API if configured
                $cctvDispatch = $integrator->dispatchToPartnerCctvApi([
                    'request_id' => $reqCode,
                    'incident_id' => 'INC-CCTV-' . $newRequestId,
                    'requesting_agency' => $requesting_agency,
                    'contact_person' => $contact_person,
                    'contact_number' => $contact_number,
                    'email_address' => $email_address,
                    'case_reference' => $case_reference,
                    'legal_basis' => $legal_basis,
                    'location' => $incident_location,
                    'camera' => $camera_id,
                    'incident_date' => $incident_date,
                    'incident_type' => $incident_type,
                    'timestamp_range' => [
                        'start_time' => "{$incident_date} {$footage_start_time}",
                        'end_time' => "{$incident_date} {$footage_end_time}"
                    ],
                    'purpose' => $purpose_reason,
                    'incident_description' => $incident_description,
                    'delivery_method' => $delivery_method,
                    'action' => 'request_cctv_footage'
                ]);

                $message = 'CCTV footage request ' . htmlspecialchars($reqCode) . ' has been recorded and submitted to Barangay San Agustin CCTV Surveillance records.';
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

// Fetch CCTV Request Records
try {
    $records_stmt = $pdo->prepare("SELECT r.*, COALESCE(s.fullname, s.emailadd, 'Admin') as requester_name FROM cctv_requests r LEFT JOIN signup s ON r.requested_by = s.user_id ORDER BY r.id DESC");
    $records_stmt->execute();
    $request_records = $records_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $request_records = [];
}
?>

<div class="main-content">
    <div class="content-container container py-4">
        <!-- Top Title & Search Bar -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h1 class="h2 fw-bold text-dark mb-0">
                    <i class="bi bi-camera-reels text-success me-2"></i>CCTV Request
                </h1>
                <p class="text-muted small mb-0">Manage and coordinate CCTV footage queries with partner surveillance networks.</p>
            </div>
            <div class="d-flex gap-2">
                <input type="search" id="searchBox" class="form-control shadow-sm" placeholder="Search by request ID, agency, contact, or location..." style="min-width:320px;" onkeyup="filterAllRecords(this.value)">
                <input type="date" id="filterDate" class="form-control shadow-sm" style="max-width:170px;" onchange="filterAllRecordsByDate(this.value)">
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo htmlspecialchars($message_type); ?> alert-dismissible fade show shadow-sm mb-4" role="alert">
                <i class="fas fa-info-circle me-2"></i><?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- ======================================================== -->
        <!-- CCTV FOOTAGE REQUEST FORM (SECTIONS 1 TO 4 AS REQUESTED) -->
        <!-- ======================================================== -->
        <div class="row">
            <div class="col-12">
                <div class="card shadow-sm border-0 rounded-3 mb-4">
                    <!-- Form Header -->
                    <div class="card-header bg-white border-bottom py-3 px-4">
                        <h4 class="fw-bold mb-1 text-dark d-flex align-items-center">
                            <i class="bi bi-file-earmark-text text-primary fs-4 me-2"></i> CCTV Footage Request
                        </h4>
                        <p class="text-secondary small mb-0">Authorized agencies and partner groups may use this form to request CCTV footage from Barangay San Agustin surveillance records.</p>
                    </div>

                    <div class="card-body p-4">
                        <form method="POST" enctype="multipart/form-data" id="cctvRequestForm">
                            <input type="hidden" name="action" value="create_request">

                            <!-- SECTION 1: REQUESTING AGENCY -->
                            <div class="mb-4">
                                <h5 class="fw-bold text-dark border-bottom pb-2 mb-3">1. Requesting Agency</h5>
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label for="requesting_agency" class="form-label fw-semibold">Requesting Agency / Group <span class="text-danger">*</span></label>
                                        <input type="text" id="requesting_agency" name="requesting_agency" class="form-control" placeholder="e.g. Digital Blotter System, PNP, Barangay Hall" value="<?php echo htmlspecialchars($_POST['requesting_agency'] ?? 'Digital Blotter System'); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="contact_person" class="form-label fw-semibold">Contact Person <span class="text-danger">*</span></label>
                                        <input type="text" id="contact_person" name="contact_person" class="form-control" value="<?php echo htmlspecialchars($_POST['contact_person'] ?? ($_SESSION['admin_fullname'] ?? '')); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="position_designation" class="form-label fw-semibold">Position / Designation</label>
                                        <input type="text" id="position_designation" name="position_designation" class="form-control" value="<?php echo htmlspecialchars($_POST['position_designation'] ?? 'Investigator / Duty Officer'); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="contact_number" class="form-label fw-semibold">Contact Number <span class="text-danger">*</span></label>
                                        <input type="text" id="contact_number" name="contact_number" class="form-control" placeholder="09XXXXXXXXX" value="<?php echo htmlspecialchars($_POST['contact_number'] ?? ''); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="email_address" class="form-label fw-semibold">Email Address</label>
                                        <input type="email" id="email_address" name="email_address" class="form-control" placeholder="name@domain.com" value="<?php echo htmlspecialchars($_POST['email_address'] ?? ($_SESSION['user_email'] ?? '')); ?>">
                                    </div>
                                    <div class="col-12">
                                        <label for="office_unit" class="form-label fw-semibold">Office / Unit</label>
                                        <input type="text" id="office_unit" name="office_unit" class="form-control" placeholder="e.g. Investigation Section, Records Division" value="<?php echo htmlspecialchars($_POST['office_unit'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>

                            <!-- SECTION 2: CASE INFORMATION -->
                            <div class="mb-4">
                                <h5 class="fw-bold text-dark border-bottom pb-2 mb-3">2. Case Information</h5>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="case_reference" class="form-label fw-semibold">Case / Blotter Reference</label>
                                        <input type="text" id="case_reference" name="case_reference" class="form-control" placeholder="e.g. DB-2026-001" value="<?php echo htmlspecialchars($_POST['case_reference'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="related_complaint_id" class="form-label fw-semibold">Related Complaint ID</label>
                                        <input type="text" id="related_complaint_id" name="related_complaint_id" class="form-control" placeholder="e.g. COMP-2026-362" value="<?php echo htmlspecialchars($_POST['related_complaint_id'] ?? ''); ?>">
                                    </div>
                                    <div class="col-12">
                                        <label for="legal_basis" class="form-label fw-semibold">Legal Basis <span class="text-danger">*</span></label>
                                        <select id="legal_basis" name="legal_basis" class="form-select" required>
                                            <option value="">Select legal basis</option>
                                            <option value="Law enforcement request" <?php echo ($_POST['legal_basis'] ?? 'Law enforcement request') === 'Law enforcement request' ? 'selected' : ''; ?>>Law enforcement request</option>
                                            <option value="Court Order / Subpoena" <?php echo ($_POST['legal_basis'] ?? '') === 'Court Order / Subpoena' ? 'selected' : ''; ?>>Court Order / Subpoena</option>
                                            <option value="Investigation" <?php echo ($_POST['legal_basis'] ?? '') === 'Investigation' ? 'selected' : ''; ?>>Investigation</option>
                                            <option value="Barangay Blotter referral" <?php echo ($_POST['legal_basis'] ?? '') === 'Barangay Blotter referral' ? 'selected' : ''; ?>>Barangay Blotter referral</option>
                                            <option value="Official Inquest" <?php echo ($_POST['legal_basis'] ?? '') === 'Official Inquest' ? 'selected' : ''; ?>>Official Inquest</option>
                                            <option value="Other" <?php echo ($_POST['legal_basis'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
                                        </select>
                                    </div>
                                    <div class="col-12">
                                        <label for="purpose_reason" class="form-label fw-semibold">Purpose / Reason for Request <span class="text-danger">*</span></label>
                                        <textarea id="purpose_reason" name="purpose_reason" class="form-control" rows="3" placeholder="Explain why the footage is needed" required><?php echo htmlspecialchars($_POST['purpose_reason'] ?? ''); ?></textarea>
                                    </div>
                                    <div class="col-12">
                                        <label for="supporting_document" class="form-label fw-semibold">Supporting Document (optional)</label>
                                        <input type="file" id="supporting_document" name="supporting_document" class="form-control" accept=".pdf,image/*">
                                        <div class="form-text text-muted small">Court order, referral letter, or blotter copy (PDF or image).</div>
                                    </div>
                                </div>
                            </div>

                            <!-- SECTION 3: FOOTAGE REQUESTED -->
                            <div class="mb-4">
                                <h5 class="fw-bold text-dark border-bottom pb-2 mb-3">3. Footage Requested</h5>
                                    <div class="col-12">
                                        <label class="form-label fw-semibold"><i class="fas fa-map-marked-alt text-success me-1"></i>Incident Location (Quezon City) <span class="text-danger">*</span></label>
                                        <div class="row g-2 p-3 bg-light rounded-3 border">
                                            <div class="col-md-3">
                                                <label class="form-label small fw-semibold text-dark">District (QC) *</label>
                                                <select id="req_district" class="form-select form-select-sm" required>
                                                    <option value="">Select District</option>
                                                    <option value="1">District 1</option>
                                                    <option value="2">District 2</option>
                                                    <option value="3">District 3</option>
                                                    <option value="4">District 4</option>
                                                    <option value="5">District 5</option>
                                                    <option value="6">District 6</option>
                                                </select>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label small fw-semibold text-dark">Barangay (QC) *</label>
                                                <select id="req_barangay" class="form-select form-select-sm" required disabled>
                                                    <option value="">Select District first</option>
                                                </select>
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label small fw-semibold text-dark">Bldg / Unit #</label>
                                                <input type="text" id="req_house" class="form-control form-control-sm" placeholder="e.g. #12">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label small fw-semibold text-dark">Street / Specific Landmark *</label>
                                                <input type="text" id="req_street" class="form-control form-control-sm" placeholder="e.g. Susano Road" required>
                                            </div>
                                            <input type="hidden" id="incident_location" name="incident_location" value="<?php echo htmlspecialchars($_POST['incident_location'] ?? ''); ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="camera_id" class="form-label fw-semibold">Camera</label>
                                        <select id="camera_id" name="camera_id" class="form-select">
                                            <option value="">Select camera (optional)</option>
                                            <option value="CAM-001 — Main Entrance Camera" <?php echo ($_POST['camera_id'] ?? '') === 'CAM-001 — Main Entrance Camera' ? 'selected' : ''; ?>>CAM-001 — Main Entrance Camera</option>
                                            <option value="CAM-002 — Susano Road North" <?php echo ($_POST['camera_id'] ?? '') === 'CAM-002 — Susano Road North' ? 'selected' : ''; ?>>CAM-002 — Susano Road North</option>
                                            <option value="CAM-003 — Susano Road South" <?php echo ($_POST['camera_id'] ?? '') === 'CAM-003 — Susano Road South' ? 'selected' : ''; ?>>CAM-003 — Susano Road South</option>
                                            <option value="CAM-004 — Barangay Hall Perimeter" <?php echo ($_POST['camera_id'] ?? '') === 'CAM-004 — Barangay Hall Perimeter' ? 'selected' : ''; ?>>CAM-004 — Barangay Hall Perimeter</option>
                                            <option value="CAM-005 — Plaza / Covered Court" <?php echo ($_POST['camera_id'] ?? '') === 'CAM-005 — Plaza / Covered Court' ? 'selected' : ''; ?>>CAM-005 — Plaza / Covered Court</option>
                                            <option value="CAM-006 — Outpost / Checkpoint" <?php echo ($_POST['camera_id'] ?? '') === 'CAM-006 — Outpost / Checkpoint' ? 'selected' : ''; ?>>CAM-006 — Outpost / Checkpoint</option>
                                            <option value="Other Camera / Unlisted" <?php echo ($_POST['camera_id'] ?? '') === 'Other Camera / Unlisted' ? 'selected' : ''; ?>>Other Camera / Unlisted</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="location_description" class="form-label fw-semibold">Location Description (if camera unknown)</label>
                                        <input type="text" id="location_description" name="location_description" class="form-control" placeholder="Nearest camera or area description" value="<?php echo htmlspecialchars($_POST['location_description'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="incident_date" class="form-label fw-semibold">Incident Date <span class="text-danger">*</span></label>
                                        <input type="date" id="incident_date" name="incident_date" class="form-control" value="<?php echo htmlspecialchars($_POST['incident_date'] ?? date('Y-m-d')); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="incident_type" class="form-label fw-semibold">Incident Type</label>
                                        <select id="incident_type" name="incident_type" class="form-select">
                                            <option value="">Select type</option>
                                            <option value="Theft / Robbery" <?php echo ($_POST['incident_type'] ?? '') === 'Theft / Robbery' ? 'selected' : ''; ?>>Theft / Robbery</option>
                                            <option value="Physical Assault" <?php echo ($_POST['incident_type'] ?? '') === 'Physical Assault' ? 'selected' : ''; ?>>Physical Assault</option>
                                            <option value="Traffic Accident" <?php echo ($_POST['incident_type'] ?? '') === 'Traffic Accident' ? 'selected' : ''; ?>>Traffic Accident</option>
                                            <option value="Public Disturbance" <?php echo ($_POST['incident_type'] ?? '') === 'Public Disturbance' ? 'selected' : ''; ?>>Public Disturbance</option>
                                            <option value="Suspicious Activity" <?php echo ($_POST['incident_type'] ?? '') === 'Suspicious Activity' ? 'selected' : ''; ?>>Suspicious Activity</option>
                                            <option value="Vandalism" <?php echo ($_POST['incident_type'] ?? '') === 'Vandalism' ? 'selected' : ''; ?>>Vandalism</option>
                                            <option value="Property Dispute" <?php echo ($_POST['incident_type'] ?? '') === 'Property Dispute' ? 'selected' : ''; ?>>Property Dispute</option>
                                            <option value="Other" <?php echo ($_POST['incident_type'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="footage_start_time" class="form-label fw-semibold">Footage Start Time <span class="text-danger">*</span></label>
                                        <input type="time" id="footage_start_time" name="footage_start_time" class="form-control" value="<?php echo htmlspecialchars($_POST['footage_start_time'] ?? '16:30'); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="footage_end_time" class="form-label fw-semibold">Footage End Time <span class="text-danger">*</span></label>
                                        <input type="time" id="footage_end_time" name="footage_end_time" class="form-control" value="<?php echo htmlspecialchars($_POST['footage_end_time'] ?? '17:00'); ?>" required>
                                    </div>
                                    <div class="col-12">
                                        <label for="incident_description" class="form-label fw-semibold">Incident Description <span class="text-danger">*</span></label>
                                        <textarea id="incident_description" name="incident_description" class="form-control" rows="3" placeholder="Describe what happened to help locate the correct footage" required><?php echo htmlspecialchars($_POST['incident_description'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                            </div>

                            <!-- SECTION 4: DELIVERY & ACKNOWLEDGMENT -->
                            <div class="mb-4">
                                <h5 class="fw-bold text-dark border-bottom pb-2 mb-3">4. Delivery & Acknowledgment</h5>
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label for="delivery_method" class="form-label fw-semibold">Preferred Delivery Method <span class="text-danger">*</span></label>
                                        <select id="delivery_method" name="delivery_method" class="form-select" required>
                                            <option value="Secure download link" <?php echo ($_POST['delivery_method'] ?? 'Secure download link') === 'Secure download link' ? 'selected' : ''; ?>>Secure download link</option>
                                            <option value="Physical flash drive / disk" <?php echo ($_POST['delivery_method'] ?? '') === 'Physical flash drive / disk' ? 'selected' : ''; ?>>Physical flash drive / disk</option>
                                            <option value="Encrypted email attachment" <?php echo ($_POST['delivery_method'] ?? '') === 'Encrypted email attachment' ? 'selected' : ''; ?>>Encrypted email attachment</option>
                                            <option value="In-person review at Control Room" <?php echo ($_POST['delivery_method'] ?? '') === 'In-person review at Control Room' ? 'selected' : ''; ?>>In-person review at Control Room</option>
                                        </select>
                                    </div>
                                    <div class="col-12">
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" id="official_use_confirmed" name="official_use_confirmed" value="1" required checked>
                                            <label class="form-check-label text-dark" for="official_use_confirmed">
                                                I confirm this request is for legitimate official use only.
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="privacy_terms_agreed" name="privacy_terms_agreed" value="1" required checked>
                                            <label class="form-check-label text-dark" for="privacy_terms_agreed">
                                                I understand the footage will be used only for the stated purpose and handled according to data privacy guidelines.
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Buttons -->
                            <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                                <button type="reset" class="btn btn-light border px-4 py-2 fw-semibold">
                                    Clear
                                </button>
                                <button type="submit" class="btn text-white px-4 py-2 fw-bold shadow-sm" style="background-color: #2e7d6f; border-color: #2e7d6f;">
                                    <i class="bi bi-send me-1"></i> Submit Request
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- ======================================================== -->
        <!-- REQUEST RECORDS (TABLE VIEW & CAROUSEL VIEW)            -->
        <!-- ======================================================== -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card shadow-sm mb-4 border-0">
                    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center flex-wrap gap-2 py-3">
                        <div class="d-flex align-items-center gap-2">
                            <h5 class="mb-0 fw-bold text-white"><i class="bi bi-camera-video text-warning me-2"></i>Request Records</h5>
                            <span class="badge bg-primary fw-bold" id="reqTotalBadge"><?php echo count($request_records); ?> record(s)</span>
                        </div>

                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <div class="input-group input-group-sm" style="width: 220px;">
                                <span class="input-group-text bg-light text-dark border-0"><i class="bi bi-search"></i></span>
                                <input type="text" id="reqSearchInput" class="form-control border-0" placeholder="Search requests..." onkeyup="filterRequestRecords()">
                            </div>

                            <select id="reqPageSizeSelect" class="form-select form-select-sm" style="width: auto;" onchange="changeReqPageSize(this.value)">
                                <option value="10" selected>10 per page</option>
                                <option value="25">25 per page</option>
                                <option value="50">50 per page</option>
                                <option value="1000">Show All</option>
                            </select>

                            <div class="btn-group btn-group-sm" role="group">
                                <button type="button" class="btn btn-light active" id="btnReqTableView" onclick="switchReqView('table')">
                                    <i class="bi bi-table me-1"></i> Table View
                                </button>
                                <button type="button" class="btn btn-light" id="btnReqCarouselView" onclick="switchReqView('carousel')">
                                    <i class="bi bi-view-stacked me-1"></i> Carousel View
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="card-body p-0">
                        <!-- TABLE VIEW -->
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
                                            <?php foreach ($request_records as $idx => $record): 
                                                $code = $record['request_id_code'] ?: ('CCTV-REQ-' . date('Y', strtotime($record['requested_at'])) . '-' . str_pad((int)$record['id'], 3, '0', STR_PAD_LEFT));
                                                $agencyName = $record['requesting_agency'] ?: 'Digital Blotter System';
                                                $contactName = $record['contact_person'] ?: ($record['requester_name'] ?? 'Admin');
                                                $cameraDisplay = $record['camera_id'] ?: ($record['camera_location'] ?: 'CAM-001 — Main Entrance Camera');
                                                $locationDisplay = $record['incident_location'] ?: ($record['camera_location'] ?: 'Quezon City');
                                                $timeWindow = ($record['incident_date'] ?: date('Y-m-d', strtotime($record['requested_at']))) . 
                                                    (($record['footage_start_time'] || $record['footage_end_time']) ? (' ' . substr($record['footage_start_time'] ?? '', 0, 5) . ' - ' . substr($record['footage_end_time'] ?? '', 0, 5)) : '');
                                            ?>
                                                <tr class="req-record-row" 
                                                    data-index="<?php echo $idx; ?>"
                                                    data-req-id="<?php echo strtolower($code); ?>"
                                                    data-agency="<?php echo strtolower(htmlspecialchars($agencyName)); ?>"
                                                    data-contact="<?php echo strtolower(htmlspecialchars($contactName)); ?>"
                                                    data-location="<?php echo strtolower(htmlspecialchars($locationDisplay)); ?>"
                                                    data-status="<?php echo strtolower(htmlspecialchars($record['status'] ?? 'Pending')); ?>"
                                                    data-date="<?php echo htmlspecialchars($record['incident_date'] ?? date('Y-m-d', strtotime($record['requested_at']))); ?>">
                                                    <td class="text-muted small fw-bold"><?php echo $idx + 1; ?></td>
                                                    <td><strong class="text-primary"><?php echo htmlspecialchars($code); ?></strong></td>
                                                    <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($agencyName); ?></span></td>
                                                    <td><?php echo htmlspecialchars($contactName); ?></td>
                                                    <td>
                                                        <div class="text-truncate" style="max-width: 200px;">
                                                            <i class="bi bi-camera-video text-secondary me-1"></i><?php echo htmlspecialchars($cameraDisplay); ?>
                                                        </div>
                                                        <small class="text-muted d-block"><?php echo htmlspecialchars($locationDisplay); ?></small>
                                                    </td>
                                                    <td><small class="fw-semibold text-dark"><?php echo htmlspecialchars($timeWindow); ?></small></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo match($record['status'] ?? 'Pending') {
                                                            'Approved', 'Completed', 'Dispatched', 'Fulfilled' => 'success',
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
                                                                data-request-id="<?php echo htmlspecialchars($code); ?>"
                                                                data-agency="<?php echo htmlspecialchars($agencyName); ?>"
                                                                data-contact="<?php echo htmlspecialchars($record['contact_number'] ? ($contactName . ' (' . $record['contact_number'] . ')') : $contactName); ?>"
                                                                data-contact-number="<?php echo htmlspecialchars($record['contact_number'] ?: '—'); ?>"
                                                                data-email="<?php echo htmlspecialchars($record['email_address'] ?: '—'); ?>"
                                                                data-case-ref="<?php echo htmlspecialchars($record['case_reference'] ?: '—'); ?>"
                                                                data-purpose="<?php echo htmlspecialchars($record['purpose_reason'] ?: ($record['reason'] ?: '—')); ?>"
                                                                data-legal="<?php echo htmlspecialchars($record['legal_basis'] ?: 'Law enforcement request'); ?>"
                                                                data-location="<?php echo htmlspecialchars($locationDisplay); ?>"
                                                                data-camera="<?php echo htmlspecialchars($cameraDisplay); ?>"
                                                                data-footage-window="<?php echo htmlspecialchars($timeWindow); ?>"
                                                                data-description="<?php echo htmlspecialchars($record['incident_description'] ?: ($record['reason'] ?: '—')); ?>"
                                                                data-delivery="<?php echo htmlspecialchars($record['delivery_method'] ?: 'Secure download link'); ?>"
                                                                data-supporting="<?php echo htmlspecialchars($record['supporting_document'] ?: 'None'); ?>"
                                                                data-review-notes="<?php echo htmlspecialchars($record['review_notes'] ?: ($record['monitoring_notes'] ?: '—')); ?>"
                                                                data-date-submitted="<?php echo htmlspecialchars(date('M d, Y, h:i A', strtotime($record['requested_at']))); ?>"
                                                                data-status="<?php echo htmlspecialchars($record['status'] ?? 'Pending'); ?>"
                                                                ><i class="bi bi-eye me-1"></i>View</button>

                                                            <button type="button" class="btn btn-sm btn-outline-warning btn-manage" 
                                                                style="font-weight: 600;"
                                                                data-id="<?php echo (int)$record['id']; ?>"
                                                                data-status-val="<?php echo htmlspecialchars($record['status'] ?? 'Pending'); ?>"
                                                                data-camera-val="<?php echo htmlspecialchars($cameraDisplay); ?>"
                                                                data-review-notes-val="<?php echo htmlspecialchars($record['review_notes'] ?: ($record['monitoring_notes'] ?: '')); ?>"
                                                                data-rejection-val="<?php echo htmlspecialchars($record['rejection_reason'] ?: ''); ?>"
                                                                ><i class="bi bi-sliders me-1"></i>Manage</button>

                                                            <form method="POST" class="d-inline" onsubmit="return confirm('Dispatch request <?php echo htmlspecialchars($code); ?> directly to Partner CCTV API?');">
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
                                                <td colspan="9" class="text-center text-muted py-4">No request records yet. Submit a request above to view it here.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3 pt-2 border-top">
                                <div class="text-muted small" id="reqPaginationInfo">
                                    Showing 1 to <?php echo min(10, count($request_records)); ?> of <?php echo count($request_records); ?> entries
                                </div>
                                <nav aria-label="Request table pagination">
                                    <ul class="pagination pagination-sm mb-0" id="reqPaginationControls"></ul>
                                </nav>
                            </div>
                        </div>

                        <!-- CAROUSEL VIEW -->
                        <div id="reqCarouselView" class="p-3" style="display: none;">
                            <?php 
                                $reqBatches = array_chunk($request_records, 10);
                                $totalReqSlides = count($reqBatches);
                            ?>
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3 p-2 bg-light rounded border">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge bg-primary px-3 py-2 fs-6">
                                        <i class="bi bi-view-stacked me-1"></i> <span id="reqCarouselSlideLabel">Slide 1 of <?php echo max(1, $totalReqSlides); ?></span>
                                    </span>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <button class="btn btn-sm btn-primary rounded-circle" type="button" data-bs-target="#requestRecordsCarousel" data-bs-slide="prev" style="width:34px; height:34px;">
                                        <i class="bi bi-chevron-left"></i>
                                    </button>
                                    <div class="d-flex gap-1" id="reqCarouselIndicators">
                                        <?php for ($s = 0; $s < $totalReqSlides; $s++): ?>
                                            <button class="btn btn-sm <?php echo $s === 0 ? 'btn-primary' : 'btn-outline-secondary'; ?> fw-bold py-1 px-2" type="button" data-bs-target="#requestRecordsCarousel" data-bs-slide-to="<?php echo $s; ?>">
                                                <?php echo $s + 1; ?>
                                            </button>
                                        <?php endfor; ?>
                                    </div>
                                    <button class="btn btn-sm btn-primary rounded-circle" type="button" data-bs-target="#requestRecordsCarousel" data-bs-slide="next" style="width:34px; height:34px;">
                                        <i class="bi bi-chevron-right"></i>
                                    </button>
                                </div>
                            </div>

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
                                            <div class="carousel-item <?php echo $slideIdx === 0 ? 'active' : ''; ?>">
                                                <div class="row g-3">
                                                    <?php foreach ($batch as $cardIdx => $rec): 
                                                        $cCode = $rec['request_id_code'] ?: ('CCTV-REQ-' . date('Y', strtotime($rec['requested_at'])) . '-' . str_pad((int)$rec['id'], 3, '0', STR_PAD_LEFT));
                                                    ?>
                                                        <div class="col-md-6 req-carousel-card-col">
                                                            <div class="card h-100 border shadow-sm">
                                                                <div class="card-header bg-light d-flex justify-content-between align-items-center py-2 px-3">
                                                                    <div>
                                                                        <strong class="text-primary"><?php echo htmlspecialchars($cCode); ?></strong>
                                                                        <span class="badge bg-light text-dark border ms-1"><?php echo htmlspecialchars($rec['requesting_agency'] ?: 'Digital Blotter'); ?></span>
                                                                    </div>
                                                                    <span class="badge bg-<?php echo match($rec['status'] ?? 'Pending') {
                                                                        'Approved', 'Completed', 'Dispatched', 'Fulfilled' => 'success',
                                                                        'Under Review', 'Processing' => 'info',
                                                                        'Rejected', 'Cancelled' => 'danger',
                                                                        default => 'warning text-dark'
                                                                    }; ?>"><?php echo htmlspecialchars($rec['status'] ?? 'Pending'); ?></span>
                                                                </div>
                                                                <div class="card-body p-3">
                                                                    <div class="row g-2 small">
                                                                        <div class="col-6">
                                                                            <span class="text-muted d-block text-uppercase fw-bold" style="font-size:0.7rem;">Requester</span>
                                                                            <strong class="text-dark"><?php echo htmlspecialchars($rec['contact_person'] ?: ($rec['requester_name'] ?? 'Admin')); ?></strong>
                                                                        </div>
                                                                        <div class="col-6">
                                                                            <span class="text-muted d-block text-uppercase fw-bold" style="font-size:0.7rem;">Camera / Location</span>
                                                                            <span class="text-truncate d-block"><?php echo htmlspecialchars($rec['camera_id'] ?: ($rec['incident_location'] ?: 'CAM-001')); ?></span>
                                                                        </div>
                                                                        <div class="col-12">
                                                                            <span class="text-muted d-block text-uppercase fw-bold" style="font-size:0.7rem;">Purpose</span>
                                                                            <span class="text-truncate d-block"><?php echo htmlspecialchars($rec['purpose_reason'] ?: ($rec['reason'] ?: 'No purpose provided')); ?></span>
                                                                        </div>
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
</div>

<!-- ======================================================== -->
<!-- FOOTAGE REQUEST DETAILS MODAL (MATCHING SCREENSHOT 5)    -->
<!-- ======================================================== -->
<div class="modal fade" id="footageDetailsModal" tabindex="-1" aria-labelledby="footageDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 650px;">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px; overflow: hidden;">
            <div class="modal-header border-bottom py-3 px-4 bg-white">
                <h5 class="modal-title fw-bold text-dark mb-0" id="footageDetailsModalLabel">
                    Footage Request Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <div class="modal-body p-4" style="font-size: 0.92rem; color: #212529;">
                <div class="row mb-2">
                    <div class="col-5 text-secondary fw-semibold">Request ID</div>
                    <div class="col-7 text-dark fw-bold" id="modalReqId">—</div>
                </div>
                <div class="row mb-2">
                    <div class="col-5 text-secondary fw-semibold">Agency</div>
                    <div class="col-7 text-dark" id="modalAgency">—</div>
                </div>
                <div class="row mb-2">
                    <div class="col-5 text-secondary fw-semibold">Contact Number</div>
                    <div class="col-7 text-dark" id="modalContactNumber">—</div>
                </div>
                <div class="row mb-2">
                    <div class="col-5 text-secondary fw-semibold">Email</div>
                    <div class="col-7 text-dark" id="modalEmail">—</div>
                </div>
                <div class="row mb-2">
                    <div class="col-5 text-secondary fw-semibold">Case Reference</div>
                    <div class="col-7 text-dark" id="modalCaseRef">—</div>
                </div>
                <div class="row mb-2">
                    <div class="col-5 text-secondary fw-semibold">Purpose</div>
                    <div class="col-7 text-dark" id="modalPurpose">—</div>
                </div>
                <div class="row mb-2">
                    <div class="col-5 text-secondary fw-semibold">Legal Basis</div>
                    <div class="col-7 text-dark" id="modalLegalBasis">—</div>
                </div>
                <div class="row mb-2">
                    <div class="col-5 text-secondary fw-semibold">Incident Location</div>
                    <div class="col-7 text-dark" id="modalLocation">—</div>
                </div>
                <div class="row mb-2">
                    <div class="col-5 text-secondary fw-semibold">Camera</div>
                    <div class="col-7 text-dark" id="modalCamera">—</div>
                </div>
                <div class="row mb-2">
                    <div class="col-5 text-secondary fw-semibold">Footage Window</div>
                    <div class="col-7 text-dark" id="modalFootageWindow">—</div>
                </div>
                <div class="row mb-2">
                    <div class="col-5 text-secondary fw-semibold">Incident Description</div>
                    <div class="col-7 text-dark" id="modalDescription">—</div>
                </div>
                <div class="row mb-2">
                    <div class="col-5 text-secondary fw-semibold">Delivery Method</div>
                    <div class="col-7 text-dark" id="modalDelivery">—</div>
                </div>
                <div class="row mb-2">
                    <div class="col-5 text-secondary fw-semibold">Supporting Document</div>
                    <div class="col-7 text-dark" id="modalSupportingDoc">—</div>
                </div>
                <div class="row mb-2">
                    <div class="col-5 text-secondary fw-semibold">Review Notes</div>
                    <div class="col-7 text-dark" id="modalReviewNotes">—</div>
                </div>
                <div class="row mb-2">
                    <div class="col-5 text-secondary fw-semibold">Date Submitted</div>
                    <div class="col-7 text-dark" id="modalDateSubmitted">—</div>
                </div>
                <div class="row mb-0">
                    <div class="col-5 text-secondary fw-semibold">Status</div>
                    <div class="col-7" id="modalStatusBadge"><span class="badge bg-warning text-dark">Pending</span></div>
                </div>
            </div>

            <div class="modal-footer border-top py-3 px-4 bg-white d-flex justify-content-start gap-2">
                <form method="POST" id="modalQuickStatusForm" class="d-inline">
                    <input type="hidden" name="action" value="quick_status_change">
                    <input type="hidden" name="request_id" id="modalQuickReqId" value="0">
                    <input type="hidden" name="status" id="modalQuickStatusVal" value="Approved">
                    <input type="hidden" name="rejection_reason" id="modalQuickRejectReason" value="">

                    <button type="button" class="btn text-white px-3 fw-bold" style="background-color: #2e7d6f;" onclick="submitModalDecision('Approved')">
                        <i class="fas fa-check me-1"></i> Approve
                    </button>
                    <button type="button" class="btn btn-danger px-3 fw-bold" onclick="submitModalDecision('Rejected')">
                        <i class="fas fa-times me-1"></i> Reject
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Manage Modal -->
<div class="modal fade" id="manageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title text-white"><i class="fas fa-sliders-h me-2 text-warning"></i>Manage CCTV Request</h5>
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
                            <option value="Fulfilled">Fulfilled</option>
                            <option value="Rejected">Rejected</option>
                            <option value="Dispatched">Dispatched</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Assigned Camera / Location</label>
                        <input type="text" id="manage_camera" name="camera_id" class="form-control" placeholder="e.g. CAM-001 — Main Entrance Camera">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Review Notes</label>
                        <textarea id="manage_notes" name="review_notes" class="form-control" rows="3" placeholder="Internal monitoring feedback..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Rejection Reason (if rejected)</label>
                        <textarea id="manage_rejection" name="rejection_reason" class="form-control" rows="2" placeholder="State reason if rejecting..."></textarea>
                    </div>
                    <div class="d-flex justify-content-end gap-2 pt-2 border-top">
                        <button type="button" class="btn btn-outline-success px-4 fw-semibold" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success fw-bold shadow-sm" style="background-color: #2e856e; border-color: #2e856e;"><i class="fas fa-save me-1"></i> Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// ================= REQUEST RECORDS ENGINE =================
let currentReqPage = 1;
let reqRowsPerPage = 10;
let filteredReqRows = [];

function initReqCatalog() {
    const rows = document.querySelectorAll('#reqTableBody tr.req-record-row');
    filteredReqRows = Array.from(rows);
    renderReqPagination();
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

    filteredReqRows = [];
    allRows.forEach(row => {
        const text = (
            (row.getAttribute('data-req-id') || '') + ' ' +
            (row.getAttribute('data-agency') || '') + ' ' +
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

    currentReqPage = 1;
    renderReqPagination();
}

function filterAllRecords(q) {
    const input = document.getElementById('reqSearchInput');
    if (input) {
        input.value = q;
        filterRequestRecords();
    }
}

function filterAllRecordsByDate(dt) {
    const allRows = document.querySelectorAll('#reqTableBody tr.req-record-row');
    filteredReqRows = [];
    allRows.forEach(row => {
        const rDate = row.getAttribute('data-date') || '';
        if (!dt || rDate === dt) {
            filteredReqRows.push(row);
        } else {
            row.style.display = 'none';
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

function submitModalDecision(status) {
    const reqId = document.getElementById('modalQuickReqId').value;
    if (!reqId || reqId === '0') {
        alert('Invalid Request ID');
        return;
    }

    let rejectReason = '';
    if (status === 'Rejected') {
        rejectReason = prompt('Please enter the reason for rejecting this footage request:') || '';
        if (rejectReason.trim() === '') {
            if (!confirm('Proceed with rejection without specific reason notes?')) return;
        }
    }

    document.getElementById('modalQuickStatusVal').value = status;
    document.getElementById('modalQuickRejectReason').value = rejectReason;
    document.getElementById('modalQuickStatusForm').submit();
}

document.addEventListener('DOMContentLoaded', function(){
    initReqCatalog();

    // VIEW MODAL (MATCHING SCREENSHOT 5)
    document.querySelectorAll('.btn-view').forEach(btn => {
        btn.addEventListener('click', function(){
            const id = this.getAttribute('data-id') || '';
            const reqId = this.getAttribute('data-request-id') || '—';
            const agency = this.getAttribute('data-agency') || '—';
            const contactNo = this.getAttribute('data-contact-number') || '—';
            const email = this.getAttribute('data-email') || '—';
            const caseRef = this.getAttribute('data-case-ref') || '—';
            const purpose = this.getAttribute('data-purpose') || '—';
            const legal = this.getAttribute('data-legal') || '—';
            const loc = this.getAttribute('data-location') || '—';
            const camera = this.getAttribute('data-camera') || '—';
            const footageWindow = this.getAttribute('data-footage-window') || '—';
            const desc = this.getAttribute('data-description') || '—';
            const delivery = this.getAttribute('data-delivery') || '—';
            const supporting = this.getAttribute('data-supporting') || 'None';
            const reviewNotes = this.getAttribute('data-review-notes') || '—';
            const dateSub = this.getAttribute('data-date-submitted') || '—';
            const status = this.getAttribute('data-status') || 'Pending';

            document.getElementById('modalQuickReqId').value = id;
            document.getElementById('modalReqId').textContent = reqId;
            document.getElementById('modalAgency').textContent = agency;
            document.getElementById('modalContactNumber').textContent = contactNo;
            document.getElementById('modalEmail').textContent = email;
            document.getElementById('modalCaseRef').textContent = caseRef;
            document.getElementById('modalPurpose').textContent = purpose;
            document.getElementById('modalLegalBasis').textContent = legal;
            document.getElementById('modalLocation').textContent = loc;
            document.getElementById('modalCamera').textContent = camera;
            document.getElementById('modalFootageWindow').textContent = footageWindow;
            document.getElementById('modalDescription').textContent = desc;
            document.getElementById('modalDelivery').textContent = delivery;
            document.getElementById('modalSupportingDoc').textContent = supporting;
            document.getElementById('modalReviewNotes').textContent = reviewNotes;
            document.getElementById('modalDateSubmitted').textContent = dateSub;

            let badgeClass = 'bg-warning text-dark';
            if (status === 'Approved' || status === 'Fulfilled') badgeClass = 'bg-success text-white';
            else if (status === 'Under Review') badgeClass = 'bg-info text-white';
            else if (status === 'Rejected') badgeClass = 'bg-danger text-white';

            document.getElementById('modalStatusBadge').innerHTML = `<span class="badge ${badgeClass} px-2 py-1">${status}</span>`;

            var modalEl = document.getElementById('footageDetailsModal');
            var detailsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
            detailsModal.show();
        });
    });

    // MANAGE MODAL
    document.querySelectorAll('.btn-manage').forEach(btn => {
        btn.addEventListener('click', function(){
            document.getElementById('manage_request_id').value = this.getAttribute('data-id');
            document.getElementById('manage_status').value = this.getAttribute('data-status-val') || 'Pending';
            document.getElementById('manage_camera').value = this.getAttribute('data-camera-val') || '';
            document.getElementById('manage_notes').value = this.getAttribute('data-review-notes-val') || '';
            document.getElementById('manage_rejection').value = this.getAttribute('data-rejection-val') || '';

            var modalEl = document.getElementById('manageModal');
            var manageModal = bootstrap.Modal.getOrCreateInstance(modalEl);
            manageModal.show();
        });
    });
});
</script>

<script src="../assets/js/address-selector.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    initQCAddressSelector({
        districtSelectId: 'req_district',
        barangaySelectId: 'req_barangay',
        streetInputId: 'req_street',
        houseNumberInputId: 'req_house',
        targetCombinedInputId: 'incident_location'
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>
