<?php
/**
 * uploads/img.php
 * Serve uploaded images via PHP to bypass IIS Windows Authentication
 * that blocks direct static file access.
 *
 * Usage: /uploads/img.php?d=avatars&f=filename.jpg
 *        /uploads/img.php?d=submissions&f=filename.png
 */

$allowedDirs  = ['avatars', 'submissions'];
$allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

$dir  = (string)($_GET['d'] ?? '');
$file = basename((string)($_GET['f'] ?? ''));   // basename prevents path traversal

// Validate directory and filename
if (!in_array($dir, $allowedDirs, true) || $file === '' || $file === '.') {
    http_response_code(404);
    exit;
}

$path = __DIR__ . '/' . $dir . '/' . $file;

if (!is_file($path)) {
    http_response_code(404);
    exit;
}

// Verify actual MIME type (not extension)
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = $finfo->file($path);

if (!in_array($mime, $allowedMimes, true)) {
    http_response_code(403);
    exit;
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Cache-Control: public, max-age=86400');
header('X-Content-Type-Options: nosniff');
readfile($path);
