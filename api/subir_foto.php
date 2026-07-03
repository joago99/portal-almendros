<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../config.php';
session_start();
$userId = $_SESSION['user_id'] ?? null;
$userRole = $_SESSION['user_role'] ?? null;
$staff = in_array($userRole, ['admin', 'staff']);
if (!$userId || !$staff) {
    echo json_encode(['ok' => false, 'error' => 'Acceso denegado']); exit;
}
$db = Database::get();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok'=>false,'error'=>'Método no permitido']); exit;
}

$eventId = (int)($_POST['event_id'] ?? 0);
if (!$eventId) { echo json_encode(['ok'=>false,'error'=>'Falta event_id']); exit; }

// Verify event belongs to a project the user can access
$ev = $db->prepare('SELECT project_id FROM progress_events WHERE id = ?');
$ev->execute([$eventId]); $e = $ev->fetch();
if (!$e) { echo json_encode(['ok'=>false,'error'=>'Evento no encontrado']); exit; }

$uploadDir = __DIR__ . '/../uploads/progress/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

$caption = trim($_POST['caption'] ?? '');
$files = $_FILES['fotos'] ?? null;
$uploaded = [];

if ($files && is_array($files['name'])) {
    for ($i = 0; $i < count($files['name']); $i++) {
        if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
        $origName = $files['name'][$i];
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','webp'])) continue;
        $size = $files['size'][$i];
        if ($size > 5 * 1024 * 1024) continue; // 5MB max

        $safeName = time() . '_' . $i . '_' . preg_replace('/[^a-zA-Z0-9_-]/', '', pathinfo($origName, PATHINFO_FILENAME)) . '.' . $ext;
        $dest = $uploadDir . $safeName;
        if (move_uploaded_file($files['tmp_name'][$i], $dest)) {
            $publicUrl = '/uploads/progress/' . $safeName;
            $db->prepare('INSERT INTO progress_photos (event_id, url, caption) VALUES (?,?,?)')
                ->execute([$eventId, $publicUrl, $caption ?: null]);
            $uploaded[] = ['url' => $publicUrl, 'caption' => $caption];
        }
    }
}

echo json_encode(['ok' => true, 'count' => count($uploaded), 'fotos' => $uploaded]);
exit;
