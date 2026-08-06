<?php
require_once 'admin_auth.php';
$page_title = 'Account Approvals';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/../config/db_connect.php';

if (session_status() === PHP_SESSION_NONE) @session_start();
// Only admins
if (empty($_SESSION['user_id']) || strtolower($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo "<div class='container mt-4'><div class='alert alert-danger'>Access denied.</div></div>";
    require_once __DIR__ . '/../includes/footer.php';
    exit();
}

// Ensure admin_approved column exists (best-effort)
try {
    $check = $pdo->query("SHOW COLUMNS FROM signup LIKE 'admin_approved'");
    $row = $check->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        $pdo->exec("ALTER TABLE signup ADD COLUMN admin_approved TINYINT(1) DEFAULT 0");
    }
} catch (Throwable $e) {
    // ignore if cannot alter
}

// Handle approve/reject POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['user_id'])) {
    $targetId = (int)$_POST['user_id'];
    if ($_POST['action'] === 'approve') {
        $stmt = $pdo->prepare("UPDATE signup SET admin_approved = 1 WHERE user_id = ?");
        $stmt->execute([$targetId]);
    } elseif ($_POST['action'] === 'reject') {
        $stmt = $pdo->prepare("UPDATE signup SET admin_approved = -1 WHERE user_id = ?");
        $stmt->execute([$targetId]);
    }
    // simple redirect to avoid resubmission
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

// Fetch accounts to review - show those not approved yet and also previously rejected
$stmt = $pdo->prepare("SELECT user_id, fullname, emailadd, username, resident_qc, uploaded_front, uploaded_back, created_at, email_verified, admin_approved FROM signup WHERE (admin_approved IS NULL OR admin_approved = 0 OR admin_approved = -1) ORDER BY created_at DESC");
$stmt->execute();
$accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="main-content">
    <div class="content-container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1>Account Approvals</h1>
                <p class="text-secondary">Approve or reject newly created accounts. Click a thumbnail to view uploaded ID images.</p>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Pending Accounts</h5>
                <div class="table-responsive">
                    <table class="table table-sm table-striped">
                        <thead>
                            <tr>
                                <th>User ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Username</th>
                                <th>ID Front</th>
                                <th>ID Back</th>
                                <th>QC Resident</th>
                                <th>State</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($accounts)): ?>
                            <tr><td colspan="9" class="text-center text-secondary">No accounts awaiting admin approval.</td></tr>
                        <?php else: foreach ($accounts as $a): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($a['user_id']); ?></td>
                                <td><?php echo htmlspecialchars($a['fullname'] ?? $a['username']); ?></td>
                                <td><?php echo htmlspecialchars($a['emailadd']); ?></td>
                                <td><?php echo htmlspecialchars($a['username']); ?></td>
                                <td>
                                    <?php if (!empty($a['uploaded_front'])): ?>
                                        <a href="<?php echo htmlspecialchars($a['uploaded_front']); ?>" target="_blank"><img src="<?php echo htmlspecialchars($a['uploaded_front']); ?>" style="height:50px;border:1px solid #ddd;padding:2px;border-radius:4px" /></a>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($a['uploaded_back'])): ?>
                                        <a href="<?php echo htmlspecialchars($a['uploaded_back']); ?>" target="_blank"><img src="<?php echo htmlspecialchars($a['uploaded_back']); ?>" style="height:50px;border:1px solid #ddd;padding:2px;border-radius:4px" /></a>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo (!empty($a['resident_qc']) ? 'Yes' : 'No'); ?></td>
                                <td>
                                    <?php
                                        $state = $a['admin_approved'] === null ? 0 : (int)$a['admin_approved'];
                                        if ($state === 1) echo '<span class="badge bg-success">Approved</span>';
                                        elseif ($state === -1) echo '<span class="badge bg-danger">Rejected</span>';
                                        else echo '<span class="badge bg-warning">Pending</span>';
                                    ?>
                                </td>
                                <td style="white-space:nowrap">
                                    <form method="post" style="display:inline">
                                        <input type="hidden" name="user_id" value="<?php echo (int)$a['user_id']; ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <button class="btn btn-sm btn-success" type="submit">Approve</button>
                                    </form>
                                    <form method="post" style="display:inline;margin-left:6px">
                                        <input type="hidden" name="user_id" value="<?php echo (int)$a['user_id']; ?>">
                                        <input type="hidden" name="action" value="reject">
                                        <button class="btn btn-sm btn-danger" type="submit" onclick="return confirm('Reject this account?')">Reject</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
