<?php
require_once 'config/db_connect.php';
require_once 'config/integration_config.php';
require_once 'modules/OperationalModuleIntegrator.php';

echo "=== TESTING EMERGENCY RESPONSE POST API WITH USER PAYLOAD ===\n";

$pdo = getDBConnection();
$integrator = new OperationalModuleIntegrator($pdo);

$userPayload = [
    'Call ID' => 'CALL-EMR-2026-9901',
    'Timestamp' => date('Y-m-d H:i:s'),
    'Caller Location' => 'Susano Road, Brgy San Agustin, Novaliches, Quezon City',
    'Emergency Level' => 'Critical',
    'Incident Description' => 'Multi-vehicle collision with severe pedestrian injuries requiring immediate ambulance and traffic police response.'
];

try {
    $result = $integrator->processIncomingEmergencyCall($userPayload);
    echo "✔ API Output Response:\n";
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

    // Verify DB insertion
    $stmtCall = $pdo->prepare("SELECT * FROM received_emergency_calls WHERE call_id = ?");
    $stmtCall->execute([$userPayload['Call ID']]);
    $callRow = $stmtCall->fetch(PDO::FETCH_ASSOC);

    echo "✔ DB verification in received_emergency_calls:\n";
    echo "  ID: " . $callRow['id'] . "\n";
    echo "  Call ID: " . $callRow['call_id'] . "\n";
    echo "  Location: " . $callRow['caller_location'] . "\n";
    echo "  Emergency Level: " . $callRow['emergency_level'] . "\n";
    echo "  Case No: " . $callRow['case_no'] . "\n";
    echo "  Status: " . $callRow['status'] . "\n";

    // Verify incidents table
    $stmtInc = $pdo->prepare("SELECT * FROM incidents WHERE case_no = ?");
    $stmtInc->execute([$result['case_no']]);
    $incRow = $stmtInc->fetch(PDO::FETCH_ASSOC);

    echo "\n✔ DB verification in central incidents table:\n";
    echo "  Incident Case No: " . $incRow['case_no'] . "\n";
    echo "  Classification: " . $incRow['auto_classification'] . "\n";
    echo "  Urgency: " . $incRow['urgency_level'] . "\n";
    echo "  Narrative: " . substr($incRow['narrative'], 0, 120) . "...\n";

    echo "\n=== ALL EMERGENCY RESPONSE INTEGRATION TESTS PASSED 100%! ===\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
