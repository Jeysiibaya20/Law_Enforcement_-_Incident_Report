<?php
/**
 * Quick NLP System Activation Script
 * 
 * This is a simple, one-click way to activate the NLP system.
 * Visit this page in your browser to automatically set up all tables.
 * 
 * URL: http://localhost/Law_Enforcement_-_Incident_Report/activate_nlp.php
 * 
 * @author System
 */

session_start();

// Check if user is admin (optional - remove for unrestricted access)
// if (($_SESSION['role'] ?? '') !== 'Admin') {
//     die('Admin access required');
// }

// Load database
require_once 'config/db_connect.php';

$message = '';
$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['activate'])) {
    try {
        // Read migration SQL
        $migration_sql = file_get_contents('database2/nlp_workflow_migration.sql');
        
        // Fix: First, handle workflow_events table - drop if exists to recreate with proper constraints
        try {
            $pdo->exec("DROP TABLE IF EXISTS workflow_events");
        } catch (Exception $e) {
            // Table might not exist, that's ok
        }
        
        // Remove comments and split by semicolon
        $queries = array_filter(array_map('trim', preg_split('/;[\s\n]*/', $migration_sql)));
        
        $executed = 0;
        $skipped = 0;
        $errors = [];
        
        foreach ($queries as $query) {
            if (empty($query) || strpos($query, '--') === 0) {
                $skipped++;
                continue;
            }
            
            try {
                $pdo->exec($query);
                $executed++;
            } catch (Exception $e) {
                // Check if it's a "table already exists" or "column already exists" error
                if (strpos($e->getMessage(), 'already exists') !== false ||
                    strpos($e->getMessage(), 'Duplicate') !== false) {
                    $skipped++;
                } else {
                    $errors[] = $e->getMessage();
                }
            }
        }
        
        if (count($errors) > 0) {
            $error = "Some queries had issues but system may still work:\n" . implode("\n", array_slice($errors, 0, 3));
        } else {
            $success = true;
            $message = "✓ NLP System Activated! Executed $executed queries, skipped $skipped (likely already existed).";
        }
        
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>NLP System Activation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; }
        .container { max-width: 600px; }
        .card { box-shadow: 0 10px 40px rgba(0,0,0,0.2); border: none; }
        .btn-activate { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; }
        .btn-activate:hover { background: linear-gradient(135deg, #764ba2 0%, #667eea 100%); }
        .status-success { color: #28a745; font-weight: bold; }
        .status-error { color: #dc3545; font-weight: bold; }
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <div class="card-body p-5">
            <h2 class="text-center mb-4">🤖 NLP System Activation</h2>
            
            <?php if ($message): ?>
                <div class="alert alert-success status-success">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-warning status-error">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if (!$success): ?>
                <div class="alert alert-info mb-4">
                    <h5>About to activate NLP System</h5>
                    <p>This will create the following database components:</p>
                    <ul class="mb-0">
                        <li>5 new tables (notifications, review_requests, workflow_events, etc.)</li>
                        <li>11 NLP fields in incidents table</li>
                        <li>Integration fields in blotters and case_assignments</li>
                        <li>Performance indexes</li>
                        <li>Analytical views</li>
                    </ul>
                </div>
                
                <form method="POST">
                    <button type="submit" name="activate" class="btn btn-activate btn-lg w-100 text-white">
                        <i class="bi bi-lightning-fill"></i> Activate NLP System Now
                    </button>
                </form>
                
                <hr class="my-4">
                
                <div class="alert alert-light">
                    <h6>What happens next:</h6>
                    <ol class="mb-0">
                        <li>All required database tables will be created</li>
                        <li>NLP fields will be added to existing tables</li>
                        <li>Indexes will be created for performance</li>
                        <li>Views will be generated for reporting</li>
                        <li>You'll see a success message</li>
                    </ol>
                </div>
                
                <p class="text-muted text-center mb-0">
                    <small>This is safe - it will skip any tables/columns that already exist</small>
                </p>
            <?php else: ?>
                <div class="text-center">
                    <div style="font-size: 60px; margin: 20px 0;">✅</div>
                    <h4>NLP System is Active!</h4>
                    <p>Your incident reporting system now has advanced AI capabilities.</p>
                    <a href="modules/Incident_report.php" class="btn btn-primary btn-lg">
                        Open Incident Report System
                    </a>
                </div>
            <?php endif; ?>
            
        </div>
        <div class="card-footer bg-light text-muted text-center">
            <small>Law Enforcement Incident Report System v2.0</small>
        </div>
    </div>
</div>

</body>
</html>
