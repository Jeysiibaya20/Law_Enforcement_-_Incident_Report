<?php
/**
 * Idempotent migration to add legacy `email` column to `signup` table
 * and populate it from `emailadd` to maintain backwards compatibility.
 * Run this via the browser (or CLI with PDO MySQL available).
 */
require_once __DIR__ . '/config/db_connect.php';

try {
    $row = $pdo->query("SHOW COLUMNS FROM signup LIKE 'email'")->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        echo "Column `email` already exists on `signup`.\n";
    } else {
        echo "Adding column `email` to `signup`...\n";
        $pdo->exec("ALTER TABLE signup ADD COLUMN email VARCHAR(150) DEFAULT NULL");
        echo "Added column.\n";
        echo "Populating `email` from `emailadd` where missing...\n";
        $pdo->exec("UPDATE signup SET email = emailadd WHERE (email IS NULL OR email = '') AND emailadd IS NOT NULL");
        echo "Done.\n";
    }

    // Extra safety: ensure future selects using COALESCE(email, emailadd) will work
    echo "Compatibility migration completed successfully.";
} catch (Exception $e) {
    http_response_code(500);
    echo "Migration failed: " . htmlspecialchars($e->getMessage());
    error_log("setup_add_compat_email.php failed: " . $e->getMessage());
}
