<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';
session_start();
$userId = $_SESSION['user_id'] ?? null;
$userRole = $_SESSION['user_role'] ?? null;
if (!$userId) { http_response_code(401); exit; }
$db = Database::get();

$projects = $db->query('SELECT p.id, p.name, c.name as client_name FROM projects p LEFT JOIN clients c ON c.id = p.client_id ORDER BY p.created_at DESC')->fetchAll();
$isStaff = in_array($userRole, ['admin','staff']);
?>
<style>
.progress-wrap { display:flex; flex-direction:column; gap:1rem; }
.progress-controls { display:flex; gap:.75rem; flex-wrap:wrap; align-items:flex-end; }
.progress-controls label { font-size:.8rem; font-weight:600; color:#64748b; display:block; margin-bottom:.25rem; }
.progress-controls select, .progress-controls input { padding:.45rem .7rem; border:1px solid #cbd5e1; border-radius:8px; font-size:.85rem; font-family:inherit; background:#fff; }
.progress-controls select { min-width:260px; }
.btn-green { background:#16a34a; color:#fff; border-color:#16a34a; padding:.45rem 1rem; border-radius:8px; font-size:.85rem; font-weight:500; cursor:pointer; border:1px solid transparent; }
.btn-green:hover { background:#14532d; }
.btn-outline { background:#fff; color:#475569; border:1px solid #cbd5e1; padding:.35rem .7rem; border-radius:8px; font-size:.8rem; cursor:pointer; }
.btn-outline:hover { background:#f8fafc; }
.btn-danger-text { background:none; color:#dc2626; border:1px solid #fecaca; padding:.35rem .7rem; border-radius:8px; font-size:.8rem; cursor:pointer; }
.btn-danger-text:hover { background:#fef2f2; }
.timeline { display:flex; flex-direction:column; gap:.75rem; }
.tl-item { background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:1rem 1.25rem; }
.tl-header { display:flex; justify-content:space-between; align-items:flex-start; gap:1rem; margin-bottom:.4rem; }
.tl-title { font-weight:600; color:#1e293b; font-size:.95rem; }
.tl-body { font-size:.85rem; color:#475569; margin-bottom:.5rem; line-height:1.5; white-space:pre-wrap; }
.tl-meta { display:flex; gap:.75rem; align-items:center; flex-wrap:wrap; margin-bottom:.5rem; }
.tl-badge { font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.03em; padding:.15rem .5rem; border-radius:20px; }
.badge-daily { background:#e0f2fe; color:#0369a1; }
.badge-milestone { background:#fef9c3; color:#713f12; }
.tl-pct { font-weight:700; font-size:.85rem; color:#059669; }
.tl-author { font-size:.72rem; color:#94a3b8; }
.tl-photos { display:grid; grid-template-columns:repeat(auto-fill,minmax(150px,1fr)); gap:.5rem; margin-top:.5rem; }
.tl-photo { position:relative; border-radius:8px; overflow:hidden; border:1px solid #e2e8f0; aspect-ratio:4/3; cursor:pointer; }
.tl-photo img { width:100%; height:100%; object-fit:cover; display:block; transition:transform .15s; }
.tl-photo:hover img { transform:scale(1.05); }
.tl-actions { display:flex; gap:.4rem; margin-top:.5rem; flex-wrap:wrap; }
.empty-state { text-align:center; padding:2.5rem 1rem; color:#94a3b8; }
</style>

<div class="progress-wrap">
  <div class="progress-controls">
    <div>
      <label>Obra</label>
      <select id="avProyecto" onchange="loadTimeline()">
        <option value="">— Selecciona una obra —</option>
        <?php foreach ($projects as $p): ?>
          <option value="<?= $p['id'] ?>">
            <?= htmlspecialchars($p['name']) ?> — <?= htmlspecialchars($p['client_name'] ?? '—') ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php if ($isStaff): ?>
      <button class="btn-green" id="btnNuevoAvance" onclick="nuevoAvance()" style="display:none">+ Registrar avance diario</button>
    <?php endif; ?>
  </div>

  <div id="tlContainer">
    <div class="empty-state"><p>Selecciona una obra para ver los avances registrados</p></div>
  </div>
</div>

<script>
const ROLE = '<?= $userRole ?>';
const IS_STAFF = <?= json_encode($isStaff) ?>;

function loadTimeline() {
  const pid = document.getElementById('avProyecto').value;
  const box = document.getElementById('tlContainer');
  const btn = document.getElementById('btnNuevoAvance');
  if (!pid) {
    box.innerHTML = '<div class="empty-state"><p>Selecciona una obra para ver los avances registrados</p></div>';
    if (btn) btn.style.display = 'none';
    return;
  }
  if (btn) btn.style.display = 'inline-block';
  box.innerHTML = '<div class="empty-state">Cargando avances...</div>';
  fetch('/api/progress.php?action=list&project_id=' + pid)
    .then(r => r.json()).then(d => {
      if (!d.ok) throw new Error(d.error);
      renderTL(d.data || [], btn);
    }).catch(() => {
      box.innerHTML = '<div class="empty-state"><p style="color:#dc2626">Error al cargar avances</p></div>';
    });
}

function renderTL(items, btn) {
  const box = document.getElementById('tlContainer');
  if (!items.length) {
    box.innerHTML = '<div class="empty-state"><p>Sin avances registrados — comienza registrando el primero</p></div>';
    return;
  }
  let h = '<div class="timeline">';
  for (const e of items) {
    const bc = e.event_type === 'milestone' ? 'badge-milestone' : 'badge-daily';
    const bl = e.event_type === 'milestone' ? 'Hito' : 'Avance diario';
    const photos = (window.__evPhotos && window.__evPhotos[e.id]) ? window.__evPhotos[e.id] : [];
    const pHtml = photos.map(f =>
      `<div class="tl-photo" onclick="showPhoto('${f.url.replace(/'/g,"&apos;")}','${(f.caption||'').replace(/'/g,"&apos;")}')">
        <img src="${f.url}" alt="${f.caption||'Foto de avance'}" loading="lazy">
      </div>`
    ).join('');
    h += `<div class="tl-item">
      <div class="tl-header">
        <div><div class="tl-title">${esc(e.title)}</div></div>
        <div style="font-size:.75rem;color:#94a3b8;white-space:nowrap">${e.event_date}</div>
      </div>
      <div class="tl-body">${esc(e.description||'')}</div>
      <div class="tl-meta">
        <span class="tl-badge ${bc}">${bl}</span>
        ${e.percentage ? `<span class="tl-pct">${e.percentage}%</span>` : ''}
        <span class="tl-author">por ${esc(e.autor)}</span>
      </div>
      ${pHtml ? '<div class="tl-photos">' + pHtml + '</div>' : ''}
      ${IS_STAFF ? `<div class="tl-actions">
        <button class="btn-outline" onclick="editAvance(${e.id})">Editar</button>
        <button class="btn-outline" onclick="uploadPhotos(${e.id})">📷 Subir fotos</button>
        <button class="btn-danger-text" onclick="delAvance(${e.id})">Eliminar</button>
      </div>` : ''}
    </div>`;
  }
  h += '</div>';
  box.innerHTML = h;
}

function esc(s) { if (!s) return ''; return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

function nuevoAvance() {
  const pid = document.getElementById('avProyecto').value;
  if (!pid) { alert('Selecciona una obra primero'); return; }
  openModal(`<h3>Registrar avance diario</h3>
    <form id="frmAvance" onsubmit="return saveAvance(this)">
      <input type="hidden" name="project_id" value="${pid}">
      <label>Título</label><input name="title" placeholder="Ej: Fundaciones listas" required>
      <label>Descripción</label><textarea name="description" rows="3" placeholder="Qué se hizo hoy, materiales usados, personal presente..."></textarea>
      <label>Fecha</label><input type="date" name="event_date" value="${new Date().toISOString().slice(0,10)}" required>
      <label>% de avance estimado (0-100)</label><input type="number" name="percentage" min="0" max="100" value="0">
      <label>Tipo</label><select name="event_type">
        <option value="daily_log">Avance diario</option>
        <option value="milestone">Hito / Inspección</option>
      </select>
      <div class="modal-actions">
        <button type="button" class="btn-outline" onclick="closeModal()">Cancelar</button>
        <button type="submit" class="btn-green">Guardar avance</button>
      </div>
    </form>
    <p style="font-size:.75rem;color:#94a3b8;margin-top:.75rem">Después de guardar podrás subir fotos del avance.</p>`);
}

function saveAvance(f) {
  const fd = new FormData(f);
  fd.set('action', 'create');
  return fetch('/api/progress.php', {
    method:'POST', body: new URLSearchParams(fd).toString(),
    headers:{'Content-Type':'application/x-www-form-urlencoded'}
  }).then(r => r.json()).then(d => {
    if (d.ok) { showToast('Avance registrado ✅'); closeModal(); loadTimeline(); return false; }
    showToast(d.error, 'error'); return false;
  });
}

function editAvance(id) {
  fetch('/api/progress.php?action=list&project_id=' + document.getElementById('avProyecto').value)
    .then(r=>r.json()).then(d => {
      const ev = (d.data||[]).find(x => x.id == id);
      if (!ev) return showToast('Error','error');
      openModal(`<h3>Editar avance</h3>
        <form onsubmit="return updateAvance(this,${id})">
          <label>Título</label><input name="title" value="${esc(ev.title).replace(/"/g,'&quot;')}" required>
          <label>Descripción</label><textarea name="description" rows="3">${esc(ev.description||'')}</textarea>
          <label>Fecha</label><input type="date" name="event_date" value="${ev.event_date}">
          <label>% de avance</label><input type="number" name="percentage" min="0" max="100" value="${ev.percentage||0}">
          <div class="modal-actions">
            <button type="button" class="btn-outline" onclick="closeModal()">Cancelar</button>
            <button type="submit" class="btn-green">Guardar</button>
          </div>
        </form>`);
    });
}
function updateAvance(f, id) {
  const fd = new FormData(f);
  fd.set('action','update'); fd.set('id', id);
  return fetch('/api/progress.php', { method:'POST', body: new URLSearchParams(fd).toString(), headers:{'Content-Type':'application/x-www-form-urlencoded'} })
    .then(r=>r.json()).then(d=>{
      if(d.ok){ showToast('Actualizado ✅'); closeModal(); loadTimeline(); return false; }
      showToast(d.error,'error'); return false;
    });
}

function delAvance(id) {
  if (!confirm('¿Eliminar avance? También se borrarán sus fotos.')) return;
  fetch('/api/progress.php', { method:'POST', body: new URLSearchParams({action:'delete',id}).toString(), headers:{'Content-Type':'application/x-www-form-urlencoded'} })
    .then(r=>r.json()).then(d=>{
      if(d.ok){ showToast('Avance eliminado'); loadTimeline(); }
      else showToast(d.error,'error');
    });
}

function uploadPhotos(eventId) {
  openModal(`<h3>Subir fotos del avance</h3>
    <form id="frmFoto" onsubmit="return savePhotos(this)">
      <input type="hidden" name="event_id" value="${eventId}">
      <label>Fotos</label>
      <input type="file" name="fotos[]" accept="image/*" multiple required style="margin-bottom:.5rem">
      <label>Descripción (opcional)</label>
      <input type="text" name="caption" placeholder="Ej: Vista frontal fundaciones" style="margin-bottom:.5rem">
      <p style="font-size:.75rem;color:#94a3b8">JPG/PNG/WebP, máximo 5MB por foto. Puedes subir varias a la vez.</p>
      <div class="modal-actions">
        <button type="button" class="btn-outline" onclick="closeModal()">Cancelar</button>
        <button type="submit" class="btn-green">Subir fotos</button>
      </div>
    </form>`);
}

function savePhotos(f) {
  const fd = new FormData(f);
  return fetch('/api/subir_foto.php', { method:'POST', body: fd })
    .then(r=>r.json()).then(d=>{
      if (d.ok) { showToast(`${d.count || 0} foto(s) subida(s) ✅`); closeModal(); loadTimeline(); }
      else showToast(d.error || 'Error', 'error');
    });
  return false;
}

function showPhoto(url, caption) {
  const m = document.createElement('div');
  m.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.92);z-index:3000;display:flex;flex-direction:column;align-items:center;justify-content:center;cursor:pointer';
  m.onclick = () => m.remove();
  const img = document.createElement('img');
  img.src = url; img.style.cssText = 'max-width:90vw;max-height:80vh;border-radius:6px';
  const cap = document.createElement('div');
  cap.textContent = caption || '';
  cap.style.cssText = 'margin-top:.75rem;background:rgba(0,0,0,.7);color:#fff;padding:.4rem 1rem;border-radius:20px;font-size:.85rem';
  m.appendChild(img); m.appendChild(cap);
  document.body.appendChild(m);
}
</script>
