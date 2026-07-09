<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../modules/NaturalLanguageProcessor.php';

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$text = trim((string) ($input['text'] ?? $input['message'] ?? ''));
$context = [
    'incident_type' => $input['incident_type'] ?? '',
    'location' => $input['location'] ?? '',
];

if ($text === '') {
    http_response_code(400);
    echo json_encode(['error' => 'A text payload is required.']);
    exit;
}

$result = NaturalLanguageProcessor::analyzeText($text, $context);

echo json_encode([
    'ok' => true,
    'analysis' => $result,
    'configured' => class_exists('CloudNLPService') && (new CloudNLPService())->isConfigured(),
]);
