<?php
/**
 * Admin Authentication Helper
 * Check if user is admin before allowing access to admin pages
 */

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

// Check if user has admin role (from signup table)
require_once '../config/db_connect.php';

try {
    $stmt = $pdo->prepare("SELECT role FROM signup WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Accept role values that contain the word 'admin' (e.g. 'Admin', 'Administrator')
    $dbRole = strtolower(trim($user['role'] ?? ''));
    $sessionRole = strtolower(trim($_SESSION['role'] ?? ''));

    if (!$user || (stripos($dbRole, 'admin') === false && stripos($sessionRole, 'admin') === false)) {
        // Not an admin, redirect to regular dashboard
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'You do not have permission to access the admin panel.'];
        header('Location: ../landing.php');
        exit();
    }
} catch (Exception $e) {
    header('Location: ../auth/login.php');
    exit();
}

// User is admin, continue
?>
