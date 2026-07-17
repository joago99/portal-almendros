<?php
// Setup: crear base SQLite + usuario admin + migraciones
$dbPath = __DIR__ . '/api/portal.db';
$db = new PDO("sqlite:$dbPath", null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

// Crear tablas base
$db->exec("
CREATE TABLE IF NOT EXISTS clients (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    email TEXT UNIQUE,
    phone TEXT,
    rut TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS app_users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT UNIQUE NOT NULL,
    password_hash TEXT NOT NULL,
    role TEXT NOT NULL DEFAULT 'client' CHECK(role IN ('admin','staff','client')),
    name TEXT NOT NULL,
    client_id INTEGER REFERENCES clients(id),
    force_password_change INTEGER DEFAULT 0,
    active INTEGER DEFAULT 1,
    expires_at DATETIME,
    last_login_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS projects (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    client_id INTEGER NOT NULL REFERENCES clients(id),
    name TEXT NOT NULL,
    description TEXT,
    address TEXT,
    status TEXT NOT NULL DEFAULT 'activo' CHECK(status IN ('activo','pausado','finalizado')),
    start_date DATE,
    end_date_estimated DATE,
    end_date_real DATE,
    budget_clp REAL,
    budget_history TEXT,
    created_by INTEGER REFERENCES app_users(id),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS project_milestones (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    project_id INTEGER NOT NULL REFERENCES projects(id),
    milestone_type TEXT NOT NULL,
    label TEXT NOT NULL,
    seq INTEGER NOT NULL,
    weight_pct INTEGER NOT NULL DEFAULT 0,
    completed INTEGER DEFAULT 0,
    completed_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS payments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    project_id INTEGER NOT NULL REFERENCES projects(id),
    concept TEXT NOT NULL,
    amount_clp REAL NOT NULL,
    due_date DATE NOT NULL,
    status TEXT NOT NULL DEFAULT 'pendiente' CHECK(status IN ('pendiente','pagado','atrasado')),
    paid_at DATE,
    receipt_path TEXT,
    notes TEXT,
    created_by INTEGER NOT NULL REFERENCES app_users(id),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS progress_events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    project_id INTEGER NOT NULL REFERENCES projects(id),
    title TEXT NOT NULL,
    description TEXT,
    event_date DATE NOT NULL,
    percentage INTEGER DEFAULT 0,
    event_type TEXT DEFAULT 'daily_log',
    team_present TEXT,
    weather_conditions TEXT,
    materials_used TEXT,
    incidents TEXT,
    created_by INTEGER NOT NULL REFERENCES app_users(id),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS progress_photos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    event_id INTEGER NOT NULL REFERENCES progress_events(id),
    url TEXT NOT NULL,
    caption TEXT,
    uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS documents (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    project_id INTEGER NOT NULL REFERENCES projects(id),
    type TEXT NOT NULL CHECK(type IN ('presupuesto','avance','plano','legal','otro')),
    title TEXT NOT NULL,
    file_path TEXT NOT NULL,
    uploaded_by INTEGER NOT NULL REFERENCES app_users(id),
    uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS audit_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    actor_id INTEGER REFERENCES app_users(id),
    action TEXT NOT NULL,
    target_type TEXT,
    target_id INTEGER,
    details TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS password_resets (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL REFERENCES app_users(id),
    token TEXT NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    used INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
");

echo "✓ Tablas base listas\n";

// ─── Migraciones para DBs existentes ───
$migrations = [
    "ALTER TABLE app_users ADD COLUMN expires_at DATETIME",
    "ALTER TABLE projects ADD COLUMN address TEXT",
    "ALTER TABLE projects ADD COLUMN budget_history TEXT",
    "ALTER TABLE projects ADD COLUMN created_by INTEGER REFERENCES app_users(id)",
    "ALTER TABLE progress_events ADD COLUMN percentage INTEGER DEFAULT 0",
    "ALTER TABLE progress_events ADD COLUMN event_type TEXT DEFAULT 'daily_log'",
    "ALTER TABLE progress_events ADD COLUMN team_present TEXT",
    "ALTER TABLE progress_events ADD COLUMN weather_conditions TEXT",
    "ALTER TABLE progress_events ADD COLUMN materials_used TEXT",
    "ALTER TABLE progress_events ADD COLUMN incidents TEXT",
    "ALTER TABLE payments ADD COLUMN receipt_path TEXT",
    "ALTER TABLE payments ADD COLUMN notes TEXT",
];

foreach ($migrations as $sql) {
    try {
        $db->exec($sql);
    } catch (Exception $e) {
        // Ya existe, ignorar
    }
}
echo "✓ Migraciones aplicadas\n";

// ─── Admin inicial ───
$tempPass = bin2hex(random_bytes(8));
$hash = password_hash($tempPass, PASSWORD_DEFAULT);

$stmt = $db->prepare('SELECT id FROM app_users WHERE email = ?');
$stmt->execute(['admin@losalmendros.cl']);
if (!$stmt->fetch()) {
    $db->prepare('INSERT INTO app_users (email, password_hash, role, name, force_password_change) VALUES (?,?,?,?,?)')
        ->execute(['admin@losalmendros.cl', $hash, 'admin', 'Admin', 1]);
    echo "✓ Admin creado: admin@losalmendros.cl / temporal: $tempPass\n";
    echo "  IMPORTANTE: cambiar contraseña inmediatamente.\n";
} else {
    echo "✓ Admin ya existe\n";
}

// ─── Crear milestones default para proyectos existentes sin milestones ───
$defaultMilestones = [
    ['cimentacion', 'Cimentación', 1, 15],
    ['albanileria', 'Albañilería / OG', 2, 20],
    ['techumbre', 'Techumbre', 3, 15],
    ['terminaciones', 'Terminaciones', 4, 25],
    ['recepcion', 'Recepción Municipal', 5, 25],
];

$projs = $db->query("SELECT id FROM projects")->fetchAll();
foreach ($projs as $p) {
    foreach ($defaultMilestones as $ms) {
        $st = $db->prepare("SELECT id FROM project_milestones WHERE project_id = ? AND milestone_type = ?");
        $st->execute([$p['id'], $ms[0]]);
        if (!$st->fetch()) {
            $db->prepare("INSERT INTO project_milestones (project_id, milestone_type, label, seq, weight_pct) VALUES (?,?,?,?,?)")
                ->execute([$p['id'], $ms[0], $ms[1], $ms[2], $ms[3]]);
        }
    }
}

echo "✓ Base lista: $dbPath\n";

// Crear directorio uploads si no existe
if (!is_dir(__DIR__ . '/uploads')) {
    mkdir(__DIR__ . '/uploads', 0777, true);
    echo "✓ Directorio uploads creado\n";
}

if (!is_dir(__DIR__ . '/uploads/progress')) {
    mkdir(__DIR__ . '/uploads/progress', 0777, true);
    echo "✓ Directorio uploads/progress creado\n";
}
