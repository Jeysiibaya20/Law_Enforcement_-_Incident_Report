<?php
session_start();
require '../config/db_connect.php';
require 'helpers.php';
require '../includes/attachment_manager.php';

$page_title = 'Create Blotter';
$base_url = '../';

/**
 * Determine priority level based on incident type
 */
function getPriorityByIncidentType($incident_type) {
    $incident_type = strtolower(trim($incident_type));
    
    // High Priority Incidents
    $high_priority = [
        'murder', 'homicide', 'rape', 'sexual assault', 'kidnapping', 'robbery',
        'armed robbery', 'assault with weapon', 'shooting', 'stabbing', 'bombing',
        'terrorism', 'hostage', 'serious injury', 'critical incident', 'arson',
        'human trafficking', 'drug trafficking', 'grave threat', 'death threat',
        'violent crime', 'aggravated assault', 'attempted murder', 'gang violence'
    ];
    
    // Medium Priority Incidents
    $medium_priority = [
        'theft', 'burglary', 'robbery attempt', 'vehicle theft', 'shoplifting',
        'fraud', 'scam', 'identity theft', 'vandalism', 'property damage',
        'trespassing', 'harassment', 'cybercrime', 'extortion', 'intimidation',
        'simple assault', 'battery', 'accident', 'hit and run', 'dui', 'drunken driving'
    ];
    
    // Low Priority Incidents
    $low_priority = [
        'lost and found', 'noise complaint', 'parking violation', 'minor dispute',
        'civil matter', 'lost property', 'found property', 'traffic violation',
        'speeding', 'jaywalking', 'loitering', 'minor trespass', 'complaint'
    ];
    
    // Check which category the incident type falls into
    foreach ($high_priority as $type) {
        if (strpos($incident_type, $type) !== false) {
            return 'High';
        }
    }
    
    foreach ($medium_priority as $type) {
        if (strpos($incident_type, $type) !== false) {
            return 'Medium';
        }
    }
    
    foreach ($low_priority as $type) {
        if (strpos($incident_type, $type) !== false) {
            return 'Low';
        }
    }
    
    // Default to Medium if no match found
    return 'Medium';
}

// Handle form submission
$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $blotter_no = 'BLT' . time() . rand(100, 999);
    $complainant = trim($_POST['complainant_name'] ?? '');
    $respondent = trim($_POST['respondent_name'] ?? '');
    $respondent_contact = trim($_POST['respondent_contact'] ?? '');
    $respondent_email = trim($_POST['respondent_email'] ?? '');
    $respondent_address = trim($_POST['respondent_address'] ?? '');
    $incident_type = trim($_POST['incident_type'] ?? '');
    $incident_date = $_POST['incident_date'] ?? null;
    $incident_time = $_POST['incident_time'] ?? null;
    $location = trim($_POST['location'] ?? '');
    $description = trim($_POST['description'] ?? '');
    
    // Auto-determine priority based on incident type
    $priority = getPriorityByIncidentType($incident_type);
    
    // Only allow setting officer_id when current user is an Admin
    $userRole = strtolower($_SESSION['role'] ?? '');
    if ($userRole === 'admin') {
        $officer_id = !empty($_POST['officer_id']) ? intval($_POST['officer_id']) : null;
    } else {
        $officer_id = null;
    }

    // Validation
    if (empty($complainant)) {
        $error = 'Complainant name is required.';
    } elseif (empty($description)) {
        $error = 'Description is required.';
    } elseif (empty($respondent_address)) {
        $error = 'Respondent home address is required.';
    } elseif (empty($respondent_contact) && empty($respondent_email)) {
        $error = 'Respondent contact number or email is required.';
    } else {
        try {
            // Build SQL with created_by and respondent contact fields
            $sql = "INSERT INTO blotters (blotter_no, complainant_name, respondent_name, respondent_contact, respondent_email, respondent_address, incident_type, incident_date, incident_time, location, description, priority, created_by";
            $params = [
                ':blotter_no' => $blotter_no,
                ':complainant' => $complainant,
                ':respondent' => $respondent,
                ':respondent_contact' => $respondent_contact,
                ':respondent_email' => $respondent_email,
                ':respondent_address' => $respondent_address,
                ':incident_type' => $incident_type,
                ':incident_date' => $incident_date,
                ':incident_time' => $incident_time,
                ':location' => $location,
                ':description' => $description,
                ':priority' => $priority,
                ':created_by' => $_SESSION['user_id'] ?? null
            ];
            
            if ($officer_id !== null) {
                $sql .= ", officer_id";
                $params[':officer_id'] = $officer_id;
            }
            
            $sql .= ") VALUES (:blotter_no, :complainant, :respondent, :respondent_contact, :respondent_email, :respondent_address, :incident_type, :incident_date, :incident_time, :location, :description, :priority, :created_by";
            if ($officer_id !== null) {
                $sql .= ", :officer_id";
            }
            $sql .= ")";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            $blotter_id = $pdo->lastInsertId();
            
            // Handle file uploads
            handleFileUpload('blotter', $blotter_id, $_SESSION['user_id'] ?? null);
            
            $success = "Blotter #{$blotter_no} created successfully!";
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
require '../includes/navbar.php';
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
        <a href="Blotter.php" class="btn btn-primary">← Back to Blotter List</a>
    </div>
<?php else: ?>

<div class="card">
    <div class="card-header">
        <h5>Blotter Details</h5>
    </div>
    <div class="card-body">
        <form method="post" enctype="multipart/form-data">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold">Complainant <span class="text-danger">*</span></label>
                    <input type="text" name="complainant_name" class="form-control" value="<?= htmlspecialchars($_POST['complainant_name'] ?? '') ?>" required>
                    <small class="text-muted">Name of the person filing the complaint</small>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Respondent Name</label>
                    <input type="text" name="respondent_name" class="form-control" value="<?= htmlspecialchars($_POST['respondent_name'] ?? '') ?>">
                    <small class="text-muted">Name of the person being reported</small>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-bold">Respondent Contact / Email</label>
                    <div class="d-flex gap-2">
                        <input type="text" name="respondent_contact" class="form-control" placeholder="Phone or contact number" value="<?= htmlspecialchars($_POST['respondent_contact'] ?? '') ?>">
                        <input type="email" name="respondent_email" class="form-control" placeholder="Email (optional)" value="<?= htmlspecialchars($_POST['respondent_email'] ?? '') ?>">
                    </div>
                    <small class="text-muted">Provide phone number or email. At least one is required.</small>
                </div>

                <div class="col-12">
                    <label class="form-label fw-bold">Respondent Home Address <span class="text-danger">*</span></label>
                    <input type="text" name="respondent_address" class="form-control" placeholder="Respondent's home address" value="<?= htmlspecialchars($_POST['respondent_address'] ?? '') ?>" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-bold">Incident Type</label>
                    <input id="incident_type" type="text" name="incident_type" class="form-control" placeholder="e.g., Theft, Assault, etc." value="<?= htmlspecialchars($_POST['incident_type'] ?? '') ?>">
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
                    <textarea name="description" class="form-control" rows="5" placeholder="Detailed account of the incident..." required><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                    <small class="text-muted">Provide a comprehensive narrative of the incident</small>
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
                <a href="Blotter.php" class="btn btn-secondary btn-lg">
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
    const incidentType = document.getElementById('incident_type').value.toLowerCase();
    const prioritySelect = document.getElementById('priority');
    const priorityDisplay = document.getElementById('priority_display');
    
    let detectedPriority = 'Medium'; // Default
    
    // High Priority Incidents
    const highPriority = ['murder', 'homicide', 'rape', 'sexual assault', 'kidnapping', 'robbery',
        'armed robbery', 'assault with weapon', 'shooting', 'stabbing', 'bombing',
        'terrorism', 'hostage', 'serious injury', 'critical incident', 'arson',
        'human trafficking', 'drug trafficking', 'grave threat', 'death threat',
        'violent crime', 'aggravated assault', 'attempted murder', 'gang violence'];
    
    // Medium Priority Incidents
    const mediumPriority = ['theft', 'burglary', 'robbery attempt', 'vehicle theft', 'shoplifting',
        'fraud', 'scam', 'identity theft', 'vandalism', 'property damage',
        'trespassing', 'harassment', 'cybercrime', 'extortion', 'intimidation',
        'simple assault', 'battery', 'accident', 'hit and run', 'dui', 'drunken driving'];
    
    // Low Priority Incidents
    const lowPriority = ['lost and found', 'noise complaint', 'parking violation', 'minor dispute',
        'civil matter', 'lost property', 'found property', 'traffic violation',
        'speeding', 'jaywalking', 'loitering', 'minor trespass', 'complaint'];
    
    // Detect priority
    for (let type of highPriority) {
        if (incidentType.includes(type)) {
            detectedPriority = 'High';
            break;
        }
    }
    
    if (detectedPriority === 'Medium') {
        for (let type of mediumPriority) {
            if (incidentType.includes(type)) {
                detectedPriority = 'Medium';
                break;
            }
        }
    }
    
    if (detectedPriority === 'Medium') {
        for (let type of lowPriority) {
            if (incidentType.includes(type)) {
                detectedPriority = 'Low';
                break;
            }
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

// Listen for incident type changes
document.getElementById('incident_type').addEventListener('input', updatePriorityBadge);

// Initial priority update on page load
document.addEventListener('DOMContentLoaded', function() {
    updatePriorityBadge();
});
</script>

