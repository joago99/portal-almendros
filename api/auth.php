<?php
require_once __DIR__ . '/db.php';

class Auth {
  public static function login(string $email, string $password): array {
    $db = Database::get();
    $stmt = $db->prepare('SELECT id, email, password_hash, role, name, force_password_change FROM app_users WHERE email = :email AND active = 1');
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    if (!$user) {
      return ['ok' => false, 'error' => 'Credenciales inválidas.'];
    }
    if (!password_verify($password, $user['password_hash'])) {
      return ['ok' => false, 'error' => 'Credenciales inválidas.'];
    }

    // Login satisfactorio
    unset($user['password_hash']);
    
    // Regenerar ID de sesión para evitar fijación
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_force_password_change'] = $user['force_password_change'];

    // Actualizar último login
    $db->prepare('UPDATE app_users SET last_login_at = NOW() WHERE id = :id')->execute([':id' => $user['id']]);

    return ['ok' => true, 'user' => $user];
  }

  public static function logout(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
      $params = session_get_cookie_params();
      setcookie(session_name(), '', [
        'expires' => time() - 42000,
        'path' => $params['path'],
        'domain' => $params['domain'],
        'secure' => $params['secure'],
        'httponly' => $params['httponly'],
        'samesite' => $params['samesite'] ?? 'Lax',
      ]);
    }
    session_destroy();
  }

  public static function currentUser(): ?array {
    if (!isset($_SESSION['user_id'])) {
      return null;
    }

    $db = Database::get();
    $stmt = $db->prepare('SELECT id, email, role, name, force_password_change, last_login_at FROM app_users WHERE id = :id AND active = 1');
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
      self::logout();
      return null;
    }

    // Sincronizar flag de cambio de contraseña
    if (isset($user['force_password_change']) && $user['force_password_change']) {
      $_SESSION['user_force_password_change'] = true;
    }

    return $user;
  }

  public static function requireLogin(): void {
    if (!self::currentUser()) {
      header('Location: /login.php');
      exit;
    }
  }

  public static function requireAdmin(): void {
    $user = self::currentUser();
    if (!$user || $user['role'] !== 'admin') {
      http_response_code(403);
      echo 'Acceso denegado.';
      exit;
    }
  }

  public static function requireChangePassword(): void {
    if (!isset($_SESSION['user_force_password_change']) || !$_SESSION['user_force_password_change']) {
      header('Location: /dashboard');
      exit;
    }
  }

  public static function changePassword(string $currentPassword, string $newPassword): array {
    $user = self::currentUser();
    if (!$user) {
      return ['ok' => false, 'error' => 'Sesión expirada.'];
    }

    $db = Database::get();
    $stmt = $db->prepare('SELECT password_hash FROM app_users WHERE id = :id');
    $stmt->execute([':id' => $user['id']]);
    $row = $stmt->fetch();

    if (!password_verify($currentPassword, $row['password_hash'])) {
      return ['ok' => false, 'error' => 'Contraseña actual incorrecta.'];
    }

    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
    $db->prepare('UPDATE app_users SET password_hash = :hash, force_password_change = 0 WHERE id = :id')
      ->execute([':hash' => $newHash, ':id' => $user['id']]);

    $_SESSION['user_force_password_change'] = false;

    // Registrar en auditoría
    Audit::log('change_password', 'app_users', $user['id'], ['email' => $user['email']]);

    return ['ok' => true];
  }

  public static function createUser(string $email, string $name, string $password, string $role, ?string $rut = null, ?string $phone = null): array {
    $db = Database::get();

    // Verificar que no exista
    $stmt = $db->prepare('SELECT id FROM app_users WHERE email = :email');
    $stmt->execute([':email' => $email]);
    if ($stmt->fetch()) {
      return ['ok' => false, 'error' => 'El email ya está registrado.'];
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $db->beginTransaction();

    try {
      // Si es cliente, crear en tabla clients y obtener ID
      $clientId = null;
      if ($role === 'client') {
        $stmt = $db->prepare('INSERT INTO clients (name, rut, email, phone) VALUES (:name, :rut, :email, :phone)');
        $stmt->execute([
          ':name' => $name,
          ':rut' => $rut,
          ':email' => $email,
          ':phone' => $phone,
        ]);
        $clientId = $db->lastInsertId();
      }

      // Crear usuario en auth/app_users
      $stmt = $db->prepare('INSERT INTO app_users (email, password_hash, role, name, client_id, force_password_change) VALUES (:email, :hash, :role, :name, :client_id, 1)');
      $stmt->execute([
        ':email' => $email,
        ':hash' => $passwordHash,
        ':role' => $role,
        ':name' => $name,
        ':client_id' => $clientId,
      ]);
      $userId = $db->lastInsertId();

      $db->commit();

      Audit::log('create_user', 'app_users', $userId, [
        'email' => $email,
        'role' => $role,
        'name' => $name,
      ]);

      return [
        'ok' => true,
        'user' => [
          'id' => $userId,
          'email' => $email,
          'name' => $name,
          'role' => $role,
        ],
      ];
    } catch (Exception $e) {
      $db->rollBack();
      return ['ok' => false, 'error' => 'Error al crear usuario: ' . $e->getMessage()];
    }
  }

  public static function requestPasswordReset(string $email): array {
    $db = Database::get();
    $stmt = $db->prepare('SELECT id, name FROM app_users WHERE email = :email AND active = 1');
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    if (!$user) {
      // No revelar si existe o no
      return ['ok' => true, 'message' => 'Si el email existe, recibirás instrucciones.'];
    }

    // Generar token de reseteo
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', time() + 3600); // 1 hora

    $stmt = $db->prepare('INSERT INTO password_resets (user_id, token, expires_at) VALUES (:uid, :token, :expires)');
    $stmt->execute([
      ':uid' => $user['id'],
      ':token' => $token,
      ':expires' => $expires,
    ]);

    // URL de reseteo
    $resetUrl = $GLOBALS['baseUrl'] . '/auth-reset.php?token=' . $token;

    // Enviar email (requiere configuración SMTP en el servidor)
    // Por ahora, devolvemos la URL para que se vea en producción
    Audit::log('password_reset_request', 'app_users', $user['id'], ['email' => $email]);

    return [
      'ok' => true,
      'message' => 'Si el email existe, recibirás instrucciones.',
    ];
  }

  public static function resetPassword(string $token, string $newPassword): array {
    $db = Database::get();
    $stmt = $db->prepare('SELECT user_id FROM password_resets WHERE token = :token AND expires_at > NOW() AND used = 0');
    $stmt->execute([':token' => $token]);
    $row = $stmt->fetch();

    if (!$row) {
      return ['ok' => false, 'error' => 'Token inválido o expirado.'];
    }

    $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
    $db->beginTransaction();

    try {
      // Actualizar contraseña
      $db->prepare('UPDATE app_users SET password_hash = :hash, force_password_change = 0 WHERE id = :id')
        ->execute([':hash' => $passwordHash, ':id' => $row['user_id']]);

      // Marcar token como usado
      $db->prepare('UPDATE password_resets SET used = 1 WHERE token = :token')
        ->execute([':token' => $token]);

      $db->commit();

      Audit::log('password_reset_complete', 'app_users', $row['user_id'], []);

      return ['ok' => true];
    } catch (Exception $e) {
      $db->rollBack();
      return ['ok' => false, 'error' => 'Error al restablecer contraseña.'];
    }
  }
}

