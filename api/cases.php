<?php
header('Content-Type: application/json; charset=utf-8');

// Ensure only authenticated admin users can call these APIs
require_once __DIR__ . '/../admin/admin_auth.php';

require_once __DIR__ . '/../modules/CaseAssign.php';
require_once __DIR__ . '/../includes/attachment_manager.php';

$input = [];
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($contentType, 'application/json') !== false) {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
} else {
    $input = $_POST;
}

$action = $input['action'] ?? null;

try {
    if (isset($input['create_case']) || $action === 'create_case') {
        $case_data = [
            'incident_type' => $input['incident_type'] ?? '',
            'complainant_name' => $input['complainant_name'] ?? '',
            'respondent_name' => $input['respondent_name'] ?? '',
            'location' => $input['location'] ?? '',
            'incident_date' => $input['incident_date'] ?? null,
            'incident_time' => $input['incident_time'] ?? null,
            'description' => $input['description'] ?? '',
            'priority' => $input['priority'] ?? 'Medium',
            'assigned_by' => $_SESSION['user_id'] ?? null,
            'assigned_to' => $input['assigned_to'] ?? null,
            'barangay_chairperson_id' => $input['barangay_chairperson_id'] ?? null
        ];

        $result = createCaseAssignment($case_data);
        if ($result['success']) {
            if (stripos($contentType, 'application/json') === false) {
                // regular form submit - redirect back to admin page with flash
                $_SESSION['flash'] = ['type' => 'success', 'message' => 'Case created: ' . ($result['case_number'] ?? '')];
                header('Location: ../admin/cases.php');
                exit;
            }

            http_response_code(201);
            echo json_encode(['success' => true, 'case_id' => $result['case_id'], 'case_number' => $result['case_number']]);
            exit;
        }

        if (stripos($contentType, 'application/json') === false) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Error creating case: ' . ($result['error'] ?? 'Unknown')];
            header('Location: ../admin/cases.php');
            exit;
        }

        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $result['error'] ?? 'Unknown error']);
        exit;
    }

    if (isset($input['update_status']) || $action === 'update_status') {
        $case_id = intval($input['case_id'] ?? 0);
        $new_status = $input['new_status'] ?? '';
        $notes = $input['status_notes'] ?? '';

        $result = updateCaseStatus($case_id, $new_status, $_SESSION['user_id'], $notes);
        if ($result['success']) {
            if (stripos($contentType, 'application/json') === false) {
                $_SESSION['flash'] = ['type' => 'success', 'message' => 'Case status updated.'];
                header('Location: ../admin/cases.php');
                exit;
            }
            echo json_encode(['success' => true]);
            exit;
        }

        if (stripos($contentType, 'application/json') === false) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Error updating case status: ' . ($result['error'] ?? '')];
            header('Location: ../admin/cases.php');
            exit;
        }

        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $result['error'] ?? 'Failed to update status']);
        exit;
    }

    if (isset($input['add_followup']) || $action === 'add_followup') {
        $case_id = intval($input['case_id'] ?? 0);
        $action_desc = $input['followup_action'] ?? '';

        $result = addFollowUpAction($case_id, $action_desc, $_SESSION['user_id']);
        if ($result['success']) {
            if (stripos($contentType, 'application/json') === false) {
                $_SESSION['flash'] = ['type' => 'success', 'message' => 'Follow-up added.'];
                header('Location: ../admin/cases.php');
                exit;
            }
            echo json_encode(['success' => true]);
            exit;
        }

        if (stripos($contentType, 'application/json') === false) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Error adding follow-up: ' . ($result['error'] ?? '')];
            header('Location: ../admin/cases.php');
            exit;
        }

        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $result['error'] ?? 'Failed to add follow-up']);
        exit;
    }

    if (isset($input['reassign_case']) || $action === 'reassign_case') {
        $case_id = intval($input['case_id'] ?? 0);
        $new_officer = $input['new_officer'] ?? null;
        $reason = $input['reassign_reason'] ?? '';

        $result = reassignCase($case_id, $new_officer, $_SESSION['user_id'], $reason);
        if ($result['success']) {
            if (stripos($contentType, 'application/json') === false) {
                $_SESSION['flash'] = ['type' => 'success', 'message' => 'Case reassigned.'];
                header('Location: ../admin/cases.php');
                exit;
            }
            echo json_encode(['success' => true]);
            exit;
        }

        if (stripos($contentType, 'application/json') === false) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Error reassigning case: ' . ($result['error'] ?? '')];
            header('Location: ../admin/cases.php');
            exit;
        }

        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $result['error'] ?? 'Failed to reassign case']);
        exit;
    }

    if ($action === 'delete_case' || isset($input['action']) && $input['action'] === 'delete_case') {
        $case_id = intval($input['case_id'] ?? 0);
        if (!$case_id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid case id']);
            exit;
        }

        // Delete attachments first
        $attachment_manager = new AttachmentManager($pdo);
        $attachments = $attachment_manager->getAttachments('case', $case_id);
        foreach ($attachments as $att) {
            try {
                $attachment_manager->deleteAttachment($att['id'], $_SESSION['user_id']);
            } catch (Exception $e) {
                error_log('Attachment deletion error: ' . $e->getMessage());
            }
        }

        // Delete case record
        $stmt = $pdo->prepare("DELETE FROM case_assignments WHERE id = ?");
        $stmt->execute([$case_id]);
        if ($stmt->rowCount() > 0) {
            if (stripos($contentType, 'application/json') === false) {
                $_SESSION['flash'] = ['type' => 'success', 'message' => 'Case deleted.'];
                header('Location: ../admin/cases.php');
                exit;
            }
            echo json_encode(['success' => true]);
            exit;
        }

        if (stripos($contentType, 'application/json') === false) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Case not found or already deleted'];
            header('Location: ../admin/cases.php');
            exit;
        }

        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Case not found or already deleted']);
        exit;
    }

    // If no recognized action
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No valid action specified']);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}

?>
