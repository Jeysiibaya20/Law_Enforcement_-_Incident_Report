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

$action = strtolower(trim($inputData['action'] ?? $inputData['module'] ?? $inputData['endpoint'] ?? 'health'));

// Dispatch Action Handlers
switch ($action) {

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
            'incident_management' => ['cases', 'blotters', 'suspects_witnesses', 'hearing_schedule', 'summons', 'certificates', 'settlements'],
            'intelligence_reports' => ['reports_analytics', 'automated_reports', 'crime_mapping', 'learning_guide'],
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
        $complainant = trim($inputData['complainant_name'] ?? '');
        $incidentType = trim($inputData['incident_type'] ?? '');
        $location = trim($inputData['location'] ?? '');
        $narrative = trim($inputData['narrative'] ?? '');

        if (empty($complainant) || empty($incidentType)) {
            sendJsonResponse('error', 'complainant_name and incident_type are required', null, 400);
        }

        try {
            $blotterNo = 'BLT-' . date('Ymd') . '-' . rand(1000, 9999);
            $stmt = $pdo->prepare("
                INSERT INTO blotters (blotter_no, complainant_name, incident_type, location, narrative, status, created_at)
                VALUES (?, ?, ?, ?, ?, 'Pending', NOW())
            ");
            $stmt->execute([$blotterNo, $complainant, $incidentType, $location, $narrative]);
            $newId = $pdo->lastInsertId();

            sendJsonResponse('success', 'Blotter entry filed successfully', [
                'id'         => $newId,
                'blotter_no' => $blotterNo,
                'status'     => 'Pending'
            ], 201);
        } catch (Exception $e) {
            sendJsonResponse('error', 'Failed to create blotter: ' . $e->getMessage(), null, 500);
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
    // 7. AI CHATBOT & NLP INTEGRATION
    // ===========================================
    case 'chatbot':
    case 'chat':
        $message = trim($inputData['message'] ?? $inputData['query'] ?? '');
        if (empty($message)) {
            sendJsonResponse('error', 'message parameter required', null, 400);
        }

        // Include Chatbot API processing if available
        $chatbot_file = __DIR__ . '/chatbot_api.php';
        if (file_exists($chatbot_file)) {
            $_POST['message'] = $message;
            include $chatbot_file;
            exit();
        } else {
            sendJsonResponse('success', 'Chatbot response', [
                'reply' => "Thank you for contacting Alertara PH. How can we assist you with your report?",
                'language' => 'en'
            ]);
        }
        break;

    // ===========================================
    // DEFAULT UNKNOWN ACTION HANDLER
    // ===========================================
    default:
        sendJsonResponse('error', "Unknown API action '{$action}'. Call action=modules to view all supported endpoints.", null, 404);
        break;
}
