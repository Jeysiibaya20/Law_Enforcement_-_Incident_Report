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

    if ($action === 'simulate_incoming_call') {
        $callSample = [
            'Call ID' => trim($_POST['call_id'] ?? ('CALL-' . date('Ymd') . '-' . rand(100, 999))),
            'Timestamp' => $_POST['timestamp'] ?? date('Y-m-d H:i:s'),
            'Caller' => trim($_POST['caller'] ?? 'Aldrin Test Caller'),
            'Location' => trim($_POST['location'] ?? 'Susano Road, Brgy San Agustin, Quezon City'),
            'Emergency Level' => $_POST['emergency_level'] ?? 'High',
            'Incident Description' => trim($_POST['incident_description'] ?? 'Physical commotion and disturbance reported by resident near commercial area.')
        ];
        try {
            $res = $integrator->processIncomingEmergencyCall($callSample);
            $message = "Successfully received Emergency Call from Aldrin's Group (#" . $res['call_id'] . ")! Mirrored to Case #" . $res['case_no'] . " and logged into database.";
            $messageType = "success";
        } catch (Exception $e) {
            $message = "Error receiving emergency call: " . $e->getMessage();
            $messageType = "danger";
        }
    }

    if ($action === 'simulate_incoming_cctv_request') {
        $cctvReqSample = [
            'requesting_agency' => trim($_POST['requesting_agency'] ?? 'PNP Station 4 - Novaliches'),
            'contact_person' => trim($_POST['contact_person'] ?? 'P/Cpt. Ramos'),
            'contact_number' => trim($_POST['contact_number'] ?? '09181234567'),
            'email_address' => trim($_POST['email_address'] ?? 'ramos@pnp.gov.ph'),
            'case_reference' => trim($_POST['case_reference'] ?? ('INV-' . date('Y') . '-001')),
            'legal_basis' => $_POST['legal_basis'] ?? 'Law enforcement request',
            'purpose_reason' => trim($_POST['purpose_reason'] ?? 'Investigation of robbery incident along Susano Road.'),
            'incident_location' => trim($_POST['incident_location'] ?? 'Susano Road cor. Ramirez St., Quezon City'),
            'camera_id' => $_POST['camera_id'] ?? 'CAM-002 — Susano Road North',
            'incident_date' => $_POST['incident_date'] ?? date('Y-m-d'),
            'incident_type' => $_POST['incident_type'] ?? 'Theft / Robbery',
            'footage_start_time' => $_POST['footage_start_time'] ?? '14:00',
            'footage_end_time' => $_POST['footage_end_time'] ?? '15:30',
            'incident_description' => trim($_POST['incident_description'] ?? 'Suspects fled on motorcycle heading north.'),
            'delivery_method' => $_POST['delivery_method'] ?? 'Secure download link'
        ];
        try {
            $res = $integrator->processIncomingCctvRequest($cctvReqSample);
            $message = "Successfully received CCTV Request from Partner System! Request Code: " . $res['request_id_code'];
            $messageType = "success";
        } catch (Exception $e) {
            $message = "Error receiving CCTV request: " . $e->getMessage();
            $messageType = "danger";
        }
    }

    if ($action === 'simulate_group2_accident') {
        $sampleAccident = [
            'report_id' => trim($_POST['report_id'] ?? ('ACC-REP-' . date('Ymd') . '-' . rand(100, 999))),
            'ticket_number' => trim($_POST['ticket_number'] ?? ('TKT-' . date('Ymd') . '-' . rand(100, 999))),
            'incident_type' => trim($_POST['incident_type'] ?? 'Vehicular Collision / Reckless Imprudence'),
            'violator_name' => trim($_POST['violator_name'] ?? 'Juan Dela Cruz'),
            'vehicle_details' => trim($_POST['vehicle_details'] ?? 'Toyota Vios (Silver)'),
            'plate_number' => trim($_POST['plate_number'] ?? 'NBD-5421'),
            'violation_type' => trim($_POST['violation_type'] ?? 'Reckless Driving & Over-speeding'),
            'fine_amount' => floatval($_POST['fine_amount'] ?? 2500.00),
            'severity_level' => $_POST['severity_level'] ?? 'High',
            'collision_type' => trim($_POST['collision_type'] ?? 'Side-impact Collision (T-Bone)'),
            'location' => trim($_POST['location'] ?? 'Quezon Ave. cor. Timog Ave., Quezon City'),
            'narrative' => trim($_POST['narrative'] ?? 'Sedan beat red light and collided with motorcycle at intersection. Paramedics called to scene.'),
            'casualties_count' => intval($_POST['casualties_count'] ?? 1),
            'property_damage_estimate' => floatval($_POST['property_damage_estimate'] ?? 45000.00),
            'reporting_officer' => trim($_POST['reporting_officer'] ?? 'Traffic Enforcer Officer #44')
        ];
        try {
            $res = $integrator->processIncomingAccidentReport($sampleAccident);
            $message = "Successfully received Group 2 Accident Ticket (#" . $res['ticket_number'] . ") and classified into Incident Logging module! Record ID: #" . $res['record_id'];
            $messageType = "success";
        } catch (Exception $e) {
            $message = "Error receiving accident report: " . $e->getMessage();
            $messageType = "danger";
        }
    }

    if ($action === 'simulate_group2_cctv_request') {
        $cctvReqData = [
            'case_number' => trim($_POST['case_number'] ?? ('CASE-' . date('Ymd') . '-042')),
            'incident_type' => trim($_POST['incident_type'] ?? 'Vehicular Collision'),
            'camera_location' => trim($_POST['camera_location'] ?? 'Quezon Ave. cor. EDSA, Quezon City'),
            'incident_date' => $_POST['incident_date'] ?? date('Y-m-d'),
            'incident_time' => $_POST['incident_time'] ?? date('H:i:s'),
            'vehicle_plate' => trim($_POST['vehicle_plate'] ?? 'NBD-5421'),
            'priority' => $_POST['priority'] ?? 'High',
            'reason' => trim($_POST['reason'] ?? 'Retrieve intersection traffic camera recordings of collision.')
        ];
        try {
            $res = $integrator->dispatchCctvRequestToGroup2($cctvReqData);
            $message = "Dispatched CCTV request to Group 2 (Accident & Violation Reporting). Request ID: " . $res['request_id'];
            $messageType = "success";
        } catch (Exception $e) {
            $message = "Error dispatching CCTV request: " . $e->getMessage();
            $messageType = "danger";
        }
    }

    if ($action === 'simulate_group2_ack') {
        $ackSample = [
            'request_id' => trim($_POST['request_id'] ?? ('REQ-CCTV-' . date('Ymd') . '-101')),
            'acknowledged_by' => trim($_POST['acknowledged_by'] ?? 'Group 2 Surveillance Operator #08'),
            'acknowledgement_notes' => trim($_POST['acknowledgement_notes'] ?? 'Request acknowledged. Traffic camera feeds from Northbound QC Ave retrieved and ready for transmission.'),
            'assigned_camera_operator' => trim($_POST['assigned_camera_operator'] ?? 'Operator #08 (Group 2)')
        ];
        try {
            $res = $integrator->acknowledgeCctvRequest($ackSample);
            $message = "Group 2 acknowledged CCTV Request #" . $res['request_id'] . " successfully!";
            $messageType = "success";
        } catch (Exception $e) {
            $message = "Error processing CCTV acknowledgement: " . $e->getMessage();
            $messageType = "danger";
        }
    }

    if ($action === 'simulate_group7_upload') {
        $g7Sample = [
            'evidence_id' => rand(10, 99),
            'evidence_number' => 'EVD-' . date('Y') . '-' . rand(1000, 9999),
            'case_number' => 'CASE-' . date('Ymd') . '-001',
            'description' => 'Accident scene still photos and dashcam video recording',
            'media_type' => 'Photo & Video',
            'photos' => [
                ['filename' => 'accident_scene_front.jpg', 'url' => 'https://report.alertaraqc.com/uploads/evidence/sample_photo_01.jpg']
            ],
            'videos' => [
                ['filename' => 'traffic_cam_playback.mp4', 'url' => 'https://report.alertaraqc.com/uploads/evidence/sample_video_01.mp4']
            ]
        ];
        try {
            $res = $integrator->dispatchToGroup7EvidenceUpload($g7Sample);
            $message = "Dispatched Photo & Video payload to Group 7 Upload API (" . htmlspecialchars(getIntegrationSetting('group7_evidence_upload_api_url', 'https://inspection.alertaraqc.com/api/upload_evidence.php')) . ")!";
            $messageType = "success";
        } catch (Exception $e) {
            $message = "Error uploading to Group 7: " . $e->getMessage();
            $messageType = "danger";
        }
    }

    if ($action === 'save_integration_settings') {
        setIntegrationSetting('cctv_request_api_url', $_POST['cctv_request_api_url'] ?? '');
        setIntegrationSetting('group7_inspection_api_url', $_POST['group7_inspection_api_url'] ?? '');
        setIntegrationSetting('group7_evidence_upload_api_url', $_POST['group7_evidence_upload_api_url'] ?? '');
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
        } elseif (($cRes['http_code'] ?? 0) === 401) {
            $message = "Campaigns server reached (HTTP 401): Connected to campaign.alertaraqc.com. Note that the remote campaign server requires a JWT auth token format.";
            $messageType = "info";
        } else {
            $message = "Campaign API response code: " . ($cRes['http_code'] ?: 'Offline') . ". Details: " . ($cRes['curl_error'] ?: 'Check endpoint URL or network');
            $messageType = "warning";
        }
    }

    if ($action === 'simulate_incoming_inspection') {
        $inspSample = [
            'request_id' => trim($_POST['request_id'] ?? ('REQ-DOC-' . date('Ymd') . '-' . rand(100, 999))),
            'document_id' => trim($_POST['document_id'] ?? ('DOC-' . date('Y') . '-' . rand(1000, 9999))),
            'case_no' => trim($_POST['case_no'] ?? ('INC-' . date('Ymd') . '-001')),
            'document_type' => trim($_POST['document_type'] ?? 'Barangay Safety & Health Inspection Certificate'),
            'business_or_location' => trim($_POST['business_or_location'] ?? 'Barangay Central Commercial Arcade, District 1, QC'),
            'inspector_name' => trim($_POST['inspector_name'] ?? 'Engr. D. Santos (Field Inspector)'),
            'inspection_status' => trim($_POST['inspection_status'] ?? 'Compliant & Approved'),
            'findings' => trim($_POST['findings'] ?? 'Premises inspected for electrical, fire exit, and sanitation safety. Full compliance observed.'),
            'compliance_score' => trim($_POST['compliance_score'] ?? '96/100'),
            'certificate_url' => trim($_POST['certificate_url'] ?? 'https://picsum.photos/seed/inspection_cert/800/600.jpg'),
            'evidence_urls' => trim($_POST['evidence_urls'] ?? 'https://picsum.photos/seed/insp_photo1/800/600.jpg, https://picsum.photos/seed/insp_photo2/800/600.jpg'),
            'inspection_date' => $_POST['inspection_date'] ?? date('Y-m-d')
        ];

        // Handle uploaded file if present
        if (!empty($_FILES['inspection_file']['name']) && $_FILES['inspection_file']['error'] === UPLOAD_ERR_OK) {
            $uDir = __DIR__ . '/../uploads/external_media/';
            if (!is_dir($uDir)) @mkdir($uDir, 0755, true);
            $ext = pathinfo($_FILES['inspection_file']['name'], PATHINFO_EXTENSION);
            $uName = 'INSP_' . date('Ymd_His') . '_' . rand(100, 999) . '.' . $ext;
            if (move_uploaded_file($_FILES['inspection_file']['tmp_name'], $uDir . $uName)) {
                $inspSample['evidence_urls'] = 'uploads/external_media/' . $uName;
            }
        }

        try {
            $res = $integrator->processIncomingInspectionDocument($inspSample);
            $message = "Successfully ingested inspection document & photos! Record ID: #" . $res['record_id'] . " (Doc ID: " . $res['document_id'] . ")";
            $messageType = "success";
        } catch (Exception $e) {
            $message = "Error receiving inspection document: " . $e->getMessage();
            $messageType = "danger";
        }
    }

    if ($action === 'dispatch_test_marto_cctv') {
        $cctvSample = [
            'request_id' => 'CCTV-TEST-' . date('Ymd-His'),
            'requesting_agency' => 'Digital Blotter System',
            'agency' => 'Digital Blotter System',
            'contact_person' => $_SESSION['admin_name'] ?? 'Admin Dispatcher',
            'contact_number' => '0917-123-4567',
            'email_address' => 'dispatch@qc.gov.ph',
            'case_reference' => 'BLT-' . date('Ymd') . '-01',
            'legal_basis' => 'Law enforcement surveillance request',
            'location' => 'Commonwealth Ave / Batasan Road Intersection, Quezon City',
            'camera' => 'CAM-001 — Main Entrance Camera',
            'incident_date' => date('Y-m-d'),
            'footage_start_time' => '14:00',
            'footage_end_time' => '15:30',
            'purpose' => 'Verification of incident footage with partner surveillance unit.',
            'incident_description' => 'Test CCTV footage request sent from Alertara QC Law Enforcement external integration portal.',
            'delivery_method' => 'Secure download link'
        ];
        $res = $integrator->dispatchToPartnerCctvApi($cctvSample);
        $partnerMsg = $res['response']['message'] ?? ($res['success'] ? 'Request received and queued by Marto Surveillance Unit.' : 'Failed');
        $message = "Dispatched Test Request to Marto CCTV API (" . htmlspecialchars($res['endpoint']) . "). HTTP Status: " . ($res['http_code'] ?: '0') . " - " . htmlspecialchars($partnerMsg);
        $messageType = $res['success'] ? 'success' : 'warning';
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
$receivedAccidents = [];
$receivedInspections = [];

try {
    $stmt = $pdo->query("SELECT * FROM external_integration_log ORDER BY id DESC LIMIT 20");
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $logs = []; }

try {
    $stmtF = $pdo->query("SELECT * FROM cctv_footage_received ORDER BY id DESC LIMIT 20");
    $receivedFootage = $stmtF->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $receivedFootage = []; }

try {
    $stmtT = $pdo->query("SELECT * FROM received_resolved_tips ORDER BY id DESC LIMIT 20");
    $receivedTips = $stmtT->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $receivedTips = []; }

try {
    $stmtC = $pdo->query("SELECT * FROM received_campaigns ORDER BY id DESC LIMIT 25");
    $receivedCampaigns = $stmtC->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $receivedCampaigns = []; }

try {
    $stmtA = $pdo->query("SELECT * FROM received_accident_reports ORDER BY id DESC LIMIT 25");
    $receivedAccidents = $stmtA->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $receivedAccidents = []; }

$receivedCalls = [];
try {
    $stmtCalls = $pdo->query("SELECT * FROM received_emergency_calls ORDER BY id DESC LIMIT 25");
    $receivedCalls = $stmtCalls->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $receivedCalls = []; }

try {
    $stmtInsp = $pdo->query("SELECT * FROM received_inspection_documents ORDER BY id DESC LIMIT 30");
    $receivedInspections = $stmtInsp->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $receivedInspections = []; }


?>

<div class="main-content">
    <div class="content-container">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h1 class="h2 mb-1 fw-bold text-dark"><i class="fas fa-network-wired text-success me-2"></i>External Systems Integration</h1>
                <p class="text-muted small mb-0">Bi-directional payload standardization and external Partner Surveillance API dashboard</p>
            </div>
            <div class="d-flex gap-2">
                <a href="../modules/department_integrations.php" class="btn btn-success fw-bold shadow-sm" style="background-color: #2e856e; border-color: #2e856e;"><i class="fas fa-cubes me-1"></i> Department Hub</a>
                <a href="dashboard.php" class="btn btn-outline-success btn-sm fw-semibold"><i class="bi bi-arrow-left me-1"></i> Dashboard</a>
            </div>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?= $messageType ?> alert-dismissible fade show mb-4" role="alert">
                <i class="fas fa-check-circle me-2"></i> <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Partner Integration API Specifications Banner -->
        <div class="card mb-4 border-0 shadow-sm rounded-3 overflow-hidden">
            <div class="card-header text-white fw-bold d-flex align-items-center py-3 px-4" style="background: linear-gradient(135deg, #1b4332 0%, #2d6a4f 100%) !important;">
                <i class="fas fa-network-wired me-2 text-warning"></i> Inter-Group Integration Specifications & Inbound Webhook URLs
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <div class="p-3 bg-white border border-success-subtle rounded-3 h-100 shadow-sm">
                            <span class="badge bg-success mb-2">INCOMING (GROUP 2)</span>
                            <h6 class="fw-bold text-dark mb-1">Accident & Violation Report</h6>
                            <code class="small text-break">/api/receive_accident_report.php</code>
                            <p class="small text-muted mt-2 mb-0">Receives Accident Tickets & Traffic Reports from Group 2; auto-logs into Incident records.</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-3 bg-white border border-success-subtle rounded-3 h-100 shadow-sm">
                            <span class="badge bg-success mb-2">OUTGOING (GROUP 7)</span>
                            <h6 class="fw-bold text-dark mb-1">Photo & Video Upload API</h6>
                            <code class="small text-break"><?= htmlspecialchars($integrationSettings['group7_evidence_upload_api_url'] ?? 'https://inspection.alertaraqc.com/api/upload_evidence.php') ?></code>
                            <p class="small text-muted mt-2 mb-0">Dispatches photos & video evidence directly to Group 7 Inspection Cloud.</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-3 bg-white border border-success-subtle rounded-3 h-100 shadow-sm">
                            <span class="badge bg-success mb-2">OUTGOING (GROUP 2)</span>
                            <h6 class="fw-bold text-dark mb-1">Request CCTV from Group 2</h6>
                            <code class="small text-break"><?= htmlspecialchars($integrationSettings['cctv_request_api_url'] ?? '') ?></code>
                            <p class="small text-muted mt-2 mb-0">Dispatches automated CCTV footage & traffic camera retrieval queries to Group 2.</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-3 bg-white border border-success-subtle rounded-3 h-100 shadow-sm">
                            <span class="badge bg-success mb-2">INCOMING (GROUP 2)</span>
                            <h6 class="fw-bold text-dark mb-1">Receive Fulfilled CCTV Evidence</h6>
                            <code class="small text-break">/api/cctv_footage_receive.php</code>
                            <p class="small text-muted mt-2 mb-0">Group 2 acknowledges & sends fulfilled camera evidence, photos, and videos.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Integration Ready Endpoint Manager Form -->
        <div class="card mb-4 border-0 shadow-sm rounded-3 overflow-hidden">
            <div class="card-header text-white fw-bold d-flex justify-content-between align-items-center py-3 px-4" style="background: linear-gradient(135deg, #1b4332 0%, #2d6a4f 100%) !important;">
                <span><i class="fas fa-sliders-h me-2 text-warning"></i>Integration Ready Endpoint Manager (Configure API Target URLs)</span>
                <span class="badge bg-warning text-dark px-3 py-1.5 rounded-pill"><i class="fas fa-plug me-1"></i>Integration Ready</span>
            </div>
            <div class="card-body">
                <p class="small text-muted mb-4">Input or update destination API URLs for external partner systems. All modules are pre-configured to route payloads to these target endpoints as soon as external APIs go live.</p>

                <form method="POST">
                    <input type="hidden" name="action" value="save_integration_settings">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-dark"><i class="fas fa-video text-success me-2"></i>1. Group 2 CCTV Request Target API URL</label>
                            <div class="input-group">
                                <input type="url" name="cctv_request_api_url" class="form-control" value="<?= htmlspecialchars($integrationSettings['cctv_request_api_url'] ?? '') ?>" placeholder="https://surveillance.alertaraqc.com/api/cctv_requests_receive.php" required>
                            </div>
                            <small class="text-muted">Target endpoint for CCTV footage & traffic camera requests.</small>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold text-dark"><i class="fas fa-camera text-primary me-2"></i>2. Group 7 Photo & Video Upload API URL</label>
                            <div class="input-group">
                                <input type="url" name="group7_evidence_upload_api_url" class="form-control" value="<?= htmlspecialchars($integrationSettings['group7_evidence_upload_api_url'] ?? '') ?>" placeholder="https://inspection.alertaraqc.com/api/upload_evidence.php">
                            </div>
                            <small class="text-muted">Target endpoint for sending photos & video evidence files.</small>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold text-dark"><i class="fas fa-calendar-check text-primary me-2"></i>3. Group 7 Inspection Scheduling API URL</label>
                            <div class="input-group">
                                <input type="url" name="group7_inspection_api_url" class="form-control" value="<?= htmlspecialchars($integrationSettings['group7_inspection_api_url'] ?? '') ?>" placeholder="https://inspection.alertaraqc.com/api/schedule_inspection.php">
                            </div>
                            <small class="text-muted">Target endpoint for inspection scheduling and case referral.</small>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold text-dark"><i class="fas fa-map-marked-alt text-info me-2"></i>4. Group 5 Crime Mapping GIS API URL</label>
                            <div class="input-group">
                                <input type="url" name="group5_crime_map_api_url" class="form-control" value="<?= htmlspecialchars($integrationSettings['group5_crime_map_api_url'] ?? '') ?>" placeholder="https://crimemap.alertaraqc.com/api/update_heatmap.php">
                            </div>
                            <small class="text-muted">Target endpoint for real-time GIS spatial heatmap updates.</small>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold text-dark"><i class="fas fa-ambulance text-warning me-2"></i>5. Group 3 EMS & Resource Allocation API URL</label>
                            <div class="input-group">
                                <input type="url" name="group3_resource_api_url" class="form-control" value="<?= htmlspecialchars($integrationSettings['group3_resource_api_url'] ?? '') ?>" placeholder="https://dispatch.alertaraqc.com/api/assign_officer.php">
                            </div>
                            <small class="text-muted">Target endpoint for officer dispatch and emergency unit tracking.</small>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold text-dark"><i class="fas fa-bullhorn text-danger me-2"></i>6. Public Safety Campaign API URL (Group 1)</label>
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
                        <button type="submit" class="btn btn-success px-4 fw-bold shadow-sm" style="background-color: #2e856e !important; border-color: #2e856e !important; color: #ffffff !important;">
                            <i class="fas fa-save me-2"></i>Save Integration Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Live Public Safety Campaigns Card (campaign.alertaraqc.com) -->
        <div class="card mb-4 border-0 shadow-sm rounded-3 overflow-hidden" id="campaignsSection">
            <div class="card-header text-white fw-bold d-flex justify-content-between align-items-center flex-wrap gap-2 py-3 px-4" style="background: linear-gradient(135deg, #1b4332 0%, #2d6a4f 100%) !important;">
                <div>
                    <i class="fas fa-bullhorn me-2 text-warning"></i>Live Public Safety Campaigns (`campaign.alertaraqc.com`)
                    <span class="badge bg-white text-success ms-2 rounded-pill px-3 py-1.5"><?= count($receivedCampaigns) ?> Synced Campaign(s)</span>
                </div>
                <div class="d-flex gap-2 align-items-center">
                    <input type="text" id="campaignSearchInput" class="form-control form-control-sm" placeholder="Filter campaigns..." style="width: 220px;">
                    <form method="POST" class="d-inline mb-0">
                        <input type="hidden" name="action" value="fetch_campaigns">
                        <button type="submit" class="btn btn-sm btn-light text-success fw-bold px-3 shadow-sm">
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
                                        <td><span class="badge bg-success bg-opacity-10 text-success border border-success-subtle"><?= htmlspecialchars(ucfirst($c['category'])) ?></span></td>
                                        <td><?= htmlspecialchars($c['geographical_scope'] ?: 'Barangay') ?></td>
                                        <td><span class="badge bg-success"><?= htmlspecialchars($c['status']) ?></span></td>
                                        <td><?= date('M d, Y g:i a', strtotime($c['fetched_at'])) ?></td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-sm btn-outline-success fw-bold py-0 px-2" onclick="showCampaignDetails(<?= htmlspecialchars(json_encode($c), ENT_QUOTES, 'UTF-8') ?>)">
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
            <div class="col-12 col-sm-6 col-xl-3">
                <article class="dashboard-analytics-card analytics-tone-notif h-100">
                    <div class="dashboard-analytics-head">
                        <span class="dashboard-analytics-label">Group 7 Inspection</span>
                        <span class="dashboard-analytics-icon"><i class="fas fa-calendar-check"></i></span>
                    </div>
                    <div class="dashboard-analytics-value" style="font-size: 1.35rem; margin: 0.4rem 0;">Active Endpoint</div>
                    <div class="dashboard-analytics-sub">Standardized case scheduling</div>
                </article>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <article class="dashboard-analytics-card analytics-tone-info h-100">
                    <div class="dashboard-analytics-head">
                        <span class="dashboard-analytics-label">Group 5 Mapping</span>
                        <span class="dashboard-analytics-icon"><i class="fas fa-map-marked-alt"></i></span>
                    </div>
                    <div class="dashboard-analytics-value" style="font-size: 1.35rem; margin: 0.4rem 0;">GIS Connected</div>
                    <div class="dashboard-analytics-sub">Realtime spatial geocoding</div>
                </article>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <article class="dashboard-analytics-card analytics-tone-pending h-100">
                    <div class="dashboard-analytics-head">
                        <span class="dashboard-analytics-label">Group 3 EMS / Police</span>
                        <span class="dashboard-analytics-icon"><i class="fas fa-ambulance"></i></span>
                    </div>
                    <div class="dashboard-analytics-value" style="font-size: 1.35rem; margin: 0.4rem 0;">Resource Dispatch</div>
                    <div class="dashboard-analytics-sub">District officer tracking</div>
                </article>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <article class="dashboard-analytics-card analytics-tone-subs h-100">
                    <div class="dashboard-analytics-head">
                        <span class="dashboard-analytics-label">Partner CCTV API</span>
                        <span class="dashboard-analytics-icon"><i class="fas fa-video"></i></span>
                    </div>
                    <div class="dashboard-analytics-value" style="font-size: 1.35rem; margin: 0.4rem 0;">CCTV Connected</div>
                    <div class="dashboard-analytics-sub">surveillance.alertaraqc.com</div>
                </article>
            </div>
        </div>

        <div class="row">
            <!-- Test Payload Processor Form -->
            <div class="col-lg-5 mb-4">
                <div class="card h-100 border-0 shadow-sm rounded-3 overflow-hidden">
                    <div class="card-header text-white fw-bold d-flex align-items-center py-3 px-4" style="background: linear-gradient(135deg, #1b4332 0%, #2d6a4f 100%) !important;">
                        <i class="fas fa-sliders-h me-2 text-warning"></i>Simulate Inbound Data Integration
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

                            <button type="submit" class="btn btn-success w-100 text-white fw-bold shadow-sm" style="background-color: #2e856e !important; border-color: #2e856e !important;">
                                <i class="fas fa-play me-2"></i>Simulate Inbound & Generate Module Dispatches
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Standardized Output Inspector -->
            <div class="col-lg-7 mb-4">
                <div class="card h-100 border-0 shadow-sm rounded-3 overflow-hidden">
                    <div class="card-header text-white fw-bold d-flex justify-content-between align-items-center py-3 px-4" style="background: linear-gradient(135deg, #1b4332 0%, #2d6a4f 100%) !important;">
                        <span><i class="fas fa-project-diagram me-2 text-warning"></i>Standardized Downstream Module Payloads</span>
                        <?php if ($testResult): ?>
                            <span class="badge bg-white text-success rounded-pill px-3 py-1.5">Processed</span>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if ($testResult): ?>
                            <!-- Executive Incident Summary -->
                            <div class="p-3 mb-3 bg-light rounded border border-success-subtle">
                                <h6 class="fw-bold text-dark mb-1"><i class="fas fa-file-alt me-2 text-success"></i>Executive Incident Summary</h6>
                                <p class="small text-secondary mb-2"><?= htmlspecialchars($testResult['executive_incident_summary']['summary']) ?></p>
                                <div class="d-flex gap-2 align-items-center">
                                    <span class="badge bg-danger">Risk Level: <?= htmlspecialchars($testResult['executive_incident_summary']['risk_level']) ?></span>
                                    <span class="badge bg-success">Score: <?= (int)$testResult['executive_incident_summary']['urgency_score'] ?>/100</span>
                                </div>
                            </div>

                            <!-- Tabs for Group Payloads -->
                            <ul class="nav nav-tabs nav-fill mb-3" id="payloadTabs" role="tablist">
                                <li class="nav-item">
                                    <button class="nav-link active btn-sm fw-semibold" id="g7-tab" data-bs-toggle="tab" data-bs-target="#g7" type="button">Group 7 Inspection</button>
                                </li>
                                <li class="nav-item">
                                    <button class="nav-link btn-sm fw-semibold" id="g5-tab" data-bs-toggle="tab" data-bs-target="#g5" type="button">Group 5 Crime Map</button>
                                </li>
                                <li class="nav-item">
                                    <button class="nav-link btn-sm fw-semibold" id="g3-tab" data-bs-toggle="tab" data-bs-target="#g3" type="button">Group 3 Resource</button>
                                </li>
                                <li class="nav-item">
                                    <button class="nav-link btn-sm fw-semibold" id="cctv-tab" data-bs-toggle="tab" data-bs-target="#cctv" type="button">Partner CCTV API</button>
                                </li>
                            </ul>

                            <div class="tab-content border p-3 rounded text-light" style="background: #0f172a;" id="payloadTabContent">
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
                                        <button type="submit" class="btn btn-sm btn-success fw-bold shadow-sm" style="background-color: #2e856e !important; border-color: #2e856e !important;">
                                            <i class="fas fa-paper-plane me-1"></i> Dispatch to Partner CCTV API
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5 text-muted">
                                <i class="fas fa-network-wired fa-3x mb-3 text-success opacity-50"></i>
                                <h5 class="fw-bold text-dark">No active payload simulation</h5>
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
                <div class="card h-100 shadow-sm border-0 rounded-3 overflow-hidden">
                    <div class="card-header text-white fw-bold d-flex justify-content-between align-items-center flex-wrap gap-2 py-3 px-4" style="background: linear-gradient(135deg, #1b4332 0%, #2d6a4f 100%) !important;">
                        <div>
                            <i class="fas fa-video me-2 text-warning"></i>Received CCTV Footage (`cctv_footage_received`)
                            <span class="badge bg-white text-success rounded-pill px-3 py-1.5 ms-2"><?= count($receivedFootage) ?> record(s)</span>
                        </div>
                        <button type="button" class="btn btn-sm btn-light text-success fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#simulateCctvModal">
                            <i class="fas fa-plus-circle me-1 text-success"></i>Simulate Inbound CCTV
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive" style="max-height: 300px;">
                            <table class="table table-sm table-hover align-middle mb-0" style="font-size: 0.82rem;">
                                <thead class="table-light">
                                    <tr>
                                        <th>REQ ID</th>
                                        <th>LOCATION / CAM</th>
                                        <th>RECEIVED</th>
                                        <th class="text-center">ACTION</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($receivedFootage)): ?>
                                        <?php foreach ($receivedFootage as $rf): ?>
                                            <tr>
                                                <td class="fw-bold text-success">#<?= htmlspecialchars($rf['request_id'] ?: $rf['id']) ?></td>
                                                <td><?= htmlspecialchars(($rf['location'] ?: 'QC') . ' (' . ($rf['camera_id'] ?: 'CAM') . ')') ?></td>
                                                <td><?= date('M d H:i', strtotime($rf['received_at'] ?? $rf['created_at'] ?? 'now')) ?></td>
                                                <td class="text-center">
                                                    <button type="button" class="btn btn-xs btn-outline-success py-0 px-2 fw-bold" onclick="showCctvDetails(<?= htmlspecialchars(json_encode($rf), ENT_QUOTES, 'UTF-8') ?>)">
                                                        <i class="fas fa-eye me-1"></i>View Details
                                                    </button>
                                                </td>
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
                <div class="card h-100 shadow-sm border-0 rounded-3 overflow-hidden">
                    <div class="card-header text-white fw-bold d-flex justify-content-between align-items-center flex-wrap gap-2 py-3 px-4" style="background: linear-gradient(135deg, #1b4332 0%, #2d6a4f 100%) !important;">
                        <div>
                            <i class="fas fa-lightbulb me-2 text-warning"></i>Received Resolved Tips (`received_resolved_tips`)
                            <span class="badge bg-white text-success rounded-pill px-3 py-1.5 ms-2"><?= count($receivedTips) ?> record(s)</span>
                        </div>
                        <button type="button" class="btn btn-sm btn-light text-success fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#simulateTipModal">
                            <i class="fas fa-plus-circle me-1 text-success"></i>Simulate Inbound Tip
                        </button>
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
                                        <th class="text-center">ACTION</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($receivedTips)): ?>
                                        <?php foreach ($receivedTips as $rt): ?>
                                            <tr>
                                                <td class="fw-bold text-success">#<?= htmlspecialchars($rt['tip_id'] ?: $rt['id']) ?></td>
                                                <td><?= htmlspecialchars($rt['title'] ?: $rt['incident_type']) ?></td>
                                                <td><?= htmlspecialchars($rt['resolved_by'] ?: 'Operator') ?></td>
                                                <td><?= date('M d H:i', strtotime($rt['created_at'])) ?></td>
                                                <td class="text-center">
                                                    <button type="button" class="btn btn-xs btn-outline-success py-0 px-2 fw-bold" onclick="showTipDetails(<?= htmlspecialchars(json_encode($rt), ENT_QUOTES, 'UTF-8') ?>)">
                                                        <i class="fas fa-eye me-1"></i>View Details
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="5" class="text-center text-muted py-3">No resolved tips received yet.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Emergency Calls & Incident Ingestion Live Stream (`received_emergency_calls`) -->
        <div class="card mb-4 border-0 shadow-sm rounded-3 overflow-hidden">
            <div class="card-header text-white fw-bold d-flex justify-content-between align-items-center flex-wrap gap-2 py-3 px-4" style="background: linear-gradient(135deg, #1b4332 0%, #2d6a4f 100%) !important;">
                <div>
                    <i class="fas fa-phone-alt me-2 text-warning"></i>Emergency Calls & Incident Ingestion Stream (`received_emergency_calls`)
                    <span class="badge bg-white text-success ms-2 rounded-pill px-3 py-1.5"><?= count($receivedCalls) ?> Ingested Record(s)</span>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-light text-success fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#simulateCallModal">
                        <i class="fas fa-plus-circle me-1 text-success"></i>Simulate Inbound Emergency Call
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height: 350px;">
                    <table class="table table-hover align-middle mb-0" style="font-size: 0.84rem;">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th>CALL ID</th>
                                <th>CALLER & LOCATION</th>
                                <th>INCIDENT DETAILS</th>
                                <th>EMERGENCY LEVEL</th>
                                <th>MIRRORED CASE #</th>
                                <th>TIMESTAMP</th>
                                <th>STATUS</th>
                                <th class="text-center">ACTION</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($receivedCalls)): ?>
                                <?php foreach ($receivedCalls as $rc): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-danger font-monospace">#<?= htmlspecialchars($rc['call_id'] ?: 'CALL') ?></span>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($rc['caller_name'] ?: 'Caller') ?></strong>
                                            <div class="small text-muted text-truncate" style="max-width: 220px;">
                                                <i class="fas fa-map-marker-alt text-danger me-1"></i><?= htmlspecialchars($rc['caller_location'] ?: 'QC') ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="fw-semibold text-dark text-truncate" style="max-width: 280px;" title="<?= htmlspecialchars($rc['incident_description']) ?>">
                                                <?= htmlspecialchars($rc['incident_description']) ?>
                                            </div>
                                            <small class="text-muted"><?= htmlspecialchars($rc['incident_type'] ?: 'Emergency Call') ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= match($rc['emergency_level']) {
                                                'Critical' => 'danger',
                                                'High' => 'warning text-dark',
                                                'Medium' => 'info text-dark',
                                                default => 'success'
                                            } ?>"><?= htmlspecialchars($rc['emergency_level'] ?: 'High') ?></span>
                                        </td>
                                        <td>
                                            <code class="fw-bold text-primary"><?= htmlspecialchars($rc['case_no'] ?: 'N/A') ?></code>
                                        </td>
                                        <td><?= date('M d, Y H:i', strtotime($rc['call_timestamp'] ?: $rc['created_at'])) ?></td>
                                        <td>
                                            <span class="badge bg-success bg-opacity-10 text-success border border-success">
                                                <i class="fas fa-check-circle me-1"></i><?= htmlspecialchars($rc['status'] ?: 'Dispatched') ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-xs btn-outline-success py-0 px-2 fw-bold" onclick="showCallDetails(<?= htmlspecialchars(json_encode($rc), ENT_QUOTES, 'UTF-8') ?>)">
                                                <i class="fas fa-eye me-1"></i>Inspect
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="8" class="text-center text-muted py-4">No emergency calls received yet. Ready to ingest via <code>POST /api/receive_emergency_call.php</code>.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Group 2: Accident & Violation Reports Live Stream (`received_accident_reports`) -->
        <div class="card mb-4 border-0 shadow-sm rounded-3 overflow-hidden">
            <div class="card-header text-white fw-bold d-flex justify-content-between align-items-center flex-wrap gap-2 py-3 px-4" style="background: linear-gradient(135deg, #1b4332 0%, #2d6a4f 100%) !important;">
                <div>
                    <i class="fas fa-car-crash me-2 text-warning"></i>Group 2: Accident & Violation Reports Received (`received_accident_reports`)
                    <span class="badge bg-white text-success ms-2 rounded-pill px-3 py-1.5"><?= count($receivedAccidents) ?> Synced Record(s)</span>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-light text-success fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#simulateAccidentModal">
                        <i class="fas fa-plus-circle me-1 text-success"></i>Simulate Inbound Accident Report & Ticket (Group 2)
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height: 380px;">
                    <table class="table table-hover align-middle mb-0" style="font-size: 0.84rem;">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th>TICKET #</th>
                                <th>VIOLATOR & VEHICLE</th>
                                <th>VIOLATION / COLLISION</th>
                                <th>FINE (PHP)</th>
                                <th>SEVERITY</th>
                                <th>LOCATION</th>
                                <th>RECEIVED AT</th>
                                <th class="text-center">ACTION</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($receivedAccidents)): ?>
                                <?php foreach ($receivedAccidents as $acc): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-success">#<?= htmlspecialchars($acc['ticket_number'] ?: 'N/A') ?></span>
                                            <div class="small text-muted font-monospace"><?= htmlspecialchars($acc['report_id'] ?: '') ?></div>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($acc['violator_name'] ?: 'Unknown') ?></strong>
                                            <div class="small text-muted"><?= htmlspecialchars($acc['vehicle_details'] ?: '') ?> &bull; <code><?= htmlspecialchars($acc['plate_number'] ?: 'NO PLATE') ?></code></div>
                                        </td>
                                        <td>
                                            <span class="fw-semibold text-danger"><?= htmlspecialchars($acc['violation_type'] ?: 'Traffic Violation') ?></span>
                                            <div class="small text-muted"><?= htmlspecialchars($acc['collision_type'] ?: 'Accident Incident') ?></div>
                                        </td>
                                        <td class="fw-bold text-success">&#8369;<?= number_format((float)$acc['fine_amount'], 2) ?></td>
                                        <td>
                                            <span class="badge bg-<?= match($acc['severity_level']) {
                                                'Critical' => 'danger',
                                                'High' => 'warning text-dark',
                                                default => 'info'
                                             } ?>"><?= htmlspecialchars($acc['severity_level'] ?: 'High') ?></span>
                                        </td>
                                        <td><small class="text-truncate d-block" style="max-width: 200px;"><?= htmlspecialchars($acc['location']) ?></small></td>
                                        <td><?= date('M d, Y H:i', strtotime($acc['created_at'])) ?></td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-xs btn-outline-success py-0 px-2 fw-bold" onclick="showAccidentDetails(<?= htmlspecialchars(json_encode($acc), ENT_QUOTES, 'UTF-8') ?>)">
                                                <i class="fas fa-eye me-1"></i>Inspect
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">
                                        No Group 2 accident reports received yet. Inbound API endpoint: <code>/api/receive_accident_report.php</code>.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Group 7: Received Inspection Documents & Compliance Certificates (`received_inspection_documents`) -->
        <div class="card mb-4 border-0 shadow-sm rounded-3 overflow-hidden">
            <div class="card-header text-white fw-bold d-flex justify-content-between align-items-center flex-wrap gap-2 py-3 px-4" style="background: linear-gradient(135deg, #1b4332 0%, #2d6a4f 100%) !important;">
                <div>
                    <i class="fas fa-clipboard-check me-2 text-warning"></i>Group 7: Received Inspection Documents & Evidentiary Photos (`received_inspection_documents`)
                    <span class="badge bg-white text-success ms-2 rounded-pill px-3 py-1.5"><?= count($receivedInspections) ?> Ingested Document(s)</span>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-light text-success fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#simulateInspectionModal">
                        <i class="fas fa-plus-circle me-1 text-success"></i>Simulate Inbound Inspection & Photos
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height: 420px;">
                    <table class="table table-hover align-middle mb-0" style="font-size: 0.84rem;">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th>DOC ID</th>
                                <th>ESTABLISHMENT / LOCATION</th>
                                <th>DOCUMENT TYPE</th>
                                <th>INSPECTOR</th>
                                <th>FINDINGS SUMMARY</th>
                                <th>SCORE / STATUS</th>
                                <th>PHOTOS / MEDIA</th>
                                <th>RECEIVED AT</th>
                                <th class="text-center">ACTION</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($receivedInspections)): ?>
                                <?php foreach ($receivedInspections as $insp): ?>
                                    <?php
                                        $mediaList = [];
                                        if (!empty($insp['evidence_urls'])) {
                                            $dec = json_decode($insp['evidence_urls'], true);
                                            if (is_array($dec)) {
                                                $mediaList = $dec;
                                            } else {
                                                $mediaList = array_map('trim', explode(',', $insp['evidence_urls']));
                                            }
                                        }
                                        if (!empty($insp['certificate_url']) && !in_array($insp['certificate_url'], $mediaList)) {
                                            array_unshift($mediaList, $insp['certificate_url']);
                                        }
                                        $mediaList = array_filter($mediaList);
                                    ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-primary font-monospace">#<?= htmlspecialchars($insp['document_id'] ?: 'DOC-' . $insp['id']) ?></span>
                                            <?php if (!empty($insp['case_no'])): ?>
                                                <div class="small text-muted font-monospace"><?= htmlspecialchars($insp['case_no']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong class="text-dark"><?= htmlspecialchars($insp['business_or_location'] ?: 'Quezon City Location') ?></strong>
                                            <div class="small text-muted"><i class="fas fa-calendar-alt me-1"></i><?= htmlspecialchars($insp['inspection_date'] ?: date('Y-m-d')) ?></div>
                                        </td>
                                        <td>
                                            <span class="badge bg-info bg-opacity-10 text-info border border-info-subtle fw-semibold">
                                                <?= htmlspecialchars($insp['document_type'] ?: 'Inspection Report') ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="fw-semibold text-dark"><?= htmlspecialchars($insp['inspector_name'] ?: 'Field Inspector') ?></div>
                                        </td>
                                        <td>
                                            <div class="text-truncate" style="max-width: 220px;" title="<?= htmlspecialchars($insp['findings'] ?: '—') ?>">
                                                <?= htmlspecialchars($insp['findings'] ?: '—') ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-success bg-opacity-10 text-success border border-success fw-bold">
                                                <?= htmlspecialchars($insp['compliance_score'] ?: 'N/A') ?>
                                            </span>
                                            <div class="small text-muted"><?= htmlspecialchars($insp['inspection_status'] ?: 'Completed') ?></div>
                                        </td>
                                        <td>
                                            <?php if (!empty($mediaList)): ?>
                                                <div class="d-flex gap-1 align-items-center flex-wrap" style="max-width: 140px;">
                                                    <?php 
                                                        $previewThumb = reset($mediaList); 
                                                        $thumbSrc = (strpos($previewThumb, 'http') === 0 || strpos($previewThumb, '/') === 0) ? $previewThumb : '../' . $previewThumb;
                                                    ?>
                                                    <a href="javascript:void(0)" onclick="openLightbox('<?= htmlspecialchars($thumbSrc, ENT_QUOTES) ?>', '<?= htmlspecialchars($insp['document_type'] . ' - ' . $insp['business_or_location'], ENT_QUOTES) ?>')" class="position-relative d-inline-block">
                                                        <img src="<?= htmlspecialchars($thumbSrc) ?>" class="rounded border shadow-sm" style="width: 42px; height: 42px; object-fit: cover;" alt="Photo">
                                                        <?php if (count($mediaList) > 1): ?>
                                                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-dark text-white" style="font-size: 0.65rem;">
                                                                +<?= count($mediaList) - 1 ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </a>
                                                    <span class="small text-muted ms-1"><?= count($mediaList) ?> photo(s)</span>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted small">No photos attached</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= date('M d, Y H:i', strtotime($insp['received_at'])) ?></td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-xs btn-outline-success py-1 px-2 fw-bold" onclick="showInspectionDetails(<?= htmlspecialchars(json_encode($insp), ENT_QUOTES, 'UTF-8') ?>)">
                                                <i class="fas fa-eye me-1"></i>Inspect & Photos
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-4">
                                        No inspection documents received yet. Inbound API endpoint: <code>/api/receive_inspection_document.php</code>.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Inter-Group Action Simulators (Group 7 Upload, Marto CCTV Policy Dispatch, Group 2 Cycle) -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm rounded-3 overflow-hidden">
                    <div class="card-header text-white fw-bold d-flex align-items-center py-3 px-4" style="background: linear-gradient(135deg, #1b4332 0%, #2d6a4f 100%) !important;">
                        <h6 class="mb-0 text-white fw-bold"><i class="fas fa-video me-2 text-warning"></i>Marto's Group: Policy CCTV Request Dispatch</h6>
                    </div>
                    <div class="card-body">
                        <p class="small text-muted">Directly test outbound dispatch of formal CCTV requests to Marto's Surveillance API on Policy (<code>policy.alertaraqc.com</code>).</p>
                        <form method="POST">
                            <input type="hidden" name="action" value="dispatch_test_marto_cctv">
                            <div class="mb-2">
                                <label class="form-label small fw-bold">Target Marto Endpoint</label>
                                <input type="text" class="form-control form-control-sm font-monospace" value="<?= htmlspecialchars($integrationSettings['cctv_request_api_url'] ?? 'https://policy.alertaraqc.com/api/cctv_requests_receive.php') ?>" readonly>
                            </div>
                            <div class="p-2 bg-light rounded border small mb-3">
                                <strong>Auth Header:</strong> <code>X-API-KEY: ALERTARA-EMERGENCY-2026</code><br>
                                <strong>Payload:</strong> Footage time window, Case Ref, Location, Purpose.
                            </div>
                            <button type="submit" class="btn btn-sm btn-success w-100 fw-bold shadow-sm" style="background-color: #2e856e !important; border-color: #2e856e !important;">
                                <i class="fas fa-paper-plane me-1"></i> Live Dispatch to Marto CCTV API (200 OK)
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm rounded-3 overflow-hidden">
                    <div class="card-header text-white fw-bold d-flex align-items-center py-3 px-4" style="background: linear-gradient(135deg, #1b4332 0%, #2d6a4f 100%) !important;">
                        <h6 class="mb-0 text-white fw-bold"><i class="fas fa-cloud-upload-alt me-2 text-warning"></i>Group 7: Photo & Video Upload Simulator</h6>
                    </div>
                    <div class="card-body">
                        <p class="small text-muted">Test transmission of collected crime/accident scene photos and surveillance videos to Group 7's Photo and Videos Upload endpoint.</p>
                        <form method="POST">
                            <input type="hidden" name="action" value="simulate_group7_upload">
                            <div class="mb-2">
                                <label class="form-label small fw-bold">Target API Endpoint</label>
                                <input type="text" class="form-control form-control-sm font-monospace" value="<?= htmlspecialchars($integrationSettings['group7_evidence_upload_api_url'] ?? 'https://inspection.alertaraqc.com/api/upload_evidence.php') ?>" readonly>
                            </div>
                            <div class="p-2 bg-light rounded border small mb-3">
                                <strong>Payload Contains:</strong> Photos (JPG/PNG), Videos (MP4/WebM), Evidence Reference Number, and Case Hash.
                            </div>
                            <button type="submit" class="btn btn-sm btn-success w-100 fw-bold shadow-sm" style="background-color: #2e856e !important; border-color: #2e856e !important;">
                                <i class="fas fa-paper-plane me-1"></i> Test Dispatch Photos & Videos to Group 7
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm rounded-3 overflow-hidden">
                    <div class="card-header text-white fw-bold d-flex align-items-center py-3 px-4" style="background: linear-gradient(135deg, #1b4332 0%, #2d6a4f 100%) !important;">
                        <h6 class="mb-0 text-white fw-bold"><i class="fas fa-sync-alt me-2 text-warning"></i>Group 2: CCTV Request & Acknowledgement Cycle</h6>
                    </div>
                    <div class="card-body">
                        <p class="small text-muted">Group 1 requests CCTV camera retrieval from Group 2 (Accident & Violation Reporting), and Group 2 acknowledges the request with fulfilled video/photo evidence.</p>
                        <div class="d-flex gap-2">
                            <form method="POST" class="w-50">
                                <input type="hidden" name="action" value="simulate_group2_cctv_request">
                                <button type="submit" class="btn btn-sm btn-outline-success w-100 fw-bold">
                                    <i class="fas fa-satellite-dish me-1"></i> 1. Dispatch
                                </button>
                            </form>
                            <form method="POST" class="w-50">
                                <input type="hidden" name="action" value="simulate_group2_ack">
                                <button type="submit" class="btn btn-sm btn-success w-100 fw-bold shadow-sm" style="background-color: #2e856e !important; border-color: #2e856e !important;">
                                    <i class="fas fa-check-double me-1"></i> 2. Acknowledge
                                </button>
                            </form>
                        </div>
                        <div class="mt-2 small text-muted">
                            Data flow: <code>Group 1 (Request CCTV) &rarr; Group 2 (Acknowledge) &rarr; Group 1 (Receive Evidence, Photos, Videos)</code>.
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
        <div class="card shadow-sm border-0 rounded-3 overflow-hidden mb-4">
            <div class="card-header py-3 px-4 d-flex justify-content-between align-items-center" style="background: linear-gradient(135deg, #1b4332 0%, #2d6a4f 100%) !important; color: #ffffff !important;">
                <div class="d-flex align-items-center gap-2">
                    <i class="fas fa-network-wired text-warning fs-5"></i>
                    <div>
                        <h6 class="mb-0 text-white fw-bold" style="color: #ffffff !important; font-family: inherit;">Integration Network Activity & Exchange Log</h6>
                        <small class="text-white" style="font-size: 0.78rem; opacity: 0.9; color: #ffffff !important;">Live audit trail of all transmitted and received API payloads</small>
                    </div>
                </div>
                <span class="badge bg-white text-success rounded-pill px-3 py-1.5 fw-bold shadow-sm" style="color: #1b4332 !important; background-color: #ffffff !important;">
                    <?= count($logs ?? []) ?> Transactions Logged
                </span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size: 0.88rem;">
                        <thead style="background-color: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                            <tr class="text-secondary" style="font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.5px;">
                                <th class="ps-4 py-3">Log ID</th>
                                <th class="py-3">Direction</th>
                                <th class="py-3">Target / Endpoint URL</th>
                                <th class="py-3">Status</th>
                                <th class="py-3">Timestamp</th>
                                <th class="pe-4 py-3 text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($logs)): ?>
                                <?php foreach ($logs as $l): ?>
                                    <?php
                                        $dir = strtolower($l['direction'] ?? '');
                                        $isIncoming = (strpos($dir, 'incoming') !== false || strpos($dir, 'inbound') !== false);
                                        $st = strtolower($l['status'] ?? '');
                                        $statusClass = 'bg-secondary-subtle text-secondary';
                                        $statusIcon = 'fa-info-circle';
                                        $statusText = strtoupper($l['status'] ?? 'RECORDED');

                                        if (strpos($st, 'success') !== false || strpos($st, 'processed') !== false || strpos($st, 'acknowledged') !== false) {
                                            $statusClass = 'bg-success-subtle text-success border border-success-subtle';
                                            $statusIcon = 'fa-check-circle';
                                        } elseif (strpos($st, 'sent') !== false || strpos($st, 'simulated') !== false || strpos($st, 'logged') !== false) {
                                            $statusClass = 'bg-primary-subtle text-primary border border-primary-subtle';
                                            $statusIcon = 'fa-paper-plane';
                                        } elseif (strpos($st, 'fail') !== false || strpos($st, 'offline') !== false || strpos($st, 'error') !== false) {
                                            $statusClass = 'bg-warning-subtle text-warning-emphasis border border-warning-subtle';
                                            $statusIcon = 'fa-clock';
                                            $statusText = 'OFFLINE / LOGGED';
                                        }
                                    ?>
                                    <tr>
                                        <td class="ps-4 fw-semibold text-dark">#<?= (int)$l['id'] ?></td>
                                        <td>
                                            <?php if ($isIncoming): ?>
                                                <span class="badge rounded-pill bg-primary-subtle text-primary border border-primary-subtle px-2.5 py-1.5 fw-semibold">
                                                    <i class="fas fa-arrow-down me-1"></i>INBOUND
                                                </span>
                                            <?php else: ?>
                                                <span class="badge rounded-pill bg-info-subtle text-dark border border-info-subtle px-2.5 py-1.5 fw-semibold">
                                                    <i class="fas fa-arrow-up me-1"></i>OUTBOUND
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-truncate font-monospace text-muted" style="max-width: 280px;" title="<?= htmlspecialchars($l['target_url'] ?? '') ?>">
                                            <?= htmlspecialchars($l['target_url'] ?: 'Internal System Receiver') ?>
                                        </td>
                                        <td>
                                            <span class="badge rounded-pill <?= $statusClass ?> px-2.5 py-1.5 fw-semibold">
                                                <i class="fas <?= $statusIcon ?> me-1"></i><?= $statusText ?>
                                            </span>
                                        </td>
                                        <td class="text-muted"><small><i class="far fa-clock me-1 text-muted"></i><?= date('M d, Y · g:i a', strtotime($l['created_at'])) ?></small></td>
                                        <td class="pe-4 text-end">
                                            <button type="button" class="btn btn-outline-success btn-sm rounded-pill px-3 fw-semibold shadow-sm" onclick="showLogDetails(<?= htmlspecialchars(json_encode($l), ENT_QUOTES, 'UTF-8') ?>)">
                                                <i class="fas fa-code me-1"></i>Payload & Logs
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-5">
                                        <i class="fas fa-inbox fs-2 text-muted mb-2 d-block"></i>
                                        No integration logs recorded yet.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Integration Log Payload & Details Modal -->
<div class="modal fade" id="logDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 rounded-3 overflow-hidden shadow-lg">
            <div class="modal-header text-white fw-bold py-3 px-4" style="background: linear-gradient(135deg, #1b4332 0%, #2d6a4f 100%) !important;">
                <h5 class="modal-title" id="mLogTitle"><i class="fas fa-network-wired text-warning me-2"></i>Integration Log Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <strong>Log Entry ID:</strong> <span id="mLogId" class="badge bg-success"></span>
                    </div>
                    <div class="col-md-4">
                        <strong>Direction / Action:</strong> <span id="mLogDirection" class="badge bg-success bg-opacity-10 text-success border border-success-subtle"></span>
                    </div>
                    <div class="col-md-4">
                        <strong>Status:</strong> <span id="mLogStatus" class="badge bg-success"></span>
                    </div>
                    <div class="col-12">
                        <strong>Target Endpoint / Destination URL:</strong>
                        <div class="input-group input-group-sm mt-1">
                            <input type="text" id="mLogTargetUrl" class="form-control font-monospace" readonly>
                            <button type="button" class="btn btn-outline-success" onclick="navigator.clipboard.writeText(document.getElementById('mLogTargetUrl').value); alert('Target URL copied!');">
                                <i class="fas fa-copy me-1"></i>Copy
                            </button>
                        </div>
                    </div>
                    <div class="col-12">
                        <strong>Transmitted Payload (Request Body JSON):</strong>
                        <pre id="mLogPayload" class="p-3 rounded mt-1 font-monospace text-warning" style="background: #0f172a; max-height: 250px; overflow-y: auto; font-size: 0.82rem; white-space: pre-wrap;"></pre>
                    </div>
                    <div class="col-12">
                        <strong>API Endpoint Response (Response Body JSON):</strong>
                        <pre id="mLogResponse" class="p-3 rounded mt-1 font-monospace text-info" style="background: #0f172a; max-height: 200px; overflow-y: auto; font-size: 0.82rem; white-space: pre-wrap;"></pre>
                    </div>
                    <div class="col-12 text-end text-muted small">
                        Logged At: <span id="mLogTimestamp"></span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-success px-4 fw-semibold" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- CCTV Footage Detail Modal -->
<div class="modal fade" id="cctvDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 rounded-3 overflow-hidden shadow-lg">
            <div class="modal-header text-white fw-bold py-3 px-4" style="background: linear-gradient(135deg, #1b4332 0%, #2d6a4f 100%) !important;">
                <h5 class="modal-title" id="mCctvTitle"><i class="fas fa-video text-warning me-2"></i>Received CCTV Footage Record</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <strong>Request Reference ID:</strong> <span id="mCctvReqId" class="badge bg-success"></span>
                    </div>
                    <div class="col-md-6">
                        <strong>Incident Case ID:</strong> <span id="mCctvIncidentId" class="badge bg-success bg-opacity-10 text-success border border-success-subtle"></span>
                    </div>
                    <div class="col-md-6">
                        <strong>Location / Barangay:</strong> <span id="mCctvLocation"></span>
                    </div>
                    <div class="col-md-6">
                        <strong>Camera Reference:</strong> <span id="mCctvCamera"></span>
                    </div>
                    <div class="col-12">
                        <strong>Footage Stream / Video URL:</strong>
                        <div class="input-group input-group-sm mt-1">
                            <input type="text" id="mCctvUrl" class="form-control font-monospace" readonly>
                            <a id="mCctvOpenBtn" href="#" target="_blank" class="btn btn-success fw-bold shadow-sm" style="background-color: #2e856e !important; border-color: #2e856e !important;">
                                <i class="fas fa-external-link-alt me-1"></i>Open Video Stream
                            </a>
                        </div>
                    </div>
                    <div class="col-12">
                        <strong>Operator Notes / Verification:</strong>
                        <div id="mCctvNotes" class="p-3 bg-light rounded mt-1 border border-success-subtle text-dark" style="font-size: 0.9rem;"></div>
                    </div>
                    <div class="col-12 text-end text-muted small">
                        Received At: <span id="mCctvTimestamp"></span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-success px-4 fw-semibold" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Resolved Tip Detail Modal -->
<div class="modal fade" id="tipDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 rounded-3 overflow-hidden shadow-lg">
            <div class="modal-header text-white fw-bold py-3 px-4" style="background: linear-gradient(135deg, #1b4332 0%, #2d6a4f 100%) !important;">
                <h5 class="modal-title" id="mTipTitle"><i class="fas fa-lightbulb text-warning me-2"></i>Received Resolved Tip Record</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <strong>Tip Reference ID:</strong> <span id="mTipId" class="badge bg-success"></span>
                    </div>
                    <div class="col-md-6">
                        <strong>Incident Type:</strong> <span id="mTipType" class="badge bg-success bg-opacity-10 text-success border border-success-subtle"></span>
                    </div>
                    <div class="col-md-6">
                        <strong>Resolved By:</strong> <span id="mTipResolvedBy" class="fw-semibold"></span>
                    </div>
                    <div class="col-md-6">
                        <strong>Resolution Status:</strong> <span id="mTipStatus" class="badge bg-success"></span>
                    </div>
                    <div class="col-12">
                        <strong>Resolution Summary & Narrative:</strong>
                        <div id="mTipSummary" class="p-3 bg-light rounded mt-1 border border-success-subtle text-dark" style="font-size: 0.9rem; line-height: 1.5; white-space: pre-line;"></div>
                    </div>
                    <div class="col-12">
                        <small class="text-muted"><i class="fas fa-info-circle me-1 text-success"></i>Ingested from Group 4 Anonymous Tip Line System</small>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-success px-4 fw-semibold" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Simulate Inbound Emergency Call Modal -->
<div class="modal fade" id="simulateCallModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 rounded-3 overflow-hidden shadow-lg">
            <div class="modal-header text-white fw-bold py-3 px-4" style="background: linear-gradient(135deg, #1b4332 0%, #2d6a4f 100%) !important;">
                <h5 class="modal-title"><i class="fas fa-phone-alt text-warning me-2"></i>Simulate Inbound Emergency Call (911 Dispatch Stream)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="simulate_incoming_call">
                <div class="modal-body p-4">
                    <p class="text-muted small mb-3">
                        <i class="fas fa-info-circle text-success me-1"></i>
                        Simulates an emergency call from Aldrin's Partner Emergency Response System. This will automatically ingest the record, assign a priority dispatch code, and mirror a case into the central Incident Logging system.
                    </p>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Call ID / Reference</label>
                            <input type="text" name="call_id" class="form-control font-monospace" value="CALL-<?= date('Ymd') ?>-<?= rand(100, 999) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Emergency Priority Level</label>
                            <select name="emergency_level" class="form-select fw-bold">
                                <option value="Critical" class="text-danger">🔴 Critical (Immediate Response)</option>
                                <option value="High" selected class="text-warning">🟠 High (Urgent Dispatch)</option>
                                <option value="Medium" class="text-info">🔵 Medium (Standard Patrol)</option>
                                <option value="Low" class="text-secondary">⚪ Low (Routine Check)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Caller Full Name / Contact</label>
                            <input type="text" name="caller" class="form-control" value="Aldrin Test Caller (0918-555-0199)" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Incident Location / Landmark</label>
                            <input type="text" name="location" class="form-control" value="Susano Road, Brgy San Agustin, Novaliches, Quezon City" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold">Incident Description / Dispatch Narrative</label>
                            <textarea name="incident_description" class="form-control" rows="3" required>Physical commotion and altercation reported by residents near the commercial establishment. Requesting emergency response team and police backup unit.</textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success fw-bold px-4 shadow-sm" style="background-color: #2e856e !important; border-color: #2e856e !important;">
                        <i class="fas fa-satellite-dish me-1"></i>Ingest & Dispatch Emergency Call
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Simulate Inbound Accident Report & Ticket Modal (Group 2) -->
<div class="modal fade" id="simulateAccidentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 rounded-3 overflow-hidden shadow-lg">
            <div class="modal-header text-white fw-bold py-3 px-4" style="background: linear-gradient(135deg, #1b4332 0%, #2d6a4f 100%) !important;">
                <h5 class="modal-title"><i class="fas fa-car-crash text-warning me-2"></i>Simulate Inbound Accident Report & Ticket (Group 2)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="simulate_group2_accident">
                <div class="modal-body p-4">
                    <p class="text-muted small mb-3">
                        <i class="fas fa-info-circle text-success me-1"></i>
                        Simulates an inbound accident report and traffic violation citation ticket from Group 2. Ingests into <code>received_accident_reports</code> and syncs to central incident logs.
                    </p>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Report ID</label>
                            <input type="text" name="report_id" class="form-control font-monospace" value="ACC-REP-<?= date('Ymd') ?>-<?= rand(100, 999) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Ticket Number</label>
                            <input type="text" name="ticket_number" class="form-control font-monospace" value="TKT-<?= date('Ymd') ?>-<?= rand(100, 999) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Violator / Driver Name</label>
                            <input type="text" name="violator_name" class="form-control" value="Juan Dela Cruz" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Vehicle Details</label>
                            <input type="text" name="vehicle_details" class="form-control" value="Toyota Vios (Silver Sedan)" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Plate Number</label>
                            <input type="text" name="plate_number" class="form-control font-monospace" value="NBD-5421" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Violation Type</label>
                            <input type="text" name="violation_type" class="form-control" value="Reckless Driving & Over-speeding" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Fine Amount (PHP)</label>
                            <input type="number" step="0.01" name="fine_amount" class="form-control" value="2500.00" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Severity Level</label>
                            <select name="severity_level" class="form-select fw-bold">
                                <option value="High" selected>High</option>
                                <option value="Critical">Critical</option>
                                <option value="Medium">Medium</option>
                                <option value="Low">Low</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Collision Type</label>
                            <input type="text" name="collision_type" class="form-control" value="Side-impact Collision (T-Bone)" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Location</label>
                            <input type="text" name="location" class="form-control" value="Quezon Ave. cor. Timog Ave., Quezon City" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Casualties Count</label>
                            <input type="number" name="casualties_count" class="form-control" value="1" min="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Property Damage Estimate (PHP)</label>
                            <input type="number" step="0.01" name="property_damage_estimate" class="form-control" value="45000.00">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Reporting Traffic Officer</label>
                            <input type="text" name="reporting_officer" class="form-control" value="Traffic Enforcer Officer #44" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold">Accident & Violation Narrative</label>
                            <textarea name="narrative" class="form-control" rows="2" required>Sedan beat red light and collided with motorcycle at intersection. Paramedics called to scene for rider checkup.</textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success fw-bold px-4 shadow-sm" style="background-color: #2e856e !important; border-color: #2e856e !important;">
                        <i class="fas fa-file-import me-1"></i>Ingest & Sync Group 2 Accident Report
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Simulate Inbound CCTV Footage Modal -->
<div class="modal fade" id="simulateCctvModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 rounded-3 overflow-hidden shadow-lg">
            <div class="modal-header text-white fw-bold py-3 px-4" style="background: linear-gradient(135deg, #1b4332 0%, #2d6a4f 100%) !important;">
                <h5 class="modal-title"><i class="fas fa-video text-warning me-2"></i>Simulate Inbound CCTV Footage Ingestion</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="simulate_incoming_cctv">
                <div class="modal-body p-4">
                    <p class="text-muted small mb-3">
                        <i class="fas fa-info-circle text-success me-1"></i>
                        Simulates fulfilled video footage transmission from Marto's Partner Surveillance Network into <code>cctv_footage_received</code>.
                    </p>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Request ID</label>
                            <input type="text" name="request_id" class="form-control font-monospace" value="REQ-CCTV-<?= date('Ymd') ?>-<?= rand(100, 999) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Incident Case ID</label>
                            <input type="text" name="incident_id" class="form-control font-monospace" value="INC-<?= date('Ymd') ?>-<?= rand(10, 99) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Camera Identifier</label>
                            <input type="text" name="camera_id" class="form-control" value="CAM-QC-D1-042 (Novaliches Central)" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Location / Barangay</label>
                            <input type="text" name="location" class="form-control" value="Susano Road, Brgy San Agustin, Quezon City" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold">Video Stream URL</label>
                            <input type="text" name="cctv_url" class="form-control font-monospace" value="https://surveillance.alertaraqc.com/media/feeds/sample_footage_001.mp4" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold">Surveillance Operator Notes</label>
                            <textarea name="notes" class="form-control" rows="2" required>Footage captured street commotion during time window. Surveillance team verified vehicle license plate.</textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success fw-bold px-4 shadow-sm" style="background-color: #2e856e !important; border-color: #2e856e !important;">
                        <i class="fas fa-save me-1"></i>Ingest & Save CCTV Record
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Simulate Inbound Resolved Tip Modal -->
<div class="modal fade" id="simulateTipModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 rounded-3 overflow-hidden shadow-lg">
            <div class="modal-header text-white fw-bold py-3 px-4" style="background: linear-gradient(135deg, #1b4332 0%, #2d6a4f 100%) !important;">
                <h5 class="modal-title"><i class="fas fa-lightbulb text-warning me-2"></i>Simulate Inbound Resolved Tip Ingestion</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="simulate_incoming_tip">
                <div class="modal-body p-4">
                    <p class="text-muted small mb-3">
                        <i class="fas fa-info-circle text-success me-1"></i>
                        Simulates a resolved citizen tip from Group 4's Anonymous Tip Line. Ingests into <code>received_resolved_tips</code> and mirrors into incident logs.
                    </p>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Tip Reference ID</label>
                            <input type="text" name="tip_id" class="form-control font-monospace" value="TIP-<?= date('Ymd') ?>-<?= rand(1000, 9999) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Incident Type / Classification</label>
                            <input type="text" name="incident_type" class="form-control" value="Physical Violence / Public Disturbance" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Tip Title</label>
                            <input type="text" name="title" class="form-control" value="Resolved Public Disturbance & Noise Complaint" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Resolved By / Unit</label>
                            <input type="text" name="resolved_by" class="form-control" value="Group 4 Surveillance Operator #12" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold">Location</label>
                            <input type="text" name="location" class="form-control" value="Barangay Central, District 1, Quezon City" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold">Tip Details & Description</label>
                            <textarea name="description" class="form-control" rows="2" required>Tipster reported street altercation and loud argument near convenience store.</textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold">Resolution Notes / Action Taken</label>
                            <textarea name="resolution_notes" class="form-control" rows="2" required>Live camera feed monitored; patrol team arrived and resolved disturbance amicably.</textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success fw-bold px-4 shadow-sm" style="background-color: #2e856e !important; border-color: #2e856e !important;">
                        <i class="fas fa-save me-1"></i>Ingest & Save Tip Record
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Emergency Call Detail Modal -->
<div class="modal fade" id="callDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 rounded-3 overflow-hidden shadow-lg">
            <div class="modal-header text-white fw-bold py-3 px-4" style="background: linear-gradient(135deg, #1b4332 0%, #2d6a4f 100%) !important;">
                <h5 class="modal-title" id="mCallTitle"><i class="fas fa-phone-alt text-warning me-2"></i>Emergency Call Record</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <strong>Call Reference ID:</strong> <span id="mCallId" class="badge bg-danger font-monospace"></span>
                    </div>
                    <div class="col-md-6">
                        <strong>Mirrored Incident Case #:</strong> <span id="mCallCaseNo" class="badge bg-success font-monospace"></span>
                    </div>
                    <div class="col-md-6">
                        <strong>Caller Name / Dispatch:</strong> <span id="mCallCaller" class="fw-semibold"></span>
                    </div>
                    <div class="col-md-6">
                        <strong>Emergency Priority Level:</strong> <span id="mCallLevel"></span>
                    </div>
                    <div class="col-md-6">
                        <strong>Caller Location:</strong> <span id="mCallLocation"></span>
                    </div>
                    <div class="col-md-6">
                        <strong>Status:</strong> <span id="mCallStatus" class="badge bg-success bg-opacity-10 text-success border border-success"></span>
                    </div>
                    <div class="col-12">
                        <strong>Incident Narrative & Description:</strong>
                        <div id="mCallDescription" class="p-3 bg-light rounded mt-1 border border-success-subtle text-dark" style="font-size: 0.9rem; line-height: 1.5; white-space: pre-line;"></div>
                    </div>
                    <div class="col-12 text-end text-muted small">
                        Logged At: <span id="mCallTimestamp"></span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-success px-4 fw-semibold" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Simulate Inbound Inspection Document Modal -->
<div class="modal fade" id="simulateInspectionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 rounded-3 overflow-hidden shadow-lg">
            <div class="modal-header text-white fw-bold py-3 px-4" style="background: linear-gradient(135deg, #1b4332 0%, #2d6a4f 100%) !important;">
                <h5 class="modal-title"><i class="fas fa-clipboard-check text-warning me-2"></i>Simulate Inbound Inspection Document & Photos (Group 7)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="simulate_incoming_inspection">
                <div class="modal-body p-4">
                    <p class="text-muted small mb-3">
                        <i class="fas fa-info-circle text-success me-1"></i>
                        Simulates an inbound Field Inspection Document, Certificate, and Evidentiary Photos payload from Group 7 (<code>/api/receive_inspection_document.php</code>).
                    </p>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Document ID / Certificate Code</label>
                            <input type="text" name="document_id" class="form-control font-monospace" value="DOC-<?= date('Y') ?>-<?= rand(1000, 9999) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Related Incident / Case #</label>
                            <input type="text" name="case_no" class="form-control font-monospace" value="INC-<?= date('Ymd') ?>-001">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Document Type</label>
                            <input type="text" name="document_type" class="form-control" value="Barangay Safety & Health Inspection Certificate" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Business / Establishment Location</label>
                            <input type="text" name="business_or_location" class="form-control" value="Novaliches Market Arcade, District 5, Quezon City" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Inspector Name / Officer</label>
                            <input type="text" name="inspector_name" class="form-control" value="Engr. J. Santos (Safety Inspector)" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Compliance Score</label>
                            <input type="text" name="compliance_score" class="form-control" value="98/100" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Inspection Status</label>
                            <select name="inspection_status" class="form-select fw-semibold">
                                <option value="Compliant & Approved" selected>Compliant & Approved</option>
                                <option value="Conditional Approval">Conditional Approval</option>
                                <option value="Non-Compliant">Non-Compliant</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold">Certificate URL / Clearance Link</label>
                            <input type="text" name="certificate_url" class="form-control font-monospace" value="https://picsum.photos/seed/cert_01/800/600.jpg">
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold">Evidence Photo URLs (comma-separated or JSON list)</label>
                            <textarea name="evidence_urls" class="form-control font-monospace" rows="2">https://picsum.photos/seed/insp_photo1/800/600.jpg, https://picsum.photos/seed/insp_photo2/800/600.jpg, https://picsum.photos/seed/insp_photo3/800/600.jpg</textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold">Or Upload Photo / Document Directly (Optional)</label>
                            <input type="file" name="inspection_file" class="form-control form-control-sm" accept="image/*,.pdf">
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold">Inspection Findings & Remarks</label>
                            <textarea name="findings" class="form-control" rows="2" required>Premises inspected for building safety, electrical layout, emergency egress, and CCTV coverage. All safety protocols passed without violation.</textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success fw-bold px-4 shadow-sm" style="background-color: #2e856e !important; border-color: #2e856e !important;">
                        <i class="fas fa-save me-1"></i>Ingest Inspection Document & Photos
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Group 2 Accident & Ticket Detail Modal -->
<div class="modal fade" id="accidentDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 rounded-3 overflow-hidden shadow-lg">
            <div class="modal-header text-white fw-bold py-3 px-4" style="background: linear-gradient(135deg, #1b4332 0%, #2d6a4f 100%) !important;">
                <h5 class="modal-title" id="mAccTitle"><i class="fas fa-car-crash text-warning me-2"></i>Group 2 Accident & Violation Record</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <strong>Citation Ticket #:</strong> <span id="mAccTicket" class="badge bg-success font-monospace"></span>
                    </div>
                    <div class="col-md-6">
                        <strong>Report Reference ID:</strong> <span id="mAccReportId" class="badge bg-secondary font-monospace"></span>
                    </div>
                    <div class="col-md-6">
                        <strong>Violator / Driver Name:</strong> <span id="mAccViolator" class="fw-semibold"></span>
                    </div>
                    <div class="col-md-6">
                        <strong>Plate Number:</strong> <code id="mAccPlate" class="fw-bold fs-6"></code>
                    </div>
                    <div class="col-md-6">
                        <strong>Vehicle Details:</strong> <span id="mAccVehicle"></span>
                    </div>
                    <div class="col-md-6">
                        <strong>Fine Amount:</strong> <span id="mAccFine" class="fw-bold text-success"></span>
                    </div>
                    <div class="col-md-6">
                        <strong>Violation Type:</strong> <span id="mAccViolation" class="text-danger fw-semibold"></span>
                    </div>
                    <div class="col-md-6">
                        <strong>Collision Type:</strong> <span id="mAccCollision"></span>
                    </div>
                    <div class="col-md-6">
                        <strong>Severity Level:</strong> <span id="mAccSeverity"></span>
                    </div>
                    <div class="col-md-6">
                        <strong>Location:</strong> <span id="mAccLocation"></span>
                    </div>
                    <div class="col-md-4">
                        <strong>Casualties Count:</strong> <span id="mAccCasualties"></span>
                    </div>
                    <div class="col-md-4">
                        <strong>Property Damage:</strong> <span id="mAccDamage"></span>
                    </div>
                    <div class="col-md-4">
                        <strong>Reporting Officer:</strong> <span id="mAccOfficer"></span>
                    </div>
                    <div class="col-12">
                        <strong>Accident Narrative:</strong>
                        <div id="mAccNarrative" class="p-3 bg-light rounded mt-1 border border-success-subtle text-dark" style="font-size: 0.9rem; line-height: 1.5; white-space: pre-line;"></div>
                    </div>
                    <div class="col-12" id="mAccMediaSection" style="display:none;">
                        <strong>Accident Scene Photos & Evidence:</strong>
                        <div id="mAccPhotosGallery" class="d-flex flex-wrap gap-2 mt-2 p-2 bg-light rounded border"></div>
                    </div>
                    <div class="col-12 text-end text-muted small">
                        Received At: <span id="mAccTimestamp"></span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-success px-4 fw-semibold" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Inspection Document & Photo Gallery Modal -->
<div class="modal fade" id="inspectionDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 rounded-3 overflow-hidden shadow-lg">
            <div class="modal-header text-white fw-bold py-3 px-4" style="background: linear-gradient(135deg, #1b4332 0%, #2d6a4f 100%) !important;">
                <h5 class="modal-title" id="mInspTitle"><i class="fas fa-clipboard-check text-warning me-2"></i>Inspection Document & Evidentiary Photos</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <strong>Document Reference ID:</strong> <span id="mInspDocId" class="badge bg-primary font-monospace fs-6"></span>
                    </div>
                    <div class="col-md-6">
                        <strong>Related Case #:</strong> <span id="mInspCaseNo" class="badge bg-secondary font-monospace"></span>
                    </div>
                    <div class="col-md-6">
                        <strong>Document Type:</strong> <span id="mInspType" class="fw-bold text-dark"></span>
                    </div>
                    <div class="col-md-6">
                        <strong>Establishment / Location:</strong> <span id="mInspLocation" class="fw-semibold"></span>
                    </div>
                    <div class="col-md-6">
                        <strong>Inspector / Officer:</strong> <span id="mInspInspector"></span>
                    </div>
                    <div class="col-md-6">
                        <strong>Compliance Score / Status:</strong> <span id="mInspScore" class="badge bg-success"></span>
                    </div>
                    <div class="col-12">
                        <strong>Inspection Findings & Remarks:</strong>
                        <div id="mInspFindings" class="p-3 bg-light rounded mt-1 border border-success-subtle text-dark" style="font-size: 0.9rem; line-height: 1.5; white-space: pre-line;"></div>
                    </div>
                    <div class="col-12" id="mInspCertSection" style="display:none;">
                        <strong>Inspection Certificate:</strong>
                        <div id="mInspCertContent" class="mt-2"></div>
                    </div>
                    <div class="col-12">
                        <strong>Attached Evidentiary Photos & Media:</strong>
                        <div id="mInspPhotosGallery" class="d-flex flex-wrap gap-2 mt-2 p-2 bg-light rounded border">
                            <span class="text-muted small">No photos attached.</span>
                        </div>
                    </div>
                    <div class="col-12 text-end text-muted small">
                        Received At: <span id="mInspTimestamp"></span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-success px-4 fw-semibold" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Universal Photo / Image Lightbox Modal -->
<div class="modal fade" id="imageLightboxModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content bg-dark border-0 shadow-lg text-white">
            <div class="modal-header border-0 pb-0">
                <h6 class="modal-title text-light" id="lightboxTitle"><i class="fas fa-image me-2 text-warning"></i>Photo Preview</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center p-3">
                <div class="d-flex justify-content-center align-items-center" style="min-height: 400px; max-height: 75vh; overflow: hidden;">
                    <img id="lightboxImg" src="" alt="Full Resolution Photo" class="img-fluid rounded shadow" style="max-height: 72vh; max-width: 100%; object-fit: contain;">
                </div>
            </div>
            <div class="modal-footer border-0 pt-0 d-flex justify-content-between">
                <a id="lightboxOpenNewTab" href="#" target="_blank" class="btn btn-sm btn-outline-light">
                    <i class="fas fa-external-link-alt me-1"></i>Open Full Size
                </a>
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
function parseJsonPretty(data) {
    if (!data) return 'N/A (Empty)';
    if (typeof data === 'object') return JSON.stringify(data, null, 2);
    try {
        var parsed = JSON.parse(data);
        return JSON.stringify(parsed, null, 2);
    } catch(e) {
        return data;
    }
}

function openLightbox(src, title) {
    if (!src) return;
    document.getElementById('lightboxImg').src = src;
    document.getElementById('lightboxTitle').innerHTML = '<i class="fas fa-image me-2 text-warning"></i>' + (title || 'Photo Preview');
    document.getElementById('lightboxOpenNewTab').href = src;
    
    var modal = new bootstrap.Modal(document.getElementById('imageLightboxModal'));
    modal.show();
}

function showLogDetails(log) {
    document.getElementById('mLogTitle').innerHTML = '<i class="fas fa-network-wired text-primary me-2"></i>Log #' + (log.id || '') + ' Details';
    document.getElementById('mLogId').textContent = '#' + (log.id || '');
    document.getElementById('mLogDirection').textContent = (log.direction || 'SYSTEM').toUpperCase();
    document.getElementById('mLogStatus').textContent = (log.status || 'UNKNOWN').toUpperCase();
    document.getElementById('mLogTargetUrl').value = log.target_url || 'Internal Ingestion Engine';
    document.getElementById('mLogPayload').textContent = parseJsonPretty(log.payload);
    document.getElementById('mLogResponse').textContent = parseJsonPretty(log.response_body);
    document.getElementById('mLogTimestamp').textContent = log.created_at || '';

    var modal = new bootstrap.Modal(document.getElementById('logDetailModal'));
    modal.show();
}

function showCctvDetails(rf) {
    document.getElementById('mCctvTitle').innerHTML = '<i class="fas fa-video me-2"></i>CCTV Record #' + (rf.request_id || rf.id || '');
    document.getElementById('mCctvReqId').textContent = '#' + (rf.request_id || rf.id || 'N/A');
    document.getElementById('mCctvIncidentId').textContent = '#' + (rf.incident_id || 'N/A');
    document.getElementById('mCctvLocation').textContent = rf.location || 'Quezon City';
    document.getElementById('mCctvCamera').textContent = rf.camera_id || 'CAM-01';
    document.getElementById('mCctvUrl').value = rf.cctv_url || '';
    document.getElementById('mCctvOpenBtn').href = rf.cctv_url || '#';
    document.getElementById('mCctvNotes').textContent = rf.notes || rf.description || 'Footage request fulfilled by partner surveillance team.';
    document.getElementById('mCctvTimestamp').textContent = rf.received_at || rf.created_at || '';

    var modal = new bootstrap.Modal(document.getElementById('cctvDetailModal'));
    modal.show();
}

function showTipDetails(rt) {
    document.getElementById('mTipTitle').innerHTML = '<i class="fas fa-lightbulb me-2"></i>Resolved Tip #' + (rt.tip_id || rt.id || '');
    document.getElementById('mTipId').textContent = '#' + (rt.tip_id || rt.id || 'N/A');
    document.getElementById('mTipType').textContent = rt.incident_type || rt.title || 'General Tip';
    document.getElementById('mTipResolvedBy').textContent = rt.resolved_by || 'Partner Operator';
    document.getElementById('mTipStatus').textContent = rt.status || 'Resolved';
    document.getElementById('mTipSummary').textContent = rt.resolution_notes || rt.description || 'Tip resolved and verified by Group 4 team.';

    var modal = new bootstrap.Modal(document.getElementById('tipDetailModal'));
    modal.show();
}

function showCallDetails(rc) {
    document.getElementById('mCallTitle').innerHTML = '<i class="fas fa-phone-alt me-2"></i>Emergency Call #' + (rc.call_id || rc.id || '');
    document.getElementById('mCallId').textContent = '#' + (rc.call_id || rc.id || 'N/A');
    document.getElementById('mCallCaseNo').textContent = rc.case_no || 'N/A';
    document.getElementById('mCallCaller').textContent = rc.caller_name || 'Emergency Caller';
    
    var levelBadge = '<span class="badge bg-success">' + (rc.emergency_level || 'High') + '</span>';
    if (rc.emergency_level === 'Critical') levelBadge = '<span class="badge bg-danger">Critical</span>';
    else if (rc.emergency_level === 'High') levelBadge = '<span class="badge bg-warning text-dark">High</span>';
    else if (rc.emergency_level === 'Medium') levelBadge = '<span class="badge bg-info text-dark">Medium</span>';
    document.getElementById('mCallLevel').innerHTML = levelBadge;

    document.getElementById('mCallLocation').textContent = rc.caller_location || 'Quezon City';
    document.getElementById('mCallStatus').textContent = rc.status || 'Dispatched';
    document.getElementById('mCallDescription').textContent = rc.incident_description || 'No additional details provided.';
    document.getElementById('mCallTimestamp').textContent = rc.call_timestamp || rc.created_at || '';

    var modal = new bootstrap.Modal(document.getElementById('callDetailModal'));
    modal.show();
}

function showAccidentDetails(acc) {
    document.getElementById('mAccTitle').innerHTML = '<i class="fas fa-car-crash me-2"></i>Accident Ticket #' + (acc.ticket_number || 'N/A');
    document.getElementById('mAccTicket').textContent = '#' + (acc.ticket_number || 'N/A');
    document.getElementById('mAccReportId').textContent = acc.report_id || 'N/A';
    document.getElementById('mAccViolator').textContent = acc.violator_name || 'Unknown Driver';
    document.getElementById('mAccPlate').textContent = acc.plate_number || 'NO PLATE';
    document.getElementById('mAccVehicle').textContent = acc.vehicle_details || 'N/A';
    document.getElementById('mAccFine').textContent = '₱' + parseFloat(acc.fine_amount || 0).toLocaleString('en-US', { minimumFractionDigits: 2 });
    document.getElementById('mAccViolation').textContent = acc.violation_type || 'Traffic Violation';
    document.getElementById('mAccCollision').textContent = acc.collision_type || 'N/A';

    var sevBadge = '<span class="badge bg-info">' + (acc.severity_level || 'High') + '</span>';
    if (acc.severity_level === 'Critical') sevBadge = '<span class="badge bg-danger">Critical</span>';
    else if (acc.severity_level === 'High') sevBadge = '<span class="badge bg-warning text-dark">High</span>';
    document.getElementById('mAccSeverity').innerHTML = sevBadge;

    document.getElementById('mAccLocation').textContent = acc.location || 'Quezon City';
    document.getElementById('mAccCasualties').textContent = acc.casualties_count || '0';
    document.getElementById('mAccDamage').textContent = '₱' + parseFloat(acc.property_damage_estimate || 0).toLocaleString('en-US', { minimumFractionDigits: 2 });
    document.getElementById('mAccOfficer').textContent = acc.reporting_officer || 'Traffic Enforcer';
    document.getElementById('mAccNarrative').textContent = acc.narrative || 'Accident record synced from Group 2 system.';
    document.getElementById('mAccTimestamp').textContent = acc.created_at || '';

    // Accident photos rendering
    var accMediaSection = document.getElementById('mAccMediaSection');
    var accGallery = document.getElementById('mAccPhotosGallery');
    var accMediaList = [];
    if (acc.evidence_media) {
        try {
            var dec = JSON.parse(acc.evidence_media);
            if (Array.isArray(dec)) accMediaList = dec;
            else accMediaList = acc.evidence_media.split(',');
        } catch(e) {
            accMediaList = acc.evidence_media.split(',');
        }
    }
    accMediaList = accMediaList.map(function(s) { return s.trim(); }).filter(function(s) { return s.length > 0; });
    if (accMediaList.length > 0) {
        accMediaSection.style.display = 'block';
        var gHtml = '';
        accMediaList.forEach(function(m, idx) {
            var mUrl = (m.startsWith('http') || m.startsWith('/')) ? m : '../' + m;
            gHtml += '<div class="text-center">' +
                '<a href="javascript:void(0)" onclick="openLightbox(\'' + mUrl.replace(/'/g, "\\'") + '\', \'Accident Scene Photo #' + (idx + 1) + ' - Ticket ' + (acc.ticket_number || '').replace(/'/g, "\\'") + '\')">' +
                '<img src="' + mUrl + '" alt="Accident Photo" class="rounded border shadow-sm" style="width: 100px; height: 100px; object-fit: cover;">' +
                '</a>' +
                '<div class="small text-muted mt-1">Photo #' + (idx + 1) + '</div>' +
                '</div>';
        });
        accGallery.innerHTML = gHtml;
    } else {
        accMediaSection.style.display = 'none';
        accGallery.innerHTML = '';
    }

    var modal = new bootstrap.Modal(document.getElementById('accidentDetailModal'));
    modal.show();
}

function showInspectionDetails(insp) {
    document.getElementById('mInspTitle').innerHTML = '<i class="fas fa-clipboard-check text-warning me-2"></i>Inspection #' + (insp.document_id || insp.id);
    document.getElementById('mInspDocId').textContent = '#' + (insp.document_id || insp.id);
    document.getElementById('mInspCaseNo').textContent = insp.case_no || 'N/A';
    document.getElementById('mInspType').textContent = insp.document_type || 'Inspection Report';
    document.getElementById('mInspLocation').textContent = insp.business_or_location || 'N/A';
    document.getElementById('mInspInspector').textContent = insp.inspector_name || 'Field Inspector';
    document.getElementById('mInspScore').textContent = (insp.compliance_score || 'Passed') + ' (' + (insp.inspection_status || 'Approved') + ')';
    document.getElementById('mInspFindings').textContent = insp.findings || 'No findings recorded.';
    document.getElementById('mInspTimestamp').textContent = insp.received_at || '';

    // Certificate section
    var certSection = document.getElementById('mInspCertSection');
    var certContent = document.getElementById('mInspCertContent');
    if (insp.certificate_url) {
        certSection.style.display = 'block';
        var cUrl = (insp.certificate_url.startsWith('http') || insp.certificate_url.startsWith('/')) ? insp.certificate_url : '../' + insp.certificate_url;
        var isImg = /\.(jpg|jpeg|png|gif|webp)$/i.test(cUrl) || cUrl.includes('picsum') || cUrl.startsWith('data:image');
        if (isImg) {
            certContent.innerHTML = '<a href="javascript:void(0)" onclick="openLightbox(\'' + cUrl.replace(/'/g, "\\'") + '\', \'Inspection Certificate - ' + (insp.document_id || '').replace(/'/g, "\\'") + '\')">' +
                '<img src="' + cUrl + '" alt="Certificate" class="img-thumbnail rounded shadow-sm" style="max-height: 140px; object-fit: cover;">' +
                '<div class="small text-success mt-1"><i class="fas fa-search-plus me-1"></i>Click to Zoom Certificate</div></a>';
        } else {
            certContent.innerHTML = '<a href="' + cUrl + '" target="_blank" class="btn btn-sm btn-outline-danger"><i class="fas fa-file-pdf me-1"></i>View Official Certificate Document</a>';
        }
    } else {
        certSection.style.display = 'none';
        certContent.innerHTML = '';
    }

    // Photo gallery
    var photosGallery = document.getElementById('mInspPhotosGallery');
    var mediaList = [];
    if (insp.evidence_urls) {
        try {
            var dec = JSON.parse(insp.evidence_urls);
            if (Array.isArray(dec)) mediaList = dec;
            else mediaList = insp.evidence_urls.split(',');
        } catch(e) {
            mediaList = insp.evidence_urls.split(',');
        }
    }
    
    mediaList = mediaList.map(function(s) { return s.trim(); }).filter(function(s) { return s.length > 0; });
    if (mediaList.length > 0) {
        var gHtml = '';
        mediaList.forEach(function(m, idx) {
            var mUrl = (m.startsWith('http') || m.startsWith('/')) ? m : '../' + m;
            gHtml += '<div class="text-center">' +
                '<a href="javascript:void(0)" onclick="openLightbox(\'' + mUrl.replace(/'/g, "\\'") + '\', \'Inspection Photo #' + (idx + 1) + ' - ' + (insp.business_or_location || '').replace(/'/g, "\\'") + '\')">' +
                '<img src="' + mUrl + '" alt="Evidence Photo ' + (idx + 1) + '" class="rounded border shadow-sm" style="width: 100px; height: 100px; object-fit: cover;">' +
                '</a>' +
                '<div class="small text-muted mt-1">Photo #' + (idx + 1) + '</div>' +
                '</div>';
        });
        photosGallery.innerHTML = gHtml;
    } else {
        photosGallery.innerHTML = '<span class="text-muted small p-2">No additional evidentiary photos attached to this record.</span>';
    }

    var modal = new bootstrap.Modal(document.getElementById('inspectionDetailModal'));
    modal.show();
}
</script>

<?php require_once '../includes/footer.php'; ?>
