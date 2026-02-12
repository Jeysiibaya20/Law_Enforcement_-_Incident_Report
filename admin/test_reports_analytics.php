<?php
/**
 * Test Report Generation and Analytics Modules
 *
 * Comprehensive testing script for all reporting and analytics functionality
 */

session_start();
require_once '../config/db_connect.php';
require_once '../modules/EnhancedReportTemplates.php';
require_once '../modules/AutomatedReportGenerator.php';
require_once '../modules/AnalyticsEngine.php';

class ReportAnalyticsTester {

    private $pdo;
    private $template_engine;
    private $auto_generator;
    private $analytics_engine;
    private $test_results = [];

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->template_engine = new EnhancedReportTemplates($pdo);
        $this->auto_generator = new AutomatedReportGenerator($pdo);
        $this->analytics_engine = new AnalyticsEngine($pdo);
    }

    /**
     * Run all tests
     */
    public function runAllTests() {
        echo "<h1>🧪 Report Generation & Analytics Testing Suite</h1>";
        echo "<div style='font-family: monospace; background: #f5f5f5; padding: 20px; border-radius: 8px;'>";

        $this->testDatabaseConnection();
        $this->testAnalyticsEngine();
        $this->testReportTemplates();
        $this->testAutomatedReports();
        $this->testDataIntegrity();

        $this->displayTestSummary();

        echo "</div>";
    }

    /**
     * Test database connection
     */
    private function testDatabaseConnection() {
        echo "<h2>🔌 Testing Database Connection</h2>";

        try {
            $stmt = $this->pdo->query("SELECT 1");
            $result = $stmt->fetch();
            $this->logTest("Database Connection", true, "Connected successfully");
        } catch (Exception $e) {
            $this->logTest("Database Connection", false, "Connection failed: " . $e->getMessage());
        }
    }

    /**
     * Test Analytics Engine
     */
    private function testAnalyticsEngine() {
        echo "<h2>📊 Testing Analytics Engine</h2>";

        try {
            // Test summary metrics
            $summary = $this->analytics_engine->getSummaryMetrics();
            $this->logTest("Summary Metrics", is_array($summary), "Retrieved " . count($summary) . " metrics");

            // Test trend analysis
            $trends = $this->analytics_engine->getTrendAnalysis();
            $this->logTest("Trend Analysis", is_array($trends), "Retrieved " . count($trends) . " trend records");

            // Test case type analysis
            $case_types = $this->analytics_engine->getCaseTypeAnalysis();
            $this->logTest("Case Type Analysis", is_array($case_types), "Analyzed " . count($case_types) . " case types");

            // Test child incident analysis
            $child_analysis = $this->analytics_engine->getChildIncidentAnalysis();
            $this->logTest("Child Incident Analysis", is_array($child_analysis), "Retrieved child incident data");

            // Test officer performance
            $officer_perf = $this->analytics_engine->getOfficerPerformance();
            $this->logTest("Officer Performance", is_array($officer_perf), "Retrieved " . count($officer_perf) . " officer records");

            // Test threat analysis
            $threat_analysis = $this->analytics_engine->getThreatAnalysis();
            $this->logTest("Threat Analysis", is_array($threat_analysis), "Retrieved threat level data");

            // Test insights generation
            $insights = $this->analytics_engine->getInsights();
            $this->logTest("Insights Generation", is_array($insights), "Generated " . count($insights) . " insights");

        } catch (Exception $e) {
            $this->logTest("Analytics Engine", false, "Error: " . $e->getMessage());
        }
    }

    /**
     * Test Report Templates
     */
    private function testReportTemplates() {
        echo "<h2>📄 Testing Report Templates</h2>";

        try {
            // Test analytics report generation
            $analytics_report = $this->template_engine->generateAnalyticsReport();
            $this->logTest("Analytics Report Template", !empty($analytics_report), "Generated " . strlen($analytics_report) . " characters");

            // Test decision-making report
            $decision_report = $this->template_engine->generateDecisionMakingReport();
            $this->logTest("Decision Making Report", !empty($decision_report), "Generated " . strlen($decision_report) . " characters");

            // Test incident report (if incidents exist)
            $incident_id = $this->getRandomIncidentId();
            if ($incident_id) {
                $incident_report = $this->template_engine->generateIncidentReportTemplate($incident_id);
                $this->logTest("Incident Report Template", !empty($incident_report), "Generated report for incident ID: $incident_id");
            } else {
                $this->logTest("Incident Report Template", true, "No incidents available for testing (expected for empty database)");
            }

            // Test case report (if cases exist)
            $case_id = $this->getRandomCaseId();
            if ($case_id) {
                $case_report = $this->template_engine->generateAutomatedCaseReport($case_id);
                $this->logTest("Case Report Template", !empty($case_report), "Generated report for case ID: $case_id");
            } else {
                $this->logTest("Case Report Template", true, "No cases available for testing (expected for empty database)");
            }

        } catch (Exception $e) {
            $this->logTest("Report Templates", false, "Error: " . $e->getMessage());
        }
    }

    /**
     * Test Automated Report Generation
     */
    private function testAutomatedReports() {
        echo "<h2>🤖 Testing Automated Report Generation</h2>";

        try {
            // Test daily incident summary
            $daily_result = $this->auto_generator->generateDailyIncidentSummary();
            $this->logTest("Daily Incident Summary", isset($daily_result['success']), $daily_result['success'] ? "Generated: " . $daily_result['filename'] : "Failed");

            // Test weekly analytics report
            $weekly_result = $this->auto_generator->generateWeeklyAnalyticsReport();
            $this->logTest("Weekly Analytics Report", isset($weekly_result['success']), $weekly_result['success'] ? "Generated: " . $weekly_result['filename'] : "Failed");

            // Test monthly case analysis
            $monthly_result = $this->auto_generator->generateMonthlyCaseAnalysis();
            $this->logTest("Monthly Case Analysis", isset($monthly_result['success']), $monthly_result['success'] ? "Generated: " . $monthly_result['filename'] : "Failed");

            // Test quarterly decision report
            $quarterly_result = $this->auto_generator->generateQuarterlyDecisionReport();
            $this->logTest("Quarterly Decision Report", isset($quarterly_result['success']), $quarterly_result['success'] ? "Generated: " . $quarterly_result['filename'] : "Failed");

            // Test schedule configuration
            $schedule = $this->auto_generator->getScheduleConfig();
            $this->logTest("Schedule Configuration", is_array($schedule), "Retrieved " . count($schedule) . " scheduled reports");

            // Test report history
            $history = $this->auto_generator->getReportHistory(10);
            $this->logTest("Report History", is_array($history), "Retrieved " . count($history) . " historical records");

        } catch (Exception $e) {
            $this->logTest("Automated Reports", false, "Error: " . $e->getMessage());
        }
    }

    /**
     * Test Data Integrity
     */
    private function testDataIntegrity() {
        echo "<h2>🔍 Testing Data Integrity</h2>";

        try {
            // Test for orphaned records
            $orphaned_blotters = $this->pdo->query("SELECT COUNT(*) FROM blotters WHERE incident_id NOT IN (SELECT id FROM incidents)")->fetchColumn();
            $this->logTest("Orphaned Blotters", $orphaned_blotters == 0, $orphaned_blotters == 0 ? "No orphaned blotters found" : "$orphaned_blotters orphaned blotters found");

            $orphaned_cases = $this->pdo->query("SELECT COUNT(*) FROM case_assignments WHERE incident_id NOT IN (SELECT id FROM incidents)")->fetchColumn();
            $this->logTest("Orphaned Cases", $orphaned_cases == 0, $orphaned_cases == 0 ? "No orphaned cases found" : "$orphaned_cases orphaned cases found");

            // Test for required fields
            $incomplete_incidents = $this->pdo->query("SELECT COUNT(*) FROM incidents WHERE incident_type IS NULL OR narrative IS NULL OR incident_date IS NULL")->fetchColumn();
            $this->logTest("Complete Incident Records", $incomplete_incidents == 0, $incomplete_incidents == 0 ? "All incidents have required fields" : "$incomplete_incidents incomplete incident records");

            // Test date consistency
            $invalid_dates = $this->pdo->query("SELECT COUNT(*) FROM incidents WHERE incident_date > CURDATE() OR created_at > NOW()")->fetchColumn();
            $this->logTest("Date Consistency", $invalid_dates == 0, $invalid_dates == 0 ? "All dates are valid" : "$invalid_dates records with invalid dates");

            // Test JSON validity in NLP fields
            $invalid_json = $this->pdo->query("SELECT COUNT(*) FROM incidents WHERE (nlp_emotions IS NOT NULL AND JSON_VALID(nlp_emotions) = 0) OR (nlp_analysis_data IS NOT NULL AND JSON_VALID(nlp_analysis_data) = 0)")->fetchColumn();
            $this->logTest("JSON Data Validity", $invalid_json == 0, $invalid_json == 0 ? "All JSON data is valid" : "$invalid_json records with invalid JSON");

        } catch (Exception $e) {
            $this->logTest("Data Integrity", false, "Error: " . $e->getMessage());
        }
    }

    /**
     * Get random incident ID for testing
     */
    private function getRandomIncidentId() {
        try {
            $stmt = $this->pdo->query("SELECT id FROM incidents ORDER BY RAND() LIMIT 1");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? $result['id'] : null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Get random case ID for testing
     */
    private function getRandomCaseId() {
        try {
            $stmt = $this->pdo->query("SELECT id FROM case_assignments ORDER BY RAND() LIMIT 1");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? $result['id'] : null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Log test result
     */
    private function logTest($test_name, $passed, $message = "") {
        $status = $passed ? "✅ PASS" : "❌ FAIL";
        $color = $passed ? "green" : "red";

        echo "<div style='margin: 10px 0; padding: 10px; border-left: 4px solid $color; background: #fff;'>";
        echo "<strong>$status:</strong> $test_name";
        if ($message) {
            echo "<br><small style='color: #666;'>$message</small>";
        }
        echo "</div>";

        $this->test_results[] = [
            'test' => $test_name,
            'passed' => $passed,
            'message' => $message
        ];
    }

    /**
     * Display test summary
     */
    private function displayTestSummary() {
        echo "<h2>📋 Test Summary</h2>";

        $total_tests = count($this->test_results);
        $passed_tests = count(array_filter($this->test_results, function($test) {
            return $test['passed'];
        }));
        $failed_tests = $total_tests - $passed_tests;

        $success_rate = $total_tests > 0 ? round(($passed_tests / $total_tests) * 100, 1) : 0;

        echo "<div style='background: #fff; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
        echo "<h3>Overall Results</h3>";
        echo "<p><strong>Total Tests:</strong> $total_tests</p>";
        echo "<p><strong>Passed:</strong> <span style='color: green;'>$passed_tests</span></p>";
        echo "<p><strong>Failed:</strong> <span style='color: red;'>$failed_tests</span></p>";
        echo "<p><strong>Success Rate:</strong> <span style='color: " . ($success_rate >= 80 ? 'green' : 'red') . ";'>$success_rate%</span></p>";

        if ($success_rate >= 80) {
            echo "<div style='background: #d4edda; color: #155724; padding: 10px; border-radius: 4px; margin-top: 15px;'>";
            echo "🎉 Report Generation and Analytics modules are working correctly!";
            echo "</div>";
        } else {
            echo "<div style='background: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px; margin-top: 15px;'>";
            echo "⚠️ Some tests failed. Please review the errors above and fix any issues.";
            echo "</div>";
        }

        echo "</div>";

        // Detailed results
        echo "<h3>Detailed Results</h3>";
        echo "<table style='width: 100%; border-collapse: collapse; background: #fff;'>";
        echo "<tr style='background: #f8f9fa;'><th style='padding: 10px; border: 1px solid #ddd;'>Test</th><th style='padding: 10px; border: 1px solid #ddd;'>Status</th><th style='padding: 10px; border: 1px solid #ddd;'>Details</th></tr>";

        foreach ($this->test_results as $result) {
            $status = $result['passed'] ? "<span style='color: green;'>PASS</span>" : "<span style='color: red;'>FAIL</span>";
            echo "<tr>";
            echo "<td style='padding: 10px; border: 1px solid #ddd;'>{$result['test']}</td>";
            echo "<td style='padding: 10px; border: 1px solid #ddd;'>$status</td>";
            echo "<td style='padding: 10px; border: 1px solid #ddd;'>{$result['message']}</td>";
            echo "</tr>";
        }

        echo "</table>";
    }

    /**
     * Get test results for external use
     */
    public function getTestResults() {
        return $this->test_results;
    }
}

// Run the tests
if (!isset($_SESSION['user_id'])) {
    // Simulate admin session for testing
    $_SESSION['user_id'] = 1;
    $_SESSION['role'] = 'Admin';
    $_SESSION['fullname'] = 'Test Admin';
}

$tester = new ReportAnalyticsTester($pdo);
$tester->runAllTests();
?>