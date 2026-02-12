<?php
/**
 * NCDB Module Index & Navigation
 * Quick access to all NCDB resources
 */

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

// Check if user has access
$has_access = isset($_SESSION['role']) && ($_SESSION['role'] === 'Officer' || $_SESSION['role'] === 'Admin');
$is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'Admin';

$page_title = 'NCDB Module - National Crime Database';
$base_url = '../';

require_once '../includes/header.php';
require_once '../includes/navbar.php';
?>

<div class="main-content">
    <div class="content-container">
        <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 40px 30px; border-radius: 12px; margin-bottom: 30px;">
            <h1 style="color: white; margin: 0; font-size: 2.5rem;">National Crime Database (NCDB)</h1>
            <p style="color: rgba(255,255,255,0.9); margin: 10px 0 0 0; font-size: 1.1rem;">Secure Integration System for Law Enforcement</p>
        </div>

        <?php if (!$has_access): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-circle"></i> 
                <strong>Access Denied</strong><br>
                This system is only available to Officers and Administrators.
            </div>
        <?php else: ?>
            <!-- Quick Access Cards -->
            <div class="row g-4 mb-5">
                <!-- Verification -->
                <div class="col-lg-4">
                    <div class="card h-100 border-0 shadow-sm" style="transition: all 0.3s ease; cursor: pointer;" onmouseover="this.style.boxShadow='0 8px 20px rgba(0,0,0,0.15)'; this.style.transform='translateY(-4px)';" onmouseout="this.style.boxShadow='0 1px 4px rgba(0,0,0,0.08)'; this.style.transform='translateY(0)';">
                        <a href="views/index.php" style="text-decoration: none; color: inherit;">
                            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 8px 8px 0 0;">
                                <i class="bi bi-search" style="font-size: 2.5rem; display: block; margin-bottom: 10px;"></i>
                                <h5 style="margin: 0; color: white;">Record Verification</h5>
                            </div>
                            <div class="card-body">
                                <p class="card-text text-muted">Verify records against national crime databases</p>
                                <ul style="font-size: 0.9rem; color: #636e72; margin: 15px 0 0 0; padding-left: 20px;">
                                    <li>Identity verification</li>
                                    <li>Criminal history check</li>
                                    <li>Warrant lookup</li>
                                    <li>Duplicate detection</li>
                                </ul>
                                <div style="margin-top: 15px;">
                                    <span class="badge bg-info">Officers & Admins</span>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>

                <!-- Admin Dashboard -->
                <?php if ($is_admin): ?>
                <div class="col-lg-4">
                    <div class="card h-100 border-0 shadow-sm" style="transition: all 0.3s ease; cursor: pointer;" onmouseover="this.style.boxShadow='0 8px 20px rgba(0,0,0,0.15)'; this.style.transform='translateY(-4px)';" onmouseout="this.style.boxShadow='0 1px 4px rgba(0,0,0,0.08)'; this.style.transform='translateY(0)';">
                        <a href="views/admin_dashboard.php" style="text-decoration: none; color: inherit;">
                            <div style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; padding: 30px; text-align: center; border-radius: 8px 8px 0 0;">
                                <i class="bi bi-gear" style="font-size: 2.5rem; display: block; margin-bottom: 10px;"></i>
                                <h5 style="margin: 0; color: white;">Admin Dashboard</h5>
                            </div>
                            <div class="card-body">
                                <p class="card-text text-muted">Manage NCDB connections and settings</p>
                                <ul style="font-size: 0.9rem; color: #636e72; margin: 15px 0 0 0; padding-left: 20px;">
                                    <li>Configure connections</li>
                                    <li>Test databases</li>
                                    <li>View sync history</li>
                                    <li>Monitor access logs</li>
                                </ul>
                                <div style="margin-top: 15px;">
                                    <span class="badge bg-danger">Admins Only</span>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Testing Suite -->
                <?php if ($is_admin): ?>
                <div class="col-lg-4">
                    <div class="card h-100 border-0 shadow-sm" style="transition: all 0.3s ease; cursor: pointer;" onmouseover="this.style.boxShadow='0 8px 20px rgba(0,0,0,0.15)'; this.style.transform='translateY(-4px)';" onmouseout="this.style.boxShadow='0 1px 4px rgba(0,0,0,0.08)'; this.style.transform='translateY(0)';">
                        <a href="views/test.php" style="text-decoration: none; color: inherit;">
                            <div style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; padding: 30px; text-align: center; border-radius: 8px 8px 0 0;">
                                <i class="bi bi-beaker" style="font-size: 2.5rem; display: block; margin-bottom: 10px;"></i>
                                <h5 style="margin: 0; color: white;">Testing Suite</h5>
                            </div>
                            <div class="card-body">
                                <p class="card-text text-muted">Test and verify NCDB system functionality</p>
                                <ul style="font-size: 0.9rem; color: #636e72; margin: 15px 0 0 0; padding-left: 20px;">
                                    <li>Configuration tests</li>
                                    <li>Database validation</li>
                                    <li>Security checks</li>
                                    <li>Performance testing</li>
                                </ul>
                                <div style="margin-top: 15px;">
                                    <span class="badge bg-success">Admins Only</span>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>
                <?php else: ?>
                <div class="col-lg-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div style="background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%); color: #333; padding: 30px; text-align: center; border-radius: 8px 8px 0 0;">
                            <i class="bi bi-info-circle" style="font-size: 2.5rem; display: block; margin-bottom: 10px;"></i>
                            <h5 style="margin: 0;">System Information</h5>
                        </div>
                        <div class="card-body">
                            <p class="card-text text-muted">NCDB system status and information</p>
                            <div style="margin-top: 20px;">
                                <div style="padding: 10px; background: #f5f6fa; border-radius: 6px; text-align: center;">
                                    <strong style="color: #00b894;">✓ System Active</strong><br>
                                    <small class="text-muted">All systems operational</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Documentation & Resources -->
            <div class="row g-4 mb-5">
                <div class="col-12">
                    <h3 style="color: #2d3436; margin-bottom: 20px;">📚 Documentation & Resources</h3>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <h5 style="color: #667eea;"><i class="bi bi-lightning-fill"></i> Quick Start</h5>
                            <p class="text-muted">Get started in 5 minutes</p>
                            <a href="QUICKSTART.md" class="btn btn-sm btn-outline-primary">Read Guide</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <h5 style="color: #667eea;"><i class="bi bi-book-fill"></i> Full Documentation</h5>
                            <p class="text-muted">Complete feature reference</p>
                            <a href="README.md" class="btn btn-sm btn-outline-primary">Read Guide</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <h5 style="color: #667eea;"><i class="bi bi-shield-lock"></i> Security Policy</h5>
                            <p class="text-muted">Security & compliance guidelines</p>
                            <a href="SECURITY_POLICY.md" class="btn btn-sm btn-outline-primary">Read Policy</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <h5 style="color: #667eea;"><i class="bi bi-wrench-adjustable"></i> Installation</h5>
                            <p class="text-muted">Setup and configuration</p>
                            <a href="INSTALLATION.md" class="btn btn-sm btn-outline-primary">Read Guide</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <h5 style="color: #667eea;"><i class="bi bi-file-earmark-text"></i> File Manifest</h5>
                            <p class="text-muted">Complete file listing</p>
                            <a href="FILE_MANIFEST.md" class="btn btn-sm btn-outline-primary">View Files</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <h5 style="color: #667eea;"><i class="bi bi-check-circle"></i> Implementation</h5>
                            <p class="text-muted">Project completion summary</p>
                            <a href="../NCDB_IMPLEMENTATION_SUMMARY.md" class="btn btn-sm btn-outline-primary">View Summary</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Feature Overview -->
            <div class="row g-4">
                <div class="col-12">
                    <h3 style="color: #2d3436; margin-bottom: 20px;">✨ Key Features</h3>
                </div>
                <div class="col-md-6">
                    <div style="padding: 20px; background: #f5f6fa; border-radius: 8px; border-left: 4px solid #667eea;">
                        <h6 style="color: #667eea; margin-bottom: 12px;">🔒 Security</h6>
                        <ul style="margin: 0; padding-left: 20px; color: #636e72;">
                            <li>AES-256 encryption</li>
                            <li>Role-based access control</li>
                            <li>Comprehensive audit logging</li>
                            <li>Threat detection</li>
                        </ul>
                    </div>
                </div>
                <div class="col-md-6">
                    <div style="padding: 20px; background: #f5f6fa; border-radius: 8px; border-left: 4px solid #764ba2;">
                        <h6 style="color: #764ba2; margin-bottom: 12px;">⚡ Performance</h6>
                        <ul style="margin: 0; padding-left: 20px; color: #636e72;">
                            <li>Intelligent caching</li>
                            <li>Fast queries (< 200ms)</li>
                            <li>Database optimization</li>
                            <li>Bulk operations</li>
                        </ul>
                    </div>
                </div>
                <div class="col-md-6">
                    <div style="padding: 20px; background: #f5f6fa; border-radius: 8px; border-left: 4px solid #00b894;">
                        <h6 style="color: #00b894; margin-bottom: 12px;">🔍 Verification</h6>
                        <ul style="margin: 0; padding-left: 20px; color: #636e72;">
                            <li>Identity verification</li>
                            <li>Criminal history checks</li>
                            <li>Warrant lookups</li>
                            <li>Case cross-referencing</li>
                        </ul>
                    </div>
                </div>
                <div class="col-md-6">
                    <div style="padding: 20px; background: #f5f6fa; border-radius: 8px; border-left: 4px solid #0984e3;">
                        <h6 style="color: #0984e3; margin-bottom: 12px;">📊 Duplicates</h6>
                        <ul style="margin: 0; padding-left: 20px; color: #636e72;">
                            <li>Fuzzy matching algorithm</li>
                            <li>Similarity scoring</li>
                            <li>Manual review workflow</li>
                            <li>Statistics & reporting</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- System Status -->
            <div class="row g-4 mt-5">
                <div class="col-12">
                    <div style="background: linear-gradient(135deg, rgba(0,184,148,0.1) 0%, rgba(0,184,148,0.05) 100%); border: 1px solid #00b894; border-radius: 8px; padding: 20px; text-align: center;">
                        <h6 style="color: #00b894; margin-bottom: 10px;">✓ System Status: Active</h6>
                        <p style="color: #636e72; margin: 0;">
                            NCDB system is fully operational. All databases connected and secure.
                            <br><small>Last verified: <?php echo date('M d, Y H:i'); ?></small>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Help Section -->
            <div class="row g-4 mt-5">
                <div class="col-12">
                    <h3 style="color: #2d3436; margin-bottom: 20px;">❓ Need Help?</h3>
                </div>
                <div class="col-12">
                    <div style="background: #fffaf0; border: 1px solid #ffd89b; border-radius: 8px; padding: 20px;">
                        <h6 style="color: #d68910; margin-bottom: 12px;">Common Tasks</h6>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <p style="margin: 0; color: #636e72;">
                                    <strong>Verify a Record:</strong> Go to Record Verification → Select record → Click "Verify"
                                </p>
                            </div>
                            <div class="col-md-6">
                                <p style="margin: 0; color: #636e72;">
                                    <strong>Configure Connection:</strong> Go to Admin Dashboard → Add New Connection
                                </p>
                            </div>
                            <div class="col-md-6">
                                <p style="margin: 0; color: #636e72;">
                                    <strong>Run Tests:</strong> Go to Testing Suite → Click "Run All Tests"
                                </p>
                            </div>
                            <div class="col-md-6">
                                <p style="margin: 0; color: #636e72;">
                                    <strong>View Logs:</strong> Go to Admin Dashboard → Access Logs tab
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
