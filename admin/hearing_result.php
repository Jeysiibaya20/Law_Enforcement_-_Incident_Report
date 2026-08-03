<?php
require_once 'admin_auth.php';
$base_url = '../';
$page_title = 'Hearing Result';
require_once '../includes/header.php';
require_once '../includes/navbar.php';

$blotter_id = filter_input(INPUT_GET, 'blotter_id', FILTER_VALIDATE_INT) ?: 0;
$message = '';
$error = '';

function ensureHearingResultColumns(PDO $pdo)
{
    $columns = [
        'hearing_result_status' => 'VARCHAR(80) NULL AFTER hearing_location',
        'hearing_result_summary' => 'TEXT NULL AFTER hearing_result_status'
    ];

    foreach ($columns as $column => $definition) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'blotters' AND COLUMN_NAME = ?");
        $stmt->execute([$column]);
        if ((int)$stmt->fetchColumn() === 0) {
            $pdo->exec("ALTER TABLE blotters ADD COLUMN {$column} {$definition}");
        }
    }
}

try {
    ensureHearingResultColumns($pdo);
} catch (Exception $e) {
    error_log('Hearing result columns check failed: ' . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_result'])) {
    $blotter_id = intval($_POST['blotter_id'] ?? 0);
    $result_status = trim($_POST['result_status'] ?? '');
    $result_summary = trim($_POST['result_summary'] ?? '');

    if ($blotter_id <= 0) {
        $error = 'Invalid hearing record.';
    } elseif ($result_status === '' && $result_summary === '') {
        $error = 'Please provide a result status or summary.';
    } else {
        try {
            $stmt = $pdo->prepare('UPDATE blotters SET hearing_result_status = ?, hearing_result_summary = ?, updated_at = NOW() WHERE id = ?');
            $stmt->execute([$result_status ?: null, $result_summary ?: null, $blotter_id]);
            $message = 'Hearing result saved successfully.';
        } catch (Exception $e) {
            $error = 'Unable to save hearing result. ' . $e->getMessage();
        }
    }
}

$selectedBlotter = null;
if ($blotter_id > 0) {
    $stmt = $pdo->prepare('SELECT * FROM blotters WHERE id = ?');
    $stmt->execute([$blotter_id]);
    $selectedBlotter = $stmt->fetch(PDO::FETCH_ASSOC);
}

try {
    $resultsStmt = $pdo->query('SELECT * FROM blotters WHERE hearing_result_status IS NOT NULL OR hearing_result_summary IS NOT NULL ORDER BY hearing_date DESC, updated_at DESC');
    $results = $resultsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $results = [];
}
?>
<div class="main-content" style="background:#fff; color:#000;">
    <div class="content-container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h2" style="color:#000;">Hearing Result</h1>
                <p class="text-muted mb-0" style="color:#000;">Record outcome details and keep hearing results connected to the schedule.</p>
            </div>
            <a href="Hearing_schedule.php" class="btn btn-dark">Back to Schedule</a>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success" role="alert" style="background:#e9f8ed; color:#000; border-color:#c8e6c9;">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger" role="alert" style="background:#ffe8e8; color:#000; border-color:#f5c2c2;">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($selectedBlotter): ?>
            <div class="card mb-4" style="background:#fff; color:#000; border:1px solid #e9ecef;">
                <div class="card-header" style="background:#fff; color:#000; border-bottom:1px solid #e9ecef;">
                    <h5 class="mb-0">Hearing Result for <?= htmlspecialchars($selectedBlotter['blotter_no']) ?></h5>
                </div>
                <div class="card-body">
                    <div class="row gy-3 mb-4">
                        <div class="col-md-4">
                            <h6 class="text-muted" style="color:#000;">Hearing Date</h6>
                            <p class="mb-0"><?= $selectedBlotter['hearing_date'] ? date('M d, Y', strtotime($selectedBlotter['hearing_date'])) : 'TBA' ?></p>
                        </div>
                        <div class="col-md-4">
                            <h6 class="text-muted" style="color:#000;">Hearing Time</h6>
                            <p class="mb-0"><?= $selectedBlotter['hearing_time'] ? date('h:i A', strtotime($selectedBlotter['hearing_time'])) : 'TBA' ?></p>
                        </div>
                        <div class="col-md-4">
                            <h6 class="text-muted" style="color:#000;">Location</h6>
                            <p class="mb-0"><?= htmlspecialchars($selectedBlotter['hearing_location'] ?: 'TBA') ?></p>
                        </div>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="blotter_id" value="<?= intval($selectedBlotter['id']) ?>">
                        <div class="mb-3">
                            <label class="form-label" style="color:#000;">Result Status</label>
                            <select name="result_status" class="form-select">
                                <option value=""<?= empty($selectedBlotter['hearing_result_status']) ? ' selected' : '' ?>>Select result status</option>
                                <option value="Resolved"<?= $selectedBlotter['hearing_result_status'] === 'Resolved' ? ' selected' : '' ?>>Resolved</option>
                                <option value="Postponed"<?= $selectedBlotter['hearing_result_status'] === 'Postponed' ? ' selected' : '' ?>>Postponed</option>
                                <option value="No Show"<?= $selectedBlotter['hearing_result_status'] === 'No Show' ? ' selected' : '' ?>>No Show</option>
                                <option value="Dismissed"<?= $selectedBlotter['hearing_result_status'] === 'Dismissed' ? ' selected' : '' ?>>Dismissed</option>
                                <option value="Continued"<?= $selectedBlotter['hearing_result_status'] === 'Continued' ? ' selected' : '' ?>>Continued</option>
                                <option value="Pending Review"<?= $selectedBlotter['hearing_result_status'] === 'Pending Review' ? ' selected' : '' ?>>Pending Review</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" style="color:#000;">Summary of Result</label>
                            <textarea name="result_summary" class="form-control" rows="5"><?= htmlspecialchars($selectedBlotter['hearing_result_summary'] ?? '') ?></textarea>
                        </div>
                        <button type="submit" name="save_result" class="btn btn-dark">Save Hearing Result</button>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-info" role="alert" style="background:#eef5ff; color:#000; border-color:#cfe2ff;">
                Select a hearing schedule from the schedule page to record the result.
            </div>
        <?php endif; ?>

        <div class="card" style="background:#fff; color:#000; border:1px solid #e9ecef;">
            <div class="card-header" style="background:#fff; color:#000; border-bottom:1px solid #e9ecef;">
                <h5 class="mb-0">Recorded Hearing Results</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" style="color:#000;">
                        <thead style="background:#f8f9fa; color:#000;">
                            <tr>
                                <th>Blotter No</th>
                                <th>Hearing Date</th>
                                <th>Hearing Location</th>
                                <th>Result Status</th>
                                <th>Result Summary</th>
                                <th>Recorded At</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($results)): ?>
                                <tr><td colspan="7" class="text-center" style="color:#000;">No hearing results recorded yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($results as $result): ?>
                                    <tr>
                                        <td class="fw-bold"><?= htmlspecialchars($result['blotter_no']) ?></td>
                                        <td><?= $result['hearing_date'] ? date('M d, Y', strtotime($result['hearing_date'])) : 'TBA' ?></td>
                                        <td><?= htmlspecialchars($result['hearing_location'] ?: 'TBA') ?></td>
                                        <td><?= htmlspecialchars($result['hearing_result_status'] ?: 'Pending') ?></td>
                                        <td><?= nl2br(htmlspecialchars(substr($result['hearing_result_summary'] ?? '', 0, 120))) ?></td>
                                        <td><?= $result['updated_at'] ? date('M d, Y H:i', strtotime($result['updated_at'])) : 'N/A' ?></td>
                                        <td>
                                            <a href="Hearing_result.php?blotter_id=<?= intval($result['id']) ?>" class="btn btn-sm btn-outline-dark">Edit</a>
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
