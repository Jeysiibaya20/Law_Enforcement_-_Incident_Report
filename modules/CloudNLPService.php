<?php
/**
 * Cloud NLP service for API-driven analysis.
 * Supports OpenAI-compatible endpoints (Chat Completions) and falls back to local NLP.
 */
class CloudNLPService
{
    private string $apiKey;
    private string $baseUrl;
    private string $model;

    public function __construct(?string $apiKey = null, ?string $baseUrl = null, ?string $model = null)
    {
        $this->apiKey = $apiKey ?? (getenv('OPENAI_API_KEY') ?: getenv('CLOUD_NLP_API_KEY') ?: '');
        $this->baseUrl = rtrim($baseUrl ?? (getenv('CLOUD_NLP_BASE_URL') ?: 'https://api.openai.com/v1'), '/');
        $this->model = $model ?? (getenv('CLOUD_NLP_MODEL') ?: 'gpt-4o-mini');
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    public function analyze(string $text, array $context = []): array
    {
        if (!$this->isConfigured()) {
            return $this->fallbackAnalysis($text, $context);
        }

        $payload = [
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are an incident-analysis assistant. Return concise JSON with keys: sentiment, threat_level, severity_score, summary, entities, emotions, confidence_score.'
                ],
                [
                    'role' => 'user',
                    'content' => $this->buildPrompt($text, $context)
                ]
            ],
            'temperature' => 0.2,
            'response_format' => ['type' => 'json_object']
        ];

        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey,
        ];

        $ch = curl_init($this->baseUrl . '/chat/completions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode >= 400) {
            error_log('Cloud NLP request failed: ' . $response);
            return $this->fallbackAnalysis($text, $context);
        }

        $decoded = json_decode($response, true);
        if (!isset($decoded['choices'][0]['message']['content'])) {
            return $this->fallbackAnalysis($text, $context);
        }

        $parsed = json_decode($decoded['choices'][0]['message']['content'], true);
        if (!is_array($parsed)) {
            return $this->fallbackAnalysis($text, $context);
        }

        return $this->normalizeResponse($parsed, $text, $context);
    }

    private function buildPrompt(string $text, array $context): string
    {
        $contextText = '';
        if (!empty($context['incident_type'])) {
            $contextText .= "Incident type: {$context['incident_type']}\n";
        }
        if (!empty($context['location'])) {
            $contextText .= "Location: {$context['location']}\n";
        }
        return "Analyze this incident report and return JSON only.\n\nText:\n{$text}\n\nContext:\n{$contextText}";
    }

    private function fallbackAnalysis(string $text, array $context = []): array
    {
        $local = NaturalLanguageProcessor::analyzeIncident($text, $context['incident_type'] ?? '');
        return [
            'sentiment' => $local['sentiment']['sentiment'] ?? 'Neutral',
            'threat_level' => $local['threat_level'] ?? 'Low',
            'severity_score' => $local['severity_score'] ?? 0,
            'summary' => NaturalLanguageProcessor::generateNLPSummary($local),
            'entities' => $local['entities'] ?? [],
            'emotions' => $local['emotions'] ?? [],
            'confidence_score' => $local['confidence_score'] ?? 0,
            'source' => 'local-fallback',
        ];
    }

    private function normalizeResponse(array $parsed, string $text, array $context): array
    {
        $local = NaturalLanguageProcessor::analyzeIncident($text, $context['incident_type'] ?? '');
        return [
            'sentiment' => $parsed['sentiment'] ?? ($local['sentiment']['sentiment'] ?? 'Neutral'),
            'threat_level' => $parsed['threat_level'] ?? ($local['threat_level'] ?? 'Low'),
            'severity_score' => (int) ($parsed['severity_score'] ?? ($local['severity_score'] ?? 0)),
            'summary' => $parsed['summary'] ?? NaturalLanguageProcessor::generateNLPSummary($local),
            'entities' => $parsed['entities'] ?? ($local['entities'] ?? []),
            'emotions' => $parsed['emotions'] ?? ($local['emotions'] ?? []),
            'confidence_score' => (int) ($parsed['confidence_score'] ?? ($local['confidence_score'] ?? 0)),
            'source' => 'cloud',
        ];
    }
}
