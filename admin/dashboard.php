<?php
/**
 * Admin Dashboard
 * Synced with EMERGENCY-COM design template: gradient analytics cards,
 * chart containers, quick actions strip, and recent activity feed.
 */
require_once 'admin_auth.php';
if (!isset($pdo) || !($pdo instanceof PDO)) {
    $pdo = getDBConnection();
}

$base_url = '../';
$page_title = 'Dashboard';

// Fetch admin statistics
try {
    $totalUsers      = $pdo->query("SELECT COUNT(*) FROM signup WHERE role != 'Admin'")->fetchColumn();
    $totalBlotters   = $pdo->query("SELECT COUNT(*) FROM blotters")->fetchColumn();
    $pendingBlotters = $pdo->query("SELECT COUNT(*) FROM blotters WHERE status = 'Pending'")->fetchColumn();
    $verifiedUsers   = $pdo->query("SELECT COUNT(*) FROM signup WHERE email_verified = 1 AND role != 'Admin'")->fetchColumn();
    $totalCases      = 0;
    try { $totalCases = $pdo->query("SELECT COUNT(*) FROM incidents")->fetchColumn(); } catch (Exception $e) {}
} catch (Exception $e) {
    $totalUsers = $totalBlotters = $pendingBlotters = $verifiedUsers = $totalCases = 0;
}

// Recent Blotters
try {
    $recentBlotters = $pdo->query("SELECT id, blotter_no, complainant_name, status, created_at FROM blotters ORDER BY created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $recentBlotters = []; }

// Recent Signups
try {
    $recentUsers = $pdo->query("SELECT user_id, fullname, emailadd, email_verified, created_at FROM signup WHERE role != 'Admin' ORDER BY created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $recentUsers = []; }

// Blotter status breakdown for chart
try {
    $statusBreakdown = $pdo->query("SELECT status, COUNT(*) as cnt FROM blotters GROUP BY status")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $statusBreakdown = []; }

// Weekly blotter trend (last 7 days)
try {
    $weeklyTrend = $pdo->query("
        SELECT DATE(created_at) as day, COUNT(*) as cnt 
        FROM blotters 
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
        GROUP BY DATE(created_at)
        ORDER BY day ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $weeklyTrend = []; }

$admin_username = $_SESSION['first_name'] ?? $_SESSION['username'] ?? 'Admin';

require_once '../includes/header.php';
?>

<!-- Dashboard CSS (EMERGENCY-COM synced) -->
<link rel="stylesheet" href="<?php echo $base_url; ?>assets/css/module-dashboard.css">

<div class="main-content">
    <div class="main-container">
        <!-- Page Title -->
        <div class="title">
            <div class="dashboard-admin-chip">
                <i class="fas fa-user-circle"></i>
                <strong>Admin:</strong>
                <span><?php echo htmlspecialchars($admin_username); ?></span>
            </div>
            <h1>
                Dashboard
                <span class="help-tooltip">
                    <i class="fas fa-question-circle"></i>
                    <span class="tooltip-text">Operational snapshot of the Law Enforcement & Incident Report system.</span>
                </span>
            </h1>
            <p>System overview for incident management, case tracking, and blotter operations.</p>
        </div>

        <div class="sub-container">
            <div class="page-content">

                <!-- ====== Analytics Strip (Gradient Cards) ====== -->
                <section class="dashboard-analytics-strip" aria-label="Dashboard analytics">
                    <div class="dashboard-analytics-grid">
                        <article class="dashboard-analytics-card analytics-tone-subs">
                            <div class="dashboard-analytics-head">
                                <span class="dashboard-analytics-label">Total Users</span>
                                <span class="dashboard-analytics-icon"><i class="fas fa-users"></i></span>
                            </div>
                            <div class="dashboard-analytics-value"><?php echo $totalUsers; ?></div>
                            <div class="dashboard-analytics-sub">Registered accounts</div>
                        </article>

                        <article class="dashboard-analytics-card analytics-tone-notif">
                            <div class="dashboard-analytics-head">
                                <span class="dashboard-analytics-label">Total Blotters</span>
                                <span class="dashboard-analytics-icon"><i class="fas fa-clipboard-list"></i></span>
                            </div>
                            <div class="dashboard-analytics-value"><?php echo $totalBlotters; ?></div>
                            <div class="dashboard-analytics-sub">All recorded blotters</div>
                        </article>

                        <article class="dashboard-analytics-card analytics-tone-pending">
                            <div class="dashboard-analytics-head">
                                <span class="dashboard-analytics-label">Pending Blotters</span>
                                <span class="dashboard-analytics-icon"><i class="fas fa-clock"></i></span>
                            </div>
                            <div class="dashboard-analytics-value"><?php echo $pendingBlotters; ?></div>
                            <div class="dashboard-analytics-sub">Awaiting action</div>
                        </article>

                        <article class="dashboard-analytics-card analytics-tone-success">
                            <div class="dashboard-analytics-head">
                                <span class="dashboard-analytics-label">Verified Users</span>
                                <span class="dashboard-analytics-icon"><i class="fas fa-user-check"></i></span>
                            </div>
                            <div class="dashboard-analytics-value"><?php echo $verifiedUsers; ?></div>
                            <div class="dashboard-analytics-sub">Email verified</div>
                        </article>
                    </div>
                </section>

                <!-- ====== Charts Grid (2x2) ====== -->
                <section class="dashboard-graph-grid" aria-label="Operational graphs">
                    <!-- Blotter Trend (7 Days) -->
                    <div class="chart-container">
                        <div class="chart-title">
                            <i class="fas fa-chart-line"></i> Blotter Trend (7 Days)
                            <span class="help-tooltip">
                                <i class="fas fa-question-circle"></i>
                                <span class="tooltip-text">Daily blotter filing volume for the past week.</span>
                            </span>
                        </div>
                        <div class="chart-canvas-wrapper">
                            <canvas id="blotterTrendChart"></canvas>
                        </div>
                    </div>

                    <!-- Blotter Status Distribution -->
                    <div class="chart-container">
                        <div class="chart-title">
                            <i class="fas fa-chart-pie"></i> Blotter Status
                            <span class="help-tooltip">
                                <i class="fas fa-question-circle"></i>
                                <span class="tooltip-text">Distribution of blotter statuses across all records.</span>
                            </span>
                        </div>
                        <div class="chart-canvas-wrapper">
                            <canvas id="statusChart"></canvas>
                        </div>
                    </div>

                    <!-- User Verification -->
                    <div class="chart-container">
                        <div class="chart-title">
                            <i class="fas fa-chart-column"></i> User Verification
                            <span class="help-tooltip">
                                <i class="fas fa-question-circle"></i>
                                <span class="tooltip-text">Breakdown of verified vs unverified user accounts.</span>
                            </span>
                        </div>
                        <div class="chart-canvas-wrapper">
                            <canvas id="verificationChart"></canvas>
                        </div>
                    </div>

                    <!-- Monthly Cases -->
                    <div class="chart-container">
                        <div class="chart-title">
                            <i class="fas fa-wave-square"></i> Case Activity
                            <span class="help-tooltip">
                                <i class="fas fa-question-circle"></i>
                                <span class="tooltip-text">Active case tracking and incident trend overview.</span>
                            </span>
                        </div>
                        <div class="chart-canvas-wrapper">
                            <canvas id="caseActivityChart"></canvas>
                        </div>
                    </div>
                </section>

                <!-- ====== Quick Actions Strip ====== -->
                <div class="chart-container chart-container-actions">
                    <div class="chart-title">
                        <i class="fas fa-bolt"></i> Quick Actions
                        <span class="help-tooltip">
                            <i class="fas fa-question-circle"></i>
                            <span class="tooltip-text">Fast links to the most used operational modules.</span>
                        </span>
                    </div>
                    <div class="quick-actions">
                        <a href="users.php" class="quick-action-btn">
                            <i class="fas fa-users"></i>
                            <strong>User Management</strong>
                            <small>Manage all accounts</small>
                        </a>
                        <a href="blotters.php" class="quick-action-btn">
                            <i class="fas fa-clipboard-list"></i>
                            <strong>Blotter Records</strong>
                            <small>View all blotter entries</small>
                        </a>
                        <a href="cases.php" class="quick-action-btn">
                            <i class="fas fa-briefcase"></i>
                            <strong>Case Tracking</strong>
                            <small>Assign & track cases</small>
                        </a>
                        <a href="reports.php" class="quick-action-btn">
                            <i class="fas fa-chart-line"></i>
                            <strong>Reports</strong>
                            <small>Analytics & statistics</small>
                        </a>
                        <a href="add_admin.php" class="quick-action-btn">
                            <i class="fas fa-user-shield"></i>
                            <strong>Create Admin</strong>
                            <small>Provision admin accounts</small>
                        </a>
                        <a href="settings.php" class="quick-action-btn">
                            <i class="fas fa-cog"></i>
                            <strong>Settings</strong>
                            <small>System configuration</small>
                        </a>
                    </div>
                </div>

                <!-- ====== Recent Activity (2-column) ====== -->
                <section class="dashboard-graph-grid" style="margin-top: 1rem;">
                    <!-- Recent Blotters -->
                    <div class="recent-activity">
                        <div class="chart-title">
                            <i class="fas fa-clipboard-list"></i> Recent Blotters
                            <span class="help-tooltip">
                                <i class="fas fa-question-circle"></i>
                                <span class="tooltip-text">Latest blotter entries filed in the system.</span>
                            </span>
                        </div>
                        <?php if (!empty($recentBlotters)): ?>
                            <?php foreach ($recentBlotters as $b): ?>
                                <div class="activity-item">
                                    <div class="activity-icon">
                                        <i class="fas fa-file-alt"></i>
                                    </div>
                                    <div class="activity-content">
                                        <div class="activity-title">
                                            <?php echo htmlspecialchars($b['blotter_no']); ?> — <?php echo htmlspecialchars(substr($b['complainant_name'], 0, 25)); ?>
                                        </div>
                                        <div class="activity-time">
                                            <span class="badge" style="background: <?php 
                                                echo $b['status'] === 'Pending' ? '#f59e0b' : ($b['status'] === 'Resolved' ? '#16a34a' : '#3b82f6');
                                            ?>; color: #fff; font-size: 0.68rem;"><?php echo htmlspecialchars($b['status']); ?></span>
                                            · <?php echo date('M d, Y', strtotime($b['created_at'])); ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="activity-empty">No recent blotters found.</p>
                        <?php endif; ?>
                    </div>

                    <!-- Recent Signups -->
                    <div class="recent-activity">
                        <div class="chart-title">
                            <i class="fas fa-user-plus"></i> Recent Signups
                            <span class="help-tooltip">
                                <i class="fas fa-question-circle"></i>
                                <span class="tooltip-text">Latest user registrations in the system.</span>
                            </span>
                        </div>
                        <?php if (!empty($recentUsers)): ?>
                            <?php foreach ($recentUsers as $u): ?>
                                <div class="activity-item">
                                    <div class="activity-icon">
                                        <i class="fas fa-user"></i>
                                    </div>
                                    <div class="activity-content">
                                        <div class="activity-title"><?php echo htmlspecialchars($u['fullname']); ?></div>
                                        <div class="activity-time">
                                            <?php echo htmlspecialchars($u['emailadd']); ?> ·
                                            <?php if ($u['email_verified']): ?>
                                                <span class="badge" style="background: #16a34a; color: #fff; font-size: 0.68rem;">Verified</span>
                                            <?php else: ?>
                                                <span class="badge" style="background: #f59e0b; color: #fff; font-size: 0.68rem;">Unverified</span>
                                            <?php endif; ?>
                                            · <?php echo date('M d, Y', strtotime($u['created_at'])); ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="activity-empty">No recent signups found.</p>
                        <?php endif; ?>
                    </div>
                </section>

            </div>
        </div>
    </div>
</div>

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const rootStyles = getComputedStyle(document.documentElement);
    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    const gridColor = isDark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.06)';
    const textColor = isDark ? '#d6e9e6' : '#1f2e2d';

    // --- Blotter Trend Chart ---
    const trendLabels = <?php
        $days = [];
        $vals = [];
        $trendMap = [];
        foreach ($weeklyTrend as $t) { $trendMap[$t['day']] = (int)$t['cnt']; }
        for ($i = 6; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-{$i} days"));
            $days[] = date('D', strtotime($d));
            $vals[] = $trendMap[$d] ?? 0;
        }
        echo json_encode($days);
    ?>;
    const trendData = <?php echo json_encode($vals); ?>;

    new Chart(document.getElementById('blotterTrendChart'), {
        type: 'line',
        data: {
            labels: trendLabels,
            datasets: [{
                label: 'Blotters Filed',
                data: trendData,
                borderColor: '#2563eb',
                backgroundColor: 'rgba(37, 99, 235, 0.12)',
                fill: true,
                tension: 0.4,
                pointRadius: 4,
                pointBackgroundColor: '#2563eb',
                borderWidth: 2.5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, grid: { color: gridColor }, ticks: { color: textColor, stepSize: 1 } },
                x: { grid: { display: false }, ticks: { color: textColor } }
            }
        }
    });

    // --- Blotter Status Doughnut ---
    const statusLabels = <?php echo json_encode(array_column($statusBreakdown, 'status')); ?>;
    const statusValues = <?php echo json_encode(array_map('intval', array_column($statusBreakdown, 'cnt'))); ?>;
    const statusColors = statusLabels.map(s => {
        if (s === 'Pending') return '#f59e0b';
        if (s === 'Resolved' || s === 'Settled') return '#16a34a';
        if (s === 'Dismissed') return '#ef4444';
        return '#3b82f6';
    });

    new Chart(document.getElementById('statusChart'), {
        type: 'doughnut',
        data: {
            labels: statusLabels,
            datasets: [{ data: statusValues, backgroundColor: statusColors, borderWidth: 2, borderColor: isDark ? '#1a1a2e' : '#fff' }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { color: textColor, padding: 12 } }
            }
        }
    });

    // --- User Verification Bar ---
    new Chart(document.getElementById('verificationChart'), {
        type: 'bar',
        data: {
            labels: ['Verified', 'Unverified'],
            datasets: [{
                data: [<?php echo $verifiedUsers; ?>, <?php echo $totalUsers - $verifiedUsers; ?>],
                backgroundColor: ['#16a34a', '#f59e0b'],
                borderRadius: 8,
                maxBarThickness: 60
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, grid: { color: gridColor }, ticks: { color: textColor, stepSize: 1 } },
                x: { grid: { display: false }, ticks: { color: textColor } }
            }
        }
    });

    // --- Case Activity Area ---
    new Chart(document.getElementById('caseActivityChart'), {
        type: 'bar',
        data: {
            labels: ['Blotters', 'Cases', 'Pending', 'Users'],
            datasets: [{
                label: 'Count',
                data: [<?php echo $totalBlotters; ?>, <?php echo $totalCases; ?>, <?php echo $pendingBlotters; ?>, <?php echo $totalUsers; ?>],
                backgroundColor: ['#3b82f6', '#7c3aed', '#f97316', '#16a34a'],
                borderRadius: 8,
                maxBarThickness: 50
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, grid: { color: gridColor }, ticks: { color: textColor, stepSize: 1 } },
                x: { grid: { display: false }, ticks: { color: textColor } }
            }
        }
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>
