<?php
require_once __DIR__ . '/../config/db_connect.php';
$pdo = getDBConnection();

echo "Testing DB connection...\n";
if ($pdo) {
    echo "✓ DB connected successfully.\n";
}

// Test reset_tokens table creation
$pdo->exec("CREATE TABLE IF NOT EXISTS reset_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(64) NOT NULL,
    expires DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
echo "✓ reset_tokens table exists/created successfully.\n";

// Test signup table columns
$check = $pdo->query("SHOW COLUMNS FROM signup LIKE 'resident_qc'");
if ($check && $check->fetch()) {
    echo "✓ resident_qc column exists in signup table.\n";
} else {
    $pdo->exec("ALTER TABLE signup ADD COLUMN resident_qc TINYINT(1) DEFAULT 1");
    echo "✓ Added resident_qc column to signup table.\n";
}

$checkBrgy = $pdo->query("SHOW COLUMNS FROM signup LIKE 'barangay'");
if ($checkBrgy && $checkBrgy->fetch()) {
    echo "✓ barangay column exists in signup table.\n";
} else {
    $pdo->exec("ALTER TABLE signup ADD COLUMN barangay VARCHAR(100) DEFAULT NULL");
    echo "✓ Added barangay column to signup table.\n";
}

$checkAddr = $pdo->query("SHOW COLUMNS FROM signup LIKE 'address'");
if ($checkAddr && $checkAddr->fetch()) {
    echo "✓ address column exists in signup table.\n";
} else {
    $pdo->exec("ALTER TABLE signup ADD COLUMN address VARCHAR(255) DEFAULT NULL");
    echo "✓ Added address column to signup table.\n";
}

echo "All verifications passed!\n";
