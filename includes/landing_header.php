<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$is_logged_in = isset($_SESSION['user_id']);
$current_user = $is_logged_in ? $_SESSION : null;
// Determine account status for notification bell
$account_status_text = null;
$account_status_badge = 'bg-secondary';
$account_status_detail = '';
$show_notif_badge = false;
if ($is_logged_in) {
    // Try to load DB connection and fetch account state
    try {
        require_once __DIR__ . '/../config/db_connect.php';
        $uid = intval($_SESSION['user_id'] ?? 0);
        if ($uid) {
            // Some installations may not have a `banned` column — select only available columns
            $s = $pdo->prepare('SELECT email_verified FROM signup WHERE user_id = ?');
            $s->execute([$uid]);
            $r = $s->fetch(PDO::FETCH_ASSOC) ?: [];
            // If `banned` column exists it will be picked up via the array; otherwise assume not banned
            $isBanned = !empty($r['banned'] ?? false);
            $emailVerified = !empty($r['email_verified']);

            // Account status shown for awareness only. Access control is now based on ban state only.
            if ($isBanned) {
                $account_status_text = 'Banned';
                $account_status_badge = 'bg-danger';
                $account_status_detail = 'Your account has been suspended by an administrator.';
                $show_notif_badge = true;
            } elseif ($emailVerified) {
                $account_status_text = 'Verified';
                $account_status_badge = 'bg-success';
                $account_status_detail = 'Your account is verified and active.';
            } else {
                $account_status_text = 'Not Verified';
                $account_status_badge = 'bg-secondary';
                $account_status_detail = 'Your account is active. Please verify your email for better tracking and communication.';
                $show_notif_badge = true;
            }
        }
    } catch (Throwable $e) {
        // ignore DB errors and keep notification hidden
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Alertara">
    <meta name="author" content="Alertara">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>Alertara</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@300;400;500;600;700&family=Libre+Baskerville:wght@400;700&display=swap" rel="stylesheet">
    <!-- Use HR 3&4 theme for parity, then local overrides -->
    <link href="<?php echo isset($base_url) ? $base_url : ''; ?>../HR-3&4-REVAMPED/MERGED_HR_SYSTEM/assets/css/style.css" rel="stylesheet">
    <link href="<?php echo isset($base_url) ? $base_url : ''; ?>assets/css/style.css" rel="stylesheet">
    <?php if (isset($additional_head)) echo $additional_head; ?>
</head>
<body class="landing-page-body">
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top shadow-sm">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="index.php">
                <img src="assets/css/tara.png" alt="Alertara" height="30" class="me-2">
                AlertaraPH
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <?php if ($is_logged_in && !empty($account_status_text)): ?>
                    <li class="nav-item dropdown me-2">
                        <a class="nav-link position-relative" href="#" id="notifDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-bell" style="font-size:1.1rem;"></i>
                            <?php if ($show_notif_badge): ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:.6rem">!</span>
                            <?php endif; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end p-3" aria-labelledby="notifDropdown" style="min-width:260px;">
                            <li><div><strong>Account Status</strong></div></li>
                            <li class="mt-2"><span class="badge <?= htmlspecialchars($account_status_badge) ?>"><?= htmlspecialchars($account_status_text) ?></span></li>
                            <li class="mt-2"><div style="font-size:.95rem;color:#666"><?= htmlspecialchars($account_status_detail) ?></div></li>
                        </ul>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link active" href="index.php"><i class="bi bi-house-fill"></i> Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#modules"><i class="bi bi-grid-3x3-gap"></i> Modules</a>
                    </li>
                    <?php if ($is_logged_in) { ?>
                        <?php $dashboardLink = (isset($_SESSION['role']) && strtolower($_SESSION['role']) === 'admin') ? 'admin/dashboard.php' : 'landing.php'; ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo htmlspecialchars($dashboardLink); ?>"><i class="bi bi-speedometer2"></i> Dashboard</a>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-person-circle"></i> <?php echo substr($_SESSION['user_email'] ?? 'User', 0, 15); ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                                <?php if (isset($_SESSION['role']) && strtolower($_SESSION['role']) === 'admin'): ?>
                                    <li><a class="dropdown-item" href="admin/settings.php"><i class="bi bi-gear"></i> Settings</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                <?php endif; ?>
                                <li><a class="dropdown-item" href="auth/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
                            </ul>
                        </li>
                    <?php } else { ?>
                        <li class="nav-item">
                            <a class="nav-link" href="auth/login.php" style="color: #4c8a89;"><i class="bi bi-box-arrow-in-right"></i> Sign In</a>
                        </li>
                    <?php } ?>
                </ul>
            </div>
        </div>
    </nav>

    <div class="landing-page-container">

    <style>
        .navbar {
            background: linear-gradient(135deg, rgba(0, 0, 0, 0.95) 0%, rgba(30, 40, 60, 0.95) 100%) !important;
            backdrop-filter: blur(10px);
        }
        .navbar-brand {
            font-size: 1.3rem;
            color: #4c8a89 !important;
        }
        .navbar-brand:hover {
            color: #2ba8a0 !important;
        }
        .navbar .nav-link {
            color: rgba(255, 255, 255, 0.8) !important;
            font-weight: 500;
            transition: all 0.3s ease;
            margin: 0 0.5rem;
        }
        .navbar .nav-link:hover,
        .navbar .nav-link.active {
            color: #4c8a89 !important;
            transform: translateY(-2px);
        }
        .navbar .dropdown-menu {
            background: rgba(0, 0, 0, 0.95) !important;
            border: 1px solid rgba(76, 138, 137, 0.3);
            border-radius: 8px;
        }
        .navbar .dropdown-item {
            color: rgba(255, 255, 255, 0.8);
            transition: all 0.2s ease;
        }
        .navbar .dropdown-item:hover {
            background: rgba(76, 138, 137, 0.2) !important;
            color: #4c8a89 !important;
        }
        .navbar-toggler {
            border: none;
        }
        .navbar-toggler:focus {
            box-shadow: 0 0 0 0.25rem rgba(76, 138, 137, 0.25);
        }
        @media (max-width: 768px) {
            .navbar .nav-link {
                margin: 0.5rem 0;
                padding: 0.5rem 0 !important;
            }
            .navbar-brand {
                font-size: 1.1rem;
            }
        }
    </style>


