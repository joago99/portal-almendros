<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../config.php';

session_start();
$userId = $_SESSION['user_id'] ?? null;
$userRole = $_SESSION['user_role'] ?? null;

if (!$userId || !in_array($userRole, ['admin', 'staff'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Acceso denegado']);
    exit;
}

$db = Database::get();
$action = $_GET['action'] ?? 'list';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'list') {
    $projects = $db->query('SELECT p.*, c.name as client_name,
        (SELECT COALESCE(SUM(amount_clp),0) FROM payments WHERE project_id = p.id AND status = "pagado") as total_pagado,
        (SELECT COUNT(*) FROM payments WHERE project_id = p.id AND status = "pendiente") as pendientes,
        (SELECT COUNT(*) FROM payments WHERE project_id = p.id AND status = "atrasado") as atrasados
        FROM projects p LEFT JOIN clients c ON c.id = p.client_id ORDER BY p.created_at DESC')->fetchAll();
    header('Content-Type: text/html; charset=utf-8');
    echo '<h2>Proyectos</h2>';
    if ($userRole === 'admin') echo '<a href="/api/projects.php?action=form" class="btn" style="margin-bottom:1rem;display:inline-block">+ Nuevo proyecto</a>';
    echo '<table><tr><th>Nombre</th><th>Cliente</th><th>Estado</th><th>Presupuesto</th><th>Pagado</th><th>Atrasados</th><th>Acciones</th></tr>';
    foreach ($projects as $p) {
        $atraso = $p['pendientes'] > 0 ? '<span style="color:#dc2626">⚠️ '.$p['pendientes'].' pend.</span>' : '—';
        echo '<tr>
            <td>'.htmlspecialchars($p['name']).'</td>
            <td>'.htmlspecialchars($p['client_name'] ?? '—').'</td>
            <td><span class="status '.$p['status'].'">'.$p['status'].'</span></td>
            <td>$'.number_format($p['budget_clp'] ?? 0, 0, ',', '.').'</td>
            <td>$'.number_format($p['total_pagado'] ?? 0, 0, ',', '.').'</td>
            <td>'.$atraso.'</td>
            <td><a href="/api/payments.php?project_id='.$p['id'].'">Ver pagos</a></td>
        </tr>';
    }
    echo '</table>';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'form') {
    $db = Database::get();
    $clients = $db->query('SELECT id, name FROM clients ORDER BY name')->fetchAll();
    header('Content-Type: text/html; charset=utf-8');
    echo '<h2>Nuevo proyecto</h2>
    <form id="projectForm" class="crud-form">
        <label>Nombre <input name="name" required></label>
        <label>Cliente <select name="client_id"><option value="">—</option>';
    foreach ($clients as $c) echo '<option value="'.$c['id'].'">'.htmlspecialchars($c['name']).'</option>';
    echo '</select></label>
        <label>Presupuesto CLP $ <input name="budget_clp" type="number"></label>
        <label>Dirección <input name="address"></label>
        <label>Inicio <input name="start_date" type="date"></label>
        <label>Término estimado <input name="end_date_estimated" type="date"></label>
        <label>Estado <select name="status"><option value="activo">Activo</option><option value="pausado">Pausado</option><option value="finalizado">Finalizado</option></select></label>
        <button type="submit">Guardar</button>
    </form>
    <div id="projectMessage"></div>
    <script>
    document.getElementById("projectForm").addEventListener("submit", async (e) => {
        e.preventDefault();
        const fd = new FormData(e.target);
        const body = Object.fromEntries(fd);
        body.budget_clp = parseFloat(body.budget_clp) || 0;
        body.client_id = body.client_id ? parseInt(body.client_id) : null;
        const res = await fetch("/api/projects.php?action=create", {method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify(body)});
        const data = await res.json();
        const msg = document.getElementById("projectMessage");
        if(data.ok) { msg.innerHTML="✅ Proyecto creado"; setTimeout(()=>location.reload(),1000); }
        else msg.innerHTML="❌ "+data.error;
    });
    </script>';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'create') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input['name']) { echo json_encode(['ok'=>false,'error'=>'Nombre requerido']); exit; }
    $stmt = $db->prepare('INSERT INTO projects (client_id, name, address, status, start_date, end_date_estimated, budget_clp) VALUES (?,?,?,?,?,?,?)');
    $stmt->execute([
        $input['client_id'] ?: null,
        $input['name'],
        $input['address'] ?? '',
        $input['status'] ?? 'activo',
        $input['start_date'] ?: null,
        $input['end_date_estimated'] ?: null,
        $input['budget_clp'] ?: 0,
    ]);
    echo json_encode(['ok'=>true, 'id'=>$db->lastInsertId()]);
    exit;
}

echo json_encode(['ok'=>false, 'error'=>'Acción no válida']);
