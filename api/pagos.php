<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';
session_start();
$userId = $_SESSION['user_id'] ?? null;
$userRole = $_SESSION['user_role'] ?? null;
$isStaff = in_array($userRole, ['admin','staff']);
if (!$userId) exit;
$db = Database::get();

// Client filter: force filter by their client_id
$clientFilter = null;
if ($userRole === 'client') {
  $st = $db->prepare('SELECT client_id FROM app_users WHERE id = ?');
  $st->execute([$userId]); $cu = $st->fetch();
  $clientFilter = $cu ? (int)$cu['client_id'] : null;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ─── Backend actions (JSON) ───
if ($action) {
  header('Content-Type: application/json');
  if (!$isStaff) { echo json_encode(['ok'=>false,'error'=>'No autorizado']); exit; }
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'create') {
    $projId = (int)($_POST['project_id'] ?? 0);
    $concept = trim($_POST['concept'] ?? '');
    $amount = (float)($_POST['amount_clp'] ?? 0);
    $due = $_POST['due_date'] ?? date('Y-m-d');
    $status = $_POST['status'] ?? 'pendiente';
    if (!$projId || !$concept || !$amount) { echo json_encode(['ok'=>false,'error'=>'Faltan datos']); exit; }
    $db->prepare('INSERT INTO payments (project_id, concept, amount_clp, due_date, status, created_by) VALUES (?,?,?,?,?,?)')
      ->execute([$projId, $concept, $amount, $due, $status, $userId]);
    echo json_encode(['ok'=>true, 'id'=>$db->lastInsertId()]); exit;
  }
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'update') {
    $id = (int)$_POST['id'];
    $fields = []; $params = [];
    foreach (['status','due_date','concept','amount_clp','paid_at'] as $k) {
      if (isset($_POST[$k])) { $fields[] = "$k = ?"; $params[] = $_POST[$k]; }
    }
    if ($fields) { $params[] = $id; $db->prepare('UPDATE payments SET '.implode(',',$fields).' WHERE id = ?')->execute($params); }
    echo json_encode(['ok'=>true]); exit;
  }
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete' && ($_POST['id'] ?? null)) {
    $db->prepare('DELETE FROM payments WHERE id = ?')->execute([(int)$_POST['id']]);
    echo json_encode(['ok'=>true]); exit;
  }
  echo json_encode(['ok'=>false,'error'=>'Acción no válida']); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'get' && isset($_GET['id'])) {
  header('Content-Type: application/json');
  $stmt = $db->prepare('SELECT * FROM payments WHERE id = ?');
  $stmt->execute([(int)$_GET['id']]);
  echo json_encode($stmt->fetch(PDO::FETCH_ASSOC) ?: ['ok'=>false]);
  exit;
}
// ─── HTML view ───
// ─── HTML view ───
$filterStatus = $_GET['status'] ?? 'todos';
$where = '';
if ($filterStatus === 'atrasados') $where = 'WHERE p.status = "pendiente" AND p.due_date < date("now")';
elseif ($filterStatus === 'pendientes') $where = 'WHERE p.status = "pendiente" AND p.due_date >= date("now")';
elseif ($filterStatus === 'pagados') $where = 'WHERE p.status = "pagado"';
// Client filter: only show payments for their projects
if ($clientFilter) {
  $clientW = 'pr.client_id = ' . (int)$clientFilter;
  $where = $where ? $where . ' AND ' . $clientW : 'WHERE ' . $clientW;
}

$payments = $db->query('SELECT p.*, pr.name as proyecto, c.name as cliente FROM payments p JOIN projects pr ON pr.id = p.project_id JOIN clients c ON c.id = pr.client_id '.$where.' ORDER BY p.due_date DESC LIMIT 100')->fetchAll();

$totalPendiente = array_sum(array_map(fn($p) => ($p['status']==='pendiente' && strtotime($p['due_date'])>=time()) ? $p['amount_clp'] : 0, $payments));
$totalAtrasado = array_sum(array_map(fn($p) => ($p['status']==='pendiente' && strtotime($p['due_date'])<time()) ? $p['amount_clp'] : 0, $payments));
$totalPagado = array_sum(array_map(fn($p) => $p['status']==='pagado' ? $p['amount_clp'] : 0, $payments));

if ($clientFilter) {
  $st = $db->prepare('SELECT p.id, p.name, COALESCE(c.name,"") as client FROM projects p LEFT JOIN clients c ON c.id = p.client_id WHERE p.client_id = ? ORDER BY p.name');
  $st->execute([$clientFilter]); $projects = $st->fetchAll();
} else {
  $projects = $db->query('SELECT p.id, p.name, COALESCE(c.name,"") as client FROM projects p LEFT JOIN clients c ON c.id = p.client_id ORDER BY p.name')->fetchAll();
}
$today = date('Y-m-d');
?>
<script>
const PAY_PROJECTS = <?= json_encode($projects) ?>;
const TODAY = '<?= $today ?>';
</script>
<div class="stats-row">
  <div class="stat-box"><div class="num" style="color:#ca8a04">$<?= number_format($totalPendiente,0,',','.') ?></div><div class="label">Pendiente</div></div>
  <div class="stat-box"><div class="num" style="color:#dc2626">$<?= number_format($totalAtrasado,0,',','.') ?></div><div class="label">Atrasado</div></div>
  <div class="stat-box"><div class="num" style="color:#16a34a">$<?= number_format($totalPagado,0,',','.') ?></div><div class="label">Pagado</div></div>
</div>

<div class="search-bar">
  <input type="text" id="searchPagos" placeholder="Buscar pago..." oninput="filtrarPagosTexto()">
  <select id="filterStatus" onchange="filtrarPagos()">
    <option value="todos" <?=$filterStatus==='todos'?'selected':''?>>Todos</option>
    <option value="pendientes" <?=$filterStatus==='pendientes'?'selected':''?>>Pendientes</option>
    <option value="atrasados" <?=$filterStatus==='atrasados'?'selected':''?>>Atrasados</option>
    <option value="pagados" <?=$filterStatus==='pagados'?'selected':''?>>Pagados</option>
  </select>
  <?php if ($isStaff): ?>
  <button class="btn btn-primary" onclick="nuevoPagoModal()">+ Nuevo pago</button>
  <?php endif; ?>
</div>

<div class="card">
  <table id="pagosTable">
    <tr><th>Cliente</th><th>Proyecto</th><th>Concepto</th><th>Monto</th><th>Vence</th><th>Pagado</th><th>Estado</th><?php if ($isStaff): ?><th></th><?php endif; ?></tr>
    <?php foreach ($payments as $p):
      $sr = ($p['status']==='pendiente' && strtotime($p['due_date'])<time()) ? 'atrasado' : $p['status'];
    ?>
    <tr class="pago-row" data-search="<?= strtolower(htmlspecialchars($p['cliente'].' '.$p['proyecto'].' '.$p['concept'])) ?>">
      <td><?= htmlspecialchars($p['cliente']) ?></td>
      <td><?= htmlspecialchars($p['proyecto']) ?></td>
      <td><?= htmlspecialchars($p['concept']) ?></td>
      <td><strong>$<?= number_format($p['amount_clp'],0,',','.') ?></strong></td>
      <td style="color:<?= $sr==='atrasado'?'#dc2626':'#64748b' ?>"><?= $p['due_date'] ?></td>
      <td><?= $p['paid_at'] ?? '—' ?></td>
      <td><span class="status <?= $sr ?>"><?= $sr==='atrasado'?'⚠️ Atrasado':$sr ?></span></td>
      <?php if ($isStaff): ?>
      <td>
        <button class="btn btn-sm btn-outline" onclick="editarPagoModal(<?= $p['id'] ?>)">Editar</button>
        <button class="btn btn-sm btn-danger" onclick="eliminarPago(<?= $p['id'] ?>)">Eliminar</button>
      </td>
      <?php endif; ?>
    </tr>
    <?php endforeach; ?>
  </table>
</div>

<script>
function filtrarPagosTexto() {
  const q = document.getElementById('searchPagos').value.toLowerCase();
  document.querySelectorAll('.pago-row').forEach(r => r.style.display = r.dataset.search.includes(q) ? '' : 'none');
}
function filtrarPagos() {
  loadTab('pagos&status=' + document.getElementById('filterStatus').value);
}

function nuevoPagoModal() {
  const opts = PAY_PROJECTS.map(p => `<option value="${p.id}">${p.client} - ${p.name}</option>`).join('');
  openModal(`<h3>Nuevo pago</h3>
    <form id="payForm" onsubmit="return crearPago(this)">
      <label>Proyecto</label><select name="project_id" required>${opts}</select>
      <label>Concepto</label><input name="concept" required>
      <label>Monto CLP $</label><input name="amount_clp" type="number" required>
      <label>Fecha de vencimiento</label><input name="due_date" type="date" required value="${TODAY}">
      <div class="modal-actions">
        <button type="button" class="btn btn-outline" onclick="closeModal()">Cancelar</button>
        <button type="submit" class="btn btn-primary">Registrar</button>
      </div>
    </form>`);
}

async function crearPago(form) {
  const fd = new FormData(form);
  fd.set('action', 'create');
  fd.set('amount_clp', parseFloat(fd.get('amount_clp')) || 0);
  const res = await fetch('/api/pagos.php', {method:'POST', body: new URLSearchParams(fd).toString(), headers: {'Content-Type':'application/x-www-form-urlencoded'}});
  const d = await res.json();
  if (d.ok) { showToast('Pago registrado ✅'); closeModal(); loadTab('pagos'); return false; }
  else { showToast(d.error, 'error'); return false; }
}

async function editarPagoModal(id) {
  const res = await fetch('/api/pagos.php', {method:'POST', body: new URLSearchParams({action:'update',id, _fetch: '1'}).toString(), headers: {'Content-Type':'application/x-www-form-urlencoded'}});
  // No: necesito obtener los datos del pago. Uso un endpoint get.
  // En lugar de eso, busco en la fila.
  const row = document.querySelector(`.pago-row:nth-child(${id+2})`); // offset
  // Mejor: incrustar datos como data-attrs
  // MEJOR: paso a cargar vía fetch de un endpoint get
  // Voy a buscar los datos inline
  const cells = document.querySelector(`.pago-row:has(td button[onclick*="${id}"])`)?.querySelectorAll('td');
  if (!cells) { showToast('Error: no se encontró el pago', 'error'); return; }
  // No funciona bien, mejor usar un pequeño endpoint get
  fetch('/api/pagos.php?action=get&id='+id).then(r=>r.json()).then(p => {
    if (!p.id) { showToast('Error al cargar pago', 'error'); return; }
    const projOpts = PAY_PROJECTS.map(pr => `<option value="${pr.id}" ${pr.id==p.project_id?'selected':''}>${pr.client} - ${pr.name}</option>`).join('');
    openModal(`<h3>Editar pago</h3>
      <form id="editPayForm" onsubmit="return editarPagoEnviar(this)">
        <input type="hidden" name="id" value="${p.id}">
        <label>Proyecto</label><select name="project_id">${projOpts}</select>
        <label>Concepto</label><input name="concept" value="${(p.concept||'').replace(/"/g,'&quot;')}">
        <label>Monto CLP $</label><input name="amount_clp" type="number" value="${p.amount_clp||0}">
        <label>Vencimiento</label><input name="due_date" type="date" value="${p.due_date||TODAY}">
        <label>Estado</label><select name="status">
          <option value="pendiente" ${p.status==='pendiente'?'selected':''}>Pendiente</option>
          <option value="pagado" ${p.status==='pagado'?'selected':''}>Pagado</option>
          <option value="atrasado" ${p.status==='atrasado'?'selected':''}>Atrasado</option>
        </select>
        <label>Fecha de pago</label><input name="paid_at" type="date" value="${p.paid_at||TODAY}">
        <div class="modal-actions">
          <button type="button" class="btn btn-outline" onclick="closeModal()">Cancelar</button>
          <button type="submit" class="btn btn-primary">Guardar</button>
        </div>
      </form>`);
  });
}

async function editarPagoEnviar(form) {
  const fd = new FormData(form);
  fd.set('action', 'update');
  fd.set('amount_clp', parseFloat(fd.get('amount_clp')) || 0);
  const res = await fetch('/api/pagos.php', {method:'POST', body: new URLSearchParams(fd).toString(), headers: {'Content-Type':'application/x-www-form-urlencoded'}});
  const d = await res.json();
  if (d.ok) { showToast('Pago actualizado ✅'); closeModal(); loadTab('pagos'); return false; }
  else { showToast(d.error, 'error'); return false; }
}

async function eliminarPago(id) {
  if (!confirm('¿Eliminar este pago?')) return;
  const res = await fetch('/api/pagos.php', {method:'POST', body: new URLSearchParams({action:'delete',id}).toString(), headers: {'Content-Type':'application/x-www-form-urlencoded'}});
  const d = await res.json();
  if (d.ok) { showToast('Pago eliminado'); loadTab('pagos'); }
  else showToast(d.error, 'error');
}
</script>
