<?php
require_once 'config/db_connect.php';
$pdo = getDBConnection();

$sql = "CREATE TABLE IF NOT EXISTS `received_inspection_documents` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `request_id` VARCHAR(100) NULL,
    `document_id` VARCHAR(100) NULL,
    `case_no` VARCHAR(100) NULL,
    `document_type` VARCHAR(150) DEFAULT 'Inspection Report',
    `business_or_location` VARCHAR(255) NULL,
    `inspector_name` VARCHAR(150) NULL,
    `inspection_status` VARCHAR(100) DEFAULT 'Completed',
    `findings` TEXT NULL,
    `compliance_score` VARCHAR(50) NULL,
    `certificate_url` TEXT NULL,
    `evidence_urls` TEXT NULL,
    `inspection_date` DATE NULL,
    `raw_payload` LONGTEXT NULL,
    `received_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (`request_id`),
    INDEX (`case_no`),
    INDEX (`inspection_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

$pdo->exec($sql);
echo "Table `received_inspection_documents` verified/created successfully!\n";
