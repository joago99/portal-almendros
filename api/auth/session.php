<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../config.php';

session_start();
$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    echo json_encode(['loggedIn' => false]);
    exit;
}

$db = Database::get();
$stmt = $db->prepare('SELECT id, email, role, name, force_password_change FROM app_users WHERE id = ? AND active = 1');
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    echo json_encode(['loggedIn' => false]);
    exit;
}

echo json_encode([
    'loggedIn' => true,
    'user' => [
        'id' => $user['id'],
        'email' => $user['email'],
        'role' => $user['role'],
        'name' => $user['name'],
        'force_password_change' => (bool)$user['force_password_change'],
    ]
]);
