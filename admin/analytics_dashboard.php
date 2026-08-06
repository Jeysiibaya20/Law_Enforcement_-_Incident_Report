<?php
/**
 * analytics_dashboard.php - Admin Analytics Dashboard
 * 
 * Interactive dashboard showing incident analytics, trends, and decision-making insights
 */

require_once 'admin_auth.php';
$base_url = '../';
$page_title = 'Analytics Dashboard';
$body_class = 'analytics-page';
$additional_head = '<link href="' . $base_url . 'assets/css/analytics.css" rel="stylesheet">';
require_once '../includes/header.php';
require_once '../includes/navbar.php';

require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../modules/AnalyticsEngine.php';
require_once __DIR__ . '/../modules/IncidentReportTemplate.php';

// Check authentication
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['Admin', 'Investigator', 'Barangay Official'])) {
    header('Location: ../auth/login.php');
    exit;
}

$analytics = new AnalyticsEngine($pdo);

// Set date range from query parameters
$from = $_GET['from'] ?? date('Y-01-01');
$to = $_GET['to'] ?? date('Y-m-d');

$analytics->setDateRange($from, $to);

$dashboard_data = $analytics->getDashboardAnalytics();
$insights = $analytics->getInsights();
$forecast = $analytics->getForecast();

$incidentTypeLabels = array_column($dashboard_data['case_types'], 'incident_type');
$incidentTypeCounts = array_column($dashboard_data['case_types'], 'count');
$threatLabels = array_column($dashboard_data['threat_analysis'], 'threat_level');
$threatCounts = array_column($dashboard_data['threat_analysis'], 'count');
$trendMonths = array_column($dashboard_data['trends'], 'month');
$trendCounts = array_column($dashboard_data['trends'], 'incident_count');
?>

    <div class="main-content">
        <div class="analytics-header">
            <div class="analytics-title">
                <i class="bi bi-graph-up"></i>
            Analytics Dashboard
        </div>

        <div class="date-filter-section">
            <div class="date-filter">
                <label for="dateFrom" class="form-label fw-bold">Period:</label>
                <input type="date" id="dateFrom" class="form-control" value="<?php echo $from; ?>">
                <span class="fw-bold text-muted">to</span>
                <input type="date" id="dateTo" class="form-control" value="<?php echo $to; ?>">
                <button onclick="applyFilter()" class="btn btn-primary">
                    <i class="bi bi-search"></i> Apply Filter
                </button>
                <button onclick="exportReport()" class="btn btn-success">
                    <i class="bi bi-download"></i> Export Report
                </button>
                <button onclick="window.print()" class="btn btn-secondary">
                    <i class="bi bi-printer"></i> Print
                </button>
            </div>
        </div>
    </div>
    
        <!-- Quick Incident Log -->
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0">✍️ Quick Incident Log</h3>
            </div>
            <div class="card-body">
                <form id="quickIncidentForm">
                <div class="row g-2">
                    <div class="col-md-4">
                        <input type="text" id="qi_reporter" name="reporter_name" class="form-control" placeholder="Reporter name" value="<?php echo htmlspecialchars($_SESSION['fullname'] ?? ''); ?>">
                    </div>
                    <div class="col-md-3">
                        <select id="qi_type" name="incident_type" class="form-select">
                            <option value="Other">Type: Other</option>
                            <option value="Abuse">Abuse</option>
                            <option value="Neglect">Neglect</option>
                            <option value="Violence">Violence</option>
                            <option value="Theft">Theft</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <input type="date" id="qi_date" name="incident_date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="col-md-12 mt-2">
                        <textarea id="qi_narrative" name="narrative" class="form-control" rows="3" placeholder="Brief description of incident (required)"></textarea>
                    </div>
                    <div class="col-md-12 mt-2 d-flex gap-2">
                        <button type="button" id="qi_submit" class="btn btn-primary">Submit Quick Log</button>
                        <button type="reset" class="btn btn-secondary">Reset</button>
                        <div id="qi_status" class="ms-3 align-self-center"></div>
                    </div>
                </div>
                </form>
            </div>
        </div>
        
        <!-- Key Metrics -->
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0">📊 Key Metrics</h3>
            </div>
            <div class="card-body">
        <div class="metrics-grid">
            <div class="metric-card">
                <div class="metric-header">
                    <div class="metric-icon">
                        <i class="bi bi-file-earmark-text"></i>
                    </div>
                    <div class="metric-value"><?php echo $dashboard_data['summary']['total_incidents']; ?></div>
                    <div class="metric-label">Total Incidents</div>
                    <div class="metric-subtext">Period: <?php echo $from; ?> to <?php echo $to; ?></div>
                </div>
            </div>

            <div class="metric-card critical">
                <div class="metric-header">
                    <div class="metric-icon">
                        <i class="bi bi-exclamation-triangle"></i>
                    </div>
                    <div class="metric-value"><?php echo $dashboard_data['summary']['critical_cases']; ?></div>
                    <div class="metric-label">Critical Cases (HIGH)</div>
                    <div class="metric-subtext"><?php
                        $percent = $dashboard_data['summary']['total_incidents'] > 0
                            ? round(($dashboard_data['summary']['critical_cases'] / $dashboard_data['summary']['total_incidents']) * 100, 1)
                            : 0;
                        echo $percent . '% of total';
                    ?></div>
                </div>
            </div>

            <div class="metric-card">
                <div class="metric-header">
                    <div class="metric-icon">
                        <i class="bi bi-person-check"></i>
                    </div>
                    <div class="metric-value"><?php echo $dashboard_data['summary']['assignment_rate']; ?>%</div>
                    <div class="metric-label">Case Assignment Rate</div>
                    <div class="metric-subtext"><?php echo $dashboard_data['summary']['total_cases']; ?> cases assigned</div>
                </div>
            </div>

            <div class="metric-card success">
                <div class="metric-header">
                    <div class="metric-icon">
                        <i class="bi bi-check-circle"></i>
                    </div>
                    <div class="metric-value"><?php echo $dashboard_data['summary']['closed_cases']; ?></div>
                    <div class="metric-label">Closed Cases</div>
                    <div class="metric-subtext">Complete and resolved</div>
                </div>
            </div>

            <div class="metric-card">
                <div class="metric-header">
                    <div class="metric-icon">
                        <i class="bi bi-speedometer2"></i>
                    </div>
                    <div class="metric-value"><?php echo round($dashboard_data['summary']['average_severity'], 1); ?>/100</div>
                    <div class="metric-label">Average Severity Score</div>
                    <div class="metric-subtext">AI-powered NLP analysis</div>
                </div>
            </div>

            <div class="metric-card">
                <div class="metric-header">
                    <div class="metric-icon">
                        <i class="bi bi-people"></i>
                    </div>
                    <div class="metric-value"><?php echo $dashboard_data['summary']['active_officers']; ?></div>
                    <div class="metric-label">Active Officers</div>
                    <div class="metric-subtext">Currently assigned</div>
                </div>
            </div>
        </div>
            </div>
        </div>

        <!-- Analytics charts -->
        <div class="row mb-4">
            <div class="col-lg-4 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-pie-chart-fill me-2"></i> Incident Type Distribution</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="incidentTypeChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-pie-chart-fill me-2"></i> Threat Level Distribution</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="threatLevelChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-graph-up me-2"></i> Monthly Incidents Trend</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="monthlyIncidentTrendChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Insights & Recommendations -->
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0"><i class="bi bi-lightbulb"></i> Key Insights & Recommendations</h3>
            </div>
            <div class="card-body">
            <div class="insights-body">
                <?php if (empty($insights)): ?>
                    <div class="insight-item info">
                        <div class="insight-title">No Issues Detected</div>
                        <div class="insight-message">All metrics are within normal ranges. Keep up the good work!</div>
                    </div>
                <?php else: ?>
                    <?php foreach ($insights as $insight): ?>
                        <div class="insight-item <?php echo $insight['type']; ?>">
                            <div class="insight-title"><?php echo htmlspecialchars($insight['title']); ?></div>
                            <div class="insight-message"><?php echo htmlspecialchars($insight['message']); ?></div>
                            <div class="insight-action">💡 <?php echo htmlspecialchars($insight['action']); ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            </div>
        </div>
        
        <!-- Trends -->
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0"><i class="bi bi-graph-up"></i> Monthly Trends</h3>
            </div>
            <div class="card-body">
                <table class="table table-striped table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>Month</th>
                            <th>Incidents</th>
                            <th>Critical</th>
                            <th>Avg Severity</th>
                            <th>Cases</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dashboard_data['trends'] as $trend): ?>
                            <tr>
                                <td><?php echo date('F Y', strtotime($trend['month'] . '-01')); ?></td>
                                <td><strong><?php echo $trend['incident_count']; ?></strong></td>
                                <td><?php echo $trend['critical_count']; ?></td>
                                <td><?php echo $trend['avg_severity']; ?>/100</td>
                                <td><?php echo $trend['case_count']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            </div>
        </div>
        
        <!-- Case Type Analysis -->
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0"><i class="bi bi-bar-chart"></i> Cases by Type</h3>
            </div>
            <div class="card-body">
                <?php foreach ($dashboard_data['case_types'] as $type): ?>
                    <div class="chart-bar">
                        <div class="chart-bar-label"><?php echo htmlspecialchars($type['incident_type']); ?></div>
                        <div class="chart-bar-fill" style="width: <?php echo ($type['count'] / max(array_column($dashboard_data['case_types'], 'count')) * 100); ?>%">
                            <?php echo $type['count']; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            </div>
        </div>
        
        <!-- Child Incident Analysis -->
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0">👶 Child-Related Incident Trends</h3>
            </div>
            <div class="card-body">
            <table class="trend-table">
                <tr>
                    <th>Month</th>
                    <th>Child Incidents</th>
                    <th>Total Incidents</th>
                    <th>% Child-Related</th>
                </tr>
                <?php 
                $child_trends = $analytics->getChildIncidentTrend();
                foreach ($child_trends as $trend): 
                ?>
                    <tr>
                        <td><?php echo date('F Y', strtotime($trend['month'] . '-01')); ?></td>
                        <td><?php echo $trend['child_incidents']; ?></td>
                        <td><?php echo $trend['total_incidents']; ?></td>
                        <td><strong><?php echo $trend['child_incident_percentage']; ?>%</strong></td>
                    </tr>
                <?php endforeach; ?>
            </table>
            </div>
        </div>
        
        <!-- Officer Performance -->
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0">👮 Officer Performance</h3>
            </div>
            <div class="card-body">
            <table class="trend-table">
                <tr>
                    <th>Officer Name</th>
                    <th>Cases Assigned</th>
                    <th>Closed Cases</th>
                    <th>Closure Rate</th>
                    <th>Avg Severity</th>
                </tr>
                <?php 
                $officers = $analytics->getOfficerPerformance();
                foreach ($officers as $officer): 
                ?>
                    <tr>
                        <td><?php echo htmlspecialchars($officer['fullname']); ?></td>
                        <td><?php echo $officer['assigned_cases']; ?></td>
                        <td><?php echo $officer['closed_cases']; ?></td>
                        <td><?php echo $officer['closure_rate']; ?>%</td>
                        <td><?php echo $officer['avg_case_severity']; ?>/100</td>
                    </tr>
                <?php endforeach; ?>
            </table>
            </div>
        </div>
        
        <!-- Threat Level Distribution -->
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0"><i class="bi bi-shield-exclamation"></i> Threat Level Distribution</h3>
            </div>
            <div class="card-body">
                <?php
                $threat_data = $analytics->getThreatAnalysis();
                // Defensive: ensure we have at least one element before calling max()
                if (empty($threat_data) || !is_array($threat_data)) {
                    $threat_data = [];
                    $max_threat = 1;
                } else {
                    $counts = array_column($threat_data, 'count');
                    $max_threat = (!empty($counts) && is_array($counts)) ? max($counts) : 1;
                }
                foreach ($threat_data as $threat):
                ?>
                    <div class="chart-bar">
                        <div class="chart-bar-label"><?php echo $threat['threat_level'] ?? 'Unknown'; ?></div>
                        <div class="chart-bar-fill <?php echo strtolower($threat['threat_level'] ?? 'info'); ?>"
                             style="width: <?php echo ($threat['count'] / $max_threat * 100); ?>%">
                            <?php echo $threat['count']; ?> (<?php echo $threat['percentage']; ?>%)
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            </div>
        </div>
        
        <!-- Forecast -->
        <?php if ($forecast): ?>
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0"><i class="bi bi-graph-up-arrow"></i> Forecast for Next Period</h3>
            </div>
            <div class="card-body">
                    <div class="forecast-box">
                        <div class="forecast-trend">
                            <?php
                            if ($forecast['trend_direction'] === 'increasing') {
                                echo '📈 ' . abs($forecast['growth_rate']) . '%';
                            } else {
                                echo '📉 ' . abs($forecast['growth_rate']) . '%';
                            }
                            ?>
                        </div>
                        <div class="forecast-label">
                            Expected trend:
                            <strong><?php echo ucfirst($forecast['trend_direction']); ?></strong>
                            <br>
                            Estimated incidents next period:
                            <strong><?php echo $forecast['estimated_next_month']; ?></strong>
                        </div>
            </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Report Generation Buttons -->
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0"><i class="bi bi-file-earmark-text"></i> Generate Reports</h3>
            </div>
            <div class="card-body">
                <div class="report-buttons">
                    <button onclick="generateIncidentReport()" class="btn btn-primary">
                        <i class="bi bi-clipboard-data"></i> Incident Report
                    </button>
                    <button onclick="generateCaseReport()" class="btn btn-primary">
                        <i class="bi bi-folder"></i> Case Report
                    </button>
                    <button onclick="generateBCPCReport()" class="btn btn-primary">
                        <i class="bi bi-person"></i> BCPC Report
                    </button>
                    <button onclick="exportCSV()" class="btn btn-success">
                        <i class="bi bi-filetype-csv"></i> Export to CSV
                    </button>
            </div>
            </div>
        </div>
    </div>
    </div>
    <script>
        function applyFilter() {
            const from = document.getElementById('dateFrom').value;
            const to = document.getElementById('dateTo').value;
            window.location.href = `?from=${from}&to=${to}`;
        }
        
        function generateIncidentReport() {
            window.location.href = 'generate_report.php?type=incident';
        }
        
        function generateCaseReport() {
            window.location.href = 'generate_report.php?type=case';
        }
        
        function generateBCPCReport() {
            window.location.href = 'generate_report.php?type=bcpc';
        }
        
        function exportCSV() {
            const from = document.getElementById('dateFrom').value;
            const to = document.getElementById('dateTo').value;
            window.location.href = `export_report.php?type=csv&from=${from}&to=${to}`;
        }
        
        function exportReport() {
            const from = document.getElementById('dateFrom').value;
            const to = document.getElementById('dateTo').value;
            window.location.href = `generate_report.php?type=export&from=${from}&to=${to}`;
        }
        
        // Quick Incident Log submission
        document.addEventListener('DOMContentLoaded', function() {
            const qiSubmit = document.getElementById('qi_submit');
            if (!qiSubmit) return;
            qiSubmit.addEventListener('click', async function() {
                const statusEl = document.getElementById('qi_status');
                statusEl.textContent = 'Submitting...';
                qiSubmit.disabled = true;
                const payload = {
                    reporter_name: document.getElementById('qi_reporter').value,
                    incident_type: document.getElementById('qi_type').value,
                    incident_date: document.getElementById('qi_date').value,
                    narrative: document.getElementById('qi_narrative').value
                };
                try {
                    const res = await fetch('../modules/incident_submit.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload)
                    });
                    const data = await res.json();
                    if (res.ok && data.success) {
                        statusEl.innerHTML = `<span class="text-success">✅ Logged (Case: ${data.case_no})</span>`;
                        document.getElementById('quickIncidentForm').reset();
                    } else {
                        statusEl.innerHTML = `<span class="text-danger">❌ ${data.error || 'Failed'}</span>`;
                    }
                } catch (err) {
                    statusEl.innerHTML = `<span class="text-danger">❌ ${err.message}</span>`;
                } finally {
                    qiSubmit.disabled = false;
                    setTimeout(() => { statusEl.textContent = ''; }, 8000);
                }
            });
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const incidentTypeCtx = document.getElementById('incidentTypeChart').getContext('2d');
        new Chart(incidentTypeCtx, {
            type: 'pie',
            data: {
                labels: <?= json_encode($incidentTypeLabels) ?>,
                datasets: [{
                    data: <?= json_encode($incidentTypeCounts) ?>,
                    backgroundColor: [
                        'rgba(54, 162, 235, 0.8)',
                        'rgba(255, 99, 132, 0.8)',
                        'rgba(255, 205, 86, 0.8)',
                        'rgba(75, 192, 192, 0.8)',
                        'rgba(153, 102, 255, 0.8)',
                        'rgba(255, 159, 64, 0.8)'
                    ],
                    borderColor: '#fff',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { usePointStyle: true, padding: 16 } }
                }
            }
        });

        const threatLevelCtx = document.getElementById('threatLevelChart').getContext('2d');
        new Chart(threatLevelCtx, {
            type: 'pie',
            data: {
                labels: <?= json_encode($threatLabels) ?>,
                datasets: [{
                    data: <?= json_encode($threatCounts) ?>,
                    backgroundColor: [
                        'rgba(220, 53, 69, 0.8)',
                        'rgba(40, 167, 69, 0.8)'
                    ],
                    borderColor: '#fff',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { usePointStyle: true, padding: 16 } }
                }
            }
        });

        const monthlyTrendCtx = document.getElementById('monthlyIncidentTrendChart').getContext('2d');
        new Chart(monthlyTrendCtx, {
            type: 'line',
            data: {
                labels: <?= json_encode($trendMonths) ?>,
                datasets: [{
                    label: 'Incidents',
                    data: <?= json_encode($trendCounts) ?>,
                    borderColor: 'rgba(13, 110, 253, 1)',
                    backgroundColor: 'rgba(13, 110, 253, 0.1)',
                    fill: true,
                    tension: 0.35,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    borderWidth: 3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    x: { grid: { display: false } },
                    y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.08)' } }
                },
                interaction: { intersect: false, mode: 'index' }
            }
        });
    });
    </script>
    <?php include __DIR__ . '/../includes/footer.php'; ?>
