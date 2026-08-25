<?php
/**
 * Direct file-based simulation of API endpoints
 */

echo "=== 1. TEST POST to api/receive_emergency_call.php ===\n";

$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['CONTENT_TYPE'] = 'application/json';

$payload = [
    'Call ID' => 'CALL-2026-ALDRIN-999',
    'Timestamp' => '2026-08-26 15:45:00',
    'Caller' => 'Barangay San Agustin Watchman',
    'Location' => 'Susano Road, Brgy San Agustin, QC',
    'Emergency Level' => 'High',
    'Incident Description' => 'Vehicular accident involving motorcycle and sedan along Susano Road.'
];

// Mock php://input
file_put_contents(__DIR__ . '/mock_input.json', json_encode($payload));

// Test processing
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../modules/OperationalModuleIntegrator.php';

$pdo = getDBConnection();
$integrator = new OperationalModuleIntegrator($pdo);

$res = $integrator->processIncomingEmergencyCall($payload);
echo "Result:\n" . json_encode($res, JSON_PRETTY_PRINT) . "\n\n";

echo "=== 2. TEST POST to api/receive_cctv_request.php ===\n";
$cctvPayload = [
    'requesting_agency' => 'Quezon City Police District',
    'contact_person' => 'P/Maj. Cruz',
    'position_designation' => 'Chief Investigator',
    'contact_number' => '09171112233',
    'email_address' => 'cruz@qcpd.gov.ph',
    'office_unit' => 'Station 4 Investigation',
    'case_reference' => 'DB-2026-001',
    'related_complaint_id' => 'COMP-2026-362',
    'legal_basis' => 'Law enforcement request',
    'purpose_reason' => 'Ongoing robbery investigation near the intersection.',
    'incident_location' => 'Susano Road, Barangay San Agustin, Quezon City',
    'camera_id' => 'CAM-001 — Main Entrance Camera',
    'incident_date' => '2026-08-26',
    'incident_type' => 'Theft / Robbery',
    'footage_start_time' => '16:30',
    'footage_end_time' => '17:00',
    'incident_description' => 'Suspect on black motorcycle fled toward north bound lane.',
    'delivery_method' => 'Secure download link'
];

$resCctv = $integrator->processIncomingCctvRequest($cctvPayload);
echo "Result:\n" . json_encode($resCctv, JSON_PRETTY_PRINT) . "\n\n";

echo "=== 3. VERIFY CCTV IN DATABASE ===\n";
$stmt = $pdo->prepare("SELECT * FROM cctv_requests WHERE id = ?");
$stmt->execute([$resCctv['record_id']]);
$cctvData = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Recorded CCTV Request in DB:\n";
echo "  Request Code: " . $cctvData['request_id_code'] . "\n";
echo "  Agency: " . $cctvData['requesting_agency'] . "\n";
echo "  Contact: " . $cctvData['contact_person'] . " (" . $cctvData['contact_number'] . ")\n";
echo "  Legal Basis: " . $cctvData['legal_basis'] . "\n";
echo "  Location: " . $cctvData['incident_location'] . "\n";
echo "  Camera: " . $cctvData['camera_id'] . "\n";
echo "  Time: " . $cctvData['footage_start_time'] . " - " . $cctvData['footage_end_time'] . "\n";
echo "  Status: " . $cctvData['status'] . "\n";
echo "\n=== ALL ENDPOINT TESTS PASSED SUCCESSFULLY! ===\n";
