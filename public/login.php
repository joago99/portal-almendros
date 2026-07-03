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
        // Get client_id for client users
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
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
  <div class="container">
    <h1>Portal Los Almendros</h1>
    <?php
error_reporting(E_ALL);
ini_set('display_errors', 1); if ($message): ?>
      <div class="error"><?= htmlspecialchars($message) ?></div>
    <?php
error_reporting(E_ALL);
ini_set('display_errors', 1); endif; ?>
    <form method="POST" action="/login.php">
      <input name="email" type="email" placeholder="Email" required>
      <input name="password" type="password" placeholder="Contraseña" required>
      <button type="submit">Ingresar</button>
    </form>
    <p><a href="#" onclick="alert('Solicita restablecimiento de contraseña al administrador.')">Olvidé mi contraseña</a></p>
  </div>
</body>
</html>
