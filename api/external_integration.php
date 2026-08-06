<?php
// Generic external integration endpoint for receiving data from other systems and forwarding data to external services.
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db_connect.php';

// Optional shared secret to require on incoming requests.
$sharedSecret = getenv('EXTERNAL_API_SECRET') ?: null;
$incomingSecret = trim($_SERVER['HTTP_X_EXTERNAL_SECRET'] ?? $_SERVER['HTTP_X_WEBHOOK_SECRET'] ?? '');

if ($sharedSecret && $incomingSecret !== $sharedSecret) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid or missing integration secret']);
    exit;
}

function jsonResponse($payload, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/*
Example usage:
Invoke-RestMethod -Method Post -Uri "https://report.alertaraqc/api/external_integration.php?action=send" -ContentType "application/json" -Body '{"target_url":"https://other-system/api","payload":{"source":"my_system","status":"ok"}}'
*/

function getRequestPayload() {
    $raw = file_get_contents('php://input');
    $parsed = json_decode($raw, true);
    if (is_array($parsed)) {
        return $parsed;
    }
    if (!empty($_POST)) {
        return $_POST;
    }
    parse_str($_SERVER['QUERY_STRING'] ?? '', $query);
    if (!empty($query)) {
        return $query;
    }
    return [];
}

function logExternalIntegration($type, $payload) {
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }

    $entry = [
        'timestamp' => date('c'),
        'type' => $type,
        'method' => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
        'remote_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'payload' => $payload
    ];

    @file_put_contents($logDir . '/external_integration.log', json_encode($entry, JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND | LOCK_EX);
}

function ensureIntegrationTable(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS external_integration_log (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        direction VARCHAR(20) NOT NULL,
        target_url TEXT NULL,
        payload LONGTEXT NULL,
        response_body LONGTEXT NULL,
        status VARCHAR(20) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function saveIntegrationRecord(PDO $pdo, string $direction, array $data): void {
    $stmt = $pdo->prepare("INSERT INTO external_integration_log (direction, target_url, payload, response_body, status) VALUES (?, ?, ?, ?, ?)");
    $payloadJson = json_encode($data['payload'] ?? $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $responseJson = isset($data['response']) ? json_encode($data['response'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
    $stmt->execute([$direction, $data['target_url'] ?? null, $payloadJson, $responseJson, $data['status'] ?? 'ok']);
}

function forwardPayload(string $targetUrl, array $payload, array $headers = []): array {
    if (empty($targetUrl)) {
        throw new Exception('No target URL provided for outgoing integration');
    }
    if (!function_exists('curl_init')) {
        throw new Exception('cURL extension is required to forward data');
    }

    $httpHeaders = array_filter(array_merge([
        'Content-Type: application/json',
        'Accept: application/json'
    ], $headers));

    $ch = curl_init($targetUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $httpHeaders);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        throw new Exception('Outgoing request failed: ' . $error);
    }

    $decoded = json_decode($response, true);
    return [
        'http_code' => $status,
        'response_body' => $decoded !== null ? $decoded : $response
    ];
}

$payload = getRequestPayload();
action:
$action = strtolower(trim($_GET['action'] ?? $payload['action'] ?? 'receive'));

if ($action === 'send') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['success' => false, 'error' => 'Outgoing data must be sent via POST'], 405);
    }

    $targetUrl = trim($payload['target_url'] ?? getenv('EXTERNAL_TARGET_URL') ?: '');
    $data = $payload['payload'] ?? $payload;
    unset($data['action'], $data['target_url'], $data['headers']);

    $customHeaders = [];
    if (!empty($payload['headers']) && is_array($payload['headers'])) {
        foreach ($payload['headers'] as $key => $value) {
            $customHeaders[] = trim($key) . ': ' . trim($value);
        }
    }

    try {
        $result = forwardPayload($targetUrl, $data, $customHeaders);
        logExternalIntegration('outgoing', [
            'target_url' => $targetUrl,
            'request_payload' => $data,
            'headers' => $customHeaders,
            'response' => $result
        ]);
        ensureIntegrationTable($pdo);
        saveIntegrationRecord($pdo, 'outgoing', [
            'payload' => $data,
            'target_url' => $targetUrl,
            'response' => $result,
            'status' => 'sent'
        ]);
        jsonResponse(['success' => true, 'sent_to' => $targetUrl, 'result' => $result]);
    } catch (Exception $e) {
        logExternalIntegration('outgoing_error', ['error' => $e->getMessage(), 'target_url' => $targetUrl, 'payload' => $data]);
        ensureIntegrationTable($pdo);
        saveIntegrationRecord($pdo, 'outgoing', [
            'payload' => $data,
            'target_url' => $targetUrl,
            'response' => ['error' => $e->getMessage()],
            'status' => 'error'
        ]);
        jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
}

if ($action === 'receive') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'PUT') {
        jsonResponse(['success' => false, 'error' => 'Incoming integration data must be sent via POST or PUT'], 405);
    }

    if (empty($payload)) {
        jsonResponse(['success' => false, 'error' => 'No payload received'], 400);
    }

    logExternalIntegration('incoming', $payload);
    ensureIntegrationTable($pdo);
    saveIntegrationRecord($pdo, 'incoming', [
        'payload' => $payload,
        'status' => 'received'
    ]);
    jsonResponse(['success' => true, 'message' => 'Data received successfully', 'received' => $payload]);
}

if ($action === 'process_module') {
    require_once __DIR__ . '/../modules/OperationalModuleIntegrator.php';
    $integrator = new OperationalModuleIntegrator($pdo);
    $output = $integrator->processInbound($payload);
    logExternalIntegration('process_module', $output);
    ensureIntegrationTable($pdo);
    saveIntegrationRecord($pdo, 'process_module', [
        'payload' => $payload,
        'response' => $output,
        'status' => 'processed'
    ]);
    jsonResponse(['success' => true, 'processed_output' => $output]);
}

if ($action === 'receive_cctv_footage') {
    require_once __DIR__ . '/../modules/OperationalModuleIntegrator.php';
    $integrator = new OperationalModuleIntegrator($pdo);
    try {
        $result = $integrator->processIncomingCctvFootage($payload);
        jsonResponse($result, 200);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'error' => $e->getMessage()], 400);
    }
}

if ($action === 'receive_resolved_tip') {
    require_once __DIR__ . '/../modules/OperationalModuleIntegrator.php';
    $integrator = new OperationalModuleIntegrator($pdo);
    try {
        $result = $integrator->processIncomingResolvedTip($payload);
        jsonResponse($result, 200);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'error' => $e->getMessage()], 400);
    }
}

if ($action === 'fetch_campaigns') {
    require_once __DIR__ . '/../modules/OperationalModuleIntegrator.php';
    $integrator = new OperationalModuleIntegrator($pdo);
    try {
        $result = $integrator->fetchPublicCampaigns();
        jsonResponse($result, 200);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'error' => $e->getMessage()], 400);
    }
}

if ($action === 'receive_community_complaint') {
    require_once __DIR__ . '/../modules/OperationalModuleIntegrator.php';
    $integrator = new OperationalModuleIntegrator($pdo);
    try {
        $result = $integrator->processIncomingCommunityComplaint($payload);
        jsonResponse($result, 200);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'error' => $e->getMessage()], 400);
    }
}

if ($action === 'receive_emergency_call') {
    require_once __DIR__ . '/../modules/OperationalModuleIntegrator.php';
    $integrator = new OperationalModuleIntegrator($pdo);
    try {
        $result = $integrator->processIncomingEmergencyCall($payload);
        jsonResponse($result, 200);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'error' => $e->getMessage()], 400);
    }
}

if ($action === 'receive_anonymous_tip') {
    require_once __DIR__ . '/../modules/OperationalModuleIntegrator.php';
    $integrator = new OperationalModuleIntegrator($pdo);
    try {
        $result = $integrator->processIncomingAnonymousTip($payload);
        jsonResponse($result, 200);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'error' => $e->getMessage()], 400);
    }
}

jsonResponse(['success' => false, 'error' => 'Invalid action'], 400);
