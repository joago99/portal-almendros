<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';
$auth = require_auth();
$userId = $auth['user_id'];
$userRole = $auth['role'];
$db = Database::get();

// Client filter: scoped data for clients
$clientFilter = null;
if ($userRole === 'client') {
    $st = $db->prepare('SELECT client_id FROM app_users WHERE id = ?');
    $st->execute([$userId]);
    $cu = $st->fetch();
    $clientFilter = $cu ? (int)$cu['client_id'] : null;
}

// Filters
$clientFilter = $clientFilter ?: ($_GET['client_id'] ?? null);
$statusFilter = $_GET['estado'] ?? '';
$searchFilter = trim((string)($_GET['q'] ?? ''));

// Stats
$proyWhere = $clientFilter ? ' WHERE client_id = ' . (int)$clientFilter : '';
$totalProyectos = $db->query('SELECT COUNT(*) as c FROM projects' . $proyWhere)->fetch()['c'];
$totalClientes = $db->query('SELECT COUNT(*) as c FROM clients')->fetch()['c'];

$payWhereBase = $clientFilter ? ' JOIN projects p2 ON p2.id = payments.project_id WHERE p2.client_id = ' . (int)$clientFilter . ' AND ' : ' WHERE ';
$pagosPendientes = $db->query('SELECT COALESCE(SUM(amount_clp),0) as t FROM payments' . $payWhereBase . 'status = "pendiente" AND due_date >= date("now")')->fetch()['t'];
$pagosAtrasados = $db->query('SELECT COALESCE(SUM(amount_clp),0) as t FROM payments' . $payWhereBase . 'status = "pendiente" AND due_date < date("now")')->fetch()['t'];
$totalCobrado = $db->query('SELECT COALESCE(SUM(amount_clp),0) as t FROM payments' . $payWhereBase . 'status = "pagado"')->fetch()['t'];

// Avance KPIs
$proyFilterSql = $clientFilter ? ' WHERE p.client_id = ' . (int)$clientFilter : '';
$sinAvance7 = $db->query("SELECT COUNT(*) FROM (SELECT p.id, MAX(e.event_date) as ult FROM projects p LEFT JOIN progress_events e ON e.project_id = p.id $proyFilterSql GROUP BY p.id HAVING ult IS NULL OR ult < date('now','-7 days'))")->fetchColumn();
$conAvance7 = $db->query("SELECT COUNT(*) FROM (SELECT p.id, MAX(e.event_date) as ult FROM projects p JOIN progress_events e ON e.project_id = p.id $proyFilterSql GROUP BY p.id HAVING ult >= date('now','-7 days'))")->fetchColumn();
$hitosCumplidos = $db->query("SELECT COUNT(*) FROM project_milestones WHERE completed = 1 AND project_id IN (SELECT id FROM projects" . ($proyFilterSql ?: '') . ")")->fetchColumn();
$proyConBrecha = 0;
if (!$clientFilter) {
    $proyConBrecha = $db->query("SELECT COUNT(*) FROM (SELECT p.id, COALESCE(SUM(m.weight_pct),0) as avance, COALESCE((SELECT SUM(amount_clp) FROM payments WHERE project_id = p.id AND status='pagado'),0) as pagado, p.budget_clp FROM projects p LEFT JOIN project_milestones m ON m.project_id = p.id AND m.completed = 1 GROUP BY p.id HAVING (avance > 0 AND budget_clp > 0 AND ABS(avance - ROUND(pagado/budget_clp*100)) > 10))")->fetchColumn();
} else {
    $proyConBrecha = 0; // skip for client view
}

$proyectosStatuses = ['activo', 'pausado', 'finalizado'];
$proyectosData = [];
$statusCounts = [];
foreach ($proyectosStatuses as $st) {
    $q = 'SELECT p.id, p.name, p.status, c.name as cliente, p.budget_clp,
        COALESCE((SELECT SUM(amount_clp) FROM payments WHERE project_id = p.id AND status = "pagado"),0) as pagado,
        COALESCE((SELECT SUM(amount_clp) FROM payments WHERE project_id = p.id AND status = "pendiente" AND due_date < date("now")),0) as atrasado,
        COALESCE((SELECT SUM(amount_clp) FROM payments WHERE project_id = p.id AND status = "pendiente" AND due_date >= date("now")),0) as pendiente,
        COALESCE((SELECT SUM(weight_pct) FROM project_milestones WHERE project_id = p.id AND completed = 1),0) as avance_fisico
        FROM projects p JOIN clients c ON c.id = p.client_id WHERE p.status = "' . $st . '"';
    if ($clientFilter) {
        $q .= ' AND p.client_id = ' . (int)$clientFilter;
    }
    if ($searchFilter) {
        $q .= ' AND lower(p.name || " " || c.name) LIKE ' . $db->quote('%' . $searchFilter . '%');
    }
    $q .= ' ORDER BY p.created_at DESC';
    $rows = $db->query($q)->fetchAll();
    $proyectosData[$st] = $rows;
    $statusCounts[$st] = count($rows);
}

$allCount = array_sum($statusCounts);

// Clientes list for filter
$clientes = $db->query('SELECT id, name FROM clients ORDER BY name')->fetchAll();

// Proximos vencimientos
$proxQ = 'SELECT p.concept, p.amount_clp, p.due_date, pr.name as proyecto, c.name as cliente
    FROM payments p JOIN projects pr ON pr.id = p.project_id JOIN clients c ON c.id = pr.client_id
    WHERE p.status = "pendiente" AND p.due_date BETWEEN date("now") AND date("now", "+30 days")';
if ($clientFilter) {
    $proxQ .= ' AND pr.client_id = ' . (int)$clientFilter;
}
$proxQ .= ' ORDER BY p.due_date ASC LIMIT 5';
$proximos = $db->query($proxQ)->fetchAll();

// Format helpers
function m($n){ return '$' . number_format((float)$n/1e6,1,',','.') . 'M'; }
function colorStatus($s){
    return match($s){
        'activo'=>'#16a34a',
        'pausado'=>'#ca8a04',
        'finalizado'=>'#64748b',
        default=> '#334155'
    };
}
?>
<div class="stats-row">
  <div class="stat-box"><div class="num"><?= (int)$totalProyectos ?></div><div class="label">Proyectos</div></div>
  <div class="stat-box"><div class="num"><?= (int)$totalClientes ?></div><div class="label">Clientes</div></div>
  <div class="stat-box"><div class="num"><?= m($pagosPendientes) ?></div><div class="label">Por cobrar</div></div>
  <div class="stat-box"><div class="num" style="color:#dc2626"><?= m($pagosAtrasados) ?></div><div class="label">Atrasado</div></div>
  <div class="stat-box"><div class="num" style="color:#16a34a"><?= m($totalCobrado) ?></div><div class="label">Cobrado</div></div>
  <div class="stat-box"><div class="num" style="color:#059669"><?= (int)$conAvance7 ?></div><div class="label">Con avance (7d)</div></div>
  <div class="stat-box"><div class="num" style="color:<?= $sinAvance7 > 0 ? '#dc2626' : '#64748b' ?>"><?= (int)$sinAvance7 ?></div><div class="label">Sin avance (7d)</div></div>
  <div class="stat-box"><div class="num" style="color:#7c3aed"><?= (int)$hitosCumplidos ?></div><div class="label">Hitos cumplidos</div></div>
  <div class="stat-box"><div class="num" style="color:<?= $proyConBrecha > 0 ? '#ca8a04' : '#64748b' ?>"><?= (int)$proyConBrecha ?></div><div class="label">Alertas brecha</div></div>
</div>

<?php if ($sinAvance7 > 0): ?>
<div style="background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:.75rem 1rem;margin-bottom:1rem;display:flex;align-items:center;gap:.75rem">
  <span style="font-size:1.2rem">⚠️</span>
  <div><span style="font-weight:600;color:#991b1b"><?= (int)$sinAvance7 ?> obra(s) sin avance en más de 7 días</span>
  <span style="font-size:.8rem;color:#b91c1c;display:block">Registrar avance pendiente — puede afectar cobranza y visibilidad al cliente.</span></div>
  <a href="#avance" style="margin-left:auto;background:#dc2626;color:#fff;padding:.4rem .8rem;border-radius:8px;text-decoration:none;font-size:.8rem;font-weight:600;white-space:nowrap">+ Registrar ahora</a>
</div>
<?php endif; ?><?php if ($proyConBrecha > 0): ?>
<div style="background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:.75rem 1rem;margin-bottom:1rem;display:flex;align-items:center;gap:.75rem">
  <span style="font-size:1.2rem">📊</span>
  <div><span style="font-weight:600;color:#92400e"><?= (int)$proyConBrecha ?> obra(s) con brecha entre avance físico y pagos</span>
  <span style="font-size:.8rem;color:#a16207;display:block">Revisar en cada obra: avance vs pagos recibidos.</span></div>
</div>
<?php endif; ?>

<div class="search-bar">
  <input type="text" id="resumenSearch" placeholder="Buscar proyecto o cliente..." value="<?= htmlspecialchars($searchFilter) ?>">
  <select id="resumenEstado" onchange="filtrarResumen()">
    <option value="">Todos los estados</option>
    <option value="activo" <?= $statusFilter==='activo'?'selected':'' ?>>Activo</option>
    <option value="pausado" <?= $statusFilter==='pausado'?'selected':'' ?>>Pausado</option>
    <option value="finalizado" <?= $statusFilter==='finalizado'?'selected':'' ?>>Finalizado</option>
  </select>
  <select id="resumenCliente" onchange="filtrarResumen()">
    <option value="">Todos los clientes</option>
    <?php foreach ($clientes as $c): ?>
      <option value="<?= $c['id'] ?>" <?= (string)(int)($clientFilter ?? 0) === (string)$c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
    <?php endforeach; ?>
  </select>
  <button class="btn btn-outline btn-sm" onclick="clearResumenFilters()">Limpiar</button>
</div>

<div class="card">
  <div class="card-header"><h2>Estado de obras</h2></div>
  <?php if ($allCount): ?>
  <div style="overflow-x:auto">
  <table>
    <tr><th>Proyecto</th><th>Cliente</th><th>Estado</th><th>Presupuesto</th><th>Pagado</th><th>Pendiente</th><th>Atrasado</th><th>Avance físico</th><th>vs Pagos</th></tr>
    <?php
      foreach ($proyectosStatuses as $st) {
        foreach ($proyectosData[$st] as $p) {
          $pagado = (float)($p['pagado'] ?? 0);
          $pendiente = (float)($p['pendiente'] ?? 0);
          $atrasado = (float)($p['atrasado'] ?? 0);
          $budget = (float)($p['budget_clp'] ?? 0);
          $avanceFisico = (int)($p['avance_fisico'] ?? 0);
          $pagadoPct = $budget > 0 ? round(($pagado / $budget) * 100) : 0;
          $gap = $avanceFisico - $pagadoPct;
          $statusColor = colorStatus($st);
    ?>
    <tr>
      <td style="max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= htmlspecialchars($p['name']) ?>"><a href="#proyectos?id=<?= (int)$p['id'] ?>" style="color:#1e293b;text-decoration:none;font-weight:600"><?= htmlspecialchars($p['name']) ?></a></td>
      <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= htmlspecialchars($p['cliente']) ?>"><?= htmlspecialchars($p['cliente']) ?></td>
      <td><span class="status" style="background:<?= $statusColor ?>20;color:<?= $statusColor ?>"><?= $st ?></span></td>
      <td><?= m($budget) ?></td>
      <td style="color:#16a34a"><?= m($pagado) ?></td>
      <td style="color:#ca8a04"><?= m($pendiente) ?></td>
      <td style="color:<?= $atrasado > 0 ? '#dc2626' : '#94a3b8' ?>"><?= $atrasado > 0 ? m($atrasado) : '—' ?></td>
      <td>
        <div style="background:#e2e8f0;border-radius:20px;height:8px;width:72px;overflow:hidden;display:inline-block;vertical-align:middle;margin-right:.35rem">
          <div style="background:<?= $avanceFisico > 50 ? '#059669' : ($avanceFisico > 25 ? '#ca8a04' : '#dc2626') ?>;height:8px;width:<?= $avanceFisico ?>%;border-radius:20px"></div>
        </div>
        <span style="font-size:0.75rem;font-weight:700;color:<?= $avanceFisico > 0 ? '#059669' : '#94a3b8' ?>"><?= $avanceFisico ?>%</span>
      </td>
      <td style="font-size:0.75rem">
        <?php if ($avanceFisico > 0 && $budget > 0): ?>
          <span style="color:<?= abs($gap) <= 10 ? '#059669' : ($gap > 0 ? '#ca8a04' : '#dc2626') ?>">
            <?= $gap > 0 ? '+' : '' ?><?= $gap ?>%
          </span>
          <?php if ($gap > 10): ?><span style="color:#ca8a04;font-weight:600"> ⚠️</span><?php endif; ?>
          <?php if ($gap < -10): ?><span style="color:#dc2626;font-weight:600"> 🔴</span><?php endif; ?>
        <?php else: ?>
          <span style="color:#94a3b8">—</span>
        <?php endif; ?>
      </td>
    </tr>
    <?php }} ?>
  </table>
  </div>
  <?php else: ?>
    <div class="empty-state"><div class="icon">🏗️</div><p>Sin proyectos para este filtro.</p></div>
  <?php endif; ?>
</div>

<?php if (count($proximos)): ?>
<div class="card">
  <div class="card-header"><h2>Próximos vencimientos (30 días)</h2></div>
  <div style="overflow-x:auto">
  <table>
    <tr><th>Proyecto</th><th>Cliente</th><th>Concepto</th><th>Monto</th><th>Vence</th></tr>
    <?php foreach ($proximos as $px): ?>
    <tr>
      <td><?= htmlspecialchars($px['proyecto']) ?></td>
      <td><?= htmlspecialchars($px['cliente']) ?></td>
      <td><?= htmlspecialchars($px['concept']) ?></td>
      <td><?= m($px['amount_clp']) ?></td>
      <td style="color:<?= strtotime($px['due_date']) < time() ? '#dc2626' : '#ca8a04' ?>"><?= $px['due_date'] ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  </div>
</div>
<?php endif; ?>

<script>
function filtrarResumen(){
  const q = document.getElementById('resumenSearch')?.value.trim() || '';
  const estado = document.getElementById('resumenEstado')?.value || '';
  const cliente = document.getElementById('resumenCliente')?.value || '';
  const params = new URLSearchParams();
  if (q) params.set('q', q);
  if (estado) params.set('estado', estado);
  if (cliente) params.set('client_id', cliente);
  const box = document.getElementById('mainContent');
  if (box) { box.innerHTML = '<div style="text-align:center;padding:2rem;color:#94a3b8">Cargando...</div>'; }
  fetch('/api/resumen.php?' + params.toString())
    .then(r => r.text())
    .then(html => { if (box) box.innerHTML = html; })
    .catch(() => { if (box) box.innerHTML = '<div style="text-align:center;padding:2rem;color:#dc2626">Error al cargar</div>'; });
}
function clearResumenFilters(){
  const box = document.getElementById('mainContent');
  if (box) { box.innerHTML = '<div style="text-align:center;padding:2rem;color:#94a3b8">Cargando...</div>'; }
  fetch('/api/resumen.php')
    .then(r => r.text())
    .then(html => { if (box) box.innerHTML = html; })
    .catch(() => { if (box) box.innerHTML = '<div style="text-align:center;padding:2rem;color:#dc2626">Error al cargar</div>'; });
}
</script>
