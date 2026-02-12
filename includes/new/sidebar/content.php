<?php
/**
 * Buttons Page with Sidebar Integration Only
 * Sample page demonstrating buttons with sidebar navigation (no header)
 */

$pageTitle = 'Content - Sidebar Demo';
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
    <link rel="stylesheet" href="css/content.css">
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
                            <span>Content Elements</span>
                        </li>
                    </ol>
                </nav>
                <h1>Content Elements</h1>
                <p>Typography, alerts, and content presentation components for effective communication and user interface design.</p>
            </div>
            <div class="sub-container">
                <div class="page-content">
                    <!-- ===================================
                       TYPOGRAPHY SECTION
                       =================================== -->
                    <div id="typography" class="component-section">
                        <h3><i class="fas fa-font"></i> Typography</h3>
                        <p>Complete typography system with headings, paragraphs, and text formatting options.</p>
                        
                        <h4>Required Files</h4>
                        <div class="usage-requirements">
                            <div class="requirement-item">
                                <i class="fas fa-link"></i>
                                <span><strong>CSS:</strong> css/styles.css (main styles)</span>
                            </div>
                            <div class="requirement-item">
                                <i class="fas fa-link"></i>
                                <span><strong>CSS:</strong> css/pages/content.css (content styles)</span>
                            </div>
                            <div class="requirement-item">
                                <i class="fas fa-link"></i>
                                <span><strong>Optional:</strong> FontAwesome icons for content elements</span>
                            </div>
                        </div>
                        
                        <h4>Headings</h4>
                        <div class="typography-showcase">
                            <h1>Heading 1</h1>
                            <h2>Heading 2</h2>
                            <h3>Heading 3</h3>
                            <h4>Heading 4</h4>
                            <h5>Heading 5</h5>
                            <h6>Heading 6</h6>
                        </div>
                        
                        <h4>Paragraph Styles</h4>
                        <div class="typography-showcase">
                            <p>This is a standard paragraph with <strong>bold text</strong> and <em>italic text</em>. You can also use <u>underline</u> and <mark>highlighted text</mark> for emphasis.</p>
                            
                            <p class="lead">This is a lead paragraph that stands out with larger font size and lighter weight. Perfect for introductory content.</p>
                            
                            <p class="text-muted">This is muted text with reduced opacity for secondary information.</p>
                            
                            <p class="text-center">This text is centered in its container.</p>
                            
                            <p class="text-right">This text is aligned to the right.</p>
                        </div>
                        
                        <h4>Text Elements</h4>
                        <div class="typography-showcase">
                            <p><abbr title="abbreviation">abbr</abbr> element for abbreviations</p>
                            <p><cite>citation</cite> element for citations</p>
                            <p><code>code</code> element for inline code</p>
                            <p><del>deleted text</del> and <ins>inserted text</ins></p>
                            <p><small>small text</small> for less important content</p>
                            <p><strong>strong text</strong> for important content</p>
                            <p><em>emphasized text</em> for stress emphasis</p>
                            <p><sub>subscript</sub> and <sup>superscript</sup> text</p>
                        </div>
                        
                        <h4>Blockquotes</h4>
                        <div class="typography-showcase">
                            <blockquote>
                                <p>"The best way to predict the future is to invent it."</p>
                                <footer>— Alan Kay</footer>
                            </blockquote>
                            
                            <blockquote class="blockquote-reverse">
                                <p>"Design is not just what it looks like and feels like. Design is how it works."</p>
                                <footer>— Steve Jobs</footer>
                            </blockquote>
                        </div>
                        
                        <h4>Lists</h4>
                        <div class="lists-showcase">
                            <div class="list-example">
                                <h5>Unordered List</h5>
                                <ul>
                                    <li>List item one</li>
                                    <li>List item two
                                        <ul>
                                            <li>Nested item one</li>
                                            <li>Nested item two</li>
                                        </ul>
                                    </li>
                                    <li>List item three</li>
                                </ul>
                            </div>
                            
                            <div class="list-example">
                                <h5>Ordered List</h5>
                                <ol>
                                    <li>First step</li>
                                    <li>Second step
                                        <ol>
                                            <li>Sub-step one</li>
                                            <li>Sub-step two</li>
                                        </ol>
                                    </li>
                                    <li>Third step</li>
                                </ol>
                            </div>
                            
                            <div class="list-example">
                                <h5>Description List</h5>
                                <dl>
                                    <dt>HTML</dt>
                                    <dd>HyperText Markup Language</dd>
                                    <dt>CSS</dt>
                                    <dd>Cascading Style Sheets</dd>
                                    <dt>JavaScript</dt>
                                    <dd>Programming language for web interactivity</dd>
                                </dl>
                            </div>
                        </div>
                    </div>

                    <!-- ===================================
                       ALERTS SECTION
                       =================================== -->
                    <div id="alerts" class="component-section">
                        <h3><i class="fas fa-exclamation-triangle"></i> Alert Components</h3>
                        <p>Beautiful alert notifications using SweetAlert2 for user feedback and confirmations.</p>
                        
                        <h4>Required Files</h4>
                        <div class="usage-requirements">
                            <div class="requirement-item">
                                <i class="fas fa-link"></i>
                                <span><strong>CSS:</strong> css/styles.css (base styles)</span>
                            </div>
                            <div class="requirement-item">
                                <i class="fas fa-link"></i>
                                <span><strong>CSS:</strong> css/pages/content.css (content styles)</span>
                            </div>
                            <div class="requirement-item">
                                <i class="fas fa-link"></i>
                                <span><strong>SweetAlert2 CSS:</strong> https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css</span>
                            </div>
                            <div class="requirement-item">
                                <i class="fas fa-code"></i>
                                <span><strong>SweetAlert2 JS:</strong> https://cdn.jsdelivr.net/npm/sweetalert2@11</span>
                            </div>
                        </div>
                        
                        <h4>Implementation Steps:</h4>
                        <div class="implementation-steps">
                            <div class="step-item">
                                <div class="step-number">1</div>
                                <div class="step-content">
                                    <strong>Include SweetAlert2 Files</strong>
                                    <pre><code>&lt;!-- CSS --&gt;
&lt;link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css"&gt;

&lt;!-- JavaScript --&gt;
&lt;script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"&gt;&lt;/script&gt;</code></pre>
                                </div>
                            </div>
                            <div class="step-item">
                                <div class="step-number">2</div>
                                <div class="step-content">
                                    <strong>Basic SweetAlert Usage</strong>
                                    <pre><code>// Simple alert
Swal.fire('Hello World!');

// With title and text
Swal.fire({
    title: 'Success!',
    text: 'Your changes have been saved.',
    icon: 'success'
});</code></pre>
                                </div>
                            </div>
                            <div class="step-item">
                                <div class="step-number">3</div>
                                <div class="step-content">
                                    <strong>Advanced Configuration</strong>
                                    <pre><code>Swal.fire({
    title: 'Are you sure?',
    text: "This action cannot be undone!",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#4c8a89',
    cancelButtonColor: '#6c757d',
    confirmButtonText: 'Yes, delete it!'
}).then((result) => {
    if (result.isConfirmed) {
        Swal.fire('Deleted!', 'Your file has been deleted.', 'success');
    }
});</code></pre>
                                </div>
                            </div>
                            <div class="step-item">
                                <div class="step-number">4</div>
                                <div class="step-content">
                                    <strong>Create Custom Alert Function</strong>
                                    <pre><code>function showSweetAlert(type) {
    const configs = {
        success: {
            title: 'Success!',
            text: 'Operation completed successfully.',
            icon: 'success',
            confirmButtonColor: '#4c8a89'
        },
        warning: {
            title: 'Warning!',
            text: 'Please review your input.',
            icon: 'warning',
            confirmButtonColor: '#ffc107'
        },
        error: {
            title: 'Error!',
            text: 'Something went wrong.',
            icon: 'error',
            confirmButtonColor: '#dc3545'
        },
        info: {
            title: 'Information',
            text: 'New features available.',
            icon: 'info',
            confirmButtonColor: '#17a2b8'
        }
    };

    Swal.fire(configs[type]);
}</code></pre>
                                </div>
                            </div>
                        </div>
                        
                        <h4>Alert Examples</h4>
                        <div class="alerts-showcase">
                            <button class="btn btn-success" onclick="showSweetAlert('success')">Show Success</button>
                            <button class="btn btn-warning" onclick="showSweetAlert('warning')">Show Warning</button>
                            <button class="btn btn-danger" onclick="showSweetAlert('error')">Show Error</button>
                            <button class="btn btn-info" onclick="showSweetAlert('info')">Show Info</button>
                            <button class="btn btn-primary" onclick="showConfirmDialog()">Show Confirm</button>
                            <button class="btn btn-secondary" onclick="showToast()">Show Toast</button>
                        </div>
                    </div>

                    <!-- ===================================
                       INLINE ALERTS SECTION
                       =================================== -->
                    <div id="inline-alerts" class="component-section">
                        <h3><i class="fas fa-info-circle"></i> Inline Alerts</h3>
                        <p>Traditional inline alert components for persistent messages and notifications.</p>
                        
                        <h4>Alert Variants</h4>
                        <div class="alerts-showcase">
                            <div class="alert alert-success">
                                <strong>Success!</strong> Your changes have been saved successfully.
                            </div>
                            <div class="alert alert-warning">
                                <strong>Warning!</strong> Please review your input before proceeding.
                            </div>
                            <div class="alert alert-danger">
                                <strong>Error!</strong> Something went wrong. Please try again.
                            </div>
                            <div class="alert alert-info">
                                <strong>Info:</strong> New features are available in this version.
                            </div>
                            <div class="alert alert-secondary">
                                <strong>Note:</strong> This is a secondary alert message.
                            </div>
                        </div>
                        
                        <h4>Dismissible Alerts</h4>
                        <div class="alerts-showcase">
                            <div class="alert alert-success alert-dismissible">
                                <strong>Success!</strong> This alert can be dismissed.
                                <button class="alert-close" onclick="this.parentElement.style.display='none'">&times;</button>
                            </div>
                            <div class="alert alert-info alert-dismissible">
                                <strong>Info:</strong> Click the × button to dismiss this alert.
                                <button class="alert-close" onclick="this.parentElement.style.display='none'">&times;</button>
                            </div>
                        </div>
                    </div>

                    <!-- ===================================
                       IMPLEMENTATION GUIDE SECTION
                       =================================== -->
                    <div id="implementation" class="component-section">
                        <h3><i class="fas fa-code"></i> Implementation Guide</h3>
                        <p>Step-by-step instructions for implementing content elements in your project.</p>
                        
                        <h4>Typography Implementation:</h4>
                        <div class="implementation-steps">
                            <div class="step-item">
                                <div class="step-number">1</div>
                                <div class="step-content">
                                    <strong>Include Required CSS</strong>
                                    <pre><code>&lt;link rel="stylesheet" href="css/styles.css"&gt;
&lt;link rel="stylesheet" href="css/pages/content.css"&gt;</code></pre>
                                </div>
                            </div>
                            <div class="step-item">
                                <div class="step-number">2</div>
                                <div class="step-content">
                                    <strong>Use Semantic HTML</strong>
                                    <pre><code>&lt;h1&gt;Main Heading&lt;/h1&gt;
&lt;h2&gt;Section Heading&lt;/h2&gt;
&lt;p&gt;Paragraph text with &lt;strong&gt;bold&lt;/strong&gt; and &lt;em&gt;italic&lt;/em&gt;.&lt;/p&gt;
&lt;blockquote&gt;
    &lt;p&gt;Quote text here&lt;/p&gt;
    &lt;footer&gt;— Author&lt;/footer&gt;
&lt;/blockquote&gt;</code></pre>
                                </div>
                            </div>
                            <div class="step-item">
                                <div class="step-number">3</div>
                                <div class="step-content">
                                    <strong>Add Text Utilities</strong>
                                    <pre><code>&lt;p class="lead"&gt;Lead paragraph&lt;/p&gt;
&lt;p class="text-muted"&gt;Muted text&lt;/p&gt;
&lt;p class="text-center"&gt;Centered text&lt;/p&gt;
&lt;p class="text-right"&gt;Right-aligned text&lt;/p&gt;</code></pre>
                                </div>
                            </div>
                        </div>
                        
                        <h4>Inline Alerts Implementation:</h4>
                        <div class="implementation-steps">
                            <div class="step-item">
                                <div class="step-number">1</div>
                                <div class="step-content">
                                    <strong>Basic Alert Structure</strong>
                                    <pre><code>&lt;div class="alert alert-success"&gt;
    &lt;strong&gt;Success!&lt;/strong&gt; Message here.
&lt;/div&gt;</code></pre>
                                </div>
                            </div>
                            <div class="step-item">
                                <div class="step-number">2</div>
                                <div class="step-content">
                                    <strong>Dismissible Alert</strong>
                                    <pre><code>&lt;div class="alert alert-warning alert-dismissible"&gt;
    &lt;strong&gt;Warning!&lt;/strong&gt; Message here.
    &lt;button class="alert-close" onclick="this.parentElement.style.display='none'"&gt;&times;&lt;/button&gt;
&lt;/div&gt;</code></pre>
                                </div>
                            </div>
                            <div class="step-item">
                                <div class="step-number">3</div>
                                <div class="step-content">
                                    <strong>Alert Variants</strong>
                                    <pre><code>alert-success    // Green success alert
alert-warning    // Yellow warning alert
alert-danger     // Red error alert
alert-info       // Blue info alert
alert-secondary  // Gray secondary alert</code></pre>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php include('includes/admin-footer.php') ?>
    </div>
    <!-- ===================================
   JAVASCRIPT FUNCTIONALITY - SweetAlert implementations
   =================================== -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // SweetAlert Functions
        function showSweetAlert(type) {
            const configs = {
                success: {
                    title: 'Success!',
                    text: 'Your changes have been saved successfully.',
                    icon: 'success',
                    confirmButtonColor: '#4c8a89'
                },
                warning: {
                    title: 'Warning!',
                    text: 'Please review your input before proceeding.',
                    icon: 'warning',
                    confirmButtonColor: '#ffc107'
                },
                error: {
                    title: 'Error!',
                    text: 'Something went wrong. Please try again.',
                    icon: 'error',
                    confirmButtonColor: '#dc3545'
                },
                info: {
                    title: 'Information',
                    text: 'New features are available in this version.',
                    icon: 'info',
                    confirmButtonColor: '#17a2b8'
                }
            };

            Swal.fire(configs[type]);
        }

        function showConfirmDialog() {
            Swal.fire({
                title: 'Are you sure?',
                text: "You won't be able to revert this!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#4c8a89',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Confirmed!',
                        text: 'Action completed successfully.',
                        icon: 'success',
                        confirmButtonColor: '#4c8a89'
                    });
                }
            });
        }

        function showToast() {
            Swal.fire({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true,
                didOpen: (toast) => {
                    toast.addEventListener('mouseenter', Swal.stopTimer)
                    toast.addEventListener('mouseleave', Swal.resumeTimer)
                },
                icon: 'success',
                title: 'Operation completed successfully!'
            });
        }
    </script>
</body>
</html>