-- ============================================================================
-- REPORTING DATABASE VIEWS
-- ============================================================================
-- These views optimize performance for reporting and analytics queries
-- Run this SQL file to create necessary reporting views in your database

-- ============================================================================
-- 1. INCIDENT SUMMARY VIEW
-- ============================================================================
CREATE OR REPLACE VIEW vw_incident_summary AS
SELECT 
    i.incident_id,
    i.incident_type,
    i.location,
    i.description,
    i.reported_by,
    i.severity_level,
    i.nlp_severity,
    i.nlp_threat_level,
    i.nlp_sentiment,
    i.nlp_confidence,
    i.created_at,
    b.blotter_id,
    b.status as blotter_status,
    b.created_at as blotter_created_at,
    ca.case_id,
    ca.assigned_officer,
    ca.status as case_status,
    u.fullname as assigned_officer_name,
    u.emailadd as officer_email,
    DATEDIFF(CURDATE(), DATE(i.created_at)) as days_open,
    CASE 
        WHEN b.status = 'Closed' THEN 'Closed'
        WHEN ca.case_id IS NOT NULL THEN 'In Progress'
        WHEN b.blotter_id IS NOT NULL THEN 'In Blotter'
        ELSE 'Pending'
    END as overall_status
FROM incidents i
LEFT JOIN blotters b ON i.incident_id = b.incident_id
LEFT JOIN case_assignments ca ON i.incident_id = ca.incident_id
LEFT JOIN signup u ON ca.assigned_officer = u.user_id;

-- ============================================================================
-- 2. INCIDENT ANALYTICS VIEW
-- ============================================================================
CREATE OR REPLACE VIEW vw_incident_analytics AS
SELECT 
    DATE_FORMAT(i.created_at, '%Y-%m') as month,
    i.incident_type,
    i.nlp_threat_level,
    COUNT(DISTINCT i.incident_id) as incident_count,
    COUNT(DISTINCT b.blotter_id) as blotter_count,
    COUNT(DISTINCT ca.case_id) as case_count,
    ROUND(AVG(CAST(i.nlp_severity AS DECIMAL(5,2))), 2) as avg_severity,
    MAX(CAST(i.nlp_severity AS DECIMAL(5,2))) as max_severity,
    MIN(CAST(i.nlp_severity AS DECIMAL(5,2))) as min_severity,
    COUNT(DISTINCT CASE WHEN b.status = 'Closed' THEN b.blotter_id END) as closed_count,
    COUNT(DISTINCT ca.assigned_officer) as officer_count
FROM incidents i
LEFT JOIN blotters b ON i.incident_id = b.incident_id
LEFT JOIN case_assignments ca ON i.incident_id = ca.incident_id
GROUP BY DATE_FORMAT(i.created_at, '%Y-%m'), i.incident_type, i.nlp_threat_level;

-- ============================================================================
-- 3. CHILD-RELATED INCIDENTS VIEW
-- ============================================================================
CREATE OR REPLACE VIEW vw_child_incidents AS
SELECT 
    i.incident_id,
    i.incident_type,
    i.location,
    i.description,
    i.nlp_severity,
    i.nlp_threat_level,
    i.created_at,
    b.blotter_id,
    b.status as blotter_status,
    ca.case_id,
    ca.assigned_officer,
    u.fullname as officer_name,
    CASE 
        WHEN i.incident_type LIKE '%child%' THEN 'Direct Child'
        WHEN i.incident_type LIKE '%abuse%' THEN 'Abuse Case'
        WHEN i.incident_type LIKE '%minor%' THEN 'Minor Involved'
        WHEN i.incident_type LIKE '%CICL%' THEN 'CICL Case'
        ELSE 'Child-Related'
    END as category
FROM incidents i
LEFT JOIN blotters b ON i.incident_id = b.incident_id
LEFT JOIN case_assignments ca ON i.incident_id = ca.incident_id
LEFT JOIN signup u ON ca.assigned_officer = u.user_id
WHERE i.incident_type LIKE '%child%' 
   OR i.incident_type LIKE '%minor%' 
   OR i.incident_type LIKE '%abuse%' 
   OR i.incident_type LIKE '%CICL%';

-- ============================================================================
-- 4. OFFICER PERFORMANCE VIEW
-- ============================================================================
CREATE OR REPLACE VIEW vw_officer_performance AS
SELECT 
    u.user_id,
    u.fullname,
    u.emailadd as email,
    COUNT(DISTINCT ca.case_id) as total_cases,
    COUNT(DISTINCT CASE WHEN b.status = 'Closed' THEN b.blotter_id END) as closed_cases,
    COUNT(DISTINCT CASE WHEN b.status IS NULL THEN ca.case_id END) as pending_cases,
    ROUND(COUNT(DISTINCT CASE WHEN b.status = 'Closed' THEN b.blotter_id END) / 
          NULLIF(COUNT(DISTINCT ca.case_id), 0) * 100, 2) as closure_rate,
    ROUND(AVG(CAST(i.nlp_severity AS DECIMAL(5,2))), 2) as avg_case_severity,
    COUNT(DISTINCT CASE WHEN i.nlp_threat_level = 'HIGH' THEN i.incident_id END) as high_threat_cases,
    MIN(ca.created_at) as first_assignment,
    MAX(ca.created_at) as last_assignment
FROM signup u
LEFT JOIN case_assignments ca ON u.user_id = ca.assigned_officer
LEFT JOIN incidents i ON ca.incident_id = i.incident_id
LEFT JOIN blotters b ON i.incident_id = b.incident_id
WHERE u.role IN ('Officer', 'Investigator', 'Barangay Official')
GROUP BY u.user_id, u.fullname, u.emailadd;

-- ============================================================================
-- 5. CASE STATUS TIMELINE VIEW
-- ============================================================================
CREATE OR REPLACE VIEW vw_case_timeline AS
SELECT 
    ca.case_id,
    ca.incident_id,
    i.incident_type,
    i.created_at as incident_date,
    ca.created_at as case_created,
    b.created_at as blotter_date,
    DATEDIFF(b.created_at, i.created_at) as days_to_blotter,
    DATEDIFF(ca.created_at, i.created_at) as days_to_case,
    ca.assigned_officer,
    u.fullname,
    ca.status,
    CASE 
        WHEN b.status = 'Closed' THEN DATEDIFF(b.updated_at, i.created_at)
        ELSE NULL
    END as days_to_close
FROM case_assignments ca
LEFT JOIN incidents i ON ca.incident_id = i.incident_id
LEFT JOIN blotters b ON i.incident_id = b.incident_id
LEFT JOIN signup u ON ca.assigned_officer = u.user_id;

-- ============================================================================
-- 6. THREAT LEVEL DISTRIBUTION VIEW
-- ============================================================================
CREATE OR REPLACE VIEW vw_threat_distribution AS
SELECT 
    DATE_FORMAT(i.created_at, '%Y-%m') as month,
    COALESCE(i.nlp_threat_level, 'UNKNOWN') as threat_level,
    COUNT(DISTINCT i.incident_id) as count,
    ROUND(COUNT(DISTINCT i.incident_id) / 
          (SELECT COUNT(*) FROM incidents 
           WHERE DATE_FORMAT(created_at, '%Y-%m') = DATE_FORMAT(i.created_at, '%Y-%m')) * 100, 2) as percentage,
    ROUND(AVG(CAST(i.nlp_severity AS DECIMAL(5,2))), 2) as avg_severity
FROM incidents i
GROUP BY DATE_FORMAT(i.created_at, '%Y-%m'), COALESCE(i.nlp_threat_level, 'UNKNOWN');

-- ============================================================================
-- 7. CASE TYPE ANALYSIS VIEW
-- ============================================================================
CREATE OR REPLACE VIEW vw_case_type_analysis AS
SELECT 
    i.incident_type,
    DATE_FORMAT(i.created_at, '%Y-%m') as month,
    COUNT(DISTINCT i.incident_id) as count,
    ROUND(AVG(CAST(i.nlp_severity AS DECIMAL(5,2))), 2) as avg_severity,
    COUNT(DISTINCT CASE WHEN i.nlp_threat_level = 'HIGH' THEN i.incident_id END) as high_threat_count,
    COUNT(DISTINCT b.blotter_id) as blotter_count,
    COUNT(DISTINCT ca.case_id) as case_count,
    ROUND(COUNT(DISTINCT b.blotter_id) / NULLIF(COUNT(i.incident_id), 0) * 100, 2) as blotter_rate
FROM incidents i
LEFT JOIN blotters b ON i.incident_id = b.incident_id
LEFT JOIN case_assignments ca ON i.incident_id = ca.incident_id
GROUP BY i.incident_type, DATE_FORMAT(i.created_at, '%Y-%m');

-- ============================================================================
-- 8. RESPONSE TIME METRICS VIEW
-- ============================================================================
CREATE OR REPLACE VIEW vw_response_metrics AS
SELECT 
    DATE_FORMAT(i.created_at, '%Y-%m') as month,
    ROUND(AVG(TIMESTAMPDIFF(HOUR, i.created_at, b.created_at)), 2) as avg_blotter_hours,
    ROUND(MIN(TIMESTAMPDIFF(HOUR, i.created_at, b.created_at)), 2) as min_blotter_hours,
    ROUND(MAX(TIMESTAMPDIFF(HOUR, i.created_at, b.created_at)), 2) as max_blotter_hours,
    ROUND(AVG(TIMESTAMPDIFF(HOUR, i.created_at, ca.created_at)), 2) as avg_assignment_hours,
    COUNT(DISTINCT b.blotter_id) as blotter_count,
    COUNT(DISTINCT ca.case_id) as case_count
FROM incidents i
LEFT JOIN blotters b ON i.incident_id = b.incident_id
LEFT JOIN case_assignments ca ON i.incident_id = ca.incident_id
WHERE b.created_at IS NOT NULL OR ca.created_at IS NOT NULL
GROUP BY DATE_FORMAT(i.created_at, '%Y-%m');

-- ============================================================================
-- 9. MONTHLY SUMMARY VIEW
-- ============================================================================
CREATE OR REPLACE VIEW vw_monthly_summary AS
SELECT 
    DATE_FORMAT(i.created_at, '%Y-%m') as month,
    COUNT(DISTINCT i.incident_id) as total_incidents,
    COUNT(DISTINCT b.blotter_id) as blotter_entries,
    COUNT(DISTINCT ca.case_id) as cases_created,
    COUNT(DISTINCT CASE WHEN b.status = 'Closed' THEN b.blotter_id END) as cases_closed,
    COUNT(DISTINCT CASE WHEN i.nlp_threat_level = 'HIGH' THEN i.incident_id END) as critical_cases,
    COUNT(DISTINCT CASE 
        WHEN i.incident_type LIKE '%child%' 
          OR i.incident_type LIKE '%minor%' 
          OR i.incident_type LIKE '%abuse%' 
          OR i.incident_type LIKE '%CICL%' 
        THEN i.incident_id 
    END) as child_incidents,
    ROUND(AVG(CAST(i.nlp_severity AS DECIMAL(5,2))), 2) as avg_severity,
    COUNT(DISTINCT ca.assigned_officer) as active_officers
FROM incidents i
LEFT JOIN blotters b ON i.incident_id = b.incident_id
LEFT JOIN case_assignments ca ON i.incident_id = ca.incident_id
GROUP BY DATE_FORMAT(i.created_at, '%Y-%m')
ORDER BY month DESC;

-- ============================================================================
-- INDEXES FOR REPORTING PERFORMANCE
-- ============================================================================

-- Incident indexes
CREATE INDEX idx_incident_created_at ON incidents(created_at);
CREATE INDEX idx_incident_type ON incidents(incident_type);
CREATE INDEX idx_incident_threat ON incidents(nlp_threat_level);
CREATE INDEX idx_incident_severity ON incidents(nlp_severity);

-- Blotter indexes
CREATE INDEX idx_blotter_incident ON blotters(incident_id);
CREATE INDEX idx_blotter_status ON blotters(status);
CREATE INDEX idx_blotter_created ON blotters(created_at);

-- Case assignment indexes
CREATE INDEX idx_case_incident ON case_assignments(incident_id);
CREATE INDEX idx_case_officer ON case_assignments(assigned_officer);
CREATE INDEX idx_case_status ON case_assignments(status);
CREATE INDEX idx_case_created ON case_assignments(created_at);

-- Signup indexes
CREATE INDEX idx_user_role ON signup(role);
CREATE INDEX idx_user_id ON signup(user_id);

-- ============================================================================
-- VIEW VERIFICATION QUERIES
-- ============================================================================

-- Test views after creation:
-- SELECT * FROM vw_incident_summary LIMIT 5;
-- SELECT * FROM vw_officer_performance LIMIT 5;
-- SELECT * FROM vw_monthly_summary LIMIT 12;
-- SELECT * FROM vw_child_incidents LIMIT 5;
-- SELECT * FROM vw_threat_distribution;

-- ============================================================================
-- END OF REPORTING DATABASE VIEWS
-- ============================================================================
