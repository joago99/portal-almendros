<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';
session_start();
$userId = $_SESSION['user_id'] ?? null;
$userRole = $_SESSION['user_role'] ?? null;
if (!$userId) exit;
$db = Database::get();

$type = $_GET['type'] ?? 'todos';
$search = $_GET['q'] ?? '';

$where = '';
if ($type !== 'todos') $where = 'WHERE d.type = "' . $type . '"';
$docs = $db->query('SELECT d.*, p.name as proyecto, c.name as cliente, u.name as uploader 
    FROM documents d JOIN projects p ON p.id = d.project_id JOIN clients c ON c.id = p.client_id JOIN app_users u ON u.id = d.uploaded_by ' . $where . ' ORDER BY d.uploaded_at DESC LIMIT 50')->fetchAll();
?>
<div class="search-bar">
  <input type="text" id="searchDocs" placeholder="Buscar documentos..." value="<?= htmlspecialchars($search) ?>">
  <select id="filterTipo" onchange="filtrarDocs()">
    <option value="todos" <?= $type === 'todos' ? 'selected' : '' ?>>Todos</option>
    <option value="presupuesto" <?= $type === 'presupuesto' ? 'selected' : '' ?>>📋 Presupuestos</option>
    <option value="plano" <?= $type === 'plano' ? 'selected' : '' ?>>📐 Planos</option>
    <option value="legal" <?= $type === 'legal' ? 'selected' : '' ?>>⚖️ Legales</option>
    <option value="avance" <?= $type === 'avance' ? 'selected' : '' ?>>📈 Avances</option>
    <option value="otro" <?= $type === 'otro' ? 'selected' : '' ?>>📎 Otros</option>
  </select>
</div>

<div class="card">
  <?php if (count($docs)): ?>
  <table id="docsTable">
    <tr><th>Tipo</th><th>Título</th><th>Proyecto</th><th>Cliente</th><th>Subido por</th><th>Fecha</th><th></th></tr>
    <?php 
    $iconos = ['presupuesto'=>'📋','plano'=>'📐','legal'=>'⚖️','avance'=>'📈','otro'=>'📎'];
    foreach ($docs as $d): ?>
    <tr class="doc-row" data-search="<?= strtolower(htmlspecialchars($d['title'].' '.$d['proyecto'].' '.$d['cliente'])) ?>">
      <td><span class="status" style="background:#e2e8f0"><?= $iconos[$d['type']] ?? '📎' ?> <?= $d['type'] ?></span></td>
      <td><strong><?= htmlspecialchars($d['title']) ?></strong></td>
      <td><?= htmlspecialchars($d['proyecto']) ?></td>
      <td><?= htmlspecialchars($d['cliente']) ?></td>
      <td style="font-size:0.8rem;color:#64748b"><?= htmlspecialchars($d['uploader']) ?></td>
      <td style="font-size:0.8rem;color:#64748b"><?= date('d/m/Y', strtotime($d['uploaded_at'])) ?></td>
      <td><a href="/uploads/<?= $d['file_path'] ?>" target="_blank" class="btn btn-sm btn-outline">📄 Abrir</a></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php else: ?>
  <div class="empty-state"><div class="icon">📄</div><p>No hay documentos</p></div>
  <?php endif; ?>
</div>

<script>
document.getElementById('searchDocs')?.addEventListener('input', function() {
  const q = this.value.toLowerCase();
  document.querySelectorAll('.doc-row').forEach(r => {
    r.style.display = r.dataset.search.includes(q) ? '' : 'none';
  });
});
function filtrarDocs() {
  const t = document.getElementById('filterTipo').value;
  loadTab('documentos&type=' + t);
}
</script>
