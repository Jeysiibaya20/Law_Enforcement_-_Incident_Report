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
    <title>Template</title>
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
                    <li><a href="buttons.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'buttons.php' ? 'active' : ''; ?>">Buttons</a></li>
                    <li><a href="forms.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'forms.php' ? 'active' : ''; ?>">Forms</a></li>
                    <li><a href="textfields.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'textfields.php' ? 'active' : ''; ?>">Text Fields</a></li>
                    <li><a href="datatables.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'datatables.php' ? 'active' : ''; ?>">Data Tables</a></li>
                    <li><a href="content.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'content.php' ? 'active' : ''; ?>">Content</a></li>
                    <li><a href="cards.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'cards.php' ? 'active' : ''; ?>">Cards</a></li>
                </ul>
            </nav>
            
            <div class="nav-actions">
                <a href="login.php" class="btn btn-secondary">Login</a>
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
                <button class="mobile-nav-close" aria-label="Close mobile menu">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <ul class="mobile-nav-menu">
                <li><a href="buttons.php" class="mobile-nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'buttons.php' ? 'active' : ''; ?>">Buttons</a></li>
                <li><a href="forms.php" class="mobile-nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'forms.php' ? 'active' : ''; ?>">Forms</a></li>
                <li><a href="textfields.php" class="mobile-nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'textfields.php' ? 'active' : ''; ?>">Text Fields</a></li>
                <li><a href="datatables.php" class="mobile-nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'datatables.php' ? 'active' : ''; ?>">Data Tables</a></li>
                <li><a href="content.php" class="mobile-nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'content.php' ? 'active' : ''; ?>">Content</a></li>
                <li><a href="cards.php" class="mobile-nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'cards.php' ? 'active' : ''; ?>">Cards</a></li>
                <li><a href="modals.php" class="mobile-nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'modals.php' ? 'active' : ''; ?>">Modals</a></li>
                <li class="mobile-nav-divider"></li>
                <li><a href="login.php" class="mobile-nav-link">Login</a></li>
                <li><a href="signup.php" class="mobile-nav-link">Learn More</a></li>
            </ul>
        </div>
        
        <!-- Mobile Navigation Overlay -->
        <div class="mobile-nav-overlay"></div>
    </header>

    <main class="main-content">
