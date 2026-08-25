<?php
require_once 'config/db_connect.php';
$pdo = getDBConnection();
$adminUser = $pdo->query("SELECT * FROM signup WHERE LOWER(role) = 'admin' LIMIT 1")->fetch(PDO::FETCH_ASSOC);

if (!$adminUser) {
    $adminUser = ['user_id' => 130, 'role' => 'admin', 'fullname' => 'Administrator'];
}

$_SESSION['admin_user_id'] = $adminUser['user_id'];
$_SESSION['user_id'] = $adminUser['user_id'];
$_SESSION['role'] = 'admin';
$_SESSION['admin_role'] = 'admin';
$_SESSION['admin_fullname'] = $adminUser['fullname'] ?? 'Administrator';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/Law_Enforcement_-_Incident_Report/admin/external_integrations.php';

$testList = [
    ['dir' => __DIR__ . '/../admin', 'file' => 'external_integrations.php'],
    ['dir' => __DIR__ . '/../admin', 'file' => 'audit_trail.php'],
    ['dir' => __DIR__ . '/../admin', 'file' => 'blotters.php'],
    ['dir' => __DIR__ . '/../admin', 'file' => 'blotter_create.php'],
    ['dir' => __DIR__ . '/../modules', 'file' => 'department_integrations.php'],
    ['dir' => __DIR__ . '/../modules', 'file' => 'Request_form.php']
];

echo "=== TESTING PAGE WEB RUNTIME EXECUTION ===\n";
foreach ($testList as $item) {
    $cwd = getcwd();
    chdir($item['dir']);
    try {
        ob_start();
        include $item['file'];
        $out = ob_get_clean();
        echo "✔ Rendered [{$item['file']}]: " . strlen($out) . " bytes HTML generated!\n";
    } catch (Throwable $e) {
        if (ob_get_level() > 0) ob_end_clean();
        echo "❌ Error in [{$item['file']}]: " . $e->getMessage() . " (Line {$e->getLine()})\n";
    }
    chdir($cwd);
}
