<?php
// Simple executor for SQL files using mysqli (avoids pdo_mysql dependency)
$path = __DIR__ . '/../migrations/20260202_add_blotter_columns.sql';
if (!file_exists($path)) {
    die("SQL file not found: $path\n");
}
$sql = file_get_contents($path);
// Replace DELIMITER directives (none here) and split on semicolon safely
// We'll execute whole file via multi_query
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'law&inci';

$mysqli = new mysqli($host, $user, $pass);
if ($mysqli->connect_errno) {
    die("MySQL connection failed: " . $mysqli->connect_error . "\n");
}

// Select database explicitly (use backticks)
if (!$mysqli->select_db($dbname)) {
    // try with backticks
    if (!$mysqli->query("USE `" . $mysqli->real_escape_string($dbname) . "`")) {
        die("Failed to select database: " . $mysqli->error . "\n");
    }
}

if ($mysqli->multi_query($sql)) {
    do {
        if ($res = $mysqli->store_result()) {
            $res->free();
        }
    } while ($mysqli->more_results() && $mysqli->next_result());
    echo "SQL executed successfully.\n";
} else {
    echo "Error executing SQL: (" . $mysqli->errno . ") " . $mysqli->error . "\n";
}

$mysqli->close();
