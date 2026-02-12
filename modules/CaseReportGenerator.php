<?php
/**
 * CaseReportGenerator - Generates comprehensive case reports
 * 
 * Creates detailed case status reports with findings, recommendations, and timelines
 */

class CaseReportGenerator extends ReportGenerator {
    
    private $case_details = [];
    
    public function __construct($pdo) {
        parent::__construct($pdo);
    }
    
    /**
     * Get complete case report data
     */
    public function getCaseReportData($case_id) {
        // Get case basic info
        $sql = "SELECT 
                    ca.case_id,
                    ca.incident_id,
                    ca.assigned_officer,
                    ca.assignment_reason,
                    ca.created_at as assigned_date,
                    ca.status,
                    ca.auto_assigned,
                    u.fullname as assigned_to,
                    u.emailadd as officer_email,
                    u.phone as officer_phone
                FROM case_assignments ca
                LEFT JOIN signup u ON ca.assigned_officer = u.user_id
                WHERE ca.case_id = ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$case_id]);
        $case = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$case) {
            return null;
        }
        
        // Get incident data
        $incident_sql = "SELECT * FROM incidents WHERE incident_id = ?";
        $stmt = $this->pdo->prepare($incident_sql);
        $stmt->execute([$case['incident_id']]);
        $case['incident'] = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get blotter data
        $blotter_sql = "SELECT * FROM blotters WHERE incident_id = ?";
        $stmt = $this->pdo->prepare($blotter_sql);
        $stmt->execute([$case['incident_id']]);
        $case['blotter'] = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get suspects
        $suspects_sql = "SELECT * FROM suspect_witness WHERE incident_id = ? AND type = 'suspect'";
        $stmt = $this->pdo->prepare($suspects_sql);
        $stmt->execute([$case['incident_id']]);
        $case['suspects'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get witnesses
        $witnesses_sql = "SELECT * FROM suspect_witness WHERE incident_id = ? AND type = 'witness'";
        $stmt = $this->pdo->prepare($witnesses_sql);
        $stmt->execute([$case['incident_id']]);
        $case['witnesses'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get review requests
        $review_sql = "SELECT * FROM review_requests WHERE incident_id = ? ORDER BY created_at DESC";
        $stmt = $this->pdo->prepare($review_sql);
        $stmt->execute([$case['incident_id']]);
        $case['reviews'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get workflow events
        $events_sql = "SELECT * FROM workflow_events WHERE incident_id = ? ORDER BY created_at ASC";
        $stmt = $this->pdo->prepare($events_sql);
        $stmt->execute([$case['incident_id']]);
        $case['workflow_events'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $this->case_details = $case;
        return $case;
    }
    
    /**
     * Generate comprehensive case HTML report
     */
    public function generateCaseHtml($case_id) {
        $this->getCaseReportData($case_id);
        
        if (!$this->case_details) {
            return '<div class="error">Case not found</div>';
        }
        
        $html = $this->getHtmlHeader();
        $html .= $this->renderCaseContent();
        $html .= $this->getHtmlFooter();
        
        return $html;
    }
    
    /**
     * Render case report content
     */
    protected function renderCaseContent() {
        $case = $this->case_details;
        $incident = $case['incident'];
        $blotter = $case['blotter'];
        
        $html = '<div class="case-report">';
        
        // Case Header
        $html .= '<section class="report-section">';
        $html .= '<h2>📁 Case Information</h2>';
        $html .= '<table class="detail-table">';
        $html .= '<tr><td>Case ID:</td><td><strong>' . htmlspecialchars($case['case_id']) . '</strong></td>';
        $html .= '<td>Incident ID:</td><td>' . htmlspecialchars($case['incident_id']) . '</td></tr>';
        $html .= '<tr><td>Status:</td><td><span class="status-badge status-' . strtolower($case['status']) . '">' . htmlspecialchars($case['status']) . '</span></td>';
        $html .= '<td>Assigned Date:</td><td>' . date('M d, Y', strtotime($case['assigned_date'])) . '</td></tr>';
        $html .= '<tr><td>Assigned To:</td><td><strong>' . htmlspecialchars($case['assigned_to'] ?? 'Unassigned') . '</strong></td>';
        $html .= '<td>Auto-Assigned:</td><td>' . ($case['auto_assigned'] ? 'Yes' : 'No') . '</td></tr>';
        $html .= '</table>';
        $html .= '</section>';
        
        // Incident Details
        $html .= '<section class="report-section">';
        $html .= '<h2>📋 Incident Details</h2>';
        $html .= '<table class="detail-table">';
        $html .= '<tr><td>Type:</td><td>' . htmlspecialchars($incident['incident_type']) . '</td>';
        $html .= '<td>Location:</td><td>' . htmlspecialchars($incident['location']) . '</td></tr>';
        $html .= '<tr><td>Date/Time:</td><td>' . date('M d, Y H:i A', strtotime($incident['created_at'])) . '</td>';
        $html .= '<td>Severity (NLP):</td><td><strong style="color: #e74c3c;">' . htmlspecialchars($incident['nlp_severity'] ?? '-') . '/100</strong></td></tr>';
        $html .= '<tr><td>Threat Level:</td><td><strong style="color: #c0392b;">' . htmlspecialchars($incident['nlp_threat_level'] ?? '-') . '</strong></td>';
        $html .= '<td>Sentiment:</td><td>' . htmlspecialchars($incident['nlp_sentiment'] ?? '-') . '</td></tr>';
        $html .= '</table>';
        $html .= '</section>';
        
        // Narrative
        $html .= '<section class="report-section">';
        $html .= '<h2>📝 Narrative</h2>';
        $html .= '<div class="narrative-box">' . nl2br(htmlspecialchars($incident['description'])) . '</div>';
        $html .= '</section>';
        
        // Suspects
        if (!empty($case['suspects'])) {
            $html .= '<section class="report-section">';
            $html .= '<h2>👤 Suspects (' . count($case['suspects']) . ')</h2>';
            $html .= '<table>';
            $html .= '<tr><th>Name</th><th>Age</th><th>Gender</th><th>Contact</th></tr>';
            foreach ($case['suspects'] as $suspect) {
                $html .= '<tr>';
                $html .= '<td>' . htmlspecialchars($suspect['name']) . '</td>';
                $html .= '<td>' . htmlspecialchars($suspect['age'] ?? '-') . '</td>';
                $html .= '<td>' . htmlspecialchars($suspect['gender'] ?? '-') . '</td>';
                $html .= '<td>' . htmlspecialchars($suspect['contact_info'] ?? '-') . '</td>';
                $html .= '</tr>';
            }
            $html .= '</table>';
            $html .= '</section>';
        }
        
        // Witnesses
        if (!empty($case['witnesses'])) {
            $html .= '<section class="report-section">';
            $html .= '<h2>👁️ Witnesses (' . count($case['witnesses']) . ')</h2>';
            $html .= '<table>';
            $html .= '<tr><th>Name</th><th>Age</th><th>Gender</th><th>Contact</th></tr>';
            foreach ($case['witnesses'] as $witness) {
                $html .= '<tr>';
                $html .= '<td>' . htmlspecialchars($witness['name']) . '</td>';
                $html .= '<td>' . htmlspecialchars($witness['age'] ?? '-') . '</td>';
                $html .= '<td>' . htmlspecialchars($witness['gender'] ?? '-') . '</td>';
                $html .= '<td>' . htmlspecialchars($witness['contact_info'] ?? '-') . '</td>';
                $html .= '</tr>';
            }
            $html .= '</table>';
            $html .= '</section>';
        }
        
        // Blotter Status
        if ($blotter) {
            $html .= '<section class="report-section">';
            $html .= '<h2>📚 Blotter Entry</h2>';
            $html .= '<table class="detail-table">';
            $html .= '<tr><td>Blotter ID:</td><td>' . htmlspecialchars($blotter['blotter_id']) . '</td>';
            $html .= '<td>Status:</td><td><span class="status-badge status-' . strtolower($blotter['status']) . '">' . htmlspecialchars($blotter['status']) . '</span></td></tr>';
            $html .= '<tr><td colspan="4">Narrative: ' . htmlspecialchars(substr($blotter['narrative'], 0, 500)) . '...</td></tr>';
            $html .= '</table>';
            $html .= '</section>';
        }
        
        // Review Requests
        if (!empty($case['reviews'])) {
            $html .= '<section class="report-section">';
            $html .= '<h2>🔍 Review History</h2>';
            $html .= '<table>';
            $html .= '<tr><th>Date</th><th>Type</th><th>Status</th><th>Findings</th></tr>';
            foreach ($case['reviews'] as $review) {
                $html .= '<tr>';
                $html .= '<td>' . date('M d, Y', strtotime($review['created_at'])) . '</td>';
                $html .= '<td>' . htmlspecialchars($review['review_type'] ?? '-') . '</td>';
                $html .= '<td><span class="status-badge status-' . strtolower($review['status'] ?? 'pending') . '">' . htmlspecialchars($review['status'] ?? 'Pending') . '</span></td>';
                $html .= '<td>' . htmlspecialchars(substr($review['findings'] ?? '-', 0, 100)) . '</td>';
                $html .= '</tr>';
            }
            $html .= '</table>';
            $html .= '</section>';
        }
        
        // Workflow Timeline
        if (!empty($case['workflow_events'])) {
            $html .= '<section class="report-section">';
            $html .= '<h2>📅 Workflow Timeline</h2>';
            $html .= '<div class="timeline">';
            foreach ($case['workflow_events'] as $event) {
                $html .= '<div class="timeline-item">';
                $html .= '<div class="timeline-date">' . date('M d, Y H:i', strtotime($event['created_at'])) . '</div>';
                $html .= '<div class="timeline-event">' . htmlspecialchars($event['event_type']) . '</div>';
                $html .= '<div class="timeline-description">' . htmlspecialchars($event['description']) . '</div>';
                $html .= '</div>';
            }
            $html .= '</div>';
            $html .= '</section>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Generate case status summary
     */
    public function getCaseStatusSummary() {
        $sql = "SELECT 
                    status,
                    COUNT(*) as count,
                    ROUND(COUNT(*) / (SELECT COUNT(*) FROM case_assignments) * 100, 2) as percentage
                FROM case_assignments
                GROUP BY status
                ORDER BY count DESC";
        
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get cases by assignment status
     */
    public function getCasesByAssignmentStatus() {
        $sql = "SELECT 
                    CASE WHEN assigned_officer IS NULL THEN 'Unassigned' ELSE 'Assigned' END as status,
                    COUNT(*) as count
                FROM case_assignments
                GROUP BY CASE WHEN assigned_officer IS NULL THEN 'Unassigned' ELSE 'Assigned' END";
        
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }
}
