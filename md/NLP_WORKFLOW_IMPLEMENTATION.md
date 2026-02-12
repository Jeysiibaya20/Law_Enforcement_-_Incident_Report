# Natural Language Processing & Incident Workflow System
## Complete Implementation Guide

**Version:** 2.0  
**Last Updated:** January 2025  
**Status:** Production Ready

---

## 📋 Overview

This document describes the complete implementation of Natural Language Processing (NLP) AI and automated incident workflow management for the Law Enforcement Incident Report System.

### Key Features Implemented

✅ **Natural Language Processing Engine**
- Sentiment analysis
- Threat level detection
- Severity scoring (0-100)
- Emotion detection
- Entity extraction (people, places, objects)
- Key phrase identification
- Text quality assessment
- Confidence scoring

✅ **Incident Workflow Automation** (DFD Level 0)
- BCPC Officer → Incident Report → Logging System
- Complainant → Incident Information → Logging System
- Automatic Blotter Entry Creation
- Case Notification to Barangay Official
- Review Request Workflow
- Officer Auto-Assignment based on availability

✅ **Notification System**
- Real-time notifications for Barangay Officials
- Case assignment notifications
- Review request notifications
- Critical incident alerts
- Email integration support

✅ **Review Request System**
- Request creation and tracking
- Review response management
- Findings and recommendations
- Audit trail logging
- Statistics and reporting

---

## 🚀 System Architecture

### Component Modules

#### 1. **NaturalLanguageProcessor.php**
Advanced text analysis and NLP capabilities.

**Key Classes & Methods:**
```php
class NaturalLanguageProcessor
  ├─ analyzeIncident($narrative, $incident_type)
  ├─ analyzeSentiment($text)
  ├─ detectEmotions($text)
  ├─ calculateSeverityScore($text, $incident_type)
  ├─ extractKeyPhrases($narrative)
  ├─ extractEntities($narrative)
  ├─ determineThreatLevel($text, $incident_type)
  ├─ extractActionableItems($narrative)
  ├─ assessTextQuality($narrative)
  ├─ generateNLPSummary($analysis)
  └─ suggestNextSteps($analysis, $incident_type)
```

**Returns:**
```php
[
    'sentiment' => ['sentiment' => 'Positive|Negative|Neutral', 'score' => 0],
    'emotions' => ['Fear', 'Anger', ...],
    'severity_score' => 0-100,
    'key_phrases' => ['phrase1', 'phrase2', ...],
    'entities' => [
        'people' => [...],
        'locations' => [...],
        'dates' => [...],
        'items' => [...]
    ],
    'threat_level' => 'Critical|High|Medium|Low',
    'actionable_items' => ['medical', 'witnesses', ...],
    'word_count' => number,
    'text_quality' => [...],
    'confidence_score' => 0-100
]
```

#### 2. **IncidentWorkflowManager.php**
Orchestrates the complete incident processing workflow.

**Key Classes & Methods:**
```php
class IncidentWorkflowManager
  ├─ processIncidentReport($incident_data)
  │   ├─ Step 1: Perform NLP analysis
  │   ├─ Step 2: Create incident record with NLP data
  │   ├─ Step 3: Create blotter entry
  │   ├─ Step 4: Generate notifications
  │   ├─ Step 5: Auto-assign to officer
  │   ├─ Step 6: Create case record
  │   └─ Step 7: Log workflow event
  ├─ createReviewRequest($incident_id, $requested_by, $reason)
  └─ getIncidentWorkflowStatus($incident_id)
```

**Workflow Diagram:**
```
Incident Report Form
        ↓
NLP Analysis Engine
        ↓
[Sentiment, Threat Level, Severity, Emotions]
        ↓
Create Incident Record (with NLP data)
        ├─ Create Blotter Entry (Digital Blotter System)
        ├─ Create Case Record (Case Management)
        ├─ Generate Notifications (Barangay Official)
        └─ Auto-assign Officer (if High/Critical)
        ↓
Log Workflow Events (Audit Trail)
```

#### 3. **NotificationSystem.php**
Manages all notifications and alerts throughout the system.

**Key Classes & Methods:**
```php
class NotificationSystem
  ├─ notifyBarangayOfficial($incident_id, $incident_data, $nlp_analysis)
  ├─ notifyOfficerAssignment($incident_id, $assigned_officer_id, ...)
  ├─ notifyReviewRequest($incident_id, $requested_by, $reason)
  ├─ getUserNotifications($user_id, $limit = 10)
  ├─ markNotificationAsRead($notification_id)
  ├─ getUnreadNotificationCount($user_id)
  ├─ broadcastIncidentAlert($incident_id, $incident_data, $nlp_analysis)
  └─ sendEmailAlert($email, $title, $message)
```

#### 4. **ReviewRequestSystem.php**
Handles review requests and approvals.

**Key Classes & Methods:**
```php
class ReviewRequestSystem
  ├─ createReviewRequest($incident_id, $requested_by, $reason, $priority)
  ├─ respondToReviewRequest($review_request_id, $responded_by, $response, ...)
  ├─ getIncidentReviewRequests($incident_id)
  ├─ getPendingReviewRequests($user_id = null)
  ├─ getReviewStatistics()
  ├─ exportReviewRequest($review_request_id)
  └─ logReviewEvent($incident_id, $event_type, $description, $user_id)
```

---

## 💾 Database Schema

### New Tables Created

#### `notifications`
Stores all system notifications for users.

**Fields:**
- `id` - Primary key
- `user_id` - Recipient
- `incident_id` - Related incident
- `notification_type` - Type of notification
- `title` - Notification title
- `message` - Detailed message
- `threat_level` - Critical, High, Medium, Low
- `urgency` - Priority label
- `is_read` - Read status
- `read_at` - When it was read
- `created_at` - Creation timestamp

#### `review_requests`
Tracks review requests for incidents.

**Fields:**
- `id` - Primary key
- `incident_id` - Incident being reviewed
- `requested_by` - User who requested review
- `reason` - Reason for review
- `priority` - High, Normal, Low
- `status` - Pending, Completed, Rejected
- `responded_by` - User who responded
- `response` - Approved, Denied, Needs Revision
- `findings` - Review findings
- `recommendations` - Recommendations
- `created_at` - Request timestamp
- `responded_at` - Response timestamp

#### `workflow_events`
Audit trail for all incident workflow events.

**Fields:**
- `id` - Primary key
- `incident_id` - Related incident
- `event_type` - Type of event
- `description` - Event details
- `performed_by` - User or system
- `created_at` - Event timestamp

#### `nlp_analysis_cache`
Caches NLP analysis results for performance.

**Fields:**
- `id` - Primary key
- `incident_id` - Associated incident
- `analysis_data` - Full NLP result (JSON)
- `created_at` - Cache creation
- `updated_at` - Last update

#### `system_alerts`
Critical incident alerts requiring attention.

**Fields:**
- `id` - Primary key
- `incident_id` - Alert related to
- `alert_type` - Type of alert
- `severity` - Critical, High, Medium, Low
- `alert_message` - Alert details
- `resolved` - Resolution status
- `resolved_by` - User who resolved
- `resolved_at` - Resolution timestamp
- `created_at` - Creation timestamp

### Modified Tables

**incidents** - Added NLP fields:
- `nlp_sentiment` - Sentiment analysis result
- `nlp_threat_level` - Threat level (Critical/High/Medium/Low)
- `nlp_severity_score` - Severity score (0-100)
- `nlp_emotions` - Detected emotions (JSON)
- `nlp_analysis_data` - Full analysis (JSON)
- `nlp_confidence_score` - Confidence (0-100)
- `nlp_summary` - Human-readable summary
- `review_requested` - Review flag
- `review_requested_at` - Review request timestamp
- `review_completed` - Completion flag
- `review_completed_at` - Completion timestamp

**blotters** - Added integration fields:
- `incident_id` - Reference to incident
- `created_from_incident` - Auto-creation flag
- `nlp_threat_level` - From NLP analysis
- `nlp_severity_score` - From NLP analysis

**case_assignments** - Added NLP fields:
- `nlp_threat_level` - Threat level
- `nlp_severity_score` - Severity score
- `incident_id` - Incident reference

---

## 🔧 Installation & Setup

### Step 1: Run Database Migration

```bash
# Connect to MySQL and run:
mysql -u root -p law&inci < database2/nlp_workflow_migration.sql
```

Or manually import through phpMyAdmin.

### Step 2: Verify File Placement

Ensure these files are in place:
```
modules/
├── NaturalLanguageProcessor.php
├── IncidentWorkflowManager.php
├── NotificationSystem.php
├── ReviewRequestSystem.php
└── Incident_report.php (updated)
```

### Step 3: Test the System

1. Create a new incident report
2. Verify NLP analysis in modal
3. Check notifications sent
4. Verify blotter entry created
5. Check workflow events logged

---

## 📊 NLP Analysis Examples

### Example 1: Child Abuse Report
**Input Narrative:**
> "Found a 6-year-old boy with severe bruises on his arms and back. He seems scared and reluctant to talk about his home. Parents were not cooperative with questioning."

**NLP Output:**
```json
{
  "sentiment": {"sentiment": "Negative", "score": 5},
  "emotions": ["Fear", "Trauma", "Distress"],
  "severity_score": 85,
  "threat_level": "Critical",
  "actionable_items": ["medical", "investigation", "witnesses"],
  "confidence_score": 95.5,
  "key_phrases": [
    "Found a 6-year-old boy with severe bruises",
    "He seems scared and reluctant to talk",
    "Parents were not cooperative"
  ]
}
```

### Example 2: Theft Report
**Input Narrative:**
> "Reported stolen cash from home, estimated value 5,000 pesos. Neighbors saw unknown person around the property yesterday around 3 PM."

**NLP Output:**
```json
{
  "sentiment": {"sentiment": "Negative", "score": 2},
  "emotions": ["Anger"],
  "severity_score": 35,
  "threat_level": "Medium",
  "actionable_items": ["witnesses", "investigation"],
  "confidence_score": 88.2,
  "key_phrases": [
    "Reported stolen cash from home",
    "Neighbors saw unknown person around the property"
  ]
}
```

---

## 🎯 DFD Level 0 Flows Implementation

### Flow 1: BCPC Officer → Incident Report
```
BCPC Officer fills Incident Form
         ↓
System validates & applies NLP
         ↓
Incident stored with auto-classification
         ↓
Blotter entry auto-created
         ↓
Case created in case management
         ↓
Notifications sent to Barangay Official
         ↓
Officer assigned (if High/Critical priority)
         ↓
Workflow events logged for audit
```

### Flow 2: Complainant → Incident Information
```
Complainant reports incident
         ↓
NLP analyzes complaint narrative
         ↓
Emotions, threat level, severity detected
         ↓
Case automatically routed to appropriate officer
         ↓
Complainant receives case number
         ↓
Barangay Official notified
```

### Flow 3: Case Notification → Barangay Official
```
Critical incident detected
         ↓
NLP flags as Critical/High
         ↓
Real-time notification generated
         ↓
Email alert sent (if configured)
         ↓
Dashboard badge shows urgency
         ↓
Auto-assignment to available officer
```

### Flow 4: Review Request
```
Officer/Admin requests review
         ↓
Review request created
         ↓
Admins notified
         ↓
Review findings recorded
         ↓
Recommendations provided
         ↓
Requestor notified of response
         ↓
Audit trail maintained
```

---

## 🔐 Security Considerations

1. **Data Validation**: All inputs are validated before NLP processing
2. **SQL Injection Prevention**: Prepared statements used throughout
3. **Authorization**: Role-based access control enforced
4. **Audit Trail**: All actions logged with user/timestamp
5. **Data Privacy**: Sensitive data encrypted in transit
6. **API Security**: Notification system uses secure delivery

---

## ⚡ Performance Optimization

### Caching Strategy
```php
// NLP analysis is cached in nlp_analysis_cache table
// Avoids reprocessing identical narratives
$cache = getAnalysisCache($incident_id);
if (!$cache) {
    $analysis = NaturalLanguageProcessor::analyzeIncident(...);
    saveAnalysisCache($incident_id, $analysis);
}
```

### Database Indexes
- `idx_incidents_nlp_threat` - Query by threat level
- `idx_incidents_nlp_severity` - Sort by severity
- `idx_user_unread` - Get unread notifications
- `idx_pending_reviews` - Query pending reviews

### Async Processing
Consider using queue system for:
- Email notifications (PHPMailer)
- Workflow event logging
- Large NLP analysis batches

---

## 🧪 Testing Guide

### Test Case 1: Critical Incident
```php
$test_narrative = "Found unconscious victim with gunshot wound. 
Suspect fled scene. Emergency services called.";

$analysis = NaturalLanguageProcessor::analyzeIncident(
    $test_narrative, 
    'Violence'
);

// Expected: threat_level = 'Critical', severity_score > 80
assert($analysis['threat_level'] === 'Critical');
assert($analysis['severity_score'] > 80);
```

### Test Case 2: Workflow Processing
```php
$incident_data = [
    'case_no' => 'INC-20250107-ABC01',
    'incident_type' => 'Abuse',
    'narrative' => '...',
    // ... other fields
];

$workflow = new IncidentWorkflowManager($pdo);
$result = $workflow->processIncidentReport($incident_data);

// Expected: All workflow steps completed
assert($result['success'] === true);
assert($result['incident_id'] > 0);
assert($result['blotter_id'] > 0);
assert($result['case_id'] > 0);
```

### Test Case 3: Notification Delivery
```php
$notification_system = new NotificationSystem($pdo);

// Verify notification created
$count = $notification_system->getUnreadNotificationCount($user_id);
assert($count > 0);

// Verify can mark as read
$notification_system->markNotificationAsRead($notification_id);
$count = $notification_system->getUnreadNotificationCount($user_id);
assert($count === 0);
```

---

## 📈 Reporting & Analytics

### Available Views

#### `incident_nlp_summary`
Summary of incidents with NLP analysis.

#### `critical_incidents_view`
High and Critical threat incidents only.

### Sample Queries

**Get all critical incidents:**
```sql
SELECT * FROM critical_incidents_view 
ORDER BY nlp_severity_score DESC;
```

**Pending reviews:**
```sql
SELECT * FROM review_requests 
WHERE status = 'Pending' 
ORDER BY priority DESC, created_at ASC;
```

**Officer workload:**
```sql
SELECT bo.user_id, u.fullname, bo.current_case_load, bo.max_case_load
FROM bcpc_officers bo
JOIN signup u ON bo.user_id = u.user_id
WHERE bo.is_available = 1
ORDER BY bo.current_case_load DESC;
```

---

## 🚨 Common Issues & Solutions

### Issue 1: NLP Fields Not Showing
**Solution**: Run database migration script
```bash
mysql -u root -p law&inci < database2/nlp_workflow_migration.sql
```

### Issue 2: Notifications Not Appearing
**Solution**: Check notification table exists and user_id is correct
```sql
SELECT COUNT(*) FROM notifications WHERE user_id = 1;
```

### Issue 3: Email Alerts Not Sending
**Solution**: Verify mail_env.php configuration
```php
// Check config/mail_env.php for SMTP settings
echo "SMTP Host: " . SMTP_HOST;
echo "SMTP Port: " . SMTP_PORT;
```

### Issue 4: Officer Auto-Assignment Not Working
**Solution**: Verify bcpc_officers records exist with available flag
```sql
SELECT * FROM bcpc_officers WHERE is_available = 1;
```

---

## 📚 API Reference

### Process Incident Report
```php
$workflow = new IncidentWorkflowManager($pdo);
$result = $workflow->processIncidentReport([
    'case_no' => 'INC-...',
    'incident_type' => 'Abuse|Violence|Theft|...',
    'narrative' => 'Incident description...',
    'location' => 'Address...',
    'reporter_name' => 'Name...',
    // ... other fields
]);
// Returns: ['success' => bool, 'incident_id' => int, ...]
```

### Analyze Text with NLP
```php
$nlp = new NaturalLanguageProcessor();
$analysis = $nlp->analyzeIncident('narrative text', 'Abuse');
// Returns: Complete NLP analysis array
```

### Send Notification
```php
$notif = new NotificationSystem($pdo);
$notif->notifyBarangayOfficial($incident_id, $incident_data, $nlp_analysis);
```

### Create Review Request
```php
$review = new ReviewRequestSystem($pdo);
$result = $review->createReviewRequest($incident_id, $user_id, 'reason');
// Returns: ['success' => bool, 'review_request_id' => int, ...]
```

---

## 🔄 Workflow State Diagram

```
[Draft] 
   ↓
[Submitted] → NLP Analysis → [Verified/Under Review]
   ↓                             ↓
[Review Requested] ← (optional)  [Resolved]
   ↓                             ↓
[Review Completed]         [Closed/Archived]
```

---

## 📞 Support & Maintenance

### Regular Maintenance Tasks

1. **Clear Notification Archive** (monthly)
```sql
DELETE FROM notifications WHERE created_at < DATE_SUB(NOW(), INTERVAL 3 MONTH);
```

2. **Archive Resolved Cases** (quarterly)
```sql
UPDATE incidents SET status = 'Archived' 
WHERE status = 'Closed' AND updated_at < DATE_SUB(NOW(), INTERVAL 6 MONTH);
```

3. **Optimize NLP Cache** (monthly)
```sql
DELETE FROM nlp_analysis_cache WHERE updated_at < DATE_SUB(NOW(), INTERVAL 1 MONTH);
```

4. **Update Officer Workload** (daily)
```sql
UPDATE bcpc_officers bo
SET current_case_load = (
    SELECT COUNT(*) FROM incidents 
    WHERE assigned_to = bo.user_id AND status IN ('Submitted', 'Under Review')
);
```

---

## ✅ Checklist for Deployment

- [ ] Database migration script executed
- [ ] All PHP module files in place
- [ ] Incident_report.php updated with NLP integration
- [ ] Blotter table has incident_id foreign key
- [ ] Case assignments linked to incidents
- [ ] Email configuration tested
- [ ] BCPC officers created with availability flag
- [ ] Sample incidents created and analyzed
- [ ] Notifications tested for delivery
- [ ] Review requests tested end-to-end
- [ ] Audit trail entries being logged
- [ ] Performance acceptable (< 2s response time)

---

## 📄 Version History

| Version | Date | Changes |
|---------|------|---------|
| 2.0 | Jan 2025 | Complete NLP implementation with workflow automation |
| 1.0 | Dec 2024 | Initial incident reporting system |

---

**System Status:** ✅ Production Ready  
**Last Updated:** January 7, 2025  
**Maintained By:** Law Enforcement Systems Team

