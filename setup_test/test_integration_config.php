<?php
/**
 * Test runner for dynamic integration settings and multi-module API dispatches
 */
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../config/integration_config.php';
require_once __DIR__ . '/../modules/OperationalModuleIntegrator.php';

echo "=== STARTING DYNAMIC INTEGRATION SETTINGS TEST ===\n\n";

$pdo = getDBConnection();
ensureIntegrationSettingsSchema($pdo);

// 1. Fetch current settings
echo "[1/4] Testing getAllIntegrationSettings()...\n";
$settings = getAllIntegrationSettings();
assert(!empty($settings['cctv_request_api_url']), "CCTV API URL setting should exist");
echo "PASS: CCTV Target URL is: " . $settings['cctv_request_api_url'] . "\n\n";

// 2. Save custom target endpoint URL
echo "[2/4] Testing setIntegrationSetting()...\n";
$testCustomUrl = 'https://custom-partner.alertaraqc.com/api/v2/cctv_receive.php';
$saveOk = setIntegrationSetting('cctv_request_api_url', $testCustomUrl);
assert($saveOk === true, "Saving setting should succeed");

$updatedUrl = getIntegrationSetting('cctv_request_api_url');
assert($updatedUrl === $testCustomUrl, "Updated URL must match custom target URL");
echo "PASS: Updated CCTV Target URL is: " . $updatedUrl . "\n\n";

// Revert back to original URL
setIntegrationSetting('cctv_request_api_url', 'https://surveillance.alertaraqc.com/api/cctv_requests_receive.php');

// 3. Test OperationalModuleIntegrator multi-module dispatching
echo "[3/4] Testing OperationalModuleIntegrator Multi-Module Dispatch...\n";
$integrator = new OperationalModuleIntegrator($pdo);

$sampleInput = [
    'source' => 'group_4_tip',
    'location' => 'Barangay Central, Quezon City',
    'description' => 'Commotion involving armed individuals near market.',
    'emergency_level' => 'High'
];

$processed = $integrator->processInbound($sampleInput, false);
$payloads = $processed['module_specific_payloads'];

echo "Dispatching to all 4 connected integration endpoints...\n";
$multiResults = $integrator->dispatchToAllConnectedModules($payloads);

assert(array_key_exists('cctv_partner', $multiResults), "CCTV dispatch result missing");
assert(array_key_exists('group7_inspection', $multiResults), "Group 7 dispatch result missing");
assert(array_key_exists('group5_crime_map', $multiResults), "Group 5 dispatch result missing");
assert(array_key_exists('group3_resource', $multiResults), "Group 3 dispatch result missing");

echo "PASS: CCTV Dispatch Endpoint: " . $multiResults['cctv_partner']['endpoint'] . "\n";
echo "PASS: Group 7 Dispatch Endpoint: " . $multiResults['group7_inspection']['endpoint'] . "\n";
echo "PASS: Group 5 Dispatch Endpoint: " . $multiResults['group5_crime_map']['endpoint'] . "\n";
echo "PASS: Group 3 Dispatch Endpoint: " . $multiResults['group3_resource']['endpoint'] . "\n\n";

// 4. Test fetchPublicCampaigns from campaign.alertaraqc.com
echo "[4/5] Testing fetchPublicCampaigns() from campaign.alertaraqc.com...\n";
$campaignRes = $integrator->fetchPublicCampaigns();
assert($campaignRes['success'] === true, "Live campaign fetch should return success HTTP 200");
echo "PASS: Live campaigns fetched: " . $campaignRes['campaign_count'] . " records\n\n";

// 5. Verify log records
echo "[5/5] Verifying `external_integration_log` entries...\n";
$stmt = $pdo->query("SELECT COUNT(*) FROM external_integration_log");
$logCount = $stmt->fetchColumn();
echo "PASS: Total integration log entries recorded: " . $logCount . "\n";

echo "\n=== ALL DYNAMIC INTEGRATION TESTS PASSED SUCCESSFULLY! ===\n";
