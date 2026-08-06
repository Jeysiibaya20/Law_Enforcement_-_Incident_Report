<?php
/**
 * Inbound API Endpoint for Receiving CCTV Footage from Partner Surveillance System
 * Endpoint URL: https://[your-domain]/api/cctv_footage_receive.php
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-External-Secret');

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
    sendJsonResponse(['success' => false, 'error' => 'Method not allowed. CCTV footage must be sent via POST or PUT.'], 405);
}

// Read raw body or $_POST
$rawBody = file_get_contents('php://input');
$payload = json_decode($rawBody, true);

if (!is_array($payload) || empty($payload)) {
    $payload = $_POST;
}

if (empty($payload)) {
    sendJsonResponse(['success' => false, 'error' => 'Empty request payload. Expected JSON object with CCTV footage details.'], 400);
}

try {
    $pdo = getDBConnection();
    $integrator = new OperationalModuleIntegrator($pdo);
    $result = $integrator->processIncomingCctvFootage($payload);
    sendJsonResponse($result, 200);
} catch (Exception $e) {
    sendJsonResponse([
        'success' => false,
        'error' => $e->getMessage()
    ], 400);
}
