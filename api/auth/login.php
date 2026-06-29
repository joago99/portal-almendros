<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$email = trim($input['email'] ?? '');
$password = $input['password'] ?? '';

if (!$email || !$password) {
    echo json_encode(['ok' => false, 'error' => 'Email y contraseña requeridos']);
    exit;
}

$db = Database::get();
$stmt = $db->prepare('SELECT id, password_hash, role, name, force_password_change FROM app_users WHERE email = ? AND active = 1');
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    echo json_encode(['ok' => false, 'error' => 'Credenciales inválidas']);
    exit;
}

session_start();
session_regenerate_id(true);
$_SESSION['user_id'] = (int)$user['id'];
$_SESSION['user_role'] = $user['role'];
$_SESSION['user_name'] = $user['name'];

$db->prepare('UPDATE app_users SET last_login_at = datetime("now") WHERE id = ?')->execute([$user['id']]);

echo json_encode([
    'ok' => true,
    'user' => [
        'id' => $user['id'],
        'role' => $user['role'],
        'name' => $user['name'],
        'force_password_change' => (bool)$user['force_password_change'],
    ]
]);
