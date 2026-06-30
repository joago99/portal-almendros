<?php
// Seed usuarios: Joago (admin), Falcon (partner), Tiguer_buin (partner), cliente_prueba (client)
chdir(__DIR__);
$dbPath = __DIR__ . '/../api/portal.db';
if (!file_exists($dbPath)) { echo "ERROR: no se encuentra $dbPath\n"; exit(1); }
$db = new PDO("sqlite:$dbPath", null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$users = [
  ['name' => 'Joago', 'email' => 'joago@losalmendros.cl', 'pass' => 'admin123', 'role' => 'admin', 'client_id' => null],
  ['name' => 'Falcon', 'email' => 'falcon@losalmendros.cl', 'pass' => 'partner123', 'role' => 'staff', 'client_id' => null],
  ['name' => 'Tiguer_buin', 'email' => 'tiguer@losalmendros.cl', 'pass' => 'partner123', 'role' => 'staff', 'client_id' => null],
  ['name' => 'Cliente Prueba', 'email' => 'cliente@losalmendros.cl', 'pass' => 'cliente123', 'role' => 'client', 'client_id' => 1],
];

$inserted = 0;
foreach ($users as $u) {
  $chk = $db->prepare('SELECT id FROM app_users WHERE email = ?');
  $chk->execute([$u['email']]);
  if (!$chk->fetch()) {
    $hash = password_hash($u['pass'], PASSWORD_DEFAULT);
    $db->prepare('INSERT INTO app_users (name, email, password_hash, role, client_id, force_password_change, active) VALUES (?,?,?,?,?,0,1)')
      ->execute([$u['name'], $u['email'], $hash, $u['role'], $u['client_id']]);
    echo "✓ Creado: {$u['name']} ({$u['email']}) como {$u['role']}\n";
    $inserted++;
  } else {
    echo "• Ya existe: {$u['name']}\n";
  }
}
echo "Usuarios insertados: $inserted\n";
