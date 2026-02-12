<?php
// Batch assign available BCPC officers to unassigned high-priority incidents
session_start();
require_once __DIR__ . '/../config/db_connect.php';

// Simple auth: only admin
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'admin') {
    echo "Access denied. Admins only.";
    exit;
}

try {
    $assigned = 0;
    $skipped = 0;
    // Select incidents needing assignment: assigned_to IS NULL and urgency High/Critical or is_high_risk
    $sql = "SELECT id, case_no, incident_type, urgency_level, is_high_risk FROM incidents WHERE (assigned_to IS NULL OR assigned_to = 0) AND (urgency_level IN ('High','Critical') OR is_high_risk = 1) ORDER BY created_at ASC LIMIT 100";
    $incStmt = $pdo->query($sql);
    $incidents = $incStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($incidents as $inc) {
        // Find an available officer with lowest case load
        $offSql = "SELECT bo.user_id, bo.current_case_load, bo.max_case_load FROM bcpc_officers bo WHERE bo.is_available = 1 AND bo.current_case_load < bo.max_case_load ORDER BY bo.current_case_load ASC LIMIT 1";
        $offStmt = $pdo->prepare($offSql);
        $offStmt->execute();
        $officer = $offStmt->fetch(PDO::FETCH_ASSOC);

        if ($officer) {
            // Assign incident
            $uSql = "UPDATE incidents SET assigned_to = :assigned_to, status = 'Under Review' WHERE id = :id";
            $uStmt = $pdo->prepare($uSql);
            $uStmt->execute([':assigned_to' => $officer['user_id'], ':id' => $inc['id']]);

            // Increment officer case load
            $incSql = "UPDATE bcpc_officers SET current_case_load = current_case_load + 1 WHERE user_id = :uid";
            $pdo->prepare($incSql)->execute([':uid' => $officer['user_id']]);

            $assigned++;
        } else {
            $skipped++;
        }
    }

    echo "<h3>Assign Officers - Batch Result</h3>";
    echo "<p>Incidents processed: " . count($incidents) . "</p>";
    echo "<p>Assigned: {$assigned}</p>";
    echo "<p>Skipped (no available officer): {$skipped}</p>";
    echo "<p><a href=\"../admin/dashboard.php\">← Back to Admin Dashboard</a></p>";

} catch (Exception $e) {
    echo "Error: " . htmlspecialchars($e->getMessage());
}
