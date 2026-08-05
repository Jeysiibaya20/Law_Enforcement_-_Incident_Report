<?php
/**
 * Multi-Step Sign Up Form
 * 1. Personal Info
 * 2. Contact Info + Barangay/Street Dropdowns for QC
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

// Quezon City Barangays and Streets (comprehensive list)
$qc_barangays = [
    "Apolonio Samson" => ["Aguinaldo Drive", "Kamagayan Street", "Scout Tuason"],
    "Bagbag" => ["Bagbag Road", "Wilson Street", "Camarin Street"],
    "Barangka" => ["Barangka Drive", "Congressional Avenue", "Don Carlos Lane"],
    "Batasan Hills" => ["Batasan Road", "Quezon Avenue", "North Avenue"],
    "Bayan" => ["Epifanio delos Santos Avenue", "Timog Avenue", "Scout Tuason"],
    "Blumentritt" => ["Blumentritt Street", "Trento Street", "Espanya Boulevard"],
    "Culiat" => ["Culiat Road", "Congressional Avenue", "Tandang Sora Avenue"],
    "Diliman" => ["Quezon Avenue", "Mother Ignacia Avenue", "Visayas Avenue"],
    "Fairview" => ["Commonwealth Avenue", "Luzon Avenue", "Visayas Avenue"],
    "Greater Lagro" => ["Lagro Street", "Libis Road", "Valley Drive"],
    "Kamuning" => ["Kamuning Road", "Scout Tuason", "Timog Avenue"],
    "Laging Handa" => ["Scout Tuason", "Timog Avenue", "Araneta Avenue"],
    "Mariana" => ["Mariana Drive", "Scout Tuason", "Congressional Avenue"],
    "Masambong" => ["Masambong Street", "Congressional Avenue", "Kamuning Road"],
    "Matandang Balara" => ["Balara Avenue", "Mother Ignacia Avenue", "Ateneo Avenue"],
    "Milagrosa" => ["North Avenue", "Quezon Avenue", "Scout Tuason"],
    "Nayong Kanluran" => ["Maharlika Drive", "North Avenue", "Bulacan Avenue"],
    "Novaliches" => ["Regalado Avenue", "Quirino Avenue", "North Avenue"],
    "Pansol" => ["Canumay Avenue", "Luzon Avenue", "Zenaida Street"],
    "Payatas" => ["Payatas Road", "Luzon Avenue", "North Avenue"],
    "Pinaglabanan" => ["Pinaglabanan Street", "Scout Tuason", "Highway 54"],
    "Pinagkaisahan" => ["Quezon Avenue", "Friar Road", "North Avenue"],
    "Quezon Hill" => ["Quezon Avenue", "Friar Road", "Araneta Avenue"],
    "Sta. Lucia" => ["Sta. Lucia Road", "Commonwealth Avenue", "Holy Spirit Drive"],
    "Tatalon" => ["Tatalon Street", "Edsa", "Visayas Avenue"],
    "UP Campus" => ["Quezon Avenue", "Mother Ignacia", "Acacia Road"],
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
        $barangay = trim($_POST['barangay'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $enable_totp = isset($_POST['enable_totp_opt']) ? 1 : 0;
        $agree_terms = isset($_POST['agree_terms']) ? 1 : 0;
        $resident_qc = isset($_POST['resident_qc']) ? 1 : 0;

        // Validation
        if (empty($first_name) || empty($last_name) || empty($dob) || empty($email) || empty($username) || empty($password)) {
            throw new Exception('Please fill in all required fields');
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

        // Account created successfully; no 2FA verification required for local setup.
        echo "<script>alert('Account created successfully. You may now log in.'); window.location.href='login.php';</script>";
        exit();

    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Sign Up - Alertara</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
.login-container {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    background: var(--primary-bg);
    overflow: hidden;
    padding: 2rem;
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
        rgba(76, 138, 137, 0.8) 0%, 
        rgba(58, 80, 107, 0.7) 50%, 
        rgba(28, 37, 65, 0.8) 100%);
    z-index: 1;
}

.login-background::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('../assets/css/QC.jpeg') center/90% no-repeat;
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
    max-width: 550px;
    padding: 2rem;
    display: flex;
    justify-content: center;
    align-items: center;
}

.login-card {
    background: rgba(250, 250, 250, 0.95);
    border-radius: var(--border-radius-lg);
    box-shadow: var(--shadow-xl);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(0, 0, 0, 0.8);
    overflow: hidden;
    animation: slideInUp 0.8s ease-out;
    width: 100%;
    max-width: 800px;
}

.login-header {
    text-align: center;
    padding: 3rem 2rem 2rem;
    background: linear-gradient(135deg, 
        rgba(76, 138, 137, 0.9) 0%, 
        rgba(58, 80, 107, 0.8) 50%, 
        rgba(28, 37, 65, 0.9) 100%);
    color: var(--text-white);
    position: relative;
}

.login-header::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 20px;
    background: linear-gradient(to bottom, transparent, rgba(255, 255, 255, 0.1));
}

.login-logo {
    width: 80px;
    height: 80px;
    background: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1.5rem;
    font-size: 2.5rem;
    color: var(--text-white);
    animation: float;
    box-shadow: var(--shadow-lg);
    overflow: hidden;
}

.logo-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 50%;
}

.login-title {
    font-family: 'Libre Baskerville', serif;
    font-size: 2.5rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
    background: linear-gradient(135deg, #FFFFFF 0%, #FEFAF6 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.login-subtitle {
    font-size: 1.1rem;
    font-weight: 500;
    margin-bottom: 0.5rem;
    opacity: 0.9;
}

.login-form-container {
    padding: 2.5rem 2rem;
}

.login-form {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.signup-step { margin-bottom: 0; }
.signup-step h3 { margin-bottom: 1.5rem; color: #1c2541; font-size: 1.3rem; font-weight: 700; }
.form-row { display: flex; gap: 0.75rem; }
.form-row > div { flex: 1; }
.form-group { margin-bottom: 1rem; }

.form-label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 0.5rem;
    font-size: 0.95rem;
}

.form-control {
    width: 100%;
    padding: 1rem 1.25rem;
    border: 4px solid #e5e5e5;
    border-radius: var(--border-radius-sm);
    font-size: 1rem;
    background: rgba(255, 255, 255, 0.9);
    transition: var(--transition);
    font-family: 'Quicksand', sans-serif;
}

.form-control:focus {
    outline: none;
    border-color: var(--main-color);
    box-shadow: 0 0 0 3px rgba(139, 111, 71, 0.1);
    background: var(--text-white);
}

.form-control::placeholder {
    color: var(--text-light);
    font-style: italic;
}

.login-btn {
    background: #4c8a89;
    color: var(--text-white);
    border: none;
    padding: 1rem 2rem;
    border-radius: var(--border-radius-sm);
    font-size: 1rem;
    font-weight: 600;
    font-family: 'Quicksand', sans-serif;
    cursor: pointer;
    transition: var(--transition);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    box-shadow: var(--shadow-md);
}

.login-btn:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
    background: #3a7270;
}

.login-btn:active {
    transform: translateY(0);
}

.login-btn[disabled] { 
    background: #ccc; 
    cursor: not-allowed; 
    opacity: 0.6;
}

.text-muted { 
    color: #6c757d; 
    font-size: 0.9rem; 
}

.alert { 
    padding: 1rem; 
    margin-bottom: 1.5rem; 
    border-radius: var(--border-radius-sm); 
    display: flex;
    align-items: center;
    gap: 0.5rem;
    animation: fadeInDown 0.5s ease-out;
}

.alert-danger { 
    background: var(--danger-color);
    color: var(--text-white);
    border: 1px solid rgba(192, 57, 43, 0.3);
}

.alert-success { 
    background: #d4edda; 
    color: #155724; 
    border: 1px solid #c3e6cb; 
}

@keyframes slideInUp {
    from {
        transform: translateY(30px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

@keyframes fadeInDown {
    from {
        transform: translateY(-10px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

@keyframes float {
    0%, 100% {
        transform: translateY(0px);
    }
    50% {
        transform: translateY(-10px);
    }
}

@media (max-width: 768px) {
    .login-container {
        padding: 1rem;
        width: 100%;
    }
    
    .login-content {
        padding: 1rem;
        width: 100%;
        max-width: 100%;
    }
    
    .login-card {
        width: 100%;
        max-width: 100%;
    }
    
    .login-header {
        padding: 2rem 1.5rem 1.5rem;
    }
    
    .login-form-container {
        padding: 2rem 1.5rem;
    }
    
    .login-title {
        font-size: 2rem;
    }
    
    .login-logo {
        width: 60px;
        height: 60px;
        font-size: 2rem;
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
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
                <?php endif; ?>

                <form method="POST" id="signupForm" enctype="multipart/form-data" novalidate>
                    <!-- Step 1: Personal Information -->
                    <div class="signup-step" data-step="1">
                        <h3>1. Personal Information</h3>
                        <div class="form-row">
                            <div><label class="form-label">First Name *</label><input type="text" class="form-control" name="first_name" required></div>
                            <div><label class="form-label">Middle Name</label><input type="text" class="form-control" name="middle_name"></div>
                        </div>
                        <div class="form-group"><label class="form-label">Last Name *</label><input type="text" class="form-control" name="last_name" required></div>
                        <div class="form-row">
                            <div><label class="form-label">Sex *</label><select class="form-control" name="sex" required><option>Select Sex</option><option>Female</option><option>Male</option><option>Other</option></select></div>
                            <div><label class="form-label">Date of Birth *</label><input type="date" class="form-control" name="dob" required></div>
                        </div>
                        <div style="margin-top:1rem;display:flex;justify-content:flex-end;"><button type="button" class="login-btn" id="toStep2">Next →</button></div>
                    </div>

                    <!-- Step 2: Contact Info & Barangay -->
                    <div class="signup-step" data-step="2" style="display:none;">
                        <h3>2. Contact Information</h3>
                        <div class="form-group"><label class="form-label">Email Address *</label><input type="email" class="form-control" name="emailadd" required></div>
                        <div class="form-group"><label class="form-label">Phone Number *</label><input type="tel" class="form-control" name="phone" pattern="\d{10,14}" placeholder="09XXXXXXXXX" required></div>
                        <div class="form-group"><label><input type="checkbox" name="resident_qc" value="1"> Are you a resident of Quezon City?</label></div>
                        <div class="form-group"><label class="form-label">Barangay *</label><select class="form-control" id="barangay" name="barangay" required></select></div>
                        <div class="form-group"><label class="form-label">Full Address *</label><input type="text" class="form-control" id="address" name="address" placeholder="Enter your full address (Street, Purok, House Number)" required></div>
                        <div style="margin-top:1rem;display:flex;justify-content:space-between;"><button type="button" class="login-btn" id="backTo1" style="background:#6c757d;">← Back</button><button type="button" class="login-btn" id="toStep3">Next →</button></div>
                    </div>

                    <!-- Step 3: Account Security -->
                    <div class="signup-step" data-step="3" style="display:none;">
                        <h3>3. Account Security</h3>
                        <div class="form-group">
                            <label class="form-label">Username *</label>
                            <div style="display:flex;gap:0.5rem;align-items:center;"><input type="text" class="form-control" id="username" name="username" required style="flex:1;"><span id="usernameStatus" style="min-width:150px;color:#6c757d;"></span></div>
                            <small id="usernameHelp" class="text-muted"></small>
                        </div>
                        <div class="form-row">
                            <div><label class="form-label">Password *</label><input type="password" class="form-control" id="password" name="password" required><small class="text-muted">Min 8 chars, no spaces</small></div>
                            <div><label class="form-label">Confirm Password *</label><input type="password" class="form-control" id="confirmpassword" name="confirmpassword" required><small id="passwordMatch" class="text-muted">Passwords must match</small></div>
                        </div>
                        <div class="form-group"><label><input type="checkbox" name="enable_totp_opt" id="enable_totp"> Enable Authenticator App (TOTP) for extra security</label></div>
                        <div style="margin-top:1rem;display:flex;justify-content:space-between;"><button type="button" class="login-btn" id="backTo2" style="background:#6c757d;">← Back</button><button type="button" class="login-btn" id="toStep4">Next →</button></div>
                    </div>

                    <!-- Step 4: Documents (Optional) -->
                    <div class="signup-step" data-step="4" style="display:none;">
                        <h3>4. Document Verification (Optional)</h3>
                        <div class="form-group"><label class="form-label">Valid ID Type</label><select class="form-control" name="id_type"><option>Select ID Type</option><option>Philippine National ID</option><option>Driver's License</option><option>Passport</option></select></div>
                        <div class="form-group"><label class="form-label">Front of ID (JPG, PNG)</label><input type="file" class="form-control" name="front_id" accept="image/jpeg,image/png"></div>
                        <div class="form-group"><label class="form-label">Back of ID (optional)</label><input type="file" class="form-control" name="back_id" accept="image/jpeg,image/png"></div>
                        <div style="margin-top:1rem;display:flex;justify-content:space-between;"><button type="button" class="login-btn" id="backTo3" style="background:#6c757d;">← Back</button><button type="button" class="login-btn" id="toStep5">Next →</button></div>
                    </div>

                    <!-- Step 5: Review -->
                    <div class="signup-step" data-step="5" style="display:none;">
                        <h3>5. Review Your Information</h3>
                        <div id="reviewContent" style="background:#f8f9fa;padding:1rem;border:1px solid #e9ecef;border-radius:4px;max-height:300px;overflow-y:auto;"></div>
                        <div class="form-group" style="margin-top:1rem;"><label><input type="checkbox" name="agree_terms" required> I agree to the Terms and Conditions and Data Privacy Policy</label></div>
                        <div style="margin-top:1rem;display:flex;justify-content:space-between;"><button type="button" class="login-btn" id="backTo4" style="background:#6c757d;">← Back</button><button type="submit" class="login-btn">Complete Registration</button></div>
                    </div>
                </form>

                <p style="margin-top:1rem;text-align:center;"><a href="login.php">Already have an account? Log in</a></p>
            </div>
        </div>
    </div>
</div>

<script>
    // QC Barangays and Streets data
    const qcData = <?php echo json_encode($qc_barangays); ?>;

    // Populate barangay dropdown
    const barangaySelect = document.getElementById('barangay');
    Object.keys(qcData).sort().forEach(brgy => {
        const opt = document.createElement('option');
        opt.value = brgy;
        opt.textContent = brgy;
        barangaySelect.appendChild(opt);
    });

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
                            usernameStatus.style.color = '#c0392b';
                            usernameStatus.textContent = '✗ Not Available';
                            usernameHelp.textContent = '';
                        }
                    })
                    .catch(err => {
                        usernameStatus.style.color = '#6c757d';
                        usernameStatus.textContent = 'Check failed';
                        usernameHelp.textContent = '';
                    });
            }, 600);
        });
    }

    // Password match validation
    document.getElementById('password').addEventListener('input', () => {
        const pass = document.getElementById('password').value;
        const match = document.getElementById('passwordMatch');
        if (pass.length < 8 || pass.includes(' ')) {
            match.textContent = 'Minimum 8 characters, no spaces';
            match.style.color = '#6c757d';
        } else {
            checkPasswordMatch();
        }
    });

    document.getElementById('confirmpassword').addEventListener('input', () => {
        checkPasswordMatch();
    });

    function checkPasswordMatch() {
        const pass = document.getElementById('password').value;
        const confirm = document.getElementById('confirmpassword').value;
        const match = document.getElementById('passwordMatch');
        if (pass !== confirm) {
            match.textContent = 'Passwords do not match';
            match.style.color = '#c0392b';
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
        document.querySelector('[data-step="' + step + '"]').style.display = 'block';
        window.scrollTo(0, 0);
    }

    function validateStep(step) {
        const stepElement = document.querySelector('[data-step="' + step + '"]');
        if (!stepElement) return true;

        const requiredFields = stepElement.querySelectorAll('input[required], select[required]');
        for (const field of requiredFields) {
            if (!field.checkValidity()) {
                field.reportValidity();
                field.focus();
                return false;
            }
        }

        return true;
    }

    function generateReview() {
        const review = {
            'First Name': document.querySelector('[name="first_name"]').value,
            'Middle Name': document.querySelector('[name="middle_name"]').value || '—',
            'Last Name': document.querySelector('[name="last_name"]').value,
            'Sex': document.querySelector('[name="sex"]').value,
            'Date of Birth': document.querySelector('[name="dob"]').value,
            'Email': document.querySelector('[name="emailadd"]').value,
            'Phone': document.querySelector('[name="phone"]').value,
            'Barangay': document.querySelector('[name="barangay"]').value,
            'Street': document.querySelector('[name="address"]').value,
            'Resident of QC': document.querySelector('[name="resident_qc"]').checked ? 'Yes' : 'No',
            'Username': document.querySelector('[name="username"]').value,
            'Enable TOTP': document.querySelector('[name="enable_totp_opt"]').checked ? 'Yes' : 'No',
        };
        const html = Object.entries(review).map(([k, v]) => '<div style="padding:0.5rem;border-bottom:1px solid #e9ecef;"><strong>' + k + ':</strong> ' + v + '</div>').join('');
        document.getElementById('reviewContent').innerHTML = html;
    }
</script>
</body>
</html>
