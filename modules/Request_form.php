<?php
session_start();
require_once '../config/db_connect.php';
require_once '../config/LanguageManager.php';

// Only allow authenticated admin users to submit CCTV requests
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../index.php');
    exit();
}

$page_title = 'CCTV Request Form';
$base_url = '../';
$current_page = 'request_form';
require_once '../includes/header.php';
require_once '../includes/navbar.php';

$message = '';
$message_type = 'info';
$submitted = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $request_type = trim($_POST['request_type'] ?? '');
    $camera_location = trim($_POST['camera_location'] ?? '');
    $incident_date = trim($_POST['incident_date'] ?? '');
    $incident_time = trim($_POST['incident_time'] ?? '');
    $priority = trim($_POST['priority'] ?? 'Normal');
    $reason = trim($_POST['reason'] ?? '');
    $additional_details = trim($_POST['additional_details'] ?? '');

    if ($request_type === '' || $reason === '') {
        $message = 'Please select a request type and provide the reason for the request.';
        $message_type = 'danger';
    } else {
        try {
            $create_sql = "CREATE TABLE IF NOT EXISTS cctv_requests (
                id int(11) NOT NULL AUTO_INCREMENT,
                requested_by int(11) NOT NULL,
                request_type enum('Footage','Capture Photo') NOT NULL,
                camera_location varchar(255) DEFAULT NULL,
                incident_date date DEFAULT NULL,
                incident_time time DEFAULT NULL,
                priority enum('High','Normal','Low') NOT NULL DEFAULT 'Normal',
                reason text NOT NULL,
                additional_details text DEFAULT NULL,
                status enum('Pending','Approved','Rejected','Completed') NOT NULL DEFAULT 'Pending',
                requested_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT NULL,
                PRIMARY KEY (id),
                KEY requested_by (requested_by),
                CONSTRAINT cctv_requests_ibfk_1 FOREIGN KEY (requested_by) REFERENCES signup (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
            $pdo->exec($create_sql);

            $insert_stmt = $pdo->prepare("INSERT INTO cctv_requests
                (requested_by, request_type, camera_location, incident_date, incident_time, priority, reason, additional_details)
                VALUES (:requested_by, :request_type, :camera_location, :incident_date, :incident_time, :priority, :reason, :additional_details)");

            $insert_stmt->execute([
                ':requested_by' => $_SESSION['user_id'],
                ':request_type' => $request_type,
                ':camera_location' => $camera_location !== '' ? $camera_location : null,
                ':incident_date' => $incident_date !== '' ? $incident_date : null,
                ':incident_time' => $incident_time !== '' ? $incident_time : null,
                ':priority' => in_array($priority, ['High', 'Normal', 'Low'], true) ? $priority : 'Normal',
                ':reason' => $reason,
                ':additional_details' => $additional_details !== '' ? $additional_details : null,
            ]);

            $message = 'Your CCTV request has been submitted successfully.';
            $message_type = 'success';
            $submitted = true;
        } catch (Exception $e) {
            $message = 'Could not submit CCTV request: ' . htmlspecialchars($e->getMessage());
            $message_type = 'danger';
        }
    }
}
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h4 class="card-title mb-0"><i class="bi bi-camera-reels me-2"></i>CCTV Request Form</h4>
                </div>
                <div class="card-body">
                    <p class="text-secondary">Use this form to request video footage or a captured still image from the monitoring/CCTV system.</p>

                    <?php if ($message): ?>
                        <div class="alert alert-<?php echo htmlspecialchars($message_type); ?>" role="alert">
                            <?php echo htmlspecialchars($message); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" class="row g-3">
                        <div class="col-md-6">
                            <label for="request_type" class="form-label">Request Type *</label>
                            <select id="request_type" name="request_type" class="form-select" required>
                                <option value="" <?php echo empty($_POST['request_type']) ? 'selected' : ''; ?>>Select request type</option>
                                <option value="Footage" <?php echo ($_POST['request_type'] ?? '') === 'Footage' ? 'selected' : ''; ?>>Footage</option>
                                <option value="Capture Photo" <?php echo ($_POST['request_type'] ?? '') === 'Capture Photo' ? 'selected' : ''; ?>>Capture Photo</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="priority" class="form-label">Priority</label>
                            <select id="priority" name="priority" class="form-select">
                                <option value="High" <?php echo ($_POST['priority'] ?? '') === 'High' ? 'selected' : ''; ?>>High</option>
                                <option value="Normal" <?php echo ($_POST['priority'] ?? '') === 'Normal' ? 'selected' : ''; ?>>Normal</option>
                                <option value="Low" <?php echo ($_POST['priority'] ?? '') === 'Low' ? 'selected' : ''; ?>>Low</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="camera_location" class="form-label">Camera Location</label>
                            <input type="text" id="camera_location" name="camera_location" class="form-control" placeholder="e.g. Entrance Gate, Main Lobby" value="<?php echo htmlspecialchars($_POST['camera_location'] ?? ''); ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="incident_date" class="form-label">Incident Date</label>
                            <input type="date" id="incident_date" name="incident_date" class="form-control" value="<?php echo htmlspecialchars($_POST['incident_date'] ?? ''); ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="incident_time" class="form-label">Incident Time</label>
                            <input type="time" id="incident_time" name="incident_time" class="form-control" value="<?php echo htmlspecialchars($_POST['incident_time'] ?? ''); ?>">
                        </div>
                        <div class="col-12">
                            <label for="reason" class="form-label">Reason for Request *</label>
                            <textarea id="reason" name="reason" class="form-control" rows="4" required><?php echo htmlspecialchars($_POST['reason'] ?? ''); ?></textarea>
                        </div>
                        <div class="col-12">
                            <label for="additional_details" class="form-label">Additional Details</label>
                            <textarea id="additional_details" name="additional_details" class="form-control" rows="3"><?php echo htmlspecialchars($_POST['additional_details'] ?? ''); ?></textarea>
                        </div>
                        <div class="col-12 d-flex justify-content-between align-items-center">
                            <a href="../index.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left me-2"></i>Back
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-send me-2"></i>Submit Request
                            </button>
                        </div>
                    </form>

                    <div class="mt-4 alert alert-light border">
                        <p class="mb-1"><strong>Note:</strong> CCTV requests are logged and routed to the monitoring team. Provide as much location and time detail as possible.</p>
                        <p class="mb-0">If you require a captured photo, choose <strong>Capture Photo</strong>. For recorded video, choose <strong>Footage</strong>.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
