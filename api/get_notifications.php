<?php
/**
 * Real-time Notifications API Endpoint
 * Serves JSON notifications for top header bell icon dropdown
 */

header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db_connect.php';

try {
    $pdo = getDBConnection();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}

$script_dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$in_subfolder = (strpos($script_dir, '/modules') !== false) || (strpos($script_dir, '/admin') !== false) || (strpos($script_dir, '/officer') !== false) || (strpos($script_dir, '/api') !== false);
$base_url = $in_subfolder ? '../' : '';

$isAdmin = !empty($_SESSION['admin_user_id']);
$residentId = $_SESSION['user_id'] ?? $_SESSION['resident_user_id'] ?? null;

$unread_count = 0;
$notifications = [];

try {
    if ($isAdmin) {
        // 1. Pending Blotters
        $stmt_b = $pdo->query("SELECT id, blotter_no, complainant_name, created_at FROM blotters WHERE status = 'Pending' ORDER BY created_at DESC LIMIT 5");
        $pending_blotters = $stmt_b->fetchAll(PDO::FETCH_ASSOC);
        foreach ($pending_blotters as $b) {
            $notifications[] = [
                'type' => 'blotter',
                'title' => 'Pending Blotter #' . ($b['blotter_no'] ?: $b['id']),
                'desc' => 'Filed by ' . ($b['complainant_name'] ?: 'Resident'),
                'time' => date('M d, g:i a', strtotime($b['created_at'])),
                'link' => $base_url . 'admin/blotters.php'
            ];
        }

        // 2. Pending User Approvals
        $stmt_unv = $pdo->query("SELECT user_id, fullname, emailadd, created_at FROM signup WHERE email_verified = 0 AND role != 'Admin' ORDER BY created_at DESC LIMIT 3");
        $unverified = $stmt_unv->fetchAll(PDO::FETCH_ASSOC);
        foreach ($unverified as $u) {
            $notifications[] = [
                'type' => 'user',
                'title' => 'Unverified Signup Request',
                'desc' => ($u['fullname'] ?: $u['emailadd']),
                'time' => date('M d, g:i a', strtotime($u['created_at'])),
                'link' => $base_url . 'admin/account_approvals.php'
            ];
        }

        // 3. Recently Received CCTV Footage
        $stmt_cctv = $pdo->query("SELECT id, request_id, file_name, created_at FROM cctv_footage_received ORDER BY created_at DESC LIMIT 3");
        $cctvList = $stmt_cctv->fetchAll(PDO::FETCH_ASSOC);
        foreach ($cctvList as $c) {
            $notifications[] = [
                'type' => 'cctv',
                'title' => 'CCTV Footage Received',
                'desc' => 'Request #' . ($c['request_id'] ?: $c['id']) . ' fulfilled',
                'time' => date('M d, g:i a', strtotime($c['created_at'])),
                'link' => $base_url . 'admin/external_integrations.php'
            ];
        }

        // 4. Recently Received Resolved Tips
        $stmt_tips = $pdo->query("SELECT id, tip_id, title, created_at FROM received_resolved_tips ORDER BY created_at DESC LIMIT 3");
        $tipsList = $stmt_tips->fetchAll(PDO::FETCH_ASSOC);
        foreach ($tipsList as $t) {
            $notifications[] = [
                'type' => 'tip',
                'title' => 'Resolved Tip Received',
                'desc' => ($t['title'] ?: 'Tip #' . $t['tip_id']),
                'time' => date('M d, g:i a', strtotime($t['created_at'])),
                'link' => $base_url . 'admin/external_integrations.php'
            ];
        }
    } else if ($residentId) {
        // User side notifications
        $stmt_r = $pdo->prepare("SELECT case_no, status, updated_at FROM incidents WHERE created_by = ? ORDER BY updated_at DESC LIMIT 5");
        $stmt_r->execute([$residentId]);
        $userUpdates = $stmt_r->fetchAll(PDO::FETCH_ASSOC);
        foreach ($userUpdates as $ur) {
            $notifications[] = [
                'type' => 'report',
                'title' => 'Incident Report #' . ($ur['case_no'] ?: 'Updated'),
                'desc' => 'Status: ' . ($ur['status'] ?: 'In Review'),
                'time' => date('M d, g:i a', strtotime($ur['updated_at'])),
                'link' => $base_url . 'modules/my_reports.php'
            ];
        }
    }

    $unread_count = count($notifications);
} catch (Exception $e) {
    $unread_count = 0;
    $notifications = [];
}

echo json_encode([
    'success' => true,
    'unread_count' => $unread_count,
    'notifications' => $notifications,
    'timestamp' => date('Y-m-d H:i:s')
]);
