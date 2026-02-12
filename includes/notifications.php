<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/mail_env.php';

/**
 * Create an in-app notification record
 */
function createNotification(PDO $pdo, int $userId, ?int $blotterId, string $notificationType, string $title, string $message)
{
    $sql = "INSERT INTO notifications (user_id, blotter_id, notification_type, title, message, created_at) VALUES (:user_id, :blotter_id, :notification_type, :title, :message, NOW())";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':user_id' => $userId,
        ':blotter_id' => $blotterId,
        ':notification_type' => $notificationType,
        ':title' => $title,
        ':message' => $message
    ]);

    return $pdo->lastInsertId();
}

/**
 * Send an email (simple wrapper around PHPMailer)
 */
function sendEmailNotification(string $toEmail, string $subject, string $body, ?string $replyTo = null) {
    try {
        $mail = new PHPMailer(true);
        // SMTP config from environment
        $mail->isSMTP();
        $mail->Host = getenv('SMTP_HOST');
        $mail->SMTPAuth = true;
        $mail->Username = getenv('SMTP_USER');
        $mail->Password = getenv('SMTP_PASS');
        $mail->SMTPSecure = 'tls';
        $mail->Port = intval(getenv('SMTP_PORT') ?: 587);

        $from = getenv('SMTP_FROM') ?: 'noreply@localhost';
        $fromName = getenv('SMTP_FROM_NAME') ?: 'System Notifications';

        $mail->setFrom($from, $fromName);
        if (!empty($replyTo)) {
            // Set Reply-To header so replies are directed to respondent email if provided
            $mail->addReplyTo($replyTo);
        }
        $mail->addAddress($toEmail);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Email send failed: ' . $e->getMessage());
        return false;
    }
}
