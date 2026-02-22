<?php
require_once '../config/db_connect.php';
header('Content-Type: application/json');

$email = trim($_GET['email'] ?? '');
if ($email === '') {
    echo json_encode(['available' => false, 'error' => 'Empty email']);
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT user_id FROM signup WHERE emailadd = ? LIMIT 1");
    $stmt->execute([$email]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        echo json_encode(['available' => false]);
    } else {
        echo json_encode(['available' => true]);
    }
} catch (PDOException $e) {
    error_log('check_email.php error: ' . $e->getMessage());
    echo json_encode(['available' => false, 'error' => 'DB error']);
}
?>
