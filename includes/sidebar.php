<?php
/**
 * Reusable Sidebar Component
 * Synced 1:1 with EMERGENCY-COM standard admin sidebar layout and icon accents
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$script_dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$in_subfolder = (strpos($script_dir, '/modules') !== false) || (strpos($script_dir, '/admin') !== false) || (strpos($script_dir, '/officer') !== false);
$base_url = isset($base_url) ? $base_url : ($in_subfolder ? '../' : '');

$role = strtolower(trim($_SESSION['role'] ?? 'user'));
$is_admin = $role === 'admin';
$is_officer = $role === 'officer';

$first_name = $_SESSION['first_name'] ?? $_SESSION['user_name'] ?? ($is_admin ? 'Admin' : ($is_officer ? 'Officer' : 'User'));
$last_name = $_SESSION['last_name'] ?? '';
$full_name = trim($first_name . ' ' . $last_name);
$display_role = ucfirst($role);

$avatar_url = !empty($_SESSION['user_picture']) ? $_SESSION['user_picture'] : 'https://ui-avatars.com/api/?name=' . urlencode($full_name ?: 'User') . '&background=4c8a89&color=fff&size=128';

$current_page = strtolower(basename($_SERVER['PHP_SELF']));
$is_user_page = in_array($current_page, ['landing.php', 'index.php', 'my_reports.php', 'blotter_create.php', 'user_profile.php', 'incident_report.php', 'request_form.php', 'learning.php']) || !empty($force_public_sidebar);
$show_admin_menu = ($is_admin || $is_officer) && !$is_user_page;
?>

<!-- Sidebar Component -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-brand">
            <a href="<?php echo $base_url; ?>index.php" class="brand-logo">
                <img src="<?php echo $base_url; ?>assets/images/logo.svg" alt="Alertara PH Logo" class="logo-img">
            </a>
        </div>
    </div>
    
    <div class="sidebar-content">
        <nav class="sidebar-nav">
            <?php if ($show_admin_menu): ?>
                <!-- Admin / Officer Primary Section -->
                <div class="sidebar-section">
                    <h3 class="sidebar-section-title">ADMIN</h3>
                    <ul class="sidebar-menu">
                        <li class="sidebar-menu-item">
                            <a href="<?php echo $base_url; ?>admin/dashboard.php" class="sidebar-link sidebar-accent-dashboard <?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>">
                                <i class="fas fa-home sidebar-icon" aria-hidden="true"></i>
                                <span>Dashboard</span>
                            </a>
                        </li>
                        <li class="sidebar-menu-item">
                            <a href="<?php echo $base_url; ?>admin/users.php" class="sidebar-link sidebar-accent-users <?php echo $current_page === 'users.php' ? 'active' : ''; ?>">
                                <i class="fas fa-users sidebar-icon" aria-hidden="true"></i>
                                <span>Users</span>
                            </a>
                        </li>
                        <li class="sidebar-menu-item">
                            <a href="<?php echo $base_url; ?>admin/account_approvals.php" class="sidebar-link sidebar-accent-approvals <?php echo $current_page === 'account_approvals.php' ? 'active' : ''; ?>">
                                <i class="fas fa-user-check sidebar-icon" aria-hidden="true"></i>
                                <span>Admin Approvals</span>
                            </a>
                        </li>
                        <li class="sidebar-menu-item">
                            <a href="<?php echo $base_url; ?>admin/add_admin.php" class="sidebar-link sidebar-accent-profile <?php echo $current_page === 'add_admin.php' ? 'active' : ''; ?>">
                                <i class="fas fa-user-shield sidebar-icon" aria-hidden="true"></i>
                                <span>Create Admin</span>
                            </a>
                        </li>
                        <li class="sidebar-menu-item">
                            <a href="<?php echo $base_url; ?>admin/settings.php" class="sidebar-link sidebar-accent-profile <?php echo ($current_page === 'settings.php' && isset($_GET['tab']) && $_GET['tab'] === 'profile') ? 'active' : ''; ?>">
                                <i class="fas fa-user-circle sidebar-icon" aria-hidden="true"></i>
                                <span>My Profile</span>
                            </a>
                        </li>
                        <li class="sidebar-menu-item">
                            <a href="<?php echo $base_url; ?>admin/external_integrations.php" class="sidebar-link sidebar-accent-auto <?php echo $current_page === 'external_integrations.php' ? 'active' : ''; ?>">
                                <i class="fas fa-network-wired sidebar-icon" aria-hidden="true"></i>
                                <span>External Integrations</span>
                            </a>
                        </li>
                        <li class="sidebar-menu-item">
                            <a href="<?php echo $base_url; ?>admin/settings.php" class="sidebar-link sidebar-accent-settings <?php echo ($current_page === 'settings.php' && !isset($_GET['tab'])) ? 'active' : ''; ?>">
                                <i class="fas fa-cog sidebar-icon" aria-hidden="true"></i>
                                <span>Settings</span>
                            </a>
                        </li>
                    </ul>
                </div>
                
                <!-- Incident & Case Management Section -->
                <?php
                    $status_param = strtolower($_GET['status'] ?? '');
                    $is_blotter_active = in_array($current_page, ['blotters.php', 'blotter.php', 'blotter_create.php', 'blotter_update.php', 'blotter_view.php', 'certificate_of_file_action.php']);
                    $is_cases_mgmt_active = in_array($current_page, ['summons.php', 'hearing_schedule.php', 'hearing_result.php', 'settle.php']) || ($current_page === 'cases.php' && $status_param === 'closed');
                ?>
                <div class="sidebar-section">
                    <h3 class="sidebar-section-title">INCIDENT MANAGEMENT</h3>
                    <ul class="sidebar-menu">
                        <!-- Digital Blotter Dropdown -->
                        <li class="sidebar-menu-item has-dropdown <?php echo $is_blotter_active ? 'open' : ''; ?>">
                            <a href="javascript:void(0);" class="sidebar-link sidebar-dropdown-toggle <?php echo $is_blotter_active ? 'active-parent' : ''; ?>">
                                <i class="fas fa-clipboard-list sidebar-icon" aria-hidden="true"></i>
                                <span>Digital Blotter</span>
                                <i class="fas fa-chevron-down dropdown-arrow"></i>
                            </a>
                            <ul class="sidebar-submenu <?php echo $is_blotter_active ? 'show' : ''; ?>">
                                <li class="sidebar-submenu-item">
                                    <a href="<?php echo $base_url; ?>admin/blotters.php" class="sidebar-submenu-link <?php echo (in_array($current_page, ['blotters.php', 'blotter.php', 'blotter_create.php', 'blotter_update.php', 'blotter_view.php'])) ? 'active' : ''; ?>">
                                        <i class="fas fa-file-alt sidebar-subicon"></i>
                                        <span>Blotter</span>
                                    </a>
                                </li>
                                <li class="sidebar-submenu-item">
                                    <a href="<?php echo $base_url; ?>admin/certificate_of_file_action.php" class="sidebar-submenu-link <?php echo $current_page === 'certificate_of_file_action.php' ? 'active' : ''; ?>">
                                        <i class="fas fa-file-contract sidebar-subicon"></i>
                                        <span>Certificate of File Action</span>
                                    </a>
                                </li>
                            </ul>
                        </li>

                        <!-- Cases Management Dropdown -->
                        <li class="sidebar-menu-item has-dropdown <?php echo $is_cases_mgmt_active ? 'open' : ''; ?>">
                            <a href="javascript:void(0);" class="sidebar-link sidebar-dropdown-toggle <?php echo $is_cases_mgmt_active ? 'active-parent' : ''; ?>">
                                <i class="fas fa-gavel sidebar-icon" aria-hidden="true"></i>
                                <span>Cases Management</span>
                                <i class="fas fa-chevron-down dropdown-arrow"></i>
                            </a>
                            <ul class="sidebar-submenu <?php echo $is_cases_mgmt_active ? 'show' : ''; ?>">
                                <li class="sidebar-submenu-item">
                                    <a href="<?php echo $base_url; ?>admin/Summons.php" class="sidebar-submenu-link <?php echo $current_page === 'summons.php' ? 'active' : ''; ?>">
                                        <i class="fas fa-envelope-open-text sidebar-subicon"></i>
                                        <span>Summons</span>
                                    </a>
                                </li>
                                <li class="sidebar-submenu-item">
                                    <a href="<?php echo $base_url; ?>admin/Hearing_schedule.php" class="sidebar-submenu-link <?php echo $current_page === 'hearing_schedule.php' ? 'active' : ''; ?>">
                                        <i class="fas fa-calendar-alt sidebar-subicon"></i>
                                        <span>Hearing Schedule</span>
                                    </a>
                                </li>
                                <li class="sidebar-submenu-item">
                                    <a href="<?php echo $base_url; ?>admin/hearing_result.php" class="sidebar-submenu-link <?php echo $current_page === 'hearing_result.php' ? 'active' : ''; ?>">
                                        <i class="fas fa-poll-h sidebar-subicon"></i>
                                        <span>Hearing Result</span>
                                    </a>
                                </li>
                                <li class="sidebar-submenu-item">
                                    <a href="<?php echo $base_url; ?>admin/settle.php" class="sidebar-submenu-link <?php echo $current_page === 'settle.php' ? 'active' : ''; ?>">
                                        <i class="fas fa-handshake sidebar-subicon"></i>
                                        <span>Settlement</span>
                                    </a>
                                </li>
                                <li class="sidebar-submenu-item">
                                    <a href="<?php echo $base_url; ?>admin/cases.php?status=Closed" class="sidebar-submenu-link <?php echo ($current_page === 'cases.php' && $status_param === 'closed') ? 'active' : ''; ?>">
                                        <i class="fas fa-folder-minus sidebar-subicon"></i>
                                        <span>Close Cases</span>
                                    </a>
                                </li>
                            </ul>
                        </li>

                        <!-- Suspects & Witnesses Direct Link -->
                        <li class="sidebar-menu-item">
                            <a href="<?php echo $base_url; ?>admin/suspects_management.php" class="sidebar-link sidebar-accent-categorization <?php echo (in_array($current_page, ['suspects_management.php', 'suspects&witnesses.php', 'witnesses_management.php'])) ? 'active' : ''; ?>">
                                <i class="fas fa-user-secret sidebar-icon" aria-hidden="true"></i>
                                <span>Suspects & Witnesses</span>
                            </a>
                        </li>

                        <!-- Incident Report Direct Link -->
                        <li class="sidebar-menu-item">
                            <a href="<?php echo $base_url; ?>modules/Incident_report.php" class="sidebar-link sidebar-accent-mass <?php echo $current_page === 'incident_report.php' ? 'active' : ''; ?>">
                                <i class="fas fa-exclamation-triangle sidebar-icon" aria-hidden="true"></i>
                                <span>Incident Report</span>
                            </a>
                        </li>

                        <!-- Request Form Direct Link -->
                        <li class="sidebar-menu-item">
                            <a href="<?php echo $base_url; ?>modules/Request_form.php" class="sidebar-link sidebar-accent-weather <?php echo (in_array($current_page, ['request_form.php', 'cctv_request.php'])) ? 'active' : ''; ?>">
                                <i class="fas fa-file-signature sidebar-icon" aria-hidden="true"></i>
                                <span>Request Form</span>
                            </a>
                        </li>

                        <!-- Case Tracking Direct Link -->
                        <li class="sidebar-menu-item">
                            <a href="<?php echo $base_url; ?>admin/cases.php" class="sidebar-link sidebar-accent-2way <?php echo ($current_page === 'cases.php' && $status_param !== 'closed') ? 'active' : ''; ?>">
                                <i class="fas fa-briefcase sidebar-icon" aria-hidden="true"></i>
                                <span>All Cases</span>
                            </a>
                        </li>

                        <!-- Evidence Collection -->
                        <li class="sidebar-menu-item">
                            <a href="<?php echo $base_url; ?>modules/evidence_collection.php" class="sidebar-link sidebar-accent-language <?php echo $current_page === 'evidence_collection.php' ? 'active' : ''; ?>">
                                <i class="fas fa-box-open sidebar-icon" aria-hidden="true"></i>
                                <span>Evidence Collection</span>
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Intelligence & Analytics Section -->
                <div class="sidebar-section">
                    <h3 class="sidebar-section-title">INTELLIGENCE & REPORTS</h3>
                    <ul class="sidebar-menu">
                        <li class="sidebar-menu-item">
                            <a href="<?php echo $base_url; ?>admin/reports.php" class="sidebar-link sidebar-accent-overview <?php echo $current_page === 'reports.php' ? 'active' : ''; ?>">
                                <i class="fas fa-chart-line sidebar-icon" aria-hidden="true"></i>
                                <span>Reports & Analytics</span>
                            </a>
                        </li>
                        <li class="sidebar-menu-item">
                            <a href="<?php echo $base_url; ?>admin/setup_automated_reports.php" class="sidebar-link sidebar-accent-auto <?php echo $current_page === 'setup_automated_reports.php' ? 'active' : ''; ?>">
                                <i class="fas fa-robot sidebar-icon" aria-hidden="true"></i>
                                <span>Automated Reports</span>
                            </a>
                        </li>
                    </ul>
                </div>

            <?php else: ?>
                <!-- Public Resident Section -->
                <div class="sidebar-section">
                    <h3 class="sidebar-section-title">RESIDENT PORTAL</h3>
                    <ul class="sidebar-menu">
                        <li class="sidebar-menu-item">
                            <a href="<?php echo $base_url; ?>modules/my_reports.php" class="sidebar-link sidebar-accent-dashboard <?php echo (in_array($current_page, ['my_reports.php', 'landing.php', 'index.php'])) ? 'active' : ''; ?>">
                                <i class="fas fa-home sidebar-icon" aria-hidden="true"></i>
                                <span>Dashboard</span>
                            </a>
                        </li>
                        <li class="sidebar-menu-item">
                            <a href="<?php echo $base_url; ?>modules/blotter_create.php" class="sidebar-link sidebar-accent-mass <?php echo $current_page === 'blotter_create.php' ? 'active' : ''; ?>">
                                <i class="fas fa-pen-nib sidebar-icon" aria-hidden="true"></i>
                                <span>Create Blotter</span>
                            </a>
                        </li>
                        <li class="sidebar-menu-item">
                            <a href="<?php echo $base_url; ?>modules/user_profile.php" class="sidebar-link sidebar-accent-profile <?php echo $current_page === 'user_profile.php' ? 'active' : ''; ?>">
                                <i class="fas fa-user-circle sidebar-icon" aria-hidden="true"></i>
                                <span>User Profile</span>
                            </a>
                        </li>
                    </ul>
                </div>
            <?php endif; ?>
        </nav>
    </div>

    <!-- Sidebar Footer User Profile -->
    <div class="sidebar-footer">
        <div class="sidebar-user-container">
            <img src="<?php echo htmlspecialchars($avatar_url); ?>" alt="Avatar" class="sidebar-user-avatar">
            <div class="sidebar-user-info">
                <span class="sidebar-user-name"><?php echo htmlspecialchars($full_name); ?></span>
                <span class="sidebar-user-role"><?php echo htmlspecialchars($display_role); ?></span>
            </div>
        </div>
        <a href="<?php echo $base_url; ?>auth/logout.php" class="sidebar-logout-btn" title="Sign Out">
            <i class="fas fa-sign-out-alt"></i>
        </a>
    </div>
</aside>

<!-- Mobile Sidebar Backdrop Overlay -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>
