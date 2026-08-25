<?php
require_once 'config/db_connect.php';
require_once 'config/integration_config.php';
require_once 'modules/OperationalModuleIntegrator.php';

$pdo = getDBConnection();
$integrator = new OperationalModuleIntegrator($pdo);

$res = $integrator->fetchPublicCampaigns();
print_r($res);
