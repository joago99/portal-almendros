<?php
$db = new PDO('sqlite:C:/Users/joaqu/portal-almendros/api/portal.db');
$sql = "SELECT e.*, u.name as autor FROM progress_events e JOIN app_users u ON u.id = e.created_by WHERE e.project_id = 1 ORDER BY e.event_date DESC";
$stmt = $db->prepare($sql);
$stmt->execute();
$rows = $stmt->fetchAll();
echo json_encode($rows);
