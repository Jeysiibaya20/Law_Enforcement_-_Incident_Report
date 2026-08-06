<?php
/**
 * Operational Module Integrator & Assistant Engine - Polished Integration Layer
 * Standardizes raw inbound data from external input modules (Group 3, Group 4),
 * generates structured outputs for downstream modules (Group 3, Group 5, Group 7),
 * and handles live bi-directional integration with Partner Surveillance API endpoints.
 */

class OperationalModuleIntegrator {
    private $pdo;
    private $partnerCctvEndpoint = 'https://surveillance.alertaraqc.com/api/cctv_requests_receive.php';
    private $timeout = 15;

    public function __construct($pdo = null) {
        $this->pdo = $pdo;
        if ($this->pdo instanceof PDO) {
            $this->ensureSchema();
        }
    }

    /**
     * Ensure database tables for external integration logs, received CCTV footage, and resolved tips exist
     */
    private function ensureSchema(): void {
        try {
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS external_integration_log (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                direction VARCHAR(50) NOT NULL,
                target_url TEXT NULL,
                payload LONGTEXT NULL,
                response_body LONGTEXT NULL,
                status VARCHAR(50) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            try {
                $this->pdo->exec("ALTER TABLE external_integration_log MODIFY COLUMN direction VARCHAR(50) NOT NULL");
            } catch (Exception $ex) {}

            $this->pdo->exec("CREATE TABLE IF NOT EXISTS cctv_footage_received (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                request_id VARCHAR(100) NULL,
                incident_id VARCHAR(100) NULL,
                cctv_url TEXT NULL,
                camera_id VARCHAR(100) NULL,
                location VARCHAR(255) NULL,
                video_format VARCHAR(50) DEFAULT 'video/mp4',
                duration VARCHAR(50) NULL,
                notes TEXT NULL,
                status VARCHAR(50) DEFAULT 'Received',
                received_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_incident_id (incident_id),
                INDEX idx_request_id (request_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $this->pdo->exec("CREATE TABLE IF NOT EXISTS received_resolved_tips (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tip_id VARCHAR(100) NULL,
                incident_id VARCHAR(100) NULL,
                incident_type VARCHAR(100) NULL,
                title VARCHAR(255) NULL,
                description TEXT NULL,
                location VARCHAR(255) NULL,
                district VARCHAR(100) NULL,
                resolved_by VARCHAR(150) NULL,
                resolution_notes TEXT NULL,
                evidence_url TEXT NULL,
                resolved_at VARCHAR(100) NULL,
                status VARCHAR(50) DEFAULT 'Logged',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_tip_id (tip_id),
                INDEX idx_incident_id (incident_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } catch (Exception $e) {
            error_log("Schema initialization notice: " . $e->getMessage());
        }
    }

    /**
     * Process Raw Inbound Data & Generate Standardized Module Payloads
     */
    public function processInbound(array $rawInput, bool $autoDispatchCctv = false): array {
        $source = strtolower(trim($rawInput['source'] ?? $rawInput['source_type'] ?? 'unknown'));

        // Standardize input fields
        $standard = $this->standardize($rawInput, $source);

        // Analyze risk & operational urgency
        $analysis = $this->analyzeIncident($standard);

        // Build module payloads
        $group7Payload = $this->buildGroup7InspectionPayload($standard, $analysis);
        $group5Payload = $this->buildGroup5CrimeMappingPayload($standard);
        $group3ResourcePayload = $this->buildGroup3ResourceAllocationPayload($standard, $analysis);
        $cctvPartnerPayload = $this->buildCctvPartnerPayload($standard);

        // Actionable next steps
        $recommendations = $this->buildRecommendations($standard, $analysis);

        $dispatchResult = null;
        if ($autoDispatchCctv) {
            $dispatchResult = $this->dispatchToPartnerCctvApi($cctvPartnerPayload);
        }

        $result = [
            'executive_incident_summary' => [
                'summary' => $analysis['summary'],
                'risk_level' => $analysis['risk_level'],
                'urgency_score' => $analysis['urgency_score'],
                'key_operational_takeaways' => $analysis['takeaways']
            ],
            'module_specific_payloads' => [
                'group_7_inspection_scheduling' => $group7Payload,
                'group_5_crime_mapping' => $group5Payload,
                'group_3_resource_allocation' => $group3ResourcePayload,
                'cctv_partner_surveillance_api' => $cctvPartnerPayload
            ],
            'cctv_partner_dispatch_status' => $dispatchResult,
            'actionable_recommendations' => $recommendations,
            'standardized_data' => $standard,
            'raw_input_data' => $rawInput
        ];

        // Save incoming integration log if PDO available
        if ($this->pdo instanceof PDO) {
            $this->saveLog('incoming', $this->partnerCctvEndpoint, $rawInput, $result, 'processed');
        }

        return $result;
    }

    /**
     * Dispatch Payload Directly to Partner Surveillance API
     */
    public function dispatchToPartnerCctvApi(array $cctvPayload): array {
        $endpoint = $cctvPayload['endpoint'] ?? $this->partnerCctvEndpoint;
        $params = $cctvPayload['request_parameters'] ?? $cctvPayload;

        if (!function_exists('curl_init')) {
            $mockResponse = [
                'status' => 'simulated',
                'message' => 'cURL not available in current PHP CLI runtime; payload prepared for POST to ' . $endpoint,
                'payload_sent' => $params
            ];
            if ($this->pdo instanceof PDO) {
                $this->saveLog('outgoing_cctv', $endpoint, $params, $mockResponse, 'simulated');
            }
            return $mockResponse;
        }

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
            'X-Partner-Client: AlertaraQC-Incident-System/2.0'
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params, JSON_UNESCAPED_UNICODE));
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);

        $responseRaw = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        $responseDecoded = json_decode($responseRaw, true) ?: ['raw_response' => $responseRaw];

        $status = ($httpCode >= 200 && $httpCode < 300) ? 'success' : 'partner_api_offline_or_failed';

        $dispatchResult = [
            'http_code' => $httpCode,
            'endpoint' => $endpoint,
            'success' => ($httpCode >= 200 && $httpCode < 300),
            'response' => $responseDecoded,
            'curl_error' => $curlErr ?: null
        ];

        if ($this->pdo instanceof PDO) {
            $this->saveLog('outgoing_cctv', $endpoint, $params, $dispatchResult, $status);
        }

        return $dispatchResult;
    }

    /**
     * Standardize fields across Group 3, Group 4, and CCTV inputs
     */
    private function standardize(array $raw, string $source): array {
        $id = $raw['tip_id'] ?? $raw['call_id'] ?? $raw['complaint_id'] ?? $raw['incident_id'] ?? $raw['id'] ?? ('INC-' . date('Ymd') . '-' . rand(1000, 9999));
        $timestamp = $raw['timestamp'] ?? $raw['date_time'] ?? $raw['created_at'] ?? date('Y-m-d H:i:s');
        $location = $raw['location'] ?? $raw['caller_location'] ?? $raw['address'] ?? 'Unspecified Location';
        $description = $raw['tip_description'] ?? $raw['incident_description'] ?? $raw['description'] ?? 'No description provided';
        $name = $raw['complainant_name'] ?? $raw['caller_name'] ?? ($source === 'group_4_tip' ? 'Anonymous Tipster' : 'Unknown Reporter');
        $emergencyLevel = $raw['emergency_level'] ?? $raw['urgency'] ?? $raw['priority'] ?? 'Medium';
        $incidentType = $raw['incident_type'] ?? $raw['category'] ?? $this->inferIncidentType($description);

        return [
            'id' => $id,
            'source' => $source,
            'timestamp' => date('Y-m-d H:i:s', strtotime($timestamp)),
            'location' => $location,
            'reporter_name' => $name,
            'emergency_level' => ucfirst(strtolower($emergencyLevel)),
            'incident_type' => $incidentType,
            'description' => $description,
            'status' => $raw['status'] ?? 'Pending Inspection',
            'evidence' => $raw['attached_evidence'] ?? $raw['evidence'] ?? null,
            'district' => $raw['district'] ?? $this->extractDistrict($location)
        ];
    }

    private function inferIncidentType(string $desc): string {
        $d = strtolower($desc);
        if (preg_match('/assault|fight|weapon|knife|gun|stabbing|shooting|violence/', $d)) return 'Physical Violence / Assault';
        if (preg_match('/theft|robbery|stolen|burglary|break-in|shoplift/', $d)) return 'Theft & Robbery';
        if (preg_match('/cctv|camera|surveillance|footage|video/', $d)) return 'Surveillance / Evidence Review';
        if (preg_match('/fire|smoke|explosion|arson/', $d)) return 'Fire / Emergency Hazard';
        if (preg_match('/noise|disturbance|dispute|argument|quarrel/', $d)) return 'Public Disturbance';
        return 'General Law Enforcement Incident';
    }

    private function extractDistrict(string $location): string {
        if (preg_match('/district\s*([1-6])/i', $location, $m)) {
            return 'District ' . $m[1];
        }
        return 'District 1 (Central)';
    }

    private function analyzeIncident(array $std): array {
        $level = $std['emergency_level'];
        $score = 50;

        if (in_array($level, ['High', 'Critical', 'Emergency'])) {
            $score = 90;
        } elseif ($level === 'Low') {
            $score = 30;
        }

        if (preg_match('/weapon|knife|gun|injured|bleeding|fire/i', $std['description'])) {
            $score = max($score, 95);
            $level = 'Critical';
        }

        $summary = sprintf(
            "Incident %s (%s) reported at %s on %s. Operational urgency is classified as %s (Score: %d/100). Primary issue: %s.",
            $std['id'],
            $std['incident_type'],
            $std['location'],
            $std['timestamp'],
            strtoupper($level),
            $score,
            $std['description']
        );

        $takeaways = [
            "Immediate routing to Group 7 for inspection & field verification.",
            "Coordinates & status pushed to Group 5 Crime Mapping.",
            "Resource availability query dispatched to Group 3 EMS/Police.",
            !empty($std['evidence']) ? "Media evidence attached; CCTV partner query triggered." : "No attached media; requesting nearby CCTV footage."
        ];

        return [
            'summary' => $summary,
            'risk_level' => $level,
            'urgency_score' => $score,
            'takeaways' => $takeaways
        ];
    }

    private function buildGroup7InspectionPayload(array $std, array $analysis): array {
        return [
            'Case Number' => $std['id'],
            'Name' => $std['reporter_name'],
            'Date & Time' => $std['timestamp'],
            'Urgency Level' => $analysis['risk_level'],
            'Incident Type' => $std['incident_type'],
            'Location' => $std['location'],
            'Status' => $std['status']
        ];
    }

    private function buildGroup5CrimeMappingPayload(array $std): array {
        return [
            'Incident ID' => $std['id'],
            'Date & Time' => $std['timestamp'],
            'Case Status' => $std['status'],
            'Location' => $std['location']
        ];
    }

    private function buildGroup3ResourceAllocationPayload(array $std, array $analysis): array {
        return [
            'Officer ID' => 'OFF-' . strtoupper(substr(md5($std['district']), 0, 5)),
            'Availability Status' => ($analysis['risk_level'] === 'Critical' || $analysis['risk_level'] === 'High') ? 'Dispatched / High Priority' : 'On-Duty / Standby',
            'Assigned District' => $std['district']
        ];
    }

    private function buildCctvPartnerPayload(array $std): array {
        $startTime = date('Y-m-d H:i:s', strtotime($std['timestamp']) - 1800);
        $endTime = date('Y-m-d H:i:s', strtotime($std['timestamp']) + 1800);

        return [
            'endpoint' => $this->partnerCctvEndpoint,
            'request_parameters' => [
                'request_id' => 'REQ-CCTV-' . date('Ymd') . '-' . rand(100, 999),
                'incident_id' => $std['id'],
                'location' => $std['location'],
                'timestamp_range' => [
                    'start_time' => $startTime,
                    'end_time' => $endTime
                ],
                'attached_evidence' => $std['evidence'],
                'media_type' => 'video/mp4',
                'action' => 'fetch_surveillance_feed'
            ]
        ];
    }

    private function buildRecommendations(array $std, array $analysis): array {
        return [
            "1. Resource Deployment: Assign unit to " . $std['district'] . " immediately based on risk level (" . $analysis['risk_level'] . ").",
            "2. Inspection Scheduling: Transmit case " . $std['id'] . " to Group 7 for field inspection.",
            "3. Heatmap Sync: Log Incident ID " . $std['id'] . " into Group 5 GIS spatial database.",
            "4. Evidence Retrieval: Submit automated query to Partner CCTV API (" . $this->partnerCctvEndpoint . ") for camera footage around " . $std['location'] . "."
        ];
    }

    /**
     * Process incoming CCTV footage payload from surveillance partner
     */
    public function processIncomingCctvFootage(array $data): array {
        $requestId = trim($data['request_id'] ?? $data['cctv_request_id'] ?? '');
        $incidentId = trim($data['incident_id'] ?? $data['case_id'] ?? '');
        $cctvUrl = trim($data['cctv_url'] ?? $data['video_url'] ?? $data['media_url'] ?? $data['file_path'] ?? '');
        $cameraId = trim($data['camera_id'] ?? $data['camera_code'] ?? 'CAM-SURV-QC');
        $location = trim($data['location'] ?? $data['camera_location'] ?? '');
        $notes = trim($data['notes'] ?? $data['remarks'] ?? $data['description'] ?? '');
        $videoFormat = trim($data['video_format'] ?? 'video/mp4');
        $duration = trim($data['duration'] ?? '');

        if (empty($cctvUrl)) {
            throw new Exception('Missing required cctv_url or video_url field in CCTV footage payload.');
        }

        $recordId = null;
        if ($this->pdo instanceof PDO) {
            $stmt = $this->pdo->prepare("INSERT INTO cctv_footage_received (request_id, incident_id, cctv_url, camera_id, location, video_format, duration, notes, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Received')");
            $stmt->execute([$requestId, $incidentId, $cctvUrl, $cameraId, $location, $videoFormat, $duration, $notes]);
            $recordId = $this->pdo->lastInsertId();

            $this->saveLog('incoming_cctv_footage', 'https://surveillance.alertaraqc.com', $data, [
                'status' => 'success',
                'record_id' => $recordId,
                'message' => 'CCTV footage logged successfully'
            ], 'received');
        }

        return [
            'success' => true,
            'message' => 'CCTV footage payload received and stored successfully.',
            'record_id' => $recordId,
            'request_id' => $requestId,
            'incident_id' => $incidentId,
            'cctv_url' => $cctvUrl,
            'camera_id' => $cameraId,
            'received_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Process incoming resolved tips payload from surveillance partner
     */
    public function processIncomingResolvedTip(array $data): array {
        $tipId = trim($data['tip_id'] ?? $data['id'] ?? ('TIP-' . date('Ymd') . '-' . rand(1000, 9999)));
        $incidentId = trim($data['incident_id'] ?? $data['case_number'] ?? '');
        $incidentType = trim($data['incident_type'] ?? $data['category'] ?? 'Surveillance Tip');
        $title = trim($data['title'] ?? $data['subject'] ?? ('Resolved Tip: ' . $incidentType));
        $description = trim($data['description'] ?? $data['tip_details'] ?? '');
        $location = trim($data['location'] ?? $data['address'] ?? '');
        $district = trim($data['district'] ?? $this->extractDistrict($location));
        $resolvedBy = trim($data['resolved_by'] ?? $data['officer_name'] ?? 'Surveillance Unit');
        $resolutionNotes = trim($data['resolution_notes'] ?? $data['action_taken'] ?? '');
        $evidenceUrl = trim($data['evidence_url'] ?? $data['media_url'] ?? '');
        $resolvedAt = trim($data['resolved_at'] ?? date('Y-m-d H:i:s'));

        if (empty($description) && empty($title)) {
            throw new Exception('Missing tip description or title in resolved tip payload.');
        }

        $recordId = null;
        if ($this->pdo instanceof PDO) {
            $stmt = $this->pdo->prepare("INSERT INTO received_resolved_tips (tip_id, incident_id, incident_type, title, description, location, district, resolved_by, resolution_notes, evidence_url, resolved_at, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Logged')");
            $stmt->execute([$tipId, $incidentId, $incidentType, $title, $description, $location, $district, $resolvedBy, $resolutionNotes, $evidenceUrl, $resolvedAt]);
            $recordId = $this->pdo->lastInsertId();

            // Also check if incidents table exists and insert as an incident record for Incident Logging & Classification module
            try {
                $checkIncidents = $this->pdo->query("SHOW TABLES LIKE 'incidents'");
                if ($checkIncidents && $checkIncidents->rowCount() > 0) {
                    $incStmt = $this->pdo->prepare("INSERT INTO incidents (title, description, incident_type, location, status, reporter_name, created_at) VALUES (?, ?, ?, ?, 'Resolved', ?, NOW())");
                    $incStmt->execute([
                        '[Resolved Tip] ' . $title,
                        "Tip ID: {$tipId}\nResolution Notes: {$resolutionNotes}\n" . $description,
                        $incidentType,
                        $location,
                        $resolvedBy
                    ]);
                }
            } catch (Exception $e) {
                error_log("Notice: Could not mirror resolved tip to main incidents table: " . $e->getMessage());
            }

            $this->saveLog('incoming_resolved_tip', 'https://surveillance.alertaraqc.com', $data, [
                'status' => 'success',
                'record_id' => $recordId,
                'tip_id' => $tipId
            ], 'logged');
        }

        return [
            'success' => true,
            'message' => 'Resolved tip received and classified into Incident Logging module successfully.',
            'record_id' => $recordId,
            'tip_id' => $tipId,
            'incident_id' => $incidentId,
            'received_at' => date('Y-m-d H:i:s')
        ];
    }

    private function saveLog(string $direction, string $targetUrl, array $payload, array $response, string $status): void {
        try {
            $stmt = $this->pdo->prepare("INSERT INTO external_integration_log (direction, target_url, payload, response_body, status) VALUES (?, ?, ?, ?, ?)");
            $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $responseJson = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $stmt->execute([$direction, $targetUrl, $payloadJson, $responseJson, $status]);
        } catch (Exception $e) {
            error_log("Failed to save integration log: " . $e->getMessage());
        }
    }
}
