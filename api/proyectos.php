<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';
session_start();
$userId = $_SESSION['user_id'] ?? null;
$userRole = $_SESSION['user_role'] ?? null;
$isStaff = in_array($userRole, ['admin','staff']);
if (!$userId) exit;
$db = Database::get();

// Client users: force filter by their assigned client
if ($userRole === 'client') {
  $st = $db->prepare('SELECT client_id FROM app_users WHERE id = ?');
  $st->execute([$userId]);
  $cu = $st->fetch();
  $clientFilter = $cu ? (int)$cu['client_id'] : null;
} else {
  $clientFilter = $_GET['client_id'] ?? null;
}

$action = $_GET['action'] ?? '';

if ($action === 'get' && $_GET['id'] ?? null) {
  header('Content-Type: application/json');
  $stmt = $db->prepare('SELECT p.*, c.name as client_name FROM projects p LEFT JOIN clients c ON c.id = p.client_id WHERE p.id = ?');
  $stmt->execute([(int)$_GET['id']]);
  $proj = $stmt->fetch(PDO::FETCH_ASSOC);
  echo json_encode($proj ?: ['ok'=>false]); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'update' && $_POST['id'] ?? null) {
  header('Content-Type: application/json');
  $id = (int)$_POST['id'];
  $fields = []; $params = [];
  foreach (['client_id','name','address','status'] as $k) {
    if (isset($_POST[$k])) { $fields[] = "$k = ?"; $params[] = $_POST[$k]; }
  }
  if (isset($_POST['budget_clp'])) {
    $fields[] = "budget_clp = ?"; $params[] = (float)$_POST['budget_clp'];
    $old = $db->prepare('SELECT budget_clp FROM projects WHERE id = ?');
    $old->execute([$id]); $prev = $old->fetch();
    if ($prev && $prev['budget_clp'] != $_POST['budget_clp']) {
      $db->prepare('UPDATE projects SET budget_history = COALESCE(budget_history,\'\') || ? WHERE id = ?')
        ->execute([json_encode(['from'=>$prev['budget_clp'],'to'=>(float)$_POST['budget_clp'],'at'=>date('c'),'by'=>$userId])."\n", $id]);
    }
  }
  if ($fields) { $params[] = $id; $db->prepare('UPDATE projects SET '.implode(',',$fields).' WHERE id = ?')->execute($params); }
  echo json_encode(['ok'=>true]); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'create') {
  header('Content-Type: application/json');
  $name = trim($_POST['name'] ?? '');
  $clientId = isset($_POST['client_id']) && $_POST['client_id'] !== '' ? (int)$_POST['client_id'] : null;
  $budget = isset($_POST['budget_clp']) ? (float)$_POST['budget_clp'] : 0;
  $status = $_POST['status'] ?? 'activo';
  $address = $_POST['address'] ?? null;
  if (!$name) { echo json_encode(['ok'=>false,'error'=>'Nombre requerido']); exit; }
  $db->prepare('INSERT INTO projects (client_id,name,status,budget_clp,address) VALUES (?,?,?,?,?)')
    ->execute([$clientId,$name,$status,$budget,$address]);
  echo json_encode(['ok'=>true,'id'=>$db->lastInsertId()]); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'change_status' && ($_POST['id'] ?? null)) {
  header('Content-Type: application/json');
  $id = (int)$_POST['id']; $status = $_POST['status'] ?? 'activo';
  $db->prepare('UPDATE projects SET status = ? WHERE id = ?')->execute([$status,$id]);
  echo json_encode(['ok'=>true]); exit;
}

// ─── HTML view ───
$clientFilter = $_GET['client_id'] ?? null;
$search = $_GET['q'] ?? '';
$statusFilter = $_GET['estado'] ?? '';

if ($clientFilter) {
    $stmt = $db->prepare('SELECT p.*, c.name as client_name FROM projects p LEFT JOIN clients c ON c.id = p.client_id WHERE p.client_id = ? ORDER BY p.created_at DESC');
    $stmt->execute([$clientFilter]);
} else {
    $stmt = $db->query('SELECT p.*, c.name as client_name FROM projects p LEFT JOIN clients c ON c.id = p.client_id ORDER BY p.created_at DESC');
}
$projects = $stmt->fetchAll();
$clientes = $db->query('SELECT id, name FROM clients ORDER BY name')->fetchAll();
?>
<script>
const CLIENTES = <?= json_encode($clientes) ?>;
</script>
<div class="search-bar">
  <input type="text" id="searchProyectos" placeholder="Buscar proyecto..." value="<?= htmlspecialchars($search) ?>">
  <select id="filterEstado" onchange="filtrarProyectos()">
    <option value="">Todos los estados</option>
    <option value="activo" <?=$statusFilter==='activo'?'selected':''?>>Activo</option>
    <option value="pausado" <?=$statusFilter==='pausado'?'selected':''?>>Pausado</option>
    <option value="finalizado" <?=$statusFilter==='finalizado'?'selected':''?>>Finalizado</option>
  </select>
  <?php if ($isStaff): ?>
  <button class="btn btn-primary" onclick="nuevoProyecto()">+ Nuevo proyecto</button>
  <?php endif; ?>
</div>

<div id="proyectosList">
<?php foreach ($projects as $proj):
  if ($statusFilter && $proj['status'] !== $statusFilter) continue;
  $pag = $db->prepare('SELECT COALESCE(SUM(amount_clp),0) FROM payments WHERE project_id = ? AND status = "pagado"');
  $pag->execute([$proj['id']]); $pagado = $pag->fetchColumn();
  $pen = $db->prepare('SELECT COALESCE(SUM(amount_clp),0) FROM payments WHERE project_id = ? AND status = "pendiente" AND due_date >= date("now")');
  $pen->execute([$proj['id']]); $pendiente = $pen->fetchColumn();
  $atr = $db->prepare('SELECT COALESCE(SUM(amount_clp),0) FROM payments WHERE project_id = ? AND status = "pendiente" AND due_date < date("now")');
  $atr->execute([$proj['id']]); $atrasado = $atr->fetchColumn();
  $st = $db->prepare('SELECT COUNT(*) FROM documents WHERE project_id = ?');
  $st->execute([$proj['id']]); $docsCount = (int)$st->fetchColumn();
  $pct = ($proj['budget_clp'] ?? 0) > 0 ? round(($pagado / $proj['budget_clp']) * 100) : 0;
?>
<div class="card proyecto-card" data-estado="<?= $proj['status'] ?>" data-search="<?= strtolower(htmlspecialchars($proj['name'].' '.$proj['client_name'])) ?>">
  <div class="card-header" style="cursor:pointer" onclick="toggleProyecto('proj-<?= $proj['id'] ?>')">
    <div>
      <h2 style="font-size:1.1rem"><?= htmlspecialchars($proj['name']) ?></h2>
      <span style="font-size:0.8rem;color:#64748b"><?= htmlspecialchars($proj['client_name'] ?? 'Sin cliente') ?> — Estado: <strong><?= $proj['status'] ?></strong></span>
    </div>
    <div style="display:flex;align-items:center;gap:0.75rem">
      <span class="status <?= $proj['status'] ?>"><?= $proj['status'] ?></span>
      <span style="font-size:0.8rem;color:#64748b"><?= $pct ?>%</span>
      <span style="font-size:0.8rem">▼</span>
    </div>
  </div>
  <div id="proj-<?= $proj['id'] ?>" style="display:none;margin-top:1rem">
    <div class="stats-row" style="margin-bottom:1rem;grid-template-columns:repeat(4,1fr)">
      <div class="stat-box" style="padding:0.5rem 1rem"><div class="num" style="font-size:1rem;color:#16a34a">$<?= number_format($pagado,0,',','.') ?></div><div class="label">Pagado</div></div>
      <div class="stat-box" style="padding:0.5rem 1rem"><div class="num" style="font-size:1rem;color:#ca8a04">$<?= number_format($pendiente,0,',','.') ?></div><div class="label">Pendiente</div></div>
      <div class="stat-box" style="padding:0.5rem 1rem"><div class="num" style="font-size:1rem;color:#dc2626">$<?= number_format($atrasado,0,',','.') ?></div><div class="label">Atrasado</div></div>
      <div class="stat-box" style="padding:0.5rem 1rem"><div class="num" style="font-size:1rem;color:#2563eb"><?= $docsCount ?> 📄</div><div class="label">Documentos</div></div>
    </div>
    <div class="search-bar">
      <strong>Detalle</strong>
      <?php if ($isStaff): ?>
      <button class="btn btn-primary btn-sm" onclick="editarProyecto(<?= $proj['id'] ?>)">Editar</button>
      <button class="btn btn-outline btn-sm" onclick="cambiarEstadoProyecto(<?= $proj['id'] ?>)">Cambiar estado</button>
      <button class="btn btn-danger btn-sm" onclick="eliminarProyecto(<?= $proj['id'] ?>)">Eliminar</button>
      <?php endif; ?>
    </div>
    <div class="card">
      <p><strong>Nombre:</strong> <?= htmlspecialchars($proj['name']) ?></p>
      <p><strong>Cliente:</strong> <?= htmlspecialchars($proj['client_name'] ?? '—') ?></p>
      <p><strong>Dirección:</strong> <?= htmlspecialchars($proj['address'] ?? '—') ?></p>
      <p><strong>Presupuesto:</strong> $<?= number_format($proj['budget_clp'] ?? 0,0,',','.') ?></p>
      <p><strong>Creado:</strong> <?= $proj['created_at'] ?></p>
      <?php if ($proj['budget_history'] ?? null): ?>
      <p style="font-size:0.8rem;color:#64748b"><strong>Historial presupuesto:</strong> <?= nl2br(htmlspecialchars($proj['budget_history'])) ?></p>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php endforeach; ?>
</div>

<script>
function toggleProyecto(id) {
  const el = document.getElementById(id);
  if (el) el.style.display = el.style.display === 'none' ? 'block' : 'none';
}
document.getElementById('searchProyectos')?.addEventListener('input', function() {
  const q = this.value.toLowerCase();
  document.querySelectorAll('.proyecto-card').forEach(c => { c.style.display = c.dataset.search.includes(q) ? '' : 'none'; });
});
function filtrarProyectos() {
  const s = document.getElementById('filterEstado').value;
  document.querySelectorAll('.proyecto-card').forEach(c => c.style.display = (!s || c.dataset.estado === s) ? '' : 'none');
}

function nuevoProyecto() {
  const opts = CLIENTES.map(c => `<option value="${c.id}">${c.name}</option>`).join('');
  openModal(`<h3>Nuevo proyecto</h3>
    <form id="projForm" onsubmit="return crearProyecto(this)">
      <label>Nombre del proyecto</label><input name="name" required>
      <label>Cliente / Solicitante</label><select name="client_id">${opts}</select>
      <label>Presupuesto CLP $</label><input name="budget_clp" type="number">
      <label>Dirección</label><input name="address">
      <div class="modal-actions">
        <button type="button" class="btn btn-outline" onclick="closeModal()">Cancelar</button>
        <button type="submit" class="btn btn-primary">Crear</button>
      </div>
    </form>`);
}

async function crearProyecto(form) {
  const fd = new FormData(form);
  fd.set('action', 'create');
  fd.set('budget_clp', parseFloat(fd.get('budget_clp')) || 0);
  fd.set('client_id', parseInt(fd.get('client_id')) || '');
  const res = await fetch('/api/projects.php', {method:'POST', body: new URLSearchParams(fd).toString(), headers: {'Content-Type':'application/x-www-form-urlencoded'}});
  const d = await res.json();
  if (d.ok) { showToast('Proyecto creado ✅'); closeModal(); loadTab('proyectos'); return false; }
  else { showToast(d.error, 'error'); return false; }
}

async function editarProyecto(id) {
  const res = await fetch(`/api/projects.php?action=get&id=${id}`);
  const p = await res.json();
  if (!p || p.ok === false) { showToast('Error al cargar proyecto', 'error'); return; }
  const opts = CLIENTES.map(c => `<option value="${c.id}" ${c.id==p.client_id?'selected':''}>${c.name}</option>`).join('');
  openModal(`<h3>Editar proyecto</h3>
    <form id="editProjForm" onsubmit="return editarProyectoEnviar(this)">
      <input type="hidden" name="id" value="${p.id}">
      <label>Nombre</label><input name="name" value="${p.name.replace(/"/g,'&quot;')}" required>
      <label>Cliente</label><select name="client_id">${opts}</select>
      <label>Presupuesto CLP $</label><input name="budget_clp" type="number" value="${p.budget_clp||0}">
      <label>Dirección</label><input name="address" value="${(p.address||'').replace(/"/g,'&quot;')}">
      <div class="modal-actions">
        <button type="button" class="btn btn-outline" onclick="closeModal()">Cancelar</button>
        <button type="submit" class="btn btn-primary">Guardar</button>
      </div>
    </form>`);
}

async function editarProyectoEnviar(form) {
  const fd = new FormData(form);
  fd.set('action', 'update');
  fd.set('budget_clp', parseFloat(fd.get('budget_clp')) || 0);
  fd.set('client_id', parseInt(fd.get('client_id')) || '');
  const res = await fetch('/api/projects.php', {method:'POST', body: new URLSearchParams(fd).toString(), headers: {'Content-Type':'application/x-www-form-urlencoded'}});
  const d = await res.json();
  if (d.ok) { showToast('Proyecto actualizado ✅'); closeModal(); loadTab('proyectos'); return false; }
  else { showToast(d.error, 'error'); return false; }
}

function cambiarEstadoProyecto(id) {
  openModal(`<h3>Cambiar estado del proyecto</h3>
    <form id="estadoForm" onsubmit="return cambiarEstadoSubmit(this, ${id})">
      <label>Nuevo estado</label>
      <select name="status" required>
        <option value="activo">Activo</option>
        <option value="pausado">Pausado</option>
        <option value="finalizado">Finalizado</option>
      </select>
      <div class="modal-actions">
        <button type="button" class="btn btn-outline" onclick="closeModal()">Cancelar</button>
        <button type="submit" class="btn btn-primary">Guardar</button>
      </div>
    </form>`);
}
async function cambiarEstadoSubmit(form, id) {
  const fd = new FormData(form);
  fd.set('action', 'change_status');
  fd.set('id', id);
  const res = await fetch('/api/projects.php', {method:'POST', body: new URLSearchParams(fd).toString(), headers: {'Content-Type':'application/x-www-form-urlencoded'}});
  const d = await res.json();
  if (d.ok) { showToast('Estado actualizado ✅'); closeModal(); loadTab('proyectos'); return false; }
  else { showToast(d.error, 'error'); return false; }
}
function eliminarProyecto(id) {
  if (!confirm('¿Eliminar este proyecto? También se borrarán sus pagos y documentos.')) return;
  fetch('/api/projects.php', {method:'POST', headers: {'Content-Type':'application/x-www-form-urlencoded'}, body: new URLSearchParams({action:'delete',id}).toString()})
    .then(r=>r.json()).then(d=>{ if(d.ok){ showToast('Proyecto eliminado'); loadTab('proyectos'); } else showToast(d.error,'error'); });
}
</script>
