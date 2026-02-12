<?php
/**
 * Officer Management Panel
 * Allow officers to add and manage BCPC officers and Barangay Officials
 */

session_start();
require_once '../config/db_connect.php';
require_once '../includes/header.php';

// Check if user is logged in and is an Officer
if (!isset($_SESSION['user_id']) || (isset($_SESSION['role']) && strtolower($_SESSION['role']) !== 'officer')) {
    echo "<div class='alert alert-danger'>Access Denied. This page is for Officer accounts only.</div>";
    require_once '../includes/footer.php';
    exit;
}

$officer_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

// Handle adding BCPC Officer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_bcpc') {
    try {
        $username = trim($_POST['username'] ?? '');
        $fullname = trim($_POST['fullname'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $barangay = trim($_POST['barangay'] ?? '');
        $rank = trim($_POST['rank'] ?? '');
        $specialization = trim($_POST['specialization'] ?? '');
        $contact = trim($_POST['contact'] ?? '');

        // Validate inputs
        if (empty($username) || empty($fullname) || empty($email) || empty($password) || empty($barangay)) {
            throw new Exception('Please fill in all required fields');
        }

        // Check if username already exists
        $check_stmt = $pdo->prepare("SELECT user_id FROM signup WHERE username = ?");
        $check_stmt->execute([$username]);
        if ($check_stmt->rowCount() > 0) {
            throw new Exception('Username already exists');
        }

        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Create user account in signup table
        $insert_user = "INSERT INTO signup (fullname, emailadd, username, password, role, email_verified, terms_accepted, created_at) 
                        VALUES (?, ?, ?, ?, 'Officer', 1, 1, NOW())";
        $stmt = $pdo->prepare($insert_user);
        $stmt->execute([$fullname, $email, $username, $hashed_password]);
        $new_user_id = $pdo->lastInsertId();

        // Add to bcpc_officers table
        $add_bcpc = "INSERT INTO bcpc_officers (user_id, barangay, rank, specialization, contact_number, is_available, current_case_load, max_case_load) 
                     VALUES (?, ?, ?, ?, ?, 1, 0, 10)";
        $stmt2 = $pdo->prepare($add_bcpc);
        $stmt2->execute([$new_user_id, $barangay, $rank, $specialization, $contact]);

        $message = "✅ BCPC Officer '{$fullname}' has been added successfully!";
        $message_type = 'success';
    } catch (Exception $e) {
        $message = "❌ Error: " . $e->getMessage();
        $message_type = 'danger';
    }
}

// Handle assigning officer to cases/blotters
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'assign_officer') {
    try {
        $officer_id = intval($_POST['officer_id'] ?? 0);
        $blotter_id = intval($_POST['blotter_id'] ?? 0);
        
        if ($officer_id <= 0 || $blotter_id <= 0) {
            throw new Exception('Invalid officer or case selection');
        }

        // Get blotter details for case_assignments
        $blotter_stmt = $pdo->prepare("SELECT blotter_no, complainant_name, respondent_name, incident_type, location, description, priority FROM blotters WHERE id = ?");
        $blotter_stmt->execute([$blotter_id]);
        $blotter = $blotter_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$blotter) {
            throw new Exception('Case not found');
        }

        // Start transaction for data consistency
        $pdo->beginTransaction();

        // Update blotter with assigned officer
        $update_stmt = $pdo->prepare("UPDATE blotters SET officer_id = ?, status = 'Under Investigation', updated_at = NOW() WHERE id = ?");
        $update_stmt->execute([$officer_id, $blotter_id]);

        // Create/Update entry in case_assignments table
        $case_no = 'CASE-' . time() . '-' . rand(100, 999);
        $check_case = $pdo->prepare("SELECT id FROM case_assignments WHERE case_number = ? LIMIT 1");
        $check_case->execute([$blotter['blotter_no']]);
        
        if ($check_case->rowCount() === 0) {
            // Create new case assignment
            $insert_case = "INSERT INTO case_assignments 
                            (case_number, incident_type, complainant_name, respondent_name, location, description, priority, assigned_by, assigned_to, status, assignment_date) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Ongoing', NOW())";
            $case_stmt = $pdo->prepare($insert_case);
            $case_stmt->execute([
                $blotter['blotter_no'],
                $blotter['incident_type'],
                $blotter['complainant_name'],
                $blotter['respondent_name'] ?? null,
                $blotter['location'] ?? null,
                $blotter['description'],
                $blotter['priority'],
                $_SESSION['user_id'],
                $officer_id
            ]);
        } else {
            // Update existing case assignment
            $update_case = "UPDATE case_assignments SET assigned_to = ?, status = 'Ongoing', assignment_date = NOW() WHERE case_number = ?";
            $case_stmt = $pdo->prepare($update_case);
            $case_stmt->execute([$officer_id, $blotter['blotter_no']]);
        }

        // Update officer's case load
        $load_stmt = $pdo->prepare("UPDATE bcpc_officers SET current_case_load = current_case_load + 1 WHERE user_id = ?");
        $load_stmt->execute([$officer_id]);

        // Create audit log entry
        $audit_table_check = $pdo->query("SHOW TABLES LIKE 'assignment_audit_log'");
        if ($audit_table_check->rowCount() === 0) {
            // Create audit log table if it doesn't exist
            $pdo->exec("
                CREATE TABLE `assignment_audit_log` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `blotter_id` INT NOT NULL,
                    `case_number` VARCHAR(50),
                    `officer_id` INT NOT NULL,
                    `assigned_by` INT NOT NULL,
                    `assigned_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    `notes` TEXT,
                    INDEX idx_blotter (blotter_id),
                    INDEX idx_officer (officer_id),
                    INDEX idx_assigned_by (assigned_by)
                )
            ");
        }

        // Log the assignment
        $audit_stmt = $pdo->prepare("INSERT INTO assignment_audit_log (blotter_id, case_number, officer_id, assigned_by, notes) VALUES (?, ?, ?, ?, ?)");
        $audit_stmt->execute([$blotter_id, $blotter['blotter_no'], $officer_id, $_SESSION['user_id'], 'Officer assigned to case']);

        // Commit transaction
        $pdo->commit();

        $message = "✅ Officer successfully assigned to case {$blotter['blotter_no']}! Case load updated.";
        $message_type = 'success';
    } catch (Exception $e) {
        // Rollback on error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $message = "❌ Error: " . $e->getMessage();
        $message_type = 'danger';
        error_log("Assignment error: " . $e->getMessage());
    }
}

// Handle adding Barangay Official
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_barangay') {
    try {
        $username = trim($_POST['username'] ?? '');
        $fullname = trim($_POST['fullname'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $barangay_name = trim($_POST['barangay_name'] ?? '');
        $position = trim($_POST['position'] ?? '');
        $contact = trim($_POST['contact'] ?? '');

        // Validate inputs
        if (empty($username) || empty($fullname) || empty($email) || empty($password) || empty($barangay_name) || empty($position)) {
            throw new Exception('Please fill in all required fields');
        }

        // Check if username already exists
        $check_stmt = $pdo->prepare("SELECT user_id FROM signup WHERE username = ?");
        $check_stmt->execute([$username]);
        if ($check_stmt->rowCount() > 0) {
            throw new Exception('Username already exists');
        }

        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Create user account
        $insert_user = "INSERT INTO signup (fullname, emailadd, username, password, role, email_verified, terms_accepted, created_at) 
                        VALUES (?, ?, ?, ?, 'Barangay Official', 1, 1, NOW())";
        $stmt = $pdo->prepare($insert_user);
        $stmt->execute([$fullname, $email, $username, $hashed_password]);
        $new_user_id = $pdo->lastInsertId();

        // Check if barangay_officials table exists, if not create it
        $check_table = $pdo->query("SHOW TABLES LIKE 'barangay_officials'");
        if ($check_table->rowCount() === 0) {
            $pdo->exec("
                CREATE TABLE `barangay_officials` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `user_id` INT UNIQUE NOT NULL,
                    `barangay_name` VARCHAR(150) NOT NULL,
                    `position` VARCHAR(100) NOT NULL,
                    `contact_number` VARCHAR(20),
                    `is_active` BOOLEAN DEFAULT TRUE,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )
            ");
        }

        // Add to barangay_officials table
        $add_barangay = "INSERT INTO barangay_officials (user_id, barangay_name, position, contact_number, is_active) 
                         VALUES (?, ?, ?, ?, 1)";
        $stmt2 = $pdo->prepare($add_barangay);
        $stmt2->execute([$new_user_id, $barangay_name, $position, $contact]);

        $message = "✅ Barangay Official '{$fullname}' has been added successfully!";
        $message_type = 'success';
    } catch (Exception $e) {
        $message = "❌ Error: " . $e->getMessage();
        $message_type = 'danger';
    }
}

// Fetch existing BCPC Officers
$bcpc_officers = $pdo->query("
    SELECT b.*, s.fullname, s.emailadd 
    FROM bcpc_officers b
    LEFT JOIN signup s ON b.user_id = s.user_id
    ORDER BY b.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch existing Barangay Officials
$barangay_check = $pdo->query("SHOW TABLES LIKE 'barangay_officials'");
$barangay_officials = [];
if ($barangay_check->rowCount() > 0) {
    $barangay_officials = $pdo->query("
        SELECT b.*, s.fullname, s.emailadd 
        FROM barangay_officials b
        LEFT JOIN signup s ON b.user_id = s.user_id
        ORDER BY b.created_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch unassigned blotters (cases/incidents)
$unassigned_blotters = $pdo->query("
    SELECT id, blotter_no, complainant_name, incident_type, priority, created_at 
    FROM blotters 
    WHERE officer_id IS NULL AND status != 'Archived' 
    ORDER BY priority DESC, created_at DESC 
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch recently assigned cases (last 5)
$recently_assigned = $pdo->query("
    SELECT b.id, b.blotter_no, b.complainant_name, b.incident_type, b.priority, s.fullname as officer_name, b.updated_at 
    FROM blotters b
    LEFT JOIN signup s ON b.officer_id = s.user_id
    WHERE b.officer_id IS NOT NULL AND b.status IN ('Under Investigation', 'Ongoing')
    ORDER BY b.updated_at DESC 
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-md-12">
            <h2><i class="bi bi-person-badge"></i> Officer Management Panel</h2>
            <p class="text-muted">Add and manage BCPC Officers and Barangay Officials</p>
            
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row mt-4">
        <!-- Add BCPC Officer Form -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-person-plus"></i> Add BCPC Officer</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="add_bcpc">
                        
                        <div class="mb-3">
                            <label for="bcpc_fullname" class="form-label">Full Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="bcpc_fullname" name="fullname" required>
                        </div>

                        <div class="mb-3">
                            <label for="bcpc_username" class="form-label">Username <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="bcpc_username" name="username" required>
                        </div>

                        <div class="mb-3">
                            <label for="bcpc_email" class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="bcpc_email" name="email" required>
                        </div>

                        <div class="mb-3">
                            <label for="bcpc_password" class="form-label">Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="bcpc_password" name="password" required>
                        </div>

                        <div class="mb-3">
                            <label for="bcpc_barangay" class="form-label">Barangay <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="bcpc_barangay" name="barangay" placeholder="e.g., Barangay 1" required>
                        </div>

                        <div class="mb-3">
                            <label for="bcpc_rank" class="form-label">Rank</label>
                            <input type="text" class="form-control" id="bcpc_rank" name="rank" placeholder="e.g., Senior Officer, Officer">
                        </div>

                        <div class="mb-3">
                            <label for="bcpc_specialization" class="form-label">Specialization</label>
                            <input type="text" class="form-control" id="bcpc_specialization" name="specialization" placeholder="e.g., Theft, Assault, etc.">
                        </div>

                        <div class="mb-3">
                            <label for="bcpc_contact" class="form-label">Contact Number</label>
                            <input type="tel" class="form-control" id="bcpc_contact" name="contact" placeholder="09123456789">
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-plus-circle"></i> Add BCPC Officer
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Add Barangay Official Form -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="bi bi-building"></i> Add Barangay Official</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="add_barangay">
                        
                        <div class="mb-3">
                            <label for="barangay_fullname" class="form-label">Full Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="barangay_fullname" name="fullname" required>
                        </div>

                        <div class="mb-3">
                            <label for="barangay_username" class="form-label">Username <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="barangay_username" name="username" required>
                        </div>

                        <div class="mb-3">
                            <label for="barangay_email" class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="barangay_email" name="email" required>
                        </div>

                        <div class="mb-3">
                            <label for="barangay_password" class="form-label">Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="barangay_password" name="password" required>
                        </div>

                        <div class="mb-3">
                            <label for="barangay_name" class="form-label">Barangay Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="barangay_name" name="barangay_name" placeholder="e.g., Barangay 1" required>
                        </div>

                        <div class="mb-3">
                            <label for="barangay_position" class="form-label">Position <span class="text-danger">*</span></label>
                            <select class="form-control" id="barangay_position" name="position" required>
                                <option value="">-- Select Position --</option>
                                <option value="Barangay Chairperson">Barangay Chairperson</option>
                                <option value="Barangay Secretary">Barangay Secretary</option>
                                <option value="Barangay Treasurer">Barangay Treasurer</option>
                                <option value="Barangay Kagawad">Barangay Kagawad</option>
                                <option value="Barangay Health Worker">Barangay Health Worker</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="barangay_contact" class="form-label">Contact Number</label>
                            <input type="tel" class="form-control" id="barangay_contact" name="contact" placeholder="09123456789">
                        </div>

                        <button type="submit" class="btn btn-success w-100">
                            <i class="bi bi-plus-circle"></i> Add Barangay Official
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- BCPC Officers List -->
    <div class="row mt-5">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-list"></i> BCPC Officers List</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($bcpc_officers)): ?>
                        <p class="text-muted">No BCPC officers added yet.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Full Name</th>
                                        <th>Barangay</th>
                                        <th>Rank</th>
                                        <th>Specialization</th>
                                        <th>Contact</th>
                                        <th>Status</th>
                                        <th>Case Load</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($bcpc_officers as $officer): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($officer['fullname'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($officer['barangay']); ?></td>
                                            <td><?php echo htmlspecialchars($officer['rank']); ?></td>
                                            <td><?php echo htmlspecialchars($officer['specialization'] ?? 'General'); ?></td>
                                            <td><?php echo htmlspecialchars($officer['contact_number'] ?? '-'); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $officer['is_available'] ? 'success' : 'warning'; ?>">
                                                    <?php echo $officer['is_available'] ? 'Available' : 'Unavailable'; ?>
                                                </span>
                                            </td>
                                            <td><?php echo $officer['current_case_load']; ?>/<?php echo $officer['max_case_load']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Barangay Officials List -->
    <div class="row mt-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="bi bi-list"></i> Barangay Officials List</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($barangay_officials)): ?>
                        <p class="text-muted">No Barangay officials added yet.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Full Name</th>
                                        <th>Barangay</th>
                                        <th>Position</th>
                                        <th>Contact</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($barangay_officials as $official): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($official['fullname'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($official['barangay_name']); ?></td>
                                            <td><?php echo htmlspecialchars($official['position']); ?></td>
                                            <td><?php echo htmlspecialchars($official['contact_number'] ?? '-'); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $official['is_active'] ? 'success' : 'secondary'; ?>">
                                                    <?php echo $official['is_active'] ? 'Active' : 'Inactive'; ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Assign Officers to Unassigned Cases -->
    <div class="row mt-5">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0"><i class="bi bi-arrow-left-right"></i> Assign Officers to Cases</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($unassigned_blotters)): ?>
                        <p class="text-muted"><i class="bi bi-check-circle"></i> All cases have been assigned!</p>
                    <?php else: ?>
                        <p class="text-muted">Unassigned cases: <strong><?php echo count($unassigned_blotters); ?></strong></p>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead class="table-light">
                                    <tr>
                                        <th>Blotter No</th>
                                        <th>Complainant</th>
                                        <th>Incident Type</th>
                                        <th>Priority</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($unassigned_blotters as $blotter): ?>
                                        <tr>
                                            <td><code><?php echo htmlspecialchars($blotter['blotter_no']); ?></code></td>
                                            <td><?php echo htmlspecialchars($blotter['complainant_name']); ?></td>
                                            <td><?php echo htmlspecialchars($blotter['incident_type']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $blotter['priority'] === 'High' ? 'danger' : ($blotter['priority'] === 'Medium' ? 'warning' : 'info'); ?>">
                                                    <?php echo htmlspecialchars($blotter['priority']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#assignModal" 
                                                    onclick="setAssignBlotterId(<?php echo $blotter['id']; ?>, '<?php echo htmlspecialchars($blotter['blotter_no']); ?>')">
                                                    <i class="bi bi-person-plus"></i> Assign
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Recently Assigned Cases -->
    <div class="row mt-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="bi bi-clipboard-check"></i> Recently Assigned Cases</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($recently_assigned)): ?>
                        <p class="text-muted">No assignments yet.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead class="table-light">
                                    <tr>
                                        <th>Blotter No</th>
                                        <th>Complainant</th>
                                        <th>Incident Type</th>
                                        <th>Assigned Officer</th>
                                        <th>Priority</th>
                                        <th>Assigned Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recently_assigned as $case): ?>
                                        <tr>
                                            <td><code><?php echo htmlspecialchars($case['blotter_no']); ?></code></td>
                                            <td><?php echo htmlspecialchars($case['complainant_name']); ?></td>
                                            <td><?php echo htmlspecialchars($case['incident_type']); ?></td>
                                            <td>
                                                <span class="badge bg-primary">
                                                    <?php echo htmlspecialchars($case['officer_name'] ?? 'Unassigned'); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $case['priority'] === 'High' ? 'danger' : ($case['priority'] === 'Medium' ? 'warning' : 'info'); ?>">
                                                    <?php echo htmlspecialchars($case['priority']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M d, Y H:i', strtotime($case['updated_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4 mb-4">
        <div class="col-md-12">
            <a href="../index.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>
</div>

<!-- Assign Officer Modal -->
<div class="modal fade" id="assignModal" tabindex="-1" aria-labelledby="assignModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="assignModalLabel">Assign Officer to Case</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="assign_officer">
                <input type="hidden" id="blotter_id" name="blotter_id">
                
                <div class="modal-body">
                    <p><strong>Case:</strong> <span id="blotter_no_display"></span></p>
                    
                    <div class="mb-3">
                        <label for="officer_id" class="form-label">Select Officer <span class="text-danger">*</span></label>
                        <select class="form-control" id="officer_id" name="officer_id" required>
                            <option value="">-- Choose an Officer --</option>
                            <?php foreach ($bcpc_officers as $officer): ?>
                                <option value="<?php echo $officer['user_id']; ?>">
                                    <?php echo htmlspecialchars($officer['fullname'] ?? 'N/A'); ?> 
                                    (<?php echo htmlspecialchars($officer['barangay']); ?>) 
                                    - Load: <?php echo $officer['current_case_load']; ?>/<?php echo $officer['max_case_load']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle"></i> Assign Officer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    .container-fluid {
        background: var(--primary-bg);
    }
    
    .card {
        border: none;
        box-shadow: var(--shadow-md);
        margin-bottom: 1.5rem;
    }
    
    .card-header {
        border-bottom: 2px solid rgba(0,0,0,0.1);
        padding: 1.25rem;
    }
    
    .table-responsive {
        border-radius: var(--border-radius-sm);
    }
</style>

<script>
    function setAssignBlotterId(blotterId, blotterNo) {
        document.getElementById('blotter_id').value = blotterId;
        document.getElementById('blotter_no_display').textContent = blotterNo;
        document.getElementById('officer_id').value = '';
    }
</script>

<?php require_once '../includes/footer.php'; ?>
