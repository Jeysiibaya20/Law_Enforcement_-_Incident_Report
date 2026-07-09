<?php
/**
 * Ensure 'Approved' exists in blotters.status enum
 * Run in browser: http://localhost/setup_blotter_status.php
 */
require_once __DIR__ . '/config/db_connect.php';

try {
    $row = $pdo->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'blotters' AND COLUMN_NAME = 'status'")->fetchColumn();
    if (!$row) {
        throw new Exception('Could not find blotters.status column.');
    }

    // column_type looks like: enum('Pending','Under Investigation','Resolved','Archived')
    if (strpos($row, "'Approved'") !== false && strpos($row, "'Rejected'") !== false) {
        echo "'Approved' and 'Rejected' already present in blotters.status.<br>\n";
        exit;
    }

    // Build new enum preserving existing values and inserting 'Approved' and 'Rejected' after 'Pending' if possible
    preg_match_all("/'([^']+)'/", $row, $matches);
    $vals = $matches[1];
    $newVals = [];
    $insertedApproved = false;
    $insertedRejected = false;

    foreach ($vals as $v) {
        $newVals[] = $v;
        if (!$insertedApproved && strtolower($v) === 'pending') {
            $newVals[] = 'Approved';
            $insertedApproved = true;
        }
        if (!$insertedRejected && strtolower($v) === 'resolved') {
            $newVals[] = 'Rejected';
            $insertedRejected = true;
        }
    }

    if (!$insertedApproved) {
        $newVals[] = 'Approved';
    }
    if (!$insertedRejected) {
        $newVals[] = 'Rejected';
    }

    $enumSql = "ENUM('" . implode("','", array_map(function($x){return str_replace("'","\\'", $x);}, $newVals)) . "') NOT NULL";

    $sql = "ALTER TABLE blotters MODIFY COLUMN status {$enumSql}";
    $pdo->exec($sql);
    echo "Updated blotters.status to include 'Approved'.<br>\n";
} catch (Exception $e) {
    echo 'Migration failed: ' . htmlspecialchars($e->getMessage());
}
