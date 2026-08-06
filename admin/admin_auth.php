<?php
/**
 * Admin & Officer Authentication Guard Helper
 * Protects all administrative and officer-restricted routes.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Check if user is logged in
if (empty($_SESSION['user_id'])) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Please sign in to access the administration system.'];
    header('Location: ../auth/login.php');
    exit();
}

// 2. Fetch role from DB to guarantee session role hasn't been tampered with
require_once __DIR__ . '/../config/db_connect.php';
if (!isset($pdo) || !($pdo instanceof PDO)) {
    $pdo = getDBConnection();
}

$userId = $_SESSION['user_id'];
$isAuthorized = false;

try {
    $stmt = $pdo->prepare("SELECT role FROM signup WHERE user_id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    $dbRole = strtolower(trim($user['role'] ?? ''));
    $sessionRole = strtolower(trim($_SESSION['role'] ?? ''));
    $effectiveRole = !empty($dbRole) ? $dbRole : $sessionRole;

    // Only allow Admin, Administrator, Officer, or Barangay Official roles
    if ($user && (strpos($effectiveRole, 'admin') !== false || strpos($effectiveRole, 'officer') !== false || strpos($effectiveRole, 'official') !== false)) {
        $isAuthorized = true;
    }
} catch (Exception $e) {
    $isAuthorized = false;
}

if (!$isAuthorized) {
    $_SESSION['flash'] = [
        'type' => 'danger',
        'message' => 'Access Denied: You do not have administrative privileges to view this section.'
    ];
    header('Location: ../modules/my_reports.php');
    exit();
}
?>
