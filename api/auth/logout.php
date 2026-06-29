<?php
require_once __DIR__ . '/../config.php';
session_start();
$_SESSION = [];
session_destroy();
echo json_encode(['ok' => true]);
