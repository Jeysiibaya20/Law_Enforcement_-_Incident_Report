<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required.']);
    exit;
}

require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/DescriptionTranslationService.php';

$text = trim((string)($_POST['text'] ?? ''));
if ($text === '') {
    echo json_encode([
        'language' => 'en',
        'translation' => '',
        'translated' => false,
        'provider' => 'none',
    ]);
    exit;
}

try {
    $result = (new DescriptionTranslationService($env ?? []))->translateToEnglish($text);
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    error_log('Description translation request failed: ' . $exception->getMessage());
    http_response_code(503);
    echo json_encode(['error' => 'Translation service is temporarily unavailable.']);
}
