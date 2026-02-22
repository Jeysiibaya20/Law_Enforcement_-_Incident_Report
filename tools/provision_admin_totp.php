<?php
// CLI tool: provision TOTP secrets for admin accounts that don't have one yet
// Usage: php tools/provision_admin_totp.php

require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/two_factor_auth.php';

$tfa = new TwoFactorAuth($pdo);

function generate_base32_secret($length = 16) {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret = '';
    try {
        for ($i = 0; $i < $length; $i++) {
            $secret .= $chars[random_int(0, strlen($chars) - 1)];
        }
    } catch (Exception $e) {
        for ($i = 0; $i < $length; $i++) {
            $secret .= $chars[mt_rand(0, strlen($chars) - 1)];
        }
    }
    return $secret;
}

echo "Provisioning TOTP for admin accounts...\n";

try {
    // Find admin users in signup table (role contains 'admin')
    $stmt = $pdo->prepare("SELECT user_id, username, emailadd, role FROM signup WHERE LOWER(role) LIKE '%admin%'");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) {
        echo "No admin rows found in signup table.\n";
        exit(0);
    }

    foreach ($rows as $r) {
        $uid = $r['user_id'];
        $username = $r['username'] ?? ('user' . $uid);
        $existing = $tfa->getUserSecret($uid);
        if (!empty($existing)) {
            echo "User {$uid} ({$username}) already has TOTP enabled; skipping.\n";
            continue;
        }

        $secret = generate_base32_secret(16);
        $ok = false;
        try {
            $ok = $tfa->enable2FA($uid, $secret, 'TOTP');
        } catch (Exception $e) {
            echo "Error enabling 2FA for user {$uid}: " . $e->getMessage() . "\n";
        }

        if ($ok) {
            echo "Enabled TOTP for user {$uid} ({$username}). Secret: {$secret}\n";
            // Optionally display otpauth URL for manual provisioning
            $issuer = 'Alertara';
            $label = urlencode($username);
            $otpauth = "otpauth://totp/" . urlencode($issuer) . ":" . $label . "?secret=" . $secret . "&issuer=" . urlencode($issuer) . "&algorithm=SHA1&digits=6&period=30";
            echo "OTPAUTH URL: {$otpauth}\n";
        } else {
            echo "Failed to enable TOTP for user {$uid} ({$username}).\n";
        }
    }
} catch (Exception $e) {
    echo "Error querying signup table: " . $e->getMessage() . "\n";
    exit(1);
}

echo "Done.\n";

?>
