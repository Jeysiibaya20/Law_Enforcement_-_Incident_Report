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
                                <span>Components</span>
                            </a>
                        </li>
                        <li class="breadcrumb-item active" aria-current="page">
                            <span>Buttons</span>
                        </li>
                    </ol>
                </nav>
                <h1>Buttons Components</h1>
                <p>Complete form layouts with various input types, validation states, and submission handling. Perfect for user data collection and interaction.</p>
            </div>
            
            <div class="sub-container">
                <div class="page-content">

                    <!-- ===================================
                       BUTTON VARIANTS SECTION
                       =================================== -->
                    <div id="button-variants" class="component-section">
                        <h3><i class="fas fa-mouse-pointer"></i> Button Variants</h3>
                        <p>Different button styles for various actions and contexts.</p>
                        
                        <h4>Required Files</h4>
                        <div class="usage-requirements">
                            <div class="requirement-item">
                                <i class="fas fa-link"></i>
                                <span><strong>CSS:</strong> css/styles.css (main styles)</span>
                            </div>
                            <div class="requirement-item">
                                <i class="fas fa-link"></i>
                                <span><strong>CSS:</strong> css/pages/buttons.css (button-specific styles)</span>
                            </div>
                            <div class="requirement-item">
                                <i class="fas fa-link"></i>
                                <span><strong>Optional:</strong> FontAwesome icons for button icons</span>
                            </div>
                        </div>
                        
                        <h4>Button Styles</h4>
                        <div class="buttons-showcase">
                            <button class="btn btn-primary">Primary</button>
                            <button class="btn btn-secondary">Secondary</button>
                            <button class="btn btn-success">Success</button>
                            <button class="btn btn-danger">Danger</button>
                            <button class="btn btn-warning">Warning</button>
                            <button class="btn btn-info">Info</button>
                        </div>
                        
                        <h4>Usage Example:</h4>
                        <pre><code>&lt;button class="btn btn-primary"&gt;Primary Button&lt;/button&gt;
&lt;button class="btn btn-success"&gt;Success Button&lt;/button&gt;
&lt;button class="btn btn-danger"&gt;Danger Button&lt;/button&gt;</code></pre>
                    </div>

                    <!-- ===================================
                       BUTTON SIZES SECTION
                       =================================== -->
                    <div id="button-sizes" class="component-section">
                        <h3><i class="fas fa-expand-arrows-alt"></i> Button Sizes</h3>
                        <p>Three different sizes to accommodate various design needs.</p>
                        
                        <h4>Available Sizes</h4>
                        <div class="buttons-showcase">
                            <button class="btn btn-primary btn-lg">Large Button</button>
                            <button class="btn btn-primary">Normal Button</button>
                            <button class="btn btn-primary btn-sm">Small Button</button>
                        </div>
                        
                        <h4>Usage Example:</h4>
                        <pre><code>&lt;button class="btn btn-primary btn-lg"&gt;Large Button&lt;/button&gt;
&lt;button class="btn btn-primary"&gt;Normal Button&lt;/button&gt;
&lt;button class="btn btn-primary btn-sm"&gt;Small Button&lt;/button&gt;</code></pre>
                    </div>

                    <!-- ===================================
                       OUTLINE BUTTONS SECTION
                       =================================== -->
                    <div id="outline-buttons" class="component-section">
                        <h3><i class="fas fa-border-style"></i> Outline Buttons</h3>
                        <p>Transparent buttons with colored borders for a lighter appearance.</p>
                        
                        <h4>Outline Variants</h4>
                        <div class="buttons-showcase">
                            <button class="btn btn-outline-primary">Outline Primary</button>
                            <button class="btn btn-outline-secondary">Outline Secondary</button>
                            <button class="btn btn-outline-success">Outline Success</button>
                            <button class="btn btn-outline-danger">Outline Danger</button>
                            <button class="btn btn-outline-warning">Outline Warning</button>
                            <button class="btn btn-outline-info">Outline Info</button>
                        </div>
                        
                        <h4>Usage Example:</h4>
                        <pre><code>&lt;button class="btn btn-outline-primary"&gt;Outline Primary&lt;/button&gt;
&lt;button class="btn btn-outline-success"&gt;Outline Success&lt;/button&gt;</code></pre>
                    </div>

                    <!-- ===================================
                       IMPLEMENTATION GUIDE SECTION
                       =================================== -->
                    <div id="implementation" class="component-section">
                        <h3><i class="fas fa-code"></i> Implementation Guide</h3>
                        <p>Step-by-step instructions for implementing buttons in your project.</p>
                        
                        <h4>Implementation Steps:</h4>
                        <div class="implementation-steps">
                            <div class="step-item">
                                <div class="step-number">1</div>
                                <div class="step-content">
                                    <strong>Include Required CSS</strong>
                                    <pre><code>&lt;link rel="stylesheet" href="css/styles.css"&gt;
&lt;link rel="stylesheet" href="css/pages/buttons.css"&gt;</code></pre>
                                </div>
                            </div>
                            <div class="step-item">
                                <div class="step-number">2</div>
                                <div class="step-content">
                                    <strong>Add FontAwesome (for icons)</strong>
                                    <pre><code>&lt;link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"&gt;</code></pre>
                                </div>
                            </div>
                            <div class="step-item">
                                <div class="step-number">3</div>
                                <div class="step-content">
                                    <strong>Use Button Classes</strong>
                                    <pre><code>&lt;button class="btn btn-primary"&gt;Primary Button&lt;/button&gt;
&lt;button class="btn btn-success btn-lg"&gt;Large Success&lt;/button&gt;
&lt;button class="btn btn-outline-primary"&gt;Outline&lt;/button&gt;</code></pre>
                                </div>
                            </div>
                            <div class="step-item">
                                <div class="step-number">4</div>
                                <div class="step-content">
                                    <strong>Add Icons (Optional)</strong>
                                    <pre><code>&lt;button class="btn btn-primary"&gt;
    &lt;i class="fas fa-save"&gt;&lt;/i&gt; Save
&lt;/button&gt;</code></pre>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ===================================
                       BUTTON STATES SECTION
                       =================================== -->
                    <div id="button-states" class="component-section">
                        <h3><i class="fas fa-toggle-on"></i> Button States</h3>
                        <p>Different states including disabled, loading, and active states.</p>
                        
                        <h4>State Examples</h4>
                        <div class="buttons-showcase">
                            <button class="btn btn-primary" disabled>Disabled Button</button>
                            <button class="btn btn-success">Active Button</button>
                            <button class="btn btn-info loading">Loading Button</button>
                        </div>
                        
                        <h4>Usage Example:</h4>
                        <pre><code>&lt;button class="btn btn-primary" disabled&gt;Disabled&lt;/button&gt;
&lt;button class="btn btn-success"&gt;Active&lt;/button&gt;
&lt;button class="btn btn-info loading"&gt;Loading&lt;/button&gt;</code></pre>
                    </div>

                </div>
            </div>
        </div>
        <?php include('includes/admin-footer.php') ?>
    </div>

</body>
</html>