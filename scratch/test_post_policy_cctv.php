<?php
$url = 'https://policy.alertaraqc.com/api/cctv_requests_receive.php';

$data = [
    'request_id' => 'CCTV-REQ-2026-TEST',
    'requesting_agency' => 'Digital Blotter System',
    'contact_person' => 'Joecel Garcia',
    'contact_number' => '09171234567',
    'email_address' => 'joecel@qc.gov.ph',
    'case_reference' => 'BLT-20260825-01',
    'legal_basis' => 'Law enforcement request',
    'location' => 'Commonwealth Ave, Quezon City',
    'camera' => 'CAM-001 — Main Entrance Camera',
    'incident_date' => date('Y-m-d'),
    'footage_start_time' => '14:00',
    'footage_end_time' => '15:00',
    'purpose' => 'Accident investigation footage cross-verification.',
    'incident_description' => 'Test footage request verification from Alertara Law Enforcement module.',
    'delivery_method' => 'Secure download link'
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'X-API-KEY: ALERTARA-EMERGENCY-2026'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

echo "HTTP Status Code: $httpCode\n";
echo "Response Output:\n" . $response . "\n";
