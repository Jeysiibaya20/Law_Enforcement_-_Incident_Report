<?php
/**
 * Header Include - Hotel HR Management System - HR 1&2
 * 
 * @author HR System
 * @version 1.0.0
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in (basic check)
$is_logged_in = isset($_SESSION['user_id']);
$current_user = $is_logged_in ? $_SESSION : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Alertara">
    <meta name="author" content="Alertara>
    <meta name="robots" content="noindex, nofollow">
    
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>Alertara</title>
    
    <!-- Bootstrap 5.3.8 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@300;400;500;600;700&family=Libre+Baskerville:wght@400;700&display=swap" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="<?php echo isset($base_url) ? $base_url : ''; ?>assets/css/style.css" rel="stylesheet">
    
    <!-- Favicon -->
    <!-- <link rel="icon" type="image/x-icon" href="<?php echo isset($base_url) ? $base_url : ''; ?>assets/images/favicon.ico"> -->
    
    <!-- Additional Head Content -->
    <?php if (isset($additional_head)) echo $additional_head; ?>
</head>
<body>
    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle d-md-none" type="button" onclick="toggleSidebar()">
        <i class="bi bi-list"></i>
    </button>