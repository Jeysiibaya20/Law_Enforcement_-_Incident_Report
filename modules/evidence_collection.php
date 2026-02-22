<?php
session_start();
require_once '../config/db_connect.php';
require_once '../config/LanguageManager.php';
require_once '../includes/header.php';
require_once '../includes/navbar.php';
// Check if user is logged in and is admin
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

// Check admin role
$adminCheck = $pdo->prepare("SELECT role FROM signup WHERE user_id = ?");
$adminCheck->execute([$_SESSION['user_id']]);
$userRole = $adminCheck->fetch(PDO::FETCH_ASSOC);

if (!$userRole || strtolower($userRole['role']) !== 'admin') {
    header('Location: ../index.php');
    exit();
}

$current_lang = LanguageManager::getCurrentLanguage();

// Handle form submission
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['create_evidence'])) {
            // Generate evidence number
            $evidence_number = 'EVD-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);

            // Get collector name
            $collector_stmt = $pdo->prepare("SELECT fullname FROM signup WHERE user_id = ?");
            $collector_stmt->execute([$_SESSION['user_id']]);
            $collector = $collector_stmt->fetch(PDO::FETCH_ASSOC);
            $collector_name = $collector ? $collector['fullname'] : 'Unknown';

            // Insert evidence record
            $insert_stmt = $pdo->prepare("\n                INSERT INTO evidence_records\n                (evidence_number, evidence_type, case_id, case_number, item_description,\n                 location_found, collection_date, `condition`, storage_location,\n                 security_level, witness_name, witness_description, notes, collected_by, collector_name, status)\n                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Collected')\n            ");
            // Combine date and time for collection_date
            $collection_datetime = $_POST['collection_date'];
            if (!empty($_POST['collection_time'])) {
                $collection_datetime .= ' ' . $_POST['collection_time'] . ':00';
            } else {
                $collection_datetime .= ' 00:00:00';
            }

            $insert_stmt->execute([
                $evidence_number,
                $_POST['evidence_type'],
                $_POST['case_id'] ?: null,
                $_POST['case_number'] ?: null,
                $_POST['item_description'],
                $_POST['location_found'] ?: null,
                $collection_datetime,
                $_POST['condition'],
                $_POST['storage_location'],
                $_POST['security_level'],
                $_POST['witness_name'] ?: null,
                $_POST['witness_description'] ?: null,
                $_POST['notes'] ?: null,
                $_SESSION['user_id'],
                $collector_name
            ]);

            $evidence_id = $pdo->lastInsertId();

            // Handle file uploads
            if (isset($_FILES['evidence_files']) && !empty($_FILES['evidence_files']['name'][0])) {
                $upload_dir = '../uploads/evidence/';

                // Create directory if it doesn't exist
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }

                foreach ($_FILES['evidence_files']['tmp_name'] as $key => $tmp_name) {
                    if (!empty($tmp_name)) {
                        $original_name = $_FILES['evidence_files']['name'][$key];
                        $file_extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));

                        // Generate unique filename
                        $new_filename = 'evidence_' . $evidence_id . '_' . time() . '_' . $key . '.' . $file_extension;
                        $file_path = $upload_dir . $new_filename;

                        if (move_uploaded_file($tmp_name, $file_path)) {
                            // Insert attachment record
                            $attachment_stmt = $pdo->prepare("
                                INSERT INTO evidence_attachments
                                (evidence_id, original_filename, stored_filename, file_path, file_type, file_size, mime_type, uploaded_by)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                            ");

                            $attachment_stmt->execute([
                                $evidence_id,
                                $original_name,
                                $new_filename,
                                $file_path,
                                $_FILES['evidence_files']['type'][$key],
                                $_FILES['evidence_files']['size'][$key],
                                $_FILES['evidence_files']['type'][$key],
                                $_SESSION['user_id']
                            ]);
                        }
                    }
                }
            }

            // Add initial chain of custody entry
            $custody_stmt = $pdo->prepare("
                INSERT INTO chain_of_custody
                (evidence_id, action_type, action_date, location, purpose, notes, performed_by)
                VALUES (?, 'Collected', NOW(), ?, 'Initial collection', ?, ?)
            ");
            $custody_stmt->execute([
                $evidence_id,
                $_POST['storage_location'],
                'Evidence collected and stored',
                $_SESSION['user_id']
            ]);

            $message = "Evidence record created successfully with number: " . $evidence_number;
            $message_type = 'success';
        } elseif (isset($_POST['update_status'])) {
            // Update evidence status
            $stmt = $pdo->prepare("UPDATE evidence_records SET status = ? WHERE id = ?");
            $stmt->execute([$_POST['new_status'], $_POST['evidence_id']]);

            // Add chain of custody entry for status change
            $custody_stmt = $pdo->prepare("
                INSERT INTO chain_of_custody
                (evidence_id, action_type, action_date, location, purpose, notes, performed_by)
                VALUES (?, 'Status Changed', NOW(), ?, ?, ?, ?)
            ");
            $custody_stmt->execute([
                $_POST['evidence_id'],
                $_POST['location'] ?: 'System Update',
                'Status changed to ' . $_POST['new_status'],
                $_POST['status_notes'],
                $_SESSION['user_id']
            ]);

            $message = "Evidence status updated successfully";
            $message_type = 'success';
        } elseif (isset($_POST['add_custody_entry'])) {
            // Add manual chain of custody entry
            $custody_stmt = $pdo->prepare("
                INSERT INTO chain_of_custody
                (evidence_id, action_type, from_person_name, to_person_name, action_date,
                 location, purpose, notes, performed_by, witness_name)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $custody_stmt->execute([
                $_POST['evidence_id'],
                $_POST['action_type'],
                $_POST['from_person'],
                $_POST['to_person'],
                $_POST['action_date'] . ' ' . ($_POST['action_time'] ?: '00:00:00'),
                $_POST['location'],
                $_POST['purpose'],
                $_POST['custody_notes'],
                $_SESSION['user_id'],
                $_POST['witness']
            ]);

            $message = "Chain of custody entry added successfully";
            $message_type = 'success';
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $message_type = 'error';
    }
}

// Get cases for dropdown
$cases_stmt = $pdo->query("SELECT id, case_number, incident_type, complainant_name FROM case_assignments ORDER BY created_at DESC");
$cases = $cases_stmt->fetchAll();

// Get evidence records (moved here so new records appear immediately)
$evidence_stmt = $pdo->query("
    SELECT e.*, COUNT(a.id) as attachment_count
    FROM evidence_records e
    LEFT JOIN evidence_attachments a ON e.id = a.evidence_id AND a.is_deleted = 0
    GROUP BY e.id
    ORDER BY e.created_at DESC
");
$evidence_records = $evidence_stmt->fetchAll();

$page_title = "Evidence Collection & Chain of Custody";
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
                    <h2 class="h2 mb-2"><i class="bi bi-file-earmark-lock"></i> Evidence Collection & Chain of Custody</h2>
                    <p class="text-muted">Manage evidence records and maintain chain of custody</p>
                </div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createEvidenceModal">
                    <i class="bi bi-plus-circle"></i> Create Evidence Record
                </button>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Evidence Records Table -->
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-file-earmark-lock"></i> Evidence Records</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Evidence #</th>
                                    <th>Type</th>
                                    <th>Case</th>
                                    <th>Description</th>
                                    <th>Status</th>
                                    <th>Collected By</th>
                                    <th>Date</th>
                                    <th>Attachments</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($evidence_records as $evidence): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($evidence['evidence_number']); ?></td>
                                        <td><?php echo htmlspecialchars($evidence['evidence_type']); ?></td>
                                        <td><?php echo htmlspecialchars($evidence['case_number'] ?: 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars(substr($evidence['item_description'], 0, 50)) . (strlen($evidence['item_description']) > 50 ? '...' : ''); ?></td>
                                        <td>
                                            <span class="badge bg-<?php
                                                echo match($evidence['status']) {
                                                    'Collected' => 'primary',
                                                    'In Storage' => 'info',
                                                    'In Transit' => 'warning',
                                                    'Released' => 'success',
                                                    'Destroyed' => 'danger',
                                                    'Lost' => 'dark',
                                                    default => 'secondary'
                                                };
                                            ?>"><?php echo htmlspecialchars($evidence['status']); ?></span>
                                        </td>
                                        <td><?php echo htmlspecialchars($evidence['collector_name']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($evidence['collection_date'])); ?></td>
                                        <td><?php echo $evidence['attachment_count']; ?> files</td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary" onclick="viewEvidence(<?php echo $evidence['id']; ?>)">
                                                <i class="bi bi-eye"></i> View
                                            </button>
                                            <button class="btn btn-sm btn-outline-secondary" onclick="viewChainOfCustody(<?php echo $evidence['id']; ?>)">
                                                <i class="bi bi-link"></i> Chain
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create Evidence Modal -->
<div class="modal fade" id="createEvidenceModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Create Evidence Record</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Evidence Type *</label>
                                <select name="evidence_type" class="form-select" required>
                                    <option value="">Select Type</option>
                                        <option value="Physical">Physical</option>
                                        <option value="Biological">Biological (blood, tissue)</option>
                                        <option value="Forensic Sample">Forensic Sample (DNA, toxicology)</option>
                                        <option value="Trace">Trace Evidence (fibers, hair)</option>
                                        <option value="Fingerprint">Fingerprint / Latent Print</option>
                                        <option value="Weapon">Weapon / Tool</option>
                                        <option value="Controlled Substance">Controlled Substance</option>
                                        <option value="Currency">Currency</option>
                                        <option value="Digital">Digital / Electronic</option>
                                        <option value="Computer Forensics">Computer Forensics (PC, HDD)</option>
                                        <option value="Mobile Device">Mobile Device (phone, tablet)</option>
                                        <option value="Network Data">Network / Server Logs</option>
                                        <option value="Document">Document</option>
                                        <option value="Photo">Photo</option>
                                        <option value="Video">Video</option>
                                        <option value="Audio">Audio</option>
                                        <option value="Clothing">Clothing / Textile</option>
                                        <option value="Toolmark">Toolmark / Impression</option>
                                        <option value="Other">Other</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Related Case</label>
                                <select name="case_id" class="form-select" onchange="updateCaseNumber(this)">
                                    <option value="">Select Case (Optional)</option>
                                    <?php foreach ($cases as $case): ?>
                                        <option value="<?php echo $case['id']; ?>" data-case-number="<?php echo htmlspecialchars($case['case_number']); ?>">
                                            <?php echo htmlspecialchars($case['case_number'] . ' - ' . $case['incident_type']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="hidden" name="case_number" id="case_number">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Item Description *</label>
                        <textarea name="item_description" class="form-control" rows="3" required></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Witness Name (if any)</label>
                        <input type="text" name="witness_name" class="form-control" placeholder="Name of witness who described the evidence">
                        <small class="text-muted">Provide the witness who described the evidence (optional)</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Witness Description</label>
                        <textarea name="witness_description" class="form-control" rows="3" placeholder="Description as given by the witness"></textarea>
                        <small class="text-muted">Record the witness's account describing the evidence (optional)</small>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Location Found</label>
                                <input type="text" name="location_found" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Collection Date *</label>
                                <input type="date" name="collection_date" class="form-control" required value="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Collection Time</label>
                                <input type="time" name="collection_time" class="form-control">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Condition</label>
                                <select name="condition" class="form-select">
                                    <option value="Excellent">Excellent</option>
                                    <option value="Good" selected>Good</option>
                                    <option value="Fair">Fair</option>
                                    <option value="Poor">Poor</option>
                                    <option value="Damaged">Damaged</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Storage Location *</label>
                                <input type="text" name="storage_location" class="form-control" required placeholder="e.g., Evidence Room A-1">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Security Level</label>
                                <select name="security_level" class="form-select">
                                    <option value="Low">Low</option>
                                    <option value="Medium" selected>Medium</option>
                                    <option value="High">High</option>
                                    <option value="Confidential">Confidential</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><strong>Upload Files (Photos, Videos, Documents)</strong></label>
                        <div class="p-3 bg-light rounded">
                            <input type="file" name="evidence_files[]" class="form-control" multiple accept="image/*,video/*,audio/*,.pdf,.doc,.docx,.txt">
                            <small class="text-muted d-block mt-2">
                                <i class="bi bi-info-circle"></i>
                                Allowed: Images, Videos, Audio, PDF, Documents<br>
                                Multiple files can be selected
                            </small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="create_evidence" class="btn btn-primary">Create Evidence Record</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Evidence Modal -->
<div class="modal fade" id="viewEvidenceModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-eye"></i> Evidence Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="evidenceDetails">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>
</div>

<!-- Chain of Custody Modal -->
<div class="modal fade" id="chainOfCustodyModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-link"></i> Chain of Custody</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="chainOfCustodyDetails">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>
</div>

<script>
function updateCaseNumber(select) {
    const selectedOption = select.options[select.selectedIndex];
    const caseNumber = selectedOption.getAttribute('data-case-number');
    document.getElementById('case_number').value = caseNumber || '';
}

function viewEvidence(evidenceId) {
    // Load evidence details via AJAX
    fetch(`evidence_ajax.php?action=view&id=${evidenceId}`)
        .then(response => response.text())
        .then(data => {
            document.getElementById('evidenceDetails').innerHTML = data;
            new bootstrap.Modal(document.getElementById('viewEvidenceModal')).show();
        });
}

function viewChainOfCustody(evidenceId) {
    // Load chain of custody via AJAX
    fetch(`evidence_ajax.php?action=chain&id=${evidenceId}`)
        .then(response => response.text())
        .then(data => {
            document.getElementById('chainOfCustodyDetails').innerHTML = data;
            new bootstrap.Modal(document.getElementById('chainOfCustodyModal')).show();
        });
}

function showUpdateStatusForm(evidenceId) {
    document.getElementById(`updateStatusForm_${evidenceId}`).style.display = 'block';
}

function hideUpdateStatusForm(evidenceId) {
    document.getElementById(`updateStatusForm_${evidenceId}`).style.display = 'none';
}

function showAddCustodyForm(evidenceId) {
    document.getElementById(`addCustodyForm_${evidenceId}`).style.display = 'block';
}

function hideAddCustodyForm(evidenceId) {
    document.getElementById(`addCustodyForm_${evidenceId}`).style.display = 'none';
}
</script>

<?php include '../includes/footer.php'; ?>