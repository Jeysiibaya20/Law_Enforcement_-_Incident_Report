<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db_connect.php';

try {
    $stmt = $pdo->prepare("SELECT id, blotter_no, complainant_name, incident_date FROM blotters ORDER BY created_at DESC LIMIT 6");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['ok' => true, 'data' => $rows]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}

?>
