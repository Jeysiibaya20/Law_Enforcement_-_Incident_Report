<?php
/**
 * User & Resident Authentication Guard (Optimized)
 * Uses session-cached role check with periodic DB re-validation (every 5 min)
 * to avoid hitting the database on every single page load.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$base_url = isset($base_url) ? $base_url : (strpos(str_replace('\\', '/', $_SERVER['PHP_SELF'] ?? ''), '/modules/') !== false ? '../' : '');

// 1. Unauthenticated users must log in
if (empty($_SESSION['user_id'])) {
    header('Location: ' . $base_url . 'auth/login.php');
    exit();
}

// 2. Role check — use cached session role, re-validate from DB every 5 minutes
$sessionRole = strtolower(trim($_SESSION['role'] ?? ''));
$roleLastChecked = $_SESSION['_role_checked_at'] ?? 0;

if ((time() - $roleLastChecked) > 300) {
    // Re-validate from DB every 5 minutes
    require_once __DIR__ . '/../config/db_connect.php';
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        $pdo = getDBConnection();
    }
    try {
        $stmt = $pdo->prepare("SELECT role FROM signup WHERE user_id = ? LIMIT 1");
        $stmt->execute([$_SESSION['user_id']]);
        $dbUser = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($dbUser) {
            $sessionRole = strtolower(trim($dbUser['role']));
            $_SESSION['role'] = $dbUser['role'];
        }
        $_SESSION['_role_checked_at'] = time();
    } catch (Exception $e) {
        // Keep cached role on DB error
    }
}

// 3. Admin/Officer/Official accounts belong in Admin Portal ONLY
if (strpos($sessionRole, 'admin') !== false || strpos($sessionRole, 'officer') !== false || strpos($sessionRole, 'official') !== false) {
    header('Location: ' . $base_url . 'admin/dashboard.php');
    exit();
}

// 4. Set force_public_sidebar flag for sidebar rendering
$force_public_sidebar = true;
?>
