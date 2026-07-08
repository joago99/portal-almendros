<?php
$f = __DIR__ . '/api/resumen.php';
$content = file_get_contents($f);
file_put_contents($f, $content);
if (function_exists('opcache_reset')) opcache_reset();
echo "re-wrote " . filesize($f) . " bytes hash=" . sha1_file($f) . "\n";
