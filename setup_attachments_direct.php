<?php
/**
 * Direct database setup for attachments table
 */

try {
    // Direct PDO connection
    $dsn = "mysql:host=localhost;dbname=law&inci;charset=utf8mb4";
    $pdo = new PDO($dsn, 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);

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
    $base_dir = __DIR__;
    $upload_dirs = [
        $base_dir . '/uploads',
        $base_dir . '/uploads/incidents',
        $base_dir . '/uploads/blotters',
        $base_dir . '/uploads/cases',
        $base_dir . '/uploads/temp'
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
    $htaccess_path = $base_dir . '/uploads/.htaccess';
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