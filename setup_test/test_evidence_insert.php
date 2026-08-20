<?php
require_once __DIR__ . '/../config/db_connect.php';
if (!isset($pdo) || !($pdo instanceof PDO)) {
    $pdo = getDBConnection();
}

$evidence_number = 'EVD-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);

$insert_stmt = $pdo->prepare("
    INSERT INTO evidence_records
    (evidence_number, evidence_type, case_id, case_number, item_description,
     location_found, source_department, received_from, source_reference, collection_date, `condition`, storage_location,
     security_level, witness_name, witness_description, notes, collected_by, collector_name, status)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Collected')
");

$res = $insert_stmt->execute([
    $evidence_number,
    'Physical',
    null,
    'CASE-TEST-001',
    'Test Item Description with Witness',
    'Main St. corner 2nd Ave',
    'CCTV Department',
    'Officer Santos',
    'REF-2026-001',
    date('Y-m-d H:i:s'),
    'Good',
    'Evidence Room A-1',
    'Medium',
    'Witness Juan Dela Cruz',
    'Witness observed the incident at 10:00 AM',
    'Test operational notes',
    1,
    'Admin User'
]);

if ($res) {
    $id = $pdo->lastInsertId();
    echo "[SUCCESS] Evidence record created with witness_name and witness_description! ID: #{$id}, Number: {$evidence_number}\n";
    // Clean up test record
    $pdo->exec("DELETE FROM evidence_records WHERE id = {$id}");
    echo "[SUCCESS] Cleaned up test record #{$id}.\n";
} else {
    echo "[FAILED] Could not insert evidence record.\n";
}
