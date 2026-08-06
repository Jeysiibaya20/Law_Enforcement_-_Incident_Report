<?php
/**
 * User & Resident Authentication Guard
 * Strictly verifies resident session (resident_user_id).
 * Completely isolated from administrative sessions.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$base_url = isset($base_url) ? $base_url : (strpos(str_replace('\\', '/', $_SERVER['PHP_SELF'] ?? ''), '/modules/') !== false ? '../' : '');

// Backward compatibility check for existing resident sessions
$residentUserId = $_SESSION['resident_user_id'] ?? null;
if (!$residentUserId && !empty($_SESSION['user_id']) && empty($_SESSION['admin_user_id'])) {
    $r = strtolower(trim($_SESSION['role'] ?? 'user'));
    if (strpos($r, 'admin') === false && strpos($r, 'officer') === false && strpos($r, 'official') === false) {
        $_SESSION['resident_user_id'] = $_SESSION['user_id'];
        $residentUserId = $_SESSION['resident_user_id'];
    }
}

// 1. Unauthenticated resident users must log in via Resident Sign In
if (empty($residentUserId)) {
    header('Location: ' . $base_url . 'auth/login.php');
    exit();
}

// 2. Validate resident account status (cached every 5 min)
$roleLastChecked = $_SESSION['_resident_role_checked_at'] ?? 0;
if ((time() - $roleLastChecked) > 300) {
    require_once __DIR__ . '/../config/db_connect.php';
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        $pdo = getDBConnection();
    }
    try {
        $stmt = $pdo->prepare("SELECT role FROM signup WHERE user_id = ? LIMIT 1");
        $stmt->execute([$residentUserId]);
        $dbUser = $stmt->fetch(PDO::FETCH_ASSOC);
        $dbRole = strtolower(trim($dbUser['role'] ?? 'user'));

        // If user was promoted to Admin, revoke resident session to prevent cross-login
        if (strpos($dbRole, 'admin') !== false || strpos($dbRole, 'officer') !== false || strpos($dbRole, 'official') !== false) {
            unset($_SESSION['resident_user_id']);
            header('Location: ' . $base_url . 'admin/login.php');
            exit();
        }
        $_SESSION['_resident_role_checked_at'] = time();
    } catch (Exception $e) {}
}

// 3. Set flags for layout rendering
$force_public_sidebar = true;
$userId = $residentUserId;
?>
