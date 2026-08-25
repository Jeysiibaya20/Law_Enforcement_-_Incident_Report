<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "=== SIMULATING POST REQUESTS TO Incident_report.php ===\n";

chdir(__DIR__ . '/../modules');

// Test 1: POST submit_incident
echo "\n--- TEST 1: POST submit_incident ---\n";
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [
    'reporter_name' => 'John Doe',
    'reporter_type' => 'Citizen',
    'reporter_email' => 'john@example.com',
    'reporter_phone' => '09123456789',
    'incident_date' => date('Y-m-d'),
    'incident_time' => '10:00',
    'location' => '#123 Commonwealth Ave, Brgy Batasan Hills, District 2, Quezon City',
    'description' => 'A robbery occurred near the grocery store with high urgency.'
];
$_SESSION = [
    'user_id' => 1,
    'role' => 'Admin',
    'fullname' => 'Admin Test'
];

ob_start();
try {
    include 'Incident_report.php';
    $out = ob_get_clean();
    echo "✔ POST submit_incident completed without 500 fatal errors! (Session message: " . json_encode($_SESSION['message'] ?? null) . ")\n";
} catch (Throwable $e) {
    ob_end_clean();
    echo "❌ FATAL on submit_incident: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo $e->getTraceAsString() . "\n";
}

// Test 2: POST forward_incident
echo "\n--- TEST 2: POST forward_incident ---\n";
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [
    'forward_incident' => '1',
    'incident_id' => '122',
    'forward_to_group' => 'GRP6',
    'forward_notes' => 'Test forwarding'
];
$_SESSION = [
    'user_id' => 1,
    'role' => 'Admin',
    'fullname' => 'Admin Test'
];

ob_start();
try {
    include 'Incident_report.php';
    $out = ob_get_clean();
    echo "✔ POST forward_incident completed without 500 fatal errors! (Session message: " . json_encode($_SESSION['message'] ?? null) . ")\n";
} catch (Throwable $e) {
    ob_end_clean();
    echo "❌ FATAL on forward_incident: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo $e->getTraceAsString() . "\n";
}
