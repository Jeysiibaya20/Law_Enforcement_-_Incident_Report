<?php
/**
 * NCDB Admin Dashboard
 * Admin interface for managing NCDB connections, settings, and sync history
 * 
 * SECURITY FEATURES:
 * - Enhanced admin-only access control
 * - Session validation
 * - Encryption verification
 * - IP logging and threat detection
 * - Rate limiting
 * - Comprehensive audit logging
 */

session_start();
require_once '../../config/db_connect.php';
require_once '../config/ncdb_config.php';
require_once '../services/NCDatabaseService.php';
require_once '../services/AccessAuditLogger.php';
require_once '../admin_access.php';

// Verify secure admin access
verifyNCDBAminAccess();

// Check authentication and authorization
if (!isset($_SESSION['user_id']) || (isset($_SESSION['role']) && $_SESSION['role'] !== 'Admin')) {
    header('Location: ../../auth/login.php');
    exit;
}

// Verify encryption configuration
if (!verifyNCDBAEncryption()) {
    $encryption_warning = "NCDB_ENCRYPTION_KEY not properly configured. Please configure encryption for security.";
}

$page_title = 'NCDB Administration';
$base_url = '../../';

// Initialize services
$audit_logger = new AccessAuditLogger($pdo);

$error_message = null;
$success_message = null;

// Handle add connection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_connection') {
    try {
        $connection_name = trim($_POST['connection_name'] ?? '');
        $api_endpoint = trim($_POST['api_endpoint'] ?? '');
        $api_key = trim($_POST['api_key'] ?? '');
        $api_secret = trim($_POST['api_secret'] ?? '');
        $connection_type = $_POST['connection_type'] ?? 'REST';
        $timeout = intval($_POST['timeout'] ?? 30);
        $retry_attempts = intval($_POST['retry_attempts'] ?? 3);
        
        if (empty($connection_name) || empty($api_endpoint)) {
            throw new Exception('Connection name and endpoint are required');
        }
        
        // Encrypt credentials
        $encrypted_key = NCDBConfig::encrypt($api_key);
        $encrypted_secret = NCDBConfig::encrypt($api_secret);
        
        $sql = "INSERT INTO ncdb_connections (
            connection_name,
            api_endpoint,
            api_key_encrypted,
            api_secret_encrypted,
            connection_type,
            timeout_seconds,
            retry_attempts,
            created_by,
            created_at
        ) VALUES (
            :name,
            :endpoint,
            :key,
            :secret,
            :type,
            :timeout,
            :retry,
            :user_id,
            NOW()
        )";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':name' => $connection_name,
            ':endpoint' => $api_endpoint,
            ':key' => $encrypted_key,
            ':secret' => $encrypted_secret,
            ':type' => $connection_type,
            ':timeout' => $timeout,
            ':retry' => $retry_attempts,
            ':user_id' => $_SESSION['user_id'],
        ]);
        
        $success_message = "Connection '{$connection_name}' added successfully!";
        
        $audit_logger->logAccess(
            'CONFIG_CHANGE',
            'ADD_CONNECTION',
            ['connection_name' => $connection_name],
            null,
            null,
            'SUCCESS'
        );
    } catch (Exception $e) {
        $error_message = $e->getMessage();
        $audit_logger->logAccess(
            'CONFIG_CHANGE',
            'ADD_CONNECTION',
            ['error' => $error_message],
            null,
            null,
            'FAILED',
            $error_message
        );
    }
}

// Handle test connection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'test_connection') {
    try {
        $connection_id = intval($_POST['connection_id'] ?? 0);
        if ($connection_id <= 0) {
            throw new Exception('Invalid connection ID');
        }
        
        $ncdb_service = new NCDatabaseService($pdo, $connection_id);
        $test_result = $ncdb_service->testConnection($connection_id);
        
        if ($test_result['success']) {
            $success_message = "Connection test passed! Status: {$test_result['status']}";
        } else {
            $error_message = "Connection test failed: {$test_result['message']}";
        }
        
        $audit_logger->logAccess(
            'TEST_CONNECTION',
            'CONNECTION_TEST',
            ['connection_id' => $connection_id],
            null,
            null,
            $test_result['success'] ? 'SUCCESS' : 'FAILED',
            $test_result['message']
        );
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Handle toggle connection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_connection') {
    try {
        $connection_id = intval($_POST['connection_id'] ?? 0);
        $is_active = intval($_POST['is_active'] ?? 0);
        
        $sql = "UPDATE ncdb_connections SET is_active = :active, updated_by = :user_id, updated_at = NOW() WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':id' => $connection_id,
            ':active' => $is_active,
            ':user_id' => $_SESSION['user_id'],
        ]);
        
        $success_message = "Connection status updated!";
        
        $audit_logger->logAccess(
            'CONFIG_CHANGE',
            'TOGGLE_CONNECTION',
            ['connection_id' => $connection_id, 'active' => $is_active],
            null,
            null,
            'SUCCESS'
        );
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Fetch all connections
$sql = "SELECT 
        id,
        connection_name,
        api_endpoint,
        connection_type,
        is_active,
        last_tested_at,
        test_status,
        test_error_message,
        created_at,
        created_by
        FROM ncdb_connections
        ORDER BY created_at DESC";

$connections = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// Fetch sync history
$sync_sql = "SELECT 
            id,
            connection_id,
            sync_type,
            sync_start_time,
            sync_end_time,
            records_processed,
            records_synced,
            duplicates_found,
            status,
            initiated_by,
            created_at
            FROM ncdb_sync_history
            ORDER BY created_at DESC
            LIMIT 50";

$sync_history = $pdo->query($sync_sql)->fetchAll(PDO::FETCH_ASSOC);

// Get access logs
$logs_sql = "SELECT 
            user_id,
            action_type,
            COUNT(*) as count,
            SUM(CASE WHEN status = 'SUCCESS' THEN 1 ELSE 0 END) as successful,
            SUM(CASE WHEN status = 'FAILED' THEN 1 ELSE 0 END) as failed,
            SUM(CASE WHEN is_suspicious = 1 THEN 1 ELSE 0 END) as suspicious,
            MAX(created_at) as last_action
            FROM ncdb_access_logs
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY user_id, action_type
            ORDER BY count DESC
            LIMIT 50";

$access_logs = $pdo->query($logs_sql)->fetchAll(PDO::FETCH_ASSOC);

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';
?>

<div class="main-content">
    <div class="content-container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h2 mb-0">NCDB Administration</h1>
            <div>
                <span class="badge bg-success me-2">
                    <i class="bi bi-lock-fill"></i> Secure Admin Panel
                </span>
                <span class="badge bg-info">
                    <i class="bi bi-shield-check"></i> Encryption Active
                </span>
            </div>
        </div>

        <?php if (!empty($encryption_warning)): ?>
            <div class="alert alert-warning alert-dismissible">
                <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($encryption_warning) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger alert-dismissible">
                <i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($error_message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success alert-dismissible">
                <i class="bi bi-check-circle"></i> <?= htmlspecialchars($success_message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Nav Tabs -->
        <ul class="nav nav-tabs mb-4" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#connections">
                    <i class="bi bi-plug"></i> Connections
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#sync-history">
                    <i class="bi bi-arrow-repeat"></i> Sync History
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#access-logs">
                    <i class="bi bi-shield-lock"></i> Access Logs
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#settings">
                    <i class="bi bi-gear"></i> Settings
                </button>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content">
            <!-- Connections Tab -->
            <div class="tab-pane fade show active" id="connections" role="tabpanel">
                <div class="row g-4">
                    <!-- Add Connection Form -->
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h5>Add New Connection</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="action" value="add_connection">
                                    
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Connection Name</label>
                                        <input type="text" name="connection_name" class="form-control" required placeholder="e.g., PNP Criminal Records">
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label fw-bold">API Endpoint</label>
                                        <input type="url" name="api_endpoint" class="form-control" required placeholder="https://ncdb.example.com/api/v1">
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Connection Type</label>
                                        <select name="connection_type" class="form-select">
                                            <option value="REST">REST API</option>
                                            <option value="SOAP">SOAP Web Service</option>
                                            <option value="DATABASE">Direct Database</option>
                                            <option value="FILE">File-based</option>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label fw-bold">API Key</label>
                                        <input type="password" name="api_key" class="form-control" placeholder="Your API key (encrypted)">
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label fw-bold">API Secret</label>
                                        <input type="password" name="api_secret" class="form-control" placeholder="Your API secret (encrypted)">
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label fw-bold">Timeout (seconds)</label>
                                                <input type="number" name="timeout" class="form-control" value="30" min="5" max="300">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label fw-bold">Retry Attempts</label>
                                                <input type="number" name="retry_attempts" class="form-control" value="3" min="1" max="10">
                                            </div>
                                        </div>
                                    </div>

                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="bi bi-plus-circle"></i> Add Connection
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Connections List -->
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h5>Active Connections (<?= count($connections) ?>)</h5>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($connections)): ?>
                                    <div class="list-group">
                                        <?php foreach ($connections as $conn): ?>
                                            <div class="list-group-item">
                                                <div class="d-flex w-100 justify-content-between align-items-start">
                                                    <div>
                                                        <h6 class="mb-1"><?= htmlspecialchars($conn['connection_name']) ?></h6>
                                                        <p class="mb-1 small text-muted"><?= htmlspecialchars($conn['api_endpoint']) ?></p>
                                                        <small>
                                                            Type: <span class="badge bg-info"><?= htmlspecialchars($conn['connection_type']) ?></span>
                                                            <?php if ($conn['test_status'] === 'ACTIVE'): ?>
                                                                <span class="badge bg-success">✓ Active</span>
                                                            <?php elseif ($conn['test_status'] === 'ERROR'): ?>
                                                                <span class="badge bg-danger">✗ Error</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-secondary">Unknown</span>
                                                            <?php endif; ?>
                                                        </small>
                                                    </div>
                                                    <div>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="action" value="test_connection">
                                                            <input type="hidden" name="connection_id" value="<?= $conn['id'] ?>">
                                                            <button type="submit" class="btn btn-sm btn-primary">
                                                                <i class="bi bi-arrow-repeat"></i> Test
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>
                                                <?php if ($conn['test_status'] === 'ERROR' && !empty($conn['test_error_message'])): ?>
                                                    <div class="alert alert-danger alert-sm mt-2 mb-0">
                                                        <small><?= htmlspecialchars($conn['test_error_message']) ?></small>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted">No connections configured</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sync History Tab -->
            <div class="tab-pane fade" id="sync-history" role="tabpanel">
                <div class="card">
                    <div class="card-header">
                        <h5>NCDB Synchronization History</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($sync_history)): ?>
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Type</th>
                                        <th>Status</th>
                                        <th>Processed</th>
                                        <th>Synced</th>
                                        <th>Duplicates</th>
                                        <th>Start Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($sync_history as $sync): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($sync['sync_type']) ?></td>
                                            <td>
                                                <span class="badge bg-<?= $sync['status'] === 'COMPLETED' ? 'success' : 'warning' ?>">
                                                    <?= htmlspecialchars($sync['status']) ?>
                                                </span>
                                            </td>
                                            <td><?= $sync['records_processed'] ?? 0 ?></td>
                                            <td><?= $sync['records_synced'] ?? 0 ?></td>
                                            <td><?= $sync['duplicates_found'] ?? 0 ?></td>
                                            <td><?= date('M d, H:i', strtotime($sync['created_at'])) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p class="text-muted">No synchronization history</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Access Logs Tab -->
            <div class="tab-pane fade" id="access-logs" role="tabpanel">
                <div class="card">
                    <div class="card-header">
                        <h5>NCDB Access Logs (Last 7 Days)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($access_logs)): ?>
                            <table class="table table-striped table-sm">
                                <thead>
                                    <tr>
                                        <th>User ID</th>
                                        <th>Action Type</th>
                                        <th>Total</th>
                                        <th>Success</th>
                                        <th>Failed</th>
                                        <th>Suspicious</th>
                                        <th>Last Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($access_logs as $log): ?>
                                        <tr>
                                            <td><?= $log['user_id'] ?></td>
                                            <td><?= htmlspecialchars($log['action_type']) ?></td>
                                            <td><?= $log['count'] ?></td>
                                            <td><span class="badge bg-success"><?= $log['successful'] ?></span></td>
                                            <td><span class="badge bg-danger"><?= $log['failed'] ?></span></td>
                                            <td><?= $log['suspicious'] > 0 ? "<span class='badge bg-warning'>{$log['suspicious']}</span>" : '0' ?></td>
                                            <td><?= date('M d, H:i', strtotime($log['last_action'])) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p class="text-muted">No access logs</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Settings Tab -->
            <div class="tab-pane fade" id="settings" role="tabpanel">
                <div class="card">
                    <div class="card-header">
                        <h5>NCDB Configuration Settings</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="settings-form">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <h6>Cache Settings</h6>
                                    <div class="mb-3">
                                        <label class="form-label">Cache TTL (seconds)</label>
                                        <input type="number" class="form-control" value="<?= NCDBConfig::get('cache.ttl_seconds') ?>" disabled>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <h6>Rate Limiting</h6>
                                    <div class="mb-3">
                                        <label class="form-label">Requests per Minute</label>
                                        <input type="number" class="form-control" value="<?= NCDBConfig::get('rate_limit.requests_per_minute') ?>" disabled>
                                    </div>
                                </div>
                            </div>

                            <hr>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <h6>Duplicate Detection</h6>
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" id="dup-enabled" disabled <?= NCDBConfig::get('duplicate_detection.enabled') ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="dup-enabled">Enabled</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <h6>Audit Logging</h6>
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" id="audit-enabled" disabled <?= NCDBConfig::get('audit.log_all_queries') ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="audit-enabled">Log All Queries</label>
                                    </div>
                                </div>
                            </div>

                            <div class="alert alert-info mt-4">
                                <i class="bi bi-info-circle"></i> To modify these settings, edit the configuration file in <code>ncdb/config/ncdb_config.php</code>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
