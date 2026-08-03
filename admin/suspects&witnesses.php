<?php
require_once 'admin_auth.php';
require_once '../config/db_connect.php';
require_once '../includes/suspect_witness_management.php';

$base_url = '../';
$page_title = 'Suspects & Witnesses';

try {
    $stmt = $pdo->prepare("SELECT ca.id AS case_id,
                                  ca.case_number,
                                  ca.incident_type,
                                  ca.complainant_name,
                                  ca.status,
                                  ca.priority,
                                  COALESCE(sus.count, 0) AS suspect_count,
                                  COALESCE(wit.count, 0) AS witness_count
                           FROM case_assignments ca
                           LEFT JOIN (
                               SELECT case_id, COUNT(*) AS count
                               FROM suspects
                               WHERE deleted_at IS NULL
                               GROUP BY case_id
                           ) sus ON sus.case_id = ca.id
                           LEFT JOIN (
                               SELECT case_id, COUNT(*) AS count
                               FROM witnesses
                               GROUP BY case_id
                           ) wit ON wit.case_id = ca.id
                           ORDER BY ca.created_at DESC");
    $stmt->execute();
    $cases = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totals = $pdo->query("SELECT
                               (SELECT COUNT(*) FROM suspects WHERE deleted_at IS NULL) AS total_suspects,
                               (SELECT COUNT(*) FROM witnesses) AS total_witnesses")->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = $e->getMessage();
    $cases = [];
    $totals = ['total_suspects' => 0, 'total_witnesses' => 0];
}

include '../includes/header.php';
include '../includes/navbar.php';
?>

<style>
    .suspects-witnesses-page {
        background-color: #ffffff !important;
        color: #000000 !important;
    }
    .suspects-witnesses-page .content-container,
    .suspects-witnesses-page .card,
    .suspects-witnesses-page .table,
    .suspects-witnesses-page .table th,
    .suspects-witnesses-page .table td,
    .suspects-witnesses-page .card-header,
    .suspects-witnesses-page .card-body,
    .suspects-witnesses-page .card-title,
    .suspects-witnesses-page h1,
    .suspects-witnesses-page p,
    .suspects-witnesses-page .badge,
    .suspects-witnesses-page .btn {
        color: #000000 !important;
    }
    .suspects-witnesses-page .card,
    .suspects-witnesses-page .card-header,
    .suspects-witnesses-page .table,
    .suspects-witnesses-page .table thead,
    .suspects-witnesses-page .table tbody tr {
        background-color: #ffffff !important;
    }
    .suspects-witnesses-page .btn-primary,
    .suspects-witnesses-page .btn-secondary,
    .suspects-witnesses-page .btn-info {
        background-color: #f0f0f0 !important;
        border-color: #dcdcdc !important;
        color: #000000 !important;
    }
    .suspects-witnesses-page .btn-primary:hover,
    .suspects-witnesses-page .btn-secondary:hover,
    .suspects-witnesses-page .btn-info:hover {
        background-color: #e0e0e0 !important;
        color: #000000 !important;
    }
</style>

<div class="main-content suspects-witnesses-page">
    <div class="content-container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h2">Suspects & Witnesses</h1>
                <p class="text-muted mb-0">View and manage suspects and witnesses for all cases.</p>
            </div>
        </div>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($error_message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row row-cols-1 row-cols-md-2 g-3 mb-4">
            <div class="col">
                <div class="card border-start border-danger border-4 h-100">
                    <div class="card-body">
                        <h5 class="card-title">Total Suspects</h5>
                        <div class="display-5 mb-2"><?= intval($totals['total_suspects']) ?></div>
                        <p class="text-muted mb-0">Active suspect records across all cases.</p>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card border-start border-info border-4 h-100">
                    <div class="card-body">
                        <h5 class="card-title">Total Witnesses</h5>
                        <div class="display-5 mb-2"><?= intval($totals['total_witnesses']) ?></div>
                        <p class="text-muted mb-0">Witness records across all cases.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Suspects by Case</h5>
                        <a href="../admin/suspects_management.php" class="btn btn-sm btn-primary">
                            <i class="bi bi-plus-circle"></i> Add Suspect
                        </a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Case Number</th>
                                        <th>Incident Type</th>
                                        <th>Status</th>
                                        <th>Suspects</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($cases)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center text-muted">No cases found.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($cases as $case): ?>
                                            <tr>
                                                <td><strong><?= htmlspecialchars($case['case_number']) ?></strong></td>
                                                <td><?= htmlspecialchars($case['incident_type']) ?></td>
                                                <td>
                                                    <span class="badge bg-<?= match($case['status']) {
                                                        'New' => 'info',
                                                        'Ongoing' => 'primary',
                                                        'Resolved' => 'success',
                                                        'Closed' => 'secondary',
                                                        default => 'secondary'
                                                    } ?>"><?= htmlspecialchars($case['status']) ?></span>
                                                </td>
                                                <td><span class="badge bg-danger"><?= intval($case['suspect_count']) ?></span></td>
                                                <td>
                                                    <a href="../admin/suspects_management.php?case_id=<?= intval($case['case_id']) ?>" class="btn btn-sm btn-outline-primary">
                                                        Manage
                                                    </a>
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

            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Witnesses by Case</h5>
                        <a href="../admin/witnesses_management.php" class="btn btn-sm btn-info text-white">
                            <i class="bi bi-plus-circle"></i> Add Witness
                        </a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Case Number</th>
                                        <th>Incident Type</th>
                                        <th>Status</th>
                                        <th>Witnesses</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($cases)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center text-muted">No cases found.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($cases as $case): ?>
                                            <tr>
                                                <td><strong><?= htmlspecialchars($case['case_number']) ?></strong></td>
                                                <td><?= htmlspecialchars($case['incident_type']) ?></td>
                                                <td>
                                                    <span class="badge bg-<?= match($case['status']) {
                                                        'New' => 'info',
                                                        'Ongoing' => 'primary',
                                                        'Resolved' => 'success',
                                                        'Closed' => 'secondary',
                                                        default => 'secondary'
                                                    } ?>"><?= htmlspecialchars($case['status']) ?></span>
                                                </td>
                                                <td><span class="badge bg-info"><?= intval($case['witness_count']) ?></span></td>
                                                <td>
                                                    <a href="../admin/witnesses_management.php?case_id=<?= intval($case['case_id']) ?>" class="btn btn-sm btn-outline-info">
                                                        Manage
                                                    </a>
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
    </div>
</div>

</body>
</html>
