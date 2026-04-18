<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
require_once 'config.php';

$totali = $pdo->query("SELECT COUNT(*) FROM candidature")->fetchColumn();
$nuovi = $pdo->query("SELECT COUNT(*) FROM candidature WHERE stato='nuova'")->fetchColumn();
$oggi = $pdo->query("SELECT COUNT(*) FROM candidature WHERE DATE(data_candidatura)=CURDATE()")->fetchColumn();
$bonifico = $pdo->query("SELECT COUNT(*) FROM candidature WHERE metodo_pagamento='bonifico'")->fetchColumn();
$contrassegno = $pdo->query("SELECT COUNT(*) FROM candidature WHERE metodo_pagamento='contrassegno'")->fetchColumn();

$whatsapp = $pdo->query("SELECT COUNT(*) FROM candidature WHERE note LIKE '%WhatsApp%'")->fetchColumn();

echo json_encode([
    'totali' => (int)$totali,
    'nuovi' => (int)$nuovi,
    'oggi' => (int)$oggi,
    'bonifico' => (int)$bonifico,
    'contrassegno' => (int)$contrassegno,
    'whatsapp' => (int)$whatsapp
]);
