<?php
// Configuración SQLite (sin MySQL)
define('DB_PATH', __DIR__ . '/portal.db');

// Roles permitidos por defecto en el portal
const ALLOWED_ROLES = ['admin', 'staff', 'client'];

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

/**
 * Centralized auth guard.
 *
 * @return array{user_id:int, role:string, active:int, expires_at:string|null}
 */
function require_auth(): array {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $userId = $_SESSION['user_id'] ?? null;
    $role   = $_SESSION['user_role'] ?? null;

    if (!$userId || !$role) {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'No autenticado']);
        exit;
    }

    $db = Database::get();
    $stmt = $db->prepare('SELECT active, expires_at FROM app_users WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    $active = $user ? (int)$user['active'] : 0;
    $expiresAt = $user ? ($user['expires_at'] ?: null) : null;

    if (!$user || $active === 0 || ($expiresAt && $expiresAt < date('Y-m-d'))) {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'No autenticado']);
        exit;
    }

    return [
        'user_id' => (int)$userId,
        'role' => (string)$role,
        'active' => $active,
        'expires_at' => $expiresAt,
    ];
}

function getDbPath(): string {
    return __DIR__ . '/portal.db';
}
