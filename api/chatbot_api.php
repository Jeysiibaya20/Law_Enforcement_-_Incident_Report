<?php
// AI Chatbot API with Multi-Language Support and Advanced NLP for USER accounts
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../modules/NaturalLanguageProcessor.php';

// Enhanced Language Detector with NLP capabilities
class LanguageDetector {
    private static $supported_languages = [
        'en' => 'English',
        'es' => 'Spanish',
        'fr' => 'French',
        'de' => 'German',
        'it' => 'Italian',
        'pt' => 'Portuguese',
        'ru' => 'Russian',
        'ja' => 'Japanese',
        'zh' => 'Chinese',
        'ko' => 'Korean',
        'ar' => 'Arabic',
        'hi' => 'Hindi',
        'tl' => 'Filipino/Tagalog'
    ];

    private static $translations = [
        'es' => [
            'welcome' => '¡Bienvenido a Alertara! 👋',
            'incident' => 'Crear un Reporte de Incidente',
            'cases' => 'Gestión de Casos',
            'account' => 'Problemas de Cuenta',
            'blotter' => 'Registro de Incidentes',
            'notifications' => 'Notificaciones',
            'help' => 'Ayuda - ¿En qué puedo ayudarte?'
        ],
        'fr' => [
            'welcome' => 'Bienvenue à Alertara! 👋',
            'incident' => 'Créer un Rapport d\'Incident',
            'cases' => 'Gestion des Dossiers',
            'account' => 'Problèmes de Compte',
            'blotter' => 'Journal des Incidents',
            'notifications' => 'Notifications',
            'help' => 'Aide - Comment puis-je vous aider?'
        ],
        'de' => [
            'welcome' => 'Willkommen bei Alertara! 👋',
            'incident' => 'Vorfallbericht erstellen',
            'cases' => 'Fallverwaltung',
            'account' => 'Kontoprobleme',
            'blotter' => 'Vorfallprotokoll',
            'notifications' => 'Benachrichtigungen',
            'help' => 'Hilfe - Wie kann ich dir helfen?'
        ],
        'pt' => [
            'welcome' => 'Bem-vindo ao Alertara! 👋',
            'incident' => 'Criar um Relatório de Incidente',
            'cases' => 'Gerenciamento de Casos',
            'account' => 'Problemas de Conta',
            'blotter' => 'Registro de Incidentes',
            'notifications' => 'Notificações',
            'help' => 'Ajuda - Como posso ajudá-lo?'
        ],
        'ja' => [
            'welcome' => 'Alertaraへようこそ！👋',
            'incident' => 'インシデント報告書を作成',
            'cases' => 'ケース管理',
            'account' => 'アカウントの問題',
            'blotter' => 'インシデントログ',
            'notifications' => '通知',
            'help' => 'ヘルプ - どのようにお手伝いできますか？'
        ],
        'tl' => [
            'welcome' => 'Maligayang pagdating sa Alertara! 👋',
            'incident' => 'Lumikha ng Ulat ng Insidente',
            'cases' => 'Pamamahala ng Mga Kaso',
            'account' => 'Mga Isyung Pang-Account',
            'blotter' => 'Pahiwatig ng Insidente',
            'notifications' => 'Mga Notipikasyon',
            'help' => 'Tulong - Paano kita matutulungan?'
        ]
    ];

    public static function detectLanguage($text = '') {
        // Try to detect from Accept-Language header
        $lang = 'en'; // default
        
        if (!empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $accept_language = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
            $primary_lang = explode('-', $accept_language[0])[0];
            if (isset(self::$supported_languages[$primary_lang])) {
                $lang = $primary_lang;
            }
        }
        
        // Simple text detection fallback (check for common words)
        if (!empty($text)) {
            $text_lower = strtolower($text);
            if (preg_match('/\b(hola|como|que|gracias|ayuda)\b/i', $text_lower)) $lang = 'es';
            elseif (preg_match('/\b(bonjour|merci|comment|quoi|aide)\b/i', $text_lower)) $lang = 'fr';
            elseif (preg_match('/\b(hallo|danke|wie|was|hilfe)\b/i', $text_lower)) $lang = 'de';
            elseif (preg_match('/\b(olá|obrigado|como|o que|ajuda)\b/i', $text_lower)) $lang = 'pt';
            elseif (preg_match('/\b(こんにちは|ありがとう|どのように|何)\b/u', $text_lower)) $lang = 'ja';
            elseif (preg_match('/\b(kamusta|salamat|paano|ano|tulong)\b/i', $text_lower)) $lang = 'tl';
        }
        
        return $lang;
    }

    public static function translateText($text, $from_lang = 'en', $to_lang = 'en') {
        // If same language or no translation available, return original
        if ($from_lang === $to_lang || !isset(self::$translations[$to_lang])) {
            return $text;
        }
        
        // Try Google Translate API if available (optional)
        $google_key = getenv('GOOGLE_TRANSLATE_API_KEY') ?: null;
        if ($google_key && extension_loaded('curl')) {
            $payload = [
                'q' => $text,
                'source_language' => $from_lang,
                'target_language' => $to_lang,
                'key' => $google_key
            ];
            
            $ch = curl_init('https://translation.googleapis.com/language/translate/v2');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
            
            $res = curl_exec($ch);
            curl_close($ch);
            
            if ($res) {
                $decoded = json_decode($res, true);
                if (isset($decoded['data']['translations'][0]['translatedText'])) {
                    return htmlspecialchars_decode($decoded['data']['translations'][0]['translatedText']);
                }
            }
        }
        
        return $text;
    }

    public static function getSupportedLanguages() {
        return self::$supported_languages;
    }
}

// Advanced NLP-powered helper functions
function getUserContext($user_id) {
    global $pdo;
    
    try {
        // Get user's recent activity
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as incident_count FROM incidents WHERE created_by = ?
        ");
        $stmt->execute([$user_id]);
        $incident_count = $stmt->fetchColumn();
        
        // Get recent incidents
        $stmt = $pdo->prepare("
            SELECT case_no, status, created_at 
            FROM incidents 
            WHERE created_by = ? 
            ORDER BY created_at DESC LIMIT 3
        ");
        $stmt->execute([$user_id]);
        $recent_incidents = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $recent_activity = [];
        foreach ($recent_incidents as $incident) {
            $recent_activity[] = "Incident {$incident['case_no']} ({$incident['status']}) on " . date('M d', strtotime($incident['created_at']));
        }
        
        return [
            'incident_count' => $incident_count,
            'recent_activity' => $recent_activity,
            'account_status' => 'Active'
        ];
    } catch (Exception $e) {
        return [
            'incident_count' => 0,
            'recent_activity' => [],
            'account_status' => 'Unknown'
        ];
    }
}

function analyzeUserMessage($message) {
    $nlp = new NaturalLanguageProcessor();
    $analysis = $nlp->analyzeText($message);
    
    // Determine intent based on keywords and NLP results
    $lower_message = strtolower($message);
    $intent = 'general';
    
    if (preg_match('/\b(help|assist|support|guide|how|what|when|where)\b/i', $lower_message)) {
        $intent = 'help_request';
    } elseif (preg_match('/\b(report|incident|create|submit|file)\b/i', $lower_message)) {
        $intent = 'report_incident';
    } elseif (preg_match('/\b(status|check|update|progress)\b/i', $lower_message)) {
        $intent = 'check_status';
    } elseif (preg_match('/\b(account|login|password|email|profile)\b/i', $lower_message)) {
        $intent = 'account_issue';
    } elseif (preg_match('/\b(emergency|urgent|danger|threat|crime)\b/i', $lower_message)) {
        $intent = 'emergency';
    }
    
    // Extract key topics
    $topics = [];
    if (preg_match('/\b(incident|report|case)\b/i', $lower_message)) $topics[] = 'incidents';
    if (preg_match('/\b(case|assignment|officer)\b/i', $lower_message)) $topics[] = 'cases';
    if (preg_match('/\b(blotter|log|record)\b/i', $lower_message)) $topics[] = 'blotters';
    if (preg_match('/\b(account|login|password)\b/i', $lower_message)) $topics[] = 'accounts';
    
    return [
        'sentiment' => $analysis['sentiment'] ?? 'neutral',
        'intent' => $intent,
        'urgency' => ($analysis['severity_score'] ?? 0) > 50 ? 'high' : 'normal',
        'topics' => $topics,
        'severity_score' => $analysis['severity_score'] ?? 0,
        'emotions' => $analysis['emotions'] ?? []
    ];
}

function storeConversation($user_id, $user_message, $bot_reply, $source = 'knowledge_base') {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO chatbot_conversations 
            (user_id, user_message, bot_reply, source, created_at) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$user_id, $user_message, $bot_reply, $source]);
    } catch (Exception $e) {
        // Log error but don't fail the response
        error_log("Failed to store conversation: " . $e->getMessage());
    }
}

// Ensure request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Invalid method']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$message = trim($input['message'] ?? '');
$user_language = $input['language'] ?? LanguageDetector::detectLanguage($message);

if (empty($message)) {
    http_response_code(400);
    echo json_encode(['error' => 'Empty message', 'language' => $user_language]);
    exit;
}

// Special status check for authentication
if ($message === 'status_check') {
    echo json_encode(['reply' => 'Authentication successful', 'status' => 'ok']);
    exit;
}

// Authentication: only logged-in normal users allowed
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'error' => 'Not authenticated',
        'details' => 'Please log in to use the AI Assistant',
        'redirect' => '../auth/login.php'
    ]);
    exit;
}

$role = isset($_SESSION['role']) ? strtolower($_SESSION['role']) : 'user';
if ($role === 'admin' || $role === 'officer') {
    http_response_code(403);
    echo json_encode([
        'error' => 'Access denied',
        'details' => 'This chat is for regular users only. Admins and officers should use the admin panel.'
    ]);
    exit;
}

// Try OpenAI with enhanced context and NLP integration
$openai_key = getenv('OPENAI_API_KEY') ?: null;
$user_context = getUserContext($_SESSION['user_id']);
$nlp_analysis = analyzeUserMessage($message);

if ($openai_key) {
    // Enhanced system prompt with NLP insights
    $system_prompt = "You are Alertara's advanced AI assistant with powerful NLP capabilities. You help users with incident reporting, case management, and system navigation.

USER CONTEXT:
- Recent activity: " . implode(', ', $user_context['recent_activity']) . "
- Total incidents: " . $user_context['incident_count'] . "
- Account status: " . $user_context['account_status'] . "

MESSAGE ANALYSIS:
- Sentiment: " . $nlp_analysis['sentiment'] . "
- Intent: " . $nlp_analysis['intent'] . "
- Urgency: " . $nlp_analysis['urgency'] . "
- Key topics: " . implode(', ', $nlp_analysis['topics']) . "

Guidelines:
1. Be empathetic and supportive, especially for urgent or negative sentiment messages
2. Provide actionable, step-by-step guidance
3. Use the user's context to give personalized responses
4. For urgent situations, prioritize immediate help and escalation
5. Always maintain confidentiality and professional boundaries
6. If the user seems distressed, suggest appropriate support resources

Answer helpfully and concisely. If this is an emergency, direct them to call emergency services.";

    $payload = [
        'model' => 'gpt-4', // Upgrade to GPT-4 for better NLP
        'messages' => [
            ['role' => 'system', 'content' => $system_prompt],
            ['role' => 'user', 'content' => $message]
        ],
        'max_tokens' => 800,
        'temperature' => 0.7,
        'presence_penalty' => 0.1,
        'frequency_penalty' => 0.1
    ];

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $openai_key
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    
    $res = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($res !== false && $http_code === 200) {
        $decoded = json_decode($res, true);
        if (isset($decoded['choices'][0]['message']['content'])) {
            $reply = trim($decoded['choices'][0]['message']['content']);
            
            // Store conversation for context
            storeConversation($_SESSION['user_id'], $message, $reply, 'openai');
            
            // Translate if needed
            if ($user_language !== 'en') {
                $reply = LanguageDetector::translateText($reply, 'en', $user_language);
            }
            
            echo json_encode([
                'reply' => $reply, 
                'source' => 'openai-gpt4', 
                'language' => $user_language,
                'nlp_insights' => $nlp_analysis,
                'context_used' => true
            ]);
            exit;
        }
    }
    
    // OpenAI failed - fall through to enhanced knowledge base
    error_log("OpenAI API error (HTTP $http_code): " . ($err ?: $res));
}

// Enhanced Fallback knowledge base responder with NLP integration
function getKnowledgeBaseReply($message, $user_context, $nlp_analysis) {
    $lower = strtolower(trim($message));
    $sentiment = $nlp_analysis['sentiment'] ?? 'neutral';
    $intent = $nlp_analysis['intent'] ?? 'general';
    $urgency = $nlp_analysis['urgency'] ?? 'normal';
    
    // Emergency detection - highest priority
    if ($urgency === 'high' || preg_match('/\b(emergency|help|danger|threat|attack|harm|kill|dead|bleeding|unconscious)\b/i', $lower)) {
        return "**🚨 EMERGENCY DETECTION**\n\nIf this is an immediate emergency:\n\n**CALL EMERGENCY SERVICES NOW:**\n- Philippines: Dial 911 or 117\n- Local Police: Contact nearest station\n\n**While waiting for help:**\n1. Stay in a safe location\n2. Provide your exact location\n3. Give clear details about the situation\n\n**Report through Alertara:**\nAfter ensuring safety, submit a detailed incident report with all available information.\n\n*Alertara will automatically notify relevant authorities.*";
    }
    
    // Context-aware responses based on user history
    if ($user_context['incident_count'] > 0) {
        $recent_activity = implode(', ', $user_context['recent_activity']);
        $context_prefix = "\n\n**Your Recent Activity:**\n$recent_activity\n";
    } else {
        $context_prefix = "\n\n**Welcome to Alertara!** This appears to be your first interaction.\n";
    }
    
    // Intent-based responses
    switch ($intent) {
        case 'report_incident':
            return "**Creating an Incident Report:**$context_prefix\n\n**Step-by-Step Guide:**\n\n1. **Navigate:** Go to 'Incident Report' in the main menu\n\n2. **Fill Details:**\n   - Incident type (theft, assault, etc.)\n   - Exact location and time\n   - Detailed description of what happened\n   - Information about people involved\n   - Any witnesses or evidence\n\n3. **Attachments:** Add photos, documents, or videos\n\n4. **Submit:** Click 'Create Incident Report'\n\n**What happens next:**\n- AI analyzes your report for sentiment, threat level, and severity\n- System automatically notifies relevant authorities\n- You'll receive updates on your dashboard\n- High-priority incidents get immediate attention\n\n**Need help?** Describe your situation and I'll guide you through it.";
            
        case 'check_status':
            if ($user_context['incident_count'] > 0) {
                return "**Checking Your Incident Status:**$context_prefix\n\n**How to check:**\n\n1. **Dashboard:** View your recent incidents on the main dashboard\n2. **Incident Report Page:** Go to 'Incident Reports' → 'My Reports'\n3. **Search:** Use case numbers to find specific incidents\n\n**Status Meanings:**\n- **Draft:** Not yet submitted\n- **Submitted:** Under review\n- **Under Review:** Being processed\n- **Verified:** Confirmed and assigned\n- **Resolved:** Case closed\n\n**Need a specific case update?** Provide the case number and I'll help you find it.";
            } else {
                return "**Welcome to Alertara!**$context_prefix\n\nYou haven't submitted any incident reports yet. Would you like help creating your first report?\n\n**Quick Start:**\n- Click 'Incident Report' in the menu\n- Fill in the details of what happened\n- Submit for immediate processing\n\nThe system uses advanced AI to analyze and prioritize your report automatically.";
            }
            
        case 'account_issue':
            return "**Account & Technical Support:**$context_prefix\n\n**Common Issues & Solutions:**\n\n**🔐 Login Problems:**\n- Check email for verification link (24-hour expiry)\n- Reset password via 'Forgot Password'\n- Clear browser cache and cookies\n\n**📧 Email Issues:**\n- Check spam/junk folder\n- Add alertara@system.com to contacts\n- Contact admin if verification doesn't arrive\n\n**🔒 Security Features:**\n- 2FA available for enhanced security\n- Session timeout after 30 minutes of inactivity\n- All communications are encrypted\n\n**🆘 Need Help?**\n- Try logging out and back in\n- Clear browser cache\n- Contact system administrator\n\n**Emergency?** If this prevents you from reporting an incident, call local authorities directly.";
            
        case 'emergency':
            return "**🚨 URGENT SITUATION DETECTED**\n\n**IMMEDIATE ACTION REQUIRED:**\n\n1. **Ensure Safety First**\n   - Move to a secure location\n   - Call emergency services if in danger\n\n2. **Report Through Alertara**\n   - Use the 'Emergency Report' option\n   - Provide exact location and details\n   - System will prioritize this report\n\n3. **Emergency Contacts**\n   - Philippines Emergency: 911\n   - Local Police: Contact nearest station\n   - Medical Emergency: 117\n\n**Alertara Emergency Features:**\n- Automatic high-priority routing\n- Immediate notification to authorities\n- Real-time status updates\n\n*Stay safe. Help is on the way.*";
            
        default:
            // Sentiment-aware general responses
            if ($sentiment === 'negative' || preg_match('/\b(frustrated|angry|upset|worried|scared|confused)\b/i', $lower)) {
                return "**I understand this is distressing**$context_prefix\n\nI'm here to help you through this situation. Alertara is designed to make incident reporting as easy and supportive as possible.\n\n**What I can help with:**\n\n1. **Guide you through reporting** - Step-by-step assistance\n2. **Answer questions** - About the process, your rights, next steps\n3. **Provide resources** - Support contacts and information\n4. **Escalate if needed** - For urgent situations\n\n**Remember:** Your safety and well-being are the top priority. Take your time, and we'll work through this together.\n\nWhat specific aspect can I help you with right now?";
            }
            
            // General helpful response
            return "**Welcome to Alertara's AI Assistant!**$context_prefix\n\nI'm your intelligent guide through the incident reporting system. With advanced NLP capabilities, I can understand your needs and provide personalized assistance.\n\n**What I can help you with:**\n\n🔍 **Find Information**\n- How to report incidents\n- Check report status\n- System features and navigation\n\n📝 **Report Assistance**\n- Step-by-step guidance\n- What information to include\n- Attachment help\n\n🚨 **Emergency Support**\n- Immediate help for urgent situations\n- Emergency contact information\n- Priority escalation\n\n💬 **General Support**\n- Account questions\n- Technical issues\n- Best practices\n\n**How can I assist you today?** Feel free to ask anything about the system or your incident reports.";
    }
}

$reply = getKnowledgeBaseReply($message, $user_context, $nlp_analysis);

// Store conversation for context
storeConversation($_SESSION['user_id'], $message, $reply, 'knowledge_base');

// Translate if needed
if ($user_language !== 'en') {
    $reply = LanguageDetector::translateText($reply, 'en', $user_language);
}

echo json_encode([
    'reply' => $reply, 
    'source' => 'knowledge_base', 
    'language' => $user_language,
    'nlp_insights' => $nlp_analysis,
    'context_used' => true
]);

?>
