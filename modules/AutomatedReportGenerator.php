<?php
/**
 * AutomatedReportGenerator - Automated Report Generation System
 *
 * Handles scheduled report generation, batch processing, and automated distribution
 */

require_once __DIR__ . '/EnhancedReportTemplates.php';

class AutomatedReportGenerator {

    private $pdo;
    private $template_engine;
    private $schedule_config = [];
    private $log_file;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->template_engine = new EnhancedReportTemplates($pdo);
        $this->log_file = __DIR__ . '/../logs/automated_reports.log';

        // Ensure log directory exists
        $log_dir = dirname($this->log_file);
        if (!is_dir($log_dir)) {
            mkdir($log_dir, 0755, true);
        }

        $this->initializeScheduleConfig();
    }

    /**
     * Initialize default schedule configuration
     */
    private function initializeScheduleConfig() {
        $this->schedule_config = [
            'daily_incident_summary' => [
                'enabled' => true,
                'frequency' => 'daily',
                'time' => '08:00',
                'recipients' => ['admin@example.com'],
                'template' => 'incident_summary'
            ],
            'weekly_analytics_report' => [
                'enabled' => true,
                'frequency' => 'weekly',
                'day' => 'monday',
                'time' => '09:00',
                'recipients' => ['admin@example.com', 'supervisor@example.com'],
                'template' => 'analytics'
            ],
            'monthly_case_analysis' => [
                'enabled' => true,
                'frequency' => 'monthly',
                'day' => 1,
                'time' => '10:00',
                'recipients' => ['admin@example.com', 'management@example.com'],
                'template' => 'case_analysis'
            ],
            'quarterly_decision_report' => [
                'enabled' => true,
                'frequency' => 'quarterly',
                'time' => '11:00',
                'recipients' => ['admin@example.com', 'board@example.com'],
                'template' => 'decision_making'
            ]
        ];
    }

    /**
     * Generate daily incident summary report
     */
    public function generateDailyIncidentSummary() {
        $this->log("Starting daily incident summary generation");

        try {
            // Set date range to yesterday
            $yesterday = date('Y-m-d', strtotime('-1 day'));
            $this->template_engine->setDateRange($yesterday, $yesterday);

            // Get incidents from yesterday
            $incidents = $this->getIncidentsForPeriod($yesterday, $yesterday);

            if (empty($incidents)) {
                $this->log("No incidents found for yesterday");
                return $this->generateEmptyReport('daily_incident_summary', $yesterday);
            }

            // Generate summary report
            $report_data = [
                'date' => $yesterday,
                'total_incidents' => count($incidents),
                'incidents_by_type' => $this->groupIncidentsByType($incidents),
                'high_priority_incidents' => $this->filterHighPriorityIncidents($incidents),
                'incidents' => $incidents
            ];

            $html_report = $this->generateIncidentSummaryHtml($report_data);

            // Save report
            $filename = "daily_incident_summary_{$yesterday}.html";
            $this->saveReport($filename, $html_report);

            // Send to recipients
            $this->distributeReport('daily_incident_summary', $filename, $report_data);

            $this->log("Daily incident summary generated successfully");

            return [
                'success' => true,
                'filename' => $filename,
                'data' => $report_data
            ];

        } catch (Exception $e) {
            $this->log("Error generating daily incident summary: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Generate weekly analytics report
     */
    public function generateWeeklyAnalyticsReport() {
        $this->log("Starting weekly analytics report generation");

        try {
            // Set date range to last week
            $week_start = date('Y-m-d', strtotime('last monday'));
            $week_end = date('Y-m-d', strtotime('last sunday'));

            $this->template_engine->setDateRange($week_start, $week_end);

            // Generate analytics report
            $html_report = $this->template_engine->generateAnalyticsReport($week_start, $week_end);

            // Save report
            $filename = "weekly_analytics_{$week_start}_to_{$week_end}.html";
            $this->saveReport($filename, $html_report);

            // Send to recipients
            $report_data = ['period' => "{$week_start} to {$week_end}"];
            $this->distributeReport('weekly_analytics_report', $filename, $report_data);

            $this->log("Weekly analytics report generated successfully");

            return [
                'success' => true,
                'filename' => $filename,
                'period' => "{$week_start} to {$week_end}"
            ];

        } catch (Exception $e) {
            $this->log("Error generating weekly analytics report: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Generate monthly case analysis report
     */
    public function generateMonthlyCaseAnalysis() {
        $this->log("Starting monthly case analysis generation");

        try {
            // Set date range to last month
            $month_start = date('Y-m-01', strtotime('last month'));
            $month_end = date('Y-m-t', strtotime('last month'));

            $this->template_engine->setDateRange($month_start, $month_end);

            // Get case analysis data
            $case_analysis = $this->generateCaseAnalysisData($month_start, $month_end);

            // Generate HTML report
            $html_report = $this->generateCaseAnalysisHtml($case_analysis);

            // Save report
            $filename = "monthly_case_analysis_{$month_start}_to_{$month_end}.html";
            $this->saveReport($filename, $html_report);

            // Send to recipients
            $report_data = ['period' => "{$month_start} to {$month_end}"];
            $this->distributeReport('monthly_case_analysis', $filename, $report_data);

            $this->log("Monthly case analysis generated successfully");

            return [
                'success' => true,
                'filename' => $filename,
                'period' => "{$month_start} to {$month_end}"
            ];

        } catch (Exception $e) {
            $this->log("Error generating monthly case analysis: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Generate quarterly decision-making report
     */
    public function generateQuarterlyDecisionReport() {
        $this->log("Starting quarterly decision report generation");

        try {
            // Set date range to last quarter
            $quarter_start = $this->getQuarterStartDate();
            $quarter_end = $this->getQuarterEndDate();

            $this->template_engine->setDateRange($quarter_start, $quarter_end);

            // Generate decision-making report
            $html_report = $this->template_engine->generateDecisionMakingReport();

            // Save report
            $filename = "quarterly_decision_report_Q{$this->getCurrentQuarter()}_{$quarter_start}_to_{$quarter_end}.html";
            $this->saveReport($filename, $html_report);

            // Send to recipients
            $report_data = ['period' => "{$quarter_start} to {$quarter_end}"];
            $this->distributeReport('quarterly_decision_report', $filename, $report_data);

            $this->log("Quarterly decision report generated successfully");

            return [
                'success' => true,
                'filename' => $filename,
                'period' => "{$quarter_start} to {$quarter_end}"
            ];

        } catch (Exception $e) {
            $this->log("Error generating quarterly decision report: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Generate case report for specific case
     */
    public function generateCaseReport($case_id) {
        $this->log("Generating automated case report for case ID: $case_id");

        try {
            $html_report = $this->template_engine->generateAutomatedCaseReport($case_id);

            // Save report
            $filename = "case_report_{$case_id}_" . date('Y-m-d_H-i-s') . ".html";
            $this->saveReport($filename, $html_report);

            $this->log("Case report generated successfully for case ID: $case_id");

            return [
                'success' => true,
                'filename' => $filename,
                'case_id' => $case_id
            ];

        } catch (Exception $e) {
            $this->log("Error generating case report for case ID $case_id: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Run scheduled reports based on current time
     */
    public function runScheduledReports() {
        $this->log("Checking for scheduled reports to run");

        $current_time = date('H:i');
        $current_day = strtolower(date('l'));
        $current_date = date('j'); // Day of month

        foreach ($this->schedule_config as $report_type => $config) {
            if (!$config['enabled']) {
                continue;
            }

            $should_run = false;

            switch ($config['frequency']) {
                case 'daily':
                    $should_run = ($current_time === $config['time']);
                    break;
                case 'weekly':
                    $should_run = ($current_day === $config['day'] && $current_time === $config['time']);
                    break;
                case 'monthly':
                    $should_run = ($current_date == $config['day'] && $current_time === $config['time']);
                    break;
                case 'quarterly':
                    // Run on first day of quarter at specified time
                    $should_run = ($this->isFirstDayOfQuarter() && $current_time === $config['time']);
                    break;
            }

            if ($should_run) {
                $this->log("Running scheduled report: $report_type");
                $this->runReport($report_type);
            }
        }
    }

    /**
     * Run specific report type
     */
    private function runReport($report_type) {
        switch ($report_type) {
            case 'daily_incident_summary':
                return $this->generateDailyIncidentSummary();
            case 'weekly_analytics_report':
                return $this->generateWeeklyAnalyticsReport();
            case 'monthly_case_analysis':
                return $this->generateMonthlyCaseAnalysis();
            case 'quarterly_decision_report':
                return $this->generateQuarterlyDecisionReport();
            default:
                $this->log("Unknown report type: $report_type");
                return false;
        }
    }

    /**
     * Get incidents for a specific period
     */
    private function getIncidentsForPeriod($start_date, $end_date) {
        $sql = "SELECT i.*, b.status as blotter_status, ca.status as case_status,
                       u.fullname as assigned_officer
                FROM incidents i
                LEFT JOIN blotters b ON i.id = b.incident_id
                LEFT JOIN case_assignments ca ON i.id = ca.incident_id
                LEFT JOIN signup u ON ca.assigned_to = u.user_id
                WHERE DATE(i.created_at) BETWEEN ? AND ?
                ORDER BY i.created_at DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$start_date, $end_date]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Group incidents by type
     */
    private function groupIncidentsByType($incidents) {
        $grouped = [];
        foreach ($incidents as $incident) {
            $type = $incident['incident_type'] ?? 'Unknown';
            if (!isset($grouped[$type])) {
                $grouped[$type] = 0;
            }
            $grouped[$type]++;
        }
        return $grouped;
    }

    /**
     * Filter high priority incidents
     */
    private function filterHighPriorityIncidents($incidents) {
        return array_filter($incidents, function($incident) {
            return ($incident['urgency_level'] === 'Critical' ||
                    $incident['urgency_level'] === 'High' ||
                    $incident['is_high_risk'] == 1);
        });
    }

    /**
     * Generate case analysis data
     */
    private function generateCaseAnalysisData($start_date, $end_date) {
        $sql = "SELECT
                    i.incident_type,
                    COUNT(DISTINCT i.id) as total_incidents,
                    COUNT(DISTINCT b.id) as total_blotters,
                    COUNT(DISTINCT ca.id) as total_cases,
                    COUNT(DISTINCT CASE WHEN i.is_high_risk = 1 THEN i.id END) as high_risk_cases,
                    ROUND(AVG(CASE WHEN i.urgency_level = 'Critical' THEN 100
                                   WHEN i.urgency_level = 'High' THEN 75
                                   WHEN i.urgency_level = 'Medium' THEN 50
                                   ELSE 25 END), 2) as avg_severity,
                    COUNT(DISTINCT CASE WHEN ca.status = 'Resolved' THEN ca.id END) as resolved_cases
                FROM incidents i
                LEFT JOIN blotters b ON i.id = b.incident_id
                LEFT JOIN case_assignments ca ON i.id = ca.incident_id
                WHERE DATE(i.created_at) BETWEEN ? AND ?
                GROUP BY i.incident_type
                ORDER BY total_incidents DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$start_date, $end_date]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Generate incident summary HTML
     */
    private function generateIncidentSummaryHtml($data) {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>Daily Incident Summary - <?php echo $data['date']; ?></title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .header { background: #f0f0f0; padding: 20px; border-radius: 5px; }
                .stats { display: flex; gap: 20px; margin: 20px 0; }
                .stat-box { background: #e8f4f8; padding: 15px; border-radius: 5px; flex: 1; }
                table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
                th { background: #f5f5f5; }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>Daily Incident Summary</h1>
                <p><strong>Date:</strong> <?php echo $data['date']; ?></p>
                <p><strong>Total Incidents:</strong> <?php echo $data['total_incidents']; ?></p>
            </div>

            <div class="stats">
                <div class="stat-box">
                    <h3>Incidents by Type</h3>
                    <ul>
                        <?php foreach ($data['incidents_by_type'] as $type => $count): ?>
                            <li><?php echo htmlspecialchars($type); ?>: <?php echo $count; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="stat-box">
                    <h3>High Priority Incidents</h3>
                    <p><?php echo count($data['high_priority_incidents']); ?> incidents requiring immediate attention</p>
                </div>
            </div>

            <h2>Incident Details</h2>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Type</th>
                        <th>Location</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Assigned Officer</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data['incidents'] as $incident): ?>
                        <tr>
                            <td><?php echo $incident['id']; ?></td>
                            <td><?php echo htmlspecialchars($incident['incident_type']); ?></td>
                            <td><?php echo htmlspecialchars($incident['location'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($incident['urgency_level']); ?></td>
                            <td><?php echo htmlspecialchars($incident['status']); ?></td>
                            <td><?php echo htmlspecialchars($incident['assigned_officer'] ?? 'Unassigned'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    /**
     * Generate case analysis HTML
     */
    private function generateCaseAnalysisHtml($data) {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>Monthly Case Analysis</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .header { background: #f0f0f0; padding: 20px; border-radius: 5px; }
                table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
                th { background: #f5f5f5; }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>Monthly Case Analysis Report</h1>
                <p>Analysis of case types, resolution rates, and trends</p>
            </div>

            <h2>Case Analysis by Type</h2>
            <table>
                <thead>
                    <tr>
                        <th>Incident Type</th>
                        <th>Total Incidents</th>
                        <th>Blotters Created</th>
                        <th>Cases Assigned</th>
                        <th>High Risk Cases</th>
                        <th>Avg Severity</th>
                        <th>Resolved Cases</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['incident_type']); ?></td>
                            <td><?php echo $row['total_incidents']; ?></td>
                            <td><?php echo $row['total_blotters']; ?></td>
                            <td><?php echo $row['total_cases']; ?></td>
                            <td><?php echo $row['high_risk_cases']; ?></td>
                            <td><?php echo $row['avg_severity']; ?>%</td>
                            <td><?php echo $row['resolved_cases']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    /**
     * Save report to file
     */
    private function saveReport($filename, $content) {
        $reports_dir = __DIR__ . '/../reports/generated/';
        if (!is_dir($reports_dir)) {
            mkdir($reports_dir, 0755, true);
        }

        $filepath = $reports_dir . $filename;
        file_put_contents($filepath, $content);

        $this->log("Report saved: $filepath");
        return $filepath;
    }

    /**
     * Distribute report to recipients
     */
    private function distributeReport($report_type, $filename, $report_data) {
        if (!isset($this->schedule_config[$report_type])) {
            return;
        }

        $config = $this->schedule_config[$report_type];
        $recipients = $config['recipients'];

        // In a real implementation, you would send emails here
        // For now, just log the distribution
        $this->log("Report $filename would be sent to: " . implode(', ', $recipients));

        // Store distribution record in database
        $this->recordReportDistribution($report_type, $filename, $recipients, $report_data);
    }

    /**
     * Record report distribution in database
     */
    private function recordReportDistribution($report_type, $filename, $recipients, $report_data) {
        try {
            $sql = "INSERT INTO report_distributions
                    (report_type, filename, recipients, report_data, distributed_at)
                    VALUES (?, ?, ?, ?, NOW())";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $report_type,
                $filename,
                json_encode($recipients),
                json_encode($report_data)
            ]);
        } catch (Exception $e) {
            $this->log("Error recording report distribution: " . $e->getMessage());
        }
    }

    /**
     * Generate empty report when no data is available
     */
    private function generateEmptyReport($report_type, $date) {
        $html = "
        <!DOCTYPE html>
        <html>
        <head>
            <title>No Data Report</title>
            <style>body { font-family: Arial, sans-serif; margin: 20px; }</style>
        </head>
        <body>
            <h1>$report_type Report</h1>
            <p>No incidents found for the specified period ($date).</p>
        </body>
        </html>";

        $filename = "{$report_type}_no_data_{$date}.html";
        $this->saveReport($filename, $html);

        return [
            'success' => true,
            'filename' => $filename,
            'message' => 'No data available for the reporting period'
        ];
    }

    /**
     * Helper methods for date calculations
     */
    private function getQuarterStartDate() {
        $current_month = date('n');
        $quarter_start_month = ceil($current_month / 3) * 3 - 2;
        return date('Y-m-d', strtotime(date('Y') . '-' . $quarter_start_month . '-01'));
    }

    private function getQuarterEndDate() {
        $current_month = date('n');
        $quarter_end_month = ceil($current_month / 3) * 3;
        return date('Y-m-t', strtotime(date('Y') . '-' . $quarter_end_month . '-01'));
    }

    private function getCurrentQuarter() {
        return ceil(date('n') / 3);
    }

    private function isFirstDayOfQuarter() {
        $current_month = date('n');
        $current_day = date('j');
        return ($current_day == 1 && in_array($current_month, [1, 4, 7, 10]));
    }

    /**
     * Update schedule configuration
     */
    public function updateScheduleConfig($report_type, $config) {
        $this->schedule_config[$report_type] = array_merge(
            $this->schedule_config[$report_type] ?? [],
            $config
        );
    }

    /**
     * Get schedule configuration
     */
    public function getScheduleConfig() {
        return $this->schedule_config;
    }

    /**
     * Log message to file
     */
    private function log($message) {
        $timestamp = date('Y-m-d H:i:s');
        $log_entry = "[$timestamp] $message\n";
        file_put_contents($this->log_file, $log_entry, FILE_APPEND);
    }

    /**
     * Get report history
     */
    public function getReportHistory($limit = 50) {
        try {
            $sql = "SELECT * FROM report_distributions
                    ORDER BY distributed_at DESC LIMIT ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $this->log("Error getting report history: " . $e->getMessage());
            return [];
        }
    }
}
