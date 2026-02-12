<?php
/**
 * ReportGenerator - Core Report Generation Engine
 * 
 * Handles all report generation, formatting, and export functionality
 * Supports multiple output formats: HTML, PDF, CSV, Excel
 */

class ReportGenerator {
    
    protected $pdo;
    protected $report_type;
    protected $date_from;
    protected $date_to;
    protected $filters = [];
    protected $report_data = [];
    protected $metadata = [];
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->date_from = date('Y-01-01');
        $this->date_to = date('Y-m-d');
    }
    
    /**
     * Set date range for report
     */
    public function setDateRange($from, $to) {
        $this->date_from = $from;
        $this->date_to = $to;
        return $this;
    }
    
    /**
     * Add filter to report (e.g., by type, officer, status)
     */
    public function addFilter($column, $value, $operator = '=') {
        $this->filters[] = [
            'column' => $column,
            'value' => $value,
            'operator' => $operator
        ];
        return $this;
    }
    
    /**
     * Set report metadata (title, author, etc.)
     */
    public function setMetadata($key, $value) {
        $this->metadata[$key] = $value;
        return $this;
    }
    
    /**
     * Build WHERE clause from filters
     */
    protected function buildWhereClause($base_column = 'created_at') {
        $where = "WHERE {$base_column} BETWEEN '{$this->date_from}' AND '{$this->date_to}'";
        
        foreach ($this->filters as $filter) {
            $where .= " AND {$filter['column']} {$filter['operator']} '{$filter['value']}'";
        }
        
        return $where;
    }
    
    /**
     * Get basic incident data
     */
    protected function getIncidentsData() {
        $where = $this->buildWhereClause('i.created_at');
        
        $sql = "SELECT 
                    i.id AS incident_id,
                    i.incident_type,
                    i.location,
                    i.narrative AS description,
                    i.nlp_severity_score AS severity_level,
                    i.nlp_summary AS nlp_severity,
                    i.nlp_threat_level,
                    i.created_at,
                    i.created_by,
                    b.id AS blotter_id,
                    b.status as blotter_status,
                    ca.assigned_to AS assigned_officer,
                    u.fullname as officer_name,
                    u.emailadd as officer_email
                FROM incidents i
                LEFT JOIN blotters b ON i.id = b.incident_id
                LEFT JOIN case_assignments ca ON i.id = ca.incident_id
                LEFT JOIN signup u ON ca.assigned_to = u.user_id
                {$where}
                ORDER BY i.created_at DESC";
        
        try {
            return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("ReportGenerator::getIncidentsData SQL Error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Generate HTML report
     */
    public function generateHtml() {
        $this->report_data = $this->getIncidentsData();
        
        $html = $this->getHtmlHeader();
        $html .= $this->renderReportContent();
        $html .= $this->getHtmlFooter();
        
        return $html;
    }
    
    /**
     * Get HTML header
     */
    protected function getHtmlHeader() {
        $title = $this->metadata['title'] ?? 'Incident Report';
        $date_range = "{$this->date_from} to {$this->date_to}";
        
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title}</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 20px;
            color: #333;
            background: #f5f5f5;
        }
        .report-container {
            background: white;
            padding: 30px;
            margin: 0 auto;
            max-width: 1200px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            border-radius: 8px;
        }
        .report-header {
            text-align: center;
            border-bottom: 3px solid #2c3e50;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .report-header h1 {
            margin: 0;
            color: #2c3e50;
            font-size: 28px;
        }
        .report-header .date-range {
            color: #666;
            font-size: 14px;
            margin-top: 10px;
        }
        .metadata {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 30px;
            padding: 15px;
            background: #f9f9f9;
            border-radius: 5px;
        }
        .metadata-item {
            font-size: 14px;
        }
        .metadata-item strong {
            color: #2c3e50;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        table th {
            background: #34495e;
            color: white;
            padding: 12px;
            text-align: left;
            font-weight: 600;
        }
        table td {
            padding: 10px 12px;
            border-bottom: 1px solid #ddd;
        }
        table tr:hover {
            background: #f5f5f5;
        }
        .severity-low { color: #27ae60; font-weight: bold; }
        .severity-medium { color: #f39c12; font-weight: bold; }
        .severity-high { color: #e74c3c; font-weight: bold; }
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-open { background: #3498db; color: white; }
        .status-in-progress { background: #f39c12; color: white; }
        .status-closed { background: #27ae60; color: white; }
        .print-button {
            margin: 20px 0;
            text-align: right;
        }
        .print-button button {
            background: #34495e;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
        }
        .print-button button:hover {
            background: #2c3e50;
        }
        @media print {
            body { background: white; margin: 0; }
            .report-container { box-shadow: none; }
            .print-button { display: none; }
        }
    </style>
</head>
<body>
    <div class="report-container">
        <div class="print-button">
            <button onclick="window.print()">🖨️ Print Report</button>
        </div>
        <div class="report-header">
            <h1>{$title}</h1>
            <div class="date-range">Period: {$date_range}</div>
        </div>
        {$this->renderMetadata()}
HTML;
    }
    
    /**
     * Render metadata section
     */
    protected function renderMetadata() {
        if (empty($this->metadata)) {
            return '';
        }
        
        $html = '<div class="metadata">';
        foreach ($this->metadata as $key => $value) {
            if ($key !== 'title') {
                $html .= "<div class='metadata-item'><strong>{$key}:</strong> {$value}</div>";
            }
        }
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Render report content (to be overridden by child classes)
     */
    protected function renderReportContent() {
        $html = '<table>';
        $html .= '<thead><tr>
                    <th>Incident ID</th>
                    <th>Type</th>
                    <th>Location</th>
                    <th>Severity</th>
                    <th>Status</th>
                    <th>Assigned Officer</th>
                    <th>Date</th>
                  </tr></thead>';
        $html .= '<tbody>';
        
        foreach ($this->report_data as $incident) {
            $severity_class = 'severity-' . strtolower($incident['severity_level'] ?? 'medium');
            $status_class = 'status-' . strtolower(str_replace(' ', '-', $incident['blotter_status'] ?? 'open'));
            
            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($incident['incident_id']) . '</td>';
            $html .= '<td>' . htmlspecialchars($incident['incident_type']) . '</td>';
            $html .= '<td>' . htmlspecialchars($incident['location'] ?? '-') . '</td>';
            $html .= '<td><span class="' . $severity_class . '">' . htmlspecialchars($incident['nlp_severity'] ?? $incident['severity_level'] ?? '-') . '</span></td>';
            $html .= '<td><span class="status-badge ' . $status_class . '">' . htmlspecialchars($incident['blotter_status'] ?? 'N/A') . '</span></td>';
            $html .= '<td>' . htmlspecialchars($incident['officer_name'] ?? 'Unassigned') . '</td>';
            $html .= '<td>' . date('M d, Y', strtotime($incident['created_at'])) . '</td>';
            $html .= '</tr>';
        }
        
        $html .= '</tbody></table>';
        
        return $html;
    }
    
    /**
     * Get HTML footer
     */
    protected function getHtmlFooter() {
        $generated_date = date('Y-m-d H:i:s');
        $generated_by = $this->metadata['generated_by'] ?? 'System';
        
        return <<<HTML
        <hr style="margin: 30px 0; border: none; border-top: 1px solid #ddd;">
        <div style="font-size: 12px; color: #999; text-align: right;">
            <p>Generated on {$generated_date} by {$generated_by}</p>
        </div>
    </div>
</body>
</html>
HTML;
    }
    
    /**
     * Generate CSV report
     */
    public function generateCsv($filename = 'report.csv') {
        $this->report_data = $this->getIncidentsData();
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // Write BOM for UTF-8 Excel compatibility
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
        
        // Headers
        fputcsv($output, [
            'Incident ID',
            'Type',
            'Location',
            'Description',
            'Severity (NLP)',
            'Threat Level',
            'Status',
            'Assigned Officer',
            'Date Created'
        ]);
        
        // Data rows
        foreach ($this->report_data as $incident) {
            fputcsv($output, [
                $incident['incident_id'],
                $incident['incident_type'],
                $incident['location'] ?? '-',
                substr($incident['description'], 0, 100),
                $incident['nlp_severity'] ?? $incident['severity_level'],
                $incident['nlp_threat_level'] ?? '-',
                $incident['blotter_status'] ?? 'N/A',
                $incident['officer_name'] ?? 'Unassigned',
                date('Y-m-d H:i:s', strtotime($incident['created_at']))
            ]);
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Get report data as array
     */
    public function getReportData() {
        if (empty($this->report_data)) {
            $this->report_data = $this->getIncidentsData();
        }
        return $this->report_data;
    }
    
    /**
     * Get summary statistics
     */
    public function getSummaryStats() {
        $data = $this->getIncidentsData();
        
        $stats = [
            'total_incidents' => count($data),
            'by_type' => [],
            'by_severity' => [
                'low' => 0,
                'medium' => 0,
                'high' => 0
            ],
            'by_status' => [],
            'assigned_count' => 0,
            'unassigned_count' => 0
        ];
        
        foreach ($data as $incident) {
            // By type
            $type = $incident['incident_type'] ?? 'Unknown';
            $stats['by_type'][$type] = ($stats['by_type'][$type] ?? 0) + 1;
            
            // By severity
            $severity = strtolower($incident['nlp_severity'] ?? $incident['severity_level'] ?? 'medium');
            if (strpos($severity, 'low') !== false) {
                $stats['by_severity']['low']++;
            } elseif (strpos($severity, 'high') !== false) {
                $stats['by_severity']['high']++;
            } else {
                $stats['by_severity']['medium']++;
            }
            
            // By status
            $status = $incident['blotter_status'] ?? 'Unknown';
            $stats['by_status'][$status] = ($stats['by_status'][$status] ?? 0) + 1;
            
            // Assignment
            if ($incident['assigned_officer']) {
                $stats['assigned_count']++;
            } else {
                $stats['unassigned_count']++;
            }
        }
        
        return $stats;
    }
}
