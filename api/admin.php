<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';
session_start();
$userId = $_SESSION['user_id'] ?? null;
$userRole = $_SESSION['user_role'] ?? null;
$isAdmin = $userRole === 'admin';
if (!$isAdmin) { echo '<div class="empty-state"><div class="icon">🔒</div><p>Acceso solo para administradores</p></div>'; exit; }
$db = Database::get();
$action = $_GET['action'] ?? 'dashboard';
$in = json_decode(file_get_contents('php://input'), true) ?: [];

// ──────── Actions POST ────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  header('Content-Type: application/json');
  if ($action === 'create_user') {
    $email = trim($_POST['email'] ?? '');
    $pass = $_POST['password'] ?? '';
    $name = $_POST['name'] ?? '';
    $role = $_POST['role'] ?? 'staff';
    $clientId = $_POST['client_id'] ?? null;
    if (!$email || !$pass || !$name) { echo json_encode(['ok'=>false,'error'=>'Faltan datos']); exit; }
    $chk = $db->prepare('SELECT id FROM app_users WHERE email = ?');
    $chk->execute([$email]);
    if ($chk->fetch()) { echo json_encode(['ok'=>false,'error'=>'Email ya existe']); exit; }
    $hash = password_hash($pass, PASSWORD_DEFAULT);
    $db->prepare('INSERT INTO app_users (email,password_hash,role,name,client_id,force_password_change) VALUES (?,?,?,?,?,1)')
      ->execute([$email, $hash, $role, $name, $clientId ? (int)$clientId : null]);
    echo json_encode(['ok'=>true,'email'=>$email]); exit;
  }
  if ($action === 'update_user') {
    $uid = (int)($_POST['user_id'] ?? 0);
    if (!$uid) { echo json_encode(['ok'=>false,'error'=>'ID requerido']); exit; }
    if (isset($_POST['email'])) $db->prepare('UPDATE app_users SET email = ? WHERE id = ?')->execute([$_POST['email'], $uid]);
    if (isset($_POST['name'])) $db->prepare('UPDATE app_users SET name = ? WHERE id = ?')->execute([$_POST['name'], $uid]);
    if (isset($_POST['role'])) $db->prepare('UPDATE app_users SET role = ? WHERE id = ?')->execute([$_POST['role'], $uid]);
    if (isset($_POST['client_id'])) $db->prepare('UPDATE app_users SET client_id = ? WHERE id = ?')->execute([$_POST['client_id'] ? (int)$_POST['client_id'] : null, $uid]);
    if (!empty($_POST['password'])) {
      $hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
      $db->prepare('UPDATE app_users SET password_hash = ?, force_password_change = 1 WHERE id = ?')->execute([$hash, $uid]);
    }
    echo json_encode(['ok'=>true]); exit;
  }
  if ($action === 'delete_user') {
    $uid = (int)$_POST['user_id'];
    if ($uid === $userId) { echo json_encode(['ok'=>false,'error'=>'No puedes eliminarte a ti mismo']); exit; }
    $db->prepare('DELETE FROM app_users WHERE id = ?')->execute([$uid]);
    echo json_encode(['ok'=>true]); exit;
  }
  echo json_encode(['ok'=>false,'error'=>'Acción no válida']); exit;
}

// ──────── Interfaz HTML ────────
$users = $db->query('SELECT u.*, c.name as client_name FROM app_users u LEFT JOIN clients c ON c.id = u.client_id ORDER BY u.role, u.name')->fetchAll();
$clients = $db->query('SELECT id, name FROM clients ORDER BY name')->fetchAll();
?>
<div class="tab-panel" style="margin-bottom:1rem">
  <button class="btn btn-primary" onclick="crearUsuarioModal()">+ Nuevo usuario</button>
  <input type="text" id="filterUsers" placeholder="Buscar usuario..." oninput="filtrarUsuarios()" style="padding:0.5rem 0.75rem;border:1px solid #cbd5e1;border-radius:8px;font-size:0.85rem">
</div>

<div class="card">
  <table>
    <tr><th>Nombre</th><th>Email</th><th>Rol</th><th>Cliente</th><th>Último acceso</th><th>Activo</th><th>Acciones</th></tr>
    <?php foreach ($users as $u): ?>
    <tr class="user-row" data-search="<?= strtolower(htmlspecialchars($u['name'].' '.$u['email'])) ?>">
      <td><strong><?= htmlspecialchars($u['name']) ?></strong></td>
      <td><?= htmlspecialchars($u['email']) ?></td>
      <td><span class="status" style="background:<?= $u['role']==='admin'?'#fecaca':'#dbeafe' ?>;color:<?= $u['role']==='admin'?'#7f1d1d':'#1d4ed8' ?>"><?= $u['role'] ?></span></td>
      <td><?= htmlspecialchars($u['client_name'] ?? '—') ?></td>
      <td><?= $u['last_login_at'] ?? 'Nunca' ?></td>
      <td><?= $u['active'] ? '✅' : '❌' ?></td>
      <td>
        <button class="btn btn-sm btn-outline" onclick="editarUsuarioModal(<?= $u['id'] ?>)">Editar</button>
        <button class="btn btn-sm btn-danger" onclick="eliminarUsuario(<?= $u['id'] ?>)">Eliminar</button>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>

<script>
// Datos para selects en modales
const clients = [
  <?php foreach ($clients as $c): ?>
  {id: <?= $c['id'] ?>, name: '<?= htmlspecialchars($c['name'], ENT_QUOTES) ?>'},
  <?php endforeach; ?>
];

function filtrarUsuarios() {
  const q = document.getElementById('filterUsers').value.toLowerCase();
  document.querySelectorAll('.user-row').forEach(r => {
    r.style.display = r.dataset.search.includes(q) ? '' : 'none';
  });
}

function crearUsuarioModal() {
  const opts = clients.map(c => `<option value="${c.id}">${c.name}</option>`).join('');
  openModal(`<h3>Nuevo usuario</h3>
    <form id="userForm">
      <label>Nombre</label><input name="name" required>
      <label>Email</label><input name="email" type="email" required>
      <label>Contraseña temporal</label><input name="password" type="password" required minlength="6">
      <label>Rol</label><select name="role"><option value="admin">Administrador</option><option value="staff" selected>Staff</option><option value="client">Cliente</option></select>
      <label>Asignar a cliente</label><select name="client_id"><option value="">Sin cliente</option>${opts}</select>
      <div class="modal-actions">
        <button type="button" class="btn btn-outline" onclick="closeModal()">Cancelar</button>
        <button type="submit" class="btn btn-primary">Crear</button>
      </div>
    </form>`);
  document.getElementById('userForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(e.target);
    fd.set('action', 'create_user');
    const res = await fetch('/api/admin.php', {method:'POST', body: new URLSearchParams(fd).toString(), headers: {'Content-Type':'application/x-www-form-urlencoded'}});
    const d = await res.json();
    if (d.ok) { showToast('Usuario creado: '+d.email); closeModal(); loadTab('admin'); }
    else showToast(d.error, 'error');
  });
}

function editarUsuarioModal(uid) {
  const row = document.querySelector(`.user-row:nth-child(${uid+1})`); // aproximación
  openModal(`<h3>Editar usuario</h3>
    <form id="editUserForm">
      <input type="hidden" name="user_id" value="${uid}">
      <label>Nombre</label><input name="name">
      <label>Email</label><input name="email" type="email">
      <label>Nueva contraseña (dejar vacío para no cambiar)</label><input name="password" type="password" minlength="6">
      <label>Rol</label><select name="role"><option value="admin">Administrador</option><option value="staff">Staff</option><option value="client">Cliente</option></select>
      <label>Asignar a cliente</label><select name="client_id"><option value="">Sin cliente</option>${clients.map(c => `<option value="${c.id}">${c.name}</option>`).join('')}</select>
      <div class="modal-actions">
        <button type="button" class="btn btn-outline" onclick="closeModal()">Cancelar</button>
        <button type="submit" class="btn btn-primary">Guardar</button>
      </div>
    </form>`);
  document.getElementById('editUserForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(e.target);
    fd.set('action', 'update_user');
    const res = await fetch('/api/admin.php', {method:'POST', body: new URLSearchParams(fd).toString(), headers: {'Content-Type':'application/x-www-form-urlencoded'}});
    const d = await res.json();
    if (d.ok) { showToast('Usuario actualizado'); closeModal(); loadTab('admin'); }
    else showToast(d.error, 'error');
  });
}

function eliminarUsuario(id) {
  if (!confirm('¿Eliminar usuario?')) return;
  fetch('/api/admin.php', {method:'POST', body: new URLSearchParams({action:'delete_user',user_id:id}).toString(), headers: {'Content-Type':'application/x-www-form-urlencoded'}})
    .then(r=>r.json()).then(d=>{ if(d.ok){ showToast('Usuario eliminado'); loadTab('admin'); } else showToast(d.error,'error'); });
}
</script>
