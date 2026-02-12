<?php
/**
 * Two-Factor Authentication - Luz De Luna Hotel HR Management System - HR 1&2
 * 
 * @author HR System
 * @version 1.0.0
 */

class TwoFactorAuth {
    private $pdo;
    private $secret_length = 16;
    private $code_length = 6;
    private $code_validity = 300; // 5 minutes
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Generate a secret key for TOTP
     * 
     * @return string
     */
    public function generateSecret() {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        for ($i = 0; $i < $this->secret_length; $i++) {
            $secret .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $secret;
    }
    
    /**
     * Generate QR code URL for Google Authenticator
     * 
     * @param string $secret
     * @param string $email
     * @param string $issuer
     * @return string
     */
    public function getQRCodeUrl($secret, $email, $issuer = 'Luz De Luna Hotel') {
        $url = sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s',
            rawurlencode($issuer),
            rawurlencode($email),
            $secret,
            rawurlencode($issuer)
        );
        return $url;
    }
    
    /**
     * Generate QR code image URL using Google Charts API
     * 
     * @param string $secret
     * @param string $email
     * @param string $issuer
     * @return string
     */
    public function getQRCodeImageUrl($secret, $email, $issuer = 'Luz De Luna Hotel') {
        $qr_url = $this->getQRCodeUrl($secret, $email, $issuer);
        return 'https://chart.googleapis.com/chart?chs=200x200&chld=M|0&cht=qr&chl=' . urlencode($qr_url);
    }
    
    /**
     * Generate TOTP code
     * 
     * @param string $secret
     * @param int $time_slice
     * @return string
     */
    public function generateTOTP($secret, $time_slice = null) {
        if ($time_slice === null) {
            $time_slice = floor(time() / 30);
        }
        
        $secret_key = $this->base32Decode($secret);
        $time = pack('N*', 0, $time_slice);
        $hm = hash_hmac('sha1', $time, $secret_key, true);
        $offset = ord(substr($hm, -1)) & 0x0F;
        $hashpart = substr($hm, $offset, 4);
        $value = unpack('N', $hashpart);
        $value = $value[1];
        $value = $value & 0x7FFFFFFF;
        
        $modulo = pow(10, $this->code_length);
        return str_pad($value % $modulo, $this->code_length, '0', STR_PAD_LEFT);
    }
    
    /**
     * Verify TOTP code
     * 
     * @param string $secret
     * @param string $code
     * @param int $window
     * @return bool
     */
    public function verifyTOTP($secret, $code, $window = 1) {
        $time_slice = floor(time() / 30);
        
        for ($i = -$window; $i <= $window; $i++) {
            $calculated_code = $this->generateTOTP($secret, $time_slice + $i);
            if (hash_equals($calculated_code, $code)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Generate SMS code
     * 
     * @return string
     */
    public function generateSMSCode() {
        return str_pad(random_int(0, 999999), $this->code_length, '0', STR_PAD_LEFT);
    }
    
    /**
     * Store SMS code in database
     * 
     * @param int $user_id
     * @param string $code
     * @return bool
     */
    public function storeSMSCode($user_id, $code) {
        try {
            $sql = "INSERT INTO two_factor_codes (user_id, code, type, expires_at) 
                    VALUES (?, ?, 'SMS', DATE_ADD(NOW(), INTERVAL ? SECOND))
                    ON DUPLICATE KEY UPDATE 
                    code = VALUES(code), 
                    expires_at = VALUES(expires_at),
                    created_at = NOW()";
            
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([$user_id, $code, $this->code_validity]);
        } catch (Exception $e) {
            error_log("Error storing SMS code: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Verify SMS code
     * 
     * @param int $user_id
     * @param string $code
     * @return bool
     */
    public function verifySMSCode($user_id, $code) {
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
            return false;
        }
    }
    
    /**
     * Store Email code in database (uses two_factor_codes with type EMAIL)
     */
    public function storeEmailCode($user_id, $code) {
        try {
            $sql = "INSERT INTO two_factor_codes (user_id, code, type, expires_at) 
                    VALUES (?, ?, 'EMAIL', DATE_ADD(NOW(), INTERVAL ? SECOND))
                    ON DUPLICATE KEY UPDATE 
                    code = VALUES(code), 
                    expires_at = VALUES(expires_at),
                    created_at = NOW()";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([$user_id, $code, $this->code_validity]);
        } catch (Exception $e) {
            error_log("Error storing Email code: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Verify Email code
     */
    public function verifyEmailCode($user_id, $code) {
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
     * Send Email code using PHPMailer (fallback to mail())
     */
    public function sendEmailCode($toEmail, $code) {
        if (!$toEmail) return false;
        $subject = 'Your Two-Factor Authentication Code';
        $body = "Your verification code is: <strong>{$code}</strong><br/>This code expires in 5 minutes.";
        
        try {
            // Try PHPMailer if available
            if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
                // Attempt to include common paths safely (no fatal on missing file)
                $autoload = __DIR__ . '/../vendor/autoload.php';
                if (file_exists($autoload)) {
                    require_once $autoload;
                }
            }
            if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
                $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
                // SMTP config from environment or defaults
                $host = getenv('SMTP_HOST') ?: 'smtp.example.com';
                $port = getenv('SMTP_PORT') ?: 587;
                $user = getenv('SMTP_USER') ?: '';
                $pass = getenv('SMTP_PASS') ?: '';
                $from = getenv('SMTP_FROM') ?: 'no-reply@example.com';
                $fromName = getenv('SMTP_FROM_NAME') ?: 'Luz De Luna HRMS';
                
                $mail->isSMTP();
                $mail->Host = $host;
                $mail->SMTPAuth = !empty($user);
                if (!empty($user)) {
                    $mail->Username = $user;
                    $mail->Password = $pass;
                }
                $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = $port;
                $mail->setFrom($from, $fromName);
                $mail->addAddress($toEmail);
                $mail->isHTML(true);
                $mail->Subject = $subject;
                $mail->Body = $body;
                $mail->AltBody = strip_tags($body);
                $mail->send();
                return true;
            }
        } catch (Exception $e) {
            error_log('PHPMailer error: ' . $e->getMessage());
        }
        
        // Fallback to PHP mail()
        $headers = "MIME-Version: 1.0\r\nContent-type:text/html;charset=UTF-8\r\nFrom: Luz De Luna HRMS <no-reply@example.com>\r\n";
        return @mail($toEmail, $subject, $body, $headers);
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
        // TODO: Integrate with actual SMS provider (Twilio, etc.)
        // For now, just log the code
        error_log("SMS Code for {$phone}: {$code}");
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
            $v += $map[$input[$i]];
            $vbits += 5;
            
            if ($vbits >= 8) {
                $output .= chr(($v >> ($vbits - 8)) & 0xFF);
                $vbits -= 8;
            }
        }
        
        return $output;
    }
}
?>

