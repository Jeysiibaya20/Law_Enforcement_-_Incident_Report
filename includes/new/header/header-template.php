<?php 
/**
 * LGU #4 - Cards Component Page
 * Dedicated page showcasing card components and layouts
 * 
 * This page demonstrates:
 * - Various card styles and layouts
 * - Card content organization
 * - Interactive card features
 * - Responsive card designs
 * 
 * @version 1.0.0
 * @author LGU #4 Development Team
 */

// Include header with navigation and theme functionality
include 'includes/header.php'; ?>

<!-- Page-specific CSS for cards -->
<link rel="stylesheet" href="css/buttons.css">
<link rel="stylesheet" href="css/hero.css">

<!-- ===================================
   HERO SECTION - Page-specific hero for cards
   =================================== -->
    <div class="hero-section">
        <div class="main-container">
            <div class="sub-container">
                <h1>Card Components</h1>
                <p>Flexible card components with various layouts, styles, and interactive features for organizing content beautifully.</p>
                
                <div class="hero-buttons">
                    <a href="#basic-cards" class="btn btn-primary">View Cards</a>
                    <a href="modals.php" class="btn btn-secondary">See Modals</a>
                    <a href="#implementation" class="btn btn-outline-primary">How to Use</a>
                </div>
            </div>
        </div>
    </div>

<!-- ===================================
   MAIN CONTENT - Card demonstrations and documentation
   =================================== -->
    <div class="main-content">
        <div class="main-container">
            <div class="sub-container">
                <div class="page-content">
                    <!--Insert content-->
                </div>
            </div>
        </div>
    </div>

<!-- ===================================
   FOOTER INCLUDE
   =================================== -->
<?php include 'includes/footer.php'; ?>
