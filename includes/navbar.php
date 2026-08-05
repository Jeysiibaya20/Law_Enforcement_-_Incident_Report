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

<style>
    :root {
        --alertara-dropdown-bg: rgba(247, 241, 241, 0.97);
        --alertara-dropdown-hover: rgba(245, 245, 243, 0.97);
        --alertara-dropdown-active: #eceef1;
        --alertara-dropdown-text: #f8fafc;
        --alertara-dropdown-border: rgb(0, 0, 0);
    }

    .alertara-group {
        margin: 0.25rem 0;
    }

    .alertara-group-toggle {
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0.8rem 0.95rem;
        border: 1px solid transparent;
        border-radius: 0.75rem;
        background: transparent;
        color: #ffffff;
        transition: all 0.2s ease;
    }

    .alertara-group-toggle:hover,
    .alertara-group-toggle.active {
        background: transparent !important;
        border-color: transparent;
        color: #ffffff !important;
    }

    .alertara-group-toggle.active {
        box-shadow: inset 0 0 0 1px rgba(248, 246, 246, 0.92);
    }

    .alertara-group-panel {
        display: none;
        margin: 0.25rem 0 0.35rem 0.9rem;
        padding: 0.25rem 0;
        border-left: 2px solid var(--alertara-dropdown-border);
    }

    .alertara-group-panel.show {
        display: block;
    }

    .alertara-group-panel .alertara-link {
        margin: 0.2rem 0;
        padding: 0.7rem 0.85rem;
        border-radius: 0.6rem;
        color: #ffffff;
        background: transparent;
    }

    .alertara-group-panel .alertara-link:hover,
    .alertara-group-panel .alertara-link.active {
        background: transparent !important;
        color: #ffffff !important;
    }

    .alertara-link:hover,
    .alertara-link.active {
        background: transparent !important;
        color: #000000 !important;
    }

    .alertara-link .link-text,
    .alertara-group-toggle .link-text,
    .alertara-group-panel .alertara-link .link-text {
        color: #0c0101 !important;
    }
</style>

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

               <a href="<?php echo $base_url; ?>admin/dashboard.php" class="alertara-link<?php echo $current_page === 'settings' ? ' active' : ''; ?>">
                    <span class="link-icon"><i class="bi bi-gear-fill"></i></span>
                    <span class="link-text">Dashboard</span>
                </a>
                
                <a href="<?php echo $watch_path; ?>" class="alertara-link<?php echo in_array($current_page, ['blotter','blotter_create','blotter_update','blotter_view']) ? ' active' : ''; ?>">
                    <span class="link-icon"><i class="bi bi-journal-text"></i></span>
                    <span class="link-text">Blotter</span>
                </a>
            <?php elseif ($is_officer): ?>
                <a href="<?php echo $watch_path; ?>" class="alertara-link<?php echo in_array($current_page, ['blotter','blotter_create','blotter_update','blotter_view']) ? ' active' : ''; ?>">
                    <span class="link-icon"><i class="bi bi-journal-text"></i></span>
                    <span class="link-text">Blotter</span>
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

                
               <div class="alertara-group<?php echo in_array($current_page, ['blotters','certificate_of_file_action','blotter','blotter_create']) ? ' active' : ''; ?>">
    <button class="alertara-link alertara-group-toggle<?php echo in_array($current_page, ['blotters','certificate_of_file_action','blotter','blotter_create']) ? ' active' : ''; ?>"
            type="button"
            aria-expanded="<?php echo in_array($current_page, ['blotters','certificate_of_file_action','blotter','blotter_create']) ? 'true' : 'false'; ?>"
            aria-controls="blotter-menu-panel"
            data-group="blotter-menu">
        <span class="link-icon"><i class="bi bi-journal-text"></i></span>
        <span class="link-text">Digital Blotter</span>
        <span class="link-caret"><i class="bi bi-caret-down-fill"></i></span>
    </button>
    <div class="alertara-group-panel<?php echo in_array($current_page, ['blotters','certificate_of_file_action','blotter','blotter_create']) ? ' show' : ''; ?>" id="blotter-menu-panel" data-group="blotter-menu">
        <a href="<?php echo $base_url; ?>admin/blotters.php" class="alertara-link<?php echo $current_page === 'blotters' ? ' active' : ''; ?>">
            <span class="link-icon"><i class="bi bi-list-task"></i></span>
            <span class="link-text">Blotters</span>
        </a>
        <a href="<?php echo $base_url; ?>admin/certificate_of_file_action.php" class="alertara-link<?php echo $current_page === 'certificate_of_file_action' ? ' active' : ''; ?>">
            <span class="link-icon"><i class="bi bi-file-earmark-text"></i></span>
            <span class="link-text">Certificate of File Action</span>
        </a>
    </div>
</div>
<!-- Case Management -->
<div class="alertara-group<?php echo in_array($current_page, ['cases','summons','hearing_schedule','hearing_result','settlement','close_cases']) ? ' active' : ''; ?>">
    <button class="alertara-link alertara-group-toggle<?php echo in_array($current_page, ['cases','summons','hearing_schedule','hearing_result','settlement','close_cases']) ? ' active' : ''; ?>"
            type="button"
            aria-expanded="<?php echo in_array($current_page, ['cases','summons','hearing_schedule','hearing_result','settlement','close_cases']) ? 'true' : 'false'; ?>"
            aria-controls="case-menu-panel"
            data-group="case-menu">
        <span class="link-icon"><i class="bi bi-clipboard-data"></i></span>
        <span class="link-text">Case Management</span>
        <span class="link-caret"><i class="bi bi-caret-down-fill"></i></span>
    </button>
    <div class="alertara-group-panel<?php echo in_array($current_page, ['cases','summons','hearing_schedule','hearing_result','settlement','close_cases']) ? ' show' : ''; ?>" id="case-menu-panel" data-group="case-menu">
        <a href="<?php echo $base_url; ?>admin/Summons.php" class="alertara-link<?php echo $current_page === 'summons' ? ' active' : ''; ?>">
            <span class="link-icon"><i class="bi bi-list-task"></i></span>
            <span class="link-text">Summons</span>
        </a>
        <a href="<?php echo $base_url; ?>admin/Hearing_schedule.php" class="alertara-link<?php echo $current_page === 'hearing_schedule' ? ' active' : ''; ?>">
            <span class="link-icon"><i class="bi bi-calendar-event"></i></span>
            <span class="link-text">Hearing Schedule</span>
        </a>
        <a href="<?php echo $base_url; ?>admin/hearing_result.php" class="alertara-link<?php echo $current_page === 'hearing_result' ? ' active' : ''; ?>">
            <span class="link-icon"><i class="bi bi-clipboard-check"></i></span>
            <span class="link-text">Hearing Result</span>
        </a>
        <a href="<?php echo $base_url; ?>admin/settle.php" class="alertara-link<?php echo $current_page === 'settlement' ? ' active' : ''; ?>">
            <span class="link-icon"><i class="bi bi-hand-thumbs-up"></i></span>
            <span class="link-text">Settlement</span>
        </a>
        <a href="<?php echo $base_url; ?>admin/cases.php" class="alertara-link<?php echo $current_page === 'close_cases' ? ' active' : ''; ?>">
            <span class="link-icon"><i class="bi bi-x-circle"></i></span>
            <span class="link-text">Close Cases</span>
        </a>
    </div>
            </div>
                        <a href="<?php echo $base_url; ?>admin/suspects&witnesses.php" class="alertara-link<?php echo $current_page === 'suspects&witnesses' ? ' active' : ''; ?>">
            <span class="link-icon"><i class="bi bi-person-lines-fill"></i></span>
            <span class="link-text">Suspects & Witnesses</span>
             </a>

                </a>
                 <a href="<?php echo $base_url; ?>modules/evidence_collection.php" class="alertara-link<?php echo $current_page === 'suspects&witnesses' ? ' active' : ''; ?>">
            <span class="link-icon"><i class="bi bi-person-lines-fill"></i></span>
            <span class="link-text">Evidence Collection</span>
                </a>

                <a href="<?php echo $base_url; ?>modules/incident_report.php" class="alertara-link<?php echo $current_page === 'incident_report' ? ' active' : ''; ?>">
                    <span class="link-icon"><i class="bi bi-file-earmark-bar-graph"></i></span>
                    <span class="link-text">Incident Report</span>
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
<?php endif; ?>


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
document.addEventListener('DOMContentLoaded', function() {
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

        if (group && panel) {
            let hideTimeout = null;

            const showPanel = () => {
                if (!isDesktop()) return;
                clearTimeout(hideTimeout);
                openDesktop();
                button.classList.add('active');
                group.classList.add('active');
                panel.classList.add('show');
                button.setAttribute('aria-expanded', 'true');
            };

            const hidePanel = () => {
                if (!isDesktop()) return;
                clearTimeout(hideTimeout);
                hideTimeout = setTimeout(() => {
                    button.classList.remove('active');
                    group.classList.remove('active');
                    panel.classList.remove('show');
                    button.setAttribute('aria-expanded', 'false');
                }, 150);
            };

            group.addEventListener('mouseenter', showPanel);
            group.addEventListener('mouseleave', hidePanel);
            panel.addEventListener('mouseenter', showPanel);
            panel.addEventListener('mouseleave', hidePanel);
        }
    });

    window.addEventListener('resize', updateEdge);
    updateEdge();
    })();
});
</script>
