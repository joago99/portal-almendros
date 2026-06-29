<?php
require_once __DIR__ . '/../api/db.php';
require_once __DIR__ . '/../api/config.php';

session_start();
$userId = $_SESSION['user_id'] ?? null;
$userRole = $_SESSION['user_role'] ?? null;
$userName = $_SESSION['user_name'] ?? null;

if (!$userId) {
    header('Location: /login.php');
    exit;
}

$db = Database::get();
$stmt = $db->prepare('SELECT force_password_change FROM app_users WHERE id = ?');
$stmt->execute([$userId]);
$row = $stmt->fetch();
$mustChange = $row && $row['force_password_change'];

// Contar atrasados para badge
$atrasados = 0;
if (in_array($userRole, ['admin', 'staff'])) {
    $r = $db->query('SELECT COUNT(*) as cnt FROM payments WHERE status = "pendiente" AND due_date < date("now")')->fetch();
    $atrasados = $r['cnt'] ?? 0;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Portal Construcciones Los Almendros</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
      font-family: 'Inter', -apple-system, sans-serif;
      background: #f0f4f8;
      color: #1e293b;
      display: flex;
      min-height: 100vh;
    }
    /* ─── Sidebar ─── */
    .sidebar {
      width: 260px;
      background: #fff;
      border-right: 1px solid #e2e8f0;
      display: flex;
      flex-direction: column;
      height: 100vh;
      position: sticky;
      top: 0;
      flex-shrink: 0;
    }
    .sidebar-logo {
      padding: 1.5rem 1.25rem;
      display: flex;
      align-items: center;
      gap: 0.75rem;
      border-bottom: 1px solid #f1f5f9;
    }
    .sidebar-logo .logo-icon {
      width: 36px; height: 36px;
      background: #0d9488;
      border-radius: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #fff;
      font-weight: 700;
      font-size: 1.1rem;
    }
    .sidebar-logo span {
      font-weight: 700;
      font-size: 1.15rem;
      color: #0d9488;
      letter-spacing: 0.08em;
      text-transform: uppercase;
    }
    .sidebar-profile {
      padding: 1rem 1.25rem;
      display: flex;
      align-items: center;
      gap: 0.75rem;
      border-bottom: 1px solid #f1f5f9;
      cursor: pointer;
    }
    .sidebar-profile .avatar {
      width: 32px; height: 32px;
      border-radius: 50%;
      background: #e0f2fe;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #0d9488;
      font-weight: 600;
      font-size: 0.85rem;
    }
    .sidebar-profile .name {
      font-size: 0.9rem;
      font-weight: 500;
      flex: 1;
    }
    .sidebar-profile .role-badge {
      font-size: 0.65rem;
      text-transform: uppercase;
      background: #dbeafe;
      color: #1d4ed8;
      padding: 0.15rem 0.4rem;
      border-radius: 4px;
      font-weight: 600;
    }
    .sidebar-nav {
      flex: 1;
      padding: 0.75rem 0.75rem;
      display: flex;
      flex-direction: column;
      gap: 0.25rem;
      overflow-y: auto;
    }
    .nav-item {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      padding: 0.7rem 0.75rem;
      border-radius: 8px;
      cursor: pointer;
      font-size: 0.9rem;
      font-weight: 500;
      color: #475569;
      transition: all 0.15s;
      text-decoration: none;
      position: relative;
    }
    .nav-item:hover {
      background: #f8fafc;
      color: #0d9488;
    }
    .nav-item.active {
      background: #e0f2fe;
      color: #0f766e;
      font-weight: 600;
    }
    .nav-item .icon {
      width: 20px; height: 20px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1rem;
      flex-shrink: 0;
    }
    .nav-item .badge {
      margin-left: auto;
      background: #ef4444;
      color: #fff;
      font-size: 0.65rem;
      font-weight: 700;
      padding: 0.1rem 0.45rem;
      border-radius: 10px;
      min-width: 18px;
      text-align: center;
    }
    /* ─── Main ─── */
    .main-area {
      flex: 1;
      display: flex;
      flex-direction: column;
      min-height: 100vh;
    }
    .main-header {
      background: #fff;
      padding: 1rem 2rem;
      border-bottom: 1px solid #e2e8f0;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    .main-header h1 {
      font-size: 1.3rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.02em;
    }
    .main-header .header-actions {
      display: flex;
      gap: 0.75rem;
      align-items: center;
    }
    .main-content {
      flex: 1;
      padding: 1.5rem 2rem;
      overflow-y: auto;
    }
    /* ─── Componentes ─── */
    .card {
      background: #fff;
      border-radius: 10px;
      border: 1px solid #e2e8f0;
      padding: 1.25rem;
      margin-bottom: 1rem;
    }
    .card-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 1rem;
    }
    .card-header h2 {
      font-size: 1rem;
      font-weight: 600;
    }
    .btn {
      display: inline-flex;
      align-items: center;
      gap: 0.35rem;
      padding: 0.5rem 1rem;
      border-radius: 8px;
      font-size: 0.85rem;
      font-weight: 500;
      cursor: pointer;
      border: 1px solid transparent;
      transition: all 0.15s;
      text-decoration: none;
    }
    .btn-primary {
      background: #0d9488;
      color: #fff;
      border-color: #0d9488;
    }
    .btn-primary:hover { background: #0f766e; }
    .btn-outline {
      background: #fff;
      color: #475569;
      border-color: #cbd5e1;
    }
    .btn-outline:hover { background: #f8fafc; border-color: #94a3b8; }
    .btn-sm { padding: 0.35rem 0.7rem; font-size: 0.8rem; }
    .btn-danger { background: #ef4444; color: #fff; border-color: #ef4444; }
    .btn-group { display: flex; gap: 0.5rem; flex-wrap: wrap; }

    table {
      width: 100%;
      border-collapse: collapse;
      font-size: 0.875rem;
    }
    th {
      text-align: left;
      padding: 0.6rem 0.5rem;
      font-weight: 600;
      color: #64748b;
      font-size: 0.75rem;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      border-bottom: 2px solid #e2e8f0;
    }
    td {
      padding: 0.6rem 0.5rem;
      border-bottom: 1px solid #f1f5f9;
    }
    tr:hover td { background: #f8fafc; }
    .search-bar {
      display: flex;
      gap: 0.75rem;
      margin-bottom: 1rem;
      flex-wrap: wrap;
    }
    .search-bar input, .search-bar select {
      padding: 0.5rem 0.75rem;
      border: 1px solid #cbd5e1;
      border-radius: 8px;
      font-size: 0.85rem;
      font-family: inherit;
      outline: none;
    }
    .search-bar input:focus, .search-bar select:focus { border-color: #0d9488; box-shadow: 0 0 0 3px rgba(13,148,136,0.1); }
    .search-bar input { min-width: 220px; }

    .status {
      display: inline-block;
      padding: 0.15rem 0.5rem;
      border-radius: 20px;
      font-size: 0.75rem;
      font-weight: 600;
    }
    .status.activo, .status.en_progreso { background: #dcfce7; color: #14532d; }
    .status.pausado { background: #fef9c3; color: #713f12; }
    .status.finalizado { background: #e2e8f0; color: #334155; }
    .status.pagado { background: #dcfce7; color: #14532d; }
    .status.pendiente { background: #fef9c3; color: #713f12; }
    .status.atrasado { background: #fecaca; color: #7f1d1d; }

    .stats-row {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
      gap: 1rem;
      margin-bottom: 1.5rem;
    }
    .stat-box {
      background: #fff;
      border: 1px solid #e2e8f0;
      border-radius: 10px;
      padding: 1rem 1.25rem;
    }
    .stat-box .num { font-size: 1.5rem; font-weight: 700; }
    .stat-box .label { font-size: 0.75rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; }

    .modal-overlay {
      position: fixed; inset: 0; background: rgba(0,0,0,0.4);
      display: none; align-items: center; justify-content: center; z-index: 1000;
    }
    .modal-overlay.show { display: flex; }
    .modal-box {
      background: #fff; border-radius: 12px; padding: 1.5rem 2rem;
      width: 100%; max-width: 500px; box-shadow: 0 20px 60px rgba(0,0,0,0.15);
    }
    .modal-box h3 { margin-bottom: 1rem; }
    .modal-box label { display: block; font-size: 0.85rem; font-weight: 500; margin-bottom: 0.25rem; color: #475569; }
    .modal-box input, .modal-box select, .modal-box textarea {
      width: 100%; padding: 0.5rem 0.75rem;
      border: 1px solid #cbd5e1; border-radius: 8px;
      margin-bottom: 0.75rem; font-family: inherit; font-size: 0.85rem;
    }
    .modal-box input:focus, .modal-box select:focus { border-color: #0d9488; box-shadow: 0 0 0 3px rgba(13,148,136,0.1); outline: none; }
    .modal-actions { display: flex; gap: 0.75rem; justify-content: flex-end; margin-top: 0.5rem; }

    .empty-state { text-align: center; padding: 3rem 1rem; color: #94a3b8; }
    .empty-state .icon { font-size: 2.5rem; margin-bottom: 0.5rem; }
    .empty-state p { font-size: 0.9rem; }

    .toast {
      position: fixed; bottom: 1.5rem; right: 1.5rem;
      padding: 0.75rem 1.25rem; border-radius: 10px;
      color: #fff; font-weight: 500; font-size: 0.9rem;
      z-index: 2000; display: none;
      box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    .toast.success { background: #16a34a; display: block; }
    .toast.error { background: #dc2626; display: block; }

    .doc-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 0.75rem; margin-top: 0.75rem; }
    .doc-card {
      background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;
      padding: 0.75rem; text-align: center; cursor: pointer;
      transition: all 0.15s;
    }
    .doc-card:hover { border-color: #0d9488; background: #f0fdfa; }
    .doc-card .doc-icon { font-size: 1.5rem; margin-bottom: 0.25rem; }
    .doc-card .doc-title { font-size: 0.8rem; font-weight: 500; }

    .loading { text-align: center; padding: 3rem; color: #94a3b8; font-size: 0.9rem; }

    @media (max-width: 768px) {
      .sidebar { width: 60px; }
      .sidebar .name, .sidebar .role-badge, .nav-item span:not(.icon) { display: none; }
      .main-content { padding: 1rem; }
    }
  </style>
</head>
<body>

<!-- Sidebar -->
<aside class="sidebar">
  <div class="sidebar-logo">
    <div class="logo-icon">LA</div>
    <span>Los Almendros</span>
  </div>
  <div class="sidebar-profile">
    <div class="avatar"><?= strtoupper(substr($userName, 0, 1)) ?></div>
    <div class="name"><?= htmlspecialchars($userName) ?></div>
    <span class="role-badge"><?= $userRole === 'admin' ? 'Admin' : ($userRole === 'staff' ? 'Staff' : 'Cliente') ?></span>
  </div>
  <nav class="sidebar-nav">
    <a class="nav-item active" data-tab="resumen" href="#resumen">
      <span class="icon">📊</span><span>Resumen</span>
    </a>
    <a class="nav-item" data-tab="proyectos" href="#proyectos">
      <span class="icon">🏗️</span><span>Proyectos</span>
    </a>
    <a class="nav-item" data-tab="clientes" href="#clientes">
      <span class="icon">👥</span><span>Clientes</span>
    </a>
    <a class="nav-item" data-tab="pagos" href="#pagos">
      <span class="icon">💰</span><span>Pagos</span>
      <?php if ($atrasados > 0 && in_array($userRole, ['admin','staff'])): ?>
        <span class="badge"><?= $atrasados ?></span>
      <?php endif; ?>
    </a>
    <a class="nav-item" data-tab="documentos" href="#documentos">
      <span class="icon">📄</span><span>Documentos</span>
    </a>
    <?php if ($mustChange): ?>
    <a class="nav-item" data-tab="password" href="#password" style="margin-top:auto;color:#dc2626">
      <span class="icon">🔐</span><span>Cambiar contraseña</span>
    </a>
    <?php endif; ?>
    <a class="nav-item" href="/logout.php" style="margin-top:auto;color:#94a3b8">
      <span class="icon">🚪</span><span>Cerrar sesión</span>
    </a>
  </nav>
</aside>

<!-- Main -->
<div class="main-area">
  <header class="main-header">
    <h1 id="pageTitle">Resumen</h1>
    <div class="header-actions">
      <button class="btn btn-outline btn-sm" id="btnLogout">Cerrar sesión</button>
    </div>
  </header>
  <div class="main-content" id="mainContent">
    <div class="loading">Cargando...</div>
  </div>
</div>

<!-- Modal genérico -->
<div class="modal-overlay" id="modalOverlay">
  <div class="modal-box" id="modalContent">
    <div id="modalBody"></div>
  </div>
</div>

<!-- Toast -->
<div class="toast" id="toast"></div>

<script>
const ROLE = '<?= $userRole ?>';
const MUST_CHANGE = <?= json_encode($mustChange) ?>;
let currentTab = 'resumen';

function showToast(msg, type = 'success') {
  const t = document.getElementById('toast');
  t.textContent = msg; t.className = 'toast ' + type;
  setTimeout(() => { t.className = 'toast'; }, 3000);
}

function closeModal() { document.getElementById('modalOverlay').classList.remove('show'); }
document.getElementById('modalOverlay').addEventListener('click', (e) => { if (e.target === e.currentTarget) closeModal(); });

function openModal(html) {
  document.getElementById('modalBody').innerHTML = html;
  document.getElementById('modalOverlay').classList.add('show');
}

async function loadTab(tab) {
  currentTab = tab;
  document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
  document.querySelector(`[data-tab="${tab}"]`)?.classList.add('active');
  
  const titles = { resumen: 'Resumen', proyectos: 'Proyectos', clientes: 'Clientes', pagos: 'Pagos', documentos: 'Documentos', password: 'Cambiar contraseña' };
  document.getElementById('pageTitle').textContent = titles[tab] || 'Portal';
  document.getElementById('mainContent').innerHTML = '<div class="loading">Cargando...</div>';

  try {
    const res = await fetch(`/api/${tab}.php`);
    const html = await res.text();
    document.getElementById('mainContent').innerHTML = html;
  } catch(e) {
    document.getElementById('mainContent').innerHTML = '<div class="empty-state"><div class="icon">⚠️</div><p>Error al cargar</p></div>';
  }
}

document.querySelectorAll('.nav-item[data-tab]').forEach(item => {
  item.addEventListener('click', (e) => {
    e.preventDefault();
    loadTab(item.dataset.tab);
    history.replaceState(null, '', '#' + item.dataset.tab);
  });
});

document.getElementById('btnLogout').addEventListener('click', async () => {
  await fetch('/logout.php');
  window.location.href = '/login.php';
});

// Hash-based routing
const tabFromHash = location.hash.replace('#', '');
if (tabFromHash && ['resumen','proyectos','clientes','pagos','documentos','password'].includes(tabFromHash)) {
  loadTab(tabFromHash);
} else {
  loadTab('resumen');
}
</script>
</body>
</html>
