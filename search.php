<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
require_once 'config.php';
$q = preg_replace('/[^0-9]/', '', $_GET['q'] ?? '');
if (strlen($q) < 4) { echo '[]'; exit; }
$stmt = $pdo->prepare("SELECT id, nome, cognome, telefono, stato, created_at FROM candidature WHERE REPLACE(REPLACE(REPLACE(telefono,' ',''),'+',''),'-','') LIKE ? LIMIT 5");
$stmt->execute(['%'.$q.'%']);
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
