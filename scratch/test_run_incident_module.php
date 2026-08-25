<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

chdir(__DIR__ . '/../modules');
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['PHP_SELF'] = '/modules/Incident_report.php';
$_SERVER['SCRIPT_NAME'] = '/modules/Incident_report.php';

session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'Admin';
$_SESSION['fullname'] = 'System Administrator';

ob_start();
try {
    include 'Incident_report.php';
    $output = ob_get_clean();
    echo "Length: " . strlen($output) . " bytes\n";
    echo "First 150 chars:\n" . substr($output, 0, 150) . "\n";
    echo "Last 150 chars:\n" . substr($output, -150) . "\n";
} catch (Throwable $e) {
    ob_end_clean();
    echo "Fatal Error: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo $e->getTraceAsString() . "\n";
}
