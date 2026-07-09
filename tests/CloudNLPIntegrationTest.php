<?php
require __DIR__ . '/../modules/NaturalLanguageProcessor.php';
require __DIR__ . '/../modules/CloudNLPService.php';

$analysis = NaturalLanguageProcessor::analyzeText('A violent robbery happened near Main Street and the victim was injured.');

if (!is_array($analysis)) {
    fwrite(STDERR, "NLP analysis did not return an array.\n");
    exit(1);
}

$required = ['sentiment', 'severity_score', 'threat_level', 'summary'];
foreach ($required as $key) {
    if (!array_key_exists($key, $analysis)) {
        fwrite(STDERR, "Missing key: {$key}\n");
        exit(1);
    }
}

echo "Cloud NLP integration test passed\n";
