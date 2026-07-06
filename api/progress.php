<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';
$auth = require_auth();
$userId = $auth['user_id'];
$userRole = $auth['role'];
$staff = in_array($userRole, ['admin', 'staff', 'client']);
$db = Database::get();

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$mode  = $_GET['mode'] ?? ''; // 'form' para GET del formulario

if ($action && !$staff) {
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Acceso denegado']);
    exit;
}

/* ─── Client filter ─── */
$clientFilter = null;
if ($userRole === 'client') {
    $st = $db->prepare('SELECT client_id FROM app_users WHERE id = ?');
    $st->execute([$userId]);
    $cu = $st->fetch();
    $clientFilter = $cu ? (int)$cu['client_id'] : null;
}

/* ══════════════ JSON API ══════════════ */
if ($action) {
    header('Content-Type: application/json');

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'create') {
        $projId = (int)($_POST['project_id'] ?? 0);
        $title  = trim($_POST['title'] ?? '');
        $desc   = trim($_POST['description'] ?? '');
        $date   = $_POST['event_date'] ?? date('Y-m-d');
        $pct    = (int)($_POST['percentage'] ?? 0);
        $type   = $_POST['event_type'] ?? 'daily_log';
        if ($projId <= 0 || !$title) {
            echo json_encode(['ok' => false, 'error' => 'Proyecto y título son requeridos']);
            exit;
        }
        $db->prepare('INSERT INTO progress_events (project_id, title, description, event_date, percentage, event_type, created_by) VALUES (?,?,?,?,?,?,?)')
            ->execute([$projId, $title, $desc ?: null, $date, $pct, $type, $userId]);
        echo json_encode(['ok' => true, 'id' => $db->lastInsertId()]);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $fields = [];
        $params = [];
        foreach (['title','description','event_date','event_type'] as $k) {
            if (isset($_POST[$k])) { $fields[] = "$k = ?"; $params[] = $_POST[$k]; }
        }
        if (isset($_POST['percentage'])) {
            $fields[] = 'percentage = ?';
            $params[] = (int)$_POST['percentage'];
        }
        if ($fields) {
            $params[] = $id;
            $db->prepare('UPDATE progress_events SET '.implode(',', $fields).' WHERE id = ?')->execute($params);
        }
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $db->prepare('DELETE FROM progress_events WHERE id = ?')->execute([$id]);
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'list') {
        $projId = isset($_GET['project_id']) ? (int)$_GET['project_id'] : null;
        if (!$projId) { echo json_encode(['ok'=>true,'data'=>[]]); exit; }
        // Verify access
        if ($userRole === 'client') {
            $proj = $db->prepare('SELECT client_id FROM projects WHERE id = ?');
            $proj->execute([$projId]); $pj = $proj->fetch();
            if (!$pj || $pj['client_id'] != $clientFilter) {
                echo json_encode(['ok'=>false,'error'=>'No autorizado']); exit;
            }
        }
        $sqlEvents = "
            SELECT e.*, u.name as autor, c.name as client_name
            FROM progress_events e
            JOIN app_users u ON u.id = e.created_by
            JOIN projects p ON p.id = e.project_id
            LEFT JOIN clients c ON c.id = p.client_id
            WHERE e.project_id = $projId
        ";
        if ($userRole === 'client' && $clientFilter) {
            $sqlEvents .= ' AND p.client_id = ' . (int)$clientFilter;
        }
        $sqlEvents .= ' ORDER BY e.event_date DESC, e.id DESC';
        $events = $db->query($sqlEvents)->fetchAll();

        // Load photos for all events
        $eventIds = array_map(fn($e) => $e['id'], $events);
        $photosByEvent = [];
        if ($eventIds) {
            $placeholders = implode(',', array_fill(0, count($eventIds), '?'));
            $stmt = $db->prepare("SELECT * FROM progress_photos WHERE event_id IN ($placeholders) ORDER BY uploaded_at ASC");
            $stmt->execute($eventIds);
            $fotos = $stmt->fetchAll();
            foreach ($fotos as $f) {
                $photosByEvent[$f['event_id']][] = ['url'=>$f['url'], 'caption'=>$f['caption']];
            }
        }
        foreach ($events as &$e) {
            $e['fotos'] = $photosByEvent[$e['id']] ?? [];
        }
        // Sanitize strings before json_encode
        array_walk_recursive($events, function(&$v) {
            if (is_string($v)) $v = preg_replace('//u', '', $v);
        });
        echo json_encode(['ok' => true, 'data' => $events]);
        exit;
    }
}

/* ══════════════ HTML VISTA ══════════════ */
$projFilter = $_GET['project_id'] ?? null;

// Projects list for selector
$projects = $db->query('SELECT p.id, p.name, c.name as client_name FROM projects p LEFT JOIN clients c ON c.id = p.client_id ORDER BY p.created_at DESC')->fetchAll();
?>
<style>
.progress-section { padding: 0; }
.progress-controls { display:flex; gap:.75rem; margin-bottom:1.25rem; flex-wrap:wrap; align-items:flex-end; }
.progress-controls label { font-size:.8rem; font-weight:600; color:#64748b; display:block; margin-bottom:.25rem; }
.progress-controls select, .progress-controls input { padding:.45rem .7rem; border:1px solid #cbd5e1; border-radius:8px; font-size:.85rem; font-family:inherit; }
.progress-controls select { min-width:180px; }
.btn-success { background:#16a34a; color:#fff; border-color:#16a34a; }
.btn-success:hover { background:#14532d; }
.timeline { display:flex; flex-direction:column; gap:.75rem; }
.timeline-item { background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:1rem 1.25rem; position:relative; }
.timeline-item::before { content:''; position:absolute; left:1.25rem; top:-.75rem; width:12px; height:12px; border-radius:50%; background:#0d9488; border:2px solid #fff; box-shadow:0 0 0 2px #0d9488; }
.timeline-header { display:flex; justify-content:space-between; align-items:flex-start; gap:1rem; margin-bottom:.5rem; }
.timeline-title { font-weight:600; color:#1e293b; font-size:.95rem; }
.timeline-date { font-size:.75rem; color:#94a3b8; white-space:nowrap; }
.timeline-desc { font-size:.85rem; color:#475569; margin-bottom:.5rem; line-height:1.5; }
.timeline-meta { display:flex; gap:.75rem; align-items:center; flex-wrap:wrap; }
.timeline-badge { font-size:.75rem; font-weight:600; padding:.1rem .4rem; border-radius:20px; }
.badge-daily { background:#e0f2fe; color:#0369a1; }
.badge-milestone { background:#fef9c3; color:#713f12; }
.timeline-percentage { font-size:.85rem; font-weight:700; color:#0d9488; }
.timeline-author { font-size:.75rem; color:#94a3b8; }
.photos-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(140px,1fr)); gap:.5rem; margin-top:.75rem; }
.photo-thumb { position:relative; border-radius:8px; overflow:hidden; border:1px solid #e2e8f0; aspect-ratio:4/3; cursor:pointer; background:#f8fafc; }
.photo-thumb img { width:100%; height:100%; object-fit:cover; transition:transform .15s; }
.photo-thumb:hover img { transform:scale(1.04); }
.photo-thumb .photo-remove { position:absolute; top:4px; right:4px; background:rgba(220,38,38,.85); color:#fff; border:none; border-radius:50%; width:20px; height:20px; cursor:pointer; font-size:.75rem; display:flex; align-items:center; justify-content:center; }
.timeline-actions { display:flex; gap:.4rem; margin-top:.5rem; }
.empty-timeline { text-align:center; padding:2.5rem 1rem; color:#94a3b8; }
.photo-modal { position:fixed; inset:0; background:rgba(0,0,0,.85); z-index:3000; display:none; align-items:center; justify-content:center; cursor:pointer; }
.photo-modal.show { display:flex; }
.photo-modal img { max-width:90vw; max-height:85vh; border-radius:8px; }
.photo-modal .photo-caption { position:absolute; bottom:1.5rem; left:50%; transform:translateX(-50%); background:rgba(0,0,0,.7); color:#fff; padding:.5rem 1rem; border-radius:20px; font-size:.85rem; white-space:nowrap; }
</style>

<div class="progress-section">
  <div class="progress-controls">
    <div>
      <label>Seleccionar proyecto</label>
      <select id="progProyecto" onchange="cargarTimeline()">
        <option value="">— Elegir obra —</option>
        <?php foreach ($projects as $p): ?>
          <option value="<?= $p['id'] ?>" <?= $projFilter == $p['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($p['name']) ?> (<?= htmlspecialchars($p['client_name'] ?? '—') ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php if (in_array($userRole, ['admin','staff'])): ?>
    <button class="btn btn-success" onclick="nuevoAvance()">+ Registrar avance diario</button>
    <?php endif; ?>
  </div>

  <div id="timelineContainer">
    <div class="empty-timeline"><p>Selecciona un proyecto para ver el timeline de avances</p></div>
  </div>
</div>

<!-- Photo lightbox -->
<div class="photo-modal" id="photoModal" onclick="this.classList.remove('show')">
  <img id="photoModalImg" src="" alt="">
  <div class="photo-caption" id="photoModalCaption"></div>
</div>

<script>
async function cargarTimeline() {
  const projId = document.getElementById('progProyecto').value;
  const container = document.getElementById('timelineContainer');
  if (!projId) {
    container.innerHTML = '<div class="empty-timeline"><p>Selecciona un proyecto para ver el timeline de avances</p></div>';
    return;
  }
  container.innerHTML = '<div class="loading">Cargando avances...</div>';
  try {
    const res = await fetch('/api/progress.php?action=list&project_id=' + projId);
    const d = await res.json();
    if (!d.ok) throw new Error(d.error || 'Error');
    renderTimeline(d.data || []);
  } catch(e) {
    container.innerHTML = '<div class="empty-timeline"><p style="color:#dc2626">Error al cargar avances</p></div>';
  }
}

function renderTimeline(items) {
  const container = document.getElementById('timelineContainer');
  if (!items.length) {
    container.innerHTML = '<div class="empty-timeline"><p>Sin avances registrados aún</p></div>';
    return;
  }
  const CL = <?= json_encode($userRole === 'client' ? 'false' : 'true') ?>;
  let html = '<div class="timeline">';
  for (const e of items) {
    const badgeClass = e.event_type === 'milestone' ? 'badge-milestone' : 'badge-daily';
    const badgeLabel = e.event_type === 'milestone' ? 'Hito' : 'Avance diario';
    const pctHtml = e.percentage > 0 ? `<span class="timeline-percentage">${e.percentage}%</span>` : '';
    const photosHtml = (e.fotos || []).map(f =>
      `<div class="photo-thumb" onclick="openPhoto('${f.url.replace(/'/g,"&apos;")}', '${(f.caption||'').replace(/'/g,"&apos;")}')">
        <img src="${f.url}" alt="${f.caption || 'Foto de avance'}" loading="lazy">
      </div>`
    ).join('');
    html += `<div class="timeline-item">
      <div class="timeline-header">
        <div>
          <div class="timeline-title">${escapeHtml(e.title)}</div>
          <div class="timeline-desc">${escapeHtml(e.description || '')}</div>
        </div>
        <div style="text-align:right">
          <div class="timeline-date">${e.event_date}</div>
        </div>
      </div>
      <div class="timeline-meta">
        <span class="timeline-badge ${badgeClass}">${badgeLabel}</span>
        ${pctHtml}
        <span class="timeline-author">por ${escapeHtml(e.autor)}</span>
      </div>
      ${photosHtml ? '<div class="photos-grid">' + photosHtml + '</div>' : ''}
      ${CL ? `<div class="timeline-actions">
        <button class="btn btn-sm btn-outline" onclick="editarAvance(${e.id})">Editar</button>
        <button class="btn btn-sm btn-outline" style="color:#dc2626;border-color:#fecaca" onclick="eliminarAvance(${e.id})">Eliminar</button>
      </div>` : ''}
    </div>`;
  }
  html += '</div>';
  container.innerHTML = html;
}

function escapeHtml(str) {
  if (!str) return '';
  return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

/* ─── Create / Edit ─── */
function nuevoAvance() {
  const projId = document.getElementById('progProyecto').value;
  if (!projId) { showToast('Selecciona un proyecto primero', 'error'); return; }
  openModal(`<h3>Registrar avance diario</h3>
    <form id="avanceForm" onsubmit="return guardarAvance(this)">
      <input type="hidden" name="project_id" value="${projId}">
      <label>Título del avance</label><input name="title" placeholder="Ej: Fundaciones completadas" required>
      <label>Descripción</label><textarea name="description" rows="3" placeholder="Qué se realizó hoy..."></textarea>
      <label>Fecha</label><input type="date" name="event_date" value="${new Date().toISOString().slice(0,10)}" required>
      <label>% de avance estimado</label><input type="number" name="percentage" min="0" max="100" value="0">
      <label>Tipo</label><select name="event_type">
        <option value="daily_log">Avance diario</option>
        <option value="milestone">Hito / Inspección</option>
      </select>
      <div class="modal-actions">
        <button type="button" class="btn btn-outline" onclick="closeModal()">Cancelar</button>
        <button type="submit" class="btn btn-primary">Guardar</button>
      </div>
    </form>
    <p style="font-size:.75rem;color:#94a3b8;margin-top:.75rem">Después de guardar podrás subir fotos.</p>`);
}

function guardarAvance(form) {
  const fd = new FormData(form);
  fd.set('action', 'create');
  return fetch('/api/progress.php', {
    method: 'POST',
    body: new URLSearchParams(fd).toString(),
    headers: {'Content-Type':'application/x-www-form-urlencoded'}
  }).then(r => r.json()).then(d => {
    if (d.ok) { showToast('Avance registrado ✅'); closeModal(); cargarTimeline(); return false; }
    showToast(d.error, 'error'); return false;
  });
}

/* ─── Photo Upload ─── */
function subirFotos(eventId) {
  openModal(`<h3>Subir fotos del avance</h3>
    <form id="fotoForm" onsubmit="return guardarFotos(this)">
      <input type="hidden" name="event_id" value="${eventId}">
      <label>Fotos (puedes seleccionar varias)</label>
      <input type="file" name="fotos[]" accept="image/*" multiple required>
      <label>Descripción (opcional, se aplica a todas)</label>
      <input type="text" name="caption" placeholder="Ej: Vista fundaciones">
      <p style="font-size:.75rem;color:#94a3b8">Formatos aceptados: JPG, PNG. Tamaño máximo: 5MB por foto.</p>
      <div class="modal-actions">
        <button type="button" class="btn btn-outline" onclick="closeModal()">Cancelar</button>
        <button type="submit" class="btn btn-primary">Subir fotos</button>
      </div>
    </form>`);
}

function guardarFotos(form) {
  const fd = new FormData(form);
  return fetch('/api/subir_foto.php', {
    method: 'POST',
    body: fd
  }).then(r => r.json()).then(d => {
    if (d.ok) { showToast(`Fotos subidas ✅`); closeModal(); cargarTimeline(); }
    else showToast(d.error || 'Error al subir', 'error');
  });
  return false;
}

function openPhoto(url, caption) {
  document.getElementById('photoModalImg').src = url;
  document.getElementById('photoModalCaption').textContent = caption || '';
  document.getElementById('photoModal').classList.add('show');
}

/* ─── Edit / Delete ─── */
function editarAvance(id) {
  fetch('/api/progress.php?action=list&project_id=' + document.getElementById('progProyecto').value)
    .then(r => r.json()).then(d => {
      const ev = (d.data || []).find(x => x.id == id);
      if (!ev) return showToast('Error', 'error');
      openModal(`<h3>Editar avance</h3>
        <form id="editAvanceForm" onsubmit="return actualizarAvance(this, ${id})">
          <label>Título</label><input name="title" value="${escapeHtml(ev.title).replace(/"/g,'&quot;')}" required>
          <label>Descripción</label><textarea name="description" rows="3">${escapeHtml(ev.description||'')}</textarea>
          <label>Fecha</label><input type="date" name="event_date" value="${ev.event_date}">
          <label>% de avance</label><input type="number" name="percentage" min="0" max="100" value="${ev.percentage||0}">
          <div class="modal-actions">
            <button type="button" class="btn btn-outline" onclick="closeModal()">Cancelar</button>
            <button type="submit" class="btn btn-primary">Guardar</button>
          </div>
        </form>`);
    });
}
function actualizarAvance(form, id) {
  const fd = new FormData(form);
  fd.set('action', 'update');
  fd.set('id', id);
  return fetch('/api/progress.php', {
    method:'POST', body: new URLSearchParams(fd).toString(),
    headers:{'Content-Type':'application/x-www-form-urlencoded'}
  }).then(r=>r.json()).then(d=>{
    if(d.ok){ showToast('Actualizado ✅'); closeModal(); cargarTimeline(); return false; }
    showToast(d.error,'error'); return false;
  });
}
function eliminarAvance(id) {
  if (!confirm('¿Eliminar este avance? Esta acción no se puede deshacer.')) return;
  fetch('/api/progress.php', {
    method:'POST', body: new URLSearchParams({action:'delete',id}).toString(),
    headers:{'Content-Type':'application/x-www-form-urlencoded'}
  }).then(r=>r.json()).then(d=>{
    if(d.ok){ showToast('Avance eliminado'); cargarTimeline(); }
    else showToast(d.error,'error');
  });
}

/* Add upload button inside each timeline item */
function addUploadButton(eventId) {
  const item = document.getElementById('tev-' + eventId);
  if (!item) return;
  const btn = document.createElement('button');
  btn.className = 'btn btn-sm btn-outline';
  btn.textContent = '📷 Subir fotos';
  btn.onclick = () => subirFotos(eventId);
  const actions = item.querySelector('.timeline-actions');
  if (actions) actions.appendChild(btn);
}

// Initial load if project_id is set
const initialProj = '<?= $projFilter ?? '' ?>';
if (initialProj) cargarTimeline();
</script>
