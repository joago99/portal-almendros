<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';
session_start();
$userId = $_SESSION['user_id'] ?? null;
$userRole = $_SESSION['user_role'] ?? null;
header('Content-Type: application/json');
if (!$userId || !in_array($userRole, ['admin', 'staff'])) { echo json_encode(['ok'=>false,'error'=>'Acceso denegado']); exit; }
$db = Database::get();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'create') {
  $input = json_decode(file_get_contents('php://input'), true);
  if (!$input['name']) { echo json_encode(['ok'=>false,'error'=>'Nombre requerido']); exit; }
  $email = $input['email'] ?? null;
  $rut = $input['rut'] ?? null;
  if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) { echo json_encode(['ok'=>false,'error'=>'Email inválido']); exit; }
  if ($rut && !preg_match('/^[0-9]{1,2}\\.[0-9]{3}\\.[0-9]{3}[-][0-9kK]{1}$/', str_replace('.', '', $rut))) { echo json_encode(['ok'=>false,'error'=>'RUT inválido']); exit; }
  $stmt = $db->prepare('INSERT INTO clients (name, email, rut, phone) VALUES (?,?,?,?)');
  $stmt->execute([$input['name'], $input['email']?:null, $input['rut']?:null, $input['phone']?:null]);
  echo json_encode(['ok'=>true, 'id'=>$db->lastInsertId()]); exit;
}

if ($action === 'get' && $_GET['id'] ?? null) {
  $stmt = $db->prepare('SELECT * FROM clients WHERE id = ?');
  $stmt->execute([(int)$_GET['id']]);
  echo json_encode($stmt->fetch(PDO::FETCH_ASSOC) ?: ['ok'=>false]); exit;
}

if ($action === 'update') {
  $id = (int)($_POST['id'] ?? 0);
  $name = $_POST['name'] ?? '';
  $email = $_POST['email'] ?? '';
  $rut = $_POST['rut'] ?? '';
  $phone = $_POST['phone'] ?? '';
  if (!$id || !$name) { echo json_encode(['ok'=>false,'error'=>'Datos inválidos']); exit; }
  if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) { echo json_encode(['ok'=>false,'error'=>'Email inválido']); exit; }
  if ($rut && !preg_match('/^[0-9]{1,2}\\.[0-9]{3}\\.[0-9]{3}[-][0-9kK]{1}$/', str_replace('.', '', $rut))) { echo json_encode(['ok'=>false,'error'=>'RUT inválido']); exit; }
  $db->prepare('UPDATE clients SET name=?, email=?, rut=?, phone=? WHERE id=?')
    ->execute([$name, $email?:null, $rut?:null, $phone?:null, $id]);
  echo json_encode(['ok'=>true]); exit;
}

if ($action === 'delete' && $_POST['id'] ?? null) {
  $id = (int)$_POST['id'];
  $db->prepare('UPDATE projects SET client_id = NULL WHERE client_id = ?')->execute([$id]);
  $db->prepare('DELETE FROM clients WHERE id = ?')->execute([$id]);
  echo json_encode(['ok'=>true]); exit;
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

if ($action === 'reset_password' && $_POST['id'] ?? null) {
  $id = (int)$_POST['id'];
  $pass = $_POST['password'] ?? '';
  if (strlen($pass) < 6) { echo json_encode(['ok'=>false,'error'=>'Mínimo 6 caracteres']); exit; }
  $hash = password_hash($pass, PASSWORD_DEFAULT);
  $db->prepare('UPDATE app_users SET password_hash = ?, force_password_change = 1 WHERE id = ?')->execute([$hash, $id]);
  echo json_encode(['ok'=>true]); exit;
}

echo json_encode(['ok'=>false, 'error'=>'Acción no válida']);
