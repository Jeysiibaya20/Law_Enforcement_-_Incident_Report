<?php
/**
 * Unified Top Navigation Header Component
 * Synced with EMERGENCY-COM standard admin header bar + Real Notifications Dropdown
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

$current_page = strtolower(basename($_SERVER['PHP_SELF']));
$is_user_page = in_array($current_page, ['landing.php', 'index.php', 'my_reports.php', 'blotter_create.php', 'user_profile.php', 'incident_report.php', 'request_form.php', 'learning.php']) || !empty($force_public_sidebar);

$login_href = $base_url . 'auth/login.php';
$profile_href = $base_url . ($is_user_page ? 'modules/user_profile.php' : ($role === 'admin' ? 'admin/settings.php' : 'modules/user_profile.php'));

// Fetch notifications — scope by role to prevent data leakage
$unread_count = 0;
$notifications = [];

try {
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        require_once __DIR__ . '/../config/db_connect.php';
        $pdo = getDBConnection();
    }

    if ($pdo instanceof PDO) {
        $isAdminView = !$is_user_page && ($role === 'admin' || strpos($role, 'officer') !== false || strpos($role, 'official') !== false);

        if ($isAdminView) {
            // ADMIN ONLY: Pending blotters
            $stmt_b = $pdo->query("SELECT id, blotter_no, complainant_name, created_at FROM blotters WHERE status = 'Pending' ORDER BY created_at DESC LIMIT 4");
            $pending_blotters = $stmt_b->fetchAll(PDO::FETCH_ASSOC);
            foreach ($pending_blotters as $b) {
                $notifications[] = [
                    'type' => 'blotter',
                    'title' => 'Pending Blotter #' . htmlspecialchars($b['blotter_no']),
                    'desc' => 'Filed by ' . htmlspecialchars($b['complainant_name']),
                    'time' => date('M d, g:i a', strtotime($b['created_at'])),
                    'link' => $base_url . 'admin/blotters.php'
                ];
            }

            // ADMIN ONLY: Pending user approvals
            $stmt_unv = $pdo->query("SELECT user_id, fullname, emailadd, created_at FROM signup WHERE email_verified = 0 AND role != 'Admin' ORDER BY created_at DESC LIMIT 3");
            $unverified = $stmt_unv->fetchAll(PDO::FETCH_ASSOC);
            foreach ($unverified as $u) {
                $notifications[] = [
                    'type' => 'user',
                    'title' => 'Unverified User Signup',
                    'desc' => htmlspecialchars($u['fullname'] ?: $u['emailadd']),
                    'time' => date('M d, g:i a', strtotime($u['created_at'])),
                    'link' => $base_url . 'admin/account_approvals.php'
                ];
            }
        } else {
            // USER SIDE: Show only their own report status updates
            $userId = $_SESSION['user_id'] ?? null;
            if ($userId) {
                $stmt_r = $pdo->prepare("SELECT case_no, status, updated_at FROM incidents WHERE created_by = ? AND status != 'Pending' ORDER BY updated_at DESC LIMIT 5");
                $stmt_r->execute([$userId]);
                $userUpdates = $stmt_r->fetchAll(PDO::FETCH_ASSOC);
                foreach ($userUpdates as $ur) {
                    $notifications[] = [
                        'type' => 'report',
                        'title' => 'Case ' . htmlspecialchars($ur['case_no']),
                        'desc' => 'Status: ' . htmlspecialchars($ur['status']),
                        'time' => date('M d, g:i a', strtotime($ur['updated_at'])),
                        'link' => $base_url . 'modules/my_reports.php'
                    ];
                }
            }
        }

        $unread_count = count($notifications);
    }
} catch (Exception $e) {
    $unread_count = 0;
    $notifications = [];
}
?>

<header class="admin-header">
    <div class="admin-header-left">
        <button class="menu-toggle" id="menuToggle" aria-label="Toggle Navigation Menu" title="Toggle Sidebar">
            <i class="fas fa-bars"></i>
        </button>

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
            <!-- Theme Mode Toggle -->
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

            <!-- Real Notification Dropdown -->
            <div class="notification-dropdown-wrap position-relative ms-1">
                <button class="header-action-btn" id="notificationBtn" aria-label="Notifications" title="Real Notifications">
                    <i class="fas fa-bell"></i>
                    <?php if ($unread_count > 0): ?>
                        <span class="notification-badge-count"><?php echo $unread_count; ?></span>
                    <?php endif; ?>
                </button>
                <div class="notification-dropdown-menu" id="notificationDropdown">
                    <div class="notification-header">
                        <h6><i class="fas fa-bell me-2"></i>Notifications</h6>
                        <span class="badge bg-danger rounded-pill"><?php echo $unread_count; ?> New</span>
                    </div>
                    <div class="notification-body">
                        <?php if (!empty($notifications)): ?>
                            <?php foreach ($notifications as $n): ?>
                                <a href="<?php echo $n['link']; ?>" class="notification-item">
                                    <div class="notification-icon <?php echo $n['type'] === 'blotter' ? 'icon-blotter' : ($n['type'] === 'report' ? 'icon-report' : 'icon-user'); ?>">
                                        <i class="fas <?php echo $n['type'] === 'blotter' ? 'fa-clipboard-list' : ($n['type'] === 'report' ? 'fa-file-alt' : 'fa-user-clock'); ?>"></i>
                                    </div>
                                    <div class="notification-content">
                                        <strong class="notification-title"><?php echo $n['title']; ?></strong>
                                        <span class="notification-desc"><?php echo $n['desc']; ?></span>
                                        <small class="notification-time"><?php echo $n['time']; ?></small>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-4 text-muted small">
                                <i class="fas fa-check-circle fa-2x mb-2 text-success opacity-50"></i>
                                <div>No new notifications</div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="notification-footer">
                        <?php if (!$is_user_page && ($role === 'admin' || strpos($role, 'officer') !== false)): ?>
                            <a href="<?php echo $base_url; ?>admin/blotters.php">View All Activity <i class="fas fa-chevron-right ms-1"></i></a>
                        <?php else: ?>
                            <a href="<?php echo $base_url; ?>modules/my_reports.php">View My Reports <i class="fas fa-chevron-right ms-1"></i></a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Language Toggle Button -->
            <button class="header-action-btn" id="headerLanguageBtn" aria-label="Language Selector" title="Switch Language">
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
