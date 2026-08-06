<?php
/**
 * Unified Footer & Global Script Include Component
 * Synced with EMERGENCY-COM standard design and interactive behavior
 */
?>

<!-- Shared Footer Info (if non-admin/public view) -->
<footer class="footer py-3 mt-auto border-top bg-card text-secondary" style="font-size: 0.85rem;">
    <div class="container-fluid px-4 d-flex flex-column flex-sm-row justify-content-between align-items-center">
        <div>
            <span>&copy; <?php echo date('Y'); ?> <strong>Alertara Incident & Law Enforcement System</strong>. All rights reserved.</span>
        </div>
        <div class="mt-2 mt-sm-0">
            <span class="badge bg-secondary">System Uniform Template v2.0</span>
        </div>
    </div>
</footer>

<!-- Bootstrap 5.3 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- SweetAlert2 JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- Header & Sidebar Dynamic Behavior Script -->
<script>
    // 1. Universal Mobile & Desktop Sidebar Toggle Handler
    document.addEventListener('click', function(e) {
        const toggleBtn = e.target.closest('#menuToggle, .menu-toggle, .mobile-menu-toggle');
        if (toggleBtn) {
            e.preventDefault();
            e.stopPropagation();
            
            const appSidebar = document.getElementById('sidebar') || document.getElementById('appSidebar') || document.querySelector('.sidebar');
            const sidebarOverlay = document.getElementById('sidebarOverlay') || document.querySelector('.sidebar-overlay');
            
            if (appSidebar) {
                appSidebar.classList.toggle('collapsed');
                appSidebar.classList.toggle('active');
                appSidebar.classList.toggle('show');
            }
            
            document.body.classList.toggle('sidebar-collapsed');
            
            if (window.innerWidth <= 992 && sidebarOverlay) {
                sidebarOverlay.classList.toggle('active');
                sidebarOverlay.classList.toggle('show');
                document.body.classList.toggle('sidebar-mobile-open');
            }
        }
    });

    const sidebarOverlay = document.getElementById('sidebarOverlay') || document.querySelector('.sidebar-overlay');
    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', function(e) {
            e.preventDefault();
            const appSidebar = document.getElementById('sidebar') || document.getElementById('appSidebar') || document.querySelector('.sidebar');
            if (appSidebar) {
                appSidebar.classList.remove('active', 'show', 'collapsed');
            }
            sidebarOverlay.classList.remove('active', 'show');
            document.body.classList.remove('sidebar-mobile-open', 'sidebar-collapsed');
        });
    }

    // Auto-close sidebar on mobile when clicking non-dropdown sidebar links
    const sidebarNavLinks = document.querySelectorAll('.sidebar-link:not(.sidebar-dropdown-toggle), .sidebar-submenu-link');
    sidebarNavLinks.forEach(link => {
        link.addEventListener('click', function() {
            if (window.innerWidth <= 992 && appSidebar) {
                appSidebar.classList.remove('active', 'show');
                if (sidebarOverlay) sidebarOverlay.classList.remove('active', 'show');
                document.body.classList.remove('sidebar-mobile-open');
            }
        });
    });

    // 1.5 Sidebar Submenu Accordion Handler
    const dropdownToggles = document.querySelectorAll('.sidebar-dropdown-toggle');
    dropdownToggles.forEach(toggle => {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            const parentItem = this.closest('.sidebar-menu-item');
            if (!parentItem) return;
            const submenu = parentItem.querySelector('.sidebar-submenu');
            if (!submenu) return;

            const isOpen = parentItem.classList.contains('open');

            document.querySelectorAll('.sidebar-menu-item.has-dropdown').forEach(item => {
                if (item !== parentItem) {
                    item.classList.remove('open');
                    const sub = item.querySelector('.sidebar-submenu');
                    if (sub) sub.classList.remove('show');
                }
            });

            if (isOpen) {
                parentItem.classList.remove('open');
                submenu.classList.remove('show');
            } else {
                parentItem.classList.add('open');
                submenu.classList.add('show');
            }
        });
    });

    // 2. Realtime Theme Mode Handler
    function setTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem('theme', theme);
        if (lightModeBtn && darkModeBtn) {
            lightModeBtn.classList.toggle('active', theme !== 'dark');
            darkModeBtn.classList.toggle('active', theme === 'dark');
        }
    }

    const savedTheme = localStorage.getItem('theme') || 'light';
    setTheme(savedTheme);

    if (lightModeBtn) lightModeBtn.addEventListener('click', () => setTheme('light'));
    if (darkModeBtn) darkModeBtn.addEventListener('click', () => setTheme('dark'));

    // 3. Realtime Clock Handler
    function updateClock() {
        const dateEl = document.querySelector('#headerDateTime .date-part');
        const timeEl = document.querySelector('#headerDateTime .time-part');
        if (!dateEl || !timeEl) return;

        const now = new Date();
        dateEl.textContent = now.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' });
        timeEl.textContent = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
    }
    updateClock();
    setInterval(updateClock, 1000);

    // 4. Header Quick Search Filter (role-scoped)
    if (searchInput && searchDropdown) {
        <?php
        $searchRole = strtolower(trim($_SESSION['role'] ?? 'user'));
        $currentSearchPage = strtolower(basename($_SERVER['PHP_SELF']));
        $isUserSidePage = in_array($currentSearchPage, ['landing.php', 'index.php', 'my_reports.php', 'incident_report.php', 'request_form.php', 'learning.php']) || !empty($force_public_sidebar);
        $isAdminRole = (strpos($searchRole, 'admin') !== false || strpos($searchRole, 'officer') !== false || strpos($searchRole, 'official') !== false);
        ?>
        <?php if (!$isUserSidePage && $isAdminRole): ?>
        const pages = [
            { name: 'Dashboard', url: '<?php echo $base_url; ?>admin/dashboard.php', category: 'Admin' },
            { name: 'Blotter (Digital Blotter)', url: '<?php echo $base_url; ?>admin/blotters.php', category: 'Digital Blotter' },
            { name: 'Certificate of File Action', url: '<?php echo $base_url; ?>admin/certificate_of_file_action.php', category: 'Digital Blotter' },
            { name: 'Summons', url: '<?php echo $base_url; ?>admin/Summons.php', category: 'Cases Mgmt' },
            { name: 'Hearing Schedule', url: '<?php echo $base_url; ?>admin/Hearing_schedule.php', category: 'Cases Mgmt' },
            { name: 'Hearing Result', url: '<?php echo $base_url; ?>admin/hearing_result.php', category: 'Cases Mgmt' },
            { name: 'Settlement', url: '<?php echo $base_url; ?>admin/settle.php', category: 'Cases Mgmt' },
            { name: 'Close Cases', url: '<?php echo $base_url; ?>admin/cases.php?status=Closed', category: 'Cases Mgmt' },
            { name: 'Suspects & Witnesses', url: '<?php echo $base_url; ?>admin/suspects_management.php', category: 'Management' },
            { name: 'All Cases', url: '<?php echo $base_url; ?>admin/cases.php', category: 'Cases' },
            { name: 'Account Approvals', url: '<?php echo $base_url; ?>admin/account_approvals.php', category: 'Users' },
            { name: 'Reports & Analytics', url: '<?php echo $base_url; ?>admin/reports.php', category: 'Reports' },
            { name: 'Evidence Collection', url: '<?php echo $base_url; ?>modules/evidence_collection.php', category: 'Evidence' },
            { name: 'Settings', url: '<?php echo $base_url; ?>admin/settings.php', category: 'Admin' }
        ];
        <?php else: ?>
        const pages = [
            { name: 'My Reports', url: '<?php echo $base_url; ?>modules/my_reports.php', category: 'Reports' },
            { name: 'File Incident Report', url: '<?php echo $base_url; ?>modules/Incident_report.php', category: 'Incident' },
            { name: 'Request Form', url: '<?php echo $base_url; ?>modules/Request_form.php', category: 'Services' },
            { name: 'Awareness & Guide', url: '<?php echo $base_url; ?>modules/learning.php', category: 'Learning' },
            { name: 'Resident Portal', url: '<?php echo $base_url; ?>landing.php', category: 'Home' }
        ];
        <?php endif; ?>

        searchInput.addEventListener('input', function() {
            const query = this.value.trim().toLowerCase();
            if (!query) {
                searchDropdown.style.display = 'none';
                return;
            }

            const matches = pages.filter(p => p.name.toLowerCase().includes(query) || p.category.toLowerCase().includes(query));
            if (matches.length > 0) {
                searchDropdown.innerHTML = matches.map(m => `
                    <div class="search-result-item" onclick="window.location.href='${m.url}'">
                        <i class="fas fa-search me-2 text-primary"></i>
                        <span>${m.name}</span>
                        <span class="badge bg-secondary ms-auto" style="font-size:0.7rem;">${m.category}</span>
                    </div>
                `).join('');
            } else {
                searchDropdown.innerHTML = `<div class="search-result-item text-muted"><i class="fas fa-times-circle me-2"></i>No pages found</div>`;
            }
            searchDropdown.style.display = 'block';
        });

        document.addEventListener('click', function(e) {
            if (!searchInput.contains(e.target) && !searchDropdown.contains(e.target)) {
                searchDropdown.style.display = 'none';
            }
        });
    }

    // 5. Notification Dropdown Toggle Handler
    const notifBtn = document.getElementById('notificationBtn');
    const notifDropdown = document.getElementById('notificationDropdown');
    if (notifBtn && notifDropdown) {
        notifBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            notifDropdown.classList.toggle('active');
        });
        document.addEventListener('click', function(e) {
            if (!notifDropdown.contains(e.target) && !notifBtn.contains(e.target)) {
                notifDropdown.classList.remove('active');
            }
        });
    }
});
</script>

<?php if (isset($additional_footer)) echo $additional_footer; ?>
</body>
</html>