<?php
/**
 * export_report.php - Export reports to various formats
 */

session_start();

require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../modules/AnalyticsEngine.php';

if (!isset($_SESSION['user_id'])) {
    exit('Unauthorized');
}

$type = $_GET['type'] ?? 'csv';
$from = $_GET['from'] ?? date('Y-01-01');
$to = $_GET['to'] ?? date('Y-m-d');

$analytics = new AnalyticsEngine($pdo);
$analytics->setDateRange($from, $to);

if ($type === 'csv') {
    exportToCSV($pdo, $from, $to);
} else if ($type === 'analytics-csv') {
    exportAnalyticsCSV($analytics);
}

function exportToCSV($pdo, $from, $to) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="incident_export_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
    
    // Headers
    fputcsv($output, [
        'Incident ID',
        'Type',
        'Location',
        'Reported By',
        'Severity (NLP)',
        'Threat Level',
        'Sentiment',
        'Confidence',
        'Blotter ID',
        'Blotter Status',
        'Case ID',
        'Assigned Officer',
        'Assignment Date',
        'Date Created'
    ]);
    
    // Data
    $sql = "SELECT 
                i.incident_id,
                i.incident_type,
                i.location,
                i.reported_by,
                i.nlp_severity,
                i.nlp_threat_level,
                i.nlp_sentiment,
                i.nlp_confidence,
                b.blotter_id,
                b.status as blotter_status,
                ca.case_id,
                u.fullname as officer_name,
                ca.created_at as assigned_date,
                i.created_at
            FROM incidents i
            LEFT JOIN blotters b ON i.incident_id = b.incident_id
            LEFT JOIN case_assignments ca ON i.incident_id = ca.incident_id
            LEFT JOIN signup u ON ca.assigned_officer = u.user_id
            WHERE i.created_at BETWEEN ? AND ?
            ORDER BY i.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$from . ' 00:00:00', $to . ' 23:59:59']);
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['incident_id'],
            $row['incident_type'],
            $row['location'] ?? '-',
            $row['reported_by'] ?? '-',
            $row['nlp_severity'] ?? '-',
            $row['nlp_threat_level'] ?? '-',
            $row['nlp_sentiment'] ?? '-',
            $row['nlp_confidence'] ?? '-',
            $row['blotter_id'] ?? '-',
            $row['blotter_status'] ?? '-',
            $row['case_id'] ?? '-',
            $row['officer_name'] ?? 'Unassigned',
            $row['assigned_date'] ?? '-',
            date('Y-m-d H:i:s', strtotime($row['created_at']))
        ]);
    }
    
    fclose($output);
    exit;
}

function exportAnalyticsCSV($analytics) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="analytics_export_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
    
    // Summary metrics
    fputcsv($output, ['SUMMARY METRICS']);
    $summary = $analytics->getSummaryMetrics();
    foreach ($summary as $key => $value) {
        fputcsv($output, [ucwords(str_replace('_', ' ', $key)), $value]);
    }
    
    fputcsv($output, []);
    
    // Trends
    fputcsv($output, ['MONTHLY TRENDS']);
    fputcsv($output, ['Month', 'Incidents', 'Critical', 'Avg Severity', 'Cases']);
    $trends = $analytics->getTrendAnalysis();
    foreach ($trends as $trend) {
        fputcsv($output, [
            date('F Y', strtotime($trend['month'] . '-01')),
            $trend['incident_count'],
            $trend['critical_count'],
            $trend['avg_severity'],
            $trend['case_count']
        ]);
    }
    
    fputcsv($output, []);
    
    // Case types
    fputcsv($output, ['CASES BY TYPE']);
    fputcsv($output, ['Type', 'Count', 'Avg Severity', 'High Threat', 'Blotter Rate']);
    $types = $analytics->getCaseTypeAnalysis();
    foreach ($types as $type) {
        fputcsv($output, [
            $type['incident_type'],
            $type['count'],
            $type['avg_severity'],
            $type['high_threat_count'],
            $type['blotter_rate'] . '%'
        ]);
    }
    
    fputcsv($output, []);
    
    // Officer performance
    fputcsv($output, ['OFFICER PERFORMANCE']);
    fputcsv($output, ['Officer', 'Cases', 'Closed', 'Closure Rate', 'Avg Severity']);
    $officers = $analytics->getOfficerPerformance();
    foreach ($officers as $officer) {
        fputcsv($output, [
            $officer['fullname'],
            $officer['assigned_cases'],
            $officer['closed_cases'],
            $officer['closure_rate'] . '%',
            $officer['avg_case_severity']
        ]);
    }
    
    fclose($output);
    exit;
}
