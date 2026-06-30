<?php
$db = new PDO("sqlite:api/portal.db", null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$fixes = [
  "ALTER TABLE projects ADD COLUMN start_date DATE DEFAULT NULL",
  "ALTER TABLE projects ADD COLUMN end_date_estimated DATE DEFAULT NULL",
];
foreach ($fixes as $sql) {
  try { $db->exec($sql); echo "ok: " . substr($sql, 0, 40) . "\n"; }
  catch (Exception $e) { echo "skip: " . $e->getMessage() . "\n"; }
}
echo "done";
