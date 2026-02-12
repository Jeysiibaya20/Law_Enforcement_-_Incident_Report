<?php 
/**
 * LGU #4 - Forms Component Page
 * Dedicated page showcasing form components and validation
 * 
 * This page demonstrates:
 * - Form layouts and structures
 * - Input field types and validation states
 * - Form submission examples
 * - Form styling and best practices
 * 
 * @version 1.0.0
 * @author LGU #4 Development Team
 */

// Include header with navigation and theme functionality
include 'includes/header.php'; ?>

<!-- Page-specific CSS for forms -->
<link rel="stylesheet" href="css/forms.css">
<link rel="stylesheet" href="css/hero.css">

<!-- ===================================
   HERO SECTION - Page-specific hero for forms
   =================================== -->
    <div class="hero-section">
        <div class="main-container">
            <div class="sub-container">
                <h1>Form Components</h1>
                <p>Complete form layouts with various input types, validation states, and submission handling. Perfect for user data collection and interaction.</p>
                
                <div class="hero-buttons">
                    <a href="#form-layouts" class="btn btn-primary">View Layouts</a>
                    <a href="buttons.php" class="btn btn-secondary">See Buttons</a>
                    <a href="#implementation" class="btn btn-outline-primary">How to Use</a>
                </div>
            </div>
        </div>
    </div>

<!-- ===================================
   MAIN CONTENT - Form demonstrations and documentation
   =================================== -->
    <div class="main-content">
        <div class="main-container">
            <div class="sub-container">
                <div class="page-content">

                    <!-- ===================================
                       FORM LAYOUTS SECTION
                       =================================== -->
                    <div id="form-layouts" class="component-section">
                        <h3><i class="fas fa-clipboard-list"></i> Form Layouts</h3>
                        <p>Different form layouts for various use cases and screen sizes.</p>
                        
                        <h4>Required Files</h4>
                        <div class="usage-requirements">
                            <div class="requirement-item">
                                <i class="fas fa-link"></i>
                                <span><strong>CSS:</strong> css/styles.css (main styles)</span>
                            </div>
                            <div class="requirement-item">
                                <i class="fas fa-link"></i>
                                <span><strong>CSS:</strong> css/pages/forms.css (form-specific styles)</span>
                            </div>
                            <div class="requirement-item">
                                <i class="fas fa-code"></i>
                                <span><strong>Optional:</strong> JavaScript for validation and interactions</span>
                            </div>
                        </div>
                        
                        <h4>Registration Form</h4>
                        <div class="forms-showcase">
                            <form class="form-demo">
                                <div class="form-group">
                                    <label class="form-label">Full Name</label>
                                    <input type="text" class="form-control" placeholder="John Doe" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Email Address</label>
                                    <input type="email" class="form-control" placeholder="john@example.com" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Password</label>
                                    <input type="password" class="form-control" placeholder="Enter password" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Confirm Password</label>
                                    <input type="password" class="form-control" placeholder="Confirm password" required>
                                </div>
                                <button type="submit" class="btn btn-primary">Register</button>
                            </form>
                        </div>
                        
                        <h4>Contact Form</h4>
                        <div class="forms-showcase">
                            <form class="form-demo">
                                <div class="form-group">
                                    <label class="form-label">Name</label>
                                    <input type="text" class="form-control" placeholder="Your Name">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" placeholder="your.email@example.com">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Subject</label>
                                    <select class="form-control">
                                        <option>General Inquiry</option>
                                        <option>Technical Support</option>
                                        <option>Sales Question</option>
                                        <option>Feedback</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Message</label>
                                    <textarea class="form-control" placeholder="Type your message here..." rows="4"></textarea>
                                </div>
                                <button type="submit" class="btn btn-secondary">Send Message</button>
                            </form>
                        </div>
                    </div>

                    <!-- ===================================
                       INPUT TYPES SECTION
                       =================================== -->
                    <div id="input-types" class="component-section">
                        <h3><i class="fas fa-keyboard"></i> Input Types</h3>
                        <p>Various input field types with different states and validation styles.</p>
                        
                        <h4>Basic Input Fields</h4>
                        <div class="forms-showcase">
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
                                <label class="form-label">Date Input</label>
                                <input type="date" class="form-control">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Time Input</label>
                                <input type="time" class="form-control">
                            </div>
                        </div>
                        
                        <h4>Text Areas</h4>
                        <div class="forms-showcase">
                            <div class="form-group">
                                <label class="form-label">Message</label>
                                <textarea class="form-control" placeholder="Type your message here..." rows="4"></textarea>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Comments</label>
                                <textarea class="form-control" placeholder="Enter your comments..." rows="6"></textarea>
                            </div>
                        </div>
                        
                        <h4>Select Dropdowns</h4>
                        <div class="forms-showcase">
                            <div class="form-group">
                                <label class="form-label">Country</label>
                                <select class="form-control">
                                    <option>Select a country</option>
                                    <option>United States</option>
                                    <option>Canada</option>
                                    <option>United Kingdom</option>
                                    <option>Australia</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Multiple Select</label>
                                <select class="form-control" multiple>
                                    <option>Option 1</option>
                                    <option>Option 2</option>
                                    <option>Option 3</option>
                                    <option>Option 4</option>
                                </select>
                            </div>
                        </div>
                        
                        <h4>Checkboxes and Radio Buttons</h4>
                        <div class="forms-showcase">
                            <div class="form-group">
                                <label class="form-label">Interests</label>
                                <div class="checkbox-group">
                                    <label class="checkbox-label">
                                        <input type="checkbox" class="form-checkbox">
                                        <span class="checkbox-text">Development</span>
                                    </label>
                                    <label class="checkbox-label">
                                        <input type="checkbox" class="form-checkbox">
                                        <span class="checkbox-text">Design</span>
                                    </label>
                                    <label class="checkbox-label">
                                        <input type="checkbox" class="form-checkbox">
                                        <span class="checkbox-text">Marketing</span>
                                    </label>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Preferred Contact Method</label>
                                <div class="radio-group">
                                    <label class="radio-label">
                                        <input type="radio" name="contact" class="form-radio" checked>
                                        <span class="radio-text">Email</span>
                                    </label>
                                    <label class="radio-label">
                                        <input type="radio" name="contact" class="form-radio">
                                        <span class="radio-text">Phone</span>
                                    </label>
                                    <label class="radio-label">
                                        <input type="radio" name="contact" class="form-radio">
                                        <span class="radio-text">SMS</span>
                                    </label>
                                </div>
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
                        <div class="forms-showcase">
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
                        </div>
                    </div>

                    <!-- ===================================
                       IMPLEMENTATION GUIDE SECTION
                       =================================== -->
                    <div id="implementation" class="component-section">
                        <h3><i class="fas fa-code"></i> Implementation Guide</h3>
                        <p>Step-by-step instructions for implementing forms in your project.</p>
                        
                        <h4>Implementation Steps:</h4>
                        <div class="implementation-steps">
                            <div class="step-item">
                                <div class="step-number">1</div>
                                <div class="step-content">
                                    <strong>Include Required CSS</strong>
                                    <pre><code>&lt;link rel="stylesheet" href="css/styles.css"&gt;
&lt;link rel="stylesheet" href="css/pages/forms.css"&gt;</code></pre>
                                </div>
                            </div>
                            <div class="step-item">
                                <div class="step-number">2</div>
                                <div class="step-content">
                                    <strong>Basic Form Structure</strong>
                                    <pre><code>&lt;form class="form-demo"&gt;
    &lt;div class="form-group"&gt;
        &lt;label class="form-label"&gt;Field Name&lt;/label&gt;
        &lt;input type="text" class="form-control" placeholder="Enter text..."&gt;
    &lt;/div&gt;
    &lt;button type="submit" class="btn btn-primary"&gt;Submit&lt;/button&gt;
&lt;/form&gt;</code></pre>
                                </div>
                            </div>
                            <div class="step-item">
                                <div class="step-number">3</div>
                                <div class="step-content">
                                    <strong>Add Validation States</strong>
                                    <pre><code>&lt;input class="form-control is-success"&gt;  <!-- Valid -->
&lt;input class="form-control is-error"&gt;    <!-- Invalid -->
&lt;input class="form-control" disabled&gt;     <!-- Disabled --></code></pre>
                                </div>
                            </div>
                            <div class="step-item">
                                <div class="step-number">4</div>
                                <div class="step-content">
                                    <strong>Add JavaScript Validation (Optional)</strong>
                                    <pre><code>function validateForm() {
    const input = document.querySelector('.form-control');
    if (input.value === '') {
        input.classList.add('is-error');
        return false;
    }
    input.classList.add('is-success');
    return true;
}</code></pre>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

<!-- ===================================
   FOOTER INCLUDE
   =================================== -->
<?php include 'includes/footer.php'; ?>
