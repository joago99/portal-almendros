<?php
require_once __DIR__ . '/../api/db.php';
require_once __DIR__ . '/../api/config.php';
session_start();
$user_id = $_SESSION['user_id'] ?? null;
$user_role = $_SESSION['user_role'] ?? null;
$user_name = $_SESSION['user_name'] ?? null;
if (!$user_id) {
  header('Location: /login.php');
  exit;
}
$client_id = $_SESSION['client_id'] ?? null;
$must_change = false;
if ($user_id) {
  $st = Database::get()->prepare('SELECT force_password_change FROM app_users WHERE id = ?');
  $st->execute([$user_id]);
  $must_change = (bool)($st->fetchColumn() ?: 0);
}
$atrasados = 0;
if (in_array($user_role, ['admin', 'staff'], true)) {
  $r = Database::get()->query('SELECT COUNT(*) FROM payments WHERE status = "pendiente" AND due_date < date("now")')->fetch();
  $atrasados = (int)($r[0] ?? 0);
}
$is_admin = $user_role === 'admin';
$is_staff = $user_role === 'staff';
$is_client = $user_role === 'client';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Portal Construcciones Los Almendros</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Playfair+Display:wght@500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
  <style>
    /* Brand: editorial blanco/negro, logo siempre negro */
    .sidebar-logo .logo-icon {
      background: #111111;
      color: #ffffff;
    }
    .sidebar-logo span {
      color: #111111;
      letter-spacing: 0.06em;
    }
    .sidebar-profile .role-badge {
      background: #f5f5f4;
      color: #333333;
      border: 1px solid rgba(17,17,17,0.08);
    }
    /* Nav links: limpios, sin pills */
    .nav-item {
      color: #57534e;
      border-radius: 16px;
    }
    .nav-item:hover {
      background: #fafafa;
      color: #111111;
    }
    .nav-item.active {
      background: #f5f5f4;
      color: #111111;
      font-weight: 600;
    }
    /* Header */
    .main-header h1 {
      font-family: 'Playfair Display', ui-serif, Georgia, serif;
    }
    /* Form controls inside modals */
    .modal-box input,
    .modal-box select,
    .modal-box textarea {
      background: #ffffff;
      border: 1px solid rgba(17,17,17,0.08);
      color: #333333;
    }
    .modal-box input:focus,
    .modal-box select:focus,
    .modal-box textarea:focus {
      border-color: #111111;
      box-shadow: 0 0 0 4px rgba(17,17,17,0.08);
      outline: none;
    }
  </style>
</head>
<body class="app">
  <aside class="sidebar">
    <div class="sidebar-logo">
      <div class="logo-icon">LA</div>
      <span>Los Almendros</span>
    </div>
    <div class="sidebar-profile">
      <div class="avatar"><?= strtoupper(substr((string)$user_name, 0, 1)) ?></div>
      <div class="name"><?= htmlspecialchars((string)$user_name) ?></div>
      <span class="role-badge"><?= $is_admin ? 'Admin' : ($is_staff ? 'Staff' : 'Cliente') ?></span>
    </div>
    <nav class="sidebar-nav">
      <?php if (!$is_client): ?>
        <a class="nav-item active" data-tab="resumen" href="#resumen" id="tabResumen"><span class="icon" style="width:8px;height:8px;border-radius:2px;background:#111111;display:inline-block;flex-shrink:0"></span><span>Resumen</span></a>
      <?php else: ?>
        <style>#tabResumen{display:none}</style>
      <?php endif; ?>
      <a class="nav-item" data-tab="proyectos" href="#proyectos"><span class="icon" style="width:8px;height:8px;border-radius:2px;background:#111111;display:inline-block;flex-shrink:0"></span><span>Proyectos</span></a>
      <a class="nav-item" data-tab="avance" href="#avance"><span class="icon" style="width:8px;height:8px;border-radius:2px;background:#111111;display:inline-block;flex-shrink:0"></span><span>Avance</span></a>
      <?php if (!$is_client): ?>
        <a class="nav-item" data-tab="clientes" href="#clientes" id="tabClientes"><span class="icon" style="width:8px;height:8px;border-radius:2px;background:#111111;display:inline-block;flex-shrink:0"></span><span>Clientes</span></a>
      <?php else: ?>
        <style>#tabClientes,.nav-item[data-tab="clientes"]{display:none}</style>
      <?php endif; ?>
      <a class="nav-item" data-tab="pagos" href="#pagos">
        <span class="icon" style="width:8px;height:8px;border-radius:2px;background:#111111;display:inline-block;flex-shrink:0"></span><span>Pagos</span>
        <?php if ($atrasados > 0): ?><span class="badge"><?= $atrasados ?></span><?php endif; ?>
      </a>
      <a class="nav-item" data-tab="documentos" href="#documentos"><span class="icon" style="width:8px;height:8px;border-radius:2px;background:#111111;display:inline-block;flex-shrink:0"></span><span>Documentos</span></a>
      <?php if (!$is_client): ?>
        <a class="nav-item" data-tab="cotizaciones" href="#cotizaciones"><span class="icon" style="width:8px;height:8px;border-radius:2px;background:#b45309;display:inline-block;flex-shrink:0"></span><span>Cotizaciones</span></a>
      <?php endif; ?>
      <?php if ($is_admin): ?>
        <a class="nav-item" data-tab="admin" href="#admin" style="margin-top:0.5rem;border-top:1px solid rgba(17,17,17,0.08);padding-top:0.75rem"><span class="icon" style="width:8px;height:8px;border-radius:2px;background:#111111;display:inline-block;flex-shrink:0"></span><span>Admin</span></a>
      <?php endif; ?>
      <?php if ($must_change): ?>
        <a class="nav-item" data-tab="password" href="#password" style="margin-top:auto;color:#111111"><span class="icon" style="width:8px;height:8px;border-radius:2px;background:#111111;display:inline-block;flex-shrink:0"></span><span>Cambiar contraseña</span></a>
      <?php endif; ?>
      <a class="nav-item" href="/logout.php" style="margin-top:auto;color:#57534e"><span class="icon" style="width:8px;height:8px;border-radius:2px;background:#57534e;display:inline-block;flex-shrink:0"></span><span>Cerrar sesión</span></a>
    </nav>
  </aside>

  <div class="main-area">
    <header class="main-header headerbar">
      <h1 id="pageTitle">Resumen</h1>
      <div class="header-actions">
        <button class="btn btn-outline btn-sm" onclick="logout()">Cerrar sesión</button>
      </div>
    </header>
    <div class="main-content" id="mainContent">
      <div class="loading">Cargando...</div>
    </div>
  </div>

  <div class="modal-overlay" id="modalOverlay">
    <div class="modal-box" id="modalContent">
      <div id="modalBody"></div>
    </div>
  </div>

  <div class="toast" id="toast"></div>

  <script>
    window.ROLE = '<?= $user_role ?>';
    window.MUST_CHANGE = <?= json_encode($must_change) ?>;
    window.CLIENT_ID = <?= json_encode($client_id) ?>;
    window.IS_CLIENT = <?= json_encode($is_client) ?>;
    let currentTab = 'resumen';

    function showToast(msg, type = 'success') {
      const t = document.getElementById('toast');
      t.textContent = msg;
      t.className = 'toast ' + type;
      setTimeout(() => { t.className = 'toast'; }, 3000);
    }

    function closeModal() {
      document.getElementById('modalOverlay').classList.remove('show');
    }
    document.getElementById('modalOverlay').addEventListener('click', (e) => {
      if (e.target === e.currentTarget) closeModal();
    });

    function openModal(html) {
      document.getElementById('modalBody').innerHTML = html;
      document.getElementById('modalOverlay').classList.add('show');
      document.querySelectorAll('#modalBody script').forEach(old => {
        const ns = document.createElement('script');
        for (const attr of old.attributes) ns.setAttribute(attr.name, attr.value);
        ns.textContent = old.textContent;
        old.parentNode.replaceChild(ns, old);
      });
    }

    function logout() {
      fetch('/logout.php').then(() => { window.location.href = '/login.php'; });
    }

    async function loadTab(tab) {
      currentTab = tab;
      document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
      const navItem = document.querySelector('.nav-item[data-tab="' + tab + '"]');
      if (navItem) navItem.classList.add('active');
      const titles = {
        resumen: 'Resumen',
        proyectos: 'Proyectos',
        avance: 'Avance de Obra',
        clientes: 'Clientes',
        pagos: 'Pagos',
        documentos: 'Documentos',
        cotizaciones: 'Cotizaciones',
        password: 'Cambiar contraseña',
        admin: 'Admin'
      };
      document.getElementById('pageTitle').textContent = titles[tab] || 'Portal';
      document.getElementById('mainContent').innerHTML = '<div class="loading">Cargando...</div>';
      try {
        let url = '/api/' + tab + '.php';
        if (window.IS_CLIENT && window.CLIENT_ID) {
          const sep = url.includes('?') ? '&' : '?';
          url += sep + 'client_id=' + window.CLIENT_ID;
        }
        const res = await fetch(url);
        const html = await res.text();
        document.getElementById('mainContent').innerHTML = html;
        document.querySelectorAll('#mainContent script').forEach(old => {
          const ns = document.createElement('script');
          for (const attr of old.attributes) ns.setAttribute(attr.name, attr.value);
          ns.textContent = old.textContent;
          old.parentNode.replaceChild(ns, old);
        });
      } catch (e) {
        document.getElementById('mainContent').innerHTML = '<div class="empty-state"><div class="icon">⚠️</div><p>Error al cargar</p></div>';
      }
    }

    // Navegación por tabs
    document.querySelectorAll('.nav-item[data-tab]').forEach(item => {
      item.addEventListener('click', (e) => {
        e.preventDefault();
        loadTab(item.dataset.tab);
        history.replaceState(null, '', '#' + item.dataset.tab);
      });
    });

    // Funciones globales proyectos
    function toggleProyecto(id) {
      const el = document.getElementById(id);
      if (el) el.style.display = el.style.display === 'none' ? 'block' : 'none';
    }
    async function crearProyecto(form) {
      const body = Object.fromEntries(new FormData(form));
      body.budget_clp = parseFloat(body.budget_clp) || 0;
      body.client_id = parseInt(body.client_id) || 0;
      const res = await fetch('/api/projects.php?action=create', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body)
      });
      const d = await res.json();
      if (d.ok) { showToast('Proyecto creado ✅'); closeModal(); loadTab('proyectos'); }
      else showToast(d.error, 'error');
    }
    async function editarProyectoEnviar(form) {
      const fd = new FormData(form);
      fd.set('action', 'update');
      fd.set('budget_clp', parseFloat(fd.get('budget_clp')) || 0);
      const res = await fetch('/api/projects.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams(fd).toString()
      });
      const d = await res.json();
      if (d.ok) { showToast('Proyecto actualizado ✅'); closeModal(); loadTab('proyectos'); }
      else showToast(d.error, 'error');
    }

    // Funciones globales pagos
    async function crearPago(form) {
      const fd = new FormData(form);
      fd.set('amount_clp', parseFloat(fd.get('amount_clp')) || 0);
      fd.set('project_id', parseInt(fd.get('project_id')) || 0);
      const res = await fetch('/api/pagos.php?action=create', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams(fd).toString()
      });
      const d = await res.json();
      if (d.ok) { showToast('Pago registrado ✅'); closeModal(); loadTab('pagos'); }
      else showToast(d.error, 'error');
    }
    async function editarPagoEnviar(form) {
      const fd = new FormData(form);
      fd.set('action', 'update');
      fd.set('amount_clp', parseFloat(fd.get('amount_clp')) || 0);
      const res = await fetch('/api/pagos.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams(fd).toString()
      });
      const d = await res.json();
      if (d.ok) { showToast('Pago actualizado ✅'); closeModal(); loadTab('pagos'); }
      else showToast(d.error, 'error');
    }
    async function eliminarPago(id) {
      if (!confirm('¿Eliminar este pago?')) return;
      await fetch('/api/pagos.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'delete', id })
      });
      showToast('Pago eliminado');
      loadTab('pagos');
    }

    // Avance modal unificado
    function openAvanceModal(projectId, eventId) {
      const pid = eventId ? (document.getElementById('avProyecto')?.value || projectId) : (projectId || document.getElementById('avProyecto')?.value);
      if (!pid) { alert('Selecciona una obra primero'); return; }
      const isEdit = !!eventId;
      const title = isEdit ? 'Editar avance' : 'Registrar avance';
      let data = {
        project_id: pid,
        title: '',
        description: '',
        event_date: new Date().toISOString().slice(0,10),
        percentage: 0,
        event_type: 'daily_log',
        team_present: '',
        weather_conditions: '',
        materials_used: '',
        incidents: ''
      };
      if (isEdit) {
        fetch('/api/progress.php?action=list&project_id=' + pid)
          .then(r => r.json()).then(d => {
            const ev = (d.data || []).find(x => x.id == eventId);
            if (!ev) return showToast('Error', 'error');
            data = {
              title: ev.title || '',
              description: ev.description || '',
              event_date: ev.event_date || data.event_date,
              percentage: ev.percentage || 0,
              event_type: ev.event_type || 'daily_log',
              team_present: ev.team_present || '',
              weather_conditions: ev.weather_conditions || '',
              materials_used: ev.materials_used || '',
              incidents: ev.incidents || ''
            };
            renderAvanceForm(pid, eventId, title, data);
          });
      } else {
        renderAvanceForm(pid, null, title, data);
      }
    }

    function renderAvanceForm(pid, eventId, title, values) {
      fetch('/api/progress.php?action=list&project_id=' + pid)
        .then(r => r.json()).then(projData => {
          if (!projData.ok) throw new Error(projData.error || 'Error de autenticación');
          const milestones = projData.milestones || [];
          const overallPct = projData.overall_pct || 0;
          const msLabels = {cimentacion:'Cimentación',albanileria:'Albañilería / OG',techumbre:'Techumbre',terminaciones:'Terminaciones',recepcion:'Recepción Municipal'};
          let msOptions = '';
          for (const m of milestones) {
            const disabled = m.completed ? ' disabled' : '';
            const suffix = m.completed ? ' ✓' : '';
            msOptions += `<option value="${m.milestone_type}"${disabled}${values.event_type===m.milestone_type?' selected':''}>${m.label} (${m.weight_pct}%)${suffix}</option>`;
          }
          openModal(`<h3>${title}${eventId ? '' : ' — ' + overallPct + '% completado'}</h3>
            <form id="frmAvanceUnificado" onsubmit="return saveAvanceForm(this, ${pid}, ${eventId || 'null'})">
              <input type="hidden" name="project_id" value="${pid}">
              ${eventId ? '<input type="hidden" name="id" value="' + eventId + '">' : ''}
              <label>Título</label>
              <input name="title" value="${(values.title || '').replace(/"/g,'&quot;')}" placeholder="Ej: Fundaciones listas" required>
              <label>Descripción</label>
              <textarea name="description" rows="2" placeholder="Qué se hizo hoy...">${values.description || ''}</textarea>
              <label>Fecha</label>
              <input type="date" name="event_date" value="${values.event_date}" required>
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem">
                <div>
                  <label>Tipo</label>
                  <select name="event_type" id="msType">
                    <option value="daily_log" ${values.event_type==='daily_log'?'selected':''}>Avance diario</option>
                    ${msOptions}
                  </select>
                </div>
                <div>
                  <label>% de avance (0-100)</label>
                  <input type="number" name="percentage" min="0" max="100" value="${values.percentage}">
                </div>
              </div>
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem">
                <div>
                  <label>Personal presente</label>
                  <input name="team_present" value="${(values.team_present || '').replace(/"/g,'&quot;')}" placeholder="Ej: 3 albañiles, 1 ayudante">
                </div>
                <div>
                  <label>Clima</label>
                  <select name="weather_conditions">
                    <option value="" ${!values.weather_conditions ? 'selected' : ''}>—</option>
                    <option value="soleado" ${values.weather_conditions==='soleado'?'selected':''}>Soleado</option>
                    <option value="nublado" ${values.weather_conditions==='nublado'?'selected':''}>Nublado</option>
                    <option value="lluvia" ${values.weather_conditions==='lluvia'?'selected':''}>Lluvia</option>
                    <option value="otro" ${values.weather_conditions==='otro'?'selected':''}>Otro</option>
                  </select>
                </div>
              </div>
              <label>Materiales usados</label>
              <input name="materials_used" value="${(values.materials_used || '').replace(/"/g,'&quot;')}" placeholder="Ej: 200 ladrillos, 5 sacos cemento">
              <label>Incidencias / Imprevistos</label>
              <textarea name="incidents" rows="2" placeholder="Ej: Lluvia detuvo obra 2h">${values.incidents || ''}</textarea>
              <div class="modal-actions">
                <button type="button" class="btn-primary" style="background:transparent;color:#111111;border:1px solid rgba(17,17,17,0.25)" onclick="closeModal()">Cancelar</button>
                <button type="submit" class="btn btn-primary">${eventId ? 'Guardar cambios' : 'Guardar avance'}</button>
              </div>
            </form>
            <p style="font-size:.75rem;color:#57534e;margin-top:.75rem">Después de guardar podrás subir fotos del avance.</p>`);
        }).catch(err => {
          showToast(err.message || 'Error al cargar formulario', 'error');
        });
    }

    function saveAvanceForm(f, pid, eventId) {
      const fd = new FormData(f);
      fd.set('action', eventId ? 'update' : 'create');
      if (eventId) fd.set('id', eventId);
      return fetch('/api/progress.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams(fd).toString()
      }).then(r => r.json()).then(d => {
        if (d.ok) {
          showToast(eventId ? 'Avance actualizado' : 'Avance registrado');
          closeModal();
          if (typeof cargarAvance === 'function') {
            const sel = document.getElementById('avProyecto');
            const prevPid = sel ? sel.value : null;
            cargarAvance();
            if (prevPid && sel) sel.value = prevPid;
            const box = document.getElementById('tlContainer');
            if (box && box.firstChild) {
              const banner = document.createElement('div');
              banner.style.cssText = 'background:#f5f5f4;color:#333333;border:1px solid rgba(17,17,17,0.08);border-radius:12px;padding:.6rem 1rem;margin-bottom:.75rem;font-size:.85rem';
              banner.textContent = eventId ? 'Avance actualizado — seguir editando o subir fotos.' : 'Avance guardado — agrega fotos o registra otro.';
              box.insertBefore(banner, box.firstChild);
              setTimeout(() => banner.remove(), 4000);
            }
          } else {
            loadTab('proyectos');
          }
          return false;
        }
        showToast(d.error, 'error');
        return false;
      });
    }

    const defaultTab = window.IS_CLIENT ? 'proyectos' : 'resumen';
    let projectIdFromHash = null;

    function parseHash() {
      const raw = location.hash.replace('#', '');
      if (!raw) return null;
      const [tab, query] = raw.split('?');
      const id = query ? new URLSearchParams(query).get('id') : null;
      if (!['resumen','proyectos','avance','clientes','pagos','documentos','password','admin'].includes(tab)) return null;
      return { tab, id: id ? parseInt(id, 10) : null };
    }
    async function navigateFromHash() {
      const parsed = parseHash();
      if (!parsed) { loadTab(defaultTab); return; }
      projectIdFromHash = parsed.id;
      await loadTab(parsed.tab);
      if (projectIdFromHash && parsed.tab === 'proyectos') {
        await expandProjectAfterLoad(projectIdFromHash);
      }
      projectIdFromHash = null;
    }
    async function expandProjectAfterLoad(id) {
      let tries = 0;
      while (tries < 30) {
        const el = document.getElementById('proj-' + id);
        const btn = el ? document.querySelector('button[aria-controls="proj-' + id + '"]') : null;
        if (el && btn && el.style.display === 'block') return;
        if (el && btn && el.style.display === 'none') { btn.click(); return; }
        await new Promise(r => setTimeout(r, 50));
        tries++;
      }
    }

    document.querySelectorAll('.nav-item[data-tab]').forEach(item => {
      item.addEventListener('click', (e) => {
        e.preventDefault();
        projectIdFromHash = null;
        loadTab(item.dataset.tab);
        history.replaceState(null, '', '#' + item.dataset.tab);
      });
    });

    window.addEventListener('hashchange', navigateFromHash);
    if (location.hash) { navigateFromHash(); } else { loadTab(defaultTab); }
  </script>
</body>
</html>
