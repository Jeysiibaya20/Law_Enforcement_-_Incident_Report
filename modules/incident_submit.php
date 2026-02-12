<?php
// Minimal JSON endpoint to accept quick incident logs
header('Content-Type: application/json');
if (session_status() == PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/IncidentWorkflowManager.php';

// Only allow POST from authenticated users
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

// Read JSON body if content-type application/json
$input = $_POST;
if (strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    $json = json_decode($raw, true);
    if (is_array($json)) $input = array_merge($input, $json);
}

try {
    $reporter_name = trim($input['reporter_name'] ?? ($_SESSION['fullname'] ?? ''));
    $incident_date = $input['incident_date'] ?? date('Y-m-d');
    $incident_type = $input['incident_type'] ?? 'Other';
    $narrative = trim($input['narrative'] ?? '');
    $location = trim($input['location'] ?? '');

    if (empty($reporter_name) || empty($narrative)) {
        throw new Exception('Reporter name and narrative are required');
    }

    // Build incident data
    $created_by = $_SESSION['user_id'];
    $incident_data = [
        'case_no' => 'INC-' . date('Ymd') . '-' . strtoupper(substr(md5(time() . rand()), 0, 5)),
        'incident_type' => $incident_type,
        'incident_subtype' => '',
        'reporter_name' => $reporter_name,
        'reporter_email' => $input['reporter_email'] ?? '',
        'reporter_phone' => $input['reporter_phone'] ?? '',
        'reporter_type' => $input['reporter_type'] ?? 'Citizen',
        'incident_date' => $incident_date,
        'incident_time' => $input['incident_time'] ?? '00:00',
        'location' => $location,
        'latitude' => null,
        'longitude' => null,
        'narrative' => $narrative,
        'evidence_description' => $input['evidence_description'] ?? '',
        'victim_name' => $input['victim_name'] ?? '',
        'victim_age' => !empty($input['victim_age']) ? intval($input['victim_age']) : null,
        'victim_gender' => $input['victim_gender'] ?? null,
        'suspect_name' => $input['suspect_name'] ?? '',
        'created_by' => $created_by
    ];

    $workflow_manager = new IncidentWorkflowManager($pdo);
    $result = $workflow_manager->processIncidentReport($incident_data);

    if ($result['success']) {
        echo json_encode(['success' => true, 'case_no' => $incident_data['case_no'], 'message' => 'Incident logged successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $result['error'] ?? 'Processing failed']);
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
