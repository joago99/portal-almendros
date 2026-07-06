<?php
// Portal entry point
$uri = $_SERVER['REQUEST_URI'];
$path = parse_url($uri, PHP_URL_PATH);
$public_dir = __DIR__ . '/public';
$static_file = $public_dir . $path;

// If a static file exists in public/, serve it directly
if ($path !== '/' && file_exists($static_file) && !is_dir($static_file)) {
    $ext = pathinfo($path, PATHINFO_EXTENSION);
    if ($ext === 'php') {
        require $static_file;
        return true;
    }
    $mime = [
        'css' => 'text/css',
        'js' => 'application/javascript',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon',
        'webp' => 'image/webp',
        'pdf' => 'application/pdf',
    ];
    if (isset($mime[$ext])) {
        header('Content-Type: ' . $mime[$ext]);
    }
    readfile($static_file);
    return true;
}

// For all other paths, run the router
require $public_dir . '/.htrouter.php';
