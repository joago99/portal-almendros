<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../config.php';
session_start();
$userId = $_SESSION['user_id'] ?? null;
if (!$userId) exit;
$db = Database::get();
$pid = $_GET['project_id'] ?? null;
$projects = $db->query('SELECT id, name FROM projects ORDER BY name')->fetchAll();
?>
<h3>📌 Nuevo pago</h3>
<form id="payForm">
  <?php if (!$pid): ?>
  <label>Proyecto</label>
  <select name="project_id" required>
    <?php foreach ($projects as $p): ?><option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option><?php endforeach; ?>
  </select>
  <?php else: ?>
  <input type="hidden" name="project_id" value="<?= (int)$pid ?>">
  <?php endif; ?>
  <label>Concepto</label><input name="concept" required>
  <label>Monto CLP $</label><input name="amount_clp" type="number" required>
  <label>Fecha de vencimiento</label><input name="due_date" type="date" required value="<?= date('Y-m-d', strtotime('+30 days')) ?>">
  <div class="modal-actions">
    <button type="button" class="btn btn-outline" onclick="closeModal()">Cancelar</button>
    <button type="submit" class="btn btn-primary">Registrar pago</button>
  </div>
</form>
<script>
document.getElementById("payForm")?.addEventListener("submit", async (e) => {
  e.preventDefault();
  const fd = new FormData(e.target);
  const body = Object.fromEntries(fd);
  body.amount_clp = parseFloat(body.amount_clp);
  body.project_id = parseInt(body.project_id);
  const res = await fetch("/api/pagos.php?action=create", {method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify(body)});
  const d = await res.json();
  if(d.ok) { showToast("Pago registrado ✅"); closeModal(); loadTab("pagos"); }
  else showToast(d.error, "error");
});
</script>
