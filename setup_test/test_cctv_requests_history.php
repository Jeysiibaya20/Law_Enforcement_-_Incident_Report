<?php
require_once __DIR__ . '/../config/db_connect.php';
try {
    $pdo = getDBConnection();
    
    // Insert test record into cctv_requests
    $stmt = $pdo->prepare("INSERT INTO cctv_requests (requested_by, request_type, camera_location, incident_date, incident_time, priority, reason, additional_details, monitoring_office, delivery_method, monitoring_notes, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        1,
        'Footage',
        'Main Lobby & Entrance Gate',
        '2026-08-06',
        '20:15:00',
        'High',
        'Verification of commotion near front entrance',
        'Ref #BLOTTER-2026-0092',
        'Control Room',
        'Portal',
        'Under review by surveillance operator',
        'Pending'
    ]);
    $id = $pdo->lastInsertId();
    echo "Sample CCTV request created with ID: #REQ-" . str_pad($id, 3, '0', STR_PAD_LEFT) . "\n";

    // Query records as Request_form.php does
    $records_stmt = $pdo->prepare("SELECT r.id, r.request_type, r.camera_location, r.incident_date, r.incident_time, r.priority, r.reason, r.additional_details, r.monitoring_office, r.delivery_method, r.monitoring_notes, r.status, r.requested_at, COALESCE(s.fullname, s.emailadd, 'Admin') as requester_name FROM cctv_requests r LEFT JOIN signup s ON r.requested_by = s.user_id ORDER BY r.requested_at DESC");
    $records_stmt->execute();
    $records = $records_stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Total records fetched: " . count($records) . "\n";
    print_r($records);

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
