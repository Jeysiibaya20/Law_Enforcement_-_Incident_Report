<?php
/**
 * Quick fix to add photo_path column to suspects table
 */

require_once __DIR__ . '/config/db_connect.php';

try {
    // Check if column exists
    $stmt = $pdo->prepare("SHOW COLUMNS FROM suspects LIKE 'photo_path'");
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        echo "Adding photo_path column...\n";
        
        // Add the photo_path column
        $pdo->exec("
            ALTER TABLE suspects 
            ADD COLUMN photo_path VARCHAR(255) DEFAULT NULL 
            AFTER known_aliases
        ");
        
        echo "✓ Successfully added photo_path column to suspects table\n";
    } else {
        echo "✓ photo_path column already exists\n";
    }
    
    // Create uploads directory if it doesn't exist
    $upload_dir = __DIR__ . '/uploads/suspects/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
        echo "✓ Created uploads/suspects directory\n";
    } else {
        echo "✓ uploads/suspects directory already exists\n";
    }
    
    // Create .htaccess file for security
    $htaccess_content = "# Allow viewing of images only
<FilesMatch \"\\.(jpg|jpeg|png|gif|webp)$\">
    Allow from all
</FilesMatch>

# Deny PHP execution in this directory
php_flag engine off
";
    
    $htaccess_file = $upload_dir . '.htaccess';
    if (!file_exists($htaccess_file)) {
        file_put_contents($htaccess_file, $htaccess_content);
        echo "✓ Created .htaccess security file\n";
    }
    
    echo "\n✅ Suspect Photo Support Successfully Enabled!\n";
    echo "You can now upload photos when adding or editing suspects.\n";
    
} catch (PDOException $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
