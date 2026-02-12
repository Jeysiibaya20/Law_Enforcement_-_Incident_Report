<?php
/**
 * Idempotent migration to add missing blotter columns
 * Run: php setup_blotter_columns.php
 */
require_once __DIR__ . '/config/db_connect.php';

try {
    $dbName = $pdo->query('SELECT DATABASE()')->fetchColumn();
    echo "Using database: {$dbName}\n";

    $cols = [
        // column_name => column_definition
        'respondent_contact' => "VARCHAR(50) DEFAULT NULL",
        'respondent_email' => "VARCHAR(150) DEFAULT NULL",
        'respondent_address' => "VARCHAR(255) DEFAULT NULL",
        'hearing_date' => "DATE NULL",
        'hearing_time' => "TIME NULL",
        'hearing_location' => "VARCHAR(255) DEFAULT NULL",
    ];

    foreach ($cols as $col => $def) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'blotters' AND COLUMN_NAME = ?");
        $stmt->execute([$dbName, $col]);
        $exists = $stmt->fetchColumn() > 0;
        if ($exists) {
            echo "Column {$col} already exists in blotters.\n";
            continue;
        }

        $alter = "ALTER TABLE blotters ADD COLUMN {$col} {$def}";
        echo "Adding column {$col}... ";
        $pdo->exec($alter);
        echo "OK\n";
    }

    // Ensure notifications table has blotter_id (optional)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'notifications' AND COLUMN_NAME = 'blotter_id'");
    $stmt->execute([$dbName]);
    $exists = $stmt->fetchColumn() > 0;
    if ($exists) {
        echo "Column blotter_id already exists in notifications.\n";
    } else {
        echo "Adding column blotter_id to notifications... ";
        $pdo->exec("ALTER TABLE notifications ADD COLUMN blotter_id INT NULL");
        echo "OK\n";
    }

    echo "Migration complete.\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
