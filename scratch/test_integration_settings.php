<?php
require_once __DIR__ . '/../config/integration_config.php';

echo "=== CURRENT SAVED INTEGRATION SETTINGS ===\n";
$settings = getAllIntegrationSettings();
print_r($settings);

echo "\n=== TESTING SAVING A TARGET URL ===\n";
setIntegrationSetting('cctv_request_api_url', 'https://policy.alertaraqc.com/api/cctv_requests_receive.php');
setIntegrationSetting('external_api_secret', 'ALERTARA_SECRET_KEY_2026');

echo "New CCTV Request URL: " . getIntegrationSetting('cctv_request_api_url') . "\n";
echo "New Secret Key: " . getIntegrationSetting('external_api_secret') . "\n";
echo "SUCCESS: Saved to database!\n";
