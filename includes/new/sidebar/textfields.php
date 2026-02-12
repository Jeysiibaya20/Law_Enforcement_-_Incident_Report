<?php
/**
 * Buttons Page with Sidebar Integration Only
 * Sample page demonstrating buttons with sidebar navigation (no header)
 */

$pageTitle = 'Forms - Sidebar Demo';
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
    <link rel="stylesheet" href="css/forms.css">
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
                            <span>Text Field</span>
                        </li>
                    </ol>
                </nav>
                <h1>Text Field Components</h1>
                <p>Comprehensive collection of input field types with validation states, styling options, and user-friendly interactions.</p>
            </div>
            <div class="sub-container">
                <div class="page-content">
                    <!-- ===================================
                       INPUT TYPES SECTION
                       =================================== -->
                    <div id="input-types" class="component-section">
                        <h3><i class="fas fa-keyboard"></i> Input Field Types</h3>
                        <p>Various input field types for different data collection needs.</p>
                        
                        <h4>Required Files</h4>
                        <div class="usage-requirements">
                            <div class="requirement-item">
                                <i class="fas fa-link"></i>
                                <span><strong>CSS:</strong> css/styles.css (main styles)</span>
                            </div>
                            <div class="requirement-item">
                                <i class="fas fa-link"></i>
                                <span><strong>CSS:</strong> css/pages/textfields.css (text field styles)</span>
                            </div>
                            <div class="requirement-item">
                                <i class="fas fa-code"></i>
                                <span><strong>Optional:</strong> JavaScript for validation and interactions</span>
                            </div>
                        </div>
                        
                        <h4>Basic Input Fields</h4>
                        <div class="textfields-showcase">
                            <div class="form-group">
                                <label class="form-label">Text Input</label>
                                <input type="text" class="form-control" placeholder="Enter text here...">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Email Input</label>
                                <input type="email" class="form-control" placeholder="email@example.com">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Password Input</label>
                                <input type="password" class="form-control" placeholder="Enter password">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Number Input</label>
                                <input type="number" class="form-control" placeholder="123">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Phone Input</label>
                                <input type="tel" class="form-control" placeholder="+1 (555) 123-4567">
                            </div>
                            <div class="form-group">
                                <label class="form-label">URL Input</label>
                                <input type="url" class="form-control" placeholder="https://example.com">
                            </div>
                        </div>
                        
                        <h4>Date and Time Inputs</h4>
                        <div class="textfields-showcase">
                            <div class="form-group">
                                <label class="form-label">Date Input</label>
                                <input type="date" class="form-control">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Time Input</label>
                                <input type="time" class="form-control">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Date-Time Input</label>
                                <input type="datetime-local" class="form-control">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Month Input</label>
                                <input type="month" class="form-control">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Week Input</label>
                                <input type="week" class="form-control">
                            </div>
                        </div>
                        
                        <h4>Specialized Inputs</h4>
                        <div class="textfields-showcase">
                            <div class="form-group">
                                <label class="form-label">Search Input</label>
                                <input type="search" class="form-control" placeholder="Search...">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Color Input</label>
                                <input type="color" class="form-control color-input">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Range Input</label>
                                <input type="range" class="form-control range-input" min="0" max="100" value="50">
                            </div>
                            <div class="form-group">
                                <label class="form-label">File Input</label>
                                <input type="file" class="form-control file-input">
                            </div>
                        </div>
                    </div>

                    <!-- ===================================
                       VALIDATION STATES SECTION
                       =================================== -->
                    <div id="validation" class="component-section">
                        <h3><i class="fas fa-check-circle"></i> Validation States</h3>
                        <p>Different input states for validation and user feedback.</p>
                        
                        <h4>Input States</h4>
                        <div class="textfields-showcase">
                            <div class="form-group">
                                <label class="form-label">Success State</label>
                                <input type="text" class="form-control is-success" placeholder="Valid input">
                                <small class="form-text success-text">This field is valid!</small>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Error State</label>
                                <input type="text" class="form-control is-error" placeholder="Invalid input">
                                <small class="form-text error-text">Please enter a valid value.</small>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Warning State</label>
                                <input type="text" class="form-control is-warning" placeholder="Warning input">
                                <small class="form-text warning-text">Please review this field.</small>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Disabled Input</label>
                                <input type="text" class="form-control" placeholder="Disabled field" disabled>
                                <small class="form-text">This field is disabled.</small>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Readonly Input</label>
                                <input type="text" class="form-control" value="Readonly value" readonly>
                                <small class="form-text">This field is readonly.</small>
                            </div>
                        </div>
                    </div>

                    <!-- ===================================
                       INPUT SIZES AND STYLES SECTION
                       =================================== -->
                    <div id="sizes" class="component-section">
                        <h3><i class="fas fa-expand-arrows-alt"></i> Input Sizes and Styles</h3>
                        <p>Different input sizes and styling options for various design needs.</p>
                        
                        <h4>Input Sizes</h4>
                        <div class="textfields-showcase">
                            <div class="form-group">
                                <label class="form-label">Large Input</label>
                                <input type="text" class="form-control input-lg" placeholder="Large input field">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Normal Input</label>
                                <input type="text" class="form-control" placeholder="Normal input field">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Small Input</label>
                                <input type="text" class="form-control input-sm" placeholder="Small input field">
                            </div>
                        </div>
                        
                        <h4>Input Styles</h4>
                        <div class="textfields-showcase">
                            <div class="form-group">
                                <label class="form-label">Rounded Input</label>
                                <input type="text" class="form-control input-rounded" placeholder="Rounded corners">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Square Input</label>
                                <input type="text" class="form-control input-square" placeholder="Square corners">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Underline Input</label>
                                <input type="text" class="form-control input-underline" placeholder="Underline style">
                            </div>
                        </div>
                    </div>

                    <!-- ===================================
                       TEXTAREAS SECTION
                       =================================== -->
                    <div id="textareas" class="component-section">
                        <h3><i class="fas fa-align-left"></i> Text Areas</h3>
                        <p>Multi-line text input fields with various configurations.</p>
                        
                        <h4>Text Area Variants</h4>
                        <div class="textfields-showcase">
                            <div class="form-group">
                                <label class="form-label">Standard Text Area</label>
                                <textarea class="form-control" placeholder="Enter your message here..." rows="4"></textarea>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Large Text Area</label>
                                <textarea class="form-control input-lg" placeholder="Large text area..." rows="6"></textarea>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Small Text Area</label>
                                <textarea class="form-control input-sm" placeholder="Small text area..." rows="3"></textarea>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Resizable Text Area</label>
                                <textarea class="form-control resizable" placeholder="Resizable text area..." rows="4"></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- ===================================
                       IMPLEMENTATION GUIDE SECTION
                       =================================== -->
                    <div id="implementation" class="component-section">
                        <h3><i class="fas fa-code"></i> Implementation Guide</h3>
                        <p>Step-by-step instructions for implementing text fields in your project.</p>
                        
                        <h4>Implementation Steps:</h4>
                        <div class="implementation-steps">
                            <div class="step-item">
                                <div class="step-number">1</div>
                                <div class="step-content">
                                    <strong>Include Required CSS</strong>
                                    <pre><code>&lt;link rel="stylesheet" href="css/styles.css"&gt;
&lt;link rel="stylesheet" href="css/pages/textfields.css"&gt;</code></pre>
                                </div>
                            </div>
                            <div class="step-item">
                                <div class="step-number">2</div>
                                <div class="step-content">
                                    <strong>Basic Input Structure</strong>
                                    <pre><code>&lt;div class="form-group"&gt;
    &lt;label class="form-label"&gt;Field Name&lt;/label&gt;
    &lt;input type="text" class="form-control" placeholder="Enter text..."&gt;
&lt;/div&gt;</code></pre>
                                </div>
                            </div>
                            <div class="step-item">
                                <div class="step-number">3</div>
                                <div class="step-content">
                                    <strong>Add Validation States</strong>
                                    <pre><code>&lt;input class="form-control is-success"&gt;  <!-- Valid -->
&lt;input class="form-control is-error"&gt;    <!-- Invalid -->
&lt;input class="form-control is-warning"&gt;  <!-- Warning --></code></pre>
                                </div>
                            </div>
                            <div class="step-item">
                                <div class="step-number">4</div>
                                <div class="step-content">
                                    <strong>Add Input Sizes</strong>
                                    <pre><code>&lt;input class="form-control input-lg"&gt;  <!-- Large -->
&lt;input class="form-control"&gt;           <!-- Normal -->
&lt;input class="form-control input-sm"&gt;  <!-- Small --></code></pre>
                                </div>
                            </div>
                            <div class="step-item">
                                <div class="step-number">5</div>
                                <div class="step-content">
                                    <strong>Add Help Text</strong>
                                    <pre><code>&lt;div class="form-group"&gt;
    &lt;label class="form-label"&gt;Email&lt;/label&gt;
    &lt;input type="email" class="form-control"&gt;
    &lt;small class="form-text"&gt;We'll never share your email.&lt;/small&gt;
&lt;/div&gt;</code></pre>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php include('includes/admin-footer.php') ?>
    </div>
</body>
</html>