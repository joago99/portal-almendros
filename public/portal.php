<?php
require_once __DIR__ . '/../api/config.php';
$auth = require_auth();
$userRole = $auth['role'];
$userId = $auth['user_id'];
$userName = $_SESSION['user_name'] ?? 'Usuario';
$isAdmin = $userRole === 'admin';
$isStaff = $userRole === 'staff';
$isClient = $userRole === 'client';
$defaultTab = $isClient ? 'proyectos' : 'resumen';
$db = Database::get();
$atrasados = 0;
if ($isAdmin || $isStaff) {
  $r = $db->query('SELECT COUNT(*) as cnt FROM payments WHERE status = "pendiente" AND due_date < date("now")')->fetch();
  $atrasados = (int)$r['cnt'];
}
header('Content-Type: text/html; charset=utf-8');
?><!DOCTYPE html>
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
    body { font-family: 'Inter', -apple-system, sans-serif; background: #f0f4f8; color: #1e293b; display: flex; min-height: 100vh; }
    .sidebar { width: 260px; background: #fff; border-right: 1px solid #e2e8f0; display: flex; flex-direction: column; height: 100vh; position: sticky; top: 0; flex-shrink: 0; }
    .sidebar-logo { padding: 1.5rem 1.25rem; display: flex; align-items: center; gap: 0.75rem; border-bottom: 1px solid #f1f5f9; }
    .sidebar-logo .logo-icon { width: 36px; height: 36px; background: #0d9488; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 700; font-size: 1.1rem; }
    .sidebar-logo span { font-weight: 700; font-size: 1.15rem; color: #0d9488; letter-spacing: 0.08em; text-transform: uppercase; }
    .sidebar-profile { padding: 1rem 1.25rem; display: flex; align-items: center; gap: 0.75rem; border-bottom: 1px solid #f1f5f9; }
    .sidebar-profile .avatar { width: 32px; height: 32px; border-radius: 50%; background: #ccfbf1; display: flex; align-items: center; justify-content: center; color: #0f766e; font-weight: 700; font-size: 0.9rem; }
    .sidebar-profile .name { font-size: 0.9rem; font-weight: 600; flex: 1; color: #0f172a; }
    .sidebar-profile .role-badge { font-size: 0.65rem; text-transform: uppercase; background: #dbeafe; color: #1d4ed8; padding: 0.15rem 0.4rem; border-radius: 4px; font-weight: 700; }
    .sidebar-nav { flex: 1; padding: 0.75rem; display: flex; flex-direction: column; gap: 0.25rem; overflow-y: auto; }
    .nav-item { display: flex; align-items: center; gap: 0.75rem; padding: 0.7rem 0.75rem; border-radius: 8px; cursor: pointer; font-size: 0.9rem; font-weight: 500; color: #475569; transition: all 0.15s; text-decoration: none; }
    .nav-item:hover { background: #f8fafc; color: #0d9488; }
    .nav-item.active { background: #e0f2fe; color: #0f766e; font-weight: 600; }
    .nav-item .dot { width: 8px; height: 8px; border-radius: 2px; flex-shrink: 0; }
    .nav-item .badge { margin-left: auto; background: #ef4444; color: #fff; font-size: 0.65rem; font-weight: 700; padding: 0.1rem 0.45rem; border-radius: 10px; min-width: 18px; text-align: center; }
    .nav-item.logout-link { margin-top: auto; color: #94a3b8; }
    .nav-item.logout-link:hover { color: #ef4444; }
    .main-area { flex: 1; display: flex; flex-direction: column; min-height: 100vh; }
    .main-header { background: #fff; padding: 1rem 2rem; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between; }
    .main-header h1 { font-size: 1.3rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.02em; }
    .main-header .header-actions { display: flex; gap: 0.75rem; align-items: center; }
    .main-content { flex: 1; padding: 1.5rem 2rem; overflow-y: auto; }
    .loading { text-align: center; padding: 3rem; color: #64748b; }
    .card { background: #fff; border-radius: 10px; border: 1px solid #e2e8f0; padding: 1.25rem; margin-bottom: 1rem; }
    .search-bar { display: flex; gap: 0.75rem; margin-bottom: 1rem; flex-wrap: wrap; }
    .search-bar input, .search-bar select { padding: 0.5rem 0.75rem; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.85rem; font-family: inherit; outline: none; }
    .search-bar input:focus, .search-bar select:focus { border-color: #0d9488; box-shadow: 0 0 0 3px rgba(13,148,136,0.1); }
    .search-bar input { min-width: 220px; }
    .stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
    .status { display: inline-block; padding: 0.15rem 0.5rem; border-radius: 20px; font-size: 0.75rem; font-weight: 600; }
    .status.activo { background: #dcfce7; color: #14532d; }
    .status.pausado { background: #fef9c3; color: #713f12; }
    .status.finalizado { background: #e2e8f0; color: #334155; }
    .status.pagado { background: #dcfce7; color: #14532d; }
    .status.pendiente { background: #fef9c3; color: #713f12; }
    .status.atrasado { background: #fecaca; color: #7f1d1d; }
    .btn { display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.5rem 1rem; border-radius: 8px; font-size: 0.85rem; font-weight: 500; cursor: pointer; border: 1px solid transparent; transition: all 0.15s; text-decoration: none; }
    .btn-primary { background: #0d9488; color: #fff; border-color: #0d9488; }
    .btn-primary:hover { background: #0f766e; }
    .btn-outline { background: #fff; color: #475569; border-color: #cbd5e1; }
    .btn-outline:hover { background: #f8fafc; border-color: #94a3b8; }
    .btn-sm { padding: 0.35rem 0.7rem; font-size: 0.8rem; }
    .btn-danger { background: #ef4444; color: #fff; }
    table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
    th { text-align: left; padding: 0.6rem 0.5rem; font-weight: 600; color: #64748b; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 2px solid #e2e8f0; }
    td { padding: 0.6rem 0.5rem; border-bottom: 1px solid #f1f5f9; }
    .modal-overlay { position: fixed; inset: 0; background: rgba(15,23,42,0.5); display: none; align-items: center; justify-content: center; z-index: 9999; padding: 1.5rem; }
    .modal-overlay.show { display: flex; }
    .modal-box { background: #fff; border-radius: 16px; padding: 1.5rem 2rem; width: 100%; max-width: 520px; max-height: 80vh; overflow-y: auto; box-shadow: 0 25px 60px rgba(0,0,0,0.15); }
    .modal-box h3 { font-size: 1.1rem; margin-bottom: 1rem; }
    .modal-box label { display: block; font-size: 0.8rem; font-weight: 600; color: #475569; margin-bottom: 0.25rem; margin-top: 0.5rem; }
    .modal-box input, .modal-box select, .modal-box textarea { width: 100%; padding: 0.5rem 0.75rem; border: 1px solid #cbd5e1; border-radius: 8px; font-family: inherit; font-size: 0.85rem; outline: none; }
    .modal-box input:focus, .modal-box select:focus, .modal-box textarea:focus { border-color: #0d9488; }
    .modal-actions { display: flex; gap: 0.5rem; justify-content: flex-end; margin-top: 1rem; }
    .toast { position: fixed; bottom: 2rem; left: 50%; transform: translateX(-50%); padding: 0.65rem 1.25rem; border-radius: 10px; font-size: 0.85rem; font-weight: 500; z-index: 99999; transition: all 0.2s; opacity: 0; pointer-events: none; }
    .toast.success { background: #0d9488; color: #fff; opacity: 1; }
    .toast.error { background: #ef4444; color: #fff; opacity: 1; }
    .empty-state { text-align: center; padding: 3rem 1rem; color: #64748b; }
    @media (max-width: 768px) {
      .sidebar { width: 100%; height: auto; position: relative; }
      body { flex-direction: column; }
      .main-header, .main-content { padding: 1rem; }
      .stats-row { grid-template-columns: 1fr 1fr; }
    }
  </style>
</head>
<body>
<aside class="sidebar">
  <div class="sidebar-logo"><div class="logo-icon">LA</div><span>Los Almendros</span></div>
  <div class="sidebar-profile">
    <div class="avatar"><?= strtoupper(substr($userName, 0, 1)) ?></div>
    <div class="name"><?= htmlspecialchars($userName) ?></div>
    <span class="role-badge"><?= $userRole === 'admin' ? 'Admin' : ($userRole === 'staff' ? 'Staff' : 'Cliente') ?></span>
  </div>
  <nav class="sidebar-nav">
    <a class="nav-item active" data-tab="resumen" href="#resumen"><span class="dot" style="background:#0d9488"></span>Resumen</a>
    <?php if ($isClient): ?><style>#tabResumen{display:none}.nav-item[data-tab="resumen"]{display:none}</style><?php endif; ?>
    <a class="nav-item" data-tab="proyectos" href="#proyectos"><span class="dot" style="background:#2563eb"></span>Proyectos</a>
    <a class="nav-item" data-tab="avance" href="#avance"><span class="dot" style="background:#059669"></span>Avance</a>
    <a class="nav-item" data-tab="clientes" href="#clientes" id="tabClientes"><span class="dot" style="background:#7c3aed"></span>Clientes</a>
    <?php if ($isClient): ?><style>#tabClientes,.nav-item[data-tab="clientes"]{display:none}</style><?php endif; ?>
    <a class="nav-item" data-tab="pagos" href="#pagos"><span class="dot" style="background:#ca8a04"></span>Pagos<?php if ($atrasados > 0 && ($isAdmin||$isStaff)): ?><span class="badge"><?= $atrasados ?></span><?php endif; ?></a>
    <a class="nav-item" data-tab="documentos" href="#documentos"><span class="dot" style="background:#0891b2"></span>Documentos</a>
    <?php if ($isAdmin): ?>
    <a class="nav-item" data-tab="admin" href="#admin" style="margin-top:0.5rem;border-top:1px solid #e2e8f0;padding-top:0.75rem"><span class="dot" style="background:#64748b"></span>Admin</a>
    <?php endif; ?>
    <a class="nav-item logout-link" onclick="logout()"><span class="dot" style="background:#94a3b8"></span>Cerrar sesión</a>
  </nav>
</aside>
<div class="main-area">
  <header class="main-header"><h1 id="pageTitle">Portal</h1><div class="header-actions"><button class="btn btn-outline btn-sm" onclick="logout()">Cerrar sesión</button></div></header>
  <div class="main-content" id="mainContent"><div class="loading">Cargando...</div></div>
</div>
<div class="modal-overlay" id="modalOverlay"><div class="modal-box"><div id="modalBody"></div></div></div>
<div class="toast" id="toast"></div>
<script>
const ROLE = '<?= $userRole ?>';
const IS_CLIENT = <?= json_encode($isClient) ?>;
const IS_STAFF = <?= json_encode($isStaff) ?>;
const IS_ADMIN = <?= json_encode($isAdmin) ?>;
let currentTab = '<?= $defaultTab ?>';
function showToast(msg, type='success') {
  const t=document.getElementById('toast'); t.textContent=msg; t.className='toast '+type;
  setTimeout(()=>{t.className='toast'},3000);
}
function closeModal(){document.getElementById('modalOverlay').classList.remove('show')}
document.getElementById('modalOverlay')?.addEventListener('click',e=>{if(e.target===e.currentTarget)closeModal()});
function openModal(html){
  document.getElementById('modalBody').innerHTML=html;
  document.getElementById('modalOverlay').classList.add('show');
  document.querySelectorAll('#modalBody script').forEach(old=>{
    const ns=document.createElement('script');
    for(let a of old.attributes) ns.setAttribute(a.name,a.value);
    ns.textContent=old.textContent; old.parentNode.replaceChild(ns,old);
  });
}
function logout(){fetch('/logout.php').then(()=>window.location.href='/');}
async function loadTab(tab){
  currentTab=tab;
  document.querySelectorAll('.nav-item').forEach(n=>n.classList.remove('active'));
  const ni=document.querySelector(`[data-tab="${tab}"]`);
  if(ni) ni.classList.add('active');
  const titles={resumen:'Resumen',proyectos:'Proyectos',avance:'Avance de Obra',clientes:'Clientes',pagos:'Pagos',documentos:'Documentos',admin:'Admin'};
  document.getElementById('pageTitle').textContent=titles[tab]||'Portal';
  document.getElementById('mainContent').innerHTML='<div class="loading">Cargando...</div>';
  try{
    const res=await fetch(`/api/${tab}.php`);
    const html=await res.text();
    document.getElementById('mainContent').innerHTML=html;
    document.querySelectorAll('#mainContent script').forEach(old=>{
      const ns=document.createElement('script');
      for(let a of old.attributes) ns.setAttribute(a.name,a.value);
      ns.textContent=old.textContent; old.parentNode.replaceChild(ns,old);
    });
  }catch(e){document.getElementById('mainContent').innerHTML='<div class="empty-state"><p>Error al cargar</p></div>';}
}
document.querySelectorAll('.nav-item[data-tab]').forEach(item=>{
  item.addEventListener('click',e=>{
    e.preventDefault();
    loadTab(item.dataset.tab);
    history.replaceState(null,'','#'+item.dataset.tab);
  });
});
loadTab('<?= $defaultTab ?>');
</script>
</body>
</html>
