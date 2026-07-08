<?php
// Test renderTL con datos reales (simula lo que hace cargarAvance)
$base = 'https://portal.constructoralosalmendros.cl';

// Primero hacer login y mantener cookie como hace el browser
$ck = sys_get_temp_dir() . '/qa_browser.txt';

// 1. Login
$ch = curl_init("$base/api/auth/login.php");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['email'=>'admin@losalmendros.cl','password'=>'admin123']));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_COOKIEJAR, $ck);
curl_setopt($ch, CURLOPT_COOKIEFILE, $ck);
$login = curl_exec($ch);
echo "Login: " . ($login ? 'OK' : 'FAIL') . "\n";
curl_close($ch);

// 2. List progress (exactamente como lo haría cargarAvance)
$ch = curl_init("$base/api/progress.php?action=list&project_id=1");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEFILE, $ck);
curl_setopt($ch, CURLOPT_COOKIEJAR, $ck);
$list = curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
echo "List HTTP=$http\n";
$data = json_decode($list, true);
echo "ok=" . ($data['ok'] ? 'true' : 'false') . "\n";
echo "events=" . count($data['data'] ?? []) . "\n";
echo "milestones=" . count($data['milestones'] ?? []) . "\n";
echo "overall_pct=" . ($data['overall_pct'] ?? 0) . "\n";
echo "finanzas=" . (isset($data['finanzas']) ? 'SI' : 'NO') . "\n";

// 3. Try to create (simula saveAvanceForm)
if ($data['ok']) {
    $ch = curl_init("$base/api/progress.php");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, "action=create&project_id=1&title=Browser+Test&description=post+login&event_date=2026-07-08&percentage=10&event_type=daily_log&team_present=&weather_conditions=&materials_used=&incidents=");
    curl_setopt($ch, CURLOPT_COOKIEFILE, $ck);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $ck);
    $create = curl_exec($ch);
    echo "Create: $create\n";
}
