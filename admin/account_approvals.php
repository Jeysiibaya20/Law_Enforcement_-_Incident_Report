<?php
require_once 'admin_auth.php';
$page_title = 'Account Approvals';
require_once __DIR__ . '/../includes/header.php';
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

        <div class="card shadow-sm border-0 mb-4" style="border-radius: 12px; overflow: hidden; border: 1px solid rgba(46,133,110,0.2) !important;">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2 py-3 text-white" style="background: linear-gradient(135deg, #1b5a56, #2e856e) !important;">
                <div class="d-flex align-items-center gap-2">
                    <h5 class="mb-0 fw-bold text-white"><i class="fas fa-user-check me-2"></i>Pending Accounts (<?= count($accounts) ?>)</h5>
                </div>

                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <!-- Search Input -->
                    <div class="input-group input-group-sm" style="width: 220px;">
                        <span class="input-group-text bg-white text-dark border-0"><i class="bi bi-search"></i></span>
                        <input type="text" id="approvalSearchInput" class="form-control border-0" placeholder="Search approvals..." onkeyup="filterApprovalRecords()">
                    </div>

                    <!-- Page Size Selector -->
                    <select id="approvalPageSizeSelect" class="form-select form-select-sm border-0" style="width: auto;" onchange="changeApprovalPageSize(this.value)">
                        <option value="10" selected>10 per page</option>
                        <option value="25">25 per page</option>
                        <option value="50">50 per page</option>
                        <option value="1000">Show All</option>
                    </select>
                </div>
            </div>

            <div class="card-body p-3">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="approvalsTable">
                        <thead class="table-light">
                            <tr>
                                <th>User ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Username</th>
                                <th>ID Front</th>
                                <th>ID Back</th>
                                <th>QC Resident</th>
                                <th>State</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="approvalsTableBody">
                        <?php if (empty($accounts)): ?>
                            <tr><td colspan="9" class="text-center text-secondary py-4">No accounts awaiting admin approval.</td></tr>
                        <?php else: foreach ($accounts as $idx => $a): ?>
                            <tr class="approval-row"
                                data-index="<?= $idx ?>"
                                data-id="<?= htmlspecialchars(strtolower($a['user_id'])) ?>"
                                data-name="<?= htmlspecialchars(strtolower($a['fullname'] ?? $a['username'])) ?>"
                                data-email="<?= htmlspecialchars(strtolower($a['emailadd'])) ?>"
                                data-username="<?= htmlspecialchars(strtolower($a['username'])) ?>">
                                <td><strong>#<?= htmlspecialchars($a['user_id']); ?></strong></td>
                                <td><strong><?php echo htmlspecialchars($a['fullname'] ?? $a['username']); ?></strong></td>
                                <td><?php echo htmlspecialchars($a['emailadd']); ?></td>
                                <td><code><?php echo htmlspecialchars($a['username']); ?></code></td>
                                <td>
                                    <?php if (!empty($a['uploaded_front'])): ?>
                                        <a href="<?php echo htmlspecialchars($a['uploaded_front']); ?>" target="_blank"><img src="<?php echo htmlspecialchars($a['uploaded_front']); ?>" style="height:45px;border:1px solid #ddd;padding:2px;border-radius:4px;object-fit:cover" /></a>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($a['uploaded_back'])): ?>
                                        <a href="<?php echo htmlspecialchars($a['uploaded_back']); ?>" target="_blank"><img src="<?php echo htmlspecialchars($a['uploaded_back']); ?>" style="height:45px;border:1px solid #ddd;padding:2px;border-radius:4px;object-fit:cover" /></a>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo (!empty($a['resident_qc']) ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>'); ?></td>
                                <td>
                                    <?php
                                        $state = $a['admin_approved'] === null ? 0 : (int)$a['admin_approved'];
                                        if ($state === 1) echo '<span class="badge bg-success">Approved</span>';
                                        elseif ($state === -1) echo '<span class="badge bg-danger">Rejected</span>';
                                        else echo '<span class="badge bg-warning text-dark">Pending</span>';
                                    ?>
                                </td>
                                <td style="white-space:nowrap">
                                    <form method="post" style="display:inline">
                                        <input type="hidden" name="user_id" value="<?php echo (int)$a['user_id']; ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <button class="btn btn-sm btn-success text-white" type="submit"><i class="bi bi-check me-1"></i>Approve</button>
                                    </form>
                                    <form method="post" style="display:inline;margin-left:4px">
                                        <input type="hidden" name="user_id" value="<?php echo (int)$a['user_id']; ?>">
                                        <input type="hidden" name="action" value="reject">
                                        <button class="btn btn-sm btn-outline-danger" type="submit" onclick="return confirm('Reject this account?')"><i class="bi bi-x me-1"></i>Reject</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Controls Bar with Bullets/Pages + Prev/Next -->
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3 pt-2 border-top">
                    <div class="text-muted small" id="approvalPaginationInfo">
                        Showing 1 to 10 of <?= count($accounts) ?> entries
                    </div>
                    <nav aria-label="Approvals pagination">
                        <ul class="pagination pagination-sm mb-0" id="approvalPaginationControls"></ul>
                    </nav>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// ================= APPROVALS PAGINATION CONTROLLER =================
let currentApprovalPage = 1;
let approvalRowsPerPage = 10;
let filteredApprovalRows = [];

function initApprovalCatalog() {
    const rows = document.querySelectorAll('#approvalsTableBody tr.approval-row');
    filteredApprovalRows = Array.from(rows);
    renderApprovalPagination();
}

function changeApprovalPageSize(size) {
    approvalRowsPerPage = parseInt(size) || 10;
    currentApprovalPage = 1;
    renderApprovalPagination();
}

function filterApprovalRecords() {
    const query = (document.getElementById('approvalSearchInput')?.value || '').toLowerCase().trim();
    const allRows = document.querySelectorAll('#approvalsTableBody tr.approval-row');

    filteredApprovalRows = [];
    allRows.forEach(row => {
        const text = (
            (row.getAttribute('data-id') || '') + ' ' +
            (row.getAttribute('data-name') || '') + ' ' +
            (row.getAttribute('data-email') || '') + ' ' +
            (row.getAttribute('data-username') || '')
        ).toLowerCase();

        if (!query || text.includes(query)) {
            filteredApprovalRows.push(row);
        } else {
            row.style.display = 'none';
        }
    });

    currentApprovalPage = 1;
    renderApprovalPagination();
}

function renderApprovalPagination() {
    const total = filteredApprovalRows.length;
    const totalPages = Math.ceil(total / approvalRowsPerPage) || 1;
    if (currentApprovalPage > totalPages) currentApprovalPage = totalPages;
    if (currentApprovalPage < 1) currentApprovalPage = 1;

    const startIdx = (currentApprovalPage - 1) * approvalRowsPerPage;
    const endIdx = Math.min(startIdx + approvalRowsPerPage, total);

    const allRows = document.querySelectorAll('#approvalsTableBody tr.approval-row');
    allRows.forEach(r => r.style.display = 'none');

    for (let i = startIdx; i < endIdx; i++) {
        if (filteredApprovalRows[i]) filteredApprovalRows[i].style.display = '';
    }

    const infoEl = document.getElementById('approvalPaginationInfo');
    if (infoEl) {
        if (total === 0) {
            infoEl.textContent = 'Showing 0 to 0 of 0 entries';
        } else {
            infoEl.textContent = `Showing ${startIdx + 1} to ${endIdx} of ${total} entries`;
        }
    }

    const controls = document.getElementById('approvalPaginationControls');
    if (!controls) return;

    let html = '';
    html += `<li class="page-item ${currentApprovalPage === 1 ? 'disabled' : ''}">
        <a class="page-link" href="javascript:void(0)" onclick="goToApprovalPage(${currentApprovalPage - 1})"><i class="bi bi-chevron-left"></i></a>
    </li>`;

    for (let p = 1; p <= totalPages; p++) {
        if (totalPages > 7 && Math.abs(p - currentApprovalPage) > 2 && p !== 1 && p !== totalPages) {
            if (p === 2 || p === totalPages - 1) {
                html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
            }
            continue;
        }
        html += `<li class="page-item ${p === currentApprovalPage ? 'active' : ''}">
            <a class="page-link" href="javascript:void(0)" onclick="goToApprovalPage(${p})" style="${p === currentApprovalPage ? 'background-color:#2e856e;border-color:#2e856e;color:#fff;' : ''}">${p}</a>
        </li>`;
    }

    html += `<li class="page-item ${currentApprovalPage >= totalPages ? 'disabled' : ''}">
        <a class="page-link" href="javascript:void(0)" onclick="goToApprovalPage(${currentApprovalPage + 1})"><i class="bi bi-chevron-right"></i></a>
    </li>`;

    controls.innerHTML = html;
}

function goToApprovalPage(page) {
    currentApprovalPage = page;
    renderApprovalPagination();
}

document.addEventListener('DOMContentLoaded', function(){
    initApprovalCatalog();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
