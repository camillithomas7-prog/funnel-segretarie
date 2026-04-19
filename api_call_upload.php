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

// Detect POST overflow (silent failure when post_max_size exceeded)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && empty($_FILES)
    && isset($_SERVER['CONTENT_LENGTH']) && (int)$_SERVER['CONTENT_LENGTH'] > 0) {
    j_out(['ok'=>false,'error'=>'POST troppo grande (CONTENT_LENGTH='.$_SERVER['CONTENT_LENGTH'].'). Alza post_max_size in php.ini/hPanel.'], 413);
}

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

if (!$lead_id) j_out(['ok'=>false,'error'=>'Missing lead_id']);
if (empty($_FILES['file'])) j_out(['ok'=>false,'error'=>'No file uploaded (forse post_max_size superato)']);

$f = $_FILES['file'];
$err_map = [
    UPLOAD_ERR_INI_SIZE => 'file oltre upload_max_filesize',
    UPLOAD_ERR_FORM_SIZE => 'file oltre MAX_FILE_SIZE (form)',
    UPLOAD_ERR_PARTIAL => 'upload interrotto',
    UPLOAD_ERR_NO_FILE => 'nessun file ricevuto',
    UPLOAD_ERR_NO_TMP_DIR => 'manca tmp dir',
    UPLOAD_ERR_CANT_WRITE => 'impossibile scrivere su disco',
    UPLOAD_ERR_EXTENSION => 'bloccato da estensione PHP',
];
if ($f['error'] !== UPLOAD_ERR_OK) {
    j_out(['ok'=>false,'error'=>'Upload errore: '.($err_map[$f['error']] ?? 'code '.$f['error'])]);
}
if ($f['size'] > 100 * 1024 * 1024) {
    j_out(['ok'=>false,'error'=>'File troppo grande (max 100MB)']);
}

$dir = __DIR__ . '/uploads/calls/';
if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
    j_out(['ok'=>false,'error'=>'mkdir failed: '.$dir]);
}
if (!is_writable($dir)) {
    j_out(['ok'=>false,'error'=>'dir non scrivibile: '.$dir]);
}

$ts = date('Ymd_His');
$rand = bin2hex(random_bytes(4));
$fname = "lead{$lead_id}_{$ts}_{$rand}.webm";
$path = $dir . $fname;

if (!move_uploaded_file($f['tmp_name'], $path)) {
    j_out(['ok'=>false,'error'=>'move_uploaded_file failed, tmp='.$f['tmp_name']]);
}

$size = filesize($path);
$stmt = $pdo->prepare("INSERT INTO chiamate_registrate (lead_id, operatore, filename, durata, size_bytes) VALUES (?, ?, ?, ?, ?)");
$stmt->execute([$lead_id, $operatore, $fname, $durata, $size]);
$id = $pdo->lastInsertId();

try {
    $pdo->prepare("INSERT INTO log_attivita (lead_id,operatore,azione) VALUES (?,?,?)")
        ->execute([$lead_id, $operatore, 'Chiamata registrata ('.gmdate('i:s', $durata).')']);
} catch (Exception $e) {}

j_out(['ok'=>true, 'id'=>$id, 'filename'=>$fname, 'size'=>$size]);
