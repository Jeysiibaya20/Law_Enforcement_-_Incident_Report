<?php
require_once __DIR__ . '/../includes/user_auth.php';
require_once __DIR__ . '/../config/db_connect.php';

// Redirect to appropriate dashboard based on role
if (in_array($_SESSION['role'], ['Admin', 'HR Manager', 'HR Staff'])) {
    header('Location: ../admin/new_hires_dashboard.php');
    exit();
} else {
    // For employees, redirect to employee portal (when we create it)
    header('Location: ../employee/onboarding.php');
    exit();
}
?>