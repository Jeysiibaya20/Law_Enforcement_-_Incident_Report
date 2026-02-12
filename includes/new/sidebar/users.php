<?php
/**
 * Buttons Page with Sidebar Integration Only
 * Sample page demonstrating buttons with sidebar navigation (no header)
 */

$pageTitle = 'Buttons - Sidebar Demo';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link rel="icon" type="image/x-icon" href="images/favicon.ico">
    <link rel="stylesheet" href="css/global.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/admin-header.css">
    <link rel="stylesheet" href="css/buttons.css">
    <link rel="stylesheet" href="css/hero.css">
    <link rel="stylesheet" href="css/sidebar-footer.css">
</head>
<body>
    <!-- Include Sidebar Component -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- Include Admin Header Component -->
    <?php include 'includes/admin-header.php'; ?>
    
    <!-- ===================================
       MAIN CONTENT - Button demonstrations and documentation
       =================================== -->
    <div class="main-content">
        <div class="main-container">
            <div class="title">
                <nav class="breadcrumb" aria-label="Breadcrumb">
                    <ol class="breadcrumb-list">
                        <li class="breadcrumb-item">
                            <a href="/" class="breadcrumb-link">
                                <span>Home</span>
                            </a>
                        </li>
                        <li class="breadcrumb-item">
                            <a href="/components" class="breadcrumb-link">
                                <span>Users</span>
                            </a>
                        </li>
                        <li class="breadcrumb-item active" aria-current="page">
                            <span>All User</span>
                        </li>
                    </ol>
                </nav>
                <h1>All User</h1>
                <p>Complete form layouts with various input types, validation states, and submission handling. Perfect for user data collection and interaction.</p>
            </div>
            
            <div class="sub-container">
                <div class="page-content">
                    <!--Insert content here-->
                </div>
            </div>
        </div>
        <!--Uncomment if already have content-->
        <?php /*include('includes/admin-footer.php')*/ ?>
    </div>

</body>
</html>