<?php
$base = 'https://portal.constructoralosalmendros.cl';
$ck = sys_get_temp_dir() . '/qa_av.txt';

// Login
$ch = curl_init("$base/api/auth/login.php");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['email'=>'admin@losalmendros.cl','password'=>'admin123']));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_COOKIEJAR, $ck);
$login = curl_exec($ch);
curl_close($ch);

function api($url, $data, $ck) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_COOKIEFILE, $ck);
    $r = curl_exec($ch);
    curl_close($ch);
    return $r;
}

// Avance 1: daily_log completo
$r1 = api("$base/api/progress.php", [
    'action' => 'create',
    'project_id' => 1,
    'title' => 'Fundaciones iniciadas',
    'description' => 'Se iniciaron excavaciones para fundaciones. Terreno en buen estado.',
    'event_date' => '2026-07-06',
    'percentage' => 15,
    'event_type' => 'daily_log',
    'team_present' => '3 albañiles, 1 operador retro',
    'weather_conditions' => 'soleado',
    'materials_used' => '30 sacos cemento, varillas acero',
    'incidents' => '',
], $ck);
echo "Avance 1: $r1\n";

// Avance 2: hito cimentacion
$r2 = api("$base/api/progress.php", [
    'action' => 'create',
    'project_id' => 1,
    'title' => 'Cimentacion completada',
    'description' => 'Fundaciones terminadas y aprobadas por inspector tecnico.',
    'event_date' => '2026-07-08',
    'percentage' => 100,
    'event_type' => 'cimentacion',
    'team_present' => '5 albañiles, 1 ingeniero',
    'weather_conditions' => 'soleado',
    'materials_used' => '50 sacos cemento, acero estructural',
    'incidents' => '',
], $ck);
echo "Avance 2: $r2\n";

// List
$ch = curl_init("$base/api/progress.php?action=list&project_id=1");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEFILE, $ck);
$list = curl_exec($ch);
curl_close($ch);
$d = json_decode($list, true);
$events = $d['data'] ?? [];
echo "\nEvents: " . count($events) . "\n";
echo "Overall: " . ($d['overall_pct'] ?? 0) . "%\n";
foreach ($d['milestones'] ?? [] as $m) {
    $ok = $m['completed'] ? '✓' : ' ';
    echo "  [$ok] {$m['label']} ({$m['weight_pct']}%)\n";
}
// Last 2
echo "\nLast 2 events:\n";
for ($i = 0; $i < min(2, count($events)); $i++) {
    $e = $events[$i];
    echo "  #{$e['id']} {$e['event_type']}: {$e['title']}\n";
    echo "    equipo: {$e['team_present']}\n";
}