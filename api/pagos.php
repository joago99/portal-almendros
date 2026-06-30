<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';
session_start();
$userId = $_SESSION['user_id'] ?? null;
$userRole = $_SESSION['user_role'] ?? null;
$isStaff = in_array($userRole, ['admin','staff']);
if (!$userId) exit;
$db = Database::get();

$action = $_GET['action'] ?? '';

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
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete' && $_POST['id'] ?? null) {
    $db->prepare('DELETE FROM payments WHERE id = ?')->execute([(int)$_POST['id']]);
    echo json_encode(['ok'=>true]); exit;
  }
  echo json_encode(['ok'=>false,'error'=>'Acción no válida']); exit;
}

// ─── HTML view ───
$search = $_GET['q'] ?? '';
$filterStatus = $_GET['status'] ?? 'todos';
$where = '';
if ($filterStatus === 'atrasados') { $where = 'WHERE p.status = "pendiente" AND p.due_date < date("now")'; }
elseif ($filterStatus === 'pendientes') { $where = 'WHERE p.status = "pendiente" AND p.due_date >= date("now")'; }
elseif ($filterStatus === 'pagados') { $where = 'WHERE p.status = "pagado"'; }

$payments = $db->query('SELECT p.*, pr.name as proyecto, c.name as cliente FROM payments p JOIN projects pr ON pr.id = p.project_id JOIN clients c ON c.id = pr.client_id '.$where.' ORDER BY p.due_date DESC LIMIT 100')->fetchAll();

$totalPendiente = array_sum(array_map(fn($p) => ($p['status']==='pendiente' && strtotime($p['due_date'])>=time()) ? $p['amount_clp'] : 0, $payments));
$totalAtrasado = array_sum(array_map(fn($p) => ($p['status']==='pendiente' && strtotime($p['due_date'])<time()) ? $p['amount_clp'] : 0, $payments));
$totalPagado = array_sum(array_map(fn($p) => $p['status']==='pagado' ? $p['amount_clp'] : 0, $payments));

$projects = $db->query('SELECT id, name, (SELECT name FROM clients WHERE id = p.client_id) as client FROM projects p ORDER BY name')->fetchAll();
?>
<div class="stats-row">
  <div class="stat-box"><div class="num" style="color:#ca8a04">$<?= number_format($totalPendiente,0,',','.') ?></div><div class="label">Pendiente</div></div>
  <div class="stat-box"><div class="num" style="color:#dc2626">$<?= number_format($totalAtrasado,0,',','.') ?></div><div class="label">Atrasado</div></div>
  <div class="stat-box"><div class="num" style="color:#16a34a">$<?= number_format($totalPagado,0,',','.') ?></div><div class="label">Pagado</div></div>
</div>

<div class="search-bar">
  <input type="text" id="searchPagos" placeholder="Buscar pago..." value="<?= htmlspecialchars($search) ?>">
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
    <tr class="pago-row" data-search="<?= strtolower(htmlspecialchars($p['cliente'].' '.$p['proyecto'].' '.$p['concept'])) ?>" data-id="<?= $p['id'] ?>" data-projid="<?= $p['project_id'] ?>" <?php if ($isStaff): ?>style="cursor:pointer"<?php endif; ?>>
      <td><?= htmlspecialchars($p['cliente']) ?></td>
      <td><?= htmlspecialchars($p['proyecto']) ?></td>
      <td><?= htmlspecialchars($p['concept']) ?></td>
      <td><strong>$<?= number_format($p['amount_clp'],0,',','.') ?></strong></td>
      <td style="color:<?= $sr==='atrasado'?'#dc2626':'#64748b' ?>"><?= $p['due_date'] ?></td>
      <td><?= $p['paid_at'] ?? '—' ?></td>
      <td><span class="status <?= $sr ?>"><?= $sr==='atrasado'?'⚠️ Atrasado':$sr ?></span></td>
      <?php if ($isStaff): ?>
      <td>
        <button class="btn btn-sm btn-outline" onclick="event.stopPropagation();editarPagoModal(<?= htmlspecialchars(json_encode($p)) ?>)">Editar</button>
        <button class="btn btn-sm btn-danger" onclick="event.stopPropagation();eliminarPago(<?= $p['id'] ?>)">Eliminar</button>
      </td>
      <?php endif; ?>
    </tr>
    <?php endforeach; ?>
  </table>
</div>

<script>
document.getElementById('searchPagos')?.addEventListener('input', function() {
  const q = this.value.toLowerCase();
  document.querySelectorAll('.pago-row').forEach(r => r.style.display = r.dataset.search.includes(q) ? '' : 'none');
});
function filtrarPagos() {
  loadTab('pagos&status=' + document.getElementById('filterStatus').value);
}

function nuevoPagoModal() {
  const projs = [
    <?php foreach ($projects as $pj): ?>
    {id:<?= $pj['id'] ?>, name:'<?= htmlspecialchars($pj['name']) ?>', client:'<?= htmlspecialchars($pj['client']??'') ?>'},
    <?php endforeach; ?>
  ];
  const opts = projs.map(p => `<option value="${p.id}">${p.client} - ${p.name}</option>`).join('');
  openModal(`<h3>Nuevo pago</h3>
    <form id="payForm" onsubmit="return crearPago(this)">
      <label>Proyecto</label><select name="project_id" required>${opts}</select>
      <label>Concepto</label><input name="concept" required>
      <label>Monto CLP $</label><input name="amount_clp" type="number" required>
      <label>Fecha de vencimiento</label><input name="due_date" type="date" required value="<?= date('Y-m-d', strtotime('+30 days')) ?>">
      <div class="modal-actions">
        <button type="button" class="btn btn-outline" onclick="closeModal()">Cancelar</button>
        <button type="submit" class="btn btn-primary">Registrar</button>
      </div>
    </form>`);
}

async function crearPago(form) {
  const fd = new FormData(form);
  fd.set('amount_clp', parseFloat(fd.get('amount_clp')) || 0);
  const res = await fetch('/api/pagos.php?action=create', {method:'POST', body: new URLSearchParams(fd).toString(), headers: {'Content-Type':'application/x-www-form-urlencoded'}});
  const d = await res.json();
  if (d.ok) { showToast('Pago registrado ✅'); closeModal(); loadTab('pagos'); return false; }
  else { showToast(d.error, 'error'); return false; }
}

function editarPagoModal(p) {
  openModal(`<h3>Editar pago</h3>
    <form id="editPayForm">
      <input type="hidden" name="id" value="${p.id}">
      <label>Concepto</label><input name="concept" value="${p.concept}">
      <label>Monto CLP $</label><input name="amount_clp" type="number" value="${p.amount_clp}">
      <label>Fecha de vencimiento</label><input name="due_date" type="date" value="${p.due_date}">
      <label>Estado</label><select name="status">
        <option value="pendiente" ${p.status==='pendiente'?'selected':''}>Pendiente</option>
        <option value="pagado" ${p.status==='pagado'?'selected':''}>Pagado</option>
        <option value="atrasado" ${p.status==='atrasado'?'selected':''}>Atrasado</option>
      </select>
      <label>Fecha de pago</label><input name="paid_at" type="date" value="${p.paid_at||''}">
      <div class="modal-actions">
        <button type="button" class="btn btn-outline" onclick="closeModal()">Cancelar</button>
        <button type="submit" class="btn btn-primary">Guardar</button>
      </div>
    </form>
    <div style="margin-top:0.5rem;text-align:right;font-size:0.75rem;color:#94a3b8">ID: ${p.id} | Creado: ${p.created_at || ''}</div>`);
  document.getElementById('editPayForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(e.target);
    fd.set('action', 'update');
    fd.set('amount_clp', parseFloat(fd.get('amount_clp')) || 0);
    const res = await fetch('/api/pagos.php', {method:'POST', body: new URLSearchParams(fd).toString(), headers: {'Content-Type':'application/x-www-form-urlencoded'}});
    const d = await res.json();
    if (d.ok) { showToast('Pago actualizado ✅'); closeModal(); loadTab('pagos'); }
    else showToast(d.error, 'error');
  });
}

async function eliminarPago(id) {
  if (!confirm('¿Eliminar este pago?')) return;
  const res = await fetch('/api/pagos.php', {method:'POST', body: new URLSearchParams({action:'delete', id}).toString(), headers: {'Content-Type':'application/x-www-form-urlencoded'}});
  const d = await res.json();
  if (d.ok) { showToast('Pago eliminado'); loadTab('pagos'); }
  else showToast(d.error, 'error');
}
</script>
