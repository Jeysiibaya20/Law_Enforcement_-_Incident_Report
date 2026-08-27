<?php
/**
 * Inbound API: Receive Citizen Complaints & Road Traffic Violations from TFTR (Mikko's System)
 * Endpoint: POST /api/receive_tftr_complaint.php
 * Endpoint Alias: POST /api/violations/violation_report_api.php
 */

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS, GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-KEY, X-External-Secret, X-Partner-Client');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    echo json_encode(['status' => 'ok']);
    exit;
}

require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../modules/OperationalModuleIntegrator.php';

try {
    $pdo = getDBConnection();
    if (!($pdo instanceof PDO)) {
        throw new Exception('Database connection failed.');
    }

    $integrator = new OperationalModuleIntegrator($pdo);

    // Read payload
    $rawInput = file_get_contents('php://input');
    $data = [];

    if (!empty($rawInput)) {
        $data = json_decode($rawInput, true);
    }

    if (empty($data) && !empty($_POST)) {
        $data = $_POST;
    }

    // Support GET test / documentation
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        echo json_encode([
            'status' => 'online',
            'endpoint' => 'https://report.alertaraqc.com/api/receive_tftr_complaint.php',
            'system' => 'Alertara Law Enforcement & Digital Blotter System (Group 1)',
            'description' => 'Dedicated Inbound Receiver for TFTR Citizen Complaints and Traffic Violations from Mikko (tftr.alertaraqc.com)',
            'accepted_method' => 'POST',
            'sample_payload' => [
                'date' => '2026-08-27',
                'time' => '21:40:00',
                'complainant_name' => 'Maria Santos',
                'complainant_address' => '123 Aurora Blvd, Cubao, Quezon City',
                'complainant_contact' => '09171234567',
                'defendant_name' => 'John Doe (PUV Driver)',
                'defendant_address' => 'Quezon City',
                'defendant_contact' => '09189876543',
                'complaint_type' => 'Overcharging / Reckless Driving',
                'description' => 'Jeepney driver overcharged passengers and drove aggressively along Aurora Blvd.'
            ]
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if (empty($data) || !is_array($data)) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid or empty payload received. Please send valid JSON or form data.'
        ]);
        exit;
    }

    // Process and register complaint
    $result = $integrator->processIncomingTftrComplaint($data);

    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'message' => 'TFTR citizen complaint received and processed successfully into Alertara Law Enforcement registry.',
        'data' => $result
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Server error processing TFTR complaint: ' . $e->getMessage()
    ]);
}
