<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';
$auth = require_auth();
$userId = $auth['user_id'];
$userRole = $auth['role'];
$isStaff = in_array($userRole, ['admin','staff']);
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
$clientFilter = $clientFilter ?: ($_GET['client_id'] ?? null);
$search = $_GET['q'] ?? '';
$statusFilter = $_GET['estado'] ?? '';

$where = [];
$params = [];
if ($clientFilter) { $where[] = 'p.client_id = ?'; $params[] = (int)$clientFilter; }
if ($statusFilter) { $where[] = 'p.status = ?'; $params[] = $statusFilter; }
$whereSql = $where ? 'WHERE '.implode(' AND ', $where) : '';
$stmt = $db->prepare("SELECT p.*, c.name as client_name FROM projects p LEFT JOIN clients c ON c.id = p.client_id $whereSql ORDER BY p.created_at DESC");
$stmt->execute($params);
$projects = $stmt->fetchAll();
$clientes = $db->query('SELECT id, name FROM clients ORDER BY name')->fetchAll();
$docTypes = ['presupuesto','plano','legal','avance','otro'];
?>
<script>
const CLIENTES = <?= json_encode($clientes) ?>;
const CLIENT_FILTER = <?= json_encode($clientFilter ?: '') ?>;
</script>
<div class="search-bar">
  <input type="text" id="searchProyectos" placeholder="Buscar proyecto..." value="<?= htmlspecialchars($search) ?>">
  <select id="filterCliente" onchange="filtrarProyectos()">
    <option value="">Todos los clientes</option>
    <?php foreach ($clientes as $c): ?>
      <option value="<?= $c['id'] ?>" <?= (string)(int)($clientFilter ?? 0) === (string)$c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
    <?php endforeach; ?>
  </select>
  <select id="filterEstado" onchange="filtrarProyectos()">
    <option value="">Todos los estados</option>
    <option value="activo" <?=$statusFilter==='activo'?'selected':''?>>Activo</option>
    <option value="pausado" <?=$statusFilter==='pausado'?'selected':''?>>Pausado</option>
    <option value="finalizado" <?=$statusFilter==='finalizado'?'selected':''?>>Finalizado</option>
  </select>
  <button class="btn btn-outline btn-sm" onclick="clearProyectoFilters()">Limpiar</button>
  <?php if ($isStaff): ?>
  <button class="btn btn-primary" onclick="nuevoProyecto()" style="margin-left:auto">+ Nuevo proyecto</button>
  <?php endif; ?>
  <select id="ordenProyectos" onchange="ordenarProyectos()" style="min-width:auto">
    <option value="">Ordenar...</option>
    <option value="atraso">Atraso primero</option>
    <option value="pendiente">Pendiente primero</option>
    <option value="presupuesto_desc">Presupuesto ↓</option>
    <option value="presupuesto_asc">Presupuesto ↑</option>
    <option value="nombre">Nombre A-Z</option>
  </select>
</div>

<div id="proyectosList">
<?php if (!count($projects)): ?>
  <div class="empty-state"><div class="empty-state-title">Sin proyectos</div><p>Crea el primero o ajusta los filtros.</p></div>
<?php endif; ?>
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
  $docsByType = [];
  foreach ($docTypes as $t) {
    $st2 = $db->prepare('SELECT COUNT(*) FROM documents WHERE project_id = ? AND type = ?');
    $st2->execute([$proj['id'], $t]); $docsByType[$t] = (int)$st2->fetchColumn();
  }
  $pct = ($proj['budget_clp'] ?? 0) > 0 ? round(($pagado / $proj['budget_clp']) * 100) : 0;
?>
<div class="card proyecto-card" data-estado="<?= $proj['status'] ?>" data-cliente="<?= (int)($proj['client_id'] ?? 0) ?>" data-search="<?= strtolower(htmlspecialchars($proj['name'].' '.($proj['client_name'] ?? ''))) ?>" data-atrasado="<?= (float)$atrasado ?>" data-pendiente="<?= (float)$pendiente ?>" data-presupuesto="<?= (float)($proj['budget_clp'] ?? 0) ?>" data-nombre="<?= strtolower(htmlspecialchars($proj['name'])) ?>">
  <button class="card-header" style="cursor:pointer;min-height:44px;background:none;border:none;width:100%;text-align:left;padding:0;display:flex;justify-content:space-between;align-items:center" onclick="toggleProyecto('proj-<?= $proj['id'] ?>')" aria-expanded="false" aria-controls="proj-<?= $proj['id'] ?>">
    <div>
      <h2 style="font-size:1.1rem"><?= htmlspecialchars($proj['name']) ?></h2>
      <span style="font-size:0.8rem;color:#64748b"><?= htmlspecialchars($proj['client_name'] ?? 'Sin cliente') ?> — Estado: <strong><?= $proj['status'] ?></strong></span>
    </div>
    <div style="display:flex;align-items:center;gap:0.75rem">
      <span class="status <?= $proj['status'] ?>"><?= $proj['status'] ?></span>
      <span style="font-size:0.8rem;color:#64748b"><?= $pct ?>%</span>
      <span class="chevron" style="font-size:0.8rem;transition:transform .2s">▼</span>
    </div>
  </button>
  <div id="proj-<?= $proj['id'] ?>" style="display:none;margin-top:1rem">
    <div class="stats-row" style="margin-bottom:1rem;grid-template-columns:repeat(4,1fr)">
      <div class="stat-box" style="padding:0.5rem 1rem"><div class="num" style="font-size:1rem;color:#16a34a">$<?= number_format($pagado,0,',','.') ?></div><div class="label">Pagado</div></div>
      <div class="stat-box" style="padding:0.5rem 1rem"><div class="num" style="font-size:1rem;color:#ca8a04">$<?= number_format($pendiente,0,',','.') ?></div><div class="label">Pendiente</div></div>
      <div class="stat-box" style="padding:0.5rem 1rem"><div class="num" style="font-size:1rem;color:#dc2626">$<?= number_format($atrasado,0,',','.') ?></div><div class="label">Atrasado</div></div>
      <div class="stat-box" style="padding:0.5rem 1rem"><div class="num" style="font-size:1rem;color:#2563eb"><?= $docsCount ?> 📄</div><div class="label">Documentos</div></div>
      <div class="stat-box" style="padding:0.5rem 1rem"><div class="num" style="font-size:.95rem;color:#64748b"><?= implode(' ', array_map(fn($c,$k)=>$c?'<span title="'.htmlspecialchars($k).'">'.$c.'</span>':'', $docsByType, array_keys($docsByType))) ?></div><div class="label">Docs por tipo</div></div>
    </div>
    <?php
      $budget = (float)($proj['budget_clp'] ?? 0);
      $saldo = max(0, $budget - $pagado);
      $pendCount = (int)$db->prepare('SELECT COUNT(*) FROM payments WHERE project_id = ? AND status = "pendiente"')->execute([$proj['id']]) ? $db->query('SELECT COUNT(*) FROM payments WHERE project_id = ? AND status = "pendiente"')->fetchColumn() : 0;
      $ultimoPct = $db->prepare('SELECT percentage FROM progress_events WHERE project_id = ? ORDER BY event_date DESC, id DESC LIMIT 1');
      $ultimoPct->execute([$proj['id']]); $lastPct = (int)($ultimoPct->fetchColumn() ?: 0);
      $ultimosPagos = $db->prepare('SELECT concept, amount_clp, status, due_date FROM payments WHERE project_id = ? ORDER BY due_date DESC LIMIT 3');
      $ultimosPagos->execute([$proj['id']]); $recentPays = $ultimosPagos->fetchAll();
    ?>
    <div class="stats-row" style="margin-bottom:1rem;grid-template-columns:repeat(4,1fr)">
      <div class="stat-box" style="padding:0.5rem 1rem"><div class="num" style="font-size:1rem;color:#475569">$<?= number_format($budget,0,',','.') ?></div><div class="label">Presupuesto</div></div>
      <div class="stat-box" style="padding:0.5rem 1rem"><div class="num" style="font-size:1rem;color:#059669">$<?= number_format($saldo,0,',','.') ?></div><div class="label">Saldo restante</div></div>
      <div class="stat-box" style="padding:0.5rem 1rem"><div class="num" style="font-size:1rem;color:#f59e0b"><?= $pendCount ?> pend.</div><div class="label">Pagos pendientes</div></div>
      <div class="stat-box" style="padding:0.5rem 1rem"><div class="num" style="font-size:1rem;color:#0d9488"><?= $lastPct ?>%</div><div class="label">Avance obra</div></div>
    </div>
    <?php if ($recentPays): ?>
    <div class="card" style="margin-bottom:1rem">
      <strong style="font-size:0.85rem;color:#475569">Últimos pagos</strong>
      <table style="width:100%;margin-top:.5rem;font-size:.85rem;border-collapse:collapse">
        <thead><tr style="color:#64748b;text-align:left"><th style="padding:.35rem .5rem">Concepto</th><th style="padding:.35rem .5rem">Monto</th><th style="padding:.35rem .5rem">Vence</th><th style="padding:.35rem .5rem">Estado</th></tr></thead>
        <tbody>
          <?php foreach ($recentPays as $r): $rs = ($r['status']=='pendiente' && strtotime($r['due_date'])<time()) ? 'atrasado' : $r['status']; ?>
          <tr style="border-top:1px solid #f1f5f9">
            <td style="padding:.4rem .5rem"><?= htmlspecialchars($r['concept']) ?></td>
            <td style="padding:.4rem .5rem">$<?= number_format($r['amount_clp'],0,',','.') ?></td>
            <td style="padding:.4rem .5rem;color:<?= $rs==='atrasado'?'#dc2626':'#64748b' ?>"><?= $r['due_date'] ?></td>
            <td style="padding:.4rem .5rem"><span class="status <?= $rs ?>" style="font-size:.75rem"><?= $rs ?></span></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
    <div class="search-bar">
      <strong>Detalle</strong>
      <?php if ($isStaff): ?>
      <button class="btn btn-primary btn-sm" onclick="editarProyecto(<?= $proj['id'] ?>)">Editar</button>
      <button class="btn btn-outline btn-sm" onclick="cambiarEstadoProyecto(<?= $proj['id'] ?>)">Cambiar estado</button>
      <button class="btn btn-outline btn-sm" onclick="openAvanceModal(<?= $proj['id'] ?>, null)">⚡ Registrar avance rápido</button>
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
  if (el) {
    const btn = document.querySelector(`button[aria-controls="${id}"]`);
    const chevron = btn && btn.querySelector('.chevron');
    if (btn) btn.setAttribute('aria-expanded', el.style.display === 'none' ? 'true' : 'false');
    if (chevron) chevron.style.transform = el.style.display === 'none' ? 'rotate(0deg)' : 'rotate(-90deg)';
    el.style.display = el.style.display === 'none' ? 'block' : 'none';
  }
}
document.getElementById('searchProyectos')?.addEventListener('input', filtrarProyectos);
function filtrarProyectos() {
  const q = document.getElementById('searchProyectos')?.value.toLowerCase() || '';
  const s = document.getElementById('filterEstado').value;
  const c = document.getElementById('filterCliente')?.value || '';
  document.querySelectorAll('.proyecto-card').forEach(card => {
    const matchQ = !q || card.dataset.search.includes(q);
    const matchS = !s || card.dataset.estado === s;
    const matchC = !c || card.dataset.cliente === c;
    card.style.display = (matchQ && matchS && matchC) ? '' : 'none';
  });
}
function clearProyectoFilters() {
  const el1 = document.getElementById('searchProyectos');
  const el2 = document.getElementById('filterEstado');
  const el3 = document.getElementById('filterCliente');
  const el4 = document.getElementById('ordenProyectos');
  if (el1) el1.value = '';
  if (el2) el2.value = '';
  if (el3) el3.value = '';
  if (el4) el4.value = '';
  document.querySelectorAll('.proyecto-card').forEach(card => card.style.display = '');
}

function ordenarProyectos() {
  const container = document.getElementById('proyectosList');
  const cards = Array.from(container.querySelectorAll('.proyecto-card'));
  const val = document.getElementById('ordenProyectos').value;
  if (!val) return;
  cards.sort((a, b) => {
    const getNum = (el, attr) => parseFloat(el.getAttribute(attr) || '0');
    const getStr = (el, attr) => (el.getAttribute(attr) || '').trim().toLowerCase();
    switch (val) {
      case 'atraso': return getNum(b, 'data-atrasado') - getNum(a, 'data-atrasado');
      case 'pendiente': return getNum(b, 'data-pendiente') - getNum(a, 'data-pendiente');
      case 'presupuesto_desc': return getNum(b, 'data-presupuesto') - getNum(a, 'data-presupuesto');
      case 'presupuesto_asc': return getNum(a, 'data-presupuesto') - getNum(b, 'data-presupuesto');
      case 'nombre': return getStr(a, 'data-nombre').localeCompare(getStr(b, 'data-nombre'), 'es');
      default: return 0;
    }
  });
  container.innerHTML = '';
  cards.forEach(card => container.appendChild(card));
}

function openAvanceTab(projectId) {
  loadTab('avance').then(() => {
    const sel = document.getElementById('avProyecto') || document.getElementById('progProyecto');
    if (!sel) return;
    sel.value = String(projectId);
    const ev = new Event('change');
    sel.dispatchEvent(ev);
    const btn = document.getElementById('btnNuevoAvance');
    if (btn) btn.style.display = 'inline-block';
  });
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
  fetch('/api/projects.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: new URLSearchParams({action:'delete',id}).toString()})
    .then(r=>r.json()).then(d=>{ if(d.ok){ showToast('Proyecto eliminado'); loadTab('proyectos'); } else showToast(d.error,'error'); });
}
function quickAvance(projectId, projectName) {
  openModal(`<h3>Registrar avance rápido — ${projectName}</h3>
    <form id="quickAvanceForm" onsubmit="return saveQuickAvance(this, ${projectId})">
      <input type="hidden" name="project_id" value="${projectId}">
      <label>Título</label><input name="title" placeholder="Ej: Fundaciones completadas" required>
      <label>Descripción</label><textarea name="description" rows="3" placeholder="Resumen del avance..."></textarea>
      <label>Fecha</label><input type="date" name="event_date" value="${new Date().toISOString().slice(0,10)}" required>
      <label>% de avance (0-100)</label><input type="number" name="percentage" min="0" max="100" value="0">
      <label>Tipo</label><select name="event_type"><option value="daily_log">Avance diario</option><option value="milestone">Hito / Inspección</option></select>
      <div class="modal-actions">
        <button type="button" class="btn-outline" onclick="closeModal()">Cancelar</button>
        <button type="submit" class="btn-primary">Guardar avance</button>
      </div>
    </form>`);
}
function saveQuickAvance(form, projectId) {
  const fd = new FormData(form);
  fd.set('action', 'create');
  return fetch('/api/progress.php', { method:'POST', body: new URLSearchParams(fd).toString(), headers:{'Content-Type':'application/x-www-form-urlencoded'} })
    .then(r=>r.json()).then(d=>{
      if(d.ok){ showToast('Avance rápido registrado ✅'); closeModal(); loadTab('proyectos'); return false; }
      showToast(d.error,'error'); return false;
    });
}
</script>
