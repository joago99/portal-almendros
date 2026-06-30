<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';
session_start();
$userId = $_SESSION['user_id'] ?? null;
$userRole = $_SESSION['user_role'] ?? null;
$staff = in_array($userRole, ['admin','staff']);
if (!$userId) exit;
$db = Database::get();

$action = $_GET['action'] ?? '';

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
$where = $docProject ? 'WHERE d.project_id = '.(int)$docProject : ($docClient ? 'WHERE p.client_id = '.(int)$docClient : '');
$docs = $db->query('SELECT d.*, p.name as proyecto, c.name as cliente, u.name as uploader FROM documents d JOIN projects p ON p.id = d.project_id JOIN clients c ON c.id = p.client_id JOIN app_users u ON u.id = d.uploaded_by '.$where.' ORDER BY d.uploaded_at DESC LIMIT 100')->fetchAll();
?>
<script>
const DOC_PROJECTS = <?= json_encode($projects) ?>;
const DOC_CLIENTS = <?= json_encode($clients) ?>;
</script>
<div class="search-bar">
  <input type="text" id="searchDocs" placeholder="Buscar documento..." oninput="filtrarDocsTexto()">
  <select id="filterDocProyecto" onchange="filtrarDocs()">
    <option value="">Por proyecto...</option>
    <?php foreach ($projects as $p): ?>
    <option value="<?= $p['id'] ?>" <?=$docProject==$p['id']?'selected':''?>><?= htmlspecialchars($p['client'].' - '.$p['name']) ?></option>
    <?php endforeach; ?>
  </select>
  <select id="filterDocCliente" onchange="filtrarDocs()">
    <option value="">Por cliente...</option>
    <?php foreach ($clients as $c): ?>
    <option value="<?= $c['id'] ?>" <?=$docClient==$c['id']?'selected':''?>><?= htmlspecialchars($c['name']) ?></option>
    <?php endforeach; ?>
  </select>
  <?php if ($staff): ?>
  <button class="btn btn-primary" onclick="subirDocumentoModal()">+ Subir documento</button>
  <button class="btn btn-outline" onclick="toggleMultiSelect()">☑️ Seleccionar</button>
  <?php endif; ?>
</div>

<div id="multiBar" style="display:none;margin-bottom:0.75rem;background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:0.75rem">
  <strong style="font-size:0.85rem">Selección múltiple</strong>
  <button class="btn btn-outline btn-sm" onclick="seleccionarVisibles(true)">Marcar todos</button>
  <button class="btn btn-outline btn-sm" onclick="seleccionarVisibles(false)">Desmarcar</button>
  <button class="btn btn-danger btn-sm" onclick="eliminarSeleccionados()">🗑 Eliminar seleccionados</button>
  <span id="countSel" style="font-size:0.8rem;color:#64748b;margin-left:0.5rem">0</span>
</div>

<div class="doc-grid" id="docGrid">
<?php foreach ($docs as $d): ?>
<label class="doc-card multi-wrap" data-id="<?= $d['id'] ?>" data-search="<?= strtolower(htmlspecialchars($d['title'].' '.$d['proyecto'].' '.$d['cliente'])) ?>">
  <input type="checkbox" class="multi-check" value="<?= $d['id'] ?>" style="position:absolute;left:6px;top:6px;display:none" onchange="actualizarCountSel()">
  <div class="doc-icon">📄</div>
  <div class="doc-title"><?= htmlspecialchars($d['title']) ?></div>
  <div style="font-size:0.7rem;color:#64748b"><?= htmlspecialchars($d['proyecto']) ?></div>
  <a href="/uploads/<?= $d['file_path'] ?>" target="_blank" class="btn btn-outline btn-sm" style="display:block;margin-top:0.5rem">Abrir</a>
  <?php if ($staff): ?>
  <button class="btn btn-outline btn-sm" style="margin-top:0.25rem;width:100%" onclick="eliminarDocumento(<?= $d['id'] ?>)">Eliminar</button>
  <?php endif; ?>
</label>
<?php endforeach; ?>
</div>

<script>
function filtrarDocsTexto() {
  const q = document.getElementById('searchDocs').value.toLowerCase();
  document.querySelectorAll('.doc-card').forEach(r => r.style.display = r.dataset.search.includes(q) ? '' : 'none');
}
function filtrarDocs() {
  const proj = document.getElementById('filterDocProyecto').value;
  const cli = document.getElementById('filterDocCliente').value;
  const params = new URLSearchParams();
  if (proj) params.set('project_id', proj);
  if (cli) params.set('client_id', cli);
  loadTab('documentos&' + params.toString());
}
function toggleMultiSelect() {
  const bar = document.getElementById('multiBar');
  const checks = document.querySelectorAll('.multi-check');
  if (bar.style.display === 'none') {
    bar.style.display = 'block'; checks.forEach(c => c.style.display = 'block');
  } else {
    bar.style.display = 'none'; checks.forEach(c => { c.checked = false; c.style.display = 'none'; }); actualizarCountSel();
  }
}
function seleccionarVisibles(flag) {
  document.querySelectorAll('.multi-check').forEach(c => c.checked = flag);
  actualizarCountSel();
}
function actualizarCountSel() {
  document.getElementById('countSel').textContent = document.querySelectorAll('.multi-check:checked').length;
}
async function eliminarSeleccionados() {
  const ids = [...document.querySelectorAll('.multi-check:checked')].map(el => el.value);
  if (!ids.length) return;
  if (!confirm('¿Borrar ' + ids.length + ' documentos?')) return;
  await fetch('/api/documentos.php', {method:'POST', body: new URLSearchParams({action:'delete_multi', ids:ids.join(',')}).toString(), headers: {'Content-Type':'application/x-www-form-urlencoded'}});
  showToast('Documentos eliminados'); loadTab('documentos');
}
async function eliminarDocumento(id) {
  if (!confirm('¿Borrar este documento?')) return;
  await fetch('/api/documentos.php', {method:'POST', body: new URLSearchParams({action:'delete',id}).toString(), headers: {'Content-Type':'application/x-www-form-urlencoded'}});
  showToast('Documento eliminado'); loadTab('documentos');
}
function subirDocumentoModal() {
  const projOpts = DOC_PROJECTS.map(p => `<option value="${p.id}">${p.client} - ${p.name}</option>`).join('');
  openModal(`<h3>Subir documento</h3>
    <form id="upDocForm" enctype="multipart/form-data" onsubmit="return subirDocumento(this)">
      <label>Proyecto</label><select name="project_id" required>${projOpts}</select>
      <label>Título</label><input name="title" required>
      <label>Tipo</label><select name="type"><option value="presupuesto">Presupuesto</option><option value="plano">Plano</option><option value="legal">Legal</option><option value="avance">Avance</option><option value="otro">Otro</option></select>
      <label>Archivo</label><input name="file" type="file" required>
      <div class="modal-actions">
        <button type="button" class="btn btn-outline" onclick="closeModal()">Cancelar</button>
        <button type="submit" class="btn btn-primary">Subir</button>
      </div>
    </form>`);
}
async function subirDocumento(form) {
  const fd = new FormData(form);
  const res = await fetch('/api/documentos.php?action=upload', {method:'POST', body: fd});
  const d = await res.json();
  if (d.ok) { showToast('Documento subido ✅'); closeModal(); loadTab('documentos'); return false; }
  else { showToast(d.error, 'error'); return false; }
}
</script>
