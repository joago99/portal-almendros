<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';
$auth = require_auth();
$userId = $auth['user_id'];
$userRole = $auth['role'];
$staff = in_array($userRole, ['admin','staff']);
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
    if (!$ids) { echo json_encode(['ok'=>false,'error'=>'IDs inválidos']); exit; }
    foreach ($ids as $id) {
      $f = $db->prepare('SELECT file_path FROM documents WHERE id = ?');
      $f->execute([$id]); $doc = $f->fetch();
      if ($doc) { $p = __DIR__ . '/../uploads/' . $doc['file_path']; if (file_exists($p)) unlink($p); }
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $db->prepare("DELETE FROM documents WHERE id IN ($placeholders)");
    $stmt->execute($ids);
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
$fileBase = '/uploads/';
?><style>
.doc-wrap{display:flex;flex-direction:column;gap:1rem;}
.doc-controls{display:flex;gap:.75rem;flex-wrap:wrap;align-items:flex-end;}
.doc-controls label{font-size:.8rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;}
.doc-controls select,.doc-controls input{padding:.45rem .7rem;border:1px solid #cbd5e1;border-radius:8px;font-size:.85rem;font-family:inherit;background:#fff;}
.doc-controls select{min-width:260px;}
.btn-primary{background:#0d9488;color:#fff;border-color:#0d9488;padding:.45rem 1rem;border-radius:8px;font-size:.85rem;font-weight:500;cursor:pointer;border:1px solid transparent;}
.btn-primary:hover{background:#0f766e;}
.btn-outline{background:#fff;color:#475569;border:1px solid #cbd5e1;padding:.35rem .7rem;border-radius:8px;font-size:.8rem;cursor:pointer;}
.btn-outline:hover{background:#f8fafc;}
.btn-danger-text{background:none;color:#dc2626;border:1px solid #fecaca;padding:.35rem .7rem;border-radius:8px;font-size:.8rem;cursor:pointer;}
.btn-danger-text:hover{background:#fef2f2;}
.doc-card{background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:.75rem 1rem;display:flex;align-items:center;justify-content:space-between;gap:1rem;}
.doc-card a{color:#0d9488;font-weight:500;text-decoration:none;}
.doc-card a:hover{text-decoration:underline;}
.doc-meta{font-size:.8rem;color:#64748b;}
.empty-state{text-align:center;padding:2.5rem 1rem;color:#94a3b8;}
</style>
<div class="doc-wrap">
  <div class="doc-controls">
    <div>
      <label>Proyecto</label>
      <select id="docProyecto" onchange="loadDocs()">
        <option value="">— Todos —</option>
        <?php foreach ($projects as $p): ?>
          <option value="<?= $p['id'] ?>" <?= $docProject == $p['id'] ? 'selected' : ''?>>
            <?= htmlspecialchars($p['name']) ?> (<?= htmlspecialchars($p['client']) ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php if ($staff): ?>
    <button class="btn-primary" onclick="openDocModal()">+ Subir documento</button>
    <?php endif; ?>
  </div>
  <div id="docsContainer">
    <?php if (!$docs): ?>
      <div class="empty-state"><p>Sin documentos para el filtro seleccionado</p></div>
    <?php else: ?>
      <?php foreach ($docs as $d): ?>
      <div class="doc-card">
        <div>
          <a href="<?= $fileBase . htmlspecialchars($d['file_path']) ?>" target="_blank" rel="noopener">📄 <?= htmlspecialchars($d['title']) ?></a>
          <div class="doc-meta"><?= htmlspecialchars($d['tipo'] ?? 'documento') ?> · <?= htmlspecialchars($d['proyecto'] ?? '') ?> · <?= htmlspecialchars($d['uploader'] ?? '') ?> · <?= $d['uploaded_at'] ?></div>
        </div>
        <?php if ($staff): ?>
        <button class="btn-danger-text" onclick="deleteDoc(<?= $d['id'] ?>)">Eliminar</button>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>
<script>
function loadDocs(){
  const pid = document.getElementById('docProyecto').value;
  const sep = location.search.includes('client_id') ? '&' : '?';
  const url = '/api/documentos.php' + (pid ? '?project_id=' + encodeURIComponent(pid) : '') + (location.search.includes('client_id=') ? sep + location.search.split('?')[1] : '');
  fetch(url).then(r=>r.text()).then(html=>{ document.getElementById('docsContainer').innerHTML = html; });
}
function openDocModal(){
  const opts = document.getElementById('docProyecto')?.value || '';
  const items = <?= json_encode($projects) ?>;
  const optsHtml = items.map(p=>`<option value="${p.id}">${p.client} - ${p.name}</option>`).join('');
  openModal(`<h3>Subir documento</h3>
    <form id="docForm" onsubmit="return saveDoc(this)">
      <label>Proyecto</label><select name="project_id" required>${optsHtml}</select>
      <label>Tipo</label><select name="type">
        <option value="plano">Plano</option>
        <option value="presupuesto">Presupuesto</option>
        <option value="avance">Avance</option>
        <option value="legal">Legal / Permiso</option>
        <option value="otro">Otro</option>
      </select>
      <label>Título</label><input name="title" placeholder="Descripción corta" required>
      <label>Archivo</label><input type="file" name="file" required>
      <p style="font-size:.75rem;color:#94a3b8">Formatos permitidos: PDF, JPG, PNG, DOCX. Máx ~10MB.</p>
      <div class="modal-actions">
        <button type="button" class="btn-outline" onclick="closeModal()">Cancelar</button>
        <button type="submit" class="btn-primary">Subir</button>
      </div>
    </form>`);
}
function saveDoc(form){
  const fd = new FormData(form);
  fetch('/api/documentos.php?action=upload', {method:'POST', body:fd})
    .then(r=>r.json()).then(d=>{
      if(d.ok){ showToast('Documento subido ✅'); closeModal(); loadDocs(); return false; }
      showToast(d.error || 'Error', 'error'); return false;
    });
  return false;
}
function deleteDoc(id){
  if(!confirm('¿Eliminar este documento?')) return;
  fetch('/api/documentos.php?action=delete', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: new URLSearchParams({id}).toString()})
    .then(r=>r.json()).then(d=>{ if(d.ok){ showToast('Documento eliminado'); loadDocs(); } else showToast(d.error,'error'); });
}
</script>
