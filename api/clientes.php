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
    (SELECT COUNT(*) FROM payments p JOIN projects pr ON pr.id = p.project_id WHERE pr.client_id = c.id AND p.status = "atrasado") as atrasos
    FROM clients c ORDER BY c.created_at DESC')->fetchAll();
?>
<div class="search-bar">
  <input type="text" id="searchClientes" placeholder="Buscar cliente por nombre o email..." value="<?= htmlspecialchars($search) ?>">
  <?php if ($userRole === 'admin'): ?>
  <button class="btn btn-primary" onclick="openModal(`<h3>Nuevo cliente</h3><form id=\'clientForm\' class=\'crud-form\'><label>Nombre</label><input name=\'name\' required><label>Email</label><input name=\'email\' type=\'email\'><label>RUT</label><input name=\'rut\'><label>Teléfono</label><input name=\'phone\'><div class=\'modal-actions\'><button type=\'button\' class=\'btn btn-outline\' onclick=\'closeModal()\'>Cancelar</button><button type=\'submit\' class=\'btn btn-primary\'>Guardar</button></div></form><script>document.getElementById(\"clientForm\")?.addEventListener(\"submit\",async(e)=>{e.preventDefault();const fd=new FormData(e.target);const body=Object.fromEntries(fd);const res=await fetch(\"/api/clientes_backend.php?action=create\",{method:\"POST\",headers:{\"Content-Type\":\"application/json\"},body:JSON.stringify(body)});const d=await res.json();if(d.ok){showToast(\"Cliente creado\");closeModal();loadTab(\"clientes\")}else showToast(d.error,\"error\")});<\/script>`)">+ Nuevo cliente</button>
  <?php endif; ?>
</div>

<div class="card">
  <table id="clientesTable">
    <tr><th>Nombre</th><th>Email</th><th>RUT</th><th>Teléfono</th><th>Proyectos</th><th>Atrasos</th><th>Acciones</th></tr>
    <?php foreach ($clients as $c): ?>
    <tr class="cliente-row" data-search="<?= strtolower(htmlspecialchars($c['name'].' '.$c['email'])) ?>">
      <td><strong><?= htmlspecialchars($c['name']) ?></strong></td>
      <td><?= htmlspecialchars($c['email'] ?? '—') ?></td>
      <td><?= htmlspecialchars($c['rut'] ?? '—') ?></td>
      <td><?= htmlspecialchars($c['phone'] ?? '—') ?></td>
      <td><?= $c['proyectos'] ?></td>
      <td><?= $c['atrasos'] > 0 ? '<span class="status atrasado">'.$c['atrasos'].'</span>' : '0' ?></td>
      <td>
        <div class="btn-group">
          <a href="#proyectos?client_id=<?= $c['id'] ?>" class="btn btn-sm btn-outline">Ver obras</a>
          <?php if ($userRole === 'admin' && $c['email']): ?>
          <button class="btn btn-sm btn-primary" onclick="darAcceso(<?= $c['id'] ?>, '<?= htmlspecialchars($c['email']) ?>', '<?= htmlspecialchars($c['name']) ?>')">🔑 Acceso</button>
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
  document.querySelectorAll('.cliente-row').forEach(r => {
    r.style.display = r.dataset.search.includes(q) ? '' : 'none';
  });
});

function darAcceso(id, email, name) {
  openModal(`<h3>Crear acceso para ${name}</h3>
    <form id="accessForm">
      <input type="hidden" name="client_id" value="${id}">
      <label>Email</label><input name="email" value="${email}" readonly>
      <label>Nombre</label><input name="name" value="${name}" readonly>
      <label>Contraseña temporal</label><input name="password" type="password" required minlength="6">
      <div class="modal-actions">
        <button type="button" class="btn btn-outline" onclick="closeModal()">Cancelar</button>
        <button type="submit" class="btn btn-primary">Crear acceso</button>
      </div>
    </form>
    <script>
    document.getElementById("accessForm")?.addEventListener("submit", async (e) => {
      e.preventDefault();
      const fd = new FormData(e.target);
      const body = Object.fromEntries(fd);
      const res = await fetch("/api/clientes_backend.php?action=create_user", {method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify(body)});
      const d = await res.json();
      if(d.ok) { showToast("Acceso creado: " + d.email); closeModal(); }
      else showToast(d.error, "error");
    });
    <\/script>`);
}
</script>
