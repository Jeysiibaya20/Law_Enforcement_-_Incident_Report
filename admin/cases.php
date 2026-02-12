<?php
require_once 'admin_auth.php';
require_once '../modules/CaseAssign.php';

$base_url = '../';
$page_title = 'Case Management';
require_once '../includes/header.php';
require_once '../includes/navbar.php';

// Handle form submissions
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_case'])) {
        // Create new case assignment
        $case_data = [
            'incident_type' => $_POST['incident_type'] ?? '',
            'complainant_name' => $_POST['complainant_name'] ?? '',
            'respondent_name' => $_POST['respondent_name'] ?? '',
            'location' => $_POST['location'] ?? '',
            'incident_date' => $_POST['incident_date'] ?? '',
            'incident_time' => $_POST['incident_time'] ?? '',
            'description' => $_POST['description'] ?? '',
            'priority' => $_POST['priority'] ?? 'Medium',
            'assigned_by' => $_SESSION['user_id'],
            'assigned_to' => $_POST['assigned_to'] ?? null,
            'barangay_chairperson_id' => $_POST['barangay_chairperson_id'] ?? null
        ];

        $result = createCaseAssignment($case_data);
        if ($result['success']) {
            $message = "Case created successfully with number: " . $result['case_number'];
            $message_type = 'success';
        } else {
            $message = "Error creating case: " . $result['error'];
            $message_type = 'danger';
        }
    } elseif (isset($_POST['update_status'])) {
        // Update case status
        $result = updateCaseStatus($_POST['case_id'], $_POST['new_status'], $_SESSION['user_id'], $_POST['status_notes'] ?? '');
        if ($result['success']) {
            $message = "Case status updated successfully";
            $message_type = 'success';
        } else {
            $message = "Error updating case status: " . $result['error'];
            $message_type = 'danger';
        }
    } elseif (isset($_POST['add_followup'])) {
        // Add follow-up action
        $result = addFollowUpAction($_POST['case_id'], $_POST['followup_action'], $_SESSION['user_id']);
        if ($result['success']) {
            $message = "Follow-up action added successfully";
            $message_type = 'success';
        } else {
            $message = "Error adding follow-up: " . $result['error'];
            $message_type = 'danger';
        }
    } elseif (isset($_POST['reassign_case'])) {
        // Reassign case
        $result = reassignCase($_POST['case_id'], $_POST['new_officer'], $_SESSION['user_id'], $_POST['reassign_reason'] ?? '');
        if ($result['success']) {
            $message = "Case reassigned successfully";
            $message_type = 'success';
        } else {
            $message = "Error reassigning case: " . $result['error'];
            $message_type = 'danger';
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'delete_case') {
        // Delete case
        try {
            $case_id = intval($_POST['case_id'] ?? 0);
            
            if (!$case_id) {
                throw new Exception('Invalid case ID');
            }
            
            // First, delete any attachments associated with this case
            require_once '../includes/attachment_manager.php';
            $attachment_manager = new AttachmentManager($pdo);
            $attachments = $attachment_manager->getAttachments('case', $case_id);
            
            foreach ($attachments as $attachment) {
                try {
                    $attachment_manager->deleteAttachment($attachment['id'], $_SESSION['user_id']);
                } catch (Exception $e) {
                    // Log but continue with case deletion
                    error_log("Failed to delete attachment {$attachment['id']}: " . $e->getMessage());
                }
            }
            
            // Delete the case
            $stmt = $pdo->prepare("DELETE FROM case_assignments WHERE id = ?");
            $success = $stmt->execute([$case_id]);
            
            if ($success && $stmt->rowCount() > 0) {
                $message = "✅ Case deleted successfully!";
                $message_type = 'success';
            } else {
                throw new Exception('Failed to delete case or case not found');
            }
            
        } catch (Exception $e) {
            $message = "❌ Error deleting case: " . $e->getMessage();
            $message_type = 'danger';
        }
    }
}

// Get filter parameters
$filters = [];
if (isset($_GET['status']) && !empty($_GET['status'])) {
    $filters['status'] = $_GET['status'];
}
if (isset($_GET['priority']) && !empty($_GET['priority'])) {
    $filters['priority'] = $_GET['priority'];
}
if (isset($_GET['assigned_to']) && !empty($_GET['assigned_to'])) {
    $filters['assigned_to'] = $_GET['assigned_to'];
}
if (isset($_GET['date_from']) && !empty($_GET['date_from'])) {
    $filters['date_from'] = $_GET['date_from'];
}
if (isset($_GET['date_to']) && !empty($_GET['date_to'])) {
    $filters['date_to'] = $_GET['date_to'];
}

// Get case assignments
$cases = getCaseAssignments($filters);

// Get available officers
$available_officers = getAvailableBCPCOfficers();
$all_officers = getAllBCPCOfficers();

// Get available barangay officials
$barangay_check = $pdo->query("SHOW TABLES LIKE 'barangay_officials'");
$barangay_officials = [];
if ($barangay_check->rowCount() > 0) {
    $barangay_officials = $pdo->query("
        SELECT b.*, s.fullname, s.emailadd 
        FROM barangay_officials b
        LEFT JOIN signup s ON b.user_id = s.user_id
        WHERE b.is_active = 1
        ORDER BY b.barangay_name, s.fullname
    ")->fetchAll(PDO::FETCH_ASSOC);
}

// Get barangay chairpersons (users with role that can assign cases)
try {
    $chairpersons = $pdo->query("SELECT user_id, fullname FROM signup WHERE role IN ('Barangay Chairperson', 'Admin') ORDER BY fullname")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $chairpersons = [];
}

// Get case statistics
$stats = getCaseStatistics();
?>

<div class="main-content">
    <div class="content-container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h2">Case Management</h1>
            <div>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createCaseModal">
                    <i class="bi bi-plus-circle"></i> Create New Case
                </button>
            </div>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?= $message_type ?> alert-dismissible">
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="row row-cols-1 row-cols-md-4 g-3 mb-4">
            <div class="col">
                <div class="card border-start border-primary border-4 h-100">
                    <div class="card-body">
                        <h6 class="text-muted mb-2">Total Cases</h6>
                        <div class="h3 text-primary"><?= $stats['total_cases'] ?? 0 ?></div>
                        <small class="text-muted">All cases</small>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card border-start border-warning border-4 h-100">
                    <div class="card-body">
                        <h6 class="text-muted mb-2">New Cases</h6>
                        <div class="h3 text-warning"><?= $stats['by_status']['New'] ?? 0 ?></div>
                        <small class="text-muted">Awaiting assignment</small>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card border-start border-info border-4 h-100">
                    <div class="card-body">
                        <h6 class="text-muted mb-2">Ongoing Cases</h6>
                        <div class="h3 text-info"><?= $stats['by_status']['Ongoing'] ?? 0 ?></div>
                        <small class="text-muted">In progress</small>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card border-start border-success border-4 h-100">
                    <div class="card-body">
                        <h6 class="text-muted mb-2">Active Officers</h6>
                        <div class="h3 text-success"><?= $stats['active_officers'] ?? 0 ?></div>
                        <small class="text-muted">Available for assignment</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Filters</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-2">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="">All Status</option>
                            <option value="New" <?= (isset($_GET['status']) && $_GET['status'] == 'New') ? 'selected' : '' ?>>New</option>
                            <option value="Ongoing" <?= (isset($_GET['status']) && $_GET['status'] == 'Ongoing') ? 'selected' : '' ?>>Ongoing</option>
                            <option value="Resolved" <?= (isset($_GET['status']) && $_GET['status'] == 'Resolved') ? 'selected' : '' ?>>Resolved</option>
                            <option value="Closed" <?= (isset($_GET['status']) && $_GET['status'] == 'Closed') ? 'selected' : '' ?>>Closed</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Priority</label>
                        <select name="priority" class="form-select">
                            <option value="">All Priority</option>
                            <option value="High" <?= (isset($_GET['priority']) && $_GET['priority'] == 'High') ? 'selected' : '' ?>>High</option>
                            <option value="Medium" <?= (isset($_GET['priority']) && $_GET['priority'] == 'Medium') ? 'selected' : '' ?>>Medium</option>
                            <option value="Low" <?= (isset($_GET['priority']) && $_GET['priority'] == 'Low') ? 'selected' : '' ?>>Low</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Assigned To</label>
                        <select name="assigned_to" class="form-select">
                            <option value="">All Officers</option>
                            <?php foreach ($all_officers as $officer): ?>
                                <option value="<?= $officer['user_id'] ?>" <?= (isset($_GET['assigned_to']) && $_GET['assigned_to'] == $officer['user_id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($officer['fullname']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">From Date</label>
                        <input type="date" name="date_from" class="form-control" value="<?= $_GET['date_from'] ?? '' ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">To Date</label>
                        <input type="date" name="date_to" class="form-control" value="<?= $_GET['date_to'] ?? '' ?>">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2">Filter</button>
                        <a href="cases.php" class="btn btn-secondary">Clear</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Cases Table -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Case Assignments</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Case Number</th>
                                <th>Incident Type</th>
                                <th>Complainant</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Assigned To</th>
                                <th>Assigned By</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($cases)): ?>
                                <tr>
                                    <td colspan="9" class="text-center text-muted">No cases found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($cases as $case): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($case['case_number']) ?></strong></td>
                                        <td><?= htmlspecialchars($case['incident_type']) ?></td>
                                        <td><?= htmlspecialchars($case['complainant_name']) ?></td>
                                        <td>
                                            <?php
                                            $priority_class = match($case['priority']) {
                                                'High' => 'danger',
                                                'Medium' => 'warning',
                                                'Low' => 'success',
                                                default => 'secondary'
                                            };
                                            ?>
                                            <span class="badge bg-<?= $priority_class ?>"><?= htmlspecialchars($case['priority']) ?></span>
                                        </td>
                                        <td>
                                            <?php
                                            $status_class = match($case['status']) {
                                                'New' => 'info',
                                                'Ongoing' => 'primary',
                                                'Resolved' => 'success',
                                                'Closed' => 'secondary',
                                                default => 'secondary'
                                            };
                                            ?>
                                            <span class="badge bg-<?= $status_class ?>"><?= htmlspecialchars($case['status']) ?></span>
                                        </td>
                                        <td><?= htmlspecialchars($case['assigned_to_name'] ?? 'Unassigned') ?></td>
                                        <td><?= htmlspecialchars($case['assigned_by_name']) ?></td>
                                        <td><?= date('M d, Y', strtotime($case['created_at'])) ?></td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="viewCaseDetails(<?= $case['id'] ?>)">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="updateCaseStatus(<?= $case['id'] ?>, '<?= $case['status'] ?>')">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-info" onclick="addFollowUp(<?= $case['id'] ?>)">
                                                    <i class="bi bi-plus-circle"></i>
                                                </button>
                                                <?php if ($case['assigned_to']): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-warning" onclick="reassignCase(<?= $case['id'] ?>)">
                                                        <i class="bi bi-arrow-left-right"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteCase(<?= $case['id'] ?>, '<?= htmlspecialchars($case['case_number']) ?>')">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create Case Modal -->
<div class="modal fade" id="createCaseModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create New Case Assignment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Incident Type *</label>
                            <input type="text" name="incident_type" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Priority</label>
                            <select name="priority" class="form-select">
                                <option value="Low">Low</option>
                                <option value="Medium" selected>Medium</option>
                                <option value="High">High</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Complainant Name *</label>
                            <input type="text" name="complainant_name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Respondent Name</label>
                            <input type="text" name="respondent_name" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Incident Date *</label>
                            <input type="date" name="incident_date" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Incident Time</label>
                            <input type="time" name="incident_time" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Location</label>
                            <input type="text" name="location" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description *</label>
                            <textarea name="description" class="form-control" rows="3" required></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Assign to Officer</label>
                            <select name="assigned_to" class="form-select">
                                <option value="">Select Officer</option>
                                <optgroup label="BCPC Officers">
                                    <?php foreach ($available_officers as $officer): ?>
                                        <option value="<?= $officer['user_id'] ?>">
                                            <?= htmlspecialchars($officer['fullname']) ?> (<?= htmlspecialchars($officer['barangay']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <?php if (!empty($barangay_officials)): ?>
                                <optgroup label="Barangay Officials">
                                    <?php foreach ($barangay_officials as $official): ?>
                                        <option value="<?= $official['user_id'] ?>">
                                            <?= htmlspecialchars($official['fullname']) ?> - <?= htmlspecialchars($official['position']) ?> (<?= htmlspecialchars($official['barangay_name']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Barangay Chairperson</label>
                            <select name="barangay_chairperson_id" class="form-select">
                                <option value="">Select Chairperson</option>
                                <?php foreach ($chairpersons as $chair): ?>
                                    <option value="<?= $chair['user_id'] ?>">
                                        <?= htmlspecialchars($chair['fullname']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="create_case" class="btn btn-primary">Create Case</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Case Details Modal -->
<div class="modal fade" id="caseDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Case Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="caseDetailsContent">
                <!-- Content will be loaded dynamically -->
            </div>
        </div>
    </div>
</div>

<!-- Update Status Modal -->
<div class="modal fade" id="updateStatusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Case Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="case_id" id="status_case_id">
                    <div class="mb-3">
                        <label class="form-label">New Status</label>
                        <select name="new_status" class="form-select" required>
                            <option value="New">New</option>
                            <option value="Ongoing">Ongoing</option>
                            <option value="Resolved">Resolved</option>
                            <option value="Closed">Closed</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes (Optional)</label>
                        <textarea name="status_notes" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_status" class="btn btn-primary">Update Status</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Follow-up Modal -->
<div class="modal fade" id="followUpModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Follow-up Action</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="case_id" id="followup_case_id">
                    <div class="mb-3">
                        <label class="form-label">Follow-up Action *</label>
                        <textarea name="followup_action" class="form-control" rows="4" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_followup" class="btn btn-primary">Add Follow-up</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reassign Case Modal -->
<div class="modal fade" id="reassignModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reassign Case</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="case_id" id="reassign_case_id">
                    <div class="mb-3">
                        <label class="form-label">New Officer *</label>
                        <select name="new_officer" class="form-select" required>
                            <option value="">Select Officer</option>
                            <optgroup label="BCPC Officers">
                                <?php foreach ($all_officers as $officer): ?>
                                    <option value="<?= $officer['user_id'] ?>">
                                        <?= htmlspecialchars($officer['fullname']) ?> (<?= htmlspecialchars($officer['barangay']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                            <?php if (!empty($barangay_officials)): ?>
                            <optgroup label="Barangay Officials">
                                <?php foreach ($barangay_officials as $official): ?>
                                    <option value="<?= $official['user_id'] ?>">
                                        <?= htmlspecialchars($official['fullname']) ?> - <?= htmlspecialchars($official['position']) ?> (<?= htmlspecialchars($official['barangay_name']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reason for Reassignment</label>
                        <textarea name="reassign_reason" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="reassign_case" class="btn btn-primary">Reassign Case</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function viewCaseDetails(caseId) {
    // Open case details in a new page
    window.location.href = `../modules/case_details.php?case_id=${caseId}`;
}

function updateCaseStatus(caseId, currentStatus) {
    document.getElementById('status_case_id').value = caseId;
    // Pre-select current status
    const statusSelect = document.querySelector('select[name="new_status"]');
    if (statusSelect) {
        statusSelect.value = currentStatus;
    }
    new bootstrap.Modal(document.getElementById('updateStatusModal')).show();
}

function addFollowUp(caseId) {
    document.getElementById('followup_case_id').value = caseId;
    new bootstrap.Modal(document.getElementById('followUpModal')).show();
}

function reassignCase(caseId) {
    document.getElementById('reassign_case_id').value = caseId;
    new bootstrap.Modal(document.getElementById('reassignModal')).show();
}

function deleteCase(caseId, caseNumber) {
    if (confirm(`Are you sure you want to delete Case ${caseNumber}?\n\nThis action cannot be undone and will permanently remove the case and all associated data.`)) {
        // Create a form to submit the delete request
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'cases.php';
        
        // Add hidden inputs
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'delete_case';
        form.appendChild(actionInput);
        
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'case_id';
        idInput.value = caseId;
        form.appendChild(idInput);
        
        // Submit the form
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>
