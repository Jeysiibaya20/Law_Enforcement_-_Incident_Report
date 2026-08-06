<?php
/**
 * User & Resident Authentication Guard
 * Ensures user is authenticated to view resident modules.
 * Allows both residents and admins to view user-side pages with the Public Services layout.
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
?>
