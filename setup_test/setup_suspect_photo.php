<?php
/**
 * Add photo_path column to suspects table
 * Run this once to add photo support to suspects
 */

require_once __DIR__ . '/config/db_connect.php';

try {
    // Check if column exists
    $stmt = $pdo->prepare("SHOW COLUMNS FROM suspects LIKE 'photo_path'");
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        // Add the photo_path column
        $pdo->exec("
            ALTER TABLE suspects 
            ADD COLUMN photo_path VARCHAR(255) DEFAULT NULL 
            AFTER known_aliases
        ");
        
        echo "✓ Successfully added photo_path column to suspects table<br>";
    } else {
        echo "✓ photo_path column already exists<br>";
    }
    
    // Create uploads directory if it doesn't exist
    $upload_dir = __DIR__ . '/uploads/suspects/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
        echo "✓ Created uploads/suspects directory<br>";
    } else {
        echo "✓ uploads/suspects directory already exists<br>";
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
        echo "✓ Created .htaccess security file<br>";
    }
    
    echo "<br><strong>Suspect Photo Support Successfully Enabled!</strong><br>";
    echo "You can now upload photos when adding or editing suspects.";
    
} catch (PDOException $e) {
    http_response_code(500);
    echo "✗ Error: " . $e->getMessage();
    exit;
}
?>
