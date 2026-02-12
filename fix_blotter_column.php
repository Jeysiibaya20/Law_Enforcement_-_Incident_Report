<?php
/**
 * Fix missing created_by column issue in blotters table
 */

require_once __DIR__ . '/config/db_connect.php';

try {
    // Check if created_by column exists
    $stmt = $pdo->prepare("SHOW COLUMNS FROM blotters LIKE 'created_by'");
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        echo "Adding created_by column to blotters table...\n";
        
        // Add the created_by column
        $pdo->exec("
            ALTER TABLE blotters 
            ADD COLUMN created_by INT(11) DEFAULT NULL 
            AFTER officer_id
        ");
        
        echo "✓ Successfully added created_by column\n";
    } else {
        echo "✓ created_by column already exists\n";
    }
    
    // Verify the column
    $stmt = $pdo->query("DESCRIBE blotters");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\n=== Blotters Table Columns ===\n";
    foreach ($columns as $col) {
        echo $col['Field'] . " (" . $col['Type'] . ")\n";
    }
    
    echo "\n✅ Blotter table is now properly configured!\n";
    
} catch (PDOException $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
