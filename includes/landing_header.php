<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$is_logged_in = isset($_SESSION['user_id']);
$current_user = $is_logged_in ? $_SESSION : null;
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
                    <li class="nav-item">
                        <a class="nav-link active" href="index.php"><i class="bi bi-house-fill"></i> Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#modules"><i class="bi bi-grid-3x3-gap"></i> Modules</a>
                    </li>
                    <?php if ($is_logged_in) { ?>
                        <li class="nav-item">
                            <a class="nav-link" href="admin/dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-person-circle"></i> <?php echo substr($_SESSION['user_email'] ?? 'User', 0, 15); ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                                <li><a class="dropdown-item" href="admin/settings.php"><i class="bi bi-gear"></i> Settings</a></li>
                                <li><hr class="dropdown-divider"></li>
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


