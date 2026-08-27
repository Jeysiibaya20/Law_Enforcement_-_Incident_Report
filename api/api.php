<?php
/**
 * Unified All-in-One Central REST API Endpoint
 * Single Gateway for All System Modules: Dashboard, Blotters, Cases, Suspects, Hearings,
 * Summons, Certificates, Settlements, Users, Notifications, and AI Chatbot.
 */

// Enable error handling & CORS
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', '0');

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle OPTIONS Pre-flight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db_connect.php';

// Response Helper Function
function sendJsonResponse($status = 'success', $message = '', $data = null, $httpCode = 200) {
    http_response_code($httpCode);
    $response = [
        'status'    => $status,
        'message'   => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    if ($data !== null) {
        $response['data'] = $data;
    }
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit();
}

// Get Database Connection
try {
    $pdo = getDBConnection();
} catch (Exception $e) {
    sendJsonResponse('error', 'Database connection error: ' . $e->getMessage(), null, 500);
}

// Parse Input Data (JSON or $_REQUEST)
$inputJSON = json_decode(file_get_contents('php://input'), true) ?: [];
$inputData = array_merge($_REQUEST, $inputJSON);

$action = strtolower(trim($inputData['action'] ?? $inputData['module'] ?? $inputData['endpoint'] ?? 'all'));

// Dispatch Action Handlers
switch ($action) {

    // ===========================================
    // ALL-IN-ONE API (RETURNS ALL MODULES IN ONE RESPONSE)
    // ===========================================
    case 'all':
    case 'all_in_one':
        try {
            // 1. System Info
            $system = [
                'api_name'    => 'Alertara Incident & Law Enforcement All-In-One Unified API',
                'version'     => '2.5.0',
                'environment' => getenv('APP_ENV') ?: 'production',
                'db_status'   => ($pdo instanceof PDO) ? 'connected' : 'disconnected',
                'server_time' => date('Y-m-d H:i:s T')
            ];

            // 2. Dashboard KPI Statistics
            $totalUsers      = (int)$pdo->query("SELECT COUNT(*) FROM signup WHERE role != 'Admin'")->fetchColumn();
            $verifiedUsers   = (int)$pdo->query("SELECT COUNT(*) FROM signup WHERE email_verified = 1 AND role != 'Admin'")->fetchColumn();
            $unverifiedUsers = (int)$pdo->query("SELECT COUNT(*) FROM signup WHERE email_verified = 0 AND role != 'Admin'")->fetchColumn();
            $totalBlotters   = (int)$pdo->query("SELECT COUNT(*) FROM blotters")->fetchColumn();
            $pendingBlotters = (int)$pdo->query("SELECT COUNT(*) FROM blotters WHERE status = 'Pending'")->fetchColumn();
            $resolvedBlotters= (int)$pdo->query("SELECT COUNT(*) FROM blotters WHERE status IN ('Resolved', 'Settled')")->fetchColumn();
            $totalCases      = 0;
            try { $totalCases = (int)$pdo->query("SELECT COUNT(*) FROM incidents")->fetchColumn(); } catch (Exception $e) {}

            $dashboard = [
                'total_users'       => $totalUsers,
                'verified_users'    => $verifiedUsers,
                'unverified_users'  => $unverifiedUsers,
                'total_blotters'    => $totalBlotters,
                'pending_blotters'  => $pendingBlotters,
                'resolved_blotters' => $resolvedBlotters,
                'total_cases'       => $totalCases
            ];

            // 3. Blotters Records List
            $stmt = $pdo->query("SELECT id, blotter_no, complainant_name, incident_type, status, location, created_at FROM blotters ORDER BY created_at DESC LIMIT 10");
            $blotters = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 4. Cases List
            $cases = [];
            try {
                $stmt = $pdo->query("SELECT * FROM incidents ORDER BY created_at DESC LIMIT 10");
                $cases = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {}

            // 5. System Users List
            $stmt = $pdo->query("SELECT user_id, fullname, emailadd, username, role, email_verified, created_at FROM signup ORDER BY created_at DESC LIMIT 10");
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 6. Header Unread Notifications
            $notifications = [];
            $stmt_b = $pdo->query("SELECT id, blotter_no, complainant_name, created_at FROM blotters WHERE status = 'Pending' ORDER BY created_at DESC LIMIT 5");
            $pending_b = $stmt_b->fetchAll(PDO::FETCH_ASSOC);
            foreach ($pending_b as $b) {
                $notifications[] = [
                    'type'  => 'blotter',
                    'title' => 'Pending Blotter #' . $b['blotter_no'],
                    'desc'  => 'Complainant: ' . $b['complainant_name'],
                    'time'  => date('M d, g:i a', strtotime($b['created_at']))
                ];
            }

            // 7. System Modules
            $modules = [
                'admin' => ['dashboard', 'users', 'account_approvals', 'create_admin', 'settings'],
                'incident_management' => ['cases', 'blotters', 'suspects_witnesses', 'hearing_schedule', 'hearing_result', 'cctv_request', 'evidence_collection', 'summons', 'certificates', 'settlements'],
                'intelligence_reports' => ['reports_analytics', 'automated_reports'],
                'services' => ['health', 'notifications', 'chatbot']
            ];

            sendJsonResponse('success', 'All-In-One Unified API Data for All Modules', [
                'system'        => $system,
                'modules'       => $modules,
                'dashboard'     => $dashboard,
                'blotters'      => $blotters,
                'cases'         => $cases,
                'users'         => $users,
                'notifications' => $notifications
            ]);
        } catch (Exception $e) {
            sendJsonResponse('error', 'Error compiling All-In-One API data: ' . $e->getMessage(), null, 500);
        }
        break;

    // ===========================================
    // 1. HEALTH & SYSTEM MODULE STATUS
    // ===========================================
    case 'health':
    case 'ping':
        sendJsonResponse('success', 'Alertara Unified API is online and operational.', [
            'api_name'    => 'Alertara Incident & Law Enforcement Unified REST API',
            'version'     => '2.5.0',
            'environment' => getenv('APP_ENV') ?: 'production',
            'db_status'   => ($pdo instanceof PDO) ? 'connected' : 'disconnected',
            'server_time' => date('Y-m-d H:i:s T')
        ]);
        break;

    case 'modules':
        sendJsonResponse('success', 'Available System Modules', [
            'admin' => ['dashboard', 'users', 'account_approvals', 'create_admin', 'settings'],
            'incident_management' => ['cases', 'blotters', 'suspects_witnesses', 'hearing_schedule', 'hearing_result', 'cctv_request', 'evidence_collection', 'summons', 'certificates', 'settlements'],
            'intelligence_reports' => ['reports_analytics', 'automated_reports'],
            'services' => ['health', 'notifications', 'chatbot']
        ]);
        break;

    // ===========================================
    // 2. DASHBOARD KPI STATS & ANALYTICS
    // ===========================================
    case 'dashboard':
    case 'dashboard_stats':
        try {
            $totalUsers      = (int)$pdo->query("SELECT COUNT(*) FROM signup WHERE role != 'Admin'")->fetchColumn();
            $verifiedUsers   = (int)$pdo->query("SELECT COUNT(*) FROM signup WHERE email_verified = 1 AND role != 'Admin'")->fetchColumn();
            $unverifiedUsers = (int)$pdo->query("SELECT COUNT(*) FROM signup WHERE email_verified = 0 AND role != 'Admin'")->fetchColumn();
            $totalBlotters   = (int)$pdo->query("SELECT COUNT(*) FROM blotters")->fetchColumn();
            $pendingBlotters = (int)$pdo->query("SELECT COUNT(*) FROM blotters WHERE status = 'Pending'")->fetchColumn();
            $resolvedBlotters= (int)$pdo->query("SELECT COUNT(*) FROM blotters WHERE status IN ('Resolved', 'Settled')")->fetchColumn();
            
            $totalCases = 0;
            try { $totalCases = (int)$pdo->query("SELECT COUNT(*) FROM incidents")->fetchColumn(); } catch (Exception $e) {}

            sendJsonResponse('success', 'Dashboard KPI Statistics', [
                'total_users'       => $totalUsers,
                'verified_users'    => $verifiedUsers,
                'unverified_users'  => $unverifiedUsers,
                'total_blotters'    => $totalBlotters,
                'pending_blotters'  => $pendingBlotters,
                'resolved_blotters' => $resolvedBlotters,
                'total_cases'       => $totalCases
            ]);
        } catch (Exception $e) {
            sendJsonResponse('error', 'Failed to fetch dashboard stats: ' . $e->getMessage(), null, 500);
        }
        break;

    // ===========================================
    // 3. BLOTTER REGISTRY MODULE
    // ===========================================
    case 'blotters':
    case 'get_blotters':
        try {
            $status = trim($inputData['status'] ?? '');
            $search = trim($inputData['search'] ?? '');
            $limit  = min(100, max(1, (int)($inputData['limit'] ?? 20)));

            $sql = "SELECT id, blotter_no, complainant_name, incident_type, status, location, created_at FROM blotters WHERE 1=1";
            $params = [];

            if (!empty($status)) {
                $sql .= " AND status = ?";
                $params[] = $status;
            }

            if (!empty($search)) {
                $sql .= " AND (blotter_no LIKE ? OR complainant_name LIKE ? OR incident_type LIKE ?)";
                $searchWild = '%' . $search . '%';
                $params[] = $searchWild;
                $params[] = $searchWild;
                $params[] = $searchWild;
            }

            $sql .= " ORDER BY created_at DESC LIMIT " . $limit;
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $blotters = $stmt->fetchAll(PDO::FETCH_ASSOC);

            sendJsonResponse('success', 'Blotters retrieved successfully', [
                'count'    => count($blotters),
                'blotters' => $blotters
            ]);
        } catch (Exception $e) {
            sendJsonResponse('error', 'Error fetching blotters: ' . $e->getMessage(), null, 500);
        }
        break;

    case 'get_blotter':
        $id = (int)($inputData['id'] ?? 0);
        if (!$id) {
            sendJsonResponse('error', 'Blotter ID required', null, 400);
        }
        try {
            $stmt = $pdo->prepare("SELECT * FROM blotters WHERE id = ? LIMIT 1");
            $stmt->execute([$id]);
            $blotter = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($blotter) {
                sendJsonResponse('success', 'Blotter details retrieved', $blotter);
            } else {
                sendJsonResponse('error', 'Blotter record not found', null, 404);
            }
        } catch (Exception $e) {
            sendJsonResponse('error', 'Database error: ' . $e->getMessage(), null, 500);
        }
        break;

    case 'create_blotter':
    case 'create_incident':
    case 'create_report':
    case 'create_complaint':
    case 'incident':
    case 'report':
    case 'complaint':
        $complainant = trim($inputData['complainant_name'] ?? $inputData['reporter_name'] ?? $inputData['complainant'] ?? $inputData['name'] ?? 'Citizen Complainant');
        
        $rawIncidentType = trim($inputData['incident_type'] ?? $inputData['type'] ?? $inputData['complaint_type'] ?? 'Other');
        $validTypes = ['Abuse', 'Neglect', 'Violence', 'Theft', 'Assault', 'Domestic', 'Other'];
        $normType = ucfirst(strtolower($rawIncidentType));
        $incidentType = in_array($normType, $validTypes) ? $normType : 'Other';
        $incidentSubtype = trim($inputData['incident_subtype'] ?? $inputData['subtype'] ?? $rawIncidentType);
        
        $location = trim($inputData['location'] ?? $inputData['incident_location'] ?? $inputData['complainant_address'] ?? 'Quezon City');
        $narrative = trim($inputData['narrative'] ?? $inputData['description'] ?? $inputData['details'] ?? $inputData['description_of_complaint'] ?? '');
        
        $complainantContact = trim($inputData['complainant_contact'] ?? $inputData['contact_number'] ?? $inputData['reporter_phone'] ?? $inputData['phone'] ?? '');
        $complainantAddress = trim($inputData['complainant_address'] ?? $inputData['address'] ?? '');
        $complainantEmail = trim($inputData['complainant_email'] ?? $inputData['email'] ?? $inputData['reporter_email'] ?? '');
        
        $respondent = trim($inputData['respondent_name'] ?? $inputData['defendant_name'] ?? $inputData['suspect_name'] ?? $inputData['respondent'] ?? $inputData['defendant'] ?? '');
        $respondentContact = trim($inputData['respondent_contact'] ?? $inputData['defendant_contact'] ?? '');
        $respondentAddress = trim($inputData['respondent_address'] ?? $inputData['defendant_address'] ?? '');
        
        $incidentDate = !empty($inputData['incident_date']) ? date('Y-m-d', strtotime($inputData['incident_date'])) : (!empty($inputData['date']) ? date('Y-m-d', strtotime($inputData['date'])) : date('Y-m-d'));
        $incidentTime = !empty($inputData['incident_time']) ? date('H:i:s', strtotime($inputData['incident_time'])) : (!empty($inputData['time']) ? date('H:i:s', strtotime($inputData['time'])) : date('H:i:s'));
        
        $rawUrgency = ucfirst(strtolower($inputData['urgency_level'] ?? $inputData['urgency'] ?? $inputData['priority'] ?? 'Medium'));
        $urgency = in_array($rawUrgency, ['Critical', 'High', 'Medium', 'Low']) ? $rawUrgency : 'Medium';
        $priority = in_array($rawUrgency, ['High', 'Medium', 'Low']) ? $rawUrgency : 'Medium';

        if (empty($complainant)) {
            sendJsonResponse('error', 'complainant_name / reporter_name is required', null, 400);
        }

        try {
            // 1. PRIMARY DESTINATION: Insert into INCIDENTS table (Incident Report Module)
            $caseNo = 'INC-' . date('Ymd') . '-' . rand(1000, 9999);
            $stmtInc = $pdo->prepare("
                INSERT INTO incidents (
                    case_no, narrative, incident_type, incident_subtype,
                    location, incident_date, incident_time, urgency_level,
                    status, reporter_name, reporter_phone, reporter_email,
                    victim_name, suspect_name, report_type, routing_group, routing_status,
                    auto_classification, created_at
                ) VALUES (
                    ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    'Submitted', ?, ?, ?,
                    ?, ?, 'External Forwarded Complaint', 'Group 1', 'Pending',
                    'External Department Referral', NOW()
                )
            ");
            $stmtInc->execute([
                $caseNo,
                $narrative ?: 'External forwarded complaint from partner department.',
                $incidentType,
                $incidentSubtype,
                $location,
                $incidentDate,
                $incidentTime,
                $urgency,
                $complainant,
                $complainantContact,
                $complainantEmail,
                $complainant,
                $respondent
            ]);
            $incidentId = (int)$pdo->lastInsertId();

            // 2. Also create linked record in BLOTTERS table
            $blotterNo = 'BLT-' . date('Ymd') . '-' . rand(1000, 9999);
            try {
                $stmtBlt = $pdo->prepare("
                    INSERT INTO blotters (
                        blotter_no, complainant_name, complainant_contact, complainant_email, complainant_address,
                        respondent_name, respondent_contact, respondent_address,
                        incident_type, incident_date, incident_time, location, description,
                        priority, status, incident_id, created_from_incident, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', ?, 1, NOW())
                ");
                $stmtBlt->execute([
                    $blotterNo, $complainant, $complainantContact, $complainantEmail, $complainantAddress,
                    $respondent, $respondentContact, $respondentAddress,
                    $incidentType, $incidentDate, $incidentTime, $location, $narrative ?: 'No detailed narrative provided.',
                    $priority, $incidentId
                ]);
            } catch (Exception $exBlt) {
                error_log("Notice creating linked blotter: " . $exBlt->getMessage());
            }

            // Log into System Audit Trail
            try {
                require_once __DIR__ . '/../includes/audit_logger.php';
                logAuditTrail('EXTERNAL_INCIDENT_REPORT_FILED', 'External API', $caseNo, "Filed external incident report for {$complainant} (Type: {$incidentType}, Case: {$caseNo}, Blotter: {$blotterNo}).", 'SUCCESS', $pdo);
            } catch (Exception $e) {}

            sendJsonResponse('success', 'Incident report successfully filed and registered into Incident Logging module', [
                'id'            => $incidentId,
                'incident_id'   => $incidentId,
                'case_no'       => $caseNo,
                'blotter_no'    => $blotterNo,
                'destination'   => 'Incident Report Registry',
                'status'        => 'Submitted',
                'reporter_name' => $complainant,
                'suspect_name'  => $respondent,
                'incident_type' => $incidentType,
                'location'      => $location,
                'narrative'     => $narrative
            ], 201);
        } catch (Exception $e) {
            sendJsonResponse('error', 'Failed to create incident report: ' . $e->getMessage(), null, 500);
        }
        break;

    case 'update_blotter_status':
        $id = (int)($inputData['id'] ?? 0);
        $newStatus = trim($inputData['status'] ?? '');
        if (!$id || empty($newStatus)) {
            sendJsonResponse('error', 'id and status parameters required', null, 400);
        }
        try {
            $stmt = $pdo->prepare("UPDATE blotters SET status = ? WHERE id = ?");
            $stmt->execute([$newStatus, $id]);
            sendJsonResponse('success', "Blotter #{$id} status updated to '{$newStatus}'");
        } catch (Exception $e) {
            sendJsonResponse('error', 'Failed to update blotter status: ' . $e->getMessage(), null, 500);
        }
        break;

    // ===========================================
    // 4. CASE MANAGEMENT MODULE
    // ===========================================
    case 'cases':
    case 'get_cases':
        try {
            $limit = min(100, max(1, (int)($inputData['limit'] ?? 20)));
            $stmt = $pdo->query("SELECT * FROM incidents ORDER BY created_at DESC LIMIT " . $limit);
            $cases = $stmt->fetchAll(PDO::FETCH_ASSOC);
            sendJsonResponse('success', 'Cases retrieved successfully', [
                'count' => count($cases),
                'cases' => $cases
            ]);
        } catch (Exception $e) {
            // Fallback if table name is different
            sendJsonResponse('success', 'Cases module active', ['count' => 0, 'cases' => []]);
        }
        break;

    // ===========================================
    // 5. USERS & ADMIN MANAGEMENT MODULE
    // ===========================================
    case 'users':
    case 'get_users':
        try {
            $roleFilter = trim($inputData['role'] ?? '');
            $sql = "SELECT user_id, fullname, emailadd, username, role, email_verified, created_at FROM signup WHERE 1=1";
            $params = [];
            if (!empty($roleFilter)) {
                $sql .= " AND role = ?";
                $params[] = $roleFilter;
            }
            $sql .= " ORDER BY created_at DESC LIMIT 50";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

            sendJsonResponse('success', 'Users list retrieved', [
                'count' => count($users),
                'users' => $users
            ]);
        } catch (Exception $e) {
            sendJsonResponse('error', 'Error fetching users: ' . $e->getMessage(), null, 500);
        }
        break;

    case 'create_admin':
        $fullname = trim($inputData['fullname'] ?? '');
        $email = trim($inputData['email'] ?? $inputData['emailadd'] ?? '');
        $username = trim($inputData['username'] ?? $email);
        $password = $inputData['password'] ?? '';
        $role = trim($inputData['role'] ?? 'Admin');

        if (empty($fullname) || empty($email) || empty($password)) {
            sendJsonResponse('error', 'fullname, email, and password are required', null, 400);
        }

        try {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("SELECT user_id FROM signup WHERE emailadd = ? OR username = ? LIMIT 1");
            $stmt->execute([$email, $username]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                $up = $pdo->prepare("UPDATE signup SET fullname = ?, emailadd = ?, username = ?, password = ?, role = ?, email_verified = 1 WHERE user_id = ?");
                $up->execute([$fullname, $email, $username, $password_hash, $role, $existing['user_id']]);
                sendJsonResponse('success', "Existing account updated to {$role}", ['user_id' => $existing['user_id']]);
            } else {
                $ins = $pdo->prepare("INSERT INTO signup (fullname, emailadd, username, password, role, email_verified, terms_accepted, created_at) VALUES (?, ?, ?, ?, ?, 1, 1, NOW())");
                $ins->execute([$fullname, $email, $username, $password_hash, $role]);
                sendJsonResponse('success', "New {$role} account created successfully", ['user_id' => $pdo->lastInsertId()], 201);
            }
        } catch (Exception $e) {
            sendJsonResponse('error', 'Failed to provision admin account: ' . $e->getMessage(), null, 500);
        }
        break;

    case 'approve_user':
        $userId = (int)($inputData['user_id'] ?? 0);
        if (!$userId) {
            sendJsonResponse('error', 'user_id required', null, 400);
        }
        try {
            $stmt = $pdo->prepare("UPDATE signup SET email_verified = 1 WHERE user_id = ?");
            $stmt->execute([$userId]);
            sendJsonResponse('success', "User #{$userId} approved successfully");
        } catch (Exception $e) {
            sendJsonResponse('error', 'Database error: ' . $e->getMessage(), null, 500);
        }
        break;

    // ===========================================
    // 6. REALTIME NOTIFICATIONS & ALERTS
    // ===========================================
    case 'notifications':
    case 'get_notifications':
        try {
            $notifications = [];

            // Fetch pending blotters
            $stmt_b = $pdo->query("SELECT id, blotter_no, complainant_name, created_at FROM blotters WHERE status = 'Pending' ORDER BY created_at DESC LIMIT 5");
            $pending_blotters = $stmt_b->fetchAll(PDO::FETCH_ASSOC);
            foreach ($pending_blotters as $b) {
                $notifications[] = [
                    'type'  => 'blotter',
                    'title' => 'Pending Blotter #' . $b['blotter_no'],
                    'desc'  => 'Complainant: ' . $b['complainant_name'],
                    'time'  => date('M d, g:i a', strtotime($b['created_at']))
                ];
            }

            // Fetch unverified signups
            $stmt_u = $pdo->query("SELECT user_id, fullname, emailadd, created_at FROM signup WHERE email_verified = 0 AND role != 'Admin' ORDER BY created_at DESC LIMIT 5");
            $unverified = $stmt_u->fetchAll(PDO::FETCH_ASSOC);
            foreach ($unverified as $u) {
                $notifications[] = [
                    'type'  => 'user',
                    'title' => 'Unverified Signup',
                    'desc'  => $u['fullname'] ?: $u['emailadd'],
                    'time'  => date('M d, g:i a', strtotime($u['created_at']))
                ];
            }

            sendJsonResponse('success', 'Header notifications retrieved', [
                'unread_count'  => count($notifications),
                'notifications' => $notifications
            ]);
        } catch (Exception $e) {
            sendJsonResponse('error', 'Error fetching notifications: ' . $e->getMessage(), null, 500);
        }
        break;

    // ===========================================
    // 8. EMERGENCY CALLS (ALDRIN'S GROUP)
    // ===========================================
    case 'emergency_calls':
    case 'get_emergency_calls':
        try {
            $limit = min((int)($inputData['limit'] ?? 50), 200);
            $stmt = $pdo->prepare("SELECT * FROM received_emergency_calls ORDER BY id DESC LIMIT ?");
            $stmt->bindValue(1, $limit, PDO::PARAM_INT);
            $stmt->execute();
            $calls = $stmt->fetchAll(PDO::FETCH_ASSOC);
            sendJsonResponse('success', 'Emergency calls retrieved successfully', [
                'count' => count($calls),
                'calls' => $calls
            ]);
        } catch (Exception $e) {
            sendJsonResponse('error', 'Error fetching emergency calls: ' . $e->getMessage(), null, 500);
        }
        break;

    case 'receive_emergency_call':
    case 'create_emergency_call':
        require_once __DIR__ . '/../modules/OperationalModuleIntegrator.php';
        $integrator = new OperationalModuleIntegrator($pdo);
        try {
            $result = $integrator->processIncomingEmergencyCall($inputData);
            sendJsonResponse('success', 'Emergency call received and processed', $result, 200);
        } catch (Exception $e) {
            sendJsonResponse('error', $e->getMessage(), null, 400);
        }
        break;

    // ===========================================
    // 9. CCTV REQUESTS & INTEGRATION (MARTO'S GROUP)
    // ===========================================
    case 'cctv_requests':
    case 'get_cctv_requests':
        try {
            $limit = min((int)($inputData['limit'] ?? 50), 200);
            $statusFilter = trim($inputData['status'] ?? '');
            if ($statusFilter !== '') {
                $stmt = $pdo->prepare("SELECT * FROM cctv_requests WHERE status = ? ORDER BY id DESC LIMIT ?");
                $stmt->bindValue(1, $statusFilter, PDO::PARAM_STR);
                $stmt->bindValue(2, $limit, PDO::PARAM_INT);
                $stmt->execute();
            } else {
                $stmt = $pdo->prepare("SELECT * FROM cctv_requests ORDER BY id DESC LIMIT ?");
                $stmt->bindValue(1, $limit, PDO::PARAM_INT);
                $stmt->execute();
            }
            $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
            sendJsonResponse('success', 'CCTV requests retrieved successfully', [
                'count' => count($requests),
                'requests' => $requests
            ]);
        } catch (Exception $e) {
            sendJsonResponse('error', 'Error fetching CCTV requests: ' . $e->getMessage(), null, 500);
        }
        break;

    case 'receive_cctv_request':
    case 'cctv_request_create':
        require_once __DIR__ . '/../modules/OperationalModuleIntegrator.php';
        $integrator = new OperationalModuleIntegrator($pdo);
        try {
            $result = $integrator->processIncomingCctvRequest($inputData);
            sendJsonResponse('success', 'CCTV request received and processed', $result, 200);
        } catch (Exception $e) {
            sendJsonResponse('error', $e->getMessage(), null, 400);
        }
        break;

    case 'cctv_request_update_status':
        $reqId = (int)($inputData['id'] ?? $inputData['request_id'] ?? 0);
        $newStatus = trim($inputData['status'] ?? 'Approved');
        $reviewNotes = trim($inputData['review_notes'] ?? $inputData['monitoring_notes'] ?? '');
        $rejectionReason = trim($inputData['rejection_reason'] ?? '');
        if (!$reqId) {
            sendJsonResponse('error', 'id or request_id is required', null, 400);
        }
        try {
            $stmt = $pdo->prepare("UPDATE cctv_requests SET status = ?, review_notes = COALESCE(NULLIF(?, ''), review_notes), rejection_reason = COALESCE(NULLIF(?, ''), rejection_reason), updated_at = NOW() WHERE id = ?");
            $stmt->execute([$newStatus, $reviewNotes, $rejectionReason, $reqId]);
            sendJsonResponse('success', "CCTV request #{$reqId} status updated to {$newStatus}", [
                'id' => $reqId,
                'status' => $newStatus
            ]);
        } catch (Exception $e) {
            sendJsonResponse('error', 'Database error: ' . $e->getMessage(), null, 500);
        }
        break;

    // ===========================================
    // 10. INSPECTION DOCUMENTS & ATTACHED PHOTOS (GROUP 7)
    // ===========================================
    case 'inspection_documents':
    case 'get_inspection_documents':
        try {
            $limit = min((int)($inputData['limit'] ?? 50), 200);
            $stmt = $pdo->prepare("SELECT * FROM received_inspection_documents ORDER BY id DESC LIMIT ?");
            $stmt->bindValue(1, $limit, PDO::PARAM_INT);
            $stmt->execute();
            $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
            sendJsonResponse('success', 'Inspection documents and photo media retrieved successfully', [
                'count' => count($documents),
                'inspection_documents' => $documents
            ]);
        } catch (Exception $e) {
            sendJsonResponse('error', 'Error fetching inspection documents: ' . $e->getMessage(), null, 500);
        }
        break;

    case 'receive_inspection_document':
        require_once __DIR__ . '/../modules/OperationalModuleIntegrator.php';
        $integrator = new OperationalModuleIntegrator($pdo);
        try {
            $result = $integrator->processIncomingInspectionDocument($inputData);
            sendJsonResponse('success', 'Inspection document and media received and processed', $result, 200);
        } catch (Exception $e) {
            sendJsonResponse('error', $e->getMessage(), null, 400);
        }
        break;

    // ===========================================
    // 11. ACCIDENT REPORTS & EVIDENCE (GROUP 2)
    // ===========================================
    case 'accident_reports':
    case 'get_accident_reports':
        try {
            $limit = min((int)($inputData['limit'] ?? 50), 200);
            $stmt = $pdo->prepare("SELECT * FROM received_accident_reports ORDER BY id DESC LIMIT ?");
            $stmt->bindValue(1, $limit, PDO::PARAM_INT);
            $stmt->execute();
            $accidents = $stmt->fetchAll(PDO::FETCH_ASSOC);
            sendJsonResponse('success', 'Accident reports and tickets retrieved successfully', [
                'count' => count($accidents),
                'accident_reports' => $accidents
            ]);
        } catch (Exception $e) {
            sendJsonResponse('error', 'Error fetching accident reports: ' . $e->getMessage(), null, 500);
        }
        break;

    // ===========================================
    // 12. RECEIVED CCTV FOOTAGE & RESOLVED TIPS
    // ===========================================
    case 'received_cctv_footage':
        try {
            $limit = min((int)($inputData['limit'] ?? 50), 200);
            $stmt = $pdo->prepare("SELECT * FROM cctv_footage_received ORDER BY id DESC LIMIT ?");
            $stmt->bindValue(1, $limit, PDO::PARAM_INT);
            $stmt->execute();
            $footage = $stmt->fetchAll(PDO::FETCH_ASSOC);
            sendJsonResponse('success', 'Received CCTV footage records retrieved successfully', [
                'count' => count($footage),
                'cctv_footage' => $footage
            ]);
        } catch (Exception $e) {
            sendJsonResponse('error', 'Error fetching CCTV footage: ' . $e->getMessage(), null, 500);
        }
        break;

    case 'received_tips':
        try {
            $limit = min((int)($inputData['limit'] ?? 50), 200);
            $stmt = $pdo->prepare("SELECT * FROM received_resolved_tips ORDER BY id DESC LIMIT ?");
            $stmt->bindValue(1, $limit, PDO::PARAM_INT);
            $stmt->execute();
            $tips = $stmt->fetchAll(PDO::FETCH_ASSOC);
            sendJsonResponse('success', 'Received resolved tips retrieved successfully', [
                'count' => count($tips),
                'resolved_tips' => $tips
            ]);
        } catch (Exception $e) {
            sendJsonResponse('error', 'Error fetching resolved tips: ' . $e->getMessage(), null, 500);
        }
        break;

    // ===========================================
    // DEFAULT UNKNOWN ACTION HANDLER
    // ===========================================
    default:
        sendJsonResponse('error', "Unknown API action '{$action}'. Call action=modules to view all supported endpoints.", null, 404);
        break;
}
