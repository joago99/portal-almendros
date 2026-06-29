<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';
session_start();
$userId = $_SESSION['user_id'] ?? null;
$userRole = $_SESSION['user_role'] ?? null;
if (!$userId || !in_array($userRole, ['admin','staff'])) { echo '<div class="empty-state"><div class="icon">🔒</div><p>Acceso solo para staff</p></div>'; exit; }
$db = Database::get();
$search = $_GET['q'] ?? '';

$filterStatus = $_GET['status'] ?? 'todos';
$where = '';
$params = [];
if ($filterStatus === 'atrasados') { $where = 'WHERE p.status = "pendiente" AND p.due_date < date("now")'; }
elseif ($filterStatus === 'pendientes') { $where = 'WHERE p.status = "pendiente" AND p.due_date >= date("now")'; }
elseif ($filterStatus === 'pagados') { $where = 'WHERE p.status = "pagado"'; }

$stmt = $db->query('SELECT p.*, pr.name as proyecto, c.name as cliente, 
    CASE WHEN p.status = "pendiente" AND p.due_date < date("now") THEN "atrasado" ELSE p.status END as status_real
    FROM payments p JOIN projects pr ON pr.id = p.project_id JOIN clients c ON c.id = pr.client_id ' . $where . ' ORDER BY p.due_date DESC LIMIT 100');
$pays = $stmt->fetchAll();

// Totales
$totalPendiente = array_sum(array_map(fn($p) => ($p['status'] === 'pendiente' && strtotime($p['due_date']) >= time()) ? $p['amount_clp'] : 0, $pays));
$totalAtrasado = array_sum(array_map(fn($p) => ($p['status'] === 'pendiente' && strtotime($p['due_date']) < time()) ? $p['amount_clp'] : 0, $pays));
$totalPagado = array_sum(array_map(fn($p) => $p['status'] === 'pagado' ? $p['amount_clp'] : 0, $pays));
?>
<div class="stats-row">
  <div class="stat-box"><div class="num" style="color:#ca8a04">$<?= number_format($totalPendiente,0,',','.') ?></div><div class="label">Pendiente</div></div>
  <div class="stat-box"><div class="num" style="color:#dc2626">$<?= number_format($totalAtrasado,0,',','.') ?></div><div class="label">Atrasado</div></div>
  <div class="stat-box"><div class="num" style="color:#16a34a">$<?= number_format($totalPagado,0,',','.') ?></div><div class="label">Pagado</div></div>
</div>

<div class="search-bar">
  <input type="text" id="searchPagos" placeholder="Buscar pago..." value="<?= htmlspecialchars($search) ?>">
  <select id="filterStatus" onchange="filtrarPagos()">
    <option value="todos" <?= $filterStatus === 'todos' ? 'selected' : '' ?>>Todos</option>
    <option value="pendientes" <?= $filterStatus === 'pendientes' ? 'selected' : '' ?>>Pendientes</option>
    <option value="atrasados" <?= $filterStatus === 'atrasados' ? 'selected' : '' ?>>Atrasados</option>
    <option value="pagados" <?= $filterStatus === 'pagados' ? 'selected' : '' ?>>Pagados</option>
  </select>
  <button class="btn btn-primary" onclick="abrirNuevoPago()">+ Nuevo pago</button>
</div>

<div class="card">
  <table id="pagosTable">
    <tr><th>Cliente</th><th>Proyecto</th><th>Concepto</th><th>Monto</th><th>Vence</th><th>Pagado</th><th>Estado</th></tr>
    <?php foreach ($pays as $p): 
      $sr = ($p['status'] === 'pendiente' && strtotime($p['due_date']) < time()) ? 'atrasado' : $p['status'];
    ?>
    <tr class="pago-row" data-search="<?= strtolower(htmlspecialchars($p['cliente'].' '.$p['proyecto'].' '.$p['concept'])) ?>">
      <td><?= htmlspecialchars($p['cliente']) ?></td>
      <td><?= htmlspecialchars($p['proyecto']) ?></td>
      <td><?= htmlspecialchars($p['concept']) ?></td>
      <td><strong>$<?= number_format($p['amount_clp'],0,',','.') ?></strong></td>
      <td style="color:<?= $sr === 'atrasado' ? '#dc2626' : '#64748b' ?>"><?= $p['due_date'] ?></td>
      <td><?= $p['paid_at'] ?? '—' ?></td>
      <td><span class="status <?= $sr ?>"><?= $sr === 'atrasado' ? '⚠️ Atrasado' : $sr ?></span></td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>

<script>
document.getElementById('searchPagos')?.addEventListener('input', function() {
  const q = this.value.toLowerCase();
  document.querySelectorAll('.pago-row').forEach(r => {
    r.style.display = r.dataset.search.includes(q) ? '' : 'none';
  });
});
function filtrarPagos() {
  const s = document.getElementById('filterStatus').value;
  loadTab('pagos&status=' + s);
}
function abrirNuevoPago() {
  fetch('/api/pagos/form.php').then(r=>r.text()).then(html=>openModal(html));
}
</script>
