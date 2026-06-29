<?php
$uri = $_SERVER['REQUEST_URI'];
$path = parse_url($uri, PHP_URL_PATH);
$root = dirname(__DIR__);

// Archivos estáticos
$staticFile = __DIR__ . $path;
if ($path !== '/' && file_exists($staticFile) && !is_dir($staticFile)) {
    return false;
}

// Rutas API y vistas
$routes = [
    '/auth/login.php' => $root . '/api/auth/login.php',
    '/auth/logout.php' => $root . '/api/auth/logout.php',
    '/auth/session.php' => $root . '/api/auth/session.php',
    '/auth/change-password.php' => $root . '/api/auth/change-password.php',
    '/logout.php' => $root . '/api/auth/logout.php',

    // Tabs principales
    '/api/resumen.php' => $root . '/api/resumen.php',
    '/api/proyectos.php' => $root . '/api/proyectos.php',
    '/api/clientes.php' => $root . '/api/clientes.php',
    '/api/pagos.php' => $root . '/api/pagos.php',
    '/api/documentos.php' => $root . '/api/documentos.php',
    '/api/password.php' => $root . '/api/auth/change-password.php',

    // Backend actions (POST)
    '/api/projects.php' => $root . '/api/app/projects.php',
    '/api/payments.php' => $root . '/api/app/payments.php',
    '/api/documents.php' => $root . '/api/app/documents.php',
    '/api/clientes_backend.php' => $root . '/api/clientes_backend.php',
    '/api/pagos/form.php' => $root . '/api/pagos/form.php',
];

$public_pages = [
    '/app.php' => __DIR__ . '/app.php',
    '/login.php' => __DIR__ . '/login.php',
];

if (isset($routes[$path])) {
    require $routes[$path];
    return true;
}

if (isset($public_pages[$path])) {
    require $public_pages[$path];
    return true;
}

require __DIR__ . '/login.php';
return true;
