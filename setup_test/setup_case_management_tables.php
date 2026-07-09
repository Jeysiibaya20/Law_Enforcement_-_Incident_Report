<?php
/**
 * Setup script to create case management tables
 * This script creates the necessary tables for case assignment functionality
 */

require_once 'config/db_connect.php';

try {
    // Check if case_assignments table exists
    $result = $pdo->query("SHOW TABLES LIKE 'case_assignments'");
    $table_exists = $result->rowCount() > 0;

    if ($table_exists) {
        echo "[OK] case_assignments table already exists<br>";
    } else {
        echo "Creating case_assignments table...<br>";
        $pdo->exec("
            CREATE TABLE `case_assignments` (
              `id` INT AUTO_INCREMENT PRIMARY KEY,
              `case_number` VARCHAR(50) UNIQUE NOT NULL,
              `incident_type` VARCHAR(100) NOT NULL,
              `complainant_name` VARCHAR(150) NOT NULL,
              `respondent_name` VARCHAR(150),
              `location` VARCHAR(255),
              `incident_date` DATE NOT NULL,
              `incident_time` TIME,
              `description` TEXT NOT NULL,
              `priority` ENUM('High', 'Medium', 'Low') DEFAULT 'Medium',
              `status` ENUM('New', 'Ongoing', 'Resolved', 'Closed') DEFAULT 'New',
              `assigned_by` INT NOT NULL,
              `assigned_to` INT,
              `barangay_chairperson_id` INT,
              `assignment_date` DATETIME DEFAULT CURRENT_TIMESTAMP,
              `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");
        echo "[OK] case_assignments table created successfully<br>";
    }

    // Check and create bcpc_officers table
    $result = $pdo->query("SHOW TABLES LIKE 'bcpc_officers'");
    $table_exists = $result->rowCount() > 0;

    if ($table_exists) {
        echo "[OK] bcpc_officers table already exists<br>";
    } else {
        echo "Creating bcpc_officers table...<br>";
        $pdo->exec("
            CREATE TABLE `bcpc_officers` (
              `id` INT AUTO_INCREMENT PRIMARY KEY,
              `user_id` INT UNIQUE NOT NULL,
              `barangay` VARCHAR(100) NOT NULL,
              `rank` VARCHAR(50) NOT NULL,
              `specialization` VARCHAR(100),
              `contact_number` VARCHAR(20),
              `is_available` BOOLEAN DEFAULT TRUE,
              `current_case_load` INT DEFAULT 0,
              `max_case_load` INT DEFAULT 10,
              `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");
        echo "[OK] bcpc_officers table created successfully<br>";
    }

    // Check and create case_updates table
    $result = $pdo->query("SHOW TABLES LIKE 'case_updates'");
    $table_exists = $result->rowCount() > 0;

    if ($table_exists) {
        echo "[OK] case_updates table already exists<br>";
    } else {
        echo "Creating case_updates table...<br>";
        $pdo->exec("
            CREATE TABLE `case_updates` (
              `id` INT AUTO_INCREMENT PRIMARY KEY,
              `case_id` INT NOT NULL,
              `case_number` VARCHAR(50) NOT NULL,
              `update_type` ENUM('Status Change', 'Follow-up Action', 'Note', 'Reassignment') NOT NULL,
              `previous_status` VARCHAR(20),
              `new_status` VARCHAR(20),
              `action_description` TEXT NOT NULL,
              `updated_by` INT NOT NULL,
              `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        echo "[OK] case_updates table created successfully<br>";
    }

    // Check and create case_notifications table
    $result = $pdo->query("SHOW TABLES LIKE 'case_notifications'");
    $table_exists = $result->rowCount() > 0;

    if ($table_exists) {
        echo "[OK] case_notifications table already exists<br>";
    } else {
        echo "Creating case_notifications table...<br>";
        $pdo->exec("
            CREATE TABLE `case_notifications` (
              `id` INT AUTO_INCREMENT PRIMARY KEY,
              `recipient_id` INT NOT NULL,
              `case_id` INT NOT NULL,
              `case_number` VARCHAR(50) NOT NULL,
              `notification_type` ENUM('New Assignment', 'Status Update', 'Reassignment', 'Follow-up Required') NOT NULL,
              `title` VARCHAR(200) NOT NULL,
              `message` TEXT NOT NULL,
              `is_read` BOOLEAN DEFAULT FALSE,
              `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        echo "[OK] case_notifications table created successfully<br>";
    }

    // Check and create case_timeline table
    $result = $pdo->query("SHOW TABLES LIKE 'case_timeline'");
    $table_exists = $result->rowCount() > 0;

    if ($table_exists) {
        echo "[OK] case_timeline table already exists<br>";
    } else {
        echo "Creating case_timeline table...<br>";
        $pdo->exec("
            CREATE TABLE `case_timeline` (
              `id` INT AUTO_INCREMENT PRIMARY KEY,
              `case_id` INT NOT NULL,
              `case_number` VARCHAR(50) NOT NULL,
              `event_type` ENUM('Case Created', 'Assigned', 'Status Changed', 'Follow-up', 'Reassigned', 'Resolved', 'Closed') NOT NULL,
              `event_description` TEXT NOT NULL,
              `performed_by` INT,
              `event_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        echo "[OK] case_timeline table created successfully<br>";
    }

    // Create indexes
    try {
        $pdo->exec("CREATE INDEX `idx_case_assignments_status` ON `case_assignments`(`status`)");
    } catch (PDOException $e) {
        // Index might already exist
    }

    try {
        $pdo->exec("CREATE INDEX `idx_case_assignments_assigned_to` ON `case_assignments`(`assigned_to`)");
    } catch (PDOException $e) {
        // Index might already exist
    }

    try {
        $pdo->exec("CREATE INDEX `idx_case_updates_case_id` ON `case_updates`(`case_id`)");
    } catch (PDOException $e) {
        // Index might already exist
    }

    try {
        $pdo->exec("CREATE INDEX `idx_notifications_recipient` ON `case_notifications`(`recipient_id`)");
    } catch (PDOException $e) {
        // Index might already exist
    }

    try {
        $pdo->exec("CREATE INDEX `idx_timeline_case_id` ON `case_timeline`(`case_id`)");
    } catch (PDOException $e) {
        // Index might already exist
    }

    echo "<br><strong>All case management tables are now ready!</strong><br>";
    echo "You can now close this page and use the case management system.";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
