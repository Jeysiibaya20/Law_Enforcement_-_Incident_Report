<?php
/**
 * Logout Page - Hotel HR Management System - HR 1&2
 * 
 * @author HR System
 * @version 1.0.0
 */

// Start session
session_start();

// Destroy session
session_destroy();

// Clear session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Redirect to login page
header('Location: login.php');
exit();
?>
