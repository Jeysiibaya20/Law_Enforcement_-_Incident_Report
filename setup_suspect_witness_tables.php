<?php
/**
 * Setup script to create suspect and witness tables
 */

require_once 'config/db_connect.php';

try {
    // Check if suspects table exists
    $result = $pdo->query("SHOW TABLES LIKE 'suspects'");
    $table_exists = $result->rowCount() > 0;

    if ($table_exists) {
        echo "[OK] suspects table already exists<br>";
    } else {
        echo "Creating suspects table...<br>";
        $pdo->exec("
            CREATE TABLE `suspects` (
              `id` INT AUTO_INCREMENT PRIMARY KEY,
              `case_id` INT NOT NULL,
              `case_number` VARCHAR(50) NOT NULL,
              `first_name` VARCHAR(100) NOT NULL,
              `middle_name` VARCHAR(100),
              `last_name` VARCHAR(100) NOT NULL,
              `age` INT,
              `date_of_birth` DATE,
              `gender` ENUM('Male', 'Female', 'Other') DEFAULT 'Male',
              `address` VARCHAR(255),
              `barangay` VARCHAR(100),
              `city` VARCHAR(100),
              `province` VARCHAR(100),
              `zip_code` VARCHAR(10),
              `contact_number` VARCHAR(20),
              `email` VARCHAR(150),
              `id_type` VARCHAR(50),
              `id_number` VARCHAR(100),
              `physical_description` TEXT,
              `known_aliases` VARCHAR(255),
              `criminal_history` TEXT,
              `remarks` TEXT,
              `status` ENUM('Active', 'Arrested', 'Released', 'Deceased', 'Unknown') DEFAULT 'Active',
              `created_by` INT NOT NULL,
              `updated_by` INT,
              `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");
        echo "[OK] suspects table created successfully<br>";
    }

    // Check if witnesses table exists
    $result = $pdo->query("SHOW TABLES LIKE 'witnesses'");
    $table_exists = $result->rowCount() > 0;

    if ($table_exists) {
        echo "[OK] witnesses table already exists<br>";
    } else {
        echo "Creating witnesses table...<br>";
        $pdo->exec("
            CREATE TABLE `witnesses` (
              `id` INT AUTO_INCREMENT PRIMARY KEY,
              `case_id` INT NOT NULL,
              `case_number` VARCHAR(50) NOT NULL,
              `first_name` VARCHAR(100) NOT NULL,
              `middle_name` VARCHAR(100),
              `last_name` VARCHAR(100) NOT NULL,
              `age` INT,
              `date_of_birth` DATE,
              `gender` ENUM('Male', 'Female', 'Other') DEFAULT 'Male',
              `address` VARCHAR(255),
              `barangay` VARCHAR(100),
              `city` VARCHAR(100),
              `province` VARCHAR(100),
              `zip_code` VARCHAR(10),
              `contact_number` VARCHAR(20),
              `email` VARCHAR(150),
              `id_type` VARCHAR(50),
              `id_number` VARCHAR(100),
              `relationship_to_case` VARCHAR(100),
              `witness_type` ENUM('Direct', 'Indirect', 'Hearsay', 'Character') DEFAULT 'Direct',
              `statement` TEXT,
              `reliability` ENUM('High', 'Medium', 'Low') DEFAULT 'Medium',
              `available_for_court` BOOLEAN DEFAULT TRUE,
              `protection_needed` BOOLEAN DEFAULT FALSE,
              `remarks` TEXT,
              `created_by` INT NOT NULL,
              `updated_by` INT,
              `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");
        echo "[OK] witnesses table created successfully<br>";
    }

    // Check if suspect_updates table exists
    $result = $pdo->query("SHOW TABLES LIKE 'suspect_updates'");
    $table_exists = $result->rowCount() > 0;

    if ($table_exists) {
        echo "[OK] suspect_updates table already exists<br>";
    } else {
        echo "Creating suspect_updates table...<br>";
        $pdo->exec("
            CREATE TABLE `suspect_updates` (
              `id` INT AUTO_INCREMENT PRIMARY KEY,
              `suspect_id` INT NOT NULL,
              `update_type` VARCHAR(50),
              `update_description` TEXT,
              `updated_by` INT NOT NULL,
              `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        echo "[OK] suspect_updates table created successfully<br>";
    }

    // Check if witness_updates table exists
    $result = $pdo->query("SHOW TABLES LIKE 'witness_updates'");
    $table_exists = $result->rowCount() > 0;

    if ($table_exists) {
        echo "[OK] witness_updates table already exists<br>";
    } else {
        echo "Creating witness_updates table...<br>";
        $pdo->exec("
            CREATE TABLE `witness_updates` (
              `id` INT AUTO_INCREMENT PRIMARY KEY,
              `witness_id` INT NOT NULL,
              `update_type` VARCHAR(50),
              `update_description` TEXT,
              `updated_by` INT NOT NULL,
              `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        echo "[OK] witness_updates table created successfully<br>";
    }

    // Create indexes
    try {
        $pdo->exec("CREATE INDEX `idx_suspects_case_id` ON `suspects`(`case_id`)");
    } catch (PDOException $e) {
        // Index might already exist
    }

    try {
        $pdo->exec("CREATE INDEX `idx_suspects_status` ON `suspects`(`status`)");
    } catch (PDOException $e) {
        // Index might already exist
    }

    try {
        $pdo->exec("CREATE INDEX `idx_witnesses_case_id` ON `witnesses`(`case_id`)");
    } catch (PDOException $e) {
        // Index might already exist
    }

    try {
        $pdo->exec("CREATE INDEX `idx_witnesses_reliability` ON `witnesses`(`reliability`)");
    } catch (PDOException $e) {
        // Index might already exist
    }

    echo "<br><strong>All suspect and witness tables are ready!</strong><br>";
    echo '<a href="admin/cases.php">Go to Cases Management</a>';

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
