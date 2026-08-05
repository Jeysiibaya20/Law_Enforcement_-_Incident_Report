<?php
/**
 * Unified Sidebar Navigation Component
 * Synced with EMERGENCY-COM standard sidebar design
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

$first_name = $_SESSION['first_name'] ?? $_SESSION['user_name'] ?? ($is_admin ? 'Admin' : ($is_officer ? 'Officer' : 'Resident'));
$last_name = $_SESSION['last_name'] ?? '';
$full_name = trim($first_name . ' ' . $last_name);
$display_role = ucfirst($role);

$avatar_url = !empty($_SESSION['user_picture']) ? $_SESSION['user_picture'] : 'https://ui-avatars.com/api/?name=' . urlencode($full_name) . '&background=4c8a89&color=fff&size=128';

$current_page = strtolower(basename($_SERVER['PHP_SELF']));
?>

<!-- Sidebar Drawer -->
<aside class="sidebar" id="appSidebar">
    <div class="sidebar-header">
        <a href="<?php echo $base_url; ?>index.php" class="sidebar-brand">
            <i class="fas fa-shield-alt"></i>
            <span>ALERTARA</span>
        </a>
    </div>

    <div class="sidebar-nav">
        <?php if ($is_admin): ?>
            <div class="sidebar-section-label">Main Navigation</div>
            <a href="<?php echo $base_url; ?>admin/dashboard.php" class="sidebar-link <?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>">
                <i class="fas fa-chart-line"></i>
                <span>Dashboard</span>
            </a>
            <a href="<?php echo $base_url; ?>admin/cases.php" class="sidebar-link <?php echo $current_page === 'cases.php' ? 'active' : ''; ?>">
                <i class="fas fa-briefcase"></i>
                <span>Case Tracking</span>
            </a>
            <a href="<?php echo $base_url; ?>admin/blotters.php" class="sidebar-link <?php echo $current_page === 'blotters.php' ? 'active' : ''; ?>">
                <i class="fas fa-clipboard-list"></i>
                <span>Blotter Registry</span>
            </a>
            
            <div class="sidebar-section-label">Management</div>
            <a href="<?php echo $base_url; ?>admin/account_approvals.php" class="sidebar-link <?php echo $current_page === 'account_approvals.php' ? 'active' : ''; ?>">
                <i class="fas fa-user-check"></i>
                <span>Account Approvals</span>
            </a>
            <a href="<?php echo $base_url; ?>admin/suspects_management.php" class="sidebar-link <?php echo ($current_page === 'suspects_management.php' || $current_page === 'suspects&witnesses.php') ? 'active' : ''; ?>">
                <i class="fas fa-users-cog"></i>
                <span>Suspects & Witnesses</span>
            </a>
            <a href="<?php echo $base_url; ?>admin/Hearing_schedule.php" class="sidebar-link <?php echo $current_page === 'hearing_schedule.php' ? 'active' : ''; ?>">
                <i class="fas fa-gavel"></i>
                <span>Hearing Schedule</span>
            </a>
            <a href="<?php echo $base_url; ?>admin/users.php" class="sidebar-link <?php echo $current_page === 'users.php' ? 'active' : ''; ?>">
                <i class="fas fa-users"></i>
                <span>User Accounts</span>
            </a>

            <div class="sidebar-section-label">Analytics & Reports</div>
            <a href="<?php echo $base_url; ?>admin/reports.php" class="sidebar-link <?php echo $current_page === 'reports.php' ? 'active' : ''; ?>">
                <i class="fas fa-file-invoice"></i>
                <span>Reports & Analytics</span>
            </a>
            <a href="<?php echo $base_url; ?>admin/setup_automated_reports.php" class="sidebar-link <?php echo $current_page === 'setup_automated_reports.php' ? 'active' : ''; ?>">
                <i class="fas fa-robot"></i>
                <span>Automated Reports</span>
            </a>
            <a href="<?php echo $base_url; ?>admin/settings.php" class="sidebar-link <?php echo $current_page === 'settings.php' ? 'active' : ''; ?>">
                <i class="fas fa-cog"></i>
                <span>System Settings</span>
            </a>

        <?php elseif ($is_officer): ?>
            <div class="sidebar-section-label">Officer Portal</div>
            <a href="<?php echo $base_url; ?>admin/dashboard.php" class="sidebar-link <?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>">
                <i class="fas fa-chart-pie"></i>
                <span>Dashboard</span>
            </a>
            <a href="<?php echo $base_url; ?>admin/cases.php" class="sidebar-link <?php echo $current_page === 'cases.php' ? 'active' : ''; ?>">
                <i class="fas fa-folder-open"></i>
                <span>Assigned Cases</span>
            </a>
            <a href="<?php echo $base_url; ?>admin/blotters.php" class="sidebar-link <?php echo $current_page === 'blotters.php' ? 'active' : ''; ?>">
                <i class="fas fa-clipboard"></i>
                <span>Incident Records</span>
            </a>
            <a href="<?php echo $base_url; ?>admin/Hearing_schedule.php" class="sidebar-link <?php echo $current_page === 'hearing_schedule.php' ? 'active' : ''; ?>">
                <i class="fas fa-calendar-alt"></i>
                <span>Hearings</span>
            </a>
            <a href="<?php echo $base_url; ?>modules/crime_mapping.php" class="sidebar-link <?php echo $current_page === 'crime_mapping.php' ? 'active' : ''; ?>">
                <i class="fas fa-map-marked-alt"></i>
                <span>Crime Mapping</span>
            </a>

        <?php else: ?>
            <div class="sidebar-section-label">Public Services</div>
            <a href="<?php echo $base_url; ?>modules/my_reports.php" class="sidebar-link <?php echo $current_page === 'my_reports.php' ? 'active' : ''; ?>">
                <i class="fas fa-folder-user"></i>
                <span>My Reports</span>
            </a>
            <a href="<?php echo $base_url; ?>modules/Incident_report.php" class="sidebar-link <?php echo $current_page === 'incident_report.php' ? 'active' : ''; ?>">
                <i class="fas fa-exclamation-triangle"></i>
                <span>File Report</span>
            </a>
            <a href="<?php echo $base_url; ?>modules/crime_mapping.php" class="sidebar-link <?php echo $current_page === 'crime_mapping.php' ? 'active' : ''; ?>">
                <i class="fas fa-map-marked-alt"></i>
                <span>Crime Mapping</span>
            </a>
            <a href="<?php echo $base_url; ?>modules/learning.php" class="sidebar-link <?php echo $current_page === 'learning.php' ? 'active' : ''; ?>">
                <i class="fas fa-book-open"></i>
                <span>Awareness & Guide</span>
            </a>
        <?php endif; ?>
    </div>

    <div class="sidebar-footer">
        <div class="sidebar-user">
            <img src="<?php echo htmlspecialchars($avatar_url); ?>" alt="Avatar" style="width:36px;height:36px;border-radius:50%;object-fit:cover;">
            <div style="display:flex;flex-direction:column;">
                <span style="font-size:0.85rem;font-weight:600;color:#fff;"><?php echo htmlspecialchars($first_name); ?></span>
                <span style="font-size:0.75rem;color:var(--sidebar-text-muted);"><?php echo htmlspecialchars($display_role); ?></span>
            </div>
        </div>
        <a href="<?php echo $base_url; ?>auth/logout.php" class="sidebar-logout-btn" title="Sign Out">
            <i class="fas fa-sign-out-alt"></i>
        </a>
    </div>
</aside>

<!-- Sidebar Overlay for mobile -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>
