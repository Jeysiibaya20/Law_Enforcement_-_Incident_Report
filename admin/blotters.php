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

        <!-- KPI Cards Strip -->
        <div class="row g-3 mb-4">
            <div class="col-12 col-sm-6 col-xl-3">
                <article class="dashboard-analytics-card analytics-tone-notif h-100">
                    <div class="dashboard-analytics-head">
                        <span class="dashboard-analytics-label">Total Blotters</span>
                        <span class="dashboard-analytics-icon"><i class="fas fa-clipboard-list"></i></span>
                    </div>
                    <div class="dashboard-analytics-value"><?= array_sum($statusCounts) ?></div>
                    <div class="dashboard-analytics-sub">All recorded blotter files</div>
                </article>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <article class="dashboard-analytics-card analytics-tone-pending h-100">
                    <div class="dashboard-analytics-head">
                        <span class="dashboard-analytics-label">Pending Blotters</span>
                        <span class="dashboard-analytics-icon"><i class="fas fa-clock"></i></span>
                    </div>
                    <div class="dashboard-analytics-value"><?= $statusCounts['Pending'] ?? 0 ?></div>
                    <div class="dashboard-analytics-sub">Awaiting action / review</div>
                </article>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <article class="dashboard-analytics-card analytics-tone-info h-100">
                    <div class="dashboard-analytics-head">
                        <span class="dashboard-analytics-label">Under Investigation</span>
                        <span class="dashboard-analytics-icon"><i class="fas fa-magnifying-glass"></i></span>
                    </div>
                    <div class="dashboard-analytics-value"><?= $statusCounts['Under Investigation'] ?? 0 ?></div>
                    <div class="dashboard-analytics-sub">Active officer inquiry</div>
                </article>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <article class="dashboard-analytics-card analytics-tone-subs h-100">
                    <div class="dashboard-analytics-head">
                        <span class="dashboard-analytics-label">Resolved Blotters</span>
                        <span class="dashboard-analytics-icon"><i class="fas fa-check-circle"></i></span>
                    </div>
                    <div class="dashboard-analytics-value"><?= $statusCounts['Resolved'] ?? 0 ?></div>
                    <div class="dashboard-analytics-sub">Settled & completed</div>
                </article>
            </div>
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

        <!-- Blotter Records Card with Dual View: Table (10/page) & Carousel (10/slide) -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center flex-wrap gap-2 py-3">
                <div class="d-flex align-items-center gap-2">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-journal-text me-2"></i>Blotter Records</h5>
                    <span class="badge bg-white text-primary fw-bold" id="blotterTotalBadge"><?= count($blotters) ?> records</span>
                </div>

                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <!-- Search -->
                    <div class="input-group input-group-sm" style="width: 220px;">
                        <span class="input-group-text bg-light text-dark border-0"><i class="bi bi-search"></i></span>
                        <input type="text" id="blotterSearchInput" class="form-control border-0" placeholder="Search blotters..." onkeyup="filterBlotterRecords()">
                    </div>

                    <!-- Page Size -->
                    <select id="blotterPageSizeSelect" class="form-select form-select-sm" style="width: auto;" onchange="changeBlotterPageSize(this.value)">
                        <option value="10" selected>10 per page</option>
                        <option value="25">25 per page</option>
                        <option value="50">50 per page</option>
                        <option value="1000">Show All</option>
                    </select>

                    <!-- View Switcher -->
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" class="btn btn-light active" id="btnBlotterTableView" onclick="switchBlotterView('table')">
                            <i class="bi bi-table me-1"></i> Table View
                        </button>
                        <button type="button" class="btn btn-light" id="btnBlotterCarouselView" onclick="switchBlotterView('carousel')">
                            <i class="bi bi-view-stacked me-1"></i> Carousel (10/Slide)
                        </button>
                    </div>
                </div>
            </div>

            <div class="card-body p-0">
                <!-- ================= TABLE VIEW ================= -->
                <div id="blotterTableView" class="p-3">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="blottersTable">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
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
                            <tbody id="blottersTableBody">
                                <?php if (empty($blotters)): ?>
                                    <tr>
                                        <td colspan="9" class="text-center text-muted py-4">No blotter records found</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($blotters as $idx => $b): 
                                        $statusClass = match($b['status']) {
                                            'Pending' => 'warning',
                                            'Under Investigation' => 'info',
                                            'Resolved' => 'success',
                                            'Rejected' => 'danger',
                                            'Archived' => 'secondary',
                                            default => 'light'
                                        };
                                        $priorityClass = match($b['priority']) {
                                            'High' => 'danger',
                                            'Medium' => 'warning',
                                            'Low' => 'info',
                                            default => 'light'
                                        };
                                    ?>
                                        <tr class="blotter-row"
                                            data-index="<?= $idx ?>"
                                            data-blotter-no="<?= htmlspecialchars(strtolower($b['blotter_no'])) ?>"
                                            data-complainant="<?= htmlspecialchars(strtolower($b['complainant_name'])) ?>"
                                            data-type="<?= htmlspecialchars(strtolower($b['incident_type'] ?? '')) ?>"
                                            data-location="<?= htmlspecialchars(strtolower($b['location'] ?? '')) ?>"
                                            data-status="<?= htmlspecialchars(strtolower($b['status'])) ?>"
                                            data-priority="<?= htmlspecialchars(strtolower($b['priority'])) ?>">
                                            <td class="text-muted small fw-bold"><?= $idx + 1 ?></td>
                                            <td class="fw-bold text-primary"><?= htmlspecialchars($b['blotter_no']) ?></td>
                                            <td><?= htmlspecialchars($b['complainant_name']) ?></td>
                                            <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($b['incident_type'] ?? 'N/A') ?></span></td>
                                            <td><small class="text-muted"><i class="bi bi-geo-alt me-1 text-danger"></i><?= htmlspecialchars(substr($b['location'] ?? 'N/A', 0, 25)) ?></small></td>
                                            <td><span class="badge bg-<?= $statusClass ?>"><?= htmlspecialchars($b['status']) ?></span></td>
                                            <td><span class="badge bg-<?= $priorityClass ?>"><?= htmlspecialchars($b['priority']) ?></span></td>
                                            <td><small class="text-muted"><?= date('M d, Y', strtotime($b['created_at'])) ?></small></td>
                                            <td>
                                                <div class="d-flex align-items-center gap-1 flex-wrap">
                                                    <form method="POST" class="d-inline-flex align-items-center">
                                                        <input type="hidden" name="action" value="update_status">
                                                        <input type="hidden" name="blotter_id" value="<?= (int)$b['id'] ?>">
                                                        <select name="new_status" class="form-select form-select-sm" style="width: auto; min-width: 130px;" onchange="this.form.submit()">
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
                                                        <i class="bi bi-printer"></i>
                                                    </button>
                                                    <a href="Summons.php?blotter_id=<?= (int)$b['id'] ?>" class="btn btn-sm btn-outline-primary" title="Create summons from this blotter">
                                                        <i class="bi bi-file-earmark-text"></i>
                                                    </a>

                                                    <div class="dropdown d-inline-block">
                                                        <button class="btn btn-sm btn-outline-success dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Integrations">
                                                            <i class="fas fa-network-wired"></i>
                                                        </button>
                                                        <ul class="dropdown-menu dropdown-menu-end shadow">
                                                            <li><h6 class="dropdown-header">Dispatch Integration</h6></li>
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
                                                                    <button type="submit" class="dropdown-item py-1"><i class="fas fa-ambulance text-warning me-2"></i>Dispatch Group 3 EMS</button>
                                                                </form>
                                                            </li>
                                                        </ul>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Blotters Table Pagination Bar -->
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3 pt-2 border-top">
                        <div class="text-muted small" id="blotterPaginationInfo">
                            Showing 1 to 10 of <?= count($blotters) ?> entries
                        </div>
                        <nav aria-label="Blotters table pagination">
                            <ul class="pagination pagination-sm mb-0" id="blotterPaginationControls">
                            </ul>
                        </nav>
                    </div>
                </div>

                <!-- ================= CAROUSEL VIEW (10 Items per Slide) ================= -->
                <div id="blotterCarouselView" class="p-3" style="display: none;">
                    <?php 
                        $blotterBatches = array_chunk($blotters, 10);
                        $totalBlotterSlides = count($blotterBatches);
                    ?>

                    <!-- Carousel Controls -->
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3 p-2 bg-light rounded border">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-primary px-3 py-2 fs-6">
                                <i class="bi bi-view-stacked me-1"></i> <span id="blotterCarouselSlideLabel">Slide 1 of <?= max(1, $totalBlotterSlides) ?></span>
                            </span>
                            <small class="text-muted" id="blotterCarouselRangeLabel">
                                Showing <?= count($blotters) > 0 ? '1 - ' . min(10, count($blotters)) : '0' ?> of <?= count($blotters) ?> blotters
                            </small>
                        </div>

                        <div class="d-flex align-items-center gap-2">
                            <button class="btn btn-sm btn-primary rounded-circle" type="button" data-bs-target="#blottersCarousel" data-bs-slide="prev" style="width:34px; height:34px;">
                                <i class="bi bi-chevron-left"></i>
                            </button>
                            
                            <div class="d-flex gap-1" id="blotterCarouselIndicators">
                                <?php for ($s = 0; $s < $totalBlotterSlides; $s++): ?>
                                    <button class="btn btn-sm <?= $s === 0 ? 'btn-primary' : 'btn-outline-secondary' ?> fw-bold py-1 px-2" type="button" data-bs-target="#blottersCarousel" data-bs-slide-to="<?= $s ?>" style="font-size: 0.75rem;">
                                        <?= $s + 1 ?>
                                    </button>
                                <?php endfor; ?>
                            </div>

                            <button class="btn btn-sm btn-primary rounded-circle" type="button" data-bs-target="#blottersCarousel" data-bs-slide="next" style="width:34px; height:34px;">
                                <i class="bi bi-chevron-right"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Bootstrap Carousel -->
                    <div id="blottersCarousel" class="carousel slide" data-bs-interval="false">
                        <div class="carousel-inner">
                            <?php if (empty($blotterBatches)): ?>
                                <div class="carousel-item active">
                                    <div class="text-center py-5 text-muted">
                                        <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                        <h5>No Blotter Records</h5>
                                    </div>
                                </div>
                            <?php else: ?>
                                <?php foreach ($blotterBatches as $slideIdx => $batch): ?>
                                    <div class="carousel-item <?= $slideIdx === 0 ? 'active' : '' ?>" data-slide-index="<?= $slideIdx ?>">
                                        <div class="row g-3">
                                            <?php foreach ($batch as $cardIdx => $item): 
                                                $sClass = match($item['status']) { 'Pending' => 'warning', 'Under Investigation' => 'info', 'Resolved' => 'success', 'Rejected' => 'danger', default => 'secondary' };
                                                $pClass = match($item['priority']) { 'High' => 'danger', 'Medium' => 'warning', 'Low' => 'info', default => 'light' };
                                            ?>
                                                <div class="col-md-6 col-lg-6 blotter-carousel-card-col">
                                                    <div class="card h-100 border shadow-sm">
                                                        <div class="card-header bg-light d-flex justify-content-between align-items-center py-2 px-3">
                                                            <strong class="text-primary"><?= htmlspecialchars($item['blotter_no']) ?></strong>
                                                            <div class="d-flex gap-1">
                                                                <span class="badge bg-<?= $pClass ?>"><?= htmlspecialchars($item['priority']) ?></span>
                                                                <span class="badge bg-<?= $sClass ?>"><?= htmlspecialchars($item['status']) ?></span>
                                                            </div>
                                                        </div>
                                                        <div class="card-body p-3">
                                                            <div class="row g-2 small">
                                                                <div class="col-6">
                                                                    <span class="text-muted d-block text-uppercase" style="font-size:0.7rem; font-weight:700;">Complainant</span>
                                                                    <strong class="text-dark"><?= htmlspecialchars($item['complainant_name']) ?></strong>
                                                                </div>
                                                                <div class="col-6">
                                                                    <span class="text-muted d-block text-uppercase" style="font-size:0.7rem; font-weight:700;">Incident Type</span>
                                                                    <span class="badge bg-light text-dark border"><?= htmlspecialchars($item['incident_type'] ?? 'N/A') ?></span>
                                                                </div>
                                                                <div class="col-6">
                                                                    <span class="text-muted d-block text-uppercase" style="font-size:0.7rem; font-weight:700;">Location</span>
                                                                    <span><i class="bi bi-geo-alt text-danger me-1"></i><?= htmlspecialchars(substr($item['location'] ?? 'N/A', 0, 25)) ?></span>
                                                                </div>
                                                                <div class="col-6">
                                                                    <span class="text-muted d-block text-uppercase" style="font-size:0.7rem; font-weight:700;">Date Logged</span>
                                                                    <span><?= date('M d, Y', strtotime($item['created_at'])) ?></span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="card-footer bg-light d-flex justify-content-end gap-1 py-2 px-3">
                                                            <a href="../modules/blotter_view.php?id=<?= (int)$item['id'] ?>" class="btn btn-sm btn-outline-info"><i class="bi bi-eye me-1"></i>View</a>
                                                            <button class="btn btn-sm btn-outline-primary" onclick="printBlotter(<?= (int)$item['id'] ?>, '<?= htmlspecialchars($item['blotter_no']) ?>')"><i class="bi bi-printer me-1"></i>Print</button>
                                                            <a href="Summons.php?blotter_id=<?= (int)$item['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-file-earmark-text me-1"></i>Summons</a>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
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

    // ================= BLOTTERS VIEW ENGINE =================
    let currentBlotterPage = 1;
    let blotterRowsPerPage = 10;
    let filteredBlotterRows = [];

    function initBlotterCatalog() {
        const rows = document.querySelectorAll('#blottersTableBody tr.blotter-row');
        filteredBlotterRows = Array.from(rows);
        renderBlotterPagination();

        const carouselEl = document.getElementById('blottersCarousel');
        if (carouselEl) {
            carouselEl.addEventListener('slide.bs.carousel', function(event) {
                const nextSlideIdx = event.to;
                const totalSlides = document.querySelectorAll('#blottersCarousel .carousel-item').length;
                const totalRecs = parseInt('<?= count($blotters) ?>') || 0;

                const label = document.getElementById('blotterCarouselSlideLabel');
                if (label) label.textContent = `Slide ${nextSlideIdx + 1} of ${totalSlides}`;

                const rangeLabel = document.getElementById('blotterCarouselRangeLabel');
                if (rangeLabel) {
                    const startRec = (nextSlideIdx * 10) + 1;
                    const endRec = Math.min((nextSlideIdx + 1) * 10, totalRecs);
                    rangeLabel.textContent = `Showing ${startRec} - ${endRec} of ${totalRecs} blotters`;
                }

                const indicators = document.querySelectorAll('#blotterCarouselIndicators button');
                indicators.forEach((b, idx) => {
                    if (idx === nextSlideIdx) {
                        b.classList.replace('btn-outline-secondary', 'btn-primary');
                    } else {
                        b.classList.replace('btn-primary', 'btn-outline-secondary');
                    }
                });
            });
        }
    }

    function switchBlotterView(viewType) {
        const tableView = document.getElementById('blotterTableView');
        const carouselView = document.getElementById('blotterCarouselView');
        const btnTable = document.getElementById('btnBlotterTableView');
        const btnCarousel = document.getElementById('btnBlotterCarouselView');

        if (viewType === 'carousel') {
            tableView.style.display = 'none';
            carouselView.style.display = 'block';
            btnTable.classList.remove('active');
            btnCarousel.classList.add('active');
        } else {
            carouselView.style.display = 'none';
            tableView.style.display = 'block';
            btnCarousel.classList.remove('active');
            btnTable.classList.add('active');
        }
    }

    function changeBlotterPageSize(size) {
        blotterRowsPerPage = parseInt(size) || 10;
        currentBlotterPage = 1;
        renderBlotterPagination();
    }

    function filterBlotterRecords() {
        const query = (document.getElementById('blotterSearchInput')?.value || '').toLowerCase().trim();
        const allRows = document.querySelectorAll('#blottersTableBody tr.blotter-row');
        const allCards = document.querySelectorAll('.blotter-carousel-card-col');

        filteredBlotterRows = [];
        allRows.forEach(row => {
            const text = (
                (row.getAttribute('data-blotter-no') || '') + ' ' +
                (row.getAttribute('data-complainant') || '') + ' ' +
                (row.getAttribute('data-type') || '') + ' ' +
                (row.getAttribute('data-location') || '') + ' ' +
                (row.getAttribute('data-status') || '') + ' ' +
                (row.getAttribute('data-priority') || '')
            ).toLowerCase();

            if (!query || text.includes(query)) {
                filteredBlotterRows.push(row);
            } else {
                row.style.display = 'none';
            }
        });

        allCards.forEach(card => {
            const cardText = card.textContent.toLowerCase();
            if (!query || cardText.includes(query)) {
                card.style.display = '';
            } else {
                card.style.display = 'none';
            }
        });

        currentBlotterPage = 1;
        renderBlotterPagination();
    }

    function renderBlotterPagination() {
        const total = filteredBlotterRows.length;
        const totalPages = Math.ceil(total / blotterRowsPerPage) || 1;
        if (currentBlotterPage > totalPages) currentBlotterPage = totalPages;
        if (currentBlotterPage < 1) currentBlotterPage = 1;

        const startIdx = (currentBlotterPage - 1) * blotterRowsPerPage;
        const endIdx = Math.min(startIdx + blotterRowsPerPage, total);

        const allRows = document.querySelectorAll('#blottersTableBody tr.blotter-row');
        allRows.forEach(r => r.style.display = 'none');

        for (let i = startIdx; i < endIdx; i++) {
            if (filteredBlotterRows[i]) filteredBlotterRows[i].style.display = '';
        }

        const infoEl = document.getElementById('blotterPaginationInfo');
        if (infoEl) {
            if (total === 0) {
                infoEl.textContent = 'Showing 0 to 0 of 0 entries';
            } else {
                infoEl.textContent = `Showing ${startIdx + 1} to ${endIdx} of ${total} entries`;
            }
        }

        const controls = document.getElementById('blotterPaginationControls');
        if (!controls) return;

        let html = '';
        html += `<li class="page-item ${currentBlotterPage === 1 ? 'disabled' : ''}">
            <a class="page-link" href="javascript:void(0)" onclick="goToBlotterPage(${currentBlotterPage - 1})"><i class="bi bi-chevron-left"></i></a>
        </li>`;

        for (let p = 1; p <= totalPages; p++) {
            if (totalPages > 7 && Math.abs(p - currentBlotterPage) > 2 && p !== 1 && p !== totalPages) {
                if (p === 2 || p === totalPages - 1) {
                    html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
                }
                continue;
            }
            html += `<li class="page-item ${p === currentBlotterPage ? 'active' : ''}">
                <a class="page-link" href="javascript:void(0)" onclick="goToBlotterPage(${p})">${p}</a>
            </li>`;
        }

        html += `<li class="page-item ${currentBlotterPage >= totalPages ? 'disabled' : ''}">
            <a class="page-link" href="javascript:void(0)" onclick="goToBlotterPage(${currentBlotterPage + 1})"><i class="bi bi-chevron-right"></i></a>
        </li>`;

        controls.innerHTML = html;
    }

    function goToBlotterPage(page) {
        currentBlotterPage = page;
        renderBlotterPagination();
    }

    document.addEventListener('DOMContentLoaded', function(){
        initBlotterCatalog();
    });
</script>

<?php require_once '../includes/footer.php'; ?>
