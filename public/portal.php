<?php
require_once __DIR__ . '/../api/config.php';
$auth = require_auth();
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
    .sidebar-nav { flex: 1; padding: 0.75rem; display: flex; flex-direction: column; gap: 0.25rem; overflow-y: auto; }
    .nav-item { display: flex; align-items: center; gap: 0.75rem; padding: 0.7rem 0.75rem; border-radius: 8px; cursor: pointer; font-size: 0.9rem; font-weight: 500; color: #475569; transition: all 0.15s; text-decoration: none; }
    .nav-item:hover { background: #f8fafc; color: #0d9488; }
    .nav-item.active { background: #e0f2fe; color: #0f766e; font-weight: 600; }
    .main-area { flex: 1; display: flex; flex-direction: column; min-height: 100vh; }
    .main-header { background: #fff; padding: 1rem 2rem; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between; }
    .main-header h1 { font-size: 1.3rem; font-weight: 700; }
    .main-content { flex: 1; padding: 1.5rem 2rem; overflow-y: auto; }
    .loading { text-align: center; padding: 3rem; color: #64748b; }
    .btn { display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.5rem 1rem; border-radius: 8px; font-size: 0.85rem; font-weight: 500; cursor: pointer; border: 1px solid transparent; transition: all 0.15s; text-decoration: none; }
    .btn-primary { background: #0d9488; color: #fff; border-color: #0d9488; }
    .btn-primary:hover { background: #0f766e; }
    .btn-outline { background: #fff; color: #475569; border-color: #cbd5e1; }
    .btn-outline:hover { background: #f8fafc; border-color: #94a3b8; }
    .btn-sm { padding: 0.35rem 0.7rem; font-size: 0.8rem; }
    .sidebar-profile { padding: 1rem 1.25rem; display: flex; align-items: center; gap: 0.75rem; border-bottom: 1px solid #f1f5f9; }
    </style>
</head>
<body>
<aside class="sidebar">
  <div class="sidebar-logo"><div class="logo-icon">LA</div><span>Los Almendros</span></div>
  <div class="sidebar-profile"><div class="name" id="userName"></div></div>
  <nav class="sidebar-nav">
    <a class="nav-item active" data-tab="resumen" href="#resumen"><span>Resumen</span></a>
    <a class="nav-item" data-tab="proyectos" href="#proyectos"><span>Proyectos</span></a>
    <a class="nav-item" data-tab="avance" href="#avance"><span>Avance</span></a>
    <a class="nav-item" data-tab="clientes" href="#clientes"><span>Clientes</span></a>
    <a class="nav-item" data-tab="pagos" href="#pagos"><span>Pagos</span></a>
    <a class="nav-item" data-tab="documentos" href="#documentos"><span>Documentos</span></a>
    <a class="nav-item" data-tab="admin" href="#admin"><span>Admin</span></a>
    <a class="nav-item" href="/logout.php"><span>Cerrar sesión</span></a>
  </nav>
</aside>
<div class="main-area">
  <header class="main-header"><h1 id="pageTitle">Portal</h1></header>
  <div class="main-content" id="mainContent"><div class="loading">Cargando...</div></div>
</div>
<script>
const defaultTab = location.hash.replace('#','') || 'avance';
let currentTab = defaultTab

async function loadTab(tab) {
  currentTab = tab
  document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'))
  const navItem = document.querySelector(`[data-tab="${tab}"]`)
  if (navItem) navItem.classList.add('active')
  document.getElementById('pageTitle').textContent = tab.charAt(0).toUpperCase() + tab.slice(1)
  document.getElementById('mainContent').innerHTML = '<div class="loading">Cargando...</div>'
  try {
    const res = await fetch(`/api/${tab}.php`)
    const html = await res.text()
    document.getElementById('mainContent').innerHTML = html
    document.querySelectorAll('#mainContent script').forEach(old => {
      const ns = document.createElement('script')
      for (let attr of old.attributes) ns.setAttribute(attr.name, attr.value)
      ns.textContent = old.textContent
      old.parentNode.replaceChild(ns, old)
    })
  } catch(e) {
    document.getElementById('mainContent').innerHTML = '<div class="empty-state"><p>Error al cargar</p></div>'
  }
}
document.querySelectorAll('.nav-item[data-tab]').forEach(item => {
  item.addEventListener('click', e => {
    e.preventDefault()
    loadTab(item.dataset.tab)
    history.replaceState(null, '', '#' + item.dataset.tab)
  })
})

loadTab(defaultTab)
</script>
</body>
</html>
