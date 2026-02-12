<?php
/**
 * Secure File Download/View Handler
 * Serves attachment files with proper permission checks
 */

session_start();
require '../config/db_connect.php';
require '../includes/attachment_manager.php';

// Get parameters
$entity_type = $_GET['type'] ?? '';
$filename = $_GET['file'] ?? '';
$action = $_GET['action'] ?? 'download'; // 'download' or 'view'

if (!$entity_type || !$filename) {
    http_response_code(400);
    die('Invalid request');
}

// Validate entity type
$valid_types = ['blotter', 'incident', 'case'];
if (!in_array($entity_type, $valid_types)) {
    http_response_code(400);
    die('Invalid entity type');
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    die('Authentication required');
}

try {
    // Get attachment info from database
    $stmt = $pdo->prepare("
        SELECT a.*, s.fullname as uploaded_by_name
        FROM attachments a
        LEFT JOIN signup s ON a.uploaded_by = s.user_id
        WHERE a.entity_type = ? AND a.stored_filename = ? AND a.is_deleted = FALSE
    ");
    $stmt->execute([$entity_type, $filename]);
    $attachment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$attachment) {
        http_response_code(404);
        die('File not found');
    }

    // Check permissions based on entity type
    $has_permission = false;
    $user_id = $_SESSION['user_id'];
    $user_role = $_SESSION['role'] ?? '';

    if ($user_role === 'Admin') {
        $has_permission = true;
    } else {
        // Check if user has access to this specific entity
        switch ($entity_type) {
            case 'blotter':
                // Check if user created the blotter or is assigned to it
                $check_stmt = $pdo->prepare("
                    SELECT id FROM blotters
                    WHERE id = ? AND (created_by = ? OR officer_id = ?)
                ");
                $check_stmt->execute([$attachment['entity_id'], $user_id, $user_id]);
                $has_permission = $check_stmt->rowCount() > 0;
                break;

            case 'incident':
                // Check if user created the incident or is assigned to it
                $check_stmt = $pdo->prepare("
                    SELECT id FROM incidents
                    WHERE id = ? AND (created_by = ? OR assigned_to = ?)
                ");
                $check_stmt->execute([$attachment['entity_id'], $user_id, $user_id]);
                $has_permission = $check_stmt->rowCount() > 0;
                break;

            case 'case':
                // Check if user is assigned to the case
                $check_stmt = $pdo->prepare("
                    SELECT id FROM case_assignments
                    WHERE id = ? AND (assigned_by = ? OR assigned_to = ?)
                ");
                $check_stmt->execute([$attachment['entity_id'], $user_id, $user_id]);
                $has_permission = $check_stmt->rowCount() > 0;
                break;
        }
    }

    if (!$has_permission) {
        http_response_code(403);
        die('Access denied');
    }

    // Build file path
    $file_path = __DIR__ . '/../uploads/' . $entity_type . 's/' . $attachment['stored_filename'];

    // Check if file exists
    if (!file_exists($file_path)) {
        http_response_code(404);
        die('File not found on disk');
    }

    // Set headers based on action
    $mime_type = $attachment['mime_type'] ?: mime_content_type($file_path);
    $file_size = filesize($file_path);

    if ($action === 'view') {
        // For viewing, try to display in browser
        header('Content-Type: ' . $mime_type);
        header('Content-Length: ' . $file_size);
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
    } else {
        // For downloading, force download
        header('Content-Type: ' . $mime_type);
        header('Content-Length: ' . $file_size);
        header('Content-Disposition: attachment; filename="' . $attachment['original_filename'] . '"');
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
    }

    // Clear output buffer
    if (ob_get_level()) {
        ob_clean();
    }

    // Output file
    readfile($file_path);
    exit;

} catch (Exception $e) {
    error_log("File access error: " . $e->getMessage());
    http_response_code(500);
    die('Server error');
}
?></content>
<parameter name="filePath">c:\xampp\htdocs\Law_Enforcement_-_Incident_Report\download_attachment.php