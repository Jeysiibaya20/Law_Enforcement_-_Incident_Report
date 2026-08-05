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
document.addEventListener('DOMContentLoaded', function() {
    const menuToggle = document.getElementById('menuToggle');
    const appSidebar = document.getElementById('appSidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const lightModeBtn = document.getElementById('lightModeBtn');
    const darkModeBtn = document.getElementById('darkModeBtn');
    const searchInput = document.getElementById('headerSearchInput');
    const searchDropdown = document.getElementById('searchDropdown');

    // 1. Sidebar Toggle Handler
    function toggleSidebar() {
        if (!appSidebar) return;
        if (window.innerWidth <= 992) {
            appSidebar.classList.toggle('active');
            if (sidebarOverlay) sidebarOverlay.classList.toggle('active');
        } else {
            document.body.classList.toggle('sidebar-collapsed');
        }
    }

    if (menuToggle) menuToggle.addEventListener('click', toggleSidebar);
    if (sidebarOverlay) sidebarOverlay.addEventListener('click', toggleSidebar);

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

    // 4. Header Quick Search Filter
    if (searchInput && searchDropdown) {
        const pages = [
            { name: 'Dashboard', url: '<?php echo $base_url; ?>admin/dashboard.php', category: 'Admin' },
            { name: 'Case Tracking', url: '<?php echo $base_url; ?>admin/cases.php', category: 'Cases' },
            { name: 'Blotter Records', url: '<?php echo $base_url; ?>admin/blotters.php', category: 'Incident' },
            { name: 'Account Approvals', url: '<?php echo $base_url; ?>admin/account_approvals.php', category: 'Users' },
            { name: 'Reports & Analytics', url: '<?php echo $base_url; ?>admin/reports.php', category: 'Reports' },
            { name: 'Hearing Schedule', url: '<?php echo $base_url; ?>admin/Hearing_schedule.php', category: 'Hearings' },
            { name: 'Crime Mapping', url: '<?php echo $base_url; ?>modules/crime_mapping.php', category: 'Map' },
            { name: 'File Incident Report', url: '<?php echo $base_url; ?>modules/Incident_report.php', category: 'Public' },
            { name: 'My Reports', url: '<?php echo $base_url; ?>modules/my_reports.php', category: 'Public' }
        ];

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