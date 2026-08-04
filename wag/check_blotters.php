<?php
// Direct database connection check
$host = "localhost";
$user = "root";
$pass = "";
$db = "law&inci";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check total blotters
$result = $conn->query('SELECT COUNT(*) as cnt FROM blotters');
$row = $result->fetch_assoc();
echo "Total Blotters in DB: " . $row['cnt'] . "\n";

// Check sample records
$result = $conn->query('SELECT id, blotter_no, created_by, status FROM blotters LIMIT 5');
echo "\nSample Blotter Records:\n";
while($r = $result->fetch_assoc()) {
    echo "- ID: " . $r['id'] . ", No: " . $r['blotter_no'] . ", CreatedBy: " . $r['created_by'] . ", Status: " . $r['status'] . "\n";
}

// Check users
$result = $conn->query('SELECT user_id, fullname FROM signup LIMIT 3');
echo "\nSample Users:\n";
while($u = $result->fetch_assoc()) {
    echo "- User ID: " . $u['user_id'] . ", Name: " . $u['fullname'] . "\n";
}

// Check if created_by column exists
$result = $conn->query("DESCRIBE blotters");
echo "\nBlotters Table Columns:\n";
while($col = $result->fetch_assoc()) {
    echo "- " . $col['Field'] . " (" . $col['Type'] . ")\n";
}

$conn->close();
?>
