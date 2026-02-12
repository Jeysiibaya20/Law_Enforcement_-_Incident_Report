<?php
/**
 * Buttons Page with Sidebar Integration Only
 * Sample page demonstrating buttons with sidebar navigation (no header)
 */

$pageTitle = 'Cards - Sidebar Demo';
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
    <link rel="stylesheet" href="css/cards.css">
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
                            <span>UI Components Library</span>
                        </li>
                    </ol>
                </nav>
                <h1>UI Components Library</h1>
                <p>A comprehensive collection of reusable UI components built with modern CSS and JavaScript. Perfect for building beautiful, responsive web applications.</p>
            </div>
            <div class="sub-container">
                <div class="page-content">
                     <!-- ===================================
                       BASIC CARDS SECTION
                       =================================== -->
                    <div id="basic-cards" class="component-section">
                        <h3><i class="fas fa-square"></i> Basic Cards</h3>
                        <p>Simple card components with headers, body content, and footers for organizing content.</p>
                        
                        <h4>Required Files</h4>
                        <div class="usage-requirements">
                            <div class="requirement-item">
                                <i class="fas fa-link"></i>
                                <span><strong>CSS:</strong> css/styles.css (main styles)</span>
                            </div>
                            <div class="requirement-item">
                                <i class="fas fa-link"></i>
                                <span><strong>CSS:</strong> css/pages/cards.css (card styles)</span>
                            </div>
                            <div class="requirement-item">
                                <i class="fas fa-link"></i>
                                <span><strong>Optional:</strong> FontAwesome icons for card content</span>
                            </div>
                        </div>
                        
                        <h4>Simple Card</h4>
                        <div class="cards-showcase">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title">Card Title</h5>
                                    <p class="card-text">Some quick example text to build on the card title and make up the bulk of the card's content.</p>
                                    <a href="#" class="btn btn-primary">Go somewhere</a>
                                </div>
                            </div>
                            
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title">Card with Icon</h5>
                                    <p class="card-text">
                                        <i class="fas fa-info-circle"></i>
                                        This card includes an icon in the content for visual enhancement.
                                    </p>
                                    <a href="#" class="btn btn-secondary">Learn More</a>
                                </div>
                            </div>
                        </div>
                        
                        <h4>Card with Header and Footer</h4>
                        <div class="cards-showcase">
                            <div class="card">
                                <div class="card-header">
                                    Featured
                                </div>
                                <div class="card-body">
                                    <h5 class="card-title">Special Title Treatment</h5>
                                    <p class="card-text">With supporting text below as a natural lead-in to additional content.</p>
                                    <a href="#" class="btn btn-primary">Go somewhere</a>
                                </div>
                                <div class="card-footer">
                                    <small class="text-muted">Last updated 3 mins ago</small>
                                </div>
                            </div>
                        </div>
                        
                        <h4>Card with Image</h4>
                        <div class="cards-showcase">
                            <div class="card">
                                <img src="https://picsum.photos/seed/card1/400/200.jpg" class="card-img-top" alt="Card image">
                                <div class="card-body">
                                    <h5 class="card-title">Card with Image</h5>
                                    <p class="card-text">This card has an image at the top and content below it.</p>
                                    <p class="card-text"><small class="text-muted">Last updated 3 mins ago</small></p>
                                </div>
                            </div>
                            
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title">Card with Bottom Image</h5>
                                    <p class="card-text">This card has content first and an image at the bottom.</p>
                                </div>
                                <img src="https://picsum.photos/seed/card2/400/200.jpg" class="card-img-bottom" alt="Card image">
                            </div>
                        </div>
                    </div>

                    <!-- ===================================
                       CARD STYLES SECTION
                       =================================== -->
                    <div id="card-styles" class="component-section">
                        <h3><i class="fas fa-palette"></i> Card Styles</h3>
                        <p>Different card styles and variations for various design needs.</p>
                        
                        <h4>Card Variants</h4>
                        <div class="cards-showcase">
                            <div class="card card-primary">
                                <div class="card-body">
                                    <h5 class="card-title">Primary Card</h5>
                                    <p class="card-text">This card uses the primary color scheme for emphasis.</p>
                                </div>
                            </div>
                            
                            <div class="card card-success">
                                <div class="card-body">
                                    <h5 class="card-title">Success Card</h5>
                                    <p class="card-text">This card uses the success color scheme for positive content.</p>
                                </div>
                            </div>
                            
                            <div class="card card-warning">
                                <div class="card-body">
                                    <h5 class="card-title">Warning Card</h5>
                                    <p class="card-text">This card uses the warning color scheme for cautionary content.</p>
                                </div>
                            </div>
                            
                            <div class="card card-danger">
                                <div class="card-body">
                                    <h5 class="card-title">Danger Card</h5>
                                    <p class="card-text">This card uses the danger color scheme for negative content.</p>
                                </div>
                            </div>
                        </div>
                        
                        <h4>Outline Cards</h4>
                        <div class="cards-showcase">
                            <div class="card card-outline-primary">
                                <div class="card-body">
                                    <h5 class="card-title">Outline Primary</h5>
                                    <p class="card-text">This card has a primary outline style.</p>
                                </div>
                            </div>
                            
                            <div class="card card-outline-secondary">
                                <div class="card-body">
                                    <h5 class="card-title">Outline Secondary</h5>
                                    <p class="card-text">This card has a secondary outline style.</p>
                                </div>
                            </div>
                        </div>
                        
                        <h4>Card Sizes</h4>
                        <div class="cards-showcase">
                            <div class="card card-sm">
                                <div class="card-body">
                                    <h5 class="card-title">Small Card</h5>
                                    <p class="card-text">This is a smaller card with reduced padding.</p>
                                </div>
                            </div>
                            
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title">Normal Card</h5>
                                    <p class="card-text">This is a standard-sized card with normal padding.</p>
                                </div>
                            </div>
                            
                            <div class="card card-lg">
                                <div class="card-body">
                                    <h5 class="card-title">Large Card</h5>
                                    <p class="card-text">This is a larger card with increased padding and font sizes.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ===================================
                       CARD LAYOUTS SECTION
                       =================================== -->
                    <div id="card-layouts" class="component-section">
                        <h3><i class="fas fa-th-large"></i> Card Layouts</h3>
                        <p>Different card layouts and arrangements for various content organization needs.</p>
                        
                        <h4>Card Grid</h4>
                        <div class="card-grid">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title">Grid Card 1</h5>
                                    <p class="card-text">Cards arranged in a grid layout for organized content display.</p>
                                </div>
                            </div>
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title">Grid Card 2</h5>
                                    <p class="card-text">Grid layout automatically adjusts to available space.</p>
                                </div>
                            </div>
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title">Grid Card 3</h5>
                                    <p class="card-text">Perfect for showcasing multiple items consistently.</p>
                                </div>
                            </div>
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title">Grid Card 4</h5>
                                    <p class="card-text">Responsive grid adapts to different screen sizes.</p>
                                </div>
                            </div>
                        </div>
                        
                        <h4>Card Group</h4>
                        <div class="card-group">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title">Group Card 1</h5>
                                    <p class="card-text">Cards in a group share borders and heights.</p>
                                </div>
                            </div>
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title">Group Card 2</h5>
                                    <p class="card-text">Group layout creates a unified appearance.</p>
                                </div>
                            </div>
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title">Group Card 3</h5>
                                    <p class="card-text">Ideal for related content items.</p>
                                </div>
                            </div>
                        </div>
                        
                        <h4>Card Deck</h4>
                        <div class="card-deck">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title">Deck Card 1</h5>
                                    <p class="card-text">Cards in a deck have equal width and height.</p>
                                </div>
                            </div>
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title">Deck Card 2</h5>
                                    <p class="card-text">Deck layout ensures consistent card dimensions.</p>
                                </div>
                            </div>
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title">Deck Card 3</h5>
                                    <p class="card-text">Great for comparison and overview layouts.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ===================================
                       INTERACTIVE CARDS SECTION
                       =================================== -->
                    <div id="interactive-cards" class="component-section">
                        <h3><i class="fas fa-hand-pointer"></i> Interactive Cards</h3>
                        <p>Cards with interactive features like hover effects, clickable areas, and dynamic content.</p>
                        
                        <h4>Hover Effects</h4>
                        <div class="cards-showcase">
                            <div class="card card-hover">
                                <div class="card-body">
                                    <h5 class="card-title">Hover Card</h5>
                                    <p class="card-text">This card has hover effects for enhanced interactivity.</p>
                                    <a href="#" class="btn btn-primary">Hover Me</a>
                                </div>
                            </div>
                            
                            <div class="card card-lift">
                                <div class="card-body">
                                    <h5 class="card-title">Lift Card</h5>
                                    <p class="card-text">This card lifts up on hover with shadow effects.</p>
                                    <a href="#" class="btn btn-secondary">Lift Me</a>
                                </div>
                            </div>
                            
                            <div class="card card-scale">
                                <div class="card-body">
                                    <h5 class="card-title">Scale Card</h5>
                                    <p class="card-text">This card scales slightly on hover for emphasis.</p>
                                    <a href="#" class="btn btn-info">Scale Me</a>
                                </div>
                            </div>
                        </div>
                        
                        <h4>Clickable Cards</h4>
                        <div class="cards-showcase">
                            <div class="card card-clickable" onclick="showCardInfo('Card 1')">
                                <div class="card-body">
                                    <h5 class="card-title">Clickable Card</h5>
                                    <p class="card-text">Click anywhere on this card to trigger an action.</p>
                                    <small class="text-muted">Click me!</small>
                                </div>
                            </div>
                            
                            <div class="card card-clickable card-primary" onclick="showCardInfo('Primary Card')">
                                <div class="card-body">
                                    <h5 class="card-title">Clickable Primary</h5>
                                    <p class="card-text">This primary card is also clickable.</p>
                                    <small class="text-muted">Click me!</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ===================================
                       CONTENT CARDS SECTION
                       =================================== -->
                    <div id="content-cards" class="component-section">
                        <h3><i class="fas fa-file-alt"></i> Content Cards</h3>
                        <p>Specialized cards for different types of content like profiles, products, and articles.</p>
                        
                        <h4>Profile Card</h4>
                        <div class="cards-showcase">
                            <div class="card profile-card">
                                <div class="card-body text-center">
                                    <img src="https://picsum.photos/seed/avatar1/100/100.jpg" class="profile-avatar" alt="Profile">
                                    <h5 class="card-title mt-3">John Doe</h5>
                                    <p class="card-text text-muted">Web Developer</p>
                                    <p class="card-text">Passionate about creating beautiful and functional web applications.</p>
                                    <div class="profile-social">
                                        <a href="#" class="btn btn-sm btn-outline-primary"><i class="fab fa-twitter"></i></a>
                                        <a href="#" class="btn btn-sm btn-outline-primary"><i class="fab fa-linkedin"></i></a>
                                        <a href="#" class="btn btn-sm btn-outline-primary"><i class="fab fa-github"></i></a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <h4>Product Card</h4>
                        <div class="cards-showcase">
                            <div class="card product-card">
                                <img src="https://picsum.photos/seed/product1/300/200.jpg" class="card-img-top" alt="Product">
                                <div class="card-body">
                                    <h5 class="card-title">Premium Widget</h5>
                                    <p class="card-text">High-quality widget with advanced features and modern design.</p>
                                    <div class="product-price">
                                        <span class="price-current">$29.99</span>
                                        <span class="price-original">$39.99</span>
                                    </div>
                                    <div class="product-rating">
                                        <i class="fas fa-star"></i>
                                        <i class="fas fa-star"></i>
                                        <i class="fas fa-star"></i>
                                        <i class="fas fa-star"></i>
                                        <i class="fas fa-star-half-alt"></i>
                                        <span class="rating-text">(4.5)</span>
                                    </div>
                                    <button class="btn btn-primary btn-block">Add to Cart</button>
                                </div>
                            </div>
                        </div>
                        
                        <h4>Article Card</h4>
                        <div class="cards-showcase">
                            <div class="card article-card">
                                <img src="https://picsum.photos/seed/article1/400/200.jpg" class="card-img-top" alt="Article">
                                <div class="card-body">
                                    <div class="article-meta">
                                        <span class="article-category">Technology</span>
                                        <span class="article-date">March 15, 2024</span>
                                    </div>
                                    <h5 class="card-title">Building Modern Web Applications</h5>
                                    <p class="card-text">Learn the latest techniques and best practices for creating responsive and user-friendly web applications.</p>
                                    <a href="#" class="btn btn-outline-primary">Read More</a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ===================================
                       IMPLEMENTATION GUIDE SECTION
                       =================================== -->
                    <div id="implementation" class="component-section">
                        <h3><i class="fas fa-code"></i> Implementation Guide</h3>
                        <p>Step-by-step instructions for implementing card components in your project.</p>
                        
                        <h4>Implementation Steps:</h4>
                        <div class="implementation-steps">
                            <div class="step-item">
                                <div class="step-number">1</div>
                                <div class="step-content">
                                    <strong>Include Required CSS</strong>
                                    <pre><code>&lt;link rel="stylesheet" href="css/styles.css"&gt;
&lt;link rel="stylesheet" href="css/pages/cards.css"&gt;</code></pre>
                                </div>
                            </div>
                            <div class="step-item">
                                <div class="step-number">2</div>
                                <div class="step-content">
                                    <strong>Basic Card Structure</strong>
                                    <pre><code>&lt;div class="card"&gt;
    &lt;div class="card-body"&gt;
        &lt;h5 class="card-title"&gt;Card Title&lt;/h5&gt;
        &lt;p class="card-text"&gt;Card content here.&lt;/p&gt;
        &lt;a href="#" class="btn btn-primary"&gt;Action&lt;/a&gt;
    &lt;/div&gt;
&lt;/div&gt;</code></pre>
                                </div>
                            </div>
                            <div class="step-item">
                                <div class="step-number">3</div>
                                <div class="step-content">
                                    <strong>Card with Header and Footer</strong>
                                    <pre><code>&lt;div class="card"&gt;
    &lt;div class="card-header"&gt;Header&lt;/div&gt;
    &lt;div class="card-body"&gt;
        &lt;h5 class="card-title"&gt;Title&lt;/h5&gt;
        &lt;p class="card-text"&gt;Content&lt;/p&gt;
    &lt;/div&gt;
    &lt;div class="card-footer"&gt;Footer&lt;/div&gt;
&lt;/div&gt;</code></pre>
                                </div>
                            </div>
                            <div class="step-item">
                                <div class="step-number">4</div>
                                <div class="step-content">
                                    <strong>Card with Image</strong>
                                    <pre><code>&lt;div class="card"&gt;
    &lt;img src="image.jpg" class="card-img-top" alt="Image"&gt;
    &lt;div class="card-body"&gt;
        &lt;h5 class="card-title"&gt;Title&lt;/h5&gt;
        &lt;p class="card-text"&gt;Content&lt;/p&gt;
    &lt;/div&gt;
&lt;/div&gt;</code></pre>
                                </div>
                            </div>
                            <div class="step-item">
                                <div class="step-number">5</div>
                                <div class="step-content">
                                    <strong>Card Variants</strong>
                                    <pre><code>&lt;!-- Color variants --&gt;
&lt;div class="card card-primary"&gt;...&lt;/div&gt;
&lt;div class="card card-success"&gt;...&lt;/div&gt;
&lt;div class="card card-warning"&gt;...&lt;/div&gt;
&lt;div class="card card-danger"&gt;...&lt;/div&gt;

&lt;!-- Outline variants --&gt;
&lt;div class="card card-outline-primary"&gt;...&lt;/div&gt;
&lt;div class="card card-outline-secondary"&gt;...&lt;/div&gt;

&lt;!-- Size variants --&gt;
&lt;div class="card card-sm"&gt;...&lt;/div&gt;
&lt;div class="card card-lg"&gt;...&lt;/div&gt;</code></pre>
                                </div>
                            </div>
                            <div class="step-item">
                                <div class="step-number">6</div>
                                <div class="step-content">
                                    <strong>Card Layouts</strong>
                                    <pre><code>&lt;!-- Card Grid --&gt;
&lt;div class="card-grid"&gt;
    &lt;div class="card"&gt;...&lt;/div&gt;
    &lt;div class="card"&gt;...&lt;/div&gt;
&lt;/div&gt;

&lt;!-- Card Group --&gt;
&lt;div class="card-group"&gt;
    &lt;div class="card"&gt;...&lt;/div&gt;
    &lt;div class="card"&gt;...&lt;/div&gt;
&lt;/div&gt;

&lt;!-- Card Deck --&gt;
&lt;div class="card-deck"&gt;
    &lt;div class="card"&gt;...&lt;/div&gt;
    &lt;div class="card"&gt;...&lt;/div&gt;
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

    <!-- ===================================
   JAVASCRIPT FUNCTIONALITY - Card interactions
   =================================== -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Show card information
        function showCardInfo(cardName) {
            Swal.fire({
                title: 'Card Clicked!',
                text: `You clicked on ${cardName}`,
                icon: 'info',
                confirmButtonColor: '#4c8a89'
            });
        }
    </script>

</body>
</html>