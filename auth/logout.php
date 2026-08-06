<?php
/**
 * Resident Portal Logout
 * Clears resident session data and redirects to Resident Login.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Clear resident session variables
unset(
    $_SESSION['resident_user_id'],
    $_SESSION['user_id'],
    $_SESSION['username'],
    $_SESSION['email'],
    $_SESSION['role'],
    $_SESSION['fullname'],
    $_SESSION['first_name']
);

// If no admin session is active, destroy session completely
if (empty($_SESSION['admin_user_id'])) {
    session_destroy();
}

header('Location: login.php');
exit();
?>
