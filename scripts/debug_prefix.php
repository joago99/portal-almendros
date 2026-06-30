<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');
session_start();
