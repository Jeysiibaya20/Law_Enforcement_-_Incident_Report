<?php
require_once 'admin_auth.php';
require_once '../modules/OperationalModuleIntegrator.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    $pdo = getDBConnection();
}

$base_url = '../';
$page_title = 'External Systems Integration';
require_once '../includes/header.php';

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

    if ($action === 'simulate_incoming_cctv') {
        $cctvSample = [
            'request_id' => trim($_POST['request_id'] ?? ('REQ-CCTV-' . date('Ymd') . '-' . rand(100, 999))),
            'incident_id' => trim($_POST['incident_id'] ?? ('INC-' . date('Ymd') . '-001')),
            'cctv_url' => trim($_POST['cctv_url'] ?? 'https://surveillance.alertaraqc.com/media/feeds/sample_footage_001.mp4'),
            'camera_id' => trim($_POST['camera_id'] ?? 'CAM-QC-D1-042'),
            'location' => trim($_POST['location'] ?? 'Barangay Central, District 1, Quezon City'),
            'notes' => trim($_POST['notes'] ?? 'Footage captured during time window showing street commotion.')
        ];
        try {
            $res = $integrator->processIncomingCctvFootage($cctvSample);
            $message = "Successfully processed and logged incoming CCTV footage! Record ID: #" . $res['record_id'];
            $messageType = "success";
        } catch (Exception $e) {
            $message = "Error receiving CCTV footage: " . $e->getMessage();
            $messageType = "danger";
        }
    }

    if ($action === 'simulate_incoming_tip') {
        $tipSample = [
            'tip_id' => trim($_POST['tip_id'] ?? ('TIP-' . date('Ymd') . '-' . rand(1000, 9999))),
            'incident_id' => trim($_POST['incident_id'] ?? ('INC-' . date('Ymd') . '-002')),
            'incident_type' => trim($_POST['incident_type'] ?? 'Physical Violence / Assault'),
            'title' => trim($_POST['title'] ?? 'Resolved Public Disturbance Tip'),
            'description' => trim($_POST['description'] ?? 'Tipster reported noise and argument. Surveillance team verified via live feed.'),
            'location' => trim($_POST['location'] ?? 'Barangay East Kamias, District 3, Quezon City'),
            'resolved_by' => trim($_POST['resolved_by'] ?? 'Surveillance Operator #12'),
            'resolution_notes' => trim($_POST['resolution_notes'] ?? 'Camera feed monitored; patrol team arrived and resolved disturbance.')
        ];
        try {
            $res = $integrator->processIncomingResolvedTip($tipSample);
            $message = "Successfully received resolved tip and classified into Incident Logging module! Record ID: #" . $res['record_id'];
            $messageType = "success";
        } catch (Exception $e) {
            $message = "Error receiving resolved tip: " . $e->getMessage();
            $messageType = "danger";
        }
    }

    if ($action === 'save_integration_settings') {
        setIntegrationSetting('cctv_request_api_url', $_POST['cctv_request_api_url'] ?? '');
        setIntegrationSetting('group7_inspection_api_url', $_POST['group7_inspection_api_url'] ?? '');
        setIntegrationSetting('group5_crime_map_api_url', $_POST['group5_crime_map_api_url'] ?? '');
        setIntegrationSetting('group3_resource_api_url', $_POST['group3_resource_api_url'] ?? '');
        setIntegrationSetting('campaign_api_url', $_POST['campaign_api_url'] ?? '');
        setIntegrationSetting('external_api_secret', $_POST['external_api_secret'] ?? '');
        setIntegrationSetting('auto_dispatch_cctv', !empty($_POST['auto_dispatch_cctv']) ? '1' : '0');
        $message = "Integration API settings updated successfully! Target endpoints are saved and ready.";
        $messageType = "success";
    }

    if ($action === 'fetch_campaigns') {
        $cRes = $integrator->fetchPublicCampaigns();
        if ($cRes['success']) {
            $message = "Successfully synced " . $cRes['campaign_count'] . " public safety campaign(s) from campaign.alertaraqc.com (HTTP 200 OK)!";
            $messageType = "success";
        } else {
            $message = "Campaign API response code: " . ($cRes['http_code'] ?: 'Offline') . ". Error: " . ($cRes['curl_error'] ?: 'Check endpoint URL');
            $messageType = "warning";
        }
    }

    if ($action === 'ping_endpoint') {
        $targetUrl = trim($_POST['target_url'] ?? '');
        $moduleName = trim($_POST['module_name'] ?? 'Target Endpoint');
        if (!empty($targetUrl)) {
            $pingRes = dispatchPayloadToEndpoint($targetUrl, ['action' => 'ping', 'system' => 'AlertaraQC', 'timestamp' => date('c')]);
            $message = "Ping test for " . htmlspecialchars($moduleName) . " (" . htmlspecialchars($targetUrl) . "). HTTP Status: " . ($pingRes['http_code'] ?: 'Offline/Simulated');
            $messageType = $pingRes['success'] ? 'success' : 'warning';
        }
    }
}

$integrationSettings = getAllIntegrationSettings();

// Fetch recent integration logs & received records
$logs = [];
$receivedFootage = [];
$receivedTips = [];
$receivedCampaigns = [];

try {
    $stmt = $pdo->query("SELECT * FROM external_integration_log ORDER BY id DESC LIMIT 20");
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $logs = []; }

try {
    $stmtF = $pdo->query("SELECT * FROM cctv_footage_received ORDER BY id DESC LIMIT 15");
    $receivedFootage = $stmtF->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $receivedFootage = []; }

try {
    $stmtT = $pdo->query("SELECT * FROM received_resolved_tips ORDER BY id DESC LIMIT 15");
    $receivedTips = $stmtT->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $receivedTips = []; }

try {
    $stmtC = $pdo->query("SELECT * FROM received_campaigns ORDER BY id DESC LIMIT 15");
    $receivedCampaigns = $stmtC->fetchAll(PDO::FETCH_ASSOC);

    if (empty($receivedCampaigns)) {
        // Auto sync live campaigns from campaign.alertaraqc.com on page load
        $cRes = $integrator->fetchPublicCampaigns();
        if (!empty($cRes['success'])) {
            $stmtC = $pdo->query("SELECT * FROM received_campaigns ORDER BY id DESC LIMIT 15");
            $receivedCampaigns = $stmtC->fetchAll(PDO::FETCH_ASSOC);
        }
    }
} catch (Exception $e) { $receivedCampaigns = []; }
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

        <!-- Partner Integration API Specifications Banner -->
        <div class="card mb-4 bg-light border-primary shadow-sm">
            <div class="card-header bg-primary text-white fw-bold d-flex align-items-center">
                <i class="fas fa-network-wired me-2"></i> Partner Integration Specifications & Inbound Webhook URLs
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="p-3 bg-white border rounded h-100">
                            <span class="badge bg-danger mb-2">OUTGOING DISPATCH</span>
                            <h6 class="fw-bold text-dark mb-1">CCTV Request Target</h6>
                            <code class="small text-break"><?= htmlspecialchars($integrationSettings['cctv_request_api_url'] ?? '') ?></code>
                            <p class="small text-muted mt-2 mb-0">Dispatches automated CCTV footage requests to surveillance partner team.</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 bg-white border rounded h-100">
                            <span class="badge bg-success mb-2">INCOMING WEBHOOK</span>
                            <h6 class="fw-bold text-dark mb-1">Receive CCTV Footage</h6>
                            <code class="small text-break">/api/cctv_footage_receive.php</code>
                            <p class="small text-muted mt-2 mb-0">Partner POSTs fulfilled CCTV footage & video links directly to this system.</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 bg-white border rounded h-100">
                            <span class="badge bg-success mb-2">INCOMING WEBHOOK</span>
                            <h6 class="fw-bold text-dark mb-1">Receive Resolved Tips</h6>
                            <code class="small text-break">/api/receive_resolved_tips.php</code>
                            <p class="small text-muted mt-2 mb-0">Partner POSTs resolved tips to log & classify into Incident module.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Integration Ready Endpoint Manager Form -->
        <div class="card mb-4 border-dark shadow-sm">
            <div class="card-header bg-dark text-white fw-bold d-flex justify-content-between align-items-center">
                <span><i class="fas fa-sliders-h me-2 text-warning"></i>Integration Ready Endpoint Manager (Configure API Target URLs)</span>
                <span class="badge bg-warning text-dark"><i class="fas fa-plug me-1"></i>Integration Ready</span>
            </div>
            <div class="card-body">
                <p class="small text-muted mb-4">Input or update the destination API URLs for external partner systems. All modules are pre-configured to route payloads to these target endpoints as soon as external APIs go live.</p>

                <form method="POST">
                    <input type="hidden" name="action" value="save_integration_settings">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-dark"><i class="fas fa-video text-success me-2"></i>1. Partner CCTV Request API URL</label>
                            <div class="input-group">
                                <input type="url" name="cctv_request_api_url" class="form-control" value="<?= htmlspecialchars($integrationSettings['cctv_request_api_url'] ?? '') ?>" placeholder="https://surveillance.alertaraqc.com/api/cctv_requests_receive.php" required>
                            </div>
                            <small class="text-muted">Target endpoint for CCTV footage & still photo requests.</small>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold text-dark"><i class="fas fa-calendar-check text-primary me-2"></i>2. Group 7 Inspection Scheduling API URL</label>
                            <div class="input-group">
                                <input type="url" name="group7_inspection_api_url" class="form-control" value="<?= htmlspecialchars($integrationSettings['group7_inspection_api_url'] ?? '') ?>" placeholder="https://inspection.alertaraqc.com/api/schedule_inspection.php">
                            </div>
                            <small class="text-muted">Target endpoint for inspection scheduling and case referral.</small>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold text-dark"><i class="fas fa-map-marked-alt text-info me-2"></i>3. Group 5 Crime Mapping GIS API URL</label>
                            <div class="input-group">
                                <input type="url" name="group5_crime_map_api_url" class="form-control" value="<?= htmlspecialchars($integrationSettings['group5_crime_map_api_url'] ?? '') ?>" placeholder="https://crimemap.alertaraqc.com/api/update_heatmap.php">
                            </div>
                            <small class="text-muted">Target endpoint for real-time GIS spatial heatmap updates.</small>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold text-dark"><i class="fas fa-ambulance text-warning me-2"></i>4. Group 3 EMS & Resource Allocation API URL</label>
                            <div class="input-group">
                                <input type="url" name="group3_resource_api_url" class="form-control" value="<?= htmlspecialchars($integrationSettings['group3_resource_api_url'] ?? '') ?>" placeholder="https://dispatch.alertaraqc.com/api/assign_officer.php">
                            </div>
                            <small class="text-muted">Target endpoint for officer dispatch and emergency unit tracking.</small>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold text-dark"><i class="fas fa-bullhorn text-danger me-2"></i>5. Public Safety Campaign API URL (Group 1)</label>
                            <div class="input-group">
                                <input type="url" name="campaign_api_url" class="form-control" value="<?= htmlspecialchars($integrationSettings['campaign_api_url'] ?? '') ?>" placeholder="https://campaign.alertaraqc.com/api/v1/campaigns/public" required>
                            </div>
                            <small class="text-muted">Public safety campaigns & awareness advisories endpoint.</small>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold text-dark"><i class="fas fa-key text-danger me-2"></i>Shared Secret Token / API Key (Optional)</label>
                            <input type="text" name="external_api_secret" class="form-control" value="<?= htmlspecialchars($integrationSettings['external_api_secret'] ?? '') ?>" placeholder="Enter shared secret token for header verification">
                            <small class="text-muted">Transmitted in <code>X-External-Secret</code> header for secure API authentication.</small>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold text-dark"><i class="fas fa-cogs me-2"></i>Automation Rules</label>
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" name="auto_dispatch_cctv" id="autoDispatchCheckGlobal" value="1" <?= !empty($integrationSettings['auto_dispatch_cctv']) ? 'checked' : '' ?>>
                                <label class="form-check-label fw-semibold" for="autoDispatchCheckGlobal">
                                    Automatically dispatch CCTV query whenever a high-urgency incident is logged
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end mt-4">
                        <button type="submit" class="btn btn-primary px-4 fw-bold">
                            <i class="fas fa-save me-2"></i>Save Integration Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Live Public Safety Campaigns Card (campaign.alertaraqc.com) -->
        <div class="card mb-4 border-warning shadow-sm" id="campaignsSection">
            <div class="card-header bg-warning text-dark fw-bold d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <i class="fas fa-bullhorn me-2"></i>Live Public Safety Campaigns (`campaign.alertaraqc.com`)
                    <span class="badge bg-dark text-warning ms-2"><?= count($receivedCampaigns) ?> Synced Campaign(s)</span>
                </div>
                <div class="d-flex gap-2 align-items-center">
                    <input type="text" id="campaignSearchInput" class="form-control form-control-sm" placeholder="Filter campaigns..." style="width: 220px;">
                    <form method="POST" class="d-inline mb-0">
                        <input type="hidden" name="action" value="fetch_campaigns">
                        <button type="submit" class="btn btn-sm btn-dark text-warning border-dark fw-bold px-3">
                            <i class="fas fa-sync-alt me-1"></i>Fetch Live Campaigns
                        </button>
                    </form>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height: 450px;">
                    <table class="table table-hover align-middle mb-0" id="campaignsTable" style="font-size: 0.85rem;">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th>ID</th>
                                <th>CAMPAIGN TITLE</th>
                                <th>CATEGORY</th>
                                <th>SCOPE</th>
                                <th>STATUS</th>
                                <th>FETCHED</th>
                                <th class="text-center">ACTION</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($receivedCampaigns)): ?>
                                <?php foreach ($receivedCampaigns as $c): ?>
                                    <tr class="campaign-row">
                                        <td class="fw-bold">#<?= htmlspecialchars($c['campaign_id'] ?: $c['id']) ?></td>
                                        <td>
                                            <strong><?= htmlspecialchars($c['title']) ?></strong>
                                            <div class="small text-muted text-truncate" style="max-width: 380px;"><?= htmlspecialchars($c['description']) ?></div>
                                        </td>
                                        <td><span class="badge bg-secondary"><?= htmlspecialchars(ucfirst($c['category'])) ?></span></td>
                                        <td><?= htmlspecialchars($c['geographical_scope'] ?: 'Barangay') ?></td>
                                        <td><span class="badge bg-success"><?= htmlspecialchars($c['status']) ?></span></td>
                                        <td><?= date('M d, Y g:i a', strtotime($c['fetched_at'])) ?></td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-sm btn-outline-primary fw-bold py-0 px-2" onclick="showCampaignDetails(<?= htmlspecialchars(json_encode($c), ENT_QUOTES, 'UTF-8') ?>)">
                                                <i class="fas fa-eye me-1"></i>View Details
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">
                                        No campaigns synced yet. Click <strong>"Fetch Live Campaigns"</strong> above to sync from <code>https://campaign.alertaraqc.com/api/v1/campaigns/public</code>.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

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
                        <div class="h5 mt-2 text-success"><i class="fas fa-video me-2"></i>cctv_requests_receive.php</div>
                        <small class="text-muted">surveillance.alertaraqc.com</small>
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

        <!-- Received Partner Data Records (Inbound CCTV Footage & Resolved Tips) -->
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <div class="card h-100 shadow-sm border-success">
                    <div class="card-header bg-success text-white fw-bold d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-video me-2"></i>Received CCTV Footage (`cctv_footage_received`)</span>
                        <span class="badge bg-white text-success"><?= count($receivedFootage) ?> record(s)</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive" style="max-height: 300px;">
                            <table class="table table-sm table-hover align-middle mb-0" style="font-size: 0.82rem;">
                                <thead class="table-light">
                                    <tr>
                                        <th>REQ ID</th>
                                        <th>LOCATION / CAM</th>
                                        <th>FOOTAGE LINK</th>
                                        <th>RECEIVED</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($receivedFootage)): ?>
                                        <?php foreach ($receivedFootage as $rf): ?>
                                            <tr>
                                                <td class="fw-bold"><?= htmlspecialchars($rf['request_id'] ?: 'N/A') ?></td>
                                                <td><?= htmlspecialchars(($rf['location'] ?: '') . ' (' . ($rf['camera_id'] ?: 'CAM') . ')') ?></td>
                                                <td>
                                                    <a href="<?= htmlspecialchars($rf['cctv_url']) ?>" target="_blank" class="btn btn-xs btn-outline-success py-0 px-2">
                                                        <i class="fas fa-play me-1"></i>View Video
                                                    </a>
                                                </td>
                                                <td><?= date('M d H:i', strtotime($rf['received_at'])) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="4" class="text-center text-muted py-3">No CCTV footage received yet.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card h-100 shadow-sm border-info">
                    <div class="card-header bg-info text-dark fw-bold d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-lightbulb me-2"></i>Received Resolved Tips (`received_resolved_tips`)</span>
                        <span class="badge bg-dark text-white"><?= count($receivedTips) ?> record(s)</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive" style="max-height: 300px;">
                            <table class="table table-sm table-hover align-middle mb-0" style="font-size: 0.82rem;">
                                <thead class="table-light">
                                    <tr>
                                        <th>TIP ID</th>
                                        <th>TITLE / TYPE</th>
                                        <th>RESOLVED BY</th>
                                        <th>LOGGED</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($receivedTips)): ?>
                                        <?php foreach ($receivedTips as $rt): ?>
                                            <tr>
                                                <td class="fw-bold"><?= htmlspecialchars($rt['tip_id'] ?: 'N/A') ?></td>
                                                <td><?= htmlspecialchars($rt['title'] ?: $rt['incident_type']) ?></td>
                                                <td><?= htmlspecialchars($rt['resolved_by'] ?: 'Partner Operator') ?></td>
                                                <td><?= date('M d H:i', strtotime($rt['created_at'])) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="4" class="text-center text-muted py-3">No resolved tips received yet.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

<!-- Campaign Details Modal -->
<div class="modal fade" id="campaignDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title" id="mCampaignTitle"><i class="fas fa-bullhorn text-warning me-2"></i>Campaign Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <strong>Campaign ID:</strong> <span id="mCampaignId" class="badge bg-dark"></span>
                    </div>
                    <div class="col-md-6">
                        <strong>Category:</strong> <span id="mCampaignCategory" class="badge bg-secondary"></span>
                    </div>
                    <div class="col-md-6">
                        <strong>Geographical Scope:</strong> <span id="mCampaignScope" class="fw-semibold"></span>
                    </div>
                    <div class="col-md-6">
                        <strong>Status:</strong> <span id="mCampaignStatus" class="badge bg-success"></span>
                    </div>
                    <div class="col-12">
                        <strong>Full Description:</strong>
                        <div id="mCampaignDesc" class="p-3 bg-light rounded mt-1 border text-dark" style="white-space: pre-line; max-height: 220px; overflow-y: auto; font-size: 0.9rem; line-height: 1.5;"></div>
                    </div>
                    <div class="col-12" id="mCampaignImageWrap" style="display: none;">
                        <strong>Campaign Cover Image:</strong><br>
                        <img id="mCampaignImage" src="" alt="Campaign Cover" class="img-fluid rounded border mt-1" style="max-height: 260px;">
                    </div>
                    <div class="col-12">
                        <small class="text-muted"><i class="fas fa-link me-1"></i>Endpoint: <code>https://campaign.alertaraqc.com/api/v1/campaigns/public</code></small>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
function showCampaignDetails(campaign) {
    document.getElementById('mCampaignTitle').innerHTML = '<i class="fas fa-bullhorn text-warning me-2"></i>' + (campaign.title || 'Campaign Details');
    document.getElementById('mCampaignId').textContent = '#' + (campaign.campaign_id || campaign.id);
    document.getElementById('mCampaignCategory').textContent = (campaign.category || 'General').toUpperCase();
    document.getElementById('mCampaignScope').textContent = campaign.geographical_scope || 'Barangay';
    document.getElementById('mCampaignStatus').textContent = campaign.status || 'Active';
    document.getElementById('mCampaignDesc').textContent = campaign.description || 'No description provided.';
    
    var imgWrap = document.getElementById('mCampaignImageWrap');
    var imgEl = document.getElementById('mCampaignImage');
    if (campaign.image_url) {
        imgEl.src = campaign.image_url;
        imgWrap.style.display = 'block';
    } else {
        imgWrap.style.display = 'none';
    }

    var modal = new bootstrap.Modal(document.getElementById('campaignDetailModal'));
    modal.show();
}

document.addEventListener('DOMContentLoaded', function() {
    var searchInput = document.getElementById('campaignSearchInput');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            var q = this.value.toLowerCase().trim();
            var rows = document.querySelectorAll('#campaignsTable tbody tr.campaign-row');
            rows.forEach(function(row) {
                var text = row.textContent.toLowerCase();
                row.style.display = text.indexOf(q) !== -1 ? '' : 'none';
            });
        });
    }
});
</script>

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
