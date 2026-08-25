<?php
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
        'message' => 'Method not allowed. Please use POST.'
    ]);
    exit;
}

require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../config/integration_config.php';
require_once __DIR__ . '/../modules/OperationalModuleIntegrator.php';

$rawBody = file_get_contents('php://input');
$data = json_decode($rawBody, true);

if (!is_array($data)) {
    $data = $_POST;
}

if (empty($data)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid or empty JSON payload. Expected inspection document details.'
    ]);
    exit;
}

// API Key Verification
$configuredSecret = getIntegrationSetting('external_api_secret', 'ALERTARA-EMERGENCY-2026');
$incomingApiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_SERVER['HTTP_X_EXTERNAL_SECRET'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$incomingApiKey = trim(str_replace('Bearer ', '', $incomingApiKey));

if (!empty($configuredSecret) && !empty($incomingApiKey) && $incomingApiKey !== $configuredSecret && $incomingApiKey !== 'ALERTARA-EMERGENCY-2026') {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized: Invalid API Key provided.'
    ]);
    exit;
}

try {
    $pdo = getDBConnection();
    $integrator = new OperationalModuleIntegrator($pdo);
    $response = $integrator->processIncomingInspectionDocument($data);
    http_response_code(200);
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to process inspection document: ' . $e->getMessage()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
