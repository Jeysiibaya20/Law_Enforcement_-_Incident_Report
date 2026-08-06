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

if ($search !== '') {
    $where[] = '(blotter_no LIKE ? OR complainant_name LIKE ? OR respondent_name LIKE ? OR incident_type LIKE ? OR location LIKE ?)';
    $term = '%' . $search . '%';
    $params = array_merge($params, [$term, $term, $term, $term, $term]);
}

if ($date_from !== '') {
    $where[] = 'hearing_date >= ?';
    $params[] = $date_from;
}

if ($date_to !== '') {
    $where[] = 'hearing_date <= ?';
    $params[] = $date_to;
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
    $hearings = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $hearings = [];
}

try {
    $summaryStmt = $pdo->query("SELECT
        SUM(CASE WHEN hearing_date >= CURDATE() THEN 1 ELSE 0 END) AS upcoming,
        SUM(CASE WHEN hearing_date = CURDATE() THEN 1 ELSE 0 END) AS today,
        SUM(CASE WHEN hearing_date < CURDATE() THEN 1 ELSE 0 END) AS past,
        SUM(CASE WHEN hearing_date IS NULL AND hearing_time IS NULL AND hearing_location IS NULL THEN 1 ELSE 0 END) AS unscheduled
        FROM blotters");
    $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $summary = ['upcoming' => 0, 'today' => 0, 'past' => 0, 'unscheduled' => 0];
}
?>
<div class="main-content" style="background:#fff; color:#000;">
    <div class="content-container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h2" style="color:#000;">Hearing Schedule</h1>
                <p class="text-muted mb-0" style="color:#000;">Manage upcoming hearings, review schedule details, and connect each hearing to its recorded result.</p>
            </div>
            <a href="hearing_result.php" class="btn btn-dark">View Hearing Results</a>
        </div>

        <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-4 g-3 mb-4">
            <div class="col">
                <div class="card border-0 shadow-sm" style="background:#fff; color:#000;">
                    <div class="card-body">
                        <h6 class="text-uppercase mb-2" style="font-size:0.82rem; letter-spacing:.08em;">Upcoming Hearings</h6>
                        <div class="display-6 fw-bold"><?= intval($summary['upcoming']) ?></div>
                        <p class="mb-0 text-muted" style="color:#000;">Hearings scheduled today or later.</p>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card border-0 shadow-sm" style="background:#fff; color:#000;">
                    <div class="card-body">
                        <h6 class="text-uppercase mb-2" style="font-size:0.82rem; letter-spacing:.08em;">Today</h6>
                        <div class="display-6 fw-bold"><?= intval($summary['today']) ?></div>
                        <p class="mb-0 text-muted" style="color:#000;">Hearings happening today.</p>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card border-0 shadow-sm" style="background:#fff; color:#000;">
                    <div class="card-body">
                        <h6 class="text-uppercase mb-2" style="font-size:0.82rem; letter-spacing:.08em;">Past Hearings</h6>
                        <div class="display-6 fw-bold"><?= intval($summary['past']) ?></div>
                        <p class="mb-0 text-muted" style="color:#000;">Hearings already held.</p>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card border-0 shadow-sm" style="background:#fff; color:#000;">
                    <div class="card-body">
                        <h6 class="text-uppercase mb-2" style="font-size:0.82rem; letter-spacing:.08em;">Unscheduled</h6>
                        <div class="display-6 fw-bold"><?= intval($summary['unscheduled']) ?></div>
                        <p class="mb-0 text-muted" style="color:#000;">Records that still need hearing details.</p>
                    </div>
                </div>
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
                        <button type="submit" class="btn btn-dark">Filter Schedule</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card" style="background:#fff; color:#000; border:1px solid #e9ecef;">
            <div class="card-header" style="background:#fff; color:#000; border-bottom:1px solid #e9ecef;">
                <h5 class="mb-0">Powerful Hearing Schedule</h5>
                <small class="text-muted" style="color:#000;">Track hearing events, monitor dates, and launch hearing results from one dashboard.</small>
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
                                                <a href="../modules/blotter_update.php?id=<?= intval($row['id']) ?>" class="btn btn-sm btn-outline-dark">Edit</a>
                                                <a href="hearing_result.php?blotter_id=<?= intval($row['id']) ?>" class="btn btn-sm btn-dark">Result</a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
