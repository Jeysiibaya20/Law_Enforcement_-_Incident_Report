<?php
$page_title = 'Law & Incident Report';
$base_url = '';
require_once "includes/header.php";
require_once "includes/navbar.php";
require_once __DIR__ . '/config/db_connect.php';

// Initialize KPI values
$kpis = [
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
                <h1>Dashboard</h1>
                <p class="text-secondary">Welcome to Alertara</p>
            </div>
        </div>
        
        <div class="row g-3">
            <div class="col-12 col-sm-6 col-md-3">
                <div class="stats-card enhanced-card">
                    <div class="card-header-icon"><i class="bi bi-people"></i></div>
                    <div class="stats-content">
                        <div class="stats-number"><?php echo htmlspecialchars((string)$kpis['total_employees']); ?></div>
                        <div class="stats-label">...</div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-md-3">
                <div class="stats-card enhanced-card" style="background: linear-gradient(135deg, rgba(255, 255, 255, 0.9) 0%, rgba(58, 80, 107, 0.8) 100%);">
                    <div class="card-header-icon"><i class="bi bi-person-plus"></i></div>
                    <div class="stats-content">
                        <div class="stats-number"><?php echo htmlspecialchars((string)$kpis['active_new_hires']); ?></div>
                        <div class="stats-label">....</div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-md-3">
                <div class="stats-card enhanced-card" style="background: var(--gradient-accent);">
                    <div class="card-header-icon"><i class="bi bi-exclamation-triangle"></i></div>
                    <div class="stats-content">
                        <div class="stats-number"><?php echo htmlspecialchars((string)$kpis['overdue_tasks']); ?></div>
                        <div class="stats-label">....</div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-md-3">
                <div class="stats-card enhanced-card" style="background: linear-gradient(135deg, #fffefc 0%, #71abad);">
                    <div class="card-header-icon"><i class="bi bi-check2-circle"></i></div>
                    <div class="stats-content">
                        <div class="stats-number"><?php echo htmlspecialchars((string)$kpis['completed_onboardings']); ?></div>
                        <div class="stats-label">.....</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mt-3">
            <div class="col-12 col-lg-8">
                <div class="enhanced-card">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h5 class="mb-0">.....</h5>
                        
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead>
                                <tr>
                                    <th>...</th>
                                    <th>....</th>
                                    <th>....</th>
                                    <th>....</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recent_new_hires)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-secondary">No recent task</td>
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
            <div class="col-12 col-lg-4">
                <div class="enhanced-card">
                    
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>
