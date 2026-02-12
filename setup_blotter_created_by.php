<?php
require 'config/db_connect.php';

try {
    // Check if created_by column exists
    $checkColumn = $pdo->query("SHOW COLUMNS FROM blotters LIKE 'created_by'");
    
    if ($checkColumn->rowCount() === 0) {
        // Add created_by column after officer_id
        $pdo->exec("ALTER TABLE blotters ADD COLUMN created_by INT NULL AFTER officer_id");
        echo "<div style='background: #d4edda; padding: 20px; margin: 20px; border-radius: 5px;'>";
        echo "<h3 style='color: #155724;'>✓ Success</h3>";
        echo "<p>Column 'created_by' added to blotters table.</p>";
        echo "</div>";
    } else {
        echo "<div style='background: #e2e3e5; padding: 20px; margin: 20px; border-radius: 5px;'>";
        echo "<h3>Column already exists</h3>";
        echo "<p>The 'created_by' column is already present in the blotters table.</p>";
        echo "</div>";
    }
} catch (PDOException $e) {
    echo "<div style='background: #f8d7da; padding: 20px; margin: 20px; border-radius: 5px;'>";
    echo "<h3 style='color: #721c24;'>✗ Error</h3>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}
?>
<style>
    body {
        font-family: Arial, sans-serif;
        background: #f8f9fa;
    }
</style>
