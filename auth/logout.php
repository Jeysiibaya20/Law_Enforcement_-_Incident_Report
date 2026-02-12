<?php
/**
 * Logout Page
 * 
 * @author System
 * @version 1.0.0
 */

// Set page variables BEFORE including header
$page_title = 'Logout';
$base_url = '../';

// Start session
session_start();

// Include header for loading screen
require_once '../includes/header.php';

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

?>

<script>
    // Redirect to login
    window.location.href = 'login.php';
</script>

<?php
require_once '../includes/footer.php';
?>

