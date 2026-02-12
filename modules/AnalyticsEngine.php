<?php
/**
 * AnalyticsEngine - Comprehensive Analytics and Statistics
 * 
 * Analyzes incident data to provide insights for decision-making
 * Generates trends, patterns, and actionable intelligence
 */

class AnalyticsEngine {
    
    private $pdo;
    private $date_from;
    private $date_to;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->date_from = date('Y-01-01');
        $this->date_to = date('Y-m-d');
    }
    
    /**
     * Set date range for analytics
     */
    public function setDateRange($from, $to) {
        $this->date_from = $from;
        $this->date_to = $to;
        return $this;
    }
    
    /**
     * Get comprehensive dashboard analytics
     */
    public function getDashboardAnalytics() {
        return [
            'summary' => $this->getSummaryMetrics(),
            'trends' => $this->getTrendAnalysis(),
            'case_types' => $this->getCaseTypeAnalysis(),
            'officer_performance' => $this->getOfficerPerformance(),
            'child_incidents' => $this->getChildIncidentAnalysis(),
            'threat_analysis' => $this->getThreatAnalysis(),
            'response_times' => $this->getResponseTimeAnalysis()
        ];
    }
    
    /**
     * Get summary metrics
     */
    public function getSummaryMetrics() {
        $sql = "SELECT 
                    COUNT(DISTINCT i.id) as total_incidents,
                    COUNT(DISTINCT b.id) as total_blotters,
                    COUNT(DISTINCT ca.id) as total_cases,
                    COUNT(DISTINCT CASE WHEN i.is_high_risk = 1 THEN i.id END) as critical_cases,
                    COUNT(DISTINCT CASE WHEN b.status = 'Closed' OR b.status = 'Resolved' THEN b.id END) as closed_cases,
                    COALESCE(ROUND(COUNT(DISTINCT CASE WHEN ca.assigned_to IS NOT NULL THEN ca.id END) / 
                          NULLIF(COUNT(DISTINCT ca.id), 0) * 100, 2), 0) as assignment_rate,
                    COALESCE(ROUND(AVG(CAST(CASE WHEN i.urgency_level = 'Critical' THEN 100 WHEN i.urgency_level = 'High' THEN 75 WHEN i.urgency_level = 'Medium' THEN 50 ELSE 25 END AS DECIMAL(5,2))), 2), 0) as avg_severity,
                    COUNT(DISTINCT CASE WHEN ca.assigned_to IS NOT NULL THEN ca.assigned_to END) as active_officers
                FROM incidents i
                LEFT JOIN blotters b ON i.id = b.incident_id
                LEFT JOIN case_assignments ca ON i.id = ca.incident_id
                WHERE i.created_at BETWEEN '{$this->date_from}' AND '{$this->date_to}'";
        
        $result = $this->pdo->query($sql)->fetch(PDO::FETCH_ASSOC);
        
        return [
            'total_incidents' => (int)$result['total_incidents'],
            'total_blotters' => (int)$result['total_blotters'],
            'total_cases' => (int)$result['total_cases'],
            'critical_cases' => (int)$result['critical_cases'],
            'closed_cases' => (int)$result['closed_cases'],
            'assignment_rate' => (float)$result['assignment_rate'],
            'average_severity' => (float)$result['avg_severity'],
            'active_officers' => (int)$result['active_officers']
        ];
    }
    
    /**
     * Get trend analysis (weekly/monthly)
     */
    public function getTrendAnalysis() {
        $sql = "SELECT 
                    DATE_FORMAT(i.created_at, '%Y-%m') as month,
                    WEEK(i.created_at) as week,
                    COUNT(i.id) as incident_count,
                    COUNT(DISTINCT CASE WHEN i.is_high_risk = 1 THEN i.id END) as critical_count,
                    ROUND(AVG(CAST(CASE WHEN i.urgency_level = 'Critical' THEN 100 WHEN i.urgency_level = 'High' THEN 75 WHEN i.urgency_level = 'Medium' THEN 50 ELSE 25 END AS DECIMAL(5,2))), 2) as avg_severity,
                    COUNT(DISTINCT b.id) as blotter_count,
                    COUNT(DISTINCT ca.id) as case_count
                FROM incidents i
                LEFT JOIN blotters b ON i.id = b.incident_id
                LEFT JOIN case_assignments ca ON i.id = ca.incident_id
                WHERE i.created_at BETWEEN '{$this->date_from}' AND '{$this->date_to}'
                GROUP BY DATE_FORMAT(i.created_at, '%Y-%m')
                ORDER BY month ASC";
        
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Analyze cases by type
     */
    public function getCaseTypeAnalysis() {
        $sql = "SELECT 
                    i.incident_type,
                    COUNT(i.id) as count,
                    ROUND(AVG(CAST(CASE WHEN i.urgency_level = 'Critical' THEN 100 WHEN i.urgency_level = 'High' THEN 75 WHEN i.urgency_level = 'Medium' THEN 50 ELSE 25 END AS DECIMAL(5,2))), 2) as avg_severity,
                    COUNT(DISTINCT CASE WHEN i.is_high_risk = 1 THEN i.id END) as high_threat_count,
                    COUNT(DISTINCT b.id) as blotter_count,
                    COUNT(DISTINCT ca.id) as case_count,
                    ROUND(COUNT(DISTINCT b.id) / NULLIF(COUNT(i.id), 0) * 100, 2) as blotter_rate,
                    ROUND(COUNT(DISTINCT ca.id) / NULLIF(COUNT(i.id), 0) * 100, 2) as case_creation_rate
                FROM incidents i
                LEFT JOIN blotters b ON i.id = b.incident_id
                LEFT JOIN case_assignments ca ON i.id = ca.incident_id
                WHERE i.created_at BETWEEN '{$this->date_from}' AND '{$this->date_to}'
                GROUP BY i.incident_type
                ORDER BY count DESC";
        
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get child-related incident analysis
     */
    public function getChildIncidentAnalysis() {
        $sql = "SELECT 
                    CASE 
                        WHEN i.incident_type LIKE '%Abuse%' OR i.incident_type LIKE '%Neglect%'
                        THEN 'Child-Related'
                        ELSE 'Non-Child-Related'
                    END as incident_category,
                    COUNT(i.id) as count,
                    ROUND(AVG(CAST(CASE WHEN i.urgency_level = 'Critical' THEN 100 WHEN i.urgency_level = 'High' THEN 75 WHEN i.urgency_level = 'Medium' THEN 50 ELSE 25 END AS DECIMAL(5,2))), 2) as avg_severity,
                    COUNT(DISTINCT CASE WHEN i.is_high_risk = 1 THEN i.id END) as high_threat_count,
                    COUNT(DISTINCT b.id) as blotter_count,
                    COUNT(DISTINCT ca.id) as case_count
                FROM incidents i
                LEFT JOIN blotters b ON i.id = b.incident_id
                LEFT JOIN case_assignments ca ON i.id = ca.incident_id
                WHERE i.created_at BETWEEN '{$this->date_from}' AND '{$this->date_to}'
                GROUP BY incident_category
                ORDER BY count DESC";
        
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get trend of child-related incidents over time
     */
    public function getChildIncidentTrend() {
        $sql = "SELECT 
                    DATE_FORMAT(i.created_at, '%Y-%m') as month,
                    COUNT(CASE 
                        WHEN i.incident_type LIKE '%Abuse%' OR i.incident_type LIKE '%Neglect%'
                        THEN i.id
                    END) as child_incidents,
                    COUNT(i.id) as total_incidents,
                    ROUND(COUNT(CASE 
                        WHEN i.incident_type LIKE '%Abuse%' OR i.incident_type LIKE '%Neglect%'
                        THEN i.id
                    END) / NULLIF(COUNT(i.id), 0) * 100, 2) as child_incident_percentage
                FROM incidents i
                WHERE i.created_at BETWEEN '{$this->date_from}' AND '{$this->date_to}'
                GROUP BY DATE_FORMAT(i.created_at, '%Y-%m')
                ORDER BY month ASC";
        
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get officer performance metrics
     */
    public function getOfficerPerformance() {
        $sql = "SELECT 
                    u.user_id,
                    u.fullname,
                    COUNT(DISTINCT ca.id) as assigned_cases,
                    COUNT(DISTINCT CASE WHEN b.status = 'Closed' OR b.status = 'Resolved' THEN b.id END) as closed_cases,
                    ROUND(AVG(CAST(CASE WHEN i.urgency_level = 'Critical' THEN 100 WHEN i.urgency_level = 'High' THEN 75 WHEN i.urgency_level = 'Medium' THEN 50 ELSE 25 END AS DECIMAL(5,2))), 2) as avg_case_severity,
                    COUNT(DISTINCT CASE WHEN i.is_high_risk = 1 THEN i.id END) as high_threat_cases,
                    COALESCE(ROUND(COUNT(DISTINCT CASE WHEN b.status = 'Closed' OR b.status = 'Resolved' THEN b.id END) / 
                          NULLIF(COUNT(DISTINCT ca.id), 0) * 100, 2), 0) as closure_rate
                FROM signup u
                LEFT JOIN case_assignments ca ON u.user_id = ca.assigned_to
                LEFT JOIN incidents i ON ca.incident_id = i.id
                LEFT JOIN blotters b ON i.id = b.incident_id
                WHERE u.role IN ('Officer', 'Barangay Official', 'Investigator')
                AND i.created_at BETWEEN '{$this->date_from}' AND '{$this->date_to}'
                GROUP BY u.user_id, u.fullname
                HAVING assigned_cases > 0
                ORDER BY assigned_cases DESC";
        
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get threat level analysis
     */
    public function getThreatAnalysis() {
        $sql = "SELECT 
                    CASE WHEN i.is_high_risk = 1 THEN 'HIGH' ELSE 'LOW' END as threat_level,
                    COUNT(i.id) as count,
                    ROUND(COUNT(i.id) / (SELECT COUNT(*) FROM incidents 
                          WHERE created_at BETWEEN '{$this->date_from}' AND '{$this->date_to}') * 100, 2) as percentage,
                    ROUND(AVG(CAST(CASE WHEN i.urgency_level = 'Critical' THEN 100 WHEN i.urgency_level = 'High' THEN 75 WHEN i.urgency_level = 'Medium' THEN 50 ELSE 25 END AS DECIMAL(5,2))), 2) as avg_severity,
                    COUNT(DISTINCT CASE WHEN b.status = 'Closed' OR b.status = 'Resolved' THEN b.id END) as closed_count
                FROM incidents i
                LEFT JOIN blotters b ON i.id = b.incident_id
                WHERE i.created_at BETWEEN '{$this->date_from}' AND '{$this->date_to}'
                GROUP BY CASE WHEN i.is_high_risk = 1 THEN 'HIGH' ELSE 'LOW' END
                ORDER BY FIELD(threat_level, 'HIGH', 'LOW')";
        
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get response time analysis
     */
    public function getResponseTimeAnalysis() {
        $sql = "SELECT 
                    ROUND(AVG(TIMESTAMPDIFF(HOUR, i.created_at, b.created_at)), 2) as avg_response_hours,
                    ROUND(MIN(TIMESTAMPDIFF(HOUR, i.created_at, b.created_at)), 2) as min_response_hours,
                    ROUND(MAX(TIMESTAMPDIFF(HOUR, i.created_at, b.created_at)), 2) as max_response_hours,
                    COUNT(DISTINCT b.id) as blotter_count,
                    ROUND(AVG(TIMESTAMPDIFF(HOUR, i.created_at, ca.created_at)), 2) as avg_assignment_hours
                FROM incidents i
                LEFT JOIN blotters b ON i.id = b.incident_id
                LEFT JOIN case_assignments ca ON i.id = ca.incident_id
                WHERE i.created_at BETWEEN '{$this->date_from}' AND '{$this->date_to}'
                AND b.created_at IS NOT NULL";
        
        return $this->pdo->query($sql)->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get actionable insights and recommendations
     */
    public function getInsights() {
        $insights = [];
        $summary = $this->getSummaryMetrics();
        $types = $this->getCaseTypeAnalysis();
        $child_trend = $this->getChildIncidentTrend();
        
        // Insight 1: High-threat cases
        if ($summary['critical_cases'] > 0) {
            $percent = round(($summary['critical_cases'] / $summary['total_incidents']) * 100, 2);
            $insights[] = [
                'type' => 'warning',
                'title' => 'High-Threat Cases',
                'message' => "{$summary['critical_cases']} critical cases ({$percent}% of total) require immediate attention",
                'action' => 'Review and prioritize assignment of critical cases'
            ];
        }
        
        // Insight 2: Assignment rate
        if ($summary['assignment_rate'] < 80) {
            $unassigned = $summary['total_cases'] - round(($summary['assignment_rate']/100) * $summary['total_cases']);
            $insights[] = [
                'type' => 'alert',
                'title' => 'Low Assignment Rate',
                'message' => "Only {$summary['assignment_rate']}% of cases are assigned. {$unassigned} cases awaiting assignment",
                'action' => 'Increase staff or redistribute workload'
            ];
        }
        
        // Insight 3: Most common case type
        if (!empty($types)) {
            $top_type = $types[0];
            $insights[] = [
                'type' => 'info',
                'title' => 'Primary Case Type',
                'message' => "{$top_type['incident_type']} is the most common type ({$top_type['count']} cases)",
                'action' => 'Consider specialized training or resource allocation'
            ];
        }
        
        // Insight 4: Child incident trend
        if (!empty($child_trend)) {
            $latest = end($child_trend);
            if ($latest['child_incident_percentage'] > 20) {
                $insights[] = [
                    'type' => 'warning',
                    'title' => 'High Child-Related Incidents',
                    'message' => "{$latest['child_incident_percentage']}% of recent incidents involve children",
                    'action' => 'Ensure specialized CICL procedures are followed'
                ];
            }
        }
        
        return $insights;
    }
    
    /**
     * Predict future needs (simple forecasting)
     */
    public function getForecast() {
        $trends = $this->getTrendAnalysis();
        
        if (count($trends) < 2) {
            return null;
        }
        
        // Calculate average growth rate
        $recent_count = $trends[count($trends) - 1]['incident_count'];
        $previous_count = $trends[count($trends) - 2]['incident_count'];
        $growth_rate = (($recent_count - $previous_count) / $previous_count) * 100;
        
        $forecast = [
            'growth_rate' => round($growth_rate, 2),
            'trend_direction' => $growth_rate > 0 ? 'increasing' : 'decreasing',
            'estimated_next_month' => round($recent_count * (1 + ($growth_rate / 100)))
        ];
        
        return $forecast;
    }
}
