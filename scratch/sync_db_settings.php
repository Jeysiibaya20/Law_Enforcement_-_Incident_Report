<?php
require_once 'config/db_connect.php';
require_once 'config/integration_config.php';

$pdo = getDBConnection();
setIntegrationSetting('cctv_request_api_url', 'https://policy.alertaraqc.com/api/cctv_requests_receive.php', $pdo);
setIntegrationSetting('group7_inspection_api_url', 'https://inspection.alertaraqc.com/api/documents/request', $pdo);
echo "Settings updated in DB!\n";
