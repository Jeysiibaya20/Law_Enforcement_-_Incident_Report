<?php
/**
 * Setup Automated Reports System
 *
 * Initializes the automated report generation system
 */

require_once 'admin_auth.php';
require_once '../config/db_connect.php';



$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Read and execute the SQL setup file
        $sql_file = __DIR__ . '/../setup_automated_reports.sql';
        if (file_exists($sql_file)) {
            $sql_content = file_get_contents($sql_file);

            // Split into individual statements
            $statements = array_filter(array_map('trim', explode(';', $sql_content)));

            foreach ($statements as $statement) {
                if (!empty($statement)) {
                    $pdo->exec($statement);
                }
            }

            $message = "✅ Automated reports system initialized successfully!";
            $success = true;

            // Create reports directory if it doesn't exist
            $reports_dir = __DIR__ . '/../reports/generated/';
            if (!is_dir($reports_dir)) {
                mkdir($reports_dir, 0755, true);
                $message .= "<br>📁 Reports directory created.";
            }

            // Create logs directory if it doesn't exist
            $logs_dir = __DIR__ . '/../logs/';
            if (!is_dir($logs_dir)) {
                mkdir($logs_dir, 0755, true);
                $message .= "<br>📝 Logs directory created.";
            }

        } else {
            throw new Exception("Setup SQL file not found: $sql_file");
        }

    } catch (Exception $e) {
        $message = "❌ Setup failed: " . $e->getMessage();
        $success = false;
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Automated Reports - Law Enforcement System</title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .setup-container {
            max-width: 800px;
            margin: 50px auto;
            padding: 30px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }

        .setup-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .setup-header h1 {
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .feature-list {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }

        .feature-list h3 {
            color: #3498db;
            margin-bottom: 15px;
        }

        .feature-list ul {
            list-style: none;
            padding: 0;
        }

        .feature-list li {
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }

        .feature-list li:before {
            content: "✓";
            color: #27ae60;
            font-weight: bold;
            margin-right: 10px;
        }

        .alert {
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .btn-setup {
            background: #3498db;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            width: 100%;
        }

        .btn-setup:hover {
            background: #2980b9;
        }

        .btn-setup:disabled {
            background: #bdc3c7;
            cursor: not-allowed;
        }
    </style>
</head>
<body>

<div class="setup-container">
    <div class="setup-header">
        <h1>🚀 Setup Automated Reports System</h1>
        <p>Initialize the comprehensive report generation and analytics system</p>
    </div>

    <?php if ($message): ?>
        <div class="alert <?php echo $success ? 'alert-success' : 'alert-danger'; ?>">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <div class="feature-list">
        <h3>📊 Features to be Enabled:</h3>
        <ul>
            <li><strong>Incident Report Templates:</strong> Pre-formatted templates for various incident types</li>
            <li><strong>Automated Case Reports:</strong> Auto-generated comprehensive case documentation</li>
            <li><strong>Analytics Dashboard:</strong> Real-time insights and trend analysis</li>
            <li><strong>Child Incident Tracking:</strong> Specialized monitoring for child-related cases</li>
            <li><strong>Decision Support Reports:</strong> Data-driven recommendations for planning</li>
            <li><strong>Scheduled Reports:</strong> Daily, weekly, monthly, and quarterly automated generation</li>
            <li><strong>Export Capabilities:</strong> PDF, HTML, and CSV export options</li>
            <li><strong>Performance Metrics:</strong> Officer performance and case resolution tracking</li>
        </ul>
    </div>

    <div class="feature-list">
        <h3>📋 Database Tables to Create:</h3>
        <ul>
            <li><code>report_distributions</code> - Track automated report generation and distribution</li>
            <li><code>automated_reports_schedule</code> - Store scheduling configuration</li>
        </ul>
    </div>

    <form method="POST">
        <button type="submit" class="btn-setup" <?php echo $success ? 'disabled' : ''; ?>>
            <?php echo $success ? '✅ System Initialized' : '🚀 Initialize Automated Reports System'; ?>
        </button>
    </form>

    <div style="margin-top: 30px; text-align: center;">
        <a href="dashboard.php" class="btn btn-secondary">← Back to Admin Dashboard</a>
        <?php if ($success): ?>
            <a href="analytics_dashboard.php" class="btn btn-primary" style="margin-left: 10px;">📊 View Analytics Dashboard</a>
            <a href="test_reports_analytics.php" class="btn btn-info" style="margin-left: 10px;">🧪 Run Tests</a>
        <?php endif; ?>
    </div>
</div>

</body>
</html>