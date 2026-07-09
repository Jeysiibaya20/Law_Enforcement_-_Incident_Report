<?php
/**
 * Language System Setup & Documentation
 * Visit: http://localhost/Law_Enforcement_-_Incident_Report/setup_language_system.php
 */

session_start();
require_once 'config/LanguageManager.php';

$current_lang = LanguageManager::getCurrentLanguage();
$supported = LanguageManager::getSupportedLanguages();

?>
<!DOCTYPE html>
<html>
<head>
    <title>Language System Setup</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 20px; }
        .container { max-width: 900px; }
        .card { box-shadow: 0 10px 40px rgba(0,0,0,0.2); border: none; }
        .language-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        .lang-card {
            background: white;
            border: 2px solid #eee;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        .lang-card:hover {
            border-color: #667eea;
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }
        .lang-flag { font-size: 40px; margin-bottom: 10px; }
        .lang-name { font-weight: 600; color: #333; }
        .lang-code { font-size: 12px; color: #999; }
        .feature-list {
            background: #f8f9ff;
            border-left: 4px solid #667eea;
            padding: 15px;
            border-radius: 6px;
            margin: 15px 0;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <div class="card-body p-5">
            <h2 class="text-center mb-4">🌍 Language System Setup & Features</h2>

            <div class="alert alert-success">
                <h5>✓ Language System is Active!</h5>
                <p>Your current language: <strong><?php echo LanguageManager::getLanguageName($current_lang); ?> (<?php echo $current_lang; ?>)</strong></p>
            </div>

            <h4 class="mt-4">📍 System-Wide Language Selector</h4>
            <p>A language selector dropdown appears in the <strong>top-left corner</strong> of every page.</p>
            <ul>
                <li>Click to open the dropdown</li>
                <li>Select your preferred language</li>
                <li>Page automatically reloads and applies the language</li>
                <li>Choice is saved in session</li>
            </ul>

            <h4 class="mt-4">💬 AI Chatbot Language Support</h4>
            <p>The floating chat widget (bottom-right) has its own language selector:</p>
            <ul>
                <li>Click the flag icon in the chat header</li>
                <li>Select chat language independently</li>
                <li>AI responds in selected language</li>
                <li>6 languages available in chat: English, Spanish, French, German, Japanese, Filipino</li>
            </ul>

            <h4 class="mt-4">✨ Supported Languages</h4>
            <div class="language-grid">
                <?php foreach ($supported as $code => $info): ?>
                    <div class="lang-card">
                        <div class="lang-flag"><?php echo $info['flag']; ?></div>
                        <div class="lang-name"><?php echo $info['name']; ?></div>
                        <div class="lang-code"><?php echo $code; ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <h4 class="mt-4">⚡ Features</h4>

            <div class="feature-list">
                <h6>1. Global Loading Screen</h6>
                <p>When changing language or logging in/out, a smooth loading screen appears:</p>
                <ul class="mb-0">
                    <li>Animated spinner with message</li>
                    <li>Smooth fade-in/fade-out transitions</li>
                    <li>Shows "Loading...", "Logging in...", "Logging out...", "Redirecting..."</li>
                </ul>
            </div>

            <div class="feature-list">
                <h6>2. Auto-Detection</h6>
                <p>System automatically detects user's preferred language:</p>
                <ul class="mb-0">
                    <li>From browser's Accept-Language header</li>
                    <li>From session storage (remembered selection)</li>
                    <li>Falls back to English if not available</li>
                </ul>
            </div>

            <div class="feature-list">
                <h6>3. Session Persistence</h6>
                <p>Language preference is stored per session:</p>
                <ul class="mb-0">
                    <li>Applied to all pages automatically</li>
                    <li>Chatbot maintains its own language selection</li>
                    <li>Resets on logout</li>
                </ul>
            </div>

            <div class="feature-list">
                <h6>4. Login/Logout Experience</h6>
                <p>Enhanced user experience with loading screens:</p>
                <ul class="mb-0">
                    <li>Login shows "Logging in..." message</li>
                    <li>Logout shows "Logging out..." message</li>
                    <li>Smooth 1-second transition</li>
                    <li>Professional appearance</li>
                </ul>
            </div>

            <h4 class="mt-4">🔧 How to Use</h4>

            <h6>For End Users:</h6>
            <ol>
                <li><strong>Change System Language</strong>
                    <ul>
                        <li>Look for language selector in top-left corner</li>
                        <li>Click dropdown and select your language</li>
                        <li>Page reloads with new language</li>
                    </ul>
                </li>
                <li><strong>Change Chat Language</strong>
                    <ul>
                        <li>Open AI chat widget (bottom-right)</li>
                        <li>Click flag icon in chat header</li>
                        <li>Select different language for chat only</li>
                    </ul>
                </li>
            </ol>

            <h6>For Developers:</h6>
            <ol>
                <li><strong>Add New Language</strong>
                    <ul>
                        <li>Edit `config/LanguageManager.php`</li>
                        <li>Add to `$supported_languages` array</li>
                        <li>Add translations to `$translations` array</li>
                    </ul>
                </li>
                <li><strong>Use in Templates</strong>
                    <ul>
                        <li><code>require_once 'config/LanguageManager.php';</code></li>
                        <li><code>echo LanguageManager::translate('key_name');</code></li>
                    </ul>
                </li>
            </ol>

            <h4 class="mt-4">📁 Files Added/Modified</h4>
            <ul>
                <li><strong>NEW:</strong> `config/LanguageManager.php` - Core language system</li>
                <li><strong>MODIFIED:</strong> `includes/header.php` - Language selector + chat language button</li>
                <li><strong>MODIFIED:</strong> `auth/login.php` - Loading screen on login</li>
                <li><strong>MODIFIED:</strong> `auth/logout.php` - Loading screen on logout</li>
            </ul>

            <h4 class="mt-4">🎨 Customization</h4>

            <h6>Change Loading Screen Color:</h6>
            <p>Edit `includes/header.php`, find this CSS:</p>
            <code>#loadingScreen { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }</code>

            <h6>Add More Languages:</h6>
            <p>Edit `config/LanguageManager.php` in the LanguageManager class</p>

            <h6>Customize Messages:</h6>
            <p>Edit translations array in `config/LanguageManager.php`</p>

            <div class="alert alert-info mt-4">
                <h6>💡 Tips</h6>
                <ul class="mb-0">
                    <li>Language selector appears on all pages automatically</li>
                    <li>Chatbot language is independent from system language</li>
                    <li>Loading screen enhances professional appearance</li>
                    <li>Works across all browsers and devices</li>
                </ul>
            </div>

            <div class="d-grid gap-2 mt-4">
                <a href="index.php" class="btn btn-primary btn-lg">← Back to Home</a>
            </div>

        </div>
        <div class="card-footer bg-light text-muted text-center">
            <small>Language System v1.0 | Alertara Incident Report System</small>
        </div>
    </div>
</div>

</body>
</html>
