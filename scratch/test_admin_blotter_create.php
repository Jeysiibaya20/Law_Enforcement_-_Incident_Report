<?php
require_once 'config/db_connect.php';

$pdo = getDBConnection();

// Ensure columns
$columns = [
    'complainant_contact' => 'VARCHAR(50) NULL AFTER complainant_name',
    'complainant_email' => 'VARCHAR(150) NULL AFTER complainant_contact',
    'complainant_address' => 'VARCHAR(255) NULL AFTER complainant_email',
    'complainant_signature' => 'LONGTEXT NULL',
    'description_english' => 'TEXT NULL AFTER description',
    'description_language' => 'VARCHAR(10) NULL AFTER description_english',
    'description_translation_provider' => 'VARCHAR(30) NULL AFTER description_language',
];

foreach ($columns as $column => $definition) {
    try {
        $check = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'blotters' AND COLUMN_NAME = ?");
        $check->execute([$column]);
        if ((int)$check->fetchColumn() === 0) {
            $pdo->exec("ALTER TABLE blotters ADD COLUMN {$column} {$definition}");
        }
    } catch (Exception $e) {}
}

echo "=== TESTING ADMIN BLOTTER CREATION INSERTION ===\n";

$blotter_no = 'BLT-ADMIN-TEST-' . time();
$complainant = 'Admin Test Complainant';
$complainant_contact = '09191234567';
$complainant_email = 'admin_test@qc.gov.ph';
$complainant_address = 'Barangay Central, QC';
$respondent = 'Test Respondent';
$respondent_contact = '09289876543';
$respondent_email = 'respondent@test.com';
$respondent_address = 'Susano Road, QC';
$incident_type = 'Physical Violence / Assault';
$incident_date = date('Y-m-d');
$incident_time = date('H:i');
$location = 'Susano Road, Novaliches, QC';
$description = 'Admin recorded physical altercation incident.';
$priority = 'High';
$status = 'Pending';
$signature = '';
$created_by = 1;

$sql = "INSERT INTO blotters (blotter_no, complainant_name, complainant_contact, complainant_email, complainant_address, respondent_name, respondent_contact, respondent_email, respondent_address, incident_type, incident_date, incident_time, location, description, priority, status, complainant_signature, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    $blotter_no, $complainant, $complainant_contact, $complainant_email, $complainant_address,
    $respondent, $respondent_contact, $respondent_email, $respondent_address,
    $incident_type, $incident_date, $incident_time, $location, $description,
    $priority, $status, $signature, $created_by
]);

$id = $pdo->lastInsertId();
echo "✔ Successfully inserted blotter record into database!\n";
echo "  Record ID: " . $id . "\n";
echo "  Blotter No: " . $blotter_no . "\n";
echo "  Status: " . $status . "\n";
echo "  Priority: " . $priority . "\n";

$check = $pdo->query("SELECT id, blotter_no, complainant_name, incident_type, status FROM blotters WHERE id = {$id}")->fetch(PDO::FETCH_ASSOC);
print_r($check);
