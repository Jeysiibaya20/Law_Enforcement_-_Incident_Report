<?php
/**
 * Setup script to create evidence collection and chain of custody tables
 */

require_once 'config/db_connect.php';

try {
    // Create evidence_records table
    $result = $pdo->query("SHOW TABLES LIKE 'evidence_records'");
    $table_exists = $result->rowCount() > 0;

    if ($table_exists) {
        echo "[OK] evidence_records table already exists<br>";
    } else {
        echo "Creating evidence_records table...<br>";
        $pdo->exec("
            CREATE TABLE `evidence_records` (
              `id` INT AUTO_INCREMENT PRIMARY KEY,
              `evidence_number` VARCHAR(50) UNIQUE NOT NULL,
              `case_id` INT,
              `case_number` VARCHAR(50),
              `evidence_type` ENUM('Physical', 'Digital', 'Document', 'Photo', 'Video', 'Audio', 'Other') NOT NULL,
              `item_description` TEXT NOT NULL,
              `location_found` VARCHAR(255),
              `source_department` VARCHAR(150),
              `received_from` VARCHAR(150),
              `source_reference` VARCHAR(100),
              `witness_name` VARCHAR(150),
              `witness_description` TEXT,
              `collection_date` DATETIME NOT NULL,
              `collected_by` INT NOT NULL,
              `collector_name` VARCHAR(150),
              `condition` ENUM('Excellent', 'Good', 'Fair', 'Poor', 'Damaged') DEFAULT 'Good',
              `storage_location` VARCHAR(255),
              `security_level` ENUM('Low', 'Medium', 'High', 'Confidential') DEFAULT 'Medium',
              `status` ENUM('Collected', 'In Storage', 'In Transit', 'Released', 'Destroyed', 'Lost') DEFAULT 'Collected',
              `notes` TEXT,
              `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");
        echo "[OK] evidence_records table created successfully<br>";
    }

    // Also auto-migrate existing tables if missing columns
    $evidenceCols = [
        'source_department' => 'VARCHAR(150) NULL',
        'received_from' => 'VARCHAR(150) NULL',
        'source_reference' => 'VARCHAR(100) NULL',
        'witness_name' => 'VARCHAR(150) NULL',
        'witness_description' => 'TEXT NULL'
    ];
    foreach ($evidenceCols as $col => $def) {
        $check = $pdo->query("SHOW COLUMNS FROM `evidence_records` LIKE '{$col}'");
        if ($check->rowCount() === 0) {
            $pdo->exec("ALTER TABLE `evidence_records` ADD COLUMN `{$col}` {$def}");
            echo "[MIGRATED] Added column {$col} to evidence_records<br>";
        }
    }

    // Create evidence_attachments table
    $result = $pdo->query("SHOW TABLES LIKE 'evidence_attachments'");
    $table_exists = $result->rowCount() > 0;

    if ($table_exists) {
        echo "[OK] evidence_attachments table already exists<br>";
    } else {
        echo "Creating evidence_attachments table...<br>";
        $pdo->exec("
            CREATE TABLE `evidence_attachments` (
              `id` INT AUTO_INCREMENT PRIMARY KEY,
              `evidence_id` INT NOT NULL,
              `original_filename` VARCHAR(255) NOT NULL,
              `stored_filename` VARCHAR(255) NOT NULL,
              `file_path` VARCHAR(500) NOT NULL,
              `file_type` VARCHAR(100) NOT NULL,
              `file_size` INT NOT NULL,
              `mime_type` VARCHAR(100) NOT NULL,
              `description` TEXT,
              `uploaded_by` INT NOT NULL,
              `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              `is_deleted` TINYINT(1) DEFAULT 0,
              FOREIGN KEY (`evidence_id`) REFERENCES `evidence_records`(`id`) ON DELETE CASCADE
            )
        ");
        echo "[OK] evidence_attachments table created successfully<br>";
    }

    // Create chain_of_custody table
    $result = $pdo->query("SHOW TABLES LIKE 'chain_of_custody'");
    $table_exists = $result->rowCount() > 0;

    if ($table_exists) {
        echo "[OK] chain_of_custody table already exists<br>";
    } else {
        echo "Creating chain_of_custody table...<br>";
        $pdo->exec("
            CREATE TABLE `chain_of_custody` (
              `id` INT AUTO_INCREMENT PRIMARY KEY,
              `evidence_id` INT NOT NULL,
              `action_type` ENUM('Collected', 'Transferred', 'Accessed', 'Released', 'Destroyed', 'Returned', 'Stored', 'Retrieved') NOT NULL,
              `from_person_id` INT,
              `from_person_name` VARCHAR(150),
              `to_person_id` INT,
              `to_person_name` VARCHAR(150),
              `action_date` DATETIME NOT NULL,
              `location` VARCHAR(255),
              `purpose` VARCHAR(255),
              `notes` TEXT,
              `performed_by` INT NOT NULL,
              `witness_name` VARCHAR(150),
              `witness_signature` TEXT,
              `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              FOREIGN KEY (`evidence_id`) REFERENCES `evidence_records`(`id`) ON DELETE CASCADE
            )
        ");
        echo "[OK] chain_of_custody table created successfully<br>";
    }

    // Create indexes
    try {
        $pdo->exec("CREATE INDEX `idx_evidence_case` ON `evidence_records`(`case_id`)");
        $pdo->exec("CREATE INDEX `idx_evidence_status` ON `evidence_records`(`status`)");
        $pdo->exec("CREATE INDEX `idx_evidence_collected_by` ON `evidence_records`(`collected_by`)");
        $pdo->exec("CREATE INDEX `idx_attachments_evidence` ON `evidence_attachments`(`evidence_id`)");
        $pdo->exec("CREATE INDEX `idx_custody_evidence` ON `chain_of_custody`(`evidence_id`)");
        $pdo->exec("CREATE INDEX `idx_custody_action_date` ON `chain_of_custody`(`action_date`)");
    } catch (PDOException $e) {
        // Indexes might already exist
        echo "Note: Some indexes may already exist<br>";
    }

    echo "<br><strong>All evidence management tables are now ready!</strong><br>";
    echo "You can now use the evidence collection and chain of custody system.";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>