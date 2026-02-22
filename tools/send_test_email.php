<?php
// Simple test endpoint to send a 2FA email using TwoFactorAuth
// Usage: visit tools/send_test_email.php?email=you@domain.com

// For this standalone test we won't require database connection; TwoFactorAuth supports null PDO.
$pdo = null;
require_once __DIR__ . '/../includes/two_factor_auth.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$email = trim($_GET['email'] ?? '');
if (empty($email)) {
    // try to read from mailer.env
    $envFile = __DIR__ . '/../mailer.env';
    if (file_exists($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $ln) {
            $ln = trim($ln);
            if ($ln === '' || strpos($ln, '#') === 0) continue;
            if (strpos($ln, '=') === false) continue;
            list($k, $v) = explode('=', $ln, 2);
            if (trim($k) === 'MAIL_FROM_ADDRESS') { $email = trim(trim($v), "\"'"); break; }
        }
    }
}

if (empty($email)) {
    echo "Please provide an email address: ?email=you@domain.com";
    exit;
}

try {
    $tfa = new TwoFactorAuth($pdo ?? null);
    $code = $tfa->generateSMSCode();
    // store with user_id = 0 (test)
    $tfa->storeEmailCode(0, $code);

    // Ensure a session fallback is available immediately so the same browser
    // can see/verify the code even if delivery is delayed or fails.
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['tfa_last_email_otp'] = $_SESSION['tfa_last_email_otp'] ?? [];
    $_SESSION['tfa_last_email_otp'][$email] = [
        'code' => $code,
        'when' => date('Y-m-d H:i:s'),
        'log' => null
    ];

    // Attempt delivery (sendEmailCode will also update session fallback)
    $sent = $tfa->sendEmailCode($email, $code);
    echo "Attempted to send code to: " . htmlspecialchars($email) . "<br>";
    echo "Result: " . ($sent ? 'OK' : 'FAILED') . "<br>";
    echo "If FAILED, check server logs for delivery errors.<br>";
    // For local development: when the request originates from the host (localhost),
    // display the session-stored OTP and local fallback log so developers can test.
    $isLocal = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1']);
    if ($isLocal) {
        if (!empty($_SESSION['tfa_last_email_otp'][$email])) {
            $info = $_SESSION['tfa_last_email_otp'][$email];
            echo "DEV OTP: " . htmlspecialchars($info['code']) . " at " . htmlspecialchars($info['when']) . "<br>";
        } else {
            echo "No session-stored OTP available for this email.<br>";
        }
        echo "<pre>\n" . htmlspecialchars(@file_get_contents(__DIR__ . '/../logs/otp.log')) . "</pre>";
    }
} catch (Exception $e) {
    echo "Error: " . htmlspecialchars($e->getMessage());
}

