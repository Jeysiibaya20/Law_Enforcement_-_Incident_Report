<?php
/**
 * Migration: Add id_attachment column to suspects table
 * Usage: php migrations/add_id_attachment_to_suspects.php
 */

require_once __DIR__ . '/../config/db_connect.php';

try {
    // Check if column already exists
    $stmt = $pdo->query("SHOW COLUMNS FROM suspects LIKE 'id_attachment'");
    $exists = $stmt->fetch();
    
    if ($exists) {
        echo "✓ Column 'id_attachment' already exists in suspects table.\n";
        exit(0);
    }

    // Add the id_attachment column
    $pdo->exec("
        ALTER TABLE suspects
        ADD COLUMN id_attachment VARCHAR(255) DEFAULT NULL
        AFTER id_number
    ");

    echo "✓ SUCCESS: Added 'id_attachment' column to suspects table\n";
    echo "  Column: id_attachment (VARCHAR 255, nullable)\n";
    echo "  Position: After id_number\n";

} catch (PDOException $e) {
    echo "✗ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
?>
