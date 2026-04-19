<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
session_start();

function j_out($data, $code = 200) {
    if (ob_get_length()) { ob_clean(); }
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
set_error_handler(function($sev, $msg, $file, $line) {
    j_out(['ok'=>false, 'error'=>"PHP: $msg in ".basename($file).":$line"], 500);
});
set_exception_handler(function($e) {
    j_out(['ok'=>false, 'error'=>'Exception: '.$e->getMessage()], 500);
});

require_once 'config.php';

$authed = false;
foreach ($_SESSION as $k => $v) {
    if (strpos($k, 'admin_logged_') === 0 && $v) { $authed = true; break; }
}
if (!$authed) { j_out(['ok'=>false,'error'=>'Not authed'], 401); }

$pdo = getDB();
$id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
if (!$id) j_out(['ok'=>false,'error'=>'Missing id']);

$stmt = $pdo->prepare("SELECT lead_id, filename FROM chiamate_registrate WHERE id = ?");
$stmt->execute([$id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) j_out(['ok'=>false,'error'=>'Not found']);

$path = __DIR__ . '/uploads/calls/' . $row['filename'];
if (file_exists($path)) { @unlink($path); }
$pdo->prepare("DELETE FROM chiamate_registrate WHERE id = ?")->execute([$id]);

try {
    $pdo->prepare("INSERT INTO log_attivita (lead_id,operatore,azione) VALUES (?,?,?)")
        ->execute([$row['lead_id'], '', 'Registrazione chiamata eliminata']);
} catch (Exception $e) {}

j_out(['ok'=>true]);
