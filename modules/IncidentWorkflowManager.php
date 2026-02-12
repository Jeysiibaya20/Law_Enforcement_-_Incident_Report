<?php
/**
 * Incident Workflow Automation System
 * 
 * Implements the DFD Level 0 flows:
 * 1. BCPC Officer → Incident Report → Incident Logging System → Digital Blotter
 * 2. Complainant → Incident Information → Incident Logging System
 * 3. Case Notification → Barangay Official
 * 4. Review Request handling
 * 
 * @author Law Enforcement System
 * @version 1.0.0
 */

class IncidentWorkflowManager {
    
    private $pdo;
    private $nlp;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        require_once 'NaturalLanguageProcessor.php';
    }
    
    /**
     * Process incident report and create complete workflow
     * Handles: Classification → Blotter Entry → Notifications → Case Assignment
     */
    public function processIncidentReport($incident_data) {
        try {
            $this->pdo->beginTransaction();
            
            // Step 1: Apply NLP analysis to narrative
            $nlp_analysis = $this->performNLPAnalysis($incident_data);
            
            // Step 2: Insert incident with NLP data
            $incident_id = $this->createIncidentRecord($incident_data, $nlp_analysis);
            
            // Step 3: Auto-create blotter entry for digital blotter system
            $blotter_id = $this->createBlotterEntry($incident_id, $incident_data, $nlp_analysis);
            
            // Step 4: Generate case notification for relevant officials
            $this->generateCaseNotifications($incident_id, $incident_data, $nlp_analysis);
            
            // Step 5: Auto-assign to appropriate officer if urgency is high
            $this->autoAssignCase($incident_id, $incident_data, $nlp_analysis);
            
            // Step 6: Create case record in case management system
            $case_id = $this->createCaseRecord($incident_id, $incident_data, $nlp_analysis);
            
            // Step 7: Log workflow event
            $this->logWorkflowEvent($incident_id, 'Incident Created & Processed', 'System automatically processed incident through NLP and workflow');
            
            $this->pdo->commit();
            
            return [
                'success' => true,
                'incident_id' => $incident_id,
                'blotter_id' => $blotter_id,
                'case_id' => $case_id,
                'nlp_analysis' => $nlp_analysis,
                'message' => 'Incident processed and entered into workflow'
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Step 1: Perform NLP analysis on the incident narrative
     */
    private function performNLPAnalysis($incident_data) {
        $nlp = new NaturalLanguageProcessor();
        return $nlp->analyzeIncident(
            $incident_data['narrative'] ?? '',
            $incident_data['incident_type'] ?? ''
        );
    }
    
    /**
     * Step 2: Create incident record with NLP data
     */
    private function createIncidentRecord($incident_data, $nlp_analysis) {
        $sql = "INSERT INTO incidents (
                case_no, incident_type, incident_subtype, auto_classification, 
                urgency_level, is_high_risk, reporter_name, reporter_email, 
                reporter_phone, reporter_type, incident_date, incident_time, 
                location, latitude, longitude, narrative, evidence_description, 
                victim_name, victim_age, victim_gender, suspect_name, status, 
                created_by, nlp_sentiment, nlp_threat_level, nlp_severity_score,
                nlp_emotions, nlp_analysis_data, nlp_confidence_score, nlp_summary
            ) VALUES (
                :case_no, :incident_type, :incident_subtype, :auto_classification,
                :urgency_level, :is_high_risk, :reporter_name, :reporter_email,
                :reporter_phone, :reporter_type, :incident_date, :incident_time,
                :location, :latitude, :longitude, :narrative, :evidence_description,
                :victim_name, :victim_age, :victim_gender, :suspect_name, :status,
                :created_by, :nlp_sentiment, :nlp_threat_level, :nlp_severity_score,
                :nlp_emotions, :nlp_analysis_data, :nlp_confidence_score, :nlp_summary
            )";
        
        $stmt = $this->pdo->prepare($sql);
        $params = [
            ':case_no' => $incident_data['case_no'],
            ':incident_type' => $incident_data['incident_type'] ?? 'Other',
            ':incident_subtype' => $incident_data['incident_subtype'] ?? '',
            ':auto_classification' => $incident_data['auto_classification'] ?? 'Other',
            ':urgency_level' => $incident_data['urgency_level'] ?? 'Medium',
            ':is_high_risk' => $incident_data['is_high_risk'] ?? 0,
            ':reporter_name' => $incident_data['reporter_name'] ?? '',
            ':reporter_email' => $incident_data['reporter_email'] ?? '',
            ':reporter_phone' => $incident_data['reporter_phone'] ?? '',
            ':reporter_type' => $incident_data['reporter_type'] ?? 'Citizen',
            ':incident_date' => $incident_data['incident_date'] ?? date('Y-m-d'),
            ':incident_time' => $incident_data['incident_time'] ?? '00:00',
            ':location' => $incident_data['location'] ?? '',
            ':latitude' => $incident_data['latitude'] ?? null,
            ':longitude' => $incident_data['longitude'] ?? null,
            ':narrative' => $incident_data['narrative'] ?? '',
            ':evidence_description' => $incident_data['evidence_description'] ?? '',
            ':victim_name' => $incident_data['victim_name'] ?? '',
            ':victim_age' => $incident_data['victim_age'] ?? null,
            ':victim_gender' => $incident_data['victim_gender'] ?? null,
            ':suspect_name' => $incident_data['suspect_name'] ?? '',
            ':status' => 'Submitted',
            ':created_by' => $incident_data['created_by'] ?? null,
            ':nlp_sentiment' => $nlp_analysis['sentiment']['sentiment'] ?? 'Neutral',
            ':nlp_threat_level' => $nlp_analysis['threat_level'] ?? 'Low',
            ':nlp_severity_score' => $nlp_analysis['severity_score'] ?? 0,
            ':nlp_emotions' => json_encode($nlp_analysis['emotions'] ?? []),
            ':nlp_analysis_data' => json_encode($nlp_analysis),
            ':nlp_confidence_score' => $nlp_analysis['confidence_score'] ?? 0,
            ':nlp_summary' => NaturalLanguageProcessor::generateNLPSummary($nlp_analysis)
        ];
        
        if (!$stmt->execute($params)) {
            throw new Exception('Failed to create incident record');
        }
        
        return $this->pdo->lastInsertId();
    }
    
    /**
     * Step 3: Auto-create blotter entry for Digital Blotter System
     */
    private function createBlotterEntry($incident_id, $incident_data, $nlp_analysis) {
        $blotter_no = $this->generateBlotterNumber();
        
        $sql = "INSERT INTO blotters (
                blotter_no, incident_id, complainant_name, respondent_name, 
                incident_type, incident_date, incident_time, location, 
                description, status, priority, created_from_incident
            ) VALUES (
                :blotter_no, :incident_id, :complainant_name, :respondent_name,
                :incident_type, :incident_date, :incident_time, :location,
                :description, :status, :priority, 1
            )";
        
        $stmt = $this->pdo->prepare($sql);
        
        // Determine priority based on NLP analysis
        $priority = 'Low';
        if ($nlp_analysis['threat_level'] === 'Critical') {
            $priority = 'High';
        } elseif ($nlp_analysis['threat_level'] === 'High') {
            $priority = 'Medium';
        }
        
        $params = [
            ':blotter_no' => $blotter_no,
            ':incident_id' => $incident_id,
            ':complainant_name' => $incident_data['reporter_name'] ?? '',
            ':respondent_name' => $incident_data['suspect_name'] ?? 'Unknown',
            ':incident_type' => $incident_data['incident_type'] ?? 'Other',
            ':incident_date' => $incident_data['incident_date'] ?? date('Y-m-d'),
            ':incident_time' => $incident_data['incident_time'] ?? null,
            ':location' => $incident_data['location'] ?? '',
            ':description' => substr($incident_data['narrative'] ?? '', 0, 500),
            ':status' => 'Pending',
            ':priority' => $priority
        ];
        
        if (!$stmt->execute($params)) {
            throw new Exception('Failed to create blotter entry');
        }
        
        return $this->pdo->lastInsertId();
    }
    
    /**
     * Step 4: Generate notifications for Barangay Officials and case workers
     */
    private function generateCaseNotifications($incident_id, $incident_data, $nlp_analysis) {
        // Check if notifications table exists
        if (!$this->tableExists('notifications')) {
            error_log('Notifications table not found - skipping notifications');
            return true;
        }
        
        // Get Barangay Official IDs (users with role = 'Barangay Official')
        $sql_officials = "SELECT user_id FROM signup WHERE role = 'Barangay Official' OR role = 'Admin'";
        $stmt = $this->pdo->prepare($sql_officials);
        $stmt->execute();
        $officials = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $notification_type = 'New Case Filed';
        if ($nlp_analysis['threat_level'] === 'Critical') {
            $notification_type = 'Critical Case - Immediate Action Required';
        }
        
        $message = sprintf(
            "New incident report filed: %s\nLocation: %s\nReporter: %s\nThreat Level: %s",
            $incident_data['incident_type'] ?? 'Unknown',
            $incident_data['location'] ?? 'Not specified',
            $incident_data['reporter_name'] ?? 'Unknown',
            $nlp_analysis['threat_level']
        );
        
        // Create notifications for each official
        foreach ($officials as $official) {
            $sql = "INSERT INTO notifications (
                    user_id, incident_id, notification_type, title, message, 
                    is_read, created_at
                ) VALUES (
                    :user_id, :incident_id, :notification_type, :title, 
                    :message, 0, NOW()
                )";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':user_id' => $official['user_id'],
                ':incident_id' => $incident_id,
                ':notification_type' => $notification_type,
                ':title' => 'Case #' . $incident_data['case_no'] . ' - ' . $notification_type,
                ':message' => $message
            ]);
        }
        
        return true;
    }
    
    /**
     * Step 5: Auto-assign case to appropriate officer based on urgency and availability
     */
    private function autoAssignCase($incident_id, $incident_data, $nlp_analysis) {
        if ($nlp_analysis['threat_level'] !== 'Critical' && $nlp_analysis['threat_level'] !== 'High') {
            return false; // Only auto-assign critical and high priority
        }
        
        // Find available officer with lowest case load
        $sql = "SELECT bo.user_id, bo.barangay, bo.current_case_load
                FROM bcpc_officers bo
                JOIN signup u ON bo.user_id = u.user_id
                WHERE bo.is_available = 1 
                AND bo.current_case_load < bo.max_case_load
                ORDER BY bo.current_case_load ASC
                LIMIT 1";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        $officer = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($officer) {
            // Update incident with assignment
            $sql_update = "UPDATE incidents SET assigned_to = :assigned_to WHERE id = :incident_id";
            $stmt = $this->pdo->prepare($sql_update);
            $stmt->execute([
                ':assigned_to' => $officer['user_id'],
                ':incident_id' => $incident_id
            ]);
            
            // Increment officer's case load
            $sql_load = "UPDATE bcpc_officers SET current_case_load = current_case_load + 1 WHERE user_id = :user_id";
            $stmt = $this->pdo->prepare($sql_load);
            $stmt->execute([':user_id' => $officer['user_id']]);
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Step 6: Create case record in case management system
     */
    private function createCaseRecord($incident_id, $incident_data, $nlp_analysis) {
        $sql = "INSERT INTO case_assignments (
                case_number, incident_type, complainant_name, respondent_name,
                location, incident_date, incident_time, description, priority,
                status, assigned_by, nlp_threat_level, nlp_severity_score
            ) VALUES (
                :case_number, :incident_type, :complainant_name, :respondent_name,
                :location, :incident_date, :incident_time, :description, :priority,
                :status, :assigned_by, :nlp_threat_level, :nlp_severity_score
            )";
        
        $stmt = $this->pdo->prepare($sql);
        
        // Determine priority
        $priority = 'Low';
        if ($nlp_analysis['threat_level'] === 'Critical') {
            $priority = 'High';
        } elseif ($nlp_analysis['threat_level'] === 'High') {
            $priority = 'Medium';
        }
        
        $params = [
            ':case_number' => $incident_data['case_no'],
            ':incident_type' => $incident_data['incident_type'] ?? 'Other',
            ':complainant_name' => $incident_data['reporter_name'] ?? '',
            ':respondent_name' => $incident_data['suspect_name'] ?? 'Unknown',
            ':location' => $incident_data['location'] ?? '',
            ':incident_date' => $incident_data['incident_date'] ?? date('Y-m-d'),
            ':incident_time' => $incident_data['incident_time'] ?? null,
            ':description' => substr($incident_data['narrative'] ?? '', 0, 1000),
            ':priority' => $priority,
            ':status' => 'New',
            ':assigned_by' => $incident_data['created_by'] ?? 1,
            ':nlp_threat_level' => $nlp_analysis['threat_level'],
            ':nlp_severity_score' => $nlp_analysis['severity_score']
        ];
        
        if (!$stmt->execute($params)) {
            throw new Exception('Failed to create case record');
        }
        
        return $this->pdo->lastInsertId();
    }
    
    /**
     * Step 7: Log workflow event for audit trail
     */
    private function logWorkflowEvent($incident_id, $event_type, $description, $user_id = null) {
        // Check if workflow_events table exists
        if (!$this->tableExists('workflow_events')) {
            error_log('Workflow events table not found - skipping logging');
            return true;
        }
        
        $sql = "INSERT INTO workflow_events (
                incident_id, event_type, description, performed_by, created_at
            ) VALUES (
                :incident_id, :event_type, :description, :performed_by, NOW()
            )";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':incident_id' => $incident_id,
            ':event_type' => $event_type,
            ':description' => $description,
            ':performed_by' => $user_id  // NULL for system actions
        ]);
        
        return true;
    }
    
    /**
     * Helper: Check if table exists
     */
    private function tableExists($table_name) {
        try {
            $result = $this->pdo->query("SELECT 1 FROM {$table_name} LIMIT 1");
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Generate unique blotter number
     */
    private function generateBlotterNumber() {
        return 'BLT-' . date('Ymd') . '-' . strtoupper(substr(md5(time() . rand()), 0, 5));
    }
    
    /**
     * Handle review request for case
     */
    public function createReviewRequest($incident_id, $requested_by, $reason) {
        try {
            $sql = "INSERT INTO review_requests (
                    incident_id, requested_by, reason, status, created_at
                ) VALUES (
                    :incident_id, :requested_by, :reason, 'Pending', NOW()
                )";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':incident_id' => $incident_id,
                ':requested_by' => $requested_by,
                ':reason' => $reason
            ]);
            
            $this->logWorkflowEvent($incident_id, 'Review Requested', $reason, $requested_by);
            
            return $this->pdo->lastInsertId();
            
        } catch (Exception $e) {
            throw new Exception('Failed to create review request: ' . $e->getMessage());
        }
    }
    
    /**
     * Get incident status with full workflow context
     */
    public function getIncidentWorkflowStatus($incident_id) {
        $sql = "SELECT 
                i.*, 
                COUNT(n.id) as total_notifications,
                SUM(CASE WHEN n.is_read = 0 THEN 1 ELSE 0 END) as unread_notifications
            FROM incidents i
            LEFT JOIN notifications n ON i.id = n.incident_id
            WHERE i.id = :incident_id
            GROUP BY i.id";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':incident_id' => $incident_id]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

?>
