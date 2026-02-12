// Mobile Menu JavaScript
class MobileMenu {
    constructor() {
        this.menuToggle = document.querySelector('.mobile-menu-toggle');
        this.mobileNav = document.querySelector('.mobile-nav');
        
        this.isOpen = false;
        this.init();
    }

    init() {
        if (this.menuToggle) {
            this.menuToggle.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                this.toggle();
            });
        }
        
        // Close menu when clicking on links
        const mobileLinks = document.querySelectorAll('.mobile-nav-link');
        mobileLinks.forEach(link => {
            link.addEventListener('click', () => this.close());
        });
        
        // Close on escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.isOpen) {
                this.close();
            }
        });
        
        // Close when clicking outside the menu
        document.addEventListener('click', (e) => {
            if (this.isOpen && 
                this.mobileNav && 
                !this.mobileNav.contains(e.target) && 
                this.menuToggle && 
                !this.menuToggle.contains(e.target)) {
                this.close();
            }
        });
    }

    toggle() {
        if (this.isOpen) {
            this.close();
        } else {
            this.open();
        }
    }

    open() {
        if (this.mobileNav) {
            this.mobileNav.classList.add('active');
            document.body.style.overflow = 'hidden';
            this.isOpen = true;
            
            // Update hamburger icon
            this.menuToggle.innerHTML = '<i class="fas fa-times"></i>';
        }
    }

    close() {
        if (this.mobileNav) {
            this.mobileNav.classList.remove('active');
            document.body.style.overflow = '';
            this.isOpen = false;
            
            // Update hamburger icon
            this.menuToggle.innerHTML = '<i class="fas fa-bars"></i>';
        }
    }
}

// Initialize mobile menu when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.mobileMenu = new MobileMenu();
});

// Make it globally accessible
window.MobileMenu = MobileMenu;
