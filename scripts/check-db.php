<?php
$path = __DIR__ . '/../api/portal.db';
if (!file_exists($path)) { echo "MISSING: $path\n"; exit(1); }
try {
  $db = new PDO('sqlite:' . $path);
  $tables = $db->query('SELECT name FROM sqlite_master WHERE type="table" ORDER BY name')->fetchAll(PDO::FETCH_COLUMN);
  echo "TABLAS:\n";
  foreach ($tables as $t) echo $t . PHP_EOL;
  foreach ($tables as $t) {
    $c = $db->query('SELECT COUNT(*) FROM "'.$t.'"')->fetchColumn();
    echo sprintf("%-20s %d\n", $t, (int)$c);
  }
} catch (Throwable $e) { echo "ERR: " . $e->getMessage() . PHP_EOL; }
