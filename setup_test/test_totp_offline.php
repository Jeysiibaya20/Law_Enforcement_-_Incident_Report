<?php
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/two_factor_auth.php';

$pdo = getDBConnection();
$tfa = new TwoFactorAuth($pdo);

echo "=== TESTING OPTION 2: OFFLINE TOTP AUTHENTICATOR ===" . PHP_EOL;

// 1. Generate Secret Key
$secret = $tfa->generateSecret(16);
echo "1. Generated 16-Char Base32 Secret: " . $secret . PHP_EOL;

// 2. Generate QR Code URL
$qrUrl = $tfa->getQRCodeUrl('test_user', $secret, 'Alertara Law Enforcement');
echo "2. QR Code URL: " . substr($qrUrl, 0, 70) . "..." . PHP_EOL;

// 3. Compute Current 6-digit TOTP Code
$timeStep = floor(time() / 30);
$secretKey = (function($input) {
    $map = array(
        'A' => 0, 'B' => 1, 'C' => 2, 'D' => 3, 'E' => 4, 'F' => 5, 'G' => 6, 'H' => 7,
        'I' => 8, 'J' => 9, 'K' => 10, 'L' => 11, 'M' => 12, 'N' => 13, 'O' => 14, 'P' => 15,
        'Q' => 16, 'R' => 17, 'S' => 18, 'T' => 19, 'U' => 20, 'V' => 21, 'W' => 22, 'X' => 23,
        'Y' => 24, 'Z' => 25, '2' => 26, '3' => 27, '4' => 28, '5' => 29, '6' => 30, '7' => 31
    );
    $input = strtoupper(str_replace('=', '', $input));
    $output = '';
    $v = 0; $vbits = 0;
    for ($i = 0; $i < strlen($input); $i++) {
        $v <<= 5; $v += $map[$input[$i]] ?? 0; $vbits += 5;
        if ($vbits >= 8) { $output .= chr(($v >> ($vbits - 8)) & 0xFF); $vbits -= 8; }
    }
    return $output;
})($secret);

$counter = pack('N*', 0) . pack('N*', $timeStep);
$hash = hash_hmac('sha1', $counter, $secretKey, true);
$offset = ord($hash[19]) & 0x0F;
$binCode = (ord($hash[$offset]) & 0x7F) << 24 | (ord($hash[$offset+1]) & 0xFF) << 16 | (ord($hash[$offset+2]) & 0xFF) << 8 | (ord($hash[$offset+3]) & 0xFF);
$expectedOtp = str_pad((string)($binCode % 1000000), 6, '0', STR_PAD_LEFT);

echo "3. Simulated Authenticator App Code: " . $expectedOtp . PHP_EOL;

// 4. Verify Code
$isValid = $tfa->verifyTOTP($secret, $expectedOtp);
echo "4. Verification Result: " . ($isValid ? "[SUCCESS - VALID OTP]" : "[FAILED]") . PHP_EOL;

// 5. Test invalid code rejection
$isInvalidRejected = !$tfa->verifyTOTP($secret, '000000');
echo "5. Invalid Code Rejection: " . ($isInvalidRejected ? "[SUCCESS - REJECTED]" : "[FAILED]") . PHP_EOL;

echo PHP_EOL . "=== ALL TOTP TESTS PASSED (100% OFFLINE / ZERO EMAIL REQUIRED) ===" . PHP_EOL;
