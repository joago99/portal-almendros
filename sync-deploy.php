<?php
// Sync FTP-uploaded file to PHP storage by read + write + opcache_reset
$files = [
    'api/avance.php',
];
foreach ($files as $f) {
    $path = __DIR__ . '/' . $f;
    if (file_exists($path)) {
        $content = file_get_contents($path);
        $written = file_put_contents($path, $content);
        echo "$f: {$written}bytes -> hash=" . sha1($content) . "\n";
    } else {
        echo "$f: NO EXISTE\n";
    }
}
if (function_exists('opcache_reset')) { opcache_reset(); echo "opcache_reset OK\n"; }
echo "SYNCED\n";
