<?php
session_start();
require_once '../config/db_connect.php';
if (!isset($pdo) || !($pdo instanceof PDO)) {
    $pdo = getDBConnection();
}

$adminId = $_SESSION['admin_user_id'] ?? $_SESSION['user_id'] ?? null;
if (empty($adminId)) {
    exit('Unauthorized');
}

// Check admin/officer role
$adminCheck = $pdo->prepare("SELECT role FROM signup WHERE user_id = ?");
$adminCheck->execute([$adminId]);
$userRole = $adminCheck->fetch(PDO::FETCH_ASSOC);

$roleStr = strtolower(trim($userRole['role'] ?? $_SESSION['admin_role'] ?? $_SESSION['role'] ?? ''));
if (strpos($roleStr, 'admin') === false && strpos($roleStr, 'officer') === false && strpos($roleStr, 'official') === false) {
    exit('Unauthorized');
}
$_SESSION['user_id'] = $adminId;

$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? 0;

if ($action === 'update_status') {
    $evidenceId = intval($_POST['evidence_id'] ?? 0);
    $newStatus = trim($_POST['new_status'] ?? 'Collected');
    $location = trim($_POST['location'] ?? '');
    $notes = trim($_POST['status_notes'] ?? '');

    if (!$evidenceId) {
        echo json_encode(['success' => false, 'error' => 'Invalid evidence ID']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("UPDATE evidence_records SET status = ?, storage_location = COALESCE(NULLIF(?, ''), storage_location) WHERE id = ?");
        $stmt->execute([$newStatus, $location, $evidenceId]);

        // Ensure chain_of_custody column type
        try {
            $pdo->exec("ALTER TABLE chain_of_custody MODIFY COLUMN action_type VARCHAR(100) NOT NULL DEFAULT 'Transferred'");
        } catch (Exception $ignored) {}

        // Add chain of custody entry
        $performedBy = !empty($_SESSION['user_id']) ? $_SESSION['user_id'] : 1;
        $custodyStmt = $pdo->prepare("INSERT INTO chain_of_custody (evidence_id, action_type, action_date, location, purpose, notes, performed_by) VALUES (?, 'Transferred', NOW(), ?, ?, ?, ?)");
        $custodyStmt->execute([
            $evidenceId,
            $location ?: 'Evidence Room',
            'Status updated to ' . $newStatus,
            $notes ?: ('Status changed to ' . $newStatus),
            $performedBy
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Evidence status successfully updated to ' . $newStatus,
            'new_status' => $newStatus,
            'evidence_id' => $evidenceId
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'add_custody') {
    $evidenceId = intval($_POST['evidence_id'] ?? 0);
    $actionType = trim($_POST['action_type'] ?? 'Transferred');
    $fromPerson = trim($_POST['from_person'] ?? '');
    $toPerson = trim($_POST['to_person'] ?? '');
    $actionDate = trim($_POST['action_date'] ?? date('Y-m-d'));
    $actionTime = trim($_POST['action_time'] ?? date('H:i:s'));
    $location = trim($_POST['location'] ?? 'Evidence Vault');
    $purpose = trim($_POST['purpose'] ?? 'Custody Handover');
    $witness = trim($_POST['witness'] ?? '');
    $custodyNotes = trim($_POST['custody_notes'] ?? '');

    if (!$evidenceId) {
        echo json_encode(['success' => false, 'error' => 'Invalid evidence ID']);
        exit;
    }

    try {
        // Ensure chain_of_custody column type
        try {
            $pdo->exec("ALTER TABLE chain_of_custody MODIFY COLUMN action_type VARCHAR(100) NOT NULL DEFAULT 'Transferred'");
        } catch (Exception $ignored) {}

        $performedBy = !empty($_SESSION['user_id']) ? $_SESSION['user_id'] : 1;
        $custodyStmt = $pdo->prepare("
            INSERT INTO chain_of_custody
            (evidence_id, action_type, from_person_name, to_person_name, action_date,
             location, purpose, notes, performed_by, witness_name)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $datetime = $actionDate . ' ' . ($actionTime ?: '00:00:00');
        $custodyStmt->execute([
            $evidenceId,
            $actionType,
            $fromPerson ?: 'Previous Custodian',
            $toPerson ?: 'Authorized Officer',
            $datetime,
            $location,
            $purpose,
            $custodyNotes,
            $performedBy,
            $witness ?: null
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Chain of Custody entry added successfully!',
            'evidence_id' => $evidenceId
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

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
                <h6 class="fw-bold text-dark mb-0"><i class="bi bi-shield-check text-success me-1"></i> Evidence Information</h6>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-outline-success fw-bold shadow-sm" onclick="showUpdateStatusForm(<?php echo $evidence['id']; ?>)">
                        <i class="bi bi-pencil me-1"></i> Update Status
                    </button>
                    <button type="button" class="btn btn-sm btn-success fw-bold shadow-sm" style="background-color: #2e856e !important; border-color: #2e856e !important;" onclick="showAddCustodyForm(<?php echo $evidence['id']; ?>)">
                        <i class="bi bi-plus-circle me-1"></i> Add Custody Entry
                    </button>
                </div>
            </div>

            <div id="updateStatusAlert_<?php echo $evidence['id']; ?>" style="display: none;"></div>
            <div id="addCustodyAlert_<?php echo $evidence['id']; ?>" style="display: none;"></div>

            <table class="table table-sm align-middle">
                <tr><th style="width: 32%;">Evidence Number:</th><td class="fw-bold text-dark font-monospace"><?php echo htmlspecialchars($evidence['evidence_number']); ?></td></tr>
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
                <tr><th>Storage Location:</th><td id="evidenceLocDisplay_<?php echo $evidence['id']; ?>"><?php echo htmlspecialchars($evidence['storage_location']); ?></td></tr>
                <tr><th>Stored File Folder:</th><td><code>uploads/evidence/</code></td></tr>
                <tr><th>Security Level:</th><td><span class="badge bg-secondary"><?php echo htmlspecialchars($evidence['security_level']); ?></span></td></tr>
                <tr><th>Status:</th><td>
                    <span id="evidenceStatusBadge_<?php echo $evidence['id']; ?>" class="badge bg-<?php
                        echo match($evidence['status']) {
                            'Collected' => 'primary',
                            'In Storage' => 'info',
                            'In Transit' => 'warning text-dark',
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
            <div id="updateStatusForm_<?php echo $evidence['id']; ?>" style="display: none;" class="mt-3 p-3 border rounded shadow-sm bg-light">
                <h6 class="fw-bold text-success mb-3"><i class="bi bi-pencil-square me-1"></i> Update Evidence Status</h6>
                <form onsubmit="submitUpdateStatusAjax(event, <?php echo $evidence['id']; ?>)">
                    <input type="hidden" name="evidence_id" value="<?php echo $evidence['id']; ?>">
                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">New Status</label>
                            <select name="new_status" class="form-select form-select-sm" required>
                                <option value="Collected" <?php echo $evidence['status'] === 'Collected' ? 'selected' : ''; ?>>Collected</option>
                                <option value="In Storage" <?php echo $evidence['status'] === 'In Storage' ? 'selected' : ''; ?>>In Storage</option>
                                <option value="In Transit" <?php echo $evidence['status'] === 'In Transit' ? 'selected' : ''; ?>>In Transit</option>
                                <option value="Released" <?php echo $evidence['status'] === 'Released' ? 'selected' : ''; ?>>Released</option>
                                <option value="Destroyed" <?php echo $evidence['status'] === 'Destroyed' ? 'selected' : ''; ?>>Destroyed</option>
                                <option value="Lost" <?php echo $evidence['status'] === 'Lost' ? 'selected' : ''; ?>>Lost</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Location / Vault</label>
                            <input type="text" name="location" class="form-control form-control-sm" value="<?php echo htmlspecialchars($evidence['storage_location']); ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Status Update Notes</label>
                        <textarea name="status_notes" class="form-control form-control-sm" rows="2" placeholder="State reason for status update..."></textarea>
                    </div>
                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-outline-success btn-sm px-3 fw-semibold" onclick="hideUpdateStatusForm(<?php echo $evidence['id']; ?>)">Cancel</button>
                        <button type="submit" class="btn btn-success btn-sm px-3 fw-bold shadow-sm" style="background-color: #2e856e !important; border-color: #2e856e !important;"><i class="bi bi-check-lg me-1"></i> Save Status</button>
                    </div>
                </form>
            </div>

            <!-- Add Custody Entry Form (hidden by default) -->
            <div id="addCustodyForm_<?php echo $evidence['id']; ?>" style="display: none;" class="mt-3 p-3 border rounded shadow-sm bg-light">
                <h6 class="fw-bold text-success mb-3"><i class="bi bi-link-45deg me-1"></i> Add Chain of Custody Entry</h6>
                <form onsubmit="submitAddCustodyAjax(event, <?php echo $evidence['id']; ?>)">
                    <input type="hidden" name="evidence_id" value="<?php echo $evidence['id']; ?>">
                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Action Type</label>
                            <select name="action_type" class="form-select form-select-sm" required>
                                <option value="Transferred" selected>Transferred</option>
                                <option value="Accessed">Accessed</option>
                                <option value="Stored">Stored</option>
                                <option value="Retrieved">Retrieved</option>
                                <option value="Released">Released</option>
                                <option value="Returned">Returned</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Date</label>
                            <input type="date" name="action_date" class="form-control form-control-sm" required value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Time</label>
                            <input type="time" name="action_time" class="form-control form-control-sm" value="<?php echo date('H:i'); ?>">
                        </div>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">From Person</label>
                            <input type="text" name="from_person" class="form-control form-control-sm" placeholder="Previous custodian" value="<?php echo htmlspecialchars($evidence['collector_name'] ?: 'Custody Officer'); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">To Person *</label>
                            <input type="text" name="to_person" class="form-control form-control-sm" placeholder="New custodian name" required>
                        </div>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Location</label>
                            <input type="text" name="location" class="form-control form-control-sm" value="<?php echo htmlspecialchars($evidence['storage_location']); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Purpose</label>
                            <input type="text" name="purpose" class="form-control form-control-sm" placeholder="e.g. Court presentation, Forensic review">
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-bold">Witness Name</label>
                        <input type="text" name="witness" class="form-control form-control-sm" placeholder="Optional witness name">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Custody Notes</label>
                        <textarea name="custody_notes" class="form-control form-control-sm" rows="2" placeholder="Any additional remarks..."></textarea>
                    </div>
                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-outline-success btn-sm px-3 fw-semibold" onclick="hideAddCustodyForm(<?php echo $evidence['id']; ?>)">Cancel</button>
                        <button type="submit" class="btn btn-success btn-sm px-3 fw-bold shadow-sm" style="background-color: #2e856e !important; border-color: #2e856e !important;"><i class="bi bi-save me-1"></i> Save Entry</button>
                    </div>
                </form>
            </div>
        </div>
        <div class="col-md-4">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="mb-0">Attachments (<?php echo count($attachments); ?>)</h6>
                <?php if (!empty($attachments)): ?>
                    <button type="button" class="btn btn-xs btn-outline-success py-0 px-2 fw-bold" onclick="forwardToGroup7(<?php echo $evidence['id']; ?>)">
                        <i class="bi bi-cloud-upload"></i> Send to Group 7
                    </button>
                <?php endif; ?>
            </div>
            <div id="group7StatusAlert_<?php echo $evidence['id']; ?>" class="mb-2" style="display:none;"></div>
            <?php if (empty($attachments)): ?>
                <p class="text-muted small">No attachments uploaded.</p>
            <?php else: ?>
                <div class="list-group mb-3">
                    <?php foreach ($attachments as $attachment): 
                        $ext = strtolower(pathinfo($attachment['stored_filename'] ?: $attachment['file_path'], PATHINFO_EXTENSION));
                        $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                        $isVideo = in_array($ext, ['mp4', 'webm', 'ogg', 'mov', 'avi']);
                    ?>
                        <div class="list-group-item p-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="text-truncate" style="max-width: 180px;">
                                    <i class="bi bi-<?php echo $isImage ? 'image' : ($isVideo ? 'camera-video' : 'file-earmark'); ?> text-primary me-1"></i>
                                    <strong><?php echo htmlspecialchars($attachment['original_filename']); ?></strong>
                                    <br><small class="text-muted"><?php echo formatFileSize($attachment['file_size']); ?></small>
                                </div>
                                <a href="<?php echo htmlspecialchars($attachment['file_path']); ?>" target="_blank" class="btn btn-sm btn-outline-secondary py-0 px-2">
                                    <i class="bi bi-download"></i>
                                </a>
                            </div>
                            <?php if ($isImage): ?>
                                <div class="mt-2 text-center bg-light p-1 rounded border">
                                    <img src="<?php echo htmlspecialchars($attachment['file_path']); ?>" alt="Evidence Photo" class="img-fluid rounded" style="max-height: 120px; object-fit: contain;">
                                </div>
                            <?php elseif ($isVideo): ?>
                                <div class="mt-2 text-center bg-dark p-1 rounded">
                                    <video src="<?php echo htmlspecialchars($attachment['file_path']); ?>" controls class="w-100 rounded" style="max-height: 140px;"></video>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
} elseif ($action === 'send_to_group7' && $id) {
    header('Content-Type: application/json; charset=utf-8');
    require_once '../modules/OperationalModuleIntegrator.php';

    $stmt = $pdo->prepare("SELECT * FROM evidence_records WHERE id = ?");
    $stmt->execute([$id]);
    $evidence = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$evidence) {
        echo json_encode(['success' => false, 'error' => 'Evidence record not found']);
        exit;
    }

    $attStmt = $pdo->prepare("SELECT * FROM evidence_attachments WHERE evidence_id = ? AND is_deleted = 0");
    $attStmt->execute([$id]);
    $attachments = $attStmt->fetchAll(PDO::FETCH_ASSOC);

    $photos = [];
    $videos = [];
    foreach ($attachments as $a) {
        $ext = strtolower(pathinfo($a['stored_filename'] ?: $a['file_path'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            $photos[] = [
                'filename' => $a['original_filename'],
                'file_url' => $a['file_path'],
                'size' => $a['file_size']
            ];
        } elseif (in_array($ext, ['mp4', 'webm', 'ogg', 'mov', 'avi'])) {
            $videos[] = [
                'filename' => $a['original_filename'],
                'file_url' => $a['file_path'],
                'size' => $a['file_size']
            ];
        }
    }

    $integrator = new OperationalModuleIntegrator($pdo);
    $payload = [
        'evidence_id' => $evidence['id'],
        'evidence_number' => $evidence['evidence_number'],
        'case_number' => $evidence['case_number'],
        'description' => $evidence['item_description'],
        'media_type' => (!empty($photos) && !empty($videos)) ? 'Photo & Video' : (!empty($photos) ? 'Photo' : 'Video'),
        'photos' => $photos,
        'videos' => $videos,
        'uploaded_by' => $_SESSION['user_id']
    ];

    try {
        $result = $integrator->dispatchToGroup7EvidenceUpload($payload);

        // Ensure chain_of_custody table has proper column width
        try {
            $pdo->exec("ALTER TABLE chain_of_custody MODIFY COLUMN action_type VARCHAR(100) NOT NULL DEFAULT 'Transferred'");
        } catch (Exception $ignored) {}

        // Add chain of custody entry
        $performedBy = !empty($_SESSION['user_id']) ? $_SESSION['user_id'] : 1;
        $cStmt = $pdo->prepare("INSERT INTO chain_of_custody (evidence_id, action_type, action_date, location, purpose, notes, performed_by) VALUES (?, 'Transferred', NOW(), 'Group 7 Inspection Cloud', 'Dispatched photos/videos to Group 7 Upload API', ?, ?)");
        $cStmt->execute([$id, "Forwarded " . count($photos) . " photo(s) and " . count($videos) . " video(s) to Group 7", $performedBy]);

        echo json_encode([
            'success' => true,
            'message' => 'Evidence media (Photos & Videos) dispatched to Group 7 successfully!',
            'photos_count' => count($photos),
            'videos_count' => count($videos),
            'result' => $result
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
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