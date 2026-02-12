<?php
/**
 * Database Setup Script for Automated Reports
 */

require_once 'config/db_connect.php';

try {
    echo "Setting up automated reports database tables...\n\n";

    $sql = file_get_contents('../setup_automated_reports.sql');

    // Split into individual statements
    $statements = array_filter(array_map('trim', explode(';', $sql)));

    $executed = 0;
    foreach ($statements as $statement) {
        if (!empty($statement)) {
            try {
                $pdo->exec($statement);
                echo "✓ Executed: " . substr(str_replace("\n", " ", $statement), 0, 60) . "...\n";
                $executed++;
            } catch (Exception $e) {
                echo "⚠️  Skipped (may already exist): " . substr(str_replace("\n", " ", $statement), 0, 40) . "...\n";
                echo "   Error: " . $e->getMessage() . "\n";
            }
        }
    }

    echo "\n✅ Database setup completed! $executed statements executed.\n";

    // Create directories
    $reports_dir = '../reports/generated/';
    if (!is_dir($reports_dir)) {
        mkdir($reports_dir, 0755, true);
        echo "📁 Created reports directory: $reports_dir\n";
    }

    $logs_dir = '../logs/';
    if (!is_dir($logs_dir)) {
        mkdir($logs_dir, 0755, true);
        echo "📝 Created logs directory: $logs_dir\n";
    }

    echo "\n🎉 Automated Reports System is ready!\n";
    echo "Next steps:\n";
    echo "1. Visit admin/setup_automated_reports.php to configure the system\n";
    echo "2. Check admin/analytics_dashboard.php for the enhanced dashboard\n";
    echo "3. Run admin/test_reports_analytics.php to test all functionality\n";

} catch (Exception $e) {
    echo "❌ Setup failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>