<?php
/**
 * Detects the language of a blotter description and translates it to English.
 * HanLP performs local language/script analysis; cloud translation is used for
 * the actual translation because HanLP is an NLP toolkit, not a translator.
 */
class DescriptionTranslationService
{
    private array $env;

    public function __construct(array $env = [])
    {
        $this->env = $env;
    }

    public function translateToEnglish(string $text): array
    {
        $text = trim($text);
        if ($text === '') {
            return ['language' => 'en', 'translation' => '', 'translated' => false, 'provider' => 'none'];
        }

        $language = $this->detectLanguage($text);
        if ($language === 'en') {
            // HanLP cannot identify every one of the world's languages. For
            // unknown non-ASCII text, let the online provider detect the source.
            if (preg_match('/[^\x00-\x7F]/', $text)) {
                $detected = $this->translateWithMyMemoryAutoDetect($text);
                if ($detected !== null) {
                    return [
                        'language' => $detected['language'],
                        'translation' => $detected['translation'],
                        'translated' => true,
                        'provider' => 'mymemory-autodetect',
                    ];
                }
            }
            return ['language' => 'en', 'translation' => $text, 'translated' => false, 'provider' => 'none'];
        }

        $translated = $this->translateWithGoogle($text, $language);
        if ($translated !== null) {
            return ['language' => $language, 'translation' => $translated, 'translated' => true, 'provider' => 'google'];
        }

        $translated = $this->translateWithAi($text, $language);
        if ($translated !== null) {
            return ['language' => $language, 'translation' => $translated, 'translated' => true, 'provider' => 'ai'];
        }

        $translated = $this->translateWithMyMemory($text, $language);
        if ($translated !== null) {
            return ['language' => $language, 'translation' => $translated, 'translated' => true, 'provider' => 'mymemory'];
        }

        return ['language' => $language, 'translation' => $text, 'translated' => false, 'provider' => 'unavailable'];
    }

    private function translateWithMyMemory(string $text, string $language): ?string
    {
        if (!function_exists('curl_init')) {
            return null;
        }

        $query = http_build_query([
            'q' => $text,
            'langpair' => $language . '|en',
        ]);
        $ch = curl_init('https://api.mymemory.translated.net/get?' . $query);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_USERAGENT => 'Law-Enforcement-Incident-Report/1.0',
        ]);
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = json_decode((string)$response, true);
        $translation = $decoded['responseData']['translatedText'] ?? null;
        if ($status >= 400 || !is_string($translation) || trim($translation) === '') {
            return null;
        }

        return html_entity_decode(trim($translation), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function translateWithMyMemoryAutoDetect(string $text): ?array
    {
        if (!function_exists('curl_init')) {
            return null;
        }

        $query = http_build_query(['q' => $text, 'langpair' => 'autodetect|en']);
        $ch = curl_init('https://api.mymemory.translated.net/get?' . $query);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_USERAGENT => 'Law-Enforcement-Incident-Report/1.0',
        ]);
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = json_decode((string)$response, true);
        $translation = $decoded['responseData']['translatedText'] ?? null;
        $detectedLanguage = $decoded['responseData']['detectedLanguage'] ?? null;
        if ($status >= 400 || !is_string($translation) || trim($translation) === '') {
            return null;
        }

        return [
            'language' => is_string($detectedLanguage) && $detectedLanguage !== '' ? $detectedLanguage : 'unknown',
            'translation' => html_entity_decode(trim($translation), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        ];
    }

    private function detectLanguage(string $text): string
    {
        $hanlpCommand = $this->env['HANLP_LANGUAGE_COMMAND'] ?? getenv('HANLP_LANGUAGE_COMMAND') ?: '';
        if ($hanlpCommand === '') {
            $script = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'hanlp_language_detector.py';
            $pythonCommand = $this->env['HANLP_PYTHON_COMMAND'] ?? getenv('HANLP_PYTHON_COMMAND') ?: 'python';
            $hanlpCommand = $pythonCommand . ' ' . escapeshellarg($script);
            $hanlpCommand .= PHP_OS_FAMILY === 'Windows' ? ' 2>NUL' : ' 2>/dev/null';
        }
        if ($hanlpCommand !== '' && function_exists('exec')) {
            $encoded = base64_encode($text);
            $output = [];
            @exec($hanlpCommand . ' ' . escapeshellarg($encoded), $output, $exitCode);
            $detected = strtolower(trim($output[0] ?? ''));
            if ($exitCode === 0 && preg_match('/^[a-z]{2,5}$/', $detected)) {
                return substr($detected, 0, 2);
            }
        }

        if (preg_match('/[\x{3040}-\x{30ff}]/u', $text)) return 'ja';
        if (preg_match('/[\x{ac00}-\x{d7af}]/u', $text)) return 'ko';
        if (preg_match('/[\x{4e00}-\x{9fff}]/u', $text)) return 'zh';
        if (preg_match('/[\x{0900}-\x{097f}]/u', $text)) return 'hi';
        if (preg_match('/[\x{0980}-\x{09ff}]/u', $text)) return 'bn';
        if (preg_match('/[\x{0b80}-\x{0bff}]/u', $text)) return 'ta';
        if (preg_match('/[\x{0c00}-\x{0c7f}]/u', $text)) return 'te';
        if (preg_match('/[\x{0e00}-\x{0e7f}]/u', $text)) return 'th';
        if (preg_match('/[\x{0600}-\x{06ff}]/u', $text)) return 'ar';
        if (preg_match('/[\x{0400}-\x{04ff}]/u', $text)) return 'ru';

        $lower = strtolower($text);
        $signals = [
            'tl' => ['ang', 'mga', 'ng', 'hindi', 'ako', 'ikaw', 'siya', 'salamat', 'mayroon', 'paano', 'ano', 'ito', 'iyon', 'po', 'ba', 'bakit', 'saan', 'kailan', 'ngayon', 'kahapon', 'insidente'],
            'es' => [' el ', ' la ', ' los ', ' las ', ' que ', ' una ', ' por '],
            'fr' => [' le ', ' la ', ' les ', ' des ', ' une ', ' avec ', ' pour '],
            'de' => [' der ', ' die ', ' das ', ' und ', ' nicht ', ' ist '],
        ];
        foreach ($signals as $language => $words) {
            foreach ($words as $word) {
                if (str_contains(' ' . $lower . ' ', $word)) return $language;
            }
        }

        return 'en';
    }

    private function translateWithGoogle(string $text, string $language): ?string
    {
        $key = $this->env['GOOGLE_TRANSLATE_API_KEY'] ?? getenv('GOOGLE_TRANSLATE_API_KEY') ?: '';
        if ($key === '' || !function_exists('curl_init')) return null;

        $ch = curl_init('https://translation.googleapis.com/language/translate/v2');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_POSTFIELDS => http_build_query([
                'q' => $text,
                'source' => $language,
                'target' => 'en',
                'format' => 'text',
                'key' => $key,
            ]),
        ]);
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = json_decode((string)$response, true);
        $translation = $decoded['data']['translations'][0]['translatedText'] ?? null;
        return $status < 400 && is_string($translation) && trim($translation) !== ''
            ? html_entity_decode($translation, ENT_QUOTES | ENT_HTML5, 'UTF-8')
            : null;
    }

    private function translateWithAi(string $text, string $language): ?string
    {
        $key = $this->env['OPENAI_API_KEY'] ?? getenv('OPENAI_API_KEY') ?: getenv('NLP_AI_API_KEY') ?: getenv('CLOUD_NLP_API_KEY') ?: '';
        $baseUrl = rtrim($this->env['CLOUD_NLP_BASE_URL'] ?? getenv('CLOUD_NLP_BASE_URL') ?: getenv('NLP_AI_BASE_URL') ?: 'https://api.openai.com/v1', '/');
        $model = $this->env['CLOUD_NLP_MODEL'] ?? getenv('CLOUD_NLP_MODEL') ?: getenv('NLP_AI_MODEL') ?: 'gpt-4o-mini';
        if ($key === '' || !function_exists('curl_init')) return null;

        $payload = [
            'model' => $model,
            'temperature' => 0,
            'messages' => [
                ['role' => 'system', 'content' => 'Translate incident reports to clear, faithful English. Return only the translation, with no explanation. Preserve names, dates, numbers, and legal meaning.'],
                ['role' => 'user', 'content' => "Detected language: {$language}\n\nText:\n{$text}"],
            ],
        ];
        $ch = curl_init($baseUrl . '/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $key],
        ]);
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = json_decode((string)$response, true);
        $translation = $decoded['choices'][0]['message']['content'] ?? null;
        return $status < 400 && is_string($translation) && trim($translation) !== '' ? trim($translation) : null;
    }
}
