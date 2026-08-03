<?php
session_start();
require '../config/db_connect.php';
require 'helpers.php';
require '../includes/attachment_manager.php';
require 'DescriptionTranslationService.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

// Get blotter ID from URL
$blotter_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$blotter_id) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Invalid blotter ID.'];
    header('Location: Blotter.php');
    exit;
}

// Fetch blotter details
$stmt = $pdo->prepare("SELECT * FROM blotters WHERE id = :id");
$stmt->execute([':id' => $blotter_id]);
$blotter = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$blotter) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Blotter not found.'];
    header('Location: Blotter.php');
    exit;
}

// Check permissions: only Admins or assigned officer can edit
$userRole = strtolower($_SESSION['role'] ?? '');
$currentUserId = $_SESSION['user_id'] ?? null;
$canEdit = false;

if ($userRole === 'admin') {
    $canEdit = true;
} elseif ($currentUserId && intval($blotter['officer_id']) === intval($currentUserId)) {
    $canEdit = true;
}

if (!$canEdit) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'You do not have permission to edit this blotter.'];
    header('Location: Blotter.php');
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
        $attachments = $attachment_manager->getAttachments('blotter', $blotter_id);

        // Set success message
        $success_message = $deleted_count === 1
            ? "Attachment deleted successfully."
            : "{$deleted_count} attachments deleted successfully.";
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    $complainant = $_POST['complainant_name'] ?? '';
    $respondent = $_POST['respondent_name'] ?? '';
    $respondent_contact = trim($_POST['respondent_contact'] ?? '');
    $respondent_email = trim($_POST['respondent_email'] ?? '');
    $respondent_address = trim($_POST['respondent_address'] ?? '');
    $incident_type = $_POST['incident_type'] ?? '';
    $incident_date = $_POST['incident_date'] ?? null;
    $incident_time = $_POST['incident_time'] ?? null;
    $location = $_POST['location'] ?? '';
    $description = $_POST['description'] ?? '';
    $description = trim($description);
    $priority = $_POST['priority'] ?? 'Medium';
    $status = $_POST['status'] ?? 'Pending';
    $officer_id = !empty($_POST['officer_id']) ? intval($_POST['officer_id']) : null;

    if (empty($respondent_address)) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Respondent home address is required.'];
        header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . $blotter_id);
        exit;
    }

    // Hearing schedule (optional)
    $hearing_date = $_POST['hearing_date'] ?? null;
    $hearing_time = $_POST['hearing_time'] ?? null;
    $hearing_location = trim($_POST['hearing_location'] ?? '');

    try {
        $translationColumns = [
            'description_english' => 'TEXT NULL AFTER description',
            'description_language' => 'VARCHAR(10) NULL AFTER description_english',
            'description_translation_provider' => 'VARCHAR(30) NULL AFTER description_language',
        ];
        foreach ($translationColumns as $column => $definition) {
            $columnCheck = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'blotters' AND COLUMN_NAME = ?");
            $columnCheck->execute([$column]);
            if ((int)$columnCheck->fetchColumn() === 0) {
                $pdo->exec("ALTER TABLE blotters ADD COLUMN {$column} {$definition}");
            }
        }
        $translation = (new DescriptionTranslationService($env ?? []))->translateToEnglish($description);

        // Keep old values for change detection
        $old_hearing_date = $blotter['hearing_date'] ?? null;
        $old_hearing_time = $blotter['hearing_time'] ?? null;

        $sql = "UPDATE blotters SET complainant_name = :complainant, respondent_name = :respondent, respondent_contact = :respondent_contact, respondent_email = :respondent_email, respondent_address = :respondent_address, incident_type = :incident_type, incident_date = :incident_date, incident_time = :incident_time, location = :location, description = :description, description_english = :description_english, description_language = :description_language, description_translation_provider = :description_translation_provider, priority = :priority, status = :status, officer_id = :officer_id, hearing_date = :hearing_date, hearing_time = :hearing_time, hearing_location = :hearing_location, updated_at = NOW() WHERE id = :id";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
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
            ':description_english' => $translation['translation'],
            ':description_language' => $translation['language'],
            ':description_translation_provider' => $translation['provider'],
            ':priority' => $priority,
            ':status' => $status,
            ':officer_id' => $officer_id,
            ':hearing_date' => $hearing_date,
            ':hearing_time' => $hearing_time,
            ':hearing_location' => $hearing_location,
            ':id' => $blotter_id
        ]);

        // Handle file uploads for new attachments
        handleFileUpload('blotter', $blotter_id, $_SESSION['user_id'] ?? null);

        // Notifications: if hearing scheduled/changed, notify complainant
        if ((!empty($hearing_date) || !empty($hearing_time)) && ($hearing_date !== $old_hearing_date || $hearing_time !== $old_hearing_time)) {
            // complainant is creator
            $complainantUserId = intval($blotter['created_by']);
            if ($complainantUserId) {
                require_once __DIR__ . '/../includes/notifications.php';

                $title = "Hearing Scheduled for {$blotter['blotter_no']}";
                $msg = "A hearing has been scheduled for your blotter ({$blotter['blotter_no']}).\nDate: " . ($hearing_date ?: 'TBA') . "\nTime: " . ($hearing_time ?: 'TBA') . "\nLocation: " . (!empty($hearing_location) ? htmlspecialchars($hearing_location) : 'TBA');

                createNotification($pdo, $complainantUserId, $blotter_id, 'Blotter Hearing', $title, $msg);

                // send email if we have their email
                $u = $pdo->prepare("SELECT emailadd AS email FROM signup WHERE user_id = :uid");
                $u->execute([':uid' => $complainantUserId]);
                $userRow = $u->fetch(PDO::FETCH_ASSOC);
                if (!empty($userRow['email'])) {
                    sendEmailNotification($userRow['email'], $title, nl2br(htmlspecialchars($msg)));
                }
            }
        }

        // Notify on approval/status change to Under Investigation or Approved
        $old_status = $blotter['status'] ?? null;
        if ($old_status !== $status) {
            $complainantUserId = intval($blotter['created_by']);
            require_once __DIR__ . '/../includes/notifications.php';

            if ($status === 'Under Investigation') {
                if ($complainantUserId) {
                    $title = "Blotter Updated: {$blotter['blotter_no']} - Under Investigation";
                    $msg = "Your blotter ({$blotter['blotter_no']}) has been approved and is now under investigation. An officer has been assigned and further updates will be posted.";
                    createNotification($pdo, $complainantUserId, $blotter_id, 'Blotter Status', $title, $msg);

                    $u = $pdo->prepare("SELECT emailadd AS email FROM signup WHERE user_id = :uid");
                    $u->execute([':uid' => $complainantUserId]);
                    $userRow = $u->fetch(PDO::FETCH_ASSOC);
                    if (!empty($userRow['email'])) {
                        // Reply-To respondent email if available
                        $replyTo = !empty($respondent_email) ? $respondent_email : null;
                        sendEmailNotification($userRow['email'], $title, nl2br(htmlspecialchars($msg)), $replyTo);
                    }
                }
            }

            if ($status === 'Approved') {
                if ($complainantUserId) {
                    $title = "Blotter Approved: {$blotter['blotter_no']}";
                    $msg = "Your blotter ({$blotter['blotter_no']}) has been approved. Reference: {$blotter['blotter_no']}.";
                    createNotification($pdo, $complainantUserId, $blotter_id, 'Blotter Approved', $title, $msg);

                    $u = $pdo->prepare("SELECT emailadd AS email FROM signup WHERE user_id = :uid");
                    $u->execute([':uid' => $complainantUserId]);
                    $userRow = $u->fetch(PDO::FETCH_ASSOC);
                    if (!empty($userRow['email'])) {
                        $replyTo = !empty($respondent_email) ? $respondent_email : null;
                        sendEmailNotification($userRow['email'], $title, nl2br(htmlspecialchars($msg)), $replyTo);
                    }
                }

                // Also notify respondent email if provided
                if (!empty($respondent_email)) {
                    $titleR = "Blotter Notification: {$blotter['blotter_no']}";
                    $msgR = "A blotter (No: {$blotter['blotter_no']}) mentioning you has been approved. Please contact the office if you need details.";
                    sendEmailNotification($respondent_email, $titleR, nl2br(htmlspecialchars($msgR)));
                }
            }
        }

        // Scope check: if assigned officer has barangay and the location/address does not match, notify complainant
        if ($officer_id) {
            $off = $pdo->prepare("SELECT barangay FROM bcpc_officers WHERE user_id = :uid LIMIT 1");
            $off->execute([':uid' => $officer_id]);
            $offRow = $off->fetch(PDO::FETCH_ASSOC);
            if ($offRow && !empty($offRow['barangay'])) {
                $barangay = strtolower($offRow['barangay']);
                $locToCheck = strtolower($location . ' ' . $respondent_address);
                if (strpos($locToCheck, $barangay) === false) {
                    // Outside officer scope
                    $complainantUserId = intval($blotter['created_by']);
                    if ($complainantUserId) {
                        require_once __DIR__ . '/../includes/notifications.php';
                        $title = "Jurisdiction Notice for {$blotter['blotter_no']}";
                        $msg = "The assigned officer's barangay (" . htmlspecialchars($offRow['barangay']) . ") does not appear to cover the incident location or respondent address you provided. The case may be outside the officer's scope of authority. Please contact the office for guidance or your local barangay.";
                        createNotification($pdo, $complainantUserId, $blotter_id, 'Jurisdiction Notice', $title, $msg);

                        $u = $pdo->prepare("SELECT emailadd AS email FROM signup WHERE user_id = :uid");
                        $u->execute([':uid' => $complainantUserId]);
                        $userRow = $u->fetch(PDO::FETCH_ASSOC);
                        if (!empty($userRow['email'])) {
                            sendEmailNotification($userRow['email'], $title, nl2br(htmlspecialchars($msg)));
                        }
                    }
                }
            }
        }

        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Blotter updated successfully.'];
        header('Location: Blotter.php');
        exit;
    } catch (Exception $e) {
        error_log('Blotter update failed: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Error updating blotter: ' . $e->getMessage()];
    }
}

// Fetch officers list for the form
$officersSql = "SELECT user_id AS id, fullname AS name FROM signup ORDER BY fullname";
try {
    $officers = $pdo->query($officersSql)->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $officers = [];
}

// Get existing attachments for this blotter
$attachment_manager = new AttachmentManager($pdo);
$attachments = $attachment_manager->getAttachments('blotter', $blotter_id);

$base_url = '../';
$page_title = 'Edit Blotter';
require '../includes/header.php';
require '../includes/navbar.php';
?>

<div class="main-content">
<div class="content-container">

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2">Edit Blotter</h1>
    <a href="Blotter.php" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Back
    </a>
</div>

<?php if (!empty($_SESSION['flash'])): $f = $_SESSION['flash']; ?>
    <div class="alert alert-<?= htmlspecialchars($f['type']) ?> alert-dismissible">
        <?= htmlspecialchars($f['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['flash']); ?>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h5>Blotter No: <span class="text-primary"><?= htmlspecialchars($blotter['blotter_no']) ?></span></h5>
    </div>
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="update">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Complainant Name *</label>
                    <input type="text" name="complainant_name" class="form-control" value="<?= htmlspecialchars($blotter['complainant_name']) ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Respondent Name</label>
                    <input type="text" name="respondent_name" class="form-control" value="<?= htmlspecialchars($blotter['respondent_name'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Respondent Contact / Email</label>
                    <div class="d-flex gap-2">
                        <input type="text" name="respondent_contact" class="form-control" placeholder="Phone" value="<?= htmlspecialchars($blotter['respondent_contact'] ?? '') ?>">
                        <input type="email" name="respondent_email" class="form-control" placeholder="Email" value="<?= htmlspecialchars($blotter['respondent_email'] ?? '') ?>">
                    </div>
                    <small class="text-muted">Optional. Provide phone number or email if available.</small>
                </div>
                <div class="col-12">
                    <label class="form-label">Respondent Home Address <span class="text-danger">*</span></label>
                    <input type="text" name="respondent_address" class="form-control" value="<?= htmlspecialchars($blotter['respondent_address'] ?? '') ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Incident Type</label>
                    <input type="text" name="incident_type" class="form-control" value="<?= htmlspecialchars($blotter['incident_type'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Date</label>
                    <input type="date" name="incident_date" class="form-control" value="<?= htmlspecialchars($blotter['incident_date'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Time</label>
                    <input type="time" name="incident_time" class="form-control" value="<?= htmlspecialchars($blotter['incident_time'] ?? '') ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Location</label>
                    <input type="text" name="location" class="form-control" value="<?= htmlspecialchars($blotter['location'] ?? '') ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Description *</label>
                    <textarea name="description" class="form-control" rows="5" required><?= htmlspecialchars($blotter['description']) ?></textarea>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Priority</label>
                    <select name="priority" class="form-select">
                        <option value="High" <?= $blotter['priority'] === 'High' ? 'selected' : '' ?>>High</option>
                        <option value="Medium" <?= $blotter['priority'] === 'Medium' ? 'selected' : '' ?>>Medium</option>
                        <option value="Low" <?= $blotter['priority'] === 'Low' ? 'selected' : '' ?>>Low</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="Pending" <?= $blotter['status'] === 'Pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="Approved" <?= $blotter['status'] === 'Approved' ? 'selected' : '' ?>>Approved</option>
                        <option value="Under Investigation" <?= $blotter['status'] === 'Under Investigation' ? 'selected' : '' ?>>Under Investigation</option>
                        <option value="Resolved" <?= $blotter['status'] === 'Resolved' ? 'selected' : '' ?>>Resolved</option>                        <option value="Rejected" <?= $blotter['status'] === 'Rejected' ? 'selected' : '' ?>>Rejected</option>                        <option value="Archived" <?= $blotter['status'] === 'Archived' ? 'selected' : '' ?>>Archived</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Assigned Officer</label>
                    <select name="officer_id" class="form-select">
                        <option value="">-- Unassigned --</option>
                        <?php foreach ($officers as $o): ?>
                            <option value="<?= intval($o['id']) ?>" <?= intval($blotter['officer_id']) === intval($o['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($o['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Hearing Date</label>
                    <input type="date" name="hearing_date" class="form-control" value="<?= htmlspecialchars($blotter['hearing_date'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Hearing Time</label>
                    <input type="time" name="hearing_time" class="form-control" value="<?= htmlspecialchars($blotter['hearing_time'] ?? '') ?>">
                </div>
                <div class="col-12 mt-2">
                    <label class="form-label">Hearing Location (optional)</label>
                    <input type="text" name="hearing_location" class="form-control" value="<?= htmlspecialchars($blotter['hearing_location'] ?? '') ?>">
                </div>
            </div>

            <!-- Attachments Section -->
            <div class="mt-4">
                <h6 class="fw-bold mb-3">Attachments</h6>

                <!-- Existing Attachments -->
                <?php if (!empty($attachments)): ?>
                <div class="mb-3">
                    <h6 class="text-muted">Existing Attachments</h6>
                    <div class="border rounded p-3">
                        <form method="POST" id="delete-attachments-form">
                            <input type="hidden" name="action" value="delete_attachments">
                            <div class="row">
                                <?php foreach ($attachments as $attachment): ?>
                                <div class="col-md-6 col-lg-4 mb-3">
                                    <div class="card h-100">
                                        <div class="card-body d-flex flex-column">
                                            <div class="form-check mb-2">
                                                <input class="form-check-input attachment-checkbox" type="checkbox"
                                                       name="attachment_ids[]" value="<?= $attachment['id'] ?>"
                                                       id="attachment_<?= $attachment['id'] ?>">
                                                <label class="form-check-label" for="attachment_<?= $attachment['id'] ?>">
                                                    <small class="text-muted">Select to delete</small>
                                                </label>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h6 class="card-title mb-1">
                                                    <i class="bi bi-file-earmark"></i>
                                                    <?= htmlspecialchars($attachment['original_filename']) ?>
                                                </h6>
                                                <?php if (!empty($attachment['description'])): ?>
                                                <p class="card-text small text-muted mb-2">
                                                    <?= htmlspecialchars($attachment['description']) ?>
                                                </p>
                                                <?php endif; ?>
                                                <small class="text-muted d-block">
                                                    <?= number_format($attachment['file_size'] / 1024, 1) ?> KB •
                                                    Uploaded by <?= htmlspecialchars($attachment['uploaded_by_name']) ?> •
                                                    <?= date('M j, Y g:i A', strtotime($attachment['uploaded_at'])) ?>
                                                </small>
                                            </div>
                                            <div class="mt-2">
                                                <a href="download_attachment.php?type=blotter&file=<?= htmlspecialchars($attachment['stored_filename']) ?>&action=view"
                                                   target="_blank" class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-eye"></i> View
                                                </a>
                                                <a href="download_attachment.php?type=blotter&file=<?= htmlspecialchars($attachment['stored_filename']) ?>&action=download"
                                                   class="btn btn-sm btn-outline-secondary">
                                                    <i class="bi bi-download"></i> Download
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="mt-3">
                                <button type="submit" class="btn btn-outline-danger btn-sm"
                                        onclick="return confirm('Are you sure you want to delete the selected attachments?')">
                                    <i class="bi bi-trash"></i> Delete Selected
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Add New Attachments -->
                <div id="attachments-container">
                    <div class="attachment-item border rounded p-3 mb-3">
                        <div class="row g-2 align-items-end">
                            <div class="col-md-5">
                                <label class="form-label">File</label>
                                <input type="file" name="attachments[]" class="form-control"
                                       accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt">
                            </div>
                            <div class="col-md-5">
                                <label class="form-label">Description (Optional)</label>
                                <input type="text" name="attachment_descriptions[]" class="form-control"
                                       placeholder="Brief description of the file">
                            </div>
                            <div class="col-md-2">
                                <button type="button" class="btn btn-outline-danger remove-attachment"
                                        onclick="removeAttachment(this)" style="display: none;">
                                    <i class="bi bi-x"></i> Remove
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <button type="button" class="btn btn-outline-primary btn-sm" onclick="addAttachment()">
                    <i class="bi bi-plus"></i> Add Another File
                </button>
            </div>

            <div class="mt-4">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-circle"></i> Save Changes
                </button>
                <a href="Blotter.php" class="btn btn-secondary">
                    <i class="bi bi-x-circle"></i> Cancel
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

    // Clear input values
    const inputs = newItem.querySelectorAll('input');
    inputs.forEach(input => {
        input.value = '';
    });

    // Show remove button
    const removeBtn = newItem.querySelector('.remove-attachment');
    removeBtn.style.display = 'block';

    container.appendChild(newItem);
}

function removeAttachment(button) {
    const container = document.getElementById('attachments-container');
    const items = container.querySelectorAll('.attachment-item');

    if (items.length > 1) {
        button.closest('.attachment-item').remove();
    } else {
        // Clear inputs instead of removing the last item
        const inputs = button.closest('.attachment-item').querySelectorAll('input');
        inputs.forEach(input => {
            input.value = '';
        });
    }
}

// Select/Deselect all attachments functionality
document.addEventListener('DOMContentLoaded', function() {
    const selectAllBtn = document.createElement('button');
    selectAllBtn.type = 'button';
    selectAllBtn.className = 'btn btn-outline-secondary btn-sm me-2';
    selectAllBtn.innerHTML = '<i class="bi bi-check-all"></i> Select All';
    selectAllBtn.onclick = function() {
        const checkboxes = document.querySelectorAll('.attachment-checkbox');
        const allChecked = Array.from(checkboxes).every(cb => cb.checked);
        checkboxes.forEach(cb => cb.checked = !allChecked);
        this.innerHTML = allChecked ?
            '<i class="bi bi-check-all"></i> Select All' :
            '<i class="bi bi-check-all"></i> Deselect All';
    };

    const deleteForm = document.getElementById('delete-attachments-form');
    if (deleteForm) {
        const submitBtn = deleteForm.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.parentNode.insertBefore(selectAllBtn, submitBtn);
        }
    }
});
</script>

