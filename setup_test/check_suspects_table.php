<?php
require_once __DIR__ . '/../config/db_connect.php';

try {
    $pdo = getDBConnection();
    
    // Ensure suspects table exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS suspects (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        case_id INT UNSIGNED NULL,
        case_number VARCHAR(100) NULL,
        first_name VARCHAR(100) NOT NULL,
        middle_name VARCHAR(100) NULL,
        last_name VARCHAR(100) NOT NULL,
        age INT NULL,
        date_of_birth DATE NULL,
        gender VARCHAR(50) DEFAULT 'Male',
        address TEXT NULL,
        barangay VARCHAR(100) NULL,
        city VARCHAR(100) NULL,
        province VARCHAR(100) NULL,
        zip_code VARCHAR(20) NULL,
        contact_number VARCHAR(50) NULL,
        email VARCHAR(100) NULL,
        id_type VARCHAR(100) NULL,
        id_number VARCHAR(100) NULL,
        id_attachment VARCHAR(255) NULL,
        physical_description TEXT NULL,
        known_aliases TEXT NULL,
        criminal_history TEXT NULL,
        remarks TEXT NULL,
        status VARCHAR(50) DEFAULT 'Active',
        photo_path VARCHAR(255) NULL,
        created_by INT UNSIGNED NULL,
        updated_by INT UNSIGNED NULL,
        deleted_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $cols = ['id_attachment', 'photo_path', 'deleted_at', 'case_number', 'case_id'];
    foreach ($cols as $col) {
        $cCheck = $pdo->query("SHOW COLUMNS FROM suspects LIKE '$col'");
        if (!$cCheck->fetch()) {
            if ($col === 'id_attachment') $pdo->exec("ALTER TABLE suspects ADD COLUMN id_attachment VARCHAR(255) DEFAULT NULL");
            if ($col === 'photo_path') $pdo->exec("ALTER TABLE suspects ADD COLUMN photo_path VARCHAR(255) DEFAULT NULL");
            if ($col === 'deleted_at') $pdo->exec("ALTER TABLE suspects ADD COLUMN deleted_at DATETIME DEFAULT NULL");
            if ($col === 'case_number') $pdo->exec("ALTER TABLE suspects ADD COLUMN case_number VARCHAR(100) DEFAULT NULL");
            if ($col === 'case_id') $pdo->exec("ALTER TABLE suspects ADD COLUMN case_id INT UNSIGNED DEFAULT NULL");
        }
    }

    echo "Suspects table verified and ready!\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
