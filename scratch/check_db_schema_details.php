<?php
require_once __DIR__ . '/../config/db_connect.php';
$pdo = getDBConnection();

$tables = [
    'received_inspection_documents',
    'cctv_footage_received',
    'received_accident_reports',
    'received_resolved_tips',
    'attachments',
    'evidence_attachments',
    'evidence_records',
    'cctv_requests'
];

foreach ($tables as $table) {
    echo "=== TABLE: $table ===\n";
    try {
        $cols = $pdo->query("DESCRIBE $table")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($cols as $col) {
            echo "  {$col['Field']} ({$col['Type']})\n";
        }
    } catch (Exception $e) {
        echo "  Error/Does not exist: " . $e->getMessage() . "\n";
    }
    echo "\n";
}
