<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../config.php';

session_start();
$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'No autenticado']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$current = $input['current'] ?? '';
$new = $input['new'] ?? '';

if (!$current || !$new) {
    echo json_encode(['ok' => false, 'error' => 'Ambas contraseñas requeridas']);
    exit;
}

if (strlen($new) < 6) {
    echo json_encode(['ok' => false, 'error' => 'La nueva contraseña debe tener al menos 6 caracteres']);
    exit;
}

$db = Database::get();
$stmt = $db->prepare('SELECT password_hash FROM app_users WHERE id = ?');
$stmt->execute([$userId]);
$row = $stmt->fetch();

if (!password_verify($current, $row['password_hash'])) {
    echo json_encode(['ok' => false, 'error' => 'Contraseña actual incorrecta']);
    exit;
}

$hash = password_hash($new, PASSWORD_DEFAULT);
$db->prepare('UPDATE app_users SET password_hash = ?, force_password_change = 0 WHERE id = ?')->execute([$hash, $userId]);
$_SESSION['user_force_password_change'] = false;

// Audit
$db->prepare('INSERT INTO audit_logs (actor_id, action, target_type, target_id) VALUES (?,?,?,?)')
    ->execute([$userId, 'change_password', 'app_users', $userId]);

echo json_encode(['ok' => true, 'message' => 'Contraseña actualizada']);
