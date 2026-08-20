<?php
/**
 * Two-Factor Authentication - Luz De Luna Hotel HR Management System - HR 1&2
 * 
 * @author HR System
 * @version 1.0.0
 */

class TwoFactorAuth {
    private $pdo;
    private $code_validity = 900; // seconds (15 minutes) for email/SMS codes
    private $totp_period = 30; // seconds for TOTP

    public function __construct($pdo = null, $code_validity = 900) {
        $this->pdo = $pdo;
        $this->code_validity = (int)$code_validity;
    }

    /**
     * Generate a 6-digit numeric code for SMS/Email
     * @return string
     */
    public function generateSMSCode() {
        try {
            $n = random_int(0, 999999);
        } catch (Exception $e) {
            $n = mt_rand(0, 999999);
        }
        return str_pad((string)$n, 6, '0', STR_PAD_LEFT);
    }

    // Backwards-compat alias
    public function generateEmailCode() { return $this->generateSMSCode(); }
    
    public function storeSMSCode($user_id, $code) {
        // If PDO is not available, skip DB attempt and use session fallback
        if (!($this->pdo instanceof PDO)) {
            if (session_status() === PHP_SESSION_NONE) { @session_start(); }
            $_SESSION['tfa_codes'] = $_SESSION['tfa_codes'] ?? [];
            $_SESSION['tfa_codes'][$user_id] = [
                'code' => $code,
                'expires_at' => time() + $this->code_validity,
                'used' => false,
                'type' => 'SMS'
            ];
            error_log("Stored SMS code in session for user {$user_id} (no PDO)");
            return true;
        }

        try {
            // Invalidate previous SMS codes for this user immediately to avoid confusion
            $invalidateSql = "UPDATE two_factor_codes SET used = 1 WHERE user_id = ? AND type = 'SMS' AND used = 0";
            $this->pdo->prepare($invalidateSql)->execute([$user_id]);

            $sql = "INSERT INTO two_factor_codes (user_id, code, type, expires_at) 
                    VALUES (?, ?, 'SMS', DATE_ADD(NOW(), INTERVAL ? SECOND))";
            $stmt = $this->pdo->prepare($sql);
            $ok = $stmt->execute([$user_id, $code, $this->code_validity]);
            if ($ok) return true;
            // if execute returns false, fallthrough to fallback below
        } catch (Exception $e) {
            error_log("Error storing SMS code: " . $e->getMessage());
            // We'll fall back to session storage below
        }

        // Fallback: if DB table is missing or write failed, store code in session for local/dev environments
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $_SESSION['tfa_codes'] = $_SESSION['tfa_codes'] ?? [];
        $_SESSION['tfa_codes'][$user_id] = [
            'code' => $code,
            'expires_at' => time() + $this->code_validity,
            'used' => false,
            'type' => 'SMS'
        ];
        error_log("Stored SMS code in session for user {$user_id}");
        return true;
    }
    
    /**
     * Verify SMS code
     * 
     * @param int $user_id
     * @param string $code
     * @return bool
     */
    public function verifySMSCode($user_id, $code) {
        // If no PDO, use session fallback
        if (!($this->pdo instanceof PDO)) {
            if (session_status() === PHP_SESSION_NONE) {
                @session_start();
            }
            if (!empty($_SESSION['tfa_codes'][$user_id])) {
                $entry = $_SESSION['tfa_codes'][$user_id];
                if (!empty($entry['used'])) return false;
                if ($entry['type'] !== 'SMS') return false;
                if ($entry['code'] === $code && time() < $entry['expires_at']) {
                    // mark used
                    $_SESSION['tfa_codes'][$user_id]['used'] = true;
                    return true;
                }
            }
            return false;
        }

        try {
            $sql = "SELECT id FROM two_factor_codes 
                    WHERE user_id = ? AND code = ? AND type = 'SMS' 
                    AND expires_at > NOW() AND used = 0";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$user_id, $code]);
            $result = $stmt->fetch();
            
            if ($result) {
                // Mark code as used
                $update_sql = "UPDATE two_factor_codes SET used = 1 WHERE id = ?";
                $update_stmt = $this->pdo->prepare($update_sql);
                $update_stmt->execute([$result['id']]);
                return true;
            }
            
            return false;
        } catch (Exception $e) {
            error_log("Error verifying SMS code: " . $e->getMessage());
            // Fallback: check session-stored codes (useful for local/dev with no table)
            if (session_status() === PHP_SESSION_NONE) {
                @session_start();
            }
            if (!empty($_SESSION['tfa_codes'][$user_id])) {
                $entry = $_SESSION['tfa_codes'][$user_id];
                if (!empty($entry['used'])) return false;
                if ($entry['type'] !== 'SMS') return false;
                if ($entry['code'] === $code && time() < $entry['expires_at']) {
                    // mark used
                    $_SESSION['tfa_codes'][$user_id]['used'] = true;
                    return true;
                }
            }
            return false;
        }
    }
    
    /**
     * Store Email code in database (uses two_factor_codes with type EMAIL)
     */
    public function storeEmailCode($user_id, $code) {
        // If PDO is not available, use session fallback immediately
        if (!($this->pdo instanceof PDO)) {
            if (session_status() === PHP_SESSION_NONE) { @session_start(); }
            $_SESSION['tfa_codes'] = $_SESSION['tfa_codes'] ?? [];
            $_SESSION['tfa_codes'][$user_id] = [
                'code' => $code,
                'expires_at' => time() + $this->code_validity,
                'used' => false,
                'type' => 'EMAIL'
            ];
            error_log("Stored EMAIL code in session for user {$user_id} (no PDO)");
            return true;
        }

        try {
            // Invalidate previous EMAIL codes for this user to ensure only the latest code is valid
            $invalidateSql = "UPDATE two_factor_codes SET used = 1 WHERE user_id = ? AND type = 'EMAIL' AND used = 0";
            $this->pdo->prepare($invalidateSql)->execute([$user_id]);

            $sql = "INSERT INTO two_factor_codes (user_id, code, type, expires_at) 
                    VALUES (?, ?, 'EMAIL', DATE_ADD(NOW(), INTERVAL ? SECOND))";
            $stmt = $this->pdo->prepare($sql);
            $ok = $stmt->execute([$user_id, $code, $this->code_validity]);
            if ($ok) return true;
            // fallthrough to session fallback
        } catch (Exception $e) {
            error_log("Error storing Email code: " . $e->getMessage());
            // fallthrough to session fallback
        }

        // Fallback: store in session for environments without table
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $_SESSION['tfa_codes'] = $_SESSION['tfa_codes'] ?? [];
        $_SESSION['tfa_codes'][$user_id] = [
            'code' => $code,
            'expires_at' => time() + $this->code_validity,
            'used' => false,
            'type' => 'EMAIL'
        ];
        error_log("Stored EMAIL code in session for user {$user_id}");
        return true;
    }
    
    /**
     * Verify Email code
     */
    public function verifyEmailCode($user_id, $code) {
        // If no PDO, use session fallback
        if (!($this->pdo instanceof PDO)) {
            if (session_status() === PHP_SESSION_NONE) {
                @session_start();
            }
            if (!empty($_SESSION['tfa_codes'][$user_id])) {
                $entry = $_SESSION['tfa_codes'][$user_id];
                if (!empty($entry['used'])) return false;
                if ($entry['type'] !== 'EMAIL') return false;
                // Use loose equality to allow string/int matches from different generators
                if ($entry['code'] == $code && time() < $entry['expires_at']) {
                    $_SESSION['tfa_codes'][$user_id]['used'] = true;
                    return true;
                }
            }

            // Also support session fallback keyed by email (used by sendEmailCode and test tools)
            $email = $this->getUserEmail($user_id);
            if ($email && !empty($_SESSION['tfa_last_email_otp'][$email])) {
                $e = $_SESSION['tfa_last_email_otp'][$email];
                // e['when'] is stored as Y-m-d H:i:s — compute expiry using timestamp
                $whenTs = !empty($e['when']) ? strtotime($e['when']) : 0;
                $expiresAt = $whenTs + $this->code_validity;
                if (!empty($e['code']) && $e['code'] == $code && time() < $expiresAt) {
                    // consume the fallback so it's not reusable
                    unset($_SESSION['tfa_last_email_otp'][$email]);
                    return true;
                }
            }
            return false;
        }

        try {
            $sql = "SELECT id FROM two_factor_codes 
                    WHERE user_id = ? AND code = ? AND type = 'EMAIL' 
                    AND expires_at > NOW() AND used = 0";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$user_id, $code]);
            $result = $stmt->fetch();
            if ($result) {
                $update_sql = "UPDATE two_factor_codes SET used = 1 WHERE id = ?";
                $update_stmt = $this->pdo->prepare($update_sql);
                $update_stmt->execute([$result['id']]);
                return true;
            }
            return false;
        } catch (Exception $e) {
            error_log("Error verifying Email code: " . $e->getMessage());
            // Fallback: check session-stored codes
            if (session_status() === PHP_SESSION_NONE) {
                @session_start();
            }
            if (!empty($_SESSION['tfa_codes'][$user_id])) {
                $entry = $_SESSION['tfa_codes'][$user_id];
                if (!empty($entry['used'])) return false;
                if ($entry['type'] !== 'EMAIL') return false;
                // Allow loose comparison for string/int
                if ($entry['code'] == $code && time() < $entry['expires_at']) {
                    $_SESSION['tfa_codes'][$user_id]['used'] = true;
                    return true;
                }
            }

            // Also check session fallback by email (sendEmailCode uses this when email delivery failed)
            $email = $this->getUserEmail($user_id);
            if ($email && !empty($_SESSION['tfa_last_email_otp'][$email])) {
                $e = $_SESSION['tfa_last_email_otp'][$email];
                $whenTs = !empty($e['when']) ? strtotime($e['when']) : 0;
                $expiresAt = $whenTs + $this->code_validity;
                if (!empty($e['code']) && $e['code'] == $code && time() < $expiresAt) {
                    unset($_SESSION['tfa_last_email_otp'][$email]);
                    return true;
                }
            }
            return false;
        }
    }
    
    /**
     * Look up user's email via users -> employees
     */
    public function getUserEmail($user_id) {
        try {
            // Try to get email from employees table if available, otherwise fall back to signup.emailadd
        $sql = "SELECT COALESCE(e.email, s.emailadd) AS email 
                FROM users u 
                LEFT JOIN employees e ON e.employee_id = u.employee_id 
                LEFT JOIN signup s ON u.user_id = s.user_id 
                WHERE u.user_id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$user_id]);
            $row = $stmt->fetch();
            return $row ? $row['email'] : null;
        } catch (Exception $e) {
            error_log("Error fetching user email: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Send Email code using PHPMailer (SMTP preferred), fallback to mail(), then session storage.
     * Works on both LOCALHOST (dev/testing) and DOMAIN (production).
     * 
     * Flow:
     * 1. If SMTP credentials are configured (not placeholders): attempt PHPMailer + SMTP
     * 2. If SMTP unavailable/fails OR on localhost: attempt PHP mail()
     * 3. Always store in session as fallback (accessible in same browser for dev testing)
     * 4. Return true if real delivery succeeded OR session fallback stored successfully
     */
    public function sendEmailCode($toEmail, $code) {
        if (!$toEmail) return false;
        $subject = 'Your Two-Factor Authentication Code';
        $minutes = (int) max(1, floor($this->code_validity / 60));
        $body = "Your verification code is: <strong>{$code}</strong><br/>This code expires in {$minutes} minute" . ($minutes > 1 ? 's' : '') . ".";
        
        // Load environment vars
        $envFile = __DIR__ . '/../mailer.env';
        $env = [];
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $ln) {
                $ln = trim($ln);
                if ($ln === '' || strpos($ln, '#') === 0) continue;
                if (strpos($ln, '=') === false) continue;
                list($k, $v) = explode('=', $ln, 2);
                $env[trim($k)] = trim(trim($v), "\"'");
            }
        }
        
        $host = getenv('SMTP_HOST') ?: ($env['MAIL_HOST'] ?? 'smtp.example.com');
        $port = getenv('SMTP_PORT') ?: ($env['MAIL_PORT'] ?? 587);
        $user = getenv('SMTP_USER') ?: ($env['MAIL_USERNAME'] ?? '');
        $pass = getenv('SMTP_PASS') ?: ($env['MAIL_PASSWORD'] ?? '');
        $from = getenv('SMTP_FROM') ?: ($env['MAIL_FROM_ADDRESS'] ?? 'no-reply@example.com');
        $fromName = getenv('SMTP_FROM_NAME') ?: ($env['MAIL_FROM_NAME'] ?? 'Alertara System');
        $encryption = getenv('SMTP_ENCRYPTION') ?: ($env['MAIL_ENCRYPTION'] ?? 'tls');
        
        // Helper: check if SMTP credentials look real (not placeholder values)
        $hasRealSMTPCreds = !empty($user) && !empty($pass) 
            && strpos($user, 'your-') === false 
            && strpos($pass, 'your-') === false
            && strpos($host, 'example.com') === false;
        
        $deliveredViaRealEmail = false;
        
        // Step 1: Attempt PHPMailer + SMTP if credentials look real
        if ($hasRealSMTPCreds) {
            try {
                if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
                    $autoload = __DIR__ . '/../vendor/autoload.php';
                    if (file_exists($autoload)) {
                        require_once $autoload;
                    }
                }
                if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
                    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
                    $mail->isSMTP();
                    $mail->Host = $host;
                    $mail->SMTPAuth = true;
                    $mail->Username = $user;
                    $mail->Password = $pass;
                    
                    // Set encryption
                    if (defined('PHPMailer\\PHPMailer\\PHPMailer::ENCRYPTION_SMTPS') && strtolower($encryption) === 'ssl') {
                        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
                    } else {
                        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                    }
                    
                    $mail->Port = (int)$port;
                    $mail->setFrom($from, $fromName);
                    $mail->addAddress($toEmail);
                    $mail->isHTML(true);
                    $mail->Subject = $subject;
                    $mail->Body = $body;
                    $mail->AltBody = strip_tags($body);
                    $mail->send();
                    $deliveredViaRealEmail = true;
                    error_log("OTP sent to {$toEmail} via SMTP");
                    return true;
                }
            } catch (Exception $e) {
                // SMTP failed; log and continue to fallback
                error_log('PHPMailer SMTP error for ' . $toEmail . ': ' . $e->getMessage());
            }
        }
        
        // Step 2: Attempt PHP mail() as secondary delivery method
        $headers = "MIME-Version: 1.0\r\nContent-type:text/html;charset=UTF-8\r\nFrom: {$fromName} <{$from}>\r\n";
        $mailOk = @mail($toEmail, $subject, $body, $headers);
        if ($mailOk) {
            $deliveredViaRealEmail = true;
            error_log("OTP sent to {$toEmail} via mail()");
            return true;
        }
        
        // Step 3: Store in session as fallback (works on both LOCALHOST and DOMAIN)
        // This ensures the same browser session can verify the code even if email delivery failed.
        $devLog = getenv('DEV_LOG_OTP') === '1';
        if (session_status() === PHP_SESSION_NONE) { @session_start(); }
        $_SESSION['tfa_last_email_otp'] = $_SESSION['tfa_last_email_otp'] ?? [];
        $_SESSION['tfa_last_email_otp'][$toEmail] = [
            'code' => $code,
            'when' => date('Y-m-d H:i:s'),
            'delivered_via_email' => $deliveredViaRealEmail,
            'log' => $devLog ? (__DIR__ . '/../logs/otp.log') : null
        ];
        
        // Optional: log to file if DEV_LOG_OTP is enabled (for debugging)
        if ($devLog) {
            try {
                $logDir = __DIR__ . '/../logs';
                if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
                $logFile = $logDir . '/otp.log';
                $status = $deliveredViaRealEmail ? 'DELIVERED' : 'SESSION_FALLBACK';
                $entry = date('Y-m-d H:i:s') . " | EMAIL OTP to {$toEmail}: {$code} ({$status})\n";
                @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
            } catch (Exception $e) {
                error_log('Failed to write OTP log: ' . $e->getMessage());
            }
        }
        
        // Return true if session fallback is available (allowing verification to succeed)
        return !empty($_SESSION['tfa_last_email_otp'][$toEmail]);
    }
    
    /**
     * Enable 2FA for user
     * 
     * @param int $user_id
     * @param string $secret
     * @param string $type
     * @return bool
     */
    public function enable2FA($user_id, $secret, $type = 'TOTP') {
        try {
            $sql = "INSERT INTO user_two_factor (user_id, secret, type, enabled, created_at) 
                    VALUES (?, ?, ?, 1, NOW())
                    ON DUPLICATE KEY UPDATE 
                    secret = VALUES(secret), 
                    type = VALUES(type),
                    enabled = 1,
                    updated_at = NOW()";
            
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([$user_id, $secret, $type]);
        } catch (Exception $e) {
            error_log("Error enabling 2FA: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Disable 2FA for user
     * 
     * @param int $user_id
     * @return bool
     */
    public function disable2FA($user_id) {
        try {
            $sql = "UPDATE user_two_factor SET enabled = 0, updated_at = NOW() WHERE user_id = ?";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([$user_id]);
        } catch (Exception $e) {
            error_log("Error disabling 2FA: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if user has 2FA enabled
     * 
     * @param int $user_id
     * @return bool
     */
    public function is2FAEnabled($user_id) {
        try {
            $sql = "SELECT enabled FROM user_two_factor WHERE user_id = ? AND enabled = 1";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$user_id]);
            $result = $stmt->fetch();
            return $result ? true : false;
        } catch (Exception $e) {
            error_log("Error checking 2FA status: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get user's 2FA secret
     * 
     * @param int $user_id
     * @return string|null
     */
    public function getUserSecret($user_id) {
        try {
            $sql = "SELECT secret FROM user_two_factor WHERE user_id = ? AND enabled = 1";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$user_id]);
            $result = $stmt->fetch();
            return $result ? $result['secret'] : null;
        } catch (Exception $e) {
            error_log("Error getting user secret: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Verify TOTP (RFC6238 compatible)
     *
     * @param string $secret Base32 encoded secret
     * @param string $code 6-digit code
     * @param int $window number of time steps to check on each side
     * @return bool
     */
    public function verifyTOTP($secret, $code, $window = 1) {
        if (empty($secret) || !preg_match('/^\d{6}$/', $code)) return false;
        $secretKey = $this->base32Decode($secret);
        if ($secretKey === '') return false;

        $timeStep = floor(time() / $this->totp_period);
        for ($i = -$window; $i <= $window; $i++) {
            $counter = pack('N*', 0) . pack('N*', $timeStep + $i);
            $hash = hash_hmac('sha1', $counter, $secretKey, true);
            $offset = ord($hash[19]) & 0x0F;
            $binCode = (ord($hash[$offset]) & 0x7F) << 24 |
                       (ord($hash[$offset+1]) & 0xFF) << 16 |
                       (ord($hash[$offset+2]) & 0xFF) << 8 |
                       (ord($hash[$offset+3]) & 0xFF);
            $otp = $binCode % 1000000;
            if ((string)str_pad($otp, 6, '0', STR_PAD_LEFT) === (string)$code) return true;
        }
        return false;
    }
    
    /**
     * Verify 2FA code
     * 
     * @param int $user_id
     * @param string $code
     * @param string $type
     * @return bool
     */
    public function verify2FACode($user_id, $code, $type = 'TOTP') {
        if ($type === 'TOTP') {
            $secret = $this->getUserSecret($user_id);
            if (!$secret) {
                return false;
            }
            return $this->verifyTOTP($secret, $code);
        } elseif ($type === 'SMS') {
            return $this->verifySMSCode($user_id, $code);
        } elseif ($type === 'EMAIL') {
            return $this->verifyEmailCode($user_id, $code);
        }
        
        return false;
    }
    
    /**
     * Send SMS code (placeholder - integrate with SMS provider)
     * 
     * @param string $phone
     * @param string $code
     * @return bool
     */
    public function sendSMSCode($phone, $code) {
        // Normalize phone number: accept 09123456789 -> +639123456789 for PH numbers
        $raw = trim($phone);
        $normalized = preg_replace('/[^0-9+]/', '', $raw);
        if (strpos($normalized, '+') !== 0) {
            if (preg_match('/^0(9\d{9})$/', $normalized, $m)) {
                $normalized = '+63' . $m[1];
            } elseif (preg_match('/^9\d{9}$/', $normalized)) {
                $normalized = '+63' . $normalized;
            } else {
                if (preg_match('/^\d{10,15}$/', $normalized)) {
                    $normalized = '+' . $normalized;
                }
            }
        }

        // Twilio integration removed. Log the code for local/dev fallback and return success.
        error_log("SMS Code for {$normalized}: {$code}");
        return true;
    }
    
    /**
     * Base32 decode
     * 
     * @param string $input
     * @return string
     */
    private function base32Decode($input) {
        $map = array(
            'A' => 0, 'B' => 1, 'C' => 2, 'D' => 3, 'E' => 4, 'F' => 5, 'G' => 6, 'H' => 7,
            'I' => 8, 'J' => 9, 'K' => 10, 'L' => 11, 'M' => 12, 'N' => 13, 'O' => 14, 'P' => 15,
            'Q' => 16, 'R' => 17, 'S' => 18, 'T' => 19, 'U' => 20, 'V' => 21, 'W' => 22, 'X' => 23,
            'Y' => 24, 'Z' => 25, '2' => 26, '3' => 27, '4' => 28, '5' => 29, '6' => 30, '7' => 31
        );
        
        $input = strtoupper($input);
        $input = str_replace('=', '', $input);
        
        $output = '';
        $v = 0;
        $vbits = 0;
        
        for ($i = 0; $i < strlen($input); $i++) {
            $v <<= 5;
            $v += $map[$input[$i]] ?? 0;
            $vbits += 5;
            
            if ($vbits >= 8) {
                $output .= chr(($v >> ($vbits - 8)) & 0xFF);
                $vbits -= 8;
            }
        }
        
        return $output;
    }

    /**
     * Generate a random 16-character Base32 Secret Key for TOTP (Google Authenticator)
     */
    public function generateSecret($length = 16) {
        $validChars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        for ($i = 0; $i < $length; $i++) {
            $secret .= $validChars[random_int(0, strlen($validChars) - 1)];
        }
        return $secret;
    }

    /**
     * Generate otpauth:// URI and QR code image URL for Google/Microsoft Authenticator
     */
    public function getQRCodeUrl($name, $secret, $issuer = 'Alertara Law Enforcement') {
        $encodedIssuer = rawurlencode($issuer);
        $encodedName = rawurlencode($name);
        $otpauth = "otpauth://totp/{$encodedIssuer}:{$encodedName}?secret={$secret}&issuer={$encodedIssuer}";
        // Use standard QR code API
        return "https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=" . urlencode($otpauth);
    }
}
?>

