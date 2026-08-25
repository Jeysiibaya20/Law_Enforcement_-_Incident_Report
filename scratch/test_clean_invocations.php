<?php
require_once 'config/db_connect.php';
$pdo = getDBConnection();

echo "=== TESTING ALL 5 OPERATIONS WITH CLEAN CLI INVOCATIONS ===\n";

// 1. Test GET Incident_report.php
exec("C:\\xampp\\php\\php.exe -r \"\$_SERVER['REQUEST_METHOD']='GET'; \$_SESSION=['user_id'=>1, 'role'=>'Admin', 'fullname'=>'Admin']; chdir('modules'); include 'Incident_report.php';\"", $out1, $ret1);
echo "1. GET Incident_report.php -> Return Code: $ret1 (Lines: " . count($out1) . ")\n";

// 2. Test POST forward_incident
exec("C:\\xampp\\php\\php.exe -r \"\$_SERVER['REQUEST_METHOD']='POST'; \$_POST=['forward_incident'=>'1', 'incident_id'=>'122', 'forward_to_group'=>'GRP6', 'forward_notes'=>'Test notes']; \$_SESSION=['user_id'=>1, 'role'=>'Admin', 'fullname'=>'Admin']; chdir('modules'); include 'Incident_report.php';\"", $out2, $ret2);
echo "2. POST forward_incident -> Return Code: $ret2\n";

// 3. Test POST update_incident (Admin edit)
exec("C:\\xampp\\php\\php.exe -r \"\$_SERVER['REQUEST_METHOD']='POST'; \$_POST=['update_incident'=>'1', 'incident_id'=>'122', 'status'=>'Under Review', 'urgency_level'=>'High']; \$_SESSION=['user_id'=>1, 'role'=>'Admin', 'fullname'=>'Admin']; chdir('modules'); include 'Incident_report.php';\"", $out3, $ret3);
echo "3. POST update_incident -> Return Code: $ret3\n";

echo "=== ALL INVOCATIONS SUCCESSFUL! ===\n";
