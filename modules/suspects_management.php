<?php
/**
 * Suspect Management Page
 * Allows admin to add, view, and update suspect information for cases
 */

require_once dirname(__DIR__) . '/config/db_connect.php';
require_once dirname(__DIR__) . '/includes/case_management.php';
require_once dirname(__DIR__) . '/includes/suspect_witness_management.php';

// Check authorization
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    http_response_code(403);
    echo "Access Denied";
    exit;
}

$case_id = $_GET['case_id'] ?? null;
$edit_id = $_GET['edit'] ?? null;

if (!$case_id) {
    http_response_code(400);
    echo "Case ID required";
    exit;
}

// Get case details
try {
    $stmt = $pdo->prepare("SELECT * FROM case_assignments WHERE id = ?");
    $stmt->execute([$case_id]);
    $case = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$case) {
        http_response_code(404);
        echo "Case not found";
        exit;
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo "Error: " . $e->getMessage();
    exit;
}

$message = '';
$message_type = '';
$suspect = null;
$old_photo_path = null;

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
    
    // Handle photo upload
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = dirname(__DIR__) . '/uploads/suspects/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (in_array($file_ext, $allowed_ext)) {
            $file_name = 'suspect_' . time() . '_' . uniqid() . '.' . $file_ext;
            $file_path = $upload_dir . $file_name;
            
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $file_path)) {
                $new_photo_path = 'uploads/suspects/' . $file_name;
                
                // Delete old photo if updating and new photo uploaded
                if ($old_photo_path && $old_photo_path !== $new_photo_path) {
                    $old_file = dirname(__DIR__) . '/' . $old_photo_path;
                    if (file_exists($old_file)) {
                        @unlink($old_file);
                    }
                }
                
                $photo_path = $new_photo_path;
            }
        } else {
            $message = "Invalid file type. Allowed: JPG, PNG, GIF, WebP";
            $message_type = 'warning';
        }
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
        'id_number' => $_POST['id_number'] ?? '',
        'physical_description' => $_POST['physical_description'] ?? '',
        'known_aliases' => $_POST['known_aliases'] ?? '',
        'criminal_history' => $_POST['criminal_history'] ?? '',
        'remarks' => $_POST['remarks'] ?? '',
        'status' => $_POST['status'] ?? 'Active',
        'photo_path' => $photo_path,
        'created_by' => $_SESSION['user_id']
    ];
    
    if ($edit_id) {
        // Update suspect
        $result = updateSuspect($edit_id, $suspect_data, $_SESSION['user_id']);
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
        // Create new suspect
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

// Get all suspects for this case (reload suspect if editing to show updated data)
if ($edit_id && !$suspect) {
    $suspect = getSuspectById($edit_id);
}
$suspects = getSuspectsByCase($case_id);
?>

$page_title = "Suspect Management - " . htmlspecialchars($case['case_number']);
include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-2">
            <?php include '../includes/navbar.php'; ?>
        </div>

        <!-- Main Content -->
        <div class="col-md-10 main-content">
        <div class="d-flex justify-content-between align-items-start mb-4">
            <div>
                <h1 class="h2 mb-2">
                    <i class="bi bi-person-fill"></i> Suspect Management
                </h1>
                <p class="text-muted">Case: <strong><?= htmlspecialchars($case['case_number']) ?></strong></p>
            </div>
            <a href="../modules/case_details.php?case_id=<?= $case_id ?>" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Back to Case
            </a>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?= $message_type ?> alert-dismissible">
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <!-- Form -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><?= $edit_id ? 'Update' : 'Add New' ?> Suspect</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <!-- Photo Upload Section -->
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
                                    <label class="form-label">Barangay</label>
                                    <input type="text" name="barangay" class="form-control" value="<?= htmlspecialchars($suspect['barangay'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">City</label>
                                    <input type="text" name="city" class="form-control" value="<?= htmlspecialchars($suspect['city'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Province</label>
                                    <input type="text" name="province" class="form-control" value="<?= htmlspecialchars($suspect['province'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">ZIP Code</label>
                                    <input type="text" name="zip_code" class="form-control" value="<?= htmlspecialchars($suspect['zip_code'] ?? '') ?>">
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
                                    <input type="text" name="id_type" class="form-control" placeholder="e.g., Driver's License, Passport" value="<?= htmlspecialchars($suspect['id_type'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">ID Number</label>
                                    <input type="text" name="id_number" class="form-control" value="<?= htmlspecialchars($suspect['id_number'] ?? '') ?>">
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
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0">Recorded Suspects (<?= count($suspects) ?>)</h5>
                    </div>
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
                                        <a href="?case_id=<?= $case_id ?>&edit=<?= $s['id'] ?>" class="btn btn-sm btn-outline-primary flex-shrink-0">Edit</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-muted text-center py-5">No suspects recorded yet</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Photo preview functionality
        const photoInput = document.getElementById('photo');
        const photoPreview = document.getElementById('photoPreview');
        
        if (photoInput) {
            photoInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    // Validate file size (5MB max)
                    if (file.size > 5 * 1024 * 1024) {
                        alert('File size exceeds 5MB limit');
                        this.value = '';
                        return;
                    }
                    
                    const reader = new FileReader();
                    reader.onload = function(event) {
                        if (photoPreview.classList.contains('border')) {
                            photoPreview.innerHTML = '';
                            photoPreview.classList.remove('border', 'rounded', 'p-3', 'text-center', 'bg-white');
                        }
                        const img = document.createElement('img');
                        img.src = event.target.result;
                        img.className = 'img-thumbnail';
                        img.style.maxWidth = '150px';
                        img.style.maxHeight = '200px';
                        photoPreview.innerHTML = '';
                        photoPreview.appendChild(img);
                    };
                    reader.readAsDataURL(file);
                }
            });
        }
    </script>
