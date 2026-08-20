<?php
require_once 'admin_auth.php';
require_once '../modules/CaseAssign.php';

$base_url = '../';
$page_title = 'Case Management';
require_once '../includes/header.php';

// All create/update/delete actions are handled via the API endpoint at ../api/cases.php
// Admin page will submit forms to that API so that all data I/O is centralized.

// Get filter parameters
$filters = [];
if (isset($_GET['status']) && !empty($_GET['status'])) {
    $filters['status'] = $_GET['status'];
}
if (isset($_GET['priority']) && !empty($_GET['priority'])) {
    $filters['priority'] = $_GET['priority'];
}
if (isset($_GET['assigned_to']) && !empty($_GET['assigned_to'])) {
    $filters['assigned_to'] = $_GET['assigned_to'];
}
if (isset($_GET['date_from']) && !empty($_GET['date_from'])) {
    $filters['date_from'] = $_GET['date_from'];
}
if (isset($_GET['date_to']) && !empty($_GET['date_to'])) {
    $filters['date_to'] = $_GET['date_to'];
}

// Get case assignments
$cases = getCaseAssignments($filters);

// Get available officers
$available_officers = getAvailableBCPCOfficers();
$all_officers = getAllBCPCOfficers();

// Get active emergency dispatchers and law enforcement officers from signup
$dispatchers = [];
try {
    $dispatchers = $pdo->query("
        SELECT user_id, fullname, role, emailadd 
        FROM signup 
        WHERE role IN ('Dispatcher', 'Dispatch Officer', 'Officer', 'Police Officer', 'Law Enforcement Officer', 'Admin') 
          AND (banned IS NULL OR banned = 0)
        ORDER BY role, fullname
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $dispatchers = [];
}

// Get available barangay officials
$barangay_check = $pdo->query("SHOW TABLES LIKE 'barangay_officials'");
$barangay_officials = [];
if ($barangay_check->rowCount() > 0) {
    $barangay_officials = $pdo->query("
        SELECT b.*, s.fullname, s.emailadd 
        FROM barangay_officials b
        LEFT JOIN signup s ON b.user_id = s.user_id
        WHERE b.is_active = 1
        ORDER BY b.barangay_name, s.fullname
    ")->fetchAll(PDO::FETCH_ASSOC);
}

// Get barangay chairpersons (users with role that can assign cases)
try {
    $chairpersons = $pdo->query("SELECT user_id, fullname FROM signup WHERE role IN ('Barangay Chairperson', 'Admin') ORDER BY fullname")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $chairpersons = [];
}

// Get case statistics
$stats = getCaseStatistics();
?>

<div class="main-content">
    <div class="content-container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h2 mb-1"><?= (isset($_GET['status']) && strtolower($_GET['status']) === 'closed') ? 'Closed Cases' : 'Case Management' ?></h1>
                <p class="text-muted small mb-0"><?= (isset($_GET['status']) && strtolower($_GET['status']) === 'closed') ? 'Archived and settled cases' : 'Track and manage law enforcement & barangay cases' ?></p>
            </div>
            <div class="d-flex gap-2">
                <a href="cases.php" class="btn <?= (!isset($_GET['status']) || $_GET['status'] === '') ? 'btn-success text-white' : 'btn-outline-success' ?> btn-sm"><i class="fas fa-folder-open me-1"></i> All Cases</a>
                <a href="cases.php?status=Closed" class="btn <?= (isset($_GET['status']) && strtolower($_GET['status']) === 'closed') ? 'btn-danger text-white' : 'btn-outline-danger' ?> btn-sm"><i class="fas fa-folder-minus me-1"></i> Closed Cases</a>
                <button type="button" class="btn btn-success btn-sm text-white shadow-sm" style="background-color: #2e856e !important; border-color: #2e856e !important;" data-bs-toggle="modal" data-bs-target="#createCaseModal">
                    <i class="bi bi-plus-circle me-1"></i> Create New Case
                </button>
            </div>
        </div>

        <?php if (!empty($_SESSION['flash'])): ?>
            <?php $flash = $_SESSION['flash']; unset($_SESSION['flash']); ?>
            <div class="alert alert-<?= htmlspecialchars($flash['type'] ?? 'info') ?> alert-dismissible">
                <?= htmlspecialchars($flash['message'] ?? '') ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="row g-3 mb-4">
            <div class="col-12 col-sm-6 col-xl-3">
                <article class="dashboard-analytics-card analytics-tone-notif h-100">
                    <div class="dashboard-analytics-head">
                        <span class="dashboard-analytics-label">Total Cases</span>
                        <span class="dashboard-analytics-icon"><i class="fas fa-briefcase"></i></span>
                    </div>
                    <div class="dashboard-analytics-value"><?= $stats['total_cases'] ?? 0 ?></div>
                    <div class="dashboard-analytics-sub">All recorded cases</div>
                </article>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <article class="dashboard-analytics-card analytics-tone-pending h-100">
                    <div class="dashboard-analytics-head">
                        <span class="dashboard-analytics-label">New Cases</span>
                        <span class="dashboard-analytics-icon"><i class="fas fa-folder-plus"></i></span>
                    </div>
                    <div class="dashboard-analytics-value"><?= $stats['by_status']['New'] ?? 0 ?></div>
                    <div class="dashboard-analytics-sub">Awaiting assignment</div>
                </article>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <article class="dashboard-analytics-card analytics-tone-info h-100">
                    <div class="dashboard-analytics-head">
                        <span class="dashboard-analytics-label">Ongoing Cases</span>
                        <span class="dashboard-analytics-icon"><i class="fas fa-spinner"></i></span>
                    </div>
                    <div class="dashboard-analytics-value"><?= $stats['by_status']['Ongoing'] ?? 0 ?></div>
                    <div class="dashboard-analytics-sub">Currently in progress</div>
                </article>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <article class="dashboard-analytics-card analytics-tone-subs h-100">
                    <div class="dashboard-analytics-head">
                        <span class="dashboard-analytics-label">Active Officers</span>
                        <span class="dashboard-analytics-icon"><i class="fas fa-user-shield"></i></span>
                    </div>
                    <div class="dashboard-analytics-value"><?= $stats['active_officers'] ?? 0 ?></div>
                    <div class="dashboard-analytics-sub">Available for assignment</div>
                </article>
            </div>
        </div>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Filters</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-2">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="">All Status</option>
                            <option value="New" <?= (isset($_GET['status']) && $_GET['status'] == 'New') ? 'selected' : '' ?>>New</option>
                            <option value="Ongoing" <?= (isset($_GET['status']) && $_GET['status'] == 'Ongoing') ? 'selected' : '' ?>>Ongoing</option>
                            <option value="Resolved" <?= (isset($_GET['status']) && $_GET['status'] == 'Resolved') ? 'selected' : '' ?>>Resolved</option>
                            <option value="Closed" <?= (isset($_GET['status']) && $_GET['status'] == 'Closed') ? 'selected' : '' ?>>Closed</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Priority</label>
                        <select name="priority" class="form-select">
                            <option value="">All Priority</option>
                            <option value="High" <?= (isset($_GET['priority']) && $_GET['priority'] == 'High') ? 'selected' : '' ?>>High</option>
                            <option value="Medium" <?= (isset($_GET['priority']) && $_GET['priority'] == 'Medium') ? 'selected' : '' ?>>Medium</option>
                            <option value="Low" <?= (isset($_GET['priority']) && $_GET['priority'] == 'Low') ? 'selected' : '' ?>>Low</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Assigned To</label>
                        <select name="assigned_to" class="form-select">
                            <option value="">All Officers / Dispatchers</option>
                            <?php if (!empty($dispatchers)): ?>
                            <optgroup label="Dispatchers & Officers">
                                <?php foreach ($dispatchers as $d): ?>
                                    <option value="<?= $d['user_id'] ?>" <?= (isset($_GET['assigned_to']) && $_GET['assigned_to'] == $d['user_id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($d['fullname']) ?> (<?= htmlspecialchars($d['role']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                            <?php endif; ?>
                            <?php if (!empty($all_officers)): ?>
                            <optgroup label="BCPC Officers">
                                <?php foreach ($all_officers as $officer): ?>
                                    <option value="<?= $officer['user_id'] ?>" <?= (isset($_GET['assigned_to']) && $_GET['assigned_to'] == $officer['user_id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($officer['fullname']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">From Date</label>
                        <input type="date" name="date_from" class="form-control" value="<?= $_GET['date_from'] ?? '' ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">To Date</label>
                        <input type="date" name="date_to" class="form-control" value="<?= $_GET['date_to'] ?? '' ?>">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-success me-2 text-white px-3" style="background-color: #2e856e !important; border-color: #2e856e !important;"><i class="bi bi-filter me-1"></i> Filter</button>
                        <a href="cases.php" class="btn btn-outline-secondary px-3"><i class="bi bi-x-circle me-1"></i> Clear</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Cases Card with Dual View: Table (10/page) & Carousel (10/slide) -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center flex-wrap gap-2 py-3">
                <div class="d-flex align-items-center gap-2">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-folder2-open me-2"></i>Case Assignments</h5>
                    <span class="badge bg-white text-primary fw-bold" id="caseTotalBadge"><?= count($cases) ?> cases</span>
                </div>

                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <!-- Search -->
                    <div class="input-group input-group-sm" style="width: 220px;">
                        <span class="input-group-text bg-light text-dark border-0"><i class="bi bi-search"></i></span>
                        <input type="text" id="caseSearchInput" class="form-control border-0" placeholder="Search cases..." onkeyup="filterCaseRecords()">
                    </div>

                    <!-- Page Size -->
                    <select id="casePageSizeSelect" class="form-select form-select-sm" style="width: auto;" onchange="changeCasePageSize(this.value)">
                        <option value="10" selected>10 per page</option>
                        <option value="25">25 per page</option>
                        <option value="50">50 per page</option>
                        <option value="1000">Show All</option>
                    </select>

                    <!-- View Switcher -->
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" class="btn btn-light active" id="btnCaseTableView" onclick="switchCaseView('table')">
                            <i class="bi bi-table me-1"></i> Table View
                        </button>
                        <button type="button" class="btn btn-light" id="btnCaseCarouselView" onclick="switchCaseView('carousel')">
                            <i class="bi bi-view-stacked me-1"></i> Carousel (10/Slide)
                        </button>
                    </div>
                </div>
            </div>

            <div class="card-body p-0">
                <!-- ================= TABLE VIEW ================= -->
                <div id="caseTableView" class="p-3">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="casesTable">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Case Number</th>
                                    <th>Incident Type</th>
                                    <th>Complainant</th>
                                    <th>Priority</th>
                                    <th>Status</th>
                                    <th>Assigned To</th>
                                    <th>Assigned By</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="casesTableBody">
                                <?php if (empty($cases)): ?>
                                    <tr>
                                        <td colspan="10" class="text-center text-muted py-4">No cases found</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($cases as $idx => $case): 
                                        $priority_class = match($case['priority']) {
                                            'High' => 'danger',
                                            'Medium' => 'warning text-dark',
                                            'Low' => 'success',
                                            default => 'secondary'
                                        };
                                        $status_class = match($case['status']) {
                                            'New' => 'info',
                                            'Ongoing' => 'primary',
                                            'Resolved' => 'success',
                                            'Closed' => 'secondary',
                                            default => 'secondary'
                                        };
                                    ?>
                                        <tr class="case-row"
                                            data-index="<?= $idx ?>"
                                            data-case-no="<?= htmlspecialchars(strtolower($case['case_number'])) ?>"
                                            data-type="<?= htmlspecialchars(strtolower($case['incident_type'])) ?>"
                                            data-complainant="<?= htmlspecialchars(strtolower($case['complainant_name'])) ?>"
                                            data-assigned-to="<?= htmlspecialchars(strtolower($case['assigned_to_name'] ?? '')) ?>"
                                            data-status="<?= htmlspecialchars(strtolower($case['status'])) ?>"
                                            data-priority="<?= htmlspecialchars(strtolower($case['priority'])) ?>">
                                            <td class="text-muted small fw-bold"><?= $idx + 1 ?></td>
                                            <td><strong class="text-primary"><?= htmlspecialchars($case['case_number']) ?></strong></td>
                                            <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($case['incident_type']) ?></span></td>
                                            <td><?= htmlspecialchars($case['complainant_name']) ?></td>
                                            <td><span class="badge bg-<?= $priority_class ?>"><?= htmlspecialchars($case['priority']) ?></span></td>
                                            <td><span class="badge bg-<?= $status_class ?>"><?= htmlspecialchars($case['status']) ?></span></td>
                                            <td><small class="fw-semibold"><?= htmlspecialchars($case['assigned_to_name'] ?? 'Unassigned') ?></small></td>
                                            <td><small class="text-muted"><?= htmlspecialchars($case['assigned_by_name']) ?></small></td>
                                            <td><small class="text-muted"><?= date('M d, Y', strtotime($case['created_at'])) ?></small></td>
                                            <td>
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <button type="button" class="btn btn-outline-primary" onclick="viewCaseDetails(<?= $case['id'] ?>)" title="View Details">
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-outline-secondary" onclick="updateCaseStatus(<?= $case['id'] ?>, '<?= $case['status'] ?>')" title="Update Status">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-outline-info" onclick="addFollowUp(<?= $case['id'] ?>)" title="Add Follow-up">
                                                        <i class="bi bi-plus-circle"></i>
                                                    </button>
                                                    <?php if ($case['assigned_to']): ?>
                                                        <button type="button" class="btn btn-outline-warning" onclick="reassignCase(<?= $case['id'] ?>)" title="Reassign">
                                                            <i class="bi bi-arrow-left-right"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    <button type="button" class="btn btn-outline-danger" onclick="deleteCase(<?= $case['id'] ?>, '<?= htmlspecialchars($case['case_number']) ?>')" title="Delete">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Cases Table Pagination Bar -->
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3 pt-2 border-top">
                        <div class="text-muted small" id="casePaginationInfo">
                            Showing 1 to 10 of <?= count($cases) ?> entries
                        </div>
                        <nav aria-label="Cases table pagination">
                            <ul class="pagination pagination-sm mb-0" id="casePaginationControls">
                            </ul>
                        </nav>
                    </div>
                </div>

                <!-- ================= CAROUSEL VIEW (10 Items per Slide) ================= -->
                <div id="caseCarouselView" class="p-3" style="display: none;">
                    <?php 
                        $caseBatches = array_chunk($cases, 10);
                        $totalCaseSlides = count($caseBatches);
                    ?>

                    <!-- Carousel Controls -->
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3 p-2 bg-light rounded border">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-primary px-3 py-2 fs-6">
                                <i class="bi bi-view-stacked me-1"></i> <span id="caseCarouselSlideLabel">Slide 1 of <?= max(1, $totalCaseSlides) ?></span>
                            </span>
                            <small class="text-muted" id="caseCarouselRangeLabel">
                                Showing <?= count($cases) > 0 ? '1 - ' . min(10, count($cases)) : '0' ?> of <?= count($cases) ?> cases
                            </small>
                        </div>

                        <div class="d-flex align-items-center gap-2">
                            <button class="btn btn-sm btn-primary rounded-circle" type="button" data-bs-target="#casesCarousel" data-bs-slide="prev" style="width:34px; height:34px;">
                                <i class="bi bi-chevron-left"></i>
                            </button>
                            
                            <div class="d-flex gap-1" id="caseCarouselIndicators">
                                <?php for ($s = 0; $s < $totalCaseSlides; $s++): ?>
                                    <button class="btn btn-sm <?= $s === 0 ? 'btn-primary' : 'btn-outline-secondary' ?> fw-bold py-1 px-2" type="button" data-bs-target="#casesCarousel" data-bs-slide-to="<?= $s ?>" style="font-size: 0.75rem;">
                                        <?= $s + 1 ?>
                                    </button>
                                <?php endfor; ?>
                            </div>

                            <button class="btn btn-sm btn-primary rounded-circle" type="button" data-bs-target="#casesCarousel" data-bs-slide="next" style="width:34px; height:34px;">
                                <i class="bi bi-chevron-right"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Bootstrap Carousel -->
                    <div id="casesCarousel" class="carousel slide" data-bs-interval="false">
                        <div class="carousel-inner">
                            <?php if (empty($caseBatches)): ?>
                                <div class="carousel-item active">
                                    <div class="text-center py-5 text-muted">
                                        <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                        <h5>No Cases Found</h5>
                                    </div>
                                </div>
                            <?php else: ?>
                                <?php foreach ($caseBatches as $slideIdx => $batch): ?>
                                    <div class="carousel-item <?= $slideIdx === 0 ? 'active' : '' ?>" data-slide-index="<?= $slideIdx ?>">
                                        <div class="row g-3">
                                            <?php foreach ($batch as $cardIdx => $item): 
                                                $pClass = match($item['priority']) { 'High' => 'danger', 'Medium' => 'warning text-dark', 'Low' => 'success', default => 'secondary' };
                                                $sClass = match($item['status']) { 'New' => 'info', 'Ongoing' => 'primary', 'Resolved' => 'success', 'Closed' => 'secondary', default => 'secondary' };
                                            ?>
                                                <div class="col-md-6 col-lg-6 case-carousel-card-col">
                                                    <div class="card h-100 border shadow-sm">
                                                        <div class="card-header bg-light d-flex justify-content-between align-items-center py-2 px-3">
                                                            <div>
                                                                <strong class="text-primary"><?= htmlspecialchars($item['case_number']) ?></strong>
                                                                <span class="badge bg-light text-dark border ms-1"><?= htmlspecialchars($item['incident_type']) ?></span>
                                                            </div>
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
                                                                    <span class="text-muted d-block text-uppercase" style="font-size:0.7rem; font-weight:700;">Assigned Officer</span>
                                                                    <strong class="text-dark"><?= htmlspecialchars($item['assigned_to_name'] ?? 'Unassigned') ?></strong>
                                                                </div>
                                                                <div class="col-6">
                                                                    <span class="text-muted d-block text-uppercase" style="font-size:0.7rem; font-weight:700;">Assigned By</span>
                                                                    <span><?= htmlspecialchars($item['assigned_by_name']) ?></span>
                                                                </div>
                                                                <div class="col-6">
                                                                    <span class="text-muted d-block text-uppercase" style="font-size:0.7rem; font-weight:700;">Created Date</span>
                                                                    <span><?= date('M d, Y', strtotime($item['created_at'])) ?></span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="card-footer bg-light d-flex justify-content-end gap-1 py-2 px-3">
                                                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="viewCaseDetails(<?= $item['id'] ?>)">
                                                                <i class="bi bi-eye me-1"></i>View
                                                            </button>
                                                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="updateCaseStatus(<?= $item['id'] ?>, '<?= $item['status'] ?>')">
                                                                <i class="bi bi-pencil me-1"></i>Status
                                                            </button>
                                                            <button type="button" class="btn btn-sm btn-outline-info" onclick="addFollowUp(<?= $item['id'] ?>)">
                                                                <i class="bi bi-plus-circle me-1"></i>Follow-up
                                                            </button>
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

<!-- Create Case Modal -->
<div class="modal fade" id="createCaseModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create New Case Assignment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="../api/cases.php">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Incident Type *</label>
                            <input type="text" name="incident_type" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Priority</label>
                            <select name="priority" class="form-select">
                                <option value="Low">Low</option>
                                <option value="Medium" selected>Medium</option>
                                <option value="High">High</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Complainant Name *</label>
                            <input type="text" name="complainant_name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Respondent Name</label>
                            <input type="text" name="respondent_name" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Incident Date *</label>
                            <input type="date" name="incident_date" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Incident Time</label>
                            <input type="time" name="incident_time" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Location</label>
                            <input type="text" name="location" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description *</label>
                            <textarea name="description" class="form-control" rows="3" required></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Assign to Officer / Dispatcher</label>
                            <select name="assigned_to" class="form-select">
                                <option value="">Select Officer / Dispatcher</option>
                                <?php if (!empty($dispatchers)): ?>
                                <optgroup label="Emergency Dispatchers & Law Enforcement">
                                    <?php foreach ($dispatchers as $disp): ?>
                                        <option value="<?= $disp['user_id'] ?>">
                                            <?= htmlspecialchars($disp['fullname']) ?> - <?= htmlspecialchars($disp['role']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <?php endif; ?>
                                <?php if (!empty($available_officers)): ?>
                                <optgroup label="BCPC Officers">
                                    <?php foreach ($available_officers as $officer): ?>
                                        <option value="<?= $officer['user_id'] ?>">
                                            <?= htmlspecialchars($officer['fullname']) ?> (<?= htmlspecialchars($officer['barangay']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <?php endif; ?>
                                <?php if (!empty($barangay_officials)): ?>
                                <optgroup label="Barangay Officials">
                                    <?php foreach ($barangay_officials as $official): ?>
                                        <option value="<?= $official['user_id'] ?>">
                                            <?= htmlspecialchars($official['fullname']) ?> - <?= htmlspecialchars($official['position']) ?> (<?= htmlspecialchars($official['barangay_name']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Barangay Chairperson</label>
                            <select name="barangay_chairperson_id" class="form-select">
                                <option value="">Select Chairperson</option>
                                <?php foreach ($chairpersons as $chair): ?>
                                    <option value="<?= $chair['user_id'] ?>">
                                        <?= htmlspecialchars($chair['fullname']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="create_case" class="btn btn-primary">Create Case</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Case Details Modal -->
<div class="modal fade" id="caseDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Case Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="caseDetailsContent">
                <!-- Content will be loaded dynamically -->
            </div>
        </div>
    </div>
</div>

<!-- Update Status Modal -->
<div class="modal fade" id="updateStatusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Case Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="../api/cases.php">
                <div class="modal-body">
                    <input type="hidden" name="case_id" id="status_case_id">
                    <div class="mb-3">
                        <label class="form-label">New Status</label>
                        <select name="new_status" class="form-select" required>
                            <option value="New">New</option>
                            <option value="Ongoing">Ongoing</option>
                            <option value="Resolved">Resolved</option>
                            <option value="Closed">Closed</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes (Optional)</label>
                        <textarea name="status_notes" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_status" class="btn btn-primary">Update Status</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Follow-up Modal -->
<div class="modal fade" id="followUpModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Follow-up Action</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="../api/cases.php">
                <div class="modal-body">
                    <input type="hidden" name="case_id" id="followup_case_id">
                    <div class="mb-3">
                        <label class="form-label">Follow-up Action *</label>
                        <textarea name="followup_action" class="form-control" rows="4" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_followup" class="btn btn-primary">Add Follow-up</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reassign Case Modal -->
<div class="modal fade" id="reassignModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reassign Case</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="../api/cases.php">
                <div class="modal-body">
                    <input type="hidden" name="case_id" id="reassign_case_id">
                    <div class="mb-3">
                        <label class="form-label">New Officer / Dispatcher *</label>
                        <select name="new_officer" class="form-select" required>
                            <option value="">Select Officer / Dispatcher</option>
                            <?php if (!empty($dispatchers)): ?>
                            <optgroup label="Emergency Dispatchers & Law Enforcement">
                                <?php foreach ($dispatchers as $disp): ?>
                                    <option value="<?= $disp['user_id'] ?>">
                                        <?= htmlspecialchars($disp['fullname']) ?> - <?= htmlspecialchars($disp['role']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                            <?php endif; ?>
                            <?php if (!empty($all_officers)): ?>
                            <optgroup label="BCPC Officers">
                                <?php foreach ($all_officers as $officer): ?>
                                    <option value="<?= $officer['user_id'] ?>">
                                        <?= htmlspecialchars($officer['fullname']) ?> (<?= htmlspecialchars($officer['barangay']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                            <?php endif; ?>
                            <?php if (!empty($barangay_officials)): ?>
                            <optgroup label="Barangay Officials">
                                <?php foreach ($barangay_officials as $official): ?>
                                    <option value="<?= $official['user_id'] ?>">
                                        <?= htmlspecialchars($official['fullname']) ?> - <?= htmlspecialchars($official['position']) ?> (<?= htmlspecialchars($official['barangay_name']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reason for Reassignment</label>
                        <textarea name="reassign_reason" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="reassign_case" class="btn btn-primary">Reassign Case</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function viewCaseDetails(caseId) {
    // Open case details in a new page
    window.location.href = `../modules/case_details.php?case_id=${caseId}`;
}

function updateCaseStatus(caseId, currentStatus) {
    document.getElementById('status_case_id').value = caseId;
    // Pre-select current status
    const statusSelect = document.querySelector('select[name="new_status"]');
    if (statusSelect) {
        statusSelect.value = currentStatus;
    }
    new bootstrap.Modal(document.getElementById('updateStatusModal')).show();
}

function addFollowUp(caseId) {
    document.getElementById('followup_case_id').value = caseId;
    new bootstrap.Modal(document.getElementById('followUpModal')).show();
}

function reassignCase(caseId) {
    document.getElementById('reassign_case_id').value = caseId;
    new bootstrap.Modal(document.getElementById('reassignModal')).show();
}

function deleteCase(caseId, caseNumber) {
    if (confirm(`Are you sure you want to delete Case ${caseNumber}?\n\nThis action cannot be undone and will permanently remove the case and all associated data.`)) {
        // Create a form to submit the delete request to the API
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '../api/cases.php';
        
        // Add hidden inputs
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'delete_case';
        form.appendChild(actionInput);
        
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'case_id';
        idInput.value = caseId;
        form.appendChild(idInput);
        
        // Submit the form
        document.body.appendChild(form);
        form.submit();
    }
}

// ================= CASES VIEW ENGINE =================
let currentCasePage = 1;
let caseRowsPerPage = 10;
let filteredCaseRows = [];

function initCaseCatalog() {
    const rows = document.querySelectorAll('#casesTableBody tr.case-row');
    filteredCaseRows = Array.from(rows);
    renderCasePagination();

    const carouselEl = document.getElementById('casesCarousel');
    if (carouselEl) {
        carouselEl.addEventListener('slide.bs.carousel', function(event) {
            const nextSlideIdx = event.to;
            const totalSlides = document.querySelectorAll('#casesCarousel .carousel-item').length;
            const totalRecs = parseInt('<?= count($cases) ?>') || 0;

            const label = document.getElementById('caseCarouselSlideLabel');
            if (label) label.textContent = `Slide ${nextSlideIdx + 1} of ${totalSlides}`;

            const rangeLabel = document.getElementById('caseCarouselRangeLabel');
            if (rangeLabel) {
                const startRec = (nextSlideIdx * 10) + 1;
                const endRec = Math.min((nextSlideIdx + 1) * 10, totalRecs);
                rangeLabel.textContent = `Showing ${startRec} - ${endRec} of ${totalRecs} cases`;
            }

            const indicators = document.querySelectorAll('#caseCarouselIndicators button');
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

function switchCaseView(viewType) {
    const tableView = document.getElementById('caseTableView');
    const carouselView = document.getElementById('caseCarouselView');
    const btnTable = document.getElementById('btnCaseTableView');
    const btnCarousel = document.getElementById('btnCaseCarouselView');

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

function changeCasePageSize(size) {
    caseRowsPerPage = parseInt(size) || 10;
    currentCasePage = 1;
    renderCasePagination();
}

function filterCaseRecords() {
    const query = (document.getElementById('caseSearchInput')?.value || '').toLowerCase().trim();
    const allRows = document.querySelectorAll('#casesTableBody tr.case-row');
    const allCards = document.querySelectorAll('.case-carousel-card-col');

    filteredCaseRows = [];
    allRows.forEach(row => {
        const text = (
            (row.getAttribute('data-case-no') || '') + ' ' +
            (row.getAttribute('data-type') || '') + ' ' +
            (row.getAttribute('data-complainant') || '') + ' ' +
            (row.getAttribute('data-assigned-to') || '') + ' ' +
            (row.getAttribute('data-status') || '') + ' ' +
            (row.getAttribute('data-priority') || '')
        ).toLowerCase();

        if (!query || text.includes(query)) {
            filteredCaseRows.push(row);
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

    currentCasePage = 1;
    renderCasePagination();
}

function renderCasePagination() {
    const total = filteredCaseRows.length;
    const totalPages = Math.ceil(total / caseRowsPerPage) || 1;
    if (currentCasePage > totalPages) currentCasePage = totalPages;
    if (currentCasePage < 1) currentCasePage = 1;

    const startIdx = (currentCasePage - 1) * caseRowsPerPage;
    const endIdx = Math.min(startIdx + caseRowsPerPage, total);

    const allRows = document.querySelectorAll('#casesTableBody tr.case-row');
    allRows.forEach(r => r.style.display = 'none');

    for (let i = startIdx; i < endIdx; i++) {
        if (filteredCaseRows[i]) filteredCaseRows[i].style.display = '';
    }

    const infoEl = document.getElementById('casePaginationInfo');
    if (infoEl) {
        if (total === 0) {
            infoEl.textContent = 'Showing 0 to 0 of 0 entries';
        } else {
            infoEl.textContent = `Showing ${startIdx + 1} to ${endIdx} of ${total} entries`;
        }
    }

    const controls = document.getElementById('casePaginationControls');
    if (!controls) return;

    let html = '';
    html += `<li class="page-item ${currentCasePage === 1 ? 'disabled' : ''}">
        <a class="page-link" href="javascript:void(0)" onclick="goToCasePage(${currentCasePage - 1})"><i class="bi bi-chevron-left"></i></a>
    </li>`;

    for (let p = 1; p <= totalPages; p++) {
        if (totalPages > 7 && Math.abs(p - currentCasePage) > 2 && p !== 1 && p !== totalPages) {
            if (p === 2 || p === totalPages - 1) {
                html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
            }
            continue;
        }
        html += `<li class="page-item ${p === currentCasePage ? 'active' : ''}">
            <a class="page-link" href="javascript:void(0)" onclick="goToCasePage(${p})">${p}</a>
        </li>`;
    }

    html += `<li class="page-item ${currentCasePage >= totalPages ? 'disabled' : ''}">
        <a class="page-link" href="javascript:void(0)" onclick="goToCasePage(${currentCasePage + 1})"><i class="bi bi-chevron-right"></i></a>
    </li>`;

    controls.innerHTML = html;
}

function goToCasePage(page) {
    currentCasePage = page;
    renderCasePagination();
}

document.addEventListener('DOMContentLoaded', function(){
    initCaseCatalog();
});
</script>

<?php require_once '../includes/footer.php'; ?>
