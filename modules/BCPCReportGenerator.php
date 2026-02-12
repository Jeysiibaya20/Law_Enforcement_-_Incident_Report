<?php
/**
 * BCPCReportGenerator - Generates official BCPC (Barangay Child Protection Committee) Reports
 * 
 * Creates comprehensive monthly reports for BCPC with child-related incident focus
 * Follows BCPC reporting standards and requirements
 */

class BCPCReportGenerator extends ReportGenerator {
    
    private $month;
    private $year;
    private $barangay;
    
    public function __construct($pdo, $month = null, $year = null) {
        parent::__construct($pdo);
        $this->month = $month ?? date('m');
        $this->year = $year ?? date('Y');
        $this->date_from = "{$this->year}-{$this->month}-01";
        $this->date_to = date('Y-m-t', strtotime($this->date_from));
    }
    
    /**
     * Set barangay information
     */
    public function setBarangay($name, $municipality = '', $province = '') {
        $this->barangay = [
            'name' => $name,
            'municipality' => $municipality,
            'province' => $province
        ];
        return $this;
    }
    
    /**
     * Get BCPC report HTML
     */
    public function generateBCPCHtml() {
        $report_data = $this->getBCPCReportData();
        
        $html = $this->getBCPCHtmlHeader();
        $html .= $this->renderBCPCContent($report_data);
        $html .= $this->getHtmlFooter();
        
        return $html;
    }
    
    /**
     * Get BCPC-specific HTML header
     */
    protected function getBCPCHtmlHeader() {
        $month_name = date('F', mktime(0, 0, 0, $this->month));
        $barangay_name = $this->barangay['name'] ?? 'Barangay';
        
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BCPC Report - {$month_name} {$this->year}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Arial', sans-serif;
            line-height: 1.6;
            color: #333;
            background: #f5f5f5;
        }
        .report-container {
            background: white;
            padding: 40px;
            max-width: 900px;
            margin: 20px auto;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .bcpc-header {
            text-align: center;
            border-bottom: 3px solid #d4af37;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .bcpc-header .logo {
            font-size: 12px;
            color: #666;
            margin-bottom: 10px;
        }
        .bcpc-header h1 {
            font-size: 20px;
            font-weight: bold;
            color: #1a1a1a;
            margin: 10px 0;
        }
        .bcpc-header .subtitle {
            font-size: 14px;
            color: #555;
            margin: 5px 0;
        }
        .bcpc-header .report-period {
            font-size: 12px;
            color: #888;
            margin-top: 10px;
            font-style: italic;
        }
        
        .section-header {
            background: #2c3e50;
            color: white;
            padding: 12px;
            margin: 30px 0 15px 0;
            font-weight: bold;
            border-left: 5px solid #d4af37;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        
        table th {
            background: #34495e;
            color: white;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            border: 1px solid #2c3e50;
        }
        
        table td {
            padding: 10px 12px;
            border: 1px solid #ddd;
        }
        
        table tr:nth-child(even) {
            background: #f9f9f9;
        }
        
        .metric-box {
            display: inline-block;
            width: 23%;
            margin: 1%;
            padding: 15px;
            background: #ecf0f1;
            border-left: 4px solid #3498db;
            border-radius: 3px;
            text-align: center;
        }
        
        .metric-box .value {
            font-size: 24px;
            font-weight: bold;
            color: #2c3e50;
        }
        
        .metric-box .label {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        
        .summary-text {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 3px;
            padding: 15px;
            margin: 20px 0;
            font-size: 13px;
            line-height: 1.6;
        }
        
        .child-related {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
            padding: 12px;
            margin: 10px 0;
            border-radius: 3px;
        }
        
        .recommendations {
            background: #d1ecf1;
            border-left: 4px solid #17a2b8;
            padding: 15px;
            margin: 20px 0;
            border-radius: 3px;
        }
        
        .recommendations h4 {
            color: #0c5460;
            margin-bottom: 10px;
        }
        
        .recommendations ol {
            margin-left: 20px;
        }
        
        .recommendations li {
            margin: 8px 0;
            color: #0c5460;
        }
        
        .signature-section {
            margin-top: 40px;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
        }
        
        .signature-block {
            text-align: center;
            border-top: 1px solid #333;
            padding-top: 10px;
            font-size: 12px;
        }
        
        .signature-block .title {
            font-weight: bold;
            margin-top: 5px;
        }
        
        .print-button {
            text-align: right;
            margin-bottom: 20px;
        }
        
        .print-button button {
            background: #34495e;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 13px;
        }
        
        .print-button button:hover {
            background: #2c3e50;
        }
        
        @media print {
            body { background: white; }
            .report-container { box-shadow: none; margin: 0; padding: 30px; }
            .print-button { display: none; }
        }
    </style>
</head>
<body>
    <div class="report-container">
        <div class="print-button">
            <button onclick="window.print()">🖨️ Print Report</button>
        </div>
        <div class="bcpc-header">
            <div class="logo">Republic of the Philippines</div>
            <h1>BARANGAY CHILD PROTECTION COMMITTEE</h1>
            <div class="subtitle">{$barangay_name}</div>
            <div class="report-period">Monthly Incident & Case Report</div>
            <div class="report-period">Period: {$month_name} {$this->year}</div>
        </div>
HTML;
    }
    
    /**
     * Get comprehensive BCPC report data
     */
    public function getBCPCReportData() {
        // Total incidents
        $sql = "SELECT COUNT(DISTINCT i.incident_id) as total FROM incidents i
                WHERE DATE_FORMAT(i.created_at, '%Y-%m') = '{$this->year}-{$this->month}'";
        $total_incidents = $this->pdo->query($sql)->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Child-related incidents
        $sql = "SELECT COUNT(DISTINCT i.incident_id) as total FROM incidents i
                WHERE DATE_FORMAT(i.created_at, '%Y-%m') = '{$this->year}-{$this->month}'
                AND (i.incident_type LIKE '%child%' OR i.incident_type LIKE '%minor%' 
                     OR i.incident_type LIKE '%abuse%' OR i.incident_type LIKE '%CICL%')";
        $child_incidents = $this->pdo->query($sql)->fetch(PDO::FETCH_ASSOC)['total'];
        
        // High severity incidents
        $sql = "SELECT COUNT(DISTINCT i.incident_id) as total FROM incidents i
                WHERE DATE_FORMAT(i.created_at, '%Y-%m') = '{$this->year}-{$this->month}'
                AND (i.nlp_severity > 70 OR i.nlp_threat_level = 'HIGH')";
        $high_severity = $this->pdo->query($sql)->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Cases created
        $sql = "SELECT COUNT(DISTINCT ca.case_id) as total FROM case_assignments ca
                JOIN incidents i ON ca.incident_id = i.incident_id
                WHERE DATE_FORMAT(i.created_at, '%Y-%m') = '{$this->year}-{$this->month}'";
        $cases_created = $this->pdo->query($sql)->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Cases closed
        $sql = "SELECT COUNT(DISTINCT b.blotter_id) as total FROM blotters b
                JOIN incidents i ON b.incident_id = i.incident_id
                WHERE DATE_FORMAT(i.created_at, '%Y-%m') = '{$this->year}-{$this->month}'
                AND b.status = 'Closed'";
        $cases_closed = $this->pdo->query($sql)->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Incidents by type
        $sql = "SELECT i.incident_type, COUNT(i.incident_id) as count
                FROM incidents i
                WHERE DATE_FORMAT(i.created_at, '%Y-%m') = '{$this->year}-{$this->month}'
                GROUP BY i.incident_type
                ORDER BY count DESC
                LIMIT 10";
        $by_type = $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        
        // Child incidents by type
        $sql = "SELECT i.incident_type, COUNT(i.incident_id) as count
                FROM incidents i
                WHERE DATE_FORMAT(i.created_at, '%Y-%m') = '{$this->year}-{$this->month}'
                AND (i.incident_type LIKE '%child%' OR i.incident_type LIKE '%minor%' 
                     OR i.incident_type LIKE '%abuse%' OR i.incident_type LIKE '%CICL%')
                GROUP BY i.incident_type
                ORDER BY count DESC";
        $child_by_type = $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        
        // Cases by status
        $sql = "SELECT ca.status, COUNT(ca.case_id) as count
                FROM case_assignments ca
                JOIN incidents i ON ca.incident_id = i.incident_id
                WHERE DATE_FORMAT(i.created_at, '%Y-%m') = '{$this->year}-{$this->month}'
                GROUP BY ca.status";
        $by_status = $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'total_incidents' => $total_incidents,
            'child_incidents' => $child_incidents,
            'high_severity' => $high_severity,
            'cases_created' => $cases_created,
            'cases_closed' => $cases_closed,
            'by_type' => $by_type,
            'child_by_type' => $child_by_type,
            'by_status' => $by_status
        ];
    }
    
    /**
     * Render BCPC report content
     */
    protected function renderBCPCContent($data) {
        $html = '<div class="bcpc-content">';
        
        // Summary Metrics
        $html .= '<div class="section-header">📊 Summary Metrics</div>';
        $html .= '<div>';
        $html .= '<div class="metric-box"><div class="value">' . $data['total_incidents'] . '</div><div class="label">Total Incidents</div></div>';
        $html .= '<div class="metric-box"><div class="value">' . $data['child_incidents'] . '</div><div class="label">Child-Related</div></div>';
        $html .= '<div class="metric-box"><div class="value">' . $data['high_severity'] . '</div><div class="label">High Severity</div></div>';
        $html .= '<div class="metric-box"><div class="value">' . $data['cases_created'] . '</div><div class="label">Cases Created</div></div>';
        $html .= '</div>';
        
        // Child-Related Incidents Summary
        $child_percent = $data['total_incidents'] > 0 
            ? round(($data['child_incidents'] / $data['total_incidents']) * 100, 1) 
            : 0;
        
        $html .= '<div class="child-related">';
        $html .= '<strong>🚨 Child-Related Incidents:</strong> ' . $data['child_incidents'] . ' incidents (' . $child_percent . '% of total)<br>';
        $html .= 'These incidents require immediate BCPC attention and adherence to CICL (Child in Conflict with the Law) protocols.';
        $html .= '</div>';
        
        // Incidents by Type
        $html .= '<div class="section-header">📋 Incidents by Type</div>';
        $html .= '<table>';
        $html .= '<tr><th>Type</th><th>Count</th><th>Percentage</th></tr>';
        foreach ($data['by_type'] as $type) {
            $percent = round(($type['count'] / $data['total_incidents']) * 100, 1);
            $html .= '<tr><td>' . htmlspecialchars($type['incident_type']) . '</td>';
            $html .= '<td>' . $type['count'] . '</td>';
            $html .= '<td>' . $percent . '%</td></tr>';
        }
        $html .= '</table>';
        
        // Child-Related Incidents by Type
        if (!empty($data['child_by_type'])) {
            $html .= '<div class="section-header">👶 Child-Related Incidents Detail</div>';
            $html .= '<table>';
            $html .= '<tr><th>Type</th><th>Count</th><th>% of Child Incidents</th></tr>';
            foreach ($data['child_by_type'] as $type) {
                $percent = round(($type['count'] / $data['child_incidents']) * 100, 1);
                $html .= '<tr><td>' . htmlspecialchars($type['incident_type']) . '</td>';
                $html .= '<td>' . $type['count'] . '</td>';
                $html .= '<td>' . $percent . '%</td></tr>';
            }
            $html .= '</table>';
        }
        
        // Case Status
        $html .= '<div class="section-header">✓ Case Status</div>';
        $html .= '<table>';
        $html .= '<tr><th>Status</th><th>Count</th></tr>';
        foreach ($data['by_status'] as $status) {
            $html .= '<tr><td>' . htmlspecialchars($status['status']) . '</td>';
            $html .= '<td>' . $status['count'] . '</td></tr>';
        }
        $html .= '<tr><td><strong>Closed Cases</strong></td><td><strong>' . $data['cases_closed'] . '</strong></td></tr>';
        $html .= '</table>';
        
        // Key Findings & Recommendations
        $html .= '<div class="section-header">🎯 Key Findings & Recommendations</div>';
        
        $findings = [];
        
        if ($data['child_incidents'] > 0) {
            $findings[] = "Child-related incidents account for {$child_percent}% of reported cases this month.";
        }
        
        if ($data['high_severity'] > 0) {
            $high_percent = round(($data['high_severity'] / $data['total_incidents']) * 100, 1);
            $findings[] = "{$data['high_severity']} high-severity incidents ({$high_percent}%) require enhanced investigation.";
        }
        
        if ($data['cases_closed'] < $data['cases_created']) {
            $pending = $data['cases_created'] - $data['cases_closed'];
            $findings[] = "{$pending} cases remain open and require follow-up.";
        }
        
        if (!empty($findings)) {
            $html .= '<div class="summary-text">';
            foreach ($findings as $finding) {
                $html .= '• ' . $finding . '<br>';
            }
            $html .= '</div>';
        }
        
        // Recommendations
        $html .= '<div class="recommendations">';
        $html .= '<h4>Recommendations for Next Month:</h4>';
        $html .= '<ol>';
        $html .= '<li>Prioritize follow-up on all open cases, especially high-severity incidents</li>';
        $html .= '<li>Ensure all child-related cases are handled with CICL protocols</li>';
        $html .= '<li>Conduct training on case management procedures for assigned officers</li>';
        $html .= '<li>Schedule review meetings for pending child-related cases</li>';
        $html .= '<li>Document all case closures with proper findings and recommendations</li>';
        $html .= '</ol>';
        $html .= '</div>';
        
        // Report Submission Section
        $html .= '<div class="section-header">📝 Report Submission</div>';
        $html .= '<div class="summary-text">';
        $html .= '<strong>Submitted by:</strong> Law Enforcement Incident Reporting System<br>';
        $html .= '<strong>Date Generated:</strong> ' . date('F d, Y') . '<br>';
        $html .= '<strong>Report Period:</strong> ' . date('F Y', strtotime("{$this->year}-{$this->month}-01")) . '<br>';
        $html .= '</div>';
        
        // Signature Section
        $html .= '<div class="signature-section">';
        $html .= '<div class="signature-block">';
        $html .= '<div style="height: 40px;"></div>';
        $html .= '<div class="title">Records Officer</div>';
        $html .= '</div>';
        $html .= '<div class="signature-block">';
        $html .= '<div style="height: 40px;"></div>';
        $html .= '<div class="title">BCPC Chairperson</div>';
        $html .= '</div>';
        $html .= '<div class="signature-block">';
        $html .= '<div style="height: 40px;"></div>';
        $html .= '<div class="title">Barangay Captain</div>';
        $html .= '</div>';
        $html .= '</div>';
        
        $html .= '</div>';
        
        return $html;
    }
}
