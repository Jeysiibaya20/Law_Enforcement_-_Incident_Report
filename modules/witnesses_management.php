<?php
/**
 * Witness Management Page
 * Allows admin to add, view, and update witness information for cases
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
$witness = null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $witness_data = [
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
        'relationship_to_case' => $_POST['relationship_to_case'] ?? '',
        'witness_type' => $_POST['witness_type'] ?? 'Direct',
        'statement' => $_POST['statement'] ?? '',
        'reliability' => $_POST['reliability'] ?? 'Medium',
        'available_for_court' => isset($_POST['available_for_court']) ? 1 : 0,
        'protection_needed' => isset($_POST['protection_needed']) ? 1 : 0,
        'remarks' => $_POST['remarks'] ?? '',
        'created_by' => $_SESSION['user_id']
    ];
    
    if ($edit_id) {
        // Update witness
        $result = updateWitness($edit_id, $witness_data, $_SESSION['user_id']);
        if ($result['success']) {
            $message = "Witness information updated successfully";
            $message_type = 'success';
            $edit_id = null;
        } else {
            $message = "Error updating witness: " . $result['error'];
            $message_type = 'danger';
        }
    } else {
        // Create new witness
        $result = createWitness($witness_data);
        if ($result['success']) {
            $message = "Witness added successfully";
            $message_type = 'success';
        } else {
            $message = "Error adding witness: " . $result['error'];
            $message_type = 'danger';
        }
    }
}

// Get witness if editing
if ($edit_id) {
    $witness = getWitnessById($edit_id);
}

// Get all witnesses for this case
$witnesses = getWitnessesByCase($case_id);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Witness Management - <?= htmlspecialchars($case['case_number']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-start mb-4">
            <div>
                <h1 class="h2 mb-2">
                    <i class="bi bi-people-fill"></i> Witness Management
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
                        <h5 class="mb-0"><?= $edit_id ? 'Update' : 'Add New' ?> Witness</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">First Name *</label>
                                    <input type="text" name="first_name" class="form-control" value="<?= htmlspecialchars($witness['first_name'] ?? '') ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Middle Name</label>
                                    <input type="text" name="middle_name" class="form-control" value="<?= htmlspecialchars($witness['middle_name'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Last Name *</label>
                                    <input type="text" name="last_name" class="form-control" value="<?= htmlspecialchars($witness['last_name'] ?? '') ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Gender</label>
                                    <select name="gender" class="form-select">
                                        <option value="Male" <?= ($witness['gender'] ?? 'Male') === 'Male' ? 'selected' : '' ?>>Male</option>
                                        <option value="Female" <?= ($witness['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
                                        <option value="Other" <?= ($witness['gender'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Age</label>
                                    <input type="number" name="age" class="form-control" value="<?= htmlspecialchars($witness['age'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Date of Birth</label>
                                    <input type="date" name="date_of_birth" class="form-control" value="<?= htmlspecialchars($witness['date_of_birth'] ?? '') ?>">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Address</label>
                                    <input type="text" name="address" class="form-control" value="<?= htmlspecialchars($witness['address'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Barangay</label>
                                    <input type="text" name="barangay" class="form-control" value="<?= htmlspecialchars($witness['barangay'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">City</label>
                                    <input type="text" name="city" class="form-control" value="<?= htmlspecialchars($witness['city'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Province</label>
                                    <input type="text" name="province" class="form-control" value="<?= htmlspecialchars($witness['province'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">ZIP Code</label>
                                    <input type="text" name="zip_code" class="form-control" value="<?= htmlspecialchars($witness['zip_code'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Contact Number</label>
                                    <input type="tel" name="contact_number" class="form-control" value="<?= htmlspecialchars($witness['contact_number'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($witness['email'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">ID Type</label>
                                    <input type="text" name="id_type" class="form-control" placeholder="e.g., Barangay ID" value="<?= htmlspecialchars($witness['id_type'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">ID Number</label>
                                    <input type="text" name="id_number" class="form-control" value="<?= htmlspecialchars($witness['id_number'] ?? '') ?>">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Relationship to Case</label>
                                    <input type="text" name="relationship_to_case" class="form-control" placeholder="e.g., Neighbor, Friend, Victim" value="<?= htmlspecialchars($witness['relationship_to_case'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Witness Type</label>
                                    <select name="witness_type" class="form-select">
                                        <option value="Direct" <?= ($witness['witness_type'] ?? 'Direct') === 'Direct' ? 'selected' : '' ?>>Direct</option>
                                        <option value="Indirect" <?= ($witness['witness_type'] ?? '') === 'Indirect' ? 'selected' : '' ?>>Indirect</option>
                                        <option value="Hearsay" <?= ($witness['witness_type'] ?? '') === 'Hearsay' ? 'selected' : '' ?>>Hearsay</option>
                                        <option value="Character" <?= ($witness['witness_type'] ?? '') === 'Character' ? 'selected' : '' ?>>Character</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Reliability</label>
                                    <select name="reliability" class="form-select">
                                        <option value="High" <?= ($witness['reliability'] ?? 'Medium') === 'High' ? 'selected' : '' ?>>High</option>
                                        <option value="Medium" <?= ($witness['reliability'] ?? 'Medium') === 'Medium' ? 'selected' : '' ?>>Medium</option>
                                        <option value="Low" <?= ($witness['reliability'] ?? '') === 'Low' ? 'selected' : '' ?>>Low</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Witness Statement</label>
                                    <textarea name="statement" class="form-control" rows="3" placeholder="Record what the witness said..."><?= htmlspecialchars($witness['statement'] ?? '') ?></textarea>
                                </div>
                                <div class="col-12">
                                    <div class="form-check form-check-inline">
                                        <input type="checkbox" name="available_for_court" class="form-check-input" id="available_court" <?= ($witness['available_for_court'] ?? false) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="available_court">
                                            Available for Court Appearance
                                        </label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input type="checkbox" name="protection_needed" class="form-check-input" id="protection_needed" <?= ($witness['protection_needed'] ?? false) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="protection_needed">
                                            Witness Protection Needed
                                        </label>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Remarks</label>
                                    <textarea name="remarks" class="form-control" rows="2"><?= htmlspecialchars($witness['remarks'] ?? '') ?></textarea>
                                </div>
                            </div>
                            <div class="d-flex gap-2 mt-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> <?= $edit_id ? 'Update' : 'Add' ?> Witness
                                </button>
                                <?php if ($edit_id): ?>
                                    <a href="witnesses_management.php?case_id=<?= $case_id ?>" class="btn btn-secondary">Cancel</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Witnesses List -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0">Recorded Witnesses (<?= count($witnesses) ?>)</h5>
                    </div>
                    <div class="card-body" style="max-height: 600px; overflow-y: auto;">
                        <?php if (!empty($witnesses)): ?>
                            <?php foreach ($witnesses as $w): ?>
                                <div class="border-bottom pb-3 mb-3">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1">
                                                <strong><?= htmlspecialchars($w['first_name'] . ' ' . $w['last_name']) ?></strong>
                                                <?php if ($w['age']): ?>
                                                    <span class="text-muted">(<?= $w['age'] ?> years)</span>
                                                <?php endif; ?>
                                            </h6>
                                            <small class="text-muted">
                                                Type: <span class="badge bg-info"><?= htmlspecialchars($w['witness_type']) ?></span>
                                                Reliability: <span class="badge bg-<?= ($w['reliability'] === 'High') ? 'success' : (($w['reliability'] === 'Low') ? 'warning' : 'secondary') ?>">
                                                    <?= htmlspecialchars($w['reliability']) ?>
                                                </span>
                                                <?php if ($w['protection_needed']): ?>
                                                    <br><span class="badge bg-danger">Protection Needed</span>
                                                <?php endif; ?>
                                                <?php if ($w['contact_number']): ?>
                                                    <br><i class="bi bi-telephone"></i> <?= htmlspecialchars($w['contact_number']) ?>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                        <a href="?case_id=<?= $case_id ?>&edit=<?= $w['id'] ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-muted text-center py-5">No witnesses recorded yet</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
