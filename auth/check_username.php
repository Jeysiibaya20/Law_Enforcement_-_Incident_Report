<?php
require_once '../config/db_connect.php';
header('Content-Type: application/json');

$username = trim($_GET['username'] ?? '');
if ($username === '') {
    echo json_encode(['available' => false, 'error' => 'Empty username']);
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT user_id FROM signup WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        echo json_encode(['available' => false]);
    } else {
        echo json_encode(['available' => true]);
    }
} catch (PDOException $e) {
    error_log('check_username.php error: ' . $e->getMessage());
    echo json_encode(['available' => false, 'error' => 'DB error']);
}

?>
