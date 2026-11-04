<?php
// ============================================================
// Secure file server — streams upload files through PHP so
// direct-access restrictions on shared hosting never block them.
//
// Usage:  /file.php?f=uploads/tenants/deed_3_1234.pdf
//         /file.php?f=uploads/signatures/sig_5_1234.png
//
// Security:
//  - Must be logged in as admin OR tenant
//  - Path is restricted to the uploads/ directory only
//  - No directory traversal allowed
//  - Only known MIME types served
// ============================================================

require_once __DIR__ . '/classes/init.php';

// Allow both admin and tenant sessions
if (!\Auth::check() && !\Auth::checkTenant()) {
    http_response_code(403);
    exit('Access denied.');
}

$rel = isset($_GET['f']) ? trim($_GET['f']) : '';

// Normalise: strip leading slash/dot
$rel = ltrim($rel, '/.');

// Only allow paths inside uploads/
if (strpos($rel, 'uploads/') !== 0) {
    http_response_code(403);
    exit('Access denied.');
}

// Resolve absolute path and confirm it stays inside the uploads dir
$uploadsRoot = realpath(__DIR__ . '/uploads');
$absPath     = realpath(__DIR__ . '/' . $rel);

if ($absPath === false || strpos($absPath, $uploadsRoot . DIRECTORY_SEPARATOR) !== 0) {
    http_response_code(403);
    exit('Access denied.');
}

if (!is_file($absPath)) {
    http_response_code(404);
    exit('File not found.');
}

// Detect MIME type
$mimeMap = [
    'pdf'  => 'application/pdf',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'gif'  => 'image/gif',
    'webp' => 'image/webp',
];

$ext  = strtolower(pathinfo($absPath, PATHINFO_EXTENSION));
$mime = $mimeMap[$ext] ?? null;

if (!$mime) {
    http_response_code(403);
    exit('File type not allowed.');
}

// Double-check with finfo to prevent MIME spoofing
$finfo    = finfo_open(FILEINFO_MIME_TYPE);
$realMime = finfo_file($finfo, $absPath);
finfo_close($finfo);

$allowedMimes = array_values($mimeMap);
if (!in_array($realMime, $allowedMimes, true)) {
    http_response_code(403);
    exit('File type not allowed.');
}

$size     = filesize($absPath);
$filename = basename($absPath);

// Stream the file
header('Content-Type: ' . $mime);
header('Content-Length: ' . $size);
// inline so PDFs/images open in the browser (iframe viewer)
header('Content-Disposition: inline; filename="' . rawurlencode($filename) . '"');
header('Cache-Control: private, max-age=3600');
header('X-Content-Type-Options: nosniff');

readfile($absPath);
exit;
