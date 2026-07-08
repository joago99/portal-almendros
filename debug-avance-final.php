<?php
$base = 'https://portal.constructoralosalmendros.cl';
$ck = sys_get_temp_dir() . '/qa_debug.txt';

// Login (mantener cookie)
$ch = curl_init("$base/api/auth/login.php");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode(['email'=>'admin@losalmendros.cl','password'=>'admin123']),
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_COOKIEJAR => $ck,
    CURLOPT_COOKIEFILE => $ck,
]);
curl_exec($ch);
curl_close($ch);

// List progress (lo mismo que hace cargarAvance)
$ch = curl_init("$base/api/progress.php?action=list&project_id=1");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEFILE => $ck,
]);
$list = curl_exec($ch);
$d = json_decode($list, true);

echo "API ok: " . ($d['ok'] ? 'true' : 'false') . "\n";
echo "Events: " . count($d['data'] ?? []) . "\n";
echo "Overall: " . ($d['overall_pct'] ?? 0) . "%\n";

// Verificar que los eventos tienen los campos que renderTL espera
$evs = $d['data'] ?? [];
echo "\nPrimeros 3 eventos:\n";
for ($i = 0; $i < min(3, count($evs)); $i++) {
    $e = $evs[$i];
    $hasTitle = isset($e['title']) ? 'OK' : 'FALTA';
    $hasDesc = isset($e['description']) ? 'OK' : 'FALTA';
    $hasEventType = isset($e['event_type']) ? 'OK' : 'FALTA';
    $hasAutor = isset($e['autor']) ? 'OK' : 'FALTA';
    $hasFotos = isset($e['fotos']) ? 'OK' : 'FALTA';
    $hasTeam = isset($e['team_present']) ? 'OK' : 'FALTA';
    $hasWeather = isset($e['weather_conditions']) ? 'OK' : 'FALTA';
    echo "  #{$e['id']} title=$hasTitle desc=$hasDesc type=$hasEventType autor=$hasAutor fotos=$hasFotos team=$hasTeam weather=$hasWeather\n";
}

echo "\nMilestones:\n";
foreach ($d['milestones'] ?? [] as $m) {
    $ok = $m['completed'] ? 'SI' : 'NO';
    echo "  {$m['milestone_type']}: completado=$ok peso={$m['weight_pct']}%\n";
}