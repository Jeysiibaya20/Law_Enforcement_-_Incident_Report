<?php
require_once '../config/db_connect.php';

// Process sign-up form and create a verified local account for development.
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Personal & contact fields (UI updated to match multi-step sign up)
    $first_name = trim($_POST['first_name'] ?? '');
    $middle_name = trim($_POST['middle_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $fullname = trim(implode(' ', array_filter([$first_name, $middle_name, $last_name])));
    $sex = trim($_POST['sex'] ?? '');
    $dob = trim($_POST['dob'] ?? '');
    $email    = trim($_POST['emailadd'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $address  = trim($_POST['address'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $agree_terms = isset($_POST['agree_terms']) ? 1 : 0;

    if (empty($first_name) || empty($last_name) || empty($dob) || empty($email) || empty($username) || empty($password)) {
        echo "<script>alert('Please fill in all fields'); window.location.href='signup_multistep.php';</script>";
        exit();
    }

    // Password confirm
    $confirm_password = $_POST['confirmpassword'] ?? '';
    if ($password !== $confirm_password) {
        echo "<script>alert('Passwords do not match'); window.location.href='signup_multistep.php';</script>";
        exit();
    }

    // Basic phone validation (digits only, length 10-14)
    if (!empty($phone) && !preg_match('/^\+?\d{10,14}$/', $phone)) {
        echo "<script>alert('Please enter a valid phone number'); window.location.href='signup_multistep.php';</script>";
        exit();
    }

    // ✅ Check if user agreed to terms and conditions
    if (!$agree_terms) {
        echo "<script>alert('You must agree to the Terms and Conditions and Data Privacy Policy to sign up.'); window.location.href='signup_multistep.php';</script>";
        exit();
    }

    // 🔎 Check if username exists (using PDO)
    try {
        $checkUserSql = "SELECT user_id FROM signup WHERE username = ?";
        $checkUserStmt = $pdo->prepare($checkUserSql);
        $checkUserStmt->execute([$username]);
        $checkUserRow = $checkUserStmt->fetch(PDO::FETCH_ASSOC);

        if ($checkUserRow) {
            echo "<script>alert('Username already exists'); window.location.href='signup_multistep.php';</script>";
            exit();
        }
    } catch (PDOException $e) {
        error_log("signup_multistep.php - Error checking username: " . $e->getMessage());
        echo "<script>alert('An error occurred. Please try again later.'); window.location.href='signup.php';</script>";
        exit();
    }

    // 🔎 Check if email exists (using PDO)
    try {
        $checkEmailSql = "SELECT user_id FROM signup WHERE emailadd = ?";
        $checkEmailStmt = $pdo->prepare($checkEmailSql);
        $checkEmailStmt->execute([$email]);
        $checkEmailRow = $checkEmailStmt->fetch(PDO::FETCH_ASSOC);

        if ($checkEmailRow) {
            echo "<script>alert('Email address already exists'); window.location.href='signup_multistep.php';</script>";
            exit();
        }
    } catch (PDOException $e) {
        error_log("signup_multistep.php - Error checking email: " . $e->getMessage());
        echo "<script>alert('An error occurred. Please try again later.'); window.location.href='signup_multistep.php';</script>";
        exit();
    }

    // ✅ Hash password and insert new account with email_verified = 0
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $verification_token = bin2hex(random_bytes(32));
    $token_expires = date("Y-m-d H:i:s", strtotime("+24 hours"));
    $terms_accepted_date = date("Y-m-d H:i:s");
    
    $sql = "INSERT INTO signup (fullname, emailadd, username, password, email_verified, verification_token, token_expires, terms_accepted, terms_accepted_date) VALUES (?, ?, ?, ?, 1, ?, ?, 1, ?)";
    try {
        $stmt = $pdo->prepare($sql);
        $res = $stmt->execute([$fullname, $email, $username, $hashed_password, $verification_token, $token_expires, $terms_accepted_date]);
    } catch (PDOException $e) {
        error_log("signup_multistep.php - Insert Error: " . $e->getMessage());
        echo "<script>alert('Error creating account. Please try again later.'); window.location.href='signup_multistep.php';</script>";
        exit();
    }

    if ($res) {
        // Get new user id
        $user_id = $pdo->lastInsertId();

        // Handle file uploads (document verification) if provided
        $front_path = null;
        $back_path = null;
        if (!empty($_FILES['front_id']) && $_FILES['front_id']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['image/jpeg','image/png'];
            if (!in_array($_FILES['front_id']['type'], $allowed)) {
                error_log('Invalid front id mime: ' . $_FILES['front_id']['type']);
            } else {
                $targetDir = __DIR__ . '/../uploads/ids/';
                if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);
                $ext = pathinfo($_FILES['front_id']['name'], PATHINFO_EXTENSION);
                $fname = uniqid('front_') . '.' . $ext;
                $dest = $targetDir . $fname;
                if (move_uploaded_file($_FILES['front_id']['tmp_name'], $dest)) {
                    $front_path = 'uploads/ids/' . $fname;
                }
            }
        }
        if (!empty($_FILES['back_id']) && $_FILES['back_id']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['image/jpeg','image/png'];
            if (!in_array($_FILES['back_id']['type'], $allowed)) {
                error_log('Invalid back id mime: ' . $_FILES['back_id']['type']);
            } else {
                $targetDir = __DIR__ . '/../uploads/ids/';
                if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);
                $ext = pathinfo($_FILES['back_id']['name'], PATHINFO_EXTENSION);
                $fname = uniqid('back_') . '.' . $ext;
                $dest = $targetDir . $fname;
                if (move_uploaded_file($_FILES['back_id']['tmp_name'], $dest)) {
                    $back_path = 'uploads/ids/' . $fname;
                }
            }
        }

        // Persist additional profile fields and uploaded ID paths into the signup table.    

        // Persist additional profile fields and uploaded ID paths into the signup table.
        // If columns don't exist, attempt to add them (best-effort).
        try {
            $colsToCheck = [
                'phone' => "ALTER TABLE signup ADD COLUMN phone VARCHAR(30) DEFAULT NULL",
                'sex' => "ALTER TABLE signup ADD COLUMN sex VARCHAR(20) DEFAULT NULL",
                'dob' => "ALTER TABLE signup ADD COLUMN dob DATE DEFAULT NULL",
                'address' => "ALTER TABLE signup ADD COLUMN address VARCHAR(255) DEFAULT NULL",
                'resident_qc' => "ALTER TABLE signup ADD COLUMN resident_qc TINYINT(1) DEFAULT 0",
                'id_type' => "ALTER TABLE signup ADD COLUMN id_type VARCHAR(100) DEFAULT NULL",
                'uploaded_front' => "ALTER TABLE signup ADD COLUMN uploaded_front VARCHAR(255) DEFAULT NULL",
                'uploaded_back' => "ALTER TABLE signup ADD COLUMN uploaded_back VARCHAR(255) DEFAULT NULL",
                'role' => "ALTER TABLE signup ADD COLUMN role VARCHAR(50) DEFAULT 'User'"
            ];

            foreach ($colsToCheck as $col => $alterSql) {
                // SHOW COLUMNS does not accept PDO parameter binding in some MariaDB versions; quote the value instead
                $qcol = $pdo->quote($col);
                $check = $pdo->query("SHOW COLUMNS FROM signup LIKE " . $qcol);
                $has = false;
                if ($check !== false) {
                    $row = $check->fetch(PDO::FETCH_ASSOC);
                    if ($row) $has = true;
                }
                if (!$has) {
                    // try to add column
                    try { $pdo->exec($alterSql); } catch (Exception $e) { error_log('Could not add column ' . $col . ': ' . $e->getMessage()); }
                }
            }

            // Now update the record with available POST values and uploaded paths
            $updateSql = "UPDATE signup SET phone = ?, sex = ?, dob = ?, address = ?, resident_qc = ?, id_type = ?, uploaded_front = ?, uploaded_back = ?, role = ? WHERE user_id = ?";
            $updateStmt = $pdo->prepare($updateSql);
            $roleVal = $_POST['role'] ?? 'User';
            $dobVal = !empty($dob) ? $dob : null;
            $updateStmt->execute([
                $phone ?? null,
                $sex ?? null,
                $dobVal,
                $address ?? null,
                isset($_POST['resident_qc']) ? (int)$_POST['resident_qc'] : 0,
                $_POST['id_type'] ?? null,
                $front_path ?? null,
                $back_path ?? null,
                $roleVal,
                $user_id
            ]);
        } catch (Exception $e) {
            error_log('Failed to persist profile fields: ' . $e->getMessage());
        }

        echo "<script>alert('Account created successfully. You may now login.'); window.location.href='login.php';</script>";
        exit();
    } else {
        $err = $stmt->errorInfo();
        $errMsg = isset($err[2]) ? $err[2] : 'Unknown error';
        echo "<script>alert('Error creating account: " . htmlspecialchars($errMsg) . "'); window.location.href='signup.php';</script>";
        exit();
    }
}


// PDO connection will be closed automatically when the script ends.

?>

<!-- Login Page - -->
<div class="login-container">
    <div class="login-background">
        <div class="login-overlay"></div>
    </div>
    
    <div class="login-content">
        <div class="login-card">
            <!-- Logo Section -->
            <div class="login-header">
                <div class="login-logo">
                <img src="../assets/css/tara.png" alt="Alertara Logo" class="logo-image">
                </div>
                <h1 class="login-title">Alertara PH</h1>
                <p class="login-subtitle">Law Enforcement and Incident</p>

            </div>
            
            <!-- Login Form -->
            <div class="login-form-container">
                
                <form method="POST" id="signupForm" class="login-form" enctype="multipart/form-data" autocomplete="off" novalidate>
                    <!-- Multi-step Sign Up -->
                    <input type="hidden" name="enable_totp" id="enable_totp_hidden" value="0">
                    <!-- Step 1: Personal Information -->
                    <div class="signup-step" data-step="1">
                    <h3>1. Personal Information</h3>
                    <div class="form-row" style="display:flex; gap:0.75rem;">
                        <div class="form-group" style="flex:1;">
                            <label for="first_name" class="form-label">First Name *</label>
                            <input type="text" class="form-control" id="first_name" name="first_name" placeholder="First name" required autocomplete="given-name" autocapitalize="words">
                        </div>
                        <div class="form-group" style="flex:1;">
                            <label for="middle_name" class="form-label">Middle Name</label>
                            <input type="text" class="form-control" id="middle_name" name="middle_name" placeholder="Optional" autocomplete="additional-name" autocapitalize="words">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="last_name" class="form-label">Last Name *</label>
                        <input type="text" class="form-control" id="last_name" name="last_name" placeholder="Last name" required autocomplete="family-name" autocapitalize="words">
                    </div>
                    <div class="form-row" style="display:flex; gap:0.75rem;">
                        <div class="form-group" style="flex:1;">
                            <label for="sex" class="form-label">Sex *</label>
                            <select name="sex" id="sex" class="form-control" required>
                                <option value="">Select Sex</option>
                                <option value="Female">Female</option>
                                <option value="Male">Male</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="form-group" style="flex:1;">
                            <label for="dob" class="form-label">Date of Birth *</label>
                            <input type="date" class="form-control" id="dob" name="dob" required autocomplete="bday">
                        </div>
                    </div>

                    <!-- Contact Information -->
                    <div class="form-group">
                        <label for="emailadd" class="form-label">Email Address *</label>
                        <div style="display:flex;gap:0.5rem;align-items:center;"><input type="email" class="form-control" id="emailadd" name="emailadd" placeholder="Enter your email address" required style="flex:1;" autocomplete="email"><span id="emailStatus" style="min-width:150px;color:#6c757d;"></span></div>
                    </div>
                    <div style="margin-top:1rem;display:flex;justify-content:flex-end;gap:0.5rem;">
                        <button type="button" class="login-btn" id="toStep2">Next →</button>
                    </div>
                    </div>

                    <!-- Step 2: Contact Info -->
                    <div class="signup-step" data-step="2" style="display:none;">
                    <h3>2. Contact Information</h3>
                    <div class="form-group">
                        <label for="emailadd" class="form-label">Email Address *</label>
                        <div style="display:flex;gap:0.5rem;align-items:center;"><input type="email" class="form-control" id="emailadd" name="emailadd" placeholder="Enter your email address" required style="flex:1;" autocomplete="email"><span id="emailStatus" style="min-width:150px;color:#6c757d;"></span></div>
                    </div>
                    <div class="form-group">
                        <label for="phone" class="form-label">Phone Number *</label>
                        <input type="tel" class="form-control" id="phone" name="phone" placeholder="09XXXXXXXXX" pattern="\d{10,14}" required autocomplete="tel">
                        <small class="form-text text-muted">We'll send a verification code to this number.</small>
                    </div>
                    <div class="form-group">
                        <label for="resident_qc" class="form-label">Are you a resident of Quezon City?</label>
                        <input type="hidden" name="resident_qc" value="0">
                        <label style="display:inline-block;margin-left:8px;"><input type="checkbox" id="resident_qc" name="resident_qc" value="1"> Yes, I am a resident of Quezon City</label>
                    </div>

                    <div class="form-group">
                        <label for="barangay">Barangay *</label>
                        <select id="barangay" name="barangay" class="form-control" required autocomplete="address-level3">
                            <option value="">Select Barangay</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="address">House Number/Street/Purok</label>
                        <input type="text" class="form-control" id="address" name="address" placeholder="Enter your full address (Street, Purok, House Number)" required>
                    </div>

                    <div style="margin-top:1rem;display:flex;justify-content:space-between;gap:0.5rem;">
                        <button type="button" class="login-btn" id="backTo1" style="background:#6c757d;">← Back</button>
                        <button type="button" class="login-btn" id="toStep3">Next →</button>
                    </div>
                    </div>
                        <label for="phone" class="form-label">Phone Number *</label>
                        <input type="tel" class="form-control" id="phone" name="phone" placeholder="09XXXXXXXXX" pattern="\d{10,14}" required>
                        <small class="form-text text-muted">We'll send a verification code to this number.</small>
                    </div>

                    <!-- Account Security -->
                    <div class="form-group">
                        <label for="username" class="form-label">Username *</label>
                        <div style="display:flex; align-items:center; gap:0.5rem;">
                            <input type="text" class="form-control" id="username" name="username" placeholder="Enter your username" required style="flex:1;" autocomplete="username" autocapitalize="off">
                            <span id="usernameStatus" style="min-width:160px;color:#6c757d;font-size:0.95rem;"></span>
                        </div>
                        <small id="usernameHelp" class="form-text"></small>
                    </div>
                    <div class="form-row" style="display:flex; gap:0.75rem;">
                        <div class="form-group" style="flex:1;">
                            <label for="password" class="form-label">Password *</label>
                            <input type="password" class="form-control" id="password" name="password" placeholder="Enter your password" required autocomplete="new-password">
                            <div id="passwordStrength" style="height:8px;background:#e9ecef;border-radius:4px;margin-top:6px;overflow:hidden;">
                                <div id="passwordStrengthBar" style="height:100%;width:0%;background:#e74c3c;transition:width .2s linear;"></div>
                            </div>
                            <small id="passwordHelp" class="form-text text-muted">Minimum 8 characters, no spaces</small>
                        </div>
                        <div class="form-group" style="flex:1;">
                            <label for="confirmpassword" class="form-label">Confirm Password *</label>
                            <input type="password" class="form-control" id="confirmpassword" name="confirmpassword" placeholder="Confirm your password" required autocomplete="new-password">
                            <small id="passwordMatch" class="form-text" style="color:#6c757d;">Passwords must match</small>
                        </div>
                    </div>

                    <div style="margin-top:0.5rem;">
                        <label><input type="checkbox" id="enable_totp" name="enable_totp_opt"> Enable Authenticator App (TOTP) after signup</label>
                    </div>

                    <!-- Document Upload (hidden until Next) -->
                    <div id="documentSection" style="display:none; margin-top:1rem; padding:1rem; border:1px dashed #dcdcdc; border-radius:6px; background:#f8f9fa;">
                        <h4>Document Verification</h4>
                        <div class="form-group">
                            <label for="id_type">Valid ID Type *</label>
                            <select id="id_type" name="id_type" class="form-control">
                                <option value="">Select ID Type</option>
                                <option>Philippine National ID (PhilID / PhilSys)</option>
                                <option>Driver's License</option>
                                <option>Passport</option>
                                <option>SSS ID</option>
                                <option>GSIS ID</option>
                                <option>PRC ID</option>
                                <option>TIN ID</option>
                                <option>Voter's ID</option>
                                <option>Postal ID</option>
                                <option>School ID</option>
                                <option>Company ID</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="front_id">Front of ID * (JPG, PNG up to 5MB)</label>
                            <input type="file" id="front_id" name="front_id" accept="image/jpeg,image/png" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="back_id">Back of ID (optional)</label>
                            <input type="file" id="back_id" name="back_id" accept="image/jpeg,image/png" class="form-control">
                        </div>
                        <div id="documentError" style="color:#c0392b;"></div>
                    </div>

                    <div style="display:flex; gap:0.5rem; margin-top:0.75rem;">
                        <button type="button" id="nextBtn" class="login-btn" style="background:#6c757d;">Next →</button>
                        <button type="submit" id="submitBtn" class="login-btn">Complete Registration</button>
                    </div>

                    <!-- Terms and Conditions Checkbox -->
                    <div class="form-group mt-3">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="agree_terms" name="agree_terms" required>
                            <label class="form-check-label" for="agree_terms">
                                I agree to the <a href="terms_conditions.php" target="_blank" class="text-primary">Terms and Conditions</a>
                                and <a href="data_privacy.php" target="_blank" class="text-primary">Data Privacy Policy</a>
                            </label>
                        </div>
                        <small class="text-muted d-block mt-1">
                            <i class="bi bi-info-circle"></i> You must accept the terms to create an account
                        </small>
                    </div>

                    <button type="submit" class="login-btn">
                        <i class="bi bi-box-arrow-in-right"></i>
                        <span>Complete Registration</span>
                    </button>

                    <div class="signup-container">
                        <a href="login.php" class="login-btn" style="margin-top:0.75rem;background:#6c757d;">
                            <i class="bi bi-box-arrow-in-right"></i>
                            <span>Back to Login</span>
                        </a>
                        <p style="margin-top:0.5rem;">Already have an account?</p>
                    </div>

                </form>
                
                <div class="login-footer">
                    <p class="help-text">
                        <i class="bi bi-info-circle"></i>
                        Need help?
                    </p>
                    <div class="login-links">
                        <a href="../index.php" class="back-link">
                            <i class="bi bi-arrow-left"></i>
                            Back to Home
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

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
    max-width: 450px;
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
    max-width: 450px;
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

.login-description {
    font-size: 0.9rem;
    opacity: 0.8;
    margin: 0;
}

.login-form-container {
    padding: 2.5rem 2rem;
}

.login-alert {
    background: var(--danger-color);
    color: var(--text-white);
    padding: 1rem;
    border-radius: var(--border-radius-sm);
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.9rem;
    animation: fadeInDown 0.5s ease-out;
}

.login-form {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.login-form .form-group {
    position: relative;
}

.login-form .form-label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 500;
    color: var(--text-primary);
    margin-bottom: 0.5rem;
    font-size: 0.9rem;
}

.login-form .form-label i {
    color: var(--main-color);
    font-size: 1rem;
}

.login-form .form-control {
    width: 100%;
    padding: 1rem 1.25rem;
    border: 4px solid #e5e5e5;
    border-radius: var(--border-radius-sm);
    font-size: 1rem;
    background: rgba(255, 255, 255, 0.9);
    transition: var(--transition);
    font-family: 'Quicksand', sans-serif;
}

.login-form .form-control:focus {
    outline: none;
    border-color: var(--main-color);
    box-shadow: 0 0 0 3px rgba(139, 111, 71, 0.1);
    background: var(--text-white);
}

.login-form .form-control::placeholder {
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
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    margin-top: 1rem;
    box-shadow: var(--shadow-md);
}

.login-btn:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
    background: var(--gradient-accent);
}

.login-btn:active {
    transform: translateY(0);
}

.login-footer {
    text-align: center;
    margin-top: 2rem;
    padding-top: 1.5rem;
    border-top: 1px solid #e5e5e5;
}

.help-text {
    color: var(--text-light);
    font-size: 0.85rem;
    margin: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.help-text i {
    color: var(--main-color);
}

.login-links {
    margin-top: 1rem;
    text-align: center;
}

.back-link {
    color: var(--main-color);
    text-decoration: none;
    font-size: 0.9rem;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    transition: var(--transition);
}

.back-link:hover {
    color: var(--accent-color);
    text-decoration: underline;
}

.back-link i {
    font-size: 0.8rem;
}

/* Responsive Design */
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

/* Animation Keyframes */
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
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Form validation
    const form = document.getElementById('loginForm');
    form.addEventListener('submit', function(event) {
        if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
        }
        form.classList.add('was-validated');
    });

    // Email availability check (debounced)
    const emailInput = document.getElementById('emailadd');
    const emailStatus = document.getElementById('emailStatus');
    let emailTimer = null;
    if (emailInput) {
        emailInput.addEventListener('input', function() {
            emailStatus.textContent = '';
            clearTimeout(emailTimer);
            const value = this.value.trim();
            if (value.length === 0) return;
            emailTimer = setTimeout(() => {
                fetch('check_email.php?email=' + encodeURIComponent(value))
                    .then(r => r.json())
                    .then(data => {
                        if (data.available) {
                            emailStatus.style.color = '#16a34a';
                            emailStatus.textContent = '✓ Available';
                        } else {
                            emailStatus.style.color = '#c0392b';
                            emailStatus.textContent = '✗ Already in Use';
                        }
                    })
                    .catch(err => {
                        emailStatus.style.color = '#6c757d';
                        emailStatus.textContent = 'Check failed';
                    });
            }, 600);
        });
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

    // Password strength and match
    const pwd = document.getElementById('password');
    const pwdConfirm = document.getElementById('confirmpassword');
    const strengthBar = document.getElementById('passwordStrengthBar');
    const passwordHelp = document.getElementById('passwordHelp');
    const passwordMatch = document.getElementById('passwordMatch');

    function updateStrength() {
        const v = pwd.value || '';
        let score = 0;
        if (v.length >= 8) score += 1;
        if (/[A-Z]/.test(v)) score += 1;
        if (/[0-9]/.test(v)) score += 1;
        if (/[^A-Za-z0-9]/.test(v)) score += 1;

        const pct = Math.min(100, (score / 4) * 100);
        strengthBar.style.width = pct + '%';
        if (pct < 50) strengthBar.style.background = '#e74c3c';
        else if (pct < 80) strengthBar.style.background = '#f39c12';
        else strengthBar.style.background = '#16a34a';

        // enforce minimum tooltip
        if (v.length >= 8) {
            passwordHelp.textContent = 'Minimum reached';
        } else {
            passwordHelp.textContent = 'Minimum 8 characters';
        }
    }

    function updateMatch() {
        if (!pwd.value && !pwdConfirm.value) {
            passwordMatch.style.color = '#6c757d';
            passwordMatch.textContent = 'Passwords must match';
            return;
        }
        if (pwd.value === pwdConfirm.value) {
            passwordMatch.style.color = '#16a34a';
            passwordMatch.textContent = 'Passwords match';
        } else {
            passwordMatch.style.color = '#c0392b';
            passwordMatch.textContent = 'Passwords do not match';
        }
    }

    if (pwd) pwd.addEventListener('input', () => { updateStrength(); updateMatch(); });
    if (pwdConfirm) pwdConfirm.addEventListener('input', updateMatch);

    // Next button shows document section and validates residency and password min
    const nextBtn = document.getElementById('nextBtn');
    const documentSection = document.getElementById('documentSection');
    const documentError = document.getElementById('documentError');
    if (nextBtn) {
        nextBtn.addEventListener('click', function() {
            documentError.textContent = '';
            // Basic checks: resident selected, password >=8, username available
            const resident = document.querySelector('input[name="resident_qc"]:checked');
            if (!resident) { documentError.textContent = 'Please indicate residency (Yes / No).'; return; }
            if (pwd.value.length < 8) { documentError.textContent = 'Password must be at least 8 characters.'; return; }
            if (usernameStatus.textContent === 'Username not available') { documentError.textContent = 'Please choose a different username.'; return; }

            // Submit current form data to document_upload.php for file upload step
            form.action = 'document_upload.php';
            form.method = 'POST';
            form.enctype = 'application/x-www-form-urlencoded';
            form.submit();
        });
    }

    // Client-side file validation on submit
    const submitBtn = document.getElementById('submitBtn');
    if (form) {
        form.addEventListener('submit', function(e) {
            const docVisible = documentSection.style.display !== 'none';
            if (docVisible) {
                const idType = document.getElementById('id_type').value;
                const front = document.getElementById('front_id').files[0];
                if (!idType) { e.preventDefault(); documentError.textContent = 'Please select an ID type.'; return false; }
                if (!front) { e.preventDefault(); documentError.textContent = 'Please upload the front of your ID.'; return false; }
                if (front.size > 5 * 1024 * 1024) { e.preventDefault(); documentError.textContent = 'Front ID exceeds 5MB.'; return false; }
            }
            return true;
        });
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>
