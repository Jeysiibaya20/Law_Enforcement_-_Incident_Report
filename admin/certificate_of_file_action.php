<?php
require_once 'admin_auth.php';
if (!isset($pdo) || !($pdo instanceof PDO)) {
    $pdo = getDBConnection();
}

$base_url = '../';
$page_title = 'Certificate of File Action';
require_once '../includes/header.php';
require_once '../includes/navbar.php';

// Fetch blotters for selection
try {
    $blotters = $pdo->query("SELECT id, blotter_no, complainant_name, incident_type, incident_date FROM blotters ORDER BY created_at DESC LIMIT 200")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $blotters = [];
}

$selected = null;
if (!empty($_GET['blotter_id'])) {
    $id = intval($_GET['blotter_id']);
    try {
        $stmt = $pdo->prepare("SELECT b.*, s.fullname AS created_by_name, o.fullname AS officer_name FROM blotters b LEFT JOIN signup s ON b.created_by = s.user_id LEFT JOIN signup o ON b.officer_id = o.user_id WHERE b.id = ? LIMIT 1");
        $stmt->execute([$id]);
        $selected = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $selected = null;
    }
}
?>

<div class="main-content">
    <div class="content-container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h2">Certificate of File Action</h1>
            <a href="blotters.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Back</a>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-8">
                        <label class="form-label">Select Blotter</label>
                        <select name="blotter_id" class="form-select">
                            <option value="">-- Choose blotter --</option>
                            <?php foreach ($blotters as $b): ?>
                                <option value="<?= (int)$b['id'] ?>" <?= (!empty($_GET['blotter_id']) && intval($_GET['blotter_id'])===$b['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($b['blotter_no']) ?> — <?= htmlspecialchars($b['complainant_name']) ?> (<?= htmlspecialchars(date('M d, Y', strtotime($b['incident_date'] ?? 'now'))) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-primary">Load Certificate</button>
                        <?php if ($selected): ?>
                        <button type="button" class="btn btn-outline-secondary" onclick="printCertificatePaper()">Print</button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($selected): ?>
            <div class="card" id="certificate">
                <div class="card-body">
                    <div id="certificate-form" class="certificate-print-form" style="font-family: 'Times New Roman', Georgia, serif; color:#000; max-width:820px; margin:0 auto; background:#fff; padding:26px 28px; border:1px solid #111; border-radius:8px;">
                        <div style="font-size:1.15rem; font-weight:700; margin-bottom:16px;">KP FORM NO. 20</div>

                        <div style="text-align:center; margin-bottom:18px;">
                            <div style="font-size:1.05rem; font-weight:600;">Republic of the Philippines</div>
                            <div style="font-size:1.05rem; font-weight:600;">Province of <span style="display:inline-block; min-width:180px; border-bottom:1px solid #111; margin:0 4px;"></span></div>
                            <div style="font-size:1.05rem; font-weight:600;">CITY / MUNICIPALITY OF <span style="display:inline-block; min-width:250px; border-bottom:1px solid #111; margin:0 4px;"></span></div>
                            <div style="font-size:1.05rem; font-weight:600;">Barangay <span style="display:inline-block; min-width:250px; border-bottom:1px solid #111; margin:0 4px;"></span></div>
                        </div>

                        <div style="text-align:center; font-size:1.2rem; font-weight:700; text-transform:uppercase; margin:24px 0 16px;">OFFICE OF THE LUPON TAGAPAMAYAPA</div>

                        <div style="display:flex; justify-content:space-between; align-items:flex-end; gap:12px; flex-wrap:wrap;">
                            <div style="display:flex; align-items:flex-end; gap:8px;">
                                <span style="display:inline-block; min-width:260px; border-bottom:1px solid #111;"></span>
                            </div>
                            <div style="display:flex; align-items:flex-end; gap:8px;">
                                <span style="font-weight:600;">Barangay Case No.</span>
                                <span style="display:inline-block; min-width:160px; border-bottom:1px solid #111;"><?= htmlspecialchars($selected['blotter_no'] ?? 'N/A') ?></span>
                            </div>
                        </div>

                        <div style="margin-top:6px; display:flex; align-items:flex-end; gap:8px; flex-wrap:wrap;">
                            <span style="font-weight:600;">For:</span>
                            <span style="display:inline-block; min-width:240px; border-bottom:1px solid #111;"></span>
                            <span style="font-weight:600;">Complainant/s</span>
                            <span style="display:inline-block; min-width:160px; border-bottom:1px solid #111;"><?= htmlspecialchars($selected['complainant_name'] ?? 'N/A') ?></span>
                        </div>

                        <div style="margin-top:8px;">
                            <div style="font-size:1.05rem; font-weight:600; margin-bottom:4px;">-Against-</div>
                            <div style="display:inline-block; min-width:420px; border-bottom:1px solid #111;"></div>
                        </div>

                        <div style="margin-top:10px;">
                            <span style="display:inline-block; min-width:420px; border-bottom:1px solid #111;"></span>
                        </div>

                        <div style="margin-top:10px;">
                            <span style="font-weight:600;">Respondent/s</span>
                            <span style="display:inline-block; min-width:330px; border-bottom:1px solid #111;"><?= htmlspecialchars($selected['respondent_name'] ?? 'N/A') ?></span>
                        </div>

                        <div style="text-align:center; font-size:1.3rem; font-weight:700; text-transform:uppercase; margin:28px 0 16px;">CERTIFICATE TO FILE ACTION</div>

                        <div style="font-size:1rem; line-height:1.8; margin:8px 0 14px;">This is to certify that:</div>

                        <div style="font-size:1rem; line-height:1.8; margin:8px 0 14px;">
                            <div>1) There has been a personal confrontation between the parties before the <span style="display:inline-block; min-width:320px; border-bottom:1px solid #111;"></span>;</div>
                            <div>2) A settlement was reached;</div>
                            <div>3) The settlement has been repudiated in a statement sworn to before the <span style="display:inline-block; min-width:220px; border-bottom:1px solid #111;"></span> on the ground of <span style="display:inline-block; min-width:150px; border-bottom:1px solid #111;"></span> and;</div>
                            <div>4) Therefore, the corresponding complaint for the dispute may now be filed in court/government office.</div>
                        </div>

                        <div style="text-align:center; margin-top:24px;">
                            <div style="display:inline-block; min-width:180px; border-bottom:1px solid #111;"></div>
                            <div style="display:inline-block; min-width:120px; border-bottom:1px solid #111;"></div>
                            <div style="font-size:0.95rem; margin-top:6px;">This <span style="display:inline-block; min-width:110px; border-bottom:1px solid #111;"></span> day of <span style="display:inline-block; min-width:110px; border-bottom:1px solid #111;"></span> 20<span style="display:inline-block; min-width:40px; border-bottom:1px solid #111;"></span></div>
                        </div>

                        <div style="text-align:center; margin-top:22px;">
                            <div style="display:inline-block; min-width:240px; border-bottom:1px solid #111;"></div>
                            <div style="margin-top:6px; font-weight:600;">Lupong Secretary</div>
                        </div>

                        <div style="margin-top:26px; font-weight:600;">Attested:</div>
                        <div style="text-align:center; margin-top:10px;">
                            <div style="display:inline-block; min-width:240px; border-bottom:1px solid #111;"></div>
                            <div style="margin-top:6px; font-weight:600;">Lupong Chairperson</div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

    </div>
</div>

<?php require_once '../includes/footer.php'; ?>

<style>
@media print {
    body * { visibility: hidden; }
    #certificate, #certificate * { visibility: visible; }
    #certificate { position: absolute; left: 0; top: 0; width: 100%; }
}
</style>

<script>
function printCertificatePaper() {
    const certificateForm = document.getElementById('certificate-form');
    if (!certificateForm) {
        return;
    }

    const printWindow = window.open('', '_blank', 'width=1000,height=800');
    if (!printWindow) {
        return;
    }

    const content = certificateForm.innerHTML;
    const markup = '<!doctype html>' +
        '<html><head><meta charset="UTF-8"><title>Certificate of File Action</title>' +
        '<style>' +
        '@page { size: A4 portrait; margin: 20mm; }' +
        'body { margin: 0; padding: 0; background: #fff; font-family: "Times New Roman", Georgia, serif; color: #000; }' +
        '.certificate-print-form { width: 100%; max-width: 820px; margin: 0 auto; padding: 30px 28px; box-sizing: border-box; min-height: 1122px; }' +
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
