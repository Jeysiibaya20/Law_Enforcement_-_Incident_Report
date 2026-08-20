<?php
require_once __DIR__ . '/../config/db_connect.php';
$pdo = getDBConnection();

$queries = [
    "CREATE TABLE IF NOT EXISTS `user_two_factor` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL UNIQUE,
        `secret` VARCHAR(64) NOT NULL,
        `type` ENUM('TOTP', 'EMAIL', 'SMS') DEFAULT 'TOTP',
        `enabled` TINYINT(1) DEFAULT 1,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX `idx_user_totp` (`user_id`, `enabled`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    "CREATE TABLE IF NOT EXISTS `two_factor_codes` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `code` VARCHAR(10) NOT NULL,
        `type` ENUM('TOTP', 'EMAIL', 'SMS') DEFAULT 'TOTP',
        `expires_at` DATETIME NOT NULL,
        `used` TINYINT(1) DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_user_tfa_lookup` (`user_id`, `type`, `used`, `expires_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
];

foreach ($queries as $q) {
    $pdo->exec($q);
}

echo "TOTP and 2FA tables verified and ready!" . PHP_EOL;
