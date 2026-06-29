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
    $clients = $db->query('SELECT c.*, 
        (SELECT COUNT(*) FROM projects WHERE client_id = c.id) as total_projects,
        (SELECT COUNT(*) FROM projects p JOIN payments pay ON pay.project_id = p.id WHERE p.client_id = c.id AND pay.status = "atrasado") as atrasados
        FROM clients c ORDER BY c.created_at DESC')->fetchAll();
    header('Content-Type: text/html; charset=utf-8');
    echo '<h2>Clientes</h2>';
    if ($userRole === 'admin') echo '<button class="btn" onclick="showNewClientForm()" style="margin-bottom:1rem">+ Nuevo cliente</button>';
    echo '<table><tr><th>Nombre</th><th>Email</th><th>RUT</th><th>Teléfono</th><th>Proyectos</th><th>Atrasos</th><th>Acceso</th></tr>';
    foreach ($clients as $c) {
        $atraso = $c['atrasados'] > 0 ? '<span style="color:#dc2626">⚠️ '.$c['atrasados'].'</span>' : '0';
        echo '<tr>
            <td>'.htmlspecialchars($c['name']).'</td>
            <td>'.htmlspecialchars($c['email'] ?? '—').'</td>
            <td>'.htmlspecialchars($c['rut'] ?? '—').'</td>
            <td>'.htmlspecialchars($c['phone'] ?? '—').'</td>
            <td>'.$c['total_projects'].'</td>
            <td>'.$atraso.'</td>
            <td>'.($c['email'] ? '<a href="#" onclick="createUserAccess('.$c['id'].',\''.htmlspecialchars($c['email']).'\',\''.htmlspecialchars($c['name']).'\')">Dar acceso</a>' : '—').'</td>
        </tr>';
    }
    echo '</table>';
    echo '<div id="newClientForm" style="display:none;margin-top:2rem">
        <h3>Nuevo cliente</h3>
        <form id="clientForm" class="crud-form">
            <label>Nombre <input name="name" required></label>
            <label>Email <input name="email" type="email"></label>
            <label>RUT <input name="rut"></label>
            <label>Teléfono <input name="phone"></label>
            <button type="submit">Guardar</button>
        </form>
        <div id="clientMessage"></div>
    </div>
    <div id="createUserModal" style="display:none;margin-top:2rem">
        <h3>Crear acceso para cliente</h3>
        <form id="userCreateForm" class="crud-form">
            <input type="hidden" name="client_id">
            <label>Email <input name="email" readonly></label>
            <label>Nombre <input name="name" readonly></label>
            <label>Contraseña temporal <input name="password" required minlength="6"></label>
            <button type="submit">Crear acceso</button>
        </form>
        <div id="userCreateMessage"></div>
    </div>
    <script>
    function showNewClientForm() {
        document.getElementById("newClientForm").style.display = "block";
        document.getElementById("newClientForm").scrollIntoView({behavior:"smooth"});
    }
    function createUserAccess(clientId, email, name) {
        const m = document.getElementById("createUserModal");
        m.style.display = "block";
        m.querySelector("input[name=client_id]").value = clientId;
        m.querySelector("input[name=email]").value = email;
        m.querySelector("input[name=name]").value = name;
        m.scrollIntoView({behavior:"smooth"});
    }
    document.getElementById("clientForm")?.addEventListener("submit", async (e) => {
        e.preventDefault();
        const fd = new FormData(e.target);
        const body = Object.fromEntries(fd);
        const res = await fetch("/api/clients.php?action=create", {method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify(body)});
        const data = await res.json();
        const msg = document.getElementById("clientMessage");
        if(data.ok) { msg.innerHTML="✅ Cliente creado"; setTimeout(()=>location.reload(),1000); }
        else msg.innerHTML="❌ "+data.error;
    });
    document.getElementById("userCreateForm")?.addEventListener("submit", async (e) => {
        e.preventDefault();
        const fd = new FormData(e.target);
        const body = Object.fromEntries(fd);
        const res = await fetch("/api/clients.php?action=create_user", {method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify(body)});
        const data = await res.json();
        const msg = document.getElementById("userCreateMessage");
        if(data.ok) { msg.innerHTML="✅ Acceso creado. Usuario: "+data.email+" / clave temporal enviada"; setTimeout(()=>location.reload(),2000); }
        else msg.innerHTML="❌ "+data.error;
    });
    </script>';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'create') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input['name']) { echo json_encode(['ok'=>false,'error'=>'Nombre requerido']); exit; }
    $stmt = $db->prepare('INSERT INTO clients (name, email, rut, phone) VALUES (?,?,?,?)');
    $stmt->execute([$input['name'], $input['email'] ?: null, $input['rut'] ?: null, $input['phone'] ?: null]);
    echo json_encode(['ok' => true, 'id' => $db->lastInsertId()]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'create_user') {
    if ($userRole !== 'admin') { echo json_encode(['ok'=>false,'error'=>'Solo admin puede crear usuarios']); exit; }
    $input = json_decode(file_get_contents('php://input'), true);
    $clientId = $input['client_id'];
    $email = $input['email'];
    $name = $input['name'];
    $password = $input['password'];
    if (!$email || !$name || !$password) { echo json_encode(['ok'=>false,'error'=>'Faltan datos']); exit; }
    // Verificar si ya existe
    $stmt = $db->prepare('SELECT id FROM app_users WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) { echo json_encode(['ok'=>false,'error'=>'Email ya registrado']); exit; }
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $db->prepare('INSERT INTO app_users (email, password_hash, role, name, client_id, force_password_change) VALUES (?,?,?,?,?,1)')
        ->execute([$email, $hash, 'client', $name, $clientId]);
    echo json_encode(['ok'=>true, 'email'=>$email, 'message'=>'Usuario cliente creado']);
    exit;
}

echo json_encode(['ok'=>false, 'error'=>'Acción no válida']);
