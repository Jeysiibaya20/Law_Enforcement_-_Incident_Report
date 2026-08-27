<?php
/**
 * Public Violation Lookup API for Residents/Users
 * Method: GET or POST
 * Query parameter: query or public_violation_id
 */

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../config/db_connect.php';

try {
    $pdo = getDBConnection();
    $query = trim($_GET['query'] ?? $_GET['public_violation_id'] ?? $_POST['query'] ?? $_POST['public_violation_id'] ?? '');

    if (empty($query)) {
        echo json_encode([
            'success' => false,
            'message' => 'Please provide a Public Violation ID, Violation ID, or Plate Number.'
        ]);
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM received_violation_reports WHERE public_violation_id = ? OR violation_id = ? OR plate_number = ? OR mirrored_case_no = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$query, $query, $query, $query]);
    $violation = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$violation) {
        // Try partial match on public_violation_id or plate
        $stmt2 = $pdo->prepare("SELECT * FROM received_violation_reports WHERE public_violation_id LIKE ? OR plate_number LIKE ? ORDER BY id DESC LIMIT 1");
        $stmt2->execute(["%{$query}%", "%{$query}%"]);
        $violation = $stmt2->fetch(PDO::FETCH_ASSOC);
    }

    if (!$violation) {
        echo json_encode([
            'success' => false,
            'message' => 'No violation record found matching the specified ID or Plate Number.'
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'violation_id' => $violation['violation_id'],
            'public_violation_id' => $violation['public_violation_id'],
            'road_name' => $violation['road_name'],
            'subject_type' => $violation['subject_type'],
            'plate_number' => $violation['plate_number'],
            'vehicle_type' => $violation['vehicle_type'],
            'violation_datetime' => $violation['violation_datetime'],
            'location_details' => $violation['location_details'],
            'description' => $violation['description'],
            'verification_status' => $violation['verification_status'],
            'offense_level' => $violation['offense_level'],
            'cloudinary_url' => $violation['cloudinary_url'],
            'mirrored_case_no' => $violation['mirrored_case_no'],
            'received_at' => $violation['received_at']
        ]
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error while querying violation status: ' . $e->getMessage()
    ]);
}
