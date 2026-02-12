<?php
/**
 * Reusable Header Template
 * Include this file at the top of your pages: <?php include 'includes/header.php'; ?>
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AlerTaraQC</title>
    <link rel="icon" type="image/x-icon" href="images/favicon.ico">
    <link rel="stylesheet" href="css/global.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <?php if (isset($extraCSS)): ?>
        <?php foreach ($extraCSS as $css): ?>
            <link rel="stylesheet" href="<?php echo htmlspecialchars($css); ?>">
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body>
    <header class="header">
        <div class="header-container">
            <a href="buttons.php" class="logo">
                <img src="images/logo.svg" alt="" class="logo-img">
            </a>
            
            <nav class="nav-center">
                <ul class="nav-menu">
                    <li><a href="#home" class="nav-link">Home</a></li>
                    <li><a href="#about" class="nav-link">About</a></li>
                    <li><a href="#systems" class="nav-link">Systems</a></li>
                    <li><a href="#departments" class="nav-link">Departments</a></li>
                    <li><a href="#contact" class="nav-link">Contact Us</a></li>
                </ul>
            </nav>
            
            <div class="nav-actions">
                <a href="login.php" class="btn btn-secondary">FAQs</a>
                <a href="signup.php" class="btn btn-primary">Learn More</a>
                <button class="mobile-menu-toggle" aria-label="Toggle mobile menu">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
        </div>
        
        <!-- Mobile Navigation -->
        <div class="mobile-nav">
            <div class="mobile-nav-header">
                <a href="buttons.php" class="logo">
                    <img src="images/logo.svg" alt="" class="logo-img">
                </a>
            </div>
            <ul class="mobile-nav-menu">
                <li><a href="#home" class="mobile-nav-link">Home</a></li>
                <li><a href="#about" class="mobile-nav-link">About</a></li>
                <li><a href="#systems" class="mobile-nav-link">Systems</a></li>
                <li><a href="#departments" class="mobile-nav-link">Departments</a></li>
                <li><a href="#contact" class="mobile-nav-link">Contact Us</a></li>
                <li class="mobile-nav-divider"></li>
                <li><a href="login.php" class="mobile-nav-link">Login</a></li>
                <li><a href="signup.php" class="mobile-nav-link">Learn More</a></li>
            </ul>
        </div>
        
        <!-- Mobile Navigation Overlay -->
        <div class="mobile-nav-overlay"></div>
    </header>

    <main class="main-content">
