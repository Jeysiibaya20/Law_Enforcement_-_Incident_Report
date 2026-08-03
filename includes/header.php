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
    
    <script>
        (function() {
            const savedTheme = localStorage.getItem('alertaraTheme');
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            const theme = savedTheme || (prefersDark ? 'dark' : 'light');
            const root = document.documentElement;

            root.setAttribute('data-theme', theme);
            root.classList.remove('light-mode', 'dark-mode');
            root.classList.add(theme + '-mode');

            function applyBodyTheme() {
                if (!document.body) return;
                document.body.classList.remove('light-mode', 'dark-mode');
                document.body.classList.add(theme + '-mode');
            }

            applyBodyTheme();
            document.addEventListener('DOMContentLoaded', applyBodyTheme);
        })();
    </script>

    <!-- Bootstrap 5.3.8 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: '#0f766e'
                    }
                }
            }
        };
    </script>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@300;400;500;600;700&family=Libre+Baskerville:wght@400;700&display=swap" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="<?php echo isset($base_url) ? $base_url : ''; ?>assets/css/global.css" rel="stylesheet">
    <link href="<?php echo isset($base_url) ? $base_url : ''; ?>assets/css/auth.css" rel="stylesheet">
    <link href="<?php echo isset($base_url) ? $base_url : ''; ?>assets/css/style.css" rel="stylesheet">
    <link href="<?php echo isset($base_url) ? $base_url : ''; ?>assets/css/navbar.css" rel="stylesheet">
    <link href="<?php echo isset($base_url) ? $base_url : ''; ?>assets/css/theme.css" rel="stylesheet">
    <link href="<?php echo isset($base_url) ? $base_url : ''; ?>assets/css/theme-dark-overrides.css" rel="stylesheet">

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
// Chat widget removed per request (site disables AI Assistant widget)
?>