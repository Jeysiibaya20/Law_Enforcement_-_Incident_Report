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
            <a href="blotters.php" class="btn btn-dark">Back to Blotters</a>
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

        <div class="row row-cols-1 row-cols-md-4 g-3 mb-4">
            <div class="col">
                <div class="card border-start border-primary border-4 h-100">
                    <div class="card-body">
                        <h6 class="text-muted mb-2" style="color:#000;">Total Blotters</h6>
                        <div class="h3 text-primary"><?= $totalBlotters ?></div>
                        <small class="text-muted">All connected records</small>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card border-start border-success border-4 h-100">
                    <div class="card-body">
                        <h6 class="text-muted mb-2" style="color:#000;">Resolved / Settled</h6>
                        <div class="h3 text-success"><?= $resolvedBlotters ?></div>
                        <small class="text-muted">Records with completed outcomes</small>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card border-start border-warning border-4 h-100">
                    <div class="card-body">
                        <h6 class="text-muted mb-2" style="color:#000;">Awaiting Settlement</h6>
                        <div class="h3 text-warning"><?= $pendingSettlement ?></div>
                        <small class="text-muted">Need hearing result or agreement</small>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card border-start border-info border-4 h-100">
                    <div class="card-body">
                        <h6 class="text-muted mb-2" style="color:#000;">Settlement Agreements</h6>
                        <div class="h3 text-info"><?= $agreementCount ?></div>
                        <small class="text-muted">Saved agreement summaries</small>
                    </div>
                </div>
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
                            <button type="submit" name="save_settlement" class="btn btn-dark">Save Settlement Agreement</button>
                            <button type="button" class="btn btn-outline-dark" onclick="printSettlementPaper()">Print Settlement Paper</button>
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

        <div class="card" style="background:#fff; color:#000; border:1px solid #e9ecef;">
            <div class="card-header" style="background:#fff; color:#000; border-bottom:1px solid #e9ecef;">
                <h5 class="mb-0">Connected Blotter Settlement Records</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" style="color:#000;">
                        <thead style="background:#f8f9fa; color:#000;">
                            <tr>
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
                        <tbody>
                            <?php if (empty($settlementRows)): ?>
                                <tr><td colspan="9" class="text-center" style="color:#000;">No blotter records available yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($settlementRows as $row): ?>
                                    <tr>
                                        <td class="fw-bold"><?= htmlspecialchars($row['blotter_no']) ?></td>
                                        <td><?= htmlspecialchars($row['complainant_name'] ?: 'N/A') ?></td>
                                        <td><?= htmlspecialchars($row['respondent_name'] ?: 'N/A') ?></td>
                                        <td><?= htmlspecialchars($row['incident_type'] ?: 'N/A') ?></td>
                                        <td><?= htmlspecialchars($row['location'] ?: 'N/A') ?></td>
                                        <td><?= htmlspecialchars($row['hearing_result_status'] ?: 'Pending') ?></td>
                                        <td><?= htmlspecialchars($row['settlement_status'] ?: 'Pending') ?></td>
                                        <td><?= $row['settlement_date'] ? date('M d, Y', strtotime($row['settlement_date'])) : 'N/A' ?></td>
                                        <td>
                                            <div class="d-flex gap-2">
                                                <a href="settle.php?blotter_id=<?= intval($row['id']) ?>" class="btn btn-sm btn-outline-dark">View</a>
                                                <a href="settle.php?blotter_id=<?= intval($row['id']) ?>" class="btn btn-sm btn-outline-secondary">Print</a>
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
</script>
