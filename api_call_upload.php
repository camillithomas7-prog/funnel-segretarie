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
    $pdo->exec("CREATE TABLE IF NOT EXISTS chiamate_registrate (
        id INT AUTO_INCREMENT PRIMARY KEY,
        lead_id INT NOT NULL,
        operatore VARCHAR(50) DEFAULT '',
        filename VARCHAR(255) NOT NULL,
        durata INT DEFAULT 0,
        size_bytes BIGINT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_lead (lead_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $lead_id = (int)($_POST['lead_id'] ?? 0);
    $durata = (int)($_POST['durata'] ?? 0);
    $operatore = substr(trim($_POST['operatore'] ?? ''), 0, 50);

    if (!$lead_id || empty($_FILES['file'])) {
        echo json_encode(['ok'=>false,'error'=>'Missing data']); exit;
    }
    $f = $_FILES['file'];
    if ($f['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['ok'=>false,'error'=>'Upload error code '.$f['error']]); exit;
    }
    if ($f['size'] > 100 * 1024 * 1024) {
        echo json_encode(['ok'=>false,'error'=>'File troppo grande (max 100MB)']); exit;
    }

    $dir = __DIR__ . '/uploads/calls/';
    if (!is_dir($dir)) { mkdir($dir, 0755, true); }

    $ts = date('Ymd_His');
    $rand = bin2hex(random_bytes(4));
    $fname = "lead{$lead_id}_{$ts}_{$rand}.webm";
    $path = $dir . $fname;

    if (!move_uploaded_file($f['tmp_name'], $path)) {
        echo json_encode(['ok'=>false,'error'=>'Move failed']); exit;
    }

    $size = filesize($path);
    $stmt = $pdo->prepare("INSERT INTO chiamate_registrate (lead_id, operatore, filename, durata, size_bytes) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$lead_id, $operatore, $fname, $durata, $size]);
    $id = $pdo->lastInsertId();

    try {
        $pdo->prepare("INSERT INTO log_attivita (lead_id,operatore,azione) VALUES (?,?,?)")
            ->execute([$lead_id, $operatore, 'Chiamata registrata ('.gmdate('i:s', $durata).')']);
    } catch (Exception $e) {}

    echo json_encode(['ok'=>true, 'id'=>$id, 'filename'=>$fname]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
