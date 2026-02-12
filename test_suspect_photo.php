<?php
/**
 * Test Suspect Photo Feature
 * Verifies that the photo upload system is properly configured
 */

require_once __DIR__ . '/config/db_connect.php';

$test_results = [];

// Test 1: Check if photo_path column exists
try {
    $stmt = $pdo->prepare("SHOW COLUMNS FROM suspects LIKE 'photo_path'");
    $stmt->execute();
    $test_results['Column Exists'] = $stmt->rowCount() > 0 ? '✓ PASS' : '✗ FAIL';
} catch (PDOException $e) {
    $test_results['Column Exists'] = '✗ FAIL: ' . $e->getMessage();
}

// Test 2: Check upload directory
$upload_dir = __DIR__ . '/uploads/suspects/';
$test_results['Upload Directory'] = is_dir($upload_dir) ? '✓ PASS' : '✗ FAIL';

// Test 3: Check write permissions
if (is_dir($upload_dir)) {
    $test_file = $upload_dir . 'test_' . time() . '.txt';
    if (file_put_contents($test_file, 'test')) {
        unlink($test_file);
        $test_results['Write Permissions'] = '✓ PASS';
    } else {
        $test_results['Write Permissions'] = '✗ FAIL: Cannot write to directory';
    }
}

// Test 4: Check .htaccess file
$htaccess_file = $upload_dir . '.htaccess';
$test_results['.htaccess Protection'] = file_exists($htaccess_file) ? '✓ PASS' : '✗ FAIL';

// Test 5: Get sample suspect with photo
try {
    $stmt = $pdo->prepare("SELECT id, first_name, last_name, photo_path FROM suspects WHERE photo_path IS NOT NULL LIMIT 1");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        $test_results['Sample Photo Found'] = '✓ PASS: ' . htmlspecialchars($result['first_name'] . ' ' . $result['last_name']);
    } else {
        $test_results['Sample Photo Found'] = '⚠ INFO: No suspects with photos yet (normal)';
    }
} catch (PDOException $e) {
    $test_results['Sample Photo Found'] = '✗ FAIL: ' . $e->getMessage();
}

// Count total suspects
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM suspects");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $test_results['Total Suspects'] = $result['total'];
} catch (PDOException $e) {
    $test_results['Total Suspects'] = 'ERROR: ' . $e->getMessage();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suspect Photo Feature - System Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .test-card { background: white; border-left: 4px solid #667eea; }
        .pass { color: #28a745; font-weight: 600; }
        .fail { color: #dc3545; font-weight: 600; }
        .info { color: #17a2b8; font-weight: 500; }
    </style>
</head>
<body class="py-4">
    <div class="container">
        <div class="card test-card shadow-lg mb-4">
            <div class="card-body">
                <h1 class="h3 mb-3">
                    <i class="bi bi-camera-fill" style="color: #667eea;"></i>
                    Suspect Photo Feature - System Test
                </h1>
                <p class="text-muted">Verifying photo upload configuration and database setup</p>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-8">
                <div class="card test-card shadow-sm mb-3">
                    <div class="card-header bg-light">
                        <h5 class="mb-0"><i class="bi bi-clipboard-check"></i> Test Results</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <tbody>
                                    <?php foreach ($test_results as $test => $result): ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($test) ?></strong>
                                            </td>
                                            <td class="text-end">
                                                <?php if (strpos($result, '✓') !== false): ?>
                                                    <span class="pass"><?= htmlspecialchars($result) ?></span>
                                                <?php elseif (strpos($result, '✗') !== false): ?>
                                                    <span class="fail"><?= htmlspecialchars($result) ?></span>
                                                <?php elseif (strpos($result, '⚠') !== false || strpos($result, 'INFO') !== false): ?>
                                                    <span class="info"><?= htmlspecialchars($result) ?></span>
                                                <?php else: ?>
                                                    <span><?= htmlspecialchars($result) ?></span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="card test-card shadow-sm">
                    <div class="card-header bg-light">
                        <h5 class="mb-0"><i class="bi bi-info-circle"></i> Next Steps</h5>
                    </div>
                    <div class="card-body">
                        <?php 
                        $has_failures = false;
                        foreach ($test_results as $result) {
                            if (strpos($result, '✗') !== false) {
                                $has_failures = true;
                            }
                        }
                        ?>
                        
                        <?php if ($has_failures): ?>
                            <div class="alert alert-warning" role="alert">
                                <strong>⚠ Issues Detected</strong>
                                <p class="mb-0 mt-2">
                                    Some tests failed. Please run the setup script first:
                                </p>
                            </div>
                            <a href="setup_suspect_photo.php" class="btn btn-warning btn-sm">
                                <i class="bi bi-wrench"></i> Run Setup Script
                            </a>
                        <?php else: ?>
                            <div class="alert alert-success" role="alert">
                                <strong>✓ System Ready</strong>
                                <p class="mb-0 mt-2">
                                    All systems operational! You can now upload suspect photos.
                                </p>
                            </div>
                            <a href="admin/cases.php" class="btn btn-success btn-sm">
                                <i class="bi bi-person-fill"></i> Go to Case Management
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card test-card shadow-sm mb-3">
                    <div class="card-header bg-light">
                        <h5 class="mb-0"><i class="bi bi-file-earmark-image"></i> Photo Support</h5>
                    </div>
                    <div class="card-body">
                        <p class="small mb-3">
                            <strong>Supported Formats:</strong>
                        </p>
                        <ul class="small list-unstyled">
                            <li><i class="bi bi-check-circle text-success"></i> JPEG (.jpg, .jpeg)</li>
                            <li><i class="bi bi-check-circle text-success"></i> PNG (.png)</li>
                            <li><i class="bi bi-check-circle text-success"></i> GIF (.gif)</li>
                            <li><i class="bi bi-check-circle text-success"></i> WebP (.webp)</li>
                        </ul>
                        <hr>
                        <p class="small mb-2">
                            <strong>Max Size:</strong> 5MB recommended
                        </p>
                        <p class="small mb-0">
                            <strong>Storage Location:</strong> <code>/uploads/suspects/</code>
                        </p>
                    </div>
                </div>

                <div class="card test-card shadow-sm">
                    <div class="card-header bg-light">
                        <h5 class="mb-0"><i class="bi bi-question-circle"></i> Need Help?</h5>
                    </div>
                    <div class="card-body">
                        <p class="small mb-2">
                            <strong>Documentation:</strong>
                        </p>
                        <a href="SUSPECT_PHOTO_FEATURE.md" class="btn btn-outline-primary btn-sm w-100 mb-2">
                            <i class="bi bi-file-text"></i> Read Guide
                        </a>
                        <p class="small text-muted mb-0">
                            Check the feature documentation for installation and usage instructions.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-4 text-center text-white">
            <small>Law Enforcement Incident Report System • Suspect Photo Feature v1.0</small>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
