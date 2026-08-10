<?php
require_once 'admin_auth.php';

$base_url = '../';
$page_title = 'Blotter Management';
require_once '../includes/header.php';

// Fetch all blotters with optional filtering
$filter = $_GET['filter'] ?? 'all';
$sql = "SELECT b.* FROM blotters b";

if ($filter !== 'all') {
    $sql .= " WHERE b.status = " . $pdo->quote($filter);
}

$sql .= " ORDER BY b.created_at DESC";

try {
    $blotters = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    $totalCount = count($blotters);
} catch (Exception $e) {
    $blotters = [];
    $totalCount = 0;
}

function ensureBlotterStatusEnum(PDO $pdo, array $requiredStatuses)
{
    try {
        $stmt = $pdo->prepare("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'blotters' AND COLUMN_NAME = 'status'");
        $stmt->execute();
        $columnType = $stmt->fetchColumn();
        if (!$columnType) {
            return;
        }

        preg_match_all("/'([^']+)'/", $columnType, $matches);
        $currentValues = $matches[1] ?? [];
        $missing = array_diff($requiredStatuses, $currentValues);
        if (empty($missing)) {
            return;
        }

        $newValues = $currentValues;
        foreach ($missing as $value) {
            if (!in_array($value, $newValues, true)) {
                $newValues[] = $value;
            }
        }

        $enumSql = "ENUM('" . implode("','", array_map(function ($item) {
            return str_replace("'", "\\'", $item);
        }, $newValues)) . "') NOT NULL";

        $pdo->exec("ALTER TABLE blotters MODIFY COLUMN status {$enumSql}");
    } catch (Exception $e) {
        error_log('Failed to ensure blotter status enum: ' . $e->getMessage());
    }
}

// Handle external module payload dispatch
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'dispatch_external_module') {
    $blotter_id = (int)($_POST['blotter_id'] ?? 0);
    $target_module = $_POST['target_module'] ?? 'all';

    if ($blotter_id > 0) {
        try {
            $stmtB = $pdo->prepare("SELECT * FROM blotters WHERE id = ?");
            $stmtB->execute([$blotter_id]);
            $b = $stmtB->fetch(PDO::FETCH_ASSOC);

            if ($b) {
                require_once '../modules/OperationalModuleIntegrator.php';
                $integrator = new OperationalModuleIntegrator($pdo);

                $payload = [
                    'source' => 'blotter_management',
                    'incident_id' => $b['blotter_no'] ?? ('BLOTTER-' . $b['id']),
                    'location' => $b['incident_location'] ?? $b['location'] ?? 'Barangay Central, Quezon City',
                    'description' => $b['incident_narrative'] ?? $b['incident_type'] ?? 'Law Enforcement Blotter Record',
                    'emergency_level' => $b['priority'] ?? 'High',
                    'complainant_name' => $b['complainant_name'] ?? 'Complainant',
                    'timestamp' => $b['created_at'] ?? date('Y-m-d H:i:s')
                ];

                $processed = $integrator->processInbound($payload, false);
                $modPayloads = $processed['module_specific_payloads'];

                if ($target_module === 'cctv') {
                    $res = $integrator->dispatchToPartnerCctvApi($modPayloads['cctv_partner_surveillance_api']);
                    $msgText = "Dispatched CCTV query to Partner API (" . htmlspecialchars($res['endpoint']) . ")";
                } elseif ($target_module === 'inspection') {
                    $res = $integrator->dispatchToGroup7InspectionApi($modPayloads['group_7_inspection_scheduling']);
                    $msgText = "Dispatched case referral to Group 7 Inspection API (" . htmlspecialchars($res['endpoint']) . ")";
                } elseif ($target_module === 'crimemap') {
                    $res = $integrator->dispatchToGroup5CrimeMapApi($modPayloads['group_5_crime_mapping']);
                    $msgText = "Synced spatial data to Group 5 Crime Mapping API (" . htmlspecialchars($res['endpoint']) . ")";
                } elseif ($target_module === 'resource') {
                    $res = $integrator->dispatchToGroup3ResourceApi($modPayloads['group_3_resource_allocation']);
                    $msgText = "Dispatched unit request to Group 3 Resource Dispatch API (" . htmlspecialchars($res['endpoint']) . ")";
                } elseif ($target_module === 'campaign') {
                    $cPayload = [
                        'title' => 'Public Advisory: ' . ($b['incident_type'] ?? 'Law Enforcement Notice'),
                        'description' => 'Safety alert notice regarding ' . strtolower($b['incident_type'] ?? 'blotter incident') . ' at ' . ($b['location'] ?? 'Quezon City'),
                        'category' => 'Public Safety',
                        'geographical_scope' => 'Barangay',
                        'status' => 'Active'
                    ];
                    $res = $integrator->dispatchToCampaignApi($cPayload);
                    $msgText = "Dispatched Public Safety Campaign Advisory to Campaign API (" . htmlspecialchars($res['endpoint']) . ")";
                } else {
                    $allRes = $integrator->dispatchToAllConnectedModules($modPayloads);
                    $msgText = "Dispatched blotter payload to ALL connected external modules!";
                }

                $_SESSION['flash'] = ['type' => 'success', 'message' => "🚀 {$msgText} for Blotter #{$b['blotter_no']}!"];
            }
        } catch (Exception $e) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Error dispatching payload: ' . $e->getMessage()];
        }
    }
    header("Location: blotters.php");
    exit;
}

// Handle status update from admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_status') {
    $blotter_id = (int)($_POST['blotter_id'] ?? 0);
    $new_status = trim($_POST['new_status'] ?? '');
    $allowed = ['Pending', 'Under Investigation', 'Resolved', 'Archived', 'Rejected'];
    if ($blotter_id > 0 && in_array($new_status, $allowed, true)) {
        // Ensure the status enum includes all allowed values before updating status.
        ensureBlotterStatusEnum($pdo, $allowed);

        $notifyData = null;
        if ($new_status === 'Rejected') {
            $notifyStmt = $pdo->prepare("SELECT created_by, blotter_no FROM blotters WHERE id = ?");
            $notifyStmt->execute([$blotter_id]);
            $notifyData = $notifyStmt->fetch(PDO::FETCH_ASSOC);
        }

        try {
            $update = $pdo->prepare("UPDATE blotters SET status = ?, updated_at = NOW() WHERE id = ?");
            $update->execute([$new_status, $blotter_id]);
        } catch (Exception $e) {
            // Try repairing the enum if the update failed due to a missing value.
            if (stripos($e->getMessage(), 'Incorrect enum value') !== false) {
                ensureBlotterStatusEnum($pdo, $allowed);
                try {
                    $update->execute([$new_status, $blotter_id]);
                } catch (Exception $inner) {
                    error_log('Blotter status update failed after enum repair: ' . $inner->getMessage());
                    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Failed to update blotter status.'];
                }
            } else {
                error_log('Blotter status update failed: ' . $e->getMessage());
                $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Failed to update blotter status.'];
            }
        }

        if ($new_status === 'Rejected' && !empty($notifyData['created_by'])) {
            require_once '../includes/notifications.php';
            $complainantUserId = intval($notifyData['created_by']);
            $bno = $notifyData['blotter_no'] ?? '';
            $title = "Blotter Rejected: {$bno}";
            $msg = "Your blotter ({$bno}) has been rejected by the administration and remains on record. Please review the details or contact support for next steps.";

            try {
                createNotification($pdo, $complainantUserId, $blotter_id, 'Blotter Rejected', $title, $msg);
            } catch (Exception $e) {
                error_log('Notification create failed: ' . $e->getMessage());
                $_SESSION['flash'] = ['type' => 'warning', 'message' => 'Blotter rejected, but the notification could not be saved.'];
            }

            $u = $pdo->prepare("SELECT emailadd AS email FROM signup WHERE user_id = :uid");
            $u->execute([':uid' => $complainantUserId]);
            $userRow = $u->fetch(PDO::FETCH_ASSOC);
            if (!empty($userRow['email'])) {
                try {
                    sendEmailNotification($userRow['email'], $title, nl2br(htmlspecialchars($msg)));
                } catch (Exception $e) {
                    error_log('Email failed: ' . $e->getMessage());
                }
            }
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
    $statuses = ['Pending', 'Under Investigation', 'Resolved', 'Archived', 'Rejected'];
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

    .header-action-btn {
        background: #ffffff;
        color: #000000 !important;
        border-color: #ced4da;
    }

    .header-action-btn:hover,
    .header-action-btn:focus {
        background: #f8f9fa;
        color: #000000 !important;
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
            <div class="d-flex gap-2">
                <a href="../modules/blotter_create.php" class="btn header-action-btn">
                    <i class="bi bi-plus-circle"></i> Create New Blotter
                </a>
                <a href="dashboard.php" class="btn header-action-btn">
                    <i class="bi bi-arrow-left"></i> Back
                </a>
            </div>
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
                <a href="blotters.php?filter=Rejected" class="btn btn-outline-danger <?= $filter === 'Rejected' ? 'active' : '' ?>">
                    Rejected (<?= $statusCounts['Rejected'] ?? 0 ?>)
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
                                        'Rejected' => 'danger',
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
                                            <option value="Rejected" <?= $b['status'] === 'Rejected' ? 'selected' : '' ?>>Rejected</option>
                                            <option value="Archived" <?= $b['status'] === 'Archived' ? 'selected' : '' ?>>Archived</option>
                                        </select>
                                    </form>
                                    <a href="../modules/blotter_view.php?id=<?= (int)$b['id'] ?>" class="btn btn-sm btn-outline-info" title="View & Edit Blotter">
                                        <i class="bi bi-eye"></i> View
                                    </a>
                                    <button class="btn btn-sm btn-outline-primary" onclick="printBlotter(<?= (int)$b['id'] ?>, '<?= htmlspecialchars($b['blotter_no']) ?>')" title="Print Blotter">
                                        <i class="bi bi-printer"></i> Print
                                    </button>
                                    <a href="Summons.php?blotter_id=<?= (int)$b['id'] ?>" class="btn btn-sm btn-outline-primary me-1" title="Create summons from this blotter">
                                        <i class="bi bi-file-earmark-text"></i> Summons
                                    </a>

                                    <div class="dropdown d-inline-block">
                                        <button class="btn btn-sm btn-outline-success dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="External Module Integration Dispatch">
                                            <i class="fas fa-network-wired me-1"></i>Integrations
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow">
                                            <li><h6 class="dropdown-header">Dispatch to External Module</h6></li>
                                            <li>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="dispatch_external_module">
                                                    <input type="hidden" name="blotter_id" value="<?= (int)$b['id'] ?>">
                                                    <input type="hidden" name="target_module" value="cctv">
                                                    <button type="submit" class="dropdown-item py-1"><i class="fas fa-video text-success me-2"></i>Request CCTV Footage</button>
                                                </form>
                                            </li>
                                            <li>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="dispatch_external_module">
                                                    <input type="hidden" name="blotter_id" value="<?= (int)$b['id'] ?>">
                                                    <input type="hidden" name="target_module" value="inspection">
                                                    <button type="submit" class="dropdown-item py-1"><i class="fas fa-calendar-check text-primary me-2"></i>Send to Group 7 Inspection</button>
                                                </form>
                                            </li>
                                            <li>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="dispatch_external_module">
                                                    <input type="hidden" name="blotter_id" value="<?= (int)$b['id'] ?>">
                                                    <input type="hidden" name="target_module" value="crimemap">
                                                    <button type="submit" class="dropdown-item py-1"><i class="fas fa-map-marked-alt text-info me-2"></i>Sync to Group 5 Crime Map</button>
                                                </form>
                                            </li>
                                            <li>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="dispatch_external_module">
                                                    <input type="hidden" name="blotter_id" value="<?= (int)$b['id'] ?>">
                                                    <input type="hidden" name="target_module" value="resource">
                                                    <button type="submit" class="dropdown-item py-1"><i class="fas fa-ambulance text-warning me-2"></i>Dispatch Group 3 EMS/Police</button>
                                                </form>
                                            </li>
                                            <li>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="dispatch_external_module">
                                                    <input type="hidden" name="blotter_id" value="<?= (int)$b['id'] ?>">
                                                    <input type="hidden" name="target_module" value="campaign">
                                                    <button type="submit" class="dropdown-item py-1"><i class="fas fa-bullhorn text-danger me-2"></i>Publish Public Safety Campaign</button>
                                                </form>
                                            </li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="dispatch_external_module">
                                                    <input type="hidden" name="blotter_id" value="<?= (int)$b['id'] ?>">
                                                    <input type="hidden" name="target_module" value="all">
                                                    <button type="submit" class="dropdown-item py-1 fw-bold text-success"><i class="fas fa-paper-plane me-2"></i>Dispatch ALL 4 Modules</button>
                                                </form>
                                            </li>
                                        </ul>
                                    </div>
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
