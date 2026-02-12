<?php
session_start();
require '../config/db_connect.php';
require 'helpers.php';
require '../includes/attachment_manager.php';

$base_url = '../';
$page_title = 'View Blotter';
require '../includes/header.php';
require '../includes/navbar.php';

// Get blotter ID from URL parameter
$blotter_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$blotter_id) {
    echo '<div class="alert alert-danger">Invalid blotter ID</div>';
    require '../includes/footer.php';
    exit;
}

// Fetch blotter details
$sql = "SELECT * FROM blotters WHERE id = :id";
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $blotter_id]);
    $blotter = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$blotter) {
        echo '<div class="alert alert-danger">Blotter not found</div>';
        require '../includes/footer.php';
        exit;
    }
} catch (Exception $e) {
    echo '<div class="alert alert-danger">Error fetching blotter: ' . htmlspecialchars($e->getMessage()) . '</div>';
    require '../includes/footer.php';
    exit;
}

// Fetch attachments
$attachment_manager = new AttachmentManager($pdo);
$attachments = $attachment_manager->getAttachments('blotter', $blotter_id);

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
        $attachments = $attachment_manager->getAttachments('blotter', $blotter_id);
        
        // Set success message
        $success_message = $deleted_count === 1 
            ? "Attachment deleted successfully." 
            : "{$deleted_count} attachments deleted successfully.";
    }
}

// Fetch officer name if assigned
$officer_name = '';
if (!empty($blotter['officer_id'])) {
    $stmt = $pdo->prepare("SELECT fullname FROM signup WHERE user_id = :id");
    $stmt->execute([':id' => $blotter['officer_id']]);
    $officer = $stmt->fetch(PDO::FETCH_ASSOC);
    $officer_name = $officer ? $officer['fullname'] : 'N/A';
}

// Check if in print mode
$isPrintMode = isset($_GET['print']) && $_GET['print'] == 1;
?>

<div class="main-content">
<div class="content-container">

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2">Blotter Details</h1>
    <div class="action-buttons">
        <button class="btn btn-primary" onclick="window.print()">
            <i class="bi bi-printer"></i> Print
        </button>
        <a href="Blotter.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Back
        </a>
    </div>
</div>

<?php if (!empty($_SESSION['flash'])): $f = $_SESSION['flash']; ?>
    <div class="alert alert-<?= htmlspecialchars($f['type']) ?> alert-dismissible">
        <?= htmlspecialchars($f['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php unset($_SESSION['flash']); endif; ?>

<?php if (isset($success_message)): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="bi bi-check-circle-fill me-2"></i>
    <?= htmlspecialchars($success_message) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Main Details Card -->
<div class="card mb-4 blotter-print-container">
    <!-- Letter Header with Logo -->
    <div class="letter-header">
        <img src="../assets/css/favicon.png" alt="Alertara Favicon" class="favicon-top-right">
        <div class="letter-header-logo">
            <img src="../assets/css/tara.png" alt="Alertara Logo" style="height: 60px;">
        </div>
        <h3>ALERTARA PH</h3>
        <p>Law Enforcement and Incident Report System</p>
        <p>Barangay Official Blotter Record</p>
    </div>

    <div class="card-header bg-light">
        <h5>Blotter No: <span class="fw-bold text-primary"><?= htmlspecialchars($blotter['blotter_no']) ?></span></h5>
    </div>
    <div class="card-body">
        <!-- Row 1: Complainant and Respondent -->
        <div class="blotter-row">
            <div class="blotter-field">
                <h6>Complainant Name</h6>
                <p><?= htmlspecialchars($blotter['complainant_name']) ?></p>
            </div>
            <div class="blotter-field">
                <h6>Respondent</h6>
                <p>
                    <?= htmlspecialchars($blotter['respondent_name'] ?? 'N/A') ?>
                    <?php if (!empty($blotter['respondent_contact'])): ?>
                        <br><small class="text-muted">Contact: <?= htmlspecialchars($blotter['respondent_contact']) ?></small>
                    <?php endif; ?>
                    <?php if (!empty($blotter['respondent_email'])): ?>
                        <br><small class="text-muted">Email: <?= htmlspecialchars($blotter['respondent_email']) ?></small>
                    <?php endif; ?>
                    <?php if (!empty($blotter['respondent_address'])): ?>
                        <br><small class="text-muted">Address: <?= htmlspecialchars($blotter['respondent_address']) ?></small>
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <!-- Hearing Info (if scheduled) -->
        <?php if (!empty($blotter['hearing_date']) || !empty($blotter['hearing_time']) || !empty($blotter['hearing_location'])): ?>
        <div class="blotter-row">
            <div class="blotter-field">
                <h6>Hearing Date</h6>
                <p><?= $blotter['hearing_date'] ? date('M d, Y', strtotime($blotter['hearing_date'])) : 'TBA' ?></p>
            </div>
            <div class="blotter-field">
                <h6>Hearing Time</h6>
                <p><?= !empty($blotter['hearing_time']) ? substr($blotter['hearing_time'], 0, 5) : 'TBA' ?></p>
            </div>
            <div class="blotter-field">
                <h6>Hearing Location</h6>
                <p><?= htmlspecialchars($blotter['hearing_location'] ?? 'TBA') ?></p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Row 2: Incident Type and Location -->
        <div class="blotter-row">
            <div class="blotter-field">
                <h6>Incident Type</h6>
                <p><?= htmlspecialchars($blotter['incident_type'] ?? 'N/A') ?></p>
            </div>
            <div class="blotter-field">
                <h6>Location</h6>
                <p><?= htmlspecialchars($blotter['location'] ?? 'N/A') ?></p>
            </div>
        </div>

        <!-- Row 3: Date, Time, Priority, Status -->
        <div class="blotter-row-4col">
            <div class="blotter-field">
                <h6>Date</h6>
                <p><?= $blotter['incident_date'] ? date('M d, Y', strtotime($blotter['incident_date'])) : 'N/A' ?></p>
            </div>
            <div class="blotter-field">
                <h6>Time</h6>
                <p><?= $blotter['incident_time'] ? substr($blotter['incident_time'], 0, 5) : 'N/A' ?></p>
            </div>
            <div class="blotter-field">
                <h6>Priority</h6>
                <p><?= render_priority_badge($blotter['priority']) ?></p>
            </div>
            <div class="blotter-field">
                <h6>Status</h6>
                <p><?= render_status_badge($blotter['status']) ?></p>
            </div>
        </div>

        <!-- Row 4: Assigned Officer and Created Date -->
        <div class="blotter-row">
            <?php $userRole = strtolower($_SESSION['role'] ?? ''); ?>
            <?php if ($userRole === 'admin'): ?>
            <div class="blotter-field">
                <h6>Assigned Officer</h6>
                <p><?= htmlspecialchars($officer_name) ?></p>
            </div>
            <?php endif; ?>
            <div class="blotter-field">
                <h6>Created Date</h6>
                <p><?= $blotter['created_at'] ? date('M d, Y H:i', strtotime($blotter['created_at'])) : 'N/A' ?></p>
            </div>
        </div>

        <!-- Description Section -->
        <div style="margin-top: 16px;">
            <h6>Description</h6>
            <div class="bg-light" style="margin-top: 8px;">
                <p style="white-space: pre-wrap; word-wrap: break-word; line-height: 1.6;">
                    <?= htmlspecialchars($blotter['description']) ?>
                </p>
            </div>
        </div>

        <!-- Attachments Section -->
        <?php if (!empty($attachments)): ?>
        <div style="margin-top: 16px;">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="mb-0">Attachments</h6>
                <div class="d-flex gap-2">
                    <button type="button" id="selectAllBtn" class="btn btn-outline-secondary btn-sm" onclick="toggleSelectAll()">
                        <i class="bi bi-check-square"></i> Select All
                    </button>
                    <button type="button" id="deleteSelectedBtn" class="btn btn-outline-danger btn-sm d-none" onclick="deleteSelectedAttachments()">
                        <i class="bi bi-trash"></i> Delete Selected
                    </button>
                </div>
            </div>
            <form id="deleteAttachmentsForm" method="POST" action="blotter_view.php?id=<?= $blotter_id ?>">
                <input type="hidden" name="action" value="delete_attachments">
                <div class="bg-light" style="margin-top: 8px; padding: 12px;">
                    <?php foreach ($attachments as $attachment): ?>
                    <div class="d-flex align-items-center mb-3 attachment-item">
                        <div class="form-check me-3">
                            <input class="form-check-input attachment-checkbox" type="checkbox" 
                                   name="attachment_ids[]" value="<?= $attachment['id'] ?>" 
                                   id="attachment_<?= $attachment['id'] ?>">
                        </div>
                        <i class="bi <?= AttachmentManager::getFileIcon($attachment['mime_type']) ?> me-2"></i>
                        <div class="flex-grow-1">
                            <label for="attachment_<?= $attachment['id'] ?>" class="cursor-pointer">
                                <a href="download_attachment.php?type=blotter&file=<?= htmlspecialchars($attachment['stored_filename']) ?>&action=view" 
                                   target="_blank" class="text-decoration-none">
                                    <?= htmlspecialchars($attachment['original_filename']) ?>
                                </a>
                            </label>
                            <?php if (!empty($attachment['description'])): ?>
                                <br><small class="text-muted"><?= htmlspecialchars($attachment['description']) ?></small>
                            <?php endif; ?>
                            <br><small class="text-muted">
                                <?= AttachmentManager::formatFileSize($attachment['file_size']) ?> • 
                                Uploaded by <?= htmlspecialchars($attachment['uploaded_by_name']) ?> on 
                                <?= date('M d, Y H:i', strtotime($attachment['uploaded_at'])) ?>
                            </small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <!-- Signature Section -->
        <div class="signature-section">
            <h6 style="margin-bottom: 20px; text-align: center;">AUTHORIZED SIGNATURES</h6>
            <div class="row">
                <div class="col-md-4 signature-block">
                    <div style="text-align: center;">
                        <div class="signature-line"></div>
                        <div class="signature-name"><?= htmlspecialchars($_SESSION['fullname'] ?? 'Report By') ?></div>
                        <div class="signature-title">Reporting Officer/User</div>
                        <small class="text-muted">Date: <?= date('M d, Y') ?></small>
                    </div>
                </div>
                <div class="col-md-4 signature-block">
                    <div style="text-align: center;">
                        <div class="signature-line"></div>
                        <div class="signature-name">____________________</div>
                        <div class="signature-title">Barangay Captain/Admin</div>
                        <small class="text-muted">Date: _______________</small>
                    </div>
                </div>
                <div class="col-md-4 signature-block">
                    <div style="text-align: center;">
                        <div class="signature-line"></div>
                        <div class="signature-name">____________________</div>
                        <div class="signature-title">Assistant/Authorized By</div>
                        <small class="text-muted">Date: _______________</small>
                    </div>
                </div>
            </div>
            <div class="signature-date">
                <strong>Record Date: <?= $blotter['created_at'] ? date('F d, Y \\a\\t h:i A', strtotime($blotter['created_at'])) : 'N/A' ?></strong>
            </div>
        </div>
    </div>
</div>

<!-- Action Buttons -->
<div class="d-flex gap-2 justify-content-center mb-4">
    <a href="Blotter.php" class="btn btn-secondary">
        <i class="bi bi-list"></i> Back to List
    </a>
</div>

</div>
</div>

<script>
function editBlotter(id) {
    // Redirect to Blotter.php with edit mode (you can enhance this)
    window.location.href = 'Blotter.php?edit=' + id;
}

function archiveBlotter(id) {
    if (confirm('Are you sure you want to archive this blotter?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'Blotter.php';
        form.innerHTML = `
            <input type="hidden" name="action" value="archive">
            <input type="hidden" name="id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

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

<?php require '../includes/footer.php'; ?>
