<?php
/**
 * Ensure database exists, then run setup_incidents_table.php
 */
$host = 'localhost';
$user = 'root';
$pass = '';
$db   = 'law&inci';

$mysqli = new mysqli($host, $user, $pass);
if ($mysqli->connect_error) {
    die("MySQL connection failed: " . $mysqli->connect_error . "\n");
}

// Create database if not exists
$createSql = "CREATE DATABASE IF NOT EXISTS `" . $mysqli->real_escape_string($db) . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
if (!$mysqli->query($createSql)) {
    die("Failed to create database: " . $mysqli->error . "\n");
}

echo "Database `" . $db . "` verified/created.\n";

// Close mysqli and run the existing setup script which uses PDO
$mysqli->close();

// Run setup script
$setupPath = __DIR__ . DIRECTORY_SEPARATOR . 'setup_incidents_table.php';
if (!file_exists($setupPath)) {
    die("Setup script not found at: " . $setupPath . "\n");
}

// Execute the setup script via include so it runs in this process
require $setupPath;

echo "Setup script executed. Check output above or visit /setup_incidents_table.php in browser.\n";
