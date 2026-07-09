<?php
/**
 * Global Language Manager
 * Handles system-wide language switching and storage
 */

// Avoid starting sessions or handling HTTP redirects when running in CLI (artisan/package discovery)
if (php_sapi_name() !== 'cli') {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

class LanguageManager {
    private static $supported_languages = [
        'en' => ['name' => 'English', 'flag' => '🇺🇸'],
        'es' => ['name' => 'Español', 'flag' => '🇪🇸'],
        'fr' => ['name' => 'Français', 'flag' => '🇫🇷'],
        'de' => ['name' => 'Deutsch', 'flag' => '🇩🇪'],
        'it' => ['name' => 'Italiano', 'flag' => '🇮🇹'],
        'pt' => ['name' => 'Português', 'flag' => '🇵🇹'],
        'ru' => ['name' => 'Русский', 'flag' => '🇷🇺'],
        'ja' => ['name' => '日本語', 'flag' => '🇯🇵'],
        'zh' => ['name' => '中文', 'flag' => '🇨🇳'],
        'ko' => ['name' => '한국어', 'flag' => '🇰🇷'],
        'ar' => ['name' => 'العربية', 'flag' => '🇸🇦'],
        'hi' => ['name' => 'हिन्दी', 'flag' => '🇮🇳'],
        'tl' => ['name' => 'Tagalog', 'flag' => '🇵🇭'],
    ];

    private static $translations = [
        'es' => [
            'language' => 'Idioma',
            'account' => 'Cuenta',
            'logout' => 'Cerrar sesión',
            'login' => 'Iniciar sesión',
            'loading' => 'Cargando...',
            'please_wait' => 'Por favor espera',
            'redirecting' => 'Redirigiendo...',
            'settings' => 'Configuración',
            'profile' => 'Perfil',
            'dashboard' => 'Panel de Control',
            'admin_panel' => 'Panel de Administrador',
            'modules' => 'Módulos',
            'blotter' => 'Sistema de Registro',
            'incident_report' => 'Reporte de Incidente',
            'case_management' => 'Gestión de Casos',
            'reports' => 'Reportes',
            'users' => 'Usuarios',
            'home' => 'Inicio',
            'back' => 'Atrás',
        ],
        'fr' => [
            'language' => 'Langue',
            'account' => 'Compte',
            'logout' => 'Déconnexion',
            'login' => 'Connexion',
            'loading' => 'Chargement...',
            'please_wait' => 'Veuillez patienter',
            'redirecting' => 'Redirection...',
            'settings' => 'Paramètres',
            'profile' => 'Profil',
            'dashboard' => 'Tableau de Bord',
            'admin_panel' => 'Panneau Administrateur',
            'modules' => 'Modules',
            'blotter' => 'Système de Registre',
            'incident_report' => 'Rapport d\'Incident',
            'case_management' => 'Gestion des Cas',
            'reports' => 'Rapports',
            'users' => 'Utilisateurs',
            'home' => 'Accueil',
            'back' => 'Retour',
        ],
        'de' => [
            'language' => 'Sprache',
            'account' => 'Konto',
            'logout' => 'Abmelden',
            'login' => 'Anmelden',
            'loading' => 'Laden...',
            'please_wait' => 'Bitte warten',
            'redirecting' => 'Umleitung...',
            'settings' => 'Einstellungen',
            'profile' => 'Profil',
            'dashboard' => 'Dashboard',
            'admin_panel' => 'Admin-Panel',
            'modules' => 'Module',
            'blotter' => 'Registersystem',
            'incident_report' => 'Vorfallbericht',
            'case_management' => 'Fallverwaltung',
            'reports' => 'Berichte',
            'users' => 'Benutzer',
            'home' => 'Startseite',
            'back' => 'Zurück',
        ],
        'pt' => [
            'language' => 'Idioma',
            'account' => 'Conta',
            'logout' => 'Sair',
            'login' => 'Entrar',
            'loading' => 'Carregando...',
            'please_wait' => 'Por favor aguarde',
            'redirecting' => 'Redirecionando...',
            'settings' => 'Configurações',
            'profile' => 'Perfil',
            'dashboard' => 'Painel de Controle',
            'admin_panel' => 'Painel Administrativo',
            'modules' => 'Módulos',
            'blotter' => 'Sistema de Registro',
            'incident_report' => 'Relatório de Incidente',
            'case_management' => 'Gestão de Casos',
            'reports' => 'Relatórios',
            'users' => 'Usuários',
            'home' => 'Início',
            'back' => 'Voltar',
        ],
        'ja' => [
            'language' => '言語',
            'account' => 'アカウント',
            'logout' => 'ログアウト',
            'login' => 'ログイン',
            'loading' => '読み込み中...',
            'please_wait' => 'お待ちください',
            'redirecting' => 'リダイレクト中...',
            'settings' => '設定',
            'profile' => 'プロフィール',
            'dashboard' => 'ダッシュボード',
            'admin_panel' => '管理者パネル',
            'modules' => 'モジュール',
            'blotter' => '登録システム',
            'incident_report' => 'インシデント報告',
            'case_management' => 'ケース管理',
            'reports' => 'レポート',
            'users' => 'ユーザー',
            'home' => 'ホーム',
            'back' => '戻る',
        ],
        'tl' => [
            'language' => 'Wika',
            'account' => 'Account',
            'logout' => 'Mag-logout',
            'login' => 'Mag-login',
            'loading' => 'Nag-load...',
            'please_wait' => 'Mangyaring maghintay',
            'redirecting' => 'Nagrerenedirekta...',
            'settings' => 'Mga Setting',
            'profile' => 'Profilo',
            'dashboard' => 'Dashboard',
            'admin_panel' => 'Admin Panel',
            'modules' => 'Mga Module',
            'blotter' => 'Blotter System',
            'incident_report' => 'Ulat ng Insidente',
            'case_management' => 'Pamamahala ng Kaso',
            'reports' => 'Mga Ulat',
            'users' => 'Mga User',
            'home' => 'Tahanan',
            'back' => 'Bumalik',
        ],
    ];

    /**
     * Get current language from session or browser
     */
    public static function getCurrentLanguage() {
        // Check session first
        if (isset($_SESSION['language']) && isset(self::$supported_languages[$_SESSION['language']])) {
            return $_SESSION['language'];
        }
        
        // Check browser language
        if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $accept_language = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
            $primary_lang = explode('-', $accept_language[0])[0];
            if (isset(self::$supported_languages[$primary_lang])) {
                return $primary_lang;
            }
        }
        
        // Default to English
        return 'en';
    }

    /**
     * Set language in session
     */
    public static function setLanguage($lang) {
        if (isset(self::$supported_languages[$lang])) {
            $_SESSION['language'] = $lang;
            return true;
        }
        return false;
    }

    /**
     * Get all supported languages
     */
    public static function getSupportedLanguages() {
        return self::$supported_languages;
    }

    /**
     * Translate a key to current language
     */
    public static function translate($key, $lang = null) {
        if ($lang === null) {
            $lang = self::getCurrentLanguage();
        }

        // If language has translation, return it
        if (isset(self::$translations[$lang][$key])) {
            return self::$translations[$lang][$key];
        }

        // Otherwise return English or key itself
        return $key;
    }

    /**
     * Get language name
     */
    public static function getLanguageName($lang) {
        return self::$supported_languages[$lang]['name'] ?? 'Unknown';
    }

    /**
     * Get language flag
     */
    public static function getLanguageFlag($lang) {
        return self::$supported_languages[$lang]['flag'] ?? '🌐';
    }
}

// Handle language change request (only in web requests)
if (php_sapi_name() !== 'cli' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['set_language'])) {
    $lang = $_POST['set_language'] ?? 'en';
    LanguageManager::setLanguage($lang);
    
    // If AJAX request
    if (!empty($_POST['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'language' => $lang]);
        exit;
    }
    
    // Redirect back
    $redirect = $_POST['redirect'] ?? ($_SERVER['HTTP_REFERER'] ?? '../index.php');
    header("Location: $redirect");
    exit;
}

?>
