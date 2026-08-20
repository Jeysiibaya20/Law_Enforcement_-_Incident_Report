<?php
/**
 * Setup Automated Reports System
 * Initializes the automated report generation system with built-in robust DDL fallback
 */

require_once 'admin_auth.php';
require_once '../config/db_connect.php';

$message = '';
$success = false;
$tableStatuses = [];

// Helper to check table existence
function checkTableExists($pdo, $tableName) {
    try {
        $result = $pdo->query("SHOW TABLES LIKE '$tableName'");
        return $result && $result->rowCount() > 0;
    } catch (Exception $e) {
        return false;
    }
}

// Built-in DDL Statements for bulletproof execution
$builtInSql = <<<SQL
CREATE TABLE IF NOT EXISTS `report_distributions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `report_type` varchar(100) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `recipients` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`recipients`)),
  `report_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`report_data`)),
  `distributed_at` datetime NOT NULL,
  `status` enum('sent','failed','pending') DEFAULT 'sent',
  PRIMARY KEY (`id`),
  KEY `report_type` (`report_type`),
  KEY `distributed_at` (`distributed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `automated_reports_schedule` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `report_type` varchar(100) NOT NULL,
  `enabled` tinyint(1) DEFAULT 1,
  `frequency` enum('daily','weekly','monthly','quarterly') NOT NULL,
  `schedule_time` time DEFAULT NULL,
  `schedule_day` varchar(20) DEFAULT NULL,
  `schedule_date` int(11) DEFAULT NULL,
  `recipients` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`recipients`)),
  `last_run` datetime DEFAULT NULL,
  `next_run` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `report_type` (`report_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO `automated_reports_schedule` (`report_type`, `enabled`, `frequency`, `schedule_time`, `schedule_day`, `schedule_date`, `recipients`) VALUES
('daily_incident_summary', 1, 'daily', '08:00:00', NULL, NULL, '["admin@example.com"]'),
('weekly_analytics_report', 1, 'weekly', '09:00:00', 'monday', NULL, '["admin@example.com", "supervisor@example.com"]'),
('monthly_case_analysis', 1, 'monthly', '10:00:00', NULL, 1, '["admin@example.com", "management@example.com"]'),
('quarterly_decision_report', 1, 'quarterly', '11:00:00', NULL, NULL, '["admin@example.com", "board@example.com"]');
SQL;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $sqlContent = '';
        $candidatePaths = [
            __DIR__ . '/../setup_automated_reports.sql',
            __DIR__ . '/../wag/setup_automated_reports.sql',
            dirname(__DIR__) . '/setup_automated_reports.sql'
        ];

        foreach ($candidatePaths as $p) {
            if (file_exists($p)) {
                $sqlContent = file_get_contents($p);
                break;
            }
        }

        // If file not found, use built-in SQL
        if (empty($sqlContent)) {
            $sqlContent = $builtInSql;
        }

        // Split into individual statements
        $statements = array_filter(array_map('trim', explode(';', $sqlContent)));

        foreach ($statements as $statement) {
            if (!empty($statement)) {
                $pdo->exec($statement);
            }
        }

        // Create reports directory if it doesn't exist
        $reports_dir = dirname(__DIR__) . '/reports/generated/';
        if (!is_dir($reports_dir)) {
            @mkdir($reports_dir, 0755, true);
        }

        // Create logs directory if it doesn't exist
        $logs_dir = dirname(__DIR__) . '/logs/';
        if (!is_dir($logs_dir)) {
            @mkdir($logs_dir, 0755, true);
        }

        $message = "Automated reports system initialized successfully! Database schema and runtime storage ready.";
        $success = true;

    } catch (Exception $e) {
        $message = "Setup failed: " . $e->getMessage();
        $success = false;
    }
}

// Check live table statuses
$tableStatuses['report_distributions'] = checkTableExists($pdo, 'report_distributions');
$tableStatuses['automated_reports_schedule'] = checkTableExists($pdo, 'automated_reports_schedule');
$allTablesReady = $tableStatuses['report_distributions'] && $tableStatuses['automated_reports_schedule'];

$page_title = 'Setup Automated Reports';
require_once '../includes/header.php';
require_once '../includes/navbar.php';
?>

<div class="main-content">
    <div class="content-container" style="max-width: 850px; margin: 30px auto;">
        
        <!-- Header Banner -->
        <div class="card shadow-sm border-0 mb-4" style="border-radius: 14px; overflow: hidden; border: 1px solid rgba(46,133,110,0.2) !important;">
            <div class="card-header py-4 text-center text-white position-relative" style="background: linear-gradient(135deg, #1b5a56, #2e856e) !important;">
                <div class="display-6 fw-bold mb-1 text-white">
                    <i class="fas fa-rocket me-2"></i>Automated Reports Setup
                </div>
                <p class="mb-0 text-white-50 fs-6">Initialize & Synchronize Comprehensive Report Generation & Analytics Engine</p>
            </div>

            <div class="card-body p-4">
                
                <?php if ($message): ?>
                    <div class="alert alert-<?= $success ? 'success' : 'danger' ?> alert-dismissible fade show d-flex align-items-center shadow-sm border-0 mb-4" role="alert" style="border-radius: 10px;">
                        <i class="bi <?= $success ? 'bi-check-circle-fill text-success fs-4' : 'bi-exclamation-triangle-fill text-danger fs-4' ?> me-3"></i>
                        <div>
                            <strong><?= $success ? 'Success!' : 'Notice:' ?></strong> <?= htmlspecialchars($message) ?>
                        </div>
                        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <!-- Table Status Strip -->
                <div class="p-3 mb-4 rounded border bg-light">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fw-bold text-dark small text-uppercase"><i class="fas fa-database text-success me-2"></i>Database Table Schema Status</span>
                        <?php if ($allTablesReady): ?>
                            <span class="badge bg-success text-white px-2 py-1"><i class="bi bi-check-circle me-1"></i>All Tables Operational</span>
                        <?php else: ?>
                            <span class="badge bg-warning text-dark px-2 py-1"><i class="bi bi-exclamation-circle me-1"></i>Initialization Needed</span>
                        <?php endif; ?>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <div class="d-flex justify-content-between align-items-center p-2 bg-white rounded border">
                                <code>report_distributions</code>
                                <?= $tableStatuses['report_distributions'] ? '<span class="badge bg-success text-white">Active</span>' : '<span class="badge bg-danger text-white">Missing</span>' ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex justify-content-between align-items-center p-2 bg-white rounded border">
                                <code>automated_reports_schedule</code>
                                <?= $tableStatuses['automated_reports_schedule'] ? '<span class="badge bg-success text-white">Active</span>' : '<span class="badge bg-danger text-white">Missing</span>' ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Features Grid -->
                <h6 class="fw-bold text-dark border-bottom pb-2 mb-3"><i class="fas fa-chart-line text-success me-2"></i>Features to be Enabled:</h6>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <div class="p-3 rounded border bg-white h-100 shadow-sm">
                            <div class="fw-bold text-dark small mb-1"><i class="bi bi-check2 text-success me-1"></i> Incident Report Templates</div>
                            <small class="text-muted">Pre-formatted templates for crimes, accidents, and civil disputes.</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 rounded border bg-white h-100 shadow-sm">
                            <div class="fw-bold text-dark small mb-1"><i class="bi bi-check2 text-success me-1"></i> Automated Case Reports</div>
                            <small class="text-muted">Auto-generated comprehensive case documentation and summary logs.</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 rounded border bg-white h-100 shadow-sm">
                            <div class="fw-bold text-dark small mb-1"><i class="bi bi-check2 text-success me-1"></i> Scheduled Automated Delivery</div>
                            <small class="text-muted">Daily, weekly, monthly, and quarterly automated dispatch to supervisors.</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 rounded border bg-white h-100 shadow-sm">
                            <div class="fw-bold text-dark small mb-1"><i class="bi bi-check2 text-success me-1"></i> Analytics & Officer Metrics</div>
                            <small class="text-muted">Resolution rates, clearance times, and child incident tracking.</small>
                        </div>
                    </div>
                </div>

                <!-- Action Button -->
                <form method="POST">
                    <button type="submit" class="btn btn-success fw-bold py-3 w-100 shadow-sm fs-5" style="background-color: #2e856e; border-color: #2e856e;">
                        <i class="bi bi-gear-wide-connected me-2"></i> <?= $allTablesReady ? 'Re-Sync Automated Reports Schema' : 'Initialize Automated Reports System Now' ?>
                    </button>
                </form>

                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-4 pt-3 border-top">
                    <a href="dashboard.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i> Back to Dashboard
                    </a>
                    <div class="d-flex gap-2">
                        <a href="analytics_dashboard.php" class="btn btn-outline-success">
                            <i class="bi bi-graph-up me-1"></i> Analytics Dashboard
                        </a>
                        <a href="test_reports_analytics.php" class="btn btn-outline-info">
                            <i class="bi bi-play-circle me-1"></i> Run Tests
                        </a>
                    </div>
                </div>

            </div>
        </div>

    </div>
</div>

<?php require_once '../includes/footer.php'; ?>