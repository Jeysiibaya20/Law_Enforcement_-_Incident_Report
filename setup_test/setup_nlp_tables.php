<?php
/**
 * Auto-Setup Script for NLP/Workflow Database Tables
 * 
 * This script automatically creates the required database tables
 * if they don't already exist. Run this ONCE when deploying the NLP system.
 * 
 * Usage: Visit http://yoursite.com/setup_nlp_tables.php in browser
 * Or call from command line: php setup_nlp_tables.php
 * 
 * @author Law Enforcement System
 * @version 1.0.0
 */

// Start session and load database
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/db_connect.php';

// HTML header for web access
$is_cli = php_sapi_name() === 'cli';
if (!$is_cli) {
    echo "<!DOCTYPE html><html><head><title>NLP Tables Setup</title>";
    echo "<link href='https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css' rel='stylesheet'>";
    echo "<style>body{padding:20px;}.success{color:green;}.error{color:red;}</style>";
    echo "</head><body><div class='container'>";
}

$results = [];
$all_success = true;

// Function to create tables if they don't exist
function createTable($pdo, $table_name, $create_sql, &$results, &$all_success) {
    try {
        // Check if table exists
        $check_sql = "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?";
        $stmt = $pdo->prepare($check_sql);
        $stmt->execute([getenv('DB_NAME') ?: 'law&inci', $table_name]);
        $exists = $stmt->fetchColumn() > 0;
        
        if ($exists) {
            $results[] = "✓ Table '{$table_name}' already exists";
            return;
        }
        
        // Create table
        $pdo->exec($create_sql);
        $results[] = "✓ Created table '{$table_name}'";
        
    } catch (Exception $e) {
        $results[] = "✗ Error with table '{$table_name}': " . $e->getMessage();
        $all_success = false;
    }
}

// Function to add column if it doesn't exist
function addColumn($pdo, $table_name, $column_name, $column_sql, &$results, &$all_success) {
    try {
        $check_sql = "SELECT COUNT(*) FROM information_schema.COLUMNS 
                     WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?";
        $stmt = $pdo->prepare($check_sql);
        $stmt->execute([getenv('DB_NAME') ?: 'law&inci', $table_name, $column_name]);
        $exists = $stmt->fetchColumn() > 0;
        
        if ($exists) {
            return;
        }
        
        $alter_sql = "ALTER TABLE {$table_name} ADD COLUMN {$column_sql}";
        $pdo->exec($alter_sql);
        $results[] = "✓ Added column '{$column_name}' to '{$table_name}'";
        
    } catch (Exception $e) {
        $results[] = "⚠ Column '{$column_name}' issue: " . $e->getMessage();
    }
}

// Create notifications table
createTable($pdo, 'notifications', "CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    incident_id INT NULL,
    blotter_id INT NULL,
    notification_type VARCHAR(100) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message LONGTEXT NOT NULL,
    threat_level VARCHAR(50),
    urgency VARCHAR(100),
    is_read TINYINT(1) DEFAULT 0,
    read_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES signup(user_id),
    FOREIGN KEY (incident_id) REFERENCES incidents(id) ON DELETE SET NULL,
    FOREIGN KEY (blotter_id) REFERENCES blotters(id) ON DELETE SET NULL,
    INDEX idx_user_unread (user_id, is_read),
    INDEX idx_incident_notifications (incident_id),
    INDEX idx_blotter_notifications (blotter_id),
    INDEX idx_notification_type (notification_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", $results, $all_success);

// Create review_requests table
createTable($pdo, 'review_requests', "CREATE TABLE review_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    incident_id INT NOT NULL,
    requested_by INT NOT NULL,
    reason TEXT NOT NULL,
    priority ENUM('High', 'Normal', 'Low') DEFAULT 'Normal',
    status ENUM('Pending', 'Completed', 'Rejected') DEFAULT 'Pending',
    responded_by INT NULL,
    response VARCHAR(50),
    findings LONGTEXT,
    recommendations LONGTEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    responded_at DATETIME NULL,
    FOREIGN KEY (incident_id) REFERENCES incidents(id),
    FOREIGN KEY (requested_by) REFERENCES signup(user_id),
    FOREIGN KEY (responded_by) REFERENCES signup(user_id),
    INDEX idx_incident_reviews (incident_id),
    INDEX idx_pending_reviews (status, priority)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", $results, $all_success);

// Create workflow_events table
createTable($pdo, 'workflow_events', "CREATE TABLE workflow_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    incident_id INT NOT NULL,
    event_type VARCHAR(100) NOT NULL,
    description TEXT,
    performed_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (incident_id) REFERENCES incidents(id),
    FOREIGN KEY (performed_by) REFERENCES signup(user_id) ON DELETE SET NULL,
    INDEX idx_incident_workflow (incident_id),
    INDEX idx_event_type (event_type),
    INDEX idx_workflow_date (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", $results, $all_success);

// Create nlp_analysis_cache table
createTable($pdo, 'nlp_analysis_cache', "CREATE TABLE nlp_analysis_cache (
    id INT AUTO_INCREMENT PRIMARY KEY,
    incident_id INT UNIQUE NOT NULL,
    analysis_data JSON NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (incident_id) REFERENCES incidents(id) ON DELETE CASCADE,
    INDEX idx_analysis_date (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", $results, $all_success);

// Create system_alerts table
createTable($pdo, 'system_alerts', "CREATE TABLE system_alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    incident_id INT NOT NULL,
    alert_type VARCHAR(100) NOT NULL,
    severity VARCHAR(50) NOT NULL,
    alert_message TEXT NOT NULL,
    resolved TINYINT(1) DEFAULT 0,
    resolved_by INT NULL,
    resolved_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (incident_id) REFERENCES incidents(id),
    FOREIGN KEY (resolved_by) REFERENCES signup(user_id),
    INDEX idx_unresolved_alerts (resolved),
    INDEX idx_alert_severity (severity),
    INDEX idx_alert_date (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", $results, $all_success);

// Add NLP fields to incidents table
addColumn($pdo, 'incidents', 'nlp_sentiment', "nlp_sentiment VARCHAR(50) DEFAULT 'Neutral' COMMENT 'Sentiment analysis result'", $results, $all_success);
addColumn($pdo, 'incidents', 'nlp_threat_level', "nlp_threat_level VARCHAR(50) DEFAULT 'Low' COMMENT 'Threat level from NLP'", $results, $all_success);
addColumn($pdo, 'incidents', 'nlp_severity_score', "nlp_severity_score DECIMAL(5,2) DEFAULT 0 COMMENT 'Severity score 0-100'", $results, $all_success);
addColumn($pdo, 'incidents', 'nlp_emotions', "nlp_emotions JSON COMMENT 'Detected emotions'", $results, $all_success);
addColumn($pdo, 'incidents', 'nlp_analysis_data', "nlp_analysis_data JSON COMMENT 'Full NLP data'", $results, $all_success);
addColumn($pdo, 'incidents', 'nlp_confidence_score', "nlp_confidence_score DECIMAL(5,2) DEFAULT 0 COMMENT 'Confidence 0-100'", $results, $all_success);
addColumn($pdo, 'incidents', 'nlp_summary', "nlp_summary LONGTEXT COMMENT 'NLP summary'", $results, $all_success);
addColumn($pdo, 'incidents', 'review_requested', "review_requested TINYINT(1) DEFAULT 0", $results, $all_success);
addColumn($pdo, 'incidents', 'review_requested_at', "review_requested_at DATETIME NULL", $results, $all_success);
addColumn($pdo, 'incidents', 'review_completed', "review_completed TINYINT(1) DEFAULT 0", $results, $all_success);
addColumn($pdo, 'incidents', 'review_completed_at', "review_completed_at DATETIME NULL", $results, $all_success);

// Add columns to blotters table
addColumn($pdo, 'blotters', 'incident_id', "incident_id INT COMMENT 'Reference to incident'", $results, $all_success);
addColumn($pdo, 'blotters', 'created_from_incident', "created_from_incident TINYINT(1) DEFAULT 0", $results, $all_success);
addColumn($pdo, 'blotters', 'nlp_threat_level', "nlp_threat_level VARCHAR(50)", $results, $all_success);
addColumn($pdo, 'blotters', 'nlp_severity_score', "nlp_severity_score DECIMAL(5,2)", $results, $all_success);
// Respondent contact details and hearing schedule
addColumn($pdo, 'blotters', 'respondent_contact', "respondent_contact VARCHAR(50) DEFAULT NULL", $results, $all_success);
addColumn($pdo, 'blotters', 'respondent_email', "respondent_email VARCHAR(150) DEFAULT NULL", $results, $all_success);
addColumn($pdo, 'blotters', 'respondent_address', "respondent_address VARCHAR(255) DEFAULT NULL", $results, $all_success);
addColumn($pdo, 'blotters', 'hearing_date', "hearing_date DATE NULL", $results, $all_success);
addColumn($pdo, 'blotters', 'hearing_time', "hearing_time TIME NULL", $results, $all_success);
addColumn($pdo, 'blotters', 'hearing_location', "hearing_location VARCHAR(255) DEFAULT NULL", $results, $all_success);

// Allow notifications to reference a blotter (polymorphic support)
addColumn($pdo, 'notifications', 'blotter_id', "blotter_id INT NULL", $results, $all_success);

// Add columns to case_assignments table
addColumn($pdo, 'case_assignments', 'nlp_threat_level', "nlp_threat_level VARCHAR(50)", $results, $all_success);
addColumn($pdo, 'case_assignments', 'nlp_severity_score', "nlp_severity_score DECIMAL(5,2)", $results, $all_success);
addColumn($pdo, 'case_assignments', 'incident_id', "incident_id INT COMMENT 'Reference to incident'", $results, $all_success);

// Output results
if (!$is_cli) {
    echo "<h2>NLP System Setup Results</h2>";
    echo "<div class='alert " . ($all_success ? 'alert-success' : 'alert-warning') . "'>";
    
    foreach ($results as $result) {
        if (strpos($result, '✓') === 0) {
            echo "<div class='success'>$result</div>";
        } elseif (strpos($result, '✗') === 0) {
            echo "<div class='error'>$result</div>";
        } else {
            echo "<div>$result</div>";
        }
    }
    
    echo "</div>";
    
    if ($all_success) {
        echo "<div class='alert alert-success'>";
        echo "<h4>✓ Setup Complete!</h4>";
        echo "<p>All NLP tables and columns have been successfully created/verified.</p>";
        echo "<p>You can now use the incident reporting system with NLP features.</p>";
        echo "<p><a href='index.php' class='btn btn-primary'>Go to Home</a></p>";
        echo "</div>";
    } else {
        echo "<div class='alert alert-warning'>";
        echo "<h4>⚠ Setup Partially Complete</h4>";
        echo "<p>Some tables may already exist or there were minor issues.</p>";
        echo "<p>The system should still work. Check the messages above for details.</p>";
        echo "</div>";
    }
    
    echo "</div></body></html>";
} else {
    // CLI output
    echo "NLP System Setup Results:\n";
    echo str_repeat("=", 60) . "\n";
    foreach ($results as $result) {
        echo "$result\n";
    }
    echo str_repeat("=", 60) . "\n";
    echo $all_success ? "✓ Setup Complete\n" : "⚠ Setup Complete (with warnings)\n";
}

?>
