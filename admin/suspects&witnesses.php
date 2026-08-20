<?php
require_once 'admin_auth.php';
require_once '../config/db_connect.php';
require_once '../includes/suspect_witness_management.php';

$base_url = '../';
$page_title = 'Suspects & Witnesses';

$privacyDescription = '';
$privacyFilePath = dirname(__DIR__) . '/data_privacy/suspect_witness_policy.md';
if (file_exists($privacyFilePath)) {
    $privacyDescription = file_get_contents($privacyFilePath);
}

function renderMarkdownToHtml($markdown) {
    $lines = preg_split('/\r\n|\r|\n/', $markdown);
    $html = '';
    $listOpen = false;

    foreach ($lines as $line) {
        $trim = trim($line);

        if ($trim === '') {
            if ($listOpen) {
                $html .= "</ul>\n";
                $listOpen = false;
            }
            $html .= "\n";
            continue;
        }

        if (preg_match('/^######\s+(.+)$/', $trim, $m)) {
            $html .= "<h6>" . htmlspecialchars($m[1]) . "</h6>\n";
        } elseif (preg_match('/^#####\s+(.+)$/', $trim, $m)) {
            $html .= "<h5>" . htmlspecialchars($m[1]) . "</h5>\n";
        } elseif (preg_match('/^####\s+(.+)$/', $trim, $m)) {
            $html .= "<h4>" . htmlspecialchars($m[1]) . "</h4>\n";
        } elseif (preg_match('/^###\s+(.+)$/', $trim, $m)) {
            $html .= "<h3>" . htmlspecialchars($m[1]) . "</h3>\n";
        } elseif (preg_match('/^##\s+(.+)$/', $trim, $m)) {
            $html .= "<h4>" . htmlspecialchars($m[1]) . "</h4>\n";
        } elseif (preg_match('/^#\s+(.+)$/', $trim, $m)) {
            $html .= "<h3>" . htmlspecialchars($m[1]) . "</h3>\n";
        } elseif (preg_match('/^[\-\*\+]\s+(.+)$/', $trim, $m)) {
            if (! $listOpen) {
                $html .= "<ul class=\"mb-3\">\n";
                $listOpen = true;
            }
            $html .= "<li>" . htmlspecialchars($m[1]) . "</li>\n";
        } else {
            if ($listOpen) {
                $html .= "</ul>\n";
                $listOpen = false;
            }
            $html .= "<p>" . htmlspecialchars($trim) . "</p>\n";
        }
    }

    if ($listOpen) {
        $html .= "</ul>\n";
    }

    return $html;
}

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
    .suspects-witnesses-page .btn-success {
        background-color: #2e856e !important;
        border-color: #2e856e !important;
        color: #ffffff !important;
    }
    .suspects-witnesses-page .btn-primary:hover,
    .suspects-witnesses-page .btn-success:hover {
        background-color: #246a58 !important;
        border-color: #246a58 !important;
        color: #ffffff !important;
    }

    .privacy-float-card {
        position: sticky;
        top: 1rem;
        box-shadow: 0 12px 30px rgba(0, 0, 0, 0.08);
        background: linear-gradient(180deg, #fffdf7 0%, #ffffff 100%);
        max-width: 100%;
    }

    .privacy-card-content {
        max-height: 520px;
        overflow-y: auto;
        padding-right: 0.75rem;
    }

    .privacy-card-content h3,
    .privacy-card-content h4,
    .privacy-card-content h5,
    .privacy-card-content h6 {
        margin-top: 1rem;
    }

    .privacy-card-content p {
        margin-bottom: 0.75rem;
        line-height: 1.6;
    }

    .privacy-card-content ul {
        margin-bottom: 1rem;
        padding-left: 1.25rem;
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

        <div class="row g-3 mb-4">
            <div class="col-12 col-md-6">
                <article class="dashboard-analytics-card analytics-tone-danger h-100">
                    <div class="dashboard-analytics-head">
                        <span class="dashboard-analytics-label">Total Suspects</span>
                        <span class="dashboard-analytics-icon"><i class="fas fa-user-ninja"></i></span>
                    </div>
                    <div class="dashboard-analytics-value"><?= intval($totals['total_suspects']) ?></div>
                    <div class="dashboard-analytics-sub">Active suspect records across all cases</div>
                </article>
            </div>
            <div class="col-12 col-md-6">
                <article class="dashboard-analytics-card analytics-tone-info h-100">
                    <div class="dashboard-analytics-head">
                        <span class="dashboard-analytics-label">Total Witnesses</span>
                        <span class="dashboard-analytics-icon"><i class="fas fa-eye"></i></span>
                    </div>
                    <div class="dashboard-analytics-value"><?= intval($totals['total_witnesses']) ?></div>
                    <div class="dashboard-analytics-sub">Witness records across all cases</div>
                </article>
            </div>
        </div>

        <div class="row mb-4 justify-content-center">
            <div class="col-lg-6">
                <div class="card privacy-float-card border-start border-warning border-4 h-100 mx-auto">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <h5 class="card-title">High Privacy Overview</h5>
                                <p class="text-muted mb-0">Review how suspect and witness data privacy is protected in the system.</p>
                            </div>
                            <span class="badge bg-warning text-dark">High Data Privacy</span>
                        </div>
                        <div class="privacy-card-content">
                            <?php if (!empty($privacyDescription)): ?>
                                <?= renderMarkdownToHtml($privacyDescription) ?>
                            <?php else: ?>
                                <p class="text-muted">Privacy details are not available. Please check the data_privacy/suspect_witness_policy.md file.</p>
                            <?php endif; ?>
                        </div>
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

        <!-- Data Privacy Access Audit Trail Table -->
        <div class="card mt-4 shadow-sm border-dark">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0"><i class="fas fa-history text-warning me-2"></i>Recent Data Privacy Access Audit Logs (`suspect_witness_privacy_audit`)</h5>
                <a href="../modules/Suspect&Witness.php" class="btn btn-sm btn-warning text-dark fw-bold">
                    <i class="fas fa-external-link-alt me-1"></i> Open Suspect & Witness Workspace
                </a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size: 0.85rem;">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Action</th>
                                <th>Target Type</th>
                                <th>Investigator</th>
                                <th>IP Address</th>
                                <th>Activity Notes</th>
                                <th>Timestamp</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $recentAudits = getPrivacyAuditLogs($pdo, 10);
                            if (empty($recentAudits)): 
                            ?>
                                <tr><td colspan="7" class="text-center text-muted py-3">No privacy access audit logs recorded yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($recentAudits as $ra): ?>
                                    <tr>
                                        <td><strong>#<?= htmlspecialchars($ra['id']) ?></strong></td>
                                        <td>
                                            <span class="badge bg-<?= match($ra['action']) {
                                                'UNMASK_VIEW' => 'warning text-dark',
                                                'CREATE_SUSPECT', 'CREATE_WITNESS' => 'success',
                                                default => 'secondary'
                                            } ?>"><?= htmlspecialchars($ra['action']) ?></span>
                                        </td>
                                        <td><code><?= htmlspecialchars($ra['target_type']) ?></code></td>
                                        <td><?= htmlspecialchars($ra['performer_name']) ?></td>
                                        <td><code><?= htmlspecialchars($ra['ip_address']) ?></code></td>
                                        <td><?= htmlspecialchars($ra['details']) ?></td>
                                        <td><?= date('M d, Y H:i:s', strtotime($ra['created_at'])) ?></td>
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

</body>
</html>

