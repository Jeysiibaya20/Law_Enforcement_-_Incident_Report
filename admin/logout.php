<?php
/**
 * Dedicated Admin Portal Logout
 * Clears administrative session data and redirects to Admin Login.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Clear admin session variables
unset(
    $_SESSION['admin_user_id'],
    $_SESSION['admin_username'],
    $_SESSION['admin_email'],
    $_SESSION['admin_role'],
    $_SESSION['admin_fullname'],
    $_SESSION['admin_first_name']
);

// If no resident session is active, destroy session completely
if (empty($_SESSION['resident_user_id'])) {
    session_destroy();
}

header('Location: login.php');
exit();
?>
