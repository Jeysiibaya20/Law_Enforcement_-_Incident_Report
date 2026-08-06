<?php
/**
 * User & Resident Authentication Guard
 * Ensures user-side pages are accessible ONLY to regular resident users.
 * Redirects Admin & Officer accounts strictly to the Admin Portal (admin/dashboard.php).
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

// 2. Strict Role Check: Admin accounts belong in the Admin Portal ONLY
$sessionRole = strtolower(trim($_SESSION['role'] ?? ''));
if (strpos($sessionRole, 'admin') !== false || strpos($sessionRole, 'officer') !== false || strpos($sessionRole, 'official') !== false) {
    header('Location: ' . $base_url . 'admin/dashboard.php');
    exit();
}
?>
