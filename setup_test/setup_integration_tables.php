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

    echo "Integration database tables created/verified successfully.\n";
} catch (Exception $e) {
    echo "Error setting up integration tables: " . $e->getMessage() . "\n";
    exit(1);
}
