<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';
$auth = require_auth();
$userId = $auth['user_id'];
$userRole = $auth['role'];
$db = Database::get();

// Client filter: force scoped data when user is a client
$clientFilter = null;
if ($userRole === 'client') {
    $st = $db->prepare('SELECT client_id FROM app_users WHERE id = ?');
    $st->execute([$userId]);
    $cu = $st->fetch();
    $clientFilter = $cu ? (int)$cu['client_id'] : null;
}

// Stats
$proyWhere = $clientFilter ? ' WHERE client_id = ' . (int)$clientFilter : '';
$totalProyectos = $db->query('SELECT COUNT(*) as c FROM projects' . $proyWhere)->fetch()['c'];
$totalClientes = $db->query('SELECT COUNT(*) as c FROM clients')->fetch()['c'];

$payJoin = $clientFilter ? ' JOIN projects p2 ON p2.id = payments.project_id WHERE p2.client_id = ' . (int)$clientFilter . ' AND ' : ' WHERE ';
$pagosPendientes = $db->query('SELECT COALESCE(SUM(amount_clp),0) as t FROM payments' . $payJoin . 'status = "pendiente" AND due_date >= date("now")')->fetch()['t'];
$pagosAtrasados = $db->query('SELECT COALESCE(SUM(amount_clp),0) as t FROM payments' . $payJoin . 'status = "pendiente" AND due_date < date("now")')->fetch()['t'];
$totalCobrado = $db->query('SELECT COALESCE(SUM(amount_clp),0) as t FROM payments' . $payJoin . 'status = "pagado"')->fetch()['t'];

// Proyectos activos
$activosQ = 'SELECT p.name, c.name as cliente, p.budget_clp, 
    COALESCE((SELECT SUM(amount_clp) FROM payments WHERE project_id = p.id AND status = "pagado"),0) as pagado,
    COALESCE((SELECT SUM(amount_clp) FROM payments WHERE project_id = p.id AND status = "pendiente" AND due_date < date("now")),0) as atrasado
    FROM projects p JOIN clients c ON c.id = p.client_id WHERE p.status = "activo"';
if ($clientFilter) {
    $activosQ .= ' AND p.client_id = ' . (int)$clientFilter;
}
$activosQ .= ' ORDER BY p.created_at DESC';
$activos = $db->query($activosQ)->fetchAll();

// Pagos próximos a vencer (próximos 30 días)
$proximosQ = 'SELECT p.concept, p.amount_clp, p.due_date, pr.name as proyecto, c.name as cliente
    FROM payments p JOIN projects pr ON pr.id = p.project_id JOIN clients c ON c.id = pr.client_id
    WHERE p.status = "pendiente" AND p.due_date BETWEEN date("now") AND date("now", "+30 days")';
if ($clientFilter) {
    $proximosQ .= ' AND pr.client_id = ' . (int)$clientFilter;
}
$proximosQ .= ' ORDER BY p.due_date ASC LIMIT 5';
$proximos = $db->query($proximosQ)->fetchAll();
?>
<div class="stats-row">
  <div class="stat-box"><div class="num" style="color:#0d9488"><?= $totalProyectos ?></div><div class="label">Proyectos</div></div>
  <div class="stat-box"><div class="num" style="color:#2563eb"><?= $totalClientes ?></div><div class="label">Clientes</div></div>
  <div class="stat-box"><div class="num" style="color:#ca8a04">$<?= number_format($pagosPendientes,0,',','.') ?></div><div class="label">Por cobrar</div></div>
  <div class="stat-box"><div class="num" style="color:#dc2626">$<?= number_format($pagosAtrasados,0,',','.') ?></div><div class="label">Atrasado</div></div>
  <div class="stat-box"><div class="num" style="color:#16a34a">$<?= number_format($totalCobrado,0,',','.') ?></div><div class="label">Cobrado</div></div>
</div>

<div class="card">
  <div class="card-header">
    <h2>Proyectos activos</h2>
  </div>
  <?php if (count($activos)): ?>
  <table>
    <tr><th>Proyecto</th><th>Cliente</th><th>Presupuesto</th><th>Pagado</th><th>Atrasado</th><th>Avance</th></tr>
    <?php foreach ($activos as $a): 
      $pct = $a['budget_clp'] > 0 ? round(($a['pagado'] / $a['budget_clp']) * 100) : 0;
    ?>
    <tr>
      <td><strong><?= htmlspecialchars($a['name']) ?></strong></td>
      <td><?= htmlspecialchars($a['cliente']) ?></td>
      <td>$<?= number_format($a['budget_clp']??0,0,',','.') ?></td>
      <td style="color:#16a34a">$<?= number_format($a['pagado']??0,0,',','.') ?></td>
      <td style="color:<?= $a['atrasado'] > 0 ? '#dc2626' : '#94a3b8' ?>">$<?= number_format($a['atrasado']??0,0,',','.') ?></td>
      <td>
        <div style="background:#e2e8f0;border-radius:20px;height:8px;width:100px;overflow:hidden">
          <div style="background:<?= $pct > 50 ? '#16a34a' : ($pct > 25 ? '#ca8a04' : '#dc2626') ?>;height:8px;width:<?= $pct ?>%;border-radius:20px"></div>
        </div>
        <span style="font-size:0.75rem;color:#64748b"><?= $pct ?>%</span>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php else: ?>
    <div class="empty-state"><div class="icon">🏗️</div><p>No hay proyectos activos aún</p></div>
  <?php endif; ?>
</div>

<?php if (count($proximos)): ?>
<div class="card">
  <div class="card-header">
    <h2>📅 Próximos vencimientos (30 días)</h2>
  </div>
  <table>
    <tr><th>Proyecto</th><th>Cliente</th><th>Concepto</th><th>Monto</th><th>Vence</th></tr>
    <?php foreach ($proximos as $px): ?>
    <tr>
      <td><?= htmlspecialchars($px['proyecto']) ?></td>
      <td><?= htmlspecialchars($px['cliente']) ?></td>
      <td><?= htmlspecialchars($px['concept']) ?></td>
      <td>$<?= number_format($px['amount_clp'],0,',','.') ?></td>
      <td style="color:<?= strtotime($px['due_date']) < time() ? '#dc2626' : '#ca8a04' ?>"><?= $px['due_date'] ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
<?php endif; ?>
