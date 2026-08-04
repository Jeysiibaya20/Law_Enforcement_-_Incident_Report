<?php
require 'config/db_connect.php';

// Check blotters table
try {
    $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM blotters");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Total blotters in DB: " . $result['cnt'] . "\n";
    
    // Try the actual query
    $sql = "SELECT b.*, COALESCE(s.fullname, '') AS officer
    FROM blotters b
    LEFT JOIN signup s ON s.user_id = b.officer_id
    WHERE b.status != 'Archived'
    ORDER BY b.created_at DESC";
    
    $stmt = $pdo->query($sql);
    $blotters = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Blotters fetched: " . count($blotters) . "\n";
    
    if (count($blotters) > 0) {
        echo "First blotter:\n";
        print_r($blotters[0]);
    } else {
        echo "No non-archived blotters found\n";
    }
    
    // Check if there are ANY blotters at all
    $stmt2 = $pdo->query("SELECT id, blotter_no, status FROM blotters LIMIT 5");
    $all = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    echo "\nAll blotters (including archived):\n";
    print_r($all);
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
