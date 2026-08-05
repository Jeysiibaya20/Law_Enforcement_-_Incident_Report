<?php
/**
 * Unified Top Navigation Header Component
 * Synced with EMERGENCY-COM standard admin header bar
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$script_dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$in_subfolder = (strpos($script_dir, '/modules') !== false) || (strpos($script_dir, '/admin') !== false) || (strpos($script_dir, '/officer') !== false);
$base_url = isset($base_url) ? $base_url : ($in_subfolder ? '../' : '');

$is_logged_in = isset($_SESSION['user_id']) || isset($_SESSION['user_logged_in']);
$role = strtolower(trim($_SESSION['role'] ?? 'guest'));
$first_name = $_SESSION['first_name'] ?? $_SESSION['user_name'] ?? ($is_logged_in ? 'User' : 'Guest');
$last_name = $_SESSION['last_name'] ?? '';
$full_name = trim($first_name . ' ' . $last_name);
$display_role = ucfirst($role);

$avatar_url = !empty($_SESSION['user_picture']) 
    ? $_SESSION['user_picture'] 
    : 'https://ui-avatars.com/api/?name=' . urlencode($full_name ?: 'User') . '&background=4c8a89&color=fff&size=128';

$login_href = $base_url . 'auth/login.php';
$profile_href = $base_url . ($role === 'admin' ? 'admin/settings.php' : 'modules/my_reports.php');
?>

<header class="admin-header">
    <div class="admin-header-left">
        <button class="menu-toggle" id="menuToggle" aria-label="Toggle Navigation Menu" title="Toggle Sidebar">
            <i class="fas fa-bars"></i>
        </button>

        <a href="<?php echo $base_url; ?>index.php" class="header-brand-title d-none d-sm-flex">
            <i class="fas fa-shield-alt"></i>
            <span>Alertara Incident System</span>
        </a>

        <div class="search-container">
            <i class="fas fa-search search-icon"></i>
            <input type="text" class="search-input" id="headerSearchInput" placeholder="Search incidents, cases, pages..." autocomplete="off">
            <div class="search-dropdown" id="searchDropdown"></div>
        </div>
    </div>

    <div class="admin-header-right">
        <div class="datetime-display d-none d-md-flex" id="headerDateTime">
            <span class="date-part"></span>
            <span class="time-separator">|</span>
            <span class="time-part"></span>
        </div>

        <div class="header-actions">
            <div class="theme-toggle-container">
                <button class="theme-mode-btn" id="lightModeBtn" aria-label="Light Mode" title="Light Theme">
                    <i class="fas fa-sun"></i>
                    <span class="d-none d-lg-inline">Light</span>
                </button>
                <button class="theme-mode-btn" id="darkModeBtn" aria-label="Dark Mode" title="Dark Theme">
                    <i class="fas fa-moon"></i>
                    <span class="d-none d-lg-inline">Dark</span>
                </button>
            </div>

            <button class="menu-toggle" id="headerLanguageBtn" aria-label="Language Selector" title="Switch Language">
                <i class="fas fa-globe"></i>
            </button>
        </div>

        <div class="header-divider"></div>

        <?php if ($is_logged_in): ?>
            <a class="user-profile" href="<?php echo $profile_href; ?>" title="Profile Settings">
                <div class="user-info d-none d-sm-flex">
                    <span class="user-name"><?php echo htmlspecialchars($full_name); ?></span>
                    <span class="user-role"><?php echo htmlspecialchars($display_role); ?></span>
                </div>
                <div class="user-avatar">
                    <img src="<?php echo htmlspecialchars($avatar_url); ?>" alt="<?php echo htmlspecialchars($full_name); ?>" class="avatar-img">
                </div>
            </a>
        <?php else: ?>
            <a class="btn btn-primary btn-sm" href="<?php echo $login_href; ?>">
                <i class="fas fa-sign-in-alt"></i> Sign In
            </a>
        <?php endif; ?>
    </div>
</header>
