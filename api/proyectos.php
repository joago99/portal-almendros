<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';
session_start();
$userId = $_SESSION['user_id'] ?? null;
$userRole = $_SESSION['user_role'] ?? null;
$isStaff = in_array($userRole, ['admin','staff']);
if (!$userId) exit;
$db = Database::get();

$clientFilter = $_GET['client_id'] ?? null;
$search = $_GET['q'] ?? '';

if ($clientFilter) {
    $stmt = $db->prepare('SELECT p.*, c.name as client_name FROM projects p JOIN clients c ON c.id = p.client_id WHERE p.client_id = ? ORDER BY p.created_at DESC');
    $stmt->execute([$clientFilter]);
} else {
    $stmt = $db->query('SELECT p.*, c.name as client_name FROM projects p JOIN clients c ON c.id = p.client_id ORDER BY p.created_at DESC');
}
$projects = $stmt->fetchAll();
?>
<div class="search-bar">
  <input type="text" id="searchProyectos" placeholder="Buscar proyecto por nombre o cliente..." value="<?= htmlspecialchars($search) ?>">
  <select id="filterEstado" onchange="filtrarProyectos()">
    <option value="">Todos los estados</option>
    <option value="activo">Activo</option><option value="pausado">Pausado</option><option value="finalizado">Finalizado</option>
  </select>
  <?php if ($isStaff): ?>
  <button class="btn btn-primary" onclick="nuevoProyecto()">+ Nuevo proyecto</button>
  <?php endif; ?>
</div>

<div id="proyectosList">
<?php foreach ($projects as $proj): 
    $pagado = $db->prepare('SELECT COALESCE(SUM(amount_clp),0) as t FROM payments WHERE project_id = ? AND status = "pagado"');
    $pagado->execute([$proj['id']]); $pag = $pagado->fetch()['t'];
    $pend = $db->prepare('SELECT COALESCE(SUM(amount_clp),0) as t FROM payments WHERE project_id = ? AND status = "pendiente" AND due_date >= date("now")');
    $pend->execute([$proj['id']]); $pen = $pend->fetch()['t'];
    $atra = $db->prepare('SELECT COALESCE(SUM(amount_clp),0) as t FROM payments WHERE project_id = ? AND status = "pendiente" AND due_date < date("now")');
    $atra->execute([$proj['id']]); $atr = $atra->fetch()['t'];
    $docs = $db->prepare('SELECT type, COUNT(*) as c FROM documents WHERE project_id = ? GROUP BY type');
    $docs->execute([$proj['id']]); $docTypes = $docs->fetchAll();
    $pct = $proj['budget_clp'] > 0 ? round(($pag / $proj['budget_clp']) * 100) : 0;
?>
<div class="card proyecto-card" data-estado="<?= $proj['status'] ?>" data-search="<?= strtolower(htmlspecialchars($proj['name'].' '.$proj['client_name'])) ?>">
  <div class="card-header" style="cursor:pointer" onclick="toggleProyecto('proj-<?= $proj['id'] ?>')">
    <div>
      <h2 style="font-size:1.1rem"><?= htmlspecialchars($proj['name']) ?></h2>
      <span style="font-size:0.8rem;color:#64748b"><?= htmlspecialchars($proj['client_name']) ?> — $<?= number_format($proj['budget_clp']??0,0,',','.') ?></span>
    </div>
    <div style="display:flex;align-items:center;gap:0.75rem">
      <span class="status <?= $proj['status'] ?>"><?= $proj['status'] ?></span>
      <span style="font-size:0.8rem;color:#64748b"><?= $pct ?>%</span>
      <span style="font-size:0.8rem">▼</span>
    </div>
  </div>
  <div id="proj-<?= $proj['id'] ?>" style="display:none;margin-top:1rem">
    <!-- Mini stats -->
    <div class="stats-row" style="margin-bottom:1rem;grid-template-columns:repeat(4,1fr)">
      <div class="stat-box" style="padding:0.5rem 1rem"><div class="num" style="font-size:1rem;color:#16a34a">$<?= number_format($pag,0,',','.') ?></div><div class="label">Pagado</div></div>
      <div class="stat-box" style="padding:0.5rem 1rem"><div class="num" style="font-size:1rem;color:#ca8a04">$<?= number_format($pen,0,',','.') ?></div><div class="label">Pendiente</div></div>
      <div class="stat-box" style="padding:0.5rem 1rem"><div class="num" style="font-size:1rem;color:#dc2626">$<?= number_format($atr,0,',','.') ?></div><div class="label">Atrasado</div></div>
      <div class="stat-box" style="padding:0.5rem 1rem"><div class="num" style="font-size:1rem;color:#2563eb"><?= count($docTypes) ?> 📄</div><div class="label">Documentos</div></div>
    </div>
    
    <!-- Documentos por tipo -->
    <h4 style="margin-bottom:0.5rem;font-size:0.9rem;color:#475569">📄 Documentos</h4>
    <div class="doc-grid" style="margin-bottom:1rem">
      <?php 
      $tiposDoc = ['presupuesto'=>'📋 Presupuestos','plano'=>'📐 Planos','legal'=>'⚖️ Legales','avance'=>'📈 Avances','otro'=>'📎 Otros'];
      foreach ($tiposDoc as $t => $label):
        $st = $db->prepare('SELECT d.*, u.name as uploader FROM documents d JOIN app_users u ON u.id = d.uploaded_by WHERE d.project_id = ? AND d.type = ? ORDER BY d.uploaded_at DESC');
        $st->execute([$proj['id'], $t]);
        $items = $st->fetchAll();
      ?>
      <div style="border:1px solid #e2e8f0;border-radius:8px;padding:0.75rem">
        <strong style="font-size:0.8rem;color:#475569"><?= $label ?> (<?= count($items) ?>)</strong>
        <?php if (count($items)): ?>
          <?php foreach ($items as $doc): ?>
          <div style="display:flex;justify-content:space-between;padding:0.25rem 0;border-top:1px solid #f1f5f9;font-size:0.8rem">
            <span><?= htmlspecialchars($doc['title']) ?></span>
            <div>
              <?php if ($isStaff): ?>
              <button class="btn btn-sm btn-outline" style="font-size:0.7rem;padding:0.1rem 0.4rem" onclick="subirDoc(<?= $proj['id'] ?>, '<?= $t ?>')">+</button>
              <?php endif; ?>
              <a href="/uploads/<?= $doc['file_path'] ?>" target="_blank" style="font-size:0.7rem;color:#2563eb">📄</a>
            </div>
          </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div style="font-size:0.75rem;color:#94a3b8;padding:0.25rem 0;border-top:1px solid #f1f5f9">
            Sin documentos
            <?php if ($isStaff): ?>
            <button class="btn btn-sm btn-outline" style="font-size:0.7rem;padding:0.1rem 0.4rem" onclick="subirDoc(<?= $proj['id'] ?>, '<?= $t ?>')">+ Subir</button>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Pagos del proyecto -->
    <h4 style="margin-bottom:0.5rem;font-size:0.9rem;color:#475569">💰 Pagos</h4>
    <?php
    $pays = $db->prepare('SELECT * FROM payments WHERE project_id = ? ORDER BY due_date ASC');
    $pays->execute([$proj['id']]); $payments = $pays->fetchAll();
    ?>
    <?php if (count($payments)): ?>
    <table style="font-size:0.8rem">
      <tr><th>Concepto</th><th>Monto</th><th>Vence</th><th>Pagado</th><th>Estado</th></tr>
      <?php foreach ($payments as $py): 
        $sr = ($py['status'] === 'pendiente' && strtotime($py['due_date']) < time()) ? 'atrasado' : $py['status'];
      ?>
      <tr>
        <td><?= htmlspecialchars($py['concept']) ?></td>
        <td>$<?= number_format($py['amount_clp'],0,',','.') ?></td>
        <td style="color:<?= $sr === 'atrasado' ? '#dc2626' : '#64748b' ?>"><?= $py['due_date'] ?></td>
        <td><?= $py['paid_at'] ?? '—' ?></td>
        <td><span class="status <?= $sr ?>" style="font-size:0.7rem"><?= $sr ?></span></td>
      </tr>
      <?php endforeach; ?>
    </table>
    <?php else: ?>
    <p style="font-size:0.8rem;color:#94a3b8">Sin pagos registrados.</p>
    <?php endif; ?>
    <?php if ($isStaff): ?>
    <button class="btn btn-sm btn-primary" style="margin-top:0.75rem" onclick="nuevoPago(<?= $proj['id'] ?>)">+ Agregar pago</button>
    <?php endif; ?>
  </div>
</div>
<?php endforeach; ?>
</div>

<script>
function toggleProyecto(id) {
  const el = document.getElementById(id);
  el.style.display = el.style.display === 'none' ? 'block' : 'none';
}
document.getElementById('searchProyectos')?.addEventListener('input', function() {
  const q = this.value.toLowerCase();
  document.querySelectorAll('.proyecto-card').forEach(c => {
    c.style.display = c.dataset.search.includes(q) ? '' : 'none';
  });
});
function filtrarProyectos() {
  const s = document.getElementById('filterEstado').value;
  document.querySelectorAll('.proyecto-card').forEach(c => {
    c.style.display = (!s || c.dataset.estado === s) ? '' : 'none';
  });
}
function nuevoProyecto() {
  openModal(`<h3>Nuevo proyecto</h3><form id="projForm">
    <label>Nombre</label><input name="name" required>
    <label>Cliente</label><select name="client_id"><?php
    $cs = $db->query('SELECT id, name FROM clients ORDER BY name')->fetchAll();
    foreach ($cs as $cl): echo '<option value="'.$cl['id'].'">'.htmlspecialchars($cl['name']).'</option>'; endforeach;
    ?></select>
    <label>Presupuesto CLP $</label><input name="budget_clp" type="number">
    <label>Dirección</label><input name="address">
    <label>Estado</label><select name="status"><option value="activo">Activo</option><option value="pausado">Pausado</option><option value="finalizado">Finalizado</option></select>
    <div class="modal-actions"><button type="button" class="btn btn-outline" onclick="closeModal()">Cancelar</button><button type="submit" class="btn btn-primary">Crear</button></div></form>
    <script>document.getElementById("projForm")?.addEventListener("submit", async (e) => {
      e.preventDefault();
      const fd = new FormData(e.target);
      const body = Object.fromEntries(fd); body.budget_clp = parseFloat(body.budget_clp) || 0; body.client_id = parseInt(body.client_id);
      const res = await fetch("/api/projects.php?action=create", {method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify(body)});
      const d = await res.json();
      if(d.ok) { showToast("Proyecto creado"); closeModal(); loadTab("proyectos"); }
      else showToast(d.error, "error");
    });<\/script>`);
}
function subirDoc(pid, type) {
  openModal(`<h3>Subir documento</h3><form id="docForm" enctype="multipart/form-data">
    <input type="hidden" name="project_id" value="${pid}">
    <input type="hidden" name="type" value="${type}">
    <label>Título</label><input name="title" required>
    <label>Archivo</label><input name="file" type="file" required>
    <div class="modal-actions"><button type="button" class="btn btn-outline" onclick="closeModal()">Cancelar</button><button type="submit" class="btn btn-primary">Subir</button></div></form>
    <script>document.getElementById("docForm")?.addEventListener("submit", async (e) => {
      e.preventDefault();
      const fd = new FormData(e.target);
      const res = await fetch("/api/documentos.php?action=upload", {method:"POST",body:fd});
      const d = await res.json();
      if(d.ok) { showToast("Documento subido"); closeModal(); loadTab("proyectos"); }
      else showToast(d.error, "error");
    });<\/script>`);
}
function nuevoPago(pid) {
  fetch('/api/pagos/form.php?project_id=' + pid).then(r=>r.text()).then(html=>openModal(html));
}
</script>
