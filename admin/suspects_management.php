<?php
/**
 * Suspect Management Page
 * Allows admin to add, view, and update suspect information for cases
 */

require_once 'admin_auth.php';

require_once dirname(__DIR__) . '/config/db_connect.php';
if (!isset($pdo) || !($pdo instanceof PDO)) {
    $pdo = getDBConnection();
}
require_once dirname(__DIR__) . '/includes/case_management.php';
require_once dirname(__DIR__) . '/includes/suspect_witness_management.php';

// Admin authentication is handled by require_once 'admin_auth.php'; above.
$userId = $_SESSION['admin_user_id'] ?? $_SESSION['user_id'] ?? 1;

$case_id = $_GET['case_id'] ?? $_POST['case_id'] ?? null;
$edit_id = $_GET['edit'] ?? null;

$cases = [];
try {
    // Try case_assignments table first
    $stmt = $pdo->query("SELECT id, case_number, incident_type, complainant_name FROM case_assignments ORDER BY created_at DESC");
    $cases = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

if (empty($cases)) {
    try {
        // Fallback to blotters table
        $stmt = $pdo->query("SELECT id, blotter_no AS case_number, incident_type, complainant_name FROM blotters ORDER BY created_at DESC");
        $cases = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {}
}

if (empty($cases)) {
    try {
        // Fallback to incidents table
        $stmt = $pdo->query("SELECT id, case_no AS case_number, incident_type, reporter_name AS complainant_name FROM incidents ORDER BY created_at DESC");
        $cases = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {}
}

$case = null;
if ($case_id) {
    try {
        $stmt = $pdo->prepare("SELECT id, case_number, incident_type, complainant_name FROM case_assignments WHERE id = ?");
        $stmt->execute([$case_id]);
        $case = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {}

    if (!$case) {
        try {
            $stmt = $pdo->prepare("SELECT id, blotter_no AS case_number, incident_type, complainant_name FROM blotters WHERE id = ?");
            $stmt->execute([$case_id]);
            $case = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {}
    }

    if (!$case) {
        try {
            $stmt = $pdo->prepare("SELECT id, case_no AS case_number, incident_type, reporter_name AS complainant_name FROM incidents WHERE id = ?");
            $stmt->execute([$case_id]);
            $case = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {}
    }
}

$message = '';
$message_type = '';
$suspect = null;
$old_photo_path = null;

// Handle soft delete request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_suspect_id'])) {
    $delete_id = intval($_POST['delete_suspect_id']);
    try {
        // Soft delete: set deleted_at timestamp instead of removing data
        $stmt = $pdo->prepare("UPDATE suspects SET deleted_at = NOW() WHERE id = ?");
        $stmt->execute([$delete_id]);
        
        $message = "Suspect moved to stash successfully. You can retrieve it from the Deleted tab.";
        $message_type = 'success';
    } catch (Exception $e) {
        $message = "Error deleting suspect: " . $e->getMessage();
        $message_type = 'danger';
    }
}

// Handle restore request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore_suspect_id'])) {
    $restore_id = intval($_POST['restore_suspect_id']);
    try {
        // Restore: clear deleted_at timestamp
        $stmt = $pdo->prepare("UPDATE suspects SET deleted_at = NULL WHERE id = ?");
        $stmt->execute([$restore_id]);
        
        $message = "Suspect restored successfully.";
        $message_type = 'success';
    } catch (Exception $e) {
        $message = "Error restoring suspect: " . $e->getMessage();
        $message_type = 'danger';
    }
}

// Handle permanent delete request ("mabura")
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['permanent_delete_suspect_id'])) {
    $perm_id = intval($_POST['permanent_delete_suspect_id']);
    try {
        $fStmt = $pdo->prepare("SELECT photo_path, id_attachment FROM suspects WHERE id = ?");
        $fStmt->execute([$perm_id]);
        $sFiles = $fStmt->fetch(PDO::FETCH_ASSOC);
        if ($sFiles) {
            if (!empty($sFiles['photo_path']) && file_exists(dirname(__DIR__) . '/' . $sFiles['photo_path'])) {
                @unlink(dirname(__DIR__) . '/' . $sFiles['photo_path']);
            }
            if (!empty($sFiles['id_attachment']) && file_exists(dirname(__DIR__) . '/' . $sFiles['id_attachment'])) {
                @unlink(dirname(__DIR__) . '/' . $sFiles['id_attachment']);
            }
        }
        try {
            $pdo->prepare("DELETE FROM suspect_updates WHERE suspect_id = ?")->execute([$perm_id]);
            $pdo->prepare("DELETE FROM suspect_photos WHERE suspect_id = ?")->execute([$perm_id]);
        } catch (Exception $ex) {}

        $stmt = $pdo->prepare("DELETE FROM suspects WHERE id = ?");
        $stmt->execute([$perm_id]);

        $message = "Suspect record permanently deleted.";
        $message_type = 'success';
    } catch (Exception $e) {
        $message = "Error permanently deleting suspect: " . $e->getMessage();
        $message_type = 'danger';
    }
}

// Get suspect if editing (do this BEFORE form submission to capture old photo)
if ($edit_id) {
    $suspect = getSuspectById($edit_id);
    if ($suspect) {
        $old_photo_path = $suspect['photo_path'] ?? null;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $photo_path = $old_photo_path; // Start with old photo path
    $id_attachment_path = $suspect['id_attachment'] ?? null; // Start with old ID attachment

    // Handle photo upload
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = dirname(__DIR__) . '/uploads/suspects/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

        $file_ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array($file_ext, $allowed_ext)) {
            $file_name = 'suspect_' . time() . '_' . uniqid() . '.' . $file_ext;
            $file_path = $upload_dir . $file_name;
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $file_path)) {
                $new_photo_path = 'uploads/suspects/' . $file_name;
                if ($old_photo_path && $old_photo_path !== $new_photo_path) {
                    $old_file = dirname(__DIR__) . '/' . $old_photo_path;
                    if (file_exists($old_file)) @unlink($old_file);
                }
                $photo_path = $new_photo_path;
            }
        } else {
            $message = "Invalid file type. Allowed: JPG, PNG, GIF, WebP";
            $message_type = 'warning';
        }
    }

    // Handle ID attachment upload
    if (isset($_FILES['id_attachment']) && $_FILES['id_attachment']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = dirname(__DIR__) . '/uploads/id_documents/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

        $file_ext = strtolower(pathinfo($_FILES['id_attachment']['name'], PATHINFO_EXTENSION));
        $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array($file_ext, $allowed_ext)) {
            $file_name = 'id_' . time() . '_' . uniqid() . '.' . $file_ext;
            $file_path = $upload_dir . $file_name;
            if (move_uploaded_file($_FILES['id_attachment']['tmp_name'], $file_path)) {
                $new_id_attach = 'uploads/id_documents/' . $file_name;
                // Delete old ID attachment if exists
                if ($id_attachment_path && $id_attachment_path !== $new_id_attach) {
                    $old_file = dirname(__DIR__) . '/' . $id_attachment_path;
                    if (file_exists($old_file)) @unlink($old_file);
                }
                $id_attachment_path = $new_id_attach;
            }
        } else {
            $message = "Invalid ID attachment file type. Allowed: JPG, PNG, GIF, WebP";
            $message_type = 'warning';
        }
    }

    // Auto-generate ID Number if not provided
    $id_number = $_POST['id_number'] ?? '';
    if (empty($id_number)) {
        // Auto-generate: ID_CASE_TIMESTAMP
        $id_number = 'ID_' . str_replace('-', '', $case['case_number']) . '_' . time();
    }

    $suspect_data = [
        'case_id' => $case_id,
        'case_number' => $case['case_number'],
        'first_name' => $_POST['first_name'] ?? '',
        'middle_name' => $_POST['middle_name'] ?? '',
        'last_name' => $_POST['last_name'] ?? '',
        'age' => !empty($_POST['age']) ? intval($_POST['age']) : null,
        'date_of_birth' => $_POST['date_of_birth'] ?? null,
        'gender' => $_POST['gender'] ?? 'Male',
        'address' => $_POST['address'] ?? '',
        'barangay' => $_POST['barangay'] ?? '',
        'city' => $_POST['city'] ?? '',
        'province' => $_POST['province'] ?? '',
        'zip_code' => $_POST['zip_code'] ?? '',
        'contact_number' => $_POST['contact_number'] ?? '',
        'email' => $_POST['email'] ?? '',
        'id_type' => $_POST['id_type'] ?? '',
        'id_number' => $id_number,
        'id_attachment' => $id_attachment_path,
        'physical_description' => $_POST['physical_description'] ?? '',
        'known_aliases' => $_POST['known_aliases'] ?? '',
        'criminal_history' => $_POST['criminal_history'] ?? '',
        'remarks' => $_POST['remarks'] ?? '',
        'status' => $_POST['status'] ?? 'Active',
        'photo_path' => $photo_path,
        'created_by' => $userId
    ];

    if ($edit_id) {
        $result = updateSuspect($edit_id, $suspect_data, $userId);
        if ($result['success']) {
            $message = "Suspect information updated successfully";
            $message_type = 'success';
            $edit_id = null;
            $suspect = null;
        } else {
            $message = "Error updating suspect: " . $result['error'];
            $message_type = 'danger';
        }
    } else {
        $result = createSuspect($suspect_data);
        if ($result['success']) {
            $message = "Suspect added successfully";
            $message_type = 'success';
        } else {
            $message = "Error adding suspect: " . $result['error'];
            $message_type = 'danger';
        }
    }
}

// Reload suspect if edited
if ($edit_id && !$suspect) {
    $suspect = getSuspectById($edit_id);
}
$suspects = getSuspectsByCase($case_id);
$deleted_suspects = getDeletedSuspectsByCase($case_id);

$base_url = '../';
$page_title = "Suspect Management" . ($case ? " - " . htmlspecialchars($case['case_number']) : '');
$body_class = 'blotter-page';
$additional_head = <<<CSS
<style>
    .suspect-card-item {
        transition: all 0.2s ease-in-out;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        background: #ffffff;
    }
    .suspect-card-item:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(0,0,0,0.08);
        border-color: #2e856e;
    }
    .nav-pills-emerald .nav-link {
        color: #475569;
        font-weight: 600;
        border-radius: 8px;
        padding: 8px 16px;
    }
    .nav-pills-emerald .nav-link.active {
        background: linear-gradient(135deg, #1b5a56, #2e856e) !important;
        color: #ffffff !important;
    }
    .form-section-title {
        font-size: 0.85rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #1b5a56;
        border-bottom: 2px solid rgba(46,133,110,0.15);
        padding-bottom: 6px;
        margin-bottom: 14px;
    }
</style>
CSS;

include '../includes/header.php';
include '../includes/navbar.php';
?>

<div class="main-content">
    <div class="content-container">
        <!-- Header Strip -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <h1 class="h2 fw-bold text-dark mb-1"><i class="fas fa-user-ninja text-success me-2"></i>Suspect & POI Management</h1>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge text-white px-3 py-1 fw-semibold" style="background: #2e856e;">
                        <i class="bi bi-folder2-open me-1"></i> Case: <?= htmlspecialchars($case['case_number'] ?? 'All Cases') ?>
                    </span>
                    <?php if ($case && !empty($case['incident_type'])): ?>
                        <span class="badge bg-light text-dark border"><?= htmlspecialchars($case['incident_type']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="d-flex gap-2">
                <?php if ($case_id && $case): ?>
                    <a href="../admin/suspects&witnesses.php?case_id=<?= htmlspecialchars($case_id) ?>" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i> Back to Case
                    </a>
                <?php endif; ?>
                <a href="dashboard.php" class="btn btn-outline-secondary">
                    <i class="bi bi-speedometer2 me-1"></i> Dashboard
                </a>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?= $message_type ?> alert-dismissible fade show shadow-sm border-0 mb-4" role="alert" style="border-radius: 10px;">
                <i class="bi <?= $message_type === 'success' ? 'bi-check-circle-fill text-success' : 'bi-exclamation-triangle-fill text-danger' ?> me-2"></i>
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <!-- ================= LEFT COLUMN: ADD / EDIT SUSPECT FORM ================= -->
            <div class="col-lg-6">
                <div class="card shadow-sm border-0" style="border-radius: 12px; overflow: hidden; border: 1px solid rgba(46,133,110,0.2) !important;">
                    <div class="card-header py-3 text-white d-flex justify-content-between align-items-center" style="background: linear-gradient(135deg, #1b5a56, #2e856e) !important;">
                        <h5 class="mb-0 fw-bold text-white">
                            <i class="fas <?= $edit_id ? 'fa-user-edit' : 'fa-user-plus' ?> me-2"></i><?= $edit_id ? 'Update Suspect Dossier' : 'Register New Suspect / POI' ?>
                        </h5>
                        <?php if ($edit_id): ?>
                            <span class="badge bg-warning text-dark fw-bold">Editing ID #<?= htmlspecialchars($edit_id) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="card-body p-4">
                        <form method="POST" enctype="multipart/form-data">
                            <!-- Section 1: Case Assignment -->
                            <div class="form-section-title"><i class="bi bi-briefcase me-1"></i> Case Assignment</div>
                            <div class="mb-4">
                                <label class="form-label fw-semibold small text-muted">Linked Case Record <span class="text-danger">*</span></label>
                                <select name="case_id" class="form-select border shadow-none" required>
                                    <option value="">-- Choose Case / Blotter --</option>
                                    <?php foreach ($cases as $caseOption): ?>
                                        <option value="<?= htmlspecialchars($caseOption['id']) ?>" <?= $case_id == $caseOption['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($caseOption['case_number'] . ' — ' . $caseOption['incident_type'] . ' (' . ($caseOption['complainant_name'] ?? 'N/A') . ')') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Section 2: Photo Uploader Widget -->
                            <div class="form-section-title"><i class="bi bi-camera me-1"></i> Suspect Facial Photo / Mugshot</div>
                            <div class="p-3 mb-4 rounded border bg-light">
                                <div class="d-flex gap-3 align-items-center flex-wrap">
                                    <div class="position-relative">
                                        <?php if ($suspect && isset($suspect['photo_path']) && $suspect['photo_path']): ?>
                                            <img id="photoPreviewImg" src="<?= htmlspecialchars('../' . $suspect['photo_path']) ?>?t=<?= time() ?>" alt="Suspect Photo" class="img-thumbnail shadow-sm" style="width: 110px; height: 130px; object-fit: cover; border-radius: 8px;">
                                        <?php else: ?>
                                            <div id="photoPreviewContainer" class="border rounded bg-white shadow-sm d-flex flex-column align-items-center justify-content-center text-muted" style="width: 110px; height: 130px;">
                                                <i class="bi bi-person-bounding-box fs-1 text-secondary"></i>
                                                <span style="font-size: 0.7rem;">No Photo</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex-grow-1">
                                        <label class="form-label small fw-bold text-dark mb-1">Upload Photo File</label>
                                        <input type="file" name="photo" id="photo" class="form-control form-control-sm" accept="image/*">
                                        <small class="text-muted d-block mt-1" style="font-size: 0.75rem;">
                                            <i class="bi bi-info-circle me-1"></i>JPG, PNG, GIF, WebP up to 5MB.
                                        </small>
                                    </div>
                                </div>
                            </div>

                            <!-- Section 3: Personal Information -->
                            <div class="form-section-title"><i class="bi bi-person-lines-fill me-1"></i> Personal Information</div>
                            <div class="row g-3 mb-4">
                                <div class="col-md-4">
                                    <label class="form-label small fw-semibold">First Name <span class="text-danger">*</span></label>
                                    <input type="text" name="first_name" class="form-control form-control-sm" value="<?= htmlspecialchars($suspect['first_name'] ?? '') ?>" placeholder="First name" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-semibold">Middle Name</label>
                                    <input type="text" name="middle_name" class="form-control form-control-sm" value="<?= htmlspecialchars($suspect['middle_name'] ?? '') ?>" placeholder="Middle name">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-semibold">Last Name <span class="text-danger">*</span></label>
                                    <input type="text" name="last_name" class="form-control form-control-sm" value="<?= htmlspecialchars($suspect['last_name'] ?? '') ?>" placeholder="Last name" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-semibold">Gender</label>
                                    <select name="gender" class="form-select form-select-sm">
                                        <option value="Male" <?= ($suspect['gender'] ?? 'Male') === 'Male' ? 'selected' : '' ?>>Male</option>
                                        <option value="Female" <?= ($suspect['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
                                        <option value="Other" <?= ($suspect['gender'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-semibold">Age</label>
                                    <input type="number" name="age" class="form-control form-control-sm" value="<?= htmlspecialchars($suspect['age'] ?? '') ?>" placeholder="Age">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-semibold">Date of Birth</label>
                                    <input type="date" name="date_of_birth" class="form-control form-control-sm" value="<?= htmlspecialchars($suspect['date_of_birth'] ?? '') ?>">
                                </div>
                            </div>

                            <!-- Section 4: Address & Jurisdiction -->
                            <div class="form-section-title"><i class="bi bi-geo-alt me-1"></i> Address & Jurisdiction</div>
                            <div class="row g-3 mb-4">
                                <div class="col-12">
                                    <label class="form-label small fw-semibold">House No. / Street / Subd</label>
                                    <input type="text" name="address" class="form-control form-control-sm" value="<?= htmlspecialchars($suspect['address'] ?? '') ?>" placeholder="e.g. #123 Maharlika St.">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold">Province</label>
                                    <select name="province" id="provinceSelect" class="form-select form-select-sm">
                                        <option value="Metro Manila" selected>Metro Manila</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold">City / Municipality</label>
                                    <select name="city" id="citySelect" class="form-select form-select-sm">
                                        <option value="Quezon City" selected>Quezon City</option>
                                    </select>
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label small fw-semibold">Barangay</label>
                                    <select name="barangay" id="brgySelect" class="form-select form-select-sm">
                                        <option value="">-- Select Barangay --</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-semibold">ZIP Code</label>
                                    <input type="text" name="zip_code" id="zipCode" class="form-control form-control-sm bg-light" value="<?= htmlspecialchars($suspect['zip_code'] ?? '1110') ?>" readonly>
                                </div>
                            </div>

                            <!-- Section 5: Contact & Identification -->
                            <div class="form-section-title"><i class="bi bi-card-heading me-1"></i> Contact & Government ID</div>
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold">Contact Number</label>
                                    <input type="tel" name="contact_number" class="form-control form-control-sm" value="<?= htmlspecialchars($suspect['contact_number'] ?? '') ?>" placeholder="09XX-XXX-XXXX">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold">Email Address</label>
                                    <input type="email" name="email" class="form-control form-control-sm" value="<?= htmlspecialchars($suspect['email'] ?? '') ?>" placeholder="email@example.com">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold">ID Type</label>
                                    <select name="id_type" class="form-select form-select-sm">
                                        <option value="">-- Select ID Type --</option>
                                        <option value="Philippine Passport" <?= ($suspect['id_type'] ?? '') === 'Philippine Passport' ? 'selected' : '' ?>>Philippine Passport</option>
                                        <option value="Driver's License" <?= ($suspect['id_type'] ?? '') === "Driver's License" ? 'selected' : '' ?>>Driver's License</option>
                                        <option value="National ID" <?= ($suspect['id_type'] ?? '') === 'National ID' ? 'selected' : '' ?>>National ID (PhilSys)</option>
                                        <option value="Postal ID" <?= ($suspect['id_type'] ?? '') === 'Postal ID' ? 'selected' : '' ?>>Postal ID</option>
                                        <option value="UMID" <?= ($suspect['id_type'] ?? '') === 'UMID' ? 'selected' : '' ?>>UMID</option>
                                        <option value="PNP Clearance" <?= ($suspect['id_type'] ?? '') === 'PNP Clearance' ? 'selected' : '' ?>>PNP Clearance</option>
                                        <option value="Barangay ID" <?= ($suspect['id_type'] ?? '') === 'Barangay ID' ? 'selected' : '' ?>>Barangay ID</option>
                                        <option value="Other" <?= ($suspect['id_type'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold">ID Number</label>
                                    <input type="text" name="id_number" class="form-control form-control-sm" value="<?= htmlspecialchars($suspect['id_number'] ?? '') ?>" placeholder="ID Number">
                                </div>
                                <div class="col-12">
                                    <label class="form-label small fw-semibold">ID Document Attachment</label>
                                    <input type="file" name="id_attachment" id="idAttachment" class="form-control form-control-sm" accept="image/*,.pdf">
                                    <?php if ($suspect && !empty($suspect['id_attachment'])): ?>
                                        <small class="text-success mt-1 d-block"><i class="bi bi-paperclip"></i> Current file: <?= htmlspecialchars(basename($suspect['id_attachment'])) ?></small>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Section 6: Legal Status & Remarks -->
                            <div class="form-section-title"><i class="bi bi-shield-shaded me-1"></i> Legal Status & Dossier Notes</div>
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold">Operational Status <span class="text-danger">*</span></label>
                                    <select name="status" class="form-select form-select-sm" required>
                                        <option value="Active" <?= ($suspect['status'] ?? 'Active') === 'Active' ? 'selected' : '' ?>>Active / At Large</option>
                                        <option value="Under Surveillance" <?= ($suspect['status'] ?? '') === 'Under Surveillance' ? 'selected' : '' ?>>Under Surveillance</option>
                                        <option value="Arrested" <?= ($suspect['status'] ?? '') === 'Arrested' ? 'selected' : '' ?>>Arrested / In Custody</option>
                                        <option value="Released" <?= ($suspect['status'] ?? '') === 'Released' ? 'selected' : '' ?>>Released on Bail</option>
                                        <option value="Deceased" <?= ($suspect['status'] ?? '') === 'Deceased' ? 'selected' : '' ?>>Deceased</option>
                                        <option value="Unknown" <?= ($suspect['status'] ?? '') === 'Unknown' ? 'selected' : '' ?>>Unknown</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label small fw-semibold">Remarks & Modus Operandi Notes</label>
                                    <textarea name="remarks" class="form-control form-control-sm" rows="3" placeholder="Enter physical descriptors, scars, tattoos, known aliases, or case notes..."><?= htmlspecialchars($suspect['remarks'] ?? '') ?></textarea>
                                </div>
                            </div>

                            <div class="d-flex gap-2 pt-2 border-top">
                                <button type="submit" class="btn btn-success fw-bold px-4 shadow-sm" style="background-color: #2e856e; border-color: #2e856e;">
                                    <i class="bi bi-check2-circle me-1"></i> <?= $edit_id ? 'Save Updates' : 'Register Suspect' ?>
                                </button>
                                <?php if ($edit_id): ?>
                                    <a href="suspects_management.php?case_id=<?= htmlspecialchars($case_id) ?>" class="btn btn-outline-secondary">
                                        Cancel Edit
                                    </a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- ================= RIGHT COLUMN: SUSPECTS & STASH CATALOG ================= -->
            <div class="col-lg-6">
                <div class="card shadow-sm border-0 mb-4" style="border-radius: 12px; overflow: hidden; border: 1px solid rgba(46,133,110,0.2) !important;">
                    <div class="card-header bg-light p-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <!-- Tab Pills Navigation -->
                        <ul class="nav nav-pills nav-pills-emerald mb-0" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="active-tab" data-bs-toggle="tab" data-bs-target="#activeTab" type="button" role="tab">
                                    <i class="bi bi-person-check me-1"></i> Active Suspects <span class="badge bg-white text-dark ms-1"><?= count($suspects) ?></span>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="deleted-tab" data-bs-toggle="tab" data-bs-target="#deletedTab" type="button" role="tab">
                                    <i class="bi bi-archive me-1"></i> Stash / Trash <span class="badge bg-secondary ms-1"><?= count($deleted_suspects) ?></span>
                                </button>
                            </li>
                        </ul>

                        <!-- Live Search Bar -->
                        <div class="input-group input-group-sm" style="width: 200px;">
                            <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                            <input type="text" id="suspectSearchInput" class="form-control border-start-0 shadow-none" placeholder="Search suspects..." onkeyup="filterSuspectList()">
                        </div>
                    </div>

                    <div class="card-body p-3">
                        <div class="tab-content">
                            <!-- TAB 1: ACTIVE SUSPECTS -->
                            <div class="tab-pane fade show active" id="activeTab" role="tabpanel">
                                <div style="max-height: 700px; overflow-y: auto;" class="pe-1" id="activeSuspectsContainer">
                                    <?php if (!empty($suspects)): ?>
                                        <div class="d-flex flex-column gap-3">
                                            <?php foreach ($suspects as $s): 
                                                $fullName = trim($s['first_name'] . ' ' . ($s['middle_name'] ? $s['middle_name'] . ' ' : '') . $s['last_name']);
                                                $statusBadge = match($s['status']) {
                                                    'Arrested' => 'bg-danger text-white',
                                                    'Under Surveillance' => 'bg-warning text-dark',
                                                    'Released' => 'bg-success text-white',
                                                    default => 'bg-info text-white'
                                                };
                                                $suspectJson = htmlspecialchars(json_encode($s), ENT_QUOTES, 'UTF-8');
                                            ?>
                                                <div class="suspect-card-item p-3 suspect-item-row"
                                                     data-name="<?= htmlspecialchars(strtolower($fullName)) ?>"
                                                     data-address="<?= htmlspecialchars(strtolower($s['address'] . ' ' . ($s['barangay'] ?? ''))) ?>"
                                                     data-status="<?= htmlspecialchars(strtolower($s['status'])) ?>">
                                                    <div class="d-flex gap-3 align-items-center">
                                                        <!-- Avatar Thumbnail -->
                                                        <div class="flex-shrink-0">
                                                            <?php if (!empty($s['photo_path']) && file_exists(dirname(__DIR__) . '/' . $s['photo_path'])): ?>
                                                                <img src="<?= htmlspecialchars('../' . $s['photo_path']) ?>" alt="Suspect" class="rounded shadow-sm" style="width: 65px; height: 75px; object-fit: cover; border: 1px solid #cbd5e1;">
                                                            <?php else: ?>
                                                                <div class="rounded bg-light text-secondary d-flex flex-column align-items-center justify-content-center shadow-sm" style="width: 65px; height: 75px; border: 1px solid #e2e8f0;">
                                                                    <i class="bi bi-person-fill fs-3 text-muted"></i>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>

                                                        <!-- Main Dossier Info -->
                                                        <div class="flex-grow-1">
                                                            <div class="d-flex justify-content-between align-items-start flex-wrap gap-1">
                                                                <h6 class="mb-1 fw-bold text-dark">
                                                                    <?= htmlspecialchars($fullName) ?>
                                                                    <?php if (!empty($s['age'])): ?>
                                                                        <span class="text-muted fw-normal small">(<?= (int)$s['age'] ?> yrs)</span>
                                                                    <?php endif; ?>
                                                                </h6>
                                                                <span class="badge <?= $statusBadge ?> px-2 py-1 small"><?= htmlspecialchars($s['status']) ?></span>
                                                            </div>

                                                            <div class="text-muted small mb-2">
                                                                <?php if (!empty($s['address'])): ?>
                                                                    <div><i class="bi bi-geo-alt text-danger me-1"></i><?= htmlspecialchars($s['address']) ?><?= !empty($s['barangay']) ? ', Brgy. ' . htmlspecialchars($s['barangay']) : '' ?></div>
                                                                <?php endif; ?>
                                                                <?php if (!empty($s['contact_number'])): ?>
                                                                    <div><i class="bi bi-telephone text-primary me-1"></i><?= htmlspecialchars($s['contact_number']) ?></div>
                                                                <?php endif; ?>
                                                            </div>

                                                            <!-- Quick Action Buttons -->
                                                            <div class="d-flex gap-2">
                                                                <button type="button" class="btn btn-sm btn-outline-info" onclick="openSuspectDossier(<?= $suspectJson ?>)">
                                                                    <i class="bi bi-eye me-1"></i> Dossier
                                                                </button>
                                                                <a href="?case_id=<?= htmlspecialchars($case_id) ?>&edit=<?= $s['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                                    <i class="bi bi-pencil me-1"></i> Edit
                                                                </a>
                                                                <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal" onclick="prepareDeleteModal(<?= $s['id'] ?>, '<?= htmlspecialchars($fullName, ENT_QUOTES) ?>')">
                                                                    <i class="bi bi-trash me-1"></i> Stash
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-center py-5 text-muted">
                                            <i class="bi bi-person-x fs-1 d-block mb-2 text-secondary"></i>
                                            <p class="mb-0 fw-semibold">No active suspects recorded for this case.</p>
                                            <small>Fill out the form on the left to add a suspect.</small>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- TAB 2: STASH / TRASH -->
                            <div class="tab-pane fade" id="deletedTab" role="tabpanel">
                                <div style="max-height: 700px; overflow-y: auto;" class="pe-1">
                                    <?php if (!empty($deleted_suspects)): ?>
                                        <div class="d-flex flex-column gap-3">
                                            <?php foreach ($deleted_suspects as $ds): 
                                                $dsFullName = trim($ds['first_name'] . ' ' . ($ds['middle_name'] ? $ds['middle_name'] . ' ' : '') . $ds['last_name']);
                                            ?>
                                                <div class="suspect-card-item p-3 border-warning" style="background: #fffdf5;">
                                                    <div class="d-flex gap-3 align-items-center">
                                                        <div class="flex-shrink-0">
                                                            <?php if (!empty($ds['photo_path']) && file_exists(dirname(__DIR__) . '/' . $ds['photo_path'])): ?>
                                                                <img src="<?= htmlspecialchars('../' . $ds['photo_path']) ?>" alt="Suspect" class="rounded opacity-75" style="width: 55px; height: 65px; object-fit: cover;">
                                                            <?php else: ?>
                                                                <div class="rounded bg-light text-secondary d-flex align-items-center justify-content-center" style="width: 55px; height: 65px;">
                                                                    <i class="bi bi-person-x fs-4"></i>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>

                                                        <div class="flex-grow-1">
                                                            <h6 class="mb-1 fw-bold text-dark"><?= htmlspecialchars($dsFullName) ?></h6>
                                                            <small class="text-muted d-block mb-2">
                                                                <i class="bi bi-clock-history me-1"></i> Stashed on <?= date('M d, Y', strtotime($ds['deleted_at'])) ?>
                                                            </small>
                                                            <div class="d-flex gap-2">
                                                                <button type="button" class="btn btn-sm btn-success text-white" data-bs-toggle="modal" data-bs-target="#restoreModal" onclick="prepareRestoreModal(<?= $ds['id'] ?>, '<?= htmlspecialchars($dsFullName, ENT_QUOTES) ?>')">
                                                                    <i class="bi bi-arrow-counterclockwise me-1"></i> Restore
                                                                </button>
                                                                <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#permDeleteModal" onclick="preparePermDeleteModal(<?= $ds['id'] ?>, '<?= htmlspecialchars($dsFullName, ENT_QUOTES) ?>')">
                                                                    <i class="bi bi-trash3 me-1"></i> Delete Permanently
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-center py-5 text-muted">
                                            <i class="bi bi-archive fs-1 d-block mb-2 text-secondary"></i>
                                            <p class="mb-0">Stash is empty. No deleted suspects.</p>
                                        </div>
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

<!-- ================= INTERACTIVE SUSPECT DOSSIER SUB-MODAL ================= -->
<div class="modal fade" id="suspectDossierModal" tabindex="-1" aria-labelledby="suspectDossierModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px; overflow: hidden;">
            <div class="modal-header text-white" style="background: linear-gradient(135deg, #1b5a56, #2e856e);">
                <h5 class="modal-title fw-bold" id="suspectDossierModalLabel"><i class="fas fa-id-card me-2"></i>Suspect Dossier & Profile</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                <div class="row g-4">
                    <!-- Photo & Quick Stats -->
                    <div class="col-md-4 text-center">
                        <div class="card p-3 shadow-sm border-0 bg-white" style="border-radius: 10px;">
                            <img id="dossierPhoto" src="" alt="Suspect" class="img-fluid rounded mb-3 shadow-sm" style="max-height: 220px; object-fit: cover; width: 100%;">
                            <h5 id="dossierName" class="fw-bold mb-1 text-dark"></h5>
                            <span id="dossierStatusBadge" class="badge px-3 py-2 mb-2"></span>
                            <div class="small text-muted" id="dossierGenderAge"></div>
                        </div>
                    </div>

                    <!-- Detailed Info Fields -->
                    <div class="col-md-8">
                        <div class="card p-3 shadow-sm border-0 bg-white" style="border-radius: 10px;">
                            <h6 class="fw-bold text-dark border-bottom pb-2 mb-3"><i class="bi bi-info-circle text-success me-2"></i>Demographics & Identification</h6>
                            <dl class="row mb-0 small">
                                <dt class="col-sm-4 text-muted text-uppercase">Date of Birth</dt><dd class="col-sm-8 fw-semibold" id="dossierDob">N/A</dd>
                                <dt class="col-sm-4 text-muted text-uppercase">Full Address</dt><dd class="col-sm-8" id="dossierAddress">N/A</dd>
                                <dt class="col-sm-4 text-muted text-uppercase">Contact Phone</dt><dd class="col-sm-8" id="dossierContact">N/A</dd>
                                <dt class="col-sm-4 text-muted text-uppercase">Email</dt><dd class="col-sm-8" id="dossierEmail">N/A</dd>
                                <dt class="col-sm-4 text-muted text-uppercase">ID Presented</dt><dd class="col-sm-8" id="dossierIdInfo">N/A</dd>
                            </dl>
                            <hr class="my-3">
                            <h6 class="fw-bold text-dark border-bottom pb-2 mb-2"><i class="bi bi-card-text text-success me-2"></i>Modus & Case Remarks</h6>
                            <p class="text-secondary small mb-0 p-2 rounded bg-light border" id="dossierRemarks" style="min-height: 60px; white-space: pre-wrap;"></p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-white border-top">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close Dossier</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px; overflow: hidden;">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-archive me-2"></i>Move to Stash</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 text-dark">
                <p class="mb-2">Move <strong id="deleteSuspectName"></strong> to stash?</p>
                <p class="text-muted small mb-0">The record will be archived in the Stash / Trash tab and hidden from active case operations.</p>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="case_id" value="<?= htmlspecialchars($case_id) ?>">
                    <input type="hidden" name="delete_suspect_id" id="deleteSuspectId" value="">
                    <button type="submit" class="btn btn-danger"><i class="bi bi-archive me-1"></i> Move to Stash</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Restore Confirmation Modal -->
<div class="modal fade" id="restoreModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px; overflow: hidden;">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-arrow-counterclockwise me-2"></i>Restore Suspect</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 text-dark">
                <p class="mb-2">Restore <strong id="restoreSuspectName"></strong> back to active suspects?</p>
                <p class="text-muted small mb-0">The suspect record will be immediately available in the active directory.</p>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="case_id" value="<?= htmlspecialchars($case_id ?? '') ?>">
                    <input type="hidden" name="restore_suspect_id" id="restoreSuspectId" value="">
                    <button type="submit" class="btn btn-success text-white"><i class="bi bi-arrow-counterclockwise me-1"></i> Restore Record</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Permanent Delete Confirmation Modal -->
<div class="modal fade" id="permDeleteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px; overflow: hidden;">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-exclamation-triangle-fill me-2"></i>Permanent Deletion</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 text-dark">
                <p class="mb-2">Permanently erase <strong id="permDeleteSuspectName"></strong> from the database?</p>
                <div class="alert alert-danger mb-0 small">
                    <i class="bi bi-exclamation-octagon me-1"></i><strong>Irreversible Action:</strong> All attached mugshots, ID proofs, and historical links will be purged permanently.
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="case_id" value="<?= htmlspecialchars($case_id ?? '') ?>">
                    <input type="hidden" name="permanent_delete_suspect_id" id="permDeleteSuspectId" value="">
                    <button type="submit" class="btn btn-danger"><i class="bi bi-trash3 me-1"></i> Delete Permanently</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Open Dossier Sub-Modal
function openSuspectDossier(s) {
    const fullName = (s.first_name || '') + ' ' + (s.middle_name ? s.middle_name + ' ' : '') + (s.last_name || '');
    document.getElementById('dossierName').textContent = fullName || 'Suspect Details';

    const photoEl = document.getElementById('dossierPhoto');
    if (s.photo_path) {
        photoEl.src = '../' + s.photo_path;
        photoEl.style.display = 'block';
    } else {
        photoEl.src = 'https://ui-avatars.com/api/?name=' + encodeURIComponent(fullName) + '&background=2e856e&color=fff&size=200';
    }

    const statusBadge = document.getElementById('dossierStatusBadge');
    statusBadge.textContent = s.status || 'Active';
    statusBadge.className = 'badge px-3 py-2 mb-2 ' + (
        s.status === 'Arrested' ? 'bg-danger' :
        s.status === 'Under Surveillance' ? 'bg-warning text-dark' :
        s.status === 'Released' ? 'bg-success' : 'bg-info'
    );

    document.getElementById('dossierGenderAge').textContent = (s.gender || 'Unknown') + (s.age ? ' • ' + s.age + ' years old' : '');
    document.getElementById('dossierDob').textContent = s.date_of_birth || 'Not recorded';
    
    let addr = s.address || '';
    if (s.barangay) addr += (addr ? ', ' : '') + 'Brgy. ' + s.barangay;
    if (s.city) addr += (addr ? ', ' : '') + s.city;
    if (s.province) addr += (addr ? ', ' : '') + s.province;
    document.getElementById('dossierAddress').textContent = addr || 'N/A';

    document.getElementById('dossierContact').textContent = s.contact_number || 'N/A';
    document.getElementById('dossierEmail').textContent = s.email || 'N/A';
    document.getElementById('dossierIdInfo').textContent = (s.id_type || 'N/A') + (s.id_number ? ' (' + s.id_number + ')' : '');
    document.getElementById('dossierRemarks').textContent = s.remarks || 'No specific remarks or modus operandi notes on record.';

    const modal = new bootstrap.Modal(document.getElementById('suspectDossierModal'));
    modal.show();
}

// Live Filter Suspect List
function filterSuspectList() {
    const q = (document.getElementById('suspectSearchInput')?.value || '').toLowerCase().trim();
    const rows = document.querySelectorAll('#activeSuspectsContainer .suspect-item-row');
    rows.forEach(r => {
        const text = (
            (r.getAttribute('data-name') || '') + ' ' +
            (r.getAttribute('data-address') || '') + ' ' +
            (r.getAttribute('data-status') || '')
        ).toLowerCase();
        r.style.display = (!q || text.includes(q)) ? '' : 'none';
    });
}

// Prepare delete modal
function prepareDeleteModal(suspectId, suspectName) {
    document.getElementById('deleteSuspectId').value = suspectId;
    document.getElementById('deleteSuspectName').textContent = suspectName;
}

// Prepare restore modal
function prepareRestoreModal(suspectId, suspectName) {
    document.getElementById('restoreSuspectId').value = suspectId;
    document.getElementById('restoreSuspectName').textContent = suspectName;
}

// Prepare permanent delete modal
function preparePermDeleteModal(suspectId, suspectName) {
    document.getElementById('permDeleteSuspectId').value = suspectId;
    document.getElementById('permDeleteSuspectName').textContent = suspectName;
}

// Photo preview functionality
const photoInput = document.getElementById('photo');
if (photoInput) {
    photoInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            if (file.size > 5 * 1024 * 1024) { alert('File size exceeds 5MB limit'); this.value = ''; return; }
            const reader = new FileReader();
            reader.onload = function(event) {
                let previewImg = document.getElementById('photoPreviewImg');
                const container = document.getElementById('photoPreviewContainer');
                if (!previewImg && container) {
                    container.innerHTML = '';
                    previewImg = document.createElement('img');
                    previewImg.id = 'photoPreviewImg';
                    previewImg.className = 'img-thumbnail shadow-sm';
                    previewImg.style.width = '110px';
                    previewImg.style.height = '130px';
                    previewImg.style.objectFit = 'cover';
                    container.parentNode.replaceChild(previewImg, container);
                }
                if (previewImg) previewImg.src = event.target.result;
            };
            reader.readAsDataURL(file);
        }
    });
}
</script>

<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

<script>
(function(){
    const provinceSelect = document.getElementById('provinceSelect');
    const citySelect = document.getElementById('citySelect');
    const brgySelect = document.getElementById('brgySelect');
    const zipCode = document.getElementById('zipCode');

    if (!provinceSelect || !citySelect || !brgySelect) return;

    const existingProvince = <?= json_encode($suspect['province'] ?? '') ?>;
    const existingCity = <?= json_encode($suspect['city'] ?? '') ?>;
    const existingBrgy = <?= json_encode($suspect['barangay'] ?? '') ?>;

    provinceSelect.value = existingProvince || 'Metro Manila';
    citySelect.value = existingCity || 'Quezon City';

    const barangays = [
        'Alicia','Amihan','Bagbag','Bagong Pag-asa','Bagong Silangan','Bahay Toro','Balingasa','Batasan Hills','Blue Ridge A','Blue Ridge B','Botocan','Burnham Park','Camp Aguinaldo','Capri','Central','Claro','Commonwealth','Culiat','Damar','Damon','Don Manuel','Don Sergio','East Kamias','Fairview','Greater Lagro','Gulod','Holy Spirit','Immaculate Concepcion','Kaligayahan','Kalusugan','Kamias','Kamagong','Kapiligan','Katipunan','Laging Handa','Libis','Lourdes','Malaya','Malayong Sangley','Mariana','Mauway','Nagkaisang Nayon','Nayon Kaunlaran','New Era','North Fairview','Novaliches Proper','Ocampo','Old Capitol Site','Paltok','Parang','Pasong Putik Proper','Payatas','Phil-Am','Pinagkaisahan','Pinyahan','Project 6','Quirino 2-A','Quirino 2-B','Quirino 3-A','R. I. P. (Resty I. P.)','Sagrado','Saint Ignatius','Saint Peter','Salawag','San Agustin','San Antonio','San Isidro','San Isidro Labrador','San Jose','San Martin De Porres','San Roque','Santo Cristo','Santo Domingo','Santo Niño','Sauyo','Sienna','South Triangle','Tagumpay','Talayan','Tandang Sora','Tatalon','Teachers Village East','Teachers Village West','U.P. Campus','Unang Sigaw','Villa Maria Clara','West Kamias','White Plains','Wigan'
    ].sort();

    barangays.forEach(brgy => {
        const opt = document.createElement('option');
        opt.value = brgy;
        opt.textContent = brgy;
        brgySelect.appendChild(opt);
    });

    try {
        $(provinceSelect).select2({ theme: 'bootstrap-5', width: '100%', placeholder: 'Province' });
        $(citySelect).select2({ theme: 'bootstrap-5', width: '100%', placeholder: 'City' });
        $(brgySelect).select2({ theme: 'bootstrap-5', width: '100%', placeholder: 'Select barangay...' });
    } catch (e) {
        console.warn('Select2 not loaded, using native select');
    }

    if (existingBrgy) {
        brgySelect.value = existingBrgy;
        try { $(brgySelect).trigger('change.select2'); } catch (e) {}
    }

    if (zipCode) zipCode.value = '1110';
})();
</script>

<?php include '../includes/footer.php'; ?>
