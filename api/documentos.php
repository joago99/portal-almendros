<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';
session_start();
$userId = $_SESSION['user_id'] ?? null;
$userRole = $_SESSION['user_role'] ?? null;
$staff = in_array($userRole, ['admin','staff']);
if (!$userId) exit;
$db = Database::get();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Client users: force filter by their assigned client
$clientFilter = null;
if ($userRole === 'client') {
  $st = $db->prepare('SELECT client_id FROM app_users WHERE id = ?');
  $st->execute([$userId]);
  $cu = $st->fetch();
  $clientFilter = $cu ? (int)$cu['client_id'] : null;
}

// ─── Backend actions ───
if ($action) {
  header('Content-Type: application/json');
  if (!$staff) { echo json_encode(['ok'=>false,'error'=>'No autorizado']); exit; }
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'upload') {
    $projectId = (int)($_POST['project_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $type = $_POST['type'] ?? 'otro';
    $file = $_FILES['file'] ?? null;
    if (!$projectId || !$title || !$file || $file['error'] !== UPLOAD_ERR_OK) {
      echo json_encode(['ok'=>false,'error'=>'Datos incompletos']); exit;
    }
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $safeName = uniqid('doc_') . '.' . $ext;
    $uploadDir = __DIR__ . '/../uploads/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
    if (!move_uploaded_file($file['tmp_name'], $uploadDir . $safeName)) {
      echo json_encode(['ok'=>false,'error'=>'Error al guardar']); exit;
    }
    $db->prepare('INSERT INTO documents (project_id, type, title, file_path, uploaded_by) VALUES (?,?,?,?,?)')
      ->execute([$projectId, $type, $title, $safeName, $userId]);
    echo json_encode(['ok'=>true, 'id'=>$db->lastInsertId()]); exit;
  }
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'bulk_upload') {
    echo json_encode(['ok'=>false,'error'=>'Función no disponible aún']); exit;
  }
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete') {
    $id = (int)$_POST['id'];
    $f = $db->prepare('SELECT file_path FROM documents WHERE id = ?');
    $f->execute([$id]); $doc = $f->fetch();
    if ($doc) { $p = __DIR__ . '/../uploads/' . $doc['file_path']; if (file_exists($p)) unlink($p); }
    $db->prepare('DELETE FROM documents WHERE id = ?')->execute([$id]);
    echo json_encode(['ok'=>true]); exit;
  }
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete_multi') {
    $ids = array_map('intval', explode(',', $_POST['ids'] ?? ''));
    foreach ($ids as $id) {
      $f = $db->prepare('SELECT file_path FROM documents WHERE id = ?');
      $f->execute([$id]); $doc = $f->fetch();
      if ($doc) { $p = __DIR__ . '/../uploads/' . $doc['file_path']; if (file_exists($p)) unlink($p); }
    }
    $db->query('DELETE FROM documents WHERE id IN (' . implode(',', $ids) . ')');
    echo json_encode(['ok'=>true]); exit;
  }
  echo json_encode(['ok'=>false,'error'=>'Acción no válida']); exit;
}

// ─── HTML view ───
$docProject = $_GET['project_id'] ?? null;
$docClient = $_GET['client_id'] ?? null;
$projects = $db->query('SELECT p.id, p.name, COALESCE(c.name,"") as client FROM projects p LEFT JOIN clients c ON c.id = p.client_id ORDER BY p.name')->fetchAll();
$clients = $db->query('SELECT id, name FROM clients ORDER BY name')->fetchAll();
$where = $docProject ? 'WHERE d.project_id = '.(int)$docProject : ($docClient ? 'WHERE p.client_id = '.(int)$docClient : ($clientFilter ? 'WHERE p.client_id = '.(int)$clientFilter : ''));
$docs = $db->query('SELECT d.*, p.name as proyecto, c.name as cliente, u.name as uploader FROM documents d LEFT JOIN projects p ON p.id = d.project_id LEFT JOIN clients c ON c.id = p.client_id LEFT JOIN app_users u ON u.id = d.uploaded_by '.$where.' ORDER BY d.uploaded_at DESC LIMIT 100')->fetchAll();
?>
