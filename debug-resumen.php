<?php
$f = __DIR__ . '/api/resumen.php';
$c = file_get_contents($f);
echo substr($c, 0, 50) . "\n";
echo "last 50: " . substr($c, -50) . "\n";
echo "size=" . strlen($c) . "\n";
// Try requiring it
try {
    $result = require $f;
    echo "REQUIRE_OK\n";
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
