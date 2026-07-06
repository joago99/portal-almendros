<?php
// Portal entry point - delegate to public router
$uri = $_SERVER['REQUEST_URI'];
$path = parse_url($uri, PHP_URL_PATH);
$public_dir = __DIR__ . '/public';
$file = $public_dir . $path;

// If static file exists in public, serve it directly
if ($path != '/' && file_exists($file) && !is_dir($file)) {
    return false;
}

// Route root to the public router
if ($path === '/' || $path === '') {
    require $public_dir . '/.htrouter.php';
    return true;
}

// API/auth routes: run .htrouter which has full route table
require $public_dir . '/.htrouter.php';
