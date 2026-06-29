<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';
session_start();
$userId = $_SESSION['user_id'] ?? null;
$userRole = $_SESSION['user_role'] ?? null;
header('Content-Type: application/json');

if (!$userId || !in_array($userRole, ['admin', 'staff'])) {
    echo json_encode(['ok'=>false,'error'=>'Acceso denegado']); exit;
}
$db = Database::get();
$action = $_GET['action'] ?? '';

if ($action === 'create') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input['name']) { echo json_encode(['ok'=>false,'error'=>'Nombre requerido']); exit; }
    $stmt = $db->prepare('INSERT INTO clients (name, email, rut, phone) VALUES (?,?,?,?)');
    $stmt->execute([$input['name'], $input['email']?:null, $input['rut']?:null, $input['phone']?:null]);
    echo json_encode(['ok'=>true, 'id'=>$db->lastInsertId()]); exit;
}

if ($action === 'create_user') {
    if ($userRole !== 'admin') { echo json_encode(['ok'=>false,'error'=>'Solo admin']); exit; }
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input['email']||!$input['name']||!$input['password']) { echo json_encode(['ok'=>false,'error'=>'Faltan datos']); exit; }
    $chk = $db->prepare('SELECT id FROM app_users WHERE email = ?');
    $chk->execute([$input['email']]);
    if ($chk->fetch()) { echo json_encode(['ok'=>false,'error'=>'Email ya registrado']); exit; }
    $hash = password_hash($input['password'], PASSWORD_DEFAULT);
    $db->prepare('INSERT INTO app_users (email, password_hash, role, name, client_id, force_password_change) VALUES (?,?,?,?,?,1)')
        ->execute([$input['email'], $hash, 'client', $input['name'], $input['client_id']?:null]);
    echo json_encode(['ok'=>true, 'email'=>$input['email']]); exit;
}

echo json_encode(['ok'=>false, 'error'=>'Acción no válida']);
