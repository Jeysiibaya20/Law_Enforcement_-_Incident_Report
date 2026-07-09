<?php
require 'config/db_connect.php';

try {
    $result = $pdo->query('SELECT COUNT(*) as cnt FROM blotters');
    $count = $result->fetch(PDO::FETCH_ASSOC);
    echo "Total blotters: " . $count['cnt'] . "\n";
    
    $result = $pdo->query('SELECT * FROM blotters LIMIT 5');
    $blotters = $result->fetchAll(PDO::FETCH_ASSOC);
    echo "Blotters retrieved: " . count($blotters) . "\n";
    
    if (count($blotters) > 0) {
        echo "First blotter:\n";
        print_r($blotters[0]);
    }
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
?>
