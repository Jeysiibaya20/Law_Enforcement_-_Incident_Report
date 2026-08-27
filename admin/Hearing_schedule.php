<?php
require_once 'admin_auth.php';
if (!isset($pdo) || !($pdo instanceof PDO)) {
    $pdo = getDBConnection();
}
$base_url = '../';
$page_title = 'Hearing Schedule';
require_once '../includes/header.php';

$search = trim($_GET['search'] ?? '');
$date_from = trim($_GET['date_from'] ?? '');
$date_to = trim($_GET['date_to'] ?? '');
$status_filter = trim($_GET['status'] ?? '');

$where = [];
$params = [];

$unscheduled = !empty($_GET['unscheduled']);

if ($search !== '') {
    $where[] = '(blotter_no LIKE ? OR complainant_name LIKE ? OR respondent_name LIKE ? OR incident_type LIKE ? OR location LIKE ?)';
    $term = '%' . $search . '%';
    $params = array_merge($params, [$term, $term, $term, $term, $term]);
}

if ($unscheduled) {
    $where[] = '(hearing_date IS NULL OR hearing_date = "" OR hearing_date = "0000-00-00")';
} else {
    if ($date_from !== '') {
        $where[] = 'hearing_date >= ?';
        $params[] = $date_from;
    }

    if ($date_to !== '') {
        $where[] = 'hearing_date <= ?';
        $params[] = $date_to;
    }
}

if ($status_filter !== '') {
    $where[] = 'status = ?';
    $params[] = $status_filter;
}

$sql = 'SELECT * FROM blotters';
if (!empty($where)) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY CASE WHEN hearing_date IS NULL THEN 1 ELSE 0 END, hearing_date ASC, hearing_time ASC, created_at DESC';

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $all_hearings = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $all_hearings = [];
}

$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 10;
$total_rows = count($all_hearings);
$total_pages = max(1, ceil($total_rows / $per_page));
if ($page > $total_pages) $page = $total_pages;
$offset = ($page - 1) * $per_page;
$hearings = array_slice($all_hearings, $offset, $per_page);

try {
    $summaryStmt = $pdo->query("SELECT
        SUM(CASE WHEN hearing_date >= CURDATE() THEN 1 ELSE 0 END) AS upcoming,
        SUM(CASE WHEN hearing_date = CURDATE() THEN 1 ELSE 0 END) AS today,
        SUM(CASE WHEN hearing_date < CURDATE() THEN 1 ELSE 0 END) AS past,
        SUM(CASE WHEN hearing_date IS NULL OR hearing_date = '' OR hearing_date = '0000-00-00' THEN 1 ELSE 0 END) AS unscheduled
        FROM blotters");
    $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $summary = ['upcoming' => 0, 'today' => 0, 'past' => 0, 'unscheduled' => 0];
}

function buildPageUrl($pageNum, $search, $date_from, $date_to, $status_filter) {
    $p = [
        'page' => $pageNum
    ];
    if (!empty($_GET['unscheduled'])) $p['unscheduled'] = '1';
    if ($search !== '') $p['search'] = $search;
    if ($date_from !== '') $p['date_from'] = $date_from;
    if ($date_to !== '') $p['date_to'] = $date_to;
    if ($status_filter !== '') $p['status'] = $status_filter;
    return 'Hearing_schedule.php?' . http_build_query($p);
}
?>
<div class="main-content" style="background:#fff; color:#000;">
    <div class="content-container">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h1 class="h2 fw-bold" style="color:#000;">Hearing Schedule</h1>
                <p class="text-muted mb-0" style="color:#000;">Manage upcoming hearings, review schedule details, and connect each hearing to its recorded result.</p>
            </div>
            <a href="hearing_result.php" class="btn btn-primary fw-semibold"><i class="bi bi-card-checklist me-1"></i>View Hearing Results</a>
        </div>

        <!-- Clickable Stat Boxes -->
        <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-4 g-3 mb-4">
            <div class="col">
                <a href="Hearing_schedule.php?date_from=<?= date('Y-m-d') ?>" class="text-decoration-none text-dark" title="Filter Upcoming hearings">
                    <div class="card border shadow-sm h-100" style="background:#fff; color:#000; cursor:pointer; transition:transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='0 8px 20px rgba(0,0,0,0.08)'" onmouseout="this.style.transform='none'; this.style.boxShadow=''">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="text-uppercase text-primary fw-bold mb-0" style="font-size:0.82rem; letter-spacing:.08em;">Upcoming Hearings</h6>
                                <i class="bi bi-calendar-check text-primary fs-5"></i>
                            </div>
                            <div class="display-6 fw-bold"><?= intval($summary['upcoming']) ?></div>
                            <p class="mb-0 text-muted small">Scheduled today or later &rarr;</p>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col">
                <a href="Hearing_schedule.php?date_from=<?= date('Y-m-d') ?>&date_to=<?= date('Y-m-d') ?>" class="text-decoration-none text-dark" title="Filter Today's hearings">
                    <div class="card border shadow-sm h-100" style="background:#fff; color:#000; cursor:pointer; transition:transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='0 8px 20px rgba(0,0,0,0.08)'" onmouseout="this.style.transform='none'; this.style.boxShadow=''">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="text-uppercase text-success fw-bold mb-0" style="font-size:0.82rem; letter-spacing:.08em;">Today</h6>
                                <i class="bi bi-clock text-success fs-5"></i>
                            </div>
                            <div class="display-6 fw-bold"><?= intval($summary['today']) ?></div>
                            <p class="mb-0 text-muted small">Happening today &rarr;</p>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col">
                <a href="Hearing_schedule.php?date_to=<?= date('Y-m-d', strtotime('-1 day')) ?>" class="text-decoration-none text-dark" title="Filter Past hearings">
                    <div class="card border shadow-sm h-100" style="background:#fff; color:#000; cursor:pointer; transition:transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='0 8px 20px rgba(0,0,0,0.08)'" onmouseout="this.style.transform='none'; this.style.boxShadow=''">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="text-uppercase text-secondary fw-bold mb-0" style="font-size:0.82rem; letter-spacing:.08em;">Past Hearings</h6>
                                <i class="bi bi-archive text-secondary fs-5"></i>
                            </div>
                            <div class="display-6 fw-bold"><?= intval($summary['past']) ?></div>
                            <p class="mb-0 text-muted small">Hearings already held &rarr;</p>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col">
                <a href="Hearing_schedule.php?unscheduled=1" class="text-decoration-none text-dark" title="Filter Unscheduled blotters">
                    <div class="card border shadow-sm h-100" style="background:#fff; color:#000; cursor:pointer; transition:transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='0 8px 20px rgba(0,0,0,0.08)'" onmouseout="this.style.transform='none'; this.style.boxShadow=''">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="text-uppercase text-warning fw-bold mb-0" style="font-size:0.82rem; letter-spacing:.08em;">Unscheduled</h6>
                                <i class="bi bi-question-circle text-warning fs-5"></i>
                            </div>
                            <div class="display-6 fw-bold"><?= intval($summary['unscheduled']) ?></div>
                            <p class="mb-0 text-muted small">Needs hearing details &rarr;</p>
                        </div>
                    </div>
                </a>
            </div>
        </div>

        <div class="card mb-4" style="background:#fff; color:#000; border:1px solid #e9ecef;">
            <div class="card-body">
                <form method="GET" class="row gx-3 gy-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label" style="color:#000;">Search</label>
                        <input type="text" name="search" class="form-control" value="<?= htmlspecialchars($search) ?>" placeholder="Blotter No, complainant, respondent, incident, location">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" style="color:#000;">From</label>
                        <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($date_from) ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" style="color:#000;">To</label>
                        <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($date_to) ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" style="color:#000;">Status</label>
                        <select name="status" class="form-select">
                            <option value="">All Status</option>
                            <option value="Pending" <?= $status_filter === 'Pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="Under Investigation" <?= $status_filter === 'Under Investigation' ? 'selected' : '' ?>>Under Investigation</option>
                            <option value="Resolved" <?= $status_filter === 'Resolved' ? 'selected' : '' ?>>Resolved</option>
                            <option value="Archived" <?= $status_filter === 'Archived' ? 'selected' : '' ?>>Archived</option>
                            <option value="Rejected" <?= $status_filter === 'Rejected' ? 'selected' : '' ?>>Rejected</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-grid">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-filter me-1"></i>Filter Schedule</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card" style="background:#fff; color:#000; border:1px solid #e9ecef;">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2 py-3 px-4" style="background:#fff; color:#000; border-bottom:1px solid #e9ecef;">
                <div>
                    <h5 class="mb-0 fw-bold">Powerful Hearing Schedule</h5>
                    <small class="text-muted" style="color:#000;">Track hearing events, monitor dates, and launch hearing results from one dashboard.</small>
                </div>
                <div>
                    <span class="badge bg-primary rounded-pill px-3 py-2 fw-bold">
                        Showing <?= count($hearings) ?> of <?= $total_rows ?> Schedule(s)
                    </span>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped mb-0" style="color:#000;">
                        <thead style="background:#f8f9fa; color:#000;">
                            <tr>
                                <th>Blotter No</th>
                                <th>Complainant</th>
                                <th>Respondent</th>
                                <th>Incident Type</th>
                                <th>Hearing Date</th>
                                <th>Hearing Time</th>
                                <th>Hearing Location</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($hearings)): ?>
                                <tr><td colspan="9" class="text-center" style="color:#000;">No blotter records found for the selected filters.</td></tr>
                            <?php else: ?>
                                <?php foreach ($hearings as $row): ?>
                                    <?php
                                        $dateLabel = $row['hearing_date'] ? date('M d, Y', strtotime($row['hearing_date'])) : 'TBA';
                                        $timeLabel = $row['hearing_time'] ? date('h:i A', strtotime($row['hearing_time'])) : 'TBA';
                                        $locationLabel = htmlspecialchars($row['hearing_location'] ?: 'TBA');
                                    ?>
                                    <tr>
                                        <td class="fw-bold"><?= htmlspecialchars($row['blotter_no']) ?></td>
                                        <td><?= htmlspecialchars($row['complainant_name'] ?: 'N/A') ?></td>
                                        <td><?= htmlspecialchars($row['respondent_name'] ?: 'N/A') ?></td>
                                        <td><?= htmlspecialchars($row['incident_type'] ?: 'N/A') ?></td>
                                        <td><?= $dateLabel ?></td>
                                        <td><?= $timeLabel ?></td>
                                        <td><?= $locationLabel ?></td>
                                        <td><?= htmlspecialchars($row['status'] ?: 'N/A') ?></td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="../modules/blotter_update.php?id=<?= intval($row['id']) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil-square me-1"></i>Edit</a>
                                                <a href="hearing_result.php?blotter_id=<?= intval($row['id']) ?>" class="btn btn-sm btn-primary"><i class="bi bi-file-earmark-text me-1"></i>Result</a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php if ($total_pages > 1): ?>
                <div class="card-footer bg-white border-top py-3 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="small text-muted">
                        Showing <strong><?= $total_rows > 0 ? ($offset + 1) : 0 ?></strong> to <strong><?= min($offset + $per_page, $total_rows) ?></strong> of <strong><?= $total_rows ?></strong> entries (10 per page)
                    </div>
                    <nav aria-label="Hearing schedule pagination">
                        <ul class="pagination pagination-sm mb-0">
                            <!-- Previous Page -->
                            <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= ($page > 1) ? buildPageUrl($page - 1, $search, $date_from, $date_to, $status_filter) : '#' ?>" tabindex="-1">
                                    <i class="bi bi-chevron-left me-1"></i>Prev
                                </a>
                            </li>

                            <!-- Page numbers -->
                            <?php 
                                $startPage = max(1, $page - 2);
                                $endPage = min($total_pages, $page + 2);
                                if ($startPage > 1): ?>
                                    <li class="page-item"><a class="page-link" href="<?= buildPageUrl(1, $search, $date_from, $date_to, $status_filter) ?>">1</a></li>
                                    <?php if ($startPage > 2): ?><li class="page-item disabled"><span class="page-link">&hellip;</span></li><?php endif; ?>
                            <?php endif; ?>

                            <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                                <li class="page-item <?= ($i === $page) ? 'active' : '' ?>">
                                    <a class="page-link" href="<?= buildPageUrl($i, $search, $date_from, $date_to, $status_filter) ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($endPage < $total_pages): ?>
                                <?php if ($endPage < $total_pages - 1): ?><li class="page-item disabled"><span class="page-link">&hellip;</span></li><?php endif; ?>
                                <li class="page-item"><a class="page-link" href="<?= buildPageUrl($total_pages, $search, $date_from, $date_to, $status_filter) ?>"><?= $total_pages ?></a></li>
                            <?php endif; ?>

                            <!-- Next Page -->
                            <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= ($page < $total_pages) ? buildPageUrl($page + 1, $search, $date_from, $date_to, $status_filter) : '#' ?>">
                                    Next<i class="bi bi-chevron-right ms-1"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                </div>
            <?php else: ?>
                <div class="card-footer bg-white border-top py-2 px-4 small text-muted">
                    Showing <strong><?= count($hearings) ?></strong> of <strong><?= $total_rows ?></strong> entries (10 per page)
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
