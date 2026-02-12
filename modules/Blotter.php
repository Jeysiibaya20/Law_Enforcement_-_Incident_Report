<?php
session_start();
require '../config/db_connect.php';
require 'helpers.php';
require '../includes/attachment_manager.php';

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

// Handle POST actions: create, update, archive
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        $action = $_POST['action'];
        if ($action === 'create') {
                $blotter_no = 'BLT' . time() . rand(100,999);
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
                
                // Auto-determine priority based on incident type
                $priority = getPriorityByIncidentType($incident_type);
                
                // Only accept officer assignment from Admin users
                $userRole = strtolower($_SESSION['role'] ?? '');
                if ($userRole === 'admin') {
                    $officer_id = !empty($_POST['officer_id']) ? intval($_POST['officer_id']) : null;
                } else {
                    $officer_id = null;
                }
                $created_by = $_SESSION['user_id'] ?? null;

                // Basic validation: require respondent address and contact/email
                if (empty($respondent_address)) {
                    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Respondent home address is required.'];
                    header('Location: ' . $_SERVER['PHP_SELF']);
                    exit;
                }
                if (empty($respondent_contact) && empty($respondent_email)) {
                    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Respondent phone or email is required.'];
                    header('Location: ' . $_SERVER['PHP_SELF']);
                    exit;
                }

                $sql = "INSERT INTO blotters (blotter_no, complainant_name, respondent_name, respondent_contact, respondent_email, respondent_address, incident_type, incident_date, incident_time, location, description, priority, officer_id, created_by) VALUES (:blotter_no, :complainant, :respondent, :respondent_contact, :respondent_email, :respondent_address, :incident_type, :incident_date, :incident_time, :location, :description, :priority, :officer_id, :created_by)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
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
                        ':officer_id' => $officer_id,
                        ':created_by' => $created_by
                ]);

                $_SESSION['flash'] = ['type' => 'success', 'message' => 'Blotter created successfully.'];
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit;
        }

        if ($action === 'update' && !empty($_POST['id'])) {
                $id = intval($_POST['id']);
                // Permission check: only Admins or the assigned officer may update
                $allowed = false;
                $userRole = strtolower($_SESSION['role'] ?? '');
                $currentUserId = $_SESSION['user_id'] ?? null;
                try {
                    $permStmt = $pdo->prepare('SELECT officer_id FROM blotters WHERE id = :id');
                    $permStmt->execute([':id' => $id]);
                    $permRow = $permStmt->fetch(PDO::FETCH_ASSOC);
                    if ($userRole === 'admin') {
                        $allowed = true;
                    } elseif ($permRow && $currentUserId && intval($permRow['officer_id']) === intval($currentUserId)) {
                        $allowed = true;
                    }
                } catch (Exception $e) {
                    error_log('Permission check error: ' . $e->getMessage());
                }

                if (! $allowed) {
                    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'You do not have permission to update this blotter.'];
                    header('Location: ' . $_SERVER['PHP_SELF']);
                    exit;
                }
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
                
                // Auto-determine priority based on incident type
                $priority = getPriorityByIncidentType($incident_type);
                
                $status = $_POST['status'] ?? 'Pending';
                // Only allow Admins to change the assigned officer
                $userRole = strtolower($_SESSION['role'] ?? '');
                if ($userRole === 'admin') {
                    $officer_id = !empty($_POST['officer_id']) ? intval($_POST['officer_id']) : null;
                } else {
                    // preserve existing officer if non-admin (do not overwrite)
                    try {
                        $oStmt = $pdo->prepare('SELECT officer_id FROM blotters WHERE id = :id');
                        $oStmt->execute([':id' => $id]);
                        $oRow = $oStmt->fetch(PDO::FETCH_ASSOC);
                        $officer_id = $oRow ? $oRow['officer_id'] : null;
                    } catch (Exception $e) {
                        $officer_id = null;
                    }
                }

                // Fetch old row for change detection
                try {
                    $oldStmt = $pdo->prepare('SELECT status, hearing_date, hearing_time, created_by, blotter_no FROM blotters WHERE id = :id');
                    $oldStmt->execute([':id' => $id]);
                    $oldRow = $oldStmt->fetch(PDO::FETCH_ASSOC) ?: [];
                    $old_status = $oldRow['status'] ?? null;
                    $bno = $oldRow['blotter_no'] ?? '';
                } catch (Exception $e) {
                    $old_status = null;
                    $bno = '';
                }

                $sql = "UPDATE blotters SET complainant_name = :complainant, respondent_name = :respondent, respondent_contact = :respondent_contact, respondent_email = :respondent_email, respondent_address = :respondent_address, incident_type = :incident_type, incident_date = :incident_date, incident_time = :incident_time, location = :location, description = :description, priority = :priority, status = :status, officer_id = :officer_id WHERE id = :id";
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
                        ':priority' => $priority,
                        ':status' => $status,
                        ':officer_id' => $officer_id,
                        ':id' => $id
                ]);

                // Handle file uploads
                handleFileUpload('blotter', $id, $currentUserId);

                // Notifications: if status changed to Approved or Under Investigation, send notices
                if ($old_status !== $status) {
                    try {
                        require_once __DIR__ . '/../includes/notifications.php';
                        $complainantUserId = intval($oldRow['created_by'] ?? 0);

                        if ($status === 'Under Investigation' && $complainantUserId) {
                            $title = "Blotter Updated: {$bno} - Under Investigation";
                            $msg = "Your blotter ({$bno}) has been updated and is now under investigation.";
                            // use try/catch to avoid fatal DB FK errors if notifications table not yet migrated
                            try {
                                createNotification($pdo, $complainantUserId, $id, 'Blotter Status', $title, $msg);
                            } catch (Exception $e) {
                                error_log('Notification create failed: ' . $e->getMessage());
                                $_SESSION['flash'] = ['type' => 'warning', 'message' => 'Status updated, but notification could not be created. Run setup_notifications_fks.php to fix schema.'];
                            }

                            $u = $pdo->prepare("SELECT emailadd AS email FROM signup WHERE user_id = :uid");
                            $u->execute([':uid' => $complainantUserId]);
                            $userRow = $u->fetch(PDO::FETCH_ASSOC);
                            if (!empty($userRow['email'])) {
                                $replyTo = !empty($respondent_email) ? $respondent_email : null;
                                try { sendEmailNotification($userRow['email'], $title, nl2br(htmlspecialchars($msg)), $replyTo); } catch (Exception $e) { error_log('Email failed: ' . $e->getMessage()); }
                            }
                        }

                        if ($status === 'Approved' && $complainantUserId) {
                            $title = "Blotter Approved: {$bno}";
                            $msg = "Your blotter has been approved. Reference: " . htmlspecialchars($bno);
                            try {
                                createNotification($pdo, $complainantUserId, $id, 'Blotter Approved', $title, $msg);
                            } catch (Exception $e) {
                                error_log('Notification create failed: ' . $e->getMessage());
                                $_SESSION['flash'] = ['type' => 'warning', 'message' => 'Status updated, but notification could not be created. Run setup_notifications_fks.php to fix schema.'];
                            }

                            // send email to complainant
                            $u = $pdo->prepare("SELECT emailadd AS email FROM signup WHERE user_id = :uid");
                            $u->execute([':uid' => $complainantUserId]);
                            $userRow = $u->fetch(PDO::FETCH_ASSOC);
                            if (!empty($userRow['email'])) {
                                $replyTo = !empty($respondent_email) ? $respondent_email : null;
                                try { sendEmailNotification($userRow['email'], $title, nl2br(htmlspecialchars($msg)), $replyTo); } catch (Exception $e) { error_log('Email failed: ' . $e->getMessage()); }
                            }

                            // optional: notify respondent email directly
                            if (!empty($respondent_email)) {
                                $titleR = "Blotter Notification: Updated - Approved";
                                $msgR = "A blotter referencing you has been approved. Blotter ID: " . ($bno ?? 'N/A');
                                try { sendEmailNotification($respondent_email, $titleR, nl2br(htmlspecialchars($msgR))); } catch (Exception $e) { error_log('Email to respondent failed: ' . $e->getMessage()); }
                            }
                        }

                    } catch (Exception $e) {
                        error_log('Notification flow error: ' . $e->getMessage());
                    }
                }

                $_SESSION['flash'] = ['type' => 'success', 'message' => 'Blotter updated successfully.'];
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit;
        }

        if ($action === 'archive' && !empty($_POST['id'])) {
                $id = intval($_POST['id']);
                // Permission check: only Admins or the assigned officer may archive
                $allowed = false;
                $userRole = strtolower($_SESSION['role'] ?? '');
                $currentUserId = $_SESSION['user_id'] ?? null;
                try {
                    $permStmt = $pdo->prepare('SELECT officer_id FROM blotters WHERE id = :id');
                    $permStmt->execute([':id' => $id]);
                    $permRow = $permStmt->fetch(PDO::FETCH_ASSOC);
                    if ($userRole === 'admin') {
                        $allowed = true;
                    } elseif ($permRow && $currentUserId && intval($permRow['officer_id']) === intval($currentUserId)) {
                        $allowed = true;
                    }
                } catch (Exception $e) {
                    error_log('Permission check error: ' . $e->getMessage());
                }

                if (! $allowed) {
                    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'You do not have permission to archive this blotter.'];
                    header('Location: ' . $_SERVER['PHP_SELF']);
                    exit;
                }

                $stmt = $pdo->prepare("UPDATE blotters SET status = 'Archived' WHERE id = :id");
                $stmt->execute([':id' => $id]);
                $_SESSION['flash'] = ['type' => 'warning', 'message' => 'Blotter archived.'];
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit;
        }
}

// AJAX: return blotter JSON for view/update
if (isset($_GET['action']) && $_GET['action'] === 'view' && !empty($_GET['id'])) {
        $id = intval($_GET['id']);
        $stmt = $pdo->prepare('SELECT * FROM blotters WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        header('Content-Type: application/json');
        echo json_encode($row ?: []);
        exit;
}

/* KPIs */
// Get user role and ID for filtering
$userRole = strtolower($_SESSION['role'] ?? '');
$currentUserId = $_SESSION['user_id'] ?? null;

// KPI counts - filter by role
if ($userRole === 'admin') {
    // Admins see all blotters
    $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM blotters WHERE status!='Archived'");
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM blotters WHERE status='Pending'");
    $pending = $stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM blotters WHERE status='Resolved'");
    $resolved = $stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0;
} else if ($currentUserId) {
    // Non-admin users see only their own blotters
    $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM blotters WHERE status!='Archived' AND created_by = :user_id");
    $stmt->execute([':user_id' => $currentUserId]);
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0;
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM blotters WHERE status='Pending' AND created_by = :user_id");
    $stmt->execute([':user_id' => $currentUserId]);
    $pending = $stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0;
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM blotters WHERE status='Resolved' AND created_by = :user_id");
    $stmt->execute([':user_id' => $currentUserId]);
    $resolved = $stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0;
} else {
    $total = $pending = $resolved = 0;
}

// Fetch blotters - filter by role: Admins see all, Users see ONLY blotters they created
$sql = "SELECT b.* FROM blotters b WHERE b.status != 'Archived'";

// If user is not admin, only show blotters they created
if ($userRole !== 'admin') {
    if ($currentUserId) {
        $sql .= " AND b.created_by = :user_id";
    } else {
        // No user ID, show nothing
        $sql .= " AND 1=0";
    }
}

$sql .= " ORDER BY b.created_at DESC";

try {
    $stmt = $pdo->prepare($sql);
    if ($userRole !== 'admin' && $currentUserId) {
        $stmt->execute([':user_id' => $currentUserId]);
    } else {
        $stmt->execute();
    }
    $blotters = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug: Log query results
    error_log("Blotter Query - User: $currentUserId, Role: $userRole, Total Results: " . count($blotters));
    
} catch (Exception $e) {
    error_log("Blotters query error: " . $e->getMessage());
    $blotters = [];
}

// Fetch officers list for the form (using signup table which has fullname)
$officersSql = "SELECT user_id AS id, fullname AS name FROM signup ORDER BY fullname";
try {
    $officers = $pdo->query($officersSql)->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Officers query error: " . $e->getMessage());
    $officers = [];
}

// Now include template header/navbar and render HTML
// Ensure base URL is set so header's stylesheet path points to /assets/css/style.css
$base_url = '../';
$body_class = 'blotter-page';
$page_title = 'Blotter System';
require '../includes/header.php';
require '../includes/navbar.php';
?>

<div class="main-content">
<div class="content-container">

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <div>
        <h1 class="h2" style="margin: 0;">Blotter System</h1>
        <?php if ($userRole !== 'admin'): ?>
            <small class="text-muted" style="display: block; margin-top: 5px;">
                <i class="bi bi-info-circle"></i> Showing blotters created by your account
            </small>
        <?php else: ?>
            <small class="text-info" style="display: block; margin-top: 5px;">
                <i class="bi bi-shield-check"></i> Admin - Viewing all blotters
            </small>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($_SESSION['flash'])): $f = $_SESSION['flash']; ?>
        <div class="alert alert-<?= htmlspecialchars($f['type']) ?> alert-dismissible">
                <?= htmlspecialchars($f['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
<?php unset($_SESSION['flash']); endif; ?>

<!-- KPI CARDS -->
<div class="row row-cols-1 row-cols-md-4 g-4 mb-4">
        <div class="col">
                <div class="card border-start border-primary border-4">
                        <div class="card-body">
                                <h6>Total Blotters</h6>
                                <div class="h3"><?= $total ?></div>
                        </div>
                </div>
        </div>
        <div class="col">
                <div class="card border-start border-warning border-4">
                        <div class="card-body">
                                <h6>Pending</h6>
                                <div class="h3"><?= $pending ?></div>
                        </div>
                </div>
        </div>
        <div class="col">
                <div class="card border-start border-success border-4">
                        <div class="card-body">
                                <h6>Resolved</h6>
                                <div class="h3"><?= $resolved ?></div>
                        </div>
                </div>
        </div>
</div>

<!-- TABLE -->
<div class="card">
<div class="card-header d-flex justify-content-between align-items-center">
        <h5 style="margin: 0;">Blotter Records</h5>
        <a href="blotter_create.php" id="btnNewBlotter" type="button" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-circle"></i> New Blotter
        </a>
</div>

<!-- Search Bar -->
<div class="card-body" style="padding: 12px 15px; border-bottom: 1px solid #dee2e6;">
        <div style="display: flex; gap: 10px; align-items: center;">
                <input 
                        type="text" 
                        id="blotterSearch" 
                        class="form-control" 
                        placeholder="Search by Blotter No..."
                        onkeyup="filterBlotterTable()"
                        style="max-width: 300px;"
                >
                <button class="btn btn-outline-secondary btn-sm" onclick="resetBlotterSearch()" title="Reset Search">
                        <i class="bi bi-arrow-counterclockwise"></i> Reset
                </button>
        </div>
</div>

<div class="table-responsive">
<table class="table table-hover align-middle">
<thead>
<tr>
        <th>Blotter No</th>
        <th>Incident</th>
        <th>Complainant</th>
        <th>Status</th>
        <th>Priority</th>
        <th>Date</th>
        <th class="text-center">Actions</th>
</tr>
</thead>
<tbody>
<?php if (empty($blotters)): ?>
<tr>
    <td colspan="7" class="text-center py-5">
        <div class="text-muted">
            <i class="bi bi-inbox" style="font-size: 2rem;"></i>
            <p class="mt-3">No blotter records found.</p>
            <small>Click "New Blotter" to create your first blotter record.</small>
        </div>
    </td>
</tr>
<?php else: ?>
<?php foreach ($blotters as $b): ?>
<tr>
        <td class="fw-bold"><?= htmlspecialchars($b['blotter_no']) ?></td>
        <td><?= htmlspecialchars($b['incident_type']) ?></td>
        <td><?= htmlspecialchars($b['complainant_name']) ?></td>
        <td><?= render_status_badge($b['status']) ?></td>
        <td><?= render_priority_badge($b['priority']) ?></td>
        <td><?= $b['incident_date'] ? date('M d, Y', strtotime($b['incident_date'])) : '' ?></td>
        <td class="text-center">
            <div class="btn-group btn-group-sm">
                <a href="blotter_view.php?id=<?= intval($b['id']) ?>" class="btn btn-outline-info" title="View Blotter Report"><i class="bi bi-eye"></i> View</a>
                <?php
                    $userRole = strtolower($_SESSION['role'] ?? '');
                    $currentUserId = $_SESSION['user_id'] ?? null;
                    $canEdit = false;
                    
                    // Check if admin or if user created this blotter
                    if ($userRole === 'admin') {
                        $canEdit = true;
                    } elseif ($currentUserId && isset($b['created_by']) && intval($b['created_by']) === intval($currentUserId)) {
                        $canEdit = true;
                    } elseif ($currentUserId && isset($b['officer_id']) && intval($b['officer_id']) === intval($currentUserId)) {
                        $canEdit = true;
                    }
                ?>
                <?php if ($canEdit): ?>
                    <a href="blotter_update.php?id=<?= intval($b['id']) ?>" class="btn btn-outline-success" title="Edit Blotter"><i class="bi bi-pencil"></i> Edit</a>
                    <button class="btn btn-outline-danger" onclick="showActionModal('Archive',<?= intval($b['id']) ?>,'<?= htmlspecialchars($b['blotter_no'], ENT_QUOTES) ?>','archive')"><i class="bi bi-archive"></i> Archive</button>
                    <button class="btn btn-outline-primary" onclick="printBlotter(<?= intval($b['id']) ?>, '<?= htmlspecialchars($b['blotter_no'], ENT_QUOTES) ?>')"><i class="bi bi-printer"></i> Print</button>
                <?php else: ?>
                    <span class="text-muted small">View only</span>
                <?php endif; ?>
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

<!-- Modal -->
<div class="modal fade" id="actionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="blotterForm" method="post" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title" id="actionTitle">Blotter</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" id="formAction" value="create">
                    <input type="hidden" name="id" id="blotterId" value="">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Complainant</label>
                            <input type="text" name="complainant_name" id="complainant_name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Respondent</label>
                            <input type="text" name="respondent_name" id="respondent_name" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Respondent Contact / Email</label>
                            <div class="d-flex gap-2">
                                <input type="text" name="respondent_contact" id="respondent_contact" class="form-control" placeholder="Phone">
                                <input type="email" name="respondent_email" id="respondent_email" class="form-control" placeholder="Email">
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Respondent Home Address</label>
                            <input type="text" name="respondent_address" id="respondent_address" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"></label>Incident Type</label>
                            <input type="text" name="incident_type" id="incident_type" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Date</label>
                            <input type="date" name="incident_date" id="incident_date" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Time</label>
                            <input type="time" name="incident_time" id="incident_time" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Location</label>
                            <input type="text" name="location" id="location" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea name="description" id="description" class="form-control" rows="4" required></textarea>
                        </div>
                        <!-- Hidden priority field - automatically detected -->
                        <input type="hidden" name="priority" id="priority" value="Medium">
                        
                        <div class="col-md-4">
                            <label class="form-label">Priority Level</label>
                            <div class="d-flex align-items-center gap-2">
                                <span id="priority_display" class="badge bg-warning p-2" style="font-size: 1rem; white-space: nowrap;">🔹 Medium</span>
                                <small class="text-muted">(Auto-detected from incident type)</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Status</label>
                            <select name="status" id="status" class="form-select">
                                <option>Pending</option>
                                <option>Approved</option>
                                <option></option>Under Investigation</option>
                                <option>Resolved</option>
                                <option>Archived</option>
                            </select>
                        </div>
                        <?php if ($userRole === 'admin'): ?>
                        <div class="col-md-4">
                            <label class="form-label">Officer</label>
                            <select name="officer_id" id="officer_id" class="form-select">
                                <option value="">-- Unassigned --</option>
                                <?php foreach ($officers as $o): ?>
                                    <option value="<?= intval($o['id']) ?>"><?= htmlspecialchars($o['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Attachments Section -->
                    <div class="mt-3" id="attachmentsSection" style="display: none;">
                        <h6 class="mb-3">Attachments</h6>
                        <div id="modalAttachmentsContainer">
                            <!-- Attachments will be loaded here -->
                        </div>
                        <button type="button" class="btn btn-outline-primary btn-sm mt-2" onclick="addModalAttachment()">
                            <i class="bi bi-plus-circle"></i> Add File
                        </button>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" id="submitBtn" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Absolute endpoint to this module file so AJAX works when included from other pages
    <?php
    $module_endpoint = str_replace('\\','/', str_replace($_SERVER['DOCUMENT_ROOT'], '', __FILE__));
    ?>
    const moduleEndpoint = '<?= $module_endpoint ?>';
    const actionModalEl = document.getElementById('actionModal');
    const bsModal = new bootstrap.Modal(actionModalEl);

    async function showActionModal(title, id, blotterNo, mode) {
        document.getElementById('actionTitle').textContent = title + (blotterNo ? ' — ' + blotterNo : '');
        const form = document.getElementById('blotterForm');
        form.reset();
        document.getElementById('blotterId').value = '';
        document.getElementById('formAction').value = mode === 'create' ? 'create' : (mode === 'update' ? 'update' : 'archive');

        if (mode === 'create') {
            document.getElementById('submitBtn').textContent = 'Create';
            document.getElementById('status').value = 'Pending';
            bsModal.show();
            return;
        }

        if (mode === 'view' || mode === 'update') {
            // fetch record from this module's endpoint (works when this file is included)
            const resp = await fetch(moduleEndpoint + '?action=view&id=' + id);
            const data = await resp.json();
            if (data) {
                document.getElementById('complainant_name').value = data.complainant_name ?? '';
                document.getElementById('respondent_name').value = data.respondent_name ?? '';
                document.getElementById('respondent_contact').value = data.respondent_contact ?? '';
                document.getElementById('respondent_email').value = data.respondent_email ?? '';
                document.getElementById('respondent_address').value = data.respondent_address ?? '';
                document.getElementById('incident_type').value = data.incident_type ?? '';
                document.getElementById('incident_date').value = data.incident_date ?? '';
                document.getElementById('incident_time').value = data.incident_time ?? '';
                document.getElementById('location').value = data.location ?? '';
                document.getElementById('description').value = data.description ?? '';
                document.getElementById('priority').value = data.priority ?? 'Medium';
                document.getElementById('status').value = data.status ?? 'Pending';
                const officerEl = document.getElementById('officer_id');
                if (officerEl) {
                    officerEl.value = data.officer_id ?? '';
                }
                document.getElementById('blotterId').value = data.id ?? '';
            }
            document.getElementById('submitBtn').style.display = mode === 'update' ? '' : 'none';
            document.getElementById('submitBtn').textContent = mode === 'update' ? 'Update' : 'Close';

            // if view, disable inputs
            const inputs = form.querySelectorAll('input,textarea,select');
            inputs.forEach(i => { i.disabled = (mode === 'view'); });

            bsModal.show();
            return;
        }

        if (mode === 'archive') {
            if (!confirm('Archive blotter ' + blotterNo + '?')) return;
            const frm = document.createElement('form');
            frm.method = 'post';
            frm.style.display = 'none';
            const a = document.createElement('input'); a.name = 'action'; a.value = 'archive'; frm.appendChild(a);
            const i = document.createElement('input'); i.name = 'id'; i.value = id; frm.appendChild(i);
            document.body.appendChild(frm);
            frm.submit();
        }
    }

    // Ensure form action is correct on submit (archive handled separately)
    document.getElementById('blotterForm').addEventListener('submit', function(e){
        document.getElementById('formAction').value = document.getElementById('formAction').value || 'create';
    });

    // Handle New Blotter button click
    const btnNewBlotter = document.getElementById('btnNewBlotter');
    if (btnNewBlotter) {
        btnNewBlotter.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            showActionModal('Create', 0, '', 'create');
        });
    }

    // Print blotter function
    function printBlotter(blotterId, blotterNo) {
        const printWindow = window.open('blotter_view.php?id=' + blotterId + '&print=1', 'PrintBlotter', 'height=800,width=1000');
        printWindow.addEventListener('load', function() {
            setTimeout(function() {
                printWindow.print();
            }, 500);
        });
    }

    // Filter blotter table by Blotter No
    function filterBlotterTable() {
        const searchInput = document.getElementById('blotterSearch').value.toUpperCase();
        const table = document.querySelector('table tbody');
        const rows = table.getElementsByTagName('tr');
        let visibleCount = 0;

        for (let i = 0; i < rows.length; i++) {
            const blotterCell = rows[i].getElementsByTagName('td')[0];
            if (blotterCell) {
                const blotterNo = blotterCell.textContent.toUpperCase();
                if (blotterNo.indexOf(searchInput) > -1) {
                    rows[i].style.display = '';
                    visibleCount++;
                } else {
                    rows[i].style.display = 'none';
                }
            }
        }

        // Update record count
        updateBlotterCount(visibleCount);
    }

    // Reset blotter search
    function resetBlotterSearch() {
        document.getElementById('blotterSearch').value = '';
        const table = document.querySelector('table tbody');
        const rows = table.getElementsByTagName('tr');

        for (let i = 0; i < rows.length; i++) {
            rows[i].style.display = '';
        }

        updateBlotterCount(rows.length);
    }

    // Update blotter record count
    function updateBlotterCount(count) {
        const headerText = document.querySelector('.card-header h5');
        if (headerText) {
            headerText.textContent = 'Blotter Records (' + count + ')';
        }
    }

    // Attachment management functions
    function addModalAttachment() {
        const container = document.getElementById('modalAttachmentsContainer');
        const attachmentDiv = document.createElement('div');
        attachmentDiv.className = 'attachment-item border rounded p-3 mb-3';
        attachmentDiv.innerHTML = `
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">File</label>
                    <input type="file" name="attachments[]" class="form-control" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt">
                </div>
                <div class="col-md-5">
                    <label class="form-label">Description</label>
                    <input type="text" name="attachment_descriptions[]" class="form-control" placeholder="Brief description">
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button type="button" class="btn btn-outline-danger" onclick="removeModalAttachment(this)">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>
        `;
        container.appendChild(attachmentDiv);
        document.getElementById('attachmentsSection').style.display = 'block';
    }

    function removeModalAttachment(button) {
        button.closest('.attachment-item').remove();
        const container = document.getElementById('modalAttachmentsContainer');
        if (container.children.length === 0) {
            document.getElementById('attachmentsSection').style.display = 'none';
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

<?php require '../includes/footer.php'; ?>

