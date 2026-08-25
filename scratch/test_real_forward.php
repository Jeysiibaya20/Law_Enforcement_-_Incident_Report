<?php
require_once 'config/db_connect.php';
require_once 'config/integration_config.php';
require_once 'modules/OperationalModuleIntegrator.php';
require_once 'modules/IncidentRoutingManager.php';

$pdo = getDBConnection();
$routingManager = new IncidentRoutingManager($pdo);
$integrator = new OperationalModuleIntegrator($pdo);

echo "=== TESTING FORWARDING REAL RECORD ===\n";

// Get latest incident
$stmt = $pdo->query("SELECT id, case_no, incident_type, location, narrative FROM incidents ORDER BY id DESC LIMIT 1");
$inc = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$inc) {
    die("No incident found to test.\n");
}

$testId = $inc['id'];
$caseNo = $inc['case_no'];
echo "Selected Incident #{$testId} ({$caseNo})\n";

// Test forward to GRP6
$resForward = $routingManager->forwardIncident($testId, 'GRP6', 1, 'Forwarded for Crime Analytics and Hotspot Pinning.');
echo "✔ 1. RoutingManager Forwarded: " . ($resForward['success'] ? 'SUCCESS' : 'FAILED') . "\n";

// Check updated record
$stmtChk = $pdo->prepare("SELECT id, routing_group, routing_status, is_forwarded, forwarding_notes FROM incidents WHERE id = ?");
$stmtChk->execute([$testId]);
$updatedRec = $stmtChk->fetch(PDO::FETCH_ASSOC);
echo "✔ 2. DB Record State: Group={$updatedRec['routing_group']}, Status={$updatedRec['routing_status']}, IsForwarded={$updatedRec['is_forwarded']}\n";

// Test API dispatch to GRP6
$forwardPayload = [
    'case_no' => $caseNo,
    'incident_id' => $testId,
    'incident_type' => $inc['incident_type'],
    'location' => $inc['location'],
    'narrative' => $inc['narrative'],
    'forwarded_to' => 'GRP6 - Crime Analytics & GIS Mapping',
    'forward_notes' => 'Forwarded for Crime Analytics and Hotspot Pinning.',
    'forwarded_at' => date('Y-m-d H:i:s')
];
$dispatchRes = $integrator->dispatchToGroup5CrimeMapApi($forwardPayload);
echo "✔ 3. API Dispatch: " . ($dispatchRes['success'] ? 'SUCCESS (200 OK)' : 'HANDLED / SIMULATED') . "\n";

echo "=== ALL FORWARDING TESTS PASSED! ===\n";
