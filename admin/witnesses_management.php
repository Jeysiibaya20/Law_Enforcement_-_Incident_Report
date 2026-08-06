<?php
/**
 * Witness Management Page
 * Allows admin to add, view, and update witness information for cases
 */

require_once 'admin_auth.php';

require_once dirname(__DIR__) . '/config/db_connect.php';
if (!isset($pdo) || !($pdo instanceof PDO)) {
    $pdo = getDBConnection();
}
require_once dirname(__DIR__) . '/includes/case_management.php';
require_once dirname(__DIR__) . '/includes/suspect_witness_management.php';


// Check authorization
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Safely read session role and user id to avoid undefined index warnings
$userId = $_SESSION['user_id'] ?? null;
$role = strtolower($_SESSION['role'] ?? '');

if (!$userId) {
    http_response_code(403);
    echo "Access Denied";
    exit;
}

// Allow access if session role is admin, otherwise fallback to DB check
if ($role !== 'admin') {
    try {
        $rstmt = $pdo->prepare("SELECT role FROM signup WHERE user_id = ? LIMIT 1");
        $rstmt->execute([$userId]);
        $rrow = $rstmt->fetch(PDO::FETCH_ASSOC);
        if (!($rrow && strtolower($rrow['role'] ?? '') === 'admin')) {
            http_response_code(403);
            echo "Access Denied";
            exit;
        }
    } catch (Exception $e) {
        http_response_code(403);
        echo "Access Denied";
        exit;
    }
}


$case_id = $_GET['case_id'] ?? $_POST['case_id'] ?? null;
$edit_id = $_GET['edit'] ?? null;
$cases = [];
try {
    $stmt = $pdo->query("SELECT id, case_number, incident_type, complainant_name FROM case_assignments ORDER BY created_at DESC");
    $cases = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching cases for witness management: " . $e->getMessage());
}

if ($case_id) {
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
} else {
    $case = null;
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
include '../includes/navbar.php';
// Get all witnesses for this case
$witnesses = getWitnessesByCase($case_id);
$page_title = "Witness Management" . ($case ? " - " . htmlspecialchars($case['case_number']) : '');
$body_class = 'blotter-page';
include '../includes/header.php';


?>


<div class="main-content">
    <div class="content-container py-4">
        <div class="d-flex justify-content-between align-items-start mb-4">
            <div>
                <h1 class="h2 mb-2">
                    <i class="bi bi-people-fill"></i> Witness Management
                </h1>
                <p class="text-muted">Case: <strong><?= htmlspecialchars($case['case_number'] ?? 'Not selected') ?></strong></p>
            </div>
            <?php if ($case_id): ?>
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
            <!-- Form -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><?= $edit_id ? 'Update' : 'Add New' ?> Witness</h5>
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
                                    <label class="form-label">Province</label>
                                    <select name="province" id="provinceSelect" class="form-select">
                                        <option value="Metro Manila">Metro Manila</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">City</label>
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
                                    <input type="text" name="zip_code" id="zipCode" class="form-control" value="<?= htmlspecialchars($witness['zip_code'] ?? '') ?>" readonly>
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
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="availableForCourt" name="available_for_court" value="1" <?= !empty($witness['available_for_court']) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="availableForCourt">Available for Court</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="protectionNeeded" name="protection_needed" value="1" <?= !empty($witness['protection_needed']) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="protectionNeeded">Protection Needed</label>
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

    const existingProvince = <?= json_encode($witness['province'] ?? '') ?>;
    const existingCity = <?= json_encode($witness['city'] ?? '') ?>;
    const existingBrgy = <?= json_encode($witness['barangay'] ?? '') ?>;

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
