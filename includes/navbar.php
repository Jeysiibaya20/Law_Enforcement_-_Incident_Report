//

<?php
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
require_once __DIR__ . '/../config/LanguageManager.php';

$current_page = basename($_SERVER['PHP_SELF'], '.php');
$user_role = $_SESSION['role'] ?? 'Costumer';
$current_lang = LanguageManager::getCurrentLanguage();
$supported_langs = LanguageManager::getSupportedLanguages();

// Calculate base URL dynamically - detect if we're in modules or root
$script_dir = dirname($_SERVER['SCRIPT_NAME']);
// If we're inside the modules or admin folder, use parent base URL
$in_subfolder = (strpos($script_dir, '/modules') !== false) || (strpos($script_dir, '/admin') !== false);
$base_url = $in_subfolder ? '../' : '';

// Create absolute paths for navigation - adjust for current directory
$root_path = $base_url;
$dashboard_path = $root_path . 'admin/reports.php';
$admin_panel_path = $root_path . 'admin/dashboard.php';
$analytics_dashboard_path = $root_path . 'admin/analytics_dashboard.php';
$reports_path = $root_path . 'admin/reports.php';
$setup_automated_reports_path = $root_path . 'admin/setup_automated_reports.php';
$test_reports_path = $root_path . 'admin/test_reports_analytics.php';
$new_hire_path = $root_path . 'admin/analytics_dashboard.php';
$performance_path = $root_path . 'modules/blotter.php';
// For regular users, link to 'My Reports' which lists their submitted incidents.
$recruitment_path = $root_path . 'modules/incident_report.php';
$my_reports_path = $root_path . 'modules/my_reports.php';
$recognition_path = $root_path . 'modules/CaseAssign.php';
$competency_path = $root_path . 'modules/competency.php';
$succession_path = $root_path . 'modules/succession.php';
$learning_path = $root_path . 'modules/learning.php';
$recognition_path = $root_path . 'admin/cases.php';
?>

<!-- Mobile Menu Toggle Button -->
<button class="mobile-menu-toggle" id="menuToggle" onclick="toggleSidebar()">
    <i class="bi bi-list"></i>
</button>

<!-- Expand Navigation Button (when sidebar is collapsed on desktop) -->
<button class="navbar-expand-btn" id="expandBtn" onclick="toggleCollapse()" title="Show Navigation" aria-label="Show Navigation" style="display:flex;position:fixed;left:16px;top:16px;z-index:9999;align-items:center;justify-content:center;width:44px;height:44px;padding:6px;background:#0d6efd;color:#fff;border:2px solid rgba(255,255,255,0.9);border-radius:8px;box-shadow:0 6px 14px rgba(13,110,253,0.24);">
    <i class="bi bi-chevron-right"></i>
</button>

<!-- Sidebar Overlay for Mobile -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<script>
    function changeLanguage() {
        const form = document.getElementById('languageForm');
        form.set_language.value = document.getElementById('langSelect').value;
        form.submit();
    }

    function toggleNavSection(titleElement) {
        const content = titleElement.nextElementSibling;
        const chevron = titleElement.querySelector('.nav-chevron');
        const sectionName = titleElement.getAttribute('data-section');
        
        content.classList.toggle('hidden');
        chevron.classList.toggle('rotated');
        
        // Save state to localStorage
        localStorage.setItem('navSection_' + sectionName, content.classList.contains('hidden'));
    }
</script>

<div class="sidebar-wrapper" id="sidebar">
    <!-- Collapse Button for Desktop / Close Button for Mobile -->
    <button class="sidebar-close-btn" id="closeBtn" onclick="closeSidebar()">
        <i class="bi bi-x"></i>
    </button>
    <button class="sidebar-collapse-btn" id="collapseBtn" onclick="toggleCollapse()" title="Hide Navigation">
        <i class="bi bi-chevron-left"></i>
    </button>
    
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <img src="<?php echo $base_url; ?>assets/css/tara.png" alt="Logo" class="sidebar-logo-image">
        </div>
        <div class="sidebar-title">Alertara</div>
        <div class="sidebar-subtitle">Law Enforcemet & Incident Report</div>
        
        <!-- Language Selector -->
        <div style="margin-top: 15px; padding: 10px 0; border-top: 1px solid rgba(255,255,255,0.2); border-bottom: 1px solid rgba(255,255,255,0.2);">
            <form id="languageForm" method="POST" action="<?php echo $base_url; ?>config/LanguageManager.php" style="margin: 0;">
                <select name="set_language" id="langSelect" onchange="changeLanguage()" style="width: 100%; padding: 8px; border-radius: 6px; border: 1px solid #ddd; background: white; color: #333; font-size: 13px; cursor: pointer;">
                    <?php foreach ($supported_langs as $code => $lang_info): ?>
                        <option value="<?php echo $code; ?>" <?php echo $current_lang === $code ? 'selected' : ''; ?>>
                            <?php echo $lang_info['flag'] . ' ' . $lang_info['name']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
        
                <?php if (isset($_SESSION['role']) && strtolower($_SESSION['role']) === 'admin'): ?>
            <div class="mt-2 text-center">
                <a href="<?php echo $base_url; ?>admin/account_approvals.php" class="btn btn-sm btn-outline-light">Account Approvals</a>
            </div>
        <?php endif; ?>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-section">
            <div class="nav-section-title" onclick="toggleNavSection(this)" data-section="dashboard">
                <span><?php echo LanguageManager::translate('dashboard'); ?></span>
                <i class="bi bi-chevron-down nav-chevron"></i>
            </div>
            <div class="nav-section-content">
                <a href="<?php echo $dashboard_path; ?>" class="nav-link <?php echo ($current_page=='index')?'active':''; ?>" onclick="closeSidebar()">
                    <i class="bi bi-speedometer2"></i> <span><?php echo LanguageManager::translate('dashboard analytics'); ?></span>
                </a>
                <?php if (isset($_SESSION['user_id'])): 
                    // Check if user is admin
                    require_once __DIR__ . '/../config/db_connect.php';
                    $adminCheck = $pdo->prepare("SELECT role FROM signup WHERE user_id = ?");
                    $adminCheck->execute([$_SESSION['user_id']]);
                    $userRole = $adminCheck->fetch(PDO::FETCH_ASSOC);
                    if ($userRole && strtolower($userRole['role']) === 'admin'):
                ?>
                <a href="<?php echo $admin_panel_path; ?>" class="nav-link <?php echo ($current_page=='dashboard' && strpos($_SERVER['PHP_SELF'], 'admin') !== false)?'active':''; ?>" onclick="closeSidebar()">
                    <i class="bi bi-shield-lock"></i> <span><?php echo LanguageManager::translate('admin_panel'); ?></span>
                </a>
                <?php endif; endif; ?>
            </div>
        </div>
        <?php if (isset($_SESSION['user_id'])): 
            // Check user role
            require_once __DIR__ . '/../config/db_connect.php';
                        $roleCheck = $pdo->prepare("SELECT role, email_verified FROM signup WHERE user_id = ?");
                        $roleCheck->execute([$_SESSION['user_id']]);
                        $userRole = $roleCheck->fetch(PDO::FETCH_ASSOC);

                        // Determine admin approval state (if column exists) and fall back to email_verified
                        $userEmailVerified = !empty($userRole['email_verified']);
                        $userApproved = $userEmailVerified; // default fallback
                        try {
                            $ap = $pdo->prepare("SELECT admin_approved FROM signup WHERE user_id = ?");
                            $ap->execute([$_SESSION['user_id']]);
                            $apRow = $ap->fetch(PDO::FETCH_ASSOC);
                                if ($apRow && array_key_exists('admin_approved', $apRow)) {
                                    $userApproved = ((int)$apRow['admin_approved'] === 1);
                                }
                                // Admin role should not be blocked by admin_approved flag
                                $sessionRole = strtolower($_SESSION['role'] ?? '');
                                if ($sessionRole === 'admin') {
                                    $userApproved = true;
                                }
                                // Also if we are inside the admin panel URL, ensure the link is available
                                $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
                                if (stripos($scriptName, '/admin/') !== false) {
                                    $userApproved = true;
                                }
                        } catch (Throwable $e) {
                            // ignore - column may not exist
                        }

                    $isOfficer = $userRole && strtolower($userRole['role']) === 'officer';
                    $isUser = $userRole && strtolower($userRole['role']) === 'user';
            
            // Show Modules section only for non-Officer users
            if (!$isOfficer):
        ?>
        <div class="nav-section">
            <div class="nav-section-title" onclick="toggleNavSection(this)" data-section="modules">
                <span><?php echo LanguageManager::translate('modules'); ?></span>
                <i class="bi bi-chevron-down nav-chevron"></i>
            </div>
            <div class="nav-section-content">
                <?php if (!$isUser): ?>

                <?php endif; ?>
                <?php if (!$userApproved): ?>
                <a class="nav-link disabled" title="Account not approved by admin">
                    <i class="bi bi-lock-fill"></i> <span><?php echo LanguageManager::translate('blotter'); ?></span>
                </a>
                <?php else: ?>
                <a href="<?php echo $performance_path; ?>" class="nav-link <?php echo ($current_page=='performance')?'active':''; ?>" onclick="closeSidebar()">
                    <i class="bi bi-journal-text"></i> <span><?php echo LanguageManager::translate('blotter'); ?></span>
                </a>
                <?php endif; ?>
                <?php if ($userRole && strtolower($userRole['role']) === 'user'): ?>
                <a href="<?php echo $my_reports_path; ?>" class="nav-link <?php echo ($current_page=='recruitment')?'active':''; ?>" onclick="closeSidebar()">
                    <i class="bi bi-briefcase"></i> <span>My Reports</span>
                </a>
                <?php else: ?>
                <a href="<?php echo $recruitment_path; ?>" class="nav-link <?php echo ($current_page=='recruitment')?'active':''; ?>" onclick="closeSidebar()">
                    <i class="bi bi-briefcase"></i> <span><?php echo LanguageManager::translate('incident_report'); ?></span>
                </a>
                <?php endif; ?>
                <?php if (!$isUser): ?>
                <a href="<?php echo $recognition_path; ?>" class="nav-link <?php echo ($current_page=='recognition')?'active':''; ?>" onclick="closeSidebar()">
                    <i class="bi bi-eye"></i> <span><?php echo LanguageManager::translate('case_management'); ?></span>
                </a>
                <?php if ($userRole && strtolower($userRole['role']) === 'admin'): ?>
                <a href="<?php echo $base_url; ?>modules/evidence_collection.php" class="nav-link <?php echo ($current_page=='evidence_collection')?'active':''; ?>" onclick="closeSidebar()">
                    <i class="bi bi-file-earmark-lock"></i> <span>Evidence Collection</span>
                </a>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($isOfficer): ?>
        <div class="nav-section">
            <div class="nav-section-title"><span>Officer Tools</span><i class="bi bi-chevron-down nav-chevron"></i></div>
            <a href="<?php echo $base_url; ?>officer/manage_staff.php" class="nav-link <?php echo ($current_page=='manage_staff')?'active':''; ?>" onclick="closeSidebar()">
                <i class="bi bi-person-badge"></i> <span>Manage Staff</span>
            </a>
        </div>
        <?php endif; endif; ?>
    </nav>
    <?php if (isset($_SESSION['user_id'])): ?>
    <div class="sidebar-user">
        <div class="user-avatar"><i class="bi bi-person"></i></div>
        <div class="user-info"><?php echo htmlspecialchars($_SESSION['first_name'] ?? 'User'); ?></div>
        <div class="user-role"><?php echo htmlspecialchars($_SESSION['fullname'] ?? 'Guest'); ?></div>

        <div class="mt-2">
            <a href="<?php echo $base_url; ?>auth/logout.php" class="btn btn-sm btn-outline" onclick="closeSidebar()">
                <i class="bi bi-box-arrow-right"></i> <?php echo LanguageManager::translate('logout'); ?>
            </a>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
    // Toggle sidebar visibility (mobile)
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        sidebar.classList.toggle('show');
        if (overlay) overlay.classList.toggle('show');
    }
    
    // Close sidebar (mobile)
    function closeSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        sidebar.classList.remove('show');
        if (overlay) overlay.classList.remove('show');
    }
    
    // Toggle collapse/expand (desktop)
    function toggleCollapse() {
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.querySelector('.main-content');
        const expandBtn = document.getElementById('expandBtn');
        
        sidebar.classList.toggle('collapsed');
        if (mainContent) {
            mainContent.classList.toggle('sidebar-collapsed');
        }
        
        // Show/hide expand button
        if (sidebar.classList.contains('collapsed')) {
            if (expandBtn) expandBtn.style.display = 'flex';
        } else {
            if (expandBtn) expandBtn.style.display = 'none';
        }
        
        // Save preference to localStorage
        localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
    }
    
    // Restore sidebar collapse state on page load
    document.addEventListener('DOMContentLoaded', function() {
        const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        if (isCollapsed) {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.querySelector('.main-content');
            const expandBtn = document.getElementById('expandBtn');
            
            sidebar.classList.add('collapsed');
            if (mainContent) {
                mainContent.classList.add('sidebar-collapsed');
            }
            if (expandBtn) {
                expandBtn.style.display = 'flex';
            }
        }

        // Restore nav section states
        const navTitles = document.querySelectorAll('.nav-section-title');
        navTitles.forEach(title => {
            const sectionName = title.getAttribute('data-section');
            const isHidden = localStorage.getItem('navSection_' + sectionName) === 'true';
            if (isHidden) {
                const content = title.nextElementSibling;
                const chevron = title.querySelector('.nav-chevron');
                content.classList.add('hidden');
                chevron.classList.add('rotated');
            }
        });
    });
    
    // Close sidebar on escape key
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeSidebar();
        }
    });
    
    // Close sidebar when clicking on a nav link (but only on mobile)
    document.addEventListener('click', function(event) {
        const sidebar = document.getElementById('sidebar');
        const toggle = document.getElementById('menuToggle');
        
        // Only close on mobile devices
        if (window.innerWidth <= 768) {
            if (event.target.closest('.nav-link') && sidebar.classList.contains('show')) {
                closeSidebar();
            }
        }
    });
</script>