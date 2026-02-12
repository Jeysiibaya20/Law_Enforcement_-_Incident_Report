</main>

    <footer class="footer">
        <div class="main-container">
            <div class="sub-container">
                <div class="footer-container">
            <div class="footer-main">
                <!-- Footer Brand Section -->
                <div class="footer-brand">
                    <a href="index.php" class="footer-logo">
                        <img src="images/logo.svg" alt="" class="logo-img">
                    </a>
                    <p class="footer-description">
                        Building modern web applications with clean code, responsive design, and user-friendly interfaces. Your trusted partner for digital solutions.
                    </p>
                    <div class="footer-social">
                        <a href="#" class="social-link" aria-label="Facebook">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="#" class="social-link" aria-label="Twitter">
                            <i class="fab fa-twitter"></i>
                        </a>
                        <a href="#" class="social-link" aria-label="LinkedIn">
                            <i class="fab fa-linkedin-in"></i>
                        </a>
                        <a href="#" class="social-link" aria-label="GitHub">
                            <i class="fab fa-github"></i>
                        </a>
                    </div>
                </div>
                
                <!-- Footer Columns -->
                <div class="footer-column">
                    <h4>Navigation</h4>
                    <ul class="footer-links">
                        <li><a href="index.php" class="footer-link">Home</a></li>
                        <li><a href="#buttons" class="footer-link">Components</a></li>
                        <li><a href="#forms" class="footer-link">Forms</a></li>
                        <li><a href="#datatables" class="footer-link">Data Tables</a></li>
                    </ul>
                </div>
                
                <div class="footer-column">
                    <h4>Resources</h4>
                    <ul class="footer-links">
                        <li><a href="#" class="footer-link">Documentation</a></li>
                        <li><a href="#" class="footer-link">API Reference</a></li>
                        <li><a href="#" class="footer-link">Tutorials</a></li>
                        <li><a href="#" class="footer-link">Blog</a></li>
                    </ul>
                </div>
                
                <div class="footer-column">
                    <h4>Support</h4>
                    <ul class="footer-links">
                        <li><a href="#" class="footer-link">Help Center</a></li>
                        <li><a href="#" class="footer-link">Contact Us</a></li>
                        <li><a href="#" class="footer-link">FAQ</a></li>
                        <li><a href="#" class="footer-link">Status</a></li>
                    </ul>
                </div>
                
                <div class="footer-column">
                    <h4>Company</h4>
                    <ul class="footer-links">
                        <li><a href="#" class="footer-link">About Us</a></li>
                        <li><a href="#" class="footer-link">Careers</a></li>
                        <li><a href="#" class="footer-link">Privacy Policy</a></li>
                        <li><a href="#" class="footer-link">Terms of Service</a></li>
                    </ul>
                </div>
            </div>
            
            <!-- Footer Bottom -->
            <div class="footer-bottom">
                <div class="footer-copyright">
                    <p>&copy; <?php echo date('Y'); ?> LGU #4. All rights reserved.</p>
                </div>
                
                <div class="footer-legal">
                    <a href="#" class="footer-link">Privacy Policy</a>
                    <a href="#" class="footer-link">Terms of Service</a>
                    <a href="#" class="footer-link">Cookie Policy</a>
                </div>
                
                <div class="theme-toggle">
                    <button class="theme-toggle-btn" data-theme="system" aria-label="System theme">
                        <i class="fas fa-desktop"></i>
                    </button>
                    <button class="theme-toggle-btn" data-theme="light" aria-label="Light theme">
                        <i class="fas fa-sun"></i>
                    </button>
                    <button class="theme-toggle-btn" data-theme="dark" aria-label="Dark theme">
                        <i class="fas fa-moon"></i>
                    </button>
                </div>
            </div>
        </div>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Mobile Navigation Toggle
        const mobileMenuToggle = document.querySelectorAll('.mobile-menu-toggle');
        const mobileNavClose = document.querySelector('.mobile-nav-close');
        const mobileNav = document.querySelector('.mobile-nav');
        const mobileNavOverlay = document.querySelector('.mobile-nav-overlay');

        mobileMenuToggle.forEach(toggle => {
            toggle.addEventListener('click', () => {
                mobileNav.classList.toggle('active');
                mobileNavOverlay.classList.toggle('active');
                document.body.style.overflow = mobileNav.classList.contains('active') ? 'hidden' : '';
            });
        });

        // Close button functionality
        if (mobileNavClose) {
            mobileNavClose.addEventListener('click', () => {
                mobileNav.classList.remove('active');
                mobileNavOverlay.classList.remove('active');
                document.body.style.overflow = '';
            });
        }

        mobileNavOverlay.addEventListener('click', () => {
            mobileNav.classList.remove('active');
            mobileNavOverlay.classList.remove('active');
            document.body.style.overflow = '';
        });

        // Theme Toggle
        const themeToggleBtns = document.querySelectorAll('.theme-toggle-btn');
        const htmlElement = document.documentElement;

        // Load saved theme
        const savedTheme = localStorage.getItem('theme') || 'system';
        htmlElement.setAttribute('data-theme', savedTheme);
        updateThemeButtons(savedTheme);

        themeToggleBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                const theme = btn.getAttribute('data-theme');
                htmlElement.setAttribute('data-theme', theme);
                localStorage.setItem('theme', theme);
                updateThemeButtons(theme);
                
                // Apply system theme if selected
                if (theme === 'system') {
                    applySystemTheme();
                }
            });
        });

        function updateThemeButtons(theme) {
            themeToggleBtns.forEach(btn => {
                if (btn.getAttribute('data-theme') === theme) {
                    btn.classList.add('active');
                } else {
                    btn.classList.remove('active');
                }
            });
        }

        function applySystemTheme() {
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            htmlElement.setAttribute('data-theme', prefersDark ? 'dark' : 'light');
        }

        // Listen for system theme changes
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
            const currentTheme = localStorage.getItem('theme');
            if (currentTheme === 'system') {
                applySystemTheme();
            }
        });

        // Apply system theme on load if needed
        if (savedTheme === 'system') {
            applySystemTheme();
        }

        // Smooth scroll for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                    
                    // Close mobile menu if open
                    if (mobileNav.classList.contains('active')) {
                        mobileNav.classList.remove('active');
                        mobileNavOverlay.classList.remove('active');
                        document.body.style.overflow = '';
                    }
                }
            });
        });

        // Header scroll effect - disabled to keep header always on top
        // let lastScrollTop = 0;
        // const header = document.querySelector('.header');

        // window.addEventListener('scroll', () => {
        //     const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        //     
        //     if (scrollTop > lastScrollTop && scrollTop > 100) {
        //         // Scrolling down
        //         header.style.transform = 'translateY(-100%)';
        //     } else {
        //         // Scrolling up
        //         header.style.transform = 'translateY(0)';
        //     }
        //     
        //     lastScrollTop = scrollTop;
        // });

        // Add transition for smooth header movement
        // header.style.transition = 'transform 0.3s ease-in-out';

        // ===================================
        // BUTTON ENHANCEMENTS - STATES & COLORS
        // ===================================
        document.addEventListener('DOMContentLoaded', function () {
            const buttons = document.querySelectorAll('.btn');

            buttons.forEach((btn) => {
                const label = (btn.textContent || '').trim().toLowerCase();
                const classes = btn.classList;

                // Skip if button already has a semantic variant
                const hasSemanticVariant = [
                    'btn-primary',
                    'btn-secondary',
                    'btn-success',
                    'btn-danger',
                    'btn-warning',
                    'btn-info'
                ].some((cls) => classes.contains(cls));

                if (!hasSemanticVariant) {
                    if (/delete|remove|trash|destroy|discard/.test(label)) {
                        classes.add('btn-danger');
                    } else if (/save|submit|register|create|add|update|confirm/.test(label)) {
                        classes.add('btn-success');
                    } else if (/cancel|close|back/.test(label)) {
                        classes.add('btn-secondary');
                    } else if (/warning|caution/.test(label)) {
                        classes.add('btn-warning');
                    } else if (/info|details|more info/.test(label)) {
                        classes.add('btn-info');
                    }
                }

                // Normalize disabled state for anchor-like buttons
                const explicitlyDisabled =
                    btn.hasAttribute('disabled') ||
                    classes.contains('disabled') ||
                    btn.getAttribute('aria-disabled') === 'true';

                if (explicitlyDisabled) {
                    if (!btn.hasAttribute('disabled') && btn.tagName !== 'BUTTON') {
                        btn.setAttribute('aria-disabled', 'true');
                        classes.add('is-disabled');
                        btn.tabIndex = -1;
                    }
                }
            });
        });
    </script>

    <?php if (isset($extraJS)): ?>
        <?php foreach ($extraJS as $js): ?>
            <script src="<?php echo htmlspecialchars($js); ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
