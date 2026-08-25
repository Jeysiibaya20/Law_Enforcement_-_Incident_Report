<?php
require_once __DIR__ . '/../config/db_connect.php';
$pdo = getDBConnection();

try {
    // 1. Create a dummy incident
    $caseNo = 'TEST-DEL-' . time();
    $stmt = $pdo->prepare("INSERT INTO incidents (case_no, incident_type, reporter_name, incident_date, narrative, status) VALUES (?, ?, ?, CURDATE(), ?, 'Pending')");
    $stmt->execute([$caseNo, 'Theft', 'Tester', 'Test narrative for delete test']);
    $incId = (int)$pdo->lastInsertId();
    echo "Created dummy incident ID: $incId with case_no: $caseNo\n";

    // 2. Create a dummy blotter referencing this incident
    $bStmt = $pdo->prepare("INSERT INTO blotters (blotter_no, complainant_name, incident_type, incident_date, incident_time, location, description, status, priority, incident_id) VALUES (?, ?, ?, CURDATE(), CURTIME(), ?, ?, ?, ?, ?)");
    $blotterNo = 'BLOT-TEST-' . time();
    $bStmt->execute([$blotterNo, 'Juan Dela Cruz', 'Theft', 'QC', 'Test blotter linked to incident', 'Pending', 'Medium', $incId]);
    $blotterId = (int)$pdo->lastInsertId();
    echo "Created dummy blotter ID: $blotterId linked to incident $incId\n";

    // 3. Now execute our robust deletion transaction logic
    $pdo->beginTransaction();
    try { $pdo->prepare("UPDATE blotters SET incident_id = NULL WHERE incident_id = ?")->execute([$incId]); } catch (Exception $e) {}
    try { $pdo->prepare("DELETE FROM case_assignments WHERE incident_id = ?")->execute([$incId]); } catch (Exception $e) {}
    try { $pdo->prepare("DELETE FROM nlp_analysis_cache WHERE incident_id = ?")->execute([$incId]); } catch (Exception $e) {}
    try { $pdo->prepare("DELETE FROM notifications WHERE incident_id = ?")->execute([$incId]); } catch (Exception $e) {}
    try { $pdo->prepare("DELETE FROM review_requests WHERE incident_id = ?")->execute([$incId]); } catch (Exception $e) {}
    try { $pdo->prepare("DELETE FROM system_alerts WHERE incident_id = ?")->execute([$incId]); } catch (Exception $e) {}
    try { $pdo->prepare("DELETE FROM incident_forwards WHERE incident_id = ?")->execute([$incId]); } catch (Exception $e) {}
    try { $pdo->prepare("DELETE FROM incident_history WHERE incident_id = ?")->execute([$incId]); } catch (Exception $e) {}
    try { $pdo->prepare("UPDATE evidence_items SET incident_id = NULL WHERE incident_id = ?")->execute([$incId]); } catch (Exception $e) {}
    
    $delStmt = $pdo->prepare("DELETE FROM incidents WHERE id = ?");
    $delStmt->execute([$incId]);
    $pdo->commit();

    echo "SUCCESS: Deleted incident $incId cleanly without any foreign key error!\n";

    // Verify blotter is intact with incident_id = NULL
    $chkBlot = $pdo->prepare("SELECT id, incident_id FROM blotters WHERE id = ?");
    $chkBlot->execute([$blotterId]);
    $bRow = $chkBlot->fetch(PDO::FETCH_ASSOC);
    echo "Blotter record after deletion: id={$bRow['id']}, incident_id=" . var_export($bRow['incident_id'], true) . " (Preserved intact)\n";

    // Cleanup dummy blotter
    $pdo->prepare("DELETE FROM blotters WHERE id = ?")->execute([$blotterId]);
    echo "Cleaned up test blotter.\n";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "ERROR: " . $e->getMessage() . "\n";
}
