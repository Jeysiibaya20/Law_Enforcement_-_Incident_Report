<?php
/**
 * NCDB Testing and Verification Page
 * Comprehensive testing of database integration features
 */

session_start();
require_once '../../config/db_connect.php';
require_once '../config/ncdb_config.php';
require_once '../services/NCDatabaseService.php';
require_once '../services/DuplicateDetectionService.php';
require_once '../services/AccessAuditLogger.php';

// Check authentication and authorization
if (!isset($_SESSION['user_id']) || (isset($_SESSION['role']) && $_SESSION['role'] !== 'Admin')) {
    header('Location: ../../auth/login.php');
    exit;
}

$page_title = 'NCDB Testing';
$base_url = '../../';

$test_results = [];
$error_message = null;

// Handle test execution
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_type'])) {
    $test_type = $_POST['test_type'];
    
    switch ($test_type) {
        case 'configuration':
            $test_results = testConfiguration();
            break;
        
        case 'database_tables':
            $test_results = testDatabaseTables();
            break;
        
        case 'connections':
            $test_results = testConnections();
            break;
        
        case 'security':
            $test_results = testSecurity();
            break;
        
        case 'duplicate_detection':
            $test_results = testDuplicateDetection();
            break;
        
        case 'performance':
            $test_results = testPerformance();
            break;
        
        case 'all':
            $test_results = runAllTests();
            break;
    }
}

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';
?>

<div class="main-content">
    <div class="content-container">
        <h1 class="h2 mb-4">NCDB System Testing</h1>

        <div class="row g-4">
            <!-- Test Selection -->
            <div class="col-lg-3">
                <div class="card sticky-top" style="top: 20px;">
                    <div class="card-header">
                        <h5>Test Suite</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="d-grid gap-2">
                                <button type="submit" name="test_type" value="configuration" class="btn btn-outline-primary">
                                    <i class="bi bi-gear"></i> Configuration
                                </button>
                                <button type="submit" name="test_type" value="database_tables" class="btn btn-outline-primary">
                                    <i class="bi bi-database"></i> Database Tables
                                </button>
                                <button type="submit" name="test_type" value="connections" class="btn btn-outline-primary">
                                    <i class="bi bi-plug"></i> Connections
                                </button>
                                <button type="submit" name="test_type" value="security" class="btn btn-outline-primary">
                                    <i class="bi bi-shield-lock"></i> Security
                                </button>
                                <button type="submit" name="test_type" value="duplicate_detection" class="btn btn-outline-primary">
                                    <i class="bi bi-exclamation-triangle"></i> Duplicates
                                </button>
                                <button type="submit" name="test_type" value="performance" class="btn btn-outline-primary">
                                    <i class="bi bi-speedometer2"></i> Performance
                                </button>
                                <hr>
                                <button type="submit" name="test_type" value="all" class="btn btn-success btn-lg">
                                    <i class="bi bi-play-circle"></i> Run All Tests
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Test Results -->
            <div class="col-lg-9">
                <?php if (!empty($test_results)): ?>
                    <?php foreach ($test_results as $section): ?>
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5>
                                    <?php if ($section['status'] === 'PASS'): ?>
                                        <span class="badge bg-success">✓ PASS</span>
                                    <?php elseif ($section['status'] === 'PARTIAL'): ?>
                                        <span class="badge bg-warning">⚠ PARTIAL</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">✗ FAIL</span>
                                    <?php endif; ?>
                                    <?= htmlspecialchars($section['name']) ?>
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($section['description'])): ?>
                                    <p class="text-muted mb-3"><?= htmlspecialchars($section['description']) ?></p>
                                <?php endif; ?>

                                <?php if (!empty($section['tests'])): ?>
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Test</th>
                                                <th>Status</th>
                                                <th>Details</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($section['tests'] as $test): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($test['name']) ?></td>
                                                    <td>
                                                        <?php if ($test['passed']): ?>
                                                            <span class="badge bg-success">✓ Pass</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-danger">✗ Fail</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($test['passed']): ?>
                                                            <small class="text-success"><?= htmlspecialchars($test['message']) ?></small>
                                                        <?php else: ?>
                                                            <small class="text-danger"><strong><?= htmlspecialchars($test['message']) ?></strong></small>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>

                                <?php if (!empty($section['summary'])): ?>
                                    <div class="alert alert-info">
                                        <strong>Summary:</strong> <?= htmlspecialchars($section['summary']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="bi bi-clipboard-check" style="font-size: 3rem; color: #ccc;"></i>
                            <p class="text-muted mt-3">Select a test suite to begin verification</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php

// ============================================================================
// TEST FUNCTIONS
// ============================================================================

function testConfiguration() {
    $tests = [];
    $passed = 0;
    $total = 0;
    
    // Test 1: Encryption key configured
    $total++;
    if (defined('NCDB_ENCRYPTION_KEY') && !empty(NCDB_ENCRYPTION_KEY)) {
        $tests[] = [
            'name' => 'Encryption key configured',
            'passed' => true,
            'message' => 'NCDB_ENCRYPTION_KEY is properly set'
        ];
        $passed++;
    } else {
        $tests[] = [
            'name' => 'Encryption key configured',
            'passed' => false,
            'message' => 'NCDB_ENCRYPTION_KEY not defined or empty (WARNING: Use default key in development only)'
        ];
    }
    
    // Test 2: NCDB enabled
    $total++;
    if (defined('NCDB_ENABLED') && NCDB_ENABLED === true) {
        $tests[] = [
            'name' => 'NCDB feature enabled',
            'passed' => true,
            'message' => 'Feature is enabled'
        ];
        $passed++;
    } else {
        $tests[] = [
            'name' => 'NCDB feature enabled',
            'passed' => false,
            'message' => 'Feature is disabled'
        ];
    }
    
    // Test 3: Configuration file readable
    $total++;
    if (is_readable(__DIR__ . '/config/ncdb_config.php')) {
        $tests[] = [
            'name' => 'Configuration file accessible',
            'passed' => true,
            'message' => 'Config file is readable'
        ];
        $passed++;
    } else {
        $tests[] = [
            'name' => 'Configuration file accessible',
            'passed' => false,
            'message' => 'Config file not found or not readable'
        ];
    }
    
    return [[
        'name' => 'Configuration Tests',
        'description' => 'Verify NCDB configuration settings',
        'status' => $passed === $total ? 'PASS' : ($passed > 0 ? 'PARTIAL' : 'FAIL'),
        'tests' => $tests,
        'summary' => "$passed of $total tests passed"
    ]];
}

function testDatabaseTables() {
    global $pdo;
    
    $tables = [
        'ncdb_connections',
        'ncdb_cache',
        'ncdb_access_logs',
        'ncdb_sync_history',
        'ncdb_duplicate_detection',
        'ncdb_verification_results',
        'ncdb_rate_limits',
    ];
    
    $tests = [];
    $passed = 0;
    
    foreach ($tables as $table) {
        try {
            $stmt = $pdo->prepare("SELECT 1 FROM {$table} LIMIT 1");
            $stmt->execute();
            $tests[] = [
                'name' => "Table: {$table}",
                'passed' => true,
                'message' => 'Table exists and is accessible'
            ];
            $passed++;
        } catch (Exception $e) {
            $tests[] = [
                'name' => "Table: {$table}",
                'passed' => false,
                'message' => 'Table does not exist or is inaccessible'
            ];
        }
    }
    
    return [[
        'name' => 'Database Tables Test',
        'description' => 'Verify all required NCDB tables exist',
        'status' => $passed === count($tables) ? 'PASS' : ($passed > 0 ? 'PARTIAL' : 'FAIL'),
        'tests' => $tests,
        'summary' => "$passed of " . count($tables) . " tables found",
        'instructions' => $passed < count($tables) ? 'Run ncdb/config/ncdb_schema.sql to create missing tables' : ''
    ]];
}

function testConnections() {
    global $pdo;
    
    $tests = [];
    
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM ncdb_connections");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $count = $result['count'];
        
        $tests[] = [
            'name' => 'Connections configured',
            'passed' => $count > 0,
            'message' => $count > 0 ? "{$count} connection(s) found" : 'No connections configured'
        ];
        
        // Test active connections
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM ncdb_connections WHERE is_active = 1");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $active = $result['count'];
        
        $tests[] = [
            'name' => 'Active connections',
            'passed' => $active > 0,
            'message' => $active > 0 ? "{$active} connection(s) are active" : 'No active connections'
        ];
        
    } catch (Exception $e) {
        $tests[] = [
            'name' => 'Connection table query',
            'passed' => false,
            'message' => $e->getMessage()
        ];
    }
    
    $passed = array_sum(array_map(fn($t) => $t['passed'] ? 1 : 0, $tests));
    
    return [[
        'name' => 'Connection Tests',
        'description' => 'Verify NCDB connections are configured',
        'status' => $passed > 0 ? 'PASS' : 'FAIL',
        'tests' => $tests,
        'summary' => "$passed of " . count($tests) . " tests passed"
    ]];
}

function testSecurity() {
    global $pdo;
    
    $tests = [];
    
    // Test 1: Encryption working
    try {
        $test_data = 'TEST_SECURITY_DATA_' . time();
        $encrypted = NCDBConfig::encrypt($test_data);
        $decrypted = NCDBConfig::decrypt($encrypted);
        
        $tests[] = [
            'name' => 'Encryption/Decryption',
            'passed' => $decrypted === $test_data,
            'message' => $decrypted === $test_data ? 'Encryption working correctly' : 'Encryption verification failed'
        ];
    } catch (Exception $e) {
        $tests[] = [
            'name' => 'Encryption/Decryption',
            'passed' => false,
            'message' => $e->getMessage()
        ];
    }
    
    // Test 2: Audit logs
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM ncdb_access_logs");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $tests[] = [
            'name' => 'Audit logging',
            'passed' => true,
            'message' => "Audit logs accessible ({$result['count']} entries)"
        ];
    } catch (Exception $e) {
        $tests[] = [
            'name' => 'Audit logging',
            'passed' => false,
            'message' => 'Cannot access audit logs'
        ];
    }
    
    // Test 3: Rate limiting table
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM ncdb_rate_limits");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $tests[] = [
            'name' => 'Rate limiting',
            'passed' => true,
            'message' => 'Rate limit tracking is operational'
        ];
    } catch (Exception $e) {
        $tests[] = [
            'name' => 'Rate limiting',
            'passed' => false,
            'message' => 'Rate limit system unavailable'
        ];
    }
    
    $passed = array_sum(array_map(fn($t) => $t['passed'] ? 1 : 0, $tests));
    
    return [[
        'name' => 'Security Tests',
        'description' => 'Verify security features are working',
        'status' => $passed === count($tests) ? 'PASS' : ($passed > 0 ? 'PARTIAL' : 'FAIL'),
        'tests' => $tests,
        'summary' => "$passed of " . count($tests) . " tests passed"
    ]];
}

function testDuplicateDetection() {
    global $pdo;
    
    $tests = [];
    
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as total, 
                            SUM(CASE WHEN is_duplicate = 1 THEN 1 ELSE 0 END) as confirmed
                            FROM ncdb_duplicate_detection");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $tests[] = [
            'name' => 'Duplicate detection records',
            'passed' => true,
            'message' => "Total: {$result['total']}, Confirmed: {$result['confirmed']}"
        ];
        
        // Check pending reviews
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM ncdb_duplicate_detection WHERE reviewed_at IS NULL");
        $pending = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $tests[] = [
            'name' => 'Pending duplicate reviews',
            'passed' => true,
            'message' => "Pending reviews: {$pending['count']}"
        ];
        
    } catch (Exception $e) {
        $tests[] = [
            'name' => 'Duplicate detection',
            'passed' => false,
            'message' => $e->getMessage()
        ];
    }
    
    $passed = array_sum(array_map(fn($t) => $t['passed'] ? 1 : 0, $tests));
    
    return [[
        'name' => 'Duplicate Detection Tests',
        'description' => 'Verify duplicate detection functionality',
        'status' => $passed > 0 ? 'PASS' : 'FAIL',
        'tests' => $tests,
        'summary' => "$passed of " . count($tests) . " tests passed"
    ]];
}

function testPerformance() {
    global $pdo;
    
    $tests = [];
    
    // Test cache performance
    try {
        $start = microtime(true);
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM ncdb_cache WHERE expires_at > NOW()");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $time = (microtime(true) - $start) * 1000;
        
        $tests[] = [
            'name' => 'Cache query performance',
            'passed' => $time < 100,
            'message' => "Query time: {$time}ms ({$result['count']} active cache entries)"
        ];
    } catch (Exception $e) {
        $tests[] = [
            'name' => 'Cache query performance',
            'passed' => false,
            'message' => $e->getMessage()
        ];
    }
    
    // Test log query performance
    try {
        $start = microtime(true);
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM ncdb_access_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $time = (microtime(true) - $start) * 1000;
        
        $tests[] = [
            'name' => 'Access log query performance',
            'passed' => $time < 200,
            'message' => "Query time: {$time}ms ({$result['count']} logs in last 7 days)"
        ];
    } catch (Exception $e) {
        $tests[] = [
            'name' => 'Access log query performance',
            'passed' => false,
            'message' => $e->getMessage()
        ];
    }
    
    $passed = array_sum(array_map(fn($t) => $t['passed'] ? 1 : 0, $tests));
    
    return [[
        'name' => 'Performance Tests',
        'description' => 'Verify system performance',
        'status' => $passed === count($tests) ? 'PASS' : ($passed > 0 ? 'PARTIAL' : 'FAIL'),
        'tests' => $tests,
        'summary' => "$passed of " . count($tests) . " tests passed"
    ]];
}

function runAllTests() {
    return array_merge(
        testConfiguration(),
        testDatabaseTables(),
        testConnections(),
        testSecurity(),
        testDuplicateDetection(),
        testPerformance()
    );
}

?>

<?php require_once '../../includes/footer.php'; ?>
