<?php
/**
 * Case Details Page
 * Displays full case information with timeline, updates, and actions
 */

require_once dirname(__DIR__) . '/config/db_connect.php';
require_once dirname(__DIR__) . '/includes/case_management.php';
require_once dirname(__DIR__) . '/includes/suspect_witness_management.php';
require_once dirname(__DIR__) . '/includes/attachment_manager.php';

// Get case ID from query parameter
$case_id = $_GET['case_id'] ?? null;

if (!$case_id) {
    http_response_code(404);
    echo "Case not found";
    exit;
}

try {
    // Get case details
    $stmt = $pdo->prepare("
        SELECT ca.*, 
               s1.fullname as assigned_by_name,
               s2.fullname as assigned_to_name,
               s3.fullname as chairperson_name
        FROM case_assignments ca
        LEFT JOIN signup s1 ON ca.assigned_by = s1.user_id
        LEFT JOIN signup s2 ON ca.assigned_to = s2.user_id
        LEFT JOIN signup s3 ON ca.barangay_chairperson_id = s3.user_id
        WHERE ca.id = ?
    ");
    $stmt->execute([$case_id]);
    $case = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$case) {
        http_response_code(404);
        echo "Case not found";
        exit;
    }
    
    // Get case timeline
    $timeline = getCaseTimeline($case_id);
    
    // Get case updates
    $updates = getCaseUpdates($case_id);
    
    // Get suspects and witnesses
    $suspects = getSuspectsByCase($case_id);
    $witnesses = getWitnessesByCase($case_id);
    
    // Get attachments
    $attachment_manager = new AttachmentManager($pdo);
    $attachments = $attachment_manager->getAttachments('case', $case_id);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo "Database error: " . htmlspecialchars($e->getMessage());
    exit;
}

// Handle attachment deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_attachments') {
    $attachment_ids = $_POST['attachment_ids'] ?? [];
    
    if (!empty($attachment_ids)) {
        $user_id = $_SESSION['user_id'] ?? null;
        $deleted_count = 0;
        
        foreach ($attachment_ids as $attachment_id) {
            try {
                $attachment_manager->deleteAttachment($attachment_id, $user_id);
                $deleted_count++;
            } catch (Exception $e) {
                // Log error but continue with other deletions
                error_log("Failed to delete attachment {$attachment_id}: " . $e->getMessage());
            }
        }
        
        // Refresh attachments list
        $attachments = $attachment_manager->getAttachments('case', $case_id);
        
        // Set success message
        $success_message = $deleted_count === 1 
            ? "Attachment deleted successfully." 
            : "{$deleted_count} attachments deleted successfully.";
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Case Details - <?= htmlspecialchars($case['case_number']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/global.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-start mb-4">
                    <div>
                        <h1 class="h2 mb-2">
                            <i class="bi bi-file-earmark-text"></i>
                            Case Details
                        </h1>
                        <p class="text-muted mb-0">Case Number: <strong><?= htmlspecialchars($case['case_number']) ?></strong></p>
                    </div>
                    <a href="../admin/cases.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Cases
                    </a>
                </div>

                <?php if (isset($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    <?= htmlspecialchars($success_message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Case Header Info -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="row g-4">
                            <div class="col-md-3">
                                <h6 class="text-muted mb-2">Status</h6>
                                <?php
                                $status_class = match($case['status']) {
                                    'New' => 'info',
                                    'Ongoing' => 'primary',
                                    'Resolved' => 'success',
                                    'Closed' => 'secondary',
                                    default => 'secondary'
                                };
                                ?>
                                <span class="badge bg-<?= $status_class ?> status-badge"><?= htmlspecialchars($case['status']) ?></span>
                            </div>
                            <div class="col-md-3">
                                <h6 class="text-muted mb-2">Priority</h6>
                                <?php
                                $priority_class = match($case['priority']) {
                                    'High' => 'danger',
                                    'Medium' => 'warning',
                                    'Low' => 'success',
                                    default => 'secondary'
                                };
                                ?>
                                <span class="badge bg-<?= $priority_class ?> priority-badge"><?= htmlspecialchars($case['priority']) ?></span>
                            </div>
                            <div class="col-md-3">
                                <h6 class="text-muted mb-2">Incident Type</h6>
                                <p class="mb-0"><?= htmlspecialchars($case['incident_type']) ?></p>
                            </div>
                            <div class="col-md-3">
                                <h6 class="text-muted mb-2">Created Date</h6>
                                <p class="mb-0"><?= date('M d, Y H:i', strtotime($case['created_at'])) ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-4">
                    <!-- Case Information -->
                    <div class="col-lg-7">
                        <div class="card mb-4">
                            <div class="card-header bg-white border-bottom">
                                <h5 class="card-title mb-0">Case Information</h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-3 mb-3">
                                    <div class="col-md-6">
                                        <h6 class="text-muted mb-1">Complainant Name</h6>
                                        <p class="mb-0"><strong><?= htmlspecialchars($case['complainant_name']) ?></strong></p>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="text-muted mb-1">Respondent Name</h6>
                                        <p class="mb-0"><strong><?= htmlspecialchars($case['respondent_name'] ?? 'Not specified') ?></strong></p>
                                    </div>
                                </div>

                                <div class="row g-3 mb-3">
                                    <div class="col-md-6">
                                        <h6 class="text-muted mb-1">Incident Date</h6>
                                        <p class="mb-0"><?= date('M d, Y', strtotime($case['incident_date'])) ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="text-muted mb-1">Incident Time</h6>
                                        <p class="mb-0"><?= $case['incident_time'] ? date('h:i A', strtotime($case['incident_time'])) : 'Not specified' ?></p>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <h6 class="text-muted mb-1">Location</h6>
                                    <p class="mb-0"><?= htmlspecialchars($case['location'] ?? 'Not specified') ?></p>
                                </div>

                                <div class="mb-0">
                                    <h6 class="text-muted mb-1">Description</h6>
                                    <p class="mb-0"><?= nl2br(htmlspecialchars($case['description'])) ?></p>
                                </div>
                            </div>
                        </div>

                        <!-- Attachments Section -->
                        <?php if (!empty($attachments)): ?>
                        <div class="card mb-4">
                            <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">Attachments (<span class="badge bg-info"><?= count($attachments) ?></span>)</h5>
                                <div class="d-flex gap-2">
                                    <button type="button" id="selectAllBtn" class="btn btn-outline-secondary btn-sm" onclick="toggleSelectAll()">
                                        <i class="bi bi-check-square"></i> Select All
                                    </button>
                                    <button type="button" id="deleteSelectedBtn" class="btn btn-outline-danger btn-sm d-none" onclick="deleteSelectedAttachments()">
                                        <i class="bi bi-trash"></i> Delete Selected
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <form id="deleteAttachmentsForm" method="POST" action="case_details.php?case_id=<?= $case_id ?>">
                                    <input type="hidden" name="action" value="delete_attachments">
                                    <div class="row g-3">
                                        <?php foreach ($attachments as $attachment): ?>
                                        <div class="col-md-6 col-lg-4">
                                            <div class="border rounded p-3 attachment-item">
                                                <div class="d-flex align-items-start">
                                                    <div class="form-check me-3">
                                                        <input class="form-check-input attachment-checkbox" type="checkbox" 
                                                               name="attachment_ids[]" value="<?= $attachment['id'] ?>" 
                                                               id="attachment_<?= $attachment['id'] ?>">
                                                    </div>
                                                    <i class="bi <?= AttachmentManager::getFileIcon($attachment['mime_type']) ?> fs-4 me-3 text-primary"></i>
                                                    <div class="flex-grow-1">
                                                        <div class="d-flex justify-content-between align-items-start gap-2 mb-1">
                                                            <label for="attachment_<?= $attachment['id'] ?>" class="text-decoration-none fw-bold cursor-pointer mb-0">
                                                                <?= htmlspecialchars($attachment['original_filename']) ?>
                                                            </label>
                                                            <?= AttachmentManager::getLevelBadge($attachment['attachment_level'] ?? '') ?>
                                                        </div>
                                                        <?php if (!empty($attachment['description'])): ?>
                                                            <p class="text-muted small mb-1"><?= htmlspecialchars($attachment['description']) ?></p>
                                                        <?php endif; ?>
                                                        <small class="text-muted d-block">
                                                            <?= AttachmentManager::formatFileSize($attachment['file_size']) ?> • 
                                                            <?= date('M d, Y H:i', strtotime($attachment['uploaded_at'])) ?>
                                                        </small>
                                                        <div class="mt-2">
                                                            <a href="download_attachment.php?type=case&file=<?= htmlspecialchars($attachment['stored_filename']) ?>&action=view" 
                                                               target="_blank" class="btn btn-sm btn-outline-primary">
                                                                <i class="bi bi-eye"></i> View
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Case Timeline -->
                        <div class="card">
                            <div class="card-header bg-white border-bottom">
                                <h5 class="card-title mb-0">Case Timeline</h5>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($timeline)): ?>
                                    <?php foreach ($timeline as $event): ?>
                                        <div class="timeline-item">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <div>
                                                    <h6 class="mb-1"><?= htmlspecialchars($event['event_type']) ?></h6>
                                                    <p class="text-muted small mb-0"><?= date('M d, Y H:i', strtotime($event['event_date'])) ?></p>
                                                </div>
                                                <?php if ($event['performed_by_name']): ?>
                                                    <small class="text-muted">by <?= htmlspecialchars($event['performed_by_name']) ?></small>
                                                <?php endif; ?>
                                            </div>
                                            <p class="mb-0"><?= nl2br(htmlspecialchars($event['event_description'])) ?></p>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="text-muted text-center py-4">No timeline events yet</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Assignment Information -->
                    <div class="col-lg-5">
                        <div class="card mb-4">
                            <div class="card-header bg-white border-bottom">
                                <h5 class="card-title mb-0">Assignment Information</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <h6 class="text-muted mb-1">Assigned By</h6>
                                    <p class="mb-0"><strong><?= htmlspecialchars($case['assigned_by_name'] ?? 'System') ?></strong></p>
                                </div>

                                <div class="mb-3">
                                    <h6 class="text-muted mb-1">Assigned To Officer</h6>
                                    <p class="mb-0">
                                        <strong><?= htmlspecialchars($case['assigned_to_name'] ?? 'Unassigned') ?></strong>
                                    </p>
                                </div>

                                <div class="mb-3">
                                    <h6 class="text-muted mb-1">Barangay Chairperson</h6>
                                    <p class="mb-0">
                                        <strong><?= htmlspecialchars($case['chairperson_name'] ?? 'Not assigned') ?></strong>
                                    </p>
                                </div>

                                <div class="mb-0">
                                    <h6 class="text-muted mb-1">Assignment Date</h6>
                                    <p class="mb-0"><?= date('M d, Y H:i', strtotime($case['assignment_date'])) ?></p>
                                </div>
                            </div>
                        </div>

                        <!-- Case Updates -->
                        <div class="card">
                            <div class="card-header bg-white border-bottom">
                                <h5 class="card-title mb-0">Updates & Actions</h5>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($updates)): ?>
                                    <?php foreach ($updates as $update): ?>
                                        <div class="mb-3 pb-3 border-bottom">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <div>
                                                    <h6 class="mb-1">
                                                        <span class="badge bg-info"><?= htmlspecialchars($update['update_type']) ?></span>
                                                    </h6>
                                                    <small class="text-muted"><?= date('M d, Y H:i', strtotime($update['updated_at'])) ?></small>
                                                </div>
                                                <?php if ($update['updated_by_name']): ?>
                                                    <small class="text-muted">by <?= htmlspecialchars($update['updated_by_name']) ?></small>
                                                <?php endif; ?>
                                            </div>
                                            <p class="small mb-0"><?= nl2br(htmlspecialchars($update['action_description'])) ?></p>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="text-muted text-center py-4">No updates yet</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Suspects Section -->
                <div class="row g-4 mt-0">
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header bg-white border-bottom">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="card-title mb-0">Suspects (<span class="badge bg-danger"><?= count($suspects) ?></span>)</h5>
                                    <a href="suspects_management.php?case_id=<?= $case_id ?>" class="btn btn-sm btn-primary">
                                        <i class="bi bi-plus-circle"></i> Add Suspect
                                    </a>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($suspects)): ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Name</th>
                                                    <th>Status</th>
                                                    <th>Contact</th>
                                                    <th>Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($suspects as $suspect): ?>
                                                    <tr>
                                                        <td>
                                                            <strong><?= htmlspecialchars($suspect['first_name'] . ' ' . $suspect['last_name']) ?></strong>
                                                            <?php if ($suspect['age']): ?>
                                                                <br><small class="text-muted">Age: <?= $suspect['age'] ?></small>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php
                                                            $status_class = match($suspect['status']) {
                                                                'Arrested' => 'danger',
                                                                'Released' => 'success',
                                                                'Deceased' => 'secondary',
                                                                default => 'warning'
                                                            };
                                                            ?>
                                                            <span class="badge bg-<?= $status_class ?>"><?= htmlspecialchars($suspect['status']) ?></span>
                                                        </td>
                                                        <td>
                                                            <small><?= htmlspecialchars($suspect['contact_number'] ?? 'N/A') ?></small>
                                                        </td>
                                                        <td>
                                                            <a href="suspects_management.php?case_id=<?= $case_id ?>&edit=<?= $suspect['id'] ?>" class="btn btn-xs btn-outline-primary">View</a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted text-center py-3">No suspects recorded yet</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Witnesses Section -->
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header bg-white border-bottom">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="card-title mb-0">Witnesses (<span class="badge bg-info"><?= count($witnesses) ?></span>)</h5>
                                    <a href="witnesses_management.php?case_id=<?= $case_id ?>" class="btn btn-sm btn-primary">
                                        <i class="bi bi-plus-circle"></i> Add Witness
                                    </a>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($witnesses)): ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Name</th>
                                                    <th>Type</th>
                                                    <th>Reliability</th>
                                                    <th>Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($witnesses as $witness): ?>
                                                    <tr>
                                                        <td>
                                                            <strong><?= htmlspecialchars($witness['first_name'] . ' ' . $witness['last_name']) ?></strong>
                                                            <?php if ($witness['age']): ?>
                                                                <br><small class="text-muted">Age: <?= $witness['age'] ?></small>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-secondary"><?= htmlspecialchars($witness['witness_type']) ?></span>
                                                        </td>
                                                        <td>
                                                            <?php
                                                            $reliability_class = match($witness['reliability']) {
                                                                'High' => 'success',
                                                                'Low' => 'warning',
                                                                default => 'info'
                                                            };
                                                            ?>
                                                            <span class="badge bg-<?= $reliability_class ?>"><?= htmlspecialchars($witness['reliability']) ?></span>
                                                        </td>
                                                        <td>
                                                            <a href="witnesses_management.php?case_id=<?= $case_id ?>&edit=<?= $witness['id'] ?>" class="btn btn-xs btn-outline-primary">View</a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted text-center py-3">No witnesses recorded yet</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    // Attachment selection and deletion functionality
    function toggleSelectAll() {
        const checkboxes = document.querySelectorAll('.attachment-checkbox');
        const selectAllBtn = document.getElementById('selectAllBtn');
        const deleteBtn = document.getElementById('deleteSelectedBtn');
        
        const allChecked = Array.from(checkboxes).every(cb => cb.checked);
        
        checkboxes.forEach(cb => {
            cb.checked = !allChecked;
        });
        
        updateDeleteButtonVisibility();
        updateSelectAllButtonText();
    }
    
    function updateDeleteButtonVisibility() {
        const checkboxes = document.querySelectorAll('.attachment-checkbox');
        const deleteBtn = document.getElementById('deleteSelectedBtn');
        const checkedBoxes = document.querySelectorAll('.attachment-checkbox:checked');
        
        if (checkedBoxes.length > 0) {
            deleteBtn.classList.remove('d-none');
        } else {
            deleteBtn.classList.add('d-none');
        }
    }
    
    function updateSelectAllButtonText() {
        const checkboxes = document.querySelectorAll('.attachment-checkbox');
        const selectAllBtn = document.getElementById('selectAllBtn');
        const checkedBoxes = document.querySelectorAll('.attachment-checkbox:checked');
        
        if (checkedBoxes.length === checkboxes.length && checkboxes.length > 0) {
            selectAllBtn.innerHTML = '<i class="bi bi-square"></i> Deselect All';
        } else {
            selectAllBtn.innerHTML = '<i class="bi bi-check-square"></i> Select All';
        }
    }
    
    function deleteSelectedAttachments() {
        const checkedBoxes = document.querySelectorAll('.attachment-checkbox:checked');
        
        if (checkedBoxes.length === 0) {
            alert('Please select attachments to delete.');
            return;
        }
        
        const count = checkedBoxes.length;
        const confirmMessage = count === 1 
            ? 'Are you sure you want to delete this attachment?'
            : `Are you sure you want to delete ${count} attachments?`;
        
        if (confirm(confirmMessage)) {
            document.getElementById('deleteAttachmentsForm').submit();
        }
    }
    
    // Add event listeners to checkboxes
    document.addEventListener('DOMContentLoaded', function() {
        const checkboxes = document.querySelectorAll('.attachment-checkbox');
        checkboxes.forEach(cb => {
            cb.addEventListener('change', function() {
                updateDeleteButtonVisibility();
                updateSelectAllButtonText();
            });
        });
        
        // Make attachment items clickable (except for the checkbox area)
        const attachmentItems = document.querySelectorAll('.attachment-item');
        attachmentItems.forEach(item => {
            item.addEventListener('click', function(e) {
                // Don't toggle if clicking on checkbox or its label
                if (e.target.type !== 'checkbox' && e.target.tagName !== 'LABEL') {
                    const checkbox = item.querySelector('.attachment-checkbox');
                    if (checkbox) {
                        checkbox.checked = !checkbox.checked;
                        updateDeleteButtonVisibility();
                        updateSelectAllButtonText();
                    }
                }
            });
        });
    });
    </script>
</body>
</html>
