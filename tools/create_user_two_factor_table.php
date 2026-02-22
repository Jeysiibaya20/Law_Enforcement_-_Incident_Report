<?php
// CLI helper to create user_two_factor table
// Usage: php tools/create_user_two_factor_table.php

// Ensure this runs from CLI only
if (php_sapi_name() !== 'cli') {
    echo "This script is intended to be run from the command line only.\n";
    exit(1);
}

// Temporarily enable the app so config/db_connect.php doesn't exit
putenv('ENABLE_APP=1');

require_once __DIR__ . '/../config/db_connect.php';

$sql = "CREATE TABLE IF NOT EXISTS user_two_factor (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  secret VARCHAR(128) NOT NULL,
  type VARCHAR(20) NOT NULL DEFAULT 'TOTP',
  enabled TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  UNIQUE KEY (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

try {
    $pdo->exec($sql);
    echo "Table `user_two_factor` created or already exists.\n";
    exit(0);
} catch (Exception $e) {
    echo "Failed to create table: " . $e->getMessage() . "\n";
    exit(2);
}
