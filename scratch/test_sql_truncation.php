<?php
require_once 'config/db_connect.php';
$pdo = getDBConnection();

try {
    echo "1. Testing cctv_requests UPDATE with 'Dispatched':\n";
    $stmt = $pdo->prepare("UPDATE cctv_requests SET status = 'Dispatched', updated_at = NOW() WHERE id = 1");
    $stmt->execute();
    echo "   -> Success!\n";
} catch (Exception $e) {
    echo "   -> Error: " . $e->getMessage() . "\n";
}

try {
    echo "2. Testing external_integration_log INSERT with 'partner_api_offline_or_failed':\n";
    $stmt = $pdo->prepare("INSERT INTO external_integration_log (direction, target_url, payload, response_body, status) VALUES ('outgoing_cctv', 'https://policy.alertaraqc.com', '{}', '{}', 'partner_api_offline_or_failed')");
    $stmt->execute();
    echo "   -> Success!\n";
} catch (Exception $e) {
    echo "   -> Error: " . $e->getMessage() . "\n";
}
