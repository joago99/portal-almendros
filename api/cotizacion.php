<?php
/**
 * cotizacion.php — Recibe solicitudes de cotización (POST desde sitio web)
 * y muestra lista de cotizaciones (GET desde portal clientes)
 *
 * POST /api/cotizacion.php  → recibe formulario público (sin auth)
 * GET  /api/cotizacion.php  → HTML con tabla de cotizaciones (requiere auth staff/admin)
 */

header('Cache-Control: no-store');

// ─── GET: HTML view for portal ───
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    require_once __DIR__ . '/db.php';
    require_once __DIR__ . '/config.php';
    $auth = require_auth();
    $isStaff = in_array($auth['role'], ['admin', 'staff']);
    if (!$isStaff) { echo '<div class="empty-state"><div class="icon">🔒</div><p>Acceso restringido</p></div>'; exit; }
    $db = Database::get();
    $rows = $db->query('SELECT * FROM cotizaciones ORDER BY created_at DESC LIMIT 100')->fetchAll();
    if ($rows) {
?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;flex-wrap:wrap;gap:.5rem">
  <p style="color:#64748b;font-size:.9rem"><?= count($rows) ?> solicitude(s) — <span style="color:#16a34a;font-weight:600"><?= count(array_filter($rows, fn($r)=>$r['status']==='nueva')) ?></span> nueva(s)</p>
</div>
<div class="card" style="overflow-x:auto">
  <table>
    <tr>
      <th>Fecha</th><th>Nombre</th><th>Email</th><th>WhatsApp</th><th>Servicio</th><th>Avance</th><th>Comuna</th><th>m²</th><th>Detalle</th><th>Estado</th>
    </tr>
<?php foreach ($rows as $r): ?>
    <tr>
      <td style="white-space:nowrap;font-size:.8rem"><?= htmlspecialchars($r['created_at']) ?></td>
      <td><strong><?= htmlspecialchars($r['name']) ?></strong></td>
      <td><a href="mailto:<?= htmlspecialchars($r['email']) ?>" style="font-size:.85rem"><?= htmlspecialchars($r['email']) ?></a></td>
      <td><?= $r['whatsapp'] ? '<a href="https://wa.me/'.preg_replace('/[^0-9]/','',$r['whatsapp']).'" target="_blank" style="font-size:.85rem">'.htmlspecialchars($r['whatsapp']).'</a>' : '—' ?></td>
      <td><span class="status" style="background:#f6f1e7"><?= htmlspecialchars($r['service'] ?: '—') ?></span></td>
      <td style="font-size:.85rem"><?= htmlspecialchars($r['progress'] ?: '—') ?></td>
      <td style="font-size:.85rem"><?= htmlspecialchars($r['city'] ?: '—') ?></td>
      <td style="font-size:.85rem"><?= htmlspecialchars($r['area'] ?: '—') ?></td>
      <td style="font-size:.8rem;max-width:200px;overflow:hidden;text-overflow:ellipsis"><?= nl2br(htmlspecialchars($r['detail'] ?: '—')) ?></td>
      <td><span class="status" style="background:<?= $r['status']==='nueva'?'#fef3c7':'#dbeafe' ?>"><?= $r['status'] ?></span></td>
    </tr>
<?php endforeach; ?>
  </table>
</div>
<?php } else { ?>
<div class="empty-state"><div class="icon">📋</div><p>No hay solicitudes de cotización todavía.</p><p style="color:#94a3b8;font-size:.85rem">Cuando alguien envíe el formulario desde constructoralosalmendros.cl aparecerán aquí.</p></div>
<?php } ?>
    <script>
    document.title = 'Cotizaciones – Portal Los Almendros';
    </script>
<?php
    exit;
}

// ─── POST: receive from site (public, no auth) ───
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Access-Control-Allow-Origin: https://constructoralosalmendros.cl');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Content-Type: application/json; charset=utf-8');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!$data) {
        http_response_code(400);
        echo json_encode(['ok'=>false, 'error'=>'JSON inválido']);
        exit;
    }

    $name     = trim($data['name'] ?? '');
    $email    = trim($data['email'] ?? '');
    $whatsapp = trim($data['whatsapp'] ?? '');
    $service  = trim($data['service'] ?? '');
    $progress = trim($data['progress'] ?? '');
    $city     = trim($data['city'] ?? '');
    $area     = trim($data['area'] ?? '');
    $detail   = trim($data['detail'] ?? '');

    if (!$name || !$email) {
        http_response_code(400);
        echo json_encode(['ok'=>false, 'error'=>'Nombre y correo son obligatorios']);
        exit;
    }

    try {
        $dbPath = dirname(__DIR__) . '/portal.db';
        $db = new PDO('sqlite:' . $dbPath, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $db->exec('PRAGMA journal_mode=WAL');
        $db->exec('CREATE TABLE IF NOT EXISTS cotizaciones (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            created_at TEXT NOT NULL DEFAULT (datetime(\'now\', \'-3 hours\')),
            name TEXT NOT NULL,
            email TEXT NOT NULL,
            whatsapp TEXT,
            service TEXT,
            progress TEXT,
            city TEXT,
            area TEXT,
            detail TEXT,
            status TEXT NOT NULL DEFAULT \'nueva\',
            note TEXT
        )');

        $stmt = $db->prepare('INSERT INTO cotizaciones (name, email, whatsapp, service, progress, city, area, detail) VALUES (?,?,?,?,?,?,?,?)');
        $stmt->execute([$name, $email, $whatsapp, $service, $progress, $city, $area, $detail]);

        echo json_encode([
            'ok' => true,
            'id' => (int) $db->lastInsertId(),
            'message' => 'Solicitud recibida. Te contactaremos pronto.'
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['ok'=>false, 'error'=>'Error al guardar: ' . $e->getMessage()]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['ok'=>false, 'error'=>'Método no permitido']);
