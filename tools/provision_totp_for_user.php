<?php
// CLI tool: provision or replace TOTP secret for a specific user by user_id or username
// Usage: php tools/provision_totp_for_user.php <user_id|username> [--force]

require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/two_factor_auth.php';

if ($argc < 2) {
    echo "Usage: php tools/provision_totp_for_user.php <user_id|username> [--force]\n";
    exit(1);
}

$identifier = $argv[1];
$force = in_array('--force', $argv);

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

// Resolve user by id or username
try {
    if (is_numeric($identifier)) {
        $stmt = $pdo->prepare("SELECT user_id, username, emailadd, role FROM signup WHERE user_id = ? LIMIT 1");
        $stmt->execute([$identifier]);
    } else {
        $stmt = $pdo->prepare("SELECT user_id, username, emailadd, role FROM signup WHERE username = ? LIMIT 1");
        $stmt->execute([$identifier]);
    }
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        echo "User not found for identifier: {$identifier}\n";
        exit(1);
    }

    $uid = $user['user_id'];
    $username = $user['username'] ?? ('user' . $uid);
    $email = $user['emailadd'] ?? '';
    $role = $user['role'] ?? '';

    $existing = $tfa->getUserSecret($uid);
    if ($existing && !$force) {
        echo "User {$uid} ({$username}) already has TOTP. Use --force to replace.\n";
        exit(0);
    }

    $secret = generate_base32_secret(16);
    // If replacing, disable then enable to update timestamps
    if ($existing) {
        $tfa->disable2FA($uid);
    }

    $ok = $tfa->enable2FA($uid, $secret, 'TOTP');
    if ($ok) {
        echo "Enabled/Updated TOTP for user {$uid} ({$username}). Secret: {$secret}\n";
        $issuer = 'Alertara';
        $label = urlencode($username);
        $otpauth = "otpauth://totp/" . urlencode($issuer) . ":" . $label . "?secret=" . $secret . "&issuer=" . urlencode($issuer) . "&algorithm=SHA1&digits=6&period=30";
        echo "OTPAUTH URL: {$otpauth}\n";
        // Provide QR link using Google Chart API
        $qr = 'https://chart.googleapis.com/chart?chs=250x250&chld=M|0&cht=qr&chl=' . urlencode($otpauth);
        echo "QR URL: {$qr}\n";
    } else {
        echo "Failed to enable TOTP for user {$uid} ({$username}).\n";
        exit(1);
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

?>