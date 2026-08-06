<?php
/**
 * Test runner for external integration endpoints
 */
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../modules/OperationalModuleIntegrator.php';

echo "=== STARTING EXTERNAL INTEGRATION TEST ===\n\n";

$pdo = getDBConnection();
$integrator = new OperationalModuleIntegrator($pdo);

// 1. Test outbound CCTV request building
echo "[1/4] Testing Outbound CCTV Request Builder...\n";
$sampleRaw = [
    'source' => 'group_4_tip',
    'location' => 'Barangay Central, Quezon City',
    'description' => 'Suspicious vehicle parked near store front.',
    'emergency_level' => 'High',
    'complainant_name' => 'John Doe'
];
$processed = $integrator->processInbound($sampleRaw, false);
$cctvPayload = $processed['module_specific_payloads']['cctv_partner_surveillance_api'];
assert(!empty($cctvPayload['endpoint']), "Endpoint should not be empty");
assert($cctvPayload['endpoint'] === 'https://surveillance.alertaraqc.com/api/cctv_requests_receive.php', "Endpoint must match target URL");
echo "PASS: Outbound endpoint is " . $cctvPayload['endpoint'] . "\n\n";

// 2. Test Inbound CCTV Footage Processor
echo "[2/4] Testing Inbound CCTV Footage Ingestion...\n";
$inboundCctv = [
    'request_id' => 'REQ-CCTV-TEST-001',
    'incident_id' => 'INC-2026-001',
    'cctv_url' => 'https://surveillance.alertaraqc.com/media/feeds/camera_feed_QC01.mp4',
    'camera_id' => 'CAM-QC-NORTH-01',
    'location' => 'Barangay Central, Quezon City',
    'notes' => 'Footage verified by operator #05'
];
$cctvResult = $integrator->processIncomingCctvFootage($inboundCctv);
assert($cctvResult['success'] === true, "CCTV ingestion must succeed");
echo "PASS: Inbound CCTV stored. Record ID: #" . $cctvResult['record_id'] . "\n\n";

// 3. Test Inbound Resolved Tip Processor
echo "[3/4] Testing Inbound Resolved Tip Ingestion...\n";
$inboundTip = [
    'tip_id' => 'TIP-TEST-999',
    'incident_id' => 'INC-2026-002',
    'incident_type' => 'Public Disturbance',
    'title' => 'Resolved Commotion Tip',
    'description' => 'Tip reported verbal altercation. Surveillance operator monitored and confirmed resolved.',
    'location' => 'District 1, Quezon City',
    'resolved_by' => 'Officer Santos',
    'resolution_notes' => 'Resolved on site by responding unit'
];
$tipResult = $integrator->processIncomingResolvedTip($inboundTip);
assert($tipResult['success'] === true, "Tip ingestion must succeed");
echo "PASS: Inbound Resolved Tip logged & classified. Record ID: #" . $tipResult['record_id'] . "\n\n";

// 4. Verify Log History Table
echo "[4/4] Verifying `external_integration_log` entries...\n";
$stmt = $pdo->query("SELECT COUNT(*) FROM external_integration_log");
$count = $stmt->fetchColumn();
echo "PASS: Total log entries in `external_integration_log`: " . $count . "\n";

echo "\n=== ALL INTEGRATION TESTS COMPLETED SUCCESSFULLY! ===\n";
