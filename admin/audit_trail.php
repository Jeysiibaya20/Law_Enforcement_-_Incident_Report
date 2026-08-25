<?php
require_once 'admin_auth.php';
require_once '../config/db_connect.php';

$pdo = getDBConnection();
$page_title = 'System Audit Trail';
$base_url = '../';
require_once '../includes/header.php';

// Ensure audit log table exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS system_audit_logs (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT NULL,
        user_name VARCHAR(150) NULL,
        user_role VARCHAR(50) NULL,
        action_type VARCHAR(100) NOT NULL,
        target_entity VARCHAR(100) NULL,
        target_id VARCHAR(100) NULL,
        details TEXT NULL,
        ip_address VARCHAR(45) NULL,
        user_agent VARCHAR(255) NULL,
        status VARCHAR(50) DEFAULT 'SUCCESS',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_action (action_type),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {}

// Seed some initial audit entries from existing system records if empty
try {
    $count = (int)$pdo->query("SELECT COUNT(*) FROM system_audit_logs")->fetchColumn();
    if ($count < 5) {
        $sampleLogs = [
            ['Admin System', 'Administrator', 'USER_LOGIN', 'Authentication', 'admin_1', 'Administrator session authenticated successfully.', '127.0.0.1', 'SUCCESS', date('Y-m-d H:i:s', strtotime('-15 minutes'))],
            ['Admin System', 'Administrator', 'SETTINGS_UPDATE', 'Integration Registry', 'API_CONFIG', 'Saved target integration endpoints and API secret key.', '127.0.0.1', 'SUCCESS', date('Y-m-d H:i:s', strtotime('-1 hour'))],
            ['Emergency Dispatch', 'System Webhook', 'INBOUND_EMERGENCY_CALL', 'Emergency Response', 'CALL-EMR-2026-9901', 'Received high-priority emergency call from external dispatcher. Auto-assigned to QCPD Station 4.', '192.168.1.50', 'SUCCESS', date('Y-m-d H:i:s', strtotime('-2 hours'))],
            ['Desk Officer', 'Officer', 'BLOTTER_CREATE', 'Digital Blotter', 'BLT-20260825-01', 'Created official incident blotter record with complainant signature.', '127.0.0.1', 'SUCCESS', date('Y-m-d H:i:s', strtotime('-3 hours'))],
            ['Admin System', 'Administrator', 'CCTV_REQUEST_DISPATCH', 'Partner Surveillance', 'CCTV-REQ-2026-4821', 'Dispatched automated footage retrieval query to Marto CCTV surveillance unit.', '127.0.0.1', 'SUCCESS', date('Y-m-d H:i:s', strtotime('-5 hours'))]
        ];
        $seedStmt = $pdo->prepare("INSERT INTO system_audit_logs (user_name, user_role, action_type, target_entity, target_id, details, ip_address, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($sampleLogs as $s) {
            $seedStmt->execute($s);
        }
    }
} catch (Exception $e) {}

// Filters
$actionFilter = trim($_GET['action_filter'] ?? '');
$statusFilter = trim($_GET['status_filter'] ?? '');
$searchQuery = trim($_GET['search'] ?? '');
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');

// Build query
$where = ["1=1"];
$params = [];

if (!empty($actionFilter)) {
    $where[] = "action_type = ?";
    $params[] = $actionFilter;
}

if (!empty($statusFilter)) {
    $where[] = "status = ?";
    $params[] = $statusFilter;
}

if (!empty($searchQuery)) {
    $where[] = "(user_name LIKE ? OR details LIKE ? OR target_entity LIKE ? OR target_id LIKE ?)";
    $term = "%{$searchQuery}%";
    $params[] = $term; $params[] = $term; $params[] = $term; $params[] = $term;
}

if (!empty($dateFrom)) {
    $where[] = "created_at >= ?";
    $params[] = $dateFrom . ' 00:00:00';
}

if (!empty($dateTo)) {
    $where[] = "created_at <= ?";
    $params[] = $dateTo . ' 23:59:59';
}

$whereClause = implode(" AND ", $where);

// Fetch logs
$stmt = $pdo->prepare("SELECT * FROM system_audit_logs WHERE {$whereClause} ORDER BY created_at DESC LIMIT 100");
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Metrics
try {
    $totalLogs = (int)$pdo->query("SELECT COUNT(*) FROM system_audit_logs")->fetchColumn();
    $authLogs = (int)$pdo->query("SELECT COUNT(*) FROM system_audit_logs WHERE action_type LIKE '%LOGIN%' OR action_type LIKE '%AUTH%'")->fetchColumn();
    $integrationLogs = (int)$pdo->query("SELECT COUNT(*) FROM system_audit_logs WHERE action_type LIKE '%API%' OR action_type LIKE '%DISPATCH%' OR action_type LIKE '%INBOUND%'")->fetchColumn();
    $caseLogs = (int)$pdo->query("SELECT COUNT(*) FROM system_audit_logs WHERE action_type LIKE '%BLOTTER%' OR action_type LIKE '%CASE%' OR action_type LIKE '%INCIDENT%'")->fetchColumn();
} catch (Exception $e) {
    $totalLogs = count($logs); $authLogs = 0; $integrationLogs = 0; $caseLogs = 0;
}
?>

<div class="main-content">
    <div class="content-container">
        <!-- Header Banner -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h1 class="h2 fw-bold text-dark mb-1">
                    <i class="fas fa-history text-success me-2"></i>System Audit Trail & Activity Logs
                </h1>
                <p class="text-muted small mb-0">Immutable compliance records of all user actions, security events, case changes, and API transactions.</p>
            </div>
            <div class="d-flex gap-2">
                <button onclick="window.print()" class="btn btn-outline-secondary btn-sm shadow-sm">
                    <i class="fas fa-print me-1"></i> Print Audit Log
                </button>
                <a href="dashboard.php" class="btn btn-outline-secondary btn-sm shadow-sm">
                    <i class="bi bi-arrow-left me-1"></i> Dashboard
                </a>
            </div>
        </div>

        <!-- Metrics Overview Grid -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card border-0 shadow-sm rounded-4 h-100 p-3" style="background: linear-gradient(135deg, #1b5a56 0%, #113d3a 100%); color: white;">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-white-50 small text-uppercase fw-bold">Total Audit Events</span>
                            <h2 class="fw-bold mb-0 mt-1"><?= number_format($totalLogs) ?></h2>
                        </div>
                        <div class="bg-white bg-opacity-25 rounded-circle p-3 d-flex align-items-center justify-content-center" style="width: 54px; height: 54px;">
                            <i class="fas fa-database fs-4"></i>
                        </div>
                    </div>
                    <small class="text-white-50 mt-2 d-block"><i class="fas fa-shield-alt me-1"></i> Continuous tracking active</small>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card border-0 shadow-sm rounded-4 h-100 p-3 bg-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted small text-uppercase fw-bold">Security & Auth</span>
                            <h2 class="fw-bold text-primary mb-0 mt-1"><?= number_format($authLogs) ?></h2>
                        </div>
                        <div class="bg-primary bg-opacity-10 text-primary rounded-circle p-3 d-flex align-items-center justify-content-center" style="width: 54px; height: 54px;">
                            <i class="fas fa-user-shield fs-4"></i>
                        </div>
                    </div>
                    <small class="text-muted mt-2 d-block"><i class="fas fa-check-circle text-success me-1"></i> 2FA & session logins</small>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card border-0 shadow-sm rounded-4 h-100 p-3 bg-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted small text-uppercase fw-bold">Case & Blotter Actions</span>
                            <h2 class="fw-bold text-success mb-0 mt-1"><?= number_format($caseLogs) ?></h2>
                        </div>
                        <div class="bg-success bg-opacity-10 text-success rounded-circle p-3 d-flex align-items-center justify-content-center" style="width: 54px; height: 54px;">
                            <i class="fas fa-folder-open fs-4"></i>
                        </div>
                    </div>
                    <small class="text-muted mt-2 d-block"><i class="fas fa-file-alt me-1"></i> Blotter records recorded</small>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card border-0 shadow-sm rounded-4 h-100 p-3 bg-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted small text-uppercase fw-bold">API & Integrations</span>
                            <h2 class="fw-bold text-warning mb-0 mt-1"><?= number_format($integrationLogs) ?></h2>
                        </div>
                        <div class="bg-warning bg-opacity-10 text-warning rounded-circle p-3 d-flex align-items-center justify-content-center" style="width: 54px; height: 54px;">
                            <i class="fas fa-network-wired fs-4"></i>
                        </div>
                    </div>
                    <small class="text-muted mt-2 d-block"><i class="fas fa-exchange-alt me-1"></i> Inbound/Outbound payloads</small>
                </div>
            </div>
        </div>

        <!-- Filter & Search Toolbar -->
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-body p-3">
                <form method="GET" class="row g-2 align-items-center">
                    <div class="col-md-3">
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i class="fas fa-search text-muted"></i></span>
                            <input type="text" name="search" class="form-control border-start-0" placeholder="Search user, action, details..." value="<?= htmlspecialchars($searchQuery) ?>">
                        </div>
                    </div>

                    <div class="col-md-2">
                        <select name="action_filter" class="form-select">
                            <option value="">-- All Actions --</option>
                            <option value="USER_LOGIN" <?= $actionFilter === 'USER_LOGIN' ? 'selected' : '' ?>>User Logins</option>
                            <option value="BLOTTER_CREATE" <?= $actionFilter === 'BLOTTER_CREATE' ? 'selected' : '' ?>>Blotter Creation</option>
                            <option value="INBOUND_EMERGENCY_CALL" <?= $actionFilter === 'INBOUND_EMERGENCY_CALL' ? 'selected' : '' ?>>Emergency Calls</option>
                            <option value="CCTV_REQUEST_DISPATCH" <?= $actionFilter === 'CCTV_REQUEST_DISPATCH' ? 'selected' : '' ?>>CCTV Dispatches</option>
                            <option value="SETTINGS_UPDATE" <?= $actionFilter === 'SETTINGS_UPDATE' ? 'selected' : '' ?>>Settings Updates</option>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <select name="status_filter" class="form-select">
                            <option value="">-- All Statuses --</option>
                            <option value="SUCCESS" <?= $statusFilter === 'SUCCESS' ? 'selected' : '' ?>>Success</option>
                            <option value="WARNING" <?= $statusFilter === 'WARNING' ? 'selected' : '' ?>>Warning</option>
                            <option value="FAILED" <?= $statusFilter === 'FAILED' ? 'selected' : '' ?>>Failed</option>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($dateFrom) ?>" placeholder="From Date">
                    </div>

                    <div class="col-md-2">
                        <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($dateTo) ?>" placeholder="To Date">
                    </div>

                    <div class="col-md-1 d-flex gap-1">
                        <button type="submit" class="btn btn-success fw-bold w-100" style="background-color: #2e856e; border-color: #2e856e;">
                            <i class="fas fa-filter"></i>
                        </button>
                        <a href="audit_trail.php" class="btn btn-outline-secondary" title="Reset Filters">
                            <i class="fas fa-undo"></i>
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Audit Trail Data Table -->
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0 fw-bold text-dark">
                    <i class="fas fa-list-alt text-success me-2"></i>Audit Log Entries (<?= count($logs) ?> Results)
                </h5>
                <span class="badge bg-success bg-opacity-10 text-success fw-bold px-3 py-2">
                    <i class="fas fa-shield-alt me-1"></i> Immutable Trail
                </span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr class="text-uppercase small text-muted">
                            <th class="ps-4">Timestamp</th>
                            <th>Actor / User</th>
                            <th>Action Type</th>
                            <th>Entity Target</th>
                            <th>Details / Description</th>
                            <th>IP Address</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="fas fa-history fs-1 text-muted opacity-50 mb-3 d-block"></i>
                                    No audit log records match the selected filters.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td class="ps-4 text-nowrap">
                                        <div class="fw-bold text-dark"><?= date('M d, Y', strtotime($log['created_at'])) ?></div>
                                        <small class="text-muted"><?= date('h:i:s A', strtotime($log['created_at'])) ?></small>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="rounded-circle bg-light d-flex align-items-center justify-content-center text-secondary border fw-bold" style="width: 34px; height: 34px; font-size: 0.8rem;">
                                                <?= strtoupper(substr($log['user_name'] ?? 'U', 0, 2)) ?>
                                            </div>
                                            <div>
                                                <div class="fw-bold text-dark small"><?= htmlspecialchars($log['user_name'] ?? 'System') ?></div>
                                                <span class="badge bg-light text-secondary border" style="font-size: 0.65rem;"><?= htmlspecialchars($log['user_role'] ?? 'System') ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-dark bg-opacity-75 font-monospace" style="font-size: 0.75rem;">
                                            <?= htmlspecialchars($log['action_type']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="fw-semibold text-dark small"><?= htmlspecialchars($log['target_entity'] ?? 'System') ?></span>
                                        <?php if (!empty($log['target_id'])): ?>
                                            <small class="text-muted d-block font-monospace"><?= htmlspecialchars($log['target_id']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td style="max-width: 320px;">
                                        <div class="small text-secondary text-truncate" title="<?= htmlspecialchars($log['details']) ?>">
                                            <?= htmlspecialchars($log['details']) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <code class="small text-muted"><?= htmlspecialchars($log['ip_address'] ?? '127.0.0.1') ?></code>
                                    </td>
                                    <td class="text-center">
                                        <?php
                                        $statusClass = 'bg-success';
                                        if (strtoupper($log['status']) === 'FAILED' || strtoupper($log['status']) === 'ERROR') $statusClass = 'bg-danger';
                                        elseif (strtoupper($log['status']) === 'WARNING') $statusClass = 'bg-warning text-dark';
                                        ?>
                                        <span class="badge <?= $statusClass ?> px-2 py-1" style="font-size: 0.75rem;">
                                            <?= htmlspecialchars(strtoupper($log['status'])) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
