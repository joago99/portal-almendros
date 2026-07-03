<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $email = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';

    if (!$email || !$password) {
        echo json_encode(['ok' => false, 'error' => 'Email y contraseña requeridos']);
        exit;
    }

    $db = Database::get();
    $stmt = $db->prepare('SELECT id, password_hash, role, name, force_password_change FROM app_users WHERE email = ? AND active = 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        echo json_encode(['ok' => false, 'error' => 'Credenciales inválidas']);
        exit;
    }

    session_start();
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['user_name'] = $user['name'];

    $db->prepare('UPDATE app_users SET last_login_at = datetime("now") WHERE id = ?')->execute([$user['id']]);

    echo json_encode([
        'ok' => true,
        'user' => [
            'id' => $user['id'],
            'role' => $user['role'],
            'name' => $user['name'],
            'force_password_change' => (bool)$user['force_password_change'],
        ]
    ]);
    exit;
}

// GET — mostrar formulario de login
session_start();
if (isset($_SESSION['user_id'])) {
    header('Location: /app.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Portal — Construcciones Los Almendros</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Inter', -apple-system, sans-serif; background: #f0f4f8; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
    .login-card { background: #fff; border-radius: 16px; padding: 2.5rem 2rem; width: 100%; max-width: 400px; box-shadow: 0 20px 60px rgba(0,0,0,0.08); }
    .login-logo { width: 48px; height: 48px; background: #0d9488; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 800; font-size: 1.25rem; margin-bottom: 1.25rem; }
    .login-card h1 { font-size: 1.25rem; font-weight: 700; color: #1e293b; margin-bottom: 0.25rem; }
    .login-card p { font-size: 0.85rem; color: #64748b; margin-bottom: 1.5rem; }
    .login-card label { display: block; font-size: 0.8rem; font-weight: 600; color: #475569; margin-bottom: 0.25rem; }
    .login-card input { width: 100%; padding: 0.6rem 0.75rem; border: 1px solid #cbd5e1; border-radius: 8px; margin-bottom: 0.75rem; font-family: inherit; font-size: 0.9rem; outline: none; transition: border-color .15s; }
    .login-card input:focus { border-color: #0d9488; box-shadow: 0 0 0 3px rgba(13,148,136,0.1); }
    .login-card button { width: 100%; padding: 0.65rem; background: #0d9488; color: #fff; border: none; border-radius: 8px; font-size: 0.9rem; font-weight: 600; cursor: pointer; margin-top: 0.5rem; }
    .login-card button:hover { background: #0f766e; }
    .login-error { background: #fef2f2; color: #dc2626; padding: 0.6rem 0.75rem; border-radius: 8px; font-size: 0.8rem; margin-bottom: 0.75rem; display: none; }
    .login-footer { text-align: center; font-size: 0.75rem; color: #94a3b8; margin-top: 1.25rem; }
  </style>
</head>
<body>
  <div class="login-card">
    <div class="login-logo">LA</div>
    <h1>Portal Construcciones Los Almendros</h1>
    <p>Ingresa con tu correo y contraseña</p>
    <div class="login-error" id="loginError"></div>
    <form id="loginForm">
      <label>Correo electrónico</label>
      <input type="email" name="email" id="emailInput" placeholder="tu@correo.cl" required autofocus>
      <label>Contraseña</label>
      <input type="password" name="password" id="passInput" placeholder="••••••••" required>
      <button type="submit">Ingresar</button>
    </form>
    <div class="login-footer">Constructora Los Almendros · Buin, Chile</div>
  </div>

  <script>
    document.getElementById('loginForm').addEventListener('submit', async (e) => {
      e.preventDefault();
      const errEl = document.getElementById('loginError');
      errEl.style.display = 'none';
      try {
        const res = await fetch('/auth/login.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            email: document.getElementById('emailInput').value.trim(),
            password: document.getElementById('passInput').value
          })
        });
        const d = await res.json();
        if (!d.ok) { errEl.textContent = d.error; errEl.style.display = 'block'; return; }
        window.location.href = '/app.php';
      } catch (err) { errEl.textContent = 'Error de conexión'; errEl.style.display = 'block'; }
    });
  </script>
</body>
</html>
