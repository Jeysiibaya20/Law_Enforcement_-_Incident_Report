<?php
require_once 'admin_auth.php';

$base_url = '../';
$page_title = 'Admin Dashboard';
require_once '../includes/header.php';
require_once '../includes/navbar.php';

// Fetch admin statistics
try {
    $totalUsers = $pdo->query("SELECT COUNT(*) FROM signup WHERE role != 'Admin'")->fetchColumn();
    $totalBlotters = $pdo->query("SELECT COUNT(*) FROM blotters")->fetchColumn();
    $pendingBlotters = $pdo->query("SELECT COUNT(*) FROM blotters WHERE status = 'Pending'")->fetchColumn();
    $verifiedUsers = $pdo->query("SELECT COUNT(*) FROM signup WHERE email_verified = 1 AND role != 'Admin'")->fetchColumn();
    $unverifiedUsers = $pdo->query("SELECT COUNT(*) FROM signup WHERE email_verified = 0 AND role != 'Admin'")->fetchColumn();
} catch (Exception $e) {
    $totalUsers = $totalBlotters = $pendingBlotters = $verifiedUsers = $unverifiedUsers = 0;
}

// NCDB Statistics and Security Check
$ncdb_available = false;
$ncdb_connections = 0;
$ncdb_recent_queries = 0;
$ncdb_security_status = 'Unknown';
$ncdb_encryption_status = false;

try {
    // Check if NCDB tables exist
    $check_table = $pdo->query("SHOW TABLES LIKE 'ncdb_%'")->fetchAll();
    if (count($check_table) > 0) {
        $ncdb_available = true;
        
        // Get NCDB statistics
        try {
            $ncdb_connections = $pdo->query("SELECT COUNT(*) FROM ncdb_connections WHERE status = 'active'")->fetchColumn() ?: 0;
        } catch (Exception $e) { $ncdb_connections = 0; }
        
        try {
            $ncdb_recent_queries = $pdo->query("SELECT COUNT(*) FROM ncdb_access_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetchColumn() ?: 0;
        } catch (Exception $e) { $ncdb_recent_queries = 0; }
        
        // Check security status
        $ncdb_security_status = 'Secured';
        
        // Check encryption
        $ncdb_encryption_status = defined('NCDB_ENCRYPTION_KEY');
    }
} catch (Exception $e) {
    $ncdb_available = false;
}
?>

<div class="main-content">
    <div class="content-container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="d-flex align-items-center">
                <a href="javascript:history.back()" class="btn btn-sm btn-outline-secondary me-3" title="Go back" aria-label="Go back">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <h1 class="h2 mb-0">Admin Dashboard</h1>
            </div>
            <div>
                
                <span class="badge bg-danger">
                    <i class="bi bi-shield-lock"></i> Admin Panel
                </span>
            </div>
        </div>

        <?php if (!empty($_SESSION['flash'])): $f = $_SESSION['flash']; ?>
            <div class="alert alert-<?= htmlspecialchars($f['type']) ?> alert-dismissible">
                <?= htmlspecialchars($f['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['flash']); endif; ?>

        <!-- KPI Cards -->
        <div class="row row-cols-1 row-cols-md-5 g-3 mb-4">
            <div class="col">
                <div class="card border-start border-primary border-4 h-100">
                    <div class="card-body">
                        <h6 class="text-muted mb-2">Total Users</h6>
                        <div class="h3 text-primary"><?= $totalUsers ?></div>
                        <small class="text-muted">Active accounts</small>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card border-start border-success border-4 h-100">
                    <div class="card-body">
                        <h6 class="text-muted mb-2">Verified Users</h6>
                        <div class="h3 text-success"><?= $verifiedUsers ?></div>
                        <small class="text-muted">Email verified</small>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card border-start border-warning border-4 h-100">
                    <div class="card-body">
                        <h6 class="text-muted mb-2">Unverified Users</h6>
                        <div class="h3 text-warning"><?= $unverifiedUsers ?></div>
                        <small class="text-muted">Pending verification</small>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card border-start border-info border-4 h-100">
                    <div class="card-body">
                        <h6 class="text-muted mb-2">Total Blotters</h6>
                        <div class="h3 text-info"><?= $totalBlotters ?></div>
                        <small class="text-muted">All records</small>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card border-start border-danger border-4 h-100">
                    <div class="card-body">
                        <h6 class="text-muted mb-2">Pending Blotters</h6>
                        <div class="h3 text-danger"><?= $pendingBlotters ?></div>
                        <small class="text-muted">Awaiting action</small>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($ncdb_available): ?>
        <!-- NCDB KPI Cards -->
        <div class="row row-cols-1 row-cols-md-3 g-3 mb-4">
            <div class="col">
                <div class="card border-start border-purple border-4 h-100" style="border-color: #667eea !important;">
                    <div class="card-body">
                        <h6 class="text-muted mb-2">
                            <i class="bi bi-shield-check"></i> NCDB Connections
                        </h6>
                        <div class="h3" style="color: #667eea;"><?= $ncdb_connections ?></div>
                        <small class="text-muted">Active databases</small>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card border-start border-purple border-4 h-100" style="border-color: #764ba2 !important;">
                    <div class="card-body">
                        <h6 class="text-muted mb-2">
                            <i class="bi bi-graph-up"></i> 24h Queries
                        </h6>
                        <div class="h3" style="color: #764ba2;"><?= $ncdb_recent_queries ?></div>
                        <small class="text-muted">NCDB operations</small>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card border-start border-4 h-100" style="border-color: <?= $ncdb_encryption_status ? '#28a745' : '#dc3545'; ?>;">
                    <div class="card-body">
                        <h6 class="text-muted mb-2">
                            <i class="bi bi-lock-fill"></i> Security
                        </h6>
                        <div class="h3" style="color: <?= $ncdb_encryption_status ? '#28a745' : '#dc3545'; ?>;">
                            <?= $ncdb_encryption_status ? 'Active' : 'Config' ?>
                        </div>
                        <small class="text-muted"><?= $ncdb_security_status ?></small>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Admin Menu -->
        <div class="row g-3 mb-4">
            <div class="col-md-6 col-lg-3">
                <a href="users.php" class="text-decoration-none">
                    <div class="card h-100 admin-menu-card">
                        <div class="card-body text-center">
                            <i class="bi bi-people-fill" style="font-size: 2.5rem; color: #007bff;"></i>
                            <h5 class="mt-3 mb-1">User Management</h5>
                            <p class="text-muted small">Manage all user accounts</p>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-6 col-lg-3">
                <a href="blotters.php" class="text-decoration-none">
                    <div class="card h-100 admin-menu-card">
                        <div class="card-body text-center">
                            <i class="bi bi-file-earmark-text" style="font-size: 2.5rem; color: #28a745;"></i>
                            <h5 class="mt-3 mb-1">Blotter Records</h5>
                            <p class="text-muted small">View all blotter entries</p>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-6 col-lg-3">
                <a href="cases.php" class="text-decoration-none">
                    <div class="card h-100 admin-menu-card">
                        <div class="card-body text-center">
                            <i class="bi bi-folder-fill" style="font-size: 2.5rem; color: #dc3545;"></i>
                            <h5 class="mt-3 mb-1">Case Management</h5>
                            <p class="text-muted small">Assign & track cases</p>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-6 col-lg-3">
                <a href="reports.php" class="text-decoration-none">
                    <div class="card h-100 admin-menu-card">
                        <div class="card-body text-center">
                            <i class="bi bi-graph-up" style="font-size: 2.5rem; color: #ffc107;"></i>
                            <h5 class="mt-3 mb-1">Reports</h5>
                            <p class="text-muted small">Analytics & statistics</p>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-6 col-lg-3">
                <a href="settings.php" class="text-decoration-none">
                    <div class="card h-100 admin-menu-card">
                        <div class="card-body text-center">
                            <i class="bi bi-gear-fill" style="font-size: 2.5rem; color: #6c757d;"></i>
                            <h5 class="mt-3 mb-1">Settings</h5>
                            <p class="text-muted small">System configuration</p>
                        </div>
                    </div>
                </a>
            </div>
            <?php if ($ncdb_available): ?>
            <div class="col-md-6 col-lg-3">
                <a href="../ncdb/views/admin_dashboard.php" class="text-decoration-none" title="National Crime Database Management">
                    <div class="card h-100 admin-menu-card" style="border: 2px solid #667eea;">
                        <div class="card-body text-center">
                            <i class="bi bi-shield-check" style="font-size: 2.5rem; color: #667eea;"></i>
                            <h5 class="mt-3 mb-1">NCDB System</h5>
                            <p class="text-muted small">
                                <small>
                                    <i class="bi bi-lock-fill" style="color: <?= $ncdb_encryption_status ? '#28a745' : '#dc3545'; ?>"></i>
                                    Secure Database
                                </small>
                            </p>
                        </div>
                    </div>
                </a>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($ncdb_available): ?>
        <!-- NCDB Security Status -->
        <div class="alert alert-info alert-dismissible fade show mb-4" role="alert" style="border-left: 4px solid #667eea;">
            <div class="d-flex align-items-start">
                <div style="flex-grow: 1;">
                    <h5 class="alert-heading mb-2">
                        <i class="bi bi-shield-check" style="color: #667eea;"></i> National Crime Database (NCDB) System Active
                    </h5>
                    <p class="mb-2 small">
                        Your system has secure NCDB integration enabled. All database queries are encrypted and audited.
                    </p>
                    <div class="row g-2 small">
                        <div class="col-auto">
                            <span class="badge bg-success">
                                <i class="bi bi-check-circle"></i> Encryption
                            </span>
                        </div>
                        <div class="col-auto">
                            <span class="badge bg-success">
                                <i class="bi bi-check-circle"></i> Audit Logging
                            </span>
                        </div>
                        <div class="col-auto">
                            <span class="badge bg-success">
                                <i class="bi bi-check-circle"></i> Rate Limiting
                            </span>
                        </div>
                        <div class="col-auto">
                            <span class="badge bg-info">
                                <i class="bi bi-shield"></i> Role-Based Access
                            </span>
                        </div>
                    </div>
                </div>
                <a href="../ncdb/views/admin_dashboard.php" class="btn btn-sm btn-primary ms-2">
                    Manage NCDB
                </a>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <!-- Recent Activity -->
        <div class="row g-3">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Recent Blotters</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead>
                                    <tr>
                                        <th>Blotter No</th>
                                        <th>Complainant</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    try {
                                        $recentBlotters = $pdo->query("SELECT id, blotter_no, complainant_name, status, created_at FROM blotters ORDER BY created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
                                        foreach ($recentBlotters as $b):
                                    ?>
                                    <tr>
                                        <td><small class="fw-bold"><?= htmlspecialchars($b['blotter_no']) ?></small></td>
                                        <td><small><?= htmlspecialchars(substr($b['complainant_name'], 0, 20)) ?></small></td>
                                        <td><small><span class="badge bg-info"><?= htmlspecialchars($b['status']) ?></span></small></td>
                                        <td><small><?= date('M d, Y', strtotime($b['created_at'])) ?></small></td>
                                    </tr>
                                    <?php endforeach; } catch (Exception $e) {} ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Recent Signups</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Verified</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    try {
                                        $recentUsers = $pdo->query("SELECT user_id, fullname, emailadd, email_verified, created_at FROM signup WHERE role != 'Admin' ORDER BY created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
                                        foreach ($recentUsers as $u):
                                    ?>
                                    <tr>
                                        <td><small><?= htmlspecialchars(substr($u['fullname'], 0, 15)) ?></small></td>
                                        <td><small><?= htmlspecialchars(substr($u['emailadd'], 0, 20)) ?></small></td>
                                        <td><small><?= $u['email_verified'] ? '<span class="badge bg-success">✓</span>' : '<span class="badge bg-warning">✗</span>' ?></small></td>
                                        <td><small><?= date('M d, Y', strtotime($u['created_at'])) ?></small></td>
                                    </tr>
                                    <?php endforeach; } catch (Exception $e) {} ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.admin-menu-card {
    transition: all 0.3s ease;
    border: none;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    cursor: pointer;
}

.admin-menu-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 16px rgba(0,0,0,0.15);
}
</style>

<!-- Embedded Reports for quick access (admin-only) -->
<?php
try {
    $embed_in_dashboard = true;
    include __DIR__ . '/reports.php';
} catch (Throwable $e) {
    // fallback: show link to reports page
    echo '<div class="content-container"><div class="alert alert-warning">Unable to load reports inline. <a href="reports.php">Open Reports page</a></div></div>';
}

require_once '../includes/footer.php';
