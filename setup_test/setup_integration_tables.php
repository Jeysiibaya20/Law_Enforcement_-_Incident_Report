<?php
/**
 * Setup script for External Integration & CCTV / Resolved Tips tables
 */
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../config/integration_config.php';

try {
    $pdo = getDBConnection();
    ensureIntegrationSettingsSchema($pdo);

    // 1. External Integration Log
    $pdo->exec("CREATE TABLE IF NOT EXISTS external_integration_log (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        direction VARCHAR(50) NOT NULL,
        target_url TEXT NULL,
        payload LONGTEXT NULL,
        response_body LONGTEXT NULL,
        status VARCHAR(50) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Upgrade column if existing table has smaller size
    try {
        $pdo->exec("ALTER TABLE external_integration_log MODIFY COLUMN direction VARCHAR(50) NOT NULL");
    } catch (Exception $e) {
        // Column alter notice
    }

    // 2. CCTV Footage Received
    $pdo->exec("CREATE TABLE IF NOT EXISTS cctv_footage_received (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        request_id VARCHAR(100) NULL,
        incident_id VARCHAR(100) NULL,
        cctv_url TEXT NULL,
        camera_id VARCHAR(100) NULL,
        location VARCHAR(255) NULL,
        video_format VARCHAR(50) DEFAULT 'video/mp4',
        duration VARCHAR(50) NULL,
        notes TEXT NULL,
        status VARCHAR(50) DEFAULT 'Received',
        received_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_incident_id (incident_id),
        INDEX idx_request_id (request_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // 3. Received Resolved Tips
    $pdo->exec("CREATE TABLE IF NOT EXISTS received_resolved_tips (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        tip_id VARCHAR(100) NULL,
        incident_id VARCHAR(100) NULL,
        incident_type VARCHAR(100) NULL,
        title VARCHAR(255) NULL,
        description TEXT NULL,
        location VARCHAR(255) NULL,
        district VARCHAR(100) NULL,
        resolved_by VARCHAR(150) NULL,
        resolution_notes TEXT NULL,
        evidence_url TEXT NULL,
        resolved_at VARCHAR(100) NULL,
        status VARCHAR(50) DEFAULT 'Logged',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_tip_id (tip_id),
        INDEX idx_incident_id (incident_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // 4. CCTV Requests Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS cctv_requests (
        id INT(11) NOT NULL AUTO_INCREMENT,
        requested_by INT(11) DEFAULT NULL,
        request_type VARCHAR(50) NOT NULL DEFAULT 'Footage',
        camera_location VARCHAR(255) DEFAULT NULL,
        incident_date DATE DEFAULT NULL,
        incident_time TIME DEFAULT NULL,
        priority VARCHAR(50) NOT NULL DEFAULT 'Normal',
        reason TEXT NOT NULL,
        additional_details TEXT DEFAULT NULL,
        monitoring_office VARCHAR(100) DEFAULT NULL,
        delivery_method VARCHAR(100) DEFAULT NULL,
        monitoring_notes TEXT DEFAULT NULL,
        status VARCHAR(50) NOT NULL DEFAULT 'Pending',
        requested_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT NULL,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // 5. Received Campaigns Table (from campaign.alertaraqc.com)
    $pdo->exec("CREATE TABLE IF NOT EXISTS received_campaigns (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        campaign_id INT UNSIGNED NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT NULL,
        category VARCHAR(100) NULL,
        geographical_scope VARCHAR(100) NULL,
        start_date VARCHAR(100) NULL,
        end_date VARCHAR(100) NULL,
        status VARCHAR(50) DEFAULT 'Active',
        image_url TEXT NULL,
        raw_json LONGTEXT NULL,
        fetched_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_campaign_id (campaign_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // 6. Received Community Complaints (from Group 4)
    $pdo->exec("CREATE TABLE IF NOT EXISTS received_community_complaints (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        complaint_id VARCHAR(100) NULL,
        complainant_name VARCHAR(150) NULL,
        incident_type VARCHAR(100) NULL,
        date_time VARCHAR(100) NULL,
        location VARCHAR(255) NULL,
        description TEXT NULL,
        status VARCHAR(50) DEFAULT 'Pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_complaint_id (complaint_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // 7. Received Accident & Violation Reports (from Group 2)
    $pdo->exec("CREATE TABLE IF NOT EXISTS received_accident_reports (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        report_id VARCHAR(100) NULL,
        ticket_number VARCHAR(100) NULL,
        incident_type VARCHAR(150) DEFAULT 'Traffic Accident / Violation',
        violator_name VARCHAR(255) NULL,
        vehicle_details VARCHAR(255) NULL,
        plate_number VARCHAR(50) NULL,
        violation_type VARCHAR(255) NULL,
        fine_amount DECIMAL(10, 2) DEFAULT 0.00,
        severity_level VARCHAR(50) DEFAULT 'Medium',
        collision_type VARCHAR(100) NULL,
        location VARCHAR(255) NULL,
        barangay VARCHAR(100) NULL,
        district VARCHAR(100) NULL,
        narrative LONGTEXT NULL,
        casualties_count INT DEFAULT 0,
        property_damage_estimate DECIMAL(12, 2) DEFAULT 0.00,
        reporting_officer VARCHAR(150) NULL,
        incident_date_time DATETIME NULL,
        evidence_media TEXT NULL,
        status VARCHAR(50) DEFAULT 'Logged & Classified',
        raw_payload LONGTEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_report_id (report_id),
        INDEX idx_ticket_number (ticket_number)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // 8. Suspect & Witness Data Privacy Audit Log
    $pdo->exec("CREATE TABLE IF NOT EXISTS suspect_witness_privacy_audit (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT NULL,
        action VARCHAR(100) NOT NULL,
        target_type VARCHAR(50) NOT NULL,
        target_id INT NULL,
        details TEXT NULL,
        ip_address VARCHAR(45) NULL,
        user_agent VARCHAR(255) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_target (target_type, target_id),
        INDEX idx_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Check & add columns to cctv_requests for Group 2 Acknowledgment workflow
    $cctvCols = [
        'acknowledged_at' => 'DATETIME NULL',
        'acknowledged_by' => 'VARCHAR(150) NULL',
        'acknowledgement_notes' => 'TEXT NULL',
        'assigned_camera_operator' => 'VARCHAR(150) NULL',
        'fulfilled_evidence_url' => 'TEXT NULL',
        'fulfilled_photo_url' => 'TEXT NULL',
        'fulfilled_video_url' => 'TEXT NULL'
    ];
    foreach ($cctvCols as $col => $type) {
        $chk = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cctv_requests' AND COLUMN_NAME = ?");
        $chk->execute([$col]);
        if ((int)$chk->fetchColumn() === 0) {
            $pdo->exec("ALTER TABLE cctv_requests ADD COLUMN {$col} {$type}");
        }
    }

    echo "Integration database tables created/verified successfully.\n";
} catch (Exception $e) {
    echo "Error setting up integration tables: " . $e->getMessage() . "\n";
    exit(1);
}

