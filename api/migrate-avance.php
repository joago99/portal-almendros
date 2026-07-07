<?php
$dbPath = __DIR__ . '/api/portal.db';
$db = new PDO("sqlite:$dbPath", null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

echo "=== Migrating progress_events ===\n";

// New columns for enriched avance form
$columns = [
    'team_present' => "ALTER TABLE progress_events ADD COLUMN team_present TEXT DEFAULT ''",
    'weather_conditions' => "ALTER TABLE progress_events ADD COLUMN weather_conditions TEXT DEFAULT ''",
    'materials_used' => "ALTER TABLE progress_events ADD COLUMN materials_used TEXT DEFAULT ''",
    'incidents' => "ALTER TABLE progress_events ADD COLUMN incidents TEXT DEFAULT ''",
];

foreach ($columns as $name => $sql) {
    try {
        $db->exec($sql);
        echo "  + $name\n";
    } catch (Exception $e) {
        echo "  $name: " . $e->getMessage() . "\n";
    }
}

// Create project_milestones table
echo "\n=== Creating project_milestones ===\n";
$db->exec("
CREATE TABLE IF NOT EXISTS project_milestones (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    project_id INTEGER NOT NULL,
    milestone_type TEXT NOT NULL,
    label TEXT NOT NULL,
    seq INTEGER NOT NULL DEFAULT 0,
    weight_pct INTEGER NOT NULL DEFAULT 0,
    completed INTEGER NOT NULL DEFAULT 0,
    completed_at TEXT,
    created_at TEXT DEFAULT (datetime('now')),
    FOREIGN KEY (project_id) REFERENCES projects(id)
)");

$exists = $db->query("SELECT COUNT(*) FROM project_milestones")->fetchColumn();
echo "  existing rows: $exists\n";

if ($exists == 0) {
    $milestones = [
        ['cimentacion',   'Cimentación',       1, 15],
        ['albanileria',   'Albañilería / OG',   2, 20],
        ['techumbre',     'Techumbre',          3, 15],
        ['terminaciones', 'Terminaciones',       4, 25],
        ['recepcion',     'Recepción Municipal', 5, 25],
    ];

    $projects = $db->query("SELECT id FROM projects")->fetchAll(PDO::FETCH_COLUMN);
    $insert = $db->prepare("INSERT INTO project_milestones (project_id, milestone_type, label, seq, weight_pct) VALUES (?, ?, ?, ?, ?)");
    
    foreach ($projects as $pid) {
        foreach ($milestones as $m) {
            $insert->execute([$pid, $m[0], $m[1], $m[2], $m[3]]);
        }
        echo "  seeded project #$pid\n";
    }
    echo "  total rows: " . $db->query("SELECT COUNT(*) FROM project_milestones")->fetchColumn() . "\n";
}

echo "\n=== Migration OK ===\n";
