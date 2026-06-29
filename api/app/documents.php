<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../config.php';

session_start();
$userId = $_SESSION['user_id'] ?? null;
$userRole = $_SESSION['user_role'] ?? null;

if (!$userId || !in_array($userRole, ['admin', 'staff'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Acceso denegado']);
    exit;
}

$db = Database::get();
$action = $_GET['action'] ?? 'list';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'list') {
    $projectId = $_GET['project_id'] ?? null;
    if ($projectId) {
        $stmt = $db->prepare('SELECT d.*, u.name as uploader_name, p.name as project_name 
            FROM documents d JOIN app_users u ON u.id = d.uploaded_by JOIN projects p ON p.id = d.project_id
            WHERE d.project_id = ? ORDER BY d.uploaded_at DESC');
        $stmt->execute([$projectId]);
    } else {
        $stmt = $db->query('SELECT d.*, u.name as uploader_name, p.name as project_name 
            FROM documents d JOIN app_users u ON u.id = d.uploaded_by JOIN projects p ON p.id = d.project_id
            ORDER BY d.uploaded_at DESC LIMIT 20');
    }
    $docs = $stmt->fetchAll();
    
    header('Content-Type: text/html; charset=utf-8');
    echo '<h2>Documentos</h2>';
    echo '<button class="btn" onclick="showUploadForm()" style="margin-bottom:1rem">+ Subir documento</button>';
    
    if (count($docs)) {
        echo '<table><tr><th>Proyecto</th><th>Título</th><th>Tipo</th><th>Subido por</th><th>Fecha</th><th>Descargar</th></tr>';
        foreach ($docs as $d) {
            echo '<tr>
                <td>'.htmlspecialchars($d['project_name']).'</td>
                <td>'.htmlspecialchars($d['title']).'</td>
                <td><span class="status" style="background:#e2e8f0">'.$d['type'].'</span></td>
                <td>'.htmlspecialchars($d['uploader_name']).'</td>
                <td>'.$d['uploaded_at'].'</td>
                <td><a href="/uploads/'.$d['file_path'].'" target="_blank">📄 Ver</a></td>
            </tr>';
        }
        echo '</table>';
    } else {
        echo '<p style="color:#94a3b8">No hay documentos aún.</p>';
    }
    
    echo '<div id="uploadForm" style="display:none;margin-top:2rem">
        <h3>Subir documento</h3>
        <form id="docForm" class="crud-form" enctype="multipart/form-data">
            <label>Proyecto <select name="project_id" required>';
    $projects = $db->query('SELECT id, name FROM projects ORDER BY name')->fetchAll();
    foreach ($projects as $p) echo '<option value="'.$p['id'].'">'.htmlspecialchars($p['name']).'</option>';
    echo '</select></label>
            <label>Título <input name="title" required></label>
            <label>Tipo <select name="type">
                <option value="presupuesto">Presupuesto</option>
                <option value="avance">Avance</option>
                <option value="plano">Plano</option>
                <option value="legal">Legal</option>
                <option value="otro">Otro</option>
            </select></label>
            <label>Archivo <input name="file" type="file" required></label>
            <button type="submit">Subir</button>
        </form>
        <div id="docMessage"></div>
        <script>
        async function uploadDoc(data) {
            const res = await fetch("/api/documents.php?action=upload", {method:"POST",body:data});
            const result = await res.json();
            const msg = document.getElementById("docMessage");
            if(result.ok) { msg.innerHTML="✅ Documento subido"; setTimeout(()=>location.reload(),1000); }
            else msg.innerHTML="❌ "+result.error;
        }
        document.getElementById("docForm")?.addEventListener("submit", async (e) => {
            e.preventDefault();
            await uploadDoc(new FormData(e.target));
        });
        function showUploadForm() {
            document.getElementById("uploadForm").style.display = "block";
            document.getElementById("uploadForm").scrollIntoView({behavior:"smooth"});
        }
        </script>
    </div>';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'upload') {
    $projectId = $_POST['project_id'] ?? null;
    $title = $_POST['title'] ?? '';
    $type = $_POST['type'] ?? 'otro';
    $file = $_FILES['file'] ?? null;

    if (!$projectId || !$title || !$file || $file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['ok'=>false,'error'=>'Datos incompletos o error de subida']); exit;
    }

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $safeName = uniqid('doc_') . '.' . $ext;
    $uploadDir = __DIR__ . '/../../uploads/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    if (!move_uploaded_file($file['tmp_name'], $uploadDir . $safeName)) {
        echo json_encode(['ok'=>false,'error'=>'Error al guardar archivo']); exit;
    }

    $stmt = $db->prepare('INSERT INTO documents (project_id, type, title, file_path, uploaded_by) VALUES (?,?,?,?,?)');
    $stmt->execute([$projectId, $type, $title, $safeName, $userId]);
    echo json_encode(['ok'=>true, 'id'=>$db->lastInsertId()]);
    exit;
}

echo json_encode(['ok'=>false, 'error'=>'Acción no válida']);
