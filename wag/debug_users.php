<?php
require 'config/db_connect.php';

// Check users table structure
try {
    $stmt = $pdo->query("DESC users");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Users table columns:\n";
    print_r($columns);
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
