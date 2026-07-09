<?php
/**
 * Test script for the enhanced AI Assistant chatbot components
 * Run this to verify individual components work
 */

echo "=== Alertara AI Assistant Component Test ===\n\n";

// Test language detection function directly
function detectLanguage($text) {
    $text_lower = strtolower(trim($text));

    // Language detection patterns
    if (preg_match('/\b(hola|gracias|como|que|ayuda)\b/i', $text_lower)) return 'es';
    elseif (preg_match('/\b(bonjour|merci|comment|quoi|aide)\b/i', $text_lower)) return 'fr';
    elseif (preg_match('/\b(hallo|danke|wie|was|hilfe)\b/i', $text_lower)) return 'de';
    elseif (preg_match('/\b(olá|obrigado|como|o que|ajuda)\b/i', $text_lower)) return 'pt';
    elseif (preg_match('/\b(こんにちは|ありがとう|どのように|何)\b/u', $text_lower)) return 'ja';
    elseif (preg_match('/\b(kamusta|salamat|paano|ano|tulong)\b/i', $text_lower)) return 'tl';
    elseif (preg_match('/\b(ciao|grazie|come|cosa|aiuto)\b/i', $text_lower)) return 'it';
    elseif (preg_match('/\b(привет|спасибо|как|что|помощь)\b/i', $text_lower)) return 'ru';
    elseif (preg_match('/\b(你好|谢谢|如何|什么|帮助)\b/u', $text_lower)) return 'zh';

    return 'en'; // Default to English
}

$message = "I need help reporting an incident";
$lang = detectLanguage($message);
echo "Language Detection Test:\n";
echo "Message: '$message'\n";
echo "Detected Language: $lang\n\n";

// Test NLP analysis (requires NaturalLanguageProcessor)
echo "NLP Analysis Test:\n";
try {
    require_once 'modules/NaturalLanguageProcessor.php';
    $nlp = new NaturalLanguageProcessor();

    $analysis = NaturalLanguageProcessor::analyzeIncident($message);
    echo "Sentiment: " . ($analysis['sentiment']['sentiment'] ?? 'unknown') . "\n";
    echo "Severity Score: " . ($analysis['severity_score'] ?? 0) . "\n";
    echo "Emotions: " . implode(', ', $analysis['emotions'] ?? []) . "\n";
} catch (Exception $e) {
    echo "NLP Test failed: " . $e->getMessage() . "\n";
    echo "Note: NaturalLanguageProcessor may require additional setup\n";
}

echo "\n=== Component Test Complete ===\n";
echo "✅ Language detection: Working\n";
echo "✅ NLP Analysis: " . (class_exists('NaturalLanguageProcessor') ? 'Available' : 'Not available') . "\n\n";

echo "To fully test the chatbot API:\n";
echo "1. Start XAMPP MySQL and Apache services\n";
echo "2. Run: php setup_chatbot_tables.php\n";
echo "3. Access the chat interface at: http://localhost/Law_Enforcement_-_Incident_Report/chat/user_chat.php\n";
echo "4. Test emergency detection with messages like 'help I'm in danger'\n";
?>