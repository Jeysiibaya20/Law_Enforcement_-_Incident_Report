<?php
/**
 * EnhancedReportTemplates - Advanced Report Template System
 *
 * Provides comprehensive templates for incident reports, case reports,
 * analytics reports, and decision-making reports
 */

require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/ReportGenerator.php';
require_once __DIR__ . '/AnalyticsEngine.php';

class EnhancedReportTemplates extends ReportGenerator {

    private $template_type;
    private $analytics_engine;
    private $auto_generate = false;

    public function __construct($pdo, $template_type = 'comprehensive') {
        parent::__construct($pdo);
        $this->template_type = $template_type;
        $this->analytics_engine = new AnalyticsEngine($pdo);
    }

    /**
     * Enable automatic report generation
     */
    public function enableAutoGeneration($enabled = true) {
        $this->auto_generate = $enabled;
        return $this;
    }

    /**
     * Generate comprehensive incident report template
     */
    public function generateIncidentReportTemplate($incident_id) {
        $incident_data = $this->getIncidentData($incident_id);
        if (!$incident_data) {
            return $this->generateErrorTemplate('Incident not found');
        }

        $template = [
            'header' => $this->generateReportHeader('INCIDENT REPORT', $incident_data),
            'executive_summary' => $this->generateExecutiveSummary($incident_data),
            'incident_details' => $this->generateIncidentDetails($incident_data),
            'analysis_section' => $this->generateAnalysisSection($incident_data),
            'recommendations' => $this->generateRecommendations($incident_data),
            'attachments' => $this->generateAttachmentsSection($incident_data),
            'footer' => $this->generateReportFooter()
        ];

        return $this->renderTemplate($template);
    }

    /**
     * Generate automated case report
     */
    public function generateAutomatedCaseReport($case_id) {
        $case_data = $this->getCaseData($case_id);
        if (!$case_data) {
            return $this->generateErrorTemplate('Case not found');
        }

        $template = [
            'header' => $this->generateReportHeader('CASE INVESTIGATION REPORT', $case_data),
            'case_overview' => $this->generateCaseOverview($case_data),
            'investigation_timeline' => $this->generateInvestigationTimeline($case_data),
            'evidence_summary' => $this->generateEvidenceSummary($case_data),
            'findings' => $this->generateFindings($case_data),
            'conclusions' => $this->generateConclusions($case_data),
            'recommendations' => $this->generateCaseRecommendations($case_data),
            'footer' => $this->generateReportFooter()
        ];

        return $this->renderTemplate($template);
    }

    /**
     * Generate analytics and trends report
     */
    public function generateAnalyticsReport($date_from = null, $date_to = null) {
        if ($date_from && $date_to) {
            $this->setDateRange($date_from, $date_to);
            $this->analytics_engine->setDateRange($date_from, $date_to);
        }

        $analytics_data = $this->analytics_engine->getDashboardAnalytics();

        $template = [
            'header' => $this->generateReportHeader('ANALYTICS & TRENDS REPORT', [
                'period' => $this->date_from . ' to ' . $this->date_to,
                'generated_at' => date('Y-m-d H:i:s')
            ]),
            'executive_summary' => $this->generateAnalyticsSummary($analytics_data),
            'case_type_analysis' => $this->generateCaseTypeAnalysis($analytics_data['case_types']),
            'child_incident_trends' => $this->generateChildIncidentTrends($analytics_data['child_incidents']),
            'performance_metrics' => $this->generatePerformanceMetrics($analytics_data['officer_performance']),
            'decision_support' => $this->generateDecisionSupport($analytics_data),
            'recommendations' => $this->generateStrategicRecommendations($analytics_data),
            'footer' => $this->generateReportFooter()
        ];

        return $this->renderTemplate($template);
    }

    /**
     * Generate decision-making report
     */
    public function generateDecisionMakingReport($focus_area = 'all') {
        $analytics_data = $this->analytics_engine->getDashboardAnalytics();
        $insights = $this->analytics_engine->getInsights();

        $template = [
            'header' => $this->generateReportHeader('DECISION-MAKING REPORT', [
                'focus_area' => $focus_area,
                'period' => $this->date_from . ' to ' . $this->date_to
            ]),
            'strategic_overview' => $this->generateStrategicOverview($analytics_data),
            'key_insights' => $this->generateKeyInsights($insights),
            'resource_allocation' => $this->generateResourceAllocation($analytics_data),
            'policy_recommendations' => $this->generatePolicyRecommendations($analytics_data),
            'action_plan' => $this->generateActionPlan($analytics_data),
            'footer' => $this->generateReportFooter()
        ];

        return $this->renderTemplate($template);
    }

    /**
     * Get incident data for report generation
     */
    private function getIncidentData($incident_id) {
        $sql = "SELECT i.id AS incident_id, i.*, b.id AS blotter_id, b.*, ca.id AS case_id, ca.*,
                       u.fullname as assigned_officer_name,
                       u.emailadd as officer_email,
                       reporter.fullname as reporter_name,
                       reporter.emailadd as reporter_email
                FROM incidents i
                LEFT JOIN blotters b ON i.id = b.incident_id
                LEFT JOIN case_assignments ca ON i.id = ca.incident_id
                LEFT JOIN signup u ON ca.assigned_to = u.user_id
                LEFT JOIN signup reporter ON i.created_by = reporter.user_id
                WHERE i.id = ?";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$incident_id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("EnhancedReportTemplates::getIncidentData SQL Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get case data for report generation
     */
    private function getCaseData($case_id) {
        $sql = "SELECT ca.id AS case_id, ca.*, i.id AS incident_id, i.*, b.id AS blotter_id, b.*,
                       u.fullname as assigned_officer_name,
                       u.emailadd as officer_email
                FROM case_assignments ca
                JOIN incidents i ON ca.incident_id = i.id
                LEFT JOIN blotters b ON i.id = b.incident_id
                LEFT JOIN signup u ON ca.assigned_to = u.user_id
                WHERE ca.id = ?";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$case_id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("EnhancedReportTemplates::getCaseData SQL Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Generate report header
     */
    private function generateReportHeader($title, $data) {
        return [
            'title' => $title,
            'generated_at' => date('Y-m-d H:i:s'),
            'period' => $data['period'] ?? null,
            'incident_id' => $data['id'] ?? $data['incident_id'] ?? null,
            'case_id' => $data['case_id'] ?? null
        ];
    }

    /**
     * Generate executive summary
     */
    private function generateExecutiveSummary($data) {
        return [
            'incident_type' => $data['incident_type'] ?? 'Unknown',
            'severity' => $data['urgency_level'] ?? 'Medium',
            'status' => $data['status'] ?? 'Unknown',
            'location' => $data['location'] ?? 'Unknown',
            'assigned_officer' => $data['assigned_officer_name'] ?? 'Unassigned',
            'key_findings' => $this->extractKeyFindings($data)
        ];
    }

    /**
     * Generate incident details section
     */
    private function generateIncidentDetails($data) {
        return [
            'basic_info' => [
                'incident_id' => $data['id'] ?? $data['incident_id'],
                'case_no' => $data['case_no'] ?? 'N/A',
                'type' => $data['incident_type'],
                'subtype' => $data['incident_subtype'] ?? 'N/A',
                'date' => $data['incident_date'],
                'time' => $data['incident_time'],
                'location' => $data['location'],
                'coordinates' => ($data['latitude'] && $data['longitude']) ?
                    $data['latitude'] . ', ' . $data['longitude'] : 'N/A'
            ],
            'parties_involved' => [
                'reporter' => $data['reporter_name'] ?? $data['reporter_name'],
                'victim' => $data['victim_name'] ?? 'N/A',
                'victim_age' => $data['victim_age'] ?? 'N/A',
                'victim_gender' => $data['victim_gender'] ?? 'N/A',
                'suspect' => $data['suspect_name'] ?? 'N/A'
            ],
            'narrative' => $data['narrative'] ?? $data['description']
        ];
    }

    /**
     * Generate analysis section
     */
    private function generateAnalysisSection($data) {
        return [
            'nlp_analysis' => [
                'sentiment' => $data['nlp_sentiment'] ?? 'Neutral',
                'threat_level' => $data['nlp_threat_level'] ?? 'Low',
                'severity_score' => $data['nlp_severity_score'] ?? 0,
                'confidence' => $data['nlp_confidence_score'] ?? 0,
                'summary' => $data['nlp_summary'] ?? 'No analysis available'
            ],
            'risk_assessment' => [
                'is_high_risk' => $data['is_high_risk'] ? 'Yes' : 'No',
                'urgency_level' => $data['urgency_level'] ?? 'Medium'
            ]
        ];
    }

    /**
     * Generate case type analysis for analytics report
     */
    private function generateCaseTypeAnalysis($case_types) {
        return [
            'summary' => 'Distribution of incidents by type',
            'data' => $case_types,
            'chart_type' => 'bar_chart',
            'insights' => $this->analyzeCaseTypeTrends($case_types)
        ];
    }

    /**
     * Generate child incident trends
     */
    private function generateChildIncidentTrends($child_data) {
        $trend = $this->analytics_engine->getChildIncidentTrend();

        return [
            'summary' => 'Trends in child-related incidents over time',
            'monthly_data' => $trend,
            'chart_type' => 'line_chart',
            'insights' => $this->analyzeChildIncidentTrends($trend)
        ];
    }

    /**
     * Generate performance metrics
     */
    private function generatePerformanceMetrics($performance_data) {
        return [
            'officer_metrics' => $performance_data,
            'summary_stats' => $this->calculatePerformanceSummary($performance_data),
            'recommendations' => $this->generatePerformanceRecommendations($performance_data)
        ];
    }

    /**
     * Generate decision support section
     */
    private function generateDecisionSupport($analytics_data) {
        return [
            'resource_needs' => $this->calculateResourceNeeds($analytics_data),
            'priority_areas' => $this->identifyPriorityAreas($analytics_data),
            'forecasting' => $this->analytics_engine->getForecast(),
            'risk_assessment' => $this->assessOverallRisk($analytics_data)
        ];
    }

    /**
     * Render template to HTML
     */
    private function renderTemplate($template) {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?php echo htmlspecialchars($template['header']['title']); ?></title>
            <style>
                <?php echo $this->getReportStyles(); ?>
            </style>
        </head>
        <body>
            <div class="report-container">
                <?php echo $this->renderHeader($template['header']); ?>

                <?php foreach ($template as $section => $data): ?>
                    <?php if ($section !== 'header' && $section !== 'footer'): ?>
                        <div class="report-section" id="<?php echo $section; ?>">
                            <?php echo $this->renderSection($section, $data); ?>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>

                <?php echo $this->renderFooter($template['footer']); ?>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    /**
     * Render report header
     */
    private function renderHeader($header) {
        ob_start();
        ?>
        <div class="report-header">
            <h1><?php echo htmlspecialchars($header['title']); ?></h1>
            <div class="report-meta">
                <p><strong>Generated:</strong> <?php echo $header['generated_at']; ?></p>
                <?php if (isset($header['period'])): ?>
                    <p><strong>Period:</strong> <?php echo htmlspecialchars($header['period']); ?></p>
                <?php endif; ?>
                <?php if (isset($header['incident_id'])): ?>
                    <p><strong>Incident ID:</strong> <?php echo htmlspecialchars($header['incident_id']); ?></p>
                <?php endif; ?>
                <?php if (isset($header['case_id'])): ?>
                    <p><strong>Case ID:</strong> <?php echo htmlspecialchars($header['case_id']); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render report section
     */
    private function renderSection($section_name, $data) {
        $method = 'render' . str_replace('_', '', ucwords($section_name, '_')) . 'Section';
        if (method_exists($this, $method)) {
            return $this->$method($data);
        }
        return $this->renderGenericSection($section_name, $data);
    }

    /**
     * Render executive summary section
     */
    private function renderExecutiveSummarySection($data) {
        ob_start();
        ?>
        <h2>Executive Summary</h2>
        <div class="executive-summary">
            <div class="summary-grid">
                <div class="summary-item">
                    <strong>Incident Type:</strong> <?php echo htmlspecialchars($data['incident_type']); ?>
                </div>
                <div class="summary-item">
                    <strong>Severity:</strong> <?php echo htmlspecialchars($data['severity']); ?>
                </div>
                <div class="summary-item">
                    <strong>Status:</strong> <?php echo htmlspecialchars($data['status']); ?>
                </div>
                <div class="summary-item">
                    <strong>Location:</strong> <?php echo htmlspecialchars($data['location']); ?>
                </div>
            </div>
            <div class="key-findings">
                <h3>Key Findings</h3>
                <ul>
                    <?php foreach ($data['key_findings'] as $finding): ?>
                        <li><?php echo htmlspecialchars($finding); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render case type analysis section
     */
    private function renderCaseTypeAnalysisSection($data) {
        ob_start();
        ?>
        <h2>Case Type Analysis</h2>
        <p><?php echo htmlspecialchars($data['summary']); ?></p>

        <div class="analytics-table">
            <table>
                <thead>
                    <tr>
                        <th>Incident Type</th>
                        <th>Count</th>
                        <th>Avg Severity</th>
                        <th>High Threat</th>
                        <th>Blotter Rate</th>
                        <th>Case Rate</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data['data'] as $type): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($type['incident_type']); ?></td>
                            <td><?php echo $type['count']; ?></td>
                            <td><?php echo $type['avg_severity']; ?>%</td>
                            <td><?php echo $type['high_threat_count']; ?></td>
                            <td><?php echo $type['blotter_rate']; ?>%</td>
                            <td><?php echo $type['case_creation_rate']; ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="insights">
            <h3>Insights</h3>
            <ul>
                <?php foreach ($data['insights'] as $insight): ?>
                    <li><?php echo htmlspecialchars($insight); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render child incident trends section
     */
    private function renderChildIncidentTrendsSection($data) {
        ob_start();
        ?>
        <h2>Child-Related Incident Trends</h2>
        <p><?php echo htmlspecialchars($data['summary']); ?></p>

        <div class="trends-chart">
            <canvas id="childTrendsChart" width="400" height="200"></canvas>
        </div>

        <div class="insights">
            <h3>Trend Analysis</h3>
            <ul>
                <?php foreach ($data['insights'] as $insight): ?>
                    <li><?php echo htmlspecialchars($insight); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>

        <script>
            // Simple chart rendering (would need Chart.js in production)
            document.addEventListener('DOMContentLoaded', function() {
                const ctx = document.getElementById('childTrendsChart').getContext('2d');
                const data = <?php echo json_encode($data['monthly_data']); ?>;

                // Placeholder for chart - in production, use Chart.js
                ctx.fillStyle = '#f0f0f0';
                ctx.fillRect(0, 0, 400, 200);
                ctx.fillStyle = '#000';
                ctx.font = '16px Arial';
                ctx.fillText('Child Incident Trends Chart', 100, 100);
                ctx.fillText('(Chart.js required for full functionality)', 80, 120);
            });
        </script>
        <?php
        return ob_get_clean();
    }

    /**
     * Get report CSS styles
     */
    private function getReportStyles() {
        return "
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                margin: 0;
                padding: 20px;
                background: #f5f5f5;
                color: #333;
                line-height: 1.6;
            }

            .report-container {
                background: white;
                max-width: 1200px;
                margin: 0 auto;
                padding: 30px;
                box-shadow: 0 0 20px rgba(0,0,0,0.1);
                border-radius: 8px;
            }

            .report-header {
                text-align: center;
                border-bottom: 3px solid #2c3e50;
                padding-bottom: 20px;
                margin-bottom: 30px;
            }

            .report-header h1 {
                color: #2c3e50;
                margin: 0 0 15px 0;
                font-size: 28px;
            }

            .report-meta {
                color: #666;
                font-size: 14px;
            }

            .report-section {
                margin-bottom: 30px;
                padding: 20px;
                background: #fafafa;
                border-radius: 6px;
                border-left: 4px solid #3498db;
            }

            .report-section h2 {
                color: #2c3e50;
                margin-top: 0;
                font-size: 22px;
                border-bottom: 2px solid #ecf0f1;
                padding-bottom: 10px;
            }

            .summary-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 15px;
                margin-bottom: 20px;
            }

            .summary-item {
                background: white;
                padding: 15px;
                border-radius: 4px;
                border: 1px solid #ddd;
            }

            .analytics-table table {
                width: 100%;
                border-collapse: collapse;
                margin: 15px 0;
            }

            .analytics-table th,
            .analytics-table td {
                padding: 12px;
                text-align: left;
                border-bottom: 1px solid #ddd;
            }

            .analytics-table th {
                background: #f8f9fa;
                font-weight: 600;
            }

            .insights {
                background: #e8f4f8;
                padding: 15px;
                border-radius: 4px;
                margin-top: 15px;
            }

            .insights ul {
                margin: 0;
                padding-left: 20px;
            }

            .insights li {
                margin-bottom: 5px;
            }

            .report-footer {
                text-align: center;
                margin-top: 40px;
                padding-top: 20px;
                border-top: 2px solid #ecf0f1;
                color: #666;
                font-size: 12px;
            }
        ";
    }

    /**
     * Helper methods for analysis
     */
    private function extractKeyFindings($data) {
        $findings = [];

        if ($data['is_high_risk']) {
            $findings[] = 'High-risk incident requiring immediate attention';
        }

        if ($data['nlp_threat_level'] === 'HIGH') {
            $findings[] = 'NLP analysis indicates high threat level';
        }

        if ($data['urgency_level'] === 'Critical') {
            $findings[] = 'Critical urgency level assigned';
        }

        if (empty($findings)) {
            $findings[] = 'Standard incident with no immediate high-risk indicators';
        }

        return $findings;
    }

    private function analyzeCaseTypeTrends($case_types) {
        $insights = [];

        if (count($case_types) > 0) {
            $top_type = $case_types[0];
            $insights[] = "Most common incident type: {$top_type['incident_type']} ({$top_type['count']} cases)";

            foreach ($case_types as $type) {
                if ($type['high_threat_count'] > 0) {
                    $insights[] = "{$type['incident_type']} has {$type['high_threat_count']} high-threat cases";
                }
            }
        }

        return $insights;
    }

    private function analyzeChildIncidentTrends($trend_data) {
        $insights = [];

        if (count($trend_data) > 1) {
            $latest = end($trend_data);
            $previous = prev($trend_data);

            if ($latest && $previous) {
                $change = $latest['child_incident_percentage'] - $previous['child_incident_percentage'];
                if ($change > 0) {
                    $insights[] = "Child-related incidents increased by " . number_format($change, 1) . "% this month";
                } elseif ($change < 0) {
                    $insights[] = "Child-related incidents decreased by " . number_format(abs($change), 1) . "% this month";
                } else {
                    $insights[] = "Child-related incident rate stable this month";
                }
            }
        }

        return $insights;
    }

    private function calculatePerformanceSummary($performance_data) {
        if (empty($performance_data)) {
            return ['avg_closure_rate' => 0, 'total_cases' => 0, 'active_officers' => 0];
        }

        $total_cases = array_sum(array_column($performance_data, 'assigned_cases'));
        $avg_closure = array_sum(array_column($performance_data, 'closure_rate')) / count($performance_data);

        return [
            'avg_closure_rate' => round($avg_closure, 2),
            'total_cases' => $total_cases,
            'active_officers' => count($performance_data)
        ];
    }

    private function generateErrorTemplate($message) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <title>Report Error</title>
            <style>
                body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
                .error { color: #e74c3c; font-size: 18px; }
            </style>
        </head>
        <body>
            <div class='error'>{$message}</div>
        </body>
        </html>";
    }

    // Additional helper methods would be implemented here...
}
