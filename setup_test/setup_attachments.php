<?php
/**
 * Setup script to create attachments tables and directories
 */

require_once 'config/db_connect.php';

try {
    // Check if attachments table exists
    $result = $pdo->query("SHOW TABLES LIKE 'attachments'");
    $table_exists = $result->rowCount() > 0;

    if ($table_exists) {
        echo "[OK] attachments table already exists<br>";
    } else {
        echo "Creating attachments table...<br>";
        $pdo->exec("
            CREATE TABLE `attachments` (
              `id` INT AUTO_INCREMENT PRIMARY KEY,
              `entity_type` ENUM('incident', 'blotter', 'case') NOT NULL,
              `entity_id` INT NOT NULL,
              `original_filename` VARCHAR(255) NOT NULL,
              `stored_filename` VARCHAR(255) NOT NULL,
              `file_path` VARCHAR(500) NOT NULL,
              `file_type` VARCHAR(100) NOT NULL,
              `file_size` INT NOT NULL,
              `mime_type` VARCHAR(100) NOT NULL,
              `description` TEXT,
              `uploaded_by` INT NOT NULL,
              `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              `is_deleted` BOOLEAN DEFAULT FALSE,
              INDEX `idx_entity` (`entity_type`, `entity_id`),
              FOREIGN KEY (`uploaded_by`) REFERENCES `users`(`user_id`)
            )
        ");
        echo "[OK] attachments table created successfully<br>";
    }

    // Create upload directories
    $upload_dirs = [
        __DIR__ . '/uploads',
        __DIR__ . '/uploads/incidents',
        __DIR__ . '/uploads/blotters',
        __DIR__ . '/uploads/cases',
        __DIR__ . '/uploads/temp'
    ];

    foreach ($upload_dirs as $dir) {
        if (!file_exists($dir)) {
            mkdir($dir, 0755, true);
            echo "[OK] Created directory: " . basename($dir) . "<br>";
        } else {
            echo "[OK] Directory already exists: " . basename($dir) . "<br>";
        }
    }

    // Create .htaccess for security
    $htaccess_content = "Options -Indexes\nDeny from all\n";
    $htaccess_path = __DIR__ . '/uploads/.htaccess';
    if (!file_exists($htaccess_path)) {
        file_put_contents($htaccess_path, $htaccess_content);
        echo "[OK] Created .htaccess security file<br>";
    }

    echo "<br><strong>Attachments system setup complete!</strong><br>";
    echo "Upload directories created and secured.<br>";

} catch (Exception $e) {
    echo "<strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "<br>";
    exit(1);
}
?>