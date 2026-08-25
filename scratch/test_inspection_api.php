<?php
$url = 'http://localhost/Law_Enforcement_-_Incident_Report/api/receive_inspection_document.php';

$payload = [
    'request_id' => 'REQ-DOC-2026-081',
    'document_id' => 'CERT-INSP-9921',
    'case_no' => 'BLT-20260825-01',
    'document_type' => 'Barangay Commercial Safety Clearance',
    'business_or_location' => 'Nova Plaza Mall, Susano Road, Brgy. San Agustin, QC',
    'inspector_name' => 'Engr. Bautista / BFP QC Inspection Team',
    'inspection_status' => 'Passed / Certified Compliant',
    'findings' => 'All fire exits cleared, emergency lighting functional, CCTV perimeters operational.',
    'compliance_score' => '98/100',
    'certificate_url' => 'https://inspection.alertaraqc.com/storage/certs/CERT_9921.pdf',
    'inspection_date' => date('Y-m-d')
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'X-API-KEY: ALERTARA-EMERGENCY-2026'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response:\n$response\n";
