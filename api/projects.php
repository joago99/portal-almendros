<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');
session_start();
$userId = $_SESSION['user_id'] ?? null;
$userRole = $_SESSION['user_role'] ?? null;
if (!$userId || !in_array($userRole, ['admin','staff'])) { echo json_encode(['ok'=>false,'error'=>'No autorizado']); exit; }
$db = Database::get();
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$in = json_decode(file_get_contents('php://input'), true) ?: [];

// GET list for app (JSON) used by JS when editing
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'list_json') {
  $rows = $db->query('SELECT p.*, c.name as client_name FROM projects p LEFT JOIN clients c ON c.id = p.client_id ORDER BY p.created_at DESC')->fetchAll();
  echo json_encode(['ok'=>true,'data'=>$rows]); exit;
}

// GET single project
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'get' && ($_GET['id'] ?? null)) {
  $st=$db->prepare('SELECT p.*, c.name as client_name FROM projects p LEFT JOIN clients c ON c.id = p.client_id WHERE p.id=?');
  $st->execute([(int)$_GET['id']]); $r=$st->fetch();
  echo json_encode(['ok'=>true,'data'=>$r]); exit;
}

// GET clients list for selects
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'clients_list') {
  $rows = $db->query('SELECT id, name FROM clients ORDER BY name')->fetchAll();
  echo json_encode(['ok'=>true,'clients'=>$rows]); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'update' && $_POST['id'] ?? null) {
  $id = (int)$_POST['id'];
  $fields = [];
  $params = [];
  foreach (['client_id','name','address','status','start_date','end_date_estimated'] as $k) {
    if (isset($_POST[$k])) { $fields[] = "$k = ?"; $params[] = $_POST[$k]; }
  }
  if (isset($_POST['budget_clp'])) { $fields[] = "budget_clp = ?"; $params[] = $_POST['budget_clp']; }
  if ($fields) {
    // presupuesto anterior, para historial
    $old = $db->prepare('SELECT budget_clp FROM projects WHERE id = ?');
    $old->execute([$id]); $prev = $old->fetch();
    $params[] = $id;
    $db->prepare('UPDATE projects SET '.implode(',',$fields).' WHERE id = ?')->execute($params);
    if (isset($_POST['budget_clp']) && $prev && $prev['budget_clp'] != $_POST['budget_clp']) {
      $db->prepare('UPDATE projects SET budget_history = COALESCE(budget_history,\' \') || ? WHERE id = ?')
        ->execute([json_encode(['from'=>$prev['budget_clp'],'to'=>$_POST['budget_clp'],'at'=>date('c'),'by'=>$userId])."\n", $id]);
    }
  }
  echo json_encode(['ok'=>true]); exit;
}

if ($action === 'doc_counts' && $_GET['id'] ?? null) {
  $id = (int)$_GET['id'];
  $out = [];
  foreach (['presupuesto','plano','legal','avance','otro'] as $t) {
    $st = $db->prepare('SELECT COUNT(*) FROM documents WHERE project_id = ? AND type = ?');
    $st->execute([$id, $t]); $out[$t] = (int)$st->fetchColumn();
  }
  echo json_encode(['ok'=>true, 'counts'=>$out]); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'create') {
  $name = trim($in['name'] ?? '');
  $status = $in['status'] ?? 'activo';
  $clientId = isset($in['client_id']) ? (int)$in['client_id'] : null;
  $budget = isset($in['budget_clp']) ? (float)$in['budget_clp'] : 0;
  $address = $in['address'] ?? null;
  $start = $in['start_date'] ?? null;
  $end = $in['end_date_estimated'] ?? null;
  if (!$name) { echo json_encode(['ok'=>false,'error'=>'Nombre requerido']); exit; }
  $db->prepare('INSERT INTO projects (client_id,name,status,budget_clp,address,start_date,end_date_estimated) VALUES (?,?,?,?,?,?,?)')
    ->execute([$clientId,$name,$status,$budget,$address,$start,$end]);
  echo json_encode(['ok'=>true,'id'=>$db->lastInsertId()]); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'change_status' && $_POST['id'] ?? null) {
  $id = (int)$_POST['id']; $status = $_POST['status'] ?? 'activo';
  $db->prepare('UPDATE projects SET status = ? WHERE id = ?')->execute([$status,$id]);
  echo json_encode(['ok'=>true]); exit;
}

echo json_encode(['ok'=>false,'error'=>'Acción no válida']);
