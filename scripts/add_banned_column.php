<?php
// Safe migration runner: adds `banned` column to `signup` if missing.
// Usage: php scripts/add_banned_column.php

require_once __DIR__ . '/../config/db_connect.php';

try {
    // check table exists
    $tableCheck = $pdo->prepare("SHOW TABLES LIKE 'signup'");
    $tableCheck->execute();
    if (!$tableCheck->fetch()) {
        echo "Table `signup` does not exist.\n";
        exit(1);
    }

    // check column
    $colCheck = $pdo->prepare("SHOW COLUMNS FROM `signup` LIKE 'banned'");
    $colCheck->execute();
    $col = $colCheck->fetch(PDO::FETCH_ASSOC);

    if ($col) {
        echo "Column `banned` already exists on `signup`. Nothing to do.\n";
        exit(0);
    }

    // perform alter
    $sql = "ALTER TABLE `signup` ADD COLUMN `banned` TINYINT(1) NOT NULL DEFAULT 0";
    $pdo->exec($sql);
    echo "Column `banned` added successfully.\n";
    exit(0);
} catch (PDOException $e) {
    echo "PDO Error: " . $e->getMessage() . "\n";
    exit(2);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(3);
}
