<?php
$page_title = 'Law & Incident Report';
$base_url = '';
require_once "includes/header.php";
require_once "includes/navbar.php";
require_once __DIR__ . '/config/db_connect.php';

if (session_status() === PHP_SESSION_NONE) @session_start();
$userId = $_SESSION['user_id'] ?? null;

// Default counts
$myReports = 0;
$activeCases = 0;
$myClearances = 0;
$accountVerified = false;
$adminApprovalState = null; // null = unknown/not configured, 0 = pending, 1 = approved, -1 = rejected

if ($userId) {
    try {
        $stmt = $pdo->prepare("SELECT email_verified, role, fullname FROM signup WHERE user_id = ?");
        $stmt->execute([$userId]);
        $u = $stmt->fetch(PDO::FETCH_ASSOC);
        $accountVerified = !empty($u['email_verified']);
        $_SESSION['fullname'] = $u['fullname'] ?? $_SESSION['fullname'] ?? '';

        // Admin users do not require admin approval - treat them as verified/unlocked
        $userRole = strtolower($u['role'] ?? $_SESSION['role'] ?? '');
        if ($userRole === 'admin') {
            $accountVerified = true;
            $adminApprovalState = 1;
        }

        // Try to read an optional admin approval column (admin_approved).
        // If the column does not exist, we'll treat admin approval as not configured and fall back to email_verified.
        try {
            $stmt2 = $pdo->prepare("SELECT admin_approved FROM signup WHERE user_id = ?");
            $stmt2->execute([$userId]);
            $a = $stmt2->fetch(PDO::FETCH_ASSOC);
            if ($a && array_key_exists('admin_approved', $a)) {
                // Normalize values: 1 = approved, 0 = pending, -1 = rejected
                $adminApprovalState = (int)$a['admin_approved'];
                // If admin_approved exists use it as the source of truth for unlocking features
                $accountVerified = ($adminApprovalState === 1);
            }
        } catch (Throwable $inner) {
            // ignore - column may not exist
            $adminApprovalState = null;
        }
    } catch (Exception $e) {
        $accountVerified = false;
    }

    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM incidents WHERE created_by = ?");
        $stmt->execute([$userId]);
        $myReports = (int)($stmt->fetchColumn() ?? 0);
    } catch (Throwable $e) { $myReports = 0; }

    try {
        // Active cases: count case assignments assigned to or created by user and not closed
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM case_assignments WHERE (assigned_to = ? OR assigned_by = ?) AND status NOT IN ('Closed','Resolved','Archived')");
        $stmt->execute([$userId, $userId]);
        $activeCases = (int)($stmt->fetchColumn() ?? 0);
    } catch (Throwable $e) { $activeCases = 0; }

    try {
        // My clearances - try table 'clearances' if exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM clearances WHERE user_id = ?");
        $stmt->execute([$userId]);
        $myClearances = (int)($stmt->fetchColumn() ?? 0);
    } catch (Throwable $e) { $myClearances = 0; }
}
?>

<!-- Floating AI Assistant (landing page only) -->
<div id="ai-assistant" aria-hidden="false">
    <div class="ai-inner">
        <span class="ai-icon">💬</span>
        <span class="ai-label">AI Assistant</span>
        <select id="ai-lang" class="ai-lang" aria-label="Assistant language">
            <option value="us">US</option>
            <option value="ph">PH</option>
        </select>
        <button id="ai-open" class="ai-open" title="Open assistant">+</button>
    </div>
</div>

<style>
    #ai-assistant{position:fixed;right:24px;top:24px;z-index:1200}
    #ai-assistant .ai-inner{display:flex;align-items:center;gap:8px;background:linear-gradient(90deg,#6c5ce7,#a29bfe);color:#fff;padding:8px 12px;border-radius:18px;box-shadow:0 8px 30px rgba(0,0,0,0.15);font-weight:600;font-size:13px}
    #ai-assistant .ai-icon{display:inline-block;width:24px;height:24px;line-height:24px;text-align:center;background:rgba(255,255,255,0.08);border-radius:6px}
    #ai-assistant .ai-label{white-space:nowrap}
    #ai-assistant .ai-lang{background:rgba(255,255,255,0.12);border:none;color:#fff;padding:2px 6px;border-radius:6px;font-weight:600}
    #ai-assistant .ai-open{background:rgba(255,255,255,0.12);border:none;color:#fff;width:28px;height:28px;border-radius:6px;font-size:18px;line-height:1}
    #ai-assistant .ai-lang:focus,#ai-assistant .ai-open:focus{outline:2px solid rgba(255,255,255,0.2)}
</style>

<script>
    // Simple handler for assistant button on landing page
    document.addEventListener('DOMContentLoaded', function(){
        var btn = document.getElementById('ai-open');
        var lang = document.getElementById('ai-lang');
        btn.addEventListener('click', function(e){
            e.preventDefault();
            // Open a small help modal or redirect to assistant page (placeholder)
            alert('AI Assistant: language ' + (lang.value || 'us'));
        });
    });
</script>

<div class="main-content">
    <div class="content-container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1>My Dashboard</h1>
                <p class="text-secondary">Welcome back, <?php echo htmlspecialchars($_SESSION['fullname'] ?? ''); ?></p>
            </div>
            <div>
                <?php if (!$accountVerified): ?>
                    <button class="btn btn-secondary" disabled>File Report (Pending Verification)</button>
                <?php else: ?>
                    <a href="modules/incident_report.php" class="btn btn-primary">File Report</a>
                <?php endif; ?>
            </div>
        </div>

        <?php if (isset($adminApprovalState) && $adminApprovalState === -1): ?>
            <div class="alert alert-danger mb-4">
                <strong>Account Not Approved</strong>
                <p>YOU ARE NOT ACCEPTABLE TO USE THIS SITE. ONLY FOR CITIZEN OF QUEZON CITY.</p>
            </div>
        <?php elseif (!$accountVerified): ?>
            <div class="alert alert-warning mb-4">
                <strong>Account Verification Pending</strong>
                <p>Your account is currently under review by the admin. Filing reports and other features (blotter, my reports) will remain locked until an admin approves your account.</p>
            </div>
        <?php endif; ?>

        <div class="card mb-4" style="background:#2f6f4f;color:#fff;padding:1rem;border-radius:8px;">
            <h4 style="margin:0">Welcome back, <?php echo htmlspecialchars($_SESSION['fullname'] ?? ''); ?>!</h4>
            <p style="opacity:0.9;margin:0.3rem 0 0;">Track your reports, clearances, and stay updated on your barangay services.</p>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-12 col-md-4">
                <div class="card p-3" style="position:relative;">
                    <h6>My Reports <?php if (!$accountVerified) echo '<i class="bi bi-lock-fill text-muted" title="Locked until admin approval"></i>'; ?></h6>
                    <div style="font-size:2rem;font-weight:700"><?php echo $myReports; ?></div>
                    <div class="text-muted">Total filed</div>
                    <?php if (!$accountVerified): ?>
                        <div style="position:absolute;inset:0;background:rgba(255,255,255,0.6);display:flex;align-items:center;justify-content:center;border-radius:6px;">
                            <div class="text-center text-muted">
                                <i class="bi bi-lock-fill" style="font-size:24px"></i>
                                <div style="font-size:0.9rem">Locked until admin approval</div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="card p-3">
                    <h6>Active Cases</h6>
                    <div style="font-size:2rem;font-weight:700"><?php echo $activeCases; ?></div>
                    <div class="text-muted">In progress</div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="card p-3">
                    <h6>My Clearances</h6>
                    <div style="font-size:2rem;font-weight:700"><?php echo $myClearances; ?></div>
                    <div class="text-muted">Requested</div>
                </div>
            </div>
        </div>

        <div class="card">
                <div class="card-body">
                <h5 class="card-title">My Recent Reports <?php if (!$accountVerified) echo '<i class="bi bi-lock-fill text-muted" title="Locked until admin approval"></i>'; ?></h5>
                <p class="text-muted">Your recent incident reports</p>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr><th>Case No</th><th>Type</th><th>Date</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                            <?php
                            // If account not verified by admin, hide report list and show locked message
                            if (!$accountVerified) {
                                echo '<tr><td colspan="4" class="text-center text-danger">Your reports are locked until an admin verifies your account.</td></tr>';
                            } else {
                                try {
                                    if ($userId) {
                                        $stmt = $pdo->prepare("SELECT case_no, incident_type, incident_date, status FROM incidents WHERE created_by = ? ORDER BY id DESC LIMIT 5");
                                        $stmt->execute([$userId]);
                                        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                    } else {
                                        $rows = [];
                                    }
                                } catch (Exception $e) { $rows = []; }

                                if (empty($rows)) {
                                    echo '<tr><td colspan="4" class="text-center text-secondary">Your recent incident reports</td></tr>';
                                } else {
                                    foreach ($rows as $r) {
                                        echo '<tr>';
                                        echo '<td>'.htmlspecialchars($r['case_no'] ?? '—').'</td>';
                                        echo '<td>'.htmlspecialchars($r['incident_type'] ?? '—').'</td>';
                                        echo '<td>'.htmlspecialchars($r['incident_date'] ?? '—').'</td>';
                                        echo '<td>'.htmlspecialchars($r['status'] ?? '—').'</td>';
                                        echo '</tr>';
                                    }
                                }
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>
