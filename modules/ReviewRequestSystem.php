<?php
/**
 * Case Review Request System
 * 
 * Handles review requests for incidents:
 * - BCPC Officer → Review Request → System → Barangay Official
 * - Tracks review status
 * - Manages review timeline
 * 
 * @author Law Enforcement System
 * @version 1.0.0
 */

class ReviewRequestSystem {
    
    private $pdo;
    private $notification_system;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        require_once __DIR__ . '/NotificationSystem.php';
        $this->notification_system = new NotificationSystem($pdo);
    }
    
    /**
     * Create a review request for an incident
     */
    public function createReviewRequest($incident_id, $requested_by, $reason, $priority = 'Normal') {
        try {
            $review_request_id = $this->pdo->lastInsertId();
            
            $sql = "INSERT INTO review_requests (
                    incident_id, requested_by, reason, priority, status, created_at
                ) VALUES (
                    :incident_id, :requested_by, :reason, :priority, 'Pending', NOW()
                )";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':incident_id' => $incident_id,
                ':requested_by' => $requested_by,
                ':reason' => $reason,
                ':priority' => $priority
            ]);
            
            $review_request_id = $this->pdo->lastInsertId();
            
            // Update incident status
            $sql_update = "UPDATE incidents SET review_requested = 1, review_requested_at = NOW() WHERE id = :incident_id";
            $stmt = $this->pdo->prepare($sql_update);
            $stmt->execute([':incident_id' => $incident_id]);
            
            // Log event
            $this->logReviewEvent($incident_id, 'Review Requested', $reason, $requested_by);
            
            // Notify relevant officials
            $this->notifyReviewRequest($incident_id, $requested_by, $reason);
            
            return [
                'success' => true,
                'review_request_id' => $review_request_id,
                'message' => 'Review request created successfully'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Respond to a review request
     */
    public function respondToReviewRequest($review_request_id, $responded_by, $response, $findings = '', $recommendations = '') {
        try {
            $sql = "UPDATE review_requests SET 
                    response = :response, 
                    responded_by = :responded_by, 
                    findings = :findings,
                    recommendations = :recommendations,
                    responded_at = NOW(),
                    status = 'Completed'
                    WHERE id = :review_request_id";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':review_request_id' => $review_request_id,
                ':response' => $response,
                ':responded_by' => $responded_by,
                ':findings' => $findings,
                ':recommendations' => $recommendations
            ]);
            
            // Get incident ID
            $sql_get = "SELECT incident_id FROM review_requests WHERE id = :review_request_id";
            $stmt = $this->pdo->prepare($sql_get);
            $stmt->execute([':review_request_id' => $review_request_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $incident_id = $result['incident_id'];
            
            // Update incident
            $sql_incident = "UPDATE incidents SET review_completed = 1, review_completed_at = NOW() WHERE id = :incident_id";
            $stmt = $this->pdo->prepare($sql_incident);
            $stmt->execute([':incident_id' => $incident_id]);
            
            // Log event
            $this->logReviewEvent($incident_id, 'Review Completed', 'Response: ' . $response, $responded_by);
            
            // Notify requestor
            $this->notifyReviewResponse($incident_id, $response, $findings);
            
            return [
                'success' => true,
                'message' => 'Review response recorded successfully'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get all review requests for an incident
     */
    public function getIncidentReviewRequests($incident_id) {
        try {
            $sql = "SELECT 
                    rr.*,
                    u1.fullname as requested_by_name,
                    u1.username as requested_by_username,
                    u2.fullname as responded_by_name,
                    u2.username as responded_by_username
                FROM review_requests rr
                LEFT JOIN signup u1 ON rr.requested_by = u1.user_id
                LEFT JOIN signup u2 ON rr.responded_by = u2.user_id
                WHERE rr.incident_id = :incident_id
                ORDER BY rr.created_at DESC";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':incident_id' => $incident_id]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log('Error fetching review requests: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get pending review requests
     */
    public function getPendingReviewRequests($user_id = null) {
        try {
            $sql = "SELECT 
                    rr.*,
                    i.case_no,
                    i.incident_type,
                    i.location,
                    u.fullname as requested_by_name
                FROM review_requests rr
                JOIN incidents i ON rr.incident_id = i.id
                LEFT JOIN signup u ON rr.requested_by = u.user_id
                WHERE rr.status = 'Pending'";
            
            $params = [];
            
            if ($user_id !== null) {
                $sql .= " AND rr.responded_by = :user_id";
                $params[':user_id'] = $user_id;
            }
            
            $sql .= " ORDER BY 
                    CASE WHEN rr.priority = 'High' THEN 1
                         WHEN rr.priority = 'Normal' THEN 2
                         ELSE 3 END,
                    rr.created_at ASC";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log('Error fetching pending reviews: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get review statistics
     */
    public function getReviewStatistics() {
        try {
            $sql = "SELECT 
                    COUNT(*) as total_reviews,
                    SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending_reviews,
                    SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed_reviews,
                    SUM(CASE WHEN priority = 'High' THEN 1 ELSE 0 END) as high_priority_reviews,
                    AVG(CASE WHEN status = 'Completed' THEN DATEDIFF(responded_at, created_at) END) as avg_response_time_days
                FROM review_requests";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log('Error fetching review statistics: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Log review event for audit trail
     */
    private function logReviewEvent($incident_id, $event_type, $description, $user_id) {
        try {
            $sql = "INSERT INTO workflow_events (
                    incident_id, event_type, description, performed_by, created_at
                ) VALUES (
                    :incident_id, :event_type, :description, :performed_by, NOW()
                )";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':incident_id' => $incident_id,
                ':event_type' => 'Review - ' . $event_type,
                ':description' => $description,
                ':performed_by' => $user_id
            ]);
            
            return true;
            
        } catch (Exception $e) {
            error_log('Error logging review event: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Notify about review request
     */
    private function notifyReviewRequest($incident_id, $requested_by, $reason) {
        try {
            // Get incident info
            $sql = "SELECT case_no, incident_type FROM incidents WHERE id = :incident_id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':incident_id' => $incident_id]);
            $incident = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get requester info
            $sql_req = "SELECT fullname FROM signup WHERE user_id = :user_id";
            $stmt = $this->pdo->prepare($sql_req);
            $stmt->execute([':user_id' => $requested_by]);
            $requester = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Notify all admins
            $sql_admins = "SELECT user_id FROM signup WHERE role = 'Admin' AND is_active = 1";
            $stmt = $this->pdo->prepare($sql_admins);
            $stmt->execute();
            $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $title = '📋 Review Request - Case #' . ($incident['case_no'] ?? 'N/A');
            $message = "Review requested by " . ($requester['fullname'] ?? 'Unknown') . "\n\n";
            $message .= "Case: #" . ($incident['case_no'] ?? 'N/A') . " (" . ($incident['incident_type'] ?? 'Unknown') . ")\n";
            $message .= "Reason: " . $reason . "\n\n";
            $message .= "Please review and provide your findings.";
            
            foreach ($admins as $admin) {
                $sql = "INSERT INTO notifications (
                        user_id, incident_id, notification_type, title, message,
                        is_read, created_at
                    ) VALUES (
                        :user_id, :incident_id, :notification_type, :title,
                        :message, 0, NOW()
                    )";
                
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([
                    ':user_id' => $admin['user_id'],
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
     * Notify about review response
     */
    private function notifyReviewResponse($incident_id, $response, $findings) {
        try {
            // Get review request to notify original requester
            $sql = "SELECT requested_by FROM review_requests WHERE incident_id = :incident_id ORDER BY created_at DESC LIMIT 1";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':incident_id' => $incident_id]);
            $review = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$review) {
                return false;
            }
            
            $title = '✅ Review Response - Case #';
            $message = "Your review request has been responded to.\n\n";
            $message .= "Response: " . $response . "\n\n";
            if ($findings) {
                $message .= "Findings: " . $findings;
            }
            
            $sql = "INSERT INTO notifications (
                    user_id, incident_id, notification_type, title, message,
                    is_read, created_at
                ) VALUES (
                    :user_id, :incident_id, :notification_type, :title,
                    :message, 0, NOW()
                )";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':user_id' => $review['requested_by'],
                ':incident_id' => $incident_id,
                ':notification_type' => 'Review Response',
                ':title' => $title,
                ':message' => $message
            ]);
            
            return true;
            
        } catch (Exception $e) {
            error_log('Error notifying review response: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Export review request as PDF or document
     */
    public function exportReviewRequest($review_request_id) {
        try {
            $sql = "SELECT 
                    rr.*,
                    i.case_no,
                    i.incident_type,
                    i.location,
                    i.incident_date,
                    u1.fullname as requested_by_name,
                    u2.fullname as responded_by_name
                FROM review_requests rr
                JOIN incidents i ON rr.incident_id = i.id
                LEFT JOIN signup u1 ON rr.requested_by = u1.user_id
                LEFT JOIN signup u2 ON rr.responded_by = u2.user_id
                WHERE rr.id = :review_request_id";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':review_request_id' => $review_request_id]);
            
            $review = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$review) {
                return null;
            }
            
            // Format for export
            $export_data = [
                'title' => 'Case Review Request Report',
                'case_number' => $review['case_no'],
                'incident_type' => $review['incident_type'],
                'location' => $review['location'],
                'incident_date' => $review['incident_date'],
                'requested_by' => $review['requested_by_name'],
                'requested_at' => $review['created_at'],
                'reason' => $review['reason'],
                'priority' => $review['priority'],
                'status' => $review['status'],
                'responded_by' => $review['responded_by_name'],
                'responded_at' => $review['responded_at'],
                'response' => $review['response'],
                'findings' => $review['findings'],
                'recommendations' => $review['recommendations']
            ];
            
            return $export_data;
            
        } catch (Exception $e) {
            error_log('Error exporting review request: ' . $e->getMessage());
            return null;
        }
    }
}

?>
