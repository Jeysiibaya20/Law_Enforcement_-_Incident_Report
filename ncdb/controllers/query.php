<?php
/**
 * NCDB Query Handler
 * Secure AJAX endpoint for database operations
 */

session_start();
require_once '../config/db_connect.php';
require_once '../config/ncdb_config.php';
require_once '../services/NCDatabaseService.php';
require_once '../services/DuplicateDetectionService.php';
require_once '../services/AccessAuditLogger.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Check permission
$allowed_roles = ['Officer', 'Admin'];
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed_roles)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access Denied']);
    exit;
}

// Initialize services
$ncdb_service = new NCDatabaseService($pdo);
$duplicate_service = new DuplicateDetectionService($pdo);
$audit_logger = new AccessAuditLogger($pdo);

header('Content-Type: application/json');

$action = $_POST['action'] ?? null;

try {
    switch ($action) {
        case 'load_records':
            handleLoadRecords();
            break;
        
        case 'verify_record':
            handleVerifyRecord();
            break;
        
        case 'flag_duplicate':
            handleFlagDuplicate();
            break;
        
        case 'get_duplicate_reviews':
            handleGetDuplicateReviews();
            break;
        
        case 'approve_duplicate':
            handleApproveDuplicate();
            break;
        
        case 'reject_duplicate':
            handleRejectDuplicate();
            break;
        
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Unknown action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// ============================================================================
// ACTION HANDLERS
// ============================================================================

function handleLoadRecords() {
    global $pdo;
    
    $type = $_POST['type'] ?? null;
    $records = [];
    
    try {
        switch ($type) {
            case 'BLOTTER':
                $sql = "SELECT id, CONCAT(blotter_no, ' - ', complainant_name) as label 
                       FROM blotters 
                       WHERE status IN ('Pending', 'Under Investigation')
                       ORDER BY created_at DESC 
                       LIMIT 100";
                break;
            
            case 'CASE':
                $sql = "SELECT id, CONCAT(case_number, ' - ', case_title) as label 
                       FROM case_assignments 
                       WHERE status IN ('Open', 'Under Investigation')
                       ORDER BY created_at DESC 
                       LIMIT 100";
                break;
            
            case 'SUSPECT':
                $sql = "SELECT id, full_name as label 
                       FROM suspect_witness 
                       WHERE type = 'Suspect'
                       ORDER BY created_at DESC 
                       LIMIT 100";
                break;
            
            case 'WITNESS':
                $sql = "SELECT id, full_name as label 
                       FROM suspect_witness 
                       WHERE type = 'Witness'
                       ORDER BY created_at DESC 
                       LIMIT 100";
                break;
            
            default:
                throw new Exception('Invalid record type');
        }
        
        $stmt = $pdo->query($sql);
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'records' => $records,
            'count' => count($records)
        ]);
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function handleVerifyRecord() {
    global $pdo, $ncdb_service, $duplicate_service, $audit_logger;
    
    try {
        $record_type = $_POST['record_type'] ?? null;
        $record_id = intval($_POST['record_id'] ?? 0);
        $verification_type = $_POST['verification_type'] ?? 'IDENTITY_VERIFICATION';
        
        if (!$record_type || $record_id <= 0) {
            throw new Exception('Invalid record selection');
        }
        
        // Verify record
        $result = $ncdb_service->verifyRecord($record_type, $record_id, $verification_type);
        
        // Check for duplicates
        $duplicates = $duplicate_service->checkForDuplicates($record_type, $result['record']);
        
        // Log verification
        $audit_logger->logAccess(
            'VERIFY',
            $verification_type,
            ['record_type' => $record_type, 'record_id' => $record_id],
            count($result['ncdb_matches']),
            null,
            'SUCCESS'
        );
        
        echo json_encode([
            'success' => true,
            'record' => $result['record'],
            'matches' => $result['ncdb_matches'],
            'duplicates' => $duplicates
        ]);
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function handleFlagDuplicate() {
    global $pdo, $duplicate_service, $audit_logger;
    
    try {
        $local_record_id = intval($_POST['local_record_id'] ?? 0);
        $local_record_type = $_POST['local_record_type'] ?? null;
        $ncdb_record_id = intval($_POST['ncdb_record_id'] ?? 0);
        $action_notes = $_POST['notes'] ?? null;
        
        if ($local_record_id <= 0 || !$local_record_type || $ncdb_record_id <= 0) {
            throw new Exception('Invalid parameters');
        }
        
        $result = $duplicate_service->flagAsDuplicate(
            $local_record_id,
            $local_record_type,
            $ncdb_record_id,
            $action_notes
        );
        
        if ($result) {
            $audit_logger->logAccess(
                'DUPLICATE_CHECK',
                'FLAG_DUPLICATE',
                ['local_id' => $local_record_id, 'ncdb_id' => $ncdb_record_id],
                null,
                null,
                'SUCCESS'
            );
            
            echo json_encode([
                'success' => true,
                'message' => 'Duplicate flagged successfully'
            ]);
        } else {
            throw new Exception('Failed to flag duplicate');
        }
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function handleGetDuplicateReviews() {
    global $duplicate_service, $audit_logger;
    
    try {
        $limit = intval($_POST['limit'] ?? 50);
        
        $reviews = $duplicate_service->getPendingDuplicateReviews($limit);
        
        $audit_logger->logAccess(
            'QUERY',
            'DUPLICATE_REVIEWS',
            ['limit' => $limit],
            count($reviews),
            null,
            'SUCCESS'
        );
        
        echo json_encode([
            'success' => true,
            'reviews' => $reviews,
            'count' => count($reviews)
        ]);
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function handleApproveDuplicate() {
    global $pdo, $audit_logger;
    
    try {
        $duplicate_id = intval($_POST['duplicate_id'] ?? 0);
        $merge_action = $_POST['merge_action'] ?? 'MARK_DUPLICATE';
        
        if ($duplicate_id <= 0) {
            throw new Exception('Invalid duplicate ID');
        }
        
        $sql = "UPDATE ncdb_duplicate_detection 
               SET is_duplicate = 1,
                   duplicate_action_taken = :action,
                   reviewed_by = :user_id,
                   reviewed_at = NOW()
               WHERE id = :id";
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            ':id' => $duplicate_id,
            ':action' => $merge_action,
            ':user_id' => $_SESSION['user_id'],
        ]);
        
        if ($result) {
            $audit_logger->logAccess(
                'DUPLICATE_CHECK',
                'APPROVE_DUPLICATE',
                ['duplicate_id' => $duplicate_id],
                null,
                null,
                'SUCCESS'
            );
            
            echo json_encode([
                'success' => true,
                'message' => 'Duplicate approved and recorded'
            ]);
        } else {
            throw new Exception('Failed to approve duplicate');
        }
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function handleRejectDuplicate() {
    global $pdo, $audit_logger;
    
    try {
        $duplicate_id = intval($_POST['duplicate_id'] ?? 0);
        $rejection_reason = $_POST['reason'] ?? 'Not a duplicate';
        
        if ($duplicate_id <= 0) {
            throw new Exception('Invalid duplicate ID');
        }
        
        $sql = "UPDATE ncdb_duplicate_detection 
               SET is_duplicate = 0,
                   duplicate_action_taken = :reason,
                   reviewed_by = :user_id,
                   reviewed_at = NOW()
               WHERE id = :id";
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            ':id' => $duplicate_id,
            ':reason' => $rejection_reason,
            ':user_id' => $_SESSION['user_id'],
        ]);
        
        if ($result) {
            $audit_logger->logAccess(
                'DUPLICATE_CHECK',
                'REJECT_DUPLICATE',
                ['duplicate_id' => $duplicate_id],
                null,
                null,
                'SUCCESS'
            );
            
            echo json_encode([
                'success' => true,
                'message' => 'Duplicate rejected'
            ]);
        } else {
            throw new Exception('Failed to reject duplicate');
        }
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

?>
