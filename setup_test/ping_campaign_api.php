<?php
$url = 'https://campaign.alertaraqc.com/api/v1/campaigns/public';
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$res = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

echo "Target URL: {$url}\n";
echo "HTTP Status Code: {$code}\n";
echo "Error: " . ($err ?: 'None') . "\n";
echo "Response Body:\n" . substr($res, 0, 500) . "\n";
