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
        
        if ($action === 'toggle_verify') {
            $new_status = intval($_POST['new_status']);
            $pdo->prepare("UPDATE signup SET email_verified = ? WHERE user_id = ?")->execute([$new_status, $user_id]);
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'User verification status updated.'];
        } elseif ($action === 'approve_account') {
            $pdo->prepare("UPDATE signup SET admin_approved = 1 WHERE user_id = ?")->execute([$user_id]);
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Account approved.'];
        } elseif ($action === 'reject_account') {
            $pdo->prepare("UPDATE signup SET admin_approved = -1 WHERE user_id = ?")->execute([$user_id]);
            $_SESSION['flash'] = ['type' => 'warning', 'message' => 'Account rejected.'];
        } elseif ($action === 'unreject_account') {
            $pdo->prepare("UPDATE signup SET admin_approved = 0 WHERE user_id = ?")->execute([$user_id]);
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Account rejection has been undone.'];
        } elseif ($action === 'ban') {
            $pdo->prepare("UPDATE signup SET banned = 1 WHERE user_id = ?")->execute([$user_id]);
            $_SESSION['flash'] = ['type' => 'warning', 'message' => 'User has been banned.'];
        } elseif ($action === 'unban') {
            $pdo->prepare("UPDATE signup SET banned = 0 WHERE user_id = ?")->execute([$user_id]);
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
                            <?php $modals_html = ''; foreach ($users as $u): ?>
                            <tr>
                                <td><?= $u['user_id'] ?></td>
                                <td><?= htmlspecialchars($u['fullname']) ?></td>
                                <td><?= htmlspecialchars($u['emailadd']) ?></td>
                                <td><?= htmlspecialchars($u['username']) ?></td>
                                <td>
                                    <?php if (!empty($u['admin_approved']) && (int)$u['admin_approved'] === 1): ?>
                                        <span class="badge bg-success"><i class="bi bi-check"></i> Approved</span>
                                    <?php elseif (!empty($u['admin_approved']) && (int)$u['admin_approved'] === -1): ?>
                                        <span class="badge bg-danger"><i class="bi bi-x-circle"></i> Rejected</span>
                                    <?php else: ?>
                                        <?php if ($u['email_verified']): ?>
                                            <span class="badge bg-secondary"><i class="bi bi-check"></i> Email Verified (Pending Admin)</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning"><i class="bi bi-x"></i> Unverified</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge bg-info"><?= htmlspecialchars($u['role'] ?? 'User') ?></span></td>
                                <td><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#userModal<?= $u['user_id'] ?>">Details</button>

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
                                            <span class="badge bg-danger"><i class="bi bi-x-circle"></i> Rejected</span>
                                            <form method="POST" class="d-inline ms-1">
                                                <input type="hidden" name="action" value="unreject_account">
                                                <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                                                <button type="submit" class="btn btn-outline-secondary btn-sm" title="Undo Rejection">
                                                    <i class="bi bi-arrow-counterclockwise"></i>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span style="background-color: #ffffff; border-color: #28a745;" class="text-muted">Approved</span>
                                        <?php endif; ?>

                                        <?php if (empty($u['banned']) || (int)$u['banned'] === 0): ?>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Ban this user?');">
                                            <input type="hidden" name="action" value="ban">
                                            <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                                            <button type="submit" class="btn btn-outline-danger btn-sm" style="background-color: #ffffff; border-color: #28a745;" title="Ban User">
                                                <i class="bi bi-slash-circle"></i>
                                            </button>
                                        </form>
                                        <?php else: ?>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Unban this user?');">
                                            <input type="hidden" name="action" value="unban">
                                            <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                                            <button type="submit" class="btn btn-warning btn-sm" style="background-color: #ffffff; border-color: #28a745;" title="Unban User">
                                                <i class="bi bi-unlock"></i>
                                            </button>
                                        </form>
                                        <?php endif; ?>

                                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete this user?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                                            <button type="submit" class="btn btn-outline-danger btn-sm" style="background-color: #ffffff; border-color: #28a745;" title="Delete">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php
                            // Build modal HTML and append to buffer (render after table to avoid modal nesting issues)
                            ob_start();
                            ?>
                            <div class="modal fade" id="userModal<?= $u['user_id'] ?>" tabindex="-1" aria-labelledby="userModalLabel<?= $u['user_id'] ?>" aria-hidden="true">
                              <div class="modal-dialog modal-lg modal-dialog-scrollable">
                                <div class="modal-content">
                                  <div class="modal-header bg-light border-bottom">
                                    <h5 class="modal-title" id="userModalLabel<?= $u['user_id'] ?>">User Details - <?= htmlspecialchars($u['fullname'] ?? $u['username']) ?></h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                  </div>
                                  <div class="modal-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <dl class="row">
                                                <dt class="col-sm-4">User ID</dt><dd class="col-sm-8"><?= $u['user_id'] ?></dd>
                                                <dt class="col-sm-4">Full Name</dt><dd class="col-sm-8"><?= htmlspecialchars($u['fullname']) ?></dd>
                                                <dt class="col-sm-4">Email</dt><dd class="col-sm-8"><?= htmlspecialchars($u['emailadd']) ?></dd>
                                                <dt class="col-sm-4">Username</dt><dd class="col-sm-8"><?= htmlspecialchars($u['username']) ?></dd>
                                                <dt class="col-sm-4">Role</dt><dd class="col-sm-8"><?= htmlspecialchars($u['role'] ?? 'User') ?></dd>
                                                <dt class="col-sm-4">Signed Up</dt><dd class="col-sm-8"><?= date('M d, Y', strtotime($u['created_at'])) ?></dd>
                                            </dl>
                                        </div>
                                        <div class="col-md-6">
                                            <dl class="row">
                                                <dt class="col-sm-4">Email Verified</dt><dd class="col-sm-8"><?= $u['email_verified'] ? 'Yes' : 'No' ?></dd>
                                                <dt class="col-sm-4">Address</dt><dd class="col-sm-8"><?= htmlspecialchars($u['address'] ?? '') ?></dd>
                                                <dt class="col-sm-4">Barangay</dt><dd class="col-sm-8"><?= htmlspecialchars($u['barangay'] ?? '') ?></dd>
                                                <dt class="col-sm-4">Admin Approved</dt><dd class="col-sm-8"><?php echo isset($u['admin_approved']) ? ((int)$u['admin_approved']===1?'Yes':((int)$u['admin_approved']===-1?'Rejected':'Pending')) : 'N/A'; ?></dd>
                                                <dt class="col-sm-4">Banned</dt><dd class="col-sm-8"><?= !empty($u['banned']) ? 'Yes' : 'No' ?></dd>
                                                <dt class="col-sm-4">Resident QC</dt><dd class="col-sm-8"><?= !empty($u['resident_qc']) ? 'Yes' : 'No' ?></dd>
                                                <dt class="col-sm-4">Phone</dt><dd class="col-sm-8"><?= htmlspecialchars($u['phone'] ?? '') ?></dd>
                                            </dl>
                                        </div>
                                    </div>
                                    <hr>
                                    <h6>Uploaded IDs</h6>
                                    <div class="d-flex gap-3">
                                        <?php
                                        // Determine filesystem path and public URL for uploaded IDs.
                                        $front_url = null;
                                        $back_url = null;
                                        // Helper to resolve candidate to a web-accessible URL
                                        $resolveUpload = function($candidateRaw) {
                                            if (empty($candidateRaw)) return null;
                                            $c = trim($candidateRaw);
                                            // If it's an absolute URL, return as-is
                                            if (preg_match('#^https?://#i', $c)) return $c;

                                            // Normalize and remove leading ./ or ../
                                            $candidate = preg_replace('#^(\./|\.\./)+#', '', $c);
                                            $candidate = str_replace('\\', '/', $candidate);

                                            $root = realpath(__DIR__ . '/..');
                                            $projectName = basename($root);

                                            // If candidate already contains project name and starts with /, return as web path
                                            if (preg_match('#^/+' . preg_quote($projectName, '#') . '/#i', '/' . ltrim($candidate, '/'))) {
                                                // ensure leading slash
                                                return '/' . ltrim($candidate, '/');
                                            }

                                            // Try direct file under project root
                                            $try1 = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $candidate);
                                            if (is_file($try1)) {
                                                $rel = str_replace('\\', '/', substr($try1, strlen($root)));
                                                return '..' . $rel;
                                            }

                                            // If candidate path already points inside uploads folder, try that exact location
                                            $try2 = $root . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . ltrim(str_replace('/', DIRECTORY_SEPARATOR, $candidate), DIRECTORY_SEPARATOR);
                                            if (is_file($try2)) {
                                                $rel = str_replace('\\', '/', substr($try2, strlen($root)));
                                                return '..' . $rel;
                                            }

                                            // Try basename lookup in uploads/ids
                                            $try3 = $root . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'ids' . DIRECTORY_SEPARATOR . basename($candidate);
                                            if (is_file($try3)) {
                                                $rel = str_replace('\\', '/', substr($try3, strlen($root)));
                                                return '..' . $rel;
                                            }

                                            // As a last resort, if candidate already starts with project folder name, expose via leading slash
                                            if (stripos($candidate, $projectName . '/') === 0) {
                                                return '/' . $candidate;
                                            }

                                            return null;
                                        };

                                        $front_url = $resolveUpload($u['uploaded_front'] ?? null);
                                        $back_url  = $resolveUpload($u['uploaded_back'] ?? null);
                                        ?>
                                        <?php if ($front_url): ?>
                                            <div class="text-center">
                                                <a href="<?= htmlspecialchars($front_url) ?>" target="_blank" title="Open full-size front ID">
                                                    <img src="<?= htmlspecialchars($front_url) ?>" alt="Front ID" style="max-height:180px;max-width:150px;border:1px solid #ddd;padding:4px;border-radius:6px;object-fit:cover">
                                                </a>
                                                <div style="font-size:12px;margin-top:4px;color:#666">Front</div>
                                                <div style="margin-top:6px;">
                                                    <?php $front_basename = htmlspecialchars(basename($u['uploaded_front'] ?? ''));?>
                                                    <a class="btn btn-sm btn-outline-primary" href="<?= $base_url ?>admin/download_upload.php?f=ids/<?= urlencode($front_basename) ?>&inline=1" target="_blank">View</a>
                                                    <a class="btn btn-sm btn-outline-secondary" href="<?= $base_url ?>admin/download_upload.php?f=ids/<?= urlencode($front_basename) ?>">Download</a>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <div class="text-muted" style="padding:20px;background:#f9f9f9;border-radius:6px">No front ID available</div>
                                        <?php endif; ?>

                                        <?php if ($back_url): ?>
                                            <div class="text-center">
                                                <a href="<?= htmlspecialchars($back_url) ?>" target="_blank" title="Open full-size back ID">
                                                    <img src="<?= htmlspecialchars($back_url) ?>" alt="Back ID" style="max-height:180px;max-width:150px;border:1px solid #ddd;padding:4px;border-radius:6px;object-fit:cover">
                                                </a>
                                                <div style="font-size:12px;margin-top:4px;color:#666">Back</div>
                                                <div style="margin-top:6px;">
                                                    <?php $back_basename = htmlspecialchars(basename($u['uploaded_back'] ?? ''));?>
                                                    <a class="btn btn-sm btn-outline-primary" href="<?= $base_url ?>admin/download_upload.php?f=ids/<?= urlencode($back_basename) ?>&inline=1" target="_blank">View</a>
                                                    <a class="btn btn-sm btn-outline-secondary" href="<?= $base_url ?>admin/download_upload.php?f=ids/<?= urlencode($back_basename) ?>">Download</a>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <div class="text-muted" style="padding:20px;background:#f9f9f9;border-radius:6px">No back ID available</div>
                                        <?php endif; ?>
                                    </div>
                                    <?php
                                    // Show additional available user fields (if any)
                                    $extra_keys = ['address','birthdate','gender','id_type','id_number','notes'];
                                    $hasExtra = false;
                                    foreach ($extra_keys as $k) { if (!empty($u[$k])) { $hasExtra = true; break; } }
                                    if ($hasExtra):
                                    ?>
                                    <hr>
                                    <h6>Other Details</h6>
                                    <dl class="row">
                                        <?php foreach ($extra_keys as $k): if (!empty($u[$k])): ?>
                                            <dt class="col-sm-3"><?= htmlspecialchars(ucwords(str_replace('_',' ',$k))) ?></dt>
                                            <dd class="col-sm-9"><?= htmlspecialchars($u[$k]) ?></dd>
                                        <?php endif; endforeach; ?>
                                    </dl>
                                    <?php endif; ?>
                                  </div>
                                  <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                  </div>
                                </div>
                              </div>
                            </div>
                            <?php
                            $modals_html .= ob_get_clean();
                            ?>
                            <?php endforeach; ?>
                            <?php // render collected modals after the table to avoid issues with nested elements ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    </div>
</div>

<?php
// Render collected modals after the page content so Bootstrap can find them
if (!empty($modals_html)) {
    echo $modals_html;
}

require_once '../includes/footer.php'; ?>
