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
            <?php if ($is_admin || $is_officer): ?>
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
                            <a href="<?php echo $base_url; ?>admin/settings.php" class="sidebar-link sidebar-accent-settings <?php echo ($current_page === 'settings.php' && !isset($_GET['tab'])) ? 'active' : ''; ?>">
                                <i class="fas fa-cog sidebar-icon" aria-hidden="true"></i>
                                <span>Settings</span>
                            </a>
                        </li>
                    </ul>
                </div>
                
                <!-- Incident & Case Management Section -->
                <div class="sidebar-section">
                    <h3 class="sidebar-section-title">INCIDENT MANAGEMENT</h3>
                    <ul class="sidebar-menu">
                        <li class="sidebar-menu-item">
                            <a href="<?php echo $base_url; ?>admin/cases.php" class="sidebar-link sidebar-accent-2way <?php echo $current_page === 'cases.php' ? 'active' : ''; ?>">
                                <i class="fas fa-briefcase sidebar-icon" aria-hidden="true"></i>
                                <span>Case Tracking</span>
                            </a>
                        </li>
                        <li class="sidebar-menu-item">
                            <a href="<?php echo $base_url; ?>admin/blotters.php" class="sidebar-link sidebar-accent-mass <?php echo $current_page === 'blotters.php' ? 'active' : ''; ?>">
                                <i class="fas fa-clipboard-list sidebar-icon" aria-hidden="true"></i>
                                <span>Blotter Registry</span>
                            </a>
                        </li>
                        <li class="sidebar-menu-item">
                            <a href="<?php echo $base_url; ?>admin/suspects_management.php" class="sidebar-link sidebar-accent-categorization <?php echo ($current_page === 'suspects_management.php' || $current_page === 'suspects&witnesses.php') ? 'active' : ''; ?>">
                                <i class="fas fa-user-secret sidebar-icon" aria-hidden="true"></i>
                                <span>Suspects & Witnesses</span>
                            </a>
                        </li>
                        <li class="sidebar-menu-item">
                            <a href="<?php echo $base_url; ?>admin/Hearing_schedule.php" class="sidebar-link sidebar-accent-auto <?php echo $current_page === 'hearing_schedule.php' ? 'active' : ''; ?>">
                                <i class="fas fa-gavel sidebar-icon" aria-hidden="true"></i>
                                <span>Hearing Schedule</span>
                            </a>
                        </li>
                        <li class="sidebar-menu-item">
                            <a href="<?php echo $base_url; ?>admin/hearing_result.php" class="sidebar-link sidebar-accent-overview <?php echo $current_page === 'hearing_result.php' ? 'active' : ''; ?>">
                                <i class="fas fa-poll-h sidebar-icon" aria-hidden="true"></i>
                                <span>Hearing Result</span>
                            </a>
                        </li>
                        <li class="sidebar-menu-item">
                            <a href="<?php echo $base_url; ?>modules/Request_form.php" class="sidebar-link sidebar-accent-weather <?php echo ($current_page === 'request_form.php' || $current_page === 'cctv_request.php') ? 'active' : ''; ?>">
                                <i class="fas fa-video sidebar-icon" aria-hidden="true"></i>
                                <span>CCTV / Request Form</span>
                            </a>
                        </li>
                        <li class="sidebar-menu-item">
                            <a href="<?php echo $base_url; ?>modules/evidence_collection.php" class="sidebar-link sidebar-accent-language <?php echo $current_page === 'evidence_collection.php' ? 'active' : ''; ?>">
                                <i class="fas fa-box-open sidebar-icon" aria-hidden="true"></i>
                                <span>Evidence Collection</span>
                            </a>
                        </li>
                        <li class="sidebar-menu-item">
                            <a href="<?php echo $base_url; ?>admin/Summons.php" class="sidebar-link sidebar-accent-multilang <?php echo $current_page === 'summons.php' ? 'active' : ''; ?>">
                                <i class="fas fa-envelope-open-text sidebar-icon" aria-hidden="true"></i>
                                <span>Summons Notices</span>
                            </a>
                        </li>
                        <li class="sidebar-menu-item">
                            <a href="<?php echo $base_url; ?>admin/certificate_of_file_action.php" class="sidebar-link sidebar-accent-citizen <?php echo $current_page === 'certificate_of_file_action.php' ? 'active' : ''; ?>">
                                <i class="fas fa-file-contract sidebar-icon" aria-hidden="true"></i>
                                <span>Certificates of Action</span>
                            </a>
                        </li>
                        <li class="sidebar-menu-item">
                            <a href="<?php echo $base_url; ?>admin/settle.php" class="sidebar-link sidebar-accent-audit <?php echo $current_page === 'settle.php' ? 'active' : ''; ?>">
                                <i class="fas fa-handshake sidebar-icon" aria-hidden="true"></i>
                                <span>Settlement Records</span>
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
                    <h3 class="sidebar-section-title">PUBLIC SERVICES</h3>
                    <ul class="sidebar-menu">
                        <li class="sidebar-menu-item">
                            <a href="<?php echo $base_url; ?>modules/my_reports.php" class="sidebar-link sidebar-accent-dashboard <?php echo $current_page === 'my_reports.php' ? 'active' : ''; ?>">
                                <i class="fas fa-folder-open sidebar-icon" aria-hidden="true"></i>
                                <span>My Reports</span>
                            </a>
                        </li>
                        <li class="sidebar-menu-item">
                            <a href="<?php echo $base_url; ?>modules/Incident_report.php" class="sidebar-link sidebar-accent-mass <?php echo $current_page === 'incident_report.php' ? 'active' : ''; ?>">
                                <i class="fas fa-exclamation-triangle sidebar-icon" aria-hidden="true"></i>
                                <span>File Incident Report</span>
                            </a>
                        </li>
                        <li class="sidebar-menu-item">
                            <a href="<?php echo $base_url; ?>modules/crime_mapping.php" class="sidebar-link sidebar-accent-weather <?php echo $current_page === 'crime_mapping.php' ? 'active' : ''; ?>">
                                <i class="fas fa-map-marked-alt sidebar-icon" aria-hidden="true"></i>
                                <span>Crime Mapping</span>
                            </a>
                        </li>
                        <li class="sidebar-menu-item">
                            <a href="<?php echo $base_url; ?>modules/learning.php" class="sidebar-link sidebar-accent-language <?php echo $current_page === 'learning.php' ? 'active' : ''; ?>">
                                <i class="fas fa-book-open sidebar-icon" aria-hidden="true"></i>
                                <span>Awareness & Guide</span>
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
