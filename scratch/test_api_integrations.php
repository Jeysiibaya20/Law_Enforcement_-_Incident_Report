<?php
/**
 * Test script for verifying API endpoints and database integrity
 */
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../modules/OperationalModuleIntegrator.php';

$pdo = getDBConnection();
$integrator = new OperationalModuleIntegrator($pdo);

echo "=== 1. TESTING ALDRIN'S EMERGENCY CALL INGESTION ===\n";

$testAldrinPayload = [
    'Call ID' => 'CALL-ALDRIN-2026-TEST01',
    'Timestamp' => date('Y-m-d H:i:s'),
    'Caller' => 'Aldrin Lead Dispatcher',
    'Location' => 'Susano Road, Barangay San Agustin, Novaliches, Quezon City',
    'Emergency Level' => 'High',
    'Incident Description' => 'Physical fight and disturbance reported by resident near convenience store.'
];

try {
    $resCall = $integrator->processIncomingEmergencyCall($testAldrinPayload);
    echo "✔ Emergency Call Processed Successfully!\n";
    echo "  Record ID: " . $resCall['record_id'] . "\n";
    echo "  Call ID: " . $resCall['call_id'] . "\n";
    echo "  Case No: " . $resCall['case_no'] . "\n";
    echo "  Caller: " . $resCall['caller'] . "\n";
    echo "  Location: " . $resCall['location'] . "\n";
    echo "  Emergency Level: " . $resCall['emergency_level'] . "\n";
    echo "  Incident Type: " . $resCall['incident_type'] . "\n";

    // Verify DB insertion
    $stmtChk = $pdo->prepare("SELECT * FROM received_emergency_calls WHERE call_id = ?");
    $stmtChk->execute([$testAldrinPayload['Call ID']]);
    $row = $stmtChk->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        echo "✔ DB Verify: Found row in received_emergency_calls with id=" . $row['id'] . "\n";
    } else {
        echo "❌ DB Verify: Record not found in received_emergency_calls\n";
    }

    // Verify incidents table mirror
    $stmtInc = $pdo->prepare("SELECT * FROM incidents WHERE case_no = ?");
    $stmtInc->execute([$resCall['case_no']]);
    $incRow = $stmtInc->fetch(PDO::FETCH_ASSOC);
    if ($incRow) {
        echo "✔ DB Verify: Successfully mirrored to incidents table with case_no=" . $incRow['case_no'] . "\n";
    } else {
        echo "❌ DB Verify: Incident not mirrored to incidents table\n";
    }
} catch (Exception $e) {
    echo "❌ Error in Emergency Call: " . $e->getMessage() . "\n";
}

echo "\n=== 2. TESTING MARTO'S CCTV REQUEST INGESTION ===\n";

$testCctvReqPayload = [
    'requesting_agency' => 'Quezon City Police District',
    'contact_person' => 'P/Cpt. Ana Reyes',
    'position_designation' => 'Lead Investigator',
    'contact_number' => '09171234567',
    'email_address' => 'ana.reyes@qcpd.gov.ph',
    'office_unit' => 'Investigation Section',
    'case_reference' => 'INV-2026-SAMPLE-01',
    'related_complaint_id' => 'COMP-2026-362',
    'legal_basis' => 'Law enforcement request',
    'purpose_reason' => 'Investigation — Footage needed for ongoing investigation of suspicious activity near the barangay hall.',
    'incident_location' => 'Susano Road, Barangay San Agustin, Quezon City',
    'camera_id' => 'CAM-001 — Main Entrance Camera',
    'location_description' => 'Main entrance camera facing Susano Road',
    'incident_date' => date('Y-m-d'),
    'incident_type' => 'Suspicious Activity',
    'footage_start_time' => '16:30',
    'footage_end_time' => '17:00',
    'incident_description' => 'Persons loitering near the main entrance after hours.',
    'delivery_method' => 'Secure download link'
];

try {
    $resCctv = $integrator->processIncomingCctvRequest($testCctvReqPayload);
    echo "✔ CCTV Request Processed Successfully!\n";
    echo "  Record ID: " . $resCctv['record_id'] . "\n";
    echo "  Request Code: " . $resCctv['request_id_code'] . "\n";
    echo "  Agency: " . $resCctv['requesting_agency'] . "\n";

    // Verify DB insertion
    $stmtCctvChk = $pdo->prepare("SELECT * FROM cctv_requests WHERE id = ?");
    $stmtCctvChk->execute([$resCctv['record_id']]);
    $cctvRow = $stmtCctvChk->fetch(PDO::FETCH_ASSOC);
    if ($cctvRow) {
        echo "✔ DB Verify: Found row in cctv_requests with legal_basis='{$cctvRow['legal_basis']}'\n";
    }
} catch (Exception $e) {
    echo "❌ Error in CCTV Request: " . $e->getMessage() . "\n";
}

echo "\n=== 3. TESTING CCTV FOOTAGE RECEIVE ===\n";

$testFootagePayload = [
    'request_id' => $resCctv['request_id_code'] ?? 'CCTV-REQ-2026-001',
    'incident_id' => 'INC-2026-001',
    'cctv_url' => 'https://surveillance.alertaraqc.com/media/feeds/sample_footage_001.mp4',
    'camera_id' => 'CAM-001 — Main Entrance Camera',
    'location' => 'Susano Road, Barangay San Agustin, Quezon City',
    'video_format' => 'video/mp4',
    'duration' => '30 mins',
    'notes' => 'High-resolution playback export ready.'
];

try {
    $resFootage = $integrator->processIncomingCctvFootage($testFootagePayload);
    echo "✔ CCTV Footage Processed Successfully!\n";
    echo "  Record ID: " . $resFootage['record_id'] . "\n";
    echo "  CCTV URL: " . $resFootage['cctv_url'] . "\n";
} catch (Exception $e) {
    echo "❌ Error in CCTV Footage Receive: " . $e->getMessage() . "\n";
}

echo "\n=== 4. TESTING ALL-IN-ONE API (api/api.php) ===\n";
$_REQUEST = ['action' => 'all'];
ob_start();
require __DIR__ . '/../api/api.php';
$apiOutput = ob_get_clean();
$apiJson = json_decode($apiOutput, true);

if ($apiJson && $apiJson['status'] === 'success') {
    echo "✔ All-In-One API Returned 200 OK with " . count($apiJson['data']['modules'] ?? []) . " module categories!\n";
} else {
    echo "❌ API Output Error: " . substr($apiOutput, 0, 300) . "\n";
}

echo "\n=== ALL VERIFICATION CHECKS COMPLETE ===\n";
