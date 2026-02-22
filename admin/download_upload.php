<?php
// Secure download / view proxy for uploaded files
// Require admin authorization (will redirect or block if not admin)
require_once __DIR__ . '/admin_auth.php';

$f = $_GET['f'] ?? '';
$inline = isset($_GET['inline']) && $_GET['inline'] === '1';

// sanitize: remove null bytes and any ../ sequences
$f = str_replace("\0", '', $f);
$f = str_replace('..', '', $f);
$f = ltrim($f, '/\\');

$root = realpath(__DIR__ . '/..');
$uploads = $root . DIRECTORY_SEPARATOR . 'uploads';

$target = $uploads . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $f);

if (!is_file($target)) {
    http_response_code(404);
    echo 'File not found';
    exit();
}

// Prevent serving files outside uploads dir
$realUploads = realpath($uploads);
$realTarget = realpath($target);
if (strpos($realTarget, $realUploads) !== 0) {
    http_response_code(403);
    echo 'Forbidden';
    exit();
}

$filename = basename($realTarget);
$mime = mime_content_type($realTarget) ?: 'application/octet-stream';

header('Content-Description: File Transfer');
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($realTarget));
if ($inline) {
    header('Content-Disposition: inline; filename="' . $filename . '"');
} else {
    header('Content-Disposition: attachment; filename="' . $filename . '"');
}
header('Cache-Control: public, must-revalidate');
header('Pragma: public');
readfile($realTarget);
exit();
