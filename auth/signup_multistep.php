<?php
/**
 * Multi-Step Sign Up Form
 * 1. Personal Info
 * 2. Contact Info + Barangay/Address for QC / Non-QC
 * 3. Account Security (Username, Password, TOTP option)
 * 4. Document Upload (Optional)
 * 5. Review & Submit
 * 6. TOTP Setup (if enabled)
 */

session_start();
require_once '../config/db_connect.php';
if (!isset($pdo) || !($pdo instanceof PDO)) {
    $pdo = getDBConnection();
}

// Quezon City Barangays (Complete 142 official barangays in alphabetical order)
$qc_barangays = [
    "Alicia", "Amihan", "Apolonio Samson", "Baesa", "Bagbag", "Bagong Lipunan ng Crame", "Bagong Pag-asa", 
    "Bagong Silangan", "Bagumbayan", "Bagumbuhay", "Bahay Toro", "Balingasa", "Balintawak", "Balumbato", 
    "Batasan Hills", "Bayanihan", "Blue Ridge A", "Blue Ridge B", "Botocan", "Bungad", "Camp Aguinaldo", 
    "Capri", "Central", "Claro", "Commonwealth", "Corazon de Jesus", "Culiat", "Damar", "Damayan", 
    "Del Monte", "Dioquino Zobel", "Doña Aurora", "Doña Imelda", "Doña Josefa", "Don Manuel", "Duyan-duyan", 
    "E. Rodriguez", "East Kamias", "Escopa I", "Escopa II", "Escopa III", "Escopa IV", "Fairview", 
    "Greater Lagro", "Gulod", "Holy Spirit", "Horseshoe", "Immaculate Concepcion", "Kaligayahan", 
    "Kalusugan", "Kamuning", "Katipunan", "Kaunlaran", "Kristong Hari", "Krus na Ligas", "Laging Handa", 
    "Libis", "Lourdes", "Loyola Heights", "Maharlika", "Malaya", "Mangga", "Manresa", "Mariana", 
    "Mariblo", "Marilag", "Masagana", "Masambong", "Matandang Balara", "Milagrosa", "Nagkaisang Nayon", 
    "Nayong Kanluran", "New Era", "North Fairview", "Novaliches Proper", "Obrero", "Old Capitol Site", 
    "Paang Bundok", "Pag-ibig sa Nayon", "Paligsahan", "Paltok", "Pansol", "Paraiso", "Pasong Putik Proper", 
    "Pasong Tamo", "Payatas", "Phil-Am", "Pinagkaisahan", "Pinyahan", "Project 6", "Quirino 2-A", 
    "Quirino 2-B", "Quirino 2-C", "Quirino 3-A", "Ramon Magsaysay", "Roxas", "Sacred Heart", "Saint Ignatius", 
    "Saint Peter", "Salvacion", "San Agustin", "San Antonio", "San Bartolome", "San Dionisio", 
    "San Isidro Labrador", "San Isidro", "San Jose", "San Martin de Porres", "San Roque", "San Vicente", 
    "Sangandaan", "Santa Cruz", "Santa Lucia", "Santa Monica", "Santa Teresita", "Santo Cristo", 
    "Santo Domingo", "Santo Niño", "Santol", "Sauyo", "Sikatuna Village", "Silangan", "Socorro", 
    "South Triangle", "Tagumpay", "Talayan", "Talipapa", "Tandang Sora", "Tatalon", "Teachers Village East", 
    "Teachers Village West", "Triangle", "U.P. Campus", "U.P. Village", "Ugong Norte", "Unang Sigaw", 
    "Valencia", "Vasra", "Veterans Village", "Villa Maria Clara", "West Kamias", "West Triangle", "White Plains"
];

$error_message = '';
$success_message = '';

// POST handler
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Basic fields
        $first_name = trim($_POST['first_name'] ?? '');
        $middle_name = trim($_POST['middle_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $fullname = trim(implode(' ', array_filter([$first_name, $middle_name, $last_name])));
        $sex = trim($_POST['sex'] ?? '');
        $dob = trim($_POST['dob'] ?? '');
        $email = trim($_POST['emailadd'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $enable_totp = isset($_POST['enable_totp_opt']) ? 1 : 0;
        $agree_terms = isset($_POST['agree_terms']) ? 1 : 0;
        $resident_qc = isset($_POST['resident_qc']) ? 1 : 0;

        if ($resident_qc) {
            $barangay = trim($_POST['barangay'] ?? '');
            $address = trim($_POST['address'] ?? '');
        } else {
            $custom_city = trim($_POST['custom_city'] ?? '');
            $barangay = trim($_POST['custom_barangay'] ?? '');
            $custom_addr = trim($_POST['custom_address'] ?? '');
            $address = trim(implode(', ', array_filter([$custom_addr, $barangay, $custom_city])));
        }

        // Validation
        if (empty($first_name) || empty($last_name) || empty($dob) || empty($email) || empty($username) || empty($password)) {
            throw new Exception('Please fill in all required fields');
        }

        // Validate Date of Birth (must be valid date and not in the future)
        $dobTime = strtotime($dob);
        if (!$dobTime || $dobTime > time()) {
            throw new Exception('Date of birth cannot be in the future.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email address');
        }

        if (strlen($password) < 8 || strpos($password, ' ') !== false) {
            throw new Exception('Password must be at least 8 characters with no spaces');
        }

        $confirm_password = $_POST['confirmpassword'] ?? '';
        if ($password !== $confirm_password) {
            throw new Exception('Passwords do not match');
        }

        if (!preg_match('/^\+?\d{10,14}$/', $phone)) {
            throw new Exception('Please enter a valid phone number');
        }

        if (!$agree_terms) {
            throw new Exception('You must agree to the Terms and Conditions');
        }

        // Check username uniqueness
        $checkUserSql = "SELECT user_id FROM signup WHERE username = ?";
        $checkUserStmt = $pdo->prepare($checkUserSql);
        $checkUserStmt->execute([$username]);
        if ($checkUserStmt->fetch(PDO::FETCH_ASSOC)) {
            throw new Exception('Username already exists');
        }

        // Check email uniqueness
        $checkEmailSql = "SELECT user_id FROM signup WHERE emailadd = ?";
        $checkEmailStmt = $pdo->prepare($checkEmailSql);
        $checkEmailStmt->execute([$email]);
        if ($checkEmailStmt->fetch(PDO::FETCH_ASSOC)) {
            throw new Exception('Email address already exists');
        }

        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $verification_token = bin2hex(random_bytes(32));
        $token_expires = date("Y-m-d H:i:s", strtotime("+24 hours"));
        $terms_accepted_date = date("Y-m-d H:i:s");

        // Insert user as verified for local development
        $sql = "INSERT INTO signup (fullname, emailadd, username, password, email_verified, verification_token, token_expires, terms_accepted, terms_accepted_date, sex, dob, phone, resident_qc) 
                VALUES (?, ?, ?, ?, 1, ?, ?, 1, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$fullname, $email, $username, $hashed_password, $verification_token, $token_expires, $terms_accepted_date, $sex, $dob, $phone, $resident_qc]);
        $user_id = $pdo->lastInsertId();

        // Store barangay and address
        try {
            $colsToCheck = [
                'barangay' => "ALTER TABLE signup ADD COLUMN barangay VARCHAR(100) DEFAULT NULL",
                'address' => "ALTER TABLE signup ADD COLUMN address VARCHAR(255) DEFAULT NULL",
            ];
            foreach ($colsToCheck as $col => $alterSql) {
                $qcol = $pdo->quote($col);
                $check = $pdo->query("SHOW COLUMNS FROM signup LIKE " . $qcol);
                if ($check === false || !$check->fetch(PDO::FETCH_ASSOC)) {
                    $pdo->exec($alterSql);
                }
            }
            $updateSql = "UPDATE signup SET barangay = ?, address = ? WHERE user_id = ?";
            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute([$barangay, $address, $user_id]);
        } catch (Exception $e) {
            error_log('Failed to store barangay/address: ' . $e->getMessage());
        }

        // Handle file uploads (optional)
        $front_path = null;
        $back_path = null;
        if (!empty($_FILES['front_id']) && $_FILES['front_id']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['image/jpeg', 'image/png'];
            if (in_array($_FILES['front_id']['type'], $allowed)) {
                $targetDir = __DIR__ . '/../uploads/ids/';
                if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);
                $ext = pathinfo($_FILES['front_id']['name'], PATHINFO_EXTENSION);
                $fname = uniqid('front_') . '.' . $ext;
                if (move_uploaded_file($_FILES['front_id']['tmp_name'], $targetDir . $fname)) {
                    $front_path = 'uploads/ids/' . $fname;
                }
            }
        }
        if (!empty($_FILES['back_id']) && $_FILES['back_id']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['image/jpeg', 'image/png'];
            if (in_array($_FILES['back_id']['type'], $allowed)) {
                $targetDir = __DIR__ . '/../uploads/ids/';
                if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);
                $ext = pathinfo($_FILES['back_id']['name'], PATHINFO_EXTENSION);
                $fname = uniqid('back_') . '.' . $ext;
                if (move_uploaded_file($_FILES['back_id']['tmp_name'], $targetDir . $fname)) {
                    $back_path = 'uploads/ids/' . $fname;
                }
            }
        }

        // Store uploaded IDs
        try {
            $colsToCheck = ['id_type' => "ALTER TABLE signup ADD COLUMN id_type VARCHAR(100) DEFAULT NULL", 'uploaded_front' => "ALTER TABLE signup ADD COLUMN uploaded_front VARCHAR(255) DEFAULT NULL", 'uploaded_back' => "ALTER TABLE signup ADD COLUMN uploaded_back VARCHAR(255) DEFAULT NULL"];
            foreach ($colsToCheck as $col => $alterSql) {
                $qcol = $pdo->quote($col);
                $check = $pdo->query("SHOW COLUMNS FROM signup LIKE " . $qcol);
                if ($check === false || !$check->fetch(PDO::FETCH_ASSOC)) {
                    $pdo->exec($alterSql);
                }
            }
            $updateSql = "UPDATE signup SET id_type = ?, uploaded_front = ?, uploaded_back = ? WHERE user_id = ?";
            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute([$_POST['id_type'] ?? null, $front_path, $back_path, $user_id]);
        } catch (Exception $e) {
            error_log('Failed to store documents: ' . $e->getMessage());
        }

        // Account created successfully
        echo "<script>alert('Account created successfully. You may now log in.'); window.location.href='login.php';</script>";
        exit();

    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - Alertara PH</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
* {
    box-sizing: border-box;
}

.login-container {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    background: var(--primary-bg, #0f172a);
    overflow-x: hidden;
    padding: 2.5rem 1rem;
    width: 100%;
    margin: 0;
    box-sizing: border-box;
}

.login-background {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(135deg, 
        rgba(76, 138, 137, 0.85) 0%, 
        rgba(58, 80, 107, 0.8) 50%, 
        rgba(28, 37, 65, 0.9) 100%);
    z-index: 1;
}

.login-background::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('../assets/css/QC.jpeg') center/cover no-repeat;
    opacity: 0.08;
    z-index: 1;
}

.login-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="%238B6F47" opacity="0.1"/><circle cx="75" cy="75" r="1" fill="%23D4A574" opacity="0.1"/><circle cx="50" cy="10" r="0.5" fill="%236B5B73" opacity="0.1"/><circle cx="10" cy="60" r="0.5" fill="%23D4A574" opacity="0.1"/><circle cx="90" cy="40" r="0.5" fill="%238B6F47" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
    z-index: 2;
}

.login-content {
    position: relative;
    z-index: 3;
    width: 100%;
    max-width: 640px;
    padding: 0;
    display: flex;
    justify-content: center;
    align-items: center;
}

.login-card {
    background: rgba(255, 255, 255, 0.97);
    border-radius: 16px;
    box-shadow: 0 20px 45px rgba(0, 0, 0, 0.25);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.4);
    overflow: hidden;
    animation: slideInUp 0.6s cubic-bezier(0.16, 1, 0.3, 1);
    width: 100%;
}

.login-header {
    text-align: center;
    padding: 2.5rem 2rem 1.8rem;
    background: linear-gradient(135deg, 
        rgba(76, 138, 137, 0.95) 0%, 
        rgba(58, 80, 107, 0.9) 50%, 
        rgba(28, 37, 65, 0.95) 100%);
    color: #ffffff;
    position: relative;
}

.login-logo {
    width: 80px;
    height: 80px;
    background: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1.25rem;
    box-shadow: 0 8px 20px rgba(0,0,0,0.18);
    overflow: hidden;
    padding: 4px;
}

.logo-image {
    width: 100%;
    height: 100%;
    object-fit: contain;
    border-radius: 50%;
}

.login-title {
    font-family: 'Libre Baskerville', serif, 'Georgia';
    font-size: 2.2rem;
    font-weight: 700;
    margin-bottom: 0.35rem;
    color: #ffffff;
    letter-spacing: -0.5px;
}

.login-subtitle {
    font-size: 1rem;
    font-weight: 400;
    margin-bottom: 0;
    opacity: 0.9;
    color: #e2e8f0;
}

.login-form-container {
    padding: 2rem 2.25rem 2.5rem;
}

.login-form {
    display: flex;
    flex-direction: column;
}

.signup-step { 
    margin-bottom: 0; 
}

.signup-step h3 { 
    margin-bottom: 1.5rem; 
    color: #1c2541; 
    font-size: 1.25rem; 
    font-weight: 700; 
    border-bottom: 2px solid #edf2f7;
    padding-bottom: 0.6rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.form-row { 
    display: flex; 
    gap: 1.25rem; 
    margin-bottom: 1.25rem;
    width: 100%;
    align-items: flex-start;
}

.form-row > div { 
    flex: 1 1 0; 
    min-width: 0;
}

.form-group { 
    margin-bottom: 1.25rem; 
    width: 100%;
}

.form-label {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-weight: 600;
    color: #2d3748;
    margin-bottom: 0.45rem;
    font-size: 0.92rem;
}

.form-control {
    width: 100%;
    padding: 0.85rem 1.05rem;
    border: 1.5px solid #cbd5e1;
    border-radius: 8px;
    font-size: 0.95rem;
    background: #ffffff;
    transition: all 0.2s ease;
    font-family: inherit;
    color: #1e293b;
    box-sizing: border-box;
}

.form-control:focus {
    outline: none;
    border-color: #2e856e;
    box-shadow: 0 0 0 3px rgba(46, 133, 110, 0.18);
    background: #ffffff;
}

.form-control::placeholder {
    color: #94a3b8;
    font-style: normal;
}

select.form-control {
    cursor: pointer;
}

.login-btn {
    background: #2e856e;
    color: #ffffff;
    border: none;
    padding: 0.85rem 1.75rem;
    border-radius: 8px;
    font-size: 0.98rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    box-shadow: 0 4px 12px rgba(46, 133, 110, 0.25);
    text-decoration: none;
}

.login-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(46, 133, 110, 0.35);
    background: #246d5a;
}

.login-btn:active {
    transform: translateY(0);
}

.login-btn[disabled] { 
    background: #94a3b8; 
    cursor: not-allowed; 
    opacity: 0.6;
    transform: none;
    box-shadow: none;
}

.text-muted { 
    color: #64748b; 
    font-size: 0.85rem; 
}

.alert { 
    padding: 0.9rem 1.1rem; 
    margin-bottom: 1.5rem; 
    border-radius: 8px; 
    display: flex;
    align-items: center;
    gap: 0.6rem;
    font-size: 0.92rem;
    animation: fadeInDown 0.4s ease-out;
}

.alert-danger { 
    background: #fef2f2;
    color: #991b1b;
    border: 1px solid #fecaca;
}

.alert-success { 
    background: #f0fdf4; 
    color: #166534; 
    border: 1px solid #bbf7d0; 
}

@keyframes slideInUp {
    from {
        transform: translateY(25px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

@keyframes fadeInDown {
    from {
        transform: translateY(-8px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

@media (max-width: 640px) {
    .login-container {
        padding: 1rem 0.5rem;
    }
    
    .login-header {
        padding: 2rem 1.25rem 1.25rem;
    }
    
    .login-form-container {
        padding: 1.5rem 1.25rem 2rem;
    }
    
    .login-title {
        font-size: 1.8rem;
    }
    
    .form-row {
        flex-direction: column;
        gap: 0;
    }
    
    .form-row > div {
        margin-bottom: 1.15rem;
    }
}
    </style>
</head>
<body>
<div class="login-container">
    <div class="login-background"></div>
    <div class="login-content">
        <div class="login-card">
            <div class="login-header">
                <div class="login-logo">
                    <img src="../assets/css/tara.png" alt="Alertara Logo" class="logo-image">
                </div>
                <h1 class="login-title">Sign Up for Alertara</h1>
                <p class="login-subtitle">Law Enforcement and Incident Reporting</p>
            </div>

            <div class="login-form-container">
                <?php if ($error_message): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-circle-fill"></i>
                        <span><?php echo htmlspecialchars($error_message); ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" id="signupForm" enctype="multipart/form-data" novalidate>
                    <!-- Step 1: Personal Information -->
                    <div class="signup-step" data-step="1">
                        <h3><i class="bi bi-person-badge"></i> 1. Personal Information</h3>
                        <div class="form-row">
                            <div>
                                <label class="form-label">First Name *</label>
                                <input type="text" class="form-control" name="first_name" placeholder="Enter first name" required>
                            </div>
                            <div>
                                <label class="form-label">Middle Name</label>
                                <input type="text" class="form-control" name="middle_name" placeholder="Optional">
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Last Name *</label>
                            <input type="text" class="form-control" name="last_name" placeholder="Enter last name" required>
                        </div>
                        <div class="form-row">
                            <div>
                                <label class="form-label">Sex *</label>
                                <select class="form-control" name="sex" required>
                                    <option value="">Select Sex</option>
                                    <option value="Female">Female</option>
                                    <option value="Male">Male</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div>
                                <label class="form-label">Date of Birth *</label>
                                <input type="date" class="form-control" id="dob" name="dob" max="<?php echo date('Y-m-d'); ?>" min="1900-01-01" required>
                            </div>
                        </div>
                        <div style="margin-top:1.5rem;display:flex;justify-content:flex-end;">
                            <button type="button" class="login-btn" id="toStep2">Next <i class="bi bi-arrow-right"></i></button>
                        </div>
                    </div>

                    <!-- Step 2: Contact Info & Barangay -->
                    <div class="signup-step" data-step="2" style="display:none;">
                        <h3><i class="bi bi-geo-alt"></i> 2. Contact Information</h3>
                        <div class="form-group">
                            <label class="form-label">Email Address *</label>
                            <input type="email" class="form-control" name="emailadd" id="emailadd" placeholder="name@example.com" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Phone Number *</label>
                            <input type="tel" class="form-control" name="phone" id="phone" pattern="\d{10,14}" placeholder="09XXXXXXXXX" required>
                        </div>
                        
                        <div class="form-group" style="background:#f8fafc; padding:0.85rem 1rem; border-radius:8px; border:1px solid #e2e8f0; margin-bottom:1.15rem;">
                            <label style="display:flex; align-items:center; gap:0.6rem; cursor:pointer; font-weight:600; color:#334155; margin:0;">
                                <input type="checkbox" name="resident_qc" id="resident_qc" value="1" checked style="width:18px; height:18px; accent-color:#2e856e; cursor:pointer;"> 
                                <span>Are you a resident of Quezon City?</span>
                            </label>
                        </div>

                        <!-- QC Resident Fields -->
                        <div id="qc_fields">
                            <div class="form-group">
                                <label class="form-label">Barangay (Quezon City) *</label>
                                <select class="form-control" id="barangay" name="barangay" required>
                                    <option value="">-- Select Barangay --</option>
                                    <?php foreach ($qc_barangays as $brgy): ?>
                                        <option value="<?php echo htmlspecialchars($brgy); ?>"><?php echo htmlspecialchars($brgy); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">House No. / Building / Street / Subdivision *</label>
                                <input type="text" class="form-control" id="address" name="address" placeholder="e.g. Blk 12 Lot 4, Dahlia St., Fairview" required>
                            </div>
                        </div>

                        <!-- Non-QC Resident Fields -->
                        <div id="non_qc_fields" style="display:none;">
                            <div class="form-row">
                                <div>
                                    <label class="form-label">City / Municipality *</label>
                                    <input type="text" class="form-control" id="custom_city" name="custom_city" placeholder="e.g. Pasig City">
                                </div>
                                <div>
                                    <label class="form-label">Barangay *</label>
                                    <input type="text" class="form-control" id="custom_barangay" name="custom_barangay" placeholder="e.g. San Antonio">
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Full Address / Street *</label>
                                <input type="text" class="form-control" id="custom_address" name="custom_address" placeholder="House No., Street, Subdivision">
                            </div>
                        </div>

                        <div style="margin-top:1.5rem;display:flex;justify-content:space-between;">
                            <button type="button" class="login-btn" id="backTo1" style="background:#64748b;"><i class="bi bi-arrow-left"></i> Back</button>
                            <button type="button" class="login-btn" id="toStep3">Next <i class="bi bi-arrow-right"></i></button>
                        </div>
                    </div>

                    <!-- Step 3: Account Security -->
                    <div class="signup-step" data-step="3" style="display:none;">
                        <h3><i class="bi bi-shield-lock"></i> 3. Account Security</h3>
                        <div class="form-group">
                            <label class="form-label">Username *</label>
                            <div style="display:flex;gap:0.5rem;align-items:center;">
                                <input type="text" class="form-control" id="username" name="username" placeholder="Choose a username" required style="flex:1;">
                                <span id="usernameStatus" style="min-width:130px;font-size:0.88rem;font-weight:600;"></span>
                            </div>
                            <small id="usernameHelp" class="text-muted"></small>
                        </div>
                        <div class="form-row">
                            <div>
                                <label class="form-label">Password *</label>
                                <input type="password" class="form-control" id="password" name="password" placeholder="Min 8 characters" required>
                                <small class="text-muted">Min 8 chars, no spaces</small>
                            </div>
                            <div>
                                <label class="form-label">Confirm Password *</label>
                                <input type="password" class="form-control" id="confirmpassword" name="confirmpassword" placeholder="Re-type password" required>
                                <small id="passwordMatch" class="text-muted">Passwords must match</small>
                            </div>
                        </div>
                        <div class="form-group" style="margin-top:0.5rem;">
                            <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer; font-size:0.92rem; color:#475569;">
                                <input type="checkbox" name="enable_totp_opt" id="enable_totp" style="accent-color:#2e856e;"> 
                                <span>Enable Authenticator App (TOTP) for extra security</span>
                            </label>
                        </div>
                        <div style="margin-top:1.5rem;display:flex;justify-content:space-between;">
                            <button type="button" class="login-btn" id="backTo2" style="background:#64748b;"><i class="bi bi-arrow-left"></i> Back</button>
                            <button type="button" class="login-btn" id="toStep4">Next <i class="bi bi-arrow-right"></i></button>
                        </div>
                    </div>

                    <!-- Step 4: Documents (Optional) -->
                    <div class="signup-step" data-step="4" style="display:none;">
                        <h3><i class="bi bi-file-earmark-person"></i> 4. Document Verification (Optional)</h3>
                        <div class="form-group">
                            <label class="form-label">Valid ID Type</label>
                            <select class="form-control" name="id_type">
                                <option value="">Select ID Type (Optional)</option>
                                <option>Philippine National ID</option>
                                <option>Driver's License</option>
                                <option>Passport</option>
                                <option>SSS / UMID</option>
                                <option>Voter's ID</option>
                                <option>Postal ID</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Front of ID (JPG, PNG)</label>
                            <input type="file" class="form-control" name="front_id" accept="image/jpeg,image/png">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Back of ID (Optional)</label>
                            <input type="file" class="form-control" name="back_id" accept="image/jpeg,image/png">
                        </div>
                        <div style="margin-top:1.5rem;display:flex;justify-content:space-between;">
                            <button type="button" class="login-btn" id="backTo3" style="background:#64748b;"><i class="bi bi-arrow-left"></i> Back</button>
                            <button type="button" class="login-btn" id="toStep5">Next <i class="bi bi-arrow-right"></i></button>
                        </div>
                    </div>

                    <!-- Step 5: Review -->
                    <div class="signup-step" data-step="5" style="display:none;">
                        <h3><i class="bi bi-clipboard-check"></i> 5. Review Your Information</h3>
                        <div id="reviewContent" style="background:#f8fafc;padding:1.25rem;border:1px solid #e2e8f0;border-radius:8px;max-height:320px;overflow-y:auto;font-size:0.92rem;"></div>
                        <div class="form-group" style="margin-top:1.25rem;">
                            <label style="display:flex; align-items:center; gap:0.6rem; cursor:pointer; font-size:0.92rem; color:#334155;">
                                <input type="checkbox" name="agree_terms" id="agree_terms" required style="width:16px; height:16px; accent-color:#2e856e;"> 
                                <span>I agree to the Terms and Conditions and Data Privacy Policy *</span>
                            </label>
                        </div>
                        <div style="margin-top:1.5rem;display:flex;justify-content:space-between;">
                            <button type="button" class="login-btn" id="backTo4" style="background:#64748b;"><i class="bi bi-arrow-left"></i> Back</button>
                            <button type="submit" class="login-btn"><i class="bi bi-check-circle"></i> Complete Registration</button>
                        </div>
                    </div>
                </form>

                <p style="margin-top:1.75rem;text-align:center;font-size:0.95rem;color:#64748b;border-top:1px solid #e2e8f0;padding-top:1.25rem;">
                    Already have an account? <a href="login.php" style="color:#2e856e;font-weight:700;text-decoration:none;">Log in</a>
                </p>
            </div>
        </div>
    </div>
</div>

<script>
    // Enforce Max Date for Date of Birth (Today's date)
    const todayStr = new Date().toISOString().split('T')[0];
    const dobInput = document.getElementById('dob');
    if (dobInput) {
        dobInput.max = todayStr;
        dobInput.min = "1900-01-01";
        dobInput.addEventListener('change', function() {
            if (this.value && this.value > todayStr) {
                this.setCustomValidity('Date of birth cannot be in the future.');
                this.reportValidity();
            } else {
                this.setCustomValidity('');
            }
        });
    }

    // QC Resident toggle logic
    const residentQcCheckbox = document.getElementById('resident_qc');
    const qcFields = document.getElementById('qc_fields');
    const nonQcFields = document.getElementById('non_qc_fields');
    const barangaySelect = document.getElementById('barangay');
    const addressInput = document.getElementById('address');
    const customCity = document.getElementById('custom_city');
    const customBarangay = document.getElementById('custom_barangay');
    const customAddress = document.getElementById('custom_address');

    function toggleQcFields() {
        if (!residentQcCheckbox) return;
        if (residentQcCheckbox.checked) {
            qcFields.style.display = 'block';
            nonQcFields.style.display = 'none';
            barangaySelect.required = true;
            addressInput.required = true;
            if (customCity) customCity.required = false;
            if (customBarangay) customBarangay.required = false;
            if (customAddress) customAddress.required = false;
        } else {
            qcFields.style.display = 'none';
            nonQcFields.style.display = 'block';
            barangaySelect.required = false;
            addressInput.required = false;
            if (customCity) customCity.required = true;
            if (customBarangay) customBarangay.required = true;
            if (customAddress) customAddress.required = true;
        }
    }
    if (residentQcCheckbox) {
        residentQcCheckbox.addEventListener('change', toggleQcFields);
    }

    // Username availability check (debounced)
    const usernameInput = document.getElementById('username');
    const usernameStatus = document.getElementById('usernameStatus');
    const usernameHelp = document.getElementById('usernameHelp');
    let usernameTimer = null;
    if (usernameInput) {
        usernameInput.addEventListener('input', function() {
            usernameStatus.textContent = '';
            usernameHelp.textContent = '';
            clearTimeout(usernameTimer);
            const value = this.value.trim();
            if (value.length === 0) return;
            usernameTimer = setTimeout(() => {
                fetch('check_username.php?username=' + encodeURIComponent(value))
                    .then(r => r.json())
                    .then(data => {
                        if (data.available) {
                            usernameStatus.style.color = '#16a34a';
                            usernameStatus.textContent = '✓ Available';
                            usernameHelp.textContent = '';
                        } else {
                            usernameStatus.style.color = '#dc2626';
                            usernameStatus.textContent = '✗ Not Available';
                            usernameHelp.textContent = '';
                        }
                    })
                    .catch(err => {
                        usernameStatus.style.color = '#64748b';
                        usernameStatus.textContent = 'Check failed';
                        usernameHelp.textContent = '';
                    });
            }, 500);
        });
    }

    // Password match validation
    const passInput = document.getElementById('password');
    const confirmInput = document.getElementById('confirmpassword');
    if (passInput && confirmInput) {
        passInput.addEventListener('input', () => {
            const pass = passInput.value;
            const match = document.getElementById('passwordMatch');
            if (pass.length < 8 || pass.includes(' ')) {
                match.textContent = 'Minimum 8 characters, no spaces';
                match.style.color = '#64748b';
            } else {
                checkPasswordMatch();
            }
        });

        confirmInput.addEventListener('input', () => {
            checkPasswordMatch();
        });
    }

    function checkPasswordMatch() {
        const pass = passInput.value;
        const confirm = confirmInput.value;
        const match = document.getElementById('passwordMatch');
        if (!match) return;
        if (!confirm) {
            match.textContent = 'Passwords must match';
            match.style.color = '#64748b';
        } else if (pass !== confirm) {
            match.textContent = 'Passwords do not match ✗';
            match.style.color = '#dc2626';
        } else {
            match.textContent = 'Passwords match ✓';
            match.style.color = '#16a34a';
        }
    }

    // Step navigation
    document.getElementById('toStep2').addEventListener('click', () => { if (validateStep(1)) showStep(2); });
    document.getElementById('backTo1').addEventListener('click', () => showStep(1));
    document.getElementById('toStep3').addEventListener('click', () => { if (validateStep(2)) showStep(3); });
    document.getElementById('backTo2').addEventListener('click', () => showStep(2));
    document.getElementById('toStep4').addEventListener('click', () => { if (validateStep(3)) showStep(4); });
    document.getElementById('backTo3').addEventListener('click', () => showStep(3));
    document.getElementById('toStep5').addEventListener('click', () => { if (validateStep(4)) { generateReview(); showStep(5); } });
    document.getElementById('backTo4').addEventListener('click', () => showStep(4));
    
    document.getElementById('signupForm').addEventListener('submit', function(e) {
        if (!validateStep(5)) {
            e.preventDefault();
            return false;
        }
    });

    function showStep(step) {
        document.querySelectorAll('.signup-step').forEach(el => el.style.display = 'none');
        const target = document.querySelector('[data-step="' + step + '"]');
        if (target) target.style.display = 'block';
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function validateStep(step) {
        const stepElement = document.querySelector('[data-step="' + step + '"]');
        if (!stepElement) return true;

        if (step === 1 && dobInput) {
            if (dobInput.value && dobInput.value > todayStr) {
                dobInput.setCustomValidity('Date of birth cannot be in the future.');
                dobInput.reportValidity();
                dobInput.focus();
                return false;
            } else {
                dobInput.setCustomValidity('');
            }
        }

        const requiredFields = stepElement.querySelectorAll('input[required], select[required]');
        for (const field of requiredFields) {
            // If field is inside a hidden container (e.g. non_qc_fields when qc_fields is active), ignore
            if (field.offsetParent === null) continue;
            if (!field.checkValidity()) {
                field.reportValidity();
                field.focus();
                return false;
            }
        }

        if (step === 3 && passInput && confirmInput) {
            if (passInput.value.length < 8 || passInput.value.includes(' ')) {
                alert('Password must be at least 8 characters with no spaces.');
                passInput.focus();
                return false;
            }
            if (passInput.value !== confirmInput.value) {
                alert('Passwords do not match.');
                confirmInput.focus();
                return false;
            }
        }

        return true;
    }

    function generateReview() {
        const isQC = document.getElementById('resident_qc').checked;
        const brgy = isQC ? document.querySelector('[name="barangay"]').value : (document.getElementById('custom_barangay').value || '—');
        const addr = isQC ? document.querySelector('[name="address"]').value : (document.getElementById('custom_address').value || '—');
        const city = isQC ? 'Quezon City' : (document.getElementById('custom_city').value || '—');

        const review = {
            'Full Name': [
                document.querySelector('[name="first_name"]').value,
                document.querySelector('[name="middle_name"]').value,
                document.querySelector('[name="last_name"]').value
            ].filter(Boolean).join(' '),
            'Sex': document.querySelector('[name="sex"]').value,
            'Date of Birth': document.querySelector('[name="dob"]').value,
            'Email': document.querySelector('[name="emailadd"]').value,
            'Phone': document.querySelector('[name="phone"]').value,
            'Resident of QC': isQC ? 'Yes' : 'No',
            'City / Municipality': city,
            'Barangay': brgy,
            'Address': addr,
            'Username': document.querySelector('[name="username"]').value,
            'Enable Authenticator (TOTP)': document.getElementById('enable_totp').checked ? 'Yes' : 'No'
        };

        const html = Object.entries(review).map(([k, v]) => `
            <div style="display:flex; justify-content:space-between; padding:0.5rem 0; border-bottom:1px solid #e2e8f0;">
                <span style="color:#64748b; font-weight:500;">${k}</span>
                <span style="font-weight:600; color:#1e293b; text-align:right;">${v}</span>
            </div>
        `).join('');
        document.getElementById('reviewContent').innerHTML = html;
    }
</script>
</body>
</html>
