<?php
/**
 * IncidentReportTemplate - Template system for various report types
 * 
 * Provides structured templates for incident, case, and management reports
 */

require_once __DIR__ . '/ReportGenerator.php';

class IncidentReportTemplate extends ReportGenerator {
    
    private $template_type;
    private $incident_details = [];
    
    public function __construct($pdo, $template_type = 'standard') {
        parent::__construct($pdo);
        $this->template_type = $template_type;
    }
    
    /**
     * Get single incident detailed report
     */
    public function getDetailedIncidentReport($incident_id) {
        $sql = "SELECT 
                    i.*,
                    b.blotter_id,
                    b.status as blotter_status,
                    b.narrative as blotter_narrative,
                    ca.case_id,
                    ca.assigned_officer,
                    ca.assignment_reason,
                    u.fullname as assigned_officer_name,
                    u.emailadd as officer_email,
                    (SELECT phone_number FROM users WHERE user_id = ca.assigned_officer LIMIT 1) as officer_phone,
                    sw.suspect_count,
                    sw.witness_count
                FROM incidents i
                LEFT JOIN blotters b ON i.incident_id = b.incident_id
                LEFT JOIN case_assignments ca ON i.incident_id = ca.incident_id
                LEFT JOIN signup u ON ca.assigned_officer = u.user_id
                LEFT JOIN (
                    SELECT incident_id, 
                           COUNT(CASE WHEN type = 'suspect' THEN 1 END) as suspect_count,
                           COUNT(CASE WHEN type = 'witness' THEN 1 END) as witness_count
                    FROM suspect_witness
                    GROUP BY incident_id
                ) sw ON i.incident_id = sw.incident_id
                WHERE i.incident_id = ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$incident_id]);
        
        $this->incident_details = $stmt->fetch(PDO::FETCH_ASSOC);
        return $this->incident_details;
    }
    
    /**
     * Generate detailed incident HTML report
     */
    public function generateDetailedHtml($incident_id) {
        $this->getDetailedIncidentReport($incident_id);
        
        if (!$this->incident_details) {
            return '<div class="error">Incident not found</div>';
        }
        
        $html = $this->getHtmlHeader();
        $html .= $this->renderDetailedIncidentContent();
        $html .= $this->getHtmlFooter();
        
        return $html;
    }
    
    /**
     * Render detailed incident content
     */
    protected function renderDetailedIncidentContent() {
        $i = $this->incident_details;
        
        $html = '<div class="incident-details">';
        
        // Basic Information
        $html .= '<section class="report-section">';
        $html .= '<h2>📋 Incident Information</h2>';
        $html .= '<table class="detail-table">';
        $html .= '<tr><td>Incident ID:</td><td><strong>' . htmlspecialchars($i['incident_id']) . '</strong></td></tr>';
        $html .= '<tr><td>Type:</td><td>' . htmlspecialchars($i['incident_type']) . '</td></tr>';
        $html .= '<tr><td>Location:</td><td>' . htmlspecialchars($i['location']) . '</td></tr>';
        $html .= '<tr><td>Date/Time:</td><td>' . date('M d, Y H:i A', strtotime($i['created_at'])) . '</td></tr>';
        $html .= '<tr><td>Reported By:</td><td>' . htmlspecialchars($i['reported_by'] ?? 'N/A') . '</td></tr>';
        $html .= '</table>';
        $html .= '</section>';
        
        // Narrative
        $html .= '<section class="report-section">';
        $html .= '<h2>📝 Incident Narrative</h2>';
        $html .= '<div class="narrative-box">' . nl2br(htmlspecialchars($i['description'])) . '</div>';
        $html .= '</section>';
        
        // NLP Analysis
        $html .= '<section class="report-section">';
        $html .= '<h2>🧠 AI Analysis Results</h2>';
        $html .= '<table class="detail-table">';
        $html .= '<tr><td>Severity Score:</td><td><strong style="color: #e74c3c;">' . htmlspecialchars($i['nlp_severity'] ?? '-') . '/100</strong></td></tr>';
        $html .= '<tr><td>Threat Level:</td><td><strong style="color: #c0392b;">' . htmlspecialchars($i['nlp_threat_level'] ?? '-') . '</strong></td></tr>';
        $html .= '<tr><td>Sentiment:</td><td>' . htmlspecialchars($i['nlp_sentiment'] ?? '-') . '</td></tr>';
        $html .= '<tr><td>Confidence:</td><td>' . htmlspecialchars($i['nlp_confidence'] ?? '-') . '%</td></tr>';
        $html .= '<tr><td>Key Entities:</td><td>' . htmlspecialchars($i['nlp_entities'] ?? '-') . '</td></tr>';
        $html .= '</table>';
        $html .= '</section>';
        
        // Blotter Information
        if ($i['blotter_id']) {
            $html .= '<section class="report-section">';
            $html .= '<h2>📚 Blotter Entry</h2>';
            $html .= '<table class="detail-table">';
            $html .= '<tr><td>Blotter ID:</td><td>' . htmlspecialchars($i['blotter_id']) . '</td></tr>';
            $html .= '<tr><td>Status:</td><td><span class="status-badge status-' . strtolower($i['blotter_status']) . '">' . htmlspecialchars($i['blotter_status']) . '</span></td></tr>';
            $html .= '<tr><td>Narrative:</td><td>' . nl2br(htmlspecialchars(substr($i['blotter_narrative'], 0, 300) . '...')) . '</td></tr>';
            $html .= '</table>';
            $html .= '</section>';
        }
        
        // Case Assignment
        if ($i['case_id']) {
            $html .= '<section class="report-section">';
            $html .= '<h2>👮 Case Assignment</h2>';
            $html .= '<table class="detail-table">';
            $html .= '<tr><td>Case ID:</td><td>' . htmlspecialchars($i['case_id']) . '</td></tr>';
            $html .= '<tr><td>Assigned Officer:</td><td>' . htmlspecialchars($i['assigned_officer_name'] ?? 'Unassigned') . '</td></tr>';
            $html .= '<tr><td>Officer Email:</td><td>' . htmlspecialchars($i['officer_email'] ?? '-') . '</td></tr>';
            $html .= '<tr><td>Officer Phone:</td><td>' . htmlspecialchars($i['officer_phone'] ?? '-') . '</td></tr>';
            $html .= '<tr><td>Assignment Reason:</td><td>' . htmlspecialchars($i['assignment_reason'] ?? '-') . '</td></tr>';
            $html .= '</table>';
            $html .= '</section>';
        }
        
        // Suspects & Witnesses
        $html .= '<section class="report-section">';
        $html .= '<h2>👥 Involved Parties</h2>';
        $html .= '<table class="detail-table">';
        $html .= '<tr><td>Suspects:</td><td>' . ($i['suspect_count'] ?? 0) . '</td></tr>';
        $html .= '<tr><td>Witnesses:</td><td>' . ($i['witness_count'] ?? 0) . '</td></tr>';
        $html .= '</table>';
        $html .= '</section>';
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Generate printable incident card
     */
    public function generateIncidentCard($incident_id) {
        $this->getDetailedIncidentReport($incident_id);
        
        if (!$this->incident_details) {
            return null;
        }
        
        $i = $this->incident_details;
        
        $card = [
            'incident_id' => $i['incident_id'],
            'type' => $i['incident_type'],
            'location' => $i['location'],
            'date' => date('M d, Y', strtotime($i['created_at'])),
            'severity' => $i['nlp_severity'] ?? 'N/A',
            'threat_level' => $i['nlp_threat_level'] ?? 'N/A',
            'status' => $i['blotter_status'] ?? 'Pending',
            'assigned_to' => $i['assigned_officer_name'] ?? 'Unassigned',
            'summary' => substr($i['description'], 0, 200) . '...'
        ];
        
        return $card;
    }
    
    /**
     * Get summary template for multiple incidents
     */
    public function getSummaryTemplate() {
        $this->report_data = $this->getIncidentsData();
        $stats = $this->getSummaryStats();
        
        $html = $this->getHtmlHeader();
        $html .= $this->renderSummaryContent($stats);
        $html .= $this->getHtmlFooter();
        
        return $html;
    }
    
    /**
     * Render summary content
     */
    protected function renderSummaryContent($stats) {
        $html = '<div class="summary-report">';
        
        // Key Metrics
        $html .= '<section class="metrics-section">';
        $html .= '<h2>📊 Key Metrics</h2>';
        $html .= '<div class="metrics-grid">';
        $html .= '<div class="metric-card">';
        $html .= '<div class="metric-value">' . $stats['total_incidents'] . '</div>';
        $html .= '<div class="metric-label">Total Incidents</div>';
        $html .= '</div>';
        $html .= '<div class="metric-card">';
        $html .= '<div class="metric-value">' . $stats['assigned_count'] . '</div>';
        $html .= '<div class="metric-label">Assigned</div>';
        $html .= '</div>';
        $html .= '<div class="metric-card">';
        $html .= '<div class="metric-value">' . $stats['unassigned_count'] . '</div>';
        $html .= '<div class="metric-label">Unassigned</div>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</section>';
        
        // By Type
        $html .= '<section class="metrics-section">';
        $html .= '<h2>📋 By Type</h2>';
        $html .= '<table>';
        $html .= '<tr><th>Type</th><th>Count</th><th>%</th></tr>';
        foreach ($stats['by_type'] as $type => $count) {
            $percent = round(($count / $stats['total_incidents']) * 100, 2);
            $html .= '<tr><td>' . htmlspecialchars($type) . '</td><td>' . $count . '</td><td>' . $percent . '%</td></tr>';
        }
        $html .= '</table>';
        $html .= '</section>';
        
        // By Severity
        $html .= '<section class="metrics-section">';
        $html .= '<h2>⚠️ By Severity</h2>';
        $html .= '<table>';
        $html .= '<tr><th>Severity</th><th>Count</th><th>%</th></tr>';
        foreach ($stats['by_severity'] as $severity => $count) {
            $percent = round(($count / $stats['total_incidents']) * 100, 2);
            $html .= '<tr><td>' . ucfirst($severity) . '</td><td>' . $count . '</td><td>' . $percent . '%</td></tr>';
        }
        $html .= '</table>';
        $html .= '</section>';
        
        // By Status
        $html .= '<section class="metrics-section">';
        $html .= '<h2>✓ By Status</h2>';
        $html .= '<table>';
        $html .= '<tr><th>Status</th><th>Count</th><th>%</th></tr>';
        foreach ($stats['by_status'] as $status => $count) {
            $percent = round(($count / $stats['total_incidents']) * 100, 2);
            $html .= '<tr><td>' . htmlspecialchars($status) . '</td><td>' . $count . '</td><td>' . $percent . '%</td></tr>';
        }
        $html .= '</table>';
        $html .= '</section>';
        
        $html .= '</div>';
        
        return $html;
    }
}
