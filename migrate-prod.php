<?php
$dbPath = __DIR__ . '/api/portal.db';
$db = new PDO("sqlite:$dbPath", null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

echo "=== Migrando DB ===";

// Add columns
$cols = ['team_present','weather_conditions','materials_used','incidents'];
foreach ($cols as $c) {
    try {
        $db->exec("ALTER TABLE progress_events ADD COLUMN $c TEXT DEFAULT ''");
        echo "\n  + $c";
    } catch (Exception $e) {
        echo "\n  $c: " . ($e->getMessage() ? 'ya existe' : 'ok');
    }
}

// Create milestones table
$db->exec("CREATE TABLE IF NOT EXISTS project_milestones (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    project_id INTEGER NOT NULL,
    milestone_type TEXT NOT NULL,
    label TEXT NOT NULL,
    seq INTEGER NOT NULL DEFAULT 0,
    weight_pct INTEGER NOT NULL DEFAULT 0,
    completed INTEGER NOT NULL DEFAULT 0,
    completed_at TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id)
)");
echo "\n  + project_milestones";

// Seed milestones for projects that don't have them
$cnt = $db->query("SELECT COUNT(*) FROM project_milestones")->fetchColumn();
if ($cnt == 0) {
    $ms = [
        ['cimentacion','Cimentación',1,15],
        ['albanileria','Albañilería / OG',2,20],
        ['techumbre','Techumbre',3,15],
        ['terminaciones','Terminaciones',4,25],
        ['recepcion','Recepción Municipal',5,25],
    ];
    $ins = $db->prepare("INSERT INTO project_milestones (project_id,milestone_type,label,seq,weight_pct) VALUES (?,?,?,?,?)");
    $pids = $db->query("SELECT id FROM projects")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($pids as $pid) {
        foreach ($ms as $m) $ins->execute([$pid,$m[0],$m[1],$m[2],$m[3]]);
    }
    echo "\n  seeded: " . $db->query("SELECT COUNT(*) FROM project_milestones")->fetchColumn() . " rows";
} else {
    echo "\n  already seeded: $cnt rows";
}

echo "\nDONE\n";
