
<?php
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
require_once __DIR__ . '/../config/LanguageManager.php';

$current_page = strtolower(basename($_SERVER['PHP_SELF'], '.php'));
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
$dashboard_path = $root_path . 'admin/dashboard.php';
$admin_panel_path = $root_path . 'admin/dashboard.php';
$analytics_dashboard_path = $root_path . 'admin/analytics_dashboard.php';
$reports_path = $root_path . 'admin/dashboard.php';
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
$crime_mapping_path = $root_path . 'modules/crime_mapping.php';
?>

<!-- Mobile Menu Toggle Button -->
<button class="mobile-menu-toggle fixed left-4 top-4 z-[9999] flex h-11 w-11 items-center justify-center rounded-lg border border-white/80 bg-[var(--main-color)] text-white shadow-lg transition duration-200 hover:scale-105 md:hidden" id="menuToggle" onclick="toggleSidebar()" aria-label="Toggle navigation">
    <i class="bi bi-list text-lg"></i>
</button>

<!-- Expand Navigation Button (when sidebar is collapsed on desktop) -->
<button class="navbar-expand-btn fixed left-4 top-4 z-[9999] hidden h-11 w-11 items-center justify-center rounded-lg border border-white/80 bg-[var(--main-color)] text-white shadow-lg transition duration-200 hover:scale-105 md:flex" id="expandBtn" onclick="toggleCollapse()" title="Show Navigation" aria-label="Show Navigation">
    <i class="bi bi-chevron-right"></i>
</button>

<!-- Hover trigger zone for desktop sidebar -->
<div class="sidebar-hover-zone" id="sidebarHoverZone" aria-hidden="true"></div>

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
    <button class="sidebar-close-btn absolute right-3 top-3 flex h-10 w-10 items-center justify-center rounded-full border border-white/20 bg-white/10 text-white transition hover:bg-white/20 md:hidden" id="closeBtn" onclick="closeSidebar()" aria-label="Close navigation">
        <i class="bi bi-x"></i>
    </button>
    <button class="sidebar-collapse-btn absolute right-3 top-14 hidden h-10 w-10 items-center justify-center rounded-full border border-white/20 bg-white/10 text-white transition hover:bg-white/20 md:flex" id="collapseBtn" onclick="toggleCollapse()" title="Hide Navigation" aria-label="Hide navigation">
        <i class="bi bi-chevron-left"></i>
    </button>
    
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <img src="<?php echo $base_url; ?>assets/css/tara.png" alt="Logo" class="sidebar-logo-image">
        </div>
        <div class="sidebar-title">Alertara</div>
        <div class="sidebar-subtitle">Law Enforcemet & Incident Report</div>
        <button id="themeToggleBtn" class="theme-toggle-btn inline-flex w-full items-center justify-center gap-2 rounded-full border border-white/20 bg-white/10 px-3 py-2 text-sm font-medium text-white transition hover:bg-white/20" type="button" onclick="toggleTheme()" aria-label="Toggle dark mode">
            <i class="bi bi-moon-fill"></i>
            <span id="themeToggleLabel">Dark Mode</span>
        </button>
        
        <!-- Language Selector -->
        <div class="mt-4 border-y border-white/20 py-3">
            <form id="languageForm" method="POST" action="<?php echo $base_url; ?>config/LanguageManager.php" class="m-0">
                <select name="set_language" id="langSelect" onchange="changeLanguage()" class="w-full cursor-pointer rounded-lg border border-white/20 bg-slate-800/80 px-3 py-2 text-sm text-white outline-none ring-0 focus:border-cyan-400"
>
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
                <a href="<?php echo $base_url; ?>admin/account_approvals.php" class="inline-flex items-center justify-center rounded-full border border-white/40 px-3 py-1.5 text-sm font-medium text-white transition hover:bg-white/10">Account Approvals</a>
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
            // Check user role and approval status
            require_once __DIR__ . '/../config/db_connect.php';
            // Note: some installations may not have a `banned` column. Select only commonly present columns
            $roleCheck = $pdo->prepare("SELECT role, email_verified, admin_approved FROM signup WHERE user_id = ?");
            $userRole = [];
            $userApproved = true;
            try {
                $roleCheck->execute([$_SESSION['user_id']]);
                $userRole = $roleCheck->fetch(PDO::FETCH_ASSOC) ?: [];
                $userApproved = !empty($userRole['admin_approved']) && (int)$userRole['admin_approved'] === 1;
                // Some schemas may include a `banned` column; if not present, fall back to false
                $userBanned = !empty($userRole['banned'] ?? false);
            } catch (Exception $e) {
                $userApproved = true;
                $userBanned = false;
            }
            // Ensure $userBanned is defined even if query/exception happened
            $userBanned = $userBanned ?? false;
            $isOfficer = $userRole && strtolower($userRole['role'] ?? '') === 'officer';
            $isUser = $userRole && strtolower($userRole['role']) === 'user';
            $needsApproval = !$userBanned && !($userRole && strtolower($userRole['role']) === 'admin') && !$userApproved;
            
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
                <?php if (!empty($userBanned) || $needsApproval): ?>
                <a class="nav-link disabled" title="Access locked">
                    <i class="bi bi-lock-fill"></i> <span><?php echo LanguageManager::translate('blotter'); ?></span>
                </a>
                <?php else: ?>
                <a href="<?php echo $performance_path; ?>" class="nav-link <?php echo ($current_page=='performance')?'active':''; ?>" onclick="closeSidebar()">
                    <i class="bi bi-journal-text"></i> <span><?php echo LanguageManager::translate('blotter'); ?></span>
                </a>
                <?php endif; ?>
                <?php if ($userRole && strtolower($userRole['role']) === 'user'): ?>
                    <?php if (!empty($userBanned) || $needsApproval): ?>
                    <a class="nav-link disabled" title="Access locked">
                        <i class="bi bi-briefcase"></i> <span>My Reports</span>
                    </a>
                    <?php else: ?>
                    <a href="<?php echo $my_reports_path; ?>" class="nav-link <?php echo ($current_page=='recruitment')?'active':''; ?>" onclick="closeSidebar()">
                        <i class="bi bi-briefcase"></i> <span>My Reports</span>
                    </a>
                    <?php endif; ?>
                <?php else: ?>
                    <?php if (!empty($userBanned) || $needsApproval): ?>
                    <a class="nav-link disabled" title="Access locked">
                        <i class="bi bi-briefcase"></i> <span><?php echo LanguageManager::translate('incident_report'); ?></span>
                    </a>
                    <?php else: ?>
                    <a href="<?php echo $recruitment_path; ?>" class="nav-link <?php echo ($current_page=='recruitment')?'active':''; ?>" onclick="closeSidebar()">
                        <i class="bi bi-briefcase"></i> <span><?php echo LanguageManager::translate('incident_report'); ?></span>
                    </a>
                    <?php endif; ?>
                <?php endif; ?>
                <?php if (!$isUser): ?>
                <a href="<?php echo $recognition_path; ?>" class="nav-link <?php echo ($current_page=='recognition')?'active':''; ?>" onclick="closeSidebar()">
                    <i class="bi bi-eye"></i> <span><?php echo LanguageManager::translate('case_management'); ?></span>
                </a>
                <?php if (!empty($userBanned) || $needsApproval): ?>
                <a class="nav-link disabled" title="Access locked">
                    <i class="bi bi-file-earmark-text"></i> <span>Request Form</span>
                </a>
                <?php else: ?>
                <a href="<?php echo $base_url; ?>modules/Request_form.php" class="nav-link <?php echo ($current_page=='request_form')?'active':''; ?>" onclick="closeSidebar()">
                    <i class="bi bi-file-earmark-text"></i> <span>Request Form</span>
                </a>
                <?php endif; ?>
                <?php if ($userRole && strtolower($userRole['role']) === 'admin'): ?>
                <a href="<?php echo $base_url; ?>modules/evidence_collection.php" class="nav-link <?php echo ($current_page=='evidence_collection')?'active':''; ?>" onclick="closeSidebar()">
                    <i class="bi bi-file-earmark-lock"></i> <span>Evidence Collection</span>
                </a>
                <a href="<?php echo $base_url; ?>modules/crime_mapping.php" class="nav-link <?php echo ($current_page=='crime_mapping')?'active':''; ?>" onclick="closeSidebar()">
                    <i class="bi bi-map"></i> <span>Crime Mapping</span>
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
        <?php endif; ?>
        <?php endif; ?>
    </nav>
    <?php if (isset($_SESSION['user_id'])): ?>
    <div class="sidebar-user">
        <div class="user-avatar"><i class="bi bi-person"></i></div>
        <div class="user-info"><?php echo htmlspecialchars($_SESSION['first_name'] ?? 'User'); ?></div>
        <div class="user-role"><?php echo htmlspecialchars($_SESSION['fullname'] ?? 'Guest'); ?></div>

        <div class="mt-3 flex flex-wrap gap-2">
            <a href="<?php echo $base_url; ?>index.php" class="inline-flex items-center gap-2 rounded-full border border-white/40 px-3 py-1.5 text-sm text-white transition hover:bg-white/10" onclick="closeSidebar()">
                <i class="bi bi-house"></i> <?php echo LanguageManager::translate('back'); ?>
            </a>
            <a href="<?php echo $base_url; ?>auth/logout.php" class="inline-flex items-center gap-2 rounded-full border border-white/40 px-3 py-1.5 text-sm text-white transition hover:bg-white/10" onclick="closeSidebar()">
                <i class="bi bi-box-arrow-right"></i> <?php echo LanguageManager::translate('logout'); ?>
            </a>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
    function setSidebarHoverState(isActive) {
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.querySelector('.main-content');
        if (!sidebar || window.innerWidth <= 768) return;

        sidebar.classList.toggle('hovered', isActive);
        if (mainContent) {
            mainContent.classList.toggle('sidebar-hover-active', isActive);
        }
    }

    function attachSidebarHoverBehavior() {
        const sidebar = document.getElementById('sidebar');
        const hoverZone = document.getElementById('sidebarHoverZone');
        const mainContent = document.querySelector('.main-content');
        if (!sidebar || window.innerWidth <= 768) return;

        const openSidebar = () => setSidebarHoverState(true);
        const closeSidebar = () => setSidebarHoverState(false);

        if (hoverZone) {
            hoverZone.addEventListener('mouseenter', openSidebar);
            hoverZone.addEventListener('mouseleave', closeSidebar);
        }

        sidebar.addEventListener('mouseenter', openSidebar);
        sidebar.addEventListener('mouseleave', closeSidebar);

        if (mainContent) {
            mainContent.addEventListener('mouseenter', closeSidebar);
        }
    }

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
        
        const isCollapsed = sidebar.classList.contains('collapsed');
        if (isCollapsed) {
            sidebar.classList.remove('collapsed');
            sidebar.classList.remove('hovered');
            if (mainContent) {
                mainContent.classList.remove('sidebar-collapsed');
                mainContent.classList.remove('sidebar-hover-active');
            }
            if (expandBtn) expandBtn.style.display = 'none';
        } else {
            sidebar.classList.add('collapsed');
            sidebar.classList.remove('hovered');
            if (mainContent) {
                mainContent.classList.add('sidebar-collapsed');
                mainContent.classList.remove('sidebar-hover-active');
            }
            if (expandBtn) expandBtn.style.display = 'flex';
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
            sidebar.classList.remove('hovered');
            if (mainContent) {
                mainContent.classList.add('sidebar-collapsed');
                mainContent.classList.remove('sidebar-hover-active');
            }
            if (expandBtn) {
                expandBtn.style.display = 'flex';
            }
        }

        attachSidebarHoverBehavior();

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

        // Restore theme preference
        const savedTheme = localStorage.getItem('alertaraTheme');
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        const initialTheme = savedTheme || (prefersDark ? 'dark' : 'light');
        applyTheme(initialTheme);

        const themeToggleBtn = document.getElementById('themeToggleBtn');
        if (themeToggleBtn) {
            themeToggleBtn.addEventListener('click', toggleTheme);
        }
    });

    function toggleTheme() {
        const isDark = document.body.classList.contains('dark-mode');
        applyTheme(isDark ? 'light' : 'dark');
    }

    function applyTheme(theme) {
        const root = document.documentElement;
        root.setAttribute('data-theme', theme);
        root.classList.toggle('dark-mode', theme === 'dark');
        root.classList.toggle('light-mode', theme === 'light');

        const body = document.body;
        if (body) {
            body.classList.toggle('dark-mode', theme === 'dark');
            body.classList.toggle('light-mode', theme === 'light');
        }

        localStorage.setItem('alertaraTheme', theme);
        updateThemeToggleButton(theme);
    }

    function updateThemeToggleButton(theme) {
        const toggleLabel = document.getElementById('themeToggleLabel');
        const toggleIcon = document.querySelector('#themeToggleBtn i');
        const themeToggleBtn = document.getElementById('themeToggleBtn');

        if (toggleLabel) {
            toggleLabel.textContent = theme === 'dark' ? 'Light Mode' : 'Dark Mode';
        }

        if (toggleIcon) {
            toggleIcon.className = theme === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-fill';
        }

        if (themeToggleBtn) {
            themeToggleBtn.setAttribute('aria-label', theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode');
        }
    }

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