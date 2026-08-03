<?php
require_once 'admin_auth.php';

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
                        <button type="button" class="btn btn-outline-secondary" onclick="window.print()">Print</button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($selected): ?>
            <div class="card" id="certificate">
                <div class="card-body">
                    <div class="text-center mb-4">
                        <h3>Certificate of File Action</h3>
                        <small class="text-muted">Blotter record maintained by the Barangay</small>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Blotter No:</strong> <?= htmlspecialchars($selected['blotter_no']) ?></p>
                            <p><strong>Complainant:</strong> <?= htmlspecialchars($selected['complainant_name']) ?></p>
                            <p><strong>Respondent:</strong> <?= htmlspecialchars($selected['respondent_name'] ?? 'N/A') ?></p>
                            <p><strong>Incident Type:</strong> <?= htmlspecialchars($selected['incident_type'] ?? 'N/A') ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Date / Time:</strong> <?= htmlspecialchars(($selected['incident_date'] ?? '') . ' ' . ($selected['incident_time'] ?? '')) ?></p>
                            <p><strong>Location:</strong> <?= htmlspecialchars($selected['location'] ?? 'N/A') ?></p>
                            <p><strong>Priority:</strong> <?= htmlspecialchars($selected['priority'] ?? 'N/A') ?></p>
                            <p><strong>Status:</strong> <?= htmlspecialchars($selected['status'] ?? 'N/A') ?></p>
                        </div>
                    </div>

                    <div class="mt-3">
                        <h6>Brief Narrative</h6>
                        <p><?= nl2br(htmlspecialchars($selected['description'] ?? '')) ?></p>
                    </div>

                    <div class="row mt-4">
                        <div class="col-md-6 text-center">
                            <p><strong>Filed By:</strong></p>
                            <p><?= htmlspecialchars($selected['created_by_name'] ?? '') ?></p>
                        </div>
                        <div class="col-md-6 text-center">
                            <p><strong>Assigned Officer:</strong></p>
                            <p><?= htmlspecialchars($selected['officer_name'] ?? 'Unassigned') ?></p>
                        </div>
                    </div>

                    <div class="mt-4 text-muted small">
                        <p>This certificate certifies that the blotter information shown above is on file with the barangay blotter records. This is a non-certified copy for administrative purposes.</p>
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
