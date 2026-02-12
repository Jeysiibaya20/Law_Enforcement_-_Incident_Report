<?php
/**
 * Fix notifications foreign keys: make incident_id nullable and add blotter_id FK
 * Run in browser: http://localhost/setup_notifications_fks.php
 */
require_once __DIR__ . '/config/db_connect.php';

try {
    // Drop existing foreign key on incident_id if exists
    $pdo->beginTransaction();

    // Find FK name for incident_id if exists
    $stmt = $pdo->prepare("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notifications' AND COLUMN_NAME = 'incident_id' AND REFERENCED_TABLE_NAME = 'incidents'");
    $stmt->execute();
    $fk = $stmt->fetchColumn();
    if ($fk) {
        $pdo->exec("ALTER TABLE notifications DROP FOREIGN KEY `" . $fk . "`");
        echo "Dropped foreign key {$fk}.<br>\n";
    }

    // Make incident_id nullable
    $pdo->exec("ALTER TABLE notifications MODIFY COLUMN incident_id INT NULL");
    echo "Made notifications.incident_id NULLABLE.<br>\n";

    // Add FK back with ON DELETE SET NULL
    $pdo->exec("ALTER TABLE notifications ADD CONSTRAINT fk_notifications_incident FOREIGN KEY (incident_id) REFERENCES incidents(id) ON DELETE SET NULL ON UPDATE CASCADE");
    echo "Added FK fk_notifications_incident ON DELETE SET NULL.<br>\n";

    // Add blotter_id column if does not exist
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notifications' AND COLUMN_NAME = 'blotter_id'");
    $stmt->execute();
    $exists = $stmt->fetchColumn() > 0;
    if (!$exists) {
        $pdo->exec("ALTER TABLE notifications ADD COLUMN blotter_id INT NULL");
        echo "Added column blotter_id to notifications.<br>\n";
    }

    // Add FK for blotter_id
    $stmt = $pdo->prepare("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notifications' AND COLUMN_NAME = 'blotter_id' AND REFERENCED_TABLE_NAME = 'blotters'");
    $stmt->execute();
    $fk2 = $stmt->fetchColumn();
    if (!$fk2) {
        $pdo->exec("ALTER TABLE notifications ADD CONSTRAINT fk_notifications_blotter FOREIGN KEY (blotter_id) REFERENCES blotters(id) ON DELETE SET NULL ON UPDATE CASCADE");
        echo "Added FK fk_notifications_blotter ON DELETE SET NULL.<br>\n";
    }

    $pdo->commit();
    echo "Migration complete.<br>\n";
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo 'Migration failed: ' . htmlspecialchars($e->getMessage());
}
