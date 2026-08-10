<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}
require_once __DIR__ . '/../config/db_connect.php';
if (!isset($pdo) || !$pdo) {
    $pdo = getDBConnection();
}

$userId = $_SESSION['user_id'] ?? null;
$userApproved = true;
if ($userId) {
    try {
        $approvalStmt = $pdo->prepare("SELECT admin_approved FROM signup WHERE user_id = ?");
        $approvalStmt->execute([$userId]);
        $approvalRow = $approvalStmt->fetch(PDO::FETCH_ASSOC);
        $userApproved = !empty($approvalRow['admin_approved']) && (int)$approvalRow['admin_approved'] === 1;
    } catch (Exception $e) {
        $userApproved = true;
    }
}

if ($userId && strtolower($_SESSION['role'] ?? '') !== 'admin' && !$userApproved) {
    require '../includes/header.php';
    echo '<div class="main-content"><div class="content-container">';
    echo '<div class="alert alert-warning"><h4>Access Locked</h4><p>Your account is pending administrator approval. The blotter creation module is locked until an administrator approves your account.</p></div>';
    echo '</div></div>';
    require '../includes/footer.php';
    exit;
}

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../includes/attachment_manager.php';
require_once __DIR__ . '/DescriptionTranslationService.php';

$page_title = 'Create Blotter';
$base_url = '../';

/**
 * Determine priority level based on incident type
 */
function getPriorityByIncidentType($incident_type) {
    $incident_type = strtolower(trim($incident_type));
    
    // Low Priority Incidents
    $low_priority = [
        'lost and found', 'noise complaint', 'parking violation', 'minor dispute',
        'civil matter', 'lost property', 'found property', 'traffic violation',
        'speeding', 'jaywalking', 'loitering', 'minor trespass', 'complaint', 'other'
    ];
    
    foreach ($low_priority as $type) {
        if (strpos($incident_type, $type) !== false) {
            return 'Low';
        }
    }
    
    // Default to Low priority
    return 'Low';
}

// Handle form submission
$error = null;
$success = null;
$userRole = strtolower($_SESSION['role'] ?? '');
$defaultComplainantName = '';
$defaultComplainantContact = '';
$defaultComplainantEmail = '';
$defaultComplainantAddress = '';

function ensureBlotterTranslationColumns(PDO $pdo): void
{
    $columns = [
        'description_english' => 'TEXT NULL AFTER description',
        'description_language' => 'VARCHAR(10) NULL AFTER description_english',
        'description_translation_provider' => 'VARCHAR(30) NULL AFTER description_language',
    ];

    foreach ($columns as $column => $definition) {
        $check = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'blotters' AND COLUMN_NAME = ?");
        $check->execute([$column]);
        if ((int)$check->fetchColumn() === 0) {
            $pdo->exec("ALTER TABLE blotters ADD COLUMN {$column} {$definition}");
        }
    }
}

if ($userRole !== 'admin') {
    $defaultComplainantName = trim($_SESSION['fullname'] ?? '');
    $defaultComplainantEmail = trim($_SESSION['email'] ?? '');

    if (!empty($_SESSION['user_id'])) {
        try {
            $stmt = $pdo->prepare('SELECT fullname, emailadd, email, phone, address FROM signup WHERE user_id = ?');
            $stmt->execute([$_SESSION['user_id']]);
            $userRow = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!empty($userRow['fullname'])) {
                $defaultComplainantName = trim($userRow['fullname']);
            }
            if (!empty($userRow['phone'])) {
                $defaultComplainantContact = trim($userRow['phone']);
            }
            if (!empty($userRow['address'])) {
                $defaultComplainantAddress = trim($userRow['address']);
            }
            if (!empty($userRow['emailadd'])) {
                $defaultComplainantEmail = trim($userRow['emailadd']);
            } elseif (!empty($userRow['email'])) {
                $defaultComplainantEmail = trim($userRow['email']);
            }
        } catch (Exception $e) {
            error_log('Unable to resolve default complainant profile: ' . $e->getMessage());
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $blotter_no = 'BLT' . time() . rand(100, 999);
    $complainant = trim($_POST['complainant_name'] ?? $defaultComplainantName);
    $complainant_contact = trim($_POST['complainant_contact'] ??'');
    $complainant_email = trim($_POST['complainant_email'] ?? '');
    $complainant_address = trim($_POST['complainant_address'] ?? '');
    $respondent = trim($_POST['respondent_name'] ?? '');
    $respondent_contact = trim($_POST['respondent_contact'] ?? '');
    $respondent_email = trim($_POST['respondent_email'] ?? '');
    $respondent_address = trim($_POST['respondent_address'] ?? '');
    $complainant_signature = trim($_POST['complainant_signature'] ?? '');
    $incident_type = trim($_POST['incident_type'] ?? '');
    // If user selected "Other", prefer the free-text other field when provided
    if (strtolower($incident_type) === 'other') {
        $otherType = trim($_POST['incident_type_other'] ?? '');
        if (!empty($otherType)) {
            $incident_type = $otherType;
        }
    }
    $incident_date = $_POST['incident_date'] ?? null;
    $incident_time = $_POST['incident_time'] ?? null;
    $location = trim($_POST['location'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $descriptionEnglish = $description;
    $descriptionLanguage = 'en';
    $descriptionTranslationProvider = 'none';
    
    // Auto-determine priority based on incident type
    $priority = getPriorityByIncidentType($incident_type);
    
    // Only allow setting officer_id when current user is an Admin
    $userRole = strtolower($_SESSION['role'] ?? '');
    if ($userRole === 'admin') {
        $officer_id = !empty($_POST['officer_id']) ? intval($_POST['officer_id']) : null;
    } else {
        $officer_id = null;
    }

    // Convert Base64 signature data URL to PNG file if present
    if (!empty($complainant_signature) && strpos($complainant_signature, 'data:image') === 0) {
        try {
            $sigDir = __DIR__ . '/../uploads/signatures/';
            if (!is_dir($sigDir)) {
                @mkdir($sigDir, 0777, true);
            }
            $parts = explode(',', $complainant_signature);
            if (count($parts) > 1) {
                $sigData = base64_decode($parts[1]);
                if ($sigData !== false) {
                    $sigFilename = 'sig_' . time() . '_' . rand(1000, 9999) . '.png';
                    $sigPath = $sigDir . $sigFilename;
                    if (@file_put_contents($sigPath, $sigData)) {
                        $complainant_signature = 'uploads/signatures/' . $sigFilename;
                    }
                }
            }
        } catch (Exception $e) {
            error_log('Error saving signature file: ' . $e->getMessage());
        }
    }

    // Validation
    if (empty($complainant)) {
        $error = 'Complainant name is required.';
    } elseif (empty($description)) {
        $error = 'Description is required.';
    } elseif (empty($respondent_address)) {
        $error = 'Respondent home address is required.';
    } elseif (empty($incident_date) || empty($incident_time)) {
        $error = 'Incident date and time are required.';
    } else {
        try {
            // Ensure complainant_signature column is LONGTEXT to prevent data truncation errors
            try {
                $pdo->exec("ALTER TABLE blotters MODIFY complainant_signature LONGTEXT NULL");
            } catch (Exception $e) {}

            ensureBlotterTranslationColumns($pdo);
            $translation = (new DescriptionTranslationService($env ?? []))->translateToEnglish($description);
            $descriptionEnglish = $translation['translation'];
            $descriptionLanguage = $translation['language'];
            $descriptionTranslationProvider = $translation['provider'];

            // Build SQL with created_by, status, and respondent contact fields
            $sql = "INSERT INTO blotters (blotter_no, complainant_name, complainant_contact, complainant_email, complainant_address, respondent_name, respondent_contact, respondent_email, respondent_address, incident_type, incident_date, incident_time, location, description, description_english, description_language, description_translation_provider, priority, status, complainant_signature, created_by";
            $params = [
                ':blotter_no' => $blotter_no,
                ':complainant' => $complainant,
                ':complainant_contact' => $complainant_contact,
                ':complainant_email' => $complainant_email,
                ':complainant_address' => $complainant_address,
                ':respondent' => $respondent,
                ':respondent_contact' => $respondent_contact,
                ':respondent_email' => $respondent_email,
                ':respondent_address' => $respondent_address,
                ':incident_type' => $incident_type,
                ':incident_date' => $incident_date,
                ':incident_time' => $incident_time,
                ':location' => $location,
                ':description' => $description,
                ':description_english' => $descriptionEnglish,
                ':description_language' => $descriptionLanguage,
                ':description_translation_provider' => $descriptionTranslationProvider,
                ':priority' => $priority,
                ':status' => 'Pending',
                ':complainant_signature' => $complainant_signature,
                ':created_by' => $_SESSION['user_id'] ?? null
            ];
            
            if ($officer_id !== null) {
                $sql .= ", officer_id";
                $params[':officer_id'] = $officer_id;
            }
            
            $sql .= ") VALUES (:blotter_no, :complainant, :complainant_contact, :complainant_email, :complainant_address, :respondent, :respondent_contact, :respondent_email, :respondent_address, :incident_type, :incident_date, :incident_time, :location, :description, :description_english, :description_language, :description_translation_provider, :priority, :status, :complainant_signature, :created_by";
            if ($officer_id !== null) {
                $sql .= ", :officer_id";
            }
            $sql .= ")";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            $blotter_id = $pdo->lastInsertId();
            
            // Handle file uploads
            handleFileUpload('blotter', $blotter_id, $_SESSION['user_id'] ?? null);
            
            $_SESSION['flash'] = ['type' => 'success', 'message' => "Blotter #{$blotter_no} created successfully."];
            $isAdminUser = !empty($_SESSION['admin_user_id']) || (strtolower($_SESSION['role'] ?? '') === 'admin');
            if ($isAdminUser) {
                header('Location: Blotter.php');
            } else {
                header('Location: my_reports.php');
            }
            exit;
        } catch (Exception $e) {
            $msg = $e->getMessage();
            if (strpos($msg, 'Unknown column') !== false) {
                // Friendly guidance when columns are missing from DB
                $error = 'Database schema missing required columns. Please open "http://localhost/setup_blotter_columns.php" in your browser to run the migration and add the required columns. (Details: ' . htmlspecialchars($msg) . ')';
            } else {
                $error = 'Error creating blotter: ' . $msg;
            }
        }
    }
}

// Fetch all available officers from manage_staff
try {
    $officers = $pdo->query("
        SELECT b.user_id AS id, s.fullname AS name, b.barangay, b.rank, b.is_available
        FROM bcpc_officers b
        LEFT JOIN signup s ON b.user_id = s.user_id
        ORDER BY s.fullname ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $officers = [];
}

// Current user role for conditional rendering
$userRole = strtolower($_SESSION['role'] ?? '');

require '../includes/header.php';
?>

<div class="main-content">
<div class="content-container">

<h1 class="h2 mb-4">Create New Blotter</h1>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible">
        <?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (!empty($success)): ?>
    <div class="alert alert-success alert-dismissible">
        <?= htmlspecialchars($success) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <div class="mt-3">
        <?php if (!empty($_SESSION['admin_user_id']) || (strtolower($_SESSION['role'] ?? '') === 'admin')): ?>
            <a href="Blotter.php" class="btn btn-primary">← Back to Blotter List</a>
        <?php else: ?>
            <a href="my_reports.php" class="btn btn-primary"><i class="fas fa-home me-1"></i> Back to Dashboard</a>
        <?php endif; ?>
    </div>
<?php else: ?>

<div class="card">
    <div class="card-header">
        <h5>Blotter Details</h5>
    </div>
    <div class="card-body">
        <form method="post" enctype="multipart/form-data" autocomplete="off">
            <div class="row g-3">
                <div class="col-12">
                    <h5 class="mb-3">Complainant Information</h5>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Complainant Name <span class="text-danger">*</span></label>
                    <input type="text" id="complainant_name" name="complainant_name" autocomplete="off" class="form-control" value="<?= htmlspecialchars($_POST['complainant_name'] ?? ($userRole !== 'admin' ? $defaultComplainantName : '')) ?>" required>
                    <small class="text-muted">Name of the person filing the complaint</small>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Complainant Contact</label>
                    <input type="text" name="complainant_contact" autocomplete="off" class="form-control" placeholder="Phone or contact number" value="<?= htmlspecialchars($_POST['complainant_contact'] ?? ($userRole !== 'admin' ? $defaultComplainantContact : '')) ?>">
                    <small class="text-muted">Optional phone or mobile number</small>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Complainant Email</label>
                    <input type="email" name="complainant_email" autocomplete="off" class="form-control" placeholder="Email address" value="<?= htmlspecialchars($_POST['complainant_email'] ?? ($userRole !== 'admin' ? $defaultComplainantEmail : '')) ?>">
                    <small class="text-muted">Optional email address</small>
                </div>
                <div class="col-12">
                    <label class="form-label fw-bold">Complainant Home Address</label>
                    <input type="text" name="complainant_address" autocomplete="off" class="form-control" placeholder="Complainant's home address" value="<?= htmlspecialchars($_POST['complainant_address'] ?? ($userRole !== 'admin' ? $defaultComplainantAddress : '')) ?>">
                    <small class="text-muted">Optional full address</small>
                </div>

                <div class="col-12 mt-4">
                    <h5 class="mb-3">Respondent Information</h5>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Respondent Name</label>
                    <input type="text" id="respondent_name" name="respondent_name" autocomplete="off" class="form-control" value="<?= htmlspecialchars($_POST['respondent_name'] ?? '') ?>">
                    <small class="text-muted">Name of the person being reported</small>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-bold">Respondent Contact / Email</label>
                    <div class="d-flex gap-2">
                        <input type="text" id="respondent_contact" name="respondent_contact" autocomplete="off" class="form-control" placeholder="Phone or contact number" value="<?= htmlspecialchars($_POST['respondent_contact'] ?? '') ?>">
                        <input type="email" id="respondent_email" name="respondent_email" autocomplete="off" class="form-control" placeholder="Email (optional)" value="<?= htmlspecialchars($_POST['respondent_email'] ?? '') ?>">
                    </div>
                    <small class="text-muted">Optional. Provide phone number or email if available.</small>
                </div>

                <div class="col-12">
                    <label class="form-label fw-bold">Respondent Home Address <span class="text-danger">*</span></label>
                    <input type="text" id="respondent_address" name="respondent_address" autocomplete="off" class="form-control" placeholder="Respondent's home address" value="<?= htmlspecialchars($_POST['respondent_address'] ?? '') ?>" required>
                </div>

                <div class="col-12 mt-4">
                    <h5 class="mb-3">Complainant Signature</h5>
                </div>
                <div class="col-12">
                    <div class="card p-3 mb-4">
                        <label class="form-label fw-bold">Use your mouse or touch screen to sign below</label>
                        <canvas id="signature-pad" class="form-control" style="width: 100%; height: 220px; border: 1px solid #ced4da; border-radius: 0.375rem; cursor: crosshair;"></canvas>
                        <div class="mt-3 d-flex gap-2">
                            <button type="button" class="btn btn-outline-secondary" id="clear-signature">Clear</button>
                            <button type="button" class="btn btn-outline-primary" id="save-signature">Capture Signature</button>
                        </div>
                        <input type="hidden" name="complainant_signature" id="complainant_signature" value="<?= htmlspecialchars($_POST['complainant_signature'] ?? '') ?>">
                        <small class="text-muted d-block mt-2">Click Capture Signature before submitting, or the signature will be saved automatically when the form is sent.</small>
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-bold">Incident Type</label>
                    <select id="incident_type" name="incident_type" class="form-select">
                        <option value="">-- Select incident type --</option>
                        <?php
                        $incident_low = [
                            'Lost and Found',
                            'Noise Complaint',
                            'Parking Violation',
                            'Minor Dispute',
                            'Civil Matter',
                            'Lost Property',
                            'Found Property',
                            'Traffic Violation',
                            'Speeding',
                            'Jaywalking',
                            'Loitering',
                            'Minor Trespass',
                            'Complaint'
                        ];

                        $selected = $_POST['incident_type'] ?? '';

                        echo '<optgroup label="Low Priority Incidents">';
                        foreach ($incident_low as $opt) {
                            $sel = ($selected === $opt) ? 'selected' : '';
                            echo '<option value="'.htmlspecialchars($opt).'" '.$sel.'>'.htmlspecialchars($opt).'</option>';
                        }
                        echo '</optgroup>';

                        $sel = ($selected === 'Other') ? 'selected' : '';
                        echo '<option value="Other" '.$sel.'>Other (specify)</option>';
                        ?>
                    </select>
                    <div id="incident_type_other_wrap" style="display: none; margin-top:8px;">
                        <input type="text" id="incident_type_other" name="incident_type_other" class="form-control" placeholder="Specify incident type" value="<?= htmlspecialchars($_POST['incident_type_other'] ?? '') ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Incident Date</label>
                    <input type="date" name="incident_date" class="form-control" value="<?= htmlspecialchars($_POST['incident_date'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Time</label>
                    <input type="time" name="incident_time" class="form-control" value="<?= htmlspecialchars($_POST['incident_time'] ?? '') ?>">
                </div>

                <div class="col-12">
                    <label class="form-label fw-bold">Location</label>
                    <input type="text" name="location" class="form-control" placeholder="Address or area where incident occurred" value="<?= htmlspecialchars($_POST['location'] ?? '') ?>">
                </div>

                <div class="col-12">
                    <label class="form-label fw-bold">Description <span class="text-danger">*</span></label>
                    <textarea id="description" name="description" class="form-control" rows="5" placeholder="Detailed account of the incident..." required><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                    <small class="text-muted">Provide a comprehensive narrative of the incident</small>
                    <small class="d-block text-muted mt-1">HanLP detects the language. English translation is generated online after you type a space or pause.</small>
                    <div id="description-translation" class="mt-2 p-2 border rounded bg-light" hidden>
                        <small class="text-muted d-block">English translation (HanLP detected language)</small>
                        <div id="description-translation-text" style="white-space: pre-wrap;"></div>
                        <small id="description-translation-status" class="text-muted"></small>
                    </div>
                </div>

                <!-- Hidden priority field - automatically detected -->
                <input type="hidden" name="priority" id="priority" value="Medium">

                <div class="col-md-4">
                    <label class="form-label fw-bold">Priority Level</label>
                    <div class="d-flex align-items-center gap-2">
                        <span id="priority_display" class="badge bg-warning p-2" style="font-size: 1rem; white-space: nowrap;">🔹 Medium</span>
                        <small class="text-muted">(Auto-detected)</small>
                    </div>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-bold">Status</label>
                    <select name="status" class="form-select" disabled>
                        <option selected>Pending</option>
                    </select>
                    <small class="text-muted">New blotters start as Pending</small>
                </div>

                <?php if ($userRole === 'admin'): ?>
                <div class="col-md-4">
                    <label class="form-label fw-bold">Assigned Officer</label>
                    <select name="officer_id" class="form-select">
                        <option value="">-- Unassigned --</option>
                        <?php foreach ($officers as $o): ?>
                            <option value="<?= intval($o['id']) ?>" <?= ($_POST['officer_id'] ?? '') == $o['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($o['name']) ?> 
                                <?php if (!empty($o['rank'])): ?>
                                    (<?= htmlspecialchars($o['rank']) ?>)
                                <?php endif; ?>
                                <?php if (!empty($o['barangay'])): ?>
                                    - <?= htmlspecialchars($o['barangay']) ?>
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">Select from available BCPC officers</small>
                </div>
                <?php endif; ?>
            </div>

            <!-- Attachments Section -->
            <div class="mt-4">
                <h6 class="fw-bold mb-3">Attachments (Optional)</h6>
                <div id="attachments-container">
                    <div class="attachment-item border rounded p-3 mb-3">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">File</label>
                                <input type="file" name="attachments[]" class="form-control" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt">
                            </div>
                            <div class="col-md-5">
                                <label class="form-label">Description</label>
                                <input type="text" name="attachment_descriptions[]" class="form-control" placeholder="Brief description of the file">
                            </div>
                            <div class="col-md-1 d-flex align-items-end">
                                <button type="button" class="btn btn-outline-danger remove-attachment" onclick="removeAttachment(this)">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <button type="button" class="btn btn-outline-primary btn-sm" onclick="addAttachment()">
                    <i class="bi bi-plus-circle"></i> Add Another File
                </button>
                <small class="text-muted d-block mt-2">
                    Supported formats: Images (JPG, PNG, GIF), PDF, Word documents, Excel files, Text files. Max 10MB per file.
                </small>
            </div>

            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="bi bi-check-circle"></i> Create Blotter
                </button>
                <?php
                $cancelUrl = (!empty($_SESSION['admin_user_id']) || (strtolower($_SESSION['role'] ?? '') === 'admin'))
                    ? ($base_url . 'admin/dashboard.php')
                    : ($base_url . 'modules/my_reports.php');
                ?>
                <a href="<?= htmlspecialchars($cancelUrl); ?>" class="btn btn-secondary btn-lg">
                    <i class="bi bi-x-circle"></i> Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<?php endif; ?>

</div>
</div>

<script>
function addAttachment() {
    const container = document.getElementById('attachments-container');
    const firstItem = container.querySelector('.attachment-item');
    const newItem = firstItem.cloneNode(true);
    
    // Clear the inputs
    const inputs = newItem.querySelectorAll('input');
    inputs.forEach(input => {
        input.value = '';
    });
    
    container.appendChild(newItem);
}

function removeAttachment(button) {
    const container = document.getElementById('attachments-container');
    const items = container.querySelectorAll('.attachment-item');
    
    if (items.length > 1) {
        button.closest('.attachment-item').remove();
    } else {
        // Clear the inputs instead of removing the last one
        const inputs = button.closest('.attachment-item').querySelectorAll('input');
        inputs.forEach(input => {
            input.value = '';
        });
    }
}

// Auto-detect priority based on incident type
function updatePriorityBadge() {
    const incidentEl = document.getElementById('incident_type');
    const otherEl = document.getElementById('incident_type_other');
    let incidentType = '';
    if (incidentEl) incidentType = String(incidentEl.value || '').toLowerCase();
    // if user selected "Other", use the free-text value for detection
    if (incidentType === 'other' && otherEl) {
        incidentType = String(otherEl.value || '').toLowerCase();
    }
    const prioritySelect = document.getElementById('priority');
    const priorityDisplay = document.getElementById('priority_display');
    
    let detectedPriority = 'Low'; // Default to Low priority
    
    // Low Priority Incidents
    const lowPriority = ['lost and found', 'noise complaint', 'parking violation', 'minor dispute',
        'civil matter', 'lost property', 'found property', 'traffic violation',
        'speeding', 'jaywalking', 'loitering', 'minor trespass', 'complaint'];
    
    for (let type of lowPriority) {
        if (incidentType.includes(type)) {
            detectedPriority = 'Low';
            break;
        }
    }
    
    // Update display
    priorityDisplay.textContent = (detectedPriority === 'High' ? '🔴' : detectedPriority === 'Low' ? '🟢' : '🔹') + ' ' + detectedPriority;
    priorityDisplay.className = 'badge p-2 fw-bold ' + 
        (detectedPriority === 'High' ? 'bg-danger' : 
         detectedPriority === 'Low' ? 'bg-success' : 'bg-warning');
    
    // ALWAYS update the hidden priority field value
    prioritySelect.value = detectedPriority;
}

function toggleOtherField() {
    const incidentEl = document.getElementById('incident_type');
    const wrap = document.getElementById('incident_type_other_wrap');
    if (!incidentEl || !wrap) return;
    wrap.style.display = (incidentEl.value === 'Other') ? 'block' : 'none';
}

// Listen for incident type changes
const incidentEl = document.getElementById('incident_type');
if (incidentEl) {
    incidentEl.addEventListener('input', updatePriorityBadge);
    incidentEl.addEventListener('change', function() { toggleOtherField(); updatePriorityBadge(); });
}
const incidentOtherEl = document.getElementById('incident_type_other');
if (incidentOtherEl) {
    incidentOtherEl.addEventListener('input', updatePriorityBadge);
}

const descriptionEl = document.getElementById('description');
const translationPanel = document.getElementById('description-translation');
const translationText = document.getElementById('description-translation-text');
const translationStatus = document.getElementById('description-translation-status');
let translationTimer = null;
let translationRequest = 0;

function translateDescription() {
    if (!descriptionEl || !translationPanel) return;
    const text = descriptionEl.value.trim();
    if (!text || text.length < 2) {
        translationPanel.hidden = true;
        return;
    }

    const requestId = ++translationRequest;
    translationPanel.hidden = false;
    translationText.textContent = 'Translating online...';
    translationStatus.textContent = '';

    fetch('translate_description.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ text })
    })
        .then(response => response.json())
        .then(result => {
            if (requestId !== translationRequest) return;
            if (result.error) throw new Error(result.error);
            translationText.textContent = result.translation || text;
            translationStatus.textContent = result.translated
                ? `Detected: ${result.language}. Online provider: ${result.provider}.`
                : `Detected: ${result.language}.`;
        })
        .catch(error => {
            if (requestId !== translationRequest) return;
            translationText.textContent = '';
            translationStatus.textContent = error.message || 'Online translation unavailable.';
        });
}

if (descriptionEl) {
    descriptionEl.addEventListener('input', function() {
        clearTimeout(translationTimer);
        translationTimer = setTimeout(translateDescription, 900);
    });
    descriptionEl.addEventListener('keyup', function(event) {
        if (event.key === ' ' || /[.!?]/.test(event.key)) {
            clearTimeout(translationTimer);
            translationTimer = setTimeout(translateDescription, 300);
        }
    });
    descriptionEl.addEventListener('blur', translateDescription);
}

    const signatureCanvas = document.getElementById('signature-pad');
    const signatureInput = document.getElementById('complainant_signature');
    const clearSignatureBtn = document.getElementById('clear-signature');
    const saveSignatureBtn = document.getElementById('save-signature');

    if (signatureCanvas) {
        const ctx = signatureCanvas.getContext('2d');
        let drawing = false;
        let lastX = 0;
        let lastY = 0;

        function resizeCanvas() {
            const rect = signatureCanvas.getBoundingClientRect();
            const ratio = window.devicePixelRatio || 1;
            signatureCanvas.width = rect.width * ratio;
            signatureCanvas.height = rect.height * ratio;
            signatureCanvas.style.width = rect.width + 'px';
            signatureCanvas.style.height = rect.height + 'px';
            ctx.scale(ratio, ratio);
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';
            ctx.strokeStyle = '#000';
            ctx.lineWidth = 2;
        }

        function getPointerPosition(event) {
            const rect = signatureCanvas.getBoundingClientRect();
            const x = (event.clientX - rect.left);
            const y = (event.clientY - rect.top);
            return { x, y };
        }

        function startDrawing(event) {
            drawing = true;
            const pos = getPointerPosition(event);
            lastX = pos.x;
            lastY = pos.y;
            ctx.beginPath();
            ctx.moveTo(lastX, lastY);
            event.preventDefault();
        }

        function draw(event) {
            if (!drawing) return;
            const pos = getPointerPosition(event);
            ctx.lineTo(pos.x, pos.y);
            ctx.stroke();
            lastX = pos.x;
            lastY = pos.y;
            event.preventDefault();
        }

        function stopDrawing() {
            drawing = false;
        }

        function clearSignature() {
            ctx.clearRect(0, 0, signatureCanvas.width, signatureCanvas.height);
            if (signatureInput) {
                signatureInput.value = '';
            }
        }

        function captureSignature() {
            const dataUrl = signatureCanvas.toDataURL('image/png');
            if (signatureInput) {
                signatureInput.value = dataUrl;
            }
        }

        window.addEventListener('resize', resizeCanvas);
        resizeCanvas();

        signatureCanvas.addEventListener('pointerdown', startDrawing);
        signatureCanvas.addEventListener('pointermove', draw);
        signatureCanvas.addEventListener('pointerup', stopDrawing);
        signatureCanvas.addEventListener('pointerleave', stopDrawing);

        if (clearSignatureBtn) {
            clearSignatureBtn.addEventListener('click', clearSignature);
        }

        if (saveSignatureBtn) {
            saveSignatureBtn.addEventListener('click', captureSignature);
        }

        const formElement = document.querySelector('form');
        if (formElement) {
            formElement.addEventListener('submit', function() {
                captureSignature();
            });
        }
    }

</script>

<?php require '../includes/footer.php'; ?>

