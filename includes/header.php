<?php
/**
 * Unified Main Header Include Component
 * Synced with EMERGENCY-COM standard design tokens & asset links
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$script_dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$in_subfolder = (strpos($script_dir, '/modules') !== false) || (strpos($script_dir, '/admin') !== false) || (strpos($script_dir, '/officer') !== false);
$base_url = isset($base_url) ? $base_url : ($in_subfolder ? '../' : '');

$is_logged_in = isset($_SESSION['user_id']) || isset($_SESSION['user_logged_in']);
$current_user = $is_logged_in ? $_SESSION : null;

// Require LanguageManager if exists
$lang_manager_file = __DIR__ . '/../config/LanguageManager.php';
if (file_exists($lang_manager_file)) {
    require_once $lang_manager_file;
    $current_lang = class_exists('LanguageManager') ? LanguageManager::getCurrentLanguage() : 'en';
} else {
    $current_lang = 'en';
}
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($current_lang); ?>" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Alertara - Law Enforcement & Incident Report System">
    <meta name="author" content="Alertara Team">
    
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) . ' - ' : ''; ?>Alertara Incident System</title>
    
    <!-- Instant Theme Loader (Prevents FOUC) -->
    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme') || localStorage.getItem('alertaraTheme') || 'light';
            document.documentElement.setAttribute('data-theme', savedTheme);
        })();
    </script>

    <!-- FontAwesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Bootstrap 5.3 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Quicksand:wght@400;600;700&display=swap" rel="stylesheet">
    
    <!-- Centralized CSS -->
    <link href="<?php echo $base_url; ?>assets/css/global.css" rel="stylesheet">
    <link href="<?php echo $base_url; ?>assets/css/style.css" rel="stylesheet">
    <link href="<?php echo $base_url; ?>assets/css/theme.css" rel="stylesheet">

    <?php if (isset($additional_head)) echo $additional_head; ?>
</head>
<body class="has-sidebar <?php echo !empty($body_class) ? htmlspecialchars($body_class) : ''; ?>">

<?php 
// Include Top Header Bar and Sidebar Navigation Drawer
include __DIR__ . '/navbar.php';
include __DIR__ . '/sidebar.php';
?>