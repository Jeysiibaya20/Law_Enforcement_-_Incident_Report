<?php
// document_upload.php - second step for registration: accept files and forward to signup finalization
require_once '../config/db_connect.php';
require_once '../includes/header.php';
// Load composer autoload if present (for libraries like PHPMailer)
$autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
} else {
    error_log('Warning: Composer autoload not found at ' . $autoload . '.');
}   

// Expect POST data from signup.php form (first step)
$posted = $_POST;

// Basic required fields check (redirect back if missing)
if (empty($posted['first_name']) || empty($posted['last_name']) || empty($posted['emailadd']) || empty($posted['username'])) {
    echo "<script>alert('Missing required registration fields. Please complete the Personal Info first.'); window.location.href='signup.php';</script>";
    exit();
}

// Render the upload form which will POST to signup.php (finalize)
?>
<div class="login-container">
    <div class="login-background">
        <div class="login-overlay"></div>
    </div>
    <style>
        body {
            background: linear-gradient(135deg, #1f2a44 0%, #274156 50%, #2d5a6b 100%);
            font-family: 'Quicksand', Arial, sans-serif;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            color: #ffffff;
            margin: 0;
            padding: 0;
            height: 100vh;
        }

        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .login-card {
            width: 100%;
            max-width: 480px;
            background: rgba(255, 255, 255, 0.96);
            color: #2d3a4a;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
            overflow: hidden;
        }

        .login-header {
            padding: 2rem 1.5rem;
            background: linear-gradient(135deg, 
                rgba(76, 138, 137, 0.95) 0%, 
                rgba(58, 80, 107, 0.9) 50%, 
                rgba(28, 37, 65, 0.95) 100%);
            color: white;
            text-align: center;
        }

        .login-header h2 {
            margin: 0;
            font-family: 'Libre Baskerville', serif;
            font-size: 1.75rem;
        }

        .login-form-container {
            padding: 1.75rem 1.5rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #2d3a4a;
        }

        .form-control {
            width: 100%;
            padding: 0.85rem 1rem;
            border-radius: 8px;
            border: 1px solid #e6e8ef;
            font-size: 0.95rem;
            background: #fff;
            box-sizing: border-box;
        }

        .btn-primary {
            background: #4c8a89;
            color: white;
            border: none;
            padding: 0.95rem 1.25rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            display: inline-block;
            transition: transform 0.15s ease;
            width: 100%;
            box-shadow: 0 6px 18px rgba(76, 138, 137, 0.18);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
        }

        .upload-area {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .upload-preview {
            width: 120px;
            height: 80px;
            background: #f6f8fa;
            border: 1px dashed #ccd6e6;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .upload-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .small {
            font-size: 0.85rem;
            color: #6b7280;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: #4c8a89;
            text-decoration: none;
            font-weight: 600;
            margin-top: 0.5rem;
        }

        .center {
            display: flex;
            justify-content: center;
        }
    </style>
        <div class="login-card">
            <div class="login-header">
                <div class="login-logo">
                    <img src="../assets/css/tara.png" alt="Alertara Logo" class="logo-image">
                </div>
                <h2>Document Verification</h2>
                <p class="login-subtitle">Please upload a valid ID (front required).</p>
            </div>

            <div class="login-form-container">
                <form method="POST" action="signup.php" enctype="multipart/form-data" class="login-form">
                    <?php
                    // Re-create hidden inputs from the first-step POST so signup.php receives the fields
                    foreach ($posted as $k => $v) {
                        $safeKey = htmlspecialchars($k, ENT_QUOTES);
                        $safeVal = htmlspecialchars($v ?? '', ENT_QUOTES);
                        echo "<input type=\"hidden\" name=\"{$safeKey}\" value=\"{$safeVal}\">\n";
                    }
                    ?>

                    <div class="form-group">
                        <label for="id_type">Valid ID Type *</label>
                        <select id="id_type" name="id_type" class="form-control" required>
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
                        <input type="file" id="front_id" name="front_id" accept="image/jpeg,image/png" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="back_id">Back of ID (optional)</label>
                        <input type="file" id="back_id" name="back_id" accept="image/jpeg,image/png" class="form-control">
                    </div>

                    <div id="documentError" style="color:#c0392b;"></div>

                    <div style="display:flex; gap:0.5rem; margin-top:0.75rem;">
                        <a href="signup.php" class="back-link" style="background:#f1f1f1; padding:10px 14px; border-radius:6px; text-decoration:none;">← Back</a>
                        <button type="submit" class="btn-primary">Upload and Complete Registration</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    const docError = document.getElementById('documentError');
    form.addEventListener('submit', function(e) {
        docError.textContent = '';
        const idType = document.getElementById('id_type').value;
        const front = document.getElementById('front_id').files[0];
        if (!idType) { e.preventDefault(); docError.textContent = 'Please select an ID type.'; return false; }
        if (!front) { e.preventDefault(); docError.textContent = 'Please upload the front of your ID.'; return false; }
        if (front.size > 5 * 1024 * 1024) { e.preventDefault(); docError.textContent = 'Front ID exceeds 5MB.'; return false; }
        return true;
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>
