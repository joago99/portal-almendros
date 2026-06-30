<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';
session_start();
$userId = $_SESSION['user_id'] ?? null;
$userRole = $_SESSION['user_role'] ?? null;
$staff = in_array($userRole, ['admin','staff']);
if (!$userId) exit;
$db = Database::get();

$search = $_GET['q'] ?? '';
$docProject = $_GET['project_id'] ?? null;
$docClient = $_GET['client_id'] ?? null;

// Listar proyectos para filtro
$projects = $db->query('SELECT p.id, p.name, c.name as client FROM projects p JOIN clients c ON c.id = p.client_id ORDER BY p.name')->fetchAll();
// Filtrar
$where = $docProject ? 'WHERE d.project_id = '.(int)$docProject : ($docClient ? 'WHERE p.client_id = '.(int)$docClient : '');
$docs = $db->query('SELECT d.id, d.type, d.title, d.file_path, d.uploaded_at, p.name as proyecto, c.name as cliente, u.name as uploader
    FROM documents d JOIN projects p ON p.id = d.project_id JOIN clients c ON c.id = p.client_id JOIN app_users u ON u.id = d.uploaded_by
    '.$where.' ORDER BY d.uploaded_at DESC LIMIT 100')->fetchAll();
?>
<div class="search-bar">
  <input type="text" id="searchDocs" placeholder="Buscar documento..." value="<?= htmlspecialchars($search) ?>">
  <select id="filterProyecto">
    <option value="">Por proyecto...</option>
    <?php foreach ($projects as $p): ?>
    <option value="<?= $p['id'] ?>" <?= $docProject == $p['id']?'selected':'' ?>><?= htmlspecialchars($p['client'].' - '.$p['name']) ?></option>
    <?php endforeach; ?>
  </select>
  <?php if ($staff): ?>
    <button class="btn btn-primary" onclick="cargarFormDocumento()">+ Subir documento</button>
  <?php endif; ?>
  <?php if ($staff): ?>
    <button class="btn btn-outline" onclick="abrirMultiSubida()">📎 Subida múltiple</button>
    <button class="btn btn-outline" onclick="toggleMultiSelect()" id="btnMulti">☑️ Seleccionar</button>
  <?php endif; ?>
</div>

<div id="multiBar" style="display:none;margin-bottom:0.75rem;background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:0.75rem">
  <strong style="font-size:0.85rem">Selección múltiple</strong>
  <button class="btn btn-outline btn-sm" onclick="seleccionarVisibles(true)">Marcar visibles</button>
  <button class="btn btn-outline btn-sm" onclick="seleccionarVisibles(false)">Desmarcar</button>
  <button class="btn btn-danger btn-sm" onclick="eliminarSeleccionados()">🗑 Eliminar seleccionados</button>
  <span id="countSel" style="font-size:0.8rem;color:#64748b;margin-left:0.5rem">0</span>
</div>

<div class="doc-grid">
<?php foreach ($docs as $d): ?>
<label class="doc-card multi-wrap" style="position:relative" data-id="<?= $d['id'] ?>" data-search="<?= strtolower(htmlspecialchars($d['title'].' '.$d['proyecto'].' '.$d['cliente'])) ?>">
  <input type="checkbox" class="multi-check" value="<?= $d['id'] ?>" style="position:absolute;left:6px;top:6px;display:none" onchange="actualizarCountSel()">
  <div class="doc-icon">📄</div>
  <div class="doc-title"><?= htmlspecialchars($d['title']) ?></div>
  <div style="font-size:0.7rem;color:#64748b;margin-top:2px"><?= htmlspecialchars($d['proyecto']) ?></div>
  <div style="font-size:0.7rem;color:#64748b;margin-top:2px"><?= $d['uploaded_at'] ?></div>
  <a href="/uploads/<?= $d['file_path'] ?>" target="_blank" class="btn btn-outline btn-sm" style="display:block;margin-top:0.5rem">Abrir</a>
  <?php if ($staff): ?>
  <button class="btn btn-outline btn-sm" onclick="eliminarDoc(<?= $d['id'] ?>)">Eliminar</button>
  <?php endif; ?>
</label>
<?php endforeach; ?>
</div>

<div class="modal-overlay" id="modalOverlay">
  <div class="modal-box" id="modalContent"><div id="modalBody"></div></div>
</div>

<script>
document.getElementById('searchDocs')?.addEventListener('input', function() {
  const q = this.value.toLowerCase();
  document.querySelectorAll('.doc-card').forEach(r => {
    r.style.display = r.dataset.search.includes(q) ? '' : 'none';
  });
});
document.getElementById('filterProyecto')?.addEventListener('change', function() {
  const url = '/api/documentos.php?project_id=' + encodeURIComponent(this.value);
  loadTab('documentos&project_id=' + encodeURIComponent(this.value));
});
function cargarFormDocumento() {
  const opts = [
    <?php foreach ($projects as $p): ?>
    {v:'<?= $p['id'] ?>', l:'<?= htmlspecialchars($p['client'].' - '.$p['name']) ?>'},
    <?php endforeach; ?>
  ].map(x => '<option value="'+x.v+'">'+x.l+'</option>').join('');
  openModal('<h3>Subir documento</h3><form id="docForm" enctype="multipart/form-data"><label>Proyecto</label><select name="project_id" required>'+opts+'</select><label>Título</label><input name="title" required><label>Tipo</label><select name="type"><option value="presupuesto">Presupuesto</option><option value="avance">Avance</option><option value="plano">Plano</option><option value="legal">Legal</option><option value="otro">Otro</option></select><label>Archivo</label><input name="file" type="file" required multiple><div class="modal-actions"><button type="button" class="btn btn-outline" onclick="closeModal()">Cancelar</button><button type="submit" class="btn btn-primary">Subir</button></div></form><script>document.getElementById("docForm")?.addEventListener("submit", async (e) => { e.preventDefault(); const fd = new FormData(e.target); const res = await fetch("/api/documentos.php?action=upload", {method:"POST",body:fd}); const d = await res.json(); if(d.ok){ showToast("Documento subido"); closeModal(); loadTab("documentos"); } else showToast(d.error,"error"); });<\/script>');
}
function abrirMultiSubida() {
  const opts = [
    <?php foreach ($projects as $p): ?>
    {v:'<?= $p['id'] ?>', l:'<?= htmlspecialchars($p['client'].' - '.$p['name']) ?>'},
    <?php endforeach; ?>
  ].map(x => '<option value="'+x.v+'">'+x.l+'</option>').join('');
  openModal('<h3>Subida múltiple</h3><form id="multiDocForm" enctype="multipart/form-data"><label>Proyecto</label><select name="project_id" required>'+opts+'</select><label>Tipo</label><select name="type"><option value="presupuesto">Presupuesto</option><option value="avance">Avance</option><option value="plano">Plano</option><option value="legal">Legal</option><option value="otro">Otro</option></select><label>Archivos</label><input name="files[]" type="file" required multiple><div class="modal-actions"><button type="button" class="btn btn-outline" onclick="closeModal()">Cancelar</button><button type="submit" class="btn btn-primary">Subir</button></div></form><script>document.getElementById("multiDocForm")?.addEventListener("submit", async (e) => { e.preventDefault(); const fd = new FormData(e.target); const res = await fetch("/api/documentos.php?action=bulk_upload", {method:"POST",body:fd}); const d = await res.json(); if(d.ok){ showToast("Documentos subidos"); closeModal(); loadTab("documentos"); } else showToast(d.error,"error"); });<\/script>');
}
function toggleMultiSelect() {
  const bar = document.getElementById('multiBar');
  const checks = document.querySelectorAll('.multi-check');
  if (bar.style.display === 'none') {
    bar.style.display = 'block'; checks.forEach(c => c.style.display = 'block'); document.getElementById('btnMulti').textContent = '☑️ Cancelar selección';
  } else {
    bar.style.display = 'none'; checks.forEach(c => { c.checked = false; c.style.display = 'none'; }); actualizarCountSel();
  }
}
function seleccionarVisibles(flag) {
  document.querySelectorAll('.multi-check').forEach(c => c.checked = flag);
  actualizarCountSel();
}
function actualizarCountSel() {
  const n = document.querySelectorAll('.multi-check:checked').length; document.getElementById('countSel').textContent = n;
}
async function eliminarSeleccionados() {
  const ids = [...document.querySelectorAll('.multi-check:checked')].map(el => el.value);
  if (!ids.length) return;
  if (!confirm('¿Borrar ' + ids.length + ' documentos?')) return;
  await fetch('/api/documentos.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: new URLSearchParams({action:'delete_multi', ids:ids.join(',')})});
  showToast('Documentos eliminados'); loadTab('documentos');
}
async function eliminarDoc(id) {
  if (!confirm('¿Borrar documento?')) return;
  await fetch('/api/documentos.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: new URLSearchParams({action:'delete',id:id})});
  showToast('Documento eliminado'); loadTab('documentos');
}
</script>
