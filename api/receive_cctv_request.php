<?php
/**
 * Inbound API Endpoint for Receiving CCTV Footage Requests from Marto's Group / External Surveillance Partners
 * Ingests directly into cctv_requests and logs integration.
 * 
 * Endpoint URL:
 *   POST https://[your-domain]/api/receive_cctv_request.php
 *   POST http://localhost/Law_Enforcement_-_Incident_Report/api/receive_cctv_request.php
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-External-Secret, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../modules/OperationalModuleIntegrator.php';

function sendJsonResponse(array $payload, int $statusCode = 200): void {
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'PUT') {
    sendJsonResponse([
        'success' => false,
        'error' => 'Method not allowed. CCTV footage requests must be sent via POST or PUT.'
    ], 405);
}

$rawBody = file_get_contents('php://input');
$payload = json_decode($rawBody, true);

if (!is_array($payload) || empty($payload)) {
    $payload = $_POST;
}

if (empty($payload)) {
    sendJsonResponse([
        'success' => false,
        'error' => 'Empty request payload. Expected JSON object with CCTV footage request details.'
    ], 400);
}

try {
    $pdo = getDBConnection();
    $integrator = new OperationalModuleIntegrator($pdo);
    $result = $integrator->processIncomingCctvRequest($payload);
    sendJsonResponse($result, 200);
} catch (Exception $e) {
    sendJsonResponse([
        'success' => false,
        'error' => $e->getMessage()
    ], 400);
}
