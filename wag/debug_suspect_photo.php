<?php
require_once __DIR__ . '/config/db_connect.php';

// Check suspects with photo paths
$stmt = $pdo->query("SELECT id, first_name, last_name, photo_path FROM suspects WHERE photo_path IS NOT NULL OR id > 0 ORDER BY id DESC LIMIT 5");
$suspects = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "=== Suspect Photo Debug ===\n\n";

foreach ($suspects as $s) {
    echo "ID: " . $s['id'] . "\n";
    echo "Name: " . $s['first_name'] . " " . $s['last_name'] . "\n";
    echo "Photo Path: " . ($s['photo_path'] ?? 'NULL') . "\n";
    
    if ($s['photo_path']) {
        $full_path = __DIR__ . '/' . $s['photo_path'];
        $exists = file_exists($full_path);
        echo "File Exists: " . ($exists ? 'YES' : 'NO') . "\n";
        echo "Full Path: " . $full_path . "\n";
    }
    echo "---\n";
}

// Check uploads directory
echo "\n=== Upload Directory ===\n";
$upload_dir = __DIR__ . '/uploads/suspects/';
echo "Path: " . $upload_dir . "\n";
echo "Exists: " . (is_dir($upload_dir) ? 'YES' : 'NO') . "\n";

if (is_dir($upload_dir)) {
    $files = scandir($upload_dir);
    echo "Files: " . (count($files) - 2) . "\n"; // -2 for . and ..
    foreach ($files as $f) {
        if ($f !== '.' && $f !== '..') {
            echo "  - " . $f . "\n";
        }
    }
}
?>
