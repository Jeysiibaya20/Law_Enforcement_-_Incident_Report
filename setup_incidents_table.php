<?php
/**
 * Database Migration: Incidents Table Setup
 * Creates or updates incidents table with classification fields
 */

require_once __DIR__ . '/config/db_connect.php';

$results = [];

try {
    // Drop existing table if it exists (for fresh setup)
    // Uncomment if you want to reset: $pdo->query("DROP TABLE IF EXISTS incidents");

    // Create incidents table with all required fields
    $sql = "CREATE TABLE IF NOT EXISTS incidents (
        id INT AUTO_INCREMENT PRIMARY KEY,
        case_no VARCHAR(30) UNIQUE,
        incident_type ENUM('Abuse', 'Neglect', 'Violence', 'Theft', 'Assault', 'Domestic', 'Other') NOT NULL DEFAULT 'Other',
        incident_subtype VARCHAR(100),
        auto_classification VARCHAR(100) COMMENT 'Auto-classified type based on narrative',
        manual_classification VARCHAR(100) COMMENT 'Admin-corrected classification',
        urgency_level ENUM('Low', 'Medium', 'High', 'Critical') DEFAULT 'Medium',
        is_high_risk TINYINT(1) DEFAULT 0 COMMENT 'Automatically flagged if contains violence/abuse keywords',
        reporter_name VARCHAR(150) NOT NULL,
        reporter_email VARCHAR(150),
        reporter_phone VARCHAR(20),
        reporter_type ENUM('Parent', 'Citizen', 'Officer', 'Organization') DEFAULT 'Citizen',
        incident_date DATE NOT NULL,
        incident_time TIME,
        incident_datetime DATETIME GENERATED ALWAYS AS (CONCAT(incident_date, ' ', COALESCE(incident_time, '00:00:00'))) STORED,
        location VARCHAR(255),
        latitude DECIMAL(10, 8),
        longitude DECIMAL(11, 8),
        narrative TEXT NOT NULL,
        evidence_description TEXT,
        victim_name VARCHAR(150),
        victim_age INT,
        victim_gender ENUM('Male', 'Female', 'Other'),
        suspect_name VARCHAR(150),
        status ENUM('Draft', 'Submitted', 'Under Review', 'Verified', 'Resolved', 'Closed', 'Archived') DEFAULT 'Draft',
        assigned_to INT COMMENT 'Officer ID',
        admin_notes TEXT,
        created_by INT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_by INT,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (created_by) REFERENCES signup(user_id),
        FOREIGN KEY (assigned_to) REFERENCES signup(user_id),
        FOREIGN KEY (updated_by) REFERENCES signup(user_id),
        INDEX idx_status (status),
        INDEX idx_urgency (urgency_level),
        INDEX idx_is_high_risk (is_high_risk),
        INDEX idx_incident_date (incident_date),
        INDEX idx_created_by (created_by),
        INDEX idx_case_no (case_no)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $pdo->exec($sql);
    $results[] = ['status' => 'success', 'message' => 'Incidents table created/verified successfully'];

    // Verify table was created
    $checkTable = $pdo->query("SHOW TABLES LIKE 'incidents'");
    if ($checkTable->rowCount() > 0) {
        $results[] = ['status' => 'success', 'message' => 'Table verification passed'];
    }

} catch (PDOException $e) {
    $results[] = ['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()];
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Incidents Table Setup</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h4><i class="bi bi-database"></i> Incidents Table Setup</h4>
        </div>
        <div class="card-body">
            <h5>Setup Results:</h5>
            <div class="list-group mt-3">
                <?php foreach ($results as $result): ?>
                    <div class="list-group-item list-group-item-<?php echo $result['status'] === 'success' ? 'success' : 'danger'; ?>">
                        <strong><?php echo ucfirst($result['status']); ?>:</strong> <?php echo htmlspecialchars($result['message']); ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="alert alert-info mt-4">
                <strong><i class="bi bi-info-circle"></i> Next Steps:</strong>
                <ul class="mb-0 mt-2">
                    <li>The incidents table has been created with all required fields for logging and classification</li>
                    <li>Fields include: incident_type, auto_classification, manual_classification, urgency_level, is_high_risk</li>
                    <li>You can now access the <a href="modules/incident_report.php">Incident Report Module</a></li>
                </ul>
            </div>
        </div>
    </div>
</div>
</body>
</html>
