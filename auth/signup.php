<?php
require_once '../config/db_connect.php';
require '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Check connection
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fullname = trim($_POST['Fullname'] ?? '');
    $email    = trim($_POST['emailadd'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $agree_terms = isset($_POST['agree_terms']) ? 1 : 0;

    if (empty($fullname) || empty($email) || empty($username) || empty($password)) {
        echo "<script>alert('Please fill in all fields'); window.location.href='signup.php';</script>";
        exit();
    }

    // ✅ Check if user agreed to terms and conditions
    if (!$agree_terms) {
        echo "<script>alert('You must agree to the Terms and Conditions and Data Privacy Policy to sign up.'); window.location.href='signup.php';</script>";
        exit();
    }

    // 🔎 Check if username exists (using PDO)
    try {
        $checkUserSql = "SELECT user_id FROM signup WHERE username = ?";
        $checkUserStmt = $pdo->prepare($checkUserSql);
        $checkUserStmt->execute([$username]);
        $checkUserRow = $checkUserStmt->fetch(PDO::FETCH_ASSOC);

        if ($checkUserRow) {
            echo "<script>alert('Username already exists'); window.location.href='signup.php';</script>";
            exit();
        }
    } catch (PDOException $e) {
        error_log("signup.php - Error checking username: " . $e->getMessage());
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
            echo "<script>alert('Email address already exists'); window.location.href='signup.php';</script>";
            exit();
        }
    } catch (PDOException $e) {
        error_log("signup.php - Error checking email: " . $e->getMessage());
        echo "<script>alert('An error occurred. Please try again later.'); window.location.href='signup.php';</script>";
        exit();
    }

    // ✅ Hash password and insert new account with email_verified = 0
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $verification_token = bin2hex(random_bytes(32));
    $token_expires = date("Y-m-d H:i:s", strtotime("+24 hours"));
    $terms_accepted_date = date("Y-m-d H:i:s");
    
    $sql = "INSERT INTO signup (fullname, emailadd, username, password, email_verified, verification_token, token_expires, terms_accepted, terms_accepted_date) VALUES (?, ?, ?, ?, 0, ?, ?, 1, ?)";
    try {
        $stmt = $pdo->prepare($sql);
        $res = $stmt->execute([$fullname, $email, $username, $hashed_password, $verification_token, $token_expires, $terms_accepted_date]);
    } catch (PDOException $e) {
        error_log("signup.php - Insert Error: " . $e->getMessage());
        echo "<script>alert('Error creating account. Please try again later.'); window.location.href='signup.php';</script>";
        exit();
    }

    if ($res) {
        // 📧 Send verification email
        $mail = new PHPMailer(true);
        
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'alertaraqc@gmail.com';
            $mail->Password   = 'fyyzywptnqlqemyt';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = 465;

            $mail->setFrom('alertaraqc@gmail.com', 'Alertara PH');
            $mail->addAddress($email);

            $mail->isHTML(true);
            $mail->Subject = 'Email Verification - Alertara PH';
            
            $verificationLink = "http://localhost/Law_Enforcement_-_Incident_Report/auth/verify_email.php?token=" . $verification_token;
            
            $mail->Body = "
                <html>
                <head></head>
                <body style='font-family: Arial, sans-serif;'>
                    <div style='max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px;'>
                        <h2 style='color: #333;'>Welcome to Alertara PH</h2>
                        <p>Hello <strong>" . htmlspecialchars($fullname) . "</strong>,</p>
                        <p>Thank you for registering! Please verify your email address to complete your account setup.</p>
                        
                        <p style='margin: 30px 0;'>
                            <a href='" . $verificationLink . "' style='background-color: #4c8a89; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;'>
                                Verify Email Address
                            </a>
                        </p>
                        
                        <p>Or copy this link in your browser:</p>
                        <p style='background: #f5f5f5; padding: 10px; word-break: break-all;'>" . $verificationLink . "</p>
                        
                        <p style='color: #666; font-size: 12px;'>This verification link expires in 24 hours.</p>
                        <p style='color: #666; font-size: 12px;'>If you did not create this account, please ignore this email.</p>
                    </div>
                </body>
                </html>
            ";

            $mail->send();
            echo "<script>alert('Account created! Please check your email to verify your account.'); window.location.href='login.php';</script>";
            exit();
            
        } catch (Exception $e) {
            error_log("Email sending failed: " . $mail->ErrorInfo);
            echo "<script>alert('Account created but email verification failed. Please try again later.'); window.location.href='login.php';</script>";
            exit();
        }
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
                
                <form method="POST" id="loginForm" class="login-form">
                      <!-- Fullname Form -->
                    <div class="form-group">
                        <label for="Fullname" class="form-label">
                            <i class="bi bi-person"></i>
                            Full Name
                        </label>
                        <input type="text" class="form-control" id="Fullname" name="Fullname" 
                               placeholder="Enter your fullname" required>
                    </div>
                    <!-- Email address Form -->
                        <div class="form-group">
                        <label for="EmailAdd" class="form-label">
                            <i class="bi bi-person"></i>
                            Email Address 
                        </label>
                        <input type="text" class="form-control" id="emailadd" name="emailadd" 
                               placeholder="Enter your email address" required>
                    </div>
                    <!-- Username Form -->
                        <div class="form-group">
                        <label for="username" class="form-label">
                            <i class="bi bi-person"></i>
                            Username
                        </label>
                        <input type="text" class="form-control" id="username" name="username" 
                               placeholder="Enter your username" required>
                    </div>
                    <!-- Password Form -->
                    <div class="form-group">
                        <label for="password" class="form-label">
                            <i class="bi bi-lock"></i>
                            Password
                        </label>
                        <input type="password" class="form-control" id="password" name="password" 
                               placeholder="Enter your password" required>
                    </div>
                    <!-- Confirm Password Form -->
                        <div class="form-group">
                        <label for="confirmpassword" class="form-label">
                            <i class="bi bi-lock"></i>
                            Confirm Password
                        </label>
                        <input type="password" class="form-control" id="confirmpassword" name="confirmpassword" 
                               placeholder="Confirm your password" required>
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
                        <span>Submit</span>
                    </button>
                    
                <div class="signup-container">
                 <a href="login.php" class="login-btn">
                <i class="bi bi-box-arrow-in-right"></i>
                <span>Back to Login</span>
            </a>
            <p>Already have an account?</p>
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
    animation: float 6s ease-in-out infinite;
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

@keyframes float {
    0%, 100% {
        transform: translateY(0px);
    }
    50% {
        transform: translateY(-10px);
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
});
</script>

<?php require_once '../includes/footer.php'; ?>
