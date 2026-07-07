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
    '/portal' => __DIR__ . '/app.php',

    // Tabs principales
    '/api/resumen.php' => $root . '/api/resumen.php',
    '/api/proyectos.php' => $root . '/api/proyectos.php',
    '/api/avance.php' => $root . '/api/avance.php',
    '/api/clientes.php' => $root . '/api/clientes.php',
    '/api/pagos.php' => $root . '/api/pagos.php',
    '/api/documentos.php' => $root . '/api/documentos.php',
    '/api/password.php' => $root . '/api/auth/change-password.php',

    // Admin
    '/api/admin.php' => $root . '/api/admin.php',

    // Backend actions (POST)
    '/api/projects.php' => $root . '/api/projects.php',
    '/api/payments.php' => $root . '/api/pagos.php',
    '/api/documents.php' => $root . '/api/documentos.php',
    '/api/clientes_backend.php' => $root . '/api/clientes_backend.php',
    '/api/pagos/form.php' => $root . '/api/pagos/form.php',
    '/api/progress.php' => $root . '/api/progress.php',
    '/api/subir_foto.php' => $root . '/api/subir_foto.php',
];

$public_pages = [
    '/' => __DIR__ . '/../api/auth/login.php',
    '/app.php' => __DIR__ . '/app.php',
    '/login.php' => __DIR__ . '/login.php',
    '/auth/login.php' => __DIR__ . '/../api/auth/login.php',
    '/api/auth/login.php' => __DIR__ . '/../api/auth/login.php',
    '/auth/logout.php' => __DIR__ . '/../api/auth/logout.php',
    '/auth/session.php' => __DIR__ . '/../api/auth/session.php',
    '/auth/change-password.php' => __DIR__ . '/../api/auth/change-password.php',
    '/logout.php' => __DIR__ . '/../api/auth/logout.php',
    '/test-router' => __DIR__ . '/../api/auth/login-test-min.php',
    '/test-session' => __DIR__ . '/../api/auth/login-test-session.php',
    '/test-full' => __DIR__ . '/../api/auth/login-test-full.php',
];

if (isset($public_pages[$path])) {
    require $public_pages[$path];
    return true;
}

if (isset($routes[$path])) {
    require $routes[$path];
    return true;
}

http_response_code(404);
echo '404 Not Found';
return true;
