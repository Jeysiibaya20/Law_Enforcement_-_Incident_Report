<?php
$page_title = 'HR 1&2 Dashboard';
$base_url = '';
require_once 'includes/header.php';
require_once 'includes/navbar.php';
require_once __DIR__ . '/config/db_connect.php';

// Initialize KPI values
$kpis = [
    'total_employees' => '--',
    'active_new_hires' => '--',
    'overdue_tasks' => '--',
    'completed_onboardings' => '--'
];

// Fetch dynamic data with safe fallbacks
try {
    // Total Employees
    $stmt = $pdo->query("SELECT COUNT(*) AS cnt FROM employees");
    $kpis['total_employees'] = (int)($stmt->fetch()['cnt'] ?? 0);
} catch (Throwable $e) {
    $kpis['total_employees'] = '--';
}

try {
    // Active New Hires (Pending or In Progress)
    $stmt = $pdo->query("SELECT COUNT(*) AS cnt FROM new_hires WHERE status IN ('Pending','In Progress')");
    $kpis['active_new_hires'] = (int)($stmt->fetch()['cnt'] ?? 0);
} catch (Throwable $e) {
    $kpis['active_new_hires'] = '--';
}

try {
    // Overdue Tasks in Onboarding
    $stmt = $pdo->query("SELECT COUNT(*) AS cnt FROM new_hire_tasks WHERE status = 'Overdue'");
    $kpis['overdue_tasks'] = (int)($stmt->fetch()['cnt'] ?? 0);
} catch (Throwable $e) {
    $kpis['overdue_tasks'] = '--';
}

try {
    // Completed Onboardings
    $stmt = $pdo->query("SELECT COUNT(*) AS cnt FROM new_hires WHERE status = 'Completed'");
    $kpis['completed_onboardings'] = (int)($stmt->fetch()['cnt'] ?? 0);
} catch (Throwable $e) {
    $kpis['completed_onboardings'] = '--';
}

// Recent new hires list (last 6)
$recent_new_hires = [];
try {
    $sql = "SELECT nh.new_hire_id, nh.employee_id, nh.status, nh.start_date, e.first_name, e.last_name, p.title AS position_title
            FROM new_hires nh
            LEFT JOIN employees e ON e.employee_id = nh.employee_id
            LEFT JOIN positions p ON p.position_id = e.position_id
            ORDER BY nh.new_hire_id DESC
            LIMIT 6";
    $stmt = $pdo->query($sql);
    $recent_new_hires = $stmt->fetchAll();
} catch (Throwable $e) {
    $recent_new_hires = [];
}
?>
<div class="main-content">
    <div class="content-container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1>HR 1&2 Dashboard</h1>
                <p class="text-secondary">Welcome to the HR 1&2 revamped system</p>
            </div>
        </div>
        
        <div class="row g-3">
            <div class="col-md-3">
                <div class="stats-card enhanced-card">
                    <div class="card-header-icon"><i class="bi bi-people"></i></div>
                    <div class="stats-content">
                        <div class="stats-number"><?php echo htmlspecialchars((string)$kpis['total_employees']); ?></div>
                        <div class="stats-label">Employees</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card enhanced-card" style="background: var(--gradient-warning);">
                    <div class="card-header-icon"><i class="bi bi-person-plus"></i></div>
                    <div class="stats-content">
                        <div class="stats-number"><?php echo htmlspecialchars((string)$kpis['active_new_hires']); ?></div>
                        <div class="stats-label">Active New Hires</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card enhanced-card" style="background: var(--gradient-accent);">
                    <div class="card-header-icon"><i class="bi bi-exclamation-triangle"></i></div>
                    <div class="stats-content">
                        <div class="stats-number"><?php echo htmlspecialchars((string)$kpis['overdue_tasks']); ?></div>
                        <div class="stats-label">Overdue Tasks</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card enhanced-card" style="background: var(--gradient-success);">
                    <div class="card-header-icon"><i class="bi bi-check2-circle"></i></div>
                    <div class="stats-content">
                        <div class="stats-number"><?php echo htmlspecialchars((string)$kpis['completed_onboardings']); ?></div>
                        <div class="stats-label">Completed Onboardings</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mt-3">
            <div class="col-lg-8">
                <div class="enhanced-card">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h5 class="mb-0">Recent New Hires</h5>
                        <a href="<?php echo $base_url; ?>admin/new_hires.php" class="btn btn-sm btn-outline">View All</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Position</th>
                                    <th>Status</th>
                                    <th>Start Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recent_new_hires)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-secondary">No recent new hires</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recent_new_hires as $row): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars(trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')) ?: '—'); ?></td>
                                            <td><?php echo htmlspecialchars($row['position_title'] ?? '—'); ?></td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo ($row['status'] === 'Completed') ? 'success' : (($row['status'] === 'In Progress') ? 'warning' : 'secondary');
                                                ?>"><?php echo htmlspecialchars($row['status'] ?? '—'); ?></span>
                                            </td>
                                            <td><?php echo htmlspecialchars($row['start_date'] ?? '—'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="enhanced-card">
                    <h5 class="mb-2">Quick Actions</h5>
                    <div class="d-grid gap-2">
                        <a href="<?php echo $base_url; ?>admin/add_new_hire.php" class="btn btn-primary"><i class="bi bi-person-plus me-2"></i>Add New Hire</a>
                        <a href="<?php echo $base_url; ?>admin/new_hires_dashboard.php" class="btn btn-outline"><i class="bi bi-speedometer2 me-2"></i>New Hire Dashboard</a>
                        <a href="<?php echo $base_url; ?>admin/onboarding_reports.php" class="btn btn-outline"><i class="bi bi-graph-up me-2"></i>Onboarding Reports</a>
                    </div>
                </div>
                <div class="enhanced-card mt-3">
                    <h5 class="mb-2">System Status</h5>
                    <ul class="list-unstyled mb-0 small text-secondary">
                        <li>Database: <span class="text-success">Connected</span></li>
                        <li>Environment: <span class="text-secondary">HR 1&2</span></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>



