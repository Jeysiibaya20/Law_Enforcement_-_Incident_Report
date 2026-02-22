<?php
require_once 'admin_auth.php';

$base_url = '../';
$page_title = 'Reports & Analytics';
// When embedded in dashboard, the parent will include header/navbar.
if (empty($embed_in_dashboard)) {
    require_once '../includes/header.php';
    require_once '../includes/navbar.php';
}

// Get statistics
try {
    // Blotter statistics by status
    $blottersByStatus = [];
    $statuses = ['Pending', 'Under Investigation', 'Resolved', 'Archived'];
    foreach ($statuses as $status) {
        $count = $pdo->query("SELECT COUNT(*) FROM blotters WHERE status = '$status'")->fetchColumn();
        $blottersByStatus[$status] = $count;
    }

    // Blotter statistics by priority
    $blottersByPriority = [];
    $priorities = ['High', 'Medium', 'Low'];
    foreach ($priorities as $priority) {
        $count = $pdo->query("SELECT COUNT(*) FROM blotters WHERE priority = '$priority'")->fetchColumn();
        $blottersByPriority[$priority] = $count;
    }

    // User statistics
    $totalUsers = $pdo->query("SELECT COUNT(*) FROM signup WHERE role != 'Admin'")->fetchColumn();
    $verifiedUsers = $pdo->query("SELECT COUNT(*) FROM signup WHERE email_verified = 1 AND role != 'Admin'")->fetchColumn();
    $unverifiedUsers = $pdo->query("SELECT COUNT(*) FROM signup WHERE email_verified = 0 AND role != 'Admin'")->fetchColumn();
    $termsAcceptedUsers = $pdo->query("SELECT COUNT(*) FROM signup WHERE terms_accepted = 1 AND role != 'Admin'")->fetchColumn();

    // Monthly blotter creation trend (last 12 months)
    $monthlyData = $pdo->query("
        SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count 
        FROM blotters 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month
    ")->fetchAll(PDO::FETCH_ASSOC);

    $months = [];
    $counts = [];
    foreach ($monthlyData as $data) {
        $months[] = $data['month'];
        $counts[] = $data['count'];
    }

    // Get incident types distribution for additional chart
    $incidentTypes = $pdo->query("
        SELECT incident_type, COUNT(*) as count 
        FROM blotters 
        WHERE incident_type IS NOT NULL AND incident_type != ''
        GROUP BY incident_type 
        ORDER BY count DESC 
        LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);

    $incidentTypeLabels = [];
    $incidentTypeCounts = [];
    foreach ($incidentTypes as $type) {
        $incidentTypeLabels[] = $type['incident_type'];
        $incidentTypeCounts[] = $type['count'];
    }

    // Evidence types distribution
    $evidenceTypes = $pdo->query("SELECT IFNULL(evidence_type, 'Unknown') AS type, COUNT(*) AS count FROM evidence_records GROUP BY evidence_type ORDER BY count DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
    $evidenceTypeLabels = [];
    $evidenceTypeCounts = [];
    foreach ($evidenceTypes as $et) {
        $evidenceTypeLabels[] = $et['type'];
        $evidenceTypeCounts[] = $et['count'];
    }

    // Top officers by assigned cases
    $topOfficers = $pdo->query("SELECT ca.assigned_to as officer_id, COUNT(*) as count, s.fullname FROM case_assignments ca LEFT JOIN signup s ON ca.assigned_to = s.user_id WHERE ca.assigned_to IS NOT NULL GROUP BY ca.assigned_to ORDER BY count DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
    $officerLabels = [];
    $officerCounts = [];
    foreach ($topOfficers as $of) {
        $officerLabels[] = $of['fullname'] ?: ('User ' . $of['officer_id']);
        $officerCounts[] = $of['count'];
    }

    // Get user registration trend (last 12 months)
    $userRegistrationData = $pdo->query("
        SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count 
        FROM signup 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH) AND role != 'Admin'
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month
    ")->fetchAll(PDO::FETCH_ASSOC);

    $userMonths = [];
    $userCounts = [];
    foreach ($userRegistrationData as $data) {
        $userMonths[] = $data['month'];
        $userCounts[] = $data['count'];
    }

} catch (Exception $e) {
    error_log("Report error: " . $e->getMessage());
}
?>

<div class="main-content">
    <div class="content-container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h2">Reports & Analytics</h1>
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Back
            </a>
        </div>

        <!-- Statistics Overview -->
        <div class="row mb-4">
            <div class="col-md-6 mb-3">
                <div class="card h-100">
                    <div class="card-header">
                        <h5>User Statistics</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-sm-6 mb-3">
                                <div class="stat-box">
                                    <div class="stat-value text-primary"><?= $totalUsers ?></div>
                                    <div class="stat-label">Total Users</div>
                                </div>
                            </div>
                            <div class="col-sm-6 mb-3">
                                <div class="stat-box">
                                    <div class="stat-value text-success"><?= $verifiedUsers ?></div>
                                    <div class="stat-label">Email Verified</div>
                                </div>
                            </div>
                            <div class="col-sm-6 mb-3">
                                <div class="stat-box">
                                    <div class="stat-value text-warning"><?= $unverifiedUsers ?></div>
                                    <div class="stat-label">Unverified</div>
                                </div>
                            </div>
                            <div class="col-sm-6 mb-3">
                                <div class="stat-box">
                                    <div class="stat-value text-info"><?= $termsAcceptedUsers ?></div>
                                    <div class="stat-label">Terms Accepted</div>
                                </div>
                            </div>
                        </div>
                        <?php
                        $verificationRate = $totalUsers > 0 ? round(($verifiedUsers / $totalUsers) * 100, 2) : 0;
                        $acceptanceRate = $totalUsers > 0 ? round(($termsAcceptedUsers / $totalUsers) * 100, 2) : 0;
                        ?>
                        <div class="mt-3">
                            <div class="mb-2">
                                <div class="d-flex justify-content-between mb-1">
                                    <small>Verification Rate</small>
                                    <small class="fw-bold"><?= $verificationRate ?>%</small>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-success" style="width: <?= $verificationRate ?>%"></div>
                                </div>
                            </div>
                            <div class="mb-2">
                                <div class="d-flex justify-content-between mb-1">
                                    <small>Terms Acceptance Rate</small>
                                    <small class="fw-bold"><?= $acceptanceRate ?>%</small>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-info" style="width: <?= $acceptanceRate ?>%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6 mb-3">
                <div class="card h-100">
                    <div class="card-header">
                        <h5>Blotter Status Breakdown</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-sm-6 mb-3">
                                <div class="stat-box">
                                    <div class="stat-value text-warning"><?= $blottersByStatus['Pending'] ?></div>
                                    <div class="stat-label">Pending</div>
                                </div>
                            </div>
                            <div class="col-sm-6 mb-3">
                                <div class="stat-box">
                                    <div class="stat-value text-info"><?= $blottersByStatus['Under Investigation'] ?></div>
                                    <div class="stat-label">Investigating</div>
                                </div>
                            </div>
                            <div class="col-sm-6 mb-3">
                                <div class="stat-box">
                                    <div class="stat-value text-success"><?= $blottersByStatus['Resolved'] ?></div>
                                    <div class="stat-label">Resolved</div>
                                </div>
                            </div>
                            <div class="col-sm-6 mb-3">
                                <div class="stat-box">
                                    <div class="stat-value text-secondary"><?= $blottersByStatus['Archived'] ?></div>
                                    <div class="stat-label">Archived</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Priority Distribution -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>Blotter Priority Distribution</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span>
                                    <span class="badge bg-danger">High</span>
                                    <strong><?= $blottersByPriority['High'] ?></strong>
                                </span>
                            </div>
                            <div class="progress" style="height: 10px;">
                                <div class="progress-bar bg-danger" style="width: <?= $blottersByPriority['High'] > 0 ? min(100, ($blottersByPriority['High'] / array_sum($blottersByPriority)) * 100) : 0 ?>%"></div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span>
                                    <span class="badge bg-warning">Medium</span>
                                    <strong><?= $blottersByPriority['Medium'] ?></strong>
                                </span>
                            </div>
                            <div class="progress" style="height: 10px;">
                                <div class="progress-bar bg-warning" style="width: <?= $blottersByPriority['Medium'] > 0 ? min(100, ($blottersByPriority['Medium'] / array_sum($blottersByPriority)) * 100) : 0 ?>%"></div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span>
                                    <span class="badge bg-info">Low</span>
                                    <strong><?= $blottersByPriority['Low'] ?></strong>
                                </span>
                            </div>
                            <div class="progress" style="height: 10px;">
                                <div class="progress-bar bg-info" style="width: <?= $blottersByPriority['Low'] > 0 ? min(100, ($blottersByPriority['Low'] / array_sum($blottersByPriority)) * 100) : 0 ?>%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>Summary Statistics</h5>
                    </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush">
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <span>Total Blotters</span>
                                <span class="badge bg-primary rounded-pill"><?= array_sum($blottersByStatus) ?></span>
                            </div>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <span>Resolution Rate</span>
                                <span class="badge bg-success rounded-pill">
                                    <?php
                                    $totalBlotters = array_sum($blottersByStatus);
                                    echo $totalBlotters > 0 ? round(($blottersByStatus['Resolved'] / $totalBlotters) * 100, 2) : 0;
                                    ?>%
                                </span>
                            </div>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <span>Average Resolution Time</span>
                                <span class="badge bg-info rounded-pill">N/A</span>
                            </div>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <span>Total Users</span>
                                <span class="badge bg-secondary rounded-pill"><?= $totalUsers ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="row mb-4">
            <!-- User Verification Status Pie Chart -->
            <div class="col-lg-4 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h5><i class="bi bi-pie-chart"></i> User Verification Status</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="userVerificationChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Blotter Status Distribution Pie Chart -->
            <div class="col-lg-4 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h5><i class="bi bi-pie-chart"></i> Blotter Status Distribution</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="blotterStatusChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Incident Types Distribution Pie Chart -->
            <div class="col-lg-4 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h5><i class="bi bi-pie-chart"></i> Top Incident Types</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="incidentTypesChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Additional Analytics (Admin-only) -->
        <div class="row mb-4">
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h5><i class="bi bi-pie-chart"></i> Evidence Types Distribution</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="evidenceTypesChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h5><i class="bi bi-bar-chart"></i> Top Officers by Assigned Cases</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="topOfficersChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Area Charts Section -->
        <div class="row mb-4">
            <!-- Monthly Blotter Trend Area Chart -->
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h5><i class="bi bi-graph-up"></i> Monthly Blotter Creation Trend</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="monthlyTrendChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- User Registration Trend Area Chart -->
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h5><i class="bi bi-graph-up"></i> User Registration Trend</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="userRegistrationChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Export Options -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5>Export & Actions</h5>
                    </div>
                    <div class="card-body">
                        <button class="btn btn-outline-success" onclick="exportToCSV()">
                            <i class="bi bi-download"></i> Export as CSV
                        </button>
                        <button class="btn btn-outline-primary" onclick="window.print()">
                            <i class="bi bi-printer"></i> Print Report
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (empty($embed_in_dashboard)): ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<?php endif; ?>
    <style>
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }
        
        .stat-box {
            text-align: center;
            padding: 15px;
            border-radius: 8px;
            background-color: #f8f9fa;
            transition: all 0.3s ease;
        }
        
        .stat-box:hover {
            background-color: #e9ecef;
            transform: translateY(-2px);
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            line-height: 1;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 12px;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
    </style>

<script>
// Chart.js configuration and data
document.addEventListener('DOMContentLoaded', function() {
    // User Verification Status Pie Chart
    const userVerificationCtx = document.getElementById('userVerificationChart').getContext('2d');
    new Chart(userVerificationCtx, {
        type: 'pie',
        data: {
            labels: ['Email Verified', 'Unverified', 'Terms Accepted'],
            datasets: [{
                data: [<?= $verifiedUsers ?>, <?= $unverifiedUsers ?>, <?= $termsAcceptedUsers ?>],
                backgroundColor: [
                    'rgba(40, 167, 69, 0.8)',   // Success green
                    'rgba(255, 193, 7, 0.8)',   // Warning yellow
                    'rgba(23, 162, 184, 0.8)'   // Info blue
                ],
                borderColor: [
                    'rgba(40, 167, 69, 1)',
                    'rgba(255, 193, 7, 1)',
                    'rgba(23, 162, 184, 1)'
                ],
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 20,
                        usePointStyle: true
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = total > 0 ? ((context.parsed / total) * 100).toFixed(1) : 0;
                            return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
                        }
                    }
                }
            }
        }
    });

    // Blotter Status Distribution Pie Chart
    const blotterStatusCtx = document.getElementById('blotterStatusChart').getContext('2d');
    new Chart(blotterStatusCtx, {
        type: 'pie',
        data: {
            labels: ['Pending', 'Under Investigation', 'Resolved', 'Archived'],
            datasets: [{
                data: [<?= $blottersByStatus['Pending'] ?>, <?= $blottersByStatus['Under Investigation'] ?>, <?= $blottersByStatus['Resolved'] ?>, <?= $blottersByStatus['Archived'] ?>],
                backgroundColor: [
                    'rgba(255, 193, 7, 0.8)',   // Warning yellow
                    'rgba(23, 162, 184, 0.8)',  // Info blue
                    'rgba(40, 167, 69, 0.8)',   // Success green
                    'rgba(108, 117, 125, 0.8)'  // Secondary gray
                ],
                borderColor: [
                    'rgba(255, 193, 7, 1)',
                    'rgba(23, 162, 184, 1)',
                    'rgba(40, 167, 69, 1)',
                    'rgba(108, 117, 125, 1)'
                ],
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 20,
                        usePointStyle: true
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = total > 0 ? ((context.parsed / total) * 100).toFixed(1) : 0;
                            return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
                        }
                    }
                }
            }
        }
    });

    // Blotter Priority Distribution Pie Chart
    const blotterPriorityCtx = document.getElementById('blotterPriorityChart').getContext('2d');
    new Chart(blotterPriorityCtx, {
        type: 'pie',
        data: {
            labels: ['High Priority', 'Medium Priority', 'Low Priority'],
            datasets: [{
                data: [<?= $blottersByPriority['High'] ?>, <?= $blottersByPriority['Medium'] ?>, <?= $blottersByPriority['Low'] ?>],
                backgroundColor: [
                    'rgba(220, 53, 69, 0.8)',   // Danger red
                    'rgba(255, 193, 7, 0.8)',   // Warning yellow
                    'rgba(23, 162, 184, 0.8)'   // Info blue
                ],
                borderColor: [
                    'rgba(220, 53, 69, 1)',
                    'rgba(255, 193, 7, 1)',
                    'rgba(23, 162, 184, 1)'
                ],
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 20,
                        usePointStyle: true
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = total > 0 ? ((context.parsed / total) * 100).toFixed(1) : 0;
                            return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
                        }
                    }
                }
            }
        }
    });

    // Incident Types Pie Chart
    const incidentTypesCtx = document.getElementById('incidentTypesChart').getContext('2d');
    new Chart(incidentTypesCtx, {
        type: 'pie',
        data: {
            labels: <?= json_encode($incidentTypeLabels) ?>,
            datasets: [{
                data: <?= json_encode($incidentTypeCounts) ?>,
                backgroundColor: [
                    'rgba(255, 99, 132, 0.8)',
                    'rgba(54, 162, 235, 0.8)',
                    'rgba(255, 206, 86, 0.8)',
                    'rgba(75, 192, 192, 0.8)',
                    'rgba(153, 102, 255, 0.8)',
                    'rgba(255, 159, 64, 0.8)'
                ],
                borderWidth: 1
            }]
        },
        options: { responsive: true, maintainAspectRatio: false }
    });

    // Evidence Types Pie Chart
    const evidenceTypesCtx = document.getElementById('evidenceTypesChart').getContext('2d');
    new Chart(evidenceTypesCtx, {
        type: 'pie',
        data: {
            labels: <?= json_encode($evidenceTypeLabels) ?>,
            datasets: [{
                data: <?= json_encode($evidenceTypeCounts) ?>,
                backgroundColor: [
                    'rgba(99, 255, 132, 0.8)',
                    'rgba(54, 162, 235, 0.8)',
                    'rgba(255, 206, 86, 0.8)',
                    'rgba(153, 102, 255, 0.8)',
                    'rgba(255, 159, 64, 0.8)'
                ],
                borderWidth: 1
            }]
        },
        options: { responsive: true, maintainAspectRatio: false }
    });

    // Top Officers Bar Chart
    const topOfficersCtx = document.getElementById('topOfficersChart').getContext('2d');
    new Chart(topOfficersCtx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($officerLabels) ?>,
            datasets: [{
                label: 'Assigned Cases',
                data: <?= json_encode($officerCounts) ?>,
                backgroundColor: 'rgba(13, 110, 253, 0.8)'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: { ticks: { autoSkip: false } },
                y: { beginAtZero: true }
            }
        }
    });

    // Monthly Trend Area Chart
    const monthlyTrendCtx = document.getElementById('monthlyTrendChart').getContext('2d');
    new Chart(monthlyTrendCtx, {
        type: 'line',
        data: {
            labels: <?= json_encode($months) ?>,
            datasets: [{
                label: 'Blotters Created',
                data: <?= json_encode($counts) ?>,
                borderColor: 'rgba(13, 110, 253, 1)',
                backgroundColor: 'rgba(13, 110, 253, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: 'rgba(13, 110, 253, 1)',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 6,
                pointHoverRadius: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'top'
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                    callbacks: {
                        title: function(context) {
                            const date = new Date(context[0].label + '-01');
                            return date.toLocaleDateString('en-US', { year: 'numeric', month: 'long' });
                        }
                    }
                }
            },
            scales: {
                x: {
                    display: true,
                    title: {
                        display: true,
                        text: 'Month'
                    },
                    grid: {
                        display: false
                    }
                },
                y: {
                    display: true,
                    title: {
                        display: true,
                        text: 'Number of Blotters'
                    },
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0, 0, 0, 0.1)'
                    }
                }
            },
            interaction: {
                intersect: false,
                mode: 'index'
            }
        }
    });
});

function exportToCSV() {
    alert('Export functionality will be implemented soon!');
}
</script>

<?php require_once '../includes/footer.php'; ?>
