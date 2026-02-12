<?php 
/**
 * LGU #4 - Data Tables Component Page
 * Dedicated page showcasing data table components
 * 
 * This page demonstrates:
 * - Interactive data tables with search, sort, pagination
 * - Responsive table design
 * - Table styling and configurations
 * - JavaScript functionality for table interactions
 * 
 * @version 1.0.0
 * @author LGU #4 Development Team
 */

// Include header with navigation and theme functionality
include 'includes/header.php'; ?>

<!-- Page-specific CSS for data tables -->
<link rel="stylesheet" href="css/datatables.css">
<link rel="stylesheet" href="css/hero.css">

<!-- DataTables CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css">

<!-- ===================================
   HERO SECTION - Page-specific hero for data tables
   =================================== -->
    <div class="hero-section">
        <div class="main-container">
            <div class="sub-container">
                <h1>Data Table Components</h1>
                <p>Interactive data tables with search, sort, and pagination capabilities. Perfect for displaying and managing large datasets.</p>
                
                <div class="hero-buttons">
                    <a href="#basic-table" class="btn btn-primary">View Tables</a>
                    <a href="content.php" class="btn btn-secondary">See Content</a>
                    <a href="#implementation" class="btn btn-outline-primary">How to Use</a>
                </div>
            </div>
        </div>
    </div>

<!-- ===================================
   MAIN CONTENT - Data table demonstrations and documentation
   =================================== -->
    <div class="main-content">
        <div class="main-container">
            <div class="sub-container">
                <div class="page-content">

                    <!-- ===================================
                       BASIC TABLE SECTION
                       =================================== -->
                    <div id="basic-table" class="component-section">
                        <h3><i class="fas fa-table"></i> Basic Data Table</h3>
                        <p>Interactive data table with search, sorting, and pagination functionality.</p>
                        
                        <h4>Required Files</h4>
                        <div class="usage-requirements">
                            <div class="requirement-item">
                                <i class="fas fa-link"></i>
                                <span><strong>CSS:</strong> css/styles.css (main styles)</span>
                            </div>
                            <div class="requirement-item">
                                <i class="fas fa-link"></i>
                                <span><strong>CSS:</strong> css/pages/datatables.css (table styles)</span>
                            </div>
                            <div class="requirement-item">
                                <i class="fas fa-code"></i>
                                <span><strong>JavaScript:</strong> Table functionality (included)</span>
                            </div>
                            <div class="requirement-item">
                                <i class="fas fa-link"></i>
                                <span><strong>Optional:</strong> SweetAlert2 for confirm dialogs</span>
                            </div>
                        </div>
                        
                        <h4>User Management Table</h4>
                        <div class="table-controls">
                            <div class="search-box">
                                <input type="text" id="tableSearch" class="form-control" placeholder="Search users...">
                            </div>
                            <div class="table-actions">
                                <select id="pageLength" class="form-control">
                                    <option value="5">5 entries</option>
                                    <option value="10" selected>10 entries</option>
                                    <option value="25">25 entries</option>
                                    <option value="50">50 entries</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="table-wrapper">
                            <table class="data-table" id="userTable">
                                <thead>
                                    <tr>
                                        <th onclick="sortTable(0)">ID <i class="fas fa-sort"></i></th>
                                        <th onclick="sortTable(1)">Name <i class="fas fa-sort"></i></th>
                                        <th onclick="sortTable(2)">Email <i class="fas fa-sort"></i></th>
                                        <th onclick="sortTable(3)">Department <i class="fas fa-sort"></i></th>
                                        <th onclick="sortTable(4)">Status <i class="fas fa-sort"></i></th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="tableBody">
                                    <!-- Table rows will be populated by JavaScript -->
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="table-info">
                            <div class="table-info-text">
                                Showing <span id="showingStart">1</span> to <span id="showingEnd">10</span> of <span id="totalEntries">0</span> entries
                            </div>
                            <div class="pagination" id="pagination">
                                <!-- Pagination buttons will be populated by JavaScript -->
                            </div>
                        </div>
                    </div>

                    <!-- ===================================
                       ADVANCED FEATURES SECTION
                       =================================== -->
                    <div id="advanced-features" class="component-section">
                        <h3><i class="fas fa-cogs"></i> Advanced Features</h3>
                        <p>Advanced table features including row selection, bulk actions, and export functionality.</p>
                        
                        <h4>Advanced Table with Selection</h4>
                        <div class="table-controls">
                            <div class="bulk-actions">
                                <button class="btn btn-sm btn-primary" onclick="bulkEdit()">Edit Selected</button>
                                <button class="btn btn-sm btn-danger" onclick="bulkDelete()">Delete Selected</button>
                                <button class="btn btn-sm btn-secondary" onclick="exportData()">Export CSV</button>
                            </div>
                            <div class="search-box">
                                <input type="text" id="advancedSearch" class="form-control" placeholder="Search...">
                            </div>
                        </div>
                        
                        <div class="table-wrapper">
                            <table class="data-table" id="advancedTable">
                                <thead>
                                    <tr>
                                        <th>
                                            <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                                        </th>
                                        <th onclick="sortAdvancedTable(1)">Name <i class="fas fa-sort"></i></th>
                                        <th onclick="sortAdvancedTable(2)">Email <i class="fas fa-sort"></i></th>
                                        <th onclick="sortAdvancedTable(3)">Role <i class="fas fa-sort"></i></th>
                                        <th onclick="sortAdvancedTable(4)">Join Date <i class="fas fa-sort"></i></th>
                                        <th onclick="sortAdvancedTable(5)">Status <i class="fas fa-sort"></i></th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="advancedTableBody">
                                    <!-- Table rows will be populated by JavaScript -->
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- ===================================
                       RESPONSIVE TABLES SECTION
                       =================================== -->
                    <div id="responsive-tables" class="component-section">
                        <h3><i class="fas fa-mobile-alt"></i> Responsive Tables</h3>
                        <p>Mobile-friendly table designs that adapt to different screen sizes.</p>
                        
                        <h4>Card View for Mobile</h4>
                        <div class="responsive-table" id="mobileTable">
                            <!-- Mobile card view will be populated by JavaScript -->
                        </div>
                    </div>

                    <!-- ===================================
                       TABLE STYLES SECTION
                       =================================== -->
                    <div id="table-styles" class="component-section">
                        <h3><i class="fas fa-palette"></i> Table Styles</h3>
                        <p>Different table styling options for various design needs.</p>
                        
                        <h4>Striped Table</h4>
                        <div class="table-wrapper">
                            <table class="data-table table-striped">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Position</th>
                                        <th>Office</th>
                                        <th>Age</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>Tiger Nixon</td>
                                        <td>System Architect</td>
                                        <td>Edinburgh</td>
                                        <td>61</td>
                                    </tr>
                                    <tr>
                                        <td>Garrett Winters</td>
                                        <td>Accountant</td>
                                        <td>Tokyo</td>
                                        <td>63</td>
                                    </tr>
                                    <tr>
                                        <td>Ashton Cox</td>
                                        <td>Junior Technical Author</td>
                                        <td>San Francisco</td>
                                        <td>66</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        
                        <h4>Bordered Table</h4>
                        <div class="table-wrapper">
                            <table class="data-table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Position</th>
                                        <th>Office</th>
                                        <th>Age</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>Cedric Kelly</td>
                                        <td>Senior Javascript Developer</td>
                                        <td>Edinburgh</td>
                                        <td>22</td>
                                    </tr>
                                    <tr>
                                        <td>Airi Satou</td>
                                        <td>Accountant</td>
                                        <td>Tokyo</td>
                                        <td>33</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- ===================================
                       IMPLEMENTATION GUIDE SECTION
                       =================================== -->
                    <div id="implementation" class="component-section">
                        <h3><i class="fas fa-code"></i> Implementation Guide</h3>
                        <p>Step-by-step instructions for implementing data tables in your project.</p>
                        
                        <h4>Implementation Steps:</h4>
                        <div class="implementation-steps">
                            <div class="step-item">
                                <div class="step-number">1</div>
                                <div class="step-content">
                                    <strong>Include Required CSS</strong>
                                    <pre><code>&lt;link rel="stylesheet" href="css/styles.css"&gt;
&lt;link rel="stylesheet" href="css/pages/datatables.css"&gt;</code></pre>
                                </div>
                            </div>
                            <div class="step-item">
                                <div class="step-number">2</div>
                                <div class="step-content">
                                    <strong>Basic Table Structure</strong>
                                    <pre><code>&lt;table class="data-table" id="myTable"&gt;
    &lt;thead&gt;
        &lt;tr&gt;
            &lt;th onclick="sortTable(0)"&gt;Name &lt;i class="fas fa-sort"&gt;&lt;/i&gt;&lt;/th&gt;
            &lt;th onclick="sortTable(1)"&gt;Email &lt;i class="fas fa-sort"&gt;&lt;/i&gt;&lt;/th&gt;
        &lt;/tr&gt;
    &lt;/thead&gt;
    &lt;tbody id="tableBody"&gt;
        &lt;!-- Table rows --&gt;
    &lt;/tbody&gt;
&lt;/table&gt;</code></pre>
                                </div>
                            </div>
                            <div class="step-item">
                                <div class="step-number">3</div>
                                <div class="step-content">
                                    <strong>Table Controls</strong>
                                    <pre><code>&lt;div class="table-controls"&gt;
    &lt;div class="search-box"&gt;
        &lt;input type="text" id="tableSearch" placeholder="Search..."&gt;
    &lt;/div&gt;
    &lt;div class="table-actions"&gt;
        &lt;select id="pageLength"&gt;
            &lt;option value="10"&gt;10 entries&lt;/option&gt;
            &lt;option value="25"&gt;25 entries&lt;/option&gt;
        &lt;/select&gt;
    &lt;/div&gt;
&lt;/div&gt;</code></pre>
                                </div>
                            </div>
                            <div class="step-item">
                                <div class="step-number">4</div>
                                <div class="step-content">
                                    <strong>JavaScript Functions</strong>
                                    <pre><code>// Initialize table data
const tableData = [
    ['001', 'John Doe', 'john@example.com', 'Admin', 'Active'],
    ['002', 'Jane Smith', 'jane@example.com', 'Manager', 'Active']
];

// Filter table
function filterTable() {
    const searchTerm = document.getElementById('tableSearch').value.toLowerCase();
    filteredData = tableData.filter(row => 
        row.some(cell => cell.toLowerCase().includes(searchTerm))
    );
    renderTable();
}

// Sort table
function sortTable(column) {
    // Sorting logic here
}

// Render table
function renderTable() {
    // Rendering logic here
}</code></pre>
                                </div>
                            </div>
                            <div class="step-item">
                                <div class="step-number">5</div>
                                <div class="step-content">
                                    <strong>Add SweetAlert2 (Optional)</strong>
                                    <pre><code>&lt;script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"&gt;&lt;/script&gt;

// Confirm action
function deleteRow(id) {
    Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            // Delete row logic
        }
    });
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
   JAVASCRIPT FUNCTIONALITY - Table interactions
   =================================== -->
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // ===================================
        // BASIC TABLE FUNCTIONALITY
        // ===================================
        let currentPage = 1;
        let pageLength = 10;
        let sortColumn = -1;
        let sortDirection = 'asc';
        let filteredData = [];

        // Initialize table data
        const tableData = [
            ['001', 'John Smith', 'john@example.com', 'Administrator', 'Active'],
            ['002', 'Jane Doe', 'jane@example.com', 'Manager', 'Active'],
            ['003', 'Bob Johnson', 'bob@example.com', 'Developer', 'Active'],
            ['004', 'Alice Brown', 'alice@example.com', 'Designer', 'Inactive'],
            ['005', 'Charlie Wilson', 'charlie@example.com', 'Developer', 'Active'],
            ['006', 'Diana Martinez', 'diana@example.com', 'Sales', 'Pending'],
            ['007', 'Edward Davis', 'edward@example.com', 'Sales', 'Pending'],
            ['008', 'Fiona Garcia', 'fiona@example.com', 'Support', 'Inactive'],
            ['009', 'George Miller', 'george@example.com', 'Developer', 'Active'],
            ['010', 'Hannah Lee', 'hannah@example.com', 'Manager', 'Active'],
            ['011', 'Ian Taylor', 'ian@example.com', 'Designer', 'Active'],
            ['012', 'Julia Anderson', 'julia@example.com', 'Support', 'Inactive']
        ];

        // Initialize table
        function initTable() {
            filteredData = [...tableData];
            renderTable();
        }

        // Filter table
        function filterTable() {
            const searchTerm = document.getElementById('tableSearch').value.toLowerCase();
            filteredData = tableData.filter(row => 
                row.some(cell => cell.toLowerCase().includes(searchTerm))
            );
            currentPage = 1;
            renderTable();
        }

        // Sort table
        function sortTable(column) {
            if (sortColumn === column) {
                sortDirection = sortDirection === 'asc' ? 'desc' : 'asc';
            } else {
                sortColumn = column;
                sortDirection = 'asc';
            }

            filteredData.sort((a, b) => {
                const aVal = a[column];
                const bVal = b[column];
                const modifier = sortDirection === 'asc' ? 1 : -1;
                
                if (aVal < bVal) return -1 * modifier;
                if (aVal > bVal) return 1 * modifier;
                return 0;
            });

            renderTable();
        }

        // Change page length
        function changePageLength() {
            pageLength = parseInt(document.getElementById('pageLength').value);
            currentPage = 1;
            renderTable();
        }

        // Render table
        function renderTable() {
            const tbody = document.getElementById('tableBody');
            const start = (currentPage - 1) * pageLength;
            const end = start + pageLength;
            const pageData = filteredData.slice(start, end);

            tbody.innerHTML = pageData.map(row => `
                <tr>
                    <td>${row[0]}</td>
                    <td>${row[1]}</td>
                    <td>${row[2]}</td>
                    <td>${row[3]}</td>
                    <td><span class="badge badge-${getStatusClass(row[4])}">${row[4]}</span></td>
                    <td>
                        <button class="btn btn-sm btn-primary" onclick="editRow('${row[0]}')">Edit</button>
                        <button class="btn btn-sm btn-danger" onclick="deleteRow('${row[0]}', '${row[1]}')">Delete</button>
                    </td>
                </tr>
            `).join('');

            updateTableInfo();
            updatePagination();
        }

        // Get status badge class
        function getStatusClass(status) {
            switch(status.toLowerCase()) {
                case 'active': return 'success';
                case 'inactive': return 'secondary';
                case 'pending': return 'warning';
                default: return 'primary';
            }
        }

        // Update table info
        function updateTableInfo() {
            const start = (currentPage - 1) * pageLength + 1;
            const end = Math.min(currentPage * pageLength, filteredData.length);
            
            document.getElementById('showingStart').textContent = filteredData.length > 0 ? start : 0;
            document.getElementById('showingEnd').textContent = end;
            document.getElementById('totalEntries').textContent = filteredData.length;
        }

        // Update pagination
        function updatePagination() {
            const totalPages = Math.ceil(filteredData.length / pageLength);
            const pagination = document.getElementById('pagination');
            
            let paginationHTML = '';
            
            // Previous button with icon
            paginationHTML += `<button class="btn btn-sm ${currentPage === 1 ? 'btn-secondary' : 'btn-primary'}" 
                onclick="goToPage(${currentPage - 1})" ${currentPage === 1 ? 'disabled' : ''}>
                <i class="fas fa-chevron-left"></i>
            </button>`;
            
            // Page numbers
            for (let i = 1; i <= totalPages; i++) {
                if (i === 1 || i === totalPages || (i >= currentPage - 2 && i <= currentPage + 2)) {
                    paginationHTML += `<button class="btn btn-sm ${i === currentPage ? 'btn-primary' : 'btn-secondary'}" 
                        onclick="goToPage(${i})">${i}</button>`;
                } else if (i === currentPage - 3 || i === currentPage + 3) {
                    paginationHTML += `<span>...</span>`;
                }
            }
            
            // Next button with icon
            paginationHTML += `<button class="btn btn-sm ${currentPage === totalPages ? 'btn-secondary' : 'btn-primary'}" 
                onclick="goToPage(${currentPage + 1})" ${currentPage === totalPages ? 'disabled' : ''}>
                <i class="fas fa-chevron-right"></i>
            </button>`;
            
            pagination.innerHTML = paginationHTML;
        }

        // Go to page
        function goToPage(page) {
            const totalPages = Math.ceil(filteredData.length / pageLength);
            if (page >= 1 && page <= totalPages) {
                currentPage = page;
                renderTable();
            }
        }

        // Edit row
        function editRow(id) {
            Swal.fire({
                title: 'Edit User',
                text: `Editing user ${id}`,
                icon: 'info',
                confirmButtonColor: '#4c8a89'
            });
        }

        // Delete row
        function deleteRow(id, name) {
            Swal.fire({
                title: 'Are you sure?',
                text: `You want to delete ${name}?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Remove from data
                    const index = tableData.findIndex(row => row[0] === id);
                    if (index > -1) {
                        tableData.splice(index, 1);
                        filterTable();
                    }
                    
                    Swal.fire('Deleted!', `${name} has been deleted.`, 'success');
                }
            });
        }

        // ===================================
        // ADVANCED TABLE FUNCTIONALITY
        // ===================================
        let advancedSortColumn = -1;
        let advancedSortDirection = 'asc';
        let advancedFilteredData = [];
        let selectedRows = new Set();

        // Advanced table data
        const advancedTableData = [
            ['001', 'John Smith', 'john@example.com', 'Admin', '2023-01-15', 'Active'],
            ['002', 'Jane Doe', 'jane@example.com', 'Manager', '2023-02-20', 'Active'],
            ['003', 'Bob Johnson', 'bob@example.com', 'Developer', '2023-03-10', 'Active'],
            ['004', 'Alice Brown', 'alice@example.com', 'Designer', '2023-04-05', 'Inactive']
        ];

        // Initialize advanced table
        function initAdvancedTable() {
            advancedFilteredData = [...advancedTableData];
            renderAdvancedTable();
        }

        // Render advanced table
        function renderAdvancedTable() {
            const tbody = document.getElementById('advancedTableBody');
            
            tbody.innerHTML = advancedFilteredData.map(row => `
                <tr>
                    <td>
                        <input type="checkbox" class="row-checkbox" value="${row[0]}" 
                            onchange="toggleRowSelection('${row[0]}')">
                    </td>
                    <td>${row[1]}</td>
                    <td>${row[2]}</td>
                    <td>${row[3]}</td>
                    <td>${row[4]}</td>
                    <td><span class="badge badge-${getStatusClass(row[5])}">${row[5]}</span></td>
                    <td>
                        <button class="btn btn-sm btn-primary" onclick="editAdvancedRow('${row[0]}')">Edit</button>
                        <button class="btn btn-sm btn-danger" onclick="deleteAdvancedRow('${row[0]}')">Delete</button>
                    </td>
                </tr>
            `).join('');
        }

        // Toggle row selection
        function toggleRowSelection(id) {
            if (selectedRows.has(id)) {
                selectedRows.delete(id);
            } else {
                selectedRows.add(id);
            }
            updateSelectAllCheckbox();
        }

        // Toggle select all
        function toggleSelectAll() {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.row-checkbox');
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
                if (selectAll.checked) {
                    selectedRows.add(checkbox.value);
                } else {
                    selectedRows.delete(checkbox.value);
                }
            });
        }

        // Update select all checkbox
        function updateSelectAllCheckbox() {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.row-checkbox');
            const checkedBoxes = document.querySelectorAll('.row-checkbox:checked');
            
            selectAll.checked = checkboxes.length > 0 && checkedBoxes.length === checkboxes.length;
        }

        // Bulk edit
        function bulkEdit() {
            if (selectedRows.size === 0) {
                Swal.fire('No Selection', 'Please select rows to edit.', 'warning');
                return;
            }
            
            Swal.fire({
                title: 'Bulk Edit',
                text: `Editing ${selectedRows.size} selected rows`,
                icon: 'info',
                confirmButtonColor: '#4c8a89'
            });
        }

        // Bulk delete
        function bulkDelete() {
            if (selectedRows.size === 0) {
                Swal.fire('No Selection', 'Please select rows to delete.', 'warning');
                return;
            }
            
            Swal.fire({
                title: 'Delete Selected',
                text: `Delete ${selectedRows.size} selected rows?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete them!'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Remove selected rows from data
                    advancedTableData = advancedTableData.filter(row => !selectedRows.has(row[0]));
                    selectedRows.clear();
                    initAdvancedTable();
                    Swal.fire('Deleted!', 'Selected rows have been deleted.', 'success');
                }
            });
        }

        // Export data
        function exportData() {
            Swal.fire({
                title: 'Export Data',
                text: 'Exporting table data to CSV...',
                icon: 'info',
                confirmButtonColor: '#4c8a89'
            });
        }

        // Edit advanced row
        function editAdvancedRow(id) {
            Swal.fire({
                title: 'Edit User',
                text: `Editing user ${id}`,
                icon: 'info',
                confirmButtonColor: '#4c8a89'
            });
        }

        // Delete advanced row
        function deleteAdvancedRow(id) {
            Swal.fire({
                title: 'Are you sure?',
                text: `You want to delete user ${id}?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    const index = advancedTableData.findIndex(row => row[0] === id);
                    if (index > -1) {
                        advancedTableData.splice(index, 1);
                        renderAdvancedTable();
                    }
                    
                    Swal.fire('Deleted!', 'User has been deleted.', 'success');
                }
            });
        }

        // ===================================
        // EVENT LISTENERS
        // ===================================
        document.addEventListener('DOMContentLoaded', function() {
            initTable();
            initAdvancedTable();
            
            // Search functionality
            document.getElementById('tableSearch').addEventListener('input', filterTable);
            document.getElementById('advancedSearch').addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                advancedFilteredData = advancedTableData.filter(row => 
                    row.some(cell => cell.toLowerCase().includes(searchTerm))
                );
                renderAdvancedTable();
            });
            
            // Page length change
            document.getElementById('pageLength').addEventListener('change', changePageLength);
        });

        // ===================================
        // RESPONSIVE MOBILE TABLE FUNCTIONALITY
        // ===================================
        
        // Render mobile responsive table
        function renderMobileTable() {
            const mobileTable = document.getElementById('mobileTable');
            const mobileData = filteredData.slice(0, 10); // Show first 10 items on mobile

            mobileTable.innerHTML = mobileData.map(row => `
                <div class="mobile-card">
                    <div class="mobile-card-header">
                        <strong>${row[1]}</strong>
                        <span class="badge badge-${getStatusClass(row[4])}">${row[4]}</span>
                    </div>
                    <div class="mobile-card-row">
                        <span class="mobile-card-label">ID:</span>
                        <span class="mobile-card-value">${row[0]}</span>
                    </div>
                    <div class="mobile-card-row">
                        <span class="mobile-card-label">Email:</span>
                        <span class="mobile-card-value">${row[2]}</span>
                    </div>
                    <div class="mobile-card-row">
                        <span class="mobile-card-label">Position:</span>
                        <span class="mobile-card-value">${row[3]}</span>
                    </div>
                    <div class="mobile-card-row">
                        <span class="mobile-card-label">Actions:</span>
                        <span class="mobile-card-value">
                            <button class="btn btn-sm btn-primary" onclick="editRow('${row[0]}')">Edit</button>
                            <button class="btn btn-sm btn-danger" onclick="deleteRow('${row[0]}', '${row[1]}')">Delete</button>
                        </span>
                    </div>
                </div>
            `).join('');
        }

        // Initialize mobile table on page load
        document.addEventListener('DOMContentLoaded', function() {
            renderMobileTable();
        });

        // Update mobile table when data changes
        const originalRenderTable = renderTable;
        renderTable = function() {
            originalRenderTable();
            renderMobileTable();
        };
    </script>

<!-- ===================================
   FOOTER INCLUDE
   =================================== -->
<?php include 'includes/footer.php'; ?>
