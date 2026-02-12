-- Law Enforcement Incident Reporting System - NLP and Workflow Enhancement
-- Database Migration for AI Features
-- Created: 2025

-- ============================================================================
-- ALTER INCIDENTS TABLE TO ADD NLP FIELDS
-- ============================================================================

ALTER TABLE incidents ADD COLUMN IF NOT EXISTS nlp_sentiment VARCHAR(50) DEFAULT 'Neutral' COMMENT 'Sentiment analysis result from NLP';
ALTER TABLE incidents ADD COLUMN IF NOT EXISTS nlp_threat_level VARCHAR(50) DEFAULT 'Low' COMMENT 'Threat level determined by NLP analysis';
ALTER TABLE incidents ADD COLUMN IF NOT EXISTS nlp_severity_score DECIMAL(5,2) DEFAULT 0 COMMENT 'Severity score (0-100) from NLP';
ALTER TABLE incidents ADD COLUMN IF NOT EXISTS nlp_emotions JSON COMMENT 'Detected emotions in narrative (JSON array)';
ALTER TABLE incidents ADD COLUMN IF NOT EXISTS nlp_analysis_data JSON COMMENT 'Full NLP analysis data (JSON)';
ALTER TABLE incidents ADD COLUMN IF NOT EXISTS nlp_confidence_score DECIMAL(5,2) DEFAULT 0 COMMENT 'Confidence score of NLP analysis (0-100)';
ALTER TABLE incidents ADD COLUMN IF NOT EXISTS nlp_summary LONGTEXT COMMENT 'Human-readable summary of NLP analysis';
ALTER TABLE incidents ADD COLUMN IF NOT EXISTS review_requested TINYINT(1) DEFAULT 0 COMMENT 'Flag indicating if review was requested';
ALTER TABLE incidents ADD COLUMN IF NOT EXISTS review_requested_at DATETIME NULL COMMENT 'Timestamp when review was requested';
ALTER TABLE incidents ADD COLUMN IF NOT EXISTS review_completed TINYINT(1) DEFAULT 0 COMMENT 'Flag indicating if review was completed';
ALTER TABLE incidents ADD COLUMN IF NOT EXISTS review_completed_at DATETIME NULL COMMENT 'Timestamp when review was completed';

-- Add indexes for improved query performance
CREATE INDEX idx_incidents_nlp_threat ON incidents(nlp_threat_level);
CREATE INDEX idx_incidents_nlp_severity ON incidents(nlp_severity_score);
CREATE INDEX idx_incidents_review_status ON incidents(review_requested, review_completed);

-- ============================================================================
-- CREATE NOTIFICATIONS TABLE
-- ============================================================================

CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    incident_id INT NOT NULL,
    notification_type VARCHAR(100) NOT NULL COMMENT 'Type: Case Notification, Review Request, Case Assignment, etc.',
    title VARCHAR(255) NOT NULL,
    message LONGTEXT NOT NULL,
    threat_level VARCHAR(50) COMMENT 'Critical, High, Medium, Low',
    urgency VARCHAR(100) COMMENT 'Immediate, Urgent, Normal, Low Priority',
    is_read TINYINT(1) DEFAULT 0,
    read_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES signup(user_id),
    FOREIGN KEY (incident_id) REFERENCES incidents(id),
    INDEX idx_user_unread (user_id, is_read),
    INDEX idx_incident_notifications (incident_id),
    INDEX idx_notification_type (notification_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- CREATE REVIEW_REQUESTS TABLE
-- ============================================================================

CREATE TABLE IF NOT EXISTS review_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    incident_id INT NOT NULL,
    requested_by INT NOT NULL,
    reason TEXT NOT NULL,
    priority ENUM('High', 'Normal', 'Low') DEFAULT 'Normal',
    status ENUM('Pending', 'Completed', 'Rejected') DEFAULT 'Pending',
    responded_by INT NULL,
    response VARCHAR(50) COMMENT 'Approved, Denied, Needs Revision',
    findings LONGTEXT,
    recommendations LONGTEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    responded_at DATETIME NULL,
    FOREIGN KEY (incident_id) REFERENCES incidents(id),
    FOREIGN KEY (requested_by) REFERENCES signup(user_id),
    FOREIGN KEY (responded_by) REFERENCES signup(user_id),
    INDEX idx_incident_reviews (incident_id),
    INDEX idx_pending_reviews (status, priority)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- ALTER BLOTTERS TABLE TO SUPPORT INCIDENT INTEGRATION
-- ============================================================================

ALTER TABLE blotters ADD COLUMN IF NOT EXISTS incident_id INT COMMENT 'Reference to incidents table';
ALTER TABLE blotters ADD COLUMN IF NOT EXISTS created_from_incident TINYINT(1) DEFAULT 0 COMMENT 'Flag indicating auto-creation from incident';
ALTER TABLE blotters ADD COLUMN IF NOT EXISTS nlp_threat_level VARCHAR(50) COMMENT 'Threat level from NLP analysis';
ALTER TABLE blotters ADD COLUMN IF NOT EXISTS nlp_severity_score DECIMAL(5,2) COMMENT 'Severity score from NLP';

ALTER TABLE blotters ADD FOREIGN KEY (incident_id) REFERENCES incidents(id);
CREATE INDEX idx_blotters_incident ON blotters(incident_id);

-- ============================================================================
-- ALTER CASE_ASSIGNMENTS TABLE FOR NLP INTEGRATION
-- ============================================================================

ALTER TABLE case_assignments ADD COLUMN IF NOT EXISTS nlp_threat_level VARCHAR(50) COMMENT 'Threat level from NLP analysis';
ALTER TABLE case_assignments ADD COLUMN IF NOT EXISTS nlp_severity_score DECIMAL(5,2) COMMENT 'Severity score from NLP';
ALTER TABLE case_assignments ADD COLUMN IF NOT EXISTS incident_id INT COMMENT 'Reference to incidents table';

ALTER TABLE case_assignments ADD FOREIGN KEY (incident_id) REFERENCES incidents(id);
CREATE INDEX idx_case_assignments_nlp_threat ON case_assignments(nlp_threat_level);

-- ============================================================================
-- CREATE WORKFLOW_EVENTS TABLE FOR AUDIT TRAIL
-- ============================================================================

CREATE TABLE IF NOT EXISTS workflow_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    incident_id INT NOT NULL,
    event_type VARCHAR(100) NOT NULL COMMENT 'Incident Created, Review Requested, Review Completed, etc.',
    description TEXT,
    performed_by INT NULL COMMENT 'User ID who performed action, NULL for system',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (incident_id) REFERENCES incidents(id),
    FOREIGN KEY (performed_by) REFERENCES signup(user_id) ON DELETE SET NULL,
    INDEX idx_incident_workflow (incident_id),
    INDEX idx_event_type (event_type),
    INDEX idx_workflow_date (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- UPDATE BCPC_OFFICERS TABLE FOR WORKLOAD MANAGEMENT
-- ============================================================================

ALTER TABLE bcpc_officers ADD COLUMN IF NOT EXISTS current_case_load INT DEFAULT 0 COMMENT 'Current number of assigned cases';
ALTER TABLE bcpc_officers ADD COLUMN IF NOT EXISTS max_case_load INT DEFAULT 10 COMMENT 'Maximum number of cases allowed';

CREATE INDEX idx_bcpc_available_load ON bcpc_officers(is_available, current_case_load);

-- ============================================================================
-- CREATE NLP_ANALYSIS_CACHE TABLE FOR PERFORMANCE
-- ============================================================================

CREATE TABLE IF NOT EXISTS nlp_analysis_cache (
    id INT AUTO_INCREMENT PRIMARY KEY,
    incident_id INT UNIQUE NOT NULL,
    analysis_data JSON NOT NULL COMMENT 'Complete NLP analysis result',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (incident_id) REFERENCES incidents(id) ON DELETE CASCADE,
    INDEX idx_analysis_date (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- CREATE SYSTEM_ALERTS TABLE FOR CRITICAL INCIDENT TRACKING
-- ============================================================================

CREATE TABLE IF NOT EXISTS system_alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    incident_id INT NOT NULL,
    alert_type VARCHAR(100) NOT NULL COMMENT 'Critical Incident, Review Required, etc.',
    severity VARCHAR(50) NOT NULL COMMENT 'Critical, High, Medium, Low',
    alert_message TEXT NOT NULL,
    resolved TINYINT(1) DEFAULT 0,
    resolved_by INT NULL,
    resolved_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (incident_id) REFERENCES incidents(id),
    FOREIGN KEY (resolved_by) REFERENCES signup(user_id),
    INDEX idx_unresolved_alerts (resolved),
    INDEX idx_alert_severity (severity),
    INDEX idx_alert_date (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- INSERT SAMPLE DATA FOR TESTING
-- ============================================================================

-- Add 'Barangay Official' role if not exists
INSERT IGNORE INTO signup (fullname, emailadd, username, password, created_at) 
VALUES ('System Barangay Official', 'barangay@lawenforcement.local', 'barangay_official', SHA2('demo123', 256), NOW());

-- ============================================================================
-- CREATE VIEWS FOR REPORTING
-- ============================================================================

CREATE OR REPLACE VIEW incident_nlp_summary AS
SELECT 
    i.id,
    i.case_no,
    i.incident_type,
    i.incident_date,
    i.nlp_threat_level,
    i.nlp_severity_score,
    i.nlp_sentiment,
    i.nlp_confidence_score,
    COUNT(DISTINCT n.id) as notification_count,
    COUNT(DISTINCT rr.id) as review_request_count,
    i.status
FROM incidents i
LEFT JOIN notifications n ON i.id = n.incident_id
LEFT JOIN review_requests rr ON i.id = rr.incident_id
GROUP BY i.id;

CREATE OR REPLACE VIEW critical_incidents_view AS
SELECT 
    i.id,
    i.case_no,
    i.incident_type,
    i.location,
    i.incident_date,
    i.nlp_threat_level,
    i.nlp_severity_score,
    CONCAT(u.fullname, ' (', u.username, ')') as assigned_to,
    i.status
FROM incidents i
LEFT JOIN signup u ON i.assigned_to = u.user_id
WHERE i.nlp_threat_level IN ('Critical', 'High')
ORDER BY i.nlp_severity_score DESC, i.created_at DESC;

-- ============================================================================
-- MIGRATION COMPLETE
-- ============================================================================
-- Run this SQL to set up NLP and workflow capabilities
-- This script adds tables and columns while preserving existing data
