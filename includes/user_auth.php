<?php
/**
 * User & Resident Authentication Guard
 * Ensures user is authenticated. Admin/Officer accounts are redirected
 * to the Admin Portal — they cannot access user-side pages.
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

// 2. Validate role from DB to prevent session tampering
require_once __DIR__ . '/../config/db_connect.php';
if (!isset($pdo) || !($pdo instanceof PDO)) {
    $pdo = getDBConnection();
}

try {
    $stmt = $pdo->prepare("SELECT role FROM signup WHERE user_id = ? LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $dbUser = $stmt->fetch(PDO::FETCH_ASSOC);
    $dbRole = strtolower(trim($dbUser['role'] ?? ''));
} catch (Exception $e) {
    $dbRole = strtolower(trim($_SESSION['role'] ?? ''));
}

// 3. Admin/Officer/Official accounts belong in Admin Portal ONLY
if (strpos($dbRole, 'admin') !== false || strpos($dbRole, 'officer') !== false || strpos($dbRole, 'official') !== false) {
    header('Location: ' . $base_url . 'admin/dashboard.php');
    exit();
}
?>
