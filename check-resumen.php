<?php
$f = __DIR__ . '/api/resumen.php';
echo "exists=" . (file_exists($f) ? 'SI' : 'NO') . "\n";
echo "size=" . filesize($f) . "\n";
echo "hash=" . sha1_file($f) . "\n";
// Try to parse
try {
    $tokens = token_get_all(file_get_contents($f));
    echo "tokens=" . count($tokens) . "\n";
    echo "OK\n";
} catch (Exception $e) {
    echo "PARSE ERROR: " . $e->getMessage() . "\n";
}
