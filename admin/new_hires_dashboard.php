<?php
session_start();
require_once '../config/db_connect.php';
require_once '../includes/navbar.php';

// Check if user is logged in and has admin/HR permissions
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['Admin', 'HR Manager', 'HR Staff'])) {
    header('Location: ../auth/login.php');
    exit();
}

// Get dashboard statistics
try {
    // Total new hires
    $total_new_hires = $pdo->query("SELECT COUNT(*) FROM new_hires")->fetchColumn();
    
    // Pending new hires
    $pending_new_hires = $pdo->query("SELECT COUNT(*) FROM new_hires WHERE status = 'Pending'")->fetchColumn();
    
    // In progress new hires
    $in_progress_new_hires = $pdo->query("SELECT COUNT(*) FROM new_hires WHERE status = 'In Progress'")->fetchColumn();
    
    // Completed this month
    $completed_this_month = $pdo->query("
        SELECT COUNT(*) FROM new_hires 
        WHERE status = 'Completed' 
        AND MONTH(completed_at) = MONTH(CURRENT_DATE()) 
        AND YEAR(completed_at) = YEAR(CURRENT_DATE())
    ")->fetchColumn();
    
    // Recent new hires (last 7 days)
    $recent_new_hires = $pdo->query("
        SELECT nh.*, d.department_name, e.first_name as created_by_name, e.last_name as created_by_last_name
        FROM new_hires nh
        LEFT JOIN departments d ON nh.department_id = d.department_id
        LEFT JOIN users u ON nh.created_by = u.user_id
        LEFT JOIN employees e ON u.employee_id = e.employee_id
        WHERE nh.created_at >= DATE_SUB(CURRENT_DATE(), INTERVAL 7 DAY)
        ORDER BY nh.created_at DESC
        LIMIT 5
    ")->fetchAll();
    
    // Overdue tasks
    $overdue_tasks = $pdo->query("
        SELECT COUNT(*) FROM onboarding_tasks 
        WHERE status = 'Pending' 
        AND due_date < CURRENT_DATE()
    ")->fetchColumn();
    
} catch (Exception $e) {
    $error = "Error loading dashboard data: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Hires Dashboard - HR System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-card.success {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        .stat-card.warning {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
        }
        .stat-card.danger {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
        }
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        .recent-hire-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 1rem;
        }
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        .status-pending { background-color: #ffc107; color: #000; }
        .status-in-progress { background-color: #17a2b8; color: #fff; }
        .status-completed { background-color: #28a745; color: #fff; }
        .status-on-hold { background-color: #6c757d; color: #fff; }
        .status-cancelled { background-color: #dc3545; color: #fff; }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="main-content">
        <div class="content-container">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <div>
                        <h1 class="h2">New Hires Dashboard</h1>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="../index.php">Home</a></li>
                                <li class="breadcrumb-item active" aria-current="page">New Hires</li>
                            </ol>
                        </nav>
                    </div>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="add_new_hire.php" class="btn btn-primary">
                            <i class="fas fa-user-plus me-2"></i>Add New Hire
                        </a>
                    </div>
                </div>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger" role="alert">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <!-- Navigation Tabs -->
                <ul class="nav nav-tabs mb-4">
                    <li class="nav-item">
                        <a class="nav-link active" href="new_hires_dashboard.php">
                            <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="new_hires.php">
                            <i class="fas fa-users me-2"></i>All New Hires
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="add_new_hire.php">
                            <i class="fas fa-user-plus me-2"></i>Add New Hire
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="onboarding_templates.php">
                            <i class="fas fa-clipboard-list me-2"></i>Templates
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="onboarding_reports.php">
                            <i class="fas fa-chart-bar me-2"></i>Reports
                        </a>
                    </li>
                </ul>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-number"><?php echo $total_new_hires; ?></div>
                            <div class="stat-label">Total New Hires</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card warning">
                            <div class="stat-number"><?php echo $pending_new_hires; ?></div>
                            <div class="stat-label">Pending</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-number"><?php echo $in_progress_new_hires; ?></div>
                            <div class="stat-label">In Progress</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card success">
                            <div class="stat-number"><?php echo $completed_this_month; ?></div>
                            <div class="stat-label">Completed This Month</div>
                        </div>
                    </div>
                </div>

                <!-- Overdue Tasks Alert -->
                <?php if ($overdue_tasks > 0): ?>
                    <div class="alert alert-warning alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Attention!</strong> You have <?php echo $overdue_tasks; ?> overdue onboarding tasks.
                        <a href="new_hires.php?filter=overdue" class="alert-link">View overdue tasks</a>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <!-- Recent New Hires -->
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-clock me-2"></i>Recent New Hires (Last 7 Days)
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recent_new_hires)): ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-user-plus fa-3x text-muted mb-3"></i>
                                        <p class="text-muted">No new hires in the last 7 days</p>
                                        <a href="add_new_hire.php" class="btn btn-primary">Add First New Hire</a>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($recent_new_hires as $hire): ?>
                                        <div class="recent-hire-card card">
                                            <div class="card-body">
                                                <div class="row align-items-center">
                                                    <div class="col-md-4">
                                                        <h6 class="mb-1"><?php echo htmlspecialchars($hire['first_name'] . ' ' . $hire['last_name']); ?></h6>
                                                        <small class="text-muted"><?php echo htmlspecialchars($hire['position']); ?></small>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <small class="text-muted">Department</small>
                                                        <div><?php echo htmlspecialchars($hire['department_name']); ?></div>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $hire['status'])); ?>">
                                                            <?php echo $hire['status']; ?>
                                                        </span>
                                                    </div>
                                                    <div class="col-md-3 text-end">
                                                        <small class="text-muted">Added by</small>
                                                        <div><?php echo htmlspecialchars($hire['created_by_name'] . ' ' . $hire['created_by_last_name']); ?></div>
                                                        <small class="text-muted"><?php echo date('M j, Y', strtotime($hire['created_at'])); ?></small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                    <div class="text-center mt-3">
                                        <a href="new_hires.php" class="btn btn-outline-primary">View All New Hires</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-bolt me-2"></i>Quick Actions
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <a href="add_new_hire.php" class="btn btn-primary">
                                        <i class="fas fa-user-plus me-2"></i>Add New Hire
                                    </a>
                                    <a href="new_hires.php?status=Pending" class="btn btn-warning">
                                        <i class="fas fa-clock me-2"></i>View Pending
                                    </a>
                                    <a href="new_hires.php?status=In Progress" class="btn btn-info">
                                        <i class="fas fa-spinner me-2"></i>View In Progress
                                    </a>
                                    <a href="onboarding_templates.php" class="btn btn-secondary">
                                        <i class="fas fa-clipboard-list me-2"></i>Manage Templates
                                    </a>
                                    <a href="onboarding_reports.php" class="btn btn-success">
                                        <i class="fas fa-chart-bar me-2"></i>View Reports
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- System Status -->
                        <div class="card mt-3">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-info-circle me-2"></i>System Status
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span>Database Connection</span>
                                    <span class="badge bg-success">Active</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span>File Upload</span>
                                    <span class="badge bg-success">Ready</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span>Email Notifications</span>
                                    <span class="badge bg-success">Enabled</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-refresh dashboard every 5 minutes
        setTimeout(function() {
            location.reload();
        }, 300000);
    </script>
</body>
</html>
