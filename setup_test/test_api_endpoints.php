<?php
require_once __DIR__ . '/../config/db_connect.php';

echo "=== TESTING REST API ENDPOINTS DIRECTLY ===\n\n";

// Test 1: receive_accident_report.php POST simulation
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['CONTENT_TYPE'] = 'application/json';

$postData = [
    'report_id' => 'ACC-POST-TEST-001',
    'ticket_number' => 'TKT-POST-TEST-001',
    'incident_type' => 'Overspeeding and Lane Splitting',
    'violator_name' => 'Maria Clara Santos',
    'plate_number' => 'XYZ-7766',
    'fine_amount' => 1500.00,
    'location' => 'Commonwealth Avenue, Quezon City',
    'narrative' => 'Motorcycle overspeeding cited via speed radar gun.',
    'severity_level' => 'Medium'
];
$_POST = $postData;

ob_start();
require __DIR__ . '/../api/receive_accident_report.php';
$output = ob_get_clean();


echo "1. API Response from /api/receive_accident_report.php:\n";
echo "   " . $output . "\n\n";

$json = json_decode($output, true);
if (!empty($json['success'])) {
    echo "   [PASS] REST API Webhook successfully processed JSON payload!\n";
} else {
    echo "   [FAIL] Expected success=true from webhook\n";
}

echo "\n=== ALL API TESTS COMPLETE ===\n";
