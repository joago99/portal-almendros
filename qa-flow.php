<?php
// Simular el flujo: crear avance y luego ver que renderTL muestra
$base = 'https://portal.constructoralosalmendros.cl';
$ck = sys_get_temp_dir() . '/qa_flow.txt';

// 1. Login
$ch = curl_init("$base/api/auth/login.php");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['email'=>'admin@losalmendros.cl','password'=>'admin123']));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_COOKIEJAR, $ck);
curl_setopt($ch, CURLOPT_COOKIEFILE, $ck);
$login = curl_exec($ch);
echo "Login: " . substr($login, 0, 40) . "\n";

// 2. Create avance via POST (como saveAvanceForm)
$ch2 = curl_init("$base/api/progress.php");
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch2, CURLOPT_POST, true);
curl_setopt($ch2, CURLOPT_POSTFIELDS, "action=create&project_id=1&title=QA+Flow+Test&description=probando+flujo&event_date=2026-07-08&percentage=25&event_type=daily_log&team_present=2+albaniles&weather_conditions=soleado&materials_used=cemento&incidents=");
curl_setopt($ch2, CURLOPT_COOKIEFILE, $ck);
$create = curl_exec($ch2);
echo "Create: $create\n";

// 3. Sleep briefly like browser
usleep(500000);

// 4. List events — debería incluir el nuevo
$ch3 = curl_init("$base/api/progress.php?action=list&project_id=1");
curl_setopt($ch3, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch3, CURLOPT_COOKIEFILE, $ck);
$list = curl_exec($ch3);
$data = json_decode($list, true);
$events = $data['data'] ?? [];
$milestones = $data['milestones'] ?? [];
$finanzas = $data['finanzas'] ?? [];
echo "Events: " . count($events) . " milestones: " . count($milestones) . "\n";
echo "Latest: " . ($events[0]['title'] ?? 'none') . " - " . ($events[0]['percentage'] ?? 0) . "%\n";
echo "Finanzas: budget=" . ($finanzas['budget_clp'] ?? 0) . " pagado=" . ($finanzas['total_pagado'] ?? 0) . "\n";
echo "Alerts: " . count($finanzas['alerts'] ?? []) . "\n";
echo "OK\n";
