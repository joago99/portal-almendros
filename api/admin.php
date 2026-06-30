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

// ─── POST actions ───
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  header('Content-Type: application/json');
  if ($action === 'create_user') {
    $email = trim($_POST['email'] ?? '');
    $pass = $_POST['password'] ?? '';
    $name = $_POST['name'] ?? '';
    $role = $_POST['role'] ?? 'staff';
    $clientId = $_POST['client_id'] ?? null;
    $expires = $_POST['expires_at'] ?? null;
    if (!$email || !$pass || !$name) { echo json_encode(['ok'=>false,'error'=>'Faltan datos']); exit; }
    $chk = $db->prepare('SELECT id FROM app_users WHERE email = ?');
    $chk->execute([$email]);
    if ($chk->fetch()) { echo json_encode(['ok'=>false,'error'=>'Email ya existe']); exit; }
    $hash = password_hash($pass, PASSWORD_DEFAULT);
    $db->prepare('INSERT INTO app_users (email,password_hash,role,name,client_id,force_password_change,expires_at) VALUES (?,?,?,?,?,?,?)')
      ->execute([$email, $hash, $role, $name, $clientId ? (int)$clientId : null, 1, $expires ?: null]);
    echo json_encode(['ok'=>true,'email'=>$email]); exit;
  }
  if ($action === 'update_user') {
    $uid = (int)($_POST['user_id'] ?? 0);
    if (!$uid) { echo json_encode(['ok'=>false,'error'=>'ID requerido']); exit; }
    if (isset($_POST['email'])) $db->prepare('UPDATE app_users SET email = ? WHERE id = ?')->execute([$_POST['email'], $uid]);
    if (isset($_POST['name'])) $db->prepare('UPDATE app_users SET name = ? WHERE id = ?')->execute([$_POST['name'], $uid]);
    if (isset($_POST['role'])) $db->prepare('UPDATE app_users SET role = ? WHERE id = ?')->execute([$_POST['role'], $uid]);
    if (isset($_POST['client_id'])) $db->prepare('UPDATE app_users SET client_id = ? WHERE id = ?')->execute([$_POST['client_id'] ? (int)$_POST['client_id'] : null, $uid]);
    if (isset($_POST['expires_at'])) $db->prepare('UPDATE app_users SET expires_at = ? WHERE id = ?')->execute([$_POST['expires_at'] ?: null, $uid]);
    if (!empty($_POST['password'])) {
      $hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
      $db->prepare('UPDATE app_users SET password_hash = ?, force_password_change = 1 WHERE id = ?')->execute([$hash, $uid]);
    }
    echo json_encode(['ok'=>true]); exit;
  }
  if ($action === 'toggle_active') {
    $uid = (int)$_POST['user_id'];
    if ($uid === $userId) { echo json_encode(['ok'=>false,'error'=>'No puedes desactivarte a ti mismo']); exit; }
    $st = $db->prepare('SELECT active FROM app_users WHERE id = ?');
    $st->execute([$uid]); $cur = $st->fetchColumn();
    $db->prepare('UPDATE app_users SET active = ? WHERE id = ?')->execute([$cur ? 0 : 1, $uid]);
    echo json_encode(['ok'=>true, 'active'=>!$cur]); exit;
  }
  if ($action === 'delete_user') {
    $uid = (int)$_POST['user_id'];
    if ($uid === $userId) { echo json_encode(['ok'=>false,'error'=>'No puedes eliminarte a ti mismo']); exit; }
    $db->prepare('DELETE FROM app_users WHERE id = ?')->execute([$uid]);
    echo json_encode(['ok'=>true]); exit;
  }
  echo json_encode(['ok'=>false,'error'=>'Acción no válida']); exit;
}

// ─── Dashboard stats ───
$totalUsers = $db->query('SELECT COUNT(*) FROM app_users')->fetchColumn();
$activeUsers = $db->query('SELECT COUNT(*) FROM app_users WHERE active = 1 AND (expires_at IS NULL OR expires_at >= date("now"))')->fetchColumn();
$expiredUsers = $db->query('SELECT COUNT(*) FROM app_users WHERE expires_at IS NOT NULL AND expires_at < date("now")')->fetchColumn();
$admins = $db->query('SELECT COUNT(*) FROM app_users WHERE role = "admin"')->fetchColumn();
$staffs = $db->query('SELECT COUNT(*) FROM app_users WHERE role = "staff"')->fetchColumn();
$clients = $db->query('SELECT COUNT(*) FROM app_users WHERE role = "client"')->fetchColumn();

$users = $db->query('SELECT u.*, c.name as client_name FROM app_users u LEFT JOIN clients c ON c.id = u.client_id ORDER BY u.active DESC, u.role, u.name')->fetchAll();
$allClients = $db->query('SELECT id, name FROM clients ORDER BY name')->fetchAll();
?>
<script>
const ADMIN_CLIENTS = <?= json_encode($allClients) ?>;
</script>

<div class="stats-row">
  <div class="stat-box"><div class="num" style="color:#0d9488"><?= $totalUsers ?></div><div class="label">Total usuarios</div></div>
  <div class="stat-box"><div class="num" style="color:#16a34a"><?= $activeUsers ?></div><div class="label">Activos</div></div>
  <div class="stat-box"><div class="num" style="color:#dc2626"><?= $expiredUsers ?></div><div class="label">Expirados</div></div>
  <div class="stat-box"><div class="num" style="color:#2563eb"><?= $admins ?></div><div class="label">Admins</div></div>
  <div class="stat-box"><div class="num"><?= $staffs ?></div><div class="label">Staff</div></div>
  <div class="stat-box"><div class="num"><?= $clients ?></div><div class="label">Clientes</div></div>
</div>

<div class="search-bar">
  <input type="text" id="filterUsers" placeholder="Buscar usuario..." oninput="filtrarAdmins()">
  <button class="btn btn-primary" onclick="crearUsuarioModal()">+ Nuevo usuario</button>
</div>

<div class="card">
  <table>
    <tr><th>Nombre</th><th>Email</th><th>Rol</th><th>Cliente</th><th>Expira</th><th>Último acceso</th><th>Activo</th><th></th></tr>
    <?php foreach ($users as $u): 
      $expired = $u['expires_at'] && $u['expires_at'] < date('Y-m-d');
      $status = $u['active'] ? ($expired ? 'expirado' : 'activo') : 'inactivo';
    ?>
    <tr class="admin-user-row" data-search="<?= strtolower(htmlspecialchars($u['name'].' '.$u['email'])) ?>">
      <td><strong><?= htmlspecialchars($u['name']) ?></strong></td>
      <td><?= htmlspecialchars($u['email']) ?></td>
      <td><span class="status" style="background:<?= $u['role']==='admin'?'#fecaca':'#dbeafe' ?>"><?= $u['role'] ?></span></td>
      <td style="font-size:0.8rem"><?= htmlspecialchars($u['client_name'] ?? '—') ?></td>
      <td style="font-size:0.8rem;color:<?= $expired?'#dc2626':'#64748b' ?>"><?= $u['expires_at'] ?? '—' ?></td>
      <td style="font-size:0.8rem"><?= $u['last_login_at'] ?? 'Nunca' ?></td>
      <td><?= $status === 'activo' ? '✅' : ($status === 'expirado' ? '⏰' : '❌') ?></td>
      <td>
        <button class="btn btn-sm btn-outline" onclick="editarUsuarioModal(<?= $u['id'] ?>)">Editar</button>
        <button class="btn btn-sm <?= $u['active'] ? 'btn-outline' : 'btn-primary' ?>" onclick="toggleActivo(<?= $u['id'] ?>)"><?= $u['active'] ? 'Desactivar' : 'Activar' ?></button>
        <button class="btn btn-sm btn-danger" onclick="eliminarAdminUser(<?= $u['id'] ?>)">Eliminar</button>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>

<script>
function filtrarAdmins() {
  const q = document.getElementById('filterUsers').value.toLowerCase();
  document.querySelectorAll('.admin-user-row').forEach(r => r.style.display = r.dataset.search.includes(q) ? '' : 'none');
}
function crearUsuarioModal() {
  const opts = ADMIN_CLIENTS.map(c => `<option value="${c.id}">${c.name}</option>`).join('');
  openModal(`<h3>Nuevo usuario</h3>
    <form id="admUserForm" onsubmit="return crearAdminUser(this)">
      <label>Nombre</label><input name="name" required>
      <label>Email</label><input name="email" type="email" required>
      <label>Contraseña temporal</label><input name="password" type="password" required minlength="6">
      <label>Rol</label><select name="role"><option value="admin">Admin</option><option value="staff" selected>Staff</option><option value="client">Cliente</option></select>
      <label>Asignar a cliente</label><select name="client_id"><option value="">Sin cliente</option>${opts}</select>
      <label>Fecha de caducidad</label><input name="expires_at" type="date">
      <div class="modal-actions">
        <button type="button" class="btn btn-outline" onclick="closeModal()">Cancelar</button>
        <button type="submit" class="btn btn-primary">Crear</button>
      </div>
    </form>`);
}
async function crearAdminUser(form) {
  const fd = new FormData(form);
  const res = await fetch('/api/admin.php?action=create_user', {method:'POST', body: new URLSearchParams(fd).toString(), headers: {'Content-Type':'application/x-www-form-urlencoded'}});
  const d = await res.json();
  if (d.ok) { showToast('Usuario creado: '+d.email); closeModal(); loadTab('admin'); return false; }
  else { showToast(d.error, 'error'); return false; }
}
async function editarUsuarioModal(uid) {
  const opts = ADMIN_CLIENTS.map(c => `<option value="${c.id}">${c.name}</option>`).join('');
  openModal(`<h3>Editar usuario</h3>
    <form id="admEditForm" onsubmit="return editarAdminUser(this)">
      <input type="hidden" name="user_id" value="${uid}">
      <label>Nombre</label><input name="name">
      <label>Email</label><input name="email" type="email">
      <label>Nueva contraseña (vacío = no cambiar)</label><input name="password" type="password" minlength="6">
      <label>Rol</label><select name="role"><option value="admin">Admin</option><option value="staff">Staff</option><option value="client">Cliente</option></select>
      <label>Asignar a cliente</label><select name="client_id"><option value="">Sin cliente</option>${opts}</select>
      <label>Fecha de caducidad</label><input name="expires_at" type="date">
      <div class="modal-actions">
        <button type="button" class="btn btn-outline" onclick="closeModal()">Cancelar</button>
        <button type="submit" class="btn btn-primary">Guardar</button>
      </div>
    </form>`);
}
async function editarAdminUser(form) {
  const fd = new FormData(form);
  const res = await fetch('/api/admin.php?action=update_user', {method:'POST', body: new URLSearchParams(fd).toString(), headers: {'Content-Type':'application/x-www-form-urlencoded'}});
  const d = await res.json();
  if (d.ok) { showToast('Usuario actualizado'); closeModal(); loadTab('admin'); return false; }
  else { showToast(d.error, 'error'); return false; }
}
function toggleActivo(uid) {
  fetch('/api/admin.php?action=toggle_active', {method:'POST', body: new URLSearchParams({user_id: uid}).toString(), headers: {'Content-Type':'application/x-www-form-urlencoded'}})
    .then(r=>r.json()).then(d=>{ if(d.ok){ showToast('Estado cambiado'); loadTab('admin'); } else showToast(d.error,'error'); });
}
function eliminarAdminUser(uid) {
  if (!confirm('¿Eliminar usuario?')) return;
  fetch('/api/admin.php?action=delete_user', {method:'POST', body: new URLSearchParams({user_id: uid}).toString(), headers: {'Content-Type':'application/x-www-form-urlencoded'}})
    .then(r=>r.json()).then(d=>{ if(d.ok){ showToast('Usuario eliminado'); loadTab('admin'); } else showToast(d.error,'error'); });
}
</script>
