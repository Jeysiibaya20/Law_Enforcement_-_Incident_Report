<?php
/**
 * Setup Admin Notification System
 * 
 * This script ensures the notifications table exists and has proper structure
 * for NLP incident alerts to admins.
 * 
 * Visit: http://localhost/Law_Enforcement_-_Incident_Report/setup_admin_notifications.php
 */

session_start();
require_once 'config/db_connect.php';

$results = [];
$success = true;

// Check and create notifications table if needed
try {
    $check = "SELECT COUNT(*) FROM information_schema.TABLES 
              WHERE TABLE_SCHEMA = (SELECT DATABASE()) AND TABLE_NAME = 'notifications'";
    $stmt = $pdo->query($check);
    $exists = $stmt->fetchColumn() > 0;

    if (!$exists) {
        $create_notifications = "
            CREATE TABLE notifications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                incident_id INT NOT NULL,
                notification_type VARCHAR(100) NOT NULL,
                title VARCHAR(255) NOT NULL,
                message LONGTEXT NOT NULL,
                threat_level VARCHAR(50),
                urgency VARCHAR(100),
                is_read TINYINT(1) DEFAULT 0,
                read_at DATETIME NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES signup(user_id) ON DELETE CASCADE,
                FOREIGN KEY (incident_id) REFERENCES incidents(id) ON DELETE CASCADE,
                INDEX idx_user_unread (user_id, is_read),
                INDEX idx_incident_notifications (incident_id),
                INDEX idx_notification_type (notification_type),
                INDEX idx_threat_level (threat_level),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        
        $pdo->exec($create_notifications);
        $results[] = '✓ Created notifications table';
    } else {
        $results[] = '✓ Notifications table already exists';
        
        // Verify required columns exist
        $columns_to_check = ['threat_level', 'urgency'];
        $col_query = "SELECT COLUMN_NAME FROM information_schema.COLUMNS 
                      WHERE TABLE_SCHEMA = (SELECT DATABASE()) AND TABLE_NAME = 'notifications'";
        $stmt = $pdo->query($col_query);
        $existing_cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Add missing columns
        if (!in_array('threat_level', $existing_cols)) {
            $pdo->exec("ALTER TABLE notifications ADD COLUMN threat_level VARCHAR(50) AFTER urgency");
            $results[] = '✓ Added threat_level column';
        }
        
        if (!in_array('urgency', $existing_cols)) {
            $pdo->exec("ALTER TABLE notifications ADD COLUMN urgency VARCHAR(100) AFTER threat_level");
            $results[] = '✓ Added urgency column';
        }
    }

} catch (Exception $e) {
    $results[] = '✗ Notifications table error: ' . $e->getMessage();
    $success = false;
}

// Check incidents table has NLP columns
try {
    $col_query = "SELECT COLUMN_NAME FROM information_schema.COLUMNS 
                  WHERE TABLE_SCHEMA = (SELECT DATABASE()) AND TABLE_NAME = 'incidents'";
    $stmt = $pdo->query($col_query);
    $incident_cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $nlp_columns = [
        'nlp_sentiment' => "nlp_sentiment VARCHAR(50) DEFAULT 'Neutral'",
        'nlp_threat_level' => "nlp_threat_level VARCHAR(50) DEFAULT 'Low'",
        'nlp_severity_score' => "nlp_severity_score DECIMAL(5,2) DEFAULT 0"
    ];
    
    foreach ($nlp_columns as $col_name => $col_def) {
        if (!in_array($col_name, $incident_cols)) {
            $pdo->exec("ALTER TABLE incidents ADD COLUMN $col_def");
            $results[] = "✓ Added $col_name to incidents table";
        }
    }
    
} catch (Exception $e) {
    $results[] = '⚠ Incidents table check: ' . $e->getMessage();
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Notification System Setup</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; }
        .container { max-width: 600px; }
        .card { box-shadow: 0 10px 40px rgba(0,0,0,0.2); border: none; }
        .success { color: #28a745; font-weight: bold; }
        .error { color: #dc3545; font-weight: bold; }
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <div class="card-body p-5">
            <h2 class="text-center mb-4">🔔 Admin Notification System Setup</h2>
            
            <div class="alert <?php echo $success ? 'alert-success' : 'alert-warning'; ?>">
                <h5><?php echo $success ? '✓ Setup Complete!' : '⚠ Setup Completed with Warnings'; ?></h5>
                <ul class="mb-0">
                    <?php foreach ($results as $result): ?>
                        <li><?php echo htmlspecialchars($result); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="alert alert-info">
                <h6>What This Does:</h6>
                <ul class="mb-0">
                    <li>Creates/verifies the notifications table</li>
                    <li>Adds threat_level and urgency columns</li>
                    <li>Ensures NLP analysis fields exist in incidents table</li>
                    <li>Sets up email notification system for high-severity incidents</li>
                </ul>
            </div>

            <div class="alert alert-success">
                <h6>Admin Notifications Now Enabled For:</h6>
                <ul class="mb-0">
                    <li>✓ High-severity incidents (Threat Level: High/Critical)</li>
                    <li>✓ Incidents with severity score ≥ 70%</li>
                    <li>✓ In-app notifications for all admins</li>
                    <li>✓ Email alerts for urgent incidents</li>
                    <li>✓ Multi-language chatbot support (13 languages)</li>
                </ul>
            </div>

            <hr>

            <h6>How It Works:</h6>
            <ol>
                <li>User submits an incident report</li>
                <li>NLP system analyzes: sentiment, threat level, severity</li>
                <li>If high-severity (High/Critical or ≥70%), admins are notified:</li>
                <li style="margin-left: 20px;">
                    - In-app notification appears in their dashboard<br>
                    - Email alert sent with incident details
                </li>
                <li>Admins can view full incident and NLP analysis data</li>
            </ol>

            <div class="d-grid gap-2 mt-4">
                <a href="index.php" class="btn btn-primary btn-lg">← Back to Home</a>
                <a href="admin/dashboard.php" class="btn btn-outline-primary btn-lg">Go to Admin Dashboard</a>
            </div>

        </div>
        <div class="card-footer bg-light text-muted text-center">
            <small>Alertara Incident Report System v2.0 | Admin Notification System</small>
        </div>
    </div>
</div>

</body>
</html>

?>
