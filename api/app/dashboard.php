<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../config.php';

session_start();
$userId = $_SESSION['user_id'] ?? null;
$userRole = $_SESSION['user_role'] ?? null;
$userName = $_SESSION['user_name'] ?? null;

if (!$userId) {
    http_response_code(401);
    echo '<p>No autenticado. <a href="/">Iniciar sesión</a></p>';
    exit;
}

$db = Database::get();
$isAdmin = $userRole === 'admin';
$isStaff = in_array($userRole, ['admin', 'staff']);

// Resumen
$proyectos = $db->query('SELECT p.*, c.name as client_name, 
    (SELECT COUNT(*) FROM payments WHERE project_id = p.id AND status = "pendiente") as pendientes,
    (SELECT COUNT(*) FROM payments WHERE project_id = p.id AND status = "atrasado") as atrasados,
    (SELECT COALESCE(SUM(amount_clp),0) FROM payments WHERE project_id = p.id AND status = "pagado") as pagado
    FROM projects p LEFT JOIN clients c ON c.id = p.client_id ORDER BY p.created_at DESC LIMIT 10')->fetchAll();

$clientes = $db->query('SELECT c.*, 
    (SELECT COUNT(*) FROM projects WHERE client_id = c.id) as obras_activas 
    FROM clients c ORDER BY c.created_at DESC LIMIT 5')->fetchAll();

$atrasados = $db->query('SELECT p.id, p.concept, p.amount_clp, p.due_date, pj.name as project_name, c.name as client_name
    FROM payments p 
    JOIN projects pj ON pj.id = p.project_id 
    JOIN clients c ON c.id = pj.client_id
    WHERE p.status = "pendiente" AND p.due_date < date("now")
    ORDER BY p.due_date ASC')->fetchAll();
?>
<div class="dashboard-grid">
    <div class="card summary">
        <h2>Resumen</h2>
        <div class="stats">
            <div class="stat">
                <span class="num"><?= count($proyectos) ?></span>
                <span class="label">Proyectos</span>
            </div>
            <div class="stat">
                <span class="num"><?= count($atrasados) ?></span>
                <span class="label">Pagos atrasados</span>
            </div>
            <div class="stat">
                <span class="num"><?= count($clientes) ?></span>
                <span class="label">Clientes</span>
            </div>
        </div>
    </div>

    <?php if ($atrasados): ?>
    <div class="card alerts">
        <h3>⚠️ Pagos atrasados</h3>
        <table>
            <tr><th>Cliente</th><th>Proyecto</th><th>Concepto</th><th>Monto</th><th>Vence</th></tr>
            <?php foreach ($atrasados as $a): ?>
            <tr class="atrasado-row">
                <td><?= htmlspecialchars($a['client_name']) ?></td>
                <td><?= htmlspecialchars($a['project_name']) ?></td>
                <td><?= htmlspecialchars($a['concept']) ?></td>
                <td>$<?= number_format($a['amount_clp'], 0, ',', '.') ?></td>
                <td><?= $a['due_date'] ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php endif; ?>

    <div class="card">
        <h3>Proyectos recientes</h3>
        <table>
            <tr><th>Proyecto</th><th>Cliente</th><th>Estado</th><th>Presupuesto</th><th>Pagado</th></tr>
            <?php foreach ($proyectos as $p): ?>
            <tr>
                <td><?= htmlspecialchars($p['name']) ?></td>
                <td><?= htmlspecialchars($p['client_name'] ?? '—') ?></td>
                <td><span class="status <?= $p['status'] ?>"><?= $p['status'] ?></span></td>
                <td>$<?= number_format($p['budget_clp'] ?? 0, 0, ',', '.') ?></td>
                <td>$<?= number_format($p['pagado'] ?? 0, 0, ',', '.') ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <?php if ($isStaff): ?>
    <div class="card actions">
        <h3>Acciones rápidas</h3>
        <div class="btn-group">
            <a href="/api/projects.php" class="btn">+ Nuevo proyecto</a>
            <a href="/api/payments.php" class="btn">+ Registrar pago</a>
            <a href="/api/clients.php" class="btn">+ Nuevo cliente</a>
            <a href="/api/documents.php" class="btn">+ Subir documento</a>
        </div>
    </div>
    <?php endif; ?>
</div>
<div class="dashboard-grid two-col">
    <?php if ($isAdmin): ?>
    <div class="card">
        <h3>Usuarios registrados (acceso admin)</h3>
        <?php
        $users = $db->query('SELECT email, role, name, last_login_at FROM app_users ORDER BY created_at DESC LIMIT 10')->fetchAll();
        ?>
        <table>
            <tr><th>Nombre</th><th>Email</th><th>Rol</th><th>Último acceso</th></tr>
            <?php foreach ($users as $u): ?>
            <tr>
                <td><?= htmlspecialchars($u['name']) ?></td>
                <td><?= htmlspecialchars($u['email']) ?></td>
                <td><?= $u['role'] ?></td>
                <td><?= $u['last_login_at'] ?? '—' ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php endif; ?>
</div>
