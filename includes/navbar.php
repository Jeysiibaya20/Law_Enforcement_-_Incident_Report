<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/LanguageManager.php';

$current_page = strtolower(basename($_SERVER['PHP_SELF'], '.php'));

$script_dir = dirname($_SERVER['SCRIPT_NAME']);
$in_subfolder = (strpos($script_dir, '/modules') !== false) || (strpos($script_dir, '/admin') !== false);
$base_url = $in_subfolder ? '../' : '';
$role = strtolower(trim($_SESSION['role'] ?? 'user'));
$is_admin = $role === 'admin';
$is_officer = $role === 'officer';
$is_user = !$is_admin && !$is_officer;

$dashboard_path = $is_admin ? $base_url . 'admin/dashboard.php' : $base_url . 'modules/my_reports.php';
$my_reports_path = $base_url . 'modules/my_reports.php';
$watch_path = $base_url . 'modules/Blotter.php';
$cctv_path = $base_url . 'modules/crime_mapping.php';
$request_path = $base_url . 'modules/Request_form.php';
$complaint_path = $base_url . 'modules/Incident_report.php';
$complaint_path = $base_url . 'modules/Request_form.php';
$patrol_path = $base_url . 'modules/succession.php';
$awareness_path = $base_url . 'modules/learning.php';
$user_management_path = $base_url . 'admin/users.php';
$logout_path = $base_url . 'auth/logout.php';

$display_name = htmlspecialchars($_SESSION['first_name'] ?? 'Officer');
$display_role = htmlspecialchars($role === 'admin' ? 'Admin' : ($role === 'officer' ? 'Officer' : 'User'));
?>

<button class="alertara-mobile-toggle" id="alertaraMobileToggle" aria-label="Open navigation">
    <i class="bi bi-list"></i>
</button>

<aside class="alertara-navbar collapsed" id="alertaraNavbar" aria-label="Primary navigation">
    <div class="alertara-navbar-inner">
        <div class="alertara-brand">
            <div class="alertara-brand-icon">
                <img src="<?php echo $base_url; ?>assets/css/tara.png" alt="Alertara logo">
            </div>
            <div class="alertara-brand-copy">
                <span class="brand-name">Alertara</span>
                <span class="brand-note">Incident Reporting</span>
                <span class="role-badge"><?php echo ucfirst($display_role); ?></span>
            </div>
        </div>

        <div class="alertara-menu">
            <a href="<?php echo $dashboard_path; ?>" class="alertara-link<?php echo in_array($current_page, ['reports','dashboard','index','my_reports']) ? ' active' : ''; ?>">
                <span class="link-icon"><i class="bi bi-house-fill"></i></span>
                <span class="link-text"><?php echo $is_admin ? LanguageManager::translate('dashboard') : 'My Reports'; ?></span>
            </a>

            <?php if ($is_user): ?>
                <a href="<?php echo $watch_path; ?>" class="alertara-link<?php echo in_array($current_page, ['blotter','blotter_create','blotter_update','blotter_view']) ? ' active' : ''; ?>">
                    <span class="link-icon"><i class="bi bi-journal-text"></i></span>
                    <span class="link-text">Neighborhood Watch</span>
                </a>
            <?php elseif ($is_officer): ?>
                <a href="<?php echo $watch_path; ?>" class="alertara-link<?php echo in_array($current_page, ['blotter','blotter_create','blotter_update','blotter_view']) ? ' active' : ''; ?>">
                    <span class="link-icon"><i class="bi bi-journal-text"></i></span>
                    <span class="link-text">Neighborhood Watch</span>
                </a>

                <a href="<?php echo $cctv_path; ?>" class="alertara-link<?php echo in_array($current_page, ['crime_mapping']) ? ' active' : ''; ?>">
                    <span class="link-icon"><i class="bi bi-camera-video"></i></span>
                    <span class="link-text">CCTV Surveillance</span>
                </a>

                <a href="<?php echo $request_path; ?>" class="alertara-link<?php echo $current_page === 'request_form' ? ' active' : ''; ?>">
                    <span class="link-icon"><i class="bi bi-card-checklist"></i></span>
                    <span class="link-text">CCTV Request</span>
                </a>

                <a href="<?php echo $complaint_path; ?>" class="alertara-link<?php echo $current_page === 'incident_report' ? ' active' : ''; ?>">
                    <span class="link-icon"><i class="bi bi-file-earmark-text"></i></span>
                    <span class="link-text">Complaint Logging</span>
                </a>

                <a href="<?php echo $patrol_path; ?>" class="alertara-link<?php echo $current_page === 'succession' ? ' active' : ''; ?>">
                    <span class="link-icon"><i class="bi bi-person-lines-fill"></i></span>
                    <span class="link-text">Patrol Scheduling</span>
                </a>

                <a href="<?php echo $awareness_path; ?>" class="alertara-link<?php echo $current_page === 'learning' ? ' active' : ''; ?>">
                    <span class="link-icon"><i class="bi bi-lightbulb-fill"></i></span>
                    <span class="link-text">Awareness & Events</span>
                </a>
            <?php endif; ?>

            <?php if ($is_admin): ?>

                
<div class="alertara-group">
    <button class="alertara-link alertara-group-toggle<?php echo in_array($current_page, ['blotters','certificate_of_file_action','blotter','blotter_create']) ? ' active' : ''; ?>" data-group="blotter-menu">
        <span class="link-icon"><i class="bi bi-journal-text"></i></span>
        <span class="link-text">Digital Blotter</span>
        <span class="link-caret"><i class="bi bi-caret-down-fill"></i></span>
    </button>
    <div class="alertara-group-panel" data-group="blotter-menu">
        <a href="<?php echo $base_url; ?>admin/blotters.php" class="alertara-link<?php echo $current_page === 'blotters' ? ' active' : ''; ?>">
            <span class="link-icon"><i class="bi bi-list-task"></i></span>
            <span class="link-text">Manage Blotters</span>
        </a>
        <a href="<?php echo $base_url; ?>admin/certificate_of_file_action.php" class="alertara-link<?php echo $current_page === 'certificate_of_file_action' ? ' active' : ''; ?>">
            <span class="link-icon"><i class="bi bi-file-earmark-text"></i></span>
            <span class="link-text">Certificate of File Action</span>
        </a>
        <?php if ($is_admin): ?>
        <a href="<?php echo $user_management_path; ?>" class="alertara-link<?php echo in_array($current_page, ['users','account_approvals']) ? ' active' : ''; ?>">
            <span class="link-icon"><i class="bi bi-people-fill"></i></span>
            <span class="link-text">User Management</span>
        </a>
        <?php endif; ?>
    </div>
</div>


                <?php endif; ?>

                <a href="<?php echo $base_url; ?>admin/cases.php" class="alertara-link<?php echo $current_page === 'cases' ? ' active' : ''; ?>">
                    <span class="link-icon"><i class="bi bi-clipboard-data"></i></span>
                    <span class="link-text">Case Management</span>
                </a>

                <a href="<?php echo $base_url; ?>modules/incident_report.php" class="alertara-link<?php echo $current_page === 'incident_report' ? ' active' : ''; ?>">
                    <span class="link-icon"><i class="bi bi-file-earmark-bar-graph"></i></span>
                    <span class="link-text">Generate Report</span>
                </a>

                <!-- User Management moved into Digital Blotter dropdown -->

                <a href="<?php echo $base_url; ?>modules/Request_form.php" class="alertara-link<?php echo $current_page === 'request_form' ? ' active' : ''; ?>">
                    <span class="link-icon"><i class="bi bi-envelope"></i></span>
                    <span class="link-text">Request Form</span>
                </a>

                <a href="<?php echo $base_url; ?>admin/settings.php" class="alertara-link<?php echo $current_page === 'settings' ? ' active' : ''; ?>">
                    <span class="link-icon"><i class="bi bi-gear-fill"></i></span>
                    <span class="link-text">Settings</span>
                </a>

        </div>

        <div class="alertara-footer">
            <div class="alertara-user">
                <span class="user-avatar"><i class="bi bi-person-fill"></i></span>
                <div class="user-copy">
                    <span class="user-name"><?php echo $display_name; ?></span>
                    <span class="user-role"><?php echo $display_role; ?></span>
                </div>
            </div>
                <div style="margin-top:.5rem; display:flex; gap:.5rem; align-items:center;">
                    <button id="themeToggle" class="theme-toggle" title="Toggle theme">
                        <span class="icon" id="themeIcon"><i class="bi bi-moon-fill"></i></span>
                        <span class="label" id="themeLabel">Dark</span>
                    </button>
                </div>
                <a href="<?php echo $logout_path; ?>" class="alertara-link alertara-link-logout">
                    <span class="link-icon"><i class="bi bi-box-arrow-right"></i></span>
                    <span class="link-text">Logout</span>
                </a>
        </div>
    </div>
</aside>

<div class="alertara-hover-edge" id="alertaraHoverEdge" aria-hidden="true"></div>
<div class="alertara-backdrop" id="alertaraBackdrop"></div>

<script>
(function() {
    const navbar = document.getElementById('alertaraNavbar');
    const edge = document.getElementById('alertaraHoverEdge');
    const backdrop = document.getElementById('alertaraBackdrop');
    const mobileToggle = document.getElementById('alertaraMobileToggle');
    const groupButtons = Array.from(document.querySelectorAll('.alertara-group-toggle'));

    // Theme toggle handling
    (function() {
        const toggle = document.getElementById('themeToggle');
        const icon = document.getElementById('themeIcon');
        const label = document.getElementById('themeLabel');

        function setTheme(t) {
            document.documentElement.setAttribute('data-theme', t);
            document.body.classList.remove('light-mode', 'dark-mode');
            document.body.classList.add(t + '-mode');
            localStorage.setItem('alertaraTheme', t);
            if (t === 'dark') {
                icon.innerHTML = '<i class="bi bi-sun-fill"></i>';
                label.textContent = 'Dark';
            } else {
                icon.innerHTML = '<i class="bi bi-moon-fill"></i>';
                label.textContent = 'Light';
            }
        }

        // Initialize UI to current theme
        const current = localStorage.getItem('alertaraTheme') || (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
        setTheme(current);

        if (toggle) {
            toggle.addEventListener('click', function() {
                const next = (document.documentElement.getAttribute('data-theme') === 'dark') ? 'light' : 'dark';
                setTheme(next);
            });
        }
    })();

    const isDesktop = () => window.innerWidth > 992;
    const openDesktop = () => {
        navbar.classList.add('expanded');
        navbar.classList.remove('collapsed');
    };
    const closeDesktop = () => {
        navbar.classList.remove('expanded');
        navbar.classList.add('collapsed');
        document.querySelectorAll('.alertara-group.active').forEach(group => group.classList.remove('active'));
        document.querySelectorAll('.alertara-group-toggle.active').forEach(button => button.classList.remove('active'));
    };
    const hideMobile = () => {
        navbar.classList.remove('mobile-open');
        backdrop.classList.remove('visible');
    };
    const showMobile = () => {
        navbar.classList.add('mobile-open');
        backdrop.classList.add('visible');
    };
    const updateEdge = () => {
        if (!edge) return;
        if (isDesktop()) {
            edge.classList.add('visible');
        } else {
            edge.classList.remove('visible');
            closeDesktop();
        }
    };

    if (edge) {
        edge.addEventListener('mouseenter', openDesktop);
    }
    if (navbar) {
        navbar.addEventListener('mouseleave', () => {
            if (isDesktop()) closeDesktop();
        });
        navbar.addEventListener('mouseenter', () => {
            if (isDesktop()) openDesktop();
        });
    }

    if (mobileToggle) {
        mobileToggle.addEventListener('click', () => {
            if (navbar.classList.contains('mobile-open')) {
                hideMobile();
            } else {
                showMobile();
            }
        });
    }

    if (backdrop) {
        backdrop.addEventListener('click', hideMobile);
    }

    document.addEventListener('click', function(event) {
        if (!event.target.closest('.alertara-navbar') && !event.target.closest('#alertaraMobileToggle')) {
            if (!isDesktop()) {
                hideMobile();
            }
        }
    });

    groupButtons.forEach(button => {
        const group = button.closest('.alertara-group');
        const panel = group ? group.querySelector('.alertara-group-panel') : null;

        button.addEventListener('click', function() {
            const isOpen = this.classList.toggle('active');
            if (group) {
                group.classList.toggle('active', isOpen);
            }
            if (panel) {
                panel.classList.toggle('active', isOpen);
            }
        });

        if (group) {
            group.addEventListener('mouseenter', () => {
                if (isDesktop()) {
                    button.classList.add('active');
                    group.classList.add('active');
                    if (panel) panel.classList.add('active');
                }
            });
            group.addEventListener('mouseleave', () => {
                if (isDesktop()) {
                    button.classList.remove('active');
                    group.classList.remove('active');
                    if (panel) panel.classList.remove('active');
                }
            });
        }
    });

    window.addEventListener('resize', updateEdge);
    updateEdge();
})();
</script>
