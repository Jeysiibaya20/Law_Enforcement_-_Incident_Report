<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "=== TEST 1: SIMULATING ACCESS TO modules/Incident_report.php (ADMIN SESSION) ===\n";

session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'Admin';
$_SESSION['fullname'] = 'System Administrator';

ob_start();
try {
    include 'modules/Incident_report.php';
    $output = ob_get_clean();
    echo "Output length: " . strlen($output) . " bytes\n";
    if (strlen($output) < 100) {
        echo "WARNING: Output is suspiciously short! Content:\n$output\n";
    } else {
        echo "✔ Rendered successfully! (First 200 chars: " . substr($output, 0, 200) . "...)\n";
    }
} catch (Throwable $e) {
    ob_end_clean();
    echo "❌ FATAL THROWABLE: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo $e->getTraceAsString() . "\n";
}
