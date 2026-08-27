<?php
$base_url = '../';
require_once __DIR__ . '/../includes/user_auth.php';
$page_title = 'Incident Logging & Classification';

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db_connect.php';
if (!isset($pdo) || !$pdo) {
    $pdo = getDBConnection();
}

$userId = $_SESSION['user_id'] ?? null;
$userApproved = true;
if ($userId) {
    try {
        $approvalStmt = $pdo->prepare("SELECT admin_approved FROM signup WHERE user_id = ?");
        $approvalStmt->execute([$userId]);
        $approvalRow = $approvalStmt->fetch(PDO::FETCH_ASSOC);
        $userApproved = !empty($approvalRow['admin_approved']) && (int)$approvalRow['admin_approved'] === 1;
    } catch (Exception $e) {
        $userApproved = true;
    }
}

if ($userId && strtolower($_SESSION['role'] ?? '') !== 'admin' && !$userApproved) {
    require_once __DIR__ . '/../includes/header.php';
    echo '<div class="main-content"><div class="content-container">';
    echo '<div class="alert alert-warning"><h4>Access Locked</h4><p>Your account is pending administrator approval. The incident reporting module is locked until an administrator approves your account.</p></div>';
    echo '</div></div>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// Load NLP and Workflow systems (needed before processing POST requests)
require_once __DIR__ . '/NaturalLanguageProcessor.php';
require_once __DIR__ . '/IncidentWorkflowManager.php';
require_once __DIR__ . '/IncidentRoutingManager.php';
require_once __DIR__ . '/NotificationSystem.php';
require_once __DIR__ . '/ReviewRequestSystem.php';
require_once __DIR__ . '/../includes/attachment_manager.php';
require_once __DIR__ . '/../config/integration_config.php';
require_once __DIR__ . '/OperationalModuleIntegrator.php';

$routing_manager = new IncidentRoutingManager($pdo);
$routing_manager->ensureSchema();

if (!function_exists('capitalize_first')) {
    function capitalize_first($text) {
        return ucfirst(strtolower($text));
    }
}

// --- INCIDENT CLASSIFICATION ENGINE ---
if (!class_exists('IncidentClassifier')) {
    class IncidentClassifier {
        private static $abuse_keywords = ['abuse', 'hit', 'punch', 'slap', 'hurt', 'mistreat', 'cruelty', 'beating', 'severe'];
        private static $neglect_keywords = ['neglect', 'abandon', 'unsupervised', 'malnourish', 'unhygenic', 'no care', 'abandoned'];
        private static $violence_keywords = ['violence', 'fight', 'attack', 'stab', 'shoot', 'kill', 'murder', 'assault', 'violent', 'rape', 'sexual'];
        private static $theft_keywords = ['theft', 'steal', 'robbery', 'burglary', 'shoplifting', 'stolen', 'missing items'];
        private static $emergency_keywords = ['emergency', 'urgent', 'critical', 'life-threatening', 'severe', 'immediate', 'danger', 'dangerous'];

        public static function classifyIncident($narrative, $selected_type) {
            $narrative_lower = strtolower($narrative);
            $scores = [];

            // Score each category based on keyword matches
            $scores['Abuse'] = self::matchKeywords($narrative_lower, self::$abuse_keywords);
            $scores['Neglect'] = self::matchKeywords($narrative_lower, self::$neglect_keywords);
            $scores['Violence'] = self::matchKeywords($narrative_lower, self::$violence_keywords);
            $scores['Theft'] = self::matchKeywords($narrative_lower, self::$theft_keywords);

            // Add weight to selected type
            if (!empty($selected_type) && isset($scores[$selected_type])) {
                $scores[$selected_type] += 2;
            }

            // Find highest scoring classification
            arsort($scores);
            $top_classification = key($scores);

            return !empty($top_classification) ? $top_classification : ($selected_type ?: 'Other');
        }

        public static function detectHighRisk($narrative, $incident_type) {
            $narrative_lower = strtolower($narrative);
            
            // Check for emergency keywords or violence/abuse in narrative
            $has_emergency = count(array_filter(self::$emergency_keywords, function($keyword) use ($narrative_lower) {
                return strpos($narrative_lower, $keyword) !== false;
            })) > 0;

            $has_violence = count(array_filter(array_merge(self::$violence_keywords, self::$abuse_keywords), function($keyword) use ($narrative_lower) {
                return strpos($narrative_lower, $keyword) !== false;
            })) > 0;

            $is_violence_type = in_array($incident_type, ['Violence', 'Assault']);

            return ($has_emergency || $has_violence || $is_violence_type) ? 1 : 0;
        }

        public static function calculateUrgency($is_high_risk, $incident_type) {
            if ($is_high_risk) {
                return 'Critical';
            }
            
            switch ($incident_type) {
                case 'Violence':
                case 'Assault':
                    return 'High';
                case 'Abuse':
                case 'Neglect':
                    return 'High';
                case 'Theft':
                    return 'Medium';
                default:
                    return 'Medium';
            }
        }

        private static function matchKeywords($text, $keywords) {
            $count = 0;
            foreach ($keywords as $keyword) {
                if (strpos($text, $keyword) !== false) {
                    $count++;
                }
            }
            return $count;
        }
    }
}

// --- MESSAGE DISPLAY FUNCTION ---
if (!function_exists('display_message')) {
    function display_message() {
        if (isset($_SESSION['message'])) {
            $type = $_SESSION['message']['type'];
            $text = $_SESSION['message']['text'];
            echo "<div class='alert alert-{$type} alert-dismissible fade show' role='alert'>
                    <i class='bi bi-" . ($type === 'success' ? 'check-circle' : 'exclamation-circle') . "-fill me-2'></i>
                    {$text}
                    <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
                  </div>";
            unset($_SESSION['message']);
        }
    }
}

if (!function_exists('generate_case_number')) {
    function generate_case_number() {
        return 'INC-' . date('Ymd') . '-' . strtoupper(substr(md5(time() . rand()), 0, 5));
    }
}

// --- FORM SUBMISSION HANDLER ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_incident'])) {
    try {
        // Collect form data
        $reporter_name = trim($_POST['reporter_name'] ?? '');
        $reporter_email = trim($_POST['reporter_email'] ?? '');
        $reporter_phone = trim($_POST['reporter_phone'] ?? '');
        $reporter_type = $_POST['reporter_type'] ?? 'Citizen';
        
        $incident_date = $_POST['incident_date'] ?? '';
        $incident_time = $_POST['incident_time'] ?? '00:00';
        $location = trim($_POST['location'] ?? '');
        $latitude = !empty($_POST['latitude']) ? floatval($_POST['latitude']) : null;
        $longitude = !empty($_POST['longitude']) ? floatval($_POST['longitude']) : null;
        
        $incident_type = $_POST['incident_type'] ?? 'Other';
        $incident_subtype = trim($_POST['incident_subtype'] ?? '');
        $report_type = $_POST['report_type'] ?? 'Walk-in Complaint';
        $incident_category = $_POST['incident_category'] ?? $incident_type;
        $narrative = trim($_POST['narrative'] ?? '');
        $evidence_description = trim($_POST['evidence_description'] ?? '');
        
        $victim_name = trim($_POST['victim_name'] ?? '');
        $victim_age = !empty($_POST['victim_age']) ? intval($_POST['victim_age']) : null;
        $victim_gender = $_POST['victim_gender'] ?? null;
        $suspect_name = trim($_POST['suspect_name'] ?? '');

        // Validation
        if (empty($reporter_name)) throw new Exception('Reporter name is required.');
        if (empty($incident_date)) throw new Exception('Incident date is required.');
        if (empty($narrative)) throw new Exception('Incident narrative/description is required.');

        // Generate case number
        $case_no = generate_case_number();
        
        // Get user ID from session
        $created_by = $_SESSION['user_id'] ?? null;

        // Prepare incident data for workflow
        $incident_data = [
            'case_no' => $case_no,
            'incident_type' => $incident_type,
            'incident_subtype' => $incident_subtype,
            'report_type' => $report_type,
            'incident_category' => $incident_category,
            'reporter_name' => $reporter_name,
            'reporter_email' => $reporter_email,
            'reporter_phone' => $reporter_phone,
            'reporter_type' => $reporter_type,
            'incident_date' => $incident_date,
            'incident_time' => $incident_time,
            'location' => $location,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'narrative' => $narrative,
            'evidence_description' => $evidence_description,
            'victim_name' => $victim_name,
            'victim_age' => $victim_age,
            'victim_gender' => $victim_gender,
            'suspect_name' => $suspect_name,
            'created_by' => $created_by
        ];

        // Process incident through NLP and Workflow system
        $workflow_manager = new IncidentWorkflowManager($pdo);
        $workflow_result = $workflow_manager->processIncidentReport($incident_data);

        if ($workflow_result['success']) {
            // Handle file uploads
            $incident_id = $workflow_result['incident_id'] ?? null;
            if ($incident_id) {
                handleFileUpload('incident', $incident_id, $created_by);
            }
            
            try {
                require_once __DIR__ . '/OperationalModuleIntegrator.php';
                $integrator = new OperationalModuleIntegrator($pdo);
                $autoIntegrateRes = $integrator->processInbound([
                    'source' => 'incident_module',
                    'incident_id' => $case_no,
                    'location' => $location,
                    'description' => $narrative,
                    'emergency_level' => (in_array($incident_type, ['Violence', 'Assault', 'Theft']) || !empty($workflow_result['urgency_score']) && $workflow_result['urgency_score'] > 70) ? 'High' : 'Medium',
                    'complainant_name' => $reporter_name,
                    'timestamp' => ($incident_date ?: date('Y-m-d')) . ' ' . ($incident_time ?: date('H:i:s'))
                ], true);
            } catch (Exception $ex) {
                error_log("OperationalModuleIntegrator notice: " . $ex->getMessage());
            }

            $_SESSION['message'] = [
                'type' => 'success',
                'text' => "🎉 Incident report submitted successfully!<br>
                          Case #: <strong>{$case_no}</strong><br>
                          <small>System has automatically classified and formatted data for connected integration modules (Group 3, Group 5, Group 7, and CCTV Partner API).</small>"
            ];

            require_once __DIR__ . '/../includes/audit_logger.php';
            logAuditTrail('INCIDENT_CREATE', 'Incident Registry', $case_no, "Filed incident report ({$incident_type} at {$location}) with automatic AI classification.", 'SUCCESS', $pdo);
        } else {
            throw new Exception($workflow_result['error'] ?? 'Failed to process incident');
        }        header("Location: Incident_report.php?view=submitted");
        exit;

    } catch (Exception $e) {
        $_SESSION['message'] = ['type' => 'danger', 'text' => '❌ Error: ' . $e->getMessage()];
    }
}

// --- ADMIN UPDATE HANDLER ---
$isAdminOrOfficer = in_array(strtolower($_SESSION['role'] ?? ''), ['admin', 'officer']) || !empty($_SESSION['admin_user_id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_incident']) && $isAdminOrOfficer) {
    try {
        $incident_id = intval($_POST['incident_id'] ?? 0);
        $status = $_POST['status'] ?? 'Submitted';
        $manual_classification = $_POST['manual_classification'] ?? null;
        $routing_group = $_POST['routing_group'] ?? null;
        $routing_notes = trim($_POST['routing_notes'] ?? '');
        $urgency_level = $_POST['urgency_level'] ?? 'Medium';
        $is_high_risk = isset($_POST['is_high_risk']) ? 1 : 0;
        $admin_notes = trim($_POST['admin_notes'] ?? '');
        $assigned_to = !empty($_POST['assigned_to']) ? intval($_POST['assigned_to']) : null;
        
        $updated_by = $_SESSION['user_id'] ?? ($_SESSION['admin_user_id'] ?? 1);

        $sql = "UPDATE incidents SET 
                status = ?, 
                manual_classification = ?, 
                urgency_level = ?, 
                is_high_risk = ?, 
                admin_notes = ?, 
                assigned_to = ?,
                routing_group = ?,
                routing_status = ?,
                forwarding_notes = ?,
                updated_by = ?,
                updated_at = NOW() 
                WHERE id = ?";

        $stmt = $pdo->prepare($sql);
        $routing_status = !empty($routing_group) ? 'Forwarded' : 'Pending';
        $success = $stmt->execute([
            $status, $manual_classification, $urgency_level, $is_high_risk, 
            $admin_notes, $assigned_to, $routing_group, $routing_status, $routing_notes, $updated_by, $incident_id
        ]);

        if ($success && !empty($routing_group)) {
            $routing_manager->forwardIncident($incident_id, $routing_group, $updated_by, $routing_notes);
        }

        if ($success) {
            // Dispatch real-time status update to Policy API (Maeren Marto: policy.alertaraqc.com/api/complaints_status_receive.php)
            try {
                $stmtIncRef = $pdo->prepare("SELECT case_no, incident_type, location, narrative FROM incidents WHERE id = ?");
                $stmtIncRef->execute([$incident_id]);
                $incData = $stmtIncRef->fetch(PDO::FETCH_ASSOC);

                $integrator = new OperationalModuleIntegrator($pdo);
                $integrator->dispatchComplaintStatusUpdate([
                    'case_no' => $incData['case_no'] ?? ('INC-' . $incident_id),
                    'complaint_id' => $incData['case_no'] ?? ('INC-' . $incident_id),
                    'reference_no' => $incData['case_no'] ?? ('INC-' . $incident_id),
                    'status' => $status,
                    'urgency_level' => $urgency_level,
                    'incident_type' => $manual_classification ?: ($incData['incident_type'] ?? 'General Incident'),
                    'location' => $incData['location'] ?? '',
                    'admin_notes' => $admin_notes,
                    'updated_by' => $_SESSION['fullname'] ?? ($_SESSION['username'] ?? 'Law Enforcement Admin')
                ]);
            } catch (Exception $exSync) {
                error_log("Notice dispatching status to Policy: " . $exSync->getMessage());
            }

            require_once __DIR__ . '/../includes/audit_logger.php';
            logAuditTrail('INCIDENT_UPDATE', 'Incident Registry', (string)$incident_id, "Updated incident report ID #{$incident_id}: status {$status}, urgency {$urgency_level}. Dispatched status sync to Policy.", 'SUCCESS', $pdo);

            $_SESSION['message'] = [
                'type' => 'success',
                'text' => 'Incident classification and status updated successfully! Status synced to partner department.'
            ];
        }

        header("Location: Incident_report.php");
        exit;

    } catch (Exception $e) {
        $_SESSION['message'] = ['type' => 'danger', 'text' => 'Error: ' . $e->getMessage()];
        header("Location: Incident_report.php");
        exit;
    }
}

// --- ADMIN FORWARDING HANDLER ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['forward_incident']) && $isAdminOrOfficer) {
    try {
        $incident_id = intval($_POST['incident_id'] ?? 0);
        $group = $_POST['forward_to_group'] ?? '';
        $notes = trim($_POST['forward_notes'] ?? '');
        $active_user_id = $_SESSION['user_id'] ?? ($_SESSION['admin_user_id'] ?? 1);

        if (!$incident_id || !$group) {
            throw new Exception('Please select a destination group.');
        }

        // Fetch incident data
        $stmtInc = $pdo->prepare("SELECT * FROM incidents WHERE id = ?");
        $stmtInc->execute([$incident_id]);
        $inc = $stmtInc->fetch(PDO::FETCH_ASSOC);

        if (!$inc) {
            throw new Exception('Incident record not found.');
        }

        $caseNo = $inc['case_no'] ?? ('INC-' . $incident_id);
        $groupLabels = [
            'GRP4' => 'GRP4 - Emergency Response Hub',
            'GRP5' => 'GRP5 - Community Complaints & Inspection',
            'GRP6' => 'GRP6 - Crime Analytics & GIS Mapping'
        ];
        $targetGroupName = $groupLabels[$group] ?? $group;

        // Perform internal forward logging
        $routing_manager->forwardIncident($incident_id, $group, $active_user_id, $notes);

        // Dispatch to partner API
        try {
            $integrator = new OperationalModuleIntegrator($pdo);
            $forwardPayload = [
                'case_no' => $caseNo,
                'incident_id' => $incident_id,
                'incident_type' => $inc['incident_type'] ?? 'General Incident',
                'incident_subtype' => $inc['incident_subtype'] ?? '',
                'location' => $inc['location'] ?? '',
                'incident_date' => $inc['incident_date'] ?? date('Y-m-d'),
                'incident_time' => $inc['incident_time'] ?? date('H:i:s'),
                'narrative' => $inc['narrative'] ?? $inc['description'] ?? '',
                'forwarded_to' => $targetGroupName,
                'forward_notes' => $notes,
                'forwarded_at' => date('Y-m-d H:i:s')
            ];

            if ($group === 'GRP6') {
                $integrator->dispatchToGroup5CrimeMapApi($forwardPayload);
            } elseif ($group === 'GRP4') {
                $integrator->dispatchToGroup3ResourceApi($forwardPayload);
            } elseif ($group === 'GRP5') {
                $integrator->dispatchToGroup7InspectionApi($forwardPayload);
            }
        } catch (Exception $e) {
            error_log("Notice: Remote dispatch note: " . $e->getMessage());
        }

        require_once __DIR__ . '/../includes/audit_logger.php';
        logAuditTrail('INCIDENT_FORWARD', 'Incident Registry', $caseNo, "Forwarded incident Case #{$caseNo} to {$targetGroupName}.", 'SUCCESS', $pdo);

        $_SESSION['message'] = ['type' => 'success', 'text' => "✅ Case #{$caseNo} successfully forwarded to {$targetGroupName}!"];
        header("Location: Incident_report.php");
        exit;
    } catch (Exception $e) {
        $_SESSION['message'] = ['type' => 'danger', 'text' => '❌ Error forwarding incident: ' . $e->getMessage()];
        header("Location: Incident_report.php");
        exit;
    }
}

// --- DELETE INCIDENT HANDLER ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_incident' && $isAdminOrOfficer) {
    try {
        $incident_id = intval($_POST['incident_id'] ?? 0);
        
        if (!$incident_id) {
            throw new Exception('Invalid incident ID');
        }

        $pdo->beginTransaction();
        
        // 1. Delete attachments associated with this incident
        try {
            $attachment_manager = new AttachmentManager($pdo);
            $attachments = $attachment_manager->getAttachments('incident', $incident_id);
            
            foreach ($attachments as $attachment) {
                try {
                    $attachment_manager->deleteAttachment($attachment['id'], $_SESSION['user_id'] ?? 1);
                } catch (Exception $e) {
                    error_log("Failed to delete attachment {$attachment['id']}: " . $e->getMessage());
                }
            }
        } catch (Exception $e) {}

        // 2. Unlink or clean up foreign-keyed child records
        // Blotters: unlink incident_id so the blotter record is preserved without FK failure
        try {
            $pdo->prepare("UPDATE blotters SET incident_id = NULL WHERE incident_id = ?")->execute([$incident_id]);
        } catch (Exception $e) {}

        // Case assignments
        try {
            $pdo->prepare("DELETE FROM case_assignments WHERE incident_id = ?")->execute([$incident_id]);
        } catch (Exception $e) {}

        // NLP analysis cache
        try {
            $pdo->prepare("DELETE FROM nlp_analysis_cache WHERE incident_id = ?")->execute([$incident_id]);
        } catch (Exception $e) {}

        // Notifications
        try {
            $pdo->prepare("DELETE FROM notifications WHERE incident_id = ?")->execute([$incident_id]);
        } catch (Exception $e) {}

        // Review requests
        try {
            $pdo->prepare("DELETE FROM review_requests WHERE incident_id = ?")->execute([$incident_id]);
        } catch (Exception $e) {}

        // System alerts
        try {
            $pdo->prepare("DELETE FROM system_alerts WHERE incident_id = ?")->execute([$incident_id]);
        } catch (Exception $e) {}

        // Incident forwards
        try {
            $pdo->prepare("DELETE FROM incident_forwards WHERE incident_id = ?")->execute([$incident_id]);
        } catch (Exception $e) {}

        // Incident history
        try {
            $pdo->prepare("DELETE FROM incident_history WHERE incident_id = ?")->execute([$incident_id]);
        } catch (Exception $e) {}

        // Evidence items: unlink incident_id
        try {
            $pdo->prepare("UPDATE evidence_items SET incident_id = NULL WHERE incident_id = ?")->execute([$incident_id]);
        } catch (Exception $e) {}

        // 3. Delete the incident
        $stmt = $pdo->prepare("DELETE FROM incidents WHERE id = ?");
        $success = $stmt->execute([$incident_id]);
        
        if ($success && $stmt->rowCount() > 0) {
            $pdo->commit();
            $_SESSION['message'] = [
                'type' => 'success',
                'text' => '✅ Incident case deleted successfully!'
            ];
        } else {
            $pdo->rollBack();
            throw new Exception('Failed to delete incident or incident not found');
        }
        
        header("Location: Incident_report.php");
        exit;
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['message'] = ['type' => 'danger', 'text' => '❌ Error deleting incident: ' . $e->getMessage()];
        header("Location: Incident_report.php");
        exit;
    }
}

// --- USER EDIT INCIDENT HANDLER ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user_incident'])) {
    try {
        $incident_id = intval($_POST['incident_id'] ?? 0);
        $user_id = $_SESSION['user_id'];
        
        if (!$incident_id) {
            throw new Exception('Invalid incident ID');
        }
        
        // Verify the user owns this incident
        $stmt = $pdo->prepare("SELECT id FROM incidents WHERE id = ? AND created_by = ?");
        $stmt->execute([$incident_id, $user_id]);
        if (!$stmt->fetch()) {
            throw new Exception('You can only edit your own incident reports');
        }
        
        // Update the incident (only basic fields that users can edit)
        $sql = "UPDATE incidents SET 
                reporter_name = ?, 
                reporter_type = ?, 
                incident_date = ?, 
                incident_time = ?, 
                location = ?, 
                description = ?, 
                updated_at = NOW()
                WHERE id = ? AND created_by = ?";
        
        $stmt = $pdo->prepare($sql);
        $success = $stmt->execute([
            trim($_POST['reporter_name']),
            $_POST['reporter_type'],
            $_POST['incident_date'],
            !empty($_POST['incident_time']) ? $_POST['incident_time'] : null,
            trim($_POST['location']),
            trim($_POST['description']),
            $incident_id,
            $user_id
        ]);
        
        if ($success) {
            // Handle file uploads for new attachments
            handleFileUpload('incident', $incident_id, $user_id);
            
            $_SESSION['message'] = [
                'type' => 'success',
                'text' => '✅ Your incident report has been updated successfully!'
            ];
        } else {
            throw new Exception('Failed to update incident report');
        }
        
        header("Location: Incident_report.php");
        exit;
        
    } catch (Exception $e) {
        $_SESSION['message'] = ['type' => 'danger', 'text' => '❌ Error updating incident: ' . $e->getMessage()];
        header("Location: Incident_report.php");
        exit;
    }
}

// --- FETCH INCIDENTS DATA ---
$incidents = [];
$filter_status = $_GET['status'] ?? '';
$filter_urgency = $_GET['urgency'] ?? '';
$user_role = $_SESSION['role'] ?? 'User';
$isAdminUser = in_array(strtolower($_SESSION['role'] ?? ''), ['admin', 'officer']) || !empty($_SESSION['admin_user_id']);
$user_id = $_SESSION['user_id'] ?? ($_SESSION['admin_user_id'] ?? null);

try {
    $sql = "SELECT i.*, 
                   CONCAT(COALESCE(u.username, ''), ' (', COALESCE(u.fullname, ''), ')') as created_by_name,
                   CONCAT(COALESCE(u2.username, ''), ' (', COALESCE(u2.fullname, ''), ')') as assigned_to_name,
                   i.nlp_sentiment,
                   i.nlp_threat_level,
                   i.nlp_severity_score,
                   i.nlp_emotions,
                   i.nlp_confidence_score,
                   COUNT(DISTINCT n.id) as notification_count
            FROM incidents i
            LEFT JOIN signup u ON i.created_by = u.user_id
            LEFT JOIN signup u2 ON i.assigned_to = u2.user_id
            LEFT JOIN notifications n ON i.id = n.incident_id
            WHERE 1=1";

    $params = [];
    // Role-based filtering
    if (!$isAdminUser && $user_id) {
        $sql .= " AND (i.created_by = ? OR i.status IN ('Verified', 'Resolved'))";
        $params[] = $user_id;
    }

    if (!empty($filter_status)) {
        $sql .= " AND i.status = ?";
        $params[] = $filter_status;
    }
    if (!empty($filter_urgency)) {
        $sql .= " AND i.urgency_level = ?";
        $params[] = $filter_urgency;
    }

    $sql .= " GROUP BY i.id ORDER BY i.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $incidents = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $_SESSION['message'] = ['type' => 'danger', 'text' => 'Database error: ' . $e->getMessage()];
}

// --- STATUS AND URGENCY BADGE RENDERING ---
if (!function_exists('render_status_badge')) {
    function render_status_badge($status) {
        $classes = [
            'Draft' => 'bg-secondary-subtle text-secondary',
            'Pending' => 'bg-primary-subtle text-primary',
            'Submitted' => 'bg-primary-subtle text-primary',
            'Under Review' => 'bg-warning-subtle text-warning',
            'Verified' => 'bg-info-subtle text-info',
            'Forwarded' => 'bg-info-subtle text-info',
            'Resolved' => 'bg-success-subtle text-success',
            'Closed' => 'bg-dark-subtle text-dark',
            'Archived' => 'bg-secondary-subtle text-secondary'
        ];
        $class = $classes[$status] ?? 'bg-secondary-subtle text-secondary';
        return "<span class='badge {$class}'>{$status}</span>";
    }
}

if (!function_exists('render_urgency_badge')) {
    function render_urgency_badge($urgency, $is_high_risk = false) {
        if ($is_high_risk) {
            return "<span class='badge bg-danger-subtle text-danger'><i class='bi bi-exclamation-triangle-fill me-1'></i>{$urgency}</span>";
        }
        
        $classes = [
            'Critical' => 'bg-danger-subtle text-danger',
            'High' => 'bg-warning-subtle text-warning',
            'Medium' => 'bg-primary-subtle text-primary',
            'Low' => 'bg-success-subtle text-success'
        ];
        $class = $classes[$urgency] ?? 'bg-secondary-subtle text-secondary';
        return "<span class='badge {$class}'>{$urgency}</span>";
    }
}

if (!function_exists('render_incident_type_badge')) {
    function render_incident_type_badge($type) {
        $colors = [
            'Abuse' => 'danger',
            'Neglect' => 'warning',
            'Violence' => 'dark',
            'Theft' => 'info',
            'Assault' => 'danger',
            'Domestic' => 'warning',
            'Other' => 'secondary'
        ];
        $color = $colors[$type] ?? 'secondary';
        return "<span class='badge bg-{$color}-subtle text-{$color}'>{$type}</span>";
    }
}

?>

<?php require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php'; ?>

<div class="main-content">
    <div class="content-container">
        <!-- Header -->
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <div>
                <h1 class="h2"><i class="bi bi-file-earmark-alert"></i> Incident Logging & Classification</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="<?php echo $base_url; ?>index.php">Home</a></li>
                        <li class="breadcrumb-item active">Incident Reports</li>
                    </ol>
                </nav>
            </div>
            <div>
                <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#newIncidentModal">
                    <i class="bi bi-plus-circle-fill me-2"></i>Report Incident
                </button>
            </div>
        </div>

        <!-- Messages -->
        <?php display_message(); ?>

        <!-- Quick Stats -->
        <div class="row g-3 mb-4">
            <?php
            // Calculate statistics
            $total = count($incidents);
            $critical = count(array_filter($incidents, fn($i) => $i['urgency_level'] === 'Critical'));
            $high_risk = count(array_filter($incidents, fn($i) => $i['is_high_risk'] === 1));
            $pending = count(array_filter($incidents, fn($i) => in_array($i['status'], ['Submitted', 'Pending'], true)));
            ?>
            <div class="col-12 col-sm-6 col-xl-3">
                <a href="Incident_report.php" class="text-decoration-none" title="View all incident records">
                    <article class="dashboard-analytics-card analytics-tone-notif h-100 shadow-sm" style="cursor: pointer; transition: transform 0.2s, box-shadow 0.2s;">
                        <div class="dashboard-analytics-head">
                            <span class="dashboard-analytics-label">Total Reports</span>
                            <span class="dashboard-analytics-icon"><i class="bi bi-file-earmark-check"></i></span>
                        </div>
                        <div class="dashboard-analytics-value"><?php echo $total; ?></div>
                        <div class="dashboard-analytics-sub d-flex justify-content-between align-items-center">
                            <span>All incident records</span>
                            <i class="bi bi-arrow-right-circle opacity-75"></i>
                        </div>
                    </article>
                </a>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <a href="Incident_report.php?urgency=Critical" class="text-decoration-none" title="Filter Critical urgency cases">
                    <article class="dashboard-analytics-card analytics-tone-danger h-100 shadow-sm" style="cursor: pointer; transition: transform 0.2s, box-shadow 0.2s;">
                        <div class="dashboard-analytics-head">
                            <span class="dashboard-analytics-label">Critical Cases</span>
                            <span class="dashboard-analytics-icon"><i class="bi bi-exclamation-triangle-fill"></i></span>
                        </div>
                        <div class="dashboard-analytics-value"><?php echo $critical; ?></div>
                        <div class="dashboard-analytics-sub d-flex justify-content-between align-items-center">
                            <span>Immediate response</span>
                            <i class="bi bi-arrow-right-circle opacity-75"></i>
                        </div>
                    </article>
                </a>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <a href="Incident_report.php?urgency=High" class="text-decoration-none" title="Filter High-Risk cases">
                    <article class="dashboard-analytics-card analytics-tone-pending h-100 shadow-sm" style="cursor: pointer; transition: transform 0.2s, box-shadow 0.2s;">
                        <div class="dashboard-analytics-head">
                            <span class="dashboard-analytics-label">High-Risk Cases</span>
                            <span class="dashboard-analytics-icon"><i class="bi bi-shield-exclamation"></i></span>
                        </div>
                        <div class="dashboard-analytics-value"><?php echo $high_risk; ?></div>
                        <div class="dashboard-analytics-sub d-flex justify-content-between align-items-center">
                            <span>Requires monitoring</span>
                            <i class="bi bi-arrow-right-circle opacity-75"></i>
                        </div>
                    </article>
                </a>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <a href="Incident_report.php?status=Pending" class="text-decoration-none" title="Filter Pending verification reports">
                    <article class="dashboard-analytics-card analytics-tone-info h-100 shadow-sm" style="cursor: pointer; transition: transform 0.2s, box-shadow 0.2s;">
                        <div class="dashboard-analytics-head">
                            <span class="dashboard-analytics-label">Pending Review</span>
                            <span class="dashboard-analytics-icon"><i class="bi bi-clock-history"></i></span>
                        </div>
                        <div class="dashboard-analytics-value"><?php echo $pending; ?></div>
                        <div class="dashboard-analytics-sub d-flex justify-content-between align-items-center">
                            <span>Awaiting verification</span>
                            <i class="bi bi-arrow-right-circle opacity-75"></i>
                        </div>
                    </article>
                </a>
            </div>
        </div>

        <!-- Filters -->
        <div class="card enhanced-card shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Filter by Status</label>
                        <select name="status" class="form-select">
                            <option value="">All Status</option>
                            <option value="Draft" <?php echo $filter_status === 'Draft' ? 'selected' : ''; ?>>Draft</option>
                            <option value="Pending" <?php echo $filter_status === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="Under Review" <?php echo $filter_status === 'Under Review' ? 'selected' : ''; ?>>Under Review</option>
                            <option value="Verified" <?php echo $filter_status === 'Verified' ? 'selected' : ''; ?>>Verified</option>
                            <option value="Forwarded" <?php echo $filter_status === 'Forwarded' ? 'selected' : ''; ?>>Forwarded</option>
                            <option value="Resolved" <?php echo $filter_status === 'Resolved' ? 'selected' : ''; ?>>Resolved</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Filter by Urgency</label>
                        <select name="urgency" class="form-select">
                            <option value="">All Urgency Levels</option>
                            <option value="Critical" <?php echo $filter_urgency === 'Critical' ? 'selected' : ''; ?>>Critical</option>
                            <option value="High" <?php echo $filter_urgency === 'High' ? 'selected' : ''; ?>>High</option>
                            <option value="Medium" <?php echo $filter_urgency === 'Medium' ? 'selected' : ''; ?>>Medium</option>
                            <option value="Low" <?php echo $filter_urgency === 'Low' ? 'selected' : ''; ?>>Low</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-funnel me-2"></i>Apply Filters
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Incidents Table -->
        <div class="card enhanced-card shadow-sm border-0">
            <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center flex-wrap gap-2 py-3 px-4">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-list-check fs-5 text-success"></i>
                    <span class="fs-6">Incident Reports</span>
                    <span class="badge bg-success bg-opacity-10 text-success border border-success-subtle rounded-pill px-2.5 py-1" id="totalIncidentsBadge"><?php echo count($incidents); ?> Records</span>
                </div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <div class="input-group input-group-sm" style="width: 240px;">
                        <span class="input-group-text bg-light text-secondary"><i class="bi bi-search"></i></span>
                        <input type="text" id="incidentTableSearch" class="form-control" placeholder="Search incidents..." oninput="filterIncidentTable()">
                    </div>
                    <div class="d-flex align-items-center gap-1 text-muted small">
                        <span>Show:</span>
                        <select id="incidentPageSize" class="form-select form-select-sm" style="width: 75px;" onchange="changeIncidentPageSize(this.value)">
                            <option value="10" selected>10</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="incidentsTable">
                        <thead class="table-light">
                            <tr>
                                <th style="white-space: nowrap; width: 14%;">Case #</th>
                                <th style="width: 16%;">Reporter</th>
                                <th style="white-space: nowrap; width: 15%;">Classification</th>
                                <th style="white-space: nowrap; width: 13%;">Date / Time</th>
                                <th style="width: 22%;">Location</th>
                                <th style="white-space: nowrap; width: 10%;">Urgency</th>
                                <th style="white-space: nowrap; width: 10%;">Status</th>
                                <th class="text-center" style="white-space: nowrap; width: 6%;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="incidentsTableBody">
                            <?php if (empty($incidents)): ?>
                                <tr id="noIncidentsRow">
                                    <td colspan="8" class="text-center text-muted py-4">
                                        <i class="bi bi-inbox"></i> No incident reports found
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($incidents as $incident): ?>
                                    <tr class="incident-row" 
                                        data-case="<?php echo strtolower(htmlspecialchars($incident['case_no'] ?? '')); ?>"
                                        data-reporter="<?php echo strtolower(htmlspecialchars($incident['reporter_name'] ?? '')); ?>"
                                        data-location="<?php echo strtolower(htmlspecialchars($incident['location'] ?? '')); ?>"
                                        data-type="<?php echo strtolower(htmlspecialchars($incident['auto_classification'] ?? '')); ?>"
                                        data-urgency="<?php echo strtolower(htmlspecialchars($incident['urgency_level'] ?? '')); ?>"
                                        data-status="<?php echo strtolower(htmlspecialchars($incident['status'] ?? '')); ?>">
                                        <td class="text-nowrap"><strong><?php echo htmlspecialchars($incident['case_no'] ?? ''); ?></strong></td>
                                        <td>
                                            <div class="fw-semibold text-truncate" style="max-width: 140px;" title="<?php echo htmlspecialchars($incident['reporter_name'] ?? ''); ?>">
                                                <?php echo htmlspecialchars($incident['reporter_name'] ?? ''); ?>
                                            </div>
                                            <small class="text-muted"><?php echo ucfirst($incident['reporter_type'] ?? 'Citizen'); ?></small>
                                        </td>
                                        <td>
                                            <?php echo render_incident_type_badge($incident['auto_classification'] ?? ''); ?>
                                            <?php if (!empty($incident['manual_classification'])): ?>
                                                <br><small class="text-muted">Corrected: <?php echo htmlspecialchars($incident['manual_classification']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-nowrap">
                                            <small class="fw-semibold"><?php echo !empty($incident['incident_date']) ? date('M d, Y', strtotime($incident['incident_date'])) : 'N/A'; ?></small>
                                            <br><small class="text-muted"><?php echo $incident['incident_time'] ?? 'N/A'; ?></small>
                                        </td>
                                        <td>
                                            <div class="text-truncate" style="max-width: 160px;" title="<?php echo htmlspecialchars($incident['location'] ?? ''); ?>">
                                                <small><?php echo htmlspecialchars($incident['location'] ?: 'Not Specified'); ?></small>
                                            </div>
                                        </td>
                                        <td class="text-nowrap"><?php echo render_urgency_badge($incident['urgency_level'] ?? 'Medium', $incident['is_high_risk'] ?? 0); ?></td>
                                        <td class="text-nowrap"><?php echo render_status_badge($incident['status'] ?? 'Submitted'); ?></td>
                                        <td class="text-center text-nowrap">
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-primary" title="View Details" data-bs-toggle="modal" data-bs-target="#viewIncidentModal" onclick="loadIncidentDetails(<?php echo htmlspecialchars(json_encode($incident)); ?>)">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                                <?php 
                                                 $canEdit = $isAdminUser || (isset($_SESSION['user_id']) && ($incident['created_by'] ?? null) == $_SESSION['user_id']);
                                                if ($canEdit): 
                                                ?>
                                                    <button class="btn btn-outline-warning" title="Edit Report" data-bs-toggle="modal" data-bs-target="#<?php echo $isAdminUser ? 'editIncidentModal' : 'userEditIncidentModal'; ?>" onclick="<?php echo $isAdminUser ? 'loadIncidentForEdit' : 'loadIncidentForUserEdit'; ?>(<?php echo htmlspecialchars(json_encode($incident)); ?>)">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <?php if ($isAdminUser): ?>
                                                    <button class="btn btn-outline-info" title="Forward Incident" data-bs-toggle="modal" data-bs-target="#forwardIncidentModal" onclick="loadIncidentForForwarding(<?php echo htmlspecialchars(json_encode($incident)); ?>)">
                                                        <i class="bi bi-send"></i>
                                                    </button>
                                                    <button class="btn btn-outline-danger" title="Delete Case" onclick="deleteIncident(<?php echo $incident['id']; ?>, '<?php echo htmlspecialchars($incident['case_no'] ?? ''); ?>')">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-white border-top d-flex justify-content-between align-items-center flex-wrap gap-2 py-3 px-4">
                <div class="small text-muted" id="incidentPaginationInfo">
                    Showing 0 to 0 of 0 entries
                </div>
                <nav aria-label="Incident Table Pagination">
                    <ul class="pagination pagination-sm mb-0" id="incidentPaginationControls">
                        <!-- Populated by JS -->
                    </ul>
                </nav>
            </div>
        </div>
    </div>
</div>

<!-- ============ MODALS ============ -->

<!-- NEW INCIDENT MODAL -->
<div class="modal fade" id="newIncidentModal" tabindex="-1" aria-labelledby="newIncidentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="Incident_report.php" enctype="multipart/form-data">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="newIncidentModalLabel">
                        <i class="bi bi-file-earmark-plus"></i> Report New Incident
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body" style="max-height: 600px; overflow-y: auto;">
                    <!-- Reporter Information -->
                    <h6 class="mb-3"><i class="bi bi-person-fill"></i> Reporter Information</h6>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Full Name *</label>
                            <input type="text" name="reporter_name" class="form-control" value="<?php echo isset($_SESSION['fullname']) ? htmlspecialchars($_SESSION['fullname']) : ''; ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Reporter Type *</label>
                            <select name="reporter_type" class="form-select" required>
                                <option value="Citizen">Citizen</option>
                                <option value="Parent">Parent</option>
                                <option value="Officer">Officer</option>
                                <option value="Organization">Organization</option>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="reporter_email" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone Number</label>
                            <input type="tel" name="reporter_phone" class="form-control">
                        </div>
                    </div>

                    <!-- Incident Details -->
                    <hr>
                    <h6 class="mb-3"><i class="bi bi-calendar-event"></i> Incident Details</h6>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Incident Date *</label>
                            <input type="date" name="incident_date" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Incident Time</label>
                            <input type="time" name="incident_time" class="form-control">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Report Type *</label>
                            <select name="report_type" class="form-select" required>
                                <option value="Walk-in Complaint">Walk-in Complaint</option>
                                <option value="Online Complaint">Online Complaint</option>
                                <option value="Referral Report">Referral Report</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Incident Category *</label>
                            <select name="incident_type" class="form-select" required>
                                <option value="">-- Select Category --</option>
                                <option value="Abuse">Abuse</option>
                                <option value="Neglect">Neglect</option>
                                <option value="Violence">Violence</option>
                                <option value="Theft">Theft</option>
                                <option value="Assault">Assault</option>
                                <option value="Domestic">Domestic</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Primary Incident Category</label>
                            <input type="text" name="incident_category" class="form-control" placeholder="e.g., Crime, Public Disturbance, Emergency Incident">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Sub-Category</label>
                            <input type="text" name="incident_subtype" class="form-control" placeholder="e.g., Physical Abuse, Child Neglect">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark"><i class="fas fa-map-marked-alt text-danger me-1"></i>Incident Location (Quezon City) *</label>
                        <div class="row g-2 p-3 bg-light rounded-3 border">
                            <div class="col-md-3">
                                <label class="form-label small fw-semibold text-dark">District (QC) *</label>
                                <select id="inc_rep_district" class="form-select form-select-sm" required>
                                    <option value="">Select District</option>
                                    <option value="1">District 1</option>
                                    <option value="2">District 2</option>
                                    <option value="3">District 3</option>
                                    <option value="4">District 4</option>
                                    <option value="5">District 5</option>
                                    <option value="6">District 6</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-semibold text-dark">Barangay (QC) *</label>
                                <select id="inc_rep_barangay" class="form-select form-select-sm" required disabled>
                                    <option value="">Select District first</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-semibold text-dark">Bldg / Unit #</label>
                                <input type="text" id="inc_rep_house" class="form-control form-control-sm" placeholder="e.g. #12">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold text-dark">Street / Landmark *</label>
                                <input type="text" id="inc_rep_street" class="form-control form-control-sm" placeholder="e.g. Commonwealth Ave" required>
                            </div>
                            <input type="hidden" id="inc_rep_location" name="location" required>
                        </div>
                    </div>

                    <!-- Incident Narrative -->
                    <hr>
                    <h6 class="mb-3"><i class="bi bi-file-text"></i> Incident Description</h6>
                    <div class="mb-3">
                        <label class="form-label">Detailed Narrative *</label>
                        <textarea name="narrative" class="form-control" rows="4" required placeholder="Describe what happened in detail..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Evidence Description</label>
                        <textarea name="evidence_description" class="form-control" rows="2" placeholder="Any physical evidence, photographs, items involved..."></textarea>
                    </div>

                    <!-- Victim Information -->
                    <hr>
                    <h6 class="mb-3"><i class="bi bi-person-x"></i> Victim Information</h6>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Victim Name</label>
                            <input type="text" name="victim_name" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Age</label>
                            <input type="number" name="victim_age" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Gender</label>
                            <select name="victim_gender" class="form-select">
                                <option value="">Select</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>

                    <!-- Suspect Information -->
                    <hr>
                    <h6 class="mb-3"><i class="bi bi-shield-exclamation"></i> Suspect Information</h6>
                    <div class="mb-3">
                        <label class="form-label">Suspect Name</label>
                        <input type="text" name="suspect_name" class="form-control" placeholder="If known">
                    </div>

                    <!-- Attachments Section with Multi-Level Categorization -->
                    <hr>
                    <h6 class="mb-3"><i class="bi bi-paperclip"></i> Classified Attachments (Optional)</h6>
                    <div id="incidentAttachmentsContainer">
                        <div class="attachment-item border rounded p-3 mb-3 bg-light">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Attachment Level / Type</label>
                                    <select name="attachment_levels[]" class="form-select">
                                        <option value="Valid ID / Government ID">🪪 Valid ID / Government ID</option>
                                        <option value="Physical / Digital Evidence" selected>🛡️ Physical / Digital Evidence</option>
                                        <option value="CCTV Footage & Screenshot">📹 CCTV Footage & Screenshot</option>
                                        <option value="Witness Affidavit & Statement">📜 Witness Affidavit / Statement</option>
                                        <option value="Medical / Medico-Legal Report">🏥 Medical / Medico-Legal</option>
                                        <option value="Barangay / Police Certification">🏛️ Barangay / Police Certification</option>
                                        <option value="Documentary / Other Proof">📁 Documentary / Other Proof</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">File Upload</label>
                                    <input type="file" name="attachments[]" class="form-control" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">Description / Label</label>
                                    <input type="text" name="attachment_descriptions[]" class="form-control" placeholder="e.g. Driver License, Photo Proof">
                                </div>
                                <div class="col-md-1 d-flex align-items-end">
                                    <button type="button" class="btn btn-outline-danger w-100" onclick="removeIncidentAttachment(this)" title="Remove item">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="addIncidentAttachment()">
                        <i class="bi bi-plus-circle"></i> Add Another File
                    </button>
                    <small class="text-muted d-block mt-2">
                        Supported formats: Images (JPG, PNG, GIF), PDF, Word documents, Excel files, Text files. Max 10MB per file.
                    </small>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="submit_incident" class="btn btn-danger">
                        <i class="bi bi-send-fill me-2"></i>Submit Incident Report
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- VIEW INCIDENT MODAL -->
<div class="modal fade" id="viewIncidentModal" tabindex="-1" aria-labelledby="viewIncidentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="viewIncidentModalLabel">
                    <i class="bi bi-file-earmark-text"></i> Incident Report Details
                </h5>
                <div class="ms-auto">
                    <button type="button" class="btn btn-sm btn-light" title="Print" onclick="printIncidentReport()">
                        <i class="bi bi-printer"></i> Print
                    </button>
                    <button type="button" class="btn-close btn-close-white ms-2" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>
            <div class="modal-body" style="max-height: 600px; overflow-y: auto;" id="incidentViewContent">
                <!-- Letter Header with Logo (for print) -->
                <div class="letter-header" id="incidentLetterHeader">
                    <img src="../assets/css/favicon.png" alt="Alertara Favicon" class="favicon-top-right">
                    <div class="letter-header-logo">
                        <img src="../assets/css/tara.png" alt="Alertara Logo" style="height: 60px;">
                    </div>
                    <h3>ALERTARA PH</h3>
                    <p>Law Enforcement and Incident Report System</p>
                    <p>Official Incident Report Record</p>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <h6>Case Number</h6>
                        <p id="view_case_no" class="fw-bold text-danger"></p>
                    </div>
                    <div class="col-md-6">
                        <h6>Status</h6>
                        <p id="view_status"></p>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <h6>Auto-Classification</h6>
                        <p id="view_auto_class"></p>
                    </div>
                    <div class="col-md-6">
                        <h6>Urgency Level</h6>
                        <p id="view_urgency"></p>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <h6>Reporter Name</h6>
                        <p id="view_reporter"></p>
                    </div>
                    <div class="col-md-6">
                        <h6>Reporter Type</h6>
                        <p id="view_reporter_type"></p>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <h6>Incident Date</h6>
                        <p id="view_incident_date"></p>
                    </div>
                    <div class="col-md-6">
                        <h6>Incident Time</h6>
                        <p id="view_incident_time"></p>
                    </div>
                </div>

                <div class="mb-3">
                    <h6>Location</h6>
                    <p id="view_location"></p>
                </div>

                <div class="mb-3">
                    <h6>Narrative</h6>
                    <p id="view_narrative" style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; border-radius: 5px;"></p>
                </div>

                <div class="mb-3">
                    <h6>Victim Information</h6>
                    <p id="view_victim"></p>
                </div>

                <div class="mb-3">
                    <h6>Suspect Name</h6>
                    <p id="view_suspect"></p>
                </div>

                <!-- Signature Section (for print) -->
                <div class="signature-section" id="incidentSignatureSection">
                    <h6 style="margin-bottom: 20px; text-align: center;">AUTHORIZED SIGNATURES</h6>
                    <div class="row">
                        <div class="col-md-4 signature-block">
                            <div style="text-align: center;">
                                <div class="signature-line"></div>
                                <div class="signature-name" id="sig_reporter_name">Report By</div>
                                <div class="signature-title">Reporting Officer/User</div>
                                <small class="text-muted">Date: <span id="sig_date"></span></small>
                            </div>
                        </div>
                        <div class="col-md-4 signature-block">
                            <div style="text-align: center;">
                                <div class="signature-line"></div>
                                <div class="signature-name">____________________</div>
                                <div class="signature-title">Barangay Captain/Admin</div>
                                <small class="text-muted">Date: _______________</small>
                            </div>
                        </div>
                        <div class="col-md-4 signature-block">
                            <div style="text-align: center;">
                                <div class="signature-line"></div>
                                <div class="signature-name">____________________</div>
                                <div class="signature-title">Assistant/Authorized By</div>
                                <small class="text-muted">Date: _______________</small>
                            </div>
                        </div>
                    </div>
                    <div class="signature-date">
                        <strong id="record_date">Record Date: N/A</strong>
                    </div>
                </div>

                <div class="mb-3">
                    <h6>Attachments</h6>
                    <div id="view_attachments">
                        <small class="text-muted">Attachments can be viewed and managed in the detailed incident report page.</small>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- EDIT INCIDENT MODAL (ADMIN ONLY) -->
<div class="modal fade" id="editIncidentModal" tabindex="-1" aria-labelledby="editIncidentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="Incident_report.php?action=edit_admin">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title" id="editIncidentModalLabel">
                        <i class="bi bi-pencil-square"></i> Edit & Correct Classification (Admin)
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="edit_incident_id" name="incident_id">

                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <strong>Admin Override:</strong> You can correct the automatic classification and update the status.
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Case Number (View Only)</label>
                            <input type="text" id="edit_case_no" class="form-control" disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Current Status</label>
                            <select name="status" class="form-select" id="edit_status">
                                <option value="Draft">Draft</option>
                                <option value="Pending">Pending</option>
                                <option value="Submitted">Submitted</option>
                                <option value="Under Review">Under Review</option>
                                <option value="Verified">Verified</option>
                                <option value="Forwarded">Forwarded</option>
                                <option value="Resolved">Resolved</option>
                                <option value="Closed">Closed</option>
                                <option value="Archived">Archived</option>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Auto-Classification (Original)</label>
                            <input type="text" id="edit_auto_class" class="form-control" disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Correct Classification (If Different)</label>
                            <select name="manual_classification" class="form-select" id="edit_manual_class">
                                <option value="">-- Keep Auto Classification --</option>
                                <option value="Abuse">Abuse</option>
                                <option value="Neglect">Neglect</option>
                                <option value="Violence">Violence</option>
                                <option value="Theft">Theft</option>
                                <option value="Assault">Assault</option>
                                <option value="Domestic">Domestic</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Urgency Level</label>
                            <select name="urgency_level" class="form-select" id="edit_urgency">
                                <option value="Low">Low</option>
                                <option value="Medium">Medium</option>
                                <option value="High">High</option>
                                <option value="Critical">Critical</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">High-Risk Case</label>
                            <div class="form-check mt-2">
                                <input type="checkbox" id="edit_high_risk" name="is_high_risk" class="form-check-input" value="1">
                                <label class="form-check-label" for="edit_high_risk">Mark as High-Risk</label>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Admin Notes</label>
                        <textarea name="admin_notes" class="form-control" rows="3" id="edit_admin_notes" placeholder="Your notes on this case..."></textarea>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Assign To Officer</label>
                            <select name="assigned_to" class="form-select" id="edit_assigned_to">
                                <option value="">-- Unassigned --</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Forward To Group</label>
                            <select name="routing_group" class="form-select" id="edit_routing_group">
                                <option value="">-- No Forwarding --</option>
                                <option value="GRP4">GRP4 - Emergency Response</option>
                                <option value="GRP5">GRP5 - Community Complaint</option>
                                <option value="GRP6">GRP6 - Crime Analytics</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Routing Notes</label>
                        <textarea name="routing_notes" class="form-control" rows="2" id="edit_routing_notes" placeholder="Explain why this incident is being forwarded or tracked."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_incident" class="btn btn-warning">
                        <i class="bi bi-save me-2"></i>Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- FORWARD INCIDENT MODAL -->
<div class="modal fade" id="forwardIncidentModal" tabindex="-1" aria-labelledby="forwardIncidentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="Incident_report.php">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="forwardIncidentModalLabel"><i class="bi bi-send"></i> Forward Incident</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="forward_incident_id" name="incident_id">
                    <div class="mb-3">
                        <label class="form-label">Case Number</label>
                        <input type="text" id="forward_case_no" class="form-control" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Forward To</label>
                        <select name="forward_to_group" class="form-select" required>
                            <option value="">-- Select Group --</option>
                            <option value="GRP4">GRP4 - Emergency Response</option>
                            <option value="GRP5">GRP5 - Community Complaint</option>
                            <option value="GRP6">GRP6 - Crime Analytics</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="forward_notes" class="form-control" rows="3" placeholder="Reason or handoff details"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="forward_incident" class="btn btn-info">Forward</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- USER EDIT INCIDENT MODAL -->
<div class="modal fade" id="userEditIncidentModal" tabindex="-1" aria-labelledby="userEditIncidentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="Incident_report.php?action=edit_user" enctype="multipart/form-data">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="userEditIncidentModalLabel">
                        <i class="bi bi-pencil-square"></i> Edit Your Incident Report
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="user_edit_incident_id" name="incident_id">

                    <div class="alert alert-info">
                        <i class="bi bi-info-circle-fill me-2"></i>
                        <strong>Note:</strong> You can edit your incident details. Classification and status changes require admin approval.
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Case Number</label>
                            <input type="text" id="user_edit_case_no" class="form-control" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <input type="text" id="user_edit_status" class="form-control" readonly>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Reporter Name *</label>
                            <input type="text" id="user_edit_reporter_name" name="reporter_name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Reporter Type</label>
                            <select id="user_edit_reporter_type" name="reporter_type" class="form-select">
                                <option value="victim">Victim</option>
                                <option value="witness">Witness</option>
                                <option value="complainant">Complainant</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Incident Date *</label>
                            <input type="date" id="user_edit_incident_date" name="incident_date" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Incident Time</label>
                            <input type="time" id="user_edit_incident_time" name="incident_time" class="form-control">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Location *</label>
                        <input type="text" id="user_edit_location" name="location" class="form-control" placeholder="Address or area where incident occurred" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Incident Description *</label>
                        <textarea id="user_edit_description" name="description" class="form-control" rows="4" placeholder="Detailed description of the incident" required></textarea>
                    </div>

                    <!-- Attachments Section -->
                    <div class="mb-3">
                        <label class="form-label">Add New Attachments (Optional)</label>
                        <div id="userEditAttachmentsContainer">
                            <div class="attachment-item border rounded p-3 mb-3">
                                <div class="row g-2 align-items-end">
                                    <div class="col-md-5">
                                        <input type="file" name="attachments[]" class="form-control" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt">
                                    </div>
                                    <div class="col-md-5">
                                        <input type="text" name="attachment_descriptions[]" class="form-control" placeholder="Brief description">
                                    </div>
                                    <div class="col-md-2">
                                        <button type="button" class="btn btn-outline-danger" onclick="removeUserEditAttachment(this)" style="display: none;">
                                            <i class="bi bi-x"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="addUserEditAttachment()">
                            <i class="bi bi-plus"></i> Add Another File
                        </button>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_user_incident" class="btn btn-primary">
                        <i class="bi bi-save me-2"></i>Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function setElText(id, text) {
    const el = document.getElementById(id);
    if (el) el.textContent = text || '—';
}

function setElHtml(id, html) {
    const el = document.getElementById(id);
    if (el) el.innerHTML = html;
}

function setElVal(id, val) {
    const el = document.getElementById(id);
    if (el) el.value = val || '';
}

function loadIncidentDetails(incident) {
    if (!incident) return;
    
    setElText('view_case_no', incident.case_no || 'N/A');
    
    // Status badge
    let statusClass = 'bg-secondary';
    const st = (incident.status || 'Pending').toLowerCase();
    if (st.includes('resolved') || st.includes('verified')) statusClass = 'bg-success';
    else if (st.includes('review') || st.includes('investigation')) statusClass = 'bg-info text-dark';
    else if (st.includes('pending') || st.includes('submitted')) statusClass = 'bg-warning text-dark';
    setElHtml('view_status', '<span class="badge ' + statusClass + '">' + (incident.status || 'Pending') + '</span>');
    
    setElText('view_auto_class', incident.auto_classification || incident.incident_type || 'General Incident');
    
    let urgClass = 'bg-secondary';
    const urg = (incident.urgency_level || 'Medium').toLowerCase();
    if (urg === 'critical') urgClass = 'bg-danger';
    else if (urg === 'high') urgClass = 'bg-warning text-dark';
    else if (urg === 'medium') urgClass = 'bg-info text-dark';
    setElHtml('view_urgency', '<span class="badge ' + urgClass + '">' + (incident.urgency_level || 'Medium') + '</span>');
    
    setElText('view_reporter', incident.reporter_name || 'Anonymous / Citizen');
    setElText('view_reporter_type', incident.reporter_type || 'Citizen');
    setElText('view_incident_date', incident.incident_date || 'N/A');
    setElText('view_incident_time', incident.incident_time || 'N/A');
    setElText('view_location', incident.location || 'Not Specified');
    setElText('view_narrative', incident.narrative || 'No description provided.');
    
    // Signature fields
    setElText('sig_reporter_name', incident.reporter_name || 'Reporting Officer/User');
    const createdDate = incident.created_at ? new Date(incident.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) : new Date().toLocaleDateString();
    setElText('sig_date', createdDate);
    
    // NLP Analysis
    const threat = incident.nlp_threat_level || 'Low';
    let threatBadge = 'bg-secondary';
    if (threat === 'Critical') threatBadge = 'bg-danger';
    else if (threat === 'High') threatBadge = 'bg-warning text-dark';
    else if (threat === 'Medium') threatBadge = 'bg-info text-dark';
    else if (threat === 'Low') threatBadge = 'bg-success';
    setElHtml('view_nlp_threat', '<span class="badge ' + threatBadge + '">' + threat + '</span>');
    
    setElText('view_nlp_severity', (Number(incident.nlp_severity_score) || 0).toFixed(1) + '/100');
    setElText('view_nlp_confidence', (Number(incident.nlp_confidence_score) || 0).toFixed(1) + '%');
    setElText('view_nlp_sentiment', incident.nlp_sentiment || 'Neutral');
    
    // Parse emotions
    let emotions = [];
    if (incident.nlp_emotions) {
        try {
            emotions = typeof incident.nlp_emotions === 'string' ? JSON.parse(incident.nlp_emotions) : incident.nlp_emotions;
        } catch (e) {
            emotions = [incident.nlp_emotions];
        }
    }
    if (Array.isArray(emotions) && emotions.length > 0) {
        setElHtml('view_nlp_emotions', emotions.map(function(e) { return '<span class="badge bg-secondary me-1">' + e + '</span>'; }).join(''));
    } else {
        setElHtml('view_nlp_emotions', '<small class="text-muted">None detected</small>');
    }
    
    let victimInfo = incident.victim_name || 'Not provided';
    if (incident.victim_age) victimInfo += ', Age: ' + incident.victim_age;
    if (incident.victim_gender) victimInfo += ', ' + incident.victim_gender;
    setElText('view_victim', victimInfo);
    
    setElText('view_suspect', incident.suspect_name || 'Not provided');
}

// Print incident report
function printIncidentReport() {
    const printContent = document.getElementById('incidentViewContent').innerHTML;
    const printWindow = window.open('', '', 'width=900,height=700');
    
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Incident Report</title>
            <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
            <style>
                body { margin: 20px; font-family: Arial, sans-serif; }
                .letter-header { text-align: center; padding: 20px 0; border-bottom: 3px solid #1a5490; margin-bottom: 20px; position: relative; }
                .letter-header-logo { height: 60px; margin-bottom: 10px; }
                .favicon-top-right { position: absolute; top: 10px; right: 10px; width: 80px; height: 80px; }
                .letter-header h3 { margin: 5px 0; color: #1a5490; font-weight: bold; }
                .letter-header p { margin: 2px 0; color: #666; font-size: 13px; }
                .signature-section { margin-top: 40px; padding-top: 20px; border-top: 1px solid #ddd; }
                .signature-block { display: inline-block; width: 30%; text-align: center; margin-right: 5%; vertical-align: top; }
                .signature-line { border-top: 1px solid #000; margin-top: 50px; margin-bottom: 5px; height: 1px; }
                .signature-name { font-weight: bold; font-size: 13px; margin-top: 5px; }
                .signature-title { font-size: 12px; color: #666; margin-top: 2px; }
                .signature-date { margin-top: 20px; font-size: 13px; text-align: center; }
                @media print { body { margin: 0; padding: 20px; } .modal-footer, .btn, button { display: none !important; } }
            </style>
        </head>
        <body>
            ${printContent}
        </body>
        </html>
    `);
    
    printWindow.document.close();
    printWindow.print();
}

function loadIncidentForEdit(incident) {
    if (!incident) return;
    setElVal('edit_incident_id', incident.id);
    setElVal('edit_case_no', incident.case_no);
    setElVal('edit_status', incident.status);
    setElVal('edit_auto_class', incident.auto_classification);
    setElVal('edit_manual_class', incident.manual_classification || '');
    setElVal('edit_urgency', incident.urgency_level);
    const hr = document.getElementById('edit_high_risk');
    if (hr) hr.checked = incident.is_high_risk === 1 || incident.is_high_risk === '1';
    setElVal('edit_admin_notes', incident.admin_notes || '');
    setElVal('edit_assigned_to', incident.assigned_to || '');
    setElVal('edit_routing_group', incident.routing_group || '');
    setElVal('edit_routing_notes', incident.forwarding_notes || '');
}

function loadIncidentForUserEdit(incident) {
    if (!incident) return;
    setElVal('user_edit_incident_id', incident.id);
    setElVal('user_edit_case_no', incident.case_no);
    setElVal('user_edit_incident_type', incident.incident_type || incident.auto_classification || 'Other');
    setElVal('user_edit_incident_date', incident.incident_date);
    setElVal('user_edit_incident_time', incident.incident_time);
    setElVal('user_edit_location', incident.location);
    setElVal('user_edit_narrative', incident.narrative);
    setElVal('user_edit_victim_name', incident.victim_name || '');
    setElVal('user_edit_victim_age', incident.victim_age || '');
    setElVal('user_edit_victim_gender', incident.victim_gender || '');
    setElVal('user_edit_suspect_name', incident.suspect_name || '');
}

function loadIncidentForForwarding(incident) {
    if (!incident) return;
    setElVal('forward_incident_id', incident.id);
    setElVal('forward_case_no', incident.case_no);
}

// Attachment management functions
function addIncidentAttachment() {
    const container = document.getElementById('incidentAttachmentsContainer');
    const firstItem = container.querySelector('.attachment-item');
    const newItem = firstItem.cloneNode(true);
    
    // Clear the inputs
    const inputs = newItem.querySelectorAll('input');
    inputs.forEach(input => {
        input.value = '';
    });
    
    container.appendChild(newItem);
}

function removeIncidentAttachment(button) {
    const container = document.getElementById('incidentAttachmentsContainer');
    const items = container.querySelectorAll('.attachment-item');
    
    if (items.length > 1) {
        button.closest('.attachment-item').remove();
    } else {
        // Clear the inputs instead of removing the last one
        const inputs = button.closest('.attachment-item').querySelectorAll('input');
        inputs.forEach(input => {
            input.value = '';
        });
    }
}

// Delete incident function
function deleteIncident(incidentId, caseNo) {
    if (confirm(`Are you sure you want to delete Case #${caseNo}?\n\nThis action cannot be undone and will permanently remove the incident report.`)) {
        // Create a form to submit the delete request
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'Incident_report.php';
        
        // Add hidden inputs
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'delete_incident';
        form.appendChild(actionInput);
        
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'incident_id';
        idInput.value = incidentId;
        form.appendChild(idInput);
        
        // Submit the form
        document.body.appendChild(form);
        form.submit();
    }
}

// User edit incident functions
function loadIncidentForUserEdit(incident) {
    document.getElementById('user_edit_incident_id').value = incident.id;
    document.getElementById('user_edit_case_no').value = incident.case_no;
    document.getElementById('user_edit_status').value = incident.status;
    document.getElementById('user_edit_reporter_name').value = incident.reporter_name;
    document.getElementById('user_edit_reporter_type').value = incident.reporter_type;
    document.getElementById('user_edit_incident_date').value = incident.incident_date;
    document.getElementById('user_edit_incident_time').value = incident.incident_time || '';
    document.getElementById('user_edit_location').value = incident.location;
    document.getElementById('user_edit_description').value = incident.description;
}

function addUserEditAttachment() {
    const container = document.getElementById('userEditAttachmentsContainer');
    const firstItem = container.querySelector('.attachment-item');
    const newItem = firstItem.cloneNode(true);
    
    // Clear input values
    const inputs = newItem.querySelectorAll('input');
    inputs.forEach(input => {
        input.value = '';
    });
    
    // Show remove button
    const removeBtn = newItem.querySelector('.btn-outline-danger');
    removeBtn.style.display = 'block';
    
    container.appendChild(newItem);
}

function removeUserEditAttachment(button) {
    const container = document.getElementById('userEditAttachmentsContainer');
    const items = container.querySelectorAll('.attachment-item');
    
    if (items.length > 1) {
        button.closest('.attachment-item').remove();
    } else {
        // Clear inputs instead of removing the last one
        const inputs = button.closest('.attachment-item').querySelectorAll('input');
        inputs.forEach(input => {
            input.value = '';
        });
    }
}

// Incident Table Pagination & Live Filter
let currentIncidentPage = 1;
let incidentRowsPerPage = 10;
let filteredIncidentRows = [];

function initIncidentTable() {
    const allRows = document.querySelectorAll('#incidentsTableBody tr.incident-row');
    filteredIncidentRows = Array.from(allRows);
    renderIncidentPagination();
}

function filterIncidentTable() {
    const query = (document.getElementById('incidentTableSearch')?.value || '').toLowerCase().trim();
    const allRows = document.querySelectorAll('#incidentsTableBody tr.incident-row');
    
    filteredIncidentRows = [];
    allRows.forEach(row => {
        const text = (
            (row.getAttribute('data-case') || '') + ' ' +
            (row.getAttribute('data-reporter') || '') + ' ' +
            (row.getAttribute('data-location') || '') + ' ' +
            (row.getAttribute('data-type') || '') + ' ' +
            (row.getAttribute('data-urgency') || '') + ' ' +
            (row.getAttribute('data-status') || '')
        ).toLowerCase();
        
        if (!query || text.includes(query)) {
            filteredIncidentRows.push(row);
        }
    });
    
    currentIncidentPage = 1;
    renderIncidentPagination();
}

function changeIncidentPageSize(size) {
    incidentRowsPerPage = parseInt(size) || 10;
    currentIncidentPage = 1;
    renderIncidentPagination();
}

function goToIncidentPage(page) {
    currentIncidentPage = page;
    renderIncidentPagination();
}

function renderIncidentPagination() {
    const total = filteredIncidentRows.length;
    const totalPages = Math.ceil(total / incidentRowsPerPage) || 1;
    if (currentIncidentPage > totalPages) currentIncidentPage = totalPages;
    if (currentIncidentPage < 1) currentIncidentPage = 1;
    
    const startIdx = (currentIncidentPage - 1) * incidentRowsPerPage;
    const endIdx = Math.min(startIdx + incidentRowsPerPage, total);
    
    // Hide all rows first
    const allRows = document.querySelectorAll('#incidentsTableBody tr.incident-row');
    allRows.forEach(r => r.style.display = 'none');
    
    const noRow = document.getElementById('noIncidentsRow');
    if (total === 0) {
        if (!noRow) {
            const tr = document.createElement('tr');
            tr.id = 'noIncidentsRow';
            tr.innerHTML = '<td colspan="9" class="text-center text-muted py-4"><i class="bi bi-search me-1"></i> No matching incident reports found</td>';
            document.getElementById('incidentsTableBody').appendChild(tr);
        } else {
            noRow.style.display = '';
        }
    } else {
        if (noRow) noRow.style.display = 'none';
        for (let i = startIdx; i < endIdx; i++) {
            if (filteredIncidentRows[i]) filteredIncidentRows[i].style.display = '';
        }
    }
    
    // Update pagination info label
    const infoEl = document.getElementById('incidentPaginationInfo');
    if (infoEl) {
        if (total === 0) {
            infoEl.textContent = 'Showing 0 to 0 of 0 entries';
        } else {
            infoEl.textContent = `Showing ${startIdx + 1} to ${endIdx} of ${total} entries`;
        }
    }
    
    // Render pagination buttons
    const controls = document.getElementById('incidentPaginationControls');
    if (!controls) return;
    
    let html = '';
    // Previous button
    html += `<li class="page-item ${currentIncidentPage === 1 ? 'disabled' : ''}">
        <a class="page-link" href="javascript:void(0)" onclick="goToIncidentPage(${currentIncidentPage - 1})"><i class="bi bi-chevron-left"></i></a>
    </li>`;
    
    for (let p = 1; p <= totalPages; p++) {
        if (totalPages > 7 && Math.abs(p - currentIncidentPage) > 2 && p !== 1 && p !== totalPages) {
            if (p === 2 || p === totalPages - 1) {
                html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
            }
            continue;
        }
        html += `<li class="page-item ${p === currentIncidentPage ? 'active' : ''}">
            <a class="page-link" href="javascript:void(0)" onclick="goToIncidentPage(${p})">${p}</a>
        </li>`;
    }
    
    // Next button
    html += `<li class="page-item ${currentIncidentPage >= totalPages ? 'disabled' : ''}">
        <a class="page-link" href="javascript:void(0)" onclick="goToIncidentPage(${currentIncidentPage + 1})"><i class="bi bi-chevron-right"></i></a>
    </li>`;
    
    controls.innerHTML = html;
}
</script>

<script src="../assets/js/address-selector.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    initIncidentTable();
    initQCAddressSelector({
        districtSelectId: 'inc_rep_district',
        barangaySelectId: 'inc_rep_barangay',
        streetInputId: 'inc_rep_street',
        houseNumberInputId: 'inc_rep_house',
        targetCombinedInputId: 'inc_rep_location'
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

