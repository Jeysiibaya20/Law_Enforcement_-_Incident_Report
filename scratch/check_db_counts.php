<?php
require 'config/db_connect.php';
require 'modules/OperationalModuleIntegrator.php';

$pdo = getDBConnection();
$integrator = new OperationalModuleIntegrator($pdo);

echo "=== DATABASE COUNTS VERIFICATION ===\n";
echo "Received emergency calls count: " . $pdo->query("SELECT COUNT(*) FROM received_emergency_calls")->fetchColumn() . "\n";
echo "CCTV requests count: " . $pdo->query("SELECT COUNT(*) FROM cctv_requests")->fetchColumn() . "\n";
echo "CCTV footages received count: " . $pdo->query("SELECT COUNT(*) FROM cctv_footage_received")->fetchColumn() . "\n";
echo "Total incidents count: " . $pdo->query("SELECT COUNT(*) FROM incidents")->fetchColumn() . "\n";

echo "\nLatest received emergency call:\n";
$call = $pdo->query("SELECT id, call_id, caller_name, emergency_level, caller_location, case_no, created_at FROM received_emergency_calls ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
print_r($call);

echo "\nLatest CCTV request:\n";
$cctv = $pdo->query("SELECT id, request_id_code, requesting_agency, contact_person, legal_basis, incident_location, camera_id, status, requested_at FROM cctv_requests ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
print_r($cctv);
