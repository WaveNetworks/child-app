<?php
/**
 * content.php
 * Serves framework content files (thumbnails, documents, media) from the
 * app-framework content directory. Lightweight — no session/auth/helpers.
 *
 * URL: /CHILD_APP_SLUG/content/{path} (rewritten by .htaccess)
 */

// Get relative path from rewrite or query string
$file = $_GET['f'] ?? '';

if (!$file || preg_match('/\.\.|\x00/', $file) || strpos($file, '\\') !== false) {
    http_response_code(400);
    exit;
}

// Resolve framework content base path
$fw_path = getenv('CHILD_APP_FRAMEWORK_PATH') ?: __DIR__ . '/../app-framework/framework';
$base_path = realpath($fw_path . '/content');

if (!$base_path) {
    http_response_code(500);
    exit;
}

$path = realpath($base_path . '/' . $file);

// Ensure resolved path is within the content directory
if (!$path || strpos($path, $base_path) !== 0 || !is_file($path)) {
    http_response_code(404);
    exit;
}

// Allowed extensions
$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$mime_map = [
    'png'  => 'image/png',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'webp' => 'image/webp',
    'gif'  => 'image/gif',
    'svg'  => 'image/svg+xml',
    'pdf'  => 'application/pdf',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'doc'  => 'application/msword',
    'mp4'  => 'video/mp4',
    'mp3'  => 'audio/mpeg',
    'md'   => 'text/markdown',
];

if (!isset($mime_map[$ext])) {
    http_response_code(403);
    exit;
}

// ETag based on file modification time for cache revalidation
$etag = '"' . md5($path . filemtime($path)) . '"';
header('ETag: ' . $etag);
header('Cache-Control: public, max-age=3600, must-revalidate');
header('X-Content-Type-Options: nosniff');

// Return 304 if browser has current version
if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
    http_response_code(304);
    exit;
}

header('Content-Type: ' . $mime_map[$ext]);
header('Content-Length: ' . filesize($path));
readfile($path);
