<?php
// Seed/repare known users in production DB with known passwords.
require_once __DIR__ . '/../api/db.php';
require_once __DIR__ . '/../api/config.php';
$db = Database::get();
$users = [
  ['joago@losalmendros.cl','admin','Joago','Partner2026!'],
  ['falcon@losalmendros.cl','staff','Falcon','Partner2026!'],
  ['tiguer@losalmendros.cl','staff','Tiguer_buin','Partner2026!'],
];
$out = [];
foreach ($users as $u) {
  $hash = password_hash($u[3], PASSWORD_DEFAULT);
  $stmt = $db->prepare('UPDATE app_users SET password_hash = ?, role = ?, active = 1 WHERE email = ?');
  $stmt->execute([$hash, $u[1], $u[0]]);
  $out[] = ['email'=>$u[0],'ok'=>true];
}
header('Content-Type: application/json');
echo json_encode(['ok'=>true,'users'=>$out]);
if (function_exists('opcache_reset')) { opcache_reset(); echo "\nOPCACHE_RESET"; }
