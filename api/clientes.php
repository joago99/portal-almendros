<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';
session_start();
$userId = $_SESSION['user_id'] ?? null;
$userRole = $_SESSION['user_role'] ?? null;
if (!$userId || !in_array($userRole, ['admin','staff'])) { echo '<div class="empty-state"><div class="icon">🔒</div><p>Acceso solo para staff</p></div>'; exit; }
$db = Database::get();

$search = $_GET['q'] ?? '';
$clients = $db->query('SELECT c.*, 
    (SELECT COUNT(*) FROM projects WHERE client_id = c.id) as proyectos,
    (SELECT COALESCE(SUM(budget_clp),0) FROM projects WHERE client_id = c.id) as presupuesto_total,
    (SELECT COALESCE(SUM(p.amount_clp),0) FROM payments p JOIN projects pr ON pr.id = p.project_id WHERE pr.client_id = c.id AND p.status = "pagado") as total_pagado,
    (SELECT COUNT(*) FROM payments p JOIN projects pr ON pr.id = p.project_id WHERE pr.client_id = c.id AND p.status = "atrasado") as atrasos
    FROM clients c ORDER BY c.created_at DESC')->fetchAll();
?>
<div class="search-bar">
  <input type="text" id="searchClientes" placeholder="Buscar cliente..." value="<?= htmlspecialchars($search) ?>">
  <?php if ($userRole === 'admin'): ?>
  <button class="btn btn-primary" onclick="nuevoClienteModal()">+ Nuevo cliente</button>
  <?php endif; ?>
</div>

<div class="card">
  <table id="clientesTable">
    <tr><th>Nombre</th><th>Email</th><th>RUT</th><th>Teléfono</th><th>Proyectos</th><th>Presupuesto</th><th>Pagado</th><th>Atrasos</th><th></th></tr>
    <?php foreach ($clients as $c): ?>
    <tr class="cliente-row" data-search="<?= strtolower(htmlspecialchars($c['name'].' '.$c['email'])) ?>">
      <td><strong><?= htmlspecialchars($c['name']) ?></strong></td>
      <td><?= htmlspecialchars($c['email'] ?? '—') ?></td>
      <td><?= htmlspecialchars($c['rut'] ?? '—') ?></td>
      <td><?= htmlspecialchars($c['phone'] ?? '—') ?></td>
      <td><?= $c['proyectos'] ?></td>
      <td>$<?= number_format($c['presupuesto_total']??0,0,',','.') ?></td>
      <td style="color:#16a34a">$<?= number_format($c['total_pagado']??0,0,',','.') ?></td>
      <td><?= $c['atrasos'] > 0 ? '<span class="status atrasado">'.$c['atrasos'].'</span>' : '0' ?></td>
      <td>
        <div class="btn-group">
          <button class="btn btn-sm btn-outline" onclick="obrasCliente(<?= $c['id'] ?>)">🔍 Obras</button>
          <?php if ($userRole === 'admin'): ?>
          <button class="btn btn-sm btn-outline" onclick="editarClienteModal(<?= $c['id'] ?>)">Editar</button>
          <button class="btn btn-sm btn-danger" onclick="eliminarCliente(<?= $c['id'] ?>)">Eliminar</button>
          <?php endif; ?>
          <?php if ($userRole === 'admin' && $c['email']): ?>
          <button class="btn btn-sm btn-primary" onclick="darAccesoModal(<?= $c['id'] ?>, '<?= htmlspecialchars($c['email']) ?>', '<?= htmlspecialchars($c['name']) ?>')">🔑 Acceso</button>
          <?php endif; ?>
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>

<script>
document.getElementById('searchClientes')?.addEventListener('input', function() {
  const q = this.value.toLowerCase();
  document.querySelectorAll('.cliente-row').forEach(r => r.style.display = r.dataset.search.includes(q) ? '' : 'none');
});

function nuevoClienteModal() {
  openModal(`<h3>Nuevo cliente</h3>
    <form id="clientForm" onsubmit="return crearCliente(this)">
      <label>Nombre</label><input name="name" required>
      <label>Email</label><input name="email" type="email">
      <label>RUT</label><input name="rut">
      <label>Teléfono</label><input name="phone">
      <div class="modal-actions">
        <button type="button" class="btn btn-outline" onclick="closeModal()">Cancelar</button>
        <button type="submit" class="btn btn-primary">Guardar</button>
      </div>
    </form>`);
}
async function crearCliente(form) {
  const fd = new FormData(form);
  const body = Object.fromEntries(fd);
  const res = await fetch('/api/clientes_backend.php?action=create', {method:'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(body)});
  const d = await res.json();
  if (d.ok) { showToast('Cliente creado ✅'); closeModal(); loadTab('clientes'); return false; }
  else { showToast(d.error, 'error'); return false; }
}

async function editarClienteModal(id) {
  const res = await fetch('/api/clientes_backend.php?action=get&id=' + id);
  const c = await res.json();
  if (!c || c.ok === false) { showToast('Error al cargar cliente', 'error'); return; }
  openModal(`<h3>Editar cliente</h3>
    <form id="editClientForm" onsubmit="return editarCliente(this)">
      <input type="hidden" name="id" value="${c.id}">
      <label>Nombre</label><input name="name" value="${(c.name||'').replace(/"/g,'&quot;')}" required>
      <label>Email</label><input name="email" type="email" value="${(c.email||'').replace(/"/g,'&quot;')}">
      <label>RUT</label><input name="rut" value="${(c.rut||'').replace(/"/g,'&quot;')}">
      <label>Teléfono</label><input name="phone" value="${(c.phone||'').replace(/"/g,'&quot;')}">
      <div class="modal-actions">
        <button type="button" class="btn btn-outline" onclick="closeModal()">Cancelar</button>
        <button type="submit" class="btn btn-primary">Guardar</button>
      </div>
    </form>`);
}
async function editarCliente(form) {
  const fd = new FormData(form);
  fd.set('action', 'update');
  const res = await fetch('/api/clientes_backend.php', {method:'POST', body: new URLSearchParams(fd).toString(), headers: {'Content-Type':'application/x-www-form-urlencoded'}});
  const d = await res.json();
  if (d.ok) { showToast('Cliente actualizado ✅'); closeModal(); loadTab('clientes'); return false; }
  else { showToast(d.error, 'error'); return false; }
}

function eliminarCliente(id) {
  if (!confirm('¿Eliminar este cliente? Los proyectos quedarán sin cliente asignado.')) return;
  fetch('/api/clientes_backend.php', {method:'POST', body: new URLSearchParams({action:'delete',id}).toString(), headers: {'Content-Type':'application/x-www-form-urlencoded'}})
    .then(r=>r.json()).then(d=>{ if(d.ok){ showToast('Cliente eliminado'); loadTab('clientes'); } else showToast(d.error,'error'); });
}

function obrasCliente(id) {
  fetch('/api/proyectos.php?client_id=' + id)
    .then(r => r.text())
    .then(html => {
      openModal('<h3>Obras del cliente</h3><div style="max-height:70vh;overflow:auto">' + html + '</div>');
    });
}

function darAccesoModal(id, email, name) {
  openModal(`<h3>Gestionar acceso: ${name}</h3>
    <p style="font-size:0.85rem;color:#64748b;margin-bottom:1rem">Email: ${email}</p>
    <form id="accessForm" onsubmit="return crearAccesoCliente(this)">
      <input type="hidden" name="client_id" value="${id}">
      <label>Nombre de usuario</label><input name="name" value="${name}">
      <label>Contraseña temporal</label><input name="password" type="password" required minlength="6">
      <div class="modal-actions">
        <button type="button" class="btn btn-outline" onclick="closeModal()">Cancelar</button>
        <button type="submit" class="btn btn-primary">Crear acceso</button>
      </div>
    </form>`);
}
async function crearAccesoCliente(form) {
  const fd = new FormData(form);
  const body = Object.fromEntries(fd);
  body.email = body.email || '';
  const res = await fetch('/api/clientes_backend.php?action=create_user', {method:'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(body)});
  const d = await res.json();
  if (d.ok) { showToast('Acceso creado: '+d.email); closeModal(); return false; }
  else { showToast(d.error, 'error'); return false; }
}
</script>
