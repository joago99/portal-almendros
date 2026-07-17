<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../api/db.php';
require_once __DIR__ . '/../api/config.php';
header('Content-Type: text/html; charset=utf-8');

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pass = $_POST['password'] ?? '';

    $db = Database::get();
    $stmt = $db->prepare('SELECT id, password_hash, role, name FROM app_users WHERE email = ? AND active = 1 AND (expires_at IS NULL OR expires_at >= date("now"))');
    $stmt->execute([$email]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row && password_verify($pass, $row['password_hash'])) {
        session_start();
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$row['id'];
        $_SESSION['user_role'] = $row['role'];
        $_SESSION['user_name'] = $row['name'];
        if ($row['role'] === 'client') {
            $st = $db->prepare('SELECT client_id FROM app_users WHERE id = ?');
            $st->execute([$row['id']]);
            $cu = $st->fetch();
            $_SESSION['client_id'] = $cu ? (int)$cu['client_id'] : null;
        }
        $db->prepare('UPDATE app_users SET last_login_at = datetime("now") WHERE id = ?')->execute([$row['id']]);
        header('Location: /app.php');
        exit;
    }
    $message = 'Credenciales inválidas.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Portal - Construcciones Los Almendros</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Playfair+Display:wght@500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
  <div class="headerbar"></div>
  <div class="section">
    <div class="content-wrap">
      <div class="container">
        <span class="section-label">Acceso</span>
        <h1 class="section-title">Construcciones Los Almendros</h1>
        <p class="section-description">Ingresá al portal para ver el estado de tu obra.</p>

        <?php if ($message): ?>
          <div class="empty-state" style="color:#111111"><p><?= htmlspecialchars($message) ?></p></div>
        <?php endif; ?>

        <form method="POST" action="/login.php" style="margin-top:1.25rem;text-align:left">
          <label>Email</label>
          <input name="email" type="email" placeholder="tu@email.cl" required autocomplete="email">
          <label>Contraseña</label>
          <input name="password" type="password" placeholder="••••••••" required autocomplete="current-password">
          <button type="submit" class="btn btn-primary" style="width:100%;margin-top:0.75rem">Ingresar</button>
        </form>

        <p style="margin-top:1rem;font-size:0.85rem;color:#57534e">
          <a href="#" onclick="alert('Solicita restablecimiento de contraseña al administrador.')">Olvidé mi contraseña</a>
        </p>
      </div>
    </div>
  </div>
</body>
</html>
