<?php
// List admin users and whether they have TOTP enabled
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/two_factor_auth.php';

$tfa = new TwoFactorAuth($pdo);

try {
    $stmt = $pdo->prepare("SELECT user_id, username, emailadd, role FROM signup ORDER BY user_id DESC");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) {
        echo "No users found in signup table.\n";
        exit(0);
    }

    echo "user_id | username | email | role | has_totp\n";
    echo str_repeat('-', 80) . "\n";
    foreach ($rows as $r) {
        $uid = $r['user_id'];
        $username = $r['username'] ?? '';
        $email = $r['emailadd'] ?? '';
        $role = $r['role'] ?? '';
        $has = $tfa->getUserSecret($uid) ? 'yes' : 'no';
        if (stripos($role, 'admin') !== false) {
            echo sprintf("%6s | %-20s | %-25s | %-10s | %s\n", $uid, $username, $email, $role, $has);
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

?>