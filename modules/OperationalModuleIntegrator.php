<?php
/**
 * Operational Module Integrator & Assistant Engine - Integration Ready Layer
 */
require_once __DIR__ . '/../config/integration_config.php';

class OperationalModuleIntegrator {
    private $pdo;
    private $partnerCctvEndpoint;
    private $timeout = 15;

    public function __construct($pdo = null) {
        $this->pdo = $pdo;
        $this->partnerCctvEndpoint = getIntegrationSetting('cctv_request_api_url', 'https://surveillance.alertaraqc.com/api/cctv_requests_receive.php');
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

            $this->pdo->exec("CREATE TABLE IF NOT EXISTS received_accident_reports (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                report_id VARCHAR(100) NULL,
                ticket_number VARCHAR(100) NULL,
                incident_type VARCHAR(150) DEFAULT 'Traffic Accident / Violation',
                violator_name VARCHAR(255) NULL,
                vehicle_details VARCHAR(255) NULL,
                plate_number VARCHAR(50) NULL,
                violation_type VARCHAR(255) NULL,
                fine_amount DECIMAL(10, 2) DEFAULT 0.00,
                severity_level VARCHAR(50) DEFAULT 'Medium',
                collision_type VARCHAR(100) NULL,
                location VARCHAR(255) NULL,
                barangay VARCHAR(100) NULL,
                district VARCHAR(100) NULL,
                narrative LONGTEXT NULL,
                casualties_count INT DEFAULT 0,
                property_damage_estimate DECIMAL(12, 2) DEFAULT 0.00,
                reporting_officer VARCHAR(150) NULL,
                incident_date_time DATETIME NULL,
                evidence_media TEXT NULL,
                status VARCHAR(50) DEFAULT 'Logged & Classified',
                raw_payload LONGTEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_report_id (report_id),
                INDEX idx_ticket_number (ticket_number)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $this->pdo->exec("CREATE TABLE IF NOT EXISTS received_emergency_calls (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                call_id VARCHAR(100) NULL,
                call_timestamp DATETIME NULL,
                caller_name VARCHAR(255) NULL,
                caller_location VARCHAR(255) NULL,
                emergency_level VARCHAR(50) DEFAULT 'High',
                incident_description LONGTEXT NULL,
                incident_type VARCHAR(150) DEFAULT 'Emergency Call',
                district VARCHAR(100) NULL,
                case_no VARCHAR(100) NULL,
                status VARCHAR(50) DEFAULT 'Logged & Dispatched',
                raw_payload LONGTEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_call_id (call_id),
                INDEX idx_case_no (case_no)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $this->pdo->exec("CREATE TABLE IF NOT EXISTS received_inspection_documents (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                document_id VARCHAR(100) NULL,
                request_id VARCHAR(100) NULL,
                case_no VARCHAR(100) NULL,
                document_type VARCHAR(255) NULL,
                business_or_location VARCHAR(255) NULL,
                inspector_name VARCHAR(150) NULL,
                inspection_status VARCHAR(100) DEFAULT 'Compliant & Approved',
                findings LONGTEXT NULL,
                compliance_score VARCHAR(50) NULL,
                certificate_url TEXT NULL,
                evidence_urls LONGTEXT NULL,
                inspection_date DATE NULL,
                received_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_doc_id (document_id),
                INDEX idx_case_no (case_no),
                INDEX idx_request_id (request_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $this->pdo->exec("CREATE TABLE IF NOT EXISTS received_campaigns (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                campaign_id VARCHAR(100) NULL,
                title VARCHAR(255) NOT NULL,
                description LONGTEXT NULL,
                category VARCHAR(100) DEFAULT 'General',
                geographical_scope VARCHAR(100) DEFAULT 'Barangay',
                start_date DATETIME NULL,
                end_date DATETIME NULL,
                status VARCHAR(50) DEFAULT 'Active',
                image_url TEXT NULL,
                raw_json LONGTEXT NULL,
                received_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_campaign_id (campaign_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $this->pdo->exec("CREATE TABLE IF NOT EXISTS received_community_complaints (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                complaint_id VARCHAR(100) NULL,
                complainant_name VARCHAR(255) NULL,
                incident_type VARCHAR(150) NULL,
                date_time DATETIME NULL,
                location VARCHAR(255) NULL,
                description LONGTEXT NULL,
                status VARCHAR(50) DEFAULT 'Pending',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_complaint_id (complaint_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            // Ensure modern CCTV request table columns exist
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS cctv_requests (
                id INT(11) NOT NULL AUTO_INCREMENT,
                request_id_code VARCHAR(100) DEFAULT NULL,
                requested_by INT(11) DEFAULT NULL,
                requesting_agency VARCHAR(255) DEFAULT 'Digital Blotter System',
                contact_person VARCHAR(255) DEFAULT NULL,
                position_designation VARCHAR(255) DEFAULT NULL,
                contact_number VARCHAR(100) DEFAULT NULL,
                email_address VARCHAR(255) DEFAULT NULL,
                office_unit VARCHAR(255) DEFAULT NULL,
                case_reference VARCHAR(255) DEFAULT NULL,
                related_complaint_id VARCHAR(255) DEFAULT NULL,
                legal_basis VARCHAR(255) DEFAULT 'Law enforcement request',
                purpose_reason TEXT DEFAULT NULL,
                supporting_document VARCHAR(255) DEFAULT NULL,
                incident_location VARCHAR(255) DEFAULT NULL,
                camera_id VARCHAR(255) DEFAULT NULL,
                location_description VARCHAR(255) DEFAULT NULL,
                incident_date DATE DEFAULT NULL,
                incident_time TIME DEFAULT NULL,
                incident_type VARCHAR(100) DEFAULT 'Footage',
                footage_start_time TIME DEFAULT NULL,
                footage_end_time TIME DEFAULT NULL,
                incident_description TEXT DEFAULT NULL,
                delivery_method VARCHAR(100) DEFAULT 'Secure download link',
                official_use_confirmed TINYINT(1) DEFAULT 1,
                privacy_terms_agreed TINYINT(1) DEFAULT 1,
                priority VARCHAR(50) NOT NULL DEFAULT 'Normal',
                reason TEXT DEFAULT NULL,
                additional_details TEXT DEFAULT NULL,
                monitoring_office VARCHAR(100) DEFAULT NULL,
                monitoring_notes TEXT DEFAULT NULL,
                review_notes TEXT DEFAULT NULL,
                rejection_reason TEXT DEFAULT NULL,
                fulfillment_notes TEXT DEFAULT NULL,
                acknowledged_at DATETIME DEFAULT NULL,
                acknowledged_by VARCHAR(150) DEFAULT NULL,
                acknowledgement_notes TEXT DEFAULT NULL,
                assigned_camera_operator VARCHAR(150) DEFAULT NULL,
                status VARCHAR(50) NOT NULL DEFAULT 'Pending',
                requested_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT NULL,
                PRIMARY KEY (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $cctvColChecks = [
                'request_id_code' => 'VARCHAR(100) DEFAULT NULL',
                'requesting_agency' => 'VARCHAR(255) DEFAULT "Digital Blotter System"',
                'contact_person' => 'VARCHAR(255) DEFAULT NULL',
                'position_designation' => 'VARCHAR(255) DEFAULT NULL',
                'contact_number' => 'VARCHAR(100) DEFAULT NULL',
                'email_address' => 'VARCHAR(255) DEFAULT NULL',
                'office_unit' => 'VARCHAR(255) DEFAULT NULL',
                'case_reference' => 'VARCHAR(255) DEFAULT NULL',
                'related_complaint_id' => 'VARCHAR(255) DEFAULT NULL',
                'legal_basis' => 'VARCHAR(255) DEFAULT "Law enforcement request"',
                'purpose_reason' => 'TEXT DEFAULT NULL',
                'supporting_document' => 'VARCHAR(255) DEFAULT NULL',
                'incident_location' => 'VARCHAR(255) DEFAULT NULL',
                'camera_id' => 'VARCHAR(255) DEFAULT NULL',
                'location_description' => 'VARCHAR(255) DEFAULT NULL',
                'incident_date' => 'DATE DEFAULT NULL',
                'incident_time' => 'TIME DEFAULT NULL',
                'incident_type' => 'VARCHAR(100) DEFAULT "Footage"',
                'footage_start_time' => 'TIME DEFAULT NULL',
                'footage_end_time' => 'TIME DEFAULT NULL',
                'incident_description' => 'TEXT DEFAULT NULL',
                'delivery_method' => 'VARCHAR(100) DEFAULT "Secure download link"',
                'official_use_confirmed' => 'TINYINT(1) DEFAULT 1',
                'privacy_terms_agreed' => 'TINYINT(1) DEFAULT 1',
                'priority' => 'VARCHAR(50) NOT NULL DEFAULT "Normal"',
                'reason' => 'TEXT DEFAULT NULL',
                'additional_details' => 'TEXT DEFAULT NULL',
                'monitoring_office' => 'VARCHAR(100) DEFAULT NULL',
                'monitoring_notes' => 'TEXT DEFAULT NULL',
                'review_notes' => 'TEXT DEFAULT NULL',
                'rejection_reason' => 'TEXT DEFAULT NULL',
                'fulfillment_notes' => 'TEXT DEFAULT NULL'
            ];

            foreach ($cctvColChecks as $cCol => $cDef) {
                try {
                    $chk = $this->pdo->query("SHOW COLUMNS FROM cctv_requests LIKE '{$cCol}'");
                    if (!$chk || $chk->rowCount() === 0) {
                        $this->pdo->exec("ALTER TABLE cctv_requests ADD COLUMN {$cCol} {$cDef}");
                    }
                } catch (Exception $ex) {}
            }
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
     * Decode and save base64 image data to local uploads/external_media folder if present
     */
    public function saveBase64MediaIfPresent(string $mediaInput): string {
        $trimmed = trim($mediaInput);
        if (preg_match('/^data:image\/(\w+);base64,(.+)$/si', $trimmed, $matches)) {
            $ext = strtolower($matches[1]);
            if ($ext === 'jpeg') $ext = 'jpg';
            $data = base64_decode($matches[2]);
            if ($data !== false) {
                $dir = __DIR__ . '/../uploads/external_media/';
                if (!is_dir($dir)) {
                    @mkdir($dir, 0755, true);
                }
                $filename = 'EXT_IMG_' . date('Ymd_His') . '_' . rand(1000, 9999) . '.' . $ext;
                if (@file_put_contents($dir . $filename, $data) !== false) {
                    return 'uploads/external_media/' . $filename;
                }
            }
        }
        return $trimmed;
    }

    /**
     * Normalize incoming media (URLs, arrays, comma-separated lists, base64) into JSON array or string
     */
    public function normalizeMediaUrls($rawMedia): string {
        if (empty($rawMedia)) {
            return '';
        }

        $list = [];
        if (is_array($rawMedia)) {
            foreach ($rawMedia as $item) {
                if (is_string($item) && trim($item) !== '') {
                    $list[] = $this->saveBase64MediaIfPresent($item);
                } elseif (is_array($item)) {
                    $val = $item['url'] ?? $item['file_path'] ?? $item['src'] ?? $item['photo'] ?? '';
                    if (!empty($val)) {
                        $list[] = $this->saveBase64MediaIfPresent($val);
                    }
                }
            }
        } elseif (is_string($rawMedia)) {
            $decoded = json_decode($rawMedia, true);
            if (is_array($decoded)) {
                return $this->normalizeMediaUrls($decoded);
            }
            if (strpos($rawMedia, ',') !== false && !preg_match('/^data:image/i', $rawMedia)) {
                $parts = explode(',', $rawMedia);
                foreach ($parts as $p) {
                    if (trim($p) !== '') {
                        $list[] = $this->saveBase64MediaIfPresent(trim($p));
                    }
                }
            } else {
                $list[] = $this->saveBase64MediaIfPresent($rawMedia);
            }
        }

        if (empty($list)) {
            return '';
        }

        if (count($list) === 1) {
            return $list[0];
        }

        return json_encode(array_values(array_unique($list)), JSON_UNESCAPED_SLASHES);
    }

    /**
     * Dispatch Payload Directly to Partner Surveillance API (Marto's Group / policy.alertaraqc.com)
     */
    public function dispatchToPartnerCctvApi(array $cctvPayload): array {
        $endpoint = getIntegrationSetting('cctv_request_api_url', $this->partnerCctvEndpoint);
        $raw = $cctvPayload['request_parameters'] ?? $cctvPayload;

        // Standardize top-level fields for Marto CCTV API / Policy endpoint
        $reqDate = $raw['incident_date'] ?? date('Y-m-d');
        $startTime = $raw['footage_start_time'] ?? ($raw['timestamp_range']['start_time'] ?? '00:00:00');
        $endTime = $raw['footage_end_time'] ?? ($raw['timestamp_range']['end_time'] ?? '23:59:59');

        // Extract HH:mm
        if (strlen($startTime) > 5 && strpos($startTime, ':') !== false) {
            $parts = explode(' ', $startTime);
            $timePart = end($parts);
            $startTime = substr($timePart, 0, 5);
        }
        if (strlen($endTime) > 5 && strpos($endTime, ':') !== false) {
            $parts = explode(' ', $endTime);
            $timePart = end($parts);
            $endTime = substr($timePart, 0, 5);
        }

        $params = array_merge($raw, [
            'request_id' => $raw['request_id'] ?? ('CCTV-REQ-' . date('Y') . '-' . rand(1000, 9999)),
            'agency' => $raw['requesting_agency'] ?? ($raw['agency'] ?? 'Digital Blotter System'),
            'requesting_agency' => $raw['requesting_agency'] ?? ($raw['agency'] ?? 'Digital Blotter System'),
            'contact_person' => $raw['contact_person'] ?? ($raw['contact'] ?? 'Admin Requester'),
            'contact_number' => $raw['contact_number'] ?? ($raw['contact_no'] ?? ''),
            'email_address' => $raw['email_address'] ?? ($raw['email'] ?? ''),
            'case_reference' => $raw['case_reference'] ?? ($raw['case_ref'] ?? ''),
            'legal_basis' => $raw['legal_basis'] ?? 'Law enforcement request',
            'location' => $raw['location'] ?? ($raw['incident_location'] ?? 'Quezon City'),
            'incident_location' => $raw['incident_location'] ?? ($raw['location'] ?? 'Quezon City'),
            'camera' => $raw['camera'] ?? ($raw['camera_id'] ?? 'CAM-001 — Main Entrance Camera'),
            'incident_date' => $reqDate,
            'footage_start_time' => $startTime,
            'footage_end_time' => $endTime,
            'footage_window' => [
                'date' => $reqDate,
                'start' => $startTime,
                'end' => $endTime
            ],
            'purpose' => $raw['purpose'] ?? ($raw['purpose_reason'] ?? ($raw['reason'] ?? 'Incident footage verification')),
            'incident_description' => $raw['incident_description'] ?? ($raw['description'] ?? ($raw['reason'] ?? 'Footage request verification')),
            'delivery_method' => $raw['delivery_method'] ?? 'Secure download link'
        ]);

        $result = dispatchPayloadToEndpoint($endpoint, $params, [], $this->timeout);
        $status = $result['success'] ? 'success' : 'partner_api_offline_or_failed';

        if ($this->pdo instanceof PDO) {
            $this->saveLog('outgoing_cctv', $endpoint, $params, $result, $status);
        }

        return $result;
    }

    /**
     * Dispatch Payload to Group 7 Inspection Scheduling API
     */
    public function dispatchToGroup7InspectionApi(array $g7Payload): array {
        $endpoint = getIntegrationSetting('group7_inspection_api_url', 'https://inspection.alertaraqc.com/api/schedule_inspection.php');
        $result = dispatchPayloadToEndpoint($endpoint, $g7Payload, [], $this->timeout);
        $status = $result['success'] ? 'success' : 'failed';

        if ($this->pdo instanceof PDO) {
            $this->saveLog('outgoing_group7_inspection', $endpoint, $g7Payload, $result, $status);
        }

        return $result;
    }

    /**
     * Dispatch Payload to Group 5 Crime Mapping & GIS API
     */
    public function dispatchToGroup5CrimeMapApi(array $g5Payload): array {
        $endpoint = getIntegrationSetting('group5_crime_map_api_url', 'https://crimemap.alertaraqc.com/api/update_heatmap.php');
        $result = dispatchPayloadToEndpoint($endpoint, $g5Payload, [], $this->timeout);
        $status = $result['success'] ? 'success' : 'failed';

        if ($this->pdo instanceof PDO) {
            $this->saveLog('outgoing_group5_crimemap', $endpoint, $g5Payload, $result, $status);
        }

        return $result;
    }

    /**
     * Dispatch Payload to Group 3 Emergency EMS & Resource Allocation API
     */
    public function dispatchToGroup3ResourceApi(array $g3Payload): array {
        $endpoint = getIntegrationSetting('group3_resource_api_url', 'https://dispatch.alertaraqc.com/api/assign_officer.php');
        $result = dispatchPayloadToEndpoint($endpoint, $g3Payload, [], $this->timeout);
        $status = $result['success'] ? 'success' : 'failed';

        if ($this->pdo instanceof PDO) {
            $this->saveLog('outgoing_group3_resource', $endpoint, $g3Payload, $result, $status);
        }

        return $result;
    }

    /**
     * Dispatch payloads to all connected external modules at once
     */
    public function dispatchToAllConnectedModules(array $modulePayloads): array {
        return [
            'cctv_partner' => $this->dispatchToPartnerCctvApi($modulePayloads['cctv_partner_surveillance_api'] ?? []),
            'group7_inspection' => $this->dispatchToGroup7InspectionApi($modulePayloads['group_7_inspection_scheduling'] ?? []),
            'group5_crime_map' => $this->dispatchToGroup5CrimeMapApi($modulePayloads['group_5_crime_mapping'] ?? []),
            'group3_resource' => $this->dispatchToGroup3ResourceApi($modulePayloads['group_3_resource_allocation'] ?? [])
        ];
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
        $rawCctv = $data['cctv_url'] ?? $data['video_url'] ?? $data['media_url'] ?? $data['file_path'] ?? $data['footage_url'] ?? $data['photos'] ?? '';
        $cctvUrl = $this->normalizeMediaUrls($rawCctv);
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
        $rawEvidence = $data['evidence_url'] ?? $data['media_url'] ?? $data['photos'] ?? $data['images'] ?? $data['attached_evidence'] ?? '';
        $evidenceUrl = $this->normalizeMediaUrls($rawEvidence);
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
                    $caseNo = 'TIP-' . date('Ymd') . '-' . substr(strtoupper(bin2hex(random_bytes(2))), 0, 4);
                    $narrative = "[Resolved Tip: {$title}]\nTip ID: {$tipId}\nResolution Notes: {$resolutionNotes}\n" . $description;
                    $incStmt = $this->pdo->prepare("INSERT INTO incidents (case_no, narrative, incident_type, location, status, reporter_name, incident_date, created_at) VALUES (?, ?, 'Other', ?, 'Resolved', ?, CURDATE(), NOW())");
                    $incStmt->execute([
                        $caseNo,
                        $narrative,
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

    /**
     * Process incoming Inspection Document / Report payload from inspection.alertaraqc.com
     */
    public function processIncomingInspectionDocument(array $data): array {
        $requestId = trim($data['request_id'] ?? $data['document_request_id'] ?? ('REQ-DOC-' . date('Ymd') . '-' . rand(100, 999)));
        $documentId = trim($data['document_id'] ?? $data['doc_id'] ?? $data['certificate_id'] ?? ('DOC-' . date('Y') . '-' . rand(1000, 9999)));
        $caseNo = trim($data['case_no'] ?? $data['blotter_no'] ?? $data['incident_id'] ?? '');
        $documentType = trim($data['document_type'] ?? $data['type'] ?? 'Inspection Report');
        $location = trim($data['business_or_location'] ?? $data['location'] ?? $data['establishment_name'] ?? '');
        $inspector = trim($data['inspector_name'] ?? $data['officer'] ?? 'Field Inspector');
        $status = trim($data['inspection_status'] ?? $data['status'] ?? 'Completed');
        $findings = trim($data['findings'] ?? $data['notes'] ?? $data['remarks'] ?? $data['description'] ?? '');
        $score = trim($data['compliance_score'] ?? $data['score'] ?? 'N/A');
        
        // Certificate link/image
        $certRaw = trim($data['certificate_url'] ?? $data['certificate'] ?? $data['cert_url'] ?? $data['document_url'] ?? $data['pdf_url'] ?? $data['clearance_url'] ?? '');
        $certUrl = $this->saveBase64MediaIfPresent($certRaw);

        // Inspection evidence photos / images
        $evidenceRaw = $data['evidence_urls'] ?? $data['photos'] ?? $data['images'] ?? $data['pictures'] ?? $data['evidence_photos'] ?? $data['media_url'] ?? $data['evidence_media'] ?? $data['attached_photos'] ?? $data['attachments'] ?? $data['scene_photos'] ?? '';
        $evidenceUrls = $this->normalizeMediaUrls($evidenceRaw);
        
        $inspDate = trim($data['inspection_date'] ?? date('Y-m-d'));

        if (empty($findings) && empty($documentType)) {
            throw new Exception('Missing required document findings or document_type in inspection payload.');
        }

        $recordId = null;
        if ($this->pdo instanceof PDO) {
            $stmt = $this->pdo->prepare("INSERT INTO received_inspection_documents 
                (request_id, document_id, case_no, document_type, business_or_location, inspector_name, inspection_status, findings, compliance_score, certificate_url, evidence_urls, inspection_date, raw_payload)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $requestId, $documentId, $caseNo, $documentType, $location, $inspector, $status, $findings, $score, $certUrl, $evidenceUrls, $inspDate, json_encode($data, JSON_UNESCAPED_UNICODE)
            ]);
            $recordId = $this->pdo->lastInsertId();

            $this->saveLog('incoming_inspection_document', 'https://inspection.alertaraqc.com', $data, [
                'status' => 'success',
                'record_id' => $recordId,
                'document_id' => $documentId
            ], 'received');
        }

        return [
            'success' => true,
            'message' => 'Inspection document payload received and stored in Law Enforcement portal successfully.',
            'record_id' => $recordId,
            'request_id' => $requestId,
            'document_id' => $documentId,
            'case_no' => $caseNo,
            'document_type' => $documentType,
            'certificate_url' => $certUrl,
            'evidence_urls' => $evidenceUrls,
            'received_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Dispatch Payload to Public Safety Campaign API (Group 1)
     */
    public function dispatchToCampaignApi(array $campaignPayload): array {
        $endpoint = getIntegrationSetting('campaign_api_url', 'https://campaign.alertaraqc.com/api/v1/campaigns/public');
        $result = dispatchPayloadToEndpoint($endpoint, $campaignPayload, [], $this->timeout);
        $status = $result['success'] ? 'success' : 'failed';

        if ($this->pdo instanceof PDO) {
            $this->saveLog('outgoing_campaign', $endpoint, $campaignPayload, $result, $status);
        }

        return $result;
    }

    /**
     * Fetch Live Campaigns from https://campaign.alertaraqc.com/api/v1/campaigns/public
     */
    public function fetchPublicCampaigns(): array {
        $endpoint = getIntegrationSetting('campaign_api_url', 'https://campaign.alertaraqc.com/api/v1/campaigns/public');

        if (!function_exists('curl_init')) {
            return ['success' => false, 'message' => 'cURL PHP extension required'];
        }

        $secret = getIntegrationSetting('external_api_secret', '');
        $effectiveSecret = !empty($secret) ? $secret : 'ALERTARA-EMERGENCY-2026';
        $headers = [
            'Accept: application/json',
            'X-Partner-Client: AlertaraQC-Incident-System/2.0',
            'X-API-KEY: ' . $effectiveSecret,
            'X-API-Key: ' . $effectiveSecret,
            'Authorization: Bearer ' . $effectiveSecret,
            'X-External-Secret: ' . $effectiveSecret
        ];

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $responseRaw = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        $decoded = json_decode($responseRaw, true);
        $campaigns = $decoded['campaigns'] ?? (is_array($decoded) && isset($decoded[0]) ? $decoded : []);

        if ($this->pdo instanceof PDO && !empty($campaigns)) {
            try {
                $stmt = $this->pdo->prepare("INSERT INTO received_campaigns (campaign_id, title, description, category, geographical_scope, start_date, end_date, status, image_url, raw_json) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE title=VALUES(title), description=VALUES(description), status=VALUES(status)");
                foreach ($campaigns as $c) {
                    $stmt->execute([
                        $c['id'] ?? null,
                        $c['title'] ?? 'Public Safety Campaign',
                        $c['description'] ?? '',
                        $c['category'] ?? 'General',
                        $c['geographical_scope'] ?? 'Barangay',
                        $c['start_date'] ?? null,
                        $c['end_date'] ?? null,
                        $c['status'] ?? 'Active',
                        $c['image_url'] ?? null,
                        json_encode($c, JSON_UNESCAPED_UNICODE)
                    ]);
                }
            } catch (Exception $e) {
                error_log("Campaign sync error: " . $e->getMessage());
            }

            $this->saveLog(
                'incoming_campaigns_fetch', 
                $endpoint, 
                ['method' => 'GET', 'client' => 'AlertaraQC-Incident-System/2.0'], 
                [
                    'status' => 'success', 
                    'http_code' => $httpCode, 
                    'count' => count($campaigns), 
                    'campaigns' => $campaigns
                ], 
                'success'
            );
        }

        return [
            'success' => ($httpCode >= 200 && $httpCode < 300),
            'http_code' => $httpCode,
            'campaign_count' => count($campaigns),
            'campaigns' => $campaigns,
            'curl_error' => $curlErr ?: null
        ];
    }

    /**
     * Process incoming Community Complaint from Group 4 (Community Complaint Logging & Resolution)
     */
    public function processIncomingCommunityComplaint(array $data): array {
        $complaintId = trim($data['complaint_id'] ?? $data['id'] ?? ('COMP-' . date('Ymd') . '-' . rand(1000, 9999)));
        $name = trim($data['complainant_name'] ?? $data['reporter_name'] ?? 'Resident Complainant');
        $type = trim($data['incident_type'] ?? $data['category'] ?? 'Community Dispute');
        $dateTime = trim($data['date_time'] ?? $data['timestamp'] ?? date('Y-m-d H:i:s'));
        $location = trim($data['location'] ?? $data['address'] ?? 'Quezon City');
        $description = trim($data['description'] ?? $data['complaint_details'] ?? '');
        $status = trim($data['status'] ?? 'Pending');

        $recordId = null;
        if ($this->pdo instanceof PDO) {
            $stmt = $this->pdo->prepare("INSERT INTO received_community_complaints (complaint_id, complainant_name, incident_type, date_time, location, description, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$complaintId, $name, $type, $dateTime, $location, $description, $status]);
            $recordId = $this->pdo->lastInsertId();

            // Also mirror to Digital Blotter module table if existing
            try {
                $checkBlotter = $this->pdo->query("SHOW TABLES LIKE 'blotters'");
                if ($checkBlotter && $checkBlotter->rowCount() > 0) {
                    $bStmt = $this->pdo->prepare("INSERT INTO blotters (blotter_no, complainant_name, incident_type, location, incident_date, incident_time, description, priority, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                    $bStmt->execute([
                        'BLOTTER-' . date('Y') . '-' . rand(1000, 9999),
                        $name,
                        $type,
                        $location,
                        date('Y-m-d'),
                        date('H:i:s'),
                        "[Group 4 Complaint #{$complaintId}] " . $description,
                        'Medium',
                        'Pending'
                    ]);
                }
            } catch (Exception $e) {
                error_log("Notice: Could not mirror community complaint to blotters table: " . $e->getMessage());
            }

            $this->saveLog('incoming_community_complaint', 'Group 4 System', $data, ['record_id' => $recordId], 'received');
        }

        return [
            'success' => true,
            'message' => 'Community complaint received and logged into Digital Blotter module.',
            'record_id' => $recordId,
            'complaint_id' => $complaintId
        ];
    }

    /**
     * Process incoming Emergency Call & Incident from External Emergency Response System
     * Fields supported: Call ID, Timestamp, Caller Location, Caller, Location, Emergency Level, Incident Description
     */
    public function processIncomingEmergencyCall(array $data): array {
        $callId = trim($data['Call ID'] ?? $data['call_id'] ?? $data['callId'] ?? $data['CallId'] ?? $data['id'] ?? ('CALL-' . date('Ymd') . '-' . rand(1000, 9999)));
        $timestamp = trim($data['Timestamp'] ?? $data['timestamp'] ?? $data['date_time'] ?? $data['dateTime'] ?? $data['call_time'] ?? date('Y-m-d H:i:s'));
        $caller = trim($data['Caller'] ?? $data['caller'] ?? $data['caller_name'] ?? $data['name'] ?? $data['complainant_name'] ?? $data['reporter_name'] ?? 'Emergency Dispatch Caller');
        $location = trim($data['Caller Location'] ?? $data['caller_location'] ?? $data['Location'] ?? $data['location'] ?? $data['address'] ?? 'Quezon City');
        $emergencyLevel = ucfirst(strtolower(trim($data['Emergency Level'] ?? $data['emergency_level'] ?? $data['EmergencyLevel'] ?? $data['urgency'] ?? $data['priority'] ?? $data['severity'] ?? 'High')));
        $description = trim($data['Incident Description'] ?? $data['incident_description'] ?? $data['IncidentDescription'] ?? $data['description'] ?? $data['details'] ?? $data['narrative'] ?? '');

        if (empty($description) && empty($location)) {
            throw new Exception('Missing incident description or caller location in incoming emergency incident payload.');
        }

        $formattedTime = date('Y-m-d H:i:s', strtotime($timestamp));
        if ($formattedTime === false || $formattedTime === '1970-01-01 00:00:00') {
            $formattedTime = date('Y-m-d H:i:s');
        }

        $incidentType = $this->inferIncidentType($description);
        $district = $this->extractDistrict($location);

        $recordId = null;
        $caseNo = null;

        // Emergency Response Unit assignment based on Emergency Level and Incident Type
        $isCritical = in_array($emergencyLevel, ['Critical', 'High']);
        $priorityCode = $isCritical ? 'CODE 1 - IMMEDIATE EMERGENCY RESPONSE' : ($emergencyLevel === 'Medium' ? 'CODE 2 - URGENT DISPATCH' : 'CODE 3 - STANDARD PATROL');
        
        $assignedUnit = 'Quezon City Emergency Response Unit (Station 4 Patrol & BCPC Quick Response)';
        $descLower = strtolower($description);
        if (strpos($descLower, 'medic') !== false || strpos($descLower, 'injur') !== false || strpos($descLower, 'hospital') !== false || strpos($descLower, 'blood') !== false) {
            $assignedUnit = 'EMS Ambulance & Paramedics Response Team + Police Escort';
        } elseif (strpos($descLower, 'fire') !== false || strpos($descLower, 'burn') !== false || strpos($descLower, 'smoke') !== false) {
            $assignedUnit = 'BFP Fire & Rescue Brigade + Police Traffic Control';
        }

        if ($this->pdo instanceof PDO) {
            $caseNo = 'EMR-' . date('Ymd') . '-' . substr(strtoupper(bin2hex(random_bytes(2))), 0, 4);

            $stmt = $this->pdo->prepare("INSERT INTO received_emergency_calls 
                (call_id, call_timestamp, caller_name, caller_location, emergency_level, incident_description, incident_type, district, case_no, status, raw_payload) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Dispatched to Emergency Response', ?)");
            $stmt->execute([
                $callId,
                $formattedTime,
                $caller,
                $location,
                $emergencyLevel,
                $description,
                $incidentType,
                $district,
                $caseNo,
                json_encode($data, JSON_UNESCAPED_UNICODE)
            ]);
            $recordId = $this->pdo->lastInsertId();

            // Automatically mirror into central incidents table for Law Enforcement & Incident Logging module
            try {
                $checkIncidents = $this->pdo->query("SHOW TABLES LIKE 'incidents'");
                if ($checkIncidents && $checkIncidents->rowCount() > 0) {
                    $fullNarrative = "[External Emergency Response Call #{$callId}]\n"
                        . "Emergency Level: {$emergencyLevel} ({$priorityCode})\n"
                        . "Caller Location: {$location}\n"
                        . "Assigned First Responders: {$assignedUnit}\n"
                        . "Incident Details: " . $description;

                    $urgency = in_array($emergencyLevel, ['Critical', 'High', 'Medium', 'Low']) ? $emergencyLevel : 'High';

                    $incStmt = $this->pdo->prepare("INSERT INTO incidents (case_no, narrative, incident_type, incident_subtype, auto_classification, urgency_level, location, status, reporter_name, incident_date, created_at) VALUES (?, ?, 'Other', 'Emergency Call', 'External Emergency Response System', ?, ?, 'Submitted', ?, ?, NOW())");
                    $incStmt->execute([
                        $caseNo,
                        $fullNarrative,
                        $urgency,
                        $location,
                        $caller,
                        date('Y-m-d', strtotime($formattedTime))
                    ]);
                }
            } catch (Exception $e) {
                error_log("Notice: Could not mirror emergency call to incidents table: " . $e->getMessage());
            }

            // Save integration log
            $this->saveLog('incoming_emergency_call', 'External Emergency Response System', $data, [
                'status' => 'success',
                'record_id' => $recordId,
                'call_id' => $callId,
                'case_no' => $caseNo,
                'emergency_response' => [
                    'dispatch_status' => 'Dispatched - En Route',
                    'assigned_unit' => $assignedUnit,
                    'priority_code' => $priorityCode
                ]
            ], 'logged_and_dispatched');
        }

        return [
            'success' => true,
            'message' => 'Incident successfully received and integrated with Emergency Response & Law Enforcement System.',
            'record_id' => $recordId,
            'call_id' => $callId,
            'case_no' => $caseNo,
            'caller_location' => $location,
            'emergency_level' => $emergencyLevel,
            'incident_type' => $incidentType,
            'timestamp' => $formattedTime,
            'emergency_response' => [
                'dispatch_status' => 'Active Response Initiated / Unit Dispatched',
                'priority_code' => $priorityCode,
                'assigned_unit' => $assignedUnit,
                'estimated_arrival' => $isCritical ? '4-7 minutes' : '8-12 minutes',
                'police_district' => $district
            ],
            'received_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Process incoming CCTV footage request from Marto's Surveillance Group or Partner Agencies
     */
    public function processIncomingCctvRequest(array $data): array {
        $agency = trim($data['requesting_agency'] ?? $data['agency'] ?? $data['Requesting Agency'] ?? 'Digital Blotter System');
        $contactPerson = trim($data['contact_person'] ?? $data['contact'] ?? $data['Contact Person'] ?? 'Admin Requester');
        $position = trim($data['position_designation'] ?? $data['position'] ?? $data['Position'] ?? '');
        $contactNumber = trim($data['contact_number'] ?? $data['contact_no'] ?? $data['Contact Number'] ?? '');
        $email = trim($data['email_address'] ?? $data['email'] ?? $data['Email'] ?? '');
        $officeUnit = trim($data['office_unit'] ?? $data['office'] ?? $data['Office / Unit'] ?? '');
        $caseRef = trim($data['case_reference'] ?? $data['case_ref'] ?? $data['Case Reference'] ?? '');
        $complaintId = trim($data['related_complaint_id'] ?? $data['complaint_id'] ?? $data['Complaint ID'] ?? '');
        $legalBasis = trim($data['legal_basis'] ?? $data['Legal Basis'] ?? 'Law enforcement request');
        $purpose = trim($data['purpose_reason'] ?? $data['purpose'] ?? $data['reason'] ?? $data['Purpose'] ?? '');
        $incidentLoc = trim($data['incident_location'] ?? $data['location'] ?? $data['Incident Location'] ?? 'Quezon City');
        $camera = trim($data['camera_id'] ?? $data['camera'] ?? $data['Camera'] ?? 'CAM-001 — Main Entrance Camera');
        $locDesc = trim($data['location_description'] ?? $data['Location Description'] ?? '');
        $incidentDate = trim($data['incident_date'] ?? $data['Incident Date'] ?? date('Y-m-d'));
        $incidentType = trim($data['incident_type'] ?? $data['Incident Type'] ?? 'Footage');
        $startTime = trim($data['footage_start_time'] ?? $data['start_time'] ?? $data['Footage Start Time'] ?? '00:00:00');
        $endTime = trim($data['footage_end_time'] ?? $data['end_time'] ?? $data['Footage End Time'] ?? '23:59:59');
        $incidentDesc = trim($data['incident_description'] ?? $data['description'] ?? $data['Incident Description'] ?? '');
        $delivery = trim($data['delivery_method'] ?? $data['Delivery Method'] ?? 'Secure download link');

        if (empty($purpose) && empty($incidentDesc)) {
            throw new Exception('Missing purpose or incident description in CCTV footage request payload.');
        }

        $recordId = null;
        $reqCode = null;

        if ($this->pdo instanceof PDO) {
            $reqCode = 'CCTV-REQ-' . date('Y') . '-' . rand(1000, 9999);

            $stmt = $this->pdo->prepare("INSERT INTO cctv_requests 
                (request_id_code, requesting_agency, contact_person, position_designation, contact_number, email_address, office_unit, case_reference, related_complaint_id, legal_basis, purpose_reason, incident_location, camera_id, location_description, incident_date, incident_type, footage_start_time, footage_end_time, incident_description, delivery_method, reason, camera_location, status, requested_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', NOW())");
            $stmt->execute([
                $reqCode,
                $agency,
                $contactPerson,
                $position,
                $contactNumber,
                $email,
                $officeUnit,
                $caseRef,
                $complaintId,
                $legalBasis,
                $purpose,
                $incidentLoc,
                $camera,
                $locDesc,
                $incidentDate,
                $incidentType,
                $startTime,
                $endTime,
                $incidentDesc,
                $delivery,
                $purpose,
                $incidentLoc
            ]);
            $recordId = $this->pdo->lastInsertId();

            $this->saveLog('incoming_cctv_request', 'Marto CCTV Surveillance Partner', $data, [
                'status' => 'success',
                'record_id' => $recordId,
                'request_id_code' => $reqCode
            ], 'received_and_queued');
        }

        return [
            'success' => true,
            'message' => 'CCTV Footage Request received and recorded successfully.',
            'record_id' => $recordId,
            'request_id_code' => $reqCode,
            'requesting_agency' => $agency,
            'status' => 'Pending',
            'received_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Process incoming Anonymous Tip from Group 4 (Anonymous Tip Line System)
     */
    public function processIncomingAnonymousTip(array $data): array {
        $tipId = trim($data['tip_id'] ?? $data['id'] ?? ('TIP-' . date('Ymd') . '-' . rand(1000, 9999)));
        $dateTime = trim($data['date_time'] ?? $data['timestamp'] ?? date('Y-m-d H:i:s'));
        $location = trim($data['location'] ?? 'Quezon City');
        $description = trim($data['tip_description'] ?? $data['description'] ?? '');
        $evidence = trim($data['attached_evidence'] ?? $data['evidence'] ?? '');

        return $this->processInbound([
            'source' => 'group_4_anonymous_tip',
            'tip_id' => $tipId,
            'timestamp' => $dateTime,
            'location' => $location,
            'description' => $description,
            'attached_evidence' => $evidence,
            'emergency_level' => 'Medium'
        ], true);
    }

    /**
     * Process incoming Accident Ticket & Report from Group 2 (Accident and Violation Reporting)
     */
    public function processIncomingAccidentReport(array $data): array {
        $reportId = trim($data['report_id'] ?? $data['id'] ?? ('ACC-REP-' . date('Ymd') . '-' . rand(1000, 9999)));
        $ticketNumber = trim($data['ticket_number'] ?? $data['ticket_no'] ?? $data['accident_ticket'] ?? ('TKT-' . date('Ymd') . '-' . rand(100, 999)));
        $incidentType = trim($data['incident_type'] ?? $data['violation_type'] ?? 'Traffic Accident / Violation');
        $violatorName = trim($data['violator_name'] ?? $data['driver_name'] ?? $data['party_name'] ?? 'Unspecified Driver / Party');
        $vehicleDetails = trim($data['vehicle_details'] ?? $data['vehicle_model'] ?? $data['vehicle'] ?? '');
        $plateNumber = trim($data['plate_number'] ?? $data['plate_no'] ?? '');
        $violationType = trim($data['violation_type'] ?? $incidentType);
        $fineAmount = floatval($data['fine_amount'] ?? $data['penalty_fee'] ?? 0.00);
        $severityLevel = trim($data['severity_level'] ?? $data['severity'] ?? $data['urgency'] ?? 'Medium');
        $collisionType = trim($data['collision_type'] ?? $data['accident_type'] ?? 'Vehicular Collision');
        $location = trim($data['location'] ?? $data['accident_location'] ?? 'Quezon City');
        $barangay = trim($data['barangay'] ?? '');
        $district = trim($data['district'] ?? $this->extractDistrict($location));
        $narrative = trim($data['narrative'] ?? $data['report'] ?? $data['description'] ?? $data['accident_details'] ?? '');
        $casualtiesCount = intval($data['casualties_count'] ?? $data['casualties'] ?? $data['injured_count'] ?? 0);
        $propertyDamage = floatval($data['property_damage_estimate'] ?? $data['damage_estimate'] ?? 0.00);
        $officerName = trim($data['reporting_officer'] ?? $data['officer_name'] ?? 'Traffic Enforcement Officer');
        $dateTime = trim($data['incident_date_time'] ?? $data['date_time'] ?? $data['timestamp'] ?? date('Y-m-d H:i:s'));
        
        $rawMedia = $data['photos'] ?? $data['videos'] ?? $data['evidence_media'] ?? $data['evidence'] ?? $data['images'] ?? $data['attached_photos'] ?? '';
        $evidenceMedia = $this->normalizeMediaUrls($rawMedia);

        if (empty($narrative) && empty($violationType)) {
            throw new Exception('Missing accident report narrative or violation description in incoming payload.');
        }

        $recordId = null;
        $caseNo = null;

        if ($this->pdo instanceof PDO) {
            $stmt = $this->pdo->prepare("INSERT INTO received_accident_reports 
                (report_id, ticket_number, incident_type, violator_name, vehicle_details, plate_number, violation_type, fine_amount, severity_level, collision_type, location, barangay, district, narrative, casualties_count, property_damage_estimate, reporting_officer, incident_date_time, evidence_media, status, raw_payload) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Logged & Classified', ?)");
            $stmt->execute([
                $reportId,
                $ticketNumber,
                $incidentType,
                $violatorName,
                $vehicleDetails,
                $plateNumber,
                $violationType,
                $fineAmount,
                $severityLevel,
                $collisionType,
                $location,
                $barangay,
                $district,
                $narrative,
                $casualtiesCount,
                $propertyDamage,
                $officerName,
                $dateTime,
                $evidenceMedia,
                json_encode($data, JSON_UNESCAPED_UNICODE)
            ]);
            $recordId = $this->pdo->lastInsertId();

            // Automatically mirror and classify into the central incidents table
            try {
                $checkIncidents = $this->pdo->query("SHOW TABLES LIKE 'incidents'");
                if ($checkIncidents && $checkIncidents->rowCount() > 0) {
                    $caseNo = 'ACC-' . date('Ymd') . '-' . substr(strtoupper(bin2hex(random_bytes(2))), 0, 4);
                    $fullNarrative = "[Group 2 Accident Ticket: {$ticketNumber} | Report: {$reportId}]\n"
                        . "Violator/Party: {$violatorName} (Plate: " . ($plateNumber ?: 'N/A') . ")\n"
                        . "Collision Type: {$collisionType} | Severity: {$severityLevel} | Casualties: {$casualtiesCount}\n"
                        . "Damage Est: PHP " . number_format($propertyDamage, 2) . " | Fine: PHP " . number_format($fineAmount, 2) . "\n"
                        . "Narrative: " . $narrative;

                    $urgency = in_array($severityLevel, ['Critical', 'High', 'Medium', 'Low']) ? $severityLevel : 'Medium';

                    $incStmt = $this->pdo->prepare("INSERT INTO incidents (case_no, narrative, incident_type, incident_subtype, auto_classification, urgency_level, location, status, reporter_name, incident_date, created_at) VALUES (?, ?, 'Other', 'Traffic Accident / Violation', 'Group 2 Accident Ticket', ?, ?, 'Submitted', ?, ?, NOW())");
                    $incStmt->execute([
                        $caseNo,
                        $fullNarrative,
                        $urgency,
                        $location,
                        $officerName,
                        date('Y-m-d', strtotime($dateTime))
                    ]);
                }
            } catch (Exception $e) {
                error_log("Notice: Could not mirror accident report to main incidents table: " . $e->getMessage());
            }

            $this->saveLog('incoming_accident_report', 'Group 2 Accident System', $data, [
                'status' => 'success',
                'record_id' => $recordId,
                'ticket_number' => $ticketNumber,
                'report_id' => $reportId,
                'case_no' => $caseNo
            ], 'logged_and_classified');
        }

        return [
            'success' => true,
            'message' => 'Accident ticket and report successfully received, stored, and classified into Incident Logging module.',
            'record_id' => $recordId,
            'report_id' => $reportId,
            'ticket_number' => $ticketNumber,
            'case_no' => $caseNo,
            'incident_type' => $incidentType,
            'severity_level' => $severityLevel,
            'received_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Dispatch Photos and Videos to Group 7 (Photo and Videos Upload API)
     */
    public function dispatchToGroup7EvidenceUpload(array $evidenceData): array {
        $endpoint = getIntegrationSetting('group7_evidence_upload_api_url', 'https://inspection.alertaraqc.com/api/upload_evidence.php');

        $payload = [
            'evidence_id' => $evidenceData['evidence_id'] ?? $evidenceData['id'] ?? null,
            'evidence_number' => $evidenceData['evidence_number'] ?? ('EVD-' . date('Y') . '-' . rand(1000, 9999)),
            'case_number' => $evidenceData['case_number'] ?? '',
            'media_type' => $evidenceData['media_type'] ?? 'Photo/Video',
            'photos' => $evidenceData['photos'] ?? [],
            'videos' => $evidenceData['videos'] ?? [],
            'description' => $evidenceData['description'] ?? $evidenceData['item_description'] ?? '',
            'uploaded_by' => $evidenceData['uploaded_by'] ?? 'Group 1 Evidence Custodian',
            'timestamp' => date('c')
        ];

        $result = dispatchPayloadToEndpoint($endpoint, $payload, [], $this->timeout);
        $status = $result['success'] ? 'success' : 'failed';

        if ($this->pdo instanceof PDO) {
            $this->saveLog('outgoing_group7_evidence_upload', $endpoint, $payload, $result, $status);
        }

        return $result;
    }

    /**
     * Group 1 requests CCTV from Group 2 (Accident & Violation Reporting)
     */
    public function dispatchCctvRequestToGroup2(array $requestData): array {
        $endpoint = getIntegrationSetting('cctv_request_api_url', $this->partnerCctvEndpoint);
        
        $payload = [
            'request_id' => $requestData['request_id'] ?? ('REQ-CCTV-' . date('Ymd') . '-' . rand(100, 999)),
            'sender' => 'Group 1 Law Enforcement & Incident Report System',
            'recipient' => 'Group 2 Accident and Violation Reporting',
            'case_number' => $requestData['case_number'] ?? '',
            'incident_type' => $requestData['incident_type'] ?? 'Accident / Traffic Incident',
            'camera_location' => $requestData['camera_location'] ?? $requestData['location'] ?? 'Quezon City',
            'incident_date' => $requestData['incident_date'] ?? date('Y-m-d'),
            'incident_time' => $requestData['incident_time'] ?? date('H:i:s'),
            'time_window_minutes' => $requestData['time_window_minutes'] ?? 30,
            'vehicle_plate' => $requestData['vehicle_plate'] ?? null,
            'priority' => $requestData['priority'] ?? 'High',
            'reason' => $requestData['reason'] ?? 'Investigation and evidence retrieval for incident report',
            'requested_at' => date('c')
        ];

        $result = dispatchPayloadToEndpoint($endpoint, $payload, [], $this->timeout);
        $status = $result['success'] ? 'success' : 'sent_or_simulated';

        if ($this->pdo instanceof PDO) {
            try {
                $stmt = $this->pdo->prepare("INSERT INTO cctv_requests (request_type, camera_location, incident_date, incident_time, priority, reason, status, requested_at) VALUES ('Footage & Still Photos', ?, ?, ?, ?, ?, 'Dispatched to Group 2', NOW())");
                $stmt->execute([
                    $payload['camera_location'],
                    $payload['incident_date'],
                    $payload['incident_time'],
                    $payload['priority'],
                    $payload['reason']
                ]);
            } catch (Exception $e) {
                error_log("Notice: " . $e->getMessage());
            }

            $this->saveLog('outgoing_group2_cctv_request', $endpoint, $payload, $result, $status);
        }

        return [
            'success' => true,
            'request_id' => $payload['request_id'],
            'status' => 'Dispatched to Group 2',
            'result' => $result
        ];
    }

    /**
     * Group 2 Acknowledges CCTV Request from Group 1
     */
    public function acknowledgeCctvRequest(array $ackData): array {
        $requestId = trim($ackData['request_id'] ?? $ackData['cctv_request_id'] ?? '');
        $acknowledgedBy = trim($ackData['acknowledged_by'] ?? $ackData['operator_name'] ?? 'Group 2 CCTV Operator');
        $ackNotes = trim($ackData['acknowledgement_notes'] ?? $ackData['notes'] ?? 'Request received and camera search queued.');
        $assignedOperator = trim($ackData['assigned_camera_operator'] ?? $acknowledgedBy);
        $status = trim($ackData['status'] ?? 'Acknowledged by Group 2');

        if (empty($requestId)) {
            throw new Exception('Missing request_id in CCTV acknowledgement payload.');
        }

        if ($this->pdo instanceof PDO) {
            try {
                if (is_numeric($requestId)) {
                    $stmt = $this->pdo->prepare("UPDATE cctv_requests SET status = ?, acknowledged_at = NOW(), acknowledged_by = ?, acknowledgement_notes = ?, assigned_camera_operator = ? WHERE id = ?");
                    $stmt->execute([$status, $acknowledgedBy, $ackNotes, $assignedOperator, intval($requestId)]);
                } else {
                    $stmt = $this->pdo->prepare("UPDATE cctv_requests SET status = ?, acknowledged_at = NOW(), acknowledged_by = ?, acknowledgement_notes = ?, assigned_camera_operator = ? WHERE reason LIKE ? OR additional_details LIKE ?");
                    $stmt->execute([$status, $acknowledgedBy, $ackNotes, $assignedOperator, "%{$requestId}%", "%{$requestId}%"]);
                }
            } catch (Exception $e) {
                error_log("Notice updating cctv_requests acknowledgement: " . $e->getMessage());
            }

            $this->saveLog('incoming_group2_cctv_acknowledgement', 'Group 2 Surveillance Desk', $ackData, [
                'status' => 'acknowledged',
                'request_id' => $requestId,
                'acknowledged_by' => $acknowledgedBy
            ], 'acknowledged');
        }

        return [
            'success' => true,
            'message' => 'CCTV request acknowledgement from Group 2 processed successfully.',
            'request_id' => $requestId,
            'status' => $status,
            'acknowledged_by' => $acknowledgedBy,
            'acknowledged_at' => date('Y-m-d H:i:s')
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

