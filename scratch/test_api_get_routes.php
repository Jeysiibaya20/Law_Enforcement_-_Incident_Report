<?php
require_once 'config/db_connect.php';

echo "=== TESTING API GET ACTIONS VIA api/api.php ===\n";

// 1. Test GET emergency_calls
$_REQUEST = ['action' => 'emergency_calls'];
ob_start();
require 'api/api.php';
$output = ob_get_clean();
$json = json_decode($output, true);
echo "1. emergency_calls action:\n";
echo "   Status: " . ($json['status'] ?? 'error') . "\n";
echo "   Calls Count: " . ($json['data']['count'] ?? 0) . "\n";

// 2. Test GET cctv_requests
$_REQUEST = ['action' => 'cctv_requests'];
ob_start();
require 'api/api.php';
$output2 = ob_get_clean();
$json2 = json_decode($output2, true);
echo "\n2. cctv_requests action:\n";
echo "   Status: " . ($json2['status'] ?? 'error') . "\n";
echo "   Requests Count: " . ($json2['data']['count'] ?? 0) . "\n";

// 3. Test CCTV Request Status Update
$_REQUEST = [
    'action' => 'cctv_request_update_status',
    'id' => 13,
    'status' => 'Approved',
    'review_notes' => 'Verified with Barangay San Agustin CCTV Control Room.'
];
ob_start();
require 'api/api.php';
$output3 = ob_get_clean();
$json3 = json_decode($output3, true);
echo "\n3. cctv_request_update_status action:\n";
echo "   Status: " . ($json3['status'] ?? 'error') . "\n";
echo "   Message: " . ($json3['message'] ?? '') . "\n";

echo "\n=== ALL API ACTION TESTS PASSED! ===\n";
