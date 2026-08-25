<?php
/**
 * Interactive REST API Tester & Documentation Sandbox
 * Allows immediate testing of all All-in-One API endpoints in browser
 */

$page_title = 'API Tester & Documentation';
$base_url = '../';
require_once '../includes/header.php';
?>

<div class="main-content">
    <div class="container-fluid py-4 px-md-4">
        <!-- Header -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 pb-3 border-bottom">
            <div>
                <h2 class="h3 font-weight-bold text-dark mb-1">
                    <i class="fas fa-plug text-primary me-2"></i>Alertara Unified API Tester & Docs
                </h2>
                <p class="text-secondary small mb-0">Interactive sandbox to test all All-in-One REST API endpoints directly in your browser.</p>
            </div>
            <div class="mt-3 mt-md-0">
                <a href="<?php echo $base_url; ?>api/api.php?action=health" target="_blank" class="btn btn-outline-success btn-sm me-2">
                    <i class="fas fa-heartbeat me-1"></i> Direct Health Endpoint
                </a>
                <a href="<?php echo $base_url; ?>admin/dashboard.php" class="btn btn-primary btn-sm">
                    <i class="fas fa-tachometer-alt me-1"></i> Admin Dashboard
                </a>
            </div>
        </div>

        <div class="row g-4">
            <!-- Left Column: Pre-configured API Action Cards -->
            <div class="col-lg-5">
                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h5 class="card-title mb-0 font-weight-bold text-dark">
                            <i class="fas fa-list text-primary me-2"></i>Quick Test Endpoints
                        </h5>
                    </div>
                    <div class="card-body p-3">
                        <div class="list-group list-group-flush">
                            <button class="list-group-item list-group-item-action py-3 px-3 border-bottom rounded-3 mb-2 api-test-btn bg-light border-primary" data-action="all" data-method="GET">
                                <div class="d-flex justify-content-between align-items-center">
                                    <strong><span class="badge bg-primary me-2">GET</span>⭐ All-In-One API (All Modules)</strong>
                                    <i class="fas fa-play text-primary"></i>
                                </div>
                                <small class="text-primary d-block mt-1 fw-bold">action=all — Returns complete data for ALL modules in one JSON response!</small>
                            </button>

                            <button class="list-group-item list-group-item-action py-3 px-3 border-bottom rounded-3 mb-2 api-test-btn" data-action="health" data-method="GET">
                                <div class="d-flex justify-content-between align-items-center">
                                    <strong><span class="badge bg-success me-2">GET</span>System Health & Ping</strong>
                                    <i class="fas fa-play text-primary"></i>
                                </div>
                                <small class="text-muted d-block mt-1">action=health — Check API online status and database connection.</small>
                            </button>

                            <button class="list-group-item list-group-item-action py-3 px-3 border-bottom rounded-3 mb-2 api-test-btn" data-action="modules" data-method="GET">
                                <div class="d-flex justify-content-between align-items-center">
                                    <strong><span class="badge bg-info me-2">GET</span>List System Modules</strong>
                                    <i class="fas fa-play text-primary"></i>
                                </div>
                                <small class="text-muted d-block mt-1">action=modules — Get list of supported module routes.</small>
                            </button>

                            <button class="list-group-item list-group-item-action py-3 px-3 border-bottom rounded-3 mb-2 api-test-btn" data-action="dashboard_stats" data-method="GET">
                                <div class="d-flex justify-content-between align-items-center">
                                    <strong><span class="badge bg-primary me-2">GET</span>Dashboard KPI Stats</strong>
                                    <i class="fas fa-play text-primary"></i>
                                </div>
                                <small class="text-muted d-block mt-1">action=dashboard_stats — Fetch users, blotters, pending stats.</small>
                            </button>

                            <button class="list-group-item list-group-item-action py-3 px-3 border-bottom rounded-3 mb-2 api-test-btn" data-action="blotters" data-method="GET">
                                <div class="d-flex justify-content-between align-items-center">
                                    <strong><span class="badge bg-warning text-dark me-2">GET</span>Blotter Records List</strong>
                                    <i class="fas fa-play text-primary"></i>
                                </div>
                                <small class="text-muted d-block mt-1">action=blotters — Retrieve list of recent blotter entries.</small>
                            </button>

                            <button class="list-group-item list-group-item-action py-3 px-3 border-bottom rounded-3 mb-2 api-test-btn" data-action="blotters&status=Pending" data-method="GET">
                                <div class="d-flex justify-content-between align-items-center">
                                    <strong><span class="badge bg-danger me-2">GET</span>Pending Blotters Only</strong>
                                    <i class="fas fa-play text-primary"></i>
                                </div>
                                <small class="text-muted d-block mt-1">action=blotters&status=Pending — Filter blotters by Pending status.</small>
                            </button>

                            <button class="list-group-item list-group-item-action py-3 px-3 border-bottom rounded-3 mb-2 api-test-btn" data-action="cases" data-method="GET">
                                <div class="d-flex justify-content-between align-items-center">
                                    <strong><span class="badge bg-indigo me-2" style="background:#4f46e5;color:#fff;">GET</span>Case Management List</strong>
                                    <i class="fas fa-play text-primary"></i>
                                </div>
                                <small class="text-muted d-block mt-1">action=cases — Get active incident cases.</small>
                            </button>

                            <button class="list-group-item list-group-item-action py-3 px-3 border-bottom rounded-3 mb-2 api-test-btn" data-action="users" data-method="GET">
                                <div class="d-flex justify-content-between align-items-center">
                                    <strong><span class="badge bg-dark me-2">GET</span>Users List</strong>
                                    <i class="fas fa-play text-primary"></i>
                                </div>
                                <small class="text-muted d-block mt-1">action=users — Retrieve system registered accounts.</small>
                            </button>

                            <button class="list-group-item list-group-item-action py-3 px-3 border-bottom rounded-3 mb-2 api-test-btn" data-action="emergency_calls" data-method="GET">
                                <div class="d-flex justify-content-between align-items-center">
                                    <strong><span class="badge bg-danger me-2">GET</span>📞 Emergency Calls (Aldrin's Group)</strong>
                                    <i class="fas fa-play text-primary"></i>
                                </div>
                                <small class="text-muted d-block mt-1">action=emergency_calls — Fetch received emergency calls log.</small>
                            </button>

                            <button class="list-group-item list-group-item-action py-3 px-3 border-bottom rounded-3 mb-2 api-test-btn" data-action="cctv_requests" data-method="GET">
                                <div class="d-flex justify-content-between align-items-center">
                                    <strong><span class="badge bg-success me-2">GET</span>📹 CCTV Requests (Marto's Group)</strong>
                                    <i class="fas fa-play text-primary"></i>
                                </div>
                                <small class="text-muted d-block mt-1">action=cctv_requests — Fetch CCTV footage requests records.</small>
                            </button>

                            <button class="list-group-item list-group-item-action py-3 px-3 border-bottom rounded-3 mb-2 api-test-btn" data-action="notifications" data-method="GET">
                                <div class="d-flex justify-content-between align-items-center">
                                    <strong><span class="badge bg-danger me-2">GET</span>Header Notifications</strong>
                                    <i class="fas fa-play text-primary"></i>
                                </div>
                                <small class="text-muted d-block mt-1">action=notifications — Fetch real-time unread alert count.</small>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Response Output Sandbox -->
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0 font-weight-bold text-dark">
                            <i class="fas fa-terminal text-primary me-2"></i>Live JSON Response Viewer
                        </h5>
                        <div id="responseStatusBadge">
                            <span class="badge bg-secondary">Ready</span>
                        </div>
                    </div>
                    <div class="card-body p-4">
                        <div class="mb-3">
                            <label class="form-label font-weight-semibold text-secondary small">Request URL:</label>
                            <div class="input-group">
                                <input type="text" id="requestUrlInput" class="form-control font-monospace text-dark bg-light" readonly value="<?php echo $base_url; ?>api/api.php?action=health">
                                <a id="openUrlBtn" href="<?php echo $base_url; ?>api/api.php?action=health" target="_blank" class="btn btn-outline-primary">
                                    <i class="fas fa-external-link-alt me-1"></i> Open Tab
                                </a>
                            </div>
                        </div>

                        <div class="mb-0">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label class="form-label font-weight-semibold text-secondary small mb-0">Response Body (JSON):</label>
                                <button class="btn btn-link btn-sm text-decoration-none p-0" id="copyJsonBtn">
                                    <i class="fas fa-copy me-1"></i> Copy Response
                                </button>
                            </div>
                            <pre id="jsonOutput" class="p-3 bg-dark text-success rounded-3 font-monospace mb-0" style="max-height: 480px; overflow-y: auto; font-size: 0.85rem;">Click any endpoint on the left to execute a test request.</pre>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const testBtns = document.querySelectorAll('.api-test-btn');
    const urlInput = document.getElementById('requestUrlInput');
    const openBtn = document.getElementById('openUrlBtn');
    const jsonOutput = document.getElementById('jsonOutput');
    const statusBadge = document.getElementById('responseStatusBadge');
    const copyBtn = document.getElementById('copyJsonBtn');

    const apiBase = window.location.origin + window.location.pathname.replace('tester.php', 'api.php');

    testBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const action = this.getAttribute('data-action');
            const targetUrl = apiBase + '?action=' + action;

            urlInput.value = targetUrl;
            openBtn.setAttribute('href', targetUrl);
            jsonOutput.textContent = 'Executing GET request to ' + targetUrl + '...';
            statusBadge.innerHTML = '<span class="badge bg-warning text-dark"><i class="fas fa-spinner fa-spin me-1"></i>Loading...</span>';

            fetch(targetUrl)
                .then(res => {
                    const status = res.status;
                    return res.json().then(data => ({ status, data }));
                })
                .then(({ status, data }) => {
                    statusBadge.innerHTML = `<span class="badge bg-success">HTTP ${status} OK</span>`;
                    jsonOutput.textContent = JSON.stringify(data, null, 2);
                })
                .catch(err => {
                    statusBadge.innerHTML = `<span class="badge bg-danger">Error</span>`;
                    jsonOutput.textContent = 'Error executing request:\n' + err.message;
                });
        });
    });

    if (copyBtn) {
        copyBtn.addEventListener('click', function() {
            navigator.clipboard.writeText(jsonOutput.textContent).then(() => {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: 'JSON response copied to clipboard',
                    showConfirmButton: false,
                    timer: 1500
                });
            });
        });
    }

    // Auto-trigger health test on load
    if (testBtns.length > 0) {
        testBtns[0].click();
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>
