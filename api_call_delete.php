<?php
session_start();
require_once 'config.php';
header('Content-Type: application/json');

$authed = false;
foreach ($_SESSION as $k => $v) {
    if (strpos($k, 'admin_logged_') === 0 && $v) { $authed = true; break; }
}
if (!$authed) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'Not authed']); exit; }

try {
    $pdo = getDB();
    $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
    if (!$id) { echo json_encode(['ok'=>false,'error'=>'Missing id']); exit; }

    $stmt = $pdo->prepare("SELECT lead_id, filename FROM chiamate_registrate WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) { echo json_encode(['ok'=>false,'error'=>'Not found']); exit; }

    $path = __DIR__ . '/uploads/calls/' . $row['filename'];
    if (file_exists($path)) { @unlink($path); }
    $pdo->prepare("DELETE FROM chiamate_registrate WHERE id = ?")->execute([$id]);

    try {
        $pdo->prepare("INSERT INTO log_attivita (lead_id,operatore,azione) VALUES (?,?,?)")
            ->execute([$row['lead_id'], '', 'Registrazione chiamata eliminata']);
    } catch (Exception $e) {}

    echo json_encode(['ok'=>true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
