<?php
/**
 * Migration: add photo_path columns to suspects and witnesses tables
 * Run this once from the project root (php migrations/add_photo_path_columns.php)
 */

require_once dirname(__DIR__) . '/config/db_connect.php';

function ensureColumn(PDO $pdo, $table, $column) {
    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM `" . str_replace('`','', $table) . "` LIKE ?");
        $stmt->execute([$column]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            echo "[OK] Column {$column} already exists on {$table}\n";
            return true;
        }

        $sql = "ALTER TABLE `" . str_replace('`','', $table) . "` ADD COLUMN `{$column}` VARCHAR(255) DEFAULT NULL";
        $pdo->exec($sql);
        echo "[OK] Added column {$column} to {$table}\n";
        return true;
    } catch (PDOException $e) {
        echo "[ERROR] Could not ensure column {$column} on {$table}: " . $e->getMessage() . "\n";
        return false;
    }
}

$allGood = true;
$allGood &= ensureColumn($pdo, 'suspects', 'photo_path');
$allGood &= ensureColumn($pdo, 'witnesses', 'photo_path');

// Create upload directories if missing
$dirs = [__DIR__ . '/../uploads/suspects/', __DIR__ . '/../uploads/witnesses/'];
foreach ($dirs as $d) {
    if (!is_dir($d)) {
        if (mkdir($d, 0755, true)) {
            echo "[OK] Created directory: {$d}\n";
        } else {
            echo "[WARN] Could not create directory: {$d}\n";
            $allGood = false;
        }
    } else {
        echo "[OK] Directory exists: {$d}\n";
    }
}

if ($allGood) {
    echo "Migration completed. You can now upload photos for suspects and witnesses.\n";
    exit(0);
} else {
    echo "Migration finished with warnings/errors. Check output above.\n";
    exit(2);
}
