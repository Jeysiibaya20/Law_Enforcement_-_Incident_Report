<?php
/**
 * Admin Notification System for NLP-Analyzed Incidents
 * 
 * This file is called when a new incident is created with NLP analysis.
 * It automatically notifies all admin users of high-severity incidents.
 */

session_start();
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function getAppUrl(): string {
    $url = trim(getenv('APP_URL') ?: '');
    if ($url !== '') {
        return rtrim($url, '/');
    }

    $scheme = 'http';
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        $scheme = 'https';
    } elseif (!empty($_SERVER['REQUEST_SCHEME']) && $_SERVER['REQUEST_SCHEME'] === 'https') {
        $scheme = 'https';
    }

    $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? '');
    return rtrim($scheme . '://' . $host, '/');
}

class IncidentNLPNotifier {
    
    /**
     * Notify admins of a new incident with NLP analysis
     */
    public static function notifyAdminsOfIncident($incident_data) {
        try {
            // Extract incident details
            $incident_id = $incident_data['incident_id'] ?? null;
            $case_no = $incident_data['case_no'] ?? '';
            $location = $incident_data['location'] ?? '';
            $narrative = $incident_data['narrative'] ?? '';
            $nlp_threat_level = $incident_data['nlp_threat_level'] ?? 'Low';
            $nlp_severity_score = $incident_data['nlp_severity_score'] ?? 0;
            $nlp_sentiment = $incident_data['nlp_sentiment'] ?? 'Neutral';
            $incident_type = $incident_data['incident_type'] ?? 'Other';
            $reporter_name = $incident_data['reporter_name'] ?? 'Unknown';
            
            if (!$incident_id) {
                error_log("IncidentNLPNotifier: Missing incident_id");
                return false;
            }

            // 1. Create in-app notifications for all admins
            self::createAdminNotifications($incident_id, $case_no, $location, $nlp_threat_level, $nlp_severity_score);

            // 2. Send email notifications to high-severity incidents
            if (in_array($nlp_threat_level, ['High', 'Critical']) || $nlp_severity_score >= 70) {
                self::sendEmailNotifications($incident_id, $case_no, $location, $narrative, $nlp_threat_level, $nlp_severity_score, $nlp_sentiment, $incident_type, $reporter_name);
            }

            return true;
            
        } catch (Exception $e) {
            error_log("IncidentNLPNotifier Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Create in-app notifications in the database
     */
    private static function createAdminNotifications($incident_id, $case_no, $location, $threat_level, $severity_score) {
        global $pdo;
        
        try {
            // Get all admin users
            $admin_query = $pdo->prepare("SELECT user_id FROM signup WHERE role = 'Admin'");
            $admin_query->execute();
            $admins = $admin_query->fetchAll(PDO::FETCH_ASSOC);

            if (empty($admins)) {
                error_log("IncidentNLPNotifier: No admin users found");
                return;
            }

            $notification_type = match($threat_level) {
                'Critical' => 'critical_incident',
                'High' => 'high_severity_incident',
                default => 'incident_reported'
            };

            $title = "New Incident Reported - {$case_no}";
            $message = "Location: {$location}\n"
                     . "Threat Level: {$threat_level}\n"
                     . "Severity Score: {$severity_score}%\n"
                     . "Type: NLP Analyzed\n\n"
                     . "Click to view full incident details.";

            // Insert notification for each admin
            $notify_stmt = $pdo->prepare(
                "INSERT INTO notifications (user_id, incident_id, notification_type, title, message, threat_level, urgency, is_read, created_at)
                 VALUES (:user_id, :incident_id, :notification_type, :title, :message, :threat_level, :urgency, 0, NOW())"
            );

            foreach ($admins as $admin) {
                $notify_stmt->execute([
                    ':user_id' => $admin['user_id'],
                    ':incident_id' => $incident_id,
                    ':notification_type' => $notification_type,
                    ':title' => $title,
                    ':message' => $message,
                    ':threat_level' => $threat_level,
                    ':urgency' => ($threat_level === 'Critical' ? 'Immediate' : ($threat_level === 'High' ? 'Urgent' : 'Normal'))
                ]);
            }

            error_log("IncidentNLPNotifier: Created " . count($admins) . " in-app notifications for incident {$incident_id}");
            return true;

        } catch (Exception $e) {
            error_log("IncidentNLPNotifier - Notification Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send email notifications to admins for high-severity incidents
     */
    private static function sendEmailNotifications($incident_id, $case_no, $location, $narrative, $threat_level, $severity_score, $sentiment, $incident_type, $reporter_name) {
        global $pdo;
        
        try {
            // Get all admin emails
            $admin_query = $pdo->prepare("SELECT user_id, emailadd, fullname FROM signup WHERE role = 'Admin'");
            $admin_query->execute();
            $admins = $admin_query->fetchAll(PDO::FETCH_ASSOC);

            if (empty($admins)) {
                error_log("IncidentNLPNotifier: No admin emails found");
                return false;
            }

            $mail = new PHPMailer(true);
            
            // SMTP Configuration
            $mail->isSMTP();
            $mail->Host = getenv('SMTP_HOST') ?: 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = getenv('SMTP_USER') ?: 'alertaraqc@gmail.com';
            $mail->Password = getenv('SMTP_PASS') ?: 'fyyzywptnqlqemyt';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port = 465;

            $mail->setFrom(
                getenv('SMTP_FROM') ?: 'alertaraqc@gmail.com',
                getenv('SMTP_FROM_NAME') ?: 'Alertara System'
            );

            $mail->isHTML(true);
            $mail->Subject = "🚨 HIGH-PRIORITY INCIDENT - {$case_no}";

            $appUrl = getAppUrl();

            // Create professional HTML email
            $html_body = "
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background: " . ($threat_level === 'Critical' ? '#dc3545' : '#ff9800') . "; color: white; padding: 15px; border-radius: 5px; }
                        .section { margin: 15px 0; padding: 10px; border-left: 4px solid #667eea; }
                        .label { font-weight: bold; color: #333; }
                        .value { color: #666; }
                        .threat-badge { 
                            display: inline-block; 
                            padding: 5px 10px; 
                            border-radius: 20px; 
                            color: white; 
                            font-weight: bold;
                            background: " . ($threat_level === 'Critical' ? '#dc3545' : ($threat_level === 'High' ? '#ff9800' : '#28a745')) . ";
                        }
                        .button { 
                            display: inline-block; 
                            background: #667eea; 
                            color: white; 
                            padding: 12px 24px; 
                            border-radius: 5px; 
                            text-decoration: none; 
                            margin-top: 15px;
                        }
                        .narrative { 
                            background: #f5f5f5; 
                            padding: 10px; 
                            border-radius: 5px; 
                            border-left: 4px solid #dc3545;
                            margin: 10px 0;
                        }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h2>🚨 NEW HIGH-SEVERITY INCIDENT</h2>
                            <p>NLP Analysis Complete - Immediate Review Required</p>
                        </div>

                        <div class='section'>
                            <p class='label'>Incident Case Number:</p>
                            <p class='value'>{$case_no}</p>
                        </div>

                        <div class='section'>
                            <p class='label'>Location:</p>
                            <p class='value'>{$location}</p>
                        </div>

                        <div class='section'>
                            <p class='label'>Reporter:</p>
                            <p class='value'>{$reporter_name}</p>
                        </div>

                        <div class='section'>
                            <p class='label'>Incident Type:</p>
                            <p class='value'>{$incident_type}</p>
                        </div>

                        <div class='section'>
                            <p class='label'>NLP Analysis Results:</p>
                            <p>
                                <strong>Threat Level:</strong> <span class='threat-badge'>{$threat_level}</span><br>
                                <strong>Severity Score:</strong> {$severity_score}%<br>
                                <strong>Sentiment:</strong> {$sentiment}
                            </p>
                        </div>

                        <div class='section'>
                            <p class='label'>Incident Description:</p>
                            <div class='narrative'>{$narrative}</div>
                        </div>

                        <div class='section'>
                            <a href='{$appUrl}/admin/dashboard.php' class='button'>
                                View the Admin Dashboard
                            </a>
                        </div>

                        <hr style='border: none; border-top: 1px solid #eee; margin: 20px 0;'>
                        <p style='color: #999; font-size: 12px; text-align: center;'>
                            This is an automated notification from the Alertara Incident Report System.<br>
                            Threat Level: <strong>{$threat_level}</strong> | Severity: <strong>{$severity_score}%</strong>
                        </p>
                    </div>
                </body>
                </html>
            ";

            // Send to each admin
            $sent_count = 0;
            foreach ($admins as $admin) {
                try {
                    $mail->clearAddresses();
                    $mail->addAddress($admin['emailadd'], $admin['fullname']);
                    $mail->Body = str_replace('Alertara', 'Alertara - ' . $admin['fullname'], $html_body);
                    $mail->send();
                    $sent_count++;
                } catch (Exception $e) {
                    error_log("Failed to send email to {$admin['emailadd']}: " . $mail->ErrorInfo);
                }
            }

            error_log("IncidentNLPNotifier: Sent {$sent_count} email notifications for incident {$incident_id}");
            return true;

        } catch (Exception $e) {
            error_log("IncidentNLPNotifier - Email Error: " . $e->getMessage());
            return false;
        }
    }
}

?>
