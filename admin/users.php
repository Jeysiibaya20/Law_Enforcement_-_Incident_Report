<?php
require_once 'admin_auth.php';

$base_url = '../';
$page_title = 'User Management';
require_once '../includes/header.php';

// Handle user status update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    try {
        $action = $_POST['action'];
        $user_id = intval($_POST['user_id']);
        
        if ($action === 'toggle_verify') {
            $new_status = $_POST['new_status'];
            $pdo->prepare("UPDATE signup SET email_verified = ? WHERE user_id = ?")->execute([$new_status, $user_id]);
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'User verification status updated.'];
        } elseif ($action === 'delete') {
            $pdo->prepare("DELETE FROM signup WHERE user_id = ?")->execute([$user_id]);
            $_SESSION['flash'] = ['type' => 'warning', 'message' => 'User account deleted.'];
        }
        header('Location: users.php');
        exit();
    } catch (Exception $e) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Error: ' . $e->getMessage()];
    }
}

// Fetch all users
try {
    $users = $pdo->query("SELECT * FROM signup WHERE role != 'Admin' ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $users = [];
}
?>

<div class="main-content">
    <div class="content-container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h2">User Management</h1>
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Back
            </a>
        </div>

        <?php if (!empty($_SESSION['flash'])): $f = $_SESSION['flash']; ?>
            <div class="alert alert-<?= htmlspecialchars($f['type']) ?> alert-dismissible">
                <?= htmlspecialchars($f['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['flash']); endif; ?>

        <div class="card">
            <div class="card-header">
                <h5>All Users (<?= count($users) ?>)</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Full Name</th>
                                <th>Email</th>
                                <th>Username</th>
                                <th>Verified</th>
                                <th>Role</th>
                                <th>Signed Up</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $u): ?>
                            <tr>
                                <td><?= $u['user_id'] ?></td>
                                <td><?= htmlspecialchars($u['fullname']) ?></td>
                                <td><?= htmlspecialchars($u['emailadd']) ?></td>
                                <td><?= htmlspecialchars($u['username']) ?></td>
                                <td>
                                    <?php if ($u['email_verified']): ?>
                                        <span class="badge bg-success"><i class="bi bi-check"></i> Verified</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning"><i class="bi bi-x"></i> Unverified</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge bg-info"><?= htmlspecialchars($u['role'] ?? 'User') ?></span></td>
                                <td><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <?php if (!$u['email_verified']): ?>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="toggle_verify">
                                            <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                                            <input type="hidden" name="new_status" value="1">
                                            <button type="submit" class="btn btn-success btn-sm" title="Verify Email">
                                                <i class="bi bi-check-circle"></i> Verify
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete this user?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">
                                                <i class="bi bi-trash"></i> Delete
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
