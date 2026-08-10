<?php
/**
 * User & Resident Authentication Guard
 * Verifies active session (resident_user_id or user_id or admin_user_id).
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$base_url = isset($base_url) ? $base_url : (strpos(str_replace('\\', '/', $_SERVER['PHP_SELF'] ?? ''), '/modules/') !== false ? '../' : '');

// 1. Resolve user ID from session
$residentUserId = $_SESSION['resident_user_id'] ?? $_SESSION['user_id'] ?? $_SESSION['admin_user_id'] ?? null;

if (empty($residentUserId)) {
    header('Location: ' . $base_url . 'auth/login.php');
    exit();
}

// Ensure backward compatibility session keys
$_SESSION['user_id'] = $residentUserId;
if (empty($_SESSION['resident_user_id']) && empty($_SESSION['admin_user_id'])) {
    $_SESSION['resident_user_id'] = $residentUserId;
}

// 2. Validate resident account status if not already checked recently
$roleLastChecked = $_SESSION['_resident_role_checked_at'] ?? 0;
if ((time() - $roleLastChecked) > 300) {
    require_once __DIR__ . '/../config/db_connect.php';
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        $pdo = getDBConnection();
    }
    try {
        $stmt = $pdo->prepare("SELECT role, fullname FROM signup WHERE user_id = ? LIMIT 1");
        $stmt->execute([$residentUserId]);
        $dbUser = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($dbUser) {
            $dbRole = strtolower(trim($dbUser['role'] ?? 'user'));
            if (strpos($dbRole, 'admin') !== false || strpos($dbRole, 'officer') !== false) {
                $_SESSION['admin_user_id'] = $residentUserId;
                $_SESSION['admin_role'] = $dbUser['role'];
                $_SESSION['admin_fullname'] = $dbUser['fullname'] ?? $_SESSION['admin_fullname'] ?? 'Admin';
            }
        }
        $_SESSION['_resident_role_checked_at'] = time();
    } catch (Exception $e) {}
}

// 3. Set flags for layout rendering
$force_public_sidebar = empty($_SESSION['admin_user_id']);
$userId = $residentUserId;
?>
