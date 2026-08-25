<?php
$url = 'https://campaign.alertaraqc.com/api/v1/campaigns/public';

$headers_list = [
    ['User-Agent: AlertaraQC/1.0', 'Accept: application/json'],
    ['User-Agent: AlertaraQC/1.0', 'Accept: application/json', 'X-API-KEY: ALERTARA-EMERGENCY-2026'],
    ['User-Agent: AlertaraQC/1.0', 'Accept: application/json', 'Authorization: Bearer ALERTARA-EMERGENCY-2026'],
    ['User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)', 'Accept: application/json']
];

foreach ($headers_list as $idx => $hdrs) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $hdrs);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    echo "Test #$idx (HTTP $code): " . substr($resp, 0, 150) . "\n";
}
