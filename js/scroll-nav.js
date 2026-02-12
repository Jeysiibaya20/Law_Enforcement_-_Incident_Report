/**
 * Scroll-based Navigation Active State
 * Handles active state highlighting for navigation based on scroll position
 */

document.addEventListener('DOMContentLoaded', function() {
    // Get all navigation links
    const navLinks = document.querySelectorAll('.nav-link');
    const mobileNavLinks = document.querySelectorAll('.mobile-nav-link');
    
    // Get all sections
    const sections = document.querySelectorAll('section[id]');
    
    // Function to update active navigation
    function updateActiveNav() {
        let current = '';
        
        // Find current section
        sections.forEach(section => {
            const sectionTop = section.offsetTop;
            const sectionHeight = section.clientHeight;
            
            if (scrollY >= (sectionTop - 200)) {
                current = section.getAttribute('id');
            }
        });
        
        // Update desktop navigation
        navLinks.forEach(link => {
            link.classList.remove('active');
            if (link.getAttribute('href') === `#${current}`) {
                link.classList.add('active');
            }
        });
        
        // Update mobile navigation
        mobileNavLinks.forEach(link => {
            link.classList.remove('active');
            if (link.getAttribute('href') === `#${current}`) {
                link.classList.add('active');
            }
        });
    }
    
    // Function to handle smooth scrolling
    function smoothScroll(e) {
        e.preventDefault();
        const targetId = this.getAttribute('href');
        const targetSection = document.querySelector(targetId);
        
        if (targetSection) {
            const headerHeight = document.querySelector('.header').offsetHeight;
            const targetPosition = targetSection.offsetTop - headerHeight;
            
            window.scrollTo({
                top: targetPosition,
                behavior: 'smooth'
            });
            
            // Close mobile nav if open
            const mobileNav = document.querySelector('.mobile-nav');
            const mobileNavOverlay = document.querySelector('.mobile-nav-overlay');
            
            if (mobileNav.classList.contains('active')) {
                mobileNav.classList.remove('active');
                mobileNavOverlay.classList.remove('active');
            }
        }
    }
    
    // Add click event listeners to navigation links
    navLinks.forEach(link => {
        link.addEventListener('click', smoothScroll);
    });
    
    mobileNavLinks.forEach(link => {
        link.addEventListener('click', smoothScroll);
    });
    
    // Update active nav on scroll
    window.addEventListener('scroll', updateActiveNav);
    
    // Initial call to set active state
    updateActiveNav();
});
