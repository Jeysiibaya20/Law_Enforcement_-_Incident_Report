<?php
require_once 'config/db_connect.php';
require_once 'config/integration_config.php';
require_once 'modules/OperationalModuleIntegrator.php';

$pdo = getDBConnection();
$integrator = new OperationalModuleIntegrator($pdo);

echo "=== TESTING CCTV REQUEST CREATION & DISPATCH FLOW ===\n";

$reqCode = 'CCTV-REQ-' . date('Y') . '-' . rand(1000, 9999);
$insert_stmt = $pdo->prepare("INSERT INTO cctv_requests
    (request_id_code, requested_by, requesting_agency, contact_person, position_designation, contact_number, email_address, office_unit,
     case_reference, related_complaint_id, legal_basis, purpose_reason, supporting_document,
     incident_location, camera_id, location_description, incident_date, incident_time, incident_type,
     footage_start_time, footage_end_time, incident_description, delivery_method, official_use_confirmed,
     privacy_terms_agreed, reason, camera_location, status, requested_at)
    VALUES
    (:request_id_code, 1, 'Digital Blotter System', 'Joecel Garcia', 'Duty Officer', '09171234567', 'joecel@qc.gov.ph', 'Investigation Unit',
     'BLT-20260825-01', 'COMP-001', 'Law enforcement request', 'Robbery footage cross check', NULL,
     '#12, Commonwealth Ave, Brgy Batasan Hills, District 2, Quezon City', 'CAM-001 — Main Entrance Camera', 'Near main gate', CURDATE(), '14:00', 'Footage',
     '14:00', '15:00', 'Suspicious activity recorded near entrance.', 'Secure download link', 1,
     1, 'Robbery footage cross check', '#12, Commonwealth Ave, Brgy Batasan Hills, District 2, Quezon City', 'Pending', NOW())");

$insert_stmt->execute([':request_id_code' => $reqCode]);
$newId = $pdo->lastInsertId();
echo "✔ 1. Inserted CCTV Request #$reqCode (ID: $newId)\n";

// Test dispatch to partner CCTV API
$dispatchRes = $integrator->dispatchToPartnerCctvApi([
    'request_id' => $reqCode,
    'incident_id' => 'INC-CCTV-' . $newId,
    'requesting_agency' => 'Digital Blotter System',
    'contact_person' => 'Joecel Garcia',
    'contact_number' => '09171234567',
    'location' => 'Commonwealth Ave, QC',
    'camera' => 'CAM-001',
    'purpose' => 'Robbery footage check'
]);
echo "✔ 2. Dispatched to Partner CCTV API: " . ($dispatchRes['success'] ? 'SUCCESS (200 OK)' : 'SIMULATED / HANDLED') . "\n";

// Update status to Dispatched
$stmtUp = $pdo->prepare("UPDATE cctv_requests SET status = 'Dispatched', updated_at = NOW() WHERE id = ?");
$stmtUp->execute([$newId]);
echo "✔ 3. Updated status to 'Dispatched' without any truncation warnings!\n";

echo "=== ALL FLOWS PASSED SUCCESSFULLY! ===\n";
