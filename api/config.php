<?php
// Configuración SQLite (sin MySQL)
define('DB_PATH', __DIR__ . '/portal.db');

// Configuración de sesión
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => false,
    'httponly' => true,
    'samesite' => 'Lax'
]);

function getDbPath(): string {
    return __DIR__ . '/portal.db';
}
