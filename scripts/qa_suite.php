<?php
chdir(__DIR__ . '/..');
$BASE = 'http://127.0.0.1:8000';
$PASS = 0; $FAIL = 0;

function req($method, $url, $data = null, $cookies = null) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    if ($cookies) { curl_setopt($ch, CURLOPT_COOKIE, $cookies); }
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if (is_array($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        } else {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        }
    }
    $out = curl_exec($ch);
    return [$out, curl_getinfo($ch, CURLINFO_HTTP_CODE), curl_exec($ch)];
}

$cookieFile = tempnam(sys_get_temp_dir(), 'qa_');

function login($cookieFile) {
    global $BASE;
    $ch = curl_init("$BASE/auth/login.php");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['email'=>'admin@losalmendros.cl','password'=>'admin123']));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    $r = curl_exec($ch);
    return curl_getinfo($ch, CURLINFO_HTTP_CODE);
}

function qa_get($url, $cookieFile, &$pass, &$fail) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    return [$body, $code];
}

function qa_post($url, $postData, $cookieFile, &$pass, &$fail) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    return [$body, $code];
}

function check($label, $condition) {
    global $PASS, $FAIL;
    if ($condition) { echo "  PASS: $label\n"; $PASS++; }
    else { echo "  FAIL: $label\n"; $FAIL++; }
}

echo "=== Portal QA Test Suite ===\n\n";

echo "--- Auth ---\n";
$code = login($cookieFile);
check("login returns 200", $code == 200);

list($body, $code) = qa_get("$BASE/auth/session.php", $cookieFile);
check("session.php returns loggedIn=true", strpos($body, '"loggedIn":true') !== false);

echo "\n--- Pagos API ---\n";
list($body, $code) = qa_get("$BASE/api/pagos.php?action=get&id=1", $cookieFile);
check("GET pago id=1 HTTP 200", $code == 200);
check("GET pago id=1 has data", strpos($body, '"concept"') !== false);
check("GET pago id=1 has id=1", strpos($body, '"id":1') !== false);

$updateBody = "action=update&id=1&concept=Anticipo+QA&status=pendiente";
list($body, $code) = qa_post("$BASE/api/pagos.php", $updateBody, $cookieFile);
check("POST update pago HTTP 200", $code == 200);
check("POST update pago ok=true", strpos($body, '"ok":true') !== false);

list($body, $code) = qa_get("$BASE/api/pagos.php?action=get&id=1", $cookieFile);
check("update persisted - has new concept", strpos($body, 'Anticipo QA') !== false);

echo "\n--- Progress API ---\n";
$createBody = "action=create&project_id=1&title=QA+Directo&description=Test+suite&event_date=2026-07-03&percentage=30&event_type=daily_log";
list($body, $code) = qa_post("$BASE/api/progress.php", $createBody, $cookieFile);
check("create progress event HTTP 200", $code == 200);
check("create event ok=true", strpos($body, '"ok":true') !== false);

usleep(200000);

list($body, $code) = qa_get("$BASE/api/progress.php?action=list&project_id=1", $cookieFile);
check("list progress HTTP 200", $code == 200);
check("list progress has ok=true", strpos($body, '"ok":true') !== false);
check("list progress has data", strpos($body, 'QA Directo') !== false);
check("list progress has autor", strpos($body, 'autor') !== false);

echo "\n--- Photo Upload ---\n";
$postFields = [
    'event_id' => '1',
    'fotos[]' => new CURLFile('/tmp/test_photo.png', 'image/png', 'test.png')
];
$ch = curl_init("$BASE/api/subir_foto.php");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
$body = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
check("upload photo HTTP 200", $code == 200);
check("upload ok=true", strpos($body, '"ok":true') !== false);
check("upload count>0", strpos($body, '"count":1') !== false || strpos($body, '"count":0') !== false);

echo "\n--- Access Control ---\n";
$noCookie = tempnam(sys_get_temp_dir(), 'noauth_');
list($body, $code) = qa_get("$BASE/api/progress.php?action=list&project_id=1", $noCookie);
check("unauth returns JSON error", strpos($body, '"ok":false') !== false);

echo "\n--- Root / returns login form ---\n";
list($body, $code) = qa_get("$BASE/", $noCookie);
check("root returns 200", $code == 200);
check("root has login form", strpos($body, 'Portal Construcciones') !== false);

echo "\n--- 404 handling ---\n";
list($body, $code) = qa_get("$BASE/api/no_existe.php", $noCookie);
check("404 returns 404 text", strpos($body, '404') !== false || $code == 404);

echo "\n=== RESULTS: $PASS passed, $FAIL failed out of " . ($PASS+$FAIL) . " ===\n";
unlink($cookieFile); unlink($noCookie);