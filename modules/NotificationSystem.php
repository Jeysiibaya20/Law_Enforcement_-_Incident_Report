<?php
/**
 * Notification System for Law Enforcement Incident Reporting
 * 
 * Handles:
 * - Real-time notifications for Barangay Officials
 * - Case notifications and escalations
 * - Review request notifications
 * - Officer assignment notifications
 * - Alert broadcasting
 * 
 * @author Law Enforcement System
 * @version 1.0.0
 */

class NotificationSystem {
    
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Send case notification to Barangay Official
     */
    public function notifyBarangayOfficial($incident_id, $incident_data, $nlp_analysis, $barangay_official_id = null) {
        try {
            // If no specific official provided, get all Barangay Officials
            if ($barangay_official_id === null) {
                $sql = "SELECT user_id FROM signup WHERE role IN ('Barangay Official', 'Admin') AND is_active = 1";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute();
                $officials = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $officials = [['user_id' => $barangay_official_id]];
            }
            
            // Determine urgency for title
            $title_prefix = '';
            if ($nlp_analysis['threat_level'] === 'Critical') {
                $title_prefix = '🚨 CRITICAL - ';
            } elseif ($nlp_analysis['threat_level'] === 'High') {
                $title_prefix = '⚠️ HIGH PRIORITY - ';
            }
            
            $title = $title_prefix . 'Case #' . ($incident_data['case_no'] ?? 'N/A') . ' - ' . ($incident_data['incident_type'] ?? 'Incident');
            
            $message = $this->generateIncidentNotificationMessage($incident_data, $nlp_analysis);
            
            // Create notification for each official
            foreach ($officials as $official) {
                $sql = "INSERT INTO notifications (
                        user_id, incident_id, notification_type, title, message, 
                        threat_level, urgency, is_read, created_at
                    ) VALUES (
                        :user_id, :incident_id, :notification_type, :title,
                        :message, :threat_level, :urgency, 0, NOW()
                    )";
                
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([
                    ':user_id' => $official['user_id'],
                    ':incident_id' => $incident_id,
                    ':notification_type' => 'Case Notification',
                    ':title' => $title,
                    ':message' => $message,
                    ':threat_level' => $nlp_analysis['threat_level'],
                    ':urgency' => $this->mapThreatLevelToUrgency($nlp_analysis['threat_level'])
                ]);
                
                // For critical cases, also send email or SMS alert
                if ($nlp_analysis['threat_level'] === 'Critical') {
                    $this->sendUrgentAlert($official['user_id'], $title, $message);
                }
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log('Error notifying Barangay Official: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Notify officer of case assignment
     */
    public function notifyOfficerAssignment($incident_id, $assigned_officer_id, $incident_data, $nlp_analysis) {
        try {
            $title = 'New Case Assignment - #' . ($incident_data['case_no'] ?? 'N/A');
            
            $message = "You have been assigned to:\n\n";
            $message .= "📋 Case: " . ($incident_data['case_no'] ?? 'N/A') . "\n";
            $message .= "🏷️ Type: " . ($incident_data['incident_type'] ?? 'Unknown') . "\n";
            $message .= "📍 Location: " . ($incident_data['location'] ?? 'Not specified') . "\n";
            $message .= "🚨 Threat Level: " . $nlp_analysis['threat_level'] . "\n";
            $message .= "⏰ Date: " . ($incident_data['incident_date'] ?? 'N/A') . "\n\n";
            $message .= "Action Required: Review details and begin investigation.";
            
            $sql = "INSERT INTO notifications (
                    user_id, incident_id, notification_type, title, message,
                    threat_level, urgency, is_read, created_at
                ) VALUES (
                    :user_id, :incident_id, :notification_type, :title,
                    :message, :threat_level, :urgency, 0, NOW()
                )";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':user_id' => $assigned_officer_id,
                ':incident_id' => $incident_id,
                ':notification_type' => 'Case Assignment',
                ':title' => $title,
                ':message' => $message,
                ':threat_level' => $nlp_analysis['threat_level'],
                ':urgency' => $this->mapThreatLevelToUrgency($nlp_analysis['threat_level'])
            ]);
            
            return true;
            
        } catch (Exception $e) {
            error_log('Error notifying officer: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generate detailed incident notification message for Barangay Official
     */
    private function generateIncidentNotificationMessage($incident_data, $nlp_analysis) {
        $lines = [];
        
        $lines[] = "📋 **Incident Report Notification**";
        $lines[] = "";
        $lines[] = "**Case Number:** " . ($incident_data['case_no'] ?? 'N/A');
        $lines[] = "**Type:** " . ($incident_data['incident_type'] ?? 'Unknown');
        $lines[] = "**Location:** " . ($incident_data['location'] ?? 'Not specified');
        $lines[] = "**Date/Time:** " . ($incident_data['incident_date'] ?? 'N/A') . " " . ($incident_data['incident_time'] ?? '');
        $lines[] = "";
        
        $lines[] = "👤 **Reporter Information:**";
        $lines[] = "• Name: " . ($incident_data['reporter_name'] ?? 'Unknown');
        $lines[] = "• Type: " . ($incident_data['reporter_type'] ?? 'Unknown');
        $lines[] = "• Contact: " . ($incident_data['reporter_phone'] ?? 'Not provided');
        $lines[] = "";
        
        if (!empty($incident_data['victim_name'])) {
            $lines[] = "🚨 **Victim Information:**";
            $lines[] = "• Name: " . $incident_data['victim_name'];
            if ($incident_data['victim_age'] ?? null) $lines[] = "• Age: " . $incident_data['victim_age'];
            if ($incident_data['victim_gender'] ?? null) $lines[] = "• Gender: " . $incident_data['victim_gender'];
            $lines[] = "";
        }
        
        if (!empty($incident_data['suspect_name'])) {
            $lines[] = "⚠️ **Suspect Information:**";
            $lines[] = "• Name: " . $incident_data['suspect_name'];
            $lines[] = "";
        }
        
        $lines[] = "🤖 **AI Analysis:**";
        $lines[] = "• Threat Level: " . $nlp_analysis['threat_level'];
        $lines[] = "• Severity Score: " . number_format($nlp_analysis['severity_score'], 1) . "/100";
        $lines[] = "• Sentiment: " . $nlp_analysis['sentiment']['sentiment'];
        $lines[] = "• Confidence: " . number_format($nlp_analysis['confidence_score'], 1) . "%";
        
        if (!empty($nlp_analysis['emotions'])) {
            $lines[] = "• Emotions Detected: " . implode(', ', array_slice($nlp_analysis['emotions'], 0, 3));
        }
        $lines[] = "";
        
        if (!empty($nlp_analysis['actionable_items'])) {
            $lines[] = "✅ **Recommended Actions:**";
            foreach (array_slice($nlp_analysis['actionable_items'], 0, 3) as $item) {
                $lines[] = "• " . ucfirst($item);
            }
            $lines[] = "";
        }
        
        $lines[] = "⏱️ **Priority Response:** " . $this->mapThreatLevelToUrgency($nlp_analysis['threat_level']);
        $lines[] = "📅 **Report Time:** " . date('M d, Y H:i:s');
        
        return implode("\n", $lines);
    }
    
    /**
     * Send urgent alert for critical cases (email/SMS)
     */
    private function sendUrgentAlert($user_id, $title, $message) {
        try {
            // Get user contact information
            $sql = "SELECT emailadd AS email, phone_number FROM signup WHERE user_id = :user_id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':user_id' => $user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                return false;
            }
            
            // Send email alert
            if (!empty($user['email'])) {
                $this->sendEmailAlert($user['email'], $title, $message);
            }
            
            // In production, could also send SMS via service like Twilio
            // $this->sendSMSAlert($user['phone_number'], $title);
            
            return true;
            
        } catch (Exception $e) {
            error_log('Error sending urgent alert: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send email notification
     */
    private function sendEmailAlert($email, $title, $message) {
        try {
            // Use PHPMailer if available
            if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                $mail = new \PHPMailer\PHPMailer\PHPMailer();
                
                // Configure based on mail_env.php settings
                if (defined('SMTP_HOST')) {
                    $mail->isSMTP();
                    $mail->Host = SMTP_HOST;
                    $mail->SMTPAuth = true;
                    $mail->Username = SMTP_USER;
                    $mail->Password = SMTP_PASS;
                    $mail->SMTPSecure = SMTP_SECURE;
                    $mail->Port = SMTP_PORT;
                }
                
                $mail->setFrom('alerts@lawenforcement.local', 'Law Enforcement Incident System');
                $mail->addAddress($email);
                $mail->Subject = $title;
                $mail->Body = $message;
                $mail->isHTML(false);
                
                return $mail->send();
            }
            
            // Fallback to PHP mail()
            $headers = "From: alerts@lawenforcement.local\r\n";
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
            
            return mail($email, $title, $message, $headers);
            
        } catch (Exception $e) {
            error_log('Email sending failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Notify about review request
     */
    public function notifyReviewRequest($incident_id, $requested_by, $reason, $assigned_to_user_id = null) {
        try {
            // If assigned to specific user
            if ($assigned_to_user_id) {
                $recipients = [['user_id' => $assigned_to_user_id]];
            } else {
                // Notify all admins
                $sql = "SELECT user_id FROM signup WHERE role = 'Admin' AND is_active = 1";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute();
                $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            
            // Get incident info
            $sql = "SELECT case_no, incident_type FROM incidents WHERE id = :incident_id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':incident_id' => $incident_id]);
            $incident = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $title = '📝 Review Requested - Case #' . ($incident['case_no'] ?? 'N/A');
            $message = "A review has been requested for case #" . ($incident['case_no'] ?? 'N/A') . "\n\n";
            $message .= "Reason: " . $reason . "\n\n";
            $message .= "Please review and take appropriate action.";
            
            foreach ($recipients as $recipient) {
                $sql = "INSERT INTO notifications (
                        user_id, incident_id, notification_type, title, message,
                        is_read, created_at
                    ) VALUES (
                        :user_id, :incident_id, :notification_type, :title,
                        :message, 0, NOW()
                    )";
                
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([
                    ':user_id' => $recipient['user_id'],
                    ':incident_id' => $incident_id,
                    ':notification_type' => 'Review Request',
                    ':title' => $title,
                    ':message' => $message
                ]);
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log('Error notifying review request: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get unread notifications for user
     */
    public function getUserNotifications($user_id, $limit = 10) {
        try {
            $sql = "SELECT * FROM notifications 
                    WHERE user_id = :user_id 
                    ORDER BY created_at DESC 
                    LIMIT :limit";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log('Error fetching notifications: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Mark notification as read
     */
    public function markNotificationAsRead($notification_id) {
        try {
            $sql = "UPDATE notifications SET is_read = 1 WHERE id = :id";
            $stmt = $this->pdo->prepare($sql);
            
            return $stmt->execute([':id' => $notification_id]);
            
        } catch (Exception $e) {
            error_log('Error marking notification: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get notification count for user
     */
    public function getUnreadNotificationCount($user_id) {
        try {
            $sql = "SELECT COUNT(*) as count FROM notifications 
                    WHERE user_id = :user_id AND is_read = 0";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':user_id' => $user_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result['count'] ?? 0;
            
        } catch (Exception $e) {
            error_log('Error getting notification count: ' . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Map threat level to urgency label
     */
    private function mapThreatLevelToUrgency($threat_level) {
        $mapping = [
            'Critical' => 'IMMEDIATE ACTION REQUIRED',
            'High' => 'Urgent - Within 24 Hours',
            'Medium' => 'Normal - Within 1 Week',
            'Low' => 'Low Priority'
        ];
        
        return $mapping[$threat_level] ?? 'Normal Priority';
    }
    
    /**
     * Broadcast alert to all relevant users based on incident type and severity
     */
    public function broadcastIncidentAlert($incident_id, $incident_data, $nlp_analysis) {
        try {
            // Notify Barangay Officials
            $this->notifyBarangayOfficial($incident_id, $incident_data, $nlp_analysis);
            
            // Notify assigned officer if exists
            if (!empty($incident_data['assigned_to'])) {
                $this->notifyOfficerAssignment($incident_id, $incident_data['assigned_to'], $incident_data, $nlp_analysis);
            }
            
            // For critical cases, notify all admins
            if ($nlp_analysis['threat_level'] === 'Critical') {
                $sql = "SELECT user_id FROM signup WHERE role = 'Admin' AND is_active = 1";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute();
                $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($admins as $admin) {
                    $this->notifyBarangayOfficial($incident_id, $incident_data, $nlp_analysis, $admin['user_id']);
                }
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log('Error broadcasting incident alert: ' . $e->getMessage());
            return false;
        }
    }
}

?>
