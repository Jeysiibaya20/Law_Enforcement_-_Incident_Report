<?php
session_start();
require_once '../config/db_connect.php';
require_once '../includes/navbar.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

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