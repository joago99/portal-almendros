<?php
// Portal Construcciones Los Almendros
session_start();
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
$uri = str_replace('/portal-almendros', '', $uri);

$public = ['/login.php', '/logout.php', '/auth-reset.php', '/assets/css/style.css'];
if (in_array($uri, $public) || str_starts_with($uri, '/assets/')) {
  return false;
}

if (!isset($_SESSION['user_id'])) {
  header('Location: /login.php');
  exit();
}

$userRole = $_SESSION['user_role'] ?? 'client';
$isAdmin = $userRole === 'admin';
$isStaff = $userRole === 'staff';
$isClient = $userRole === 'client';

$publicPages = ['dashboard'];
$page = $uri === '/' ? 'dashboard' : trim($uri, '/');

$adminOnly = ['clients', 'projects', 'payments', 'documents', 'settings'];
$staffOrAdmin = ['clients', 'projects', 'payments', 'documents', 'progress', 'settings'];

if (in_array($page, $adminOnly) && !$isAdmin) {
  header('Location: /dashboard');
  exit();
}
if (in_array($page, $staffOrAdmin) && !$isAdmin && !$isStaff) {
  header('Location: /dashboard');
  exit();
}

$view = __DIR__ . '/../views/' . $page . '.php';
if (!file_exists($view)) {
  http_response_code(404);
  echo 'Página no encontrada';
  exit();
}
require $view;
