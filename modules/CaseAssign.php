<?php
/**
 * Case Management Helper Functions
 * Law Enforcement Incident Report System
 */

// Database connection
require_once __DIR__ . '/../config/db_connect.php';

/**
 * Generate automatic case number
 * Format: CASE-YYYY-MM-DD-XXX (where XXX is sequential)
 */
function generateCaseNumber() {
    global $pdo;
    
    $date = date('Y-m-d');
    $prefix = "CASE-{$date}";
    
    try {
        // Get the highest case number for today
        $stmt = $pdo->prepare("SELECT case_number FROM case_assignments WHERE case_number LIKE ? ORDER BY case_number DESC LIMIT 1");
        $stmt->execute(["{$prefix}%"]);
        $last_case = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($last_case) {
            // Extract the sequence number and increment
            $parts = explode('-', $last_case['case_number']);
            $sequence = intval(end($parts)) + 1;
        } else {
            $sequence = 1;
        }
        
        return sprintf("%s-%03d", $prefix, $sequence);
    } catch (PDOException $e) {
        error_log("Error generating case number: " . $e->getMessage());
        return "{$prefix}-001";
    }
}

/**
 * Get available BCPC officers
 */
function getAvailableBCPCOfficers() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT bo.*, s.fullname, s.emailadd AS email, u.phone_number 
            FROM bcpc_officers bo
            JOIN signup s ON bo.user_id = s.user_id
            JOIN users u ON bo.user_id = u.user_id 
            WHERE bo.is_available = TRUE AND bo.current_case_load < bo.max_case_load 
            AND u.is_active = 1
            ORDER BY bo.current_case_load ASC, bo.rank ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting BCPC officers: " . $e->getMessage());
        return [];
    }
}

/**
 * Get all BCPC officers (including unavailable)
 */
function getAllBCPCOfficers() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT bo.*, s.fullname, s.emailadd AS email, u.phone_number 
            FROM bcpc_officers bo 
            JOIN signup s ON bo.user_id = s.user_id
            LEFT JOIN users u ON bo.user_id = u.user_id 
            ORDER BY bo.barangay, bo.rank
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting all BCPC officers: " . $e->getMessage());
        return [];
    }
}

/**
 * Create new case assignment
 */
function createCaseAssignment($data) {
    global $pdo;
    
    try {
        $case_number = generateCaseNumber();
        
        $stmt = $pdo->prepare("
            INSERT INTO case_assignments 
            (case_number, incident_type, complainant_name, respondent_name, location, 
             incident_date, incident_time, description, priority, assigned_by, assigned_to, 
             barangay_chairperson_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $case_number,
            $data['incident_type'],
            $data['complainant_name'],
            $data['respondent_name'],
            $data['location'],
            $data['incident_date'],
            $data['incident_time'],
            $data['description'],
            $data['priority'],
            $data['assigned_by'],
            $data['assigned_to'],
            $data['barangay_chairperson_id']
        ]);
        
        $case_id = $pdo->lastInsertId();
        
        // Update officer's case load
        if ($data['assigned_to']) {
            updateOfficerCaseLoad($data['assigned_to'], 1);
        }
        
        // Add to timeline
        addCaseTimeline($case_id, $case_number, 'Case Created', 'Case created and assigned', $data['assigned_by']);
        
        // Create notification for assigned officer
        if ($data['assigned_to']) {
            createNotification($data['assigned_to'], $case_id, $case_number, 'New Assignment', 
                'New Case Assigned', "You have been assigned a new case: {$case_number}");
        }
        
        return ['success' => true, 'case_id' => $case_id, 'case_number' => $case_number];
        
    } catch (PDOException $e) {
        error_log("Error creating case assignment: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Get case assignments with filters
 */
function getCaseAssignments($filters = []) {
    global $pdo;
    
    $where_conditions = [];
    $params = [];
    
    $sql = "
        SELECT ca.*, 
               s1.fullname as assigned_by_name,
               s2.fullname as assigned_to_name,
               s3.fullname as chairperson_name
        FROM case_assignments ca
        LEFT JOIN signup s1 ON ca.assigned_by = s1.user_id
        LEFT JOIN signup s2 ON ca.assigned_to = s2.user_id
        LEFT JOIN signup s3 ON ca.barangay_chairperson_id = s3.user_id
    ";
    
    if (!empty($filters['status'])) {
        $where_conditions[] = "ca.status = ?";
        $params[] = $filters['status'];
    }
    
    if (!empty($filters['assigned_to'])) {
        $where_conditions[] = "ca.assigned_to = ?";
        $params[] = $filters['assigned_to'];
    }
    
    if (!empty($filters['priority'])) {
        $where_conditions[] = "ca.priority = ?";
        $params[] = $filters['priority'];
    }
    
    if (!empty($filters['date_from'])) {
        $where_conditions[] = "ca.incident_date >= ?";
        $params[] = $filters['date_from'];
    }
    
    if (!empty($filters['date_to'])) {
        $where_conditions[] = "ca.incident_date <= ?";
        $params[] = $filters['date_to'];
    }
    
    if (!empty($where_conditions)) {
        $sql .= " WHERE " . implode(' AND ', $where_conditions);
    }
    
    $sql .= " ORDER BY ca.created_at DESC";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting case assignments: " . $e->getMessage());
        return [];
    }
}

/**
 * Update case status
 */
function updateCaseStatus($case_id, $new_status, $updated_by, $notes = '') {
    global $pdo;
    
    try {
        // Get current status
        $stmt = $pdo->prepare("SELECT status, case_number FROM case_assignments WHERE id = ?");
        $stmt->execute([$case_id]);
        $case = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$case) {
            return ['success' => false, 'error' => 'Case not found'];
        }
        
        $old_status = $case['status'];
        
        // Update case status
        $stmt = $pdo->prepare("UPDATE case_assignments SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$new_status, $case_id]);
        
        // Add status update record
        $stmt = $pdo->prepare("
            INSERT INTO case_updates 
            (case_id, case_number, update_type, previous_status, new_status, action_description, updated_by) 
            VALUES (?, ?, 'Status Change', ?, ?, ?, ?)
        ");
        $stmt->execute([$case_id, $case['case_number'], $old_status, $new_status, $notes, $updated_by]);
        
        // Add to timeline
        addCaseTimeline($case_id, $case['case_number'], 'Status Changed', 
            "Status changed from {$old_status} to {$new_status}. {$notes}", $updated_by);
        
        // Create notification for assigned officer
        $stmt = $pdo->prepare("SELECT assigned_to FROM case_assignments WHERE id = ?");
        $stmt->execute([$case_id]);
        $assigned_to = $stmt->fetchColumn();
        
        if ($assigned_to && $assigned_to != $updated_by) {
            createNotification($assigned_to, $case_id, $case['case_number'], 'Status Update', 
                'Case Status Updated', "Case {$case['case_number']} status updated to {$new_status}");
        }
        
        return ['success' => true];
        
    } catch (PDOException $e) {
        error_log("Error updating case status: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Add follow-up action
 */
function addFollowUpAction($case_id, $action_description, $updated_by) {
    global $pdo;
    
    try {
        // Get case number
        $stmt = $pdo->prepare("SELECT case_number, assigned_to FROM case_assignments WHERE id = ?");
        $stmt->execute([$case_id]);
        $case = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$case) {
            return ['success' => false, 'error' => 'Case not found'];
        }
        
        // Add follow-up record
        $stmt = $pdo->prepare("
            INSERT INTO case_updates 
            (case_id, case_number, update_type, action_description, updated_by) 
            VALUES (?, ?, 'Follow-up Action', ?, ?)
        ");
        $stmt->execute([$case_id, $case['case_number'], $action_description, $updated_by]);
        
        // Add to timeline
        addCaseTimeline($case_id, $case['case_number'], 'Follow-up', $action_description, $updated_by);
        
        // Create notification if needed
        if ($case['assigned_to'] && $case['assigned_to'] != $updated_by) {
            createNotification($case['assigned_to'], $case_id, $case['case_number'], 'Follow-up Required', 
                'Follow-up Action Required', "Follow-up required for case {$case['case_number']}: {$action_description}");
        }
        
        return ['success' => true];
        
    } catch (PDOException $e) {
        error_log("Error adding follow-up action: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Reassign case
 */
function reassignCase($case_id, $new_officer_id, $reassigned_by, $reason = '') {
    global $pdo;
    
    try {
        // Get current assignment
        $stmt = $pdo->prepare("SELECT assigned_to, case_number FROM case_assignments WHERE id = ?");
        $stmt->execute([$case_id]);
        $case = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$case) {
            return ['success' => false, 'error' => 'Case not found'];
        }
        
        $old_officer_id = $case['assigned_to'];
        
        // Update assignment
        $stmt = $pdo->prepare("UPDATE case_assignments SET assigned_to = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$new_officer_id, $case_id]);
        
        // Update case loads
        if ($old_officer_id) {
            updateOfficerCaseLoad($old_officer_id, -1);
        }
        updateOfficerCaseLoad($new_officer_id, 1);
        
        // Add reassignment record
        $stmt = $pdo->prepare("
            INSERT INTO case_updates 
            (case_id, case_number, update_type, action_description, updated_by) 
            VALUES (?, ?, 'Reassignment', ?, ?)
        ");
        $stmt->execute([$case_id, $case['case_number'], $reason, $reassigned_by]);
        
        // Add to timeline
        addCaseTimeline($case_id, $case['case_number'], 'Reassigned', 
            "Case reassigned. Reason: {$reason}", $reassigned_by);
        
        // Create notifications
        if ($new_officer_id) {
            createNotification($new_officer_id, $case_id, $case['case_number'], 'Reassignment', 
                'Case Reassigned to You', "Case {$case['case_number']} has been reassigned to you");
        }
        
        if ($old_officer_id && $old_officer_id != $reassigned_by) {
            createNotification($old_officer_id, $case_id, $case['case_number'], 'Reassignment', 
                'Case Reassigned from You', "Case {$case['case_number']} has been reassigned to another officer");
        }
        
        return ['success' => true];
        
    } catch (PDOException $e) {
        error_log("Error reassigning case: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Update officer case load
 */
function updateOfficerCaseLoad($officer_id, $change) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            UPDATE bcpc_officers 
            SET current_case_load = current_case_load + ?, 
                is_available = CASE 
                    WHEN current_case_load + ? >= max_case_load THEN FALSE 
                    ELSE is_available 
                END 
            WHERE user_id = ?
        ");
        $stmt->execute([$change, $change, $officer_id]);
    } catch (PDOException $e) {
        error_log("Error updating officer case load: " . $e->getMessage());
    }
}

/**
 * Add case timeline event
 */
function addCaseTimeline($case_id, $case_number, $event_type, $event_description, $performed_by) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO case_timeline 
            (case_id, case_number, event_type, event_description, performed_by) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$case_id, $case_number, $event_type, $event_description, $performed_by]);
    } catch (PDOException $e) {
        error_log("Error adding case timeline: " . $e->getMessage());
    }
}

/**
 * Create notification
 */
function createNotification($recipient_id, $case_id, $case_number, $notification_type, $title, $message) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO case_notifications 
            (recipient_id, case_id, case_number, notification_type, title, message) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$recipient_id, $case_id, $case_number, $notification_type, $title, $message]);
    } catch (PDOException $e) {
        error_log("Error creating notification: " . $e->getMessage());
    }
}

/**
 * Get case timeline
 */
function getCaseTimeline($case_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT ct.*, s.fullname as performed_by_name 
            FROM case_timeline ct
            LEFT JOIN signup s ON ct.performed_by = s.user_id
            WHERE ct.case_id = ? 
            ORDER BY ct.event_date ASC
        ");
        $stmt->execute([$case_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting case timeline: " . $e->getMessage());
        return [];
    }
}

/**
 * Get case updates
 */
function getCaseUpdates($case_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT cu.*, s.fullname as updated_by_name 
            FROM case_updates cu
            LEFT JOIN signup s ON cu.updated_by = s.user_id
            WHERE cu.case_id = ? 
            ORDER BY cu.updated_at DESC
        ");
        $stmt->execute([$case_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting case updates: " . $e->getMessage());
        return [];
    }
}

/**
 * Get user notifications
 */
function getUserNotifications($user_id, $unread_only = false) {
    global $pdo;
    
    try {
        $sql = "
            SELECT cn.*, ca.incident_type, ca.status 
            FROM case_notifications cn 
            LEFT JOIN case_assignments ca ON cn.case_id = ca.id 
            WHERE cn.recipient_id = ?
        ";
        
        if ($unread_only) {
            $sql .= " AND cn.is_read = FALSE";
        }
        
        $sql .= " ORDER BY cn.created_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting user notifications: " . $e->getMessage());
        return [];
    }
}

/**
 * Mark notification as read
 */
function markNotificationAsRead($notification_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("UPDATE case_notifications SET is_read = TRUE WHERE id = ?");
        $stmt->execute([$notification_id]);
        return true;
    } catch (PDOException $e) {
        error_log("Error marking notification as read: " . $e->getMessage());
        return false;
    }
}

/**
 * Get case statistics
 */
function getCaseStatistics() {
    global $pdo;
    
    try {
        $stats = [];
        
        // Total cases
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM case_assignments");
        $stmt->execute();
        $stats['total_cases'] = $stmt->fetchColumn();
        
        // Cases by status
        $stmt = $pdo->prepare("
            SELECT status, COUNT(*) as count 
            FROM case_assignments 
            GROUP BY status
        ");
        $stmt->execute();
        $status_counts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stats['by_status'] = [];
        foreach ($status_counts as $row) {
            $stats['by_status'][$row['status']] = $row['count'];
        }
        
        // Cases by priority
        $stmt = $pdo->prepare("
            SELECT priority, COUNT(*) as count 
            FROM case_assignments 
            GROUP BY priority
        ");
        $stmt->execute();
        $priority_counts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stats['by_priority'] = [];
        foreach ($priority_counts as $row) {
            $stats['by_priority'][$row['priority']] = $row['count'];
        }
        
        // Active officers
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM bcpc_officers WHERE is_available = TRUE");
        $stmt->execute();
        $stats['active_officers'] = $stmt->fetchColumn();
        
        return $stats;
        
    } catch (PDOException $e) {
        error_log("Error getting case statistics: " . $e->getMessage());
        return [];
    }
}
?>
