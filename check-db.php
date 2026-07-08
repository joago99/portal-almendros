<?php
$db = new PDO('sqlite:' . __DIR__ . '/api/portal.db');
$tables = $db->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name");
echo "tables:\n";
foreach ($tables as $t) echo "  " . $t['name'] . "\n";
echo "milestones: " . $db->query("SELECT COUNT(*) FROM project_milestones")->fetchColumn() . " rows\n";
echo "completed: " . $db->query("SELECT COUNT(*) FROM project_milestones WHERE completed=1")->fetchColumn() . "\n";
