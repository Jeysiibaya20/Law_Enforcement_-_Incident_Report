<?php
require_once 'admin_auth.php';
require_once '../modules/OperationalModuleIntegrator.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    $pdo = getDBConnection();
}

$base_url = '../';
$page_title = 'External Systems Integration';
require_once '../includes/header.php';
require_once '../includes/navbar.php';

$integrator = new OperationalModuleIntegrator($pdo);

$testResult = null;
$message = '';
$messageType = '';

// Handle manual test payload submission or CCTV dispatch
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'test_integration') {
        $sourceType = $_POST['source_type'] ?? 'group_4_tip';
        $location = trim($_POST['location'] ?? 'Barangay Central, District 1, Quezon City');
        $description = trim($_POST['description'] ?? 'Reported physical dispute and commotion involving multiple individuals.');
        $urgency = $_POST['urgency'] ?? 'High';
        $reporterName = trim($_POST['reporter_name'] ?? 'Anonymous Tipster');
        $evidence = trim($_POST['evidence'] ?? 'cctv_feed_sample_01.jpg');

        $rawInput = [
            'source' => $sourceType,
            'location' => $location,
            'description' => $description,
            'emergency_level' => $urgency,
            'complainant_name' => $reporterName,
            'attached_evidence' => $evidence,
            'timestamp' => date('Y-m-d H:i:s')
        ];

        $autoDispatch = !empty($_POST['auto_dispatch_cctv']);
        $testResult = $integrator->processInbound($rawInput, $autoDispatch);

        $message = "Test payload successfully processed and formatted across operational modules!";
        $messageType = "success";
    }

    if ($action === 'dispatch_cctv_direct') {
        $cctvPayload = json_decode($_POST['cctv_payload_json'] ?? '{}', true);
        if (!empty($cctvPayload)) {
            $dispatchStatus = $integrator->dispatchToPartnerCctvApi($cctvPayload);
            $message = "Dispatched CCTV payload to Partner Surveillance API (" . htmlspecialchars($dispatchStatus['endpoint']) . "). HTTP Status: " . ($dispatchStatus['http_code'] ?: 'CLI Simulated');
            $messageType = $dispatchStatus['success'] ? 'success' : 'info';
        }
    }
}

// Fetch recent integration logs
$logs = [];
try {
    $stmt = $pdo->query("SELECT * FROM external_integration_log ORDER BY id DESC LIMIT 20");
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $logs = [];
}
?>

<div class="main-content">
    <div class="content-container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h2 mb-1">External Systems Integration</h1>
                <p class="text-muted small mb-0">Bi-directional payload standardization and external Partner Surveillance API dashboard</p>
            </div>
            <div>
                <a href="dashboard.php" class="btn btn-secondary btn-sm"><i class="bi bi-arrow-left"></i> Dashboard</a>
            </div>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?= $messageType ?> alert-dismissible fade show mb-4" role="alert">
                <i class="fas fa-check-circle me-2"></i> <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Active Integration Status Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card border-start border-primary border-4 h-100">
                    <div class="card-body">
                        <small class="text-muted text-uppercase fw-bold">Group 7 Inspection</small>
                        <div class="h5 mt-2 text-primary"><i class="fas fa-calendar-check me-2"></i>Active Endpoint</div>
                        <small class="text-muted">Standardized case scheduling</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-start border-info border-4 h-100">
                    <div class="card-body">
                        <small class="text-muted text-uppercase fw-bold">Group 5 Crime Mapping</small>
                        <div class="h5 mt-2 text-info"><i class="fas fa-map-marked-alt me-2"></i>GIS Connected</div>
                        <small class="text-muted">Realtime spatial geocoding</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-start border-warning border-4 h-100">
                    <div class="card-body">
                        <small class="text-muted text-uppercase fw-bold">Group 3 EMS / Police</small>
                        <div class="h5 mt-2 text-warning"><i class="fas fa-ambulance me-2"></i>Resource Dispatch</div>
                        <small class="text-muted">District officer tracking</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-start border-success border-4 h-100">
                    <div class="card-body">
                        <small class="text-muted text-uppercase fw-bold">Partner CCTV API</small>
                        <div class="h5 mt-2 text-success"><i class="fas fa-video me-2"></i>surveillance.alertaraqc.com</div>
                        <small class="text-muted">Partner API endpoint online</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Test Payload Processor Form -->
            <div class="col-lg-5 mb-4">
                <div class="card h-100">
                    <div class="card-header bg-card fw-bold">
                        <i class="fas fa-sliders-h me-2 text-primary"></i>Simulate Inbound Data Integration
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="test_integration">

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Inbound Source Module</label>
                                <select name="source_type" class="form-select">
                                    <option value="group_4_tip">Group 4: Anonymous Tip Line System</option>
                                    <option value="group_3_call">Group 3: Emergency Call Receiving & Logging</option>
                                    <option value="group_4_complaint">Group 4: Community Complaint Logging</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Location / Address</label>
                                <input type="text" name="location" class="form-control" value="Barangay Central, District 1, Quezon City" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Incident Description</label>
                                <textarea name="description" class="form-control" rows="3" required>Reported physical dispute and disturbance involving armed individuals.</textarea>
                            </div>

                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label class="form-label fw-semibold">Emergency Level</label>
                                    <select name="urgency" class="form-select">
                                        <option value="High" selected>High</option>
                                        <option value="Medium">Medium</option>
                                        <option value="Low">Low</option>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label fw-semibold">Reporter Name</label>
                                    <input type="text" name="reporter_name" class="form-control" value="Anonymous Tipster">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Attached Evidence File</label>
                                <input type="text" name="evidence" class="form-control" value="cctv_feed_sample_01.jpg">
                            </div>

                            <div class="form-check mb-4">
                                <input class="form-check-input" type="checkbox" name="auto_dispatch_cctv" id="autoDispatchCheck" value="1">
                                <label class="form-check-label" for="autoDispatchCheck">
                                    Auto-dispatch request to CCTV Partner API (surveillance.alertaraqc.com)
                                </label>
                            </div>

                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-cogs me-2"></i>Process & Standardize Payload
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Standardized Output Inspector -->
            <div class="col-lg-7 mb-4">
                <div class="card h-100">
                    <div class="card-header bg-card fw-bold d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-project-diagram me-2 text-success"></i>Standardized Downstream Module Payloads</span>
                        <?php if ($testResult): ?>
                            <span class="badge bg-success rounded-pill">Processed</span>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if ($testResult): ?>
                            <!-- Executive Incident Summary -->
                            <div class="p-3 mb-3 bg-light rounded border">
                                <h6 class="fw-bold text-dark mb-1"><i class="fas fa-file-alt me-2 text-primary"></i>Executive Incident Summary</h6>
                                <p class="small text-secondary mb-2"><?= htmlspecialchars($testResult['executive_incident_summary']['summary']) ?></p>
                                <div class="d-flex gap-2 align-items-center">
                                    <span class="badge bg-danger">Risk Level: <?= htmlspecialchars($testResult['executive_incident_summary']['risk_level']) ?></span>
                                    <span class="badge bg-dark">Score: <?= (int)$testResult['executive_incident_summary']['urgency_score'] ?>/100</span>
                                </div>
                            </div>

                            <!-- Tabs for Group Payloads -->
                            <ul class="nav nav-tabs nav-fill mb-3" id="payloadTabs" role="tablist">
                                <li class="nav-item">
                                    <button class="nav-link active btn-sm" id="g7-tab" data-bs-toggle="tab" data-bs-target="#g7" type="button">Group 7 Inspection</button>
                                </li>
                                <li class="nav-item">
                                    <button class="nav-link btn-sm" id="g5-tab" data-bs-toggle="tab" data-bs-target="#g5" type="button">Group 5 Crime Map</button>
                                </li>
                                <li class="nav-item">
                                    <button class="nav-link btn-sm" id="g3-tab" data-bs-toggle="tab" data-bs-target="#g3" type="button">Group 3 Resource</button>
                                </li>
                                <li class="nav-item">
                                    <button class="nav-link btn-sm" id="cctv-tab" data-bs-toggle="tab" data-bs-target="#cctv" type="button">Partner CCTV API</button>
                                </li>
                            </ul>

                            <div class="tab-content border p-3 rounded bg-dark text-light" id="payloadTabContent">
                                <div class="tab-pane fade show active" id="g7">
                                    <pre class="mb-0 text-success font-monospace" style="font-size: 0.825rem;"><?= htmlspecialchars(json_encode($testResult['module_specific_payloads']['group_7_inspection_scheduling'], JSON_PRETTY_PRINT)) ?></pre>
                                </div>
                                <div class="tab-pane fade" id="g5">
                                    <pre class="mb-0 text-info font-monospace" style="font-size: 0.825rem;"><?= htmlspecialchars(json_encode($testResult['module_specific_payloads']['group_5_crime_mapping'], JSON_PRETTY_PRINT)) ?></pre>
                                </div>
                                <div class="tab-pane fade" id="g3">
                                    <pre class="mb-0 text-warning font-monospace" style="font-size: 0.825rem;"><?= htmlspecialchars(json_encode($testResult['module_specific_payloads']['group_3_resource_allocation'], JSON_PRETTY_PRINT)) ?></pre>
                                </div>
                                <div class="tab-pane fade" id="cctv">
                                    <pre class="mb-0 text-light font-monospace" style="font-size: 0.825rem;"><?= htmlspecialchars(json_encode($testResult['module_specific_payloads']['cctv_partner_surveillance_api'], JSON_PRETTY_PRINT)) ?></pre>
                                    <form method="POST" class="mt-3">
                                        <input type="hidden" name="action" value="dispatch_cctv_direct">
                                        <input type="hidden" name="cctv_payload_json" value="<?= htmlspecialchars(json_encode($testResult['module_specific_payloads']['cctv_partner_surveillance_api'])) ?>">
                                        <button type="submit" class="btn btn-sm btn-success">
                                            <i class="fas fa-paper-plane me-1"></i> Dispatch to Partner CCTV API
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5 text-muted">
                                <i class="fas fa-network-wired fa-3x mb-3 opacity-50"></i>
                                <h5>No active payload simulation</h5>
                                <p class="small">Use the form on the left to simulate inbound data from Group 3 or Group 4 modules.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- External Integration Log Table -->
        <div class="card">
            <div class="card-header bg-card fw-bold">
                <i class="fas fa-history me-2 text-primary"></i>Integration Log History (`external_integration_log`)
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size: 0.85rem;">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Direction</th>
                                <th>Target / Endpoint</th>
                                <th>Status</th>
                                <th>Timestamp</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($logs)): ?>
                                <?php foreach ($logs as $l): ?>
                                    <tr>
                                        <td>#<?= (int)$l['id'] ?></td>
                                        <td>
                                            <span class="badge <?= $l['direction'] === 'incoming' ? 'bg-primary' : 'bg-success' ?>">
                                                <?= htmlspecialchars(strtoupper($l['direction'])) ?>
                                            </span>
                                        </td>
                                        <td class="text-truncate" style="max-width: 250px;"><?= htmlspecialchars($l['target_url'] ?: 'System Ingestion') ?></td>
                                        <td>
                                            <span class="badge bg-secondary"><?= htmlspecialchars($l['status']) ?></span>
                                        </td>
                                        <td><?= date('M d, Y g:i a', strtotime($l['created_at'])) ?></td>
                                        <td>
                                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="alert(<?= htmlspecialchars(json_encode($l['payload'])) ?>)">
                                                <i class="fas fa-code"></i> Payload
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">No integration logs recorded yet.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
