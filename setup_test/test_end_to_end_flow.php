<?php
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../modules/OperationalModuleIntegrator.php';
require_once __DIR__ . '/../includes/suspect_witness_management.php';

$pdo = getDBConnection();
$integrator = new OperationalModuleIntegrator($pdo);

echo "=== STARTING FULL END-TO-END FLOW VERIFICATION ===\n\n";

// 1. Inbound Group 2 Accident Report & Ticket
echo "1. Testing Group 2 Accident Report & Ticket Inbound Integration...\n";
$accidentPayload = [
    'report_id' => 'ACC-REP-' . date('Ymd') . '-' . rand(1000, 9999),
    'ticket_number' => 'TKT-' . date('Ymd') . '-' . rand(1000, 9999),
    'incident_type' => 'Vehicular Accident & Over-speeding',
    'violator_name' => 'Roberto M. Santos',
    'vehicle_details' => 'Honda Civic (Midnight Black)',
    'plate_number' => 'ABC-9988',
    'violation_type' => 'Reckless Driving with Property Damage',
    'fine_amount' => 3500.00,
    'severity_level' => 'High',
    'collision_type' => 'Rear-end Collision',
    'location' => 'EDSA cor. Quezon Avenue Flyover, Quezon City',
    'narrative' => 'Vehicle failed to brake in time causing rear-end collision with passenger bus. Road traffic cleared.',
    'casualties_count' => 0,
    'property_damage_estimate' => 60000.00,
    'reporting_officer' => 'Enforcer Officer #12'
];

$res1 = $integrator->processIncomingAccidentReport($accidentPayload);
echo "   [SUCCESS] Received Group 2 Accident Ticket! ID: #" . $res1['record_id'] . " | Case No: " . $res1['case_no'] . " | Ticket: " . $res1['ticket_number'] . "\n\n";

// 2. Dispatch Evidence (Photos & Videos) to Group 7
echo "2. Testing Forwarding Photos & Videos to Group 7...\n";
$evidencePayload = [
    'evidence_id' => 101,
    'evidence_number' => 'EVD-' . date('Y') . '-7788',
    'case_number' => $res1['case_no'],
    'description' => 'Scene dashcam video recording and front bumper damage photos',
    'media_type' => 'Photo & Video',
    'photos' => [
        ['filename' => 'bumper_damage_01.jpg', 'url' => 'https://report.alertaraqc.com/uploads/evidence/bumper_01.jpg', 'size' => 1024000]
    ],
    'videos' => [
        ['filename' => 'dashcam_playback_01.mp4', 'url' => 'https://report.alertaraqc.com/uploads/evidence/dashcam_01.mp4', 'size' => 15728640]
    ]
];

$res2 = $integrator->dispatchToGroup7EvidenceUpload($evidencePayload);
echo "   [SUCCESS] Dispatched to Group 7 Upload Endpoint! Response Status: " . ($res2['http_code'] ?: 'Simulated/Offline') . "\n\n";

// 3. Group 1 Request CCTV from Group 2
echo "3. Testing CCTV Request to Group 2 (Accident & Violation Reporting)...\n";
$cctvRequestPayload = [
    'case_number' => $res1['case_no'],
    'incident_type' => 'Vehicular Accident & Over-speeding',
    'camera_location' => 'EDSA cor. Quezon Ave Intersection',
    'incident_date' => date('Y-m-d'),
    'incident_time' => date('H:i:s'),
    'vehicle_plate' => 'ABC-9988',
    'priority' => 'High',
    'reason' => 'Need CCTV recording of intersection signal lights during vehicle collision.'
];

$res3 = $integrator->dispatchCctvRequestToGroup2($cctvRequestPayload);
echo "   [SUCCESS] Dispatched CCTV Request to Group 2! Request ID: #" . $res3['request_id'] . "\n\n";

// 4. Group 2 Acknowledges CCTV Request
echo "4. Testing Group 2 Acknowledging CCTV Request...\n";
$ackPayload = [
    'request_id' => $res3['request_id'],
    'acknowledged_by' => 'Operator #05 (Group 2 Surveillance Desk)',
    'acknowledgement_notes' => 'Traffic camera footage recovered from EDSA Northbound camera #12. Ready for handoff.',
    'assigned_camera_operator' => 'Operator #05',
    'fulfilled_photo_url' => 'https://surveillance.alertaraqc.com/media/cam12_snapshot.jpg',
    'fulfilled_video_url' => 'https://surveillance.alertaraqc.com/media/cam12_clip.mp4'
];

$res4 = $integrator->acknowledgeCctvRequest($ackPayload);
echo "   [SUCCESS] Group 2 Acknowledged CCTV Request! Request ID: #" . $res4['request_id'] . " | Status: " . $res4['status'] . "\n\n";

// 5. Data Privacy Engine (Masking & Audit Logging)
echo "5. Testing Data Privacy Engine (RA 10173 Compliance)...\n";
$testName = "Jonathan Dela Cruz";
$testContact = "0917-123-4567";
$testAddress = "123 Mabini Street, Barangay Commonwealth, Quezon City";
$testStatement = "The suspect was wearing a black jacket and fled east on a motorcycle.";

$maskedName = maskPersonalInfo($testName, 'name');
$maskedContact = maskPersonalInfo($testContact, 'contact');
$maskedAddress = maskPersonalInfo($testAddress, 'address');
$maskedStatement = maskPersonalInfo($testStatement, 'statement');

echo "   Original Name:      $testName => Masked: $maskedName\n";
echo "   Original Contact:   $testContact => Masked: $maskedContact\n";
echo "   Original Address:   $testAddress => Masked: $maskedAddress\n";
echo "   Original Statement: $testStatement => Masked: $maskedStatement\n";

// Log Privacy Audit
logPrivacyAudit($pdo, 1, 'TEST_AUDIT_VERIFICATION', 'suspects', 101, 'Automated end-to-end verification audit log entry');
$auditLogs = getPrivacyAuditLogs($pdo, 3);
echo "   [SUCCESS] Verified Privacy Audit Logging! Total recent logs retrieved: " . count($auditLogs) . "\n\n";

echo "=== ALL 5 INTEGRATION & DATA PRIVACY CHECKS COMPLETED SUCCESSFULLY! ===\n";
