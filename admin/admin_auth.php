<?php
/**
 * Admin & Officer Authentication Guard Helper
 * Strictly verifies administrative session (admin_user_id).
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$base_url = isset($base_url) ? $base_url : (strpos(str_replace('\\', '/', $_SERVER['PHP_SELF'] ?? ''), '/admin/') !== false ? '../' : '');

// 1. Check if admin session exists
$adminUserId = $_SESSION['admin_user_id'] ?? null;

// Backward compatibility check for existing active admin sessions
if (!$adminUserId && !empty($_SESSION['user_id'])) {
    $r = strtolower(trim($_SESSION['role'] ?? ''));
    if (strpos($r, 'admin') !== false || strpos($r, 'officer') !== false || strpos($r, 'official') !== false) {
        $_SESSION['admin_user_id'] = $_SESSION['user_id'];
        $_SESSION['admin_role'] = $_SESSION['role'];
        $_SESSION['admin_fullname'] = $_SESSION['fullname'] ?? $_SESSION['first_name'] ?? 'Admin';
        $adminUserId = $_SESSION['admin_user_id'];
    }
}

if (empty($adminUserId)) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Please sign in with your administrator credentials to access the administration portal.'];
    header('Location: ' . $base_url . 'admin/login.php');
    exit();
}

// 2. Fetch role from DB to guarantee session role hasn't been tampered with
require_once __DIR__ . '/../config/db_connect.php';
if (!isset($pdo) || !($pdo instanceof PDO)) {
    $pdo = getDBConnection();
}

$isAuthorized = false;
try {
    $stmt = $pdo->prepare("SELECT role, fullname FROM signup WHERE user_id = ? LIMIT 1");
    $stmt->execute([$adminUserId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    $dbRole = strtolower(trim($user['role'] ?? ''));
    $sessionRole = strtolower(trim($_SESSION['admin_role'] ?? ''));
    $effectiveRole = !empty($dbRole) ? $dbRole : $sessionRole;

    if ($user && (strpos($effectiveRole, 'admin') !== false || strpos($effectiveRole, 'officer') !== false || strpos($effectiveRole, 'official') !== false)) {
        $isAuthorized = true;
        $_SESSION['admin_role'] = $user['role'];
        $_SESSION['admin_fullname'] = $user['fullname'] ?? $_SESSION['admin_fullname'] ?? 'Admin';
    }
} catch (Exception $e) {
    $isAuthorized = false;
}

if (!$isAuthorized) {
    unset($_SESSION['admin_user_id'], $_SESSION['admin_role'], $_SESSION['admin_fullname']);
    $_SESSION['flash'] = [
        'type' => 'danger',
        'message' => 'Access Denied: You do not have administrative privileges to view this section.'
    ];
    header('Location: ' . $base_url . 'admin/login.php');
    exit();
}

// Global variable for admin pages
$userId = $adminUserId;
?>
