<?php
require_once 'admin_auth.php';

$base_url = '../';
$page_title = 'User Management';
// header is included after processing POST and preparing data to avoid "headers already sent" errors


// Ensure admin_approved column exists (best-effort)
try {
    $check = $pdo->query("SHOW COLUMNS FROM signup LIKE 'admin_approved'");
    $row = $check->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        $pdo->exec("ALTER TABLE signup ADD COLUMN admin_approved TINYINT(1) DEFAULT 0");
    }
} catch (Throwable $e) {
    // ignore
}

// Ensure banned column exists (best-effort)
try {
    $check = $pdo->query("SHOW COLUMNS FROM signup LIKE 'banned'");
    $row = $check->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        $pdo->exec("ALTER TABLE signup ADD COLUMN banned TINYINT(1) DEFAULT 0");
    }
} catch (Throwable $e) {
    // ignore
}

// Handle user status update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    try {
        $action = $_POST['action'];
        $user_id = intval($_POST['user_id']);
        require_once __DIR__ . '/../includes/audit_logger.php';
        
        if ($action === 'toggle_verify') {
            $new_status = intval($_POST['new_status']);
            $pdo->prepare("UPDATE signup SET email_verified = ? WHERE user_id = ?")->execute([$new_status, $user_id]);
            logAuditTrail('USER_UPDATE', 'User Management', (string)$user_id, "Toggled email verification to {$new_status} for User ID #{$user_id}.", 'SUCCESS', $pdo);
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'User verification status updated.'];
        } elseif ($action === 'approve_account') {
            $pdo->prepare("UPDATE signup SET admin_approved = 1 WHERE user_id = ?")->execute([$user_id]);
            logAuditTrail('USER_APPROVE', 'User Management', (string)$user_id, "Approved resident account ID #{$user_id}.", 'SUCCESS', $pdo);
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Account approved.'];
        } elseif ($action === 'reject_account') {
            $pdo->prepare("UPDATE signup SET admin_approved = -1 WHERE user_id = ?")->execute([$user_id]);
            logAuditTrail('USER_REJECT', 'User Management', (string)$user_id, "Rejected resident account ID #{$user_id}.", 'SUCCESS', $pdo);
            $_SESSION['flash'] = ['type' => 'warning', 'message' => 'Account rejected.'];
        } elseif ($action === 'unreject_account') {
            $pdo->prepare("UPDATE signup SET admin_approved = 0 WHERE user_id = ?")->execute([$user_id]);
            logAuditTrail('USER_UPDATE', 'User Management', (string)$user_id, "Reset approval status for User ID #{$user_id}.", 'SUCCESS', $pdo);
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Account rejection has been undone.'];
        } elseif ($action === 'ban') {
            $pdo->prepare("UPDATE signup SET banned = 1 WHERE user_id = ?")->execute([$user_id]);
            logAuditTrail('USER_BAN', 'User Management', (string)$user_id, "Banned User ID #{$user_id}.", 'SUCCESS', $pdo);
            $_SESSION['flash'] = ['type' => 'warning', 'message' => 'User has been banned.'];
        } elseif ($action === 'unban') {
            $pdo->prepare("UPDATE signup SET banned = 0 WHERE user_id = ?")->execute([$user_id]);
            logAuditTrail('USER_UNBAN', 'User Management', (string)$user_id, "Unbanned User ID #{$user_id}.", 'SUCCESS', $pdo);
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'User has been unbanned.'];
        } elseif ($action === 'delete') {
            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM incidents WHERE created_by = ? OR assigned_to = ? OR updated_by = ?");
            $countStmt->execute([$user_id, $user_id, $user_id]);
            $referenceCount = (int)$countStmt->fetchColumn();

            $attachmentStmt = $pdo->prepare("SELECT COUNT(*) FROM attachments WHERE uploaded_by = ?");
            $attachmentStmt->execute([$user_id]);
            $attachmentCount = (int)$attachmentStmt->fetchColumn();

            if ($referenceCount > 0) {
                $_SESSION['flash'] = [
                    'type' => 'danger',
                    'message' => 'Cannot delete this user because they are referenced by one or more incident reports. Reassign or remove the related incident records first.'
                ];
            } else {
                if ($attachmentCount > 0) {
                    // Remove attachments uploaded by this user before deleting the account.
                    $deleteAttachments = $pdo->prepare("DELETE FROM attachments WHERE uploaded_by = ?");
                    $deleteAttachments->execute([$user_id]);
                }

                $pdo->prepare("DELETE FROM signup WHERE user_id = ?")->execute([$user_id]);
                logAuditTrail('USER_DELETE', 'User Management', (string)$user_id, "Deleted User ID #{$user_id}.", 'SUCCESS', $pdo);
                $_SESSION['flash'] = ['type' => 'warning', 'message' => 'User account deleted.'];
            }
        }
        header('Location: users.php');
        exit();
    } catch (Exception $e) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Error: ' . $e->getMessage()];
    }
}

// Fetch all users (include admin accounts so admins can be managed/approved)
try {
    $users = $pdo->query("SELECT * FROM signup ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $users = [];
}

// Now include header (outputs HTML) after we've processed any redirects
require_once '../includes/header.php';
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

        <?php
        $userTotalCount = count($users);
        $approvedUsers = count(array_filter($users, fn($u) => !empty($u['admin_approved']) && (int)$u['admin_approved'] === 1));
        $pendingUsers = count(array_filter($users, fn($u) => empty($u['admin_approved']) || (int)$u['admin_approved'] === 0));
        $verifiedEmailUsers = count(array_filter($users, fn($u) => !empty($u['email_verified'])));
        ?>
        <!-- KPI Strip -->
        <div class="row g-3 mb-4">
            <div class="col-12 col-sm-6 col-xl-3">
                <article class="dashboard-analytics-card analytics-tone-notif h-100">
                    <div class="dashboard-analytics-head">
                        <span class="dashboard-analytics-label">Total Users</span>
                        <span class="dashboard-analytics-icon"><i class="fas fa-users"></i></span>
                    </div>
                    <div class="dashboard-analytics-value"><?= $userTotalCount ?></div>
                    <div class="dashboard-analytics-sub">Registered accounts</div>
                </article>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <article class="dashboard-analytics-card analytics-tone-subs h-100">
                    <div class="dashboard-analytics-head">
                        <span class="dashboard-analytics-label">Approved Accounts</span>
                        <span class="dashboard-analytics-icon"><i class="fas fa-user-check"></i></span>
                    </div>
                    <div class="dashboard-analytics-value"><?= $approvedUsers ?></div>
                    <div class="dashboard-analytics-sub">Active & granted access</div>
                </article>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <article class="dashboard-analytics-card analytics-tone-pending h-100">
                    <div class="dashboard-analytics-head">
                        <span class="dashboard-analytics-label">Pending Approval</span>
                        <span class="dashboard-analytics-icon"><i class="fas fa-user-clock"></i></span>
                    </div>
                    <div class="dashboard-analytics-value"><?= $pendingUsers ?></div>
                    <div class="dashboard-analytics-sub">Awaiting admin review</div>
                </article>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <article class="dashboard-analytics-card analytics-tone-purple h-100">
                    <div class="dashboard-analytics-head">
                        <span class="dashboard-analytics-label">Verified Email</span>
                        <span class="dashboard-analytics-icon"><i class="fas fa-envelope-circle-check"></i></span>
                    </div>
                    <div class="dashboard-analytics-value"><?= $verifiedEmailUsers ?></div>
                    <div class="dashboard-analytics-sub">Confirmed email addresses</div>
                </article>
            </div>
        </div>

        <!-- Master Users Card with 10-Item Pagination & Carousel Mode -->
        <div class="card shadow-sm border-0 mb-4" style="border-radius: 12px; overflow: hidden; border: 1px solid rgba(46,133,110,0.2) !important;">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2 py-3 text-white" style="background: linear-gradient(135deg, #1b5a56, #2e856e) !important;">
                <div class="d-flex align-items-center gap-2">
                    <h5 class="mb-0 fw-bold text-white"><i class="fas fa-users me-2"></i>All Registered Users (<?= count($users) ?>)</h5>
                </div>

                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <!-- Search Input -->
                    <div class="input-group input-group-sm" style="width: 220px;">
                        <span class="input-group-text bg-white text-dark border-0"><i class="bi bi-search"></i></span>
                        <input type="text" id="userSearchInput" class="form-control border-0" placeholder="Search users..." onkeyup="filterUserRecords()">
                    </div>

                    <!-- Page Size Selector (Default 10) -->
                    <select id="userPageSizeSelect" class="form-select form-select-sm border-0" style="width: auto;" onchange="changeUserPageSize(this.value)">
                        <option value="10" selected>10 per page</option>
                        <option value="25">25 per page</option>
                        <option value="50">50 per page</option>
                        <option value="1000">Show All</option>
                    </select>

                    <!-- View Switcher -->
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" class="btn btn-light active fw-semibold" id="btnUserTableView" onclick="switchUserView('table')">
                            <i class="bi bi-table me-1"></i> Table
                        </button>
                        <button type="button" class="btn btn-light fw-semibold" id="btnUserCarouselView" onclick="switchUserView('carousel')">
                            <i class="bi bi-view-stacked me-1"></i> Carousel (10/Slide)
                        </button>
                    </div>
                </div>
            </div>

            <div class="card-body p-0">
                <!-- ================= 1. TABLE VIEW (10 PER PAGE) ================= -->
                <div id="userTableView" class="p-3">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="usersTable">
                            <thead class="table-light">
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
                            <tbody id="usersTableBody">
                                <?php $modals_html = ''; foreach ($users as $idx => $u): ?>
                                <tr class="user-row"
                                    data-index="<?= $idx ?>"
                                    data-id="<?= htmlspecialchars(strtolower($u['user_id'])) ?>"
                                    data-name="<?= htmlspecialchars(strtolower($u['fullname'])) ?>"
                                    data-email="<?= htmlspecialchars(strtolower($u['emailadd'])) ?>"
                                    data-username="<?= htmlspecialchars(strtolower($u['username'])) ?>"
                                    data-role="<?= htmlspecialchars(strtolower($u['role'] ?? 'user')) ?>">
                                    <td class="text-muted fw-bold"><?= $u['user_id'] ?></td>
                                    <td><strong><?= htmlspecialchars($u['fullname']) ?></strong></td>
                                    <td><?= htmlspecialchars($u['emailadd']) ?></td>
                                    <td><code><?= htmlspecialchars($u['username']) ?></code></td>
                                    <td>
                                        <?php if (!empty($u['admin_approved']) && (int)$u['admin_approved'] === 1): ?>
                                            <span class="badge bg-success"><i class="bi bi-check"></i> Approved</span>
                                        <?php elseif (!empty($u['admin_approved']) && (int)$u['admin_approved'] === -1): ?>
                                            <span class="badge bg-danger"><i class="bi bi-x-circle"></i> Rejected</span>
                                        <?php else: ?>
                                            <?php if ($u['email_verified']): ?>
                                                <span class="badge bg-secondary"><i class="bi bi-check"></i> Email Verified (Pending Admin)</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark"><i class="bi bi-x"></i> Unverified</span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge bg-info text-white"><?= htmlspecialchars($u['role'] ?? 'User') ?></span></td>
                                    <td><small class="text-muted"><?= date('M d, Y', strtotime($u['created_at'])) ?></small></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-info btn-sm" data-bs-toggle="modal" data-bs-target="#userModal<?= $u['user_id'] ?>">
                                                <i class="fas fa-eye me-1"></i> Details
                                            </button>

                                            <?php if (!$u['email_verified']): ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="toggle_verify">
                                                <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                                                <input type="hidden" name="new_status" value="1">
                                                <button type="submit" class="btn btn-outline-success btn-sm" title="Verify Email">
                                                    <i class="bi bi-check-circle"></i>
                                                </button>
                                            </form>
                                            <?php endif; ?>

                                            <?php if (empty($u['admin_approved']) || (int)$u['admin_approved'] === 0): ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="approve_account">
                                                <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                                                <button type="submit" class="btn btn-success btn-sm text-white" title="Approve Account">
                                                    <i class="bi bi-person-check me-1"></i>Approve
                                                </button>
                                            </form>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="reject_account">
                                                <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                                                <button type="submit" class="btn btn-outline-danger btn-sm" title="Reject Account">
                                                    <i class="bi bi-person-x me-1"></i>Reject
                                                </button>
                                            </form>
                                            <?php elseif ((int)$u['admin_approved'] === -1): ?>
                                                <form method="POST" class="d-inline ms-1">
                                                    <input type="hidden" name="action" value="unreject_account">
                                                    <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                                                    <button type="submit" class="btn btn-outline-secondary btn-sm" title="Undo Rejection">
                                                        <i class="bi bi-arrow-counterclockwise"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>

                                            <?php if (empty($u['banned']) || (int)$u['banned'] === 0): ?>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Ban this user?');">
                                                <input type="hidden" name="action" value="ban">
                                                <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                                                <button type="submit" class="btn btn-outline-danger btn-sm" title="Ban User">
                                                    <i class="bi bi-slash-circle"></i>
                                                </button>
                                            </form>
                                            <?php else: ?>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Unban this user?');">
                                                <input type="hidden" name="action" value="unban">
                                                <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                                                <button type="submit" class="btn btn-warning btn-sm" title="Unban User">
                                                    <i class="bi bi-unlock"></i>
                                                </button>
                                            </form>
                                            <?php endif; ?>

                                            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this user?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                                                <button type="submit" class="btn btn-outline-danger btn-sm" title="Delete">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php
                                // Modal HTML buffer
                                ob_start();
                                ?>
                                <div class="modal fade" id="userModal<?= $u['user_id'] ?>" tabindex="-1" aria-labelledby="userModalLabel<?= $u['user_id'] ?>" aria-hidden="true">
                                  <div class="modal-dialog modal-lg modal-dialog-scrollable">
                                    <div class="modal-content border-0 shadow-lg" style="border-radius: 12px; overflow: hidden;">
                                      <div class="modal-header text-white" style="background: linear-gradient(135deg, #1b5a56, #2e856e);">
                                        <h5 class="modal-title fw-bold" id="userModalLabel<?= $u['user_id'] ?>"><i class="fas fa-user-circle me-2"></i>User Details - <?= htmlspecialchars($u['fullname'] ?? $u['username']) ?></h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                      </div>
                                      <div class="modal-body p-4">
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <dl class="row mb-0">
                                                    <dt class="col-sm-4 text-muted small text-uppercase">User ID</dt><dd class="col-sm-8 fw-bold">#<?= $u['user_id'] ?></dd>
                                                    <dt class="col-sm-4 text-muted small text-uppercase">Full Name</dt><dd class="col-sm-8"><?= htmlspecialchars($u['fullname']) ?></dd>
                                                    <dt class="col-sm-4 text-muted small text-uppercase">Email</dt><dd class="col-sm-8"><?= htmlspecialchars($u['emailadd']) ?></dd>
                                                    <dt class="col-sm-4 text-muted small text-uppercase">Username</dt><dd class="col-sm-8"><code><?= htmlspecialchars($u['username']) ?></code></dd>
                                                    <dt class="col-sm-4 text-muted small text-uppercase">Role</dt><dd class="col-sm-8"><span class="badge bg-info"><?= htmlspecialchars($u['role'] ?? 'User') ?></span></dd>
                                                    <dt class="col-sm-4 text-muted small text-uppercase">Signed Up</dt><dd class="col-sm-8"><?= date('M d, Y', strtotime($u['created_at'])) ?></dd>
                                                </dl>
                                            </div>
                                            <div class="col-md-6">
                                                <dl class="row mb-0">
                                                    <dt class="col-sm-4 text-muted small text-uppercase">Email Verified</dt><dd class="col-sm-8"><?= $u['email_verified'] ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>' ?></dd>
                                                    <dt class="col-sm-4 text-muted small text-uppercase">Address</dt><dd class="col-sm-8"><?= htmlspecialchars($u['address'] ?? 'N/A') ?></dd>
                                                    <dt class="col-sm-4 text-muted small text-uppercase">Barangay</dt><dd class="col-sm-8"><?= htmlspecialchars($u['barangay'] ?? 'N/A') ?></dd>
                                                    <dt class="col-sm-4 text-muted small text-uppercase">Admin Status</dt><dd class="col-sm-8"><?php echo isset($u['admin_approved']) ? ((int)$u['admin_approved']===1?'<span class="badge bg-success">Approved</span>':((int)$u['admin_approved']===-1?'<span class="badge bg-danger">Rejected</span>':'<span class="badge bg-warning text-dark">Pending</span>')) : 'N/A'; ?></dd>
                                                    <dt class="col-sm-4 text-muted small text-uppercase">Banned</dt><dd class="col-sm-8"><?= !empty($u['banned']) ? '<span class="badge bg-danger">Yes</span>' : '<span class="badge bg-success">No</span>' ?></dd>
                                                    <dt class="col-sm-4 text-muted small text-uppercase">Phone</dt><dd class="col-sm-8"><?= htmlspecialchars($u['phone'] ?? 'N/A') ?></dd>
                                                </dl>
                                            </div>
                                        </div>
                                      </div>
                                      <div class="modal-footer bg-light">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                      </div>
                                    </div>
                                  </div>
                                </div>
                                <?php
                                $modals_html .= ob_get_clean();
                                ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination Bar with Bullets/Pages + Prev/Next -->
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3 pt-2 border-top">
                        <div class="text-muted small" id="userPaginationInfo">
                            Showing 1 to 10 of <?= count($users) ?> entries
                        </div>
                        <nav aria-label="User table pagination">
                            <ul class="pagination pagination-sm mb-0" id="userPaginationControls"></ul>
                        </nav>
                    </div>
                </div>

                <!-- ================= 2. CAROUSEL VIEW (10 USERS PER SLIDE) ================= -->
                <div id="userCarouselView" class="p-3" style="display: none;">
                    <?php 
                        $userBatches = array_chunk($users, 10);
                        $totalUserSlides = count($userBatches);
                    ?>
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3 p-2 bg-light rounded border">
                        <span class="badge text-white px-3 py-2 fs-6" style="background: #2e856e;">
                            <i class="bi bi-view-stacked me-1"></i> <span id="userCarouselSlideLabel">Slide 1 of <?= max(1, $totalUserSlides) ?></span>
                        </span>
                        
                        <!-- Bullet Navigation Chips for Slides -->
                        <div class="d-flex gap-1 align-items-center flex-wrap" id="userCarouselBullets">
                            <?php foreach ($userBatches as $sIdx => $b): ?>
                                <button type="button" class="btn btn-sm <?= $sIdx === 0 ? 'btn-success text-white' : 'btn-outline-secondary' ?> py-0 px-2 rounded-pill fw-bold" onclick="goToUserCarouselSlide(<?= $sIdx ?>)" style="<?= $sIdx === 0 ? 'background-color: #2e856e; border-color: #2e856e;' : '' ?>">
                                    <?= $sIdx + 1 ?>
                                </button>
                            <?php endforeach; ?>
                        </div>

                        <div class="d-flex align-items-center gap-2">
                            <button class="btn btn-sm btn-success rounded-circle shadow-sm" type="button" data-bs-target="#userCarousel" data-bs-slide="prev" style="width:34px; height:34px; background-color: #2e856e; border-color: #2e856e;">
                                <i class="bi bi-chevron-left"></i>
                            </button>
                            <button class="btn btn-sm btn-success rounded-circle shadow-sm" type="button" data-bs-target="#userCarousel" data-bs-slide="next" style="width:34px; height:34px; background-color: #2e856e; border-color: #2e856e;">
                                <i class="bi bi-chevron-right"></i>
                            </button>
                        </div>
                    </div>

                    <div id="userCarousel" class="carousel slide" data-bs-interval="false">
                        <div class="carousel-inner">
                            <?php if (empty($userBatches)): ?>
                                <div class="carousel-item active"><div class="text-center py-5 text-muted">No user records found.</div></div>
                            <?php else: ?>
                                <?php foreach ($userBatches as $sIdx => $batch): ?>
                                    <div class="carousel-item <?= $sIdx === 0 ? 'active' : '' ?>">
                                        <div class="row g-3">
                                            <?php foreach ($batch as $u): ?>
                                                <div class="col-md-6 col-lg-6">
                                                    <div class="card h-100 border shadow-sm" style="border-radius: 10px; overflow: hidden;">
                                                        <div class="card-header bg-light d-flex justify-content-between align-items-center py-2 px-3">
                                                            <strong class="text-dark">#<?= $u['user_id'] ?> <?= htmlspecialchars($u['fullname']) ?></strong>
                                                            <span class="badge bg-info text-white"><?= htmlspecialchars($u['role'] ?? 'User') ?></span>
                                                        </div>
                                                        <div class="card-body p-3 small">
                                                            <div class="row g-2">
                                                                <div class="col-12"><strong>Email:</strong> <?= htmlspecialchars($u['emailadd']) ?></div>
                                                                <div class="col-6"><strong>Username:</strong> <code><?= htmlspecialchars($u['username']) ?></code></div>
                                                                <div class="col-6"><strong>Status:</strong> 
                                                                    <?php if (!empty($u['admin_approved']) && (int)$u['admin_approved'] === 1): ?>
                                                                        <span class="badge bg-success">Approved</span>
                                                                    <?php else: ?>
                                                                        <span class="badge bg-warning text-dark">Pending</span>
                                                                    <?php endif; ?>
                                                                </div>
                                                                <div class="col-12"><strong>Signed Up:</strong> <?= date('M d, Y', strtotime($u['created_at'])) ?></div>
                                                            </div>
                                                        </div>
                                                        <div class="card-footer bg-light d-flex justify-content-end gap-1 py-2 px-3">
                                                            <button class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#userModal<?= $u['user_id'] ?>">
                                                                <i class="fas fa-eye me-1"></i>Details
                                                            </button>
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
// ================= USER CATALOG PAGINATION & VIEW CONTROLLER =================
let currentUserPage = 1;
let userRowsPerPage = 10;
let filteredUserRows = [];

function initUserCatalog() {
    const rows = document.querySelectorAll('#usersTableBody tr.user-row');
    filteredUserRows = Array.from(rows);
    renderUserPagination();
}

function switchUserView(viewType) {
    const tableView = document.getElementById('userTableView');
    const carouselView = document.getElementById('userCarouselView');
    const btnTable = document.getElementById('btnUserTableView');
    const btnCarousel = document.getElementById('btnUserCarouselView');

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

function changeUserPageSize(size) {
    userRowsPerPage = parseInt(size) || 10;
    currentUserPage = 1;
    renderUserPagination();
}

function filterUserRecords() {
    const query = (document.getElementById('userSearchInput')?.value || '').toLowerCase().trim();
    const allRows = document.querySelectorAll('#usersTableBody tr.user-row');

    filteredUserRows = [];
    allRows.forEach(row => {
        const text = (
            (row.getAttribute('data-id') || '') + ' ' +
            (row.getAttribute('data-name') || '') + ' ' +
            (row.getAttribute('data-email') || '') + ' ' +
            (row.getAttribute('data-username') || '') + ' ' +
            (row.getAttribute('data-role') || '')
        ).toLowerCase();

        if (!query || text.includes(query)) {
            filteredUserRows.push(row);
        } else {
            row.style.display = 'none';
        }
    });

    currentUserPage = 1;
    renderUserPagination();
}

function renderUserPagination() {
    const total = filteredUserRows.length;
    const totalPages = Math.ceil(total / userRowsPerPage) || 1;
    if (currentUserPage > totalPages) currentUserPage = totalPages;
    if (currentUserPage < 1) currentUserPage = 1;

    const startIdx = (currentUserPage - 1) * userRowsPerPage;
    const endIdx = Math.min(startIdx + userRowsPerPage, total);

    const allRows = document.querySelectorAll('#usersTableBody tr.user-row');
    allRows.forEach(r => r.style.display = 'none');

    for (let i = startIdx; i < endIdx; i++) {
        if (filteredUserRows[i]) filteredUserRows[i].style.display = '';
    }

    const infoEl = document.getElementById('userPaginationInfo');
    if (infoEl) {
        if (total === 0) {
            infoEl.textContent = 'Showing 0 to 0 of 0 entries';
        } else {
            infoEl.textContent = `Showing ${startIdx + 1} to ${endIdx} of ${total} entries`;
        }
    }

    const controls = document.getElementById('userPaginationControls');
    if (!controls) return;

    let html = '';
    html += `<li class="page-item ${currentUserPage === 1 ? 'disabled' : ''}">
        <a class="page-link" href="javascript:void(0)" onclick="goToUserPage(${currentUserPage - 1})"><i class="bi bi-chevron-left"></i></a>
    </li>`;

    for (let p = 1; p <= totalPages; p++) {
        if (totalPages > 7 && Math.abs(p - currentUserPage) > 2 && p !== 1 && p !== totalPages) {
            if (p === 2 || p === totalPages - 1) {
                html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
            }
            continue;
        }
        html += `<li class="page-item ${p === currentUserPage ? 'active' : ''}">
            <a class="page-link" href="javascript:void(0)" onclick="goToUserPage(${p})" style="${p === currentUserPage ? 'background-color:#2e856e;border-color:#2e856e;color:#fff;' : ''}">${p}</a>
        </li>`;
    }

    html += `<li class="page-item ${currentUserPage >= totalPages ? 'disabled' : ''}">
        <a class="page-link" href="javascript:void(0)" onclick="goToUserPage(${currentUserPage + 1})"><i class="bi bi-chevron-right"></i></a>
    </li>`;

    controls.innerHTML = html;
}

function goToUserPage(page) {
    currentUserPage = page;
    renderUserPagination();
}

function goToUserCarouselSlide(index) {
    const carouselEl = document.getElementById('userCarousel');
    if (carouselEl) {
        const carousel = bootstrap.Carousel.getOrCreateInstance(carouselEl);
        carousel.to(index);
    }
}

document.addEventListener('DOMContentLoaded', function(){
    initUserCatalog();

    const userCarouselEl = document.getElementById('userCarousel');
    if (userCarouselEl) {
        userCarouselEl.addEventListener('slid.bs.carousel', function (e) {
            const label = document.getElementById('userCarouselSlideLabel');
            const totalSlides = <?= max(1, count(array_chunk($users, 10))) ?>;
            if (label) {
                label.textContent = `Slide ${e.to + 1} of ${totalSlides}`;
            }
            const bullets = document.querySelectorAll('#userCarouselBullets button');
            bullets.forEach((btn, idx) => {
                if (idx === e.to) {
                    btn.className = 'btn btn-sm btn-success text-white py-0 px-2 rounded-pill fw-bold';
                    btn.style.backgroundColor = '#2e856e';
                    btn.style.borderColor = '#2e856e';
                } else {
                    btn.className = 'btn btn-sm btn-outline-secondary py-0 px-2 rounded-pill fw-bold';
                    btn.style.backgroundColor = '';
                    btn.style.borderColor = '';
                }
            });
        });
    }
});
</script>

<?php
if (!empty($modals_html)) {
    echo $modals_html;
}

require_once '../includes/footer.php'; ?>

