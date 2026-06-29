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
$projectId = $_GET['project_id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($action === 'list' || $projectId)) {
    if ($projectId) {
        $stmt = $db->prepare('SELECT pay.*, p.name as project_name, c.name as client_name 
            FROM payments pay JOIN projects p ON p.id = pay.project_id JOIN clients c ON c.id = p.client_id
            WHERE pay.project_id = ? ORDER BY pay.due_date ASC');
        $stmt->execute([$projectId]);
    } else {
        $stmt = $db->query('SELECT pay.*, p.name as project_name, c.name as client_name 
            FROM payments pay JOIN projects p ON p.id = pay.project_id JOIN clients c ON c.id = p.client_id
            ORDER BY pay.due_date DESC LIMIT 50');
    }
    $payments = $stmt->fetchAll();
    
    header('Content-Type: text/html; charset=utf-8');
    $projectName = $payments[0]['project_name'] ?? 'Todos los pagos';
    echo '<h2>Pagos — '.htmlspecialchars($projectName).'</h2>';
    
    // Resumen
    $totalPendiente = array_sum(array_map(fn($p) => $p['status'] === 'pendiente' ? $p['amount_clp'] : 0, $payments));
    $totalPagado = array_sum(array_map(fn($p) => $p['status'] === 'pagado' ? $p['amount_clp'] : 0, $payments));
    $totalAtrasado = array_sum(array_map(fn($p) => ($p['status'] === 'pendiente' && strtotime($p['due_date']) < time()) ? $p['amount_clp'] : 0, $payments));
    echo '<div class="stats" style="margin-bottom:1rem">
        <div class="stat"><span class="num" style="color:#16a34a">$'.number_format($totalPagado,0,',','.').'</span><span class="label">Pagado</span></div>
        <div class="stat"><span class="num" style="color:#ca8a04">$'.number_format($totalPendiente,0,',','.').'</span><span class="label">Pendiente</span></div>
        <div class="stat"><span class="num" style="color:#dc2626">$'.number_format($totalAtrasado,0,',','.').'</span><span class="label">Atrasado</span></div>
    </div>';
    
    echo '<button class="btn" onclick="showPaymentForm('.$projectId.')" style="margin-bottom:1rem">+ Registrar pago</button>';
    echo '<table><tr><th>Cliente</th><th>Proyecto</th><th>Concepto</th><th>Monto</th><th>Vence</th><th>Pagado</th><th>Estado</th></tr>';
    $now = time();
    foreach ($payments as $pay) {
        $isOverdue = $pay['status'] === 'pendiente' && strtotime($pay['due_date']) < $now;
        $statusText = $isOverdue ? 'atrasado' : $pay['status'];
        $statusClass = $statusText;
        echo '<tr class="'.($isOverdue ? 'atrasado-row' : '').'">
            <td>'.htmlspecialchars($pay['client_name']).'</td>
            <td>'.htmlspecialchars($pay['project_name']).'</td>
            <td>'.htmlspecialchars($pay['concept']).'</td>
            <td>$'.number_format($pay['amount_clp'],0,',','.').'</td>
            <td>'.$pay['due_date'].'</td>
            <td>'.($pay['paid_at'] ?? '—').'</td>
            <td><span class="status '.$statusClass.'">'.$statusText.'</span></td>
        </tr>';
    }
    echo '</table>';
    
    echo '<div id="paymentForm" style="display:none;margin-top:2rem">
        <h3>Nuevo pago</h3>
        <form id="payForm" class="crud-form">
            <input type="hidden" name="project_id" value="'.($projectId ?? '').'">
            <label>Proyecto <select name="project_id">';
    $projects = $db->query('SELECT id, name FROM projects ORDER BY name')->fetchAll();
    foreach ($projects as $proj) {
        $sel = $proj['id'] == $projectId ? 'selected' : '';
        echo '<option value="'.$proj['id'].'" '.$sel.'>'.htmlspecialchars($proj['name']).'</option>';
    }
    echo '</select></label>
            <label>Concepto <input name="concept" required></label>
            <label>Monto CLP $ <input name="amount_clp" type="number" required></label>
            <label>Fecha vencimiento <input name="due_date" type="date" required></label>
            <button type="submit">Guardar</button>
        </form>
        <div id="payMessage"></div>
        <script>
        function showPaymentForm(pid) {
            document.getElementById("paymentForm").style.display = "block";
            document.getElementById("paymentForm").scrollIntoView({behavior:"smooth"});
        }
        document.getElementById("payForm")?.addEventListener("submit", async (e) => {
            e.preventDefault();
            const fd = new FormData(e.target);
            const body = Object.fromEntries(fd);
            body.amount_clp = parseFloat(body.amount_clp);
            body.project_id = parseInt(body.project_id);
            const res = await fetch("/api/payments.php?action=create", {method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify(body)});
            const data = await res.json();
            const msg = document.getElementById("payMessage");
            if(data.ok) { msg.innerHTML="✅ Pago registrado"; setTimeout(()=>location.reload(),1000); }
            else msg.innerHTML="❌ "+data.error;
        });
        </script>
    </div>';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'create') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input['project_id'] || !$input['concept'] || !$input['amount_clp'] || !$input['due_date']) {
        echo json_encode(['ok'=>false,'error'=>'Todos los campos son requeridos']); exit;
    }
    $stmt = $db->prepare('INSERT INTO payments (project_id, concept, amount_clp, due_date, status, created_by) VALUES (?,?,?,?,?,?)');
    $stmt->execute([$input['project_id'], $input['concept'], $input['amount_clp'], $input['due_date'], 'pendiente', $userId]);
    echo json_encode(['ok'=>true, 'id'=>$db->lastInsertId()]);
    exit;
}

echo json_encode(['ok'=>false, 'error'=>'Acción no válida']);
