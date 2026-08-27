<?php
/**
 * Inbound API: Receive CCTV Road & Vendor Violation Report
 * Method: POST
 * Accepts JSON or Form Data with Cloudinary media URLs
 */

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-KEY, X-External-Secret, X-Partner-Client');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method Not Allowed. Use HTTP POST to transmit CCTV violation reports.'
    ]);
    exit;
}

require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../modules/OperationalModuleIntegrator.php';
require_once __DIR__ . '/../includes/audit_logger.php';

try {
    $pdo = getDBConnection();
    $integrator = new OperationalModuleIntegrator($pdo);

    $raw = file_get_contents('php://input');
    $payload = json_decode($raw, true);

    if (!is_array($payload) || empty($payload)) {
        $payload = $_POST;
    }

    if (empty($payload)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Empty request body. Please transmit a JSON payload with violation details.'
        ]);
        exit;
    }

    $result = $integrator->processIncomingViolationReport($payload);

    http_response_code(200);
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to process violation report: ' . $e->getMessage()
    ]);
}
