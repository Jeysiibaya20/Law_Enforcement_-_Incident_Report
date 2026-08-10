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
    body.blotter-page {
        background-color: #ffffff !important;
        color: #000000 !important;
    }
    .suspect-management-page .content-container,
    .suspect-management-page .card,
    .suspect-management-page .card-header,
    .suspect-management-page .card-body,
    .suspect-management-page .table,
    .suspect-management-page .table th,
    .suspect-management-page .table td,
    .suspect-management-page .nav-tabs .nav-link,
    .suspect-management-page .alert,
    .suspect-management-page .form-control,
    .suspect-management-page .form-select,
    .suspect-management-page .btn,
    .suspect-management-page .badge {
        color: #000000 !important;
    }
    .suspect-management-page .content-container,
    .suspect-management-page .card,
    .suspect-management-page .card-header,
    .suspect-management-page .card-body,
    .suspect-management-page .table,
    .suspect-management-page .alert {
        background-color: #ffffff !important;
        border-color: #dcdcdc !important;
    }
    .suspect-management-page .nav-tabs .nav-link.active {
        background-color: #f7f7f7 !important;
        border-color: #dcdcdc !important;
    }
    .suspect-management-page .btn-primary,
    .suspect-management-page .btn-secondary,
    .suspect-management-page .btn-outline-secondary,
    .suspect-management-page .btn-info {
        color: #000000 !important;
    }
    .suspect-management-page .btn-primary {
        background-color: #e9ecef !important;
        border-color: #ced4da !important;
    }
    .suspect-management-page .btn-secondary,
    .suspect-management-page .btn-outline-secondary,
    .suspect-management-page .btn-info {
        background-color: #f8f9fa !important;
        border-color: #ced4da !important;
    }
    .suspect-management-page .text-muted {
        color: #444 !important;
    }
</style>
CSS;

include '../includes/header.php';
include '../includes/navbar.php';
?>

<div class="main-content suspect-management-page">
    <div class="content-container">
        <div class="d-flex justify-content-between align-items-start mb-4">
            <div>
                <h1 class="h2 mb-2"><i class="bi bi-person-fill"></i> Suspect Management</h1>
                <p class="text-muted">Case: <strong><?= htmlspecialchars($case['case_number'] ?? 'Not selected') ?></strong></p>
            </div>
            <?php if ($case_id && $case): ?>
                <a href="../admin/suspects&witnesses.php?case_id=<?= htmlspecialchars($case_id) ?>" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Back to Case
                </a>
            <?php endif; ?>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?= $message_type ?> alert-dismissible">
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><?= $edit_id ? 'Update' : 'Add New' ?> Suspect</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <div class="row g-3 mb-4 p-3 bg-light rounded">
                                <div class="col-12">
                                    <label class="form-label">Select Case *</label>
                                    <select name="case_id" class="form-select" required>
                                        <option value="">Select case</option>
                                        <?php foreach ($cases as $caseOption): ?>
                                            <option value="<?= htmlspecialchars($caseOption['id']) ?>" <?= $case_id == $caseOption['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($caseOption['case_number'] . ' — ' . $caseOption['incident_type'] . ' (' . $caseOption['complainant_name'] . ')') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="row g-3 mb-4 p-3 bg-light rounded">
                                <div class="col-12">
                                    <label class="form-label"><strong>Suspect Photo</strong></label>
                                    <div class="d-flex gap-3 align-items-start">
                                        <div>
                                            <?php if ($suspect && isset($suspect['photo_path']) && $suspect['photo_path']): ?>
                                                <img id="photoPreview" src="<?= htmlspecialchars('../' . $suspect['photo_path']) ?>?t=<?= time() ?>" alt="Suspect Photo" class="img-thumbnail" style="max-width: 150px; max-height: 200px;">
                                            <?php else: ?>
                                                <div id="photoPreview" class="border rounded p-3 text-center bg-white" style="width: 150px; height: 200px; display: flex; align-items: center; justify-content: center;">
                                                    <div>
                                                        <i class="bi bi-image" style="font-size: 2rem; color: #ccc;"></i>
                                                        <p class="text-muted mt-2 mb-0" style="font-size: 0.8rem;">No photo</p>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="flex-grow-1">
                                            <input type="file" name="photo" id="photo" class="form-control" accept="image/*">
                                            <small class="text-muted d-block mt-2">
                                                <i class="bi bi-info-circle"></i>
                                                Accepted: JPG, PNG, GIF, WebP<br>
                                                Max size: 5MB recommended
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">First Name *</label>
                                    <input type="text" name="first_name" class="form-control" value="<?= htmlspecialchars($suspect['first_name'] ?? '') ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Middle Name</label>
                                    <input type="text" name="middle_name" class="form-control" value="<?= htmlspecialchars($suspect['middle_name'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Last Name *</label>
                                    <input type="text" name="last_name" class="form-control" value="<?= htmlspecialchars($suspect['last_name'] ?? '') ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Gender</label>
                                    <select name="gender" class="form-select">
                                        <option value="Male" <?= ($suspect['gender'] ?? 'Male') === 'Male' ? 'selected' : '' ?>>Male</option>
                                        <option value="Female" <?= ($suspect['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
                                        <option value="Other" <?= ($suspect['gender'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Age</label>
                                    <input type="number" name="age" class="form-control" value="<?= htmlspecialchars($suspect['age'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Date of Birth</label>
                                    <input type="date" name="date_of_birth" class="form-control" value="<?= htmlspecialchars($suspect['date_of_birth'] ?? '') ?>">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Address</label>
                                    <input type="text" name="address" class="form-control" value="<?= htmlspecialchars($suspect['address'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Province</label>
                                    <select name="province" id="provinceSelect" class="form-select">
                                        <option value="Metro Manila">Metro Manila</option>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">City / Municipality</label>
                                    <select name="city" id="citySelect" class="form-select">
                                        <option value="Quezon City">Quezon City</option>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Barangay</label>
                                    <select name="barangay" id="brgySelect" class="form-select">
                                        <option value="">-- Select Barangay --</option>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">ZIP Code</label>
                                    <input type="text" name="zip_code" id="zipCode" class="form-control" value="<?= htmlspecialchars($suspect['zip_code'] ?? '') ?>" readonly>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Contact Number</label>
                                    <input type="tel" name="contact_number" class="form-control" value="<?= htmlspecialchars($suspect['contact_number'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($suspect['email'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">ID Type</label>
                                    <select name="id_type" class="form-select">
                                        <option value="">-- Select ID Type --</option>
                                        <option value="Philippine Passport" <?= ($suspect['id_type'] ?? '') === 'Philippine Passport' ? 'selected' : '' ?>>Philippine Passport</option>
                                        <option value="Driver's License" <?= ($suspect['id_type'] ?? '') === "Driver's License" ? 'selected' : '' ?>>Driver's License</option>
                                        <option value="National ID (ID)" <?= ($suspect['id_type'] ?? '') === 'National ID (ID)' ? 'selected' : '' ?>>National ID (ID)</option>
                                        <option value="Postal ID" <?= ($suspect['id_type'] ?? '') === 'Postal ID' ? 'selected' : '' ?>>Postal ID</option>
                                        <option value="UMID" <?= ($suspect['id_type'] ?? '') === 'UMID' ? 'selected' : '' ?>>UMID (Union Card)</option>
                                        <option value="PNP Clearance" <?= ($suspect['id_type'] ?? '') === 'PNP Clearance' ? 'selected' : '' ?>>PNP Clearance</option>
                                        <option value="TIN ID" <?= ($suspect['id_type'] ?? '') === 'TIN ID' ? 'selected' : '' ?>>TIN ID</option>
                                        <option value="Senior Citizen ID" <?= ($suspect['id_type'] ?? '') === 'Senior Citizen ID' ? 'selected' : '' ?>>Senior Citizen ID</option>
                                        <option value="PWD ID" <?= ($suspect['id_type'] ?? '') === 'PWD ID' ? 'selected' : '' ?>>PWD ID</option>
                                        <option value="Barangay ID" <?= ($suspect['id_type'] ?? '') === 'Barangay ID' ? 'selected' : '' ?>>Barangay ID</option>
                                        <option value="School ID" <?= ($suspect['id_type'] ?? '') === 'School ID' ? 'selected' : '' ?>>School ID</option>
                                        <option value="Company ID" <?= ($suspect['id_type'] ?? '') === 'Company ID' ? 'selected' : '' ?>>Company ID</option>
                                        <option value="Other" <?= ($suspect['id_type'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">ID Number</label>
                                    <input type="text" name="id_number" class="form-control" value="<?= htmlspecialchars($suspect['id_number'] ?? '') ?>" placeholder="Auto-generated or enter manually">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">ID Attachment (Document Photo)</label>
                                    <div class="d-flex gap-3 align-items-start">
                                        <div>
                                            <?php if ($suspect && isset($suspect['id_attachment']) && $suspect['id_attachment']): ?>
                                                <img id="idPreview" src="<?= htmlspecialchars('../' . $suspect['id_attachment']) ?>?t=<?= time() ?>" alt="ID Document" class="img-thumbnail" style="max-width: 150px; max-height: 150px;">
                                            <?php else: ?>
                                                <div id="idPreview" class="border rounded p-3 text-center bg-light" style="width: 150px; height: 150px; display: flex; align-items: center; justify-content: center;">
                                                    <div>
                                                        <i class="bi bi-card-text" style="font-size: 2rem; color: #ccc;"></i>
                                                        <p class="text-muted mt-2 mb-0" style="font-size: 0.8rem;">No ID</p>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="flex-grow-1">
                                            <input type="file" name="id_attachment" id="idAttachment" class="form-control" accept="image/*">
                                            <small class="text-muted d-block mt-2">
                                                <i class="bi bi-info-circle"></i>
                                                Upload a photo of the ID/document<br>
                                                Accepted: JPG, PNG, GIF, WebP<br>
                                                Max size: 5MB recommended
                                            </small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Physical Description</label>
                                    <textarea name="physical_description" class="form-control" rows="2"><?= htmlspecialchars($suspect['physical_description'] ?? '') ?></textarea>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Known Aliases</label>
                                    <input type="text" name="known_aliases" class="form-control" value="<?= htmlspecialchars($suspect['known_aliases'] ?? '') ?>">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Criminal History</label>
                                    <textarea name="criminal_history" class="form-control" rows="2"><?= htmlspecialchars($suspect['criminal_history'] ?? '') ?></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Status</label>
                                    <select name="status" class="form-select">
                                        <option value="Active" <?= ($suspect['status'] ?? 'Active') === 'Active' ? 'selected' : '' ?>>Active</option>
                                        <option value="Arrested" <?= ($suspect['status'] ?? '') === 'Arrested' ? 'selected' : '' ?>>Arrested</option>
                                        <option value="Released" <?= ($suspect['status'] ?? '') === 'Released' ? 'selected' : '' ?>>Released</option>
                                        <option value="Deceased" <?= ($suspect['status'] ?? '') === 'Deceased' ? 'selected' : '' ?>>Deceased</option>
                                        <option value="Unknown" <?= ($suspect['status'] ?? '') === 'Unknown' ? 'selected' : '' ?>>Unknown</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Remarks</label>
                                    <textarea name="remarks" class="form-control" rows="2"><?= htmlspecialchars($suspect['remarks'] ?? '') ?></textarea>
                                </div>
                            </div>
                            <div class="d-flex gap-2 mt-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> <?= $edit_id ? 'Update' : 'Add' ?> Suspect
                                </button>
                                <?php if ($edit_id): ?>
                                    <a href="suspects_management.php?case_id=<?= $case_id ?>" class="btn btn-secondary">Cancel</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Suspects List -->
            <div class="col-lg-6">
                <div class="card">
                    <!-- Tabs -->
                    <ul class="nav nav-tabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="active-tab" data-bs-toggle="tab" data-bs-target="#activeTab" type="button" role="tab">
                                <i class="bi bi-person-check"></i> Active Suspects (<?= count($suspects) ?>)
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="deleted-tab" data-bs-toggle="tab" data-bs-target="#deletedTab" type="button" role="tab">
                                <i class="bi bi-trash"></i> Stash (<?= count($deleted_suspects) ?>)
                            </button>
                        </li>
                    </ul>

                    <!-- Tab Content -->
                    <div class="tab-content">
                        <!-- Active Suspects Tab -->
                        <div class="tab-pane fade show active" id="activeTab" role="tabpanel">
                            <div class="card-body" style="max-height: 600px; overflow-y: auto;">
                                <?php if (!empty($suspects)): ?>
                                    <?php foreach ($suspects as $s): ?>
                                        <div class="border-bottom pb-3 mb-3">
                                            <div class="d-flex justify-content-between align-items-start gap-3">
                                                <?php if (isset($s['photo_path']) && $s['photo_path']): ?>
                                            <img src="<?= htmlspecialchars('../' . $s['photo_path']) ?>" alt="Suspect Photo" class="img-thumbnail" style="width: 80px; height: 100px; object-fit: cover; flex-shrink: 0;" onerror="this.style.display='none'">
                                        <?php else: ?>
                                            <div class="border rounded p-2 text-center bg-light" style="width: 80px; height: 100px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                                <i class="bi bi-person-fill" style="font-size: 2rem; color: #ccc;"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1">
                                                <strong><?= htmlspecialchars($s['first_name'] . ' ' . $s['last_name']) ?></strong>
                                                <?php if ($s['age']): ?>
                                                    <span class="text-muted">(<?= $s['age'] ?> years)</span>
                                                <?php endif; ?>
                                            </h6>
                                            <small class="text-muted d-block">
                                                <?php if ($s['address']): ?>
                                                    <?= htmlspecialchars($s['address']) ?><br>
                                                <?php endif; ?>
                                                <?php if ($s['contact_number']): ?>
                                                    <i class="bi bi-telephone"></i> <?= htmlspecialchars($s['contact_number']) ?><br>
                                                <?php endif; ?>
                                                Status: 
                                                <span class="badge bg-<?= ($s['status'] === 'Arrested') ? 'danger' : (($s['status'] === 'Released') ? 'success' : 'warning') ?>">
                                                    <?= htmlspecialchars($s['status']) ?>
                                                </span>
                                            </small>
                                        </div>
                                        <div class="flex-shrink-0 d-flex gap-2">
                                            <a href="?case_id=<?= $case_id ?>&edit=<?= $s['id'] ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                            <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal" onclick="prepareDeleteModal(<?= $s['id'] ?>, '<?= htmlspecialchars($s['first_name'] . ' ' . $s['last_name']) ?>')">Delete</button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="text-muted text-center py-5">No suspects recorded yet</p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Deleted Suspects Tab (Stash) -->
                        <div class="tab-pane fade" id="deletedTab" role="tabpanel">
                            <div class="card-body" style="max-height: 600px; overflow-y: auto;">
                                <?php if (!empty($deleted_suspects)): ?>
                                    <?php foreach ($deleted_suspects as $ds): ?>
                                        <div class="border-bottom pb-3 mb-3">
                                            <div class="d-flex justify-content-between align-items-start gap-3">
                                                <?php if (isset($ds['photo_path']) && $ds['photo_path']): ?>
                                                    <img src="<?= htmlspecialchars('../' . $ds['photo_path']) ?>" alt="Suspect Photo" class="img-thumbnail" style="width: 80px; height: 100px; object-fit: cover; flex-shrink: 0;" onerror="this.style.display='none'">
                                                <?php else: ?>
                                                    <div class="border rounded p-2 text-center bg-light" style="width: 80px; height: 100px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                                        <i class="bi bi-person-fill" style="font-size: 2rem; color: #ccc;"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="flex-grow-1">
                                                    <h6 class="mb-1">
                                                        <strong><?= htmlspecialchars($ds['first_name'] . ' ' . $ds['last_name']) ?></strong>
                                                        <?php if ($ds['age']): ?>
                                                            <span class="text-muted">(<?= $ds['age'] ?> years)</span>
                                                        <?php endif; ?>
                                                    </h6>
                                                    <small class="text-muted d-block">
                                                        <?php if ($ds['address']): ?>
                                                            <?= htmlspecialchars($ds['address']) ?><br>
                                                        <?php endif; ?>
                                                        <?php if ($ds['contact_number']): ?>
                                                            <i class="bi bi-telephone"></i> <?= htmlspecialchars($ds['contact_number']) ?><br>
                                                        <?php endif; ?>
                                                        Deleted: <span class="badge bg-secondary"><?= date('M d, Y', strtotime($ds['deleted_at'])) ?></span>
                                                    </small>
                                                </div>
                                                <div class="flex-shrink-0 d-flex gap-2">
                                                    <button type="button" class="btn btn-sm btn-success text-white" data-bs-toggle="modal" data-bs-target="#restoreModal" onclick="prepareRestoreModal(<?= $ds['id'] ?>, '<?= htmlspecialchars($ds['first_name'] . ' ' . $ds['last_name'], ENT_QUOTES) ?>')"><i class="bi bi-arrow-counterclockwise"></i> Restore</button>
                                                    <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#permDeleteModal" onclick="preparePermDeleteModal(<?= $ds['id'] ?>, '<?= htmlspecialchars($ds['first_name'] . ' ' . $ds['last_name'], ENT_QUOTES) ?>')"><i class="bi bi-trash"></i> Delete Permanently</button>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="text-muted text-center py-5">No deleted suspects</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-white text-dark border border-2">
            <div class="modal-header bg-danger text-white border-bottom border-2">
                <h5 class="modal-title">Move to Stash</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-dark border-bottom border-2">
                <p>Move <strong id="deleteSuspectName"></strong> to stash?</p>
                <p class="text-muted small mb-0">The suspect record will be moved to the stash and hidden from the active list. You can restore it later from the Stash tab.</p>
            </div>
            <div class="modal-footer border-top border-2">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="case_id" value="<?= htmlspecialchars($case_id) ?>">
                    <input type="hidden" name="delete_suspect_id" id="deleteSuspectId" value="">
                    <button type="submit" class="btn btn-danger">Move to Stash</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Restore Confirmation Modal -->
<div class="modal fade" id="restoreModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-white text-dark border border-2">
            <div class="modal-header bg-success text-white border-bottom border-2">
                <h5 class="modal-title"><i class="bi bi-arrow-counterclockwise me-1"></i>Restore Suspect</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-dark border-bottom border-2">
                <p>Restore <strong id="restoreSuspectName"></strong> to active suspects?</p>
                <p class="text-muted small mb-0">The suspect record will be moved back to the active list.</p>
            </div>
            <div class="modal-footer border-top border-2">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="case_id" value="<?= htmlspecialchars($case_id ?? '') ?>">
                    <input type="hidden" name="restore_suspect_id" id="restoreSuspectId" value="">
                    <button type="submit" class="btn btn-success">Restore Suspect</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Permanent Delete Confirmation Modal -->
<div class="modal fade" id="permDeleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-white text-dark border border-2">
            <div class="modal-header bg-danger text-white border-bottom border-2">
                <h5 class="modal-title"><i class="bi bi-exclamation-triangle me-1"></i>Permanent Delete Suspect</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-dark">
                <p>Are you sure you want to <strong>permanently delete</strong> <strong id="permDeleteSuspectName"></strong>?</p>
                <div class="alert alert-danger mb-0 small">
                    <i class="bi bi-exclamation-octagon me-1"></i><strong>Warning:</strong> This action cannot be undone. All suspect records, attachments, and photos will be erased.
                </div>
            </div>
            <div class="modal-footer border-top">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="case_id" value="<?= htmlspecialchars($case_id ?? '') ?>">
                    <input type="hidden" name="permanent_delete_suspect_id" id="permDeleteSuspectId" value="">
                    <button type="submit" class="btn btn-danger">Delete Permanently</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
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
const photoPreview = document.getElementById('photoPreview');
if (photoInput) {
    photoInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            if (file.size > 5 * 1024 * 1024) { alert('File size exceeds 5MB limit'); this.value = ''; return; }
            const reader = new FileReader();
            reader.onload = function(event) {
                if (photoPreview.classList.contains('border')) {
                    photoPreview.innerHTML = ''; photoPreview.classList.remove('border','rounded','p-3','text-center','bg-white');
                }
                const img = document.createElement('img'); img.src = event.target.result; img.className = 'img-thumbnail'; img.style.maxWidth = '150px'; img.style.maxHeight = '200px'; photoPreview.innerHTML = ''; photoPreview.appendChild(img);
            };
            reader.readAsDataURL(file);
        }
    });
}

// ID attachment preview functionality
const idInput = document.getElementById('idAttachment');
const idPreview = document.getElementById('idPreview');
if (idInput) {
    idInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            if (file.size > 5 * 1024 * 1024) { alert('File size exceeds 5MB limit'); this.value = ''; return; }
            const reader = new FileReader();
            reader.onload = function(event) {
                if (idPreview.classList.contains('border')) {
                    idPreview.innerHTML = ''; idPreview.classList.remove('border','rounded','p-3','text-center','bg-light');
                }
                const img = document.createElement('img'); img.src = event.target.result; img.className = 'img-thumbnail'; img.style.maxWidth = '150px'; img.style.maxHeight = '150px'; idPreview.innerHTML = ''; idPreview.appendChild(img);
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

    zipCode.value = '1110';
})();
</script>

<?php include '../includes/footer.php'; ?>
