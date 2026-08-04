<?php
require_once __DIR__ . '/config/db_connect.php';

$keys = [
    'OPENAI_API_KEY' => getenv('OPENAI_API_KEY') ?: '',
    'NLP_AI_API_KEY' => getenv('NLP_AI_API_KEY') ?: '',
    'CLOUD_NLP_API_KEY' => getenv('CLOUD_NLP_API_KEY') ?: '',
];

foreach ($keys as $name => $value) {
    echo $name . ': ' . ($value !== '' ? 'SET' : 'EMPTY') . PHP_EOL;
}

$key = $keys['OPENAI_API_KEY'] ?: $keys['NLP_AI_API_KEY'] ?: $keys['CLOUD_NLP_API_KEY'];
if ($key === '') {
    echo "No AI key configured. Please add a real key to .env." . PHP_EOL;
    exit(1);
}

$ch = curl_init('https://api.openai.com/v1/models');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 20);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $key,
    'Content-Type: application/json',
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo 'HTTP_CODE=' . $httpCode . PHP_EOL;
if ($httpCode === 200) {
    echo 'API connectivity: OK' . PHP_EOL;
} else {
    echo 'API connectivity: FAILED' . PHP_EOL;
    echo $response . PHP_EOL;
}
