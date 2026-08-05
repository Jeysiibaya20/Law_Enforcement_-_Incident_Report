<?php
session_start();
require_once '../config/db_connect.php';
if (!isset($pdo) || !($pdo instanceof PDO)) {
    $pdo = getDBConnection();
}

if (!isset($_SESSION['user_id'])) {
    exit('Unauthorized');
}

// Check admin role
$adminCheck = $pdo->prepare("SELECT role FROM signup WHERE user_id = ?");
$adminCheck->execute([$_SESSION['user_id']]);
$userRole = $adminCheck->fetch(PDO::FETCH_ASSOC);

if (!$userRole || strtolower($userRole['role']) !== 'admin') {
    exit('Unauthorized');
}

$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? 0;

if ($action === 'view' && $id) {
    // Get evidence details
    $stmt = $pdo->prepare("
        SELECT e.*, u.fullname
        FROM evidence_records e
        LEFT JOIN signup u ON e.collected_by = u.user_id
        WHERE e.id = ?
    ");
    $stmt->execute([$id]);
    $evidence = $stmt->fetch();

    if (!$evidence) {
        exit('<div class="alert alert-danger">Evidence record not found.</div>');
    }

    // Get attachments
    $attachments_stmt = $pdo->prepare("
        SELECT * FROM evidence_attachments
        WHERE evidence_id = ? AND is_deleted = 0
        ORDER BY uploaded_at DESC
    ");
    $attachments_stmt->execute([$id]);
    $attachments = $attachments_stmt->fetchAll();

    ?>
    <div class="row">
        <div class="col-md-8">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6>Evidence Information</h6>
                <div>
                    <button class="btn btn-sm btn-outline-primary" onclick="showUpdateStatusForm(<?php echo $evidence['id']; ?>)">
                        <i class="bi bi-pencil"></i> Update Status
                    </button>
                    <button class="btn btn-sm btn-outline-secondary" onclick="showAddCustodyForm(<?php echo $evidence['id']; ?>)">
                        <i class="bi bi-plus-circle"></i> Add Custody Entry
                    </button>
                </div>
            </div>
            <table class="table table-sm">
                <tr><th>Evidence Number:</th><td><?php echo htmlspecialchars($evidence['evidence_number']); ?></td></tr>
                <tr><th>Type:</th><td><?php echo htmlspecialchars($evidence['evidence_type']); ?></td></tr>
                <tr><th>Case:</th><td><?php echo htmlspecialchars($evidence['case_number'] ?: 'N/A'); ?></td></tr>
                <tr><th>Description:</th><td><?php echo nl2br(htmlspecialchars($evidence['item_description'])); ?></td></tr>
                <tr><th>Location Found:</th><td><?php echo htmlspecialchars($evidence['location_found'] ?: 'N/A'); ?></td></tr>
                <tr><th>Source Department:</th><td><?php echo htmlspecialchars($evidence['source_department'] ?: 'N/A'); ?></td></tr>
                <tr><th>Received From:</th><td><?php echo htmlspecialchars($evidence['received_from'] ?: 'N/A'); ?></td></tr>
                <tr><th>Source Reference:</th><td><?php echo htmlspecialchars($evidence['source_reference'] ?: 'N/A'); ?></td></tr>
                <tr><th>Collection Date:</th><td><?php echo date('M d, Y H:i', strtotime($evidence['collection_date'])); ?></td></tr>
                <tr><th>Collected By:</th><td><?php echo htmlspecialchars($evidence['collector_name']); ?></td></tr>
                <tr><th>Condition:</th><td><?php echo htmlspecialchars($evidence['condition']); ?></td></tr>
                <tr><th>Storage Location:</th><td><?php echo htmlspecialchars($evidence['storage_location']); ?></td></tr>
                <tr><th>Stored File Folder:</th><td>uploads/evidence/</td></tr>
                <tr><th>Security Level:</th><td><?php echo htmlspecialchars($evidence['security_level']); ?></td></tr>
                <tr><th>Status:</th><td>
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
                </td></tr>
                <?php if ($evidence['notes']): ?>
                <tr><th>Notes:</th><td><?php echo nl2br(htmlspecialchars($evidence['notes'])); ?></td></tr>
                <?php endif; ?>
            </table>

            <!-- Update Status Form (hidden by default) -->
            <div id="updateStatusForm_<?php echo $evidence['id']; ?>" style="display: none;" class="mt-3 p-3 border rounded">
                <h6>Update Evidence Status</h6>
                <form method="POST" action="evidence_collection.php">
                    <input type="hidden" name="evidence_id" value="<?php echo $evidence['id']; ?>">
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label">New Status</label>
                            <select name="new_status" class="form-select" required>
                                <option value="Collected" <?php echo $evidence['status'] === 'Collected' ? 'selected' : ''; ?>>Collected</option>
                                <option value="In Storage" <?php echo $evidence['status'] === 'In Storage' ? 'selected' : ''; ?>>In Storage</option>
                                <option value="In Transit" <?php echo $evidence['status'] === 'In Transit' ? 'selected' : ''; ?>>In Transit</option>
                                <option value="Released" <?php echo $evidence['status'] === 'Released' ? 'selected' : ''; ?>>Released</option>
                                <option value="Destroyed" <?php echo $evidence['status'] === 'Destroyed' ? 'selected' : ''; ?>>Destroyed</option>
                                <option value="Lost" <?php echo $evidence['status'] === 'Lost' ? 'selected' : ''; ?>>Lost</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Location</label>
                            <input type="text" name="location" class="form-control" value="<?php echo htmlspecialchars($evidence['storage_location']); ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="status_notes" class="form-control" rows="2"></textarea>
                    </div>
                    <button type="submit" name="update_status" class="btn btn-primary btn-sm">Update Status</button>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="hideUpdateStatusForm(<?php echo $evidence['id']; ?>)">Cancel</button>
                </form>
            </div>

            <!-- Add Custody Entry Form (hidden by default) -->
            <div id="addCustodyForm_<?php echo $evidence['id']; ?>" style="display: none;" class="mt-3 p-3 border rounded">
                <h6>Add Chain of Custody Entry</h6>
                <form method="POST" action="evidence_collection.php">
                    <input type="hidden" name="evidence_id" value="<?php echo $evidence['id']; ?>">
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label">Action Type</label>
                            <select name="action_type" class="form-select" required>
                                <option value="Transferred">Transferred</option>
                                <option value="Accessed">Accessed</option>
                                <option value="Stored">Stored</option>
                                <option value="Retrieved">Retrieved</option>
                                <option value="Released">Released</option>
                                <option value="Returned">Returned</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Date</label>
                            <input type="date" name="action_date" class="form-control" required value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Time</label>
                            <input type="time" name="action_time" class="form-control">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label">From Person</label>
                            <input type="text" name="from_person" class="form-control" placeholder="Previous custodian">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">To Person</label>
                            <input type="text" name="to_person" class="form-control" placeholder="New custodian" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Location</label>
                        <input type="text" name="location" class="form-control" value="<?php echo htmlspecialchars($evidence['storage_location']); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Purpose</label>
                        <input type="text" name="purpose" class="form-control" placeholder="Reason for action">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Witness</label>
                        <input type="text" name="witness" class="form-control" placeholder="Witness name (optional)">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="custody_notes" class="form-control" rows="2"></textarea>
                    </div>
                    <button type="submit" name="add_custody_entry" class="btn btn-primary btn-sm">Add Entry</button>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="hideAddCustodyForm(<?php echo $evidence['id']; ?>)">Cancel</button>
                </form>
            </div>
        </div>
        <div class="col-md-4">
            <h6>Attachments (<?php echo count($attachments); ?>)</h6>
            <?php if (empty($attachments)): ?>
                <p class="text-muted">No attachments</p>
            <?php else: ?>
                <div class="list-group">
                    <?php foreach ($attachments as $attachment): ?>
                        <a href="<?php echo htmlspecialchars($attachment['file_path']); ?>"
                           target="_blank"
                           class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                            <div>
                                <i class="bi bi-file-earmark"></i>
                                <?php echo htmlspecialchars($attachment['original_filename']); ?>
                                <br><small class="text-muted"><?php echo formatFileSize($attachment['file_size']); ?></small>
                            </div>
                            <i class="bi bi-download"></i>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
} elseif ($action === 'chain' && $id) {
    // Get chain of custody
    $stmt = $pdo->prepare("
        SELECT c.*, u.fullname
        FROM chain_of_custody c
        LEFT JOIN signup u ON c.performed_by = u.user_id
        WHERE c.evidence_id = ?
        ORDER BY c.action_date DESC
    ");
    $stmt->execute([$id]);
    $chain = $stmt->fetchAll();

    if (empty($chain)) {
        exit('<div class="alert alert-info">No chain of custody records found.</div>');
    }

    ?>
    <div class="border rounded p-3 bg-white">
        <h6 class="fw-bold mb-2">4. Chain of Custody Log</h6>
        <p class="text-muted mb-3">Instead of police-style forensic tracking, your system simply records who handled the evidence.</p>

        <div class="table-responsive">
            <table class="table table-bordered table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 18%;">Date</th>
                        <th style="width: 24%;">Action</th>
                        <th style="width: 24%;">Handled By</th>
                        <th style="width: 34%;">Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($chain as $entry): ?>
                        <tr>
                            <td><?php echo date('M. j', strtotime($entry['action_date'])); ?></td>
                            <td><?php echo htmlspecialchars($entry['action_type']); ?></td>
                            <td><?php echo htmlspecialchars($entry['fullname'] ?: 'Unknown'); ?></td>
                            <td>
                                <?php
                                $remarks = trim($entry['notes'] ?: $entry['purpose'] ?: $entry['location'] ?: '');
                                if ($entry['from_person_name'] || $entry['to_person_name']) {
                                    $personFlow = [];
                                    if ($entry['from_person_name']) {
                                        $personFlow[] = 'From ' . $entry['from_person_name'];
                                    }
                                    if ($entry['to_person_name']) {
                                        $personFlow[] = 'To ' . $entry['to_person_name'];
                                    }
                                    if ($remarks !== '') {
                                        $remarks .= ' | ' . implode(' | ', $personFlow);
                                    } else {
                                        $remarks = implode(' | ', $personFlow);
                                    }
                                }
                                echo nl2br(htmlspecialchars($remarks ?: 'No remarks provided.'));
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}

function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    while ($bytes >= 1024 && $i < 3) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}
?>