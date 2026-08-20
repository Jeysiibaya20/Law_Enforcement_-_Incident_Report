<?php
require_once 'admin_auth.php';
if (!isset($pdo) || !($pdo instanceof PDO)) {
    $pdo = getDBConnection();
}

$base_url = '../';
$page_title = 'Settlement Aggregation';
$current_page = 'settlement';
require_once '../includes/header.php';

function ensureSettlementColumns(PDO $pdo): void
{
    $columns = [
        'settlement_status' => 'VARCHAR(80) NULL AFTER hearing_result_status',
        'settlement_agreement' => 'TEXT NULL AFTER settlement_status',
        'settlement_date' => 'DATE NULL AFTER settlement_agreement',
    ];

    foreach ($columns as $column => $definition) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'blotters' AND COLUMN_NAME = ?");
        $stmt->execute([$column]);
        if ((int) $stmt->fetchColumn() === 0) {
            $pdo->exec("ALTER TABLE blotters ADD COLUMN {$column} {$definition}");
        }
    }
}

try {
    ensureSettlementColumns($pdo);
} catch (Exception $e) {
    error_log('Settlement columns check failed: ' . $e->getMessage());
}

$message = '';
$error = '';
$blotter_id = filter_input(INPUT_GET, 'blotter_id', FILTER_VALIDATE_INT) ?: 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settlement'])) {
    $blotter_id = intval($_POST['blotter_id'] ?? 0);
    $settlement_status = trim($_POST['settlement_status'] ?? '');
    $settlement_agreement = trim($_POST['settlement_agreement'] ?? '');
    $settlement_date = trim($_POST['settlement_date'] ?? '');

    if ($blotter_id <= 0) {
        $error = 'Invalid blotter record.';
    } elseif ($settlement_status === '') {
        $error = 'Please provide a settlement status.';
    } else {
        try {
            $stmt = $pdo->prepare('UPDATE blotters SET settlement_status = ?, settlement_agreement = ?, settlement_date = ?, updated_at = NOW() WHERE id = ?');
            $stmt->execute([$settlement_status, $settlement_agreement ?: null, $settlement_date ?: null, $blotter_id]);
            $message = 'Settlement agreement saved successfully.';
        } catch (Exception $e) {
            $error = 'Unable to save settlement agreement. ' . $e->getMessage();
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
    $totalBlotters = (int) $pdo->query('SELECT COUNT(*) FROM blotters')->fetchColumn();
    $resolvedBlotters = (int) $pdo->query("SELECT COUNT(*) FROM blotters WHERE status = 'Resolved' OR hearing_result_status = 'Resolved' OR settlement_status = 'Settled'")->fetchColumn();
    $pendingSettlement = (int) $pdo->query("SELECT COUNT(*) FROM blotters WHERE (hearing_result_status IS NULL OR hearing_result_status = '') AND (settlement_status IS NULL OR settlement_status = '')")->fetchColumn();
    $agreementCount = (int) $pdo->query("SELECT COUNT(*) FROM blotters WHERE settlement_status IS NOT NULL AND settlement_status <> ''")->fetchColumn();

    $statusBreakdown = $pdo->query("SELECT settlement_status, COUNT(*) AS total FROM blotters WHERE settlement_status IS NOT NULL AND settlement_status <> '' GROUP BY settlement_status ORDER BY total DESC")->fetchAll(PDO::FETCH_ASSOC);
    $locationBreakdown = $pdo->query("SELECT location, COUNT(*) AS total FROM blotters WHERE location IS NOT NULL AND location <> '' GROUP BY location ORDER BY total DESC LIMIT 8")->fetchAll(PDO::FETCH_ASSOC);
    $settlementRows = $pdo->query("SELECT id, blotter_no, complainant_name, respondent_name, incident_type, location, hearing_result_status, settlement_status, settlement_agreement, settlement_date, updated_at FROM blotters ORDER BY CASE WHEN settlement_date IS NOT NULL THEN 0 ELSE 1 END, settlement_date DESC, updated_at DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $totalBlotters = 0;
    $resolvedBlotters = 0;
    $pendingSettlement = 0;
    $agreementCount = 0;
    $statusBreakdown = [];
    $locationBreakdown = [];
    $settlementRows = [];
}
?>
<style>
    @page {
        size: A4 portrait;
        margin: 20mm;
    }

    .print-settlement-paper {
        font-family: "Times New Roman", Georgia, serif;
        color: #000;
        background: #fff;
        width: 100%;
        max-width: 820px;
        margin: 0 auto;
        padding: 30px 28px;
        box-sizing: border-box;
        border: 1px solid #111;
        border-radius: 8px;
    }

    .kp-form-title {
        font-size: 1.15rem;
        font-weight: 700;
        margin-bottom: 18px;
    }

    .kp-form-line {
        display: inline-block;
        min-width: 150px;
        border-bottom: 1px solid #111;
        padding-bottom: 1px;
        margin: 0 4px;
    }

    .kp-form-block {
        margin-top: 10px;
        margin-bottom: 10px;
    }

    .kp-form-caption {
        text-align: center;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        margin: 16px 0 10px;
    }

    .kp-form-paragraph {
        font-size: 1rem;
        line-height: 1.8;
        margin: 12px 0;
    }

    .kp-form-signature {
        border-top: 1px solid #111;
        width: 250px;
        margin: 60px auto 0;
        text-align: center;
        padding-top: 6px;
    }

    @media print {
        body * {
            visibility: hidden;
        }

        .print-settlement-paper, .print-settlement-paper * {
            visibility: visible;
        }

        .print-settlement-paper {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
            max-width: none;
            border: none;
            border-radius: 0;
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        .print-hide,
        .navbar,
        .sidebar,
        .header,
        .footer,
        .main-content > .content-container > .row.row-cols-1.row-cols-md-4.g-3.mb-4,
        .card:not(.print-settlement-paper) {
            display: none !important;
        }
    }
</style>
<div class="main-content" style="background:#fff; color:#000;">
    <div class="content-container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h2" style="color:#000;">Settlement Aggregation</h1>
                <p class="text-muted mb-0" style="color:#000;">Review blotter-linked settlement outcomes and keep the agreement summary connected to each case record.</p>
            </div>
            <a href="blotters.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to Blotters</a>
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

        <div class="row g-3 mb-4">
            <div class="col-12 col-sm-6 col-xl-3">
                <article class="dashboard-analytics-card analytics-tone-notif h-100">
                    <div class="dashboard-analytics-head">
                        <span class="dashboard-analytics-label">Total Blotters</span>
                        <span class="dashboard-analytics-icon"><i class="fas fa-clipboard-list"></i></span>
                    </div>
                    <div class="dashboard-analytics-value"><?= $totalBlotters ?></div>
                    <div class="dashboard-analytics-sub">All connected records</div>
                </article>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <article class="dashboard-analytics-card analytics-tone-subs h-100">
                    <div class="dashboard-analytics-head">
                        <span class="dashboard-analytics-label">Resolved / Settled</span>
                        <span class="dashboard-analytics-icon"><i class="fas fa-check-circle"></i></span>
                    </div>
                    <div class="dashboard-analytics-value"><?= $resolvedBlotters ?></div>
                    <div class="dashboard-analytics-sub">Records with completed outcomes</div>
                </article>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <article class="dashboard-analytics-card analytics-tone-pending h-100">
                    <div class="dashboard-analytics-head">
                        <span class="dashboard-analytics-label">Awaiting Settlement</span>
                        <span class="dashboard-analytics-icon"><i class="fas fa-clock"></i></span>
                    </div>
                    <div class="dashboard-analytics-value"><?= $pendingSettlement ?></div>
                    <div class="dashboard-analytics-sub">Need hearing result or agreement</div>
                </article>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <article class="dashboard-analytics-card analytics-tone-purple h-100">
                    <div class="dashboard-analytics-head">
                        <span class="dashboard-analytics-label">Settlement Agreements</span>
                        <span class="dashboard-analytics-icon"><i class="fas fa-file-signature"></i></span>
                    </div>
                    <div class="dashboard-analytics-value"><?= $agreementCount ?></div>
                    <div class="dashboard-analytics-sub">Saved agreement summaries</div>
                </article>
            </div>
        </div>

        <?php if ($selectedBlotter): ?>
            <div class="card mb-4" style="background:#fff; color:#000; border:1px solid #e9ecef;">
                <div class="card-header" style="background:#fff; color:#000; border-bottom:1px solid #e9ecef;">
                    <h5 class="mb-0">Settlement Agreement for <?= htmlspecialchars($selectedBlotter['blotter_no']) ?></h5>
                </div>
                <div class="card-body">
                    <div class="row gy-3 mb-4">
                        <div class="col-md-4">
                            <h6 class="text-muted" style="color:#000;">Complainant</h6>
                            <p class="mb-0"><?= htmlspecialchars($selectedBlotter['complainant_name'] ?: 'N/A') ?></p>
                        </div>
                        <div class="col-md-4">
                            <h6 class="text-muted" style="color:#000;">Respondent</h6>
                            <p class="mb-0"><?= htmlspecialchars($selectedBlotter['respondent_name'] ?: 'N/A') ?></p>
                        </div>
                        <div class="col-md-4">
                            <h6 class="text-muted" style="color:#000;">Incident Type</h6>
                            <p class="mb-0"><?= htmlspecialchars($selectedBlotter['incident_type'] ?: 'N/A') ?></p>
                        </div>
                    </div>

                    <form method="POST">
                        <input type="hidden" name="blotter_id" value="<?= intval($selectedBlotter['id']) ?>">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label" style="color:#000;">Settlement Status</label>
                                <select name="settlement_status" class="form-select">
                                    <option value="">Select status</option>
                                    <option value="Settled"<?= ($selectedBlotter['settlement_status'] ?? '') === 'Settled' ? ' selected' : '' ?>>Settled</option>
                                    <option value="Pending"<?= ($selectedBlotter['settlement_status'] ?? '') === 'Pending' ? ' selected' : '' ?>>Pending</option>
                                    <option value="Unsettled"<?= ($selectedBlotter['settlement_status'] ?? '') === 'Unsettled' ? ' selected' : '' ?>>Unsettled</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" style="color:#000;">Settlement Date</label>
                                <input type="date" name="settlement_date" class="form-control" value="<?= htmlspecialchars($selectedBlotter['settlement_date'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" style="color:#000;">Related Hearing Result</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($selectedBlotter['hearing_result_status'] ?: 'Not recorded yet') ?>" readonly>
                            </div>
                        </div>
                        <div class="mt-3">
                            <label class="form-label" style="color:#000;">Settlement Agreement Summary</label>
                            <textarea name="settlement_agreement" class="form-control" rows="5"><?= htmlspecialchars($selectedBlotter['settlement_agreement'] ?? '') ?></textarea>
                        </div>
                        <div class="mt-3 d-flex gap-2 flex-wrap print-hide">
                            <button type="submit" name="save_settlement" class="btn btn-primary"><i class="bi bi-save me-1"></i>Save Settlement Agreement</button>
                            <button type="button" class="btn btn-outline-primary" onclick="printSettlementPaper()"><i class="bi bi-printer me-1"></i>Print Settlement Paper</button>
                        </div>
                    </form>

                    <div id="print-paper" class="print-settlement-paper mt-4">
                        <div class="kp-form-title">KP FORM NO. 16</div>

                        <div class="text-center kp-form-block">
                            <div>Republic of the Philippines</div>
                            <div>Province of <span class="kp-form-line" style="min-width: 200px;"></span></div>
                            <div>CITY/MUNICIPALITY OF <span class="kp-form-line" style="min-width: 260px;"></span></div>
                            <div>Barangay <span class="kp-form-line" style="min-width: 260px;"></span></div>
                        </div>

                        <div class="text-center kp-form-caption">OFFICE OF THE LUPON TAGAPAMAYAPA</div>

                        <div class="kp-form-block" style="display:flex; gap:12px; align-items:flex-end; justify-content:space-between; flex-wrap:wrap;">
                            <div>For: <span class="kp-form-line" style="min-width: 300px;"></span></div>
                            <div>Barangay Case No. <span class="kp-form-line" style="min-width: 180px;"><?= htmlspecialchars($selectedBlotter['blotter_no'] ?: 'N/A') ?></span></div>
                            <div>Complainant/s <span class="kp-form-line" style="min-width: 180px;"><?= htmlspecialchars($selectedBlotter['complainant_name'] ?: 'N/A') ?></span></div>
                        </div>

                        <div class="kp-form-block">
                            <div>-Against-</div>
                            <div><span class="kp-form-line" style="min-width: 420px;"></span></div>
                        </div>

                        <div class="kp-form-block">
                            <div>Respondent/s <span class="kp-form-line" style="min-width: 390px;"><?= htmlspecialchars($selectedBlotter['respondent_name'] ?: 'N/A') ?></span></div>
                        </div>

                        <div class="kp-form-caption">AMICABLE SETTLEMENT</div>

                        <div class="kp-form-paragraph">
                            We, complainant/s and respondent/s in the above-captioned case, do hereby agree to settle our dispute as follows:
                        </div>

                        <div class="kp-form-block" style="border-bottom:1px solid #111; padding-bottom:6px; min-height:110px;">
                            <?= nl2br(htmlspecialchars($selectedBlotter['settlement_agreement'] ?? '')) ?>
                        </div>

                        <div class="kp-form-paragraph">
                            and bind ourselves to comply honestly and faithfully with the above terms of settlement.
                        </div>

                        <div class="text-center kp-form-block" style="margin-top:24px;">
                            Entered into this <span class="kp-form-line" style="min-width: 140px;"></span> day of <span class="kp-form-line" style="min-width: 150px;"></span><br>
                            <div style="margin-top:10px;">20<span class="kp-form-line" style="min-width: 70px;"></span>, Complainant/s Respondent/s</div>
                        </div>

                        <div class="kp-form-signature">Punong Barangay/Pangkat Chairperson</div>

                        <div class="kp-form-caption" style="margin-top:24px;">ATTESTATION</div>

                        <div class="kp-form-paragraph text-center" style="max-width: 720px; margin: 0 auto;">
                            I hereby certify that the foregoing amicable settlement was entered into by the parties freely and voluntarily, after I had explained to them the nature and consequences of such settlement.
                        </div>

                        <div class="kp-form-signature" style="margin-top:32px; width: 340px;">Punong Barangay/Pangkat Chairperson</div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="row g-4 mb-4">
            <div class="col-lg-6">
                <div class="card" style="background:#fff; color:#000; border:1px solid #e9ecef;">
                    <div class="card-header" style="background:#fff; color:#000; border-bottom:1px solid #e9ecef;">
                        <h5 class="mb-0">Settlement Status Breakdown</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($statusBreakdown)): ?>
                            <p class="text-muted mb-0">No settlement statuses recorded yet.</p>
                        <?php else: ?>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($statusBreakdown as $row): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                        <span><?= htmlspecialchars($row['settlement_status']) ?></span>
                                        <span class="badge bg-dark rounded-pill"><?= (int) $row['total'] ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card" style="background:#fff; color:#000; border:1px solid #e9ecef;">
                    <div class="card-header" style="background:#fff; color:#000; border-bottom:1px solid #e9ecef;">
                        <h5 class="mb-0">Top Blotter Locations</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($locationBreakdown)): ?>
                            <p class="text-muted mb-0">No location aggregation available yet.</p>
                        <?php else: ?>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($locationBreakdown as $row): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                        <span><?= htmlspecialchars($row['location']) ?></span>
                                        <span class="badge bg-dark rounded-pill"><?= (int) $row['total'] ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Connected Blotter Settlement Records Card with Dual View: Table (10 per page) & Carousel (10 per slide) -->
        <div class="card shadow-sm mb-4" style="background:#fff; color:#000; border:1px solid #e9ecef;">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2 py-3" style="background:#f8f9fa; color:#000; border-bottom:1px solid #e9ecef;">
                <div class="d-flex align-items-center gap-2">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-file-earmark-check text-primary me-1"></i> Connected Blotter Settlement Records</h5>
                    <span class="badge bg-primary text-white fw-bold" id="settleTotalBadge"><?= count($settlementRows) ?> records</span>
                </div>
                
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <!-- Search Filter -->
                    <div class="input-group input-group-sm" style="width: 220px;">
                        <span class="input-group-text bg-white text-dark border"><i class="bi bi-search"></i></span>
                        <input type="text" id="settleSearchInput" class="form-control border" placeholder="Search settlement records..." onkeyup="filterSettleRecords()">
                    </div>

                    <!-- Page Size Selector -->
                    <select id="settlePageSizeSelect" class="form-select form-select-sm" style="width: auto;" onchange="changeSettlePageSize(this.value)">
                        <option value="10" selected>10 per page</option>
                        <option value="25">25 per page</option>
                        <option value="50">50 per page</option>
                        <option value="1000">Show All</option>
                    </select>

                    <!-- View Switcher -->
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" class="btn btn-outline-primary active" id="btnSettleTableView" onclick="switchSettleView('table')">
                            <i class="bi bi-table me-1"></i> Table View
                        </button>
                        <button type="button" class="btn btn-outline-primary" id="btnSettleCarouselView" onclick="switchSettleView('carousel')">
                            <i class="bi bi-view-stacked me-1"></i> Carousel View (10/Slide)
                        </button>
                    </div>
                </div>
            </div>

            <div class="card-body p-0">
                <!-- ================= TABLE VIEW ================= -->
                <div id="settleTableView" class="p-3">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="settleTable" style="color:#000;">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Blotter No</th>
                                    <th>Complainant</th>
                                    <th>Respondent</th>
                                    <th>Incident Type</th>
                                    <th>Location</th>
                                    <th>Hearing Result</th>
                                    <th>Settlement Status</th>
                                    <th>Settlement Date</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="settleTableBody">
                                <?php if (empty($settlementRows)): ?>
                                    <tr class="no-settle-records"><td colspan="10" class="text-center py-5 text-muted"><i class="bi bi-inbox fs-2 d-block mb-2 text-secondary"></i> No blotter settlement records available yet.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($settlementRows as $idx => $row): ?>
                                        <tr class="settle-row" 
                                            data-index="<?= $idx ?>"
                                            data-blotter="<?= htmlspecialchars(strtolower($row['blotter_no'])) ?>"
                                            data-complainant="<?= htmlspecialchars(strtolower($row['complainant_name'] ?? '')) ?>"
                                            data-respondent="<?= htmlspecialchars(strtolower($row['respondent_name'] ?? '')) ?>"
                                            data-type="<?= htmlspecialchars(strtolower($row['incident_type'] ?? '')) ?>"
                                            data-location="<?= htmlspecialchars(strtolower($row['location'] ?? '')) ?>"
                                            data-hearing="<?= htmlspecialchars(strtolower($row['hearing_result_status'] ?? '')) ?>"
                                            data-settlement="<?= htmlspecialchars(strtolower($row['settlement_status'] ?? '')) ?>">
                                            <td class="text-muted small fw-bold"><?= $idx + 1 ?></td>
                                            <td class="fw-bold text-primary"><?= htmlspecialchars($row['blotter_no']) ?></td>
                                            <td><?= htmlspecialchars($row['complainant_name'] ?: 'N/A') ?></td>
                                            <td><?= htmlspecialchars($row['respondent_name'] ?: 'N/A') ?></td>
                                            <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($row['incident_type'] ?: 'N/A') ?></span></td>
                                            <td><small class="text-muted"><i class="bi bi-geo-alt me-1 text-danger"></i><?= htmlspecialchars($row['location'] ?: 'N/A') ?></small></td>
                                            <td>
                                                <span class="badge bg-<?= match($row['hearing_result_status'] ?? 'Pending') {
                                                    'Settled', 'Resolved' => 'success',
                                                    'Ongoing', 'In Progress' => 'info',
                                                    'Repudiated', 'Failed' => 'danger',
                                                    default => 'warning text-dark'
                                                } ?>"><?= htmlspecialchars($row['hearing_result_status'] ?: 'Pending') ?></span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?= match($row['settlement_status'] ?? 'Pending') {
                                                    'Settled', 'Signed', 'Completed' => 'success',
                                                    'Ongoing', 'Drafted' => 'info',
                                                    'Repudiated', 'Cancelled' => 'danger',
                                                    default => 'warning text-dark'
                                                } ?>"><?= htmlspecialchars($row['settlement_status'] ?: 'Pending') ?></span>
                                            </td>
                                            <td><?= $row['settlement_date'] ? date('M d, Y', strtotime($row['settlement_date'])) : '<span class="text-muted">N/A</span>' ?></td>
                                            <td>
                                                <div class="d-flex gap-1">
                                                    <a href="settle.php?blotter_id=<?= intval($row['id']) ?>" class="btn btn-sm btn-outline-primary" title="View Settlement"><i class="bi bi-eye me-1"></i>View</a>
                                                    <a href="settle.php?blotter_id=<?= intval($row['id']) ?>" class="btn btn-sm btn-outline-secondary" title="Print Agreement"><i class="bi bi-printer me-1"></i>Print</a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Table Pagination Bar -->
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3 pt-2 border-top">
                        <div class="text-muted small" id="settlePaginationInfo">
                            Showing 1 to 10 of <?= count($settlementRows) ?> entries
                        </div>
                        <nav aria-label="Settlement table pagination">
                            <ul class="pagination pagination-sm mb-0" id="settlePaginationControls">
                                <!-- Populated dynamically via JS -->
                            </ul>
                        </nav>
                    </div>
                </div>

                <!-- ================= CAROUSEL VIEW (10 Items per Slide) ================= -->
                <div id="settleCarouselView" class="p-3" style="display: none;">
                    <?php 
                        $settleBatches = array_chunk($settlementRows, 10);
                        $totalSettleSlides = count($settleBatches);
                    ?>

                    <!-- Carousel Controls Bar -->
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3 p-2 bg-light rounded border">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-primary px-3 py-2 fs-6">
                                <i class="bi bi-view-stacked me-1"></i> <span id="settleCarouselSlideLabel">Slide 1 of <?= max(1, $totalSettleSlides) ?></span>
                            </span>
                            <small class="text-muted" id="settleCarouselRangeLabel">
                                Showing <?= count($settlementRows) > 0 ? '1 - ' . min(10, count($settlementRows)) : '0' ?> of <?= count($settlementRows) ?> records
                            </small>
                        </div>

                        <div class="d-flex align-items-center gap-2">
                            <button class="btn btn-sm btn-primary rounded-circle" type="button" data-bs-target="#settlementCarousel" data-bs-slide="prev" style="width:34px; height:34px;">
                                <i class="bi bi-chevron-left"></i>
                            </button>
                            
                            <div class="d-flex gap-1" id="settleCarouselIndicators">
                                <?php for ($s = 0; $s < $totalSettleSlides; $s++): ?>
                                    <button class="btn btn-sm <?= $s === 0 ? 'btn-primary' : 'btn-outline-secondary' ?> fw-bold py-1 px-2" type="button" data-bs-target="#settlementCarousel" data-bs-slide-to="<?= $s ?>" style="font-size: 0.75rem;">
                                        <?= $s + 1 ?>
                                    </button>
                                <?php endfor; ?>
                            </div>

                            <button class="btn btn-sm btn-primary rounded-circle" type="button" data-bs-target="#settlementCarousel" data-bs-slide="next" style="width:34px; height:34px;">
                                <i class="bi bi-chevron-right"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Bootstrap Carousel -->
                    <div id="settlementCarousel" class="carousel slide" data-bs-interval="false">
                        <div class="carousel-inner">
                            <?php if (empty($settleBatches)): ?>
                                <div class="carousel-item active">
                                    <div class="text-center py-5 text-muted">
                                        <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                        <h5>No Settlement Records</h5>
                                    </div>
                                </div>
                            <?php else: ?>
                                <?php foreach ($settleBatches as $slideIdx => $batch): ?>
                                    <div class="carousel-item <?= $slideIdx === 0 ? 'active' : '' ?>" data-slide-index="<?= $slideIdx ?>">
                                        <div class="row g-3">
                                            <?php foreach ($batch as $cardIdx => $item): ?>
                                                <div class="col-md-6 col-lg-6 settle-carousel-card-col">
                                                    <div class="card h-100 border shadow-sm">
                                                        <div class="card-header bg-light d-flex justify-content-between align-items-center py-2 px-3">
                                                            <strong class="text-primary"><?= htmlspecialchars($item['blotter_no']) ?></strong>
                                                            <div class="d-flex gap-1">
                                                                <span class="badge bg-<?= match($item['settlement_status'] ?? 'Pending') {
                                                                    'Settled', 'Signed', 'Completed' => 'success',
                                                                    'Ongoing', 'Drafted' => 'info',
                                                                    'Repudiated', 'Cancelled' => 'danger',
                                                                    default => 'warning text-dark'
                                                                } ?>"><?= htmlspecialchars($item['settlement_status'] ?: 'Pending') ?></span>
                                                            </div>
                                                        </div>
                                                        <div class="card-body p-3">
                                                            <div class="row g-2 small">
                                                                <div class="col-6">
                                                                    <span class="text-muted d-block text-uppercase" style="font-size:0.7rem; font-weight:700;">Complainant</span>
                                                                    <strong class="text-dark"><?= htmlspecialchars($item['complainant_name'] ?: 'N/A') ?></strong>
                                                                </div>
                                                                <div class="col-6">
                                                                    <span class="text-muted d-block text-uppercase" style="font-size:0.7rem; font-weight:700;">Respondent</span>
                                                                    <strong class="text-dark"><?= htmlspecialchars($item['respondent_name'] ?: 'N/A') ?></strong>
                                                                </div>
                                                                <div class="col-6">
                                                                    <span class="text-muted d-block text-uppercase" style="font-size:0.7rem; font-weight:700;">Incident Type</span>
                                                                    <span class="badge bg-light text-dark border"><?= htmlspecialchars($item['incident_type'] ?: 'N/A') ?></span>
                                                                </div>
                                                                <div class="col-6">
                                                                    <span class="text-muted d-block text-uppercase" style="font-size:0.7rem; font-weight:700;">Location</span>
                                                                    <span><i class="bi bi-geo-alt text-danger me-1"></i><?= htmlspecialchars($item['location'] ?: 'N/A') ?></span>
                                                                </div>
                                                                <div class="col-6">
                                                                    <span class="text-muted d-block text-uppercase" style="font-size:0.7rem; font-weight:700;">Hearing Result</span>
                                                                    <span class="badge bg-<?= match($item['hearing_result_status'] ?? 'Pending') {
                                                                        'Settled', 'Resolved' => 'success',
                                                                        'Ongoing', 'In Progress' => 'info',
                                                                        'Repudiated', 'Failed' => 'danger',
                                                                        default => 'warning text-dark'
                                                                    } ?>"><?= htmlspecialchars($item['hearing_result_status'] ?: 'Pending') ?></span>
                                                                </div>
                                                                <div class="col-6">
                                                                    <span class="text-muted d-block text-uppercase" style="font-size:0.7rem; font-weight:700;">Settlement Date</span>
                                                                    <span><?= $item['settlement_date'] ? date('M d, Y', strtotime($item['settlement_date'])) : 'N/A' ?></span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="card-footer bg-light d-flex justify-content-end gap-2 py-2 px-3">
                                                            <a href="settle.php?blotter_id=<?= intval($item['id']) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye me-1"></i>View</a>
                                                            <a href="settle.php?blotter_id=<?= intval($item['id']) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer me-1"></i>Print</a>
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
function printSettlementPaper() {
    const printSection = document.getElementById('print-paper');
    if (!printSection) {
        return;
    }

    const printWindow = window.open('', '_blank', 'width=1000,height=800');
    if (!printWindow) {
        return;
    }

    const content = printSection.innerHTML;
    const markup = '<!doctype html>' +
        '<html><head><meta charset="UTF-8"><title>Settlement Agreement</title>' +
        '<style>' +
        '@page { size: A4 portrait; margin: 20mm; }' +
        'body { margin: 0; padding: 0; background: #ffffff; font-family: "Times New Roman", Georgia, serif; color: #000; }' +
        '.print-settlement-paper { width: 100%; max-width: 820px; margin: 0 auto; padding: 28px 28px 32px; box-sizing: border-box; border-left: 1px solid #111; border-right: 1px solid #111; min-height: 1122px; }' +
        '.kp-form-title { font-size: 1.15rem; font-weight: 700; margin-bottom: 18px; }' +
        '.kp-form-line { display: inline-block; min-width: 150px; border-bottom: 1px solid #111; padding-bottom: 1px; margin: 0 4px; }' +
        '.kp-form-block { margin-top: 10px; margin-bottom: 10px; }' +
        '.kp-form-caption { text-align: center; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; margin: 18px 0 12px; }' +
        '.kp-form-paragraph { font-size: 1rem; line-height: 1.8; margin: 12px 0; }' +
        '.kp-form-signature { border-top: 1px solid #111; width: 250px; margin: 60px auto 0; text-align: center; padding-top: 6px; }' +
        '</style></head><body>' + content + '</body></html>';

    printWindow.document.open();
    printWindow.document.write(markup);
    printWindow.document.close();

    setTimeout(function () {
        printWindow.focus();
        printWindow.print();
    }, 250);
}

// ================= SETTLEMENT RECORDS VIEW ENGINE =================
let currentSettlePage = 1;
let settleRowsPerPage = 10;
let filteredSettleRows = [];

function initSettleCatalog() {
    const rows = document.querySelectorAll('#settleTableBody tr.settle-row');
    filteredSettleRows = Array.from(rows);
    renderSettlePagination();
    
    const carouselEl = document.getElementById('settlementCarousel');
    if (carouselEl) {
        carouselEl.addEventListener('slide.bs.carousel', function(event) {
            const nextSlideIdx = event.to;
            const totalSlides = document.querySelectorAll('#settlementCarousel .carousel-item').length;
            const totalRecs = parseInt('<?= count($settlementRows) ?>') || 0;
            
            const label = document.getElementById('settleCarouselSlideLabel');
            if (label) label.textContent = `Slide ${nextSlideIdx + 1} of ${totalSlides}`;
            
            const rangeLabel = document.getElementById('settleCarouselRangeLabel');
            if (rangeLabel) {
                const startRec = (nextSlideIdx * 10) + 1;
                const endRec = Math.min((nextSlideIdx + 1) * 10, totalRecs);
                rangeLabel.textContent = `Showing ${startRec} - ${endRec} of ${totalRecs} records`;
            }
            
            const indicators = document.querySelectorAll('#settleCarouselIndicators button');
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

function switchSettleView(viewType) {
    const tableView = document.getElementById('settleTableView');
    const carouselView = document.getElementById('settleCarouselView');
    const btnTable = document.getElementById('btnSettleTableView');
    const btnCarousel = document.getElementById('btnSettleCarouselView');
    
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

function changeSettlePageSize(size) {
    settleRowsPerPage = parseInt(size) || 10;
    currentSettlePage = 1;
    renderSettlePagination();
}

function filterSettleRecords() {
    const query = (document.getElementById('settleSearchInput')?.value || '').toLowerCase().trim();
    const allRows = document.querySelectorAll('#settleTableBody tr.settle-row');
    const allCards = document.querySelectorAll('.settle-carousel-card-col');
    
    filteredSettleRows = [];
    allRows.forEach(row => {
        const text = (
            (row.getAttribute('data-blotter') || '') + ' ' +
            (row.getAttribute('data-complainant') || '') + ' ' +
            (row.getAttribute('data-respondent') || '') + ' ' +
            (row.getAttribute('data-type') || '') + ' ' +
            (row.getAttribute('data-location') || '') + ' ' +
            (row.getAttribute('data-hearing') || '') + ' ' +
            (row.getAttribute('data-settlement') || '')
        ).toLowerCase();
        
        if (!query || text.includes(query)) {
            filteredSettleRows.push(row);
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
    
    currentSettlePage = 1;
    renderSettlePagination();
}

function renderSettlePagination() {
    const total = filteredSettleRows.length;
    const totalPages = Math.ceil(total / settleRowsPerPage) || 1;
    if (currentSettlePage > totalPages) currentSettlePage = totalPages;
    if (currentSettlePage < 1) currentSettlePage = 1;
    
    const startIdx = (currentSettlePage - 1) * settleRowsPerPage;
    const endIdx = Math.min(startIdx + settleRowsPerPage, total);
    
    const allRows = document.querySelectorAll('#settleTableBody tr.settle-row');
    allRows.forEach(r => r.style.display = 'none');
    
    for (let i = startIdx; i < endIdx; i++) {
        if (filteredSettleRows[i]) filteredSettleRows[i].style.display = '';
    }
    
    const infoEl = document.getElementById('settlePaginationInfo');
    if (infoEl) {
        if (total === 0) {
            infoEl.textContent = 'Showing 0 to 0 of 0 entries';
        } else {
            infoEl.textContent = `Showing ${startIdx + 1} to ${endIdx} of ${total} entries`;
        }
    }
    
    const controls = document.getElementById('settlePaginationControls');
    if (!controls) return;
    
    let html = '';
    html += `<li class="page-item ${currentSettlePage === 1 ? 'disabled' : ''}">
        <a class="page-link" href="javascript:void(0)" onclick="goToSettlePage(${currentSettlePage - 1})"><i class="bi bi-chevron-left"></i></a>
    </li>`;
    
    for (let p = 1; p <= totalPages; p++) {
        if (totalPages > 7 && Math.abs(p - currentSettlePage) > 2 && p !== 1 && p !== totalPages) {
            if (p === 2 || p === totalPages - 1) {
                html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
            }
            continue;
        }
        html += `<li class="page-item ${p === currentSettlePage ? 'active' : ''}">
            <a class="page-link" href="javascript:void(0)" onclick="goToSettlePage(${p})">${p}</a>
        </li>`;
    }
    
    html += `<li class="page-item ${currentSettlePage >= totalPages ? 'disabled' : ''}">
        <a class="page-link" href="javascript:void(0)" onclick="goToSettlePage(${currentSettlePage + 1})"><i class="bi bi-chevron-right"></i></a>
    </li>`;
    
    controls.innerHTML = html;
}

function goToSettlePage(page) {
    currentSettlePage = page;
    renderSettlePagination();
}

document.addEventListener('DOMContentLoaded', function() {
    initSettleCatalog();
});
</script>
