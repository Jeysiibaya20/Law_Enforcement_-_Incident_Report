<?php
$current_page = basename($_SERVER['PHP_SELF'], '.php');
$user_role = $_SESSION['role'] ?? 'Employee';

// Calculate base URL dynamically
$script_dir = dirname($_SERVER['SCRIPT_NAME']);
$base_url = ($script_dir === '/admin' || strpos($script_dir, '/admin') !== false) ? '../' : '';

// Create absolute paths for navigation
$root_path = '/HOTEL-MANAGEMENT-SYSTEM/HR-1&2-REVAMPED/MERGED_HR_SYSTEM/';
$new_hire_path = $root_path . 'admin/new_hires_dashboard.php';
$performance_path = $root_path . 'modules/performance.php';
$recruitment_path = $root_path . 'modules/recruitment.php';
$recognition_path = $root_path . 'modules/recognition.php';
$competency_path = $root_path . 'modules/competency.php';
$succession_path = $root_path . 'modules/succession.php';
$learning_path = $root_path . 'modules/learning.php';
?>
<div class="sidebar-wrapper" id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <img src="<?php echo $base_url; ?>assets/css/logo.png" alt="Logo" class="sidebar-logo-image">
        </div>
        <div class="sidebar-title">Luz De Luna</div>
        <div class="sidebar-subtitle">HR 1&2</div>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-section">
            <div class="nav-section-title"><span>Dashboard</span><i class="bi bi-chevron-down nav-chevron"></i></div>
            <a href="<?php echo $base_url; ?>index.php" class="nav-link <?php echo ($current_page=='index')?'active':''; ?>">
                <i class="bi bi-speedometer2"></i> <span>Dashboard</span>
            </a>
        </div>
        <div class="nav-section">
            <div class="nav-section-title"><span>Modules</span><i class="bi bi-chevron-down nav-chevron"></i></div>
            <a href="<?php echo $new_hire_path; ?>" class="nav-link <?php echo ($current_page=='new_hires_dashboard')?'active':''; ?>">
                <i class="bi bi-person-plus"></i> <span>New Hire & ESS</span>
            </a>
            <a href="<?php echo $performance_path; ?>" class="nav-link <?php echo ($current_page=='performance')?'active':''; ?>">
                <i class="bi bi-speedometer"></i> <span>Performance Management</span>
            </a>
            <a href="<?php echo $recruitment_path; ?>" class="nav-link <?php echo ($current_page=='recruitment')?'active':''; ?>">
                <i class="bi bi-briefcase"></i> <span>Recruitment & Applicants</span>
            </a>
            <a href="<?php echo $recognition_path; ?>" class="nav-link <?php echo ($current_page=='recognition')?'active':''; ?>">
                <i class="bi bi-heart"></i> <span>Social Recognition</span>
            </a>
            <a href="<?php echo $competency_path; ?>" class="nav-link <?php echo ($current_page=='competency')?'active':''; ?>">
                <i class="bi bi-award"></i> <span>Competency Management</span>
            </a>
            <a href="<?php echo $succession_path; ?>" class="nav-link <?php echo ($current_page=='succession')?'active':''; ?>">
                <i class="bi bi-diagram-3"></i> <span>Succession Planning</span>
            </a>
            <a href="<?php echo $learning_path; ?>" class="nav-link <?php echo ($current_page=='learning')?'active':''; ?>">
                <i class="bi bi-mortarboard"></i> <span>Learning & Training</span>
            </a>
        </div>
    </nav>
    <?php if (isset($_SESSION['user_id'])): ?>
    <div class="sidebar-user">
        <div class="user-avatar"><i class="bi bi-person"></i></div>
        <div class="user-info"><?php echo htmlspecialchars($_SESSION['first_name'] ?? 'User'); ?></div>
        <div class="user-role"><?php echo htmlspecialchars($_SESSION['role'] ?? 'Employee'); ?></div>
        <div class="mt-2">
            <a href="<?php echo $base_url; ?>auth/logout.php" class="btn btn-sm btn-outline">
                <i class="bi bi-box-arrow-right"></i> Logout
            </a>
        </div>
    </div>
    <?php endif; ?>
</div>

