<?php
require_once 'admin_auth.php';
require_once '../config/db_connect.php';
if (!isset($pdo) || !$pdo) {
    $pdo = getDBConnection();
}

require_once __DIR__ . '/../modules/helpers.php';
require_once __DIR__ . '/../includes/attachment_manager.php';
require_once __DIR__ . '/../modules/DescriptionTranslationService.php';

$page_title = 'Create New Blotter';
$base_url = '../';

/**
 * Determine priority level based on incident type
 */
function getPriorityByIncidentType($incident_type) {
    $incident_type = strtolower(trim($incident_type));
    
    $low_priority = [
        'lost and found', 'noise complaint', 'parking violation', 'minor dispute',
        'civil matter', 'lost property', 'found property', 'traffic violation',
        'speeding', 'jaywalking', 'loitering', 'minor trespass', 'complaint'
    ];
    
    foreach ($low_priority as $type) {
        if (strpos($incident_type, $type) !== false) {
            return 'Low';
        }
    }
    
    return 'Medium';
}

$error = null;
$success = null;

function ensureBlotterSchema(PDO $pdo): void
{
    $columns = [
        'complainant_contact' => 'VARCHAR(50) NULL AFTER complainant_name',
        'complainant_email' => 'VARCHAR(150) NULL AFTER complainant_contact',
        'complainant_address' => 'VARCHAR(255) NULL AFTER complainant_email',
        'complainant_signature' => 'LONGTEXT NULL',
        'description_english' => 'TEXT NULL AFTER description',
        'description_language' => 'VARCHAR(10) NULL AFTER description_english',
        'description_translation_provider' => 'VARCHAR(30) NULL AFTER description_language',
    ];

    foreach ($columns as $column => $definition) {
        try {
            $check = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'blotters' AND COLUMN_NAME = ?");
            $check->execute([$column]);
            if ((int)$check->fetchColumn() === 0) {
                $pdo->exec("ALTER TABLE blotters ADD COLUMN {$column} {$definition}");
            }
        } catch (Exception $e) {}
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $blotter_no = 'BLT' . time() . rand(100, 999);
    $complainant = trim($_POST['complainant_name'] ?? '');
    $complainant_contact = trim($_POST['complainant_contact'] ?? '');
    $complainant_email = trim($_POST['complainant_email'] ?? '');
    $complainant_address = trim($_POST['complainant_address'] ?? '');
    $respondent = trim($_POST['respondent_name'] ?? '');
    $respondent_contact = trim($_POST['respondent_contact'] ?? '');
    $respondent_email = trim($_POST['respondent_email'] ?? '');
    $respondent_address = trim($_POST['respondent_address'] ?? '');
    $complainant_signature = trim($_POST['complainant_signature'] ?? '');
    $incident_type = trim($_POST['incident_type'] ?? '');
    
    if (strtolower($incident_type) === 'other') {
        $otherType = trim($_POST['incident_type_other'] ?? '');
        if (!empty($otherType)) {
            $incident_type = $otherType;
        }
    }
    
    $incident_date = $_POST['incident_date'] ?? date('Y-m-d');
    $incident_time = $_POST['incident_time'] ?? date('H:i');
    $location = trim($_POST['location'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $officer_id = !empty($_POST['officer_id']) ? intval($_POST['officer_id']) : null;
    $customPriority = trim($_POST['priority'] ?? '');
    $priority = !empty($customPriority) ? $customPriority : getPriorityByIncidentType($incident_type);

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
            try {
                $pdo->exec("ALTER TABLE blotters MODIFY complainant_signature LONGTEXT NULL");
            } catch (Exception $e) {}

            ensureBlotterSchema($pdo);
            $translation = (new DescriptionTranslationService($env ?? []))->translateToEnglish($description);
            $descriptionEnglish = $translation['translation'];
            $descriptionLanguage = $translation['language'];
            $descriptionTranslationProvider = $translation['provider'];

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
                ':created_by' => $_SESSION['admin_user_id'] ?? $_SESSION['user_id'] ?? null
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
            handleFileUpload('blotter', $blotter_id, $_SESSION['admin_user_id'] ?? $_SESSION['user_id'] ?? null);
            
            $_SESSION['flash'] = ['type' => 'success', 'message' => "Blotter #{$blotter_no} created successfully."];
            header('Location: blotters.php');
            exit;
        } catch (Exception $e) {
            $error = 'Error creating blotter: ' . $e->getMessage();
        }
    }
}

// Fetch all available officers from bcpc_officers
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

require_once '../includes/header.php';
?>

<div class="main-content">
    <div class="content-container">
        <!-- Header Banner -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h1 class="h2 fw-bold text-dark mb-1"><i class="fas fa-clipboard-list text-success me-2"></i>Create New Blotter</h1>
                <p class="text-muted small mb-0">Record a new official incident blotter directly in the Law Enforcement Administration portal.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="blotters.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i> Back to Blotters
                </a>
            </div>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i> <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0 fw-bold text-dark">
                    <i class="fas fa-file-alt text-success me-2"></i>Blotter Information Form
                </h5>
                <span class="badge bg-warning text-dark px-3 py-2">Status: Pending</span>
            </div>
            <div class="card-body p-4">
                <form method="post" enctype="multipart/form-data" autocomplete="off">
                    
                    <!-- Section 1: Complainant Information -->
                    <div class="mb-4">
                        <h6 class="fw-bold text-success border-bottom pb-2 mb-3"><i class="fas fa-user me-2"></i>1. Complainant Information</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Complainant Name <span class="text-danger">*</span></label>
                                <input type="text" id="complainant_name" name="complainant_name" autocomplete="off" class="form-control" placeholder="Full name of complainant" value="<?= htmlspecialchars($_POST['complainant_name'] ?? '') ?>" required>
                                <small class="text-muted">Name of the person filing the complaint</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Complainant Contact</label>
                                <input type="text" name="complainant_contact" autocomplete="off" class="form-control" placeholder="Phone or mobile number" value="<?= htmlspecialchars($_POST['complainant_contact'] ?? '') ?>">
                                <small class="text-muted">Optional phone or mobile number</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Complainant Email</label>
                                <input type="email" name="complainant_email" autocomplete="off" class="form-control" placeholder="Email address" value="<?= htmlspecialchars($_POST['complainant_email'] ?? '') ?>">
                                <small class="text-muted">Optional email address</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Complainant Home Address</label>
                                <input type="text" name="complainant_address" autocomplete="off" class="form-control" placeholder="Full residential address" value="<?= htmlspecialchars($_POST['complainant_address'] ?? '') ?>">
                                <small class="text-muted">Optional full residential address</small>
                            </div>
                        </div>
                    </div>

                    <!-- Section 2: Respondent Information -->
                    <div class="mb-4">
                        <h6 class="fw-bold text-danger border-bottom pb-2 mb-3"><i class="fas fa-user-ninja me-2"></i>2. Respondent Information</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Respondent Name</label>
                                <input type="text" id="respondent_name" name="respondent_name" autocomplete="off" class="form-control" placeholder="Name of person being reported" value="<?= htmlspecialchars($_POST['respondent_name'] ?? '') ?>">
                                <small class="text-muted">Name of the individual being reported / respondent</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Respondent Contact / Email</label>
                                <div class="d-flex gap-2">
                                    <input type="text" id="respondent_contact" name="respondent_contact" autocomplete="off" class="form-control" placeholder="Contact number" value="<?= htmlspecialchars($_POST['respondent_contact'] ?? '') ?>">
                                    <input type="email" id="respondent_email" name="respondent_email" autocomplete="off" class="form-control" placeholder="Email (optional)" value="<?= htmlspecialchars($_POST['respondent_email'] ?? '') ?>">
                                </div>
                                <small class="text-muted">Optional. Provide phone number or email if available.</small>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-bold">Respondent Home Address <span class="text-danger">*</span></label>
                                <input type="text" id="respondent_address" name="respondent_address" autocomplete="off" class="form-control" placeholder="Respondent's complete residential address" value="<?= htmlspecialchars($_POST['respondent_address'] ?? '') ?>" required>
                                <small class="text-muted">Required for summons or formal barangay notifications.</small>
                            </div>
                        </div>
                    </div>

                    <!-- Section 3: Incident Details -->
                    <div class="mb-4">
                        <h6 class="fw-bold text-primary border-bottom pb-2 mb-3"><i class="fas fa-map-marker-alt me-2"></i>3. Incident Details</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Incident Type <span class="text-danger">*</span></label>
                                <select id="incident_type" name="incident_type" class="form-select" required>
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
                                        'Physical Violence / Assault',
                                        'Theft / Robbery',
                                        'Harassment / Threat',
                                        'Domestic Dispute',
                                        'Vandalism / Property Damage',
                                        'Complaint'
                                    ];

                                    $selected = $_POST['incident_type'] ?? '';

                                    foreach ($incident_low as $opt) {
                                        $sel = ($selected === $opt) ? 'selected' : '';
                                        echo '<option value="'.htmlspecialchars($opt).'" '.$sel.'>'.htmlspecialchars($opt).'</option>';
                                    }

                                    $sel = ($selected === 'Other') ? 'selected' : '';
                                    echo '<option value="Other" '.$sel.'>Other (specify)</option>';
                                    ?>
                                </select>
                                <div id="incident_type_other_wrap" style="display: none; margin-top:8px;">
                                    <input type="text" id="incident_type_other" name="incident_type_other" class="form-control" placeholder="Specify incident type" value="<?= htmlspecialchars($_POST['incident_type_other'] ?? '') ?>">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-bold">Incident Date <span class="text-danger">*</span></label>
                                <input type="date" name="incident_date" class="form-control" value="<?= htmlspecialchars($_POST['incident_date'] ?? date('Y-m-d')) ?>" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-bold">Incident Time <span class="text-danger">*</span></label>
                                <input type="time" name="incident_time" class="form-control" value="<?= htmlspecialchars($_POST['incident_time'] ?? date('H:i')) ?>" required>
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-bold">Incident Location <span class="text-danger">*</span></label>
                                <input type="text" name="location" class="form-control" placeholder="Street, barangay, or landmark where incident occurred" value="<?= htmlspecialchars($_POST['location'] ?? '') ?>" required>
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-bold">Description / Narrative <span class="text-danger">*</span></label>
                                <textarea id="description" name="description" class="form-control" rows="5" placeholder="Detailed official narrative of the incident..." required><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                                <small class="text-muted">Provide a comprehensive narrative of the incident.</small>
                                <div id="description-translation" class="mt-2 p-2 border rounded bg-light" hidden>
                                    <small class="text-muted d-block fw-bold"><i class="fas fa-language me-1"></i>English Translation (Auto-detected)</small>
                                    <div id="description-translation-text" style="white-space: pre-wrap;" class="small text-dark"></div>
                                    <small id="description-translation-status" class="text-muted"></small>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-bold">Priority Level</label>
                                <div class="d-flex align-items-center gap-2">
                                    <input type="hidden" name="priority" id="priority" value="<?= htmlspecialchars($_POST['priority'] ?? 'Medium') ?>">
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

                            <div class="col-md-4">
                                <label class="form-label fw-bold">Assign Officer</label>
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
                                <small class="text-muted">Select from registered BCPC officers</small>
                            </div>
                        </div>
                    </div>

                    <!-- Section 4: Complainant Signature -->
                    <div class="mb-4">
                        <h6 class="fw-bold text-dark border-bottom pb-2 mb-3"><i class="fas fa-signature me-2"></i>4. Complainant Signature</h6>
                        <div class="card p-3 bg-light border">
                            <label class="form-label fw-bold small text-muted">Use mouse or touchscreen to capture signature below:</label>
                            <canvas id="signature-pad" class="form-control bg-white" style="width: 100%; height: 180px; border: 1px solid #ced4da; border-radius: 0.375rem; cursor: crosshair;"></canvas>
                            <div class="mt-2 d-flex gap-2">
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="clear-signature"><i class="fas fa-eraser me-1"></i>Clear</button>
                                <button type="button" class="btn btn-outline-primary btn-sm" id="save-signature"><i class="fas fa-check me-1"></i>Capture Signature</button>
                            </div>
                            <input type="hidden" name="complainant_signature" id="complainant_signature" value="<?= htmlspecialchars($_POST['complainant_signature'] ?? '') ?>">
                        </div>
                    </div>

                    <!-- Section 5: Attachments -->
                    <div class="mb-4">
                        <h6 class="fw-bold text-dark border-bottom pb-2 mb-3"><i class="fas fa-paperclip me-2"></i>5. Attachments (Optional)</h6>
                        <div id="attachments-container">
                            <div class="attachment-item border rounded p-3 mb-2 bg-light">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold">Upload File</label>
                                        <input type="file" name="attachments[]" class="form-control form-control-sm" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt">
                                    </div>
                                    <div class="col-md-5">
                                        <label class="form-label small fw-bold">Description / Label</label>
                                        <input type="text" name="attachment_descriptions[]" class="form-control form-control-sm" placeholder="e.g. Incident Scene Photo, Medical Slip">
                                    </div>
                                    <div class="col-md-1 d-flex align-items-end">
                                        <button type="button" class="btn btn-outline-danger btn-sm remove-attachment" onclick="removeAttachment(this)">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <button type="button" class="btn btn-outline-primary btn-sm mt-1" onclick="addAttachment()">
                            <i class="bi bi-plus-circle me-1"></i> Add Another File
                        </button>
                        <small class="text-muted d-block mt-2">
                            Supported formats: Images (JPG, PNG, GIF), PDF, Word, Excel, Text files. Max 10MB per file.
                        </small>
                    </div>

                    <!-- Form Submission Buttons -->
                    <div class="mt-4 pt-3 border-top d-flex gap-2">
                        <button type="submit" class="btn btn-success fw-bold px-4 py-2" style="background-color: #2e856e; border-color: #2e856e;">
                            <i class="bi bi-check-circle me-1"></i> Save & Record Blotter
                        </button>
                        <a href="blotters.php" class="btn btn-outline-secondary px-4 py-2">
                            <i class="bi bi-x-circle me-1"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function addAttachment() {
    const container = document.getElementById('attachments-container');
    const firstItem = container.querySelector('.attachment-item');
    const newItem = firstItem.cloneNode(true);
    
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
        const inputs = button.closest('.attachment-item').querySelectorAll('input');
        inputs.forEach(input => {
            input.value = '';
        });
    }
}

function updatePriorityBadge() {
    const incidentEl = document.getElementById('incident_type');
    const otherEl = document.getElementById('incident_type_other');
    let incidentType = '';
    if (incidentEl) incidentType = String(incidentEl.value || '').toLowerCase();
    if (incidentType === 'other' && otherEl) {
        incidentType = String(otherEl.value || '').toLowerCase();
    }
    const prioritySelect = document.getElementById('priority');
    const priorityDisplay = document.getElementById('priority_display');
    
    let detectedPriority = 'Medium';
    
    const lowPriority = ['lost and found', 'noise complaint', 'parking violation', 'minor dispute',
        'civil matter', 'lost property', 'found property', 'traffic violation',
        'speeding', 'jaywalking', 'loitering', 'minor trespass', 'complaint'];
    
    const highPriority = ['physical violence', 'assault', 'theft', 'robbery', 'harassment', 'threat', 'domestic dispute', 'property damage'];

    for (let type of lowPriority) {
        if (incidentType.includes(type)) {
            detectedPriority = 'Low';
            break;
        }
    }

    for (let type of highPriority) {
        if (incidentType.includes(type)) {
            detectedPriority = 'High';
            break;
        }
    }
    
    priorityDisplay.textContent = (detectedPriority === 'High' ? '🔴' : detectedPriority === 'Low' ? '🟢' : '🔹') + ' ' + detectedPriority;
    priorityDisplay.className = 'badge p-2 fw-bold ' + 
        (detectedPriority === 'High' ? 'bg-danger' : 
         detectedPriority === 'Low' ? 'bg-success' : 'bg-warning');
    
    prioritySelect.value = detectedPriority;
}

function toggleOtherField() {
    const incidentEl = document.getElementById('incident_type');
    const wrap = document.getElementById('incident_type_other_wrap');
    if (!incidentEl || !wrap) return;
    wrap.style.display = (incidentEl.value === 'Other') ? 'block' : 'none';
}

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

    fetch('../modules/translate_description.php', {
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

<?php require_once '../includes/footer.php'; ?>
