<?php
require_once 'admin_auth.php';

$base_url = '../';
$page_title = 'Blotter Management';
require_once '../includes/header.php';

// Fetch all blotters with optional filtering
$filter = $_GET['filter'] ?? 'all';
$sql = "SELECT b.* FROM blotters b";

if ($filter !== 'all') {
    $sql .= " WHERE b.status = '" . $pdo->quote($filter) . "'";
}

$sql .= " ORDER BY b.created_at DESC";

try {
    $blotters = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    $totalCount = count($blotters);
} catch (Exception $e) {
    $blotters = [];
    $totalCount = 0;
}

// Handle status update from admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_status') {
    $blotter_id = (int)($_POST['blotter_id'] ?? 0);
    $new_status = trim($_POST['new_status'] ?? '');
    $allowed = ['Pending', 'Under Investigation', 'Resolved', 'Archived'];
    if ($blotter_id > 0 && in_array($new_status, $allowed, true)) {
        try {
            $update = $pdo->prepare("UPDATE blotters SET status = ?, updated_at = NOW() WHERE id = ?");
            $update->execute([$new_status, $blotter_id]);
        } catch (Exception $e) {
            // ignore; page will reload and show updated data if successful
        }
    }
    // Redirect to avoid form resubmission and preserve filter
    $loc = 'blotters.php';
    if (!empty($_GET['filter'])) $loc .= '?filter=' . urlencode($_GET['filter']);
    header('Location: ' . $loc);
    exit();
}

// Get status counts
try {
    $statusCounts = [];
    $statuses = ['Pending', 'Under Investigation', 'Resolved', 'Archived'];
    foreach ($statuses as $status) {
        $count = $pdo->query("SELECT COUNT(*) FROM blotters WHERE status = '$status'")->fetchColumn();
        $statusCounts[$status] = $count;
    }
} catch (Exception $e) {
    $statusCounts = [];
}
?>

<style>
    .search-bar-container {
        display: flex;
        gap: 10px;
        margin-bottom: 20px;
    }
    .search-bar-container input {
        flex: 1;
        max-width: 400px;
    }
    @media (max-width: 768px) {
        .search-bar-container {
            flex-direction: column;
        }
        .search-bar-container input {
            max-width: 100%;
        }
    }
</style>

<div class="main-content">
    <div class="content-container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h2">Blotter Management</h1>
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Back
            </a>
        </div>

        <!-- Search Bar -->
        <div class="search-bar-container">
            <input 
                type="text" 
                id="blotterSearch" 
                class="form-control" 
                placeholder="Search by Blotter No..."
                onkeyup="filterTableByBlotterNo()"
            >
            <button class="btn btn-outline-secondary" onclick="resetSearch()">
                <i class="bi bi-arrow-counterclockwise"></i> Reset
            </button>
            <button class="btn btn-primary" onclick="printSelectedBlotters()">
                <i class="bi bi-printer"></i> Print
            </button>
        </div>

        <!-- Filter Tabs -->
        <div class="mb-4">
            <div class="btn-group" role="group">
                <a href="blotters.php?filter=all" class="btn btn-outline-primary <?= $filter === 'all' ? 'active' : '' ?>">
                    All (<?= array_sum($statusCounts) ?>)
                </a>
                <a href="blotters.php?filter=Pending" class="btn btn-outline-warning <?= $filter === 'Pending' ? 'active' : '' ?>">
                    Pending (<?= $statusCounts['Pending'] ?? 0 ?>)
                </a>
                <a href="blotters.php?filter=Under Investigation" class="btn btn-outline-info <?= $filter === 'Under Investigation' ? 'active' : '' ?>">
                    Investigating (<?= $statusCounts['Under Investigation'] ?? 0 ?>)
                </a>
                <a href="blotters.php?filter=Resolved" class="btn btn-outline-success <?= $filter === 'Resolved' ? 'active' : '' ?>">
                    Resolved (<?= $statusCounts['Resolved'] ?? 0 ?>)
                </a>
                <a href="blotters.php?filter=Archived" class="btn btn-outline-secondary <?= $filter === 'Archived' ? 'active' : '' ?>">
                    Archived (<?= $statusCounts['Archived'] ?? 0 ?>)
                </a>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5>Blotter Records (<?= $totalCount ?>)</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Blotter No</th>
                                <th>Complainant</th>
                                <th>Incident Type</th>
                                <th>Location</th>
                                <th>Status</th>
                                <th>Priority</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($blotters as $b): ?>
                            <tr>
                                <td class="fw-bold"><?= htmlspecialchars($b['blotter_no']) ?></td>
                                <td><?= htmlspecialchars($b['complainant_name']) ?></td>
                                <td><?= htmlspecialchars($b['incident_type'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars(substr($b['location'] ?? 'N/A', 0, 25)) ?></td>
                                <td>
                                    <?php
                                    $statusClass = match($b['status']) {
                                        'Pending' => 'warning',
                                        'Under Investigation' => 'info',
                                        'Resolved' => 'success',
                                        'Archived' => 'secondary',
                                        default => 'light'
                                    };
                                    ?>
                                    <span class="badge bg-<?= $statusClass ?>"><?= htmlspecialchars($b['status']) ?></span>
                                </td>
                                <td>
                                    <?php
                                    $priorityClass = match($b['priority']) {
                                        'High' => 'danger',
                                        'Medium' => 'warning',
                                        'Low' => 'info',
                                        default => 'light'
                                    };
                                    ?>
                                    <span class="badge bg-<?= $priorityClass ?>"><?= htmlspecialchars($b['priority']) ?></span>
                                </td>
                                <td><?= date('M d, Y', strtotime($b['created_at'])) ?></td>
                                <td>
                                    <form method="POST" class="d-inline-flex align-items-center gap-2">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="blotter_id" value="<?= (int)$b['id'] ?>">
                                        <select name="new_status" class="form-select form-select-sm" style="width: auto; min-width: 140px;" onchange="this.form.submit()">
                                            <option value="Pending" <?= $b['status'] === 'Pending' ? 'selected' : '' ?>>Pending</option>
                                            <option value="Under Investigation" <?= $b['status'] === 'Under Investigation' ? 'selected' : '' ?>>Under Investigation</option>
                                            <option value="Resolved" <?= $b['status'] === 'Resolved' ? 'selected' : '' ?>>Resolved</option>
                                            <option value="Archived" <?= $b['status'] === 'Archived' ? 'selected' : '' ?>>Archived</option>
                                        </select>
                                    </form>
                                    <a href="../modules/blotter_view.php?id=<?= (int)$b['id'] ?>" class="btn btn-sm btn-outline-info" title="View & Edit Blotter">
                                        <i class="bi bi-eye"></i> View
                                    </a>
                                    <button class="btn btn-sm btn-outline-primary" onclick="printBlotter(<?= (int)$b['id'] ?>, '<?= htmlspecialchars($b['blotter_no']) ?>')" title="Print Blotter">
                                        <i class="bi bi-printer"></i> Print
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Filter table by Blotter No
    function filterTableByBlotterNo() {
        const searchInput = document.getElementById('blotterSearch').value.toUpperCase();
        const table = document.querySelector('table tbody');
        const rows = table.getElementsByTagName('tr');
        
        let visibleCount = 0;
        
        for (let i = 0; i < rows.length; i++) {
            const blotterNoCell = rows[i].getElementsByTagName('td')[0];
            if (blotterNoCell) {
                const blotterNo = blotterNoCell.textContent.toUpperCase();
                if (blotterNo.indexOf(searchInput) > -1) {
                    rows[i].style.display = '';
                    visibleCount++;
                } else {
                    rows[i].style.display = 'none';
                }
            }
        }
        
        // Update count
        updateCount(visibleCount);
    }
    
    // Reset search
    function resetSearch() {
        document.getElementById('blotterSearch').value = '';
        const table = document.querySelector('table tbody');
        const rows = table.getElementsByTagName('tr');
        
        for (let i = 0; i < rows.length; i++) {
            rows[i].style.display = '';
        }
        
        updateCount(rows.length);
    }
    
    // Update visible count
    function updateCount(count) {
        const countDisplay = document.querySelector('.card-header h5');
        if (countDisplay) {
            countDisplay.textContent = 'Blotter Records (' + count + ')';
        }
    }
    
    // Print single blotter
    function printBlotter(blotterId, blotterNo) {
        const printWindow = window.open('../modules/blotter_view.php?id=' + blotterId + '&print=1', 'PrintBlotter', 'height=800,width=1000');
        printWindow.addEventListener('load', function() {
            setTimeout(function() {
                printWindow.print();
            }, 500);
        });
    }
    
    // Print selected blotters (all visible)
    function printSelectedBlotters() {
        const table = document.querySelector('table tbody');
        const rows = table.getElementsByTagName('tr');
        const visibleRows = Array.from(rows).filter(row => row.style.display !== 'none');
        
        if (visibleRows.length === 0) {
            alert('No blotters to print. Please adjust your search.');
            return;
        }
        
        if (visibleRows.length > 1) {
            alert('Opening blotters for printing. Please note: You can print each one individually.\n\nTotal blotters to print: ' + visibleRows.length);
        }
        
        // Open first blotter for printing
        const firstRow = visibleRows[0];
        const blotterNo = firstRow.getElementsByTagName('td')[0].textContent;
        const link = firstRow.querySelector('a[href*="blotter_view.php"]');
        if (link) {
            const url = link.getAttribute('href');
            const printWindow = window.open(url + '&print=1', 'PrintBlotter', 'height=800,width=1000');
            printWindow.addEventListener('load', function() {
                setTimeout(function() {
                    printWindow.print();
                }, 500);
            });
        }
    }
</script>

<?php require_once '../includes/footer.php'; ?>
