<?php
require_once 'config/db_connect.php';
$pdo = getDBConnection();

$tables = [
    'received_campaigns',
    'received_emergency_calls',
    'cctv_requests',
    'cctv_footage_received',
    'received_accident_reports',
    'received_resolved_tips',
    'blotters',
    'incidents',
    'module_integration_logs'
];

echo "=== DATABASE TABLE RECORD COUNTS ===\n";
foreach ($tables as $t) {
    try {
        $cnt = (int)$pdo->query("SELECT COUNT(*) FROM {$t}")->fetchColumn();
        echo "Table `{$t}`: {$cnt} record(s)\n";
    } catch (Exception $e) {
        echo "Table `{$t}`: Table error or missing ({$e->getMessage()})\n";
    }
}
