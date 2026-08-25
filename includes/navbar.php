<?php
/**
 * Unified Top Navigation Header Component
 * Synced with EMERGENCY-COM standard admin header bar + Real Notifications Dropdown
 */

if (defined('ALERTARA_NAVBAR_LOADED')) {
    return;
}
define('ALERTARA_NAVBAR_LOADED', true);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$script_dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$in_subfolder = (strpos($script_dir, '/modules') !== false) || (strpos($script_dir, '/admin') !== false) || (strpos($script_dir, '/officer') !== false);
$base_url = isset($base_url) ? $base_url : ($in_subfolder ? '../' : '');

$current_page = strtolower(basename($_SERVER['PHP_SELF']));
$script_path = str_replace('\\', '/', $_SERVER['PHP_SELF'] ?? '');
$is_admin_dir = (strpos($script_path, '/admin/') !== false);

$has_admin_session = !empty($_SESSION['admin_user_id']);
$has_resident_session = !empty($_SESSION['resident_user_id']);
$session_role = strtolower(trim($_SESSION['admin_role'] ?? $_SESSION['role'] ?? ''));
$is_admin_or_officer = (strpos($session_role, 'admin') !== false || strpos($session_role, 'officer') !== false || strpos($session_role, 'official') !== false);

if ($is_admin_dir) {
    $is_user_page = false;
} elseif ($has_resident_session && !$has_admin_session && !$is_admin_or_officer) {
    $is_user_page = true;
} elseif (!empty($force_public_sidebar)) {
    $is_user_page = true;
} elseif (in_array($current_page, ['landing.php', 'index.php', 'my_reports.php', 'blotter_create.php', 'blotter_view.php', 'user_profile.php', 'learning.php'])) {
    $is_user_page = !$has_admin_session || !$is_admin_or_officer;
} else {
    $is_user_page = false;
}

if ($is_user_page) {
    $is_logged_in = !empty($_SESSION['resident_user_id']) || (!empty($_SESSION['user_id']) && !$has_admin_session);
    $full_name = $_SESSION['fullname'] ?? $_SESSION['first_name'] ?? 'Resident';
    $role = 'user';
    $display_role = 'Resident';
    $profile_href = $base_url . 'modules/user_profile.php';
    $login_href = $base_url . 'auth/login.php';
} else {
    $is_logged_in = !empty($_SESSION['admin_user_id']);
    $full_name = $_SESSION['admin_fullname'] ?? $_SESSION['admin_first_name'] ?? 'Admin';
    $role = strtolower(trim($_SESSION['admin_role'] ?? 'admin'));
    $display_role = ucfirst($role);
    $profile_href = $base_url . 'admin/settings.php';
    $login_href = $base_url . 'admin/login.php';
}

$avatar_url = !empty($_SESSION['user_picture']) 
    ? $_SESSION['user_picture'] 
    : 'https://ui-avatars.com/api/?name=' . urlencode($full_name ?: 'User') . '&background=4c8a89&color=fff&size=128';

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

<script>
function toggleAlertaraSidebar(e) {
    if (e) {
        e.preventDefault();
        e.stopPropagation();
    }
    var sidebar = document.getElementById('sidebar') || document.getElementById('appSidebar') || document.querySelector('.sidebar');
    var overlay = document.getElementById('sidebarOverlay') || document.querySelector('.sidebar-overlay');
    var isMobile = window.innerWidth <= 992;
    
    if (sidebar) {
        if (isMobile) {
            sidebar.classList.toggle('active');
            sidebar.classList.toggle('show');
            if (overlay) {
                overlay.classList.toggle('active');
                overlay.classList.toggle('show');
            }
            document.body.classList.toggle('sidebar-mobile-open');
        } else {
            sidebar.classList.toggle('collapsed');
            sidebar.classList.remove('active', 'show');
            document.body.classList.toggle('sidebar-collapsed');
        }
    }
}
</script>

<header class="admin-header">
    <div class="admin-header-left">
        <button class="menu-toggle" id="menuToggle" onclick="toggleAlertaraSidebar(event)" aria-label="Toggle Navigation Menu" title="Toggle Sidebar" style="cursor: pointer; position: relative; z-index: 1055; pointer-events: auto;">
            <i class="fas fa-bars" style="pointer-events: none;"></i>
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
                <button class="header-action-btn" id="notificationBtn" onclick="toggleNotificationDropdown(event)" aria-label="Notifications" title="Real Notifications" style="cursor: pointer; position: relative;">
                    <i class="fas fa-bell"></i>
                    <span class="notification-badge-count" id="notificationBadgeCount" style="<?php echo $unread_count > 0 ? '' : 'display: none;'; ?>"><?php echo $unread_count; ?></span>
                </button>
                <div class="notification-dropdown-menu" id="notificationDropdown" style="z-index: 1060;">
                    <div class="notification-header">
                        <h6><i class="fas fa-bell me-2"></i>Notifications</h6>
                        <span class="badge bg-danger rounded-pill" id="notificationHeaderBadge"><?php echo $unread_count; ?> New</span>
                    </div>
                    <div class="notification-body" id="notificationListBody">
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

            <script>
            function toggleNotificationDropdown(e) {
                if (e) {
                    e.preventDefault();
                    e.stopPropagation();
                }
                var notifDropdown = document.getElementById('notificationDropdown');
                if (notifDropdown) {
                    notifDropdown.classList.toggle('active');
                }
            }

            document.addEventListener('click', function(e) {
                var notifDropdown = document.getElementById('notificationDropdown');
                var notifBtn = document.getElementById('notificationBtn');
                if (notifDropdown && notifBtn && !notifDropdown.contains(e.target) && !notifBtn.contains(e.target)) {
                    notifDropdown.classList.remove('active');
                }
            });

            // Real-time AJAX notification poller
            function fetchRealtimeNotifications() {
                var apiUrl = '<?php echo $base_url; ?>api/get_notifications.php';
                fetch(apiUrl)
                    .then(function(res) { return res.json(); })
                    .then(function(data) {
                        if (!data || !data.success) return;

                        var badgeCountEl = document.getElementById('notificationBadgeCount');
                        var headerBadgeEl = document.getElementById('notificationHeaderBadge');
                        var bodyEl = document.getElementById('notificationListBody');

                        if (badgeCountEl) {
                            if (data.unread_count > 0) {
                                badgeCountEl.textContent = data.unread_count;
                                badgeCountEl.style.display = 'inline-block';
                            } else {
                                badgeCountEl.style.display = 'none';
                            }
                        }

                        if (headerBadgeEl) {
                            headerBadgeEl.textContent = data.unread_count + ' New';
                        }

                        if (bodyEl && Array.isArray(data.notifications)) {
                            if (data.notifications.length > 0) {
                                var html = '';
                                data.notifications.forEach(function(n) {
                                    var iconClass = n.type === 'blotter' ? 'icon-blotter' : (n.type === 'report' ? 'icon-report' : 'icon-user');
                                    var iconFa = n.type === 'blotter' ? 'fa-clipboard-list' : (n.type === 'report' ? 'fa-file-alt' : 'fa-user-clock');
                                    html += '<a href="' + n.link + '" class="notification-item">' +
                                                '<div class="notification-icon ' + iconClass + '">' +
                                                    '<i class="fas ' + iconFa + '"></i>' +
                                                '</div>' +
                                                '<div class="notification-content">' +
                                                    '<strong class="notification-title">' + n.title + '</strong>' +
                                                    '<span class="notification-desc">' + n.desc + '</span>' +
                                                    '<small class="notification-time">' + n.time + '</small>' +
                                                '</div>' +
                                            '</a>';
                                });
                                bodyEl.innerHTML = html;
                            } else {
                                bodyEl.innerHTML = '<div class="text-center py-4 text-muted small"><i class="fas fa-check-circle fa-2x mb-2 text-success opacity-50"></i><div>No new notifications</div></div>';
                            }
                        }
                    })
                    .catch(function(err) {
                        console.log('Realtime notification poller exception:', err);
                    });
            }

            // Poll every 10 seconds for real-time updates
            setInterval(fetchRealtimeNotifications, 10000);
            </script>

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
