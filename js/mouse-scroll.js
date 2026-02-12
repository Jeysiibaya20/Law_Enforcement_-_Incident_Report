/**
 * Mouse Scroll Indicator
 * Handles the animated scroll indicator in the home section
 */

document.addEventListener('DOMContentLoaded', function() {
    const mouseScroll = document.querySelector('.mouse-scroll');
    
    if (!mouseScroll) return;
    
    // Hide mouse scroll indicator when user scrolls
    function handleScroll() {
        if (window.scrollY > 100) {
            mouseScroll.style.opacity = '0';
        } else {
            mouseScroll.style.opacity = '1';
        }
    }
    
    // Add smooth scroll when clicking the mouse indicator
    mouseScroll.addEventListener('click', function() {
        const homeSection = document.querySelector('.home');
        const nextSection = homeSection.nextElementSibling;
        
        if (nextSection) {
            const headerHeight = document.querySelector('.header').offsetHeight;
            const targetPosition = nextSection.offsetTop - headerHeight;
            
            window.scrollTo({
                top: targetPosition,
                behavior: 'smooth'
            });
        }
    });
    
    // Listen for scroll events
    window.addEventListener('scroll', handleScroll);
    
    // Initial check
    handleScroll();
});
