<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';
$auth = require_auth();
$userId = $auth['user_id'];
$userRole = $auth['role'];
$db = Database::get();

$projects = $db->query('SELECT p.id, p.name, c.name as client_name FROM projects p LEFT JOIN clients c ON c.id = p.client_id ORDER BY p.created_at DESC')->fetchAll();
$isStaff = in_array($userRole, ['admin','staff']);
if (!$isStaff && $userRole === 'client') {
  $me = $db->prepare('SELECT client_id FROM app_users WHERE id = ?');
  $me->execute([$userId]); $u = $me->fetch();
  $myClientId = $u ? (int)$u['client_id'] : null;
  $projects = array_values(array_filter($projects, fn($p) => (int)($p['client_id'] ?? 0) === $myClientId));
}
?>
<style>
.progress-wrap { display:flex; flex-direction:column; gap:1.25rem; }
.progress-controls { display:flex; gap:.75rem; flex-wrap:wrap; align-items:flex-end; }
.progress-controls label { font-size:.75rem; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:#64748b; display:block; margin-bottom:.35rem; }
.progress-controls select, .progress-controls input { padding:.5rem .8rem; border:1px solid #cbd5e1; border-radius:10px; font-size:.88rem; font-family:inherit; background:#fff; color:#0f172a; box-shadow:0 1px 0 rgba(0,0,0,0.02); }
.progress-controls select { min-width:280px; }
.progress-header { display:flex; justify-content:space-between; align-items:center; gap:1rem; }
.progress-title { font-weight:700; color:#0f172a; font-size:1rem; }
.progress-meta { font-size:.8rem; color:#64748b; }
.btn-primary { background:#0d9488; color:#fff; border-color:#0d9488; padding:.5rem 1.1rem; border-radius:8px; font-size:.88rem; font-weight:600; cursor:pointer; border:1px solid transparent; box-shadow:0 1px 2px rgba(0,0,0,0.04); }
.btn-primary:hover { background:#0f766e; }
.btn-outline { background:#fff; color:#334155; border:1px solid #cbd5e1; padding:.4rem .7rem; border-radius:8px; font-size:.8rem; cursor:pointer; }
.btn-outline:hover { background:#f8fafc; border-color:#94a3b8; }
.btn-danger-text { background:none; color:#b91c1c; border:1px solid #fecaca; padding:.4rem .7rem; border-radius:8px; font-size:.8rem; cursor:pointer; }
.btn-danger-text:hover { background:#fef2f2; }
.timeline { display:flex; flex-direction:column; gap:.9rem; }
.tl-item { background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:1.1rem 1.25rem; box-shadow:0 1px 0 rgba(0,0,0,0.02); }
.tl-header { display:flex; justify-content:space-between; align-items:flex-start; gap:1rem; margin-bottom:.5rem; }
.tl-title { font-weight:700; color:#0f172a; font-size:1rem; }
.tl-body { font-size:.88rem; color:#334155; margin-bottom:.6rem; line-height:1.55; white-space:pre-wrap; }
.tl-meta { display:flex; gap:.75rem; align-items:center; flex-wrap:wrap; margin-bottom:.5rem; }
.tl-badge { font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em; padding:.18rem .55rem; border-radius:20px; }
.badge-daily { background:#e0f2fe; color:#0369a1; }
.badge-milestone { background:#fef9c3; color:#713f12; }
.tl-pct { font-weight:800; font-size:.88rem; color:#059669; background:#ecfdf5; padding:.18rem .55rem; border-radius:20px; }
.tl-author { font-size:.75rem; color:#64748b; }
.tl-photos { display:grid; grid-template-columns:repeat(auto-fill,minmax(140px,1fr)); gap:.6rem; margin-top:.6rem; }
.tl-photo { position:relative; border-radius:10px; overflow:hidden; border:1px solid #e2e8f0; aspect-ratio:4/3; cursor:pointer; background:#f8fafc; }
.tl-photo img { width:100%; height:100%; object-fit:cover; display:block; transition:transform .15s; }
.tl-photo:hover img { transform:scale(1.04); }
.tl-actions { display:flex; gap:.5rem; margin-top:.6rem; flex-wrap:wrap; }
.empty-state { text-align:center; padding:2.8rem 1rem; color:#64748b; }
.empty-state-title { font-weight:700; color:#334155; margin-bottom:.35rem; }
.skeleton { background:linear-gradient(90deg,#e2e8f0 25%,#f1f5f9 50%,#e2e8f0 75%); background-size:200% 100%; animation:shimmer 1.2s infinite; border-radius:10px; height:76px; }
@keyframes shimmer { 0%{background-position:200% 0} 100%{background-position:-200% 0} }
.lb { position:fixed; inset:0; background:rgba(15,23,42,0.72); display:flex; align-items:center; justify-content:center; z-index:9999; padding:1.5rem; }
.lb img { max-width:92vw; max-height:82vh; border-radius:12px; box-shadow:0 25px 60px rgba(0,0,0,0.35); background:#111; }
.lb-caption { color:#f8fafc; text-align:center; margin-top:.6rem; font-size:.9rem; }
@media (max-width: 640px) {
  .progress-controls select { min-width: 100%; }
  .tl-photos { grid-template-columns: repeat(auto-fill,minmax(110px,1fr)); }
}
</style>

<div class="progress-wrap">
  <div class="progress-controls">
    <div style="flex:1 1 280px">
      <label>Obra</label>
      <select id="avProyecto" onchange="cargarAvance()">
        <option value="">— Selecciona una obra —</option>
        <?php foreach ($projects as $p): ?>
          <option value="<?= $p['id'] ?>">
            <?= htmlspecialchars($p['name']) ?> — <?= htmlspecialchars($p['client_name'] ?? '—') ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <button class="btn-outline" id="btnCargarAvance" onclick="cargarAvance()" style="display:none">⟳ Recargar</button>
    <?php if ($isStaff): ?>
      <button class="btn-primary" id="btnNuevoAvance" onclick="openAvanceModal(<?= $projects[0]['id'] ?? 'null' ?>)" style="display:none">+ Registrar avance</button>
    <?php endif; ?>
  </div>

  <div id="tlContainer">
    <div class="empty-state">
      <div class="empty-state-title">Sin obra seleccionada</div>
      <p>Elegí una obra para revisar el historial o crear un avance.</p>
    </div>
  </div>
</div>

<script>
const ROLE = '<?= $userRole ?>';
const IS_STAFF = <?= json_encode($isStaff) ?>;
const AUTO_PROJECT = <?= json_encode($projects[0]['id'] ?? null) ?>;

(function init(){
  if (!AUTO_PROJECT) return;
  const sel = document.getElementById('avProyecto');
  if (!sel) return;
  sel.value = AUTO_PROJECT;
  cargarAvance();
  const btn = document.getElementById('btnNuevoAvance');
  if (btn && IS_STAFF) btn.style.display = 'inline-block';
})();

function cargarAvance() {
  const pid = document.getElementById('avProyecto').value;
  const box = document.getElementById('tlContainer');
  const btn = document.getElementById('btnNuevoAvance');
  const reloadBtn = document.getElementById('btnCargarAvance');
  if (!pid) {
    box.innerHTML = '<div class="empty-state"><div class="empty-state-title">Sin obra seleccionada</div><p>Elegí una obra para revisar el historial o crear un avance.</p></div>';
    if (btn) btn.style.display = 'none';
    if (reloadBtn) reloadBtn.style.display = 'none';
    return;
  }
  if (btn && IS_STAFF) btn.style.display = 'inline-block';
  if (reloadBtn) reloadBtn.style.display = 'inline-block';
  box.innerHTML = '<div class="skeleton"></div><div class="skeleton"></div><div class="skeleton"></div>';
  fetch('/api/progress.php?action=list&project_id=' + pid)
    .then(r => r.json()).then(d => {
      if (!d.ok) throw new Error(d.error || 'Error');
      if (window.__evPhotos !== d.data?.__photos) {
        window.__evPhotos = d.data?.__photos || {};
      }
      renderTL(d.data || [], btn);
      if (typeof updateTitleCount === 'function') updateTitleCount(d.data.length);
    }).catch(() => {
      box.innerHTML = '<div class="empty-state"><p style="color:#b91c1c;font-weight:600">No se pudo cargar la timeline</p></div>';
    });
}

function renderTL(items, btn) {
  const box = document.getElementById('tlContainer');
  if (!items.length) {
    box.innerHTML = '<div class="empty-state"><div class="empty-state-title">Sin avances registrados</div><p>Todavía no hay registros para esta obra.</p></div>';
    return;
  }
  let h = '<div class="timeline">';
  for (const e of items) {
    const bc = e.event_type === 'milestone' ? 'badge-milestone' : 'badge-daily';
    const bl = e.event_type === 'milestone' ? 'Hito' : 'Avance diario';
    const photos = (window.__evPhotos && window.__evPhotos[e.id]) ? window.__evPhotos[e.id] : [];
    const pHtml = photos.map(f =>
      `<div class="tl-photo" onclick="showPhoto('${(f.url||'').replace(/'/g,"&apos;")}','${(f.caption||'').replace(/'/g,"&apos;")}')">
        <img src="${f.url}" alt="${esc(f.caption||'Foto de avance')}" loading="lazy">
      </div>`
    ).join('');
    h += `<div class="tl-item">
      <div class="tl-header">
        <div><div class="tl-title">${esc(e.title)}</div></div>
        <div style="font-size:.78rem;color:#64748b;white-space:nowrap">${e.event_date}</div>
      </div>
      <div class="tl-body">${esc(e.description||' ')}</div>
      <div class="tl-meta">
        <span class="tl-badge ${bc}">${bl}</span>
        ${e.percentage ? `<span class="tl-pct">${e.percentage}%</span>` : ''}
        <span class="tl-author">por ${esc(e.autor)}</span>
      </div>
      ${pHtml ? '<div class="tl-photos">' + pHtml + '</div>' : ''}
      ${IS_STAFF ? `<div class="tl-actions">
        <button class="btn-outline" onclick="openAvanceModal(null, ${e.id})">Editar</button>
        <button class="btn-outline" onclick="uploadPhotos(${e.id})">📷 Subir fotos</button>
        <button class="btn-danger-text" onclick="delAvance(${e.id})">Eliminar</button>
      </div>` : ''}
    </div>`;
  }
  h += '</div>';
  box.innerHTML = h;
}

function updateTitleCount(n) {
  const el = document.getElementById('tlCount');
  if (el) el.textContent = n + ' registro' + (n===1?'':'s');
}

function showPhoto(url, caption) {
  if (!url) return;
  const wrap = document.createElement('div');
  wrap.className = 'lb';
  wrap.innerHTML = `<img src="${url}" alt="${esc(caption||'')}"><div class="lb-caption">${esc(caption||'')}</div>`;
  wrap.onclick = function(){ wrap.remove(); };
  document.body.appendChild(wrap);
}

function editAvance(id) {
  openAvanceModal(null, id);
}

function delAvance(id) {
  if (!confirm('¿Eliminar avance? También se borrarán sus fotos.')) return;
  fetch('/api/progress.php', { method:'POST', body: new URLSearchParams({action:'delete',id}).toString(), headers:{'Content-Type':'application/x-www-form-urlencoded'} })
    .then(r=>r.json()).then(d=>{
      if(d.ok){ showToast('Avance eliminado'); cargarAvance(); }
      else showToast(d.error,'error');
    });
}

function uploadPhotos(eventId) {
  openModal(`<h3>Subir fotos del avance</h3>
    <form id="frmFoto" onsubmit="return savePhotos(this)">
      <input type="hidden" name="event_id" value="${eventId}">
      <label>Fotos</label>
      <input type="file" name="fotos[]" accept="image/*" multiple required id="fotoInput" style="margin-bottom:.5rem">
      <div id="fotoPreview" style="display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:.5rem"></div>
      <label>Descripción (opcional)</label><input type="text" name="caption" placeholder="Ej: Vista frontal fundaciones" style="margin-bottom:.5rem">
      <p id="fotoMeta" style="font-size:.75rem;color:#94a3b8">JPG/PNG/WebP, máximo 5MB por foto. Puedes subir varias a la vez.</p>
      <div class="modal-actions">
        <button type="button" class="btn-outline" onclick="closeModal()">Cancelar</button>
        <button type="submit" class="btn-primary">Subir fotos</button>
      </div>
    </form>`);
  document.getElementById('fotoInput')?.addEventListener('change', function(){
    const box = document.getElementById('fotoPreview');
    const meta = document.getElementById('fotoMeta');
    if (!box) return;
    box.innerHTML = '';
    let total = 0;
    Array.from(this.files||[]).forEach(f=>{
      total += f.size;
      const reader = new FileReader();
      reader.onload = e => {
        const item = document.createElement('div');
        item.style.cssText = 'width:72px;height:54px;border-radius:6px;overflow:hidden;border:1px solid #e2e8f0;background:#f8fafc';
        item.innerHTML = '<img src="'+e.target.result+'" style="width:100%;height:100%;object-fit:cover;display:block">';
        box.appendChild(item);
      };
      reader.readAsDataURL(f);
    });
    if (meta) meta.textContent = (this.files?.length||0) + ' archivo(s), ' + (total/1024/1024).toFixed(1) + ' MB';
  });
}

function savePhotos(f) {
  const fd = new FormData(f);
  return fetch('/api/subir_foto.php', { method:'POST', body: fd })
    .then(r=>r.json()).then(d=>{
      const msgs = [];
      if (d.ok) {
        const uploaded = d.count || 0;
        msgs.push(`${uploaded} foto(s) subida(s) ✅`);
        if (d.rejected && d.rejected.length) {
          msgs.push(d.rejected.map(r => r.reason || 'Archivo rechazado').join(', '));
        }
        showToast(msgs.join(' | '));
        closeModal();
        cargarAvance();
      } else {
        showToast(d.error || 'Error', 'error');
      }
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
