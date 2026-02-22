<?php
/**
 * EmailSender - Unified email sending helper
 * Supports: Mailersend HTTP API (when MAILERSEND_API_KEY set), SMTP via PHPMailer, or PHP mail()
 */
class EmailSender {
    public function send($to, $subject, $htmlBody, $textBody = '') {
        $result = [
            'success' => false,
            'method' => null,
            'message_id' => null,
            'error' => null,
            'raw' => null
        ];

        // Load env / mailer.env
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

        $smtpHost = getenv('SMTP_HOST') ?: ($env['MAIL_HOST'] ?? 'smtp.example.com');
        $smtpPort = getenv('SMTP_PORT') ?: ($env['MAIL_PORT'] ?? 587);
        $smtpUser = getenv('SMTP_USER') ?: ($env['MAIL_USERNAME'] ?? '');
        $smtpPass = getenv('SMTP_PASS') ?: ($env['MAIL_PASSWORD'] ?? '');
        $smtpFrom = getenv('SMTP_FROM') ?: ($env['MAIL_FROM_ADDRESS'] ?? 'no-reply@example.com');
        $smtpFromName = getenv('SMTP_FROM_NAME') ?: ($env['MAIL_FROM_NAME'] ?? 'App');
        $smtpEnc = getenv('SMTP_ENCRYPTION') ?: ($env['MAIL_ENCRYPTION'] ?? 'tls');

        // 1) Mailersend API if key present
        $msApiKey = getenv('MAILERSEND_API_KEY') ?: ($env['MAILERSEND_API_KEY'] ?? getenv('MAILERSEND_API_KEY'));
        if (!empty($msApiKey)) {
            $payload = [
                'from' => ['email' => $smtpFrom, 'name' => $smtpFromName],
                'to' => [['email' => $to]],
                'subject' => $subject,
                'html' => $htmlBody,
                'text' => $textBody ?: strip_tags($htmlBody)
            ];
            $ch = curl_init('https://api.mailersend.com/v1/email');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $msApiKey,
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            $resp = curl_exec($ch);
            $err = curl_error($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            $result['raw'] = $resp;
            if ($resp && $httpCode >= 200 && $httpCode < 300) {
                $result['success'] = true;
                $result['method'] = 'mailersend';
                // Mailersend returns data with message_id; attempt to parse
                $j = json_decode($resp, true);
                if (is_array($j) && isset($j['data'][0]['id'])) $result['message_id'] = $j['data'][0]['id'];
                return $result;
            }
            $result['error'] = $err ?: 'Mailersend HTTP ' . $httpCode;
        }

        // 2) SMTP via PHPMailer if credentials present
        $hasSmtp = !empty($smtpUser) && !empty($smtpPass) && strpos($smtpHost, 'example.com') === false;
        if ($hasSmtp) {
            try {
                if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
                    $autoload = __DIR__ . '/../vendor/autoload.php';
                    if (file_exists($autoload)) require_once $autoload;
                }
                if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
                    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
                    $mail->isSMTP();
                    $mail->Host = $smtpHost;
                    $mail->SMTPAuth = true;
                    $mail->Username = $smtpUser;
                    $mail->Password = $smtpPass;
                    if (defined('PHPMailer\\PHPMailer\\PHPMailer::ENCRYPTION_SMTPS') && strtolower($smtpEnc) === 'ssl') {
                        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
                    } else {
                        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                    }
                    $mail->Port = (int)$smtpPort;
                    $mail->setFrom($smtpFrom, $smtpFromName);
                    $mail->addAddress($to);
                    $mail->isHTML(true);
                    $mail->Subject = $subject;
                    $mail->Body = $htmlBody;
                    $mail->AltBody = $textBody ?: strip_tags($htmlBody);
                    $mail->send();
                    $result['success'] = true;
                    $result['method'] = 'smtp';
                    return $result;
                }
            } catch (Exception $e) {
                $result['error'] = 'PHPMailer error: ' . $e->getMessage();
            }
        }

        // 3) Fallback to PHP mail()
        $headers = "MIME-Version: 1.0\r\nContent-type:text/html;charset=UTF-8\r\nFrom: {$smtpFromName} <{$smtpFrom}>\r\n";
        $ok = @mail($to, $subject, $htmlBody, $headers);
        if ($ok) {
            $result['success'] = true;
            $result['method'] = 'mail';
            return $result;
        }

        return $result;
    }
}
