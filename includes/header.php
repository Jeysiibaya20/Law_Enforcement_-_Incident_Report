<?php

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load language manager
require_once __DIR__ . '/../config/LanguageManager.php';

// Check if user is logged in (basic check)
$is_logged_in = isset($_SESSION['user_id']);
$current_user = $is_logged_in ? $_SESSION : null;
$current_lang = LanguageManager::getCurrentLanguage();
$supported_langs = LanguageManager::getSupportedLanguages();
?>
<!DOCTYPE html>
<html lang="<?php echo $current_lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Alertara">
    <meta name="author" content="Alertara">
    <meta name="robots" content="noindex, nofollow">
    
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>Alertara</title>
    
    <!-- Bootstrap 5.3.8 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@300;400;500;600;700&family=Libre+Baskerville:wght@400;700&display=swap" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="<?php echo isset($base_url) ? $base_url : ''; ?>assets/css/style.css" rel="stylesheet">
    <link href="<?php echo isset($base_url) ? $base_url : ''; ?>assets/css/global.css" rel="stylesheet">
    
    <!-- Favicon -->
    <!-- <link rel="icon" type="image/x-icon" href="<?php echo isset($base_url) ? $base_url : ''; ?>assets/images/favicon.ico"> -->
    
    <!-- Additional Head Content -->
    <?php if (isset($additional_head)) echo $additional_head; ?>
</head>
<body<?php if (!empty($body_class)) { echo ' class="' . htmlspecialchars($body_class) . '"'; } ?>>

<?php $hdr_base = isset($base_url) ? $base_url : ''; ?>
<?php if (isset($_SESSION['role']) && strtolower($_SESSION['role']) === 'admin'): ?>

<?php endif; ?>

<?php
// Show floating chat widget for regular user accounts (not Admin/Officer)
if (isset($_SESSION['user_id'])) {
    $role = isset($_SESSION['role']) ? strtolower($_SESSION['role']) : 'user';
    if ($role !== 'admin' && $role !== 'officer') {
        ?>
        <!-- Floating Chat Widget -->
        <style>
            #chatWidget {
                position: fixed;
                bottom: 20px;
                right: 20px;
                width: 350px;
                max-width: 90vw;
                border-radius: 12px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                z-index: 9999;
                background: white;
                display: flex;
                flex-direction: column;
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            }
            #chatWidget.closed {
                width: auto;
            }
            #chatWidgetHeader {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 15px;
                border-radius: 12px 12px 0 0;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            #chatWidgetControls {
                display: flex;
                gap: 8px;
            }
            #langSelectChat {
                background: rgba(255,255,255,0.2);
                color: white;
                border: none;
                border-radius: 4px;
                padding: 4px 8px;
                font-size: 12px;
                cursor: pointer;
            }
            #langSelectChat option {
                color: #333;
            }
            #toggleBtn {
                background: none;
                border: none;
                color: white;
                cursor: pointer;
                font-size: 18px;
            }
            #chatWidgetBody {
                display: flex;
                flex-direction: column;
                height: 400px;
                overflow: hidden;
            }
            #chatWidgetBody.closed {
                display: none;
            }
            #chatBox {
                flex: 1;
                overflow-y: auto;
                padding: 12px;
                background: #fafafa;
            }
            #chatBox .msg {
                margin-bottom: 10px;
                padding: 8px 12px;
                border-radius: 6px;
                max-width: 85%;
                word-wrap: break-word;
                font-size: 13px;
            }
            #chatBox .msg.user {
                background: #667eea;
                color: white;
                margin-left: auto;
                text-align: right;
            }
            #chatBox .msg.assistant {
                background: white;
                color: #333;
                border: 1px solid #ddd;
            }
            #chatFooter {
                border-top: 1px solid #eee;
                padding: 10px;
                display: flex;
                gap: 6px;
            }
            #messageInput {
                flex: 1;
                border: 1px solid #ddd;
                border-radius: 6px;
                padding: 8px 10px;
                font-size: 13px;
            }
            #sendBtn {
                background: #667eea;
                color: white;
                border: none;
                border-radius: 6px;
                padding: 8px 14px;
                cursor: pointer;
                font-size: 13px;
                font-weight: 600;
            }
            #sendBtn:hover {
                background: #764ba2;
            }
        </style>

        <div id="chatWidget">
            <div id="chatWidgetHeader">
                <span><i class="bi bi-chat-dots"></i> AI Assistant</span>
                <div id="chatWidgetControls">
                    <select id="langSelectChat" title="Chat Language">
                        <option value="en">🇺🇸</option>
                        <option value="es">🇪🇸</option>
                        <option value="fr">🇫🇷</option>
                        <option value="de">🇩🇪</option>
                        <option value="ja">🇯🇵</option>
                        <option value="tl">🇵🇭</option>
                    </select>
                    <button id="toggleBtn" title="Minimize">−</button>
                </div>
            </div>
            <div id="chatWidgetBody">
                <div id="chatBox"></div>
                <div id="chatFooter">
                    <input id="messageInput" placeholder="Ask me..." autocomplete="off">
                    <button id="sendBtn">Send</button>
                </div>
            </div>
        </div>

        <script>
        const chatWidget = document.getElementById('chatWidget');
        const chatBox = document.getElementById('chatBox');
        const msgInput = document.getElementById('messageInput');
        const sendBtn = document.getElementById('sendBtn');
        const toggleBtn = document.getElementById('toggleBtn');
        const chatBody = document.getElementById('chatWidgetBody');
        const langSelectChat = document.getElementById('langSelectChat');

        // Detect user language
        let userLanguage = navigator.language.split('-')[0] || 'en';
        langSelectChat.value = userLanguage;

        // Change chat language
        langSelectChat.addEventListener('change', (e) => {
            userLanguage = e.target.value;
            chatBox.innerHTML = '';
            appendWelcomeMessage();
        });

        // Toggle widget open/close
        toggleBtn.addEventListener('click', () => {
            chatBody.classList.toggle('closed');
            chatWidget.classList.toggle('closed');
            toggleBtn.textContent = chatBody.classList.contains('closed') ? '+' : '−';
        });

        // Append message to chat
        function appendMessage(who, text) {
            const el = document.createElement('div');
            el.className = 'msg ' + (who === 'You' ? 'user' : 'assistant');
            el.textContent = text;
            chatBox.appendChild(el);
            chatBox.scrollTop = chatBox.scrollHeight;
        }

        // Send message
        function sendMessage() {
            const text = msgInput.value.trim();
            if (!text) return;
            appendMessage('You', text);
            msgInput.value = '';

            fetch('/Law_Enforcement_-_Incident_Report/api/chatbot_api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    message: text,
                    language: userLanguage
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.reply) {
                    appendMessage('Assistant', data.reply);
                } else if (data.error) {
                    appendMessage('Assistant', '⚠ Error: ' + data.error);
                }
            })
            .catch(() => {
                appendMessage('Assistant', '⚠ Network error');
            });
        }

        sendBtn.addEventListener('click', sendMessage);
        msgInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                sendMessage();
            }
        });

        // Welcome message in user's language
        const welcomeMessages = {
            'es': '¡Hola! Soy el Asistente de IA de Alertara. Puedo ayudarte con reportes de incidentes, gestión de casos, cuentas y más.',
            'fr': 'Bonjour! Je suis l\'Assistant IA d\'Alertara. Je peux vous aider avec les rapports d\'incident, la gestion des dossiers, les comptes, etc.',
            'de': 'Hallo! Ich bin der KI-Assistent von Alertara. Ich kann dir bei Vorfallberichten, Fallverwaltung, Konten und vielem mehr helfen.',
            'pt': 'Olá! Sou o Assistente de IA do Alertara. Posso ajudá-lo com relatórios de incidentes, gerenciamento de casos, contas e muito mais.',
            'ja': 'こんにちは！Alertaraのアシスタント AIです。インシデント報告書、ケース管理、アカウント、その他をお手伝いできます。',
            'tl': 'Kamusta! Ako ang AI Assistant ng Alertara. Matutulungan kita sa mga ulat ng insidente, pamamahala ng mga kaso, mga account, at marami pang iba.'
        };
        
        function appendWelcomeMessage() {
            const welcome = welcomeMessages[userLanguage] || 'Hi! I\'m the Alertara AI Assistant. I can help you with incident reports, case management, accounts, and more.';
            appendMessage('Assistant', welcome);
        }

        appendWelcomeMessage();
        </script>
        <?php
    }
}
?>