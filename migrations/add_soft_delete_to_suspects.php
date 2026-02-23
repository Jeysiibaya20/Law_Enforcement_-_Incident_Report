<?php
/**
 * Migration: Add soft delete support to suspects table
 * Adds deleted_at column to enable soft deletes (stash)
 */

require_once dirname(__DIR__) . '/config/db_connect.php';

try {
    // Check if deleted_at column already exists
    $stmt = $pdo->query("DESCRIBE suspects");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $hasDeletedAt = false;
    
    foreach ($columns as $column) {
        if ($column['Field'] === 'deleted_at') {
            $hasDeletedAt = true;
            break;
        }
    }
    
    if (!$hasDeletedAt) {
        // Add deleted_at column after updated_at
        $pdo->exec("ALTER TABLE suspects ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL AFTER updated_at");
        echo "✓ Column 'deleted_at' added to suspects table\n";
    } else {
        echo "✓ Column 'deleted_at' already exists in suspects table\n";
    }
    
    echo "Migration completed successfully!\n";
    exit(0);
    
} catch (Exception $e) {
    echo "Error during migration: " . $e->getMessage() . "\n";
    exit(1);
}
?>
